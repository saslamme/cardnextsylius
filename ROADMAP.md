# Cardnext Technical Audit & Roadmap

## Audit-Basis

Audit des Repository-Stands `ca1167e` (lokaler aktueller Stand des bereitgestellten Main-Snapshots) am 30.08.2026. Referenz waren der eigene Code, `composer.lock` (Sylius 2.2.8/Symfony 7.4), die installierten Vendor-Sourcen nach einem nur lokal mit ignorierter `ext-sodium` ausgeführten Install sowie die unten dokumentierten Befehle. Es wurden keine PHP-, Twig-, Routing-, Security- oder Doctrine-Dateien geändert.

## Scope

Geprüft wurden eigener Code unter `src/`, Entities/Doctrine-Attribute, sämtliche eigenen Migrationen, `config/`, Routen, Security, Services/DI, Twig/Templates/Hooks, Mail/PDF, Quote-Account und Quote-to-Order, Tests, Composer/PHPStan/ECS sowie GitHub Actions. Bestandsdaten wurden nicht verändert. Eine echte MySQL-Verifikation war in der Audit-Umgebung nicht möglich.

## Executive Summary

**Audit result:** P0: **0**, P1: **4**, P2: **7**, P3: **3**.

Die Account-Autorisierung ist im aktuellen Code grundsätzlich sauber: Login, Customer- und Channel-Filter, Statusfilter, 404 bei fremdem Objekt, POST/CSRF und private/no-store/noindex sind vorhanden. Öffentliche Angebots-Token-Routen wurden nicht gefunden. Quote-to-Order ist transaktional, pessimistisch gesperrt, idempotent geprüft und schützt die Gesamtsumme. Zwei zentrale fachliche Fehler bleiben bestätigt: Der Converter ignoriert `Quote.customer` und sucht per Snapshot-E-Mail; außerdem fallen `Service` und `Shipping` implizit in den Product-OrderItem-Pfad. Die CI prüft falsche Verzeichnisse, lässt Security-Fehler zu und führt ECS/PHPUnit/Composer Quality Gates nicht vollständig aus.

### Verifikation der bekannten Hypothesen

- Customer Ownership: **Status: Code-Fix in PR #119 umgesetzt; Staging-/Runtime-Verifikation ausstehend** (P1-001).
- Quote Account Security: **Status: bereits behoben**; funktionale Regressionstests fehlen (P1-004).
- Public Quote URLs: **Status: bereits behoben**; keine Quote-Token-Route/Controller/Mail-URL gefunden.
- Transaktion, Sperre, Idempotenz, Sylius-Factories/Modifier/Number-Assigner und Total-Invariante: **Status: bereits behoben**. Customer und Enum-Dispatch bleiben offen.
- OrderItem-DI: **Status: bereits behoben** durch explizite Service-Bindings.
- Argon2i-Konfiguration: **Status: nicht bestätigt** als Fehler; sie entspricht der mitgelieferten Sylius-2.2-App-Konfiguration. Runtime muss Sodium/Argon2 bereitstellen.

## P0

Keine bestätigten P0-Findings. Insbesondere wurde kein öffentlicher Zugriff auf fremde Angebote bestätigt.

## P1

### P1-001 — Quote Customer Identity

- **ID/Priorität/Status/Bereich:** P1-001 / P1 / Code-Fix in PR #119 umgesetzt; Staging-/Runtime-Verifikation ausstehend / Datenintegrität, Quote-to-Order
- **Betroffene Dateien:** `src/Service/Quote/QuoteOrderConverter.php`, `src/Entity/Quote/Quote.php`, Tests unter `tests/Quote/`
- **Behobenes Verhalten:** Der Converter suchte Customer über `quote.customerEmail` und erzeugte bei Nichtfund einen Guest-Customer, obwohl `Quote.customer` die autoritative Beziehung ist.
- **Risiko:** Nach einer E-Mail-Änderung kann die Order einem anderen/neuen Customer gehören; Account-Ownership und Order-Historie divergieren.
- **Lösung:** Ausschließlich `Quote::getCustomer()` verwenden und bei fehlendem Customer die Konvertierung fachlich ablehnen. Keine Identity-Auflösung über Snapshot-Felder.
- **Acceptance Criteria:** Order- und Quote-Customer haben dieselbe Datenbank-ID; E-Mail-Änderung ändert die Zuordnung nicht; kein impliziter Guest wird erzeugt.
- **Umsetzung:** `QuoteOrderConverter` verwendet ausschließlich `Quote.customer`; die Snapshot-E-Mail wird nicht mehr zur Customer-Auflösung verwendet.
- **Tests:** Integrationstest mit persistiertem Customer, geänderter E-Mail, Konvertierung und Identity-Assertion; Missing-Customer-Negativtest.

