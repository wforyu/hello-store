'use strict'

/**
 * Layout regression guard. Runs the real renderer in an offscreen Electron
 * window and asserts the first-run screen is the login card.
 *
 * This exists because of a bug the unit suite could never catch: every panel
 * in index.html is toggled with the `hidden` attribute, but the UA rule that
 * implements it loses to any author `display` value, and .modal / .drawer /
 * .login all set one. With `display: grid` winning, a `hidden` modal stayed
 * `position: fixed; inset: 0; z-index: 40` and covered the login screen -- the
 * till opened straight onto the "Alamat server / Client ID" settings dialog.
 *
 *   npx electron test/check-layout.js      (add npm run check:layout)
 *
 * Exits non-zero when anything leaks, so it can gate a build.
 */

const { app, BrowserWindow, ipcMain, Menu } = require('electron')
const path = require('node:path')
const { installMenu } = require('../src/main/menu')
const { renderReceipt } = require('../src/main/print')

const ROOT = path.join(__dirname, '..')

/* Minimal stand-in for the main process: boot() only needs the state call to
   resolve, and with user = null it must fall through to the login screen. */
const loggedOutState = {
  online: false,
  syncing: false,
  lastError: null,
  lastSyncAt: null,
  stats: { products: 0, pendingSync: 0, conflicts: 0, rejected: 0, todayOrders: 0, todayRevenue: 0 },
  posConfig: { store_name: 'Hello Store', ppn_enabled: false, ppn_percentage: 11 },
  user: null,
  apiUrl: 'http://localhost:8000',
  clientId: 'layout-check',
  printer: { name: null, paper: 58, pageMode: 'auto', layout: { margin: 3, topOffset: 0, fontScale: 1 } }
}

ipcMain.handle('pos:state', () => loggedOutState)
ipcMain.handle('pos:version', () => '9.9.9')
ipcMain.handle('pos:api-url:get', () => ({ url: loggedOutState.apiUrl }))
ipcMain.handle('pos:api-url:set', () => ({ url: loggedOutState.apiUrl }))
ipcMain.handle('pos:products', () => [])
ipcMain.handle('pos:categories', () => [])
ipcMain.handle('pos:orders', () => [])
ipcMain.handle('pos:printers', () => [
  {
    name: 'POS-58',
    displayName: 'POS-58 (Generic / Text Only)',
    isDefault: false,
    status: 0,
    // A driver left on A4 letterboxes whatever page it is handed -- the exact
    // cause of a blank strip before the slip and a lost right edge. The panel
    // has to say so out loud.
    options: [
      { name: 'Print Quality', value: 'Normal' },
      { name: 'Copies', value: '1' }
    ]
  },
  {
    name: 'Microsoft Print to PDF',
    displayName: 'Microsoft Print to PDF',
    isDefault: true,
    status: 0,
    options: [{ name: 'Print Quality', value: '600 dpi' }]
  },
  {
    name: 'POS-80',
    displayName: 'POS-80 (roll)',
    isDefault: false,
    status: 0,
    options: []
  }
])
// The driver's paper size is queried over its own IPC in production (PowerShell
// on Windows); stubbed here so the fixed-paper warning is exercised without a
// shell. Values mirror what Get-PrintConfiguration actually returns.
ipcMain.handle('pos:printer:paper', (_e, name) => {
  if (name === 'POS-80') return '80 x 297 mm'
  if (name === 'POS-58' || name === 'Microsoft Print to PDF') return 'A4'
  return null
})
ipcMain.handle('pos:printer:set', (_e, { name, paper, layout = {}, pageMode } = {}) => ({
  name: name || null,
  paper: paper === 80 || paper === '80' ? 80 : 58,
  pageMode: pageMode === 'driver' ? 'driver' : 'auto',
  layout: {
    margin: Number(layout.margin) || 0,
    topOffset: Number(layout.topOffset) || 0,
    fontScale: Number(layout.fontScale) || 1
  }
}))
ipcMain.handle('pos:receipt:print', () => ({ printed: true, deviceName: 'POS-58' }))

/** Enough of the store for the About dialog and the admin-panel link. */
const metaStub = { getMeta: (key) => loggedOutState[key === 'api_url' ? 'apiUrl' : 'clientId'] }

