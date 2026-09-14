# Doctrine schema validation after the Sylius 2.2 upgrade

## Shipment units

Sylius Core models a shipment unit as its configured `order_item_unit` resource.
The CoreBundle's standard shipping configuration therefore points
`sylius_shipping.resources.shipment_unit` at
`%sylius.model.order_item_unit.class%`. Cardnext previously overrode the
shipment and order-item-unit resources but omitted that bridge. Consequently,
the Shipping component's standalone `ShipmentUnit` metadata and Cardnext's
`OrderItemUnit` metadata were both loaded. Doctrine ORM 3.7 validates both ends
of that inherited bidirectional association and reports that `Shipment#units`
targets `App\Entity\Order\OrderItemUnit`, while `ShipmentUnit#shipment` belongs
to the standalone class.

The resource bridge has been restored. A metadata listener removes `inversedBy`
only from the unused Shipping component mapped superclass; the real Core
`OrderItemUnit#shipment` / `Shipment#units` association remains bidirectional.
No association is duplicated in an App entity and the existing
`sylius_order_item_unit.shipment_id` schema is unchanged. Doctrine ORM 3.7 made
this inconsistent metadata visible through a stricter validation added in the
3.7 line; it did not introduce a new Sylius relation.

## Deliberately retained database objects

The following objects are intentionally not removed by a migration:

* `cardnext_backup_print_resolution_20260814` and
  `cardnext_backup_single_select_20260814_1651` are historical, unmanaged
  backup tables. The DBAL schema filter excludes them from comparisons, without
  modifying or deleting them.
* Cardnext's named performance and uniqueness indexes are part of the mapping,
  including variant tier lookup, order-item bundle lookup, CMS rendering,
  slug/locale, publication and locale/scope uniqueness indexes. Their names
  come from the migrations that introduced the corresponding features.
* Symfony Messenger 7.4 defines the composite
  `(queue_name, available_at, delivered_at, id)` index. Replacing the three
  existing production indexes is a separate operational decision and is not
  included in this change.

No database migration is required by these metadata-only corrections. In
particular, this change contains no table, column, data or index removal.