### P1-002 — Expliziter QuoteItemType-Dispatch

- **ID/Priorität/Status/Bereich:** P1-002 / P1 / Behoben in PR #120 / Order-Modell, Money
- **Betroffene Dateien:** `src/Enum/Quote/QuoteItemType.php`, `src/Service/Quote/QuoteOrderConverter.php`, `src/Service/Quote/QuoteCalculator.php`
- **Ausgangsverhalten:** Nur `Custom` wurde separat behandelt; `Product`, `Service` und `Shipping` liefen gemeinsam in den Product-OrderItem-Pfad. Service/Shipping konnten dabei eine null Variant erhalten, während zusätzlich globale Service-/Shipping-Adjustments angelegt wurden.
- **Risiko:** Ungültige Sylius-OrderItems oder doppelte Beträge; neue Enum-Werte fallen unbemerkt durch.
- **Lösung:** Exhaustive `match`-Dispatches legen das zulässige Domain-Modell fest. Product benötigt eine Variant; Service/Shipping werden als QuoteItems explizit abgelehnt und ausschließlich über die Quote-Level-Totals in gesperrte Adjustments übernommen.
- **Acceptance Criteria:** Jeder Enum-Wert hat einen dokumentierten Pfad; unbekannte/unzulässige Typen brechen vor Persistierung ab; Total-Invariante bleibt hart.
- **Tests:** Datensätze für alle vier Enum-Werte, Null-Variant, doppelte Service-/Shipping-Erfassung und Totals.
- **Umsetzung:** Product und Custom besitzen explizite Konvertierungssemantiken. Service- und Shipping-QuoteItems werden ausdrücklich abgelehnt, weil Service und Versand durch Quote-Level-Totals und die zugehörigen Order-Adjustments abgebildet werden.
- **Repository-Audit:** `QuoteFactory::createFromRequest()` erzeugt Product-Items, freie Admin-Positionen sind Custom-Items, und die Admin-Kalkulation schreibt Service und Versand in `Quote.serviceTotal` bzw. `Quote.shippingTotal`. Es existiert aktuell kein produktiver Erzeugungspfad für Service-/Shipping-QuoteItems.

### P1-003 — Unvollständige CI Quality Gates

- **ID/Priorität/Status/Bereich:** P1-003 / P1 / teilweise behoben in PR #121 / CI
- **Betroffene Dateien:** `.github/workflows/ci_static-checks.yaml`, `.github/workflows/build.yml`, `composer.json`
- **Umsetzung PR #121:** Composer Validate, Composer Audit und Plattformanforderungen sind blockierend. Twig, YAML und Container werden auf den tatsächlichen Projektpfaden geprüft; PHPStan verwendet deterministisch `phpstan.dist.neon` mit 1 GiB Speicher. Die MySQL-Testinstallation validiert anschließend Schema und Migrationsstatus; die bewusst unmanaged `cardnext_quote_sequence` ist aus ORM-Schemavergleichen ausgeschlossen.
- **Noch offen:** Repository-weite ECS-Bereinigung und anschließend ECS als Required Gate (separater mechanischer Folge-PR; aktuell rund 48 Bestandsfunde).
- **Gate-Stand nach PHPStan-Audit:** Composer-, Plattform-, Symfony-Lint-, PHPStan- und Schema-Gates sind aktiv. ECS bleibt das letzte ausstehende Coding-Standard-Gate.
- **Risiko:** Defekte Templates/Config, Coding-Standard- und Dependency-Security-Regressionen können grün mergen. Markdown-only Audit-PRs triggern den Build wegen `*.md`-Ignore nicht.
- **Lösung:** Gates mit den im README genannten exakten Pfaden hinzufügen; `composer audit` blockierend; ECS als eigener Gate nach separatem Cleanup; Schema Validate mit MySQL-Service.
- **Acceptance Criteria:** Jeder Gate wird auf PRs ausgeführt und Fehler blockieren; keine `continue-on-error`-Ausnahme für Security.
- **Tests:** Absichtlich fehlerhafte Fixture/Branch pro Gate; erfolgreicher kompletter Workflow.

