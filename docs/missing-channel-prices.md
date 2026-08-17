# Diagnose fehlender Channel-Preise

Die Produktkarten zeigen Produkte ohne Preis weiterhin an. Die folgende
read-only Query zählt die betroffenen **aktivierten** Varianten aktivierter
Produkte im gewünschten Channel, getrennt nach fehlendem `ChannelPricing` und
vorhandenem Datensatz mit `price IS NULL`. Sie verändert keine Daten.

```sql
SELECT
    SUM(CASE WHEN cp.id IS NULL THEN 1 ELSE 0 END) AS missing_channel_pricing,
    SUM(CASE WHEN cp.id IS NOT NULL AND cp.price IS NULL THEN 1 ELSE 0 END) AS null_price,
    COUNT(DISTINCT p.id) AS affected_products
FROM sylius_product p
INNER JOIN sylius_product_channels pc ON pc.product_id = p.id
INNER JOIN sylius_channel c ON c.id = pc.channel_id AND c.code = :channel_code
INNER JOIN sylius_product_variant v ON v.product_id = p.id AND v.enabled = 1
LEFT JOIN sylius_channel_pricing cp
    ON cp.product_variant_id = v.id
   AND cp.channel_code = c.code
WHERE p.enabled = 1
  AND (cp.id IS NULL OR cp.price IS NULL);
```

Für die sichere Detailprüfung kann dieselbe Query mit `SELECT p.code,
v.code, cp.id, cp.price` statt der Aggregation ausgeführt werden.

Die Sylius-Preissortierung arbeitet auf einem SQL-Join zum ChannelPricing. Sie
ruft keinen PHP-Preisresolver auf und kann daher keine
`MissingChannelConfigurationException` auslösen. Varianten ohne
ChannelPricing nehmen an einer expliziten Preissortierung nicht teil;
ChannelPricing-Datensätze mit `NULL` werden entsprechend der NULL-Sortierung
der Datenbank eingeordnet. Die normale Kategorieansicht und Facetten werden
davon nicht beeinflusst.
