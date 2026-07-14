const sharp = require('sharp');
const path = require('path');
const fs = require('fs');

const foregroundSvg = Buffer.from(`
<svg width="432" height="432" viewBox="0 0 432 432" xmlns="http://www.w3.org/2000/svg">
  <!-- Tote bag body -->
  <rect x="116" y="140" width="200" height="160" rx="12" fill="#F59E0B"/>
  <!-- Bag top fold -->
  <rect x="108" y="128" width="216" height="24" rx="8" fill="#D97706"/>
  <!-- Handle left -->
  <path d="M170 128 C170 72 178 52 216 48" stroke="#D97706" stroke-width="12" stroke-linecap="round" fill="none"/>
  <!-- Handle right -->
  <path d="M262 128 C262 72 254 52 216 48" stroke="#D97706" stroke-width="12" stroke-linecap="round" fill="none"/>
  <!-- Text: Hello Store -->
  <text x="216" y="360" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-weight="700" font-size="52" fill="#111827" letter-spacing="1">Hello Store</text>
</svg>`);

const backgroundSvg = Buffer.from(`
<svg width="432" height="432" viewBox="0 0 432 432" xmlns="http://www.w3.org/2000/svg">
  <rect width="432" height="432" fill="#FEF3C7"/>
</svg>`);

const monochromeSvg = Buffer.from(`
<svg width="432" height="432" viewBox="0 0 432 432" xmlns="http://www.w3.org/2000/svg">
  <rect x="116" y="140" width="200" height="160" rx="12" fill="#000000"/>
  <rect x="108" y="128" width="216" height="24" rx="8" fill="#000000"/>
  <path d="M170 128 C170 72 178 52 216 48" stroke="#000000" stroke-width="12" stroke-linecap="round" fill="none"/>
  <path d="M262 128 C262 72 254 52 216 48" stroke="#000000" stroke-width="12" stroke-linecap="round" fill="none"/>
  <text x="216" y="360" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-weight="700" font-size="52" fill="#000000" letter-spacing="1">Hello Store</text>
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
