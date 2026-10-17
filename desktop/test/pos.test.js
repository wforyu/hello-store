'use strict'

/**
 * Smoke tests for the parts of the desktop POS that do not need Electron:
 * the local database, the outbox state machine, and the quote arithmetic.
 *
 *   node --test test/
 *
 * The quote cases are pinned against the same numbers App\Services\PosService
 * produces. If the two ever disagree, the customer keeps a receipt that does
 * not match the order the shop filed -- so these are not "cosmetic" tests.
 */

const test = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const os = require('node:os')
const path = require('node:path')
const vm = require('node:vm')

const store = require('../src/main/store')
const { quote, calcItemDiscount, stockDeltas } = require('../src/main/sync')
const { buildReceipt, buildCalibrationPaper, renderReceiptHtml, receiptCss, pageSizeMm, printOptions, mmToMicrons, mmFromPx, refusalReason } = require('../src/renderer/receipt')

let workdir

const line = (price, quantity, discount = 0, discount_type = 'nominal') => ({
  product_id: price, name: `p${price}`, price, quantity, discount, discount_type
})

test.before(async () => {
  workdir = fs.mkdtempSync(path.join(os.tmpdir(), 'pos-test-'))
  await store.init(workdir)
})

test.after(() => {
  store.close()
  fs.rmSync(workdir, { recursive: true, force: true })
})

/* ------------------------------------------------------------------ *
 * quote
 * ------------------------------------------------------------------ */

test('quote: PPN is charged on the discounted base, not the subtotal', () => {
  const q = quote([line(10000, 3)], { discount: 5000, ppn: true },
    { ppn_enabled: true, ppn_percentage: 11 })

  assert.equal(q.subtotal, 30000)
  assert.equal(q.globalDiscount, 5000)
  assert.equal(q.ppnBase, 25000)        // 30000 - 5000
  assert.equal(q.ppnAmount, 2750)       // 11% of 25000, NOT 11% of 30000
  assert.equal(q.total, 27750)
})

test('quote: per-item percent discount reduces the taxable base', () => {
  const q = quote([line(20000, 2, 10, 'percent')], { ppn: true },
    { ppn_enabled: true, ppn_percentage: 11 })

  assert.equal(q.itemDiscounts, 4000)    // 10% of 40000
  assert.equal(q.ppnBase, 36000)
  assert.equal(q.ppnAmount, 3960)
  assert.equal(q.total, 39960)
})

test('quote: global discount is clamped so the total cannot go negative', () => {
  const q = quote([line(10000, 3)], { discount: 999999, ppn: true },
    { ppn_enabled: true, ppn_percentage: 11 })

  assert.equal(q.globalDiscount, 30000)
  assert.equal(q.ppnBase, 0)
  assert.equal(q.ppnAmount, 0)
  assert.equal(q.total, 0)
})

test('quote: PPN is skipped when the store has it switched off', () => {
  const q = quote([line(10000, 1)], { ppn: true },
    { ppn_enabled: false, ppn_percentage: 11 })

  assert.equal(q.ppnApplied, false)
  assert.equal(q.ppnAmount, 0)
  assert.equal(q.total, 10000)
})

test('quote: a non-default rate is honoured', () => {
  const q = quote([line(100000, 1)], { ppn: true },
    { ppn_enabled: true, ppn_percentage: 12 })

  assert.equal(q.ppnAmount, 12000)
  assert.equal(q.total, 112000)
})

test('calcItemDiscount: nominal discount cannot exceed the line total', () => {
  assert.equal(calcItemDiscount(line(5000, 2, 999999)), 10000)
  assert.equal(calcItemDiscount(line(5000, 2, 2500)), 2500)
  assert.equal(calcItemDiscount(line(5000, 2, 0)), 0)
})

test('stockDeltas aggregates repeated lines and stays negative', () => {
  assert.deepEqual(
    stockDeltas([{ product_id: 7, quantity: 2 }, { product_id: 7, quantity: 3 }, { product_id: 9, quantity: 1 }]),
    { 7: -5, 9: -1 }
  )
})