### P1-004 — Fehlende funktionale Account-/Concurrency-Tests

- **ID/Priorität/Status/Bereich:** P1-004 / P1 / bestätigt / Security Regression Risk
- **Betroffene Dateien:** `src/Controller/Shop/QuoteAccountController.php`, `tests/Quote/`
- **Aktuelles Verhalten:** Source- und Routing-Tests prüfen Teile der Architektur; es fehlt ein echter HTTP/DB-Sicherheitsmatrix-Test für Customer A/B, Channel, Status, PDF und Entscheidungen sowie ein paralleler Double-Submit/Convert-Test.
- **Risiko:** Kleine Repository-/Route-/Security-Änderungen können IDOR oder Doppelorders wieder einführen.
- **Lösung:** Symfony WebTestCase/Behat mit realer Firewall und MySQL; fremde Angebote immer 404; Header und CSRF explizit prüfen.
- **Acceptance Criteria:** Matrix für anonym/eigen/fremd/falscher Channel/falscher Status und GET/POST/PDF; parallele Konvertierung erzeugt genau eine Order.
- **Tests:** PR A4 Security-Suite plus DB-Concurrency-Test.

## P2

### P2-001 — Sylius Order Workflow beim manuellen Import
- **ID/Priorität/Status/Bereich:** P2-001 / P2 / bestätigt / Sylius-Konformität
- **Betroffene Dateien:** `src/Service/Quote/QuoteOrderConverter.php`
- **Aktuelles Verhalten:** Checkout- und Order-State werden direkt gesetzt/completed; Payment und Shipment werden bewusst nicht erzeugt.
- **Risiko:** Sylius-State-Machine-Callbacks/Prozessoren können umgangen werden; Semantik „Checkout completed“ ohne Versand/Zahlung ist erklärungsbedürftig.
- **Lösung:** ADR und Test gegen Sylius 2.2; wenn möglich offizielle State-Machine-Transitions bzw. dedizierten, dokumentierten Import-Workflow nutzen. Keine Katalog-Neuberechnung der Snapshots.
- **Acceptance Criteria:** Unterstützte Zustände, ausgelöste Events und bewusst unterdrückte Mail/Payment/Shipment sind dokumentiert und getestet.
- **Tests:** Event-/State-Integrationstest und Assertion, dass keine Confirmation-Mail, Payment oder Shipment entsteht.

### P2-002 — Mailrollen gekoppelt
- **ID/Priorität/Status/Bereich:** P2-002 / P2 / bestätigt / Mail-Konfiguration
- **Betroffene Dateien:** `config/services.yaml`, `.env`, `src/Service/Quote/QuoteRequestMailer.php`, `QuoteOfferMailer.php`
- **Aktuelles Verhalten:** `CARDNEXT_QUOTE_RECIPIENT` dient gleichzeitig als From und interner To-Empfänger.
- **Risiko:** Deployment-/DMARC-Anforderungen und interne Routingänderungen sind unnötig gekoppelt.
- **Lösung:** `CARDNEXT_QUOTE_FROM` und `CARDNEXT_QUOTE_INTERNAL_RECIPIENT`, optional zentrale Sylius-Mail-Senderkonfiguration; Reply-To fachlich festlegen.
- **Acceptance Criteria:** getrennte validierte Adressen, channel-/locale-bewusste Templates, Mailer-Tests.
- **Tests:** Mailer-Collector assertions für From/To/Reply-To/Locale und Fehlerpfad.

