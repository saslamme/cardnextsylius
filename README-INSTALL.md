# Cardnext – zentraler CSS-/Klassen-Refactor

Dieses Paket ist **kein weiteres CSS-Overlay**. Es ersetzt die bisherige SCSS-Kette durch eine einzige Quelle:

`assets/shop/styles/cardnext.css`

`assets/shop/entrypoint.js` importiert danach nur noch diese Cardnext-CSS-Datei. Die alten `_homepage.scss`, `_category.scss`, `_product.scss`, `_polish.scss` und Hotfix-Dateien werden nicht mehr geladen.

## Design Tokens

Alle zentralen Werte stehen genau einmal in `:root`:

- `--cn-primary: #e95126`
- `--cn-primary-hover: #c83b16`
- `--cn-ink: #20272d`
- `--cn-text: #3b454d`
- `--cn-muted: #6b747b`
- `--cn-border: #dce1e4`

`.btn-primary` nutzt dieselben Tokens und hat zusätzlich direkte Fallbackwerte. Damit bleibt der Button auch dann sichtbar, wenn ein CSS-Custom-Property aus irgendeinem Grund nicht aufgelöst wird.

## Wiederverwendbare Klassen

Die Templates wurden auf ein kleines Komponenten-/Primitive-System umgestellt. Beispiele:

- Layout: `cn-container`, `cn-section`, `cn-stack`, `cn-cluster`, `cn-grid`, `cn-split`
- Typografie: `cn-kicker`, `cn-title`, `cn-lead`, `cn-prose`
- Actions: `cn-btn`, `cn-link`
- Produkte: `cn-product-grid`, `cn-product-card`, `cn-status`
- Filter: `cn-accordion`, `cn-filter`, `cn-toolbar`
- Produktdetail: `cn-tabs`, `cn-specs`, `cn-resource`, `cn-qty`
- Oberflächen: `cn-card`, `cn-panel`, `cn-info-list`

Neue element- oder seitenbezogene Klassen sollen künftig nur noch entstehen, wenn die Struktur tatsächlich einzigartig ist.

## Installation

Vorher einen Sicherungs-Commit erstellen:

```bash
cd ~/public_html/cardnext
git status
git add -A
git commit -m "Backup before central CSS refactor"
```

ZIP im Projekt-Hauptverzeichnis entpacken und vorhandene Dateien überschreiben. Danach:

```bash
chmod +x cleanup-old-styles.sh
./cleanup-old-styles.sh
npm run build:prod
php bin/console cache:clear --env=prod
```

Anschließend Browser-Hard-Reload.

## Prüfen

Bitte Startseite, Kategorie, Produktdetail/Tabs, Kontakt, Login, Registrierung, Kundenkonto, Warenkorb, Checkout und mobile Navigation prüfen.

## Verifikation

Nach dem Entpacken kann die zentrale CSS-Struktur geprüft werden:

```bash
python3 verify-central-css.py
```

Der Check meldet u. a. nicht definierte `--cn-*` Variablen, alte `--cardnext-*` Referenzen und einen falschen Entrypoint.

## Rollback

Den Hash des Sicherungs-Commits notieren und bei Bedarf gezielt wiederherstellen:

```bash
git restore --source=<BACKUP_COMMIT_HASH> -- .
npm run build:prod
php bin/console cache:clear --env=prod
```
