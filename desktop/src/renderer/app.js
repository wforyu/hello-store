'use strict'

/**
 * Hello Store POS renderer.
 *
 * Two rules shape this file:
 *  - no inline event handlers and no remote assets, because the CSP in
 *    index.html forbids both and the app must work with the network down;
 *  - the money shown while shopping is computed here for responsiveness, but
 *    the receipt always renders the quote that came back from the main
 *    process, which mirrors the server. The customer's receipt is therefore
 *    never a local guess.
 */

const $ = (id) => document.getElementById(id)

const state = {
  app: null,
  products: [],
  categories: [],
  cart: [],
  search: '',
  categoryId: null,
  options: {
    ppn: false,
    discount: 0,
    paid: 0,
    payment_method: 'cash',
    order_type: 'dine_in',
    customer_name: ''
  }
}

/* ------------------------------------------------------------------ *
 * formatting
 * ------------------------------------------------------------------ */

const rupiah = new Intl.NumberFormat('id-ID', {
  style: 'currency',
  currency: 'IDR',
  maximumFractionDigits: 0
})

const plain = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 })

const money = (n) => rupiah.format(Number(n) || 0)

/** "3.250.000" -> 3250000. `(int) "3.250.000"` is 3 in PHP; JS Number is fine. */
const parseNum = (value) => {
  const digits = String(value ?? '').replace(/[^\d]/g, '')
  return digits ? Number(digits) : 0
}

