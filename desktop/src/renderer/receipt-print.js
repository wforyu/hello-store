'use strict'

/**
 * Renders the receipt in the hidden print window, then tells main how tall it
 * is. Main feeds that height to the printer as the page length, which is what
 * lets the thermal roll cut exactly at the end of the last line.
 *
 * The markup and the CSS both come from receipt.js, so what the preview shows
 * in the till and what the printer receives cannot drift apart.
 */

const { renderReceiptHtml, receiptCss, pageSizeMm } = window.posReceipt

function render (receipt) {
  const css = receiptCss(receipt.layout, receipt.paper)
  const paper = document.getElementById('paper')

  // CSS first, then markup: the height measured below depends on the font
  // size the CSS sets.
  document.getElementById('receipt-css').textContent = css
  paper.innerHTML = renderReceiptHtml(receipt)

  return paper
}

/**
 * Pin the paper length in CSS, straight from the shared formula so the number
 * quoted in the preview and the length the printer is given are the same
 * calculation.
 */
function pinPaper (receipt, contentHeightPx) {
  const { widthMm, heightMm } = pageSizeMm(receipt, contentHeightPx)

  document.getElementById('page-size').textContent =
    `@page { size: ${widthMm}mm ${heightMm.toFixed(2)}mm; margin: 0; }`

  return { widthMm, heightMm }
}

async function boot () {
  try {
    const receipt = await window.receiptBridge.data()

    if (!receipt) {
      window.receiptBridge.failed('tidak ada data struk')
      return
    }

    const paper = render(receipt)

    // The height is only final once fonts have loaded and layout has settled.
    await document.fonts?.ready

    // setTimeout, not requestAnimationFrame: this window is never shown, and
    // Chromium stops firing animation frames in a hidden window entirely --
    // waiting on rAF here means the printer is never contacted and the sale
    // hangs with no error at all.
    await new Promise((resolve) => setTimeout(resolve, 50))

    const heightPx = paper.scrollHeight
    const pinned = pinPaper(receipt, heightPx)

    // Reported in millimetres: main hands the printer a page size in microns,
    // and rounding the same number twice on two sides is how the printed slip
    // ends up a millimetre wider than the paper.
    window.receiptBridge.measured({ heightPx, widthMm: pinned.widthMm, heightMm: pinned.heightMm })
  } catch (error) {
    window.receiptBridge.failed(error.message)
  }
}

boot()
