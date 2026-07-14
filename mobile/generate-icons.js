const sharp = require('sharp');
const path = require('path');
const fs = require('fs');

// Adaptive icon canvas: 432dp, safe zone circle ~130px radius centered at 216,216
// Everything must fit within ~y=86 to y=346, ~x=86 to x=346

const foregroundSvg = Buffer.from(`
<svg width="432" height="432" viewBox="0 0 432 432" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="bag" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#fbbf24"/>
      <stop offset="100%" stop-color="#d97706"/>
    </linearGradient>
  </defs>
  <!-- Handle left — keep within safe zone top y=96+ -->
  <path d="M186 148 C186 112 196 98 216 96" stroke="#d97706" stroke-width="9" stroke-linecap="round" fill="none"/>
  <!-- Handle right -->
  <path d="M246 148 C246 112 236 98 216 96" stroke="#d97706" stroke-width="9" stroke-linecap="round" fill="none"/>
  <!-- Bag body -->
  <rect x="162" y="148" width="108" height="120" rx="10" fill="url(#bag)"/>
  <!-- Hello Store text -->
  <text x="216" y="328" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-weight="700" font-size="40" fill="#111827" letter-spacing="1">Hello Store</text>
</svg>`);

const backgroundSvg = Buffer.from(`
<svg width="432" height="432" viewBox="0 0 432 432" xmlns="http://www.w3.org/2000/svg">
  <rect width="432" height="432" fill="#FEF3C7"/>
</svg>`);

const monochromeSvg = Buffer.from(`
<svg width="432" height="432" viewBox="0 0 432 432" xmlns="http://www.w3.org/2000/svg">
  <path d="M186 148 C186 112 196 98 216 96" stroke="#000000" stroke-width="9" stroke-linecap="round" fill="none"/>
  <path d="M246 148 C246 112 236 98 216 96" stroke="#000000" stroke-width="9" stroke-linecap="round" fill="none"/>
  <rect x="162" y="148" width="108" height="120" rx="10" fill="#000000"/>
  <text x="216" y="328" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-weight="700" font-size="40" fill="#000000" letter-spacing="1">Hello Store</text>
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
