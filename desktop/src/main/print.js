'use strict'

/**
 * Receipt printing.
 *
 * Two things make this more than `webContents.print()`:
 *
 * 1. A thermal roll has no page size, only a width. The page height is measured
 *    from the rendered receipt and handed to the printer as an exact custom
 *    page, so an 8-item sale doesn't roll out a sheet of blank paper and a
 *    30-item one doesn't get cut in half.
 * 2. The receipt is rendered in a hidden window, not the till window. Printing
 *    the live window would print the product grid, and reusing the visible
 *    window would make the cashier watch a flash of white on every sale.
 */

const { BrowserWindow, ipcMain } = require('electron')
const { execFile } = require('node:child_process')
const path = require('node:path')
const { mmFromPx, printOptions } = require('../renderer/receipt')

/** Thermal paper widths in millimetres -- the unit the receipt's CSS @page uses. */
const PAPER_WIDTH_MM = { 58: 58, 80: 80 }

/** The receipt currently being printed, handed to the hidden window on request. */
let current = null

/**
 * The hidden window pulls its payload over IPC rather than through a query
 * string: a 40-item basket serialises to a few kilobytes, which is fine in a
 * message and nowhere near a URL length limit.
 */
ipcMain.handle('pos:receipt:data', () => current?.receipt ?? null)

/**
 * @typedef {object} Receipt
 * @property {object} store   { name, address, phone, whatsapp }
 * @property {object} order   { number, at, type, customer, cashier, payment }
 * @property {Array<{name: string, qty: number, price: number, discount: number, total: number}>} items
 * @property {object} totals  { subtotal, itemDiscount, globalDiscount, ppnLabel, ppn, total, paid, change }
 * @property {number} paper   58 or 80
 * @property {string} footer  optional closing line
 */

/**
 * Load the receipt page offscreen and wait until it reports its height.
 *
 * Split out from `printReceipt()` so the height measurement -- the part that
 * makes a thermal roll cut at the right place -- can be tested without a
 * printer being installed on the machine running the tests.
 *
 * @param {Receipt} receipt
 * @returns {Promise<{window: BrowserWindow, heightPx: number}>}
 */
function renderReceipt (receipt) {
  return new Promise((resolve, reject) => {
    current = { receipt }

    const win = new BrowserWindow({
      show: false,
      width: 400,
      height: 600,
      webPreferences: {
        preload: path.join(__dirname, 'print-preload.js'),
        contextIsolation: true,
        nodeIntegration: false,
        sandbox: true,
        // Without this a hidden window has its timers throttled to the point
        // where measuring the receipt can take seconds on a busy till.
        backgroundThrottling: false
      }
    })

    let settled = false

    const done = (fn) => {
      if (settled) return
      settled = true
      current = null
      ipcMain.removeListener('pos:receipt:measured', onMeasured)
      ipcMain.removeListener('pos:receipt:failed', onFailed)
      fn()
    }

    const onMeasured = (_event, size) => done(() => {
      const widthMm = Number(size && size.widthMm) || (Number(receipt.paper) === 80 ? 80 : 58)
      const heightMm = Number(size && size.heightMm) || mmFromPx(Number(size && size.heightPx) || 0)

      resolve({
        window: win,
        heightPx: Number(size && size.heightPx) || 0,
        widthMm,
        heightMm: Math.max(10, heightMm)
      })
    })

    const onFailed = (_event, message) => {
      if (!win.isDestroyed()) win.destroy()
      done(() => reject(new Error(message)))
    }

    win.webContents.on('render-process-gone', (_event, details) => {
      if (!win.isDestroyed()) win.destroy()
      done(() => reject(new Error(`halaman struk gagal: ${details.reason}`)))
    })

    ipcMain.once('pos:receipt:measured', onMeasured)
    ipcMain.once('pos:receipt:failed', onFailed)

    win.loadFile(path.join(__dirname, '..', 'renderer', 'receipt.html'))
      .catch((error) => {
        if (!win.isDestroyed()) win.destroy()
        done(() => reject(error))
      })
  })
}

/**
 * @param {Receipt} receipt
 * @param {object} options
 * @param {'silent'|'dialog'} [options.mode]      silent = straight to the remembered printer
 * @param {string|null} [options.deviceName]      printer the job goes to
 * @param {'auto'|'driver'} [options.pageMode]    who decides the paper size
 * @returns {Promise<{printed: boolean, reason?: string, deviceName?: string}>}
 */
