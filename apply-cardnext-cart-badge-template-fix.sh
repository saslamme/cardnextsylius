#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

TEMPLATE="templates/shop/layout/header/cart.html.twig"
CSS="assets/shop/styles/cardnext.css"
JS="assets/shop/cardnext.js"
STALE="assets/shop/cardnext/header-cart-badge.js"
STAMP="$(date +%Y%m%d-%H%M%S)"

for file in "$TEMPLATE" "$CSS" "$JS"; do
    if [ ! -f "$file" ]; then
        echo "FEHLER: $file wurde nicht gefunden."
        exit 1
    fi
done

echo "Backups..."
cp "$TEMPLATE" "${TEMPLATE}.before-cart-badge-template-${STAMP}"
cp "$CSS" "${CSS}.before-cart-badge-template-${STAMP}"
cp "$JS" "${JS}.before-cart-badge-template-${STAMP}"

python3 patch-cardnext-cart-template.py
python3 patch-cardnext-cart-badge-css.py
python3 remove-cardnext-cart-badge-js.py

if [ -f "$STALE" ]; then
    rm -f "$STALE"
    echo "OK: alte Zwischen-Datei entfernt."
fi

echo
echo "Twig prüfen..."
php bin/console lint:twig "$TEMPLATE" --env=prod

echo
echo "Struktur prüfen..."
grep -n -A12 -B4 "cardnext-cart-badge" "$TEMPLATE"

echo
echo "Assets bauen..."
npm run build:prod

echo
echo "Cache..."
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod

echo
echo "Fertig."
echo "Das Warenkorb-SVG bleibt unverändert im Template."
echo "Die vorhandene Mengenanzeige sitzt jetzt als orange Badge direkt am SVG."
