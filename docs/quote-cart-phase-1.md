# Angebotskorb – Phase 1

Der Angebotskorb ist vollständig vom Sylius-Warenkorb getrennt. Vor dem Absenden liegen keine Datenbankdaten vor: Die Session `cardnext.quote_cart` enthält ausschließlich den gebundenen Channel-Code und `items` als Map aus Variant-Code und Menge. Ein Channelwechsel initialisiert den Korb neu und zeigt einen Hinweis.

`QuoteCartService` lädt Varianten immer erneut und akzeptiert nur aktivierte Varianten aktivierter, dem aktuellen Channel zugeordneter Produkte mit `ChannelPricing`. Preise stammen in Minor Units aus diesem Pricing; Browserwerte werden nicht akzeptiert. Der technische Mengenbereich ist 1–100.000.

Beim Absenden persistiert `QuoteRequestSubmitter` Request, Item-Snapshots und erstes History-Ereignis in einer Transaktion. Snapshots enthalten Codes, lokalisierte Namen, Hersteller, Menge, Währung und den zu diesem Zeitpunkt im Shop dargestellten Channelpreis. Die unverbindliche Summe wird danach nicht neu berechnet. `cardnext_quote_sequence` und ein atomisches MySQL-Upsert mit `LAST_INSERT_ID` erzeugen pro Jahr konkurrenzsicher `AN-YYYY-NNNNN`; zusätzlich schützt ein Unique Index die Nummer.

Öffentliche Mutationen sind POST-only, CSRF-geschützt und validieren den Channel erneut. Symfony Validator begrenzt alle Eingaben, Datenschutz ist erforderlich, ein Honeypot bietet leichten Spamschutz, und ein einmaliges Session-Submission-Token verhindert Doppelsendungen. Die öffentliche Bestätigung nennt nur die Referenz. Twig escaped Kundendaten standardmäßig.

Admin-Routen sind `/admin/cardnext/quotes`, `/admin/cardnext/quotes/{id}` und `/admin/cardnext/quotes/{id}/status`, verlangen `ROLE_ADMINISTRATION_ACCESS`, bieten Status-/Channelfilter und protokollieren zentral validierte Statuswechsel (`new`, `in_progress`, `question`, `closed`).

Nach erfolgreichem Commit werden Kunden- und interne HTML-Mail über Symfony Mailer/Twig gesendet. Fehler werden nur mit Referenz protokolliert und machen die gespeicherte Anfrage nicht rückgängig. Der interne Empfänger kommt aus `CARDNEXT_QUOTE_RECIPIENT`. Erst nach dem Commit wird der Session-Korb geleert.

Die Migration `Version20260829120000` erstellt Request-, Snapshot-, History- und Sequenz-Tabelle mit Foreign Keys, Unique Constraint und Listenindizes.

## Nicht enthalten

Phase 2 intentionally not included:
- offer price editing
- discounts
- shipping/project fees
- PDF generation
- sending final offers from admin
