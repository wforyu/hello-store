const sharp = require('sharp');
const path = require('path');
const fs = require('fs');

// Minimalist tote bag — small, clean, matching website amber gradient
// Canvas 432x432, safe zone circle ~296px diameter centered
const foregroundSvg = Buffer.from(`
<svg width="432" height="432" viewBox="0 0 432 432" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="bag" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#fbbf24"/>
      <stop offset="100%" stop-color="#d97706"/>
    </linearGradient>
  </defs>
  <!-- Small tote bag body -->
  <rect x="164" y="100" width="104" height="110" rx="10" fill="url(#bag)"/>
  <!-- Handle left -->
  <path d="M192 100 C192 68 200 56 216 52" stroke="#d97706" stroke-width="8" stroke-linecap="round" fill="none"/>
  <!-- Handle right -->
  <path d="M240 100 C240 68 232 56 216 52" stroke="#d97706" stroke-width="8" stroke-linecap="round" fill="none"/>
  <!-- Text -->
  <text x="216" y="280" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-weight="700" font-size="42" fill="#111827" letter-spacing="1">Hello Store</text>
</svg>`);

const backgroundSvg = Buffer.from(`
<svg width="432" height="432" viewBox="0 0 432 432" xmlns="http://www.w3.org/2000/svg">
  <rect width="432" height="432" fill="#FEF3C7"/>
</svg>`);

const monochromeSvg = Buffer.from(`
<svg width="432" height="432" viewBox="0 0 432 432" xmlns="http://www.w3.org/2000/svg">
  <rect x="164" y="100" width="104" height="110" rx="10" fill="#000000"/>
  <path d="M192 100 C192 68 200 56 216 52" stroke="#000000" stroke-width="8" stroke-linecap="round" fill="none"/>
  <path d="M240 100 C240 68 232 56 216 52" stroke="#000000" stroke-width="8" stroke-linecap="round" fill="none"/>
  <text x="216" y="280" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-weight="700" font-size="42" fill="#000000" letter-spacing="1">Hello Store</text>
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

    const bg = await sharp(backgroundSvg).resize(size, size).png().toBuffer();
    const fg = await sharp(foregroundSvg).resize(size, size).png().toBuffer();
    const composed = await sharp(bg).composite([{ input: fg, top: 0, left: 0 }]).png().toBuffer();
    await sharp(composed).toFile(path.join(dir, 'ic_launcher.png'));
    await sharp(composed).toFile(path.join(dir, 'ic_launcher_round.png'));

    for (const f of ['ic_launcher.webp','ic_launcher_round.webp','ic_launcher_foreground.webp','ic_launcher_background.webp','ic_launcher_monochrome.webp']) {
      const fp = path.join(dir, f);
      if (fs.existsSync(fp)) fs.unlinkSync(fp);
    }

    console.log(`${folder} (${size}px) done`);
  }
  console.log('All icons generated!');
}

generate().catch(err => { console.error(err); process.exit(1); });
