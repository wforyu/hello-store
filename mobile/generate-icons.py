#!/usr/bin/env python3
"""
Generate Hello Store app icons — v3
Follows Android adaptive icon spec:
  - Canvas: 108dp (432px at xxxhdpi)
  - Safe zone: inner 66dp (264px centered)
  - Motion padding: 18dp each side
  - Foreground content must fit within 66dp to avoid clipping on any mask shape
"""
from PIL import Image, ImageDraw, ImageFont

SIZE = 1024
FG_SIZE = 432
BG_DARK = (180, 83, 9)
BG_LIGHT = (234, 179, 8)


def lerp(c1, c2, t):
    return tuple(int(c1[i] + (c2[i] - c1[i]) * t) for i in range(3))


def gradient_image(size):
    img = Image.new('RGB', (size, size))
    draw = ImageDraw.Draw(img)
    for y in range(size):
        t = y / size
        c = lerp(BG_DARK, BG_LIGHT, t)
        draw.line([(0, y), (size, y)], fill=c)
    return img


def rounded_mask(size, r):
    img = Image.new('L', (size, size), 0)
    d = ImageDraw.Draw(img)
    d.rectangle([r, 0, size - r, size], fill=255)
    d.rectangle([0, r, size, size - r], fill=255)
    d.pieslice([0, 0, 2 * r, 2 * r], 180, 270, fill=255)
    d.pieslice([size - 2 * r, 0, size, 2 * r], 270, 360, fill=255)
    d.pieslice([0, size - 2 * r, 2 * r, size], 90, 180, fill=255)
    d.pieslice([size - 2 * r, size - 2 * r, size, size], 0, 90, fill=255)
    return img


def rrect(draw, xy, r, fill):
    x0, y0, x1, y1 = xy
    draw.rectangle([x0 + r, y0, x1 - r, y1], fill=fill)
    draw.rectangle([x0, y0 + r, x1, y1 - r], fill=fill)
    draw.pieslice([x0, y0, x0 + 2 * r, y0 + 2 * r], 180, 270, fill=fill)
    draw.pieslice([x1 - 2 * r, y0, x1, y0 + 2 * r], 270, 360, fill=fill)
    draw.pieslice([x0, y1 - 2 * r, x0 + 2 * r, y1], 90, 180, fill=fill)
    draw.pieslice([x1 - 2 * r, y1 - 2 * r, x1, y1], 0, 90, fill=fill)


def draw_bag(draw, cx, cy, bw, bh, color, lw):
    """Draw a shopping bag centered at (cx, cy) with given width/height."""
    bl = cx - bw // 2
    bt = cy - bh // 2
    br = bl + bw
    bb = bt + bh
    rr = int(bw * 0.12)

    # Bag body
    rrect(draw, (bl, bt, br, bb), rr, color)

    # Fold line at top
    fy = bt + int(bh * 0.06)
    fh = max(int(bh * 0.025), 2)
    draw.rectangle([bl + int(bw * 0.08), fy, br - int(bw * 0.08), fy + fh], fill=color)

    # Handle arc
    hw = int(bw * 0.45)
    hh = int(bh * 0.42)
    hl = cx - hw // 2
    hr = cx + hw // 2
    ht = bt - hh + int(bh * 0.06)
    draw.arc([hl, ht, hr, bt + int(bh * 0.06)], 180, 360, fill=color, width=lw)


def get_font(sz):
    for p in ["C:/Windows/Fonts/segoeuib.ttf", "C:/Windows/Fonts/arialbd.ttf",
              "C:/Windows/Fonts/calibrib.ttf", "C:/Windows/Fonts/arial.ttf"]:
        try:
            return ImageFont.truetype(p, sz)
        except (OSError, IOError):
            continue
    return ImageFont.load_default()


