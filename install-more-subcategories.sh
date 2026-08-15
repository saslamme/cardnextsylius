#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

if [ ! -f "bin/console" ]; then
    echo "FEHLER: Kein Symfony/Sylius-Projektstamm gefunden."
    echo "Bitte ZIP in ~/public_html/cardnext entpacken."
    exit 1
fi

SOURCE="overlay/src/Command/CardnextExtendNavigationDemoCommand.php"
TARGET="src/Command/CardnextExtendNavigationDemoCommand.php"

mkdir -p src/Command

if [ -f "$TARGET" ]; then
    STAMP="$(date +%Y%m%d-%H%M%S)"
    cp "$TARGET" "${TARGET}.before-more-subcategories-${STAMP}"
fi

cp "$SOURCE" "$TARGET"

php -l "$TARGET"
php bin/console cache:clear --env=prod
php bin/console app:cardnext:extend-navigation-demo --env=prod
php bin/console cache:warmup --env=prod

echo
echo "Fertig."
echo "Kartendrucker und RFID-Leser haben jetzt jeweils acht Demo-Unterkategorien."
