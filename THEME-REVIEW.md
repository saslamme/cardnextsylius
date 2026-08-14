# Cardnext Theme Review – bereinigte Version

## Gefundene Probleme

1. **Header-CSS war mehrfach übereinander gepatcht**
   - `.cardnext-header__inner` wurde in den vorhandenen Dateien sehr oft neu definiert.
   - Navigation, Icon-Größen, Header-Höhen und Mobile-Breakpoints lagen in mehreren späteren Hotfix-Blöcken.
   - Das machte die tatsächliche Darstellung stark von der Reihenfolge abhängig.

2. **Produktseite enthielt mehrere Phasen in derselben Datei**
   - Basislayout, Phase 6, Phase 9.5, Breadcrumb-Fix und Lieferzeit-Fix waren hintereinander angehängt.
   - Mehrere identische Selektoren wurden außerhalb von Media Queries erneut überschrieben.
   - Dabei konnte z. B. eine Desktop-Regel nach einer früheren Mobile-Regel wieder gewinnen.
   - Die bereinigte Datei hat jetzt eine Basisdefinition und anschließend ausschließlich responsive Overrides.

3. **Kontakt-Button-Hotfix wurde gar nicht importiert**
   - `_contact-button-fix.scss` lag im Projekt, `cardnext.scss` importierte die Datei aber nicht.
   - Der Fix konnte daher nie wirken.
   - Die Datei ist jetzt überflüssig; der robuste Button-Stil ist direkt in `_contact.scss` integriert.

4. **Kontaktformular hatte doppelte Renderwege**
   - `form.html.twig` rendert E-Mail, Nachricht und Submit bereits direkt.
   - Gleichzeitig lagen noch die alten Hook-Templates `form/email.html.twig`, `form/message.html.twig` und `form/submit.html.twig` daneben.
   - Sie werden mit dem aktuellen Formular nicht benötigt.

5. **Alte Header-/Mega-Menü-Regeln waren noch in `cardnext.scss`**
   - Die aktuelle Navigation verwendet `cardnext-category-nav`, nicht mehr die alte `cardnext-nav`-/Mega-Menü-Struktur.
   - Diese Altlasten wurden aus der Basisdatei entfernt.

6. **Mobile Header**
   - Die mobile Grid-Struktur ist jetzt konsequent zweispaltig: Logo | Aktionen.
   - Warenkorb und Hamburger erhalten feste, nicht schrumpfende Breiten.

## Dateien

Die bereinigte Struktur bleibt bewusst klein:

```text
assets/shop/styles/
├── cardnext.scss
├── _homepage.scss
├── _category.scss
├── _product.scss
├── _header-shop.scss
└── _contact.scss
```

`_contact-button-fix.scss` wird nicht mehr benötigt.

## Installation

Vorher Backup:

```bash
cd ~/public_html/cardnext
cp -a assets/shop/styles assets/shop/styles.backup-$(date +%Y%m%d-%H%M%S)
cp -a assets/shop/cardnext.js assets/shop/cardnext.js.backup-$(date +%Y%m%d-%H%M%S)
```

Paket entpacken:

```bash
unzip -o cardnext-theme-clean-v1.zip
```

Nicht mehr benötigte Dateien löschen:

```bash
rm -f assets/shop/styles/_contact-button-fix.scss

rm -f templates/bundles/SyliusShopBundle/contact/contact_request/content/form/email.html.twig
rm -f templates/bundles/SyliusShopBundle/contact/contact_request/content/form/message.html.twig
rm -f templates/bundles/SyliusShopBundle/contact/contact_request/content/form/submit.html.twig
```

Validieren:

```bash
php bin/console lint:twig templates --env=prod
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

Assets:

```bash
nvm use 20.18.3
npm run build:prod
php bin/console assets:install public
php bin/console cache:clear --env=prod
```

Danach Browser hart neu laden.

## Empfohlene Sichtprüfung

- Desktop Header
- Mobile Header bei ca. 360–390 px Breite
- Kategorieseite
- Produktseite
- Kontaktseite
- Kontakt-Submit-Button
- Produkt-Lieferzeit
