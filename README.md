# Cardnext – Phase 18 v8b: Produkt-Tabs sichtbar + Abstände korrigiert

Der aktuelle HTML-Quelltext enthält die Tab-Navigation korrekt, sie wurde
optisch aber nicht zuverlässig dargestellt.

Dieser Hotfix verwendet deshalb bewusst höhere CSS-Spezifität und erzwingt die
Darstellung nur auf der Sylius-Produktdetailroute.

## Änderungen

- dunkle Tab-Leiste `#20272d`
- Tabs sicher sichtbar
- aktive Schrift weiß
- inaktive Schrift hellgrau
- orange aktive Unterstreichung
- keine nativen Button-Rahmen
- kein Zeilenumbruch
- mobil horizontal scrollbar
- Abstand zwischen Kaufbereich und Tabs reduziert
- aktiver Tab-Inhalt beginnt kompakter
- versteckte Tabpanels reservieren garantiert keinen Platz

## Installation

```bash
cd ~/public_html/cardnext

unzip -o cardnext-sylius-phase18-v8b-product-tabs-visible.zip

nvm use 20.18.3
npm run build:prod

php bin/console assets:install public

rm -rf var/cache/prod
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

## Schnelle Kontrolle nach dem Build

Der neue Hotfix muss im kompilierten CSS vorhanden sein:

```bash
grep -R "Force visible dark product tab navigation" public/build -n | head
```

Wenn der grep-Treffer vorhanden ist, aber die Leiste im Browser noch nicht
sichtbar ist, bitte den Browser-Cache einmal hart neu laden.
