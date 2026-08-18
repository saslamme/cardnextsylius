# Audit: Configured Items in Post-Order Views

Stand: Sylius 2.2.8, Repository nach PR #68. Geprüft wurden die lokalen Verzeichnisse
`templates/`, `src/`, `config/` und `tests/` sowie die relevanten Sylius-2.2.8-Templates
und die installierten Refund-/PDF-Konfigurationen. Die Klassifikation lautet:

- **A** – unterstützt ConfiguredOrderItems bereits korrekt
- **B** – reine OrderItem-Darstellung, in diesem Audit korrigiert
- **C** – reine OrderItem-Verarbeitung ist fachlich beabsichtigt
- **D** – potenzielles Folgeproblem außerhalb eines gezielten Presentation-Fixes

## Fundstellen

| Datei / Komponente | Bereich und Zweck | Felder | Sichtbarkeit | Configured Items | Änderung | Klasse / Begründung |
|---|---|---|---|---|---|---|
| `templates/bundles/SyliusShopBundle/email/order_confirmation.html.twig` | Kunden-Bestellbestätigung | `order.items`, `order.configuredItems`, `itemsTotal`, gespeicherte Item-Beträge, `order.total` | Kunde | Ja | Nein | **A** – getrennte Positions- und Zwischensummendarstellung, finaler Betrag aus `order.total`, gemeinsames Snapshot-Macro. |
| `templates/email/internal_order_notification.html.twig` | interne Bestellbenachrichtigung | beide Collections, Positionsanzahl, gespeicherte Beträge, `order.total` | Intern | Ja | Nein | **A** – durch PR #68 abgedeckt; Menge wird nicht als Positionsanzahl verwendet. |
| `templates/shop/checkout/complete/configured_items.html.twig` und Shared-Order-Summary-Hook | Bestelldetail, Checkout-Abschluss und Kundenkonto-Zusammenfassung | `order.configuredItems`, Snapshot, `unitAmount`, `quantity`, `total` | Kunde | Ja | Nein | **A** – der gemeinsame Sylius-Order-Summary-Hook wird auch im Kunden-Bestelldetail verwendet. |
| `templates/admin/order/show/configured_items.html.twig` und Hooks | Admin-Bestelldetail | `order.configuredItems`, Snapshot, gespeicherte Beträge | Admin | Ja | Nein | **A** – durch PR #67 abgedeckt. |
| `templates/admin/order/show/items_total.html.twig` / `configured_items_total.html.twig` | Admin Positions- und Summenfuß | `order.itemsTotal`, Summe `item.total` | Admin | Ja | Nein | **A** – getrennte gespeicherte Zwischensummen; keine Rekonstruktion von `order.total`. |
| `templates/bundles/SyliusShopBundle/order/show/content/header.html.twig` | öffentliche Bestell-/Zahlungsansicht, Positionsanzahl | beide Collections, `order.total` | Kunde | Ja | Ja | **B** – Sylius nutzte `totalQuantity` und bezeichnete die Stückzahl mit `sylius.ui.items`; Cardnext zählt stattdessen bewusst normale plus konfigurierte Bestellpositionen und benennt sie mit lokalen DE-/EN-Keys eindeutig als „Position(en)“ bzw. „line(s)“. Eine native Zeile mit Menge 5 bleibt damit eine Position, ebenso eine konfigurierte Zeile mit Menge 250. |
| `templates/bundles/SyliusAdminBundle/dashboard/index/component/new_orders.html.twig` | Admin-Dashboard „Neue Bestellungen“, Positionsanzahl | beide Collections, `order.total` | Admin | Ja | Ja | **B** – Sylius zählte nur `order.items`; jetzt werden beide Positions-Collections gezählt. |
| Sylius Account-Order-Grid (`account/order/index`) | Kunden-Bestellhistorie | Nummer, Datum/Status, `order.total`, Adresse | Kunde | Indirekt | Nein | **A** – zeigt keine Artikelzeilen oder Artikelanzahl; `order.total` enthält Configured Items bereits. |
| Sylius Shipment-Confirmation-E-Mail | Versandbestätigung | Shipment/Tracking, keine Artikel-Iteration | Kunde | Nicht erforderlich | Nein | **C** – keine auszulassende Artikeldarstellung vorhanden. |
| Sylius Payment-Status/-Methodenansichten | Zahlung und erneute Zahlung | Payments, Status, `order.total` | Kunde/Admin | Indirekt | Nein | **A** – Betrag kommt aus dem autoritativen Order-/Payment-Betrag; keine Artikelliste. |
| `src/OrderProcessing/B2BPriceOrderProcessor.php` | B2B-Preisberechnung vor Abschluss | `order.getItems()` | Technisch | Nein | Nein | **C** – ProductVariant-spezifische Preisregel für native OrderItems, keine Post-Order-Darstellung. |
| `src/Controller/Shop/ConfiguredCartController.php` | Konfigurator-Warenkorb | Configured Items und Order Processor | Kunde, vor Bestellung | Ja | Nein | **C** – Checkout-/Cart-Verarbeitung, nicht Post-Order-Presentation. |
| Sylius Inventory-, Availability-, Tax- und Promotion-Verarbeitung | native Sylius-Fachlogik | `getItems()`, OrderItem/Units | Technisch | Nein | Nein | **C** – absichtlich an echte ProductVariants/Units gebunden; Configured Items besitzen eigene gespeicherte Metadaten und Adjustment-Verarbeitung. |
| Sylius Refund Plugin / Credit Memo PDF | Refund-/Storno-Dokumente | native OrderItemUnits und Refund Units | Kunde/Admin | Nein | Nein | **D** – Configured Items sind keine Units und daher aktuell nicht einzeln refundierbar; eine Korrektur wäre Refund-Architektur, nicht Template-Audit. |
| Sylius PDF Generation Bundle | technische PDF-Erzeugung für Refund-Dokumente | vom Refund-Dokument gelieferte Daten | Kunde/Admin | Nein | Nein | **D** – kein eigenständiges Rechnungs- oder Lieferscheinmodell; folgt der vorgenannten Refund-Lücke. |
| APIs / Serializer / Exporte | Suche in App-Konfiguration und Quellcode | keine Order-Item-Ausgabe gefunden | Extern | Nicht vorhanden | Nein | **C** – keine anwendungseigene Order-API, Serialisierung oder Order-Exportfunktion vorhanden. |
| Eigene Rechnungen, Lieferscheine, Fulfillment- und Druckansichten | Suche in Templates, Services und Konfiguration | nicht vorhanden | – | Nicht vorhanden | Nein | **C** – in diesem Projekt existiert kein solches System; gemäß Scope wurde keines neu angelegt. |

