# Cardnext Klassen-Migration

| Bisher | Zentralisiert |
|---|---|
| `cardnext-container`, `cn-category__container` | `cn-container` |
| `cardnext-smallcap`, `cn-smallcap`, `cn-category__smallcap`, `cn-product__smallcap` | `cn-kicker` |
| seitenbezogene Section-Klassen | `cn-section` + Modifier |
| `cn-btn`, `cn-product__add-cart`, `cn-contact-form__submit` | `cn-btn` + Modifier |
| `cn-link-arrow`, `cn-category-card__details`, `cn-product-related__link` | `cn-link cn-link--arrow` |
| `cn-category-card`, `cn-product-related` | `cn-product-card` |
| `cn-category-card__stock`, `cn-product__stock-status` | `cn-status` |
| Produkt-Tab-Hotfix-Klassen | `cn-tabs`, `cn-tabs__list`, `cn-tabs__tab`, `cn-tabs__panel` |
| Download-spezifische Klassen | `cn-resource-grid`, `cn-resource` |
| technische Daten | `cn-specs`, `cn-specs__row` |
| `cn-contact-form` | `cn-panel` |
| `cn-filter-accordion*` | `cn-accordion*` |
| `cardnext-breadcrumbs*` | `cn-breadcrumbs*` |
| `cardnext-icon-button` | `cn-icon-btn` |

## Grundregel

Erst vorhandene Primitives/Komponenten kombinieren. Eine neue Klasse nur dann anlegen, wenn ein neues **wiederverwendbares Muster** oder eine tatsächlich einzigartige Struktur entsteht.
