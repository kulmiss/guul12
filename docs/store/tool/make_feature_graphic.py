#!/usr/bin/env python3
"""Makes the Google Play feature graphic (1024 x 500) in the app icon's colours.

Run from the repo root:  python3 docs/store/tool/make_feature_graphic.py
Needs the same tools as app/tool/icon/make_icon.py (fonttools, pillow, rsvg-convert).
Writes docs/store/feature-graphic.png. Text stays inside the middle of the picture, because
Play may crop the edges or put a play button in the centre (so nothing important is there).
"""
import os
import subprocess
import sys

from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.ttLib import TTFont
from PIL import Image

HERE = os.path.dirname(os.path.abspath(__file__))
REPO = os.path.dirname(os.path.dirname(os.path.dirname(HERE)))
sys.path.insert(0, os.path.join(REPO, 'app', 'tool', 'icon'))
import make_icon  # noqa: E402  (same colours and the same "12 + tick" mark as the app icon)

FONTS = os.path.join(REPO, 'app', 'assets', 'fonts')
OUT = os.path.join(os.path.dirname(HERE), 'feature-graphic.png')
W, H = 1024, 500


def text_path(text, font_file, height, x, baseline):
    """SVG path of `text` starting at x; `height` is the cap height. Returns (path, width)."""
    font = TTFont(os.path.join(FONTS, font_file))
    cmap = font.getBestCmap()
    glyphs = font.getGlyphSet()
    upm = font['head'].unitsPerEm
    cap = font['OS/2'].sCapHeight or int(upm * 0.7)
    scale = height / cap
    parts = []
    start = x
    for c in text:
        g = glyphs[cmap[ord(c)]]
        pen = SVGPathPen(glyphs)
        g.draw(TransformPen(pen, (scale, 0, 0, -scale, x, baseline)))
        parts.append(pen.getCommands())
        x += g.width * scale
    return ' '.join(parts), x - start


def main():
    # Icon tile on the left, words on the right; the group is centred as a whole.
    tile = 220
    gap = 48
    title, title_w = text_path('Guul 12', 'Nunito-Black.ttf', 92, 0, 0)
    line1, line1_w = text_path('Grade 12 exam practice', 'NotoSans-Bold.ttf', 34, 0, 0)
    words_w = max(title_w, line1_w)
    left = (W - (tile + gap + words_w)) / 2
    top = (H - tile) / 2
    tx = left + tile + gap

    title, _ = text_path('Guul 12', 'Nunito-Black.ttf', 92, tx, 232)
    line1, _ = text_path('Grade 12 exam practice', 'NotoSans-Bold.ttf', 34, tx, 300)

    svg = (
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}">'
        f'<rect width="{W}" height="{H}" fill="{make_icon.CREAM}"/>'
        f'<rect x="{left:.1f}" y="{top:.1f}" width="{tile}" height="{tile}" rx="48" fill="{make_icon.TEAL}"/>'
        f'<g transform="translate({left:.1f} {top:.1f}) scale({tile / 512:.5f})">'
        f'{make_icon.mark(512, 256, 268, 200)}</g>'
        f'<path d="{title}" fill="{make_icon.TEAL}"/>'
        f'<path d="{line1}" fill="#3B4A4F"/>'
        f'<rect x="{tx:.1f}" y="328" width="96" height="10" rx="5" fill="{make_icon.ORANGE}"/>'
        '</svg>\n'
    )
    svg_path = OUT + '.svg'
    with open(svg_path, 'w', encoding='utf-8') as f:
        f.write(svg)
    try:
        subprocess.run(['rsvg-convert', '-w', str(W), '-h', str(H), '-o', OUT, svg_path], check=True)
    finally:
        os.remove(svg_path)
    # Play wants a 24-bit PNG or JPEG with no transparency.
    Image.open(OUT).convert('RGB').save(OUT, optimize=True)
    with Image.open(OUT) as im:
        if im.size != (W, H) or im.mode != 'RGB':
            sys.exit(f'Unexpected picture: {im.size} {im.mode}')
    print(f'Wrote {os.path.relpath(OUT, REPO)} ({W} x {H}, RGB).')


if __name__ == '__main__':
    main()
