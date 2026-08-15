#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

HEADER="templates/shop/layout/header/content.html.twig"
CATEGORIES="templates/shop/layout/header/categories.html.twig"
JS="assets/shop/cardnext.js"
STAMP="$(date +%Y%m%d-%H%M%S)"

for file in "$HEADER" "$CATEGORIES" "$JS"; do
    if [ ! -f "$file" ]; then
        echo "FEHLER: $file wurde nicht gefunden."
        exit 1
    fi
done

echo "Backups..."
cp "$HEADER" "${HEADER}.before-search-structural-final-${STAMP}"
cp "$CATEGORIES" "${CATEGORIES}.before-search-structural-final-${STAMP}"
cp "$JS" "${JS}.before-search-structural-final-${STAMP}"

python3 patch-cardnext-header-smart-search.py
python3 remove-cardnext-secondary-search.py
python3 cleanup-cardnext-search-js.py

echo
echo "Struktur prüfen..."

if grep -q "data-cardnext-search-open" "$HEADER"; then
    echo "FEHLER: alter Search-Open-Trigger ist noch im Header."
    exit 1
fi

if grep -q 'class="cn-search-form"' "$CATEGORIES"; then
    echo "FEHLER: zweite Desktop-Suche ist noch in categories.html.twig."
    exit 1
fi

grep -n "cardnext_shop_search" "$HEADER"
grep -n "data-cn-smart-search-input" "$HEADER"
grep -n "data-cn-search-suggest" "$HEADER"

echo
echo "Twig prüfen..."
php bin/console lint:twig "$HEADER" "$CATEGORIES" --env=prod

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
echo "- nur EIN Suchfeld im Desktop-Header"
echo "- Eingabe direkt im sichtbaren Feld"
echo "- Live-Suggestions ab 2 Zeichen"
echo "- Enter/Lupe -> /suche?q=..."
echo "- KEIN zweites Suchpanel unter der Navigation"
