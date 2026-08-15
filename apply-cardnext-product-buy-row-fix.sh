#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

CSS="assets/shop/styles/cardnext.css"
STAMP="$(date +%Y%m%d-%H%M%S)"

if [ ! -f "$CSS" ]; then
    echo "FEHLER: $CSS wurde nicht gefunden."
    exit 1
fi

if ! grep -q "cn-purchase" "$CSS"; then
    echo "FEHLER: Der Cardnext Produkt-Kaufbereich wurde in der CSS nicht gefunden."
    exit 1
fi

echo "Backup..."
cp "$CSS" "${CSS}.before-product-buy-row-fix-${STAMP}"

python3 patch-cardnext-product-buy-row.py

echo
echo "Assets bauen..."
npm run build:prod

echo
echo "Cache..."
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod

echo
echo "Fertig."
echo "Bitte Produktdetailseite neu laden und Mengenwahl + Warenkorb-Button prüfen."
