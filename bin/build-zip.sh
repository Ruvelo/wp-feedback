#!/usr/bin/env bash
# Builds build/ruvelo-feedback.zip: the plugin as WordPress.org and the
# GitHub release ship it. No Composer needed at runtime (the plugin has its
# own PSR-4 loader), so vendor/ isn't included.
set -euo pipefail
cd "$(dirname "$0")/.."

out=build/ruvelo-feedback
rm -rf build && mkdir -p "$out"
cp ruvelo-feedback.php uninstall.php readme.txt LICENSE "$out/"
cp -R src blocks templates assets languages "$out/"
mkdir -p "$out/demo/assets"
cp demo/content.php "$out/demo/"
cp demo/assets/*.jpg "$out/demo/assets/"
if command -v zip >/dev/null; then
    (cd build && zip -qr ruvelo-feedback.zip ruvelo-feedback)
else
    (cd build && python3 -m zipfile -c ruvelo-feedback.zip ruvelo-feedback)
fi
echo "build/ruvelo-feedback.zip ($(du -h build/ruvelo-feedback.zip | cut -f1))"
