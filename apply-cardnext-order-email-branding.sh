#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

CUSTOMER="templates/bundles/SyliusShopBundle/email/order_confirmation.html.twig"
INTERNAL="templates/email/internal_order_notification.html.twig"
STAMP="$(date +%Y%m%d-%H%M%S)"

if [ ! -f "$CUSTOMER" ]; then
    echo "FEHLER: $CUSTOMER wurde nicht gefunden."
    exit 1
fi

echo "Backups..."
cp "$CUSTOMER" "${CUSTOMER}.before-cardnext-branding-${STAMP}"

if [ -f "$INTERNAL" ]; then
    cp "$INTERNAL" "${INTERNAL}.before-cardnext-branding-${STAMP}"
fi

python3 patch-cardnext-order-email-branding.py

echo
echo "Twig prüfen..."
php bin/console lint:twig "$CUSTOMER" --env=prod

if [ -f "$INTERNAL" ]; then
    php bin/console lint:twig "$INTERNAL" --env=prod
fi

echo
echo "Cache..."
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod

echo
echo "Fertig."
echo "Bestellmail verwendet jetzt Cardnext Navy/Orange und das cardneXt-Wortlogo."
