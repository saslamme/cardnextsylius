# Cardnext market matrix

`CardnextMarketRegistry` is the single application-level source for market metadata. Sylius channels persist the matching hostname, default locale and base currency and remain authoritative for catalogue availability and prices.

| Channel | Country | Hostname | Locale | Currency |
|---|---|---|---|---|
| CARDNEXT_DE | DE | www.cardnext.de | de_DE | EUR |
| CARDNEXT_AT | AT | at.cardnext.de | de_AT | EUR |
| CARDNEXT_DK | DK | dk.cardnext.de | da_DK | DKK |
| CARDNEXT_ES | ES | es.cardnext.de | es_ES | EUR |
| CARDNEXT_IT | IT | it.cardnext.de | it_IT | EUR |
| CARDNEXT_NL | NL | nl.cardnext.de | nl_NL | EUR |
| CARDNEXT_SE | SE | se.cardnext.de | sv_SE | SEK |

Run `php bin/console app:cardnext:setup-markets` during deployment to idempotently create/update the locale, currency, country and channel records. It does not assign products, prices, tax rules, shipping, payments, issuer profiles or legal pages.