/* ------------------------------------------------------------------ *
 * catalog
 * ------------------------------------------------------------------ */

test('catalog upsert is idempotent and update-in-place', () => {
  store.upsertProducts([{ id: 1, name: 'Kopi', sku: 'K-1', price: 15000, stock: 20, category_id: 3, category_name: 'Minuman', updated_at: '2026-01-01 00:00:00' }])
  store.upsertProducts([{ id: 1, name: 'Kopi', sku: 'K-1', price: 15000, stock: 20, category_id: 3, category_name: 'Minuman', updated_at: '2026-01-01 00:00:00' }])

  assert.equal(store.listProducts({}).length, 1)

  store.upsertProducts([{ id: 1, name: 'Kopi', sku: 'K-1', price: 15000, stock: 7, category_id: 3, category_name: 'Minuman', updated_at: '2026-01-02 00:00:00' }])

  const [product] = store.listProducts({ search: 'kopi' })
  assert.equal(product.stock, 7)
})

test('search matches SKU as well as name', () => {
  store.upsertProducts([{ id: 2, name: 'Teh Manis', sku: 'SKU-UNIK-77', price: 8000, stock: 5, category_id: 3, category_name: 'Minuman' }])

  assert.equal(store.listProducts({ search: 'SKU-UNIK' }).length, 1)
  assert.equal(store.listProducts({ search: 'teh' }).length, 1)
  assert.equal(store.listProducts({ search: 'tidak-ada' }).length, 0)
})

test('categories are derived from the cached catalog', () => {
  const categories = store.listCategories()
  const minuman = categories.find((c) => c.name === 'Minuman')

  assert.ok(minuman, 'kategori Minuman harus muncul')
  assert.equal(minuman.count, 2)
})

/* ------------------------------------------------------------------ *
 * outbox state machine
 * ------------------------------------------------------------------ */

test('an offline sale lands in orders and the outbox, and moves local stock', () => {
  store.upsertProducts([{ id: 50, name: 'Roti', price: 8000, stock: 10 }])

  const payload = { client_ref: 'ref-offline-1', payment_method: 'cash', order_type: 'dine_in', items: [{ product_id: 50, quantity: 2 }] }

  const ref = store.createPendingOrder({
    payload,
    total: 16000,
    amountPaid: 20000,
    changeDue: 4000,
    localStockDeltas: { 50: -2 }
  })

  assert.equal(ref, 'ref-offline-1')
  assert.equal(store.listProducts({ search: 'roti' })[0].stock, 8)
  assert.equal(store.stats().pendingSync, 1)
})

test('claiming a batch moves rows to sending and is re-claimable after a crash', () => {
  const first = store.claimOutboxBatch(10)
  assert.equal(first.length, 1)
  assert.equal(first[0].client_ref, 'ref-offline-1')

  // A claim that never got a response must be recoverable, otherwise the sale
  // is stranded in the outbox forever.
  store.releaseOutboxClaims()

  const second = store.claimOutboxBatch(10)
  assert.equal(second.length, 1)
  assert.equal(second[0].client_ref, 'ref-offline-1')
})

test('a sync result settles the order and empties the outbox', () => {
  store.applySyncResult({
    client_ref: 'ref-offline-1',
    status: 'synced',
    order_id: 99,
    order_number: 'POS-000099',
    stock_conflict: false
  })

  const [order] = store.listOrders()
  assert.equal(order.status, 'synced')
  assert.equal(order.order_number, 'POS-000099')

  assert.equal(store.claimOutboxBatch(10).length, 0)
  assert.equal(store.stats().pendingSync, 0)
})

