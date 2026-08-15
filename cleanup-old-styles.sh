#!/usr/bin/env sh
set -eu
if [ ! -f "assets/shop/styles/cardnext.css" ]; then
    echo "Abbruch: assets/shop/styles/cardnext.css wurde nicht gefunden."
    exit 1
fi
rm -f \
    assets/shop/styles/cardnext.scss \
    assets/shop/styles/_homepage.scss \
    assets/shop/styles/_category.scss \
    assets/shop/styles/_category-polish-v2.scss \
    assets/shop/styles/_category-v7a-hotfix.scss \
    assets/shop/styles/_product.scss \
    assets/shop/styles/_product-v8-polish.scss \
    assets/shop/styles/_product-v8a-dark-tabs.scss \
    assets/shop/styles/_product-v8b-tabs-visible.scss \
    assets/shop/styles/_header-shop.scss \
    assets/shop/styles/_contact.scss \
    assets/shop/styles/_polish.scss \
    assets/shop/styles/_storefront-system.scss
echo "Alte Cardnext-SCSS-Dateien entfernt."
echo "Jetzt: npm run build:prod && php bin/console cache:clear --env=prod"
