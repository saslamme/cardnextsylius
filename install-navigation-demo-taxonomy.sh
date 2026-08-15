#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

if [ ! -f "bin/console" ]; then
    echo "FEHLER: Kein Sylius/Symfony-Projektstamm gefunden."
    echo "Bitte ZIP in ~/public_html/cardnext entpacken."
    exit 1
fi

SOURCE="overlay/src/Command/CardnextSetupNavigationDemoCommand.php"
TARGET="src/Command/CardnextSetupNavigationDemoCommand.php"

mkdir -p src/Command

if [ -f "$TARGET" ]; then
    STAMP="$(date +%Y%m%d-%H%M%S)"
    cp "$TARGET" "${TARGET}.before-navigation-demo-${STAMP}"
    echo "Backup: ${TARGET}.before-navigation-demo-${STAMP}"
fi

cp "$SOURCE" "$TARGET"

php -l "$TARGET"
php bin/console cache:clear --env=prod
php bin/console app:cardnext:setup-navigation-demo --env=prod
php bin/console cache:warmup --env=prod

echo
echo "Fertig: Demo-Unterkategorien wurden angelegt/aktualisiert."