### P2-003 — Canonical Account Hosts im Service-Container hart codiert
- **ID/Priorität/Status/Bereich:** P2-003 / P2 / bestätigt / Portabilität
- **Betroffene Dateien:** `config/services.yaml`, `src/Service/Quote/QuoteAccountUrlGenerator.php`
- **Aktuelles Verhalten:** DE/AT-Hosts sind feste Parameter; die bewusste Unabhängigkeit vom Admin-Request-Host ist korrekt.
- **Risiko:** Staging, Preview, Domainwechsel und weitere Channels benötigen Codeänderungen.
- **Lösung:** explizite, env-injizierte Canonical-URL-Map pro Channel (robuster als Request RouterContext); alternativ gepflegte Channel-Hostname-Metadaten, falls Sylius-Version/Domainmodell dies eindeutig anbietet.
- **Acceptance Criteria:** pro aktivem Channel validierte absolute HTTPS-Base-URL, keine Ableitung aus Admin-Host.
- **Tests:** DE/AT/Staging/fehlende Konfiguration.

### P2-004 — Unvollständige PDF-Ausstellerprofile
- **ID/Priorität/Status/Bereich:** P2-004 / P2 / bestätigt / Compliance/PDF
- **Betroffene Dateien:** `config/services.yaml`, `src/Service/Quote/QuoteIssuerProfileRegistry.php`, `templates/pdf/quote.html.twig`
- **Aktuelles Verhalten:** Mehrere rechtliche, Adress- und Bankfelder sind absichtlich null; Registry prüft nur Struktur/Channel.
- **Risiko:** Ein Produktionsangebot kann ohne benötigte Ausstellerangaben erzeugt werden.
- **Lösung:** Pflichtfelder je Geschäftsfall beim Deployment/„ready“-Übergang validieren. **Production issuer data requires business approval**; keine Daten erfinden.
- **Acceptance Criteria:** fachlich freigegebene Pflichtfeldliste, fail-fast vor Versand, channel-spezifische Tests.
- **Tests:** vollständiges/unvollständiges Profil und PDF-Snapshot.

### P2-005 — Quote-Zeit ist nicht deterministisch injiziert
- **ID/Priorität/Status/Bereich:** P2-005 / P2 / bestätigt / Testbarkeit
- **Betroffene Dateien:** `src/Service/Quote/QuoteNumberGenerator.php`, Quote-Entities/Controller/Services
- **Aktuelles Verhalten:** Generator nutzt `date('Y')`, Domaincode mehrfach `new DateTimeImmutable()`.
- **Risiko:** Jahreswechsel-/Expiry-Tests sind fragil; Nummernjahr und Domainzeit können auseinanderlaufen.
- **Lösung:** Symfony `ClockInterface` dort injizieren, wo Zeit fachliche Entscheidungen/Nummern bestimmt; rein technische Timestamps nur bei erkennbarem Nutzen migrieren.
- **Acceptance Criteria:** deterministischer Jahreswechsel- und Expiry-Test.
- **Tests:** 31.12./01.01, Zeitzone, Wiederholung und Sequence.

### P2-006 — Migration-Recovery-Policy fehlt
- **ID/Priorität/Status/Bereich:** P2-006 / P2 / bestätigt / Doctrine
- **Betroffene Dateien:** `migrations/`, `config/packages/doctrine_migrations.yaml`
- **Aktuelles Verhalten:** Viele app-spezifische MySQL-DDLs, inklusive historischer Sequence-Erstellung/Down-Drop; kein versioniertes Runbook für partiell ausgeführte DDL.
- **Risiko:** MySQL-DDL kann implizit committen; erneutes Ausführen nach Teilfehlern kann scheitern. Alte Migrationen nachträglich zu ändern zerstört Nachvollziehbarkeit.
- **Lösung:** ausgeführte Migrationen immutable; nur neue Forward-Fix-Migrationen; vorab `information_schema`-Guards nur wo Recovery nötig; Staging-Kopie, Backup und geprüftes Runbook. `cardnext_quote_sequence` niemals aus Schema-Diffs droppen.
- **Acceptance Criteria:** Policy im Deployment-Runbook; vollständiger Fresh-Install und Upgrade von letztem Produktionsstand.
- **Tests:** MySQL 8.4 Up, Down soweit sicher, simulierte Teilmigration und Recovery.

