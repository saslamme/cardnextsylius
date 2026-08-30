# Cardnext Sylius

Cardnext Sylius ist die Commerce-Anwendung für die Cardnext-Shops in Deutschland und Österreich. Das Projekt erweitert Sylius um Produktkonfiguration, B2B-Preise, Angebotsanfragen, verbindliche Angebote, PDF-Ausgabe und die kontrollierte Überführung angenommener Angebote in Sylius-Bestellungen.

> Der verifizierte technische Audit und die priorisierte Arbeit sind in [ROADMAP.md](ROADMAP.md) dokumentiert. Die Roadmap ist kein Ersatz für Tickets; Findings müssen vor ihrer Umsetzung erneut gegen den dann aktuellen Stand geprüft werden.

## Technologiestack

- PHP `^8.3`, Symfony `^7.4`, Sylius `^2.2.6`
- Doctrine ORM/Migrations, MySQL, Twig und Sylius Twig Hooks
- Symfony Security und Mailer, Dompdf
- PHPUnit, PHPStan 2, Sylius Labs Coding Standard/ECS
- Webpack Encore, Stimulus und JavaScript/CSS-Assets unter `assets/`

Die tatsächlich aufgelösten Versionen stehen in `composer.lock` und `package-lock.json`.

## Architektur

Die Anwendung folgt grundsätzlich den Sylius-Aggregaten und erweitert deren konkrete App-Entities unter `src/Entity/`. Eigene Shop- und Admin-Controller liegen unter `src/Controller/`, Geschäftslogik unter `src/Service/`, Formulare unter `src/Form/`, Templates unter `templates/` und Konfiguration unter `config/`. Sylius-Factories, Repositories, Modifier und Number-Assigner sollen anstelle paralleler Commerce-Abstraktionen verwendet werden.

### Channels

Die Geschäftsbereiche `CARDNEXT_DE` und `CARDNEXT_AT` werden über Sylius-Channels isoliert. Channel, Locale und Currency sind bei Angebotsanfragen und Angeboten als Snapshot gespeichert. Account-Zugriffe filtern zusätzlich nach dem aktuellen Channel. Canonical Hosts und rechtliche Ausstellerdaten sind deployment- bzw. fachlich freizugebende Konfiguration; sie dürfen nicht aus dem eingehenden Admin-Request abgeleitet werden.

## Angebotsworkflow

1. Ein Besucher stellt einen Angebotskorb zusammen und sendet eine `QuoteRequest`.
2. Die Anfrage speichert Kontakt-, Produkt-, Channel-, Locale- und Currency-Snapshots; bei einem angemeldeten Nutzer zusätzlich den Sylius-Customer.
3. Ein Administrator erstellt und bearbeitet versionierte `Quote`-Entities und friert Positionen, Rabatte, Service, Versand und Steuer ein.
4. Das Angebot wird per Mail ausschließlich in das geschützte Kundenkonto verlinkt.
5. Der Kunde kann ein eigenes, zum aktuellen Channel gehörendes Angebot ansehen, als PDF laden sowie mit CSRF-Schutz annehmen oder ablehnen.
6. Ein angenommenes Angebot kann einmalig in eine Sylius-Bestellung überführt werden.

Öffentliche Token-Links für Angebote gehören **nicht** zur aktuellen Architektur.

### Account-Sicherheit

Angebotsrouten liegen unter `/{_locale}/account/angebote`. Symfony Security verlangt dort `ROLE_USER`. Die Anwendung löst `ShopUser -> Customer` auf und sucht Angebote mit Customer, Channel, Status, Nummer und Version; fremde oder unzulässige Angebote ergeben 404. Entscheidungen sind POST-only und CSRF-geschützt. Account- und PDF-Antworten setzen `Cache-Control: private, no-store` und `X-Robots-Tag: noindex, nofollow, noarchive`.

### Quote zu Order

`QuoteOrderConverter` verwendet die Sylius Order-/OrderItem-Factories, den Quantity Modifier und den Order Number Assigner, sperrt persistierte Angebote pessimistisch und arbeitet transaktional. Angebotswerte bleiben Snapshots; eine abschließende harte Invariante verlangt `order.total === quote.grandTotal`. Der Audit hat dennoch fachlich relevante offene Punkte zur Customer-Identität, zur expliziten Behandlung aller Positionstypen und zum Sylius-Workflow festgestellt; siehe ROADMAP.

## Lokale Installation

Voraussetzungen: PHP 8.3/8.4 mit den von Composer verlangten Extensions (insbesondere `intl`, `gd`, `pdo_mysql`, `zip` und `sodium`), Composer 2, Node.js/npm und MySQL 8.

