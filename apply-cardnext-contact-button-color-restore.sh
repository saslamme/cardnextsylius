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
cp "$CSS" "${CSS}.before-contact-button-color-${STAMP}"

python3 patch-cardnext-contact-button-color.py

echo
echo "Prüfen..."
grep -n -A12 -B2 "CARDNEXT CONTACT SUBMIT:START" "$CSS"

echo
echo "Assets bauen..."
npm run build:prod

echo
echo "Cache..."
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod

echo
echo "Fertig."
echo "Der Button 'Nachricht senden' ist wieder Cardnext-Orange mit weißer Schrift."
