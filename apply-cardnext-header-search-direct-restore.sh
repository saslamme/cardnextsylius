#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

JS="assets/shop/cardnext.js"
STAMP="$(date +%Y%m%d-%H%M%S)"

if [ ! -f "$JS" ]; then
    echo "FEHLER: $JS wurde nicht gefunden."
    exit 1
fi

echo "Backup..."
cp "$JS" "${JS}.before-header-search-direct-restore-${STAMP}"

python3 patch-cardnext-header-search-direct.py

echo
echo "Assets bauen..."
npm run build:prod

echo
echo "Cache..."
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod

echo
echo "Fertig."
echo
echo "Sollverhalten:"
echo "- Desktop-Suchfeld bleibt die einzige Suche"
echo "- Enter oder Lupe -> direkte Smart Search"
echo "- keine zweite Suchleiste unterhalb der Navigation"
echo "- Suggestions bleiben erhalten"
