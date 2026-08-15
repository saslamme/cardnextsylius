#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

STAMP="$(date +%Y%m%d-%H%M%S)"

echo "Backups..."
[ -f assets/shop/styles/cardnext.css ] && cp assets/shop/styles/cardnext.css "assets/shop/styles/cardnext.css.before-cart-badge-dot-${STAMP}"
[ -f assets/shop/app.js ] && cp assets/shop/app.js "assets/shop/app.js.before-cart-badge-dot-${STAMP}"

echo "Overlay kopieren..."
mkdir -p assets/shop/cardnext
cp -f overlay/assets/shop/cardnext/header-cart-badge.js assets/shop/cardnext/header-cart-badge.js

echo "Patches anwenden..."
python3 patch-cardnext-cart-badge-css.py
python3 patch-cardnext-cart-badge-app.py

echo
echo "Assets bauen..."
npm run build:prod

echo
echo "Cache leeren..."
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod

echo
echo "Fertig."
echo "Die Warenkorb-Anzahl sollte jetzt als orange Badge direkt am Icon erscheinen."
