'use strict'

/**
 * Offline-tolerant checkout and the outbox sync loop.
 *
 * The rule this file exists to enforce: **the cashier is never blocked by the
 * network.** If the server is reachable the sale goes up in strict mode. If
 * it is not, the sale is written to the local database, local stock moves,
 * and the entry waits in the outbox. The server resolves those later in clamp
 * mode -- the customer already paid, so the money is kept and the shortfall
 * is flagged for an admin instead of vanishing.
 */

const { randomUUID } = require('node:crypto')

const OUTBOX_BATCH = 100
const AUTO_SYNC_INTERVAL_MS = 20000

/**
 * Mirror of App\Services\PosService::quote(). Keep these in lockstep: if the
 * client quotes a different total than the server, the receipt the customer
 * signed for stops matching the order the shop keeps.
 */
function calcItemDiscount (item) {
  const itemTotal = item.price * item.quantity
  const discount = Number(item.discount) || 0

  if (discount <= 0) return 0

  if (item.discount_type === 'percent') {
    return Math.round(itemTotal * Math.min(discount, 100) / 100)
  }

  return Math.min(discount, itemTotal)
}

function quote (cart, options, config) {
  const subtotal = cart.reduce((sum, i) => sum + i.price * i.quantity, 0)
  const itemDiscounts = cart.reduce((sum, i) => sum + calcItemDiscount(i), 0)
  const afterItemDiscount = subtotal - itemDiscounts

  const globalDiscount = Math.min(
    Number(options.discount) || 0,
    Math.max(0, afterItemDiscount)
  )

  const ppnEnabled = Boolean(config?.ppn_enabled)
  const ppnRate = Number(config?.ppn_percentage) || 0
  const ppnApplied = ppnEnabled && Boolean(options.ppn)
  const ppnBase = Math.max(0, afterItemDiscount - globalDiscount)
  const ppnAmount = ppnApplied ? Math.round((ppnBase * ppnRate) / 100) : 0

  return {
    subtotal,
    itemDiscounts,
    afterItemDiscount,
    globalDiscount,
    ppnEnabled,
    ppnApplied,
    ppnRate,
    ppnBase,
    ppnAmount,
    total: Math.max(0, ppnBase + ppnAmount)
  }
}

/** Stock movement a sale implies, for the optimistic local update. */
function stockDeltas (cart) {
  const deltas = {}

  for (const item of cart) {
    deltas[item.product_id] = (deltas[item.product_id] || 0) - item.quantity
  }

  return deltas
}

class PosSync {
  constructor (store, api, { onChange = () => {} } = {}) {
    this.store = store
    this.api = api
    this.onChange = onChange
    this.online = true
    this.syncing = false
    this.lastError = null
    this.lastSyncAt = null
    this.timer = null
  }

  /* ---------------------------------------------------------------- *
   * connectivity
   * ---------------------------------------------------------------- */

  async checkOnline () {
    try {
      await this.api.ping()
      this.online = true
      this.lastError = null
    } catch (error) {
      this.online = false
      this.lastError = error.message
    }

    this.onChange()
    return this.online
  }

  /* ---------------------------------------------------------------- *
   * checkout
   * ---------------------------------------------------------------- */

  /**
   * @param {Array} cart    lines of {product_id, name, sku, price, quantity, discount, discount_type}
   * @param {object} options {discount, ppn, payment_method, order_type, customer_name, amount_paid}
   * @returns {Promise<{mode: 'online'|'offline'|'rejected', quote: object, result?: object, message?: string}>}
   */
  async checkout (cart, options) {
    const config = this.store.getJson('pos_config', {})
    const q = quote(cart, options, config)
    const amountPaid = Number(options.amount_paid) || q.total

    const payload = {
      client_ref: randomUUID(),
      paid_at: new Date().toISOString(),
      items: cart.map((item) => ({
        product_id: item.product_id,
        name: item.name,
        price: item.price,
        quantity: item.quantity,
        discount: Number(item.discount) || 0,
        discount_type: item.discount_type === 'percent' ? 'percent' : 'nominal'
      })),
      customer_name: options.customer_name || null,
      amount_paid: amountPaid,
      discount: Number(options.discount) || 0,
      ppn: Boolean(options.ppn),
      payment_method: options.payment_method || 'cash',
      order_type: options.order_type || 'dine_in'
    }

    const deltas = stockDeltas(cart)

    if (this.online) {
      try {
        const result = await this.api.checkout(payload)

        this.store.recordOnlineOrder({ payload, quote: q, result })
        this.store.applyLocalStockDeltas(deltas)

        this.onChange()

        return {
          mode: 'online',
          quote: { ...q, change: Math.max(0, amountPaid - q.total) },
          result
        }
      } catch (error) {
        // A dead network is not a failed sale. Anything else is.
        if (error.offline) {
          this.online = false
          this.lastError = error.message
        } else if (error.status === 422) {
          this.store.recordRejectedOrder({ payload, quote: q, message: error.message })

          this.onChange()

          return { mode: 'rejected', quote: q, message: error.message }
        } else {
          throw error
        }
      }
    }

    const change = Math.max(0, amountPaid - q.total)
    const clientRef = this.store.createPendingOrder({
      payload,
      total: q.total,
      amountPaid,
      changeDue: change,
      localStockDeltas: deltas
    })

    this.onChange()

    return { mode: 'offline', quote: { ...q, change }, client_ref: clientRef }
  }