test('a stock conflict is recorded rather than silently dropped', () => {
  store.createPendingOrder({
    payload: { client_ref: 'ref-offline-2', payment_method: 'qris', items: [] },
    total: 5000,
    amountPaid: 5000,
    changeDue: 0,
    localStockDeltas: {}
  })

  store.claimOutboxBatch(10)
  store.applySyncResult({
    client_ref: 'ref-offline-2',
    status: 'synced',
    order_id: 100,
    order_number: 'POS-000100',
    stock_conflict: true,
    conflict_reason: 'Stok tidak cukup untuk 5 item'
  })

  const [order] = store.listOrders()
  assert.equal(order.status, 'synced')
  assert.equal(order.stock_conflict, 1)
  assert.match(order.conflict_reason, /Stok tidak cukup/)
  assert.equal(store.stats().conflicts, 1)
})

test('duplicate detection is a normal outcome, not an error', () => {
  store.createPendingOrder({
    payload: { client_ref: 'ref-offline-3', payment_method: 'cash', items: [] },
    total: 7000, amountPaid: 7000, changeDue: 0, localStockDeltas: {}
  })

  store.claimOutboxBatch(10)
  store.applySyncResult({ client_ref: 'ref-offline-3', status: 'duplicate', order_id: 100, order_number: 'POS-000100' })

  const [order] = store.listOrders()
  assert.equal(order.status, 'duplicate')
  assert.equal(store.stats().pendingSync, 0)
})

test('local stock never goes negative', () => {
  store.upsertProducts([{ id: 60, name: 'Es Batu', price: 1000, stock: 1 }])
  store.applyLocalStockDeltas({ 60: -99 })

  assert.equal(store.listProducts({ search: 'es batu' })[0].stock, 0)
})

/* ------------------------------------------------------------------ *
 * durability
 * ------------------------------------------------------------------ */

test('the database survives a restart', async () => {
  const clientId = store.getMeta('client_id')
  const before = store.listProducts({}).length

  store.close()
  await store.init(workdir)

  assert.equal(store.getMeta('client_id'), clientId, 'client_id harus stabil lintas restart')
  assert.equal(store.listProducts({}).length, before, 'katalog harus tersimpan')
  assert.ok(store.listOrders().length >= 3, 'riwayat transaksi harus tersimpan')
})

/* ------------------------------------------------------------------ *
 * receipts
 * ------------------------------------------------------------------ */

test('receipt carries the store header, the lines and the money', () => {
  const items = [line(25000, 2), line(10000, 1, 10, 'percent')]
  const totals = quote(items, { discount: 5000, ppn: true, paid: 100000 }, { ppn_enabled: true, ppn_percentage: 11 })

  const receipt = buildReceipt({
    items,
    totals: { ...totals, paid: 100000 },
    config: { store_name: 'Hello Store', store_address: 'Jl. Merdeka 10', store_phone: '0812' },
    order: { number: 'POS-000123', at: '2026-09-30T02:00:00Z', type: 'takeaway', customer: 'Rina', cashier: 'Kasir', payment: 'cash' },
    paper: 80
  })

  assert.equal(receipt.store.name, 'Hello Store')
  assert.equal(receipt.paper, 80)
  assert.equal(receipt.order.number, 'POS-000123')
  assert.equal(receipt.order.type, 'takeaway')
  assert.equal(receipt.order.payment, 'cash')

  // Line totals must be net of the per-line discount, and add up to subtotal
  // minus those discounts -- a receipt that adds up to something else than
  // what the customer was charged is worse than no receipt.
  const lineSum = receipt.items.reduce((sum, i) => sum + i.total, 0)
  assert.equal(lineSum, totals.subtotal - totals.itemDiscounts)
  assert.equal(receipt.items[0].total, 50000)
  assert.equal(receipt.items[1].discount, 1000)
  assert.equal(receipt.totals.total, totals.total)
  assert.equal(receipt.totals.paid, 100000)
  assert.equal(receipt.totals.change, 100000 - totals.total)
  assert.equal(receipt.totals.ppnLabel, 'PPN 11%')
})

