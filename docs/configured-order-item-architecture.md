# Standalone configured order items

A configurator is deliberately **not** a Sylius Product or ProductVariant. A `ConfiguredOrderItem` is an independent, order-owned snapshot and has neither a product nor a variant relation.

```
Configurator
    ↓ server-side calculate
ConfiguratorConfiguration
    ↓ immutable, readable snapshot
ConfiguredOrderItem
    ↓ belongs to
Sylius Order

ConfiguredOrderItem ✕ Product
ConfiguredOrderItem ✕ ProductVariant
```

The snapshot contains localized field/value labels, canonical selections for an explicit cart quantity recalculation, the existing `ConfigurationHashGenerator` result, all calculated minor-unit amounts, `taxCategoryCode`, and `shippingRequired`. The current tax category relation belongs only to the `Configurator`; the configured order item deliberately snapshots its code rather than retaining a Doctrine relation. Both checkout metadata values are copied server-side and refreshed with pricing while the line is still in the cart.

It has no configurator foreign key and no Product or ProductVariant dependency, so catalog renames, pricing or checkout metadata changes, and deletion cannot rewrite historical lines. Identical hashes remain separate lines in preparation for line-specific artwork.

## Sylius 2.2 integration findings

Sylius' regular cart pipeline models lines as `OrderItem -> ProductVariant -> Product`. Item pricing, promotion eligibility, inventory, shipment creation, taxation, checkout rendering, and payment integrations routinely consume the variant/product. Making the variant nullable would therefore move failures downstream rather than create a standalone line type. `Order::isEmpty()` also only considers regular items.

Configured lines consequently belong directly to the application `Order`. A dedicated idempotent order processor replaces only the `cardnext_configured_items` order adjustment and leaves item, promotion, shipping, and tax adjustments intact. The custom cart fragment renders snapshots even in a configured-only cart. Cart mutations resolve the current CartContext and verify line ownership. Checkout routes are deliberately guarded whenever configured lines exist; this ensures shipment, tax, and Mollie code never receives a partially integrated order. Ordinary product carts retain the unmodified Sylius path.

## Follow-up (PR #56)

PR #56 must integrate configured lines with checkout, shipping, taxes, payment/Mollie, order confirmation, and the admin order view before removing the guard. Secure reconfiguration preloading is also deferred; the current edit link returns to the configurator without putting canonical JSON in the URL.
