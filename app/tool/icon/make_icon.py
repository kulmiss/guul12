#!/usr/bin/env python3
"""Makes the Guul 12 app icon in every Android size from one design (Sunrise colours).

Original design: cream "12" (Nunito Black, SIL OFL, bundled in assets/fonts) on teal, with an
orange "success" tick. Run from app/:  python3 tool/icon/make_icon.py
Needs: fonttools + pillow (pip) and rsvg-convert (librsvg). Fails if the adaptive art
leaves the 66dp safe circle. Writes:
  tool/icon/icon.svg, tool/icon/foreground.svg      (sources, kept in git)
  android/app/src/main/res/mipmap-*/ic_launcher.png  (legacy icons, rounded square)
  android/app/src/main/res/drawable-*/ic_launcher_foreground.png + mipmap-anydpi-v26 (adaptive)
  tool/icon/play-store-512.png                       (Play Console icon)
"""
import os
import subprocess
import sys

from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.ttLib import TTFont
from PIL import Image

HERE = os.path.dirname(os.path.abspath(__file__))
APP = os.path.dirname(os.path.dirname(HERE))
RES = os.path.join(APP, 'android', 'app', 'src', 'main', 'res')
FONT = os.path.join(APP, 'assets', 'fonts', 'Nunito-Black.ttf')

TEAL = '#0E5E6F'
CREAM = '#FFF8EE'
ORANGE = '#F28C28'


def text_path(text, height, cx, baseline):
    """SVG path of `text`, cap height `height`, centred on cx with the given baseline."""
    font = TTFont(FONT)
    cmap = font.getBestCmap()
    glyphs = font.getGlyphSet()
    upm = font['head'].unitsPerEm
    cap = font['OS/2'].sCapHeight or int(upm * 0.7)
    scale = height / cap
    width = sum(glyphs[cmap[ord(c)]].width for c in text) * scale
    x = cx - width / 2
    parts = []
    for c in text:
        g = glyphs[cmap[ord(c)]]
        pen = SVGPathPen(glyphs)
        # Font units go up; SVG goes down.
        g.draw(TransformPen(pen, (scale, 0, 0, -scale, x, baseline)))
        parts.append(pen.getCommands())
        x += g.width * scale
    return ' '.join(parts)


def mark(size, cx, cy, digit_height):
    """The '12' and the tick, centred near (cx, cy), drawn in a square of `size`."""
    digits = text_path('12', digit_height, cx - digit_height * 0.08, cy + digit_height * 0.5)
    s = digit_height / 100
    # Tick above-right of the digits.
    tx, ty = cx + 62 * s, cy - 78 * s
    tick = (f'M {tx - 22 * s:.1f} {ty:.1f} L {tx - 6 * s:.1f} {ty + 16 * s:.1f} '
            f'L {tx + 26 * s:.1f} {ty - 18 * s:.1f}')
    return (f'<path d="{digits}" fill="{CREAM}"/>'
            f'<path d="{tick}" fill="none" stroke="{ORANGE}" stroke-width="{12 * s:.1f}" '
            f'stroke-linecap="round" stroke-linejoin="round"/>')


def write(path, svg):
    with open(path, 'w', encoding='utf-8') as f:
        f.write(svg)


def render(svg_path, out, px):
    os.makedirs(os.path.dirname(out), exist_ok=True)
    subprocess.run(['rsvg-convert', '-w', str(px), '-h', str(px), '-o', out, svg_path], check=True)


def max_radius_dp(png):
    """Furthest visible pixel from the centre of a 108dp foreground, in dp."""
    im = Image.open(png).convert('RGBA')
    w, h = im.size
    c = w / 2
    px = im.load()
    far = max(((x - c) ** 2 + (y - c) ** 2) ** 0.5
              for y in range(h) for x in range(w) if px[x, y][3] > 16)
    return far / w * 108


def main():
    if not os.path.exists(FONT):
        sys.exit('Font not found: ' + FONT)
    # Full icon (legacy launchers + Play): rounded teal square.
    icon = (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">'
            f'<rect width="512" height="512" rx="112" fill="{TEAL}"/>'
            f'{mark(512, 256, 268, 200)}</svg>\n')
    # Play wants a full square (it rounds the corners itself).
    play = (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">'
            f'<rect width="512" height="512" fill="{TEAL}"/>{mark(512, 256, 268, 200)}</svg>\n')
    # Adaptive foreground: 108dp canvas, art inside the central 66dp safe circle.
    fg = (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 108 108">'
          f'{mark(108, 54, 59, 25)}</svg>\n')
    icon_svg = os.path.join(HERE, 'icon.svg')
    fg_svg = os.path.join(HERE, 'foreground.svg')
    play_svg = os.path.join(HERE, '.play.svg')
    write(icon_svg, icon)
    write(fg_svg, fg)
    write(play_svg, play)

    densities = {'mdpi': 1, 'hdpi': 1.5, 'xhdpi': 2, 'xxhdpi': 3, 'xxxhdpi': 4}
    for name, f in densities.items():
        render(icon_svg, os.path.join(RES, f'mipmap-{name}', 'ic_launcher.png'), round(48 * f))
        render(fg_svg, os.path.join(RES, f'drawable-{name}', 'ic_launcher_foreground.png'), round(108 * f))
    render(play_svg, os.path.join(HERE, 'play-store-512.png'), 512)
    os.remove(play_svg)

    anydpi = os.path.join(RES, 'mipmap-anydpi-v26')
    os.makedirs(anydpi, exist_ok=True)
    write(os.path.join(anydpi, 'ic_launcher.xml'),
          '<?xml version="1.0" encoding="utf-8"?>\n'
          '<adaptive-icon xmlns:android="http://schemas.android.com/apk/res/android">\n'
          '    <background android:drawable="@color/ic_launcher_background"/>\n'
          '    <foreground android:drawable="@drawable/ic_launcher_foreground"/>\n'
          '</adaptive-icon>\n')
    write(os.path.join(RES, 'values', 'ic_launcher_background.xml'),
          '<?xml version="1.0" encoding="utf-8"?>\n<resources>\n'
          f'    <color name="ic_launcher_background">{TEAL}</color>\n</resources>\n')
    # Round launcher masks show only the central 66dp circle (radius 33dp).
    far = max_radius_dp(os.path.join(RES, 'drawable-xxxhdpi', 'ic_launcher_foreground.png'))
    if far > 33:
        sys.exit(f'Foreground art reaches {far:.1f}dp from the centre; it must stay within 33dp.')
    print(f'Icons written. Foreground art stays within {far:.1f}dp of the centre (limit 33dp).')


if __name__ == '__main__':
    main()
