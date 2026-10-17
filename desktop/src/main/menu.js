'use strict'

/**
 * Application menu.
 *
 * Without an explicit menu Electron falls back to its own English default, and
 * on a till that reads as five dead words sitting above the product grid. This
 * builds the real thing in Indonesian, with every item wired to something that
 * actually does work.
 *
 * Division of labour: items that only need the window (zoom, fullscreen,
 * reload, devtools, about) are handled here in main. Items that need the
 * renderer's state -- syncing, reloading the catalog, opening a panel -- are
 * forwarded over `pos:menu` so the menu and the on-screen button run the exact
 * same function instead of two copies of the logic drifting apart.
 */

const { app, dialog, Menu, shell } = require('electron')

const SHORTCUTS = [
  ['F2', 'Fokus ke kolom pencarian'],
  ['F4', 'Bayar / simpan transaksi'],
  ['F9', 'Cetak struk transaksi terakhir'],
  ['Esc', 'Tutup panel atau struk yang terbuka'],
  ['Ctrl+S', 'Kirim antrean offline ke server sekarang'],
  ['F5', 'Tarik ulang katalog produk dari server'],
  ['Ctrl+R', 'Muat ulang tampilan'],
  ['Ctrl+Shift+I', 'Alat pengembang']
]

const HELP_HTML = `
  <p style="margin:0 0 10px"><b>Cara pakai</b></p>
  <ol style="margin:0 0 12px;padding-left:18px;line-height:1.7">
    <li>Cari produk lewat kolom atas atau klik kartu langsung.</li>
    <li>Ubah jumlah, kasir masih bisa diskon per item.</li>
    <li>Tekan <b>F4</b> atau tombol <b>Bayar</b>.</li>
    <li>Tekan <b>Cetak Struk</b> (atau <b>F9</b>) untuk mencetak struk pelanggan.</li>
    <li>Kalau internet mati, transaksi tetap tersimpan dan dikirim otomatis begitu online lagi.</li>
  </ol>
  <p style="margin:0 0 10px"><b>Printer struk</b></p>
  <p style="margin:0 0 12px;line-height:1.7">
    Pilih printer thermal dan lebar kertas di <b>Pengaturan Perangkat</b>, lalu tekan
    <b>Uji Cetak</b>. Setelah printer dipilih, setiap pencetakan langsung keluar tanpa
    dialog. Kalau belum ada printer pilihan, Windows menampilkan dialog cetak biasa.
    Struk lama bisa dicetak ulang dari <b>Riwayat Transaksi Lokal</b>.
  </p>
  <p style="margin:0 0 10px"><b>Pintasan keyboard</b></p>
  <table cellpadding="3" style="border-collapse:collapse">
    ${SHORTCUTS.map(([key, what]) => `<tr><td><b>${key}</b></td><td>${what}</td></tr>`).join('')}
  </table>
  <p style="margin:12px 0 0;color:#666;font-size:12px">
    Nomor Client di layar Pengaturan Perangkat menandai mesin ini. Jangan dihapus --
    itu yang mencegah satu penjualan offline tercatat dua kali di server.
  </p>
`

/**
 * @param {object} ctx
 * @param {() => import('electron').BrowserWindow|null} ctx.getWindow
 * @param {() => object} ctx.store
 * @param {() => object} ctx.sync
 * @param {boolean} ctx.isDev
 * @param {() => boolean} ctx.confirmQuit
 */
