'use strict'

const { app, BrowserWindow, ipcMain, dialog, shell, screen } = require('electron')
const path = require('node:path')
const fs = require('node:fs')

const store = require('./store')
const { PosApi, DEFAULT_API_URL } = require('./client')
const { PosSync, quote } = require('./sync')
const { installMenu } = require('./menu')
const { printReceipt, listPrinters, queryDriverPaper } = require('./print')
const { refusalReason } = require('../renderer/receipt')

const isDev = process.argv.includes('--dev')

let win = null
let api = null
let sync = null
let allowClose = false

/* ------------------------------------------------------------------ *
 * window
 * ------------------------------------------------------------------ */

function createWindow () {
  // Never open larger than the display: a till that runs off the bottom edge
  // loses the cart panel to a taskbar. The Window menu can still maximize.
  const area = screen.getPrimaryDisplay().workAreaSize

  win = new BrowserWindow({
    width: Math.min(1440, area.width),
    height: Math.min(900, area.height),
    minWidth: 1180,
    minHeight: 700,
    backgroundColor: '#0f172a',
    show: false,
    title: 'Hello Store POS',
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: true,
      spellcheck: false
    }
  })

  win.loadFile(path.join(__dirname, '..', 'renderer', 'index.html'))

  win.once('ready-to-show', () => win.show())

  // A till should never navigate away from the POS, and should never be able
  // to spawn a second window.
  win.webContents.setWindowOpenHandler(({ url }) => {
    if (url.startsWith('https://')) shell.openExternal(url)
    return { action: 'deny' }
  })

  win.on('closed', () => {
    win = null
  })

  // Closing with unsynced sales is allowed but never silent: the outbox is
  // durable, so the money is safe, but the cashier should know it is still
  // waiting rather than assume the night is finished.
  win.on('close', (event) => {
    if (allowClose) return

    event.preventDefault()

    if (confirmQuit()) {
      allowClose = true
      win.close()
    }
  })

  if (isDev) win.webContents.openDevTools({ mode: 'detach' })
}

/** How many sales are still sitting in the outbox, 0 if the store is unreadable. */
function pendingSyncCount () {
  try {
    return store?.stats().pendingSync ?? 0
  } catch {
    return 0
  }
}

/** @returns {boolean} true to proceed with closing/quitting. */
function confirmQuit () {
  const pending = pendingSyncCount()

  if (pending === 0) return true

  const choice = dialog.showMessageBoxSync(win, {
    type: 'warning',
    title: 'Transaksi Belum Terkirim',
    message: `${pending} transaksi belum terkirim ke server.`,
    detail:
      'Antrean tersimpan di komputer ini dan dikirim otomatis saat aplikasi dibuka lagi. ' +
      'Tidak ada penjualan yang hilang, tapi angka di panel admin belum termasuk.',
    buttons: ['Batal', 'Keluar Tetap'],
    defaultId: 0,
    cancelId: 0,
    noLink: true
  })

  return choice === 1
}

/**
 * Push state to the renderer. Everything that can change the header strip --
 * connectivity, outbox depth, conflict count -- funnels through here.
 */
function broadcast () {
  if (win && !win.isDestroyed()) {
    win.webContents.send('pos:changed', sync.state())
  }
}

/* ------------------------------------------------------------------ *
 * ipc
 * ------------------------------------------------------------------ */

/**
 * Printer choice and layout, kept in the local database so they survive
 * restarts. The three numbers are the ones a thermal printer actually needs
 * dialling in by hand: how far in the text sits from the edge of the roll, how
 * much of the leading strip is unprintable, and how big the type is.
 */
function printerSettings () {
  // NOT `Number(x) || fallback`: an unset meta is null (Number -> 0, wrong for
  // margin), and a saved "0" is falsy too -- the cashier dialling the margin to
  // zero got 3 back without ever seeing it change.
  const metaNum = (key, fallback) => {
    const raw = store.getMeta(key)

    if (raw === null || raw === undefined || raw === '') return fallback

    const n = Number(raw)

    return Number.isFinite(n) ? n : fallback
  }

  return {
    name: store.getMeta('printer_name') || null,
    paper: store.getMeta('paper_mm') === '80' ? 80 : 58,
    // Who decides the paper size: 'auto' hands the printer the receipt's exact
    // measured page, 'driver' lets the printer's own driver form win. The
    // second is the fallback for drivers that ignore our page size on a silent
    // job and letterbox the slip into A4.
    pageMode: store.getMeta('paper_mode') === 'driver' ? 'driver' : 'auto',
    layout: {
      margin: metaNum('paper_margin_mm', 3),
      topOffset: metaNum('paper_top_offset_mm', 0),
      fontScale: metaNum('paper_font_scale', 1)
    }
  }
}

