#!/usr/bin/env python3
"""Generate professional Hello Store app icons — v2, FAST (no numpy)."""
from PIL import Image, ImageDraw, ImageFont

SIZE = 1024
FG_SIZE = 432
BG_DARK = (180, 83, 9)
BG_LIGHT = (234, 179, 8)


def lerp(c1, c2, t):
    return tuple(int(c1[i] + (c2[i] - c1[i]) * t) for i in range(3))


def gradient_image(size):
    """Fast diagonal gradient using line drawing."""
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
    draw.pieslice([x0, y0, x0 + 2*r, y0 + 2*r], 180, 270, fill=fill)
    draw.pieslice([x1 - 2*r, y0, x1, y0 + 2*r], 270, 360, fill=fill)
    draw.pieslice([x0, y1 - 2*r, x0 + 2*r, y1], 90, 180, fill=fill)
    draw.pieslice([x1 - 2*r, y1 - 2*r, x1, y1], 0, 90, fill=fill)


def draw_bag(draw, cx, cy, scale, color, lw):
    bw = int(270 * scale)
    bh = int(240 * scale)
    bl, bt = cx - bw // 2, cy - bh // 2 + int(35 * scale)
    br, bb = bl + bw, bt + bh
    rr = int(30 * scale)
    rrect(draw, (bl, bt, br, bb), rr, color)

    # Fold line
    fy = bt + int(16 * scale)
    draw.rectangle([bl + int(10 * scale), fy, br - int(10 * scale), fy + int(5 * scale)], fill=color)

    # Handle
    hw, hh = int(120 * scale), int(110 * scale)
    hl, hr = cx - hw // 2, cx + hw // 2
    ht = bt - hh + int(15 * scale)
    draw.arc([hl, ht, hr, bt + int(15 * scale)], 180, 360, fill=color, width=lw)


def font(sz):
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

# --- Main icon 1024x1024 ---
bg = gradient_image(SIZE)
mask = rounded_mask(SIZE, int(SIZE * 0.22))
img = Image.new('RGBA', (SIZE, SIZE), (0, 0, 0, 0))
img.paste(bg.convert('RGBA'), mask=mask)
d = ImageDraw.Draw(img)
cx = cy = SIZE // 2
s = SIZE / 1024
draw_bag(d, cx, cy - int(20 * s), s * 0.70, 'white', int(20 * s))
centered_text(d, "HS", cx, cy + int(18 * s), font(int(155 * s)), 'white')
img.save(f'{OUT}/icon.png', 'PNG')
print("icon.png")

# --- Adaptive foreground 432x432 ---
img = Image.new('RGBA', (FG_SIZE, FG_SIZE), (0, 0, 0, 0))
d = ImageDraw.Draw(img)
s = FG_SIZE / 432
draw_bag(d, FG_SIZE // 2, FG_SIZE // 2 - int(18 * s), s * 0.76, 'white', int(22 * s))
centered_text(d, "HS", FG_SIZE // 2, FG_SIZE // 2 + int(18 * s), font(int(170 * s)), 'white')
img.save(f'{OUT}/android-icon-foreground.png', 'PNG')
print("foreground")

# --- Adaptive background 432x432 ---
gradient_image(FG_SIZE).save(f'{OUT}/android-icon-background.png', 'PNG')
print("background")

# --- Monochrome 432x432 ---
img = Image.new('RGBA', (FG_SIZE, FG_SIZE), (0, 0, 0, 0))
d = ImageDraw.Draw(img)
s = FG_SIZE / 432
draw_bag(d, FG_SIZE // 2, FG_SIZE // 2 - int(18 * s), s * 0.76, 'black', int(22 * s))
centered_text(d, "HS", FG_SIZE // 2, FG_SIZE // 2 + int(18 * s), font(int(170 * s)), 'black')
img.save(f'{OUT}/android-icon-monochrome.png', 'PNG')
print("monochrome")

# --- Splash 512x512 ---
sz = 512
img = Image.new('RGBA', (sz, sz), (0, 0, 0, 0))
d = ImageDraw.Draw(img)
s = sz / 512
draw_bag(d, sz // 2, sz // 2 - int(16 * s), s * 0.70, BG_LIGHT, int(18 * s))
centered_text(d, "HS", sz // 2, sz // 2 + int(16 * s), font(int(145 * s)), BG_LIGHT)
img.save(f'{OUT}/splash-icon.png', 'PNG')
print("splash")

# --- Favicon 48x48 ---
img = Image.new('RGBA', (48, 48), (0, 0, 0, 0))
d = ImageDraw.Draw(img)
rrect(d, (0, 0, 47, 47), 10, BG_LIGHT + (255,))
centered_text(d, "HS", 24, 24, font(16), 'white')
img.save(f'{OUT}/favicon.png', 'PNG')
print("favicon")
print("All done!")
