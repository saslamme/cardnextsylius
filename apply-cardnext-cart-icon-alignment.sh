#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

CSS="assets/shop/styles/cardnext.css"
STAMP="$(date +%Y%m%d-%H%M%S)"

if [ ! -f "$CSS" ]; then
    echo "FEHLER: $CSS wurde nicht gefunden."
    exit 1
fi

echo "Backup..."
cp "$CSS" "${CSS}.before-cart-icon-alignment-${STAMP}"

python3 patch-cardnext-cart-icon-alignment.py

echo
echo "Assets bauen..."
npm run build:prod

echo
echo "Cache..."
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod

echo
echo "Fertig."
echo "Warenkorb und Mein Konto sollten jetzt auf derselben vertikalen Linie sitzen."
