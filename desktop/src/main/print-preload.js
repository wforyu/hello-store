'use strict'

const { contextBridge, ipcRenderer } = require('electron')

/**
 * Bridge for the hidden receipt window. It gets the payload it should print
 * and one way out -- the size it measured -- and nothing else.
 *
 * The measured size travels in millimetres, not pixels, because the page size
 * has to be handed to the printer in microns and converting twice invites the
 * two sides to disagree.
 */
contextBridge.exposeInMainWorld('receiptBridge', {
  data: () => ipcRenderer.invoke('pos:receipt:data'),
  measured: (size) => ipcRenderer.send('pos:receipt:measured', size),
  failed: (message) => ipcRenderer.send('pos:receipt:failed', String(message))
})