### P2-007 — PHPStan-/Composer-Metadaten deterministisch schließen
- **ID/Priorität/Status/Bereich:** P2-007 / P2 / behoben mit PR #124 / Tooling
- **Betroffene Dateien:** `phpstan.dist.neon`, `composer.json`, CI
- **Aktuelles Verhalten:** Eine versionierte PHPStan-Konfiguration existiert (`phpstan.dist.neon`, nicht die vermuteten Namen). Composer strict scheitert wegen fehlendem `name`/`description` für das proprietäre Root-Package.
- **Risiko:** Lokale/CI-Kommandos divergieren; Strict Gate kann nicht aktiviert werden.
- **Umsetzung PR #121:** Der kanonische PHPStan-Befehl lädt `phpstan.dist.neon` und damit alle versionierten Pfade mit einem expliziten 1-GiB-Limit. Das Root-Package besitzt valide proprietäre Metadaten; CI installiert exakt den committed Lockfile-Stand.
- **Noch offen:** Keine PHPStan-Findings. Der reproduzierbare Level-9-Voll-Lauf ist grün; es wurde weder eine Baseline noch eine breite Ignore-Liste erzeugt.
- **Audit PR #122:** Der unveränderte Level-9-Voll-Lauf bestätigte 441 Findings (299 in `src/`, 142 in `tests/`). Nach explizitem Laden der bereits installierten Doctrine- und Webmozart-Assert-Extensions sowie der Korrektur einer veralteten Sylius-Calculator-Schnittstelle verbleiben 414 Findings (272 in `src/`, 142 in `tests/`). Da die Bereinigung mehrere hundert fachlich zu prüfende Änderungen erfordert, wird sie gemäß Review-Sicherheitsvorgabe auf Folge-PRs für Domain/Doctrine, Import/Search, Symfony/Application-Services und Tests aufgeteilt. Level, Analysepfade und Gates bleiben unverändert; es gibt weder Baseline noch Ignore-Regeln.
- **Umsetzung PR #123:** Die 272 Findings unter `src/` sind auf 0 reduziert; der separat reproduzierte Lauf über `tests/`, `config/`, `bin/` und `public/` weist weiterhin 142 Findings aus. P2-007 bleibt deshalb teilweise behoben und der repositoryweite CI-Befehl unverändert. Es wurde keine Baseline, keine Migration und keine pfadweite Ignore-Regel ergänzt.
- **Umsetzung PR #124:** Die 142 Findings unter `tests/` sind auf 0 reduziert. Der abschließende repositoryweite Level-9-Lauf meldet ebenfalls 0 Findings; damit verbleiben unter `bin/`, `config/` und `public/` zusammen 0 Findings. Behoben wurden insbesondere unsichere Datei-/YAML-/CSV-Zugriffe, unpräzise Data-Provider- und Array-Typen, nullable Collection-Ergebnisse, nicht genarrowte Container-/Doctrine-Dienste sowie Reflection- und Routing-Typen. Assertions und fachliche Testpfade einschließlich der Quote-Invarianten blieben erhalten; keine Migration erforderlich.
- **Acceptance Criteria:** beide Befehle reproduzierbar und grün.
- **Tests:** Composer validate strict und PHPStan aus sauberem Checkout.

## P3

