#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

CSS="assets/shop/styles/cardnext.css"
JS="assets/shop/cardnext.js"
STALE="assets/shop/cardnext/header-cart-badge.js"
STAMP="$(date +%Y%m%d-%H%M%S)"

for file in "$CSS" "$JS"; do
    if [ ! -f "$file" ]; then
        echo "FEHLER: $file wurde nicht gefunden."
        exit 1
    fi
done

echo "Backups..."
cp "$CSS" "${CSS}.before-cart-badge-dot-fixed-${STAMP}"
cp "$JS" "${JS}.before-cart-badge-dot-fixed-${STAMP}"

python3 patch-cardnext-cart-badge-css.py
python3 patch-cardnext-cart-badge-js.py

if [ -f "$STALE" ]; then
    rm -f "$STALE"
    echo "OK: unnötige alte Zwischen-Datei $STALE entfernt."
fi

echo
echo "Prüfen..."
grep -n "CARDNEXT HEADER CART BADGE" "$JS"
grep -n "CARDNEXT CART BADGE DOT" "$CSS"

echo
echo "Assets bauen..."
npm run build:prod

echo
echo "Cache..."
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod

echo
echo "Fertig."
echo "Die Warenkorb-Anzahl wird jetzt als orange runde Badge direkt am Icon dargestellt."
