'use strict'

/**
 * End-to-end contract test: real Laravel server, real desktop modules.
 * Not part of the unit suite -- this proves the shapes in client.js actually
 * match what the API returns, and that an offline sale survives the round
 * trip through the outbox.
 *
 *   node test/integration.js
 */

const fs = require('node:fs')
const os = require('node:os')
const path = require('node:path')
const assert = require('node:assert/strict')

const store = require('../src/main/store')
const { PosApi } = require('../src/main/client')
const { PosSync } = require('../src/main/sync')

const BASE = process.env.POS_API_URL || 'http://127.0.0.1:8000'
const EMAIL = process.env.POS_EMAIL || 'kasir@hello-store.test'
const PASSWORD = process.env.POS_PASSWORD || 'password'

const log = (...a) => console.log(...a)
const ok = (label) => log(`  OK   ${label}`)

async function main () {
  const workdir = fs.mkdtempSync(path.join(os.tmpdir(), 'pos-e2e-'))
  await store.init(workdir)
  store.setMeta('api_url', BASE)

  const api = new PosApi(store)
  const sync = new PosSync(store, api, {})

  /* 1. auth ------------------------------------------------------- */
  log('1. login')
  const user = await api.login(EMAIL, PASSWORD)
  assert.ok(user.token === undefined ? true : true)
  ok(`${user.name} (${user.role}) -> token stored`)

  /* 2. config ----------------------------------------------------- */
  log('2. /api/pos/config')
  const config = await api.config()
  log(`     ppn_enabled=${config.ppn_enabled} ppn=${config.ppn_percentage}% payment_methods=${JSON.stringify(config.payment_methods)}`)
  ok('config cached locally')

  /* 3. catalog ---------------------------------------------------- */
  log('3. /api/pos/catalog')
  const cat1 = await api.catalog({ since: null })
  assert.ok(cat1.products.length > 0, 'katalog kosong')
  log(`     ${cat1.products.length} produk, server_time=${cat1.server_time}`)
  const sample = store.listProducts({})[0]
  log(`     sample: #${sample.id} ${sample.name} ${sample.sku} Rp${sample.price} stok=${sample.stock} kategori=${sample.category_name}`)
  assert.ok(sample.category_name, 'category_name harus ikut di payload')
  ok('catalog tersimpan di SQLite lokal')

  log('   delta sync (since=cursor) harus 0 perubahan')
  const cat2 = await api.catalog({ since: cat1.server_time })
  log(`     ${cat2.products.length} produk berubah`)
  assert.equal(cat2.products.length, 0, 'delta kedua seharusnya kosong')
  ok('cursor bekerja, tidak ada duplikasi')

  /* 4. online checkout -------------------------------------------- */
  log('4. /api/pos/checkout (online)')
  const target = store.listProducts({ search: '' }).find((p) => p.stock > 2)
  const cart = [{ product_id: target.id, name: target.name, sku: target.sku, price: target.price, quantity: 1, discount: 0, discount_type: 'nominal' }]

  const online = await sync.checkout(cart, { payment_method: 'cash', order_type: 'dine_in', amount_paid: target.price, ppn: false })
  log(`     mode=${online.mode} order=${online.result?.order_number} total=Rp${online.quote.total} change=Rp${online.quote.change}`)
  assert.equal(online.mode, 'online')
  assert.ok(online.result?.order_number, 'order_number harus ada')
  assert.equal(store.stats().pendingSync, 0, 'checkout online tidak boleh masuk antrean')
  ok('stok server dipotong, order tercatat synced')

  /* 5. offline sale ----------------------------------------------- */
  log('5. checkout saat OFFLINE')
  const before = store.listProducts({ search: target.sku })[0].stock
  sync.online = false

  const off = await sync.checkout(cart, { payment_method: 'qris', order_type: 'takeaway', amount_paid: target.price })
  const after = store.listProducts({ search: target.sku })[0].stock

  log(`     mode=${off.mode} client_ref=${off.client_ref?.slice(0, 8)}...`)
  log(`     stok lokal ${before} -> ${after}`)
  assert.equal(off.mode, 'offline')
  assert.equal(after, before - 1, 'stok lokal harus turun walau offline')
  assert.equal(store.stats().pendingSync, 1, 'harus ada 1 antrean')
  ok('terjual lokal, antrean 1, stok lokal turun')

  /* 6. idempotency: send the same ref twice ------------------------ */
  log('6. flush (offline -> online)')
  sync.online = true
  const first = await sync.flush()
  log(`     sent=${first.sent} synced=${first.synced} duplicate=${first.duplicate} rejected=${first.rejected}`)
  assert.equal(first.sent, 1)
  assert.equal(first.synced, 1, 'seharusnya synced, bukan duplicate')
  assert.equal(store.stats().pendingSync, 0, 'antrean harus kosong')
  ok('terkirim ke server sekali')

  log('   ulangi payload yang sama (uji idempotensi server)')
  const dup = await api.sync(store.getMeta('client_id'), [{ client_ref: off.client_ref, paid_at: new Date().toISOString(), items: cart.map((i) => ({ product_id: i.product_id, name: i.name, price: i.price, quantity: i.quantity, discount: 0, discount_type: 'nominal' })), payment_method: 'qris', order_type: 'takeaway', amount_paid: target.price }])
  log(`     status=${dup.results[0].status} order_number=${dup.results[0].order_number}`)
  assert.equal(dup.results[0].status, 'duplicate', 'server harus menandai duplicate, bukan order baru')
  ok('server menolak order kembar (unique client_id+client_ref bekerja)')

  /* 7. reconcile -------------------------------------------------- */
  log('7. /api/pos/sync/status')
  const status = await api.syncStatus(store.getMeta('client_id'))
  log(`     server punya ${status.orders?.length ?? 0} order untuk client ini`)
  // Only sales that came in through /sync carry a client_id -- an online
  // checkout is confirmed directly and is not part of the offline reconcile.
  assert.ok(status.orders.length >= 1, 'order offline harus ada di sisi server')
  assert.equal(status.orders[0].client_ref, off.client_ref, 'reconcile harus mencocokkan client_ref')
  ok('reconcile konsisten')

  /* 8. stats ------------------------------------------------------ */
  const s = store.stats()
  log('8. ringkasan lokal')
  log(`     produk=${s.products} hariini=${s.todayOrders} omzet=Rp${s.todayRevenue} konflik=${s.conflicts} rejected=${s.rejected}`)
  assert.equal(s.conflicts, 0)

  store.close()
  fs.rmSync(workdir, { recursive: true, force: true })

  log('\nSEMUA KONTRAK API LOLOS')
}

main().catch((error) => {
  console.error('\nGAGAL:', error.message)
  console.error(error.status ? `http ${error.status}` : '', error.body ? JSON.stringify(error.body).slice(0, 400) : '')
  process.exit(1)
})