const esc = (value) =>
  String(value ?? '').replace(/[&<>"']/g, (c) =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])
  )

const clockTime = (iso) => {
  if (!iso) return '—'
  const d = new Date(iso)
  return Number.isNaN(d.getTime()) ? '—' : d.toLocaleString('id-ID')
}

/** Live thousand separators while typing in a rupiah field. */
function bindAmountInput (id, onChange) {
  const el = $(id)

  el.addEventListener('input', () => {
    const digits = el.value.replace(/[^\d]/g, '').replace(/^0+(?=\d)/, '')
    el.value = digits ? plain.format(Number(digits)) : ''
    onChange(parseNum(el.value))
  })

  el.addEventListener('focus', () => el.select())
}

/* ------------------------------------------------------------------ *
 * quote (mirror of App\Services\PosService::quote)
 * ------------------------------------------------------------------ */

function calcItemDiscount (item) {
  const itemTotal = item.price * item.quantity
  const discount = Number(item.discount) || 0

  if (discount <= 0) return 0
  if (item.discount_type === 'percent') return Math.round(itemTotal * Math.min(discount, 100) / 100)

  return Math.min(discount, itemTotal)
}

function quote () {
  return computeTotals(state.cart, state.options, state.app?.posConfig)
}

/**
 * The money, for any basket plus any options -- the cart on screen right now
 * or a basket replayed from local storage for a reprint. Kept separate from
 * `quote()` so a reprinted receipt cannot drift from the one handed to the
 * customer at the time of sale.
 */
function computeTotals (items, options, config = {}) {
  const subtotal = items.reduce((sum, i) => sum + i.price * i.quantity, 0)
  const itemDiscounts = items.reduce((sum, i) => sum + calcItemDiscount(i), 0)
  const afterItemDiscount = subtotal - itemDiscounts
  const globalDiscount = Math.min(Number(options.discount) || 0, Math.max(0, afterItemDiscount))

  const ppnEnabled = Boolean(config.ppn_enabled)
  const ppnRate = Number(config.ppn_percentage) || 0
  const ppnApplied = ppnEnabled && Boolean(options.ppn)
  const ppnBase = Math.max(0, afterItemDiscount - globalDiscount)
  const ppnAmount = ppnApplied ? Math.round((ppnBase * ppnRate) / 100) : 0
  const total = Math.max(0, ppnBase + ppnAmount)

  const paid = options.paid

  return {
    subtotal, itemDiscounts, globalDiscount, ppnEnabled, ppnApplied, ppnRate, ppnBase, ppnAmount, total,
    paid: paid || total,
    change: Math.max(0, (paid || total) - total),
    short: Boolean(paid) && paid < total
  }
}

/* ------------------------------------------------------------------ *
 * cart
 * ------------------------------------------------------------------ */

const stockOf = (id) => {
  const p = state.products.find((x) => x.id === id)
  return p ? p.stock : 0
}

function addToCart (id) {
  const product = state.products.find((p) => p.id === id)
  if (!product) return

  const line = state.cart.find((i) => i.product_id === id)

  if (line) {
    if (line.quantity >= product.stock) {
      flashCard(id)
      return
    }
    line.quantity += 1
  } else {
    state.cart.push({
      product_id: product.id,
      name: product.name,
      sku: product.sku,
      price: product.price,
      quantity: 1,
      discount: 0,
      discount_type: 'nominal'
    })
  }

  render()
}

function setQuantity (id, quantity) {
  const line = state.cart.find((i) => i.product_id === id)
  if (!line) return

  const cap = stockOf(id)
  const next = Math.max(0, Math.min(quantity, cap))

  if (next === 0) {
    state.cart = state.cart.filter((i) => i.product_id !== id)
  } else {
    line.quantity = next
  }

  render()
}

function removeLine (id) {
  state.cart = state.cart.filter((i) => i.product_id !== id)
  render()
}

function setLineDiscount (id, discount, type) {
  const line = state.cart.find((i) => i.product_id === id)
  if (!line) return

  line.discount = Math.max(0, discount)
  line.discount_type = type === 'percent' ? 'percent' : 'nominal'
  render()
}

function flashCard (id) {
  const el = document.querySelector(`[data-product="${id}"]`)
  if (!el) return

  el.animate(
    [{ transform: 'translateX(0)' }, { transform: 'translateX(-5px)' },
      { transform: 'translateX(5px)' }, { transform: 'translateX(0)' }],
    { duration: 180 }
  )
}

/* ------------------------------------------------------------------ *
 * render
 * ------------------------------------------------------------------ */

function renderHeader () {
  const app = state.app
  const badge = $('conn-badge')

  if (!app) return

  if (app.syncing) {
    badge.className = 'badge busy'
    badge.textContent = 'Sinkron…'
  } else if (app.online) {
    badge.className = 'badge up'
    badge.textContent = 'Online'
  } else {
    badge.className = 'badge down'
    badge.textContent = 'Offline'
  }

  const s = app.stats
  $('topbar-stats').innerHTML = [
    `Hari ini <b>${s.todayOrders}</b>`,
    `Omzet <b>${money(s.todayRevenue)}</b>`,
    `Antrean <b>${s.pendingSync}</b>`,
    `Konflik <b>${s.conflicts}</b>`,
    `Produk <b>${s.products}</b>`
  ].join('')

  $('cashier-name').textContent = app.user?.name || '—'

  const banner = $('offline-banner')
  const showBanner = !app.online || s.pendingSync > 0

  banner.hidden = !showBanner
  document.querySelector('.workspace').classList.toggle('has-banner', showBanner)

  if (showBanner) {
    banner.textContent = !app.online
      ? `Mode offline — transaksi tetap dilayani dan akan dikirim otomatis.${app.lastError ? ' (' + app.lastError + ')' : ''}`
      : `${s.pendingSync} transaksi menunggu dikirim ke server.`
  }
}

function renderCategories () {
  const all = `<button data-cat="" class="${state.categoryId ? '' : 'active'}">Semua <small>${state.products.length}</small></button>`

  const rest = state.categories
    .map((c) => `<button data-cat="${c.id}" class="${state.categoryId === c.id ? 'active' : ''}">${esc(c.name)} <small>${c.count}</small></button>`)
    .join('')

  $('categories').innerHTML = all + rest
}

function renderGrid () {
  const q = state.search.trim().toLowerCase()

  const items = state.products.filter((p) => {
    if (state.categoryId && p.category_id !== state.categoryId) return false
    if (!q) return true
    return (p.name || '').toLowerCase().includes(q) || (p.sku || '').toLowerCase().includes(q)
  })

  $('grid-empty').hidden = items.length > 0

  $('grid').innerHTML = items
    .map((p) => {
      const stock = p.stock
      const cls = stock <= 0 ? 'out' : stock <= 5 ? 'low' : 'ok'
      const label = stock <= 0 ? 'Habis' : `Stok ${stock}`

      return `<button class="card ${stock <= 0 ? 'dead' : ''}" data-product="${p.id}" ${stock <= 0 ? 'disabled' : ''}>
        <span class="card-name">${esc(p.name)}</span>
        <span class="card-sku">${esc(p.sku || '—')}</span>
        <span class="card-price">${money(p.price)}</span>
        <span class="card-stock ${cls}">${label}</span>
      </button>`
    })
    .join('')
}

function renderCart () {
  const q = quote()

  $('cart-empty').hidden = state.cart.length > 0

  $('cart-items').innerHTML = state.cart
    .map((line) => {
      const cap = stockOf(line.product_id)
      const discountCell = line.quantity === 1
        ? `<div class="line-discount">
             <input data-discount="${line.product_id}" type="text" inputmode="numeric"
                    placeholder="diskon" value="${line.discount ? plain.format(line.discount) : ''}">
             <select data-discount-type="${line.product_id}">
               <option value="nominal" ${line.discount_type === 'nominal' ? 'selected' : ''}>Rp</option>
               <option value="percent" ${line.discount_type === 'percent' ? 'selected' : ''}>%</option>
             </select>
           </div>`
        : ''

      return `<div class="line" data-line="${line.product_id}">
        <div class="line-top">
          <span class="line-name">${esc(line.name)}</span>
          <span class="line-price">${money(line.price * line.quantity)}</span>
        </div>
        <div class="line-bot">
          <button class="stepper" data-step="-1" data-id="${line.product_id}">−</button>
          <span class="qty">${line.quantity}</span>
          <button class="stepper" data-step="1" data-id="${line.product_id}" ${line.quantity >= cap ? 'disabled' : ''}>+</button>
          <button class="btn-link" data-remove="${line.product_id}">hapus</button>
          ${discountCell}
        </div>
      </div>`
    })
    .join('')

  $('sum-subtotal').textContent = money(q.subtotal)
  $('sum-item-discount').textContent = money(q.itemDiscounts)
  $('sum-global-discount').textContent = money(q.globalDiscount)
  $('sum-ppn').textContent = money(q.ppnAmount)
  $('sum-total').textContent = money(q.total)
  $('change-due').textContent = money(q.change)

  const config = state.app?.posConfig || {}
  const rate = q.ppnRate || config.ppn_percentage || 0

  $('ppn-label').textContent = `PPN ${rate}%`
  $('ppn-row-label').textContent = `PPN ${rate}%`

  $('row-ppn').hidden = !q.ppnApplied
  $('opt-ppn').disabled = !q.ppnEnabled

  $('opt-ppn').closest('.check').title = q.ppnEnabled
    ? 'PPN aktif di pengaturan toko'
    : 'PPN dinonaktifkan di pengaturan toko'

  $('cash-warning').hidden = !(q.short && state.options.payment_method === 'cash')
  $('btn-checkout').disabled = state.cart.length === 0 || (state.options.payment_method === 'cash' && q.short)
}

function render () {
  renderHeader()
  renderCategories()
  renderGrid()
  renderCart()
}

/* ------------------------------------------------------------------ *
 * data
 * ------------------------------------------------------------------ */

async function loadCatalog () {
  const [products, categories] = await Promise.all([window.pos.products({}), window.pos.categories()])
  state.products = products
  state.categories = categories
  render()
}

async function refreshState () {
  state.app = { ...(await window.pos.state()), version: await window.pos.version() }
  render()
}

function openApp () {
  $('login').hidden = true
  $('app').hidden = false
  render()
}

/* ------------------------------------------------------------------ *
 * printing
 * ------------------------------------------------------------------ */

const paperMm = () => state.app?.printer?.paper || 58

/** The cashier's print layout, as last saved. */
const printLayout = () => state.app?.printer?.layout || { margin: 3, topOffset: 0, fontScale: 1 }

const posBuildReceipt = (args) => window.posReceipt.buildReceipt(args)

/* ------------------------------------------------------------------ *
 * print preview
 * ------------------------------------------------------------------ */

/**
 * A receipt to look at when the cashier has not sold anything yet: the last
 * sale if there is one, otherwise a fixed sample with a discount and PPN so
 * every line of the layout is exercised.
 */
function sampleSale () {
  const config = state.app?.posConfig || {}

  if (lastSale) return { ...lastSale, config: lastSale.config || config }

  const items = [
    { name: 'Kopi Susu Botol', price: 18000, quantity: 2, discount: 0, discount_type: 'fixed' },
    { name: 'Roti Cokelat', price: 15000, quantity: 1, discount: 10, discount_type: 'percent' },
    { name: 'Air Mineral 600 ml', price: 5000, quantity: 3, discount: 0, discount_type: 'fixed' }
  ]

  return {
    items,
    totals: computeTotals(items, { discount: 3000, ppn: Boolean(config.ppn_enabled) }, config),
    config,
    order: {
      number: 'CONTOH-001',
      at: new Date().toISOString(),
      type: 'dine_in',
      customer: 'Pelanggan Contoh',
      cashier: state.app?.user?.name,
      payment: 'cash'
    }
  }
}

/**
 * Draw the receipt exactly as it will print, at real millimetre width, scaled
 * to fit the panel. Built by the same function the print page uses, so if the
 * right edge is going to be clipped the cashier sees it here first.
 *
 * Every value comes from the form, not from saved state: a preview of what is
 * already stored is a preview of yesterday's settings.
 */
function renderPreview () {
  const paper = $('preview-paper')

  try {
    const stage = $('preview-stage')
    const receipt = posBuildReceipt({
      ...sampleSale(),
      paper: paperFromForm(),
      layout: previewLayoutFromForm(),
      footer: 'Ini struk contoh, bukan transaksi.'
    })

    // True millimetres, so the wrapping is the wrapping the printer gets. A
    // preview narrowed to fit the panel would wrap differently and quote a
    // different paper length than the one that comes out.
    const realWidthPx = receipt.paper * (96 / 25.4)

    paper.style.width = `${realWidthPx}px`
    // receiptCss() carries the margin and font rules; the print page injects the
    // same string into its own <style>.
    paper.innerHTML =
      `<style>${window.posReceipt.receiptCss(receipt.layout, receipt.paper)}</style>` +
      window.posReceipt.renderReceiptHtml(receipt)

    // Scale down to fit the panel, and give the box back the height the scale
    // takes away so the scrollbar tracks the printed slip, not the unscaled one.
    const scale = Math.min(1, (stage.clientWidth - 24) / realWidthPx)

    paper.style.transform = `scale(${scale})`
    paper.style.marginBottom = `${paper.scrollHeight * (scale - 1)}px`

    // The length the printer will be told, from the same formula the print page
    // uses -- so the cashier can judge the paper cost before burning a roll.
    const { heightMm } = window.posReceipt.pageSizeMm(receipt, paper.scrollHeight)

    $('preview-size').textContent = `${receipt.paper} x ${heightMm.toFixed(0)} mm`
  } catch (error) {
    // A preview that silently goes blank is the one thing worse than no
    // preview: the cashier trusts it and finds out at the printer.
    paper.innerHTML = ''
    $('preview-size').textContent = 'gagal'
    toast(`Pratinjau gagal: ${error.message}`)
  }
}

const paperFromForm = () => (Number($('set-paper').value) === 80 ? 80 : 58)

const pageModeFromForm = () => ($('set-paper-mode').value === 'driver' ? 'driver' : 'auto')

const previewLayoutFromForm = () => ({
  margin: Number($('set-margin').value) || 0,
  topOffset: Number($('set-top-offset').value) || 0,
  fontScale: Number($('set-font').value) || 1
})

let toastTimer = null

/** Short-lived message in the middle of the screen. */
function toast (message, bad = false) {
  const el = $('toast')

  el.textContent = message
  el.className = bad ? 'toast bad' : 'toast'
  el.hidden = false

  clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    el.hidden = true
  }, bad ? 7000 : 3500)
}

