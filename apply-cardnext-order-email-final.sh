#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

STAMP="$(date +%Y%m%d-%H%M%S)"

TARGETS=(
    "templates/bundles/SyliusShopBundle/email/order_confirmation.html.twig"
    "templates/email/internal_order_notification.html.twig"
    "src/EventListener/InternalOrderNotificationListener.php"
)

echo "Backups..."
for target in "${TARGETS[@]}"; do
    if [ -f "$target" ]; then
        cp "$target" "${target}.before-cardnext-order-email-final-${STAMP}"
    fi
done

echo "Dateien installieren..."
while IFS= read -r -d '' source; do
    relative="${source#overlay/}"
    mkdir -p "$(dirname "$relative")"
    cp "$source" "$relative"
done < <(find overlay -type f -print0)

echo
echo "PHP prüfen..."
php -l src/EventListener/InternalOrderNotificationListener.php

echo
echo "Twig prüfen..."
php bin/console lint:twig \
    templates/bundles/SyliusShopBundle/email/order_confirmation.html.twig \
    templates/email/internal_order_notification.html.twig \
    --env=prod

echo
echo "Listener prüfen..."
php bin/console debug:event-dispatcher sylius.order.post_complete --env=prod | grep -E "InternalOrderNotificationListener|OrderCompleteListener" || true

if [ ! -f "config/packages/sylius_mailer.yaml" ]; then
    echo
    echo "HINWEIS: config/packages/sylius_mailer.yaml wurde nicht gefunden."
    echo "Der SMTP-Transport kann trotzdem funktionieren, aber der Sylius-Absender sollte separat konfiguriert werden."
fi

echo
echo "Cache..."
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod

echo
echo "Fertig."
echo
echo "Bei der nächsten Testbestellung sollten zwei Mails versendet werden:"
echo "1. Kunden-Bestellbestätigung"
echo "2. Interne Benachrichtigung an info@cardnext.de"
