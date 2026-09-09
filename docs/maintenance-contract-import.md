# idbMaster maintenance-contract import

## Production configuration

The full URL has priority over the legacy base URI and endpoint. Authentication is optional; a header is only sent when both values are non-empty.

```dotenv
CARDNEXT_ERP_MAINTENANCE_CONTRACTS_URL=https://idbmaster.de/sc_api/service_contract.json
CARDNEXT_ERP_AUTH_HEADER=
CARDNEXT_ERP_AUTH_VALUE=
```

`CARDNEXT_ERP_BASE_URI` and `CARDNEXT_ERP_MAINTENANCE_CONTRACTS_ENDPOINT` remain available as a fallback. `CARDNEXT_ERP_MAINTENANCE_FIELD_MAP` is optional and can override individual built-in aliases.

## Schema and mapping

The endpoint could not be inspected from the development environment on 2026-09-09 because outbound access returned HTTP 403. Therefore the fixture documents the supported idbMaster-shaped contract rather than claiming to be a captured production response. Before the first production sync, verify the top-level wrapper and field spellings against a current response. Supported wrappers are `service_contracts`, `service_contract`, `contracts`, and `data`; a direct list is also supported.

| Cardnext | Built-in source aliases (in priority order) |
|---|---|
| `externalId` | `contract_id`, `contractId`, `service_contract_id`, `id`; then contract number |
| `erpCustomerNumber` | `customer_number`, `customerNumber`, `erp_customer_number`, `customer_id`, `kundennummer`, `kdnr` |
| `serialNumbers` | `serial_number`, `serialNumbers`, `serial_numbers`, `serialnumber`, `seriennummer` |
| `startsAt` | `start_date`, `startsAt`, `contract_start`, `contractStart`, `startdatum` |
| `endsAt` | `end_date`, `endsAt`, `contract_end`, `contractEnd`, `enddatum` |
| `printerModel` | `printer_model`, `printerModel`, `model`, `druckermodell` |
| `contractReference` | `contract_number`, `contractNumber`, `contract_reference`, `referenceNumber`, `vertragsnummer` |
| `sourceUpdatedAt` | `updated_at`, `sourceUpdatedAt`, `modified_at`, `last_modified`, `aenderungsdatum` |

Dates accept `YYYY-MM-DD`, `DD.MM.YYYY`, ISO-8601 timestamps and `YYYY-MM-DD HH:MM:SS`. If neither an ID nor contract number exists, the fallback ID is `generated-` plus SHA-256 of customer number, normalized start/end dates, and normalized serial numbers. This is deterministic and uses no descriptive or random data.

After deployment, clear the production cache if configuration is cached and run the first sync:

```bash
APP_ENV=prod php bin/console cache:clear
APP_ENV=prod php bin/console cardnext:erp:sync-maintenance-contracts -vv
```

Review the `Fetched`, `Created`, `Updated`, `Unchanged`, `Skipped`, and `Errors` totals and warning logs. No idbMaster-side change or intermediate conversion is intended; only an unrecognized real wrapper or field spelling would require updating the alias configuration/code after verification.