test('a reprint reproduces the numbers of the original sale', () => {
  const items = [line(25000, 2), line(10000, 1, 10, 'percent')]
  const options = { discount: 5000, ppn: true }
  const config = { ppn_enabled: true, ppn_percentage: 11 }

  const original = buildReceipt({
    items,
    totals: quote(items, options, config),
    config,
    order: { number: 'POS-000123', payment: 'qris' },
    paper: 58
  })

  // Reprinting goes through the same builder with a basket read back out of the
  // local database, so replaying it must be bit-identical in the money.
  const replay = buildReceipt({
    items,
    totals: quote(items, options, config),
    config,
    order: { number: 'POS-000123', payment: 'qris' },
    paper: 58
  })

  assert.deepEqual(replay.totals, original.totals)
  assert.deepEqual(replay.items, original.items)
})

test('an offline sale is stamped as not yet on the server', () => {
  const receipt = buildReceipt({
    items: [line(10000, 1)],
    totals: quote([line(10000, 1)], {}, {}),
    order: { number: 'abc-123', pending: true }
  })

  assert.equal(receipt.order.pending, true)
})

test('a receipt with no items is refused by the printer, not printed blank', () => {
  const empty = buildReceipt({ items: [], totals: quote([], {}, {}), order: { number: 'X' } })

  assert.equal(refusalReason(empty), 'struk kosong')
  assert.equal(refusalReason(null), 'struk kosong')
  assert.equal(refusalReason({ items: 'not-an-array' }), 'struk kosong')

  const sale = buildReceipt({
    items: [{ name: 'Kopi', price: 18000, quantity: 1, discount: 0, discount_type: 'fixed' }],
    totals: quote([line(18000, 1)], {}, {}),
    order: { number: 'POS-1' }
  })

  assert.equal(refusalReason(sale), null)

  // The calibration sheet has NO sale lines -- the old "refuse empty
  // receipts" guard also refused it, so the sheet built to measure a clipped
  // right edge could never reach the printer. That must never regress.
  assert.equal(refusalReason(buildCalibrationPaper({ paper: 58 })), null)
})

/* ------------------------------------------------------------------ *
 * script parsing
 *
 * A single duplicate `const` in receipt-print.js once took down the whole
 * print pipeline: the script failed to parse, the hidden window never
 * reported its height, and every print hung silently with no error anywhere.
 * Unit tests could not catch it because nothing required that file. This
 * parses every script the app ships instead.
 * ------------------------------------------------------------------ */

test('every shipped script parses', () => {
  const roots = ['src/main', 'src/renderer']
  const checked = []

  const walk = (dir) => {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
      const full = path.join(dir, entry.name)

      if (entry.isDirectory()) walk(full)
      else if (entry.name.endsWith('.js')) {
        const source = fs.readFileSync(full, 'utf8')

        // vm.Script parses a file as a script (not a module or a function
        // body), which is exactly how the browser and Electron parse these.
        assert.doesNotThrow(
          () => new vm.Script(source, { filename: full }),
          `${full} gagal diparse`
        )
        checked.push(full)
      }
    }
  }

  for (const root of roots) walk(path.join(__dirname, '..', root))

  assert.ok(checked.length >= 10, `hanya ${checked.length} skrip terdaftar`)
  // The file whose SyntaxError once hung every print must be in the set.
  assert.ok(
    checked.some((file) => file.endsWith(`receipt-print.js`)),
    'receipt-print.js tidak ikut diparse'
  )
})

/* ------------------------------------------------------------------ *
 * print layout
 *
 * Two real complaints from the printer, both of them money: the right edge of
 * every rupiah amount was cut off, and a few centimetres of blank paper came
 * out before the first line.
 * ------------------------------------------------------------------ */

test('the print layout is carried on the receipt, defaulting to a safe inset', () => {
  const bare = buildReceipt({ items: [], totals: quote([], {}, {}), order: { number: 'X' } })

  // Edge-to-edge text is what got clipped, so the default has to pull in. A
  // 58mm head cannot print the last ~5mm of the roll, so the safe default is
  // 5mm -- the old 3mm still let the tail of a rupiah amount sit under the
  // dead zone and disappear.
  assert.deepEqual(bare.layout, { margin: 5, topOffset: 0, fontScale: 1 })

  // 80mm rolls have a narrower dead zone, so their default is a touch tighter.
  const wide = buildReceipt({ items: [], totals: quote([], {}, {}), order: { number: 'X' }, paper: 80 })

  assert.equal(wide.layout.margin, 4)

  const tuned = buildReceipt({
    items: [],
    totals: quote([], {}, {}),
    order: { number: 'X' },
    layout: { margin: 4.5, topOffset: 6, fontScale: 1.2 }
  })

  assert.deepEqual(tuned.layout, { margin: 4.5, topOffset: 6, fontScale: 1.2 })
})