/** The basket + totals the most recent sale used, kept so a reprint or a
 *  F9 from the menu prints that sale rather than the now-empty cart. */
let lastSale = null

function rememberSale ({ items, totals, order, config }) {
  lastSale = { items, totals, order, config }
}

/**
 * Print a receipt, reporting what the printer actually did. Silence on success
 * is wrong here: a cashier handing over a blank slip because the paper ran out
 * is the exact failure this feature exists to prevent, so the paper name and
 * the failure reason both get shown.
 */
async function printReceipt (receipt, label = 'Struk') {
  // The calibration sheet has no sale lines on it; refusing it here on an
  // "is it empty" rule would throw away the one tool that measures the printer.
  const calibration = receipt && receipt.kind === 'calibration'

  if (!receipt || (!calibration && !receipt.items.length)) {
    toast('Tidak ada struk untuk dicetak.', true)
    return { printed: false }
  }

  const btn = $('btn-print-receipt')
  const buttonLabel = btn ? btn.textContent : null

  if (btn) {
    btn.disabled = true
    btn.textContent = 'Mencetak…'
  }

  try {
    const result = await window.pos.printReceipt(receipt)

    if (result.printed) {
      toast(`${label} dicetak ke ${result.deviceName}.`)
    } else {
      toast(`Gagal mencetak: ${result.reason}`, true)
    }

    return result
  } catch (error) {
    toast(`Gagal mencetak: ${error.message}`, true)
    return { printed: false, reason: error.message }
  } finally {
    if (btn) {
      btn.disabled = false
      btn.textContent = buttonLabel
    }
  }
}

