#!/usr/bin/env bash
# Regenerates .wordpress-org/ (screenshots, banners, icons) from the local
# demo site. Run bin/dev-site.sh --fresh and `wp server --path=.wp --port=8990`
# first. Needs a Chrome or chrome-headless-shell binary (set CHROME=...).
set -euo pipefail
cd "$(dirname "$0")/.."

BASE=${BASE:-http://localhost:8990}
CHROME=${CHROME:-$(ls -d ~/.cache/ms-playwright/chromium_headless_shell-*/*/ 2>/dev/null | tail -1)chrome-headless-shell}
[ -x "$CHROME" ] || { echo "Chrome not found; set CHROME=/path/to/chrome" >&2; exit 1; }
OUT=.wordpress-org
mkdir -p "$OUT"

shot() { # file, url, width,height, [budget ms], [scale]
    for attempt in 1 2 3; do # Headless Chrome sometimes hangs on a page; try again.
        timeout 90 "$CHROME" --headless --no-sandbox --hide-scrollbars --force-device-scale-factor="${5:-2}" \
            --virtual-time-budget="${4:-5000}" --window-size="$3" --screenshot="$PWD/$1" "$2" 2>/dev/null >/dev/null && break
    done
    convert "$1" -strip -define png:compression-level=9 "$1"
    echo "$1"
}
edit() { # path for the block editor, signed in for real
    echo "$BASE/?shot_login=maya&to=$(python3 -c 'import sys,urllib.parse;print(urllib.parse.quote(sys.argv[1],safe=""))' "$1")"
}
id() { wp post list --path=.wp --post_type="$1" --name="$2" --field=ID --post_status=any; }

KENJI="shot_as=kenji.mori"
shot "$OUT/screenshot-1.png"  "$BASE/feedback/?$KENJI"                                   1440,1000
shot "$OUT/screenshot-2.png"  "$BASE/feedback/requests/usage-based-billing/?$KENJI"      1440,1200
shot "$OUT/screenshot-3.png"  "$BASE/feedback/roadmap/?$KENJI"                           1440,900
shot "$OUT/screenshot-4.png"  "$BASE/feedback/changelog/?$KENJI"                         1440,1100
shot "$OUT/screenshot-5.png"  "$BASE/feedback/changelog/usage-based-billing-is-here/?$KENJI&shot_open=1" 1440,760
shot "$OUT/screenshot-6.png"  "$BASE/feedback/new/?$KENJI&fb_title=Sync%20invoices%20with%20QuickBooks" 1440,900
shot "$OUT/screenshot-7.png"  "$BASE/wp-admin/edit.php?post_type=ruvelo_request&orderby=ruvelo_votes&order=desc&shot_as=maya" 1440,900
shot "$OUT/screenshot-8.png"  "$(edit "/wp-admin/post.php?post=$(id ruvelo_request two-way-xero-sync)&action=edit")" 1440,1100 30000
shot "$OUT/screenshot-9.png"  "$BASE/wp-admin/edit.php?post_type=ruvelo_request&page=ruvelo-feedback-settings&shot_as=maya" 1440,1300
shot "$OUT/screenshot-10.png" "$BASE/wp-admin/edit.php?post_type=ruvelo_request&page=ruvelo-feedback-stats&shot_as=maya" 1440,1000
shot "$OUT/screenshot-11.png" "$BASE/feedback/roadmap/?$KENJI"                           390,844 5000 2
shot "$OUT/screenshot-12.png" "$BASE/feedback/requests/dark-mode/?$KENJI&shot_scheme=dark" 1440,1000

# Banners and icons, from bin/art/.
convert "$OUT/screenshot-1.png" -crop 2880x1500+250+260 -resize 1300x bin/art/board-crop.png
shot "$OUT/banner-772x250.png"   "file://$PWD/bin/art/banner.html" 772,250 3000 1
shot "$OUT/banner-1544x500.png"  "file://$PWD/bin/art/banner.html" 772,250 3000 2
"$CHROME" --headless --no-sandbox --hide-scrollbars --default-background-color=00000000 --force-device-scale-factor=1 --virtual-time-budget=3000 --window-size=128,128 --screenshot="$OUT/icon-128x128.png" "file://$PWD/bin/art/icon.html" 2>/dev/null >/dev/null
"$CHROME" --headless --no-sandbox --hide-scrollbars --default-background-color=00000000 --force-device-scale-factor=2 --virtual-time-budget=3000 --window-size=128,128 --screenshot="$OUT/icon-256x256.png" "file://$PWD/bin/art/icon.html" 2>/dev/null >/dev/null
rm -f bin/art/board-crop.png
echo "Done. Look at every image before committing."