## Ergebnis und Grenzen

Die zwei klaren verbleibenden Presentation-Lücken waren Positionszähler. Beide zählen nun
Collections (`items|length + configuredItems|length`), nicht verkaufte Stückmengen. Im
Shop-Header macht eine Cardnext-spezifische, singular-/pluralabhängige Beschriftung diese
bewusste Abweichung von der nativen Sylius-Stückzahl sichtbar; `sylius.ui.items` bleibt
global unverändert. Das Admin-Dashboard zählte bereits upstream mit `items|length`
Bestellpositionen, deshalb wird dort lediglich die konfigurierte Collection ergänzt. Alle
Positionsdetails bleiben auf historischen Snapshot-Feldern; es werden weder Configuratoren
noch aktuelle Optionen, Preise oder Lead Times geladen. Der finale Betrag bleibt überall
`order.total`.

Eine spätere fachliche Entscheidung ist für Refunds/Credit Memos sinnvoll: Soll ein
ConfiguredOrderItem teilweise oder vollständig erstattbar sein, braucht es ein eigenes
Refund-Konzept. Es darf nicht durch Fake-OrderItemUnits im Presentation-Layer simuliert
werden. Separate Rechnungs-, Lieferschein-, ERP- oder Exportfunktionen sind derzeit nicht
vorhanden.

Für diese Änderungen sind **keine Migration**, **kein Composer-Install** und **kein
Asset-Build** erforderlich.