```bash
git clone https://github.com/saslamme/cardnextsylius.git
cd cardnextsylius
composer install
npm ci
cp .env .env.local
# DATABASE_URL, APP_SECRET, MAILER_DSN und deployment-spezifische Werte setzen
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console sylius:install --no-interaction
npm run build
```

Die konkrete Installationsreihenfolge ist je nach leerer Bestandsdatenbank oder Deployment-Upgrade festzulegen. Niemals Produktions-Secrets committen; `.env.local` bzw. Secret Management verwenden.

## Umgebungsvariablen

Mindestens zu prüfen sind `APP_ENV`, `APP_SECRET`, `DATABASE_URL`, `MAILER_DSN` und `CARDNEXT_QUOTE_RECIPIENT`. Der Audit empfiehlt, den derzeit gekoppelten Angebots-Absender und internen Empfänger in getrennte Werte aufzuteilen. Canonical Account-URLs und vollständige Ausstellerprofile sollten ebenfalls explizit pro Deployment/Channel konfiguriert und beim Booten oder Versenden validiert werden. Keine Beispielwerte in diesem Repository sind als freigegebene Produktions-Rechtsdaten zu verstehen.

## Doctrine und Migrationen

```bash
php bin/console doctrine:migrations:status
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:schema:validate
```

- Ausgeführte Migrationen sind immutable. Änderungen erfolgen ausschließlich durch eine neue, vorwärts gerichtete Migration.
- Migrationen werden auf einer Kopie des Produktionsschemas sowie für vollständigen Up- und Down-/Recovery-Pfad getestet.
- Vor jedem Deployment: Backup, Migrationsstatus, SQL-Review und dokumentierter Rollback/Forward-Fix.
- Kein `doctrine:schema:update --force` in Produktion.

### `cardnext_quote_sequence`

`cardnext_quote_sequence` ist eine bewusst custom/unmanaged Tabelle des atomaren Angebotsnummern-Generators. Doctrine kann deshalb bei einem Schema-Diff fälschlich `DROP TABLE cardnext_quote_sequence` vorschlagen. **Diesen Drop niemals ausführen oder in eine Migration übernehmen.** Diffs müssen manuell geprüft werden.

## Qualitätsprüfungen

```bash
composer validate --strict --no-check-version
composer audit
composer check-platform-reqs
php bin/console about
php bin/console lint:container
php bin/console lint:yaml config translations
php bin/console lint:twig templates
vendor/bin/ecs check
vendor/bin/phpstan analyse src tests
vendor/bin/phpunit tests
php bin/console doctrine:schema:validate
php bin/console doctrine:migrations:status
php bin/console debug:router
```

ECS-Formatbereinigungen gehören in einen eigenen mechanischen PR. PHPStan soll über die versionierte `phpstan.dist.neon` deterministisch laufen; Fehler werden an der Ursache behoben und nicht durch breite Ignore-Listen verborgen.

## Deployment

1. Backup und Wartungs-/Rollbackplan bestätigen.
2. Composer-Plattformanforderungen und Security Audit prüfen.
3. Produktionskonfiguration, Canonical Hosts, Mail-Absender/-Empfänger und Ausstellerdaten validieren.
4. Assets reproduzierbar mit `npm ci` und `npm run build` bauen.
5. Migrationen zunächst gegen Staging/Produktionskopie, dann mit `--no-interaction` ausführen.
6. Cache warmen, Worker neu starten und Smoke Tests durchführen.
7. Logs, Mailzustellung, Queue/Worker, Fehlerquote und Order-/Quote-Invarianten beobachten.

## Smoke Test

- DE- und AT-Homepage, Kategorie, Produkt und Konfigurator öffnen.
- Registrierung/Login/Logout und Kundenkonto prüfen.
- Angebotsanfrage mit Produkt erstellen; interne und Kunden-Mail prüfen.
- Angebot administrativ erstellen, freigeben und versenden.
- Eigener Kunde: Seite/PDF/Annahme; anderer Kunde und anderer Channel: 404.
- Annahme doppelt absenden; höchstens eine Order und identische Customer-ID erwarten.
- Order-Summen, Currency, Locale, Adressen, Rabatte, Steuer, Service und Versand gegen PDF prüfen.
- Admin-Orderansicht, Checkout sowie konfigurierte Artikel stichprobenartig prüfen.

## Beitragen

Kleine, fokussierte PRs verwenden; Tests und Migration Notes beilegen. Fachliche Refactorings nicht mit ECS-Massendiffs kombinieren. Security-, Money-, Customer- und Order-Änderungen benötigen Regressionstests und Review gegen die installierte Sylius-2.2-API.
