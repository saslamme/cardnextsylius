# Faceted product filter architecture

## Repository analysis (A–G)

**A/B — Listing query and extension point.** Taxon pages use Sylius'
`sylius.controller.product::indexAction` with the `sylius_shop_product` grid. Descendants
are enabled through `sylius_shop.product_grid.include_all_descendants`. The filter system
therefore extends that grid with a grid mutator and custom Doctrine ORM filters instead
of introducing a second product listing query.

**C/D — Profiles and suitable attributes.** `ProductAttributeProfileService` owns the
Cardnext taxon profiles and their attribute definitions. A definition is exposed only
when it belongs to the resolved profile and explicitly contains `filterable: true`.
Controlled select/multiselect values and meaningful booleans are enabled; descriptions,
uncontrolled text and high-cardinality measurements remain excluded.

**E — Attribute storage.** Sylius select and multiselect values use the JSON storage
column and contain stable choice keys (a scalar/list depending on the attribute
configuration). Checkbox, integer, float and text values use their typed columns. The
filter URL and SQL matching use choice keys; labels are resolved from the attribute's
locale configuration.

**F — Template.** The existing Sylius product index override remains responsible for
the Cardnext sidebar, sorting, result grid and pagination. Filters are rendered through
the existing Sylius grid filter form, so criteria automatically remain GET parameters.

**G — Queries and counts.** Facets use three bounded queries: one query obtains the
distinct product ids in the same taxon-descendant, channel and enabled scope while
applying active filters; one groups their manufacturer relation; and one loads all
filterable attribute rows for those ids. Values and distinct-product counts are folded
in memory. Query count is constant with respect to products and facet values, and no
product entity or attribute collection is hydrated.

Within one submitted attribute group, values are joined with `OR`; separate attribute
groups and the manufacturer group are correlated `EXISTS` clauses joined with `AND`.
Unknown codes never enter the query, submitted values are intersected with profile
choice keys, and all values are bound parameters.