def centered_text(draw, text, cx, cy, fnt, fill):
    bb = draw.textbbox((0, 0), text, font=fnt)
    tw, th = bb[2] - bb[0], bb[3] - bb[1]
    draw.text((cx - tw // 2, cy - th // 2), text, fill=fill, font=fnt)


OUT = 'C:/Users/ganyo/hello-store/mobile/assets'

# ============================================================
# ANDROID ADAPTIVE ICON FOREGROUND (432x432 px = 108dp at xxxhdpi)
#
# Safe zone: inner 66dp = 264px centered → from 84px to 348px
# All critical content MUST be within this zone.
#
# Layout (all relative to 432px canvas):
#   - Shopping bag: 130w x 120h, centered at (216, 165)
#   - "HS" text: font 85px, centered at (216, 290)
#   - Gap between bag bottom (225) and text top (~255): ~30px
#   - Total span: ~105 (handle top) to ~335 (text bottom) = 230px → fits in 264px safe zone ✓
# ============================================================

# --- Main icon 1024x1024 (rounded square, not adaptive) ---
bg = gradient_image(SIZE)
mask = rounded_mask(SIZE, int(SIZE * 0.22))
img = Image.new('RGBA', (SIZE, SIZE), (0, 0, 0, 0))
img.paste(bg.convert('RGBA'), mask=mask)
d = ImageDraw.Draw(img)
cx = cy = SIZE // 2
s = SIZE / 432  # scale factor from 432 to 1024

# Bag: 130*2.37=308w, 120*2.37=284h, centered at (512, 393)
bag_cx, bag_cy = cx, cy - int(110 * s)
draw_bag(d, bag_cx, bag_cy, int(130 * s), int(120 * s), 'white', int(18 * s))

# HS text: font 85*2.37=201px, centered below bag
hs_cy = cy + int(80 * s)
centered_text(d, "HS", cx, hs_cy, get_font(int(85 * s)), 'white')

img.save(f'{OUT}/icon.png', 'PNG')
print("icon.png done")

# --- Adaptive foreground 432x432 ---
img = Image.new('RGBA', (FG_SIZE, FG_SIZE), (0, 0, 0, 0))
d = ImageDraw.Draw(img)
cx, cy = FG_SIZE // 2, FG_SIZE // 2

# Bag: 130w x 120h, centered at (216, 155)
draw_bag(d, cx, cy - int(50), 130, 120, 'white', 14)

# HS text: font 85px, centered at (216, 280)
centered_text(d, "HS", cx, cy + int(70), get_font(85), 'white')

img.save(f'{OUT}/android-icon-foreground.png', 'PNG')
print("foreground done")

# --- Adaptive background 432x432 ---
gradient_image(FG_SIZE).save(f'{OUT}/android-icon-background.png', 'PNG')
print("background done")

# --- Monochrome 432x432 ---
img = Image.new('RGBA', (FG_SIZE, FG_SIZE), (0, 0, 0, 0))
d = ImageDraw.Draw(img)
cx, cy = FG_SIZE // 2, FG_SIZE // 2
draw_bag(d, cx, cy - int(50), 130, 120, 'black', 14)
centered_text(d, "HS", cx, cy + int(70), get_font(85), 'black')
img.save(f'{OUT}/android-icon-monochrome.png', 'PNG')
print("monochrome done")

# --- Splash 512x512 ---
sz = 512
img = Image.new('RGBA', (sz, sz), (0, 0, 0, 0))
d = ImageDraw.Draw(img)
s = sz / 432
cx, cy = sz // 2, sz // 2
draw_bag(d, cx, cy - int(50 * s), int(130 * s), int(120 * s), BG_LIGHT, int(14 * s))
centered_text(d, "HS", cx, cy + int(70 * s), get_font(int(85 * s)), BG_LIGHT)
img.save(f'{OUT}/splash-icon.png', 'PNG')
print("splash done")

# --- Favicon 48x48 ---
img = Image.new('RGBA', (48, 48), (0, 0, 0, 0))
d = ImageDraw.Draw(img)
rrect(d, (0, 0, 47, 47), 10, BG_LIGHT + (255,))
centered_text(d, "HS", 24, 25, get_font(18), 'white')
img.save(f'{OUT}/favicon.png', 'PNG')
print("favicon done")

print("\nAll icons generated successfully!")