  /* ---------------------------------------------------------------- *
   * outbox
   * ---------------------------------------------------------------- */

  /**
   * Push queued sales. The server dedupes on (client_id, client_ref), so a
   * retry after a timeout is a no-op rather than a second order -- which is
   * what makes it safe to just try again on the next tick.
   */
  async flush () {
    if (this.syncing) return { skipped: true }
    this.syncing = true

    try {
      const clientId = this.store.getMeta('client_id')
      const summary = { sent: 0, synced: 0, duplicate: 0, rejected: 0, conflicts: 0 }

      // A previous attempt that died mid-flight left rows in `sending`. They
      // are safe to resend because of the unique index server side.
      this.store.releaseOutboxClaims()

      const batch = this.store.claimOutboxBatch(OUTBOX_BATCH)

      if (!batch.length) {
        this.online = true
        this.lastError = null
        return summary
      }

      summary.sent = batch.length

      const result = await this.api.sync(clientId, batch.map((b) => b.payload))

      for (const entry of result.results || []) {
        this.store.applySyncResult(entry)

        if (entry.status === 'synced') summary.synced++
        else if (entry.status === 'duplicate') summary.duplicate++
        else if (entry.status === 'rejected') summary.rejected++

        if (entry.stock_conflict) summary.conflicts++
      }

      this.online = true
      this.lastError = null
      this.lastSyncAt = new Date().toISOString()

      // Local stock was decremented optimistically at checkout. A clamped
      // order means the server disagreed about how much was on hand, so pull
      // the catalog again and let the delta put local stock back in line.
      if (summary.conflicts > 0) {
        try {
          await this.api.catalog({ since: this.store.getMeta('catalog_cursor') })
        } catch {
          // Not fatal: the next catalog pull will fix it.
        }
      }

      this.onChange()

      return summary
    } catch (error) {
      // Only a transport failure means "offline". A 401 means the token
      // expired and the cashier has to sign in again -- calling the shop
      // offline would send them down the wrong path. Either way the batch
      // stays in the outbox, so no sale is dropped.
      if (error.offline) {
        this.online = false
      }

      this.lastError = error.message

      this.onChange()

      return {
        error: error.message,
        offline: Boolean(error.offline),
        needsLogin: error.status === 401
      }
    } finally {
      this.syncing = false
    }
  }

  /** Ask the server what it actually holds for this client. */
  async reconcile () {
    const clientId = this.store.getMeta('client_id')
    const result = await this.api.syncStatus(clientId)

    for (const order of result.orders || []) {
      if (!order.client_ref) continue

      this.store.applySyncResult({
        client_ref: order.client_ref,
        status: order.stock_conflict ? 'synced' : 'synced',
        order_id: order.order_id,
        order_number: order.order_number,
        stock_conflict: order.stock_conflict,
        conflict_reason: order.conflict_reason,
        message: order.price_drift || null
      })
    }

    this.onChange()

    return result
  }

  /* ---------------------------------------------------------------- *
   * boot + background loop
   * ---------------------------------------------------------------- */

  async bootstrap () {
    await this.checkOnline()

    if (this.online) {
      try {
        await this.api.config()
        await this.api.catalog({ since: this.store.getMeta('catalog_cursor') })
        await this.flush()
      } catch (error) {
        this.lastError = error.message
      }
    }

    this.timer = setInterval(() => {
      if (this.online) this.flush().catch(() => {})
      else this.checkOnline().then((up) => (up ? this.flush() : null)).catch(() => {})
    }, AUTO_SYNC_INTERVAL_MS)

    this.onChange()
  }

  stop () {
    if (this.timer) clearInterval(this.timer)
    this.timer = null
  }

  state () {
    return {
      online: this.online,
      syncing: this.syncing,
      lastError: this.lastError,
      lastSyncAt: this.lastSyncAt,
      stats: this.store.stats(),
      // Cached so the UI can still show the right tax rate with no network.
      posConfig: this.store.getJson('pos_config', {}),
      user: this.store.getJson('user', null),
      apiUrl: this.store.getMeta('api_url'),
      clientId: this.store.getMeta('client_id')
    }
  }
}

module.exports = { PosSync, quote, calcItemDiscount, stockDeltas }