function buildMenu (ctx) {
  const { getWindow, store, isDev, confirmQuit } = ctx

  /** Hand the action to the renderer so the menu and the button share one path. */
  const tell = (action) => () => {
    const win = getWindow()
    if (win && !win.isDestroyed()) win.webContents.send('pos:menu', action)
  }

  const template = [
    {
      label: 'File',
      submenu: [
        { label: 'Sinkron Sekarang', accelerator: 'CmdOrCtrl+S', click: tell('sync') },
        { label: 'Muat Ulang Katalog', accelerator: 'F5', click: tell('catalog') },
        { label: 'Cetak Struk Terakhir', accelerator: 'F9', click: tell('print') },
        { type: 'separator' },
        { label: 'Pengaturan Perangkat…', accelerator: 'CmdOrCtrl+,', click: tell('settings') },
        { label: 'Riwayat Transaksi Lokal…', accelerator: 'CmdOrCtrl+H', click: tell('orders') },
        { type: 'separator' },
        { label: 'Keluar dari Perangkat', accelerator: 'CmdOrCtrl+Shift+Q', click: tell('logout') },
        { type: 'separator' },
        { label: 'Keluar', accelerator: 'CmdOrCtrl+Q', click: () => { if (confirmQuit()) app.quit() } }
      ]
    },
    {
      label: 'Edit',
      submenu: [
        { role: 'undo', label: 'Urungkan' },
        { role: 'redo', label: 'Ulangi' },
        { type: 'separator' },
        { role: 'cut', label: 'Potong' },
        { role: 'copy', label: 'Salin' },
        { role: 'paste', label: 'Tempel' },
        { role: 'selectAll', label: 'Pilih Semua' }
      ]
    },
    {
      label: 'Tampilan',
      submenu: [
        { role: 'reload', label: 'Muat Ulang' },
        { role: 'forceReload', label: 'Muat Ulang Paksa' },
        { type: 'separator' },
        { role: 'resetZoom', label: 'Zoom Normal' },
        { role: 'zoomIn', label: 'Perbesar' },
        { role: 'zoomOut', label: 'Perkecil' },
        { type: 'separator' },
        { role: 'togglefullscreen', label: 'Layar Penuh' },
        {
          label: 'Selalu di Atas',
          type: 'checkbox',
          checked: Boolean(getWindow()?.isAlwaysOnTop()),
          click: (item) => {
            const win = getWindow()
            if (win) win.setAlwaysOnTop(item.checked)
            // Rebuild so the checkmark follows the real window state.
            Menu.setApplicationMenu(buildMenu(ctx))
          }
        },
        { type: 'separator' },
        { role: 'toggleDevTools', label: 'Alat Pengembang' }
      ]
    },
    {
      label: 'Jendela',
      submenu: [
        { label: 'Perkecil', accelerator: 'CmdOrCtrl+Shift+M', role: 'minimize' },
        { label: 'Perbesar', accelerator: 'CmdOrCtrl+Shift+B', role: 'zoom' },
        { type: 'separator' },
        {
          label: 'Tutup Jendela',
          accelerator: 'CmdOrCtrl+W',
          click: () => {
            const win = getWindow()
            if (win && confirmQuit()) win.close()
          }
        }
      ]
    },
    {
      label: 'Bantuan',
      submenu: [
        {
          label: 'Panduan Cepat',
          accelerator: 'F1',
          click: () => {
            const win = getWindow()
            if (win) {
              dialog.showMessageBox(win, {
                type: 'info',
                title: 'Cara Pakai',
                message: 'Hello Store POS',
                detail: 'Pintasan keyboard dan alur penjualan singkat.',
                htmlMessage: HELP_HTML,
                buttons: ['Tutup']
              })
            }
          }
        },
        {
          label: 'Tentang Hello Store POS',
          click: () => {
            const win = getWindow()
            const meta = store()
            const info = [
              `Versi       : ${app.getVersion()}`,
              `Server      : ${meta.getMeta('api_url') || '—'}`,
              `Client ID   : ${meta.getMeta('client_id') || '—'}`,
              `Elektron    : ${process.versions.electron}`,
              `Chrome      : ${process.versions.chrome}`
            ].join('\n')
            if (win) dialog.showMessageBox(win, { type: 'info', title: 'Tentang', message: 'Hello Store POS', detail: info, buttons: ['Tutup'] })
          }
        },
        { type: 'separator' },
        {
          label: 'Buka Panel Admin di Browser',
          click: () => {
            const base = String(store().getMeta('api_url') || '').replace(/\/+$/, '')
            if (!base) return
            shell.openExternal(`${base}/admin`)
          }
        }
      ]
    }
  ]

  return Menu.buildFromTemplate(template)
}

function installMenu (ctx) {
  const menu = buildMenu(ctx)

  Menu.setApplicationMenu(menu)

  return menu
}

module.exports = { installMenu, buildMenu }
