const sharp = require('sharp');
const path = require('path');
const fs = require('fs');

// Match website storefront bag exactly:
// gradient amber-500 to orange-600, white outlined bag stroke, "Hello Store" text
const foregroundSvg = Buffer.from(`
<svg width="432" height="432" viewBox="0 0 432 432" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#f59e0b"/>
      <stop offset="100%" stop-color="#ea580c"/>
    </linearGradient>
  </defs>
  <!-- Exact bag from website: scaled 11x, centered, white stroke outlined bag -->
  <g transform="translate(84,68) scale(11)" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none">
    <path d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
  </g>
  <!-- Hello Store text -->
  <text x="216" y="358" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-weight="700" font-size="40" fill="#111827" letter-spacing="1">Hello Store</text>
</svg>`);

const backgroundSvg = Buffer.from(`
<svg width="432" height="432" viewBox="0 0 432 432" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#f59e0b"/>
      <stop offset="100%" stop-color="#ea580c"/>
    </linearGradient>
  </defs>
  <rect width="432" height="432" rx="96" fill="url(#bg)"/>
</svg>`);

const monochromeSvg = Buffer.from(`
<svg width="432" height="432" viewBox="0 0 432 432" xmlns="http://www.w3.org/2000/svg">
  <g transform="translate(84,68) scale(11)" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none">
    <path d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
  </g>
  <text x="216" y="358" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-weight="700" font-size="40" fill="#ffffff" letter-spacing="1">Hello Store</text>
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

    // Full icon = background composited with foreground
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
