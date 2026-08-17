# Flat public URL routing

Cardnext uses one locale-aware public slug namespace for category and product
pages. The previous Sylius addresses were `/{locale}/taxons/{slug}` and
`/{locale}/products/{slug}`. Both route names now generate
`/{locale}/{slug}`; no legacy redirect is provided because the shop was not
live when this changed.

`PublicSlugController` resolves an enabled taxon belonging to the current
channel's menu-taxonomy first, then an enabled product belonging to the
current channel, and otherwise returns 404. It delegates rendering to the
standard Sylius product resource controller, so category grids (including
facets, filters, sorting and pagination) and product detail components remain
unchanged. The structural taxonomy root is excluded by its actual code,
`CARDNEXT_PRODUCTS`, rather than by a translated slug.

Product and taxon translations are protected by a cross-entity validation
constraint scoped to their locale. Existing data can be audited without
modification:

```console
php bin/console cardnext:check-public-slugs
```

The command exits successfully when there are no collisions and prints the
locale, slug, product code and taxon code for every collision otherwise.

## Deployment

1. Deploy the application code (there is no schema migration).
2. Clear the Symfony cache.
3. Run `php bin/console cardnext:check-public-slugs` for every environment and
   resolve reported collisions manually before publishing it.
4. Warm the cache and smoke-test one category and product in each enabled
   locale/channel.