### P3-001 — Architekturentscheidungen dokumentieren
- **ID/Priorität/Status/Bereich:** P3-001 / P3 / offen / ADR
- **Betroffene Dateien:** neue ADRs unter `docs/adr/`
- **Aktuelles Verhalten:** Snapshot-Money, unmanaged Sequence, Canonical URLs und manueller Order-Import sind nur verteilt kommentiert.
- **Risiko/Lösung:** Entscheidungen können versehentlich zurückgebaut werden; ADRs mit Alternativen und Sylius-2.2-Bezug erstellen.
- **Acceptance Criteria/Tests:** vier akzeptierte ADRs; Review-Checklist verweist darauf (Dokumentationsprüfung).

### P3-002 — Observability für Quote-to-Order und Mail
- **ID/Priorität/Status/Bereich:** P3-002 / P3 / offen / Betrieb
- **Betroffene Dateien:** Quote-Services, Logging/Monitoring-Konfiguration
- **Aktuelles Verhalten:** Teilweise Logs/History, aber keine konsistenten Correlation IDs/Metriken.
- **Risiko/Lösung:** stille Mail- oder Conversion-Probleme; strukturierte quoteId/orderId/channel Events, Counter und Alerts ohne PII einführen.
- **Acceptance Criteria/Tests:** Dashboard/Alert und redaction tests.

### P3-003 — Deployment- und Smoke-Test-Automation
- **ID/Priorität/Status/Bereich:** P3-003 / P3 / offen / Delivery
- **Betroffene Dateien:** CI/CD und Betriebsdokumentation
- **Aktuelles Verhalten:** README beschreibt manuelle Schritte; kein nachweisbarer automatisierter Quote-Security-Smoke-Test nach Deployment.
- **Risiko/Lösung:** Umgebungsabweichungen werden spät erkannt; migrations dry-run/rehearsal und synthetischen Account-Test automatisieren.
- **Acceptance Criteria/Tests:** staging promotion gate und protokollierter Post-Deploy-Smoke-Test.

## Verified Good Practices

- Account-Quote-Zugriff verlangt den Sylius ShopUser/Customer und filtert Customer, Channel und erlaubte Status in derselben Query; Fremdzugriff liefert 404.
- PDF verwendet dieselbe Autorisierung; Responses sind private/no-store/noindex; Accept/Reject sind POST + CSRF.
- Keine öffentliche Quote-Token-Route, kein Public Quote Controller und kein alter Token-Link in Quote-Mailtemplates gefunden.
- Quote-to-Order nutzt Transaktion, pessimistische Sperre, Idempotenzprüfung, Sylius-Factories, Quantity Modifier, Number Assigner und eine harte Total-Invariante.
- OrderItemFactory-DI ist explizit verdrahtet; die Runtime-Containerprüfung blieb ausschließlich am nicht verfügbaren Argon2i-Algorithmus hängen und muss in der Zielruntime wiederholt werden.
- Quote `(number, version)` und Order-Beziehung sind eindeutig indiziert; Customer-/Request-Beziehungen und relevante Query-Spalten sind gemappt.
- Der Sequence-Generator nutzt MySQL `LAST_INSERT_ID` mit atomarem Upsert; die Tabelle ist bewusst unmanaged.
- Unbekannte PDF-Ausstellerdaten wurden nicht erfunden.
- Security-Firewalls/Hasher entsprechen der tatsächlich installierten Sylius-2.2-App-Basis; keine Schwächung wegen lokaler Extensions empfohlen.

## Recommended Implementation Order

1. **PR A1 – Quote Customer Identity:** P1-001 plus fokussierte Tests.
2. **PR A1b – Quote Item Type/Order invariants:** P1-002 und P2-001, getrennt falls fachliche Entscheidung nötig.
3. **PR A2 – CI + PHPStan Quality Gates:** P1-003/P2-007; zunächst korrekte Lint-/Composer-/PHPStan-Gates.
4. **PR A3 – ECS Cleanup:** rein mechanisch, danach ECS blockierend schalten.
5. **PR A4 – Account Quote Functional Security Tests:** P1-004 einschließlich Concurrency.
6. **PR A5 – Issuer/Mail/Canonical Configuration:** P2-002 bis P2-004.
7. Migration Policy/Clock, anschließend P3-Reife.

