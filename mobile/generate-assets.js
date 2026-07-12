const sharp = require('sharp');
const path = require('path');

const ASSETS_DIR = path.join(__dirname, 'assets');

async function generateIcon() {
  // 1024x1024 icon with amber gradient background, white "H" letter, and "HELLO STORE" text
  const svg = `<svg width="1024" height="1024" xmlns="http://www.w3.org/2000/svg">
    <defs>
      <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" style="stop-color:#F59E0B"/>
        <stop offset="100%" style="stop-color:#D97706"/>
      </linearGradient>
    </defs>
    <rect width="1024" height="1024" rx="228" fill="url(#bg)"/>
    <text x="512" y="480" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="420" font-weight="900" fill="white">H</text>
    <text x="512" y="680" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="100" font-weight="700" fill="white" letter-spacing="8">HELLO STORE</text>
  </svg>`;

  await sharp(Buffer.from(svg))
    .resize(1024, 1024)
    .png()
    .toFile(path.join(ASSETS_DIR, 'icon.png'));

  console.log('icon.png generated');

  // Adaptive icon foreground (same but without rounded corners, smaller margins)
  const fgSvg = `<svg width="1024" height="1024" xmlns="http://www.w3.org/2000/svg">
    <defs>
      <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" style="stop-color:#F59E0B"/>
        <stop offset="100%" style="stop-color:#D97706"/>
      </linearGradient>
    </defs>
    <rect width="1024" height="1024" fill="url(#bg)"/>
    <text x="512" y="480" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="420" font-weight="900" fill="white">H</text>
    <text x="512" y="680" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="100" font-weight="700" fill="white" letter-spacing="8">HELLO STORE</text>
  </svg>`;

  await sharp(Buffer.from(fgSvg))
    .resize(1024, 1024)
    .png()
    .toFile(path.join(ASSETS_DIR, 'android-icon-foreground.png'));

  console.log('android-icon-foreground.png generated');

  // Background (solid amber)
  const bgSvg = `<svg width="1024" height="1024" xmlns="http://www.w3.org/2000/svg">
    <rect width="1024" height="1024" fill="#FEF3C7"/>
  </svg>`;

  await sharp(Buffer.from(bgSvg))
    .resize(1024, 1024)
    .png()
    .toFile(path.join(ASSETS_DIR, 'android-icon-background.png'));

  console.log('android-icon-background.png generated');

  // Monochrome (black H on transparent)
  const monoSvg = `<svg width="1024" height="1024" xmlns="http://www.w3.org/2000/svg">
    <rect width="1024" height="1024" fill="black"/>
    <text x="512" y="480" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="420" font-weight="900" fill="white">H</text>
    <text x="512" y="680" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="100" font-weight="700" fill="white" letter-spacing="8">HELLO STORE</text>
  </svg>`;

  await sharp(Buffer.from(monoSvg))
    .resize(1024, 1024)
    .png()
    .toFile(path.join(ASSETS_DIR, 'android-icon-monochrome.png'));

  console.log('android-icon-monochrome.png generated');

  // Splash icon (smaller, just the H in a circle)
  const splashSvg = `<svg width="512" height="512" xmlns="http://www.w3.org/2000/svg">
    <defs>
      <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" style="stop-color:#F59E0B"/>
        <stop offset="100%" style="stop-color:#D97706"/>
      </linearGradient>
    </defs>
    <rect width="512" height="512" rx="100" fill="url(#bg)"/>
    <text x="256" y="320" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="300" font-weight="900" fill="white">H</text>
  </svg>`;

  await sharp(Buffer.from(splashSvg))
    .resize(512, 512)
    .png()
    .toFile(path.join(ASSETS_DIR, 'splash-icon.png'));

  console.log('splash-icon.png generated');
  console.log('All assets generated!');
}

generateIcon().catch(console.error);
