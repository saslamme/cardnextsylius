#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

OUTER="templates/bundles/SyliusShopBundle/product/show/content/info/summary/add_to_cart.html.twig"
QUANTITY="templates/bundles/SyliusShopBundle/product/show/content/info/summary/add_to_cart/quantity.html.twig"
SUBMIT="templates/bundles/SyliusShopBundle/product/show/content/info/summary/add_to_cart/submit.html.twig"
CSS="assets/shop/styles/cardnext.css"
STAMP="$(date +%Y%m%d-%H%M%S)"

if [ ! -f "$OUTER" ]; then
    echo "FEHLER: $OUTER wurde nicht gefunden."
    exit 1
fi

echo "Backups..."
cp "$OUTER" "${OUTER}.before-purchase-restore-${STAMP}"
[ -f "$QUANTITY" ] && cp "$QUANTITY" "${QUANTITY}.before-purchase-restore-${STAMP}"
[ -f "$SUBMIT" ] && cp "$SUBMIT" "${SUBMIT}.before-purchase-restore-${STAMP}"
[ -f "$CSS" ] && cp "$CSS" "${CSS}.before-purchase-restore-${STAMP}"

python3 restore-cardnext-purchase-structure.py

echo
echo "Struktur prüfen..."
grep -n "cn-purchase" "$OUTER"
grep -n "cn-quantity" "$QUANTITY"
grep -n "cn-purchase__submit" "$SUBMIT"

echo
echo "Twig prüfen..."
php bin/console lint:twig \
    "$OUTER" \
    "$QUANTITY" \
    "$SUBMIT" \
    --env=prod

echo
echo "Assets..."
npm run build:prod

echo
echo "Cache..."
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod

echo
echo "Fertig."
echo "Mengenwahl und 'In den Warenkorb' sollten jetzt wieder in derselben Zeile stehen."
echo "Das automatische Öffnen von #offcanvasCart bleibt erhalten."