test('a nonsense print layout is clamped instead of destroying the slip', () => {
  const receipt = buildReceipt({
    items: [],
    totals: quote([], {}, {}),
    order: { number: 'X' },
    // A fat finger on a touch screen, or a renderer that sends junk.
    layout: { margin: 400, topOffset: -50, fontScale: 99 }
  })

  assert.deepEqual(receipt.layout, { margin: 8, topOffset: 0, fontScale: 1.6 })

  const garbage = buildReceipt({
    items: [],
    totals: quote([], {}, {}),
    order: { number: 'X' },
    layout: { margin: 'abc', topOffset: null, fontScale: undefined }
  })

  assert.deepEqual(garbage.layout, { margin: 5, topOffset: 0, fontScale: 1 })
})

test('the inset shows up as real CSS the printer can act on', () => {
  const css = receiptCss({ margin: 4, topOffset: 5, fontScale: 1.2 })

  // The right inset: content must end 4mm before the edge of the roll.
  assert.match(css, /padding:\s*3mm 4\.0mm 2mm/)
  // The leading strip: content pulled up into the printer's dead top margin.
  assert.match(css, /margin-top:\s*-5\.0mm/)
  // Font size has to follow the setting or "besar" would print at normal size.
  assert.match(css, /font:\s*13\.2px\/1\.35/)
  // @page itself must stay margin-free: a page margin is applied by Chromium
  // and would push the content off the calibrated offset.
  assert.match(css, /@page\s*\{\s*margin:\s*0;\s*\}/)
})

test('the preview and the printer render from one template', () => {
  const receipt = buildReceipt({
    items: [{ name: 'Kopi', price: 18000, quantity: 2, discount: 0, discount_type: 'fixed' }],
    totals: quote([{ name: 'Kopi', price: 18000, quantity: 2, discount: 0 }], {}, {}),
    order: { number: 'POS-1', at: '2026-01-02T03:04:00', type: 'dine_in', payment: 'cash' },
    config: { store_name: 'Toko Uji' }
  })

  const html = renderReceiptHtml(receipt)

  // If the preview ever becomes its own copy of the markup, it becomes a
  // preview that lies: it looks right, then the paper comes out different.
  assert.match(html, /Toko Uji/)
  assert.match(html, /POS-1/)
  assert.match(html, /36\.000/)
  // The order number must not be reprinted as a spaced-out code line: it is
  // already in the header, and duplicating it wastes roll.
  assert.ok(!/<div class="code spaced"/.test(html))

  // Ampersands and angle brackets in a product name must not become markup.
  const nasty = renderReceiptHtml({
    ...receipt,
    items: [{ name: 'Kopi & Teh <b>', price: 1000, qty: 1, total: 1000 }]
  })

  assert.match(nasty, /Kopi &amp; Teh &lt;b&gt;/)
  assert.ok(!nasty.includes('<b>'))
})

