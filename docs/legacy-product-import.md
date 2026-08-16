# Cardnext legacy product migration

`products.zip` is a Windows-1252 encoded, pipe-delimited export. Its 126 `*.dat`
files contain exactly 62 fields per physical record. Field 0 is the legacy row
ID, 2/17 identify the vendor, 3 is the MPN, 4 the title, 5 the EUR price, 6 the
HTML description, 9 related article/device references, 25 the `:#` separated
attribute block and 35 the GTIN. `shop.artindex` maps legacy IDs to MPNs,
`shop.vendor` is the vendor vocabulary and `shop.attrib` is the attribute/value
vocabulary. Finder metadata (`__MACOSX`, `._*`, `.DS_Store`) is ignored.

The parser keeps the source archive outside the application, converts text to
UTF-8, removes active HTML, maps category filenames explicitly, parses money
without floats and deduplicates first by normalized MPN, then GTIN, then legacy
ID. Duplicate category and attribute sets are united. Manufacturer/price
conflicts are reported and conservative one-product-per-SKU planning avoids
inventing product families. Stable `LEGACY_*` codes make the existing CSV
upsert service idempotent; MPN and GTIN remain variant-only. Archived records
are disabled. No schema migration or parallel persistence model is introduced.

Dry run (no database write):

```bash
php bin/console app:cardnext:import-legacy-products /path/products.zip --dry-run
```

Production import after reviewing the JSON report:

```bash
php bin/console app:cardnext:import-legacy-products /path/products.zip --report=var/import/cardnext-legacy-import-report.json
```

Unknown categories are deliberately not persisted, and are counted in the
report. The source has no usable embedded image/PDF assets; absent references
are reported. Compatibility references are preserved during parsing but are not
materialized when the legacy value cannot unambiguously identify manufacturer,
model and device type.

## Baseline dry-run (private archive, 2026-08-16)

The reproducible parser run found 1,510 ZIP entries (755 Finder metadata), 126
product data files and 4,311 physical rows. MPN-first deduplication removed
3,672 redundant category/vendor rows and planned 639 products with 639 variants,
48 manufacturers and 663 taxon assignments. No unambiguous device model or
structured device compatibility could be derived. Of the products, 572 require
review (chiefly conflicting prices across redundant rows); 0 lack an MPN, 397
lack GTIN, 0 lack a price and 608 lack an image reference. Thirty products have
no safe final-category mapping and are intentionally withheld from persistence.
