#!/usr/bin/env python3
"""Adds `file:` to every chapter in curriculum.yaml whose chapter file exists.

Chapter files are named <subject>/u<unit>-c<chapter>.yaml. Run from anywhere:
    python3 content/tools/link-files.py
"""
import pathlib
import re

root = pathlib.Path(__file__).resolve().parent.parent / "grade12"
path = root / "curriculum.yaml"
lines = path.read_text().splitlines()
out = []
subject = unit = None
i = 0
added = 0
while i < len(lines):
    line = lines[i]
    out.append(line)
    if m := re.match(r"^- code: (\w+)", line):
        subject = m.group(1)
    elif m := re.match(r"^  - number: (\d+)", line):
        unit = m.group(1)
    elif m := re.match(r"^    - number: (\d+)", line):
        chapter = m.group(1)
        # copy the title line(s) and any existing file line
        j = i + 1
        block = []
        while j < len(lines) and lines[j].startswith("      "):
            block.append(lines[j])
            j += 1
        has_file = any(b.startswith("      file:") for b in block)
        out.extend(block)
        name = f"{subject}/u{unit}-c{chapter}.yaml"
        if not has_file and (root / name).exists():
            out.append(f"      file: {name}")
            added += 1
        i = j
        continue
    i += 1
path.write_text("\n".join(out) + "\n")
print(f"linked {added} new chapter file(s)")