const printLastSale = () => {
  if (!lastSale) {
    toast('Belum ada transaksi untuk dicetak.', true)
    return
  }

  printReceipt(posBuildReceipt({ ...lastSale, paper: paperMm(), layout: printLayout() }))
}

/** Reprint a sale out of the history drawer. */
async function reprintOrder (clientRef) {
  const row = await window.pos.order(clientRef)

  if (!row || !row.payload || !Array.isArray(row.payload.items) || !row.payload.items.length) {
    toast('Rincian item transaksi ini tidak tersimpan di perangkat.', true)
    return
  }

  const config = state.app?.posConfig || {}
  const totals = computeTotals(
    row.payload.items,
    { discount: row.payload.discount, ppn: Boolean(row.ppn_applied) },
    config
  )

  await printReceipt(
    posBuildReceipt({
      items: row.payload.items,
      totals,
      config,
      paper: paperMm(),
      layout: printLayout(),
      order: {
        number: row.order_number || row.client_ref,
        at: row.created_at,
        type: row.order_type,
        customer: row.customer_name,
        cashier: state.app?.user?.name,
        payment: row.payment_method,
        pending: row.status === 'pending_sync'
      }
    })
  )
}

/* ------------------------------------------------------------------ *
 * actions
 * ------------------------------------------------------------------ */