app.whenReady().then(async () => {
  const win = new BrowserWindow({
    width: 1440,
    height: 900,
    show: false,
    webPreferences: {
      offscreen: true,
      sandbox: true,
      contextIsolation: true,
      nodeIntegration: false,
      preload: path.join(ROOT, 'src', 'main', 'preload.js')
    }
  })

  const errors = []
  win.webContents.on('console-message', (_e, _level, message) => {
    if (message.startsWith('[renderer]')) errors.push(message)
  })

  await win.loadFile(path.join(ROOT, 'src', 'renderer', 'index.html'))
  await new Promise((resolve) => setTimeout(resolve, 2500))

  const report = await win.webContents.executeJavaScript(`
    (() => {
      const leaking = []
      for (const el of document.querySelectorAll('[hidden]')) {
        if (getComputedStyle(el).display !== 'none') leaking.push(el.id || el.tagName)
      }
      return {
        leaking,
        topLevelVisible: [...document.querySelectorAll('body > *')]
          .filter((el) => getComputedStyle(el).display !== 'none')
          .map((el) => el.id || el.tagName)
      }
    })()
  `)

  const failures = []

  if (report.leaking.length) {
    failures.push(`[hidden] elements that still render: ${report.leaking.join(', ')}`)
  }
  if (report.topLevelVisible.join() !== 'login') {
    failures.push(`first screen should be login only, got: ${report.topLevelVisible.join(', ')}`)
  }

  // Building the menu is the only test for it: a duplicated accelerator makes
  // Electron throw while installing, which is a crash on a fresh boot.
  installMenu({
    getWindow: () => win,
    store: () => metaStub,
    isDev: false,
    confirmQuit: () => true
  })

  const items = Menu.getApplicationMenu().items
  const labels = items.map((item) => item.label)
  const accelerators = []

  for (const item of items) {
    if (item.accelerator) accelerators.push(item.accelerator)
    for (const child of item.submenu?.items || []) {
      if (child.accelerator) accelerators.push(child.accelerator)
    }
  }

  const duplicates = accelerators.filter((a, i) => accelerators.indexOf(a) !== i)

  if (duplicates.length) {
    failures.push(`accelerator dobel: ${[...new Set(duplicates)].join(', ')}`)
  }

  // The menu is useless if its actions never reach the renderer, so drive one
  // over IPC and check the panel actually opened.
  win.webContents.send('pos:menu', 'settings')
  await new Promise((resolve) => setTimeout(resolve, 300))

  const settingsOpened = await win.webContents.executeJavaScript(
    "getComputedStyle(document.getElementById('settings')).display !== 'none'"
  )

  if (!settingsOpened) {
    failures.push('menu File > Pengaturan Perangkat tidak membuka panelnya')
  }

  // The printer dropdown is filled from the OS, which only happens over IPC, so
  // an empty list here means the printing path is dead on a real machine too.
  const printerOptions = await win.webContents.executeJavaScript(
    "document.getElementById('set-printer').options.length"
  )

  if (printerOptions < 3) {
    failures.push(`daftar printer tidak termuat: ${printerOptions} opsi`)
  }

  /* --- what the driver says about the paper --------------------------
     Two of the four reasons a thermal receipt loses its right edge cannot be
     fixed from here, and they are only visible here: a driver set to a fixed
     page letterboxes whatever page we hand it. Quietly tuning margins against
     that is how a cashier spends an afternoon changing settings and getting an
     identical slip. The paper size arrives asynchronously (real builds shell
     out to Windows), so the panel is given a beat to finish rendering. */
  const selectPrinter = (name) => `(() => {
    const select = document.getElementById('set-printer')
    select.value = ${JSON.stringify(name)}
    select.dispatchEvent(new Event('change'))
  })()`

  const readPrinterInfo = () =>
    win.webContents.executeJavaScript("document.getElementById('printer-info').innerText")

  await win.webContents.executeJavaScript(selectPrinter('POS-58'))
  await new Promise((resolve) => setTimeout(resolve, 400))
  const onFixedPage = await readPrinterInfo()

  if (!onFixedPage.includes('A4')) {
    failures.push(`ukuran kertas driver tidak ditampilkan: "${onFixedPage}"`)
  }

  if (!onFixedPage.includes('kertas tetap')) {
    failures.push('driver di kertas tetap (A4) tidak diberi peringatan roll')
  }

  await win.webContents.executeJavaScript(selectPrinter('POS-80'))
  await new Promise((resolve) => setTimeout(resolve, 400))
  const onRoll = await readPrinterInfo()

  if (onRoll.includes('kertas tetap')) {
    failures.push('driver yang sudah roll tetap diberi peringatan kertas tetap')
  }

  if (!onRoll.includes('80 x 297 mm')) {
    failures.push(`ukuran kertas driver roll tidak ditampilkan: "${onRoll}"`)
  }

  // The version has to be visible: a cashier reporting "it did not change" is
  // usually running a different build than the one that was just fixed.
  if (!/\d+\.\d+\.\d+/.test(onFixedPage)) {
    failures.push(`versi aplikasi tidak tampil di pengaturan: "${onFixedPage}"`)
  }

  /* --- the preview ---------------------------------------------------
     The preview is what stops the cashier burning paper on surprises, so it
     has to draw the slip with the same function the printer uses, and it has
     to redraw when a setting changes. */
  const preview = await win.webContents.executeJavaScript(`(() => {
    const paper = document.getElementById('preview-paper')
    return {
      text: paper.innerText,
      size: document.getElementById('preview-size').textContent,
      // style.width, not getBoundingClientRect: the preview is scaled down to
      // fit the panel, and the unscaled millimetre width is the real thing.
      width: Math.round(parseFloat(paper.style.width))
    }
  })()`)

  if (!preview.text.includes('Hello Store')) {
    failures.push('pratinjau struk kosong, tidak memuat isi struk')
  }

  if (!/^58 x \d+ mm$/.test(preview.size)) {
    failures.push(`pratinjau tidak menampilkan ukuran kertas: "${preview.size}"`)
  }

  // 58mm at the 96px/inch print scale is 219px. A preview box that is not the
  // paper's real width wraps differently and quotes the wrong paper length.
  if (Math.abs(preview.width - 219) > 2) {
    failures.push(`lebar pratinjau bukan 58mm sungguhan: ${preview.width}px, harus ~219px`)
  }

  const previewWide = await win.webContents.executeJavaScript(`(() => {
    const select = document.getElementById('set-paper')
    select.value = '80'
    select.dispatchEvent(new Event('change'))
    return Math.round(parseFloat(document.getElementById('preview-paper').style.width))
  })()`)

  if (Math.abs(previewWide - 302) > 2) {
    failures.push(`pratinjau tidak ikut lebar kertas yang dipilih: ${previewWide}px, harus ~302px`)
  }

  // The compensation for the blank leading strip has to show in the preview
  // too, otherwise the cashier is tuning blind.
  const previewOffset = await win.webContents.executeJavaScript(`(() => {
    const input = document.getElementById('set-top-offset')
    input.value = '5'
    input.dispatchEvent(new Event('input'))
    const before = document.getElementById('preview-size').textContent
    input.value = '0'
    input.dispatchEvent(new Event('input'))
    return before + ' -> ' + document.getElementById('preview-size').textContent
  })()`)

  await win.webContents.executeJavaScript(`(() => {
    const select = document.getElementById('set-paper')
    select.value = '58'
    select.dispatchEvent(new Event('change'))
  })()`)

  // Same for printing: with no sale yet F9 must report that, not fail silently.
  win.webContents.send('pos:menu', 'print')
  await new Promise((resolve) => setTimeout(resolve, 300))

  const printToast = await win.webContents.executeJavaScript(
    "document.getElementById('toast').hidden ? '' : document.getElementById('toast').textContent"
  )

  if (!printToast) {
    failures.push('menu File > Cetak Struk tidak memberi umpan balik ke kasir')
  }

  /* --- the receipt page itself ---------------------------------------
     The receipt is the one screen that cannot be checked by unit tests: it is
     built in a hidden window and its height becomes the paper length. If the
     template throws, a blank slip comes out of the printer and the sale still
     looks successful on screen -- so render it for real and measure. */
  const smallReceipt = {
    store: { name: 'Hello Store', address: 'Jl. Merdeka 10', phone: '0812', whatsapp: '' },
    order: { number: 'POS-000001', at: new Date().toISOString(), type: 'dine_in', customer: '', cashier: 'Kasir', payment: 'cash', pending: false },
    items: [{ name: 'Kopi', qty: 1, price: 18000, discount: 0, total: 18000 }],
    totals: { subtotal: 18000, itemDiscount: 0, globalDiscount: 0, ppnLabel: 'PPN 11%', ppn: 0, total: 18000, paid: 20000, change: 2000 },
    paper: 58,
    layout: { margin: 3, topOffset: 0, fontScale: 1 },
    footer: 'Terima kasih sudah berbelanja'
  }

  const bigReceipt = {
    ...smallReceipt,
    items: Array.from({ length: 30 }, (_, i) => ({
      name: `Produk Nomor ${i + 1}`, qty: 2, price: 15000 + i * 100, discount: 0, total: (15000 + i * 100) * 2
    }))
  }

  let smallHeight = 0
  let bigHeight = 0
  let pageRule = ''
  let pdfSize = ''
  let rightInsetMm = 0
  let offsetHeightMm = ''

  try {
    const small = await renderReceipt(smallReceipt)
    smallHeight = small.heightPx
    const rendered = await small.window.webContents.executeJavaScript(
      'document.getElementById("page-size").textContent'
    )
    pageRule = rendered.trim()
    const smallText = await small.window.webContents.executeJavaScript('document.getElementById("paper").innerText')

    /* --- the right edge of the money --------------------------------
       A thermal head cannot print in the last few millimetres of the roll,
       so text laid out edge to edge loses the right of every rupiah amount --
       the number the customer is holding the receipt to check. Measure the
       real gap between the paper's right edge and the right-most text, and
       prove the cashier's margin setting is what moves it. */
    const measureInset = `(function () {
      const paper = document.getElementById('paper')
      const right = paper.getBoundingClientRect().right
      let gap = 0
      for (const el of paper.querySelectorAll('.row span:last-child, td.num, .grand span:last-child')) {
        const box = el.getBoundingClientRect()
        if (box.width) gap = Math.max(gap, right - box.right)
      }
      return gap
    })()`

    rightInsetMm = ((await small.window.webContents.executeJavaScript(measureInset)) / 96) * 25.4
    const bodyMarginTop = await small.window.webContents.executeJavaScript(
      'getComputedStyle(document.body).marginTop'
    )

    small.window.destroy()

    // The 3mm inset must actually appear as ~3mm of air, not as a stylesheet
    // value nobody can see the effect of.
    if (Math.abs(rightInsetMm - 3) > 0.6) {
      failures.push(`teks tidak menjauh dari tepi kanan: ${rightInsetMm.toFixed(1)}mm, yang diatur 3mm`)
    }

    if (parseFloat(bodyMarginTop) !== 0) {
      failures.push(`margin atas halaman tidak nol: ${bodyMarginTop}`)
    }

    if (!smallText.includes('Hello Store')) failures.push('kepala struk tidak memuat nama toko')
    if (!smallText.includes('POS-000001')) failures.push('struk tidak memuat nomor pesanan')
    if (!smallText.includes('KEMBALI')) failures.push('struk tidak memuat kembalian')

    // The paper length has to be expressed in CSS millimetres. Electron's own
    // `pageSize` option is measured in microns (its docs say inches) and a
    // 2.28 "inch" value is rejected with a RangeError about 353 microns -- the
    // receipt would then be a promise that never prints.
    if (!/size:\s*58mm\s+[\d.]+mm/.test(pageRule)) {
      failures.push(`aturan @page structuring salah: "${pageRule}"`)
    }

    // The quoted paper length and the length the printer is told have to come
    // out of the same formula, or the preview is a decoration.
    const { pageSizeMm } = require('../src/renderer/receipt')
    const quoted = pageSizeMm(smallReceipt, smallHeight).heightMm
    const actual = Number((pageRule.match(/size:\s*\S+\s+([\d.]+)mm/) || [])[1])

    if (Math.abs(quoted - actual) > 0.05) {
      failures.push(`panjang kertas di @page ${actual}mm, hasil rumus ${quoted.toFixed(2)}mm`)
    }

    const big = await renderReceipt(bigReceipt)
    bigHeight = big.heightPx
    big.window.destroy()

    /* --- the leading blank strip ------------------------------------
       A thermal head cannot print in the first few millimetres of the roll
       either, so the paper feeds a strip of nothing before the first line and
       the cashier throws it away every single time. The compensation setting
       pulls the content up into that strip and shortens the page by the same
       amount, so the strip turns back into paper that carries text. */
    const offset = await renderReceipt({
      ...smallReceipt,
      layout: { margin: 3, topOffset: 5, fontScale: 1 }
    })
    const offsetRule = await offset.window.webContents.executeJavaScript(
      'document.getElementById("page-size").textContent'
    )
    const offsetBody = await offset.window.webContents.executeJavaScript(
      'getComputedStyle(document.body).marginTop'
    )
    offset.window.destroy()

    const heightMmOf = (rule) => Number((rule.match(/size:\s*\S+\s+([\d.]+)mm/) || [])[1])
    const baseMm = heightMmOf(pageRule)
    const shiftedMm = heightMmOf(offsetRule)

    offsetHeightMm = `${baseMm.toFixed(2)} -> ${shiftedMm.toFixed(2)}mm`

    if (Math.abs(baseMm - shiftedMm - 5) > 0.3) {
      failures.push(`kompensasi atas tidak memotong panjang kertas: ${baseMm.toFixed(2)}mm -> ${shiftedMm.toFixed(2)}mm, kurangnya harus 5mm`)
    }

    if (Math.abs(parseFloat(offsetBody) + 5 * (96 / 25.4)) > 1.5) {
      failures.push(`konten tidak naik mengikuti kompensasi atas: margin-top body = ${offsetBody}`)
    }
  } catch (error) {
    failures.push(`halaman struk gagal dirender: ${error.message}`)
  }

  // A fixed page size is the classic thermal-printer bug: a long basket gets
  // cut in half, or the roll feeds out a metre of blank paper.
  if (!(bigHeight > smallHeight * 2)) {
    failures.push(`tinggi struk tidak ikut grows: 1 item = ${smallHeight}px, 30 item = ${bigHeight}px`)
  }

  if (!(smallHeight > 100 && smallHeight < 4000)) {
    failures.push(`tinggi struk 1 item di luar wajar: ${smallHeight}px`)
  }

  /* --- the paper size that actually reaches the printer --------------
     printToPDF uses the same page-size plumbing as print() but needs no
     printer, so the rendered page can be measured for real. This is the
     regression guard for "height and width must be minimum 353 microns":
     Electron's own `pageSize` option is in microns despite being documented
     in inches, and passing inches there made every print throw. The size has
     to come from the receipt's @page rule. */
  try {
    const wide = await renderReceipt({ ...smallReceipt, paper: 80 })
    const wideHeight = wide.heightPx
    const pdf = await wide.window.webContents.printToPDF({
      printBackground: true,
      preferCSSPageSize: true,
      pageSize: 'A4'
    })
    wide.window.destroy()

    // PDF user space is 1/72 inch.
    const box = pdf.toString('latin1').match(/MediaBox\s*\[\s*[\d.]+\s+[\d.]+\s+([\d.]+)\s+([\d.]+)/)

    if (!box) {
      failures.push('ukuran halaman PDF tidak bisa dibaca, cek assertion-nya')
    } else {
      const widthMm = (Number(box[1]) / 72) * 25.4
      const heightMm = (Number(box[2]) / 72) * 25.4
      // The 80mm sheet is measured separately: its own content, not the 58mm one.
      // CUT_BUFFER_PX is the air past the last line so the cut lands clear of it.
      const expectedMm = (wideHeight + 20) * (25.4 / 96)

      pdfSize = `${widthMm.toFixed(1)} x ${heightMm.toFixed(1)} mm`

      if (Math.abs(widthMm - 80) > 1.5) {
        failures.push(`lebar kertas 80mm jadi ${widthMm.toFixed(1)}mm di PDF`)
      }

      if (Math.abs(heightMm - expectedMm) > 2) {
        failures.push(`panjang kertas ${heightMm.toFixed(1)}mm, seharusnya ~${expectedMm.toFixed(1)}mm`)
      }
    }
  } catch (error) {
    failures.push(`ukuran kertas tidak bisa diverifikasi: ${error.message}`)
  }

  console.log(`  top-level visible : ${report.topLevelVisible.join(', ') || '(kosong)'}`)
  console.log(`  leaking [hidden]  : ${report.leaking.join(', ') || '(tidak ada)'}`)
  console.log(`  menu              : ${labels.join(' | ')}`)
  console.log(`  accelerator       : ${accelerators.length} (dobel: ${duplicates.length})`)
  console.log(`  menu -> renderer  : ${settingsOpened ? 'panel Pengaturan terbuka' : 'GAGAL'}`)
  console.log(`  printer           : ${printerOptions} opsi`)
  console.log(`  pratinjau         : ${preview.size} · lebar ${preview.width}px → ${previewWide}px · offset ${previewOffset}`)
  console.log(`  cetak struk      : ${printToast || 'TIDAK ADA UMPAN BALIK'}`)
  console.log(`  tinggi struk      : 1 item ${smallHeight}px · 30 item ${bigHeight}px`)
  console.log(`  @page             : ${pageRule || '(tidak ada)'}`)
  console.log(`  kompensasi atas   : ${offsetHeightMm || '(tidak diukur)'}`)
  console.log(`  jarak tepi kanan : ${rightInsetMm.toFixed(1)}mm`)
  console.log(`  kertas ke printer : ${pdfSize || '(tidak diukur)'}`)

  if (errors.length) {
    for (const line of errors) console.log(`  ${line}`)
  }

  if (failures.length) {
    console.error('\nLAYOUT GAGAL:')
    for (const line of failures) console.error(`  - ${line}`)
    app.exit(1)
  } else {
    console.log('  OK   layar pertama = login, menu terpasang, printer dan cetak terhubung')
    app.exit(0)
  }
})