async function printReceipt (receipt, { mode = 'silent', deviceName = null, pageMode = 'auto' } = {}) {
  const rendered = await renderReceipt(receipt)
  const win = rendered.window

  const close = () => {
    if (!win.isDestroyed()) win.destroy()
  }

  // Everything the printer is told -- page size in the right unit, which paper
  // decides the height, no driver margins, no Chromium header/footer -- is
  // built by printOptions() so it can be unit-tested without a printer. See
  // that function for why `auto` and `driver` are mutually exclusive.
  const options = printOptions({
    mode,
    deviceName,
    pageMode,
    widthMm: rendered.widthMm,
    heightMm: rendered.heightMm
  })

  return new Promise((resolve) => {
    const done = (result) => {
      close()
      resolve({ deviceName: deviceName || '(printer bawaan sistem)', ...result })
    }

    // print() validates its options synchronously and throws. A printer driver
    // that rejects the page must not take the whole sale down with it, so the
    // throw becomes a message the cashier can read.
    try {
      win.webContents.print(options, (success, failureReason) => {
        done({
          printed: success,
          reason: success ? undefined : failureReason || 'cetak dibatalkan atau printer tidak merespons'
        })
      })
    } catch (error) {
      done({ printed: false, reason: error.message })
    }
  })
}

/**
 * Installed printers, for the picker. Electron exposes this per webContents,
 * so a throwaway window is needed when the till window is not up yet.
 *
 * NOTE: `PrinterInfo` has no paper-size field (only name/status/options), so
 * the driver's own paper size is read separately via queryDriverPaper() --
 * pretending `p.pageSize` exists is how the "driver masih di kertas tetap"
 * warning ended up dead code on every real machine while tests passed on a
 * stub that invented the field.
 */
async function listPrinters (hostWindow) {
  const target = hostWindow && !hostWindow.isDestroyed() ? hostWindow : BrowserWindow.getFocusedWindow()

  if (!target) return []

  const printers = await target.webContents.getPrintersAsync()

  return printers.map((p) => ({
    name: p.name,
    displayName: p.displayName || p.name,
    isDefault: Boolean(p.isDefault),
    status: p.status || 0,
    options: Array.isArray(p.options)
      ? p.options
        .filter((option) => option.selected)
        .map((option) => ({ name: option.name || option.index, value: option.value }))
      : []
  }))
}

/**
 * The paper size the Windows driver currently has selected for a printer,
 * e.g. "A4" or "58mm Receipt", or null when it cannot be read.
 *
 * This is the value that decides whether our 58mm page letterboxes: a driver
 * sitting on A4/letter centers the page (blank strip before the slip) and the
 * head clips what runs past the roll (right edge of every rupiah amount). The
 * app cannot change the driver, but it can -- and must -- say so out loud.
 *
 * Windows has no Electron API for this, so the driver is asked directly via
 * PowerShell's Get-PrintConfiguration. Results (including failures) are cached
 * per printer: the settings panel re-renders on every keystroke, and spawning
 * a shell per keystroke would make the panel stutter.
 *
 * @param {string} printerName
 * @returns {Promise<string|null>}
 */
const paperSizeCache = new Map()

function queryDriverPaper (printerName) {
  const name = String(printerName || '').trim()

  if (!name) return Promise.resolve(null)
  if (process.platform !== 'win32') return Promise.resolve(null)
  if (paperSizeCache.has(name)) return Promise.resolve(paperSizeCache.get(name))

  // Printer names come from the OS and may contain quotes; inside a PowerShell
  // single-quoted string they are escaped by doubling them.
  const script = `(Get-PrintConfiguration -PrinterName '${name.replace(/'/g, "''")}' -ErrorAction Stop).PaperSize`

  return new Promise((resolve) => {
    execFile(
      'powershell.exe',
      ['-NoProfile', '-NonInteractive', '-Command', script],
      { timeout: 5000, windowsHide: true },
      (error, stdout) => {
        const value = error ? null : String(stdout || '').trim() || null

        paperSizeCache.set(name, value)
        resolve(value)
      }
    )
  })
}

module.exports = { printReceipt, renderReceipt, listPrinters, queryDriverPaper, PAPER_WIDTH_MM }