async function checkout () {
  if (!state.cart.length) return

  const q = quote()
  const btn = $('btn-checkout')

  btn.disabled = true
  btn.textContent = 'Memproses…'

  try {
    const result = await window.pos.checkout(state.cart, { ...state.options })
    const server = result.quote || q

    const isPending = result.mode === 'offline'
    const isRejected = result.mode === 'rejected'

    $('receipt-title').textContent = isRejected
      ? 'Transaksi Ditolak'
      : isPending
        ? 'Tersimpan Offline'
        : 'Transaksi Berhasil'

    const mode = $('receipt-mode')
    mode.className = `receipt-mode ${isRejected ? 'bad' : isPending ? 'pending' : 'ok'}`

    mode.textContent = isRejected
      ? result.message
      : isPending
        ? 'Belum terkirim ke server. Akan dikirim otomatis saat koneksi kembali.'
        : `Nomor pesanan ${result.result?.order_number ?? '—'}`

    $('r-subtotal').textContent = money(server.subtotal)
    $('r-ppn-row').hidden = !server.ppnApplied
    $('r-ppn-label').textContent = `PPN ${server.ppnRate}%`
    $('r-ppn').textContent = money(server.ppnAmount)
    $('r-total').textContent = money(server.total)
    $('r-paid').textContent = money(server.paid ?? q.paid)
    $('r-change').textContent = money(server.change ?? q.change)

    const notes = []
    if (isPending) notes.push('Tersimpan di perangkat ini dengan aman.')
    if (!isRejected && result.result?.stock_conflict) {
      notes.push('Stok kurang dari jumlah yang dimasukkan. Pesanan tetap diproses dan selisihnya ditandai untuk(admin.')
    }
    $('receipt-note').textContent = notes.join(' ')

    // Snapshot the basket before it is cleared: this is the only place the
    // line items and the totals as quoted still exist.
    if (!isRejected) {
      rememberSale({
        items: state.cart.map((line) => ({ ...line })),
        totals: server,
        config: state.app?.posConfig || {},
        order: {
          number: result.result?.order_number || result.client_ref || 'MENUNGGU SYNC',
          at: new Date().toISOString(),
          type: state.options.order_type,
          customer: state.options.customer_name,
          cashier: state.app?.user?.name,
          payment: state.options.payment_method,
          pending: isPending
        }
      })
    }

    $('receipt').hidden = false

    state.cart = []
    state.options.paid = 0
    state.options.discount = 0
    $('opt-paid').value = ''
    $('opt-discount').value = ''

    await loadCatalog()
    await refreshState()
  } catch (error) {
    $('receipt-title').textContent = 'Gagal Memproses'
    $('receipt-mode').className = 'receipt-mode bad'
    $('receipt-mode').textContent = error.message
    $('receipt-note').textContent = ''
    $('receipt').hidden = false
  } finally {
    btn.textContent = 'Bayar'
    renderCart()
  }
}

async function syncNow () {
  const btn = $('btn-sync')
  btn.disabled = true

  try {
    await window.pos.syncNow()
  } finally {
    btn.disabled = false
  }
}

const STATUS_TEXT = {
  synced: 'Tersinkron',
  pending_sync: 'Menunggu sync',
  rejected: 'Ditolak'
}

async function openOrders () {
  const orders = await window.pos.orders(80)

  $('orders-list').innerHTML = orders.length
    ? orders
        .map((o) => {
          const tags = [`<span class="tag ${o.status}">${STATUS_TEXT[o.status] || o.status}</span>`]
          if (o.stock_conflict) tags.push('<span class="tag conflict">konflik stok</span>')

          const note = o.conflict_reason || o.server_message || ''

          return `<div class="order">
            <div class="order-top">
              <span class="order-total">${money(o.total)}</span>
              ${tags.join(' ')}
            </div>
            <div class="order-meta">
              ${o.order_number ? esc(o.order_number) + ' · ' : ''}${esc(o.payment_method || '')} ·
              ${esc(o.customer_name || 'Umum')} · ${clockTime(o.created_at)}
            </div>
            ${note ? `<div class="order-note">${esc(note)}</div>` : ''}
            <div class="order-actions">
              <button type="button" data-reprint="${esc(o.client_ref)}">Cetak ulang struk</button>
            </div>
          </div>`
        })
        .join('')
    : '<p class="empty">Belum ada transaksi lokal.</p>'

  $('orders').hidden = false
}

function openSettings () {
  $('set-url').value = state.app?.apiUrl || ''
  $('set-client').value = state.app?.clientId || ''
  $('set-paper').value = String(paperMm())
  $('set-paper-mode').value = state.app?.printer?.pageMode === 'driver' ? 'driver' : 'auto'
  const layout = printLayout()
  $('set-margin').value = layout.margin
  $('set-top-offset').value = layout.topOffset
  $('set-font').value = String(layout.fontScale)

  $('settings').hidden = false

  // The printer list first: it comes from the OS and can be slow, and a
  // preview that throws must not take the printer dropdown down with it.
  loadPrinters()
  renderPreview()
}

/**
 * Printer list comes from the OS, so it can change while the app is open (a
 * USB thermal printer plugged in mid-shift is normal). It is only re-read when
 * the settings dialog opens or the cashier asks for it, never on a timer.
 */