function registerIpc () {
  ipcMain.handle('pos:state', () => ({ ...sync.state(), printer: printerSettings() }))

  ipcMain.handle('pos:login', async (_event, { email, password }) => {
    const user = await api.login(email, password)
    await api.config()
    await api.catalog({ since: store.getMeta('catalog_cursor') })
    await sync.flush()
    return { user, state: sync.state() }
  })

  ipcMain.handle('pos:logout', () => {
    api.logout()
    broadcast()
    return { ok: true }
  })

  ipcMain.handle('pos:api-url:get', () => ({
    url: store.getMeta('api_url') || DEFAULT_API_URL
  }))

  ipcMain.handle('pos:api-url:set', async (_event, url) => {
    api.setApiUrl(url)
    await sync.checkOnline()
    if (sync.online) await api.catalog({ since: null })
    broadcast()
    return { url: store.getMeta('api_url'), state: sync.state() }
  })

  ipcMain.handle('pos:config', async () => {
    const config = await api.config()
    broadcast()
    return config
  })

  ipcMain.handle('pos:products', (_event, filters = {}) => store.listProducts(filters))
  ipcMain.handle('pos:categories', () => store.listCategories())

  ipcMain.handle('pos:catalog:refresh', async () => {
    const result = await api.catalog({ since: store.getMeta('catalog_cursor') })
    broadcast()
    return { received: (result.products || []).length, serverTime: result.server_time }
  })

  ipcMain.handle('pos:quote', (_event, { cart, options }) =>
    quote(cart || [], options || {}, store.getJson('pos_config', {}))
  )

  ipcMain.handle('pos:checkout', (_event, { cart, options }) => sync.checkout(cart || [], options || {}))

  ipcMain.handle('pos:sync-now', async () => {
    const result = await sync.flush()
    broadcast()
    return result
  })

  ipcMain.handle('pos:reconcile', async () => {
    const result = await sync.reconcile()
    broadcast()
    return result
  })

  ipcMain.handle('pos:orders', (_event, limit = 50) => store.listOrders(Number(limit) || 50))

  ipcMain.handle('pos:clear-rejected', () => {
    store.setMeta('rejected_ack_at', new Date().toISOString())
    return { ok: true }
  })

  ipcMain.handle('pos:db:export', async () => {
    const result = await dialog.showSaveDialog(win, {
      title: 'Ekspor database POS',
      defaultPath: `pos-backup-${new Date().toISOString().slice(0, 10)}.sqlite`,
      filters: [{ name: 'SQLite', extensions: ['sqlite'] }]
    })

    if (result.canceled || !result.filePath) return { canceled: true }

    fs.writeFileSync(result.filePath, store.exportDatabase())

    return { canceled: false, path: result.filePath }
  })

  /* --- printing ---------------------------------------------------- *
   * Silent printing to the remembered printer is the everyday path -- the
   * cashier should not pick a printer per sale. Until one is configured the
   * OS print dialog is shown instead, so printing works on a fresh install
   * rather than only after setup.                                       */

  ipcMain.handle('pos:printers', async () => listPrinters(win))
  // Shown in the print settings so "it did not change" can be told apart from
  // "I am still running last week's build".
  ipcMain.handle('pos:version', () => app.getVersion())

  ipcMain.handle('pos:printer:set', (_event, { name, paper, layout = {}, pageMode } = {}) => {
    const clamp = (value, min, max, fallback) => {
      const n = Number(value)

      return Number.isFinite(n) ? Math.min(max, Math.max(min, n)) : fallback
    }

    store.setMeta('printer_name', name || null)
    store.setMeta('paper_mm', paper === 80 || paper === '80' ? '80' : '58')
    store.setMeta('paper_mode', pageMode === 'driver' ? 'driver' : 'auto')
    store.setMeta('paper_margin_mm', String(clamp(layout.margin, 0, 8, 3)))
    store.setMeta('paper_top_offset_mm', String(clamp(layout.topOffset, 0, 15, 0)))
    store.setMeta('paper_font_scale', String(clamp(layout.fontScale, 0.8, 1.6, 1)))

    return printerSettings()
  })

  ipcMain.handle('pos:order:one', (_event, clientRef) => store.getOrder(clientRef))

  ipcMain.handle('pos:receipt:print', async (_event, receipt) => {
    // Empty baskets are refused, but the calibration sheet has no sale lines
    // and must go through: it is the tool that measures this printer.
    const refused = refusalReason(receipt)

    if (refused) return { printed: false, reason: refused }

    const deviceName = store.getMeta('printer_name')
    const mode = deviceName ? 'silent' : 'dialog'
    const paper = store.getMeta('paper_mm') === '80' ? 80 : 58
    const pageMode = store.getMeta('paper_mode') === 'driver' ? 'driver' : 'auto'
    const layout = printerSettings().layout
    // The calibration sheet is printed with nothing held back on purpose: it
    // exists to show where the printer can actually reach, so the cashier's
    // margin and offset must not be applied on top of it.
    const calibration = receipt.kind === 'calibration'

    return printReceipt({ ...receipt, paper, layout: calibration ? receipt.layout : layout }, { mode, deviceName, pageMode })
  })

  /**
   * The paper size the SELECTED printer's driver currently has -- the value
   * that decides whether Windows letterboxes our page (blank strip in front of
   * the slip, right edge lost). Electron's PrinterInfo does not expose it, so
   * it is read from the driver itself; see queryDriverPaper().
   */
  ipcMain.handle('pos:printer:paper', (_event, name) => queryDriverPaper(name))
}

/* ------------------------------------------------------------------ *
 * boot
 * ------------------------------------------------------------------ */

app.whenReady().then(async () => {
  await store.init(app.getPath('userData'))

  if (!store.getMeta('api_url')) store.setMeta('api_url', DEFAULT_API_URL)

  api = new PosApi(store)
  sync = new PosSync(store, api, { onChange: broadcast })

  registerIpc()
  createWindow()

  installMenu({
    getWindow: () => win,
    store: () => store,
    isDev,
    confirmQuit
  })

  // Warm the cache and drain the outbox without blocking first paint.
  sync.bootstrap().catch((error) => {
    console.error('[pos] bootstrap failed:', error.message)
  })

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) createWindow()
  })
})

app.on('window-all-closed', () => {
  sync?.stop()
  store.close()
  allowClose = true
  app.quit()
})

app.on('before-quit', (event) => {
  sync?.stop()

  if (allowClose) return

  // Reached through File > Keluar or the window X; ask before dropping the
  // cashier out with a queue still full.
  if (!confirmQuit()) {
    event.preventDefault()
    return
  }

  allowClose = true
})

process.on('uncaughtException', (error) => {
  console.error('[pos] uncaught:', error)
})
