#!/usr/bin/env bash
# Copies the landing-page app pictures (test/golden/goldens/landing/*.png, drawn by
# test/golden/landing_screens_test.dart) to the website as WebP, 540 px wide.
# Run from app/ after `flutter test test/golden/landing_screens_test.dart --update-goldens`.
# Needs ImageMagick (`convert`).
set -euo pipefail
cd "$(dirname "$0")/.."
out=../backend/public/img/landing
mkdir -p "$out"
for png in test/golden/goldens/landing/*.png; do
  name=$(basename "$png" .png)
  convert "$png" -resize 540x -strip -define webp:method=6 -quality 80 "$out/$name.webp"
  echo "$out/$name.webp"
done