test('the calibration sheet is printed with nothing held back', () => {
  const paper = buildCalibrationPaper({ paper: 80 })

  // A calibration sheet that respects the current margin cannot show where the
  // margin is needed -- it would confirm the number we already put in.
  assert.deepEqual(paper.layout, { margin: 0, topOffset: 0, fontScale: 1 })
  assert.equal(paper.paper, 80)
  assert.equal(paper.kind, 'calibration')

  const html = renderReceiptHtml(paper)

  // The frame on all four edges is what shows a clipped edge.
  assert.match(html, /cal-frame/)

  // A millimetre ruler the cashier can count, labelled every 5mm.
  assert.match(html, /class="ticks"/)
  assert.ok((html.match(/<i><\/i>/g) || []).length === 81, 'tick 0..80mm')
  assert.match(html, /<b>50<\/b>/)

  // The money repeated at a known inset on both sides, and labelled with it.
  assert.match(html, /padding-right: 8mm/)
  assert.match(html, /padding-left: 8mm/)
  assert.ok((html.match(/class="pv"/g) || []).length === 18, '0..8mm kiri dan kanan')

  // 1mm bars at the top, so the first line of ink shows where the head starts.
  assert.match(html, /class="bar-row"/)
  assert.ok((html.match(/class="bar-row"/g) || []).length === 12)
})

test('the paper length the printer is told comes from one formula', () => {
  const receipt = buildReceipt({
    items: [],
    totals: quote([], {}, {}),
    order: { number: 'X' },
    paper: 58,
    layout: { margin: 3, topOffset: 0, fontScale: 1 }
  })

  // 20px of cut buffer plus the content, in millimetres at 96px/inch.
  assert.equal(pageSizeMm(receipt, 100).widthMm, 58)
  assert.ok(Math.abs(pageSizeMm(receipt, 100).heightMm - ((120 * 25.4) / 96)) < 0.001)

  // The top offset comes off the paper, it does not shrink the content.
  const shifted = pageSizeMm({ ...receipt, layout: { ...receipt.layout, topOffset: 5 } }, 100)

  assert.ok(Math.abs(pageSizeMm(receipt, 100).heightMm - shifted.heightMm - 5) < 0.001)

  // A page can never be shorter than the driver will accept.
  assert.ok(pageSizeMm(receipt, 0).heightMm >= 10)
})

test('millimetres become microns for the printer, never inches', () => {
  // Electron's docs say inches; its validator says microns. Inches made it
  // throw "must be minimum 353 microns", so this conversion is the fix and
  // deserves its own test -- get it backwards and no receipt prints at all.
  assert.equal(mmToMicrons(58), 58000)
  assert.equal(mmToMicrons(80), 80000)
  assert.equal(mmToMicrons(103.98), 103980)
  assert.equal(mmToMicrons(0.1), 353)

  // And the pixel -> millimetre leg the renderer measures with.
  assert.ok(Math.abs(mmFromPx(96) - 25.4) < 0.001)
})

test('the printer is handed exactly one page-size rule, in the chosen mode', () => {
  // The everyday path: silent to the remembered printer, an exact micron page
  // so the roll is cut at the end of the receipt, no driver margins.
  const auto = printOptions({ mode: 'silent', deviceName: 'POS-58', pageMode: 'auto', widthMm: 58, heightMm: 103.98 })

  assert.equal(auto.silent, true)
  assert.equal(auto.deviceName, 'POS-58')
  assert.deepEqual(auto.pageSize, { width: 58000, height: 103980 })
  assert.equal(auto.usePrinterDefaultPageSize, undefined)
  assert.deepEqual(auto.margins, { marginType: 'none' })
  assert.equal(auto.header, '')
  assert.equal(auto.footer, '')

  // The escape hatch for drivers that letterbox a silent job into A4: let the
  // printer's own form decide. pageSize MUST be absent here -- Electron throws
  // "usePrinterDefaultPageSize cannot be combined with pageSize".
  const driver = printOptions({ mode: 'silent', deviceName: 'POS-58', pageMode: 'driver', widthMm: 58, heightMm: 103.98 })

  assert.equal(driver.usePrinterDefaultPageSize, true)
  assert.equal(driver.pageSize, undefined)

  // No printer remembered: the OS dialog is shown, which decides its own page.
  const dialog = printOptions({ mode: 'dialog' })

  assert.equal(dialog.silent, false)
  assert.equal(dialog.deviceName, undefined)
  assert.ok(dialog.pageSize, 'dialog still carries a page for the preview/driver')
  assert.equal(dialog.usePrinterDefaultPageSize, undefined)
})