async function loadPrinters () {
  const select = $('set-printer')
  const hint = $('printer-hint')
  const chosen = state.app?.printer?.name || ''

  select.disabled = true
  select.innerHTML = '<option value="">Memuat…</option>'

  try {
    const printers = await window.pos.printers()

    select.disabled = false

    if (!printers.length) {
      select.innerHTML = '<option value="">Tidak ada printer terdaftar</option>'
      hint.textContent =
        'Windows tidak menemukan printer. Untuk struk thermal, pasang driver printer di sini dulu ' +
        'lalu pilih printernya. Tanpa pilihan printer, setiap pencetakan memakai dialog cetak Windows.'
      state.printers = []
      return
    }

    state.printers = printers

    const options = [
      '<option value="">Dialog cetak Windows (pilih tiap kali)</option>',
      ...printers.map((p) => {
        const label = `${p.displayName}${p.isDefault ? ' (bawaan)' : ''}`

        return `<option value="${esc(p.name)}">${esc(label)}</option>`
      })
    ]

    select.innerHTML = options.join('')

    // A remembered printer that is no longer installed must not silently stay
    // selected: printing would go nowhere while the UI claims it is configured.
    const stillThere = printers.some((p) => p.name === chosen)

    if (chosen && !stillThere) {
      select.value = ''
      hint.textContent = `Printer "${chosen}" tidak lagi terdaftar di komputer ini. Pilih yang lain atau pasang driver-nya.`
    } else {
      select.value = chosen
      hint.textContent = chosen
        ? `Struk akan dicetak langsung ke ${chosen} tanpa menampilkan dialog.`
        : 'Belum ada printer pilihan. Setiap pencetakan akan membuka dialog cetak Windows.'
    }
  } catch (error) {
    select.disabled = false
    select.innerHTML = '<option value="">Gagal memuat daftar printer</option>'
    hint.textContent = error.message
  }

  describePrinter()
}

/**
 * What the driver's own paper setting says, next to what we are asking for.
 *
 * A driver left on A4 or Letter will letterbox whatever page it is handed, and
 * that -- not the receipt's margins -- is what pushes a blank strip in front of
 * the slip and eats the right-hand edge. So the disagreement is worth stating
 * instead of letting the cashier tune margins against a driver that is scaling
 * the page anyway.
 *
 * The paper size comes from Windows itself over `pos:printer:paper` (Electron's
 * PrinterInfo does not carry it), so this renders a skeleton first and fills in
 * the diagnosis when the answer lands -- the panel must not freeze on a shell
 * query.
 */
function describePrinter () {
  const box = $('printer-info')

  if (!box) return

  const paperMm = paperFromForm()
  const modeLabel = pageModeFromForm() === 'driver' ? 'ikuti driver' : 'otomatis (panjang pas struk)'
  // The dropdown, not the saved setting: this panel is where the cashier is
  // deciding, and a diagnosis that describes the previous printer is worse
  // than no diagnosis.
  const chosen = $('set-printer') ? $('set-printer').value : (state.app?.printer?.name || '')
  const printer = (state.printers || []).find((p) => p.name === chosen)
  const version = state.app?.version ? `v${state.app.version}` : ''
  const footerLine = `Versi aplikasi ${esc(version)} · printer: ${(state.printers || []).length} terdaftar`

  if (!printer) {
    box.innerHTML = [
      `<strong>${esc(chosen || 'Belum ada printer dipilih')}</strong>`,
      'Printer bawaan sistem / dialog cetak Windows dipakai. Ukuran kertas ikut yang dipilih di dialog, ' +
      'jadi perhatikan ukuran halaman di sana: harus ikut berubah mengikuti panjang struk.',
      footerLine
    ].join('<br>')
    return
  }

  const optionsLines = (target) => {
    const quality = printer.options.find((o) => /quality|resolusi|dpi/i.test(o.name || ''))

    if (quality) target.push(`Kualitas cetak: ${esc(quality.value)}`)

    if (printer.options.length) {
      const picked = printer.options
        .filter((o) => !/quality|resolusi|dpi|copies|salinan/i.test(o.name || ''))
        .slice(0, 4)
        .map((o) => `${esc(o.name)}: ${esc(o.value)}`)
        .join(' &middot; ')

      if (picked) target.push(picked)
    }
  }

  // Skeleton while the driver is being asked: a visible "checking" line beats
  // a panel that looks frozen or stale.
  const skeleton = [`<strong>${esc(chosen)}</strong>`, `struk diminta: <strong>${paperMm}mm</strong> (${modeLabel}) · memeriksa ukuran kertas driver…`]
  optionsLines(skeleton)
  skeleton.push(footerLine)
  box.innerHTML = skeleton.join('<br>')

  const render = (size) => {
    // Stale answers lose: the cashier may already have picked another printer
    // (or closed the panel) while the shell query was in flight.
    const live = $('printer-info')
    const stillChosen = $('set-printer') ? $('set-printer').value : (state.app?.printer?.name || '')

    if (!live || stillChosen !== chosen) return

    const lines = [`<strong>${esc(chosen)}</strong>`]

    if (size) {
      lines.push(`Ukuran kertas di driver: <strong>${esc(size)}</strong> &middot; struk diminta: <strong>${paperMm}mm</strong> (${modeLabel})`)

      // A named sheet of paper is a fixed page: the driver will letterbox whatever
      // we hand it, which is where the blank strip and the lost right edge come from.
      // Anything we cannot recognise is treated the same way -- warning costs a
      // sentence, tuning margins against a scaling driver costs an afternoon.
      const fixedSheet = /^\s*(letter|legal|a4|a5|b5|jis-?b5|tabloid|executive|statement|folio|ledger|quarto|octavo)\b/i
      const rollish = /\d+\s*(mm|cm|in)\b|\d+\s*(x|\u00d7)\s*\d+|roll|continuous|struk|thermal|kertas\s*struk/i

      if (fixedSheet.test(size) || !rollish.test(size)) {
        lines.push(
          '<span class="warn">Driver masih di kertas tetap (bukan roll). Buka properti printer di Windows, ' +
          'ubah ukuran kertas ke roll/continu sesuai lebar struk. Selama masih di sini, Windows ' +
          'menyesuaikan halaman kita dan menggeser isi ke tengah.</span>'
        )
      }
    } else {
      lines.push(
        'Ukuran kertas driver tidak bisa dibaca otomatis dari komputer ini. Buka properti printer di ' +
        `Windows dan pastikan ukuran kertasnya roll ${paperMm}mm / continu, bukan A4 atau Letter -- ` +
        'kertas tetap itulah yang bikin kertas kosong di atas struk dan tepi kanan angka terpotong.'
      )
    }

    optionsLines(lines)
    lines.push(footerLine)
    live.innerHTML = lines.join('<br>')
  }

  window.pos
    .printerPaper(chosen)
    .then(render)
    .catch(() => render(null))
}

