# Translation audit

The German Cardnext catalogue is the canonical source for application-owned translation keys. The
`de_AT`, `da_DK`, `es_ES`, `it_IT`, `nl_NL`, and `sv_SE` catalogues contain the same 268 leaf keys.
The automated completeness test compares recursive leaf paths so that a missing locale entry fails CI.

Customer quote-request and quote-offer email subjects and bodies now use the quote/customer locale.
Internal operational emails, admin-only labels, logs, domain exceptions, ERP history entries, brand
names, technical standards, and company/legal sender details intentionally remain unchanged.

## MANUAL TRANSLATION REQUIRED

The following content is database-managed and cannot be derived safely from this repository. It must
be reviewed in each active channel for `de_AT`, `da_DK`, `es_ES`, `it_IT`, `nl_NL`, and `sv_SE` without
overwriting production data:

| Entity / table | Translated field(s) |
| --- | --- |
| Shipping method translation (`sylius_shipping_method_translation`) | `name`, `description` |
| Payment method translation (`sylius_payment_method_translation`) | `name`, `description`, `instructions` |
| Product option translation (`sylius_product_option_translation`) | `name` |
| Product option value translation (`sylius_product_option_value_translation`) | `value` |
| Product association type translation (`sylius_product_association_type_translation`) | `name` |
| Channel-specific legal pages | title, navigation label, and legally binding body |
| Configurator translations | public name, descriptions, SEO metadata, and path |
| Configurator field/value translations | labels, help text, and public option values |

Product, taxon, attribute, inventory, price, and channel-assignment data are deliberately outside this
audit because those records were internationalized separately. No production-data migration or fixture
has been added.
