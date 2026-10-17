'use strict'

/**
 * Receipt building for the desktop till.
 *
 * Both printing paths go through `buildReceipt`: the receipt shown right after
 * a sale, and the reprint of an earlier one. The basket lines and the money
 * are recomputed with the same functions the cart screen uses, so a reprint
 * shows the same numbers the customer walked away with rather than today's
 * prices or a re-derived total that could round differently.
 *
 * This file is loaded by index.html *and* required by the unit tests, so it is
 * wrapped: a bare `module.exports` throws in the renderer, and a bare
 * `window.x =` is undefined in Node. Everything lives inside the factory to
 * keep the two script scopes from colliding.
 */

;(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory()
  } else {
    root.posReceipt = factory()
  }
})(typeof self !== 'undefined' ? self : this, function () {
  const esc = (value) =>
    String(value ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]))

  const rupiah = (n) => 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number(n) || 0)

  const pad2 = (n) => String(n).padStart(2, '0')

  function formatDate (iso) {
    if (!iso) return '-'
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return String(iso)

    return `${pad2(d.getDate())}/${pad2(d.getMonth() + 1)}/${d.getFullYear()} ` +
      `${pad2(d.getHours())}:${pad2(d.getMinutes())}`
  }

  const PAYMENT_LABEL = {
    cash: 'Tunai',
    qris: 'QRIS',
    debit: 'Kartu Debit',
    transfer: 'Transfer Bank'
  }

  const TYPE_LABEL = { dine_in: 'Makan di Tempat', takeaway: 'Bawa Pulang' }

  /** Mirrors quote()'s per-line discount, kept local to avoid a circular import. */
  function itemDiscountAmount (item) {
    const itemTotal = Number(item.price) * Number(item.quantity)
    const discount = Number(item.discount) || 0

    if (discount <= 0) return 0
    if (item.discount_type === 'percent') return Math.round((itemTotal * Math.min(discount, 100)) / 100)

    return Math.min(discount, itemTotal)
  }

  /**
   * @param {object} input
   * @param {Array} input.items    cart lines, or the payload lines of an old order
   * @param {object} input.totals  result of quote()/computeTotals() for that basket
   * @param {object} input.order   { number, at, type, customer, cashier, payment, pending }
   * @param {object} input.config  cached /api/pos/config response
   * @param {number} input.paper   58 or 80
   * @param {object} input.layout  { margin, topOffset, fontScale } from the cashier's print settings
   * @param {string} [input.footer]
   */
  function buildReceipt ({
    items = [],
    totals = {},
    order = {},
    config = {},
    paper = 58,
    layout = {},
    footer = ''
  }) {
    const total = totals.total || 0
    const paid = totals.paid || 0

    return {
      store: {
        name: config.store_name || 'Toko',
        address: config.store_address || '',
        phone: config.store_phone || '',
        whatsapp: config.store_whatsapp || ''
      },
      order: {
        number: order.number || '-',
        at: order.at || null,
        type: order.type || 'dine_in',
        customer: order.customer || '',
        cashier: order.cashier || '-',
        payment: order.payment || 'cash',
        pending: Boolean(order.pending)
      },
      items: items.map((item) => {
        const gross = Number(item.price) * Number(item.quantity)
        const discount = itemDiscountAmount(item)

        return {
          name: item.name,
          qty: Number(item.quantity) || 0,
          price: Number(item.price) || 0,
          discount,
          total: Math.max(0, gross - discount)
        }
      }),
      totals: {
        subtotal: totals.subtotal || 0,
        itemDiscount: totals.itemDiscounts || 0,
        globalDiscount: totals.globalDiscount || 0,
        ppnLabel: `PPN ${totals.ppnRate || 0}%`,
        ppn: totals.ppnAmount || 0,
        total,
        paid,
        // `quote()` in main stops at the total; change is derived here so a
        // reprint of a cash sale still shows the right money back, instead of
        // defaulting to a silent zero.
        change: totals.change ?? Math.max(0, paid - total)
      },
      paper: Number(paper) === 80 ? 80 : 58,
      layout: {
        margin: clampMm(layout.margin, defaultMargin(paper), 0, 8),
        topOffset: clampMm(layout.topOffset, 0, 0, 15),
        fontScale: Math.min(1.6, Math.max(0.8, Number(layout.fontScale) || 1))
      },
      footer
    }
  }

  /**
   * The receipt markup itself.
   *
   * Lives here rather than in the print page so the on-screen preview and the
   * printed slip are the same function. A preview that is a re-implementation
   * is a preview that lies: it looks right, then the paper comes out different.
   *
   * @param {Receipt} receipt
   * @returns {string}
   */
  function renderReceiptHtml (receipt) {
    // The calibration sheet is markup of its own: it exists to show the paper's
    // limits, so it cannot go through the sale template.
    if (receipt.kind === 'calibration') return calibrationMarkup(Number(receipt.paper) === 80 ? 80 : 58)

    const { store = {}, order = {}, items = [], totals = {}, footer = '' } = receipt
    const paperMm = Number(receipt.paper) === 80 ? 80 : 58

    const row = (label, value) => `<div class="row"><span>${esc(label)}</span><span>${esc(value)}</span></div>`

    const body = items
      .map((item) => {
        const qty = Number(item.qty) || 0
        const price = Number(item.price) || 0
        const discount = Number(item.discount) || 0
        const gross = price * qty

        const math = discount > 0
          ? `@${rupiah(price)} x${qty} &minus; ${rupiah(discount)}`
          : `@${rupiah(price)} x${qty}`

        // Long product names are the normal case in a shop, not the exception,
        // so the name gets the room and the money column stays fixed.
        return `<tr>
          <td class="item-name">${esc(item.name)}<div class="item-sub">${math}</div></td>
          <td class="num">${rupiah(gross - discount)}</td>
        </tr>`
      })
      .join('')

    return `
      <div class="center">
        <div class="store-name">${esc(store.name || 'Toko')}</div>
        ${store.address ? `<div class="store-meta">${esc(store.address)}</div>` : ''}
        ${store.phone ? `<div class="store-meta">Telp. ${esc(store.phone)}</div>` : ''}
        ${store.whatsapp ? `<div class="store-meta">WA ${esc(store.whatsapp)}</div>` : ''}
      </div>

      <hr>

      ${row('No', order.number || '-')}
      ${row('Tanggal', formatDate(order.at))}
      ${row('Kasir', order.cashier || '-')}
      ${row('Pesanan', TYPE_LABEL[order.type] || 'Dine-in')}
      ${order.customer ? row('Pelanggan', order.customer) : ''}
      ${
        order.pending
          ? '<div class="center" style="margin-top:4px"><span class="badge">Belum terkirim ke server</span></div>'
          : ''
      }

      <hr>

      <table class="items">
        <thead>
          <tr><th>Item</th><th>Jumlah</th></tr>
        </thead>
        <tbody>${body}</tbody>
      </table>

      <hr>

      ${row('Subtotal', rupiah(totals.subtotal))}
      ${Number(totals.itemDiscount) > 0 ? row('Diskon item', '-' + rupiah(totals.itemDiscount)) : ''}
      ${Number(totals.globalDiscount) > 0 ? row('Diskon transaksi', '-' + rupiah(totals.globalDiscount)) : ''}
      ${Number(totals.ppn) > 0 ? row(totals.ppnLabel || 'PPN', rupiah(totals.ppn)) : ''}

      <div class="row grand">
        <span>TOTAL</span>
        <span>${rupiah(totals.total)}</span>
      </div>

      ${row(PAYMENT_LABEL[order.payment] || String(order.payment || '-').toUpperCase(), rupiah(totals.paid))}
      ${
        Number(totals.change) > 0
          ? `<div class="row bold"><span>KEMBALI</span><span>${rupiah(totals.change)}</span></div>`
          : ''
      }

      <hr>

      <div class="center foot">
        ${esc(footer || 'Terima kasih sudah berbelanja')}
        <div style="margin-top:4px">${paperMm}mm &bull; ${esc(formatDate(order.at))}</div>
      </div>
    `
  }

  /**
   * The print page's own CSS, as a string, so the preview and the printed slip
   * are laid out by the same rules.
   *
   * The page is laid out at the EXACT roll width (`width: 58mm`/`80mm`), not at
   * `100%`. The hidden print window is 400px wide for the rest of the UI, so a
   * `width: 100%` paper was silently laid out at 400px and then clipped to the
   * 58mm page the printer actually got: content ran ~13mm past the edge of the
   * roll (the right-hand rupiah figures cut off) and the top offset ate into
   * blank paper that was never inside the page anyway. Margins looked like they
   * "did nothing" because they only shifted a layout that was already too wide.
   *
   * `margin` is the horizontal inset. Thermal heads cannot print in the last few
   * millimetres of the roll: 58mm paper prints 48mm, 80mm paper prints 72mm, so
   * the default inset is 5mm / 4mm respectively.
   *
   * `topOffset` pulls the content up into the printer's unprintable top margin
   * and shortens the page by the same amount, which is what eats the strip of
   * blank paper that would otherwise come out before the first line.
   */
  function receiptCss (layout = {}, paperMm = 58) {
    const paper = Number(paperMm) === 80 ? 80 : 58
    const margin = clampMm(layout.margin, defaultMargin(paper), 0, 8)
    const topOffset = clampMm(layout.topOffset, 0, 0, 15)
    const scale = Number(layout.fontScale) || 1

    return `
      @page { margin: 0; }
      * { box-sizing: border-box; }
      html, body {
        width: ${paper}mm;
        margin: 0;
        padding: 0;
        background: #fff;
        color: #000;
        font: ${(11 * scale).toFixed(1)}px/1.35 "Consolas", "Courier New", monospace;
      }
      body { margin-top: ${(-topOffset).toFixed(1)}mm; }
      #paper { width: 100%; padding: 3mm ${margin.toFixed(1)}mm 2mm; overflow: hidden; }
      .center { text-align: center; }
      .bold { font-weight: 700; }
      .spaced { letter-spacing: 1px; }
      .store-name { font-size: ${(15 * scale).toFixed(1)}px; font-weight: 700; }
      .store-meta { font-size: ${(10 * scale).toFixed(1)}px; }
      hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
      .row { display: flex; justify-content: space-between; gap: 6px; }
      .row span:last-child { white-space: nowrap; }
      table.items { width: 100%; border-collapse: collapse; font-size: ${(11 * scale).toFixed(1)}px; }
      table.items th {
        text-align: left; font-size: ${(9 * scale).toFixed(1)}px;
        text-transform: uppercase; letter-spacing: .5px; padding-bottom: 2px;
      }
      table.items th:last-child, table.items td.num { text-align: right; }
      table.items td { padding: 1px 0; vertical-align: top; }
      table.items td.num { white-space: nowrap; padding-left: 4px; }
      .item-name { word-break: break-word; }
      .item-sub { font-size: ${(9.5 * scale).toFixed(1)}px; }
      .grand {
        font-size: ${(14 * scale).toFixed(1)}px; font-weight: 700;
        border-top: 1px solid #000; border-bottom: 1px solid #000;
        padding: 4px 0; margin: 6px 0;
      }
      .badge {
        display: inline-block; border: 1px solid #000; padding: 0 3px;
        font-size: ${(9 * scale).toFixed(1)}px; text-transform: uppercase;
      }
      .foot { font-size: ${(10 * scale).toFixed(1)}px; }

      /* calibration sheet */
      .cal-head { text-align: center; font-weight: 700; margin-bottom: 1mm; }
      .cal-frame { border: 1px solid #000; padding: 0 0 1mm; }
      .cal-note { font-size: 8px; padding: 1mm 0; }
      .ticks { display: flex; align-items: flex-end; height: 7mm; }
      .ticks .tick { width: ${(96 / 25.4).toFixed(4)}mm; text-align: center; font-size: 7px; }
      .ticks .tick i { display: block; border-left: 1px solid #000; height: 2mm; }
      .ticks .tick.major i { height: 4mm; }
      .ticks .tick b { font-size: 7px; font-weight: 400; }
      .bars { margin: 0 0 1mm; }
      .bar-row { display: flex; align-items: center; gap: 1mm; height: 1mm; }
      .bar-row .bar { display: block; flex: 1; height: 1mm; background: #000; }
      .bar-row .pl { font-size: 7px; }
      .probes { margin: 1mm 0; }
      .probe { display: flex; align-items: baseline; justify-content: space-between; gap: 2mm; }
      .probe .pv { font-weight: 700; }
      .probe .pl { font-size: 7px; }
    `
  }

  /** Millimetre settings are clamped: a fat finger cannot ask for a 400mm inset. */
  function clampMm (value, fallback, min, max) {
    const n = Number(value)

    if (!Number.isFinite(n)) return fallback

    return Math.min(max, Math.max(min, n))
  }

  /**
   * The safe horizontal inset for a roll: a thermal head cannot reach the last
   * few millimetres, so 58mm paper really prints 48mm (5mm each side) and 80mm
   * paper prints 72mm (4mm each side). Used as the default when the cashier has
   * not dialled one in, so a fresh install does not ship with clipped edges.
   */
  function defaultMargin (paperMm) {
    return Number(paperMm) === 80 ? 4 : 5
  }

  /**
   * Why this receipt must not be sent to the printer, or null when it may be.
   *
   * Lives here rather than in the IPC handler so it can be tested without
   * Electron -- and because the calibration sheet has NO sale lines on it:
   * refusing every empty receipt also refused the one sheet that measures the
   * printer, so the tool built to diagnose a clipped right edge could never
   * print at all.
   *
   * @param {Receipt|null|undefined} receipt
   * @returns {string|null}
   */
  function refusalReason (receipt) {
    if (!receipt) return 'struk kosong'
    if (receipt.kind === 'calibration') return null
    if (!Array.isArray(receipt.items) || receipt.items.length === 0) return 'struk kosong'

    return null
  }

  /** CSS px -> millimetres at the fixed 96 px per inch print scale. */
  function pxToMm (px) {
    return (Number(px) * 25.4) / 96
  }

  /**
   * Millimetres -> microns.
   *
   * Electron's documentation says the object form of `pageSize` is in inches;
   * the validator says microns ("width and height must be minimum 353
   * microns"), and the source it comes from is keyed `width_microns`. Reading
   * inches here is what made every print attempt die with a RangeError.
   */
  function mmToMicrons (mm) {
    return Math.max(353, Math.round(Number(mm) * 1000))
  }

  /** CSS px -> millimetres, named for callers outside the renderer. */
  function mmFromPx (px) {
    return pxToMm(px)
  }

  /**
   * The options object handed to Electron's webContents.print().
   *
   * Lives here, not in the main process, so the two ways of choosing a page
   * size can be tested without a printer. Electron throws if `pageSize` and
   * `usePrinterDefaultPageSize` are set together, so exactly one is ever added:
   * `driver` follows the printer's own configured form (the escape hatch for
   * drivers that letterbox a silent job into A4), `auto` hands over the exact
   * measured page so the roll is cut at the end of the receipt.
   *
   * @param {object} input
   * @param {'silent'|'dialog'} [input.mode]
   * @param {string|null} [input.deviceName]
   * @param {'auto'|'driver'} [input.pageMode]
   * @param {number} [input.widthMm]
   * @param {number} [input.heightMm]
   */
  function printOptions ({ mode = 'silent', deviceName = null, pageMode = 'auto', widthMm = 58, heightMm = 0 } = {}) {
    const options = {
      silent: mode === 'silent',
      printBackground: true,
      margins: { marginType: 'none' },
      scaleFactor: 1,
      landscape: false,
      header: '',
      footer: '',
      ...(deviceName ? { deviceName } : {})
    }

    if (pageMode === 'driver') {
      options.usePrinterDefaultPageSize = true
    } else {
      options.pageSize = { width: mmToMicrons(widthMm), height: mmToMicrons(heightMm) }
    }

    return options
  }

  /** Extra paper past the last line so the cut lands outside the printed area. */
  const CUT_BUFFER_PX = 20

  /**
   * The paper length the printer is told to use for this content.
   *
   * A thermal roll has no page height -- it is however far the paper is fed --
   * so the page is made exactly as tall as the receipt, less the top offset the
   * cashier dialled in to eat the strip of blank paper the head cannot print on.
   *
   * Both the print window and the on-screen preview call this, so the length
   * quoted in the preview is the length the printer receives.
   */
  function pageSizeMm (receipt, contentHeightPx) {
    const widthMm = Number(receipt.paper) === 80 ? 80 : 58
    const topOffset = clampMm(receipt.layout && receipt.layout.topOffset, 0, 0, 15)
    const heightMm = Math.max(10, pxToMm(contentHeightPx + CUT_BUFFER_PX - topOffset * (96 / 25.4)))

    return { widthMm, heightMm, topOffset }
  }

  /**
   * The calibration sheet.
   *
   * Two symptoms -- a blank strip before the slip and a clipped right edge --
   * have four possible causes, three of which are not ours: the head cannot
   * print its own dead zone, the driver letterboxes a page it thinks is A4, the
   * roll is not the width we think it is, or our own margin is too small. No
   * amount of guessing separates those four.
   *
   * So print the ambiguity instead: this sheet is printed at margin 0 and
   * offset 0, with a frame on all four edges, a millimetre ruler, and the money
   * repeated at a known inset on both sides. Whatever survives the paper is the
   * printable area, and the numbers are read straight off the slip.
   *
   * @param {{paper?: number}} [input]
   */
  function buildCalibrationPaper ({ paper = 58 } = {}) {
    return {
      kind: 'calibration',
      paper: Number(paper) === 80 ? 80 : 58,
      // Deliberately nothing held back: a calibration sheet that respects the
      // current margin cannot show where the margin is needed.
      layout: { margin: 0, topOffset: 0, fontScale: 1 },
      store: {}, order: {}, items: [], totals: {}, footer: ''
    }
  }

  /** The millimetre ruler along the top of the calibration sheet. */
  function calibrationTicks (paperMm) {
    let ticks = ''

    for (let mm = 0; mm <= paperMm; mm++) {
      const major = mm % 5 === 0

      ticks += `<span class="tick${major ? ' major' : ''}"><i></i>${major ? `<b>${mm}</b>` : ''}</span>`
    }

    return `<div class="ticks" data-mm="${paperMm}">${ticks}</div>`
  }

  /**
   * The money at a known inset from one edge, labelled with that inset. The
   * innermost line that prints in full is the dead zone; the first line that is
   * cut is the clipping.
   */
  function calibrationProbes (side) {
    let rows = ''

    for (let mm = 0; mm <= 8; mm++) {
      const money = '<span class="pv">Rp 123.456</span>'
      const label = `<span class="pl">${mm} mm</span>`
      const pad = `padding-${side}: ${mm}mm`

      rows += `<div class="probe" style="${pad}">${side === 'right' ? money + label : label + money}</div>`
    }

    return `<div class="probes">${rows}</div>`
  }

  /** One millimetre tall bars, so the first line of ink shows where the head starts. */
  function calibrationTopBars () {
    let bars = ''

    for (let mm = 1; mm <= 12; mm++) {
      bars += `<div class="bar-row"><span class="bar"></span><span class="pl">${mm} mm</span></div>`
    }

    return `<div class="bars">${bars}</div>`
  }

  function calibrationMarkup (paperMm) {
    return `
      <div class="cal">
        <div class="cal-head">KALIBRASI KERTAS ${paperMm}mm</div>
        <div class="cal-frame">
          ${calibrationTicks(paperMm)}
          ${calibrationTopBars()}
          <div class="cal-note">
            1. Garis kotak hilang atau terpotong = area mati printer. Catat angkanya dari tepi.
          </div>
          ${calibrationProbes('left')}
          ${calibrationProbes('right')}
          <div class="cal-note">
            2. Kolom "Rp 123.456" diulang tiap 1mm dari tepi.
            Angka paling dalam yang tercetak utuh = batas area cetak.
          </div>
        </div>
      </div>
    `
  }

  return {
    buildReceipt,
    buildCalibrationPaper,
    renderReceiptHtml,
    receiptCss,
    pageSizeMm,
    printOptions,
    itemDiscountAmount,
    clampMm,
    refusalReason,
    mmToMicrons,
    mmFromPx,
    PAYMENT_LABEL,
    TYPE_LABEL
  }
})