/* ------------------------------------------------------------------ *
 * events
 * ------------------------------------------------------------------ */

function bindEvents () {
  /* --- login --- */
  $('login-form').addEventListener('submit', async (event) => {
    event.preventDefault()

    const btn = $('login-submit')
    const err = $('login-error')

    btn.disabled = true
    btn.textContent = 'Memeriksa…'
    err.hidden = true

    try {
      const url = $('api-url').value.trim()
      if (url) await window.pos.setApiUrl(url)

      await window.pos.login($('email').value.trim(), $('password').value)

      openApp()
      await loadCatalog()
    } catch (error) {
      err.textContent = error.message
      err.hidden = false
    } finally {
      btn.disabled = false
      btn.textContent = 'Masuk'
    }
  })

  /* --- catalog --- */
  $('search').addEventListener('input', (event) => {
    state.search = event.target.value
    renderGrid()
  })

  $('btn-clear-search').addEventListener('click', () => {
    state.search = ''
    $('search').value = ''
    renderGrid()
    $('search').focus()
  })

  $('categories').addEventListener('click', (event) => {
    const button = event.target.closest('button[data-cat]')
    if (!button) return

    state.categoryId = button.dataset.cat ? Number(button.dataset.cat) : null
    render()
  })

  $('grid').addEventListener('click', (event) => {
    const card = event.target.closest('.card[data-product]')
    if (card && !card.disabled) addToCart(Number(card.dataset.product))
  })

  /* --- cart --- */
  $('cart-items').addEventListener('click', (event) => {
    const step = event.target.closest('button[data-step]')
    if (step) {
      const id = Number(step.dataset.id)
      const line = state.cart.find((i) => i.product_id === id)
      if (line) setQuantity(id, line.quantity + Number(step.dataset.step))
      return
    }

    const remove = event.target.closest('button[data-remove]')
    if (remove) removeLine(Number(remove.dataset.remove))
  })

  $('cart-items').addEventListener('input', (event) => {
    const input = event.target.closest('input[data-discount]')
    if (!input) return

    const digits = input.value.replace(/[^\d]/g, '').replace(/^0+(?=\d)/, '')
    input.value = digits ? plain.format(Number(digits)) : ''

    const type = document.querySelector(`select[data-discount-type="${input.dataset.discount}"]`)?.value
    setLineDiscount(Number(input.dataset.discount), parseNum(input.value), type)
  })

  $('cart-items').addEventListener('change', (event) => {
    const select = event.target.closest('select[data-discount-type]')
    if (!select) return

    const id = Number(select.dataset.discountType)
    const line = state.cart.find((i) => i.product_id === id)
    if (line) setLineDiscount(id, line.discount, select.value)
  })

  $('btn-clear-cart').addEventListener('click', () => {
    state.cart = []
    render()
  })

  /* --- options --- */
  $('opt-ppn').addEventListener('change', (event) => {
    state.options.ppn = event.target.checked
    renderCart()
  })

  bindAmountInput('opt-discount', (value) => {
    state.options.discount = value
    renderCart()
  })

  bindAmountInput('opt-paid', (value) => {
    state.options.paid = value
    renderCart()
  })

  $('quick-amounts').addEventListener('click', (event) => {
    const button = event.target.closest('button[data-amount]')
    if (!button) return

    state.options.paid = Number(button.dataset.amount)
    $('opt-paid').value = plain.format(state.options.paid)
    renderCart()
  })

  $('opt-payment').addEventListener('change', (event) => {
    state.options.payment_method = event.target.value
    renderCart()
  })

  $('opt-order-type').addEventListener('change', (event) => {
    state.options.order_type = event.target.value
  })

  $('opt-customer').addEventListener('input', (event) => {
    state.options.customer_name = event.target.value
  })

  $('btn-checkout').addEventListener('click', checkout)
  $('btn-sync').addEventListener('click', syncNow)

  /* --- modals / drawer --- */
  $('btn-receipt-done').addEventListener('click', () => {
    $('receipt').hidden = true
    $('search').focus()
  })

  $('btn-print-receipt').addEventListener('click', printLastSale)

  $('btn-orders').addEventListener('click', openOrders)
  $('btn-orders-close').addEventListener('click', () => {
    $('orders').hidden = true
  })

  $('orders-list').addEventListener('click', (event) => {
    const button = event.target.closest('button[data-reprint]')
    if (button) reprintOrder(button.dataset.reprint)
  })

  $('btn-settings').addEventListener('click', openSettings)

  // Any change to the print settings redraws the preview, because the point of
  // the preview is to see the effect before spending paper on it.
  for (const id of ['set-paper', 'set-paper-mode', 'set-margin', 'set-top-offset', 'set-font']) {
    $(id).addEventListener('input', () => { renderPreview(); describePrinter() })
    $(id).addEventListener('change', () => { renderPreview(); describePrinter() })
  }

  $('set-printer').addEventListener('change', describePrinter)

  $('btn-calibrate').addEventListener('click', async () => {
    // Save the printer choice first: a calibration sheet measured on the wrong
    // printer produces confident, useless numbers.
    state.app = {
      ...state.app,
      printer: await window.pos.setPrinter($('set-printer').value, paperFromForm(), previewLayoutFromForm(), pageModeFromForm())
    }

    state.printers = await window.pos.printers()
    describePrinter()

    // Printed with nothing held back, so it shows the printer's dead zone
    // instead of our idea of where to print.
    await printReceipt(
      window.posReceipt.buildCalibrationPaper({ paper: paperFromForm() }),
      'Kertas Kalibrasi'
    )

    toast(
      'Lihat struknya: garis kotak yang hilang atau angka yang terpotong menandai area mati printer. ' +
      'Masukkan angkanya ke margin kiri/kanan dan kompensasi atas.'
    )
  })

  $('btn-save-settings').addEventListener('click', async () => {
    state.app = {
      ...state.app,
      printer: await window.pos.setPrinter($('set-printer').value, Number($('set-paper').value) === 80 ? 80 : 58, previewLayoutFromForm(), pageModeFromForm())
    }

    await window.pos.setApiUrl($('set-url').value.trim())
    $('settings').hidden = true
    await loadCatalog()
  })

  $('btn-refresh-printers').addEventListener('click', loadPrinters)

  $('btn-test-print').addEventListener('click', async () => {
    // Save first: a test print that goes to a different printer than the one
    // just chosen is worse than no test print at all.
    state.app = {
      ...state.app,
      printer: await window.pos.setPrinter($('set-printer').value, Number($('set-paper').value) === 80 ? 80 : 58, previewLayoutFromForm(), pageModeFromForm())
    }

    await printReceipt(
      posBuildReceipt({
        ...sampleSale(),
        paper: paperMm(),
        layout: printLayout(),
        footer: 'Ini struk uji cetak, bukan transaksi.'
      })
    )
  })

  $('btn-refresh-catalog').addEventListener('click', async () => {
    await window.pos.refreshCatalog()
    await loadCatalog()
  })

  $('btn-export').addEventListener('click', () => window.pos.exportDatabase())

  $('btn-logout').addEventListener('click', async () => {
    await window.pos.logout()
    location.reload()
  })

  /* --- keyboard: F2 search, F4 pay, Esc close --- */
  document.addEventListener('keydown', (event) => {
    if (event.key === 'F2') {
      event.preventDefault()
      $('search').focus()
      $('search').select()
    }

    if (event.key === 'F4' && !$('app').hidden) {
      event.preventDefault()
      if (!$('btn-checkout').disabled) checkout()
    }

    if (event.key === 'Escape') {
      for (const id of ['receipt', 'orders', 'settings']) $(id).hidden = true
    }
  })

  /* --- application menu ---
     The menu bar owns the same actions as the buttons above. Both end up in
     the functions defined here, so there is a single implementation to keep
     correct and a single place for the menu to point at. */
  window.pos.onMenu(async (action) => {
    if (action === 'sync') {
      await syncNow()
    } else if (action === 'catalog') {
      await window.pos.refreshCatalog()
      await loadCatalog()
    } else if (action === 'orders') {
      await openOrders()
    } else if (action === 'print') {
      printLastSale()
    } else if (action === 'settings') {
      openSettings()
    } else if (action === 'logout') {
      await window.pos.logout()
      location.reload()
    }
  })

  window.pos.onChange((next) => {
    state.app = next
    renderHeader()
  })
}

/* ------------------------------------------------------------------ *
 * boot
 * ------------------------------------------------------------------ */

async function boot () {
  bindEvents()

  state.app = { ...(await window.pos.state()), version: await window.pos.version() }

  if (!state.app.user) {
    $('api-url').value = state.app.apiUrl || ''
    $('login').hidden = false
    $('email').focus()
    return
  }

  openApp()
  await loadCatalog()
}

boot().catch((error) => {
  document.body.textContent = `Gagal memuat aplikasi: ${error.message}`
})
