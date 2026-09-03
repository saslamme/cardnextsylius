# Cardnext CMS

The CMS is deliberately a small Sylius feature. Pages, reusable structured blocks, controlled layouts, menus and redirects are separate Doctrine concepts. `CmsPage` uses the existing Sylius channels; a translation owns its locale-free slug and SEO values. Menus therefore link one page from any number of positions without duplicating content.

## Publication and resolution

The final, priority `-255` storefront route asks Sylius' `ChannelContext` and `LocaleContext` to resolve a normalized path. A page is visible only while published and in its publishing window, on the current channel, with an exact current-locale translation and an enabled layout. Reserved application prefixes are rejected before querying. Existing Symfony routes always win. Redirect lookup is performed only when no real page wins, in the same channel/locale, and a target must resolve to a currently visible page.

The sitemap appends only visible CMS translations with `includeInSitemap` and `robotsIndex`. CMS templates supply title, description, robots, OpenGraph and canonical fallbacks.

## Layouts and blocks

Layouts store a renderer key, never executable templates. Add a renderer by adding its key to `CmsLayout` validation and mapping it to a code-owned page template. Add a block by adding its key/template to `CmsBlockRendererRegistry`, its schema validation there, an admin form configuration, and a template under `templates/shop/cms/block`. V1 supports `rich_text`, `hero`, `image_text`, `faq` and `cta`. Rich text uses a strict output allow-list; URLs are validated and ordinary fields remain escaped.

## Navigation

Stable menu codes are `utility`, `footer_solutions`, `footer_service`, and `footer_cardnext`. Menu items are channel- and locale-specific, ordered, nestable, and target exactly one page, Symfony route, or safe URL. Header/footer templates retain their prior links whenever a menu is empty, making deployment safe before content entry.

Example: create the published page code `support`, assign the `standard` layout and required channels, and add a `de_DE` translation with title `Support` and slug `support`. Add PAGE items referencing it to both `utility` and `footer_service`, with the same channel and locale.

## Deployment

```bash
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

Signed draft preview and a full revision workflow are intentionally deferred; drafts are never publicly rendered.