## Definition of Done

Ein Finding ist erledigt, wenn Acceptance Criteria und positive/negative Tests erfüllt sind, Sylius-2.2-APIs gegen `vendor/` geprüft wurden, Money/Customer/Channel-Invarianten dokumentiert sind, Migrationen auf MySQL-Fresh/Upgrade getestet wurden und alle Quality Gates ohne breite Ignores bestehen. Security-Änderungen benötigen 404-/CSRF-/Header-Regressionstests; Order-Änderungen Identity-, Total-, Idempotenz- und Concurrency-Tests.

## Working Roadmap

- **Jetzt:** Audit-Dokumente reviewen; Product Owner entscheidet das zulässige Service-/Shipping-Modell und Pflicht-Ausstellerdaten.
- **Vor Feature-Entwicklung:** P1-001 bis P1-004.
- **Danach:** P2 in der empfohlenen Reihenfolge, jeweils fokussiert.
- **Laufend:** P3 in Betriebsplanung aufnehmen; Status/Owner/PR-Link je Finding ergänzen.

## Change Policy

- Produktionsmigrationen sind immutable; Korrekturen ausschließlich als neue Migration.
- `cardnext_quote_sequence` ist unmanaged und darf nicht durch Doctrine-Diffs gelöscht werden.
- Keine fachlichen Refactorings zusammen mit mechanischen ECS-Diffs.
- Keine P0-Hochstufung ohne reproduzierbaren Exploit/Datenverlust; bereits behobene Hypothesen bleiben als solche markiert.
- Keine Secrets oder ungeprüften Rechts-/Firmendaten committen.
- Vor einer Cardnext-Eigenlösung stets Sylius Factory/Repository/Modifier/State Machine/Mailer/Twig Hook der installierten 2.2-Version prüfen; Hook-Namen nie raten.

## Ausgeführte Checks

### Erfolgreich

- `composer audit` — vor Installation: keine installierten Pakete, daher inhaltlich nicht aussagekräftig; nach Installation erneut erforderlich.

### Fehlgeschlagen

- `composer validate --strict --no-check-version`: `name` und `description` fehlen.
- `composer check-platform-reqs`: `ext-sodium` fehlt.
- Der erste Lauf aller Console-/Vendor-Befehle scheiterte erwartbar am fehlenden `vendor/`. Nach lokaler Installation scheitert der Kernel-Boot weiterhin transparent, weil die bereitgestellte PHP-Runtime weder Sodium noch den konfigurierten `argon2i`-Hasher anbietet; Security wird dafür nicht abgeschwächt.
- `vendor/bin/ecs check`: 48 rein mechanisch fixbare Coding-Standard-Verstöße; bewusst kein Formatierungsdiff in diesem Dokumentations-PR.
- `vendor/bin/phpunit tests`: begann mit 310 Tests, traf nach 79 Tests auf einen Fehler und hing anschließend an DB-abhängiger Ausführung; nach transparenter Erfassung abgebrochen.
- Beide PHPStan-Aufrufe: unvollständig, weil das konfigurierte Runtime-Limit von 128 MB erreicht wurde; Wiederholung mit explizitem CI-Memory-Limit erforderlich.

### Runtime verification required

- `about`, Container-/YAML-/Twig-Lint und `debug:router`: Kernel-Boot durch fehlende Argon2i/Sodium-Unterstützung der Audit-Runtime blockiert. Runtime/Container korrigieren, nicht `security.yaml` schwächen.
- `doctrine:schema:validate` und `doctrine:migrations:status`: zusätzlich keine erreichbare MySQL-Datenbank in der Audit-Umgebung; auf Staging/Produktionskopie wiederholen.
- Composer Security Audit nach normaler, plattformkonformer Installation mit Sodium wiederholen.
- Echte HTTP-Firewall-, Mailzustellungs-, PDF- und Double-Click/Concurrency-Szenarien benötigen MySQL und eine laufende Testanwendung.
