#!/usr/bin/env python3
"""Checks the Play listing texts in docs/store/listing-*.md.

Each listing has three ```text blocks, in this order: app name, short description, full
description. The script checks Google's length limits and words the Play payments and
metadata policies do not allow. Run from the repo root:  python3 docs/store/tool/check_listing.py
Pass file paths to check other files (CI uses this to prove a bad listing fails).
Exit code 0 = all good, 1 = a problem (each problem is printed).
"""

import glob
import os
import re
import sys

LIMITS = (('app name', 30), ('short description', 80), ('full description', 4000))

# Whole words, any case. Payment words (payments policy) and ranking words (metadata policy).
BANNED = [
    'price', 'prices', 'birr', 'etb', 'pay', 'paid', 'payment', 'buy', 'purchase', 'discount',
    'offer', 'sale', 'trial', 'subscribe', 'subscription', 'whatsapp', 'telebirr', 'cbe',
    'best', 'top', 'no.1', '#1', 'number one', 'free download',
]
URL = re.compile(r'https?://|www\.', re.IGNORECASE)
BLOCK = re.compile(r'```text\n(.*?)\n```', re.DOTALL)


def problems_in(path):
    with open(path, encoding='utf-8') as f:
        blocks = BLOCK.findall(f.read())
    name = os.path.basename(path)
    if len(blocks) != len(LIMITS):
        return [f'{name}: expected {len(LIMITS)} ```text blocks, found {len(blocks)}']
    found = []
    for (label, limit), text in zip(LIMITS, blocks):
        if len(text) > limit:
            found.append(f'{name}: {label} has {len(text)} characters (max {limit})')
        if label != 'full description' and '\n' in text:
            found.append(f'{name}: {label} must be one line')
        if URL.search(text):
            found.append(f'{name}: {label} contains a web address')
        lower = text.lower()
        for word in BANNED:
            if re.search(r'(?<![\w#])' + re.escape(word) + r'(?!\w)', lower):
                found.append(f'{name}: {label} contains "{word}"')
    return found


def main():
    here = os.path.dirname(os.path.abspath(__file__))
    files = sys.argv[1:] or sorted(glob.glob(os.path.join(here, '..', 'listing-*.md')))
    if not files:
        sys.exit('No listing files found.')
    found = [p for path in files for p in problems_in(path)]
    for p in found:
        print(p)
    if found:
        sys.exit(1)
    for path in files:
        with open(path, encoding='utf-8') as f:
            sizes = [len(b) for b in BLOCK.findall(f.read())]
        print(f'{os.path.basename(path)}: OK (name {sizes[0]}, short {sizes[1]}, full {sizes[2]} characters)')


if __name__ == '__main__':
    main()
