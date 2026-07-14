const sharp = require('sharp');
const path = require('path');
const fs = require('fs');

const foregroundSvg = Buffer.from(`
<svg width="432" height="432" viewBox="0 0 432 432" xmlns="http://www.w3.org/2000/svg">
  <path d="M152 72 L280 72 L264 208 L168 208 Z" fill="#F59E0B"/>
  <path d="M144 72 L288 72 L280 92 L160 92 Z" fill="#D97706"/>
  <path d="M184 72 C184 40 192 28 204 20" stroke="#D97706" stroke-width="10" stroke-linecap="round" fill="none"/>
  <path d="M248 72 C248 40 240 28 228 20" stroke="#D97706" stroke-width="10" stroke-linecap="round" fill="none"/>
  <path d="M168 140 L264 140" stroke="#D97706" stroke-width="3" stroke-dasharray="8 6"/>
  <path d="M172 172 L260 172" stroke="#D97706" stroke-width="3" stroke-dasharray="8 6"/>
  <text x="216" y="268" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-weight="700" font-size="48" fill="#111827" letter-spacing="2">Hello Store</text>
</svg>`);

const backgroundSvg = Buffer.from(`
<svg width="432" height="432" viewBox="0 0 432 432" xmlns="http://www.w3.org/2000/svg">
  <rect width="432" height="432" fill="#FEF3C7"/>
</svg>`);

const monochromeSvg = Buffer.from(`
<svg width="432" height="432" viewBox="0 0 432 432" xmlns="http://www.w3.org/2000/svg">
  <path d="M152 72 L280 72 L264 208 L168 208 Z" fill="#000000"/>
  <path d="M144 72 L288 72 L280 92 L160 92 Z" fill="#000000"/>
  <path d="M184 72 C184 40 192 28 204 20" stroke="#000000" stroke-width="10" stroke-linecap="round" fill="none"/>
  <path d="M248 72 C248 40 240 28 228 20" stroke="#000000" stroke-width="10" stroke-linecap="round" fill="none"/>
  <path d="M168 140 L264 140" stroke="#333" stroke-width="3" stroke-dasharray="8 6"/>
  <path d="M172 172 L260 172" stroke="#333" stroke-width="3" stroke-dasharray="8 6"/>
  <text x="216" y="268" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-weight="700" font-size="48" fill="#000000" letter-spacing="2">Hello Store</text>
</svg>`);

const densities = {
  'mipmap-mdpi': 48,
  'mipmap-hdpi': 72,
  'mipmap-xhdpi': 96,
  'mipmap-xxhdpi': 144,
  'mipmap-xxxhdpi': 192,
};

const resDir = path.join(__dirname, 'android', 'app', 'src', 'main', 'res');

async function generate() {
  for (const [folder, size] of Object.entries(densities)) {
    const dir = path.join(resDir, folder);

    await sharp(foregroundSvg).resize(size, size).png().toFile(path.join(dir, 'ic_launcher_foreground.png'));
    await sharp(backgroundSvg).resize(size, size).png().toFile(path.join(dir, 'ic_launcher_background.png'));
    await sharp(monochromeSvg).resize(size, size).png().toFile(path.join(dir, 'ic_launcher_monochrome.png'));

    // Full icon = background + foreground composited
    const bg = await sharp(backgroundSvg).resize(size, size).png().toBuffer();
    const fg = await sharp(foregroundSvg).resize(size, size).png().toBuffer();
    const composed = await sharp(bg).composite([{ input: fg, top: 0, left: 0 }]).png().toBuffer();
    await sharp(composed).toFile(path.join(dir, 'ic_launcher.png'));
    await sharp(composed).toFile(path.join(dir, 'ic_launcher_round.png'));

    // Remove old webp files
    for (const f of ['ic_launcher.webp','ic_launcher_round.webp','ic_launcher_foreground.webp','ic_launcher_background.webp','ic_launcher_monochrome.webp']) {
      const fp = path.join(dir, f);
      if (fs.existsSync(fp)) fs.unlinkSync(fp);
    }

    console.log(`${folder} (${size}px) done`);
  }

  // Also update the anydpi-v26 to reference PNGs instead of drawables
  const anydpiDir = path.join(resDir, 'mipmap-anydpi-v26');
  fs.writeFileSync(path.join(anydpiDir, 'ic_launcher.xml'), `<?xml version="1.0" encoding="utf-8"?>
<adaptive-icon xmlns:android="http://schemas.android.com/apk/res/android">
    <background android:drawable="@mipmap/ic_launcher_background"/>
    <foreground android:drawable="@mipmap/ic_launcher_foreground"/>
    <monochrome android:drawable="@mipmap/ic_launcher_monochrome"/>
</adaptive-icon>`);
  fs.writeFileSync(path.join(anydpiDir, 'ic_launcher_round.xml'), `<?xml version="1.0" encoding="utf-8"?>
<adaptive-icon xmlns:android="http://schemas.android.com/apk/res/android">
    <background android:drawable="@mipmap/ic_launcher_background"/>
    <foreground android:drawable="@mipmap/ic_launcher_foreground"/>
    <monochrome android:drawable="@mipmap/ic_launcher_monochrome"/>
</adaptive-icon>`);

  console.log('All icons generated!');
}

generate().catch(err => { console.error(err); process.exit(1); });
