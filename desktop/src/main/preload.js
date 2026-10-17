'use strict'

const { contextBridge, ipcRenderer } = require('electron')

/**
 * The renderer gets a fixed set of named operations and nothing else -- no
 * `require`, no `fs`, no fetch to arbitrary hosts. Everything that touches the
 * disk or the network goes through main, which is the only process with
 * those permissions.
 */
const invoke = (channel, ...args) => ipcRenderer.invoke(channel, ...args)

contextBridge.exposeInMainWorld('pos', {
  /* connection + session */
  state: () => invoke('pos:state'),
  login: (email, password) => invoke('pos:login', { email, password }),
  logout: () => invoke('pos:logout'),
  apiUrl: () => invoke('pos:api-url:get'),
  setApiUrl: (url) => invoke('pos:api-url:set', url),
  config: () => invoke('pos:config'),

  /* catalog */
  products: (filters) => invoke('pos:products', filters),
  categories: () => invoke('pos:categories'),
  refreshCatalog: () => invoke('pos:catalog:refresh'),

  /* selling */
  checkout: (cart, options) => invoke('pos:checkout', { cart, options }),
  quote: (cart, options) => invoke('pos:quote', { cart, options }),

  /* sync + history */
  syncNow: () => invoke('pos:sync-now'),
  reconcile: () => invoke('pos:reconcile'),
  orders: (limit) => invoke('pos:orders', limit),
  order: (clientRef) => invoke('pos:order:one', clientRef),
  clearRejected: () => invoke('pos:clear-rejected'),

  /* diagnostics */
  exportDatabase: () => invoke('pos:db:export'),

  /* printing */
  printers: () => invoke('pos:printers'),
  // The driver's current paper size, read from Windows (Electron does not
  // expose it). This is what decides whether our page gets letterboxed, so the
  // settings panel can warn instead of letting margins be tuned against a
  // driver that is scaling the page anyway.
  printerPaper: (name) => invoke('pos:printer:paper', name),
  // layout must be forwarded. It was dropped here for two releases, so every
  // margin and offset the cashier dialled in was silently replaced by the
  // default before it reached the printer -- the settings looked saved and
  // changed nothing. pageMode rides along the same way: it decides whether the
  // printer follows the receipt's measured page or the driver's own form.
  setPrinter: (name, paper, layout, pageMode) => invoke('pos:printer:set', { name, paper, layout, pageMode }),
  printReceipt: (receipt) => invoke('pos:receipt:print', receipt),
  version: () => invoke('pos:version'),

  /* main -> renderer push */
  onChange: (callback) => {
    const listener = (_event, state) => callback(state)
    ipcRenderer.on('pos:changed', listener)
    return () => ipcRenderer.removeListener('pos:changed', listener)
  },

  /**
   * Menu items that need renderer state (sync, catalog refresh, open a panel)
   * are forwarded instead of reimplemented in main, so the menu item and the
   * on-screen button run the same function.
   */
  onMenu: (callback) => {
    const listener = (_event, action) => callback(action)
    ipcRenderer.on('pos:menu', listener)
    return () => ipcRenderer.removeListener('pos:menu', listener)
  },

  platform: process.platform
})
