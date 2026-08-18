# Cardnext Configurator Architecture and Sylius Integration Audit

**Audit date:** 2026-08-18  
**Baseline:** current repository branch after the changes described as PR #70  
**Installed platform:** Sylius `v2.2.8`, Symfony FrameworkBundle `v7.4.16` (from `composer.lock`)  
**Scope:** `src/`, `templates/`, `assets/`, `config/`, `migrations/`, `tests/`, `translations/`, and relevant installed Sylius/Refund Plugin code.  
**Change policy:** this report is the only changed file. No application code, dependency, vendor file, or migration was changed.

## 1. Executive Summary

The configurator has a credible domain core: prices are recomputed server-side from channel/currency context, money is stored in integer minor units, rule resolution is deterministic, historical presentation data is snapshotted, configured totals are exposed to payment providers through the native Sylius order total, and cart mutations verify both CSRF and cart ownership. Keeping `order.configuredItems` separate from native `OrderItem`s is therefore an **acceptable custom architecture**, not itself a defect.

It is not yet ready for broad production use. The separate line model has only been bridged into selected Sylius capabilities. In particular, Sylius taxation iterates taxable order-item units/adjustments produced for native items; no configured-item tax processor exists. Shipment creation/calculation and Refund Plugin credit-memo flows similarly operate on native units. Consequently tax, physical fulfilment, and granular refunds are not domain-complete. Promotions and B2B pricing also need explicit product decisions and adapters.

The most immediate security defect is concrete: several admin delete/update actions call `validToken()` but ignore its boolean result, then mutate and flush. A forged request with an invalid token can therefore pass on those routes for an authenticated administrator. Public calculation is computationally unbounded by a configured maximum quantity/rate limit. The shop add route itself does not accept a browser-supplied price, total, channel, currency, tax category, or shipping flag.

**Finding totals:** 24: **2 Critical, 6 High, 9 Medium, 4 Low, 3 Info**. Three findings are `MISSING_BUSINESS_INTEGRATION`; two findings are Symfony `NON_CONFORMANT` and two are Sylius `NON_CONFORMANT` (some findings have a primary category rather than duplicating conformance labels).

## 2. Top 10 Findings

| Rank | Finding | Severity | Area | Why important | Recommendation |
|---:|---|---|---|---|---|
| 1 | CFG-001 | CRITICAL | Tax | Configured revenue is added as a plain order adjustment; configured tax is never calculated or represented in `taxTotal`. | Add an explicit configured-item taxation integration before accepting taxable orders. |
| 2 | CFG-002 | CRITICAL | Shipping | `isShippingRequired()` opens the shipping path but configured lines create no shipment units; rates/rules cannot reliably see their physical attributes. | Design a real configured fulfilment/shipping subject, without fake order-item units. |
| 3 | CFG-003 | HIGH | Security/CSRF | Multiple admin mutations ignore a failed CSRF check. | Fail closed consistently and add functional CSRF tests. |
| 4 | CFG-004 | HIGH | Refunds | Refund Plugin is unit/credit-memo oriented; configured lines are absent from its model/UI. | Add a configured-line refund/credit-memo extension. |
| 5 | CFG-005 | HIGH | Pricing/DoS | Quantity has no system ceiling; integer multiplication can exhaust business limits or overflow. | Introduce centrally validated per-configurator limits and checked arithmetic. |
| 6 | CFG-006 | HIGH | Data integrity | Snapshot version is constant and no configurator/pricing revision identifies the rules used. | Snapshot immutable revision/schema/pricing identifiers. |
| 7 | CFG-007 | HIGH | Checkout | No end-to-end tests prove tax/shipment/payment/refund behavior against real Sylius processors. | Add kernel/functional lifecycle coverage before launch. |
| 8 | CFG-008 | HIGH | Admin integrity | The 770-line manual controller can persist partially valid aggregates and catches broad throwables. | Extract validated input/application services in small PRs. |
| 9 | CFG-009 | MEDIUM | Promotions | Native item promotions cannot target configured lines; order promotion semantics are unverified. | Decide discount policy, then implement/test only required promotion types. |
| 10 | CFG-010 | MEDIUM | B2B | B2B calculators/processors cover product variants, not configured totals. | Define configured B2B policy and introduce explicit pricing context/rules. |

## 3. Configurator Architecture Map

| Layer | Components | Responsibility / endpoints |
|---|---|---|
| Domain entities | `Configurator`, `ConfiguratorSection`, `ConfiguratorField`, `ConfiguratorValue`, `ConfiguratorDependency`, `ConfiguratorPriceRule`, `ConfiguratorLeadTime`, `ConfiguratorTranslation`, `ConfiguratorImage`, `ConfiguratorTaxon` | Aggregate definition, channel/taxon/media assignment, rules and lead-time metadata. Doctrine entities are intentionally not Sylius Resources. |
| Order entities | `ConfiguredOrderItem`, `Order`, `ConfiguredItemsAwareOrderInterface` | Immutable-ish purchase snapshots in `order.configuredItems`; order emptiness and shipping intent extensions. |
| Enums/value semantics | `FieldType`, `DependencyEffect`, `DependencyOperator`, `PriceType`, `MultiplierType`, `PercentageBase` | Closed rule vocabulary. |
| Input/output DTOs | `ConfiguratorConfiguration`, `ConfiguratorPriceResult`, `PriceBreakdownLine`, `ValidationError`, `ValidationResult` | Typed calculation input/results; HTTP is still manually mapped to the DTO. |
| Repositories | `ConfiguratorRepository`, `ConfiguratorPriceRuleRepository`, `ConfiguratorValueRepository` | Enabled/path lookup and eagerly joined applicable pricing rule query. |
| Domain services | `ConfiguratorValidator`, `DependencyStateResolver`, `ConfiguratorPriceCalculator`, `PriceRuleResolver`, `PriceRuleOverlapValidator`, `ConfigurationHashGenerator`, `ConfiguredOrderItemSnapshotFactory` | Validation, dependency state, deterministic pricing, overlap rejection, hashing and order snapshot creation. |
| Admin application | `ConfiguratorAdminController`, `ConfiguratorAggregateDeleter`, `DecimalAmountTransformer`, admin Twig templates | Manual CRUD/queries/forms for all aggregate children. Admin role is enforced at class level. No Sylius Grid/Resource workflow. |
| Shop HTTP | `ConfiguratorPageController` (`GET` page), `ConfiguratorCalculateController` (`POST /configurators/{code}/calculate`), `ConfiguredCartController` (`POST` add/quantity/remove), `ConfiguratorPageResolver` | Resolve published-looking enabled configurators, calculate, create/update/remove snapshot lines. |
| Sylius lifecycle | `ConfiguredItemsOrderProcessor` tagged `sylius.order_processor`, priority 5; custom `Order::isEmpty()` and `Order::isShippingRequired()` | Rebuild one `cardnext_configured_items` order adjustment and expose configured-only carts to checkout. |
| Presentation | `ConfiguratorProductComponent`, `TaxonConfiguratorsComponent`; configurator/cart/checkout/order/admin/email templates; hooks in `cardnext_twig_hooks.yaml` | Shop rendering and post-order visibility. Hooks cover most total/detail insertions. |
| Full overrides | `bundles/SyliusShopBundle/cart/.../items/body.html.twig`, `email/order_confirmation.html.twig`, `order/show/content/header.html.twig`, admin dashboard new-orders template, plus broader non-configurator product templates | Cart rows/email/position counts where current hook granularity was insufficient; upgrade-sensitive. |
| Browser code | `assets/shop/configurator.js`, `assets/shop/entrypoint.js`, `assets/shop/styles/cardnext.css` | Dependency UI, debounced pricing, AbortController stale-response protection, add/update/remove actions. Vanilla module, not Stimulus. |
| Routing/security | Attribute routes imported by `config/routes/attributes.yaml`; shop precedence comments in `zz_cardnext_shop.yaml`; `security.yaml` admin/shop firewalls | Route method restrictions, admin access, route-level/cart ownership checks and CSRF. |
| Persistence | migrations `Version20260817120000` through `Version20260818230000` | Aggregate tables/indexes/FKs, standalone translations/channels/media/taxons, configured snapshots, tax/shipping flags. |
| Tests | `tests/Configurator` (13 files), `tests/Checkout`, `tests/Order`, `tests/Template` | Mostly unit/source-architecture/template regression tests; no JavaScript E2E and insufficient live Sylius lifecycle tests. |
| Not present | Configurator forms/constraints, commands, Messenger handlers, voters, API Platform resources, audit-log subscriber, configured tax/shipping/promotion/refund processors | Absence is only problematic where called out in findings. |

## 4. Request/Data Flow

### Admin definition flow

1. `ConfiguratorAdminController` manually maps `Request` fields into entities and flushes through `EntityManagerInterface`. The browser/admin session is the trust boundary; class-level `#[IsGranted(DEFAULT_ADMIN_ROLE)]` is correct.
2. Sections own ordered fields; choice fields own ordered values. Dependencies reference source/target fields/values. Price rules bind configurator, optional value/lead time/channel, currency, quantity interval, charge dimension, amount and multiplier.
3. Translations, channels, media and taxons are stored separately. `enabled` is the only publication state; `findEnabledByCode()`/page resolution control shop visibility.
4. DB unique constraints protect primary technical-code/path dimensions. `PriceRuleOverlapValidator` supplements rule persistence, but aggregate-wide publish validation and revisioning do not exist.

### Shop calculation and cart flow

1. Twig renders server-owned configurator metadata and a CSRF token. Browser code collects `quantity`, `selections` and `leadTimeCode`; displayed price is never trusted for cart creation.
2. `ConfiguratorCalculateController::__invoke()` parses JSON, obtains channel and currency from Sylius contexts, confirms channel assignment, builds `ConfiguratorConfiguration`, and calls `ConfiguratorPriceCalculator::calculate()`.
3. The calculator re-fetches the enabled configurator, validates field/dependency membership, resolves enabled rules for server channel/currency/quantity, uses deterministic ordering, integer minor units, and half-away-from-zero basis-point rounding.
4. `ConfiguredCartController::add()` repeats server calculation after CSRF validation. It ignores client price/total/currency/channel/shipping/tax payload values, creates a snapshot through `ConfiguredOrderItemSnapshotFactory`, attaches it to the current cart, runs the native composite order processor and flushes.
5. Quantity update starts from stored canonical selections, replaces only the quantity, recalculates current pricing, replaces pricing snapshots, reprocesses and flushes. Remove checks current-cart identity and CSRF before orphan removal.

### Order/checkout/post-order flow

1. `ConfiguredItemsOrderProcessor` removes the previous custom adjustment, sums snapshots and creates one non-neutral order adjustment. Repeated processing is idempotent with respect to this adjustment.
2. `Order::isEmpty()` lets configured-only carts survive native empty-cart checks. `Order::isShippingRequired()` requests checkout shipping when any configured snapshot says so.
3. Native payment preparation sees `order.total`, so Mollie/Stripe/Adyen/Payum integrations remain provider-neutral. No provider-specific configured arithmetic was found.
4. Twig hooks/limited overrides expose configured lines in cart, checkout review, completion, customer/internal mail and admin order detail.
5. The trace stops at native taxation, shipment-unit creation, promotions and Refund Plugin: none enumerates `configuredItems` through a configured-specific adapter. These are missing lifecycle integrations, not evidence that the separate entity is inherently wrong.

### Trust-boundary table

| Boundary | Untrusted data | Server source/recalculation | Stored snapshot | Extension point |
|---|---|---|---|---|
| Calculate POST | quantity, selections, lead time | configurator repository + channel/currency contexts + calculator | none | Symfony controller/service |
| Add POST | same; arbitrary extra JSON ignored | complete recalculation | names/codes/locale/channel/currency/amounts/breakdown/canonical/tax/shipping | Sylius cart context + order processor |
| Quantity POST | item id, quantity | current-cart identity, stored canonical configuration, current rules | pricing/canonical fields replaced | Sylius cart context + order processor |
| Remove POST | item id | current-cart identity | row deleted through orphan removal | Doctrine/order processor |
| Checkout | cart/session/address/payment/shipping choice | native Sylius processors | native order plus configured snapshots | state machine/order processors |
| Post-order | persisted snapshots | no configurator lookup needed for display | historical row | Twig hooks/email/admin |

## 5. What Is Already Good

* **Keep the separate configured-line model.** The interface boundary prevents payment-provider coupling and the order association is consistent (`cascade persist`, `orphanRemoval`, FK cascade, initialized collection, bidirectional add). This is `ACCEPTABLE_CUSTOM`.
* **Keep server-authoritative add-to-cart pricing.** Only selection/quantity/lead time enter the calculation; channel and currency are contexts, and the resulting entity comes from server models.
* **Keep integer money and explicit rounding.** UNIT/FIXED are minor-unit integers; percentages are basis points. `basisPoints()` rounds half away from zero after applying the full percentage base.
* **Keep deterministic resolution.** Applicable rules are ordered and resolver output has a stable composite sort. Hash canonicalization recursively sorts associative keys while intentionally preserving list order.
* **Keep snapshot presentation.** Orders remain legible if labels/options/configurator are later edited or deleted. Autoescaping and `textContent` are used rather than raw snapshot HTML.
* **Keep adjustment rebuilding.** Removing and recreating the dedicated adjustment makes the processor idempotent after quantity/removal changes.
* **Keep provider-neutral payments.** Providers can consume native `order.total`; there is no duplicated Mollie/Stripe/Adyen calculation.
* **Keep Twig Hooks where available.** Checkout/admin totals and details use supported hooks rather than copying whole screens.
* **Keep ownership checks.** Quantity/removal compare the configured line's exact order object with the current cart before mutation.

## 6. Symfony Conformance

Constructor injection, autowiring/autoconfiguration, attribute routes and explicit HTTP methods are consistently used in shop code. No container/service-locator/static service access was found. Shop controllers delegate pricing/validation/snapshot construction cleanly enough.

The admin controller is a concrete exception: it combines 30+ endpoints, request parsing, aggregate rules, DQL, file operations and persistence. Manual input is acceptable for a small CRUD surface but this one is large enough to create inconsistent validation and testing. Broad `catch (\Throwable)` also converts programming/database faults into admin flash messages and can expose exception text. Symfony Forms/Validator are not mandatory, but validated input/application services now have direct value.

CSRF is present in forms, yet ignored return values on specific mutations are a real non-conformance (CFG-003), not a stylistic concern. Hard-coded German response/validation strings bypass translation catalogues (CFG-016).

## 7. Sylius Conformance

The processor uses the documented `sylius.order_processor` tag and a dedicated adjustment. Its priority 5 must be protected by an integration test against the installed composite ordering. The adjustment is non-neutral (default), which correctly affects order total/payment, but it is not an item tax/promotion/shipment subject.

`Order::isEmpty()` preserves native meaning plus the custom collection and is appropriate. `isShippingRequired()` expresses intent but cannot alone populate a shipment. ResourceBundle/GridBundle would improve standard admin pagination/filtering/events/factories, yet migration is not justified solely for conformance; the near-term need is validation/lifecycle, not framework ceremony.

Configured entity creation through the snapshot factory is already centralized. Replacing its internal `new ConfiguredOrderItem()` with a generic Sylius factory offers no current benefit.

## 8. Domain Model

Codes have meaningful unique constraints: configurator globally, section per configurator, field per configurator, value per field, lead time per configurator, translation/path per locale. Ordered collections include `position, id` tie-breakers. Rule lookup and configured order/hash indexes exist.

Cross-aggregate references are guarded in many controller methods with `assertSame()`. However lifecycle is binary enabled/disabled, code immutability/revision semantics are implicit, and publish-time aggregate validation is absent. JSON snapshots use unrestricted DB JSON columns without application schema/size/version validation.

## 9. Pricing

### Current algorithm

* Repository filters enabled rules by configurator, upper/lower quantity, currency, selected value/lead time and global-or-specific channel.
* Resolver chooses a channel-specific rule over a global rule per dimension and rejects ambiguous peers.
* UNIT amounts form base/options unit price and are multiplied by quantity once. FIXED amounts apply optional quantity/field multipliers. PERCENT rules are deferred and operate on BASE, OPTIONS or accumulating SUBTOTAL.
* `basisPoints(amount, bp)` calculates `amount * bp`, adds 5000 in magnitude, then integer-divides by 10000: nearest minor unit, ties away from zero.

This is deterministic for persisted rules. Remaining risks are unchecked integer overflow/huge quantity, float-based decimal field validation, no dated/revisioned price rules, and unclear negative-price policy. Client prices cannot influence the persisted total.

## 10. Snapshots / Historical Integrity

Present: configurator code/name, locale/channel/currency, quantity, all amount totals, lead-time code/name/working days, tax-category code, shipping flag, selection labels, price breakdown, canonical configuration, hash, timestamps and `snapshotVersion=1`.

Missing: configurator revision/hash, pricing revision/effective timestamp and a defined JSON schema migration contract. The configuration hash is canonical for associative keys and Unicode/slashes, but multiple-choice arrays preserve client order; semantically identical unordered multi-selects can therefore hash differently. Numeric strings versus numbers also remain distinct. That may be desirable for typed decimal input, but must be specified.

## 11. Tax

Answers based on the traced code:

1. **Are configured items taxed?** No configured-specific tax is calculated.
2. **Where?** Nowhere in application code; native Sylius tax processors operate on native taxable items/units and their adjustments.
3. **Does `order.taxTotal` contain it?** No evidence/path adds configured tax adjustments, so no.
4. **Is `cardnext_configured_items` taxable?** It is a plain order-level adjustment with no tax-category subject/relationship.
5. **Is `taxCategoryCode` used?** Snapshot/display only; no calculator consumes it.
6–9. **Channel/country, net/gross, zones/categories?** Not implemented for configured lines.
10–13. **B2B VAT, reverse charge, exemption, CH/non-EU?** Native order items may follow Sylius rules, but configured lines do not enter that pipeline, so these cases are unsupported.

This is CFG-001 (CRITICAL). Whether stored configured prices are net or gross is also not encoded, so simply appending a tax adjustment would be unsafe without a price/tax policy.

## 12. Shipping

`Order::isShippingRequired()` correctly distinguishes configured-only digital/non-shipping orders from physical configurations at checkout gating. However Sylius shipment creation and calculators conventionally consume `OrderItemUnit`s/native shipment units. A configured-only physical order has no such units. Weight, quantity, shipping category, per-item rules, multiple shipments and destination-sensitive rates cannot see configured lines. Mixed orders may acquire a shipment from native lines while silently omitting configured fulfilment content. Free-shipping promotion similarly lacks a configured subject. This is more than visual, and needs CFG-002 before shipping configured goods.

## 13. Promotions

Configured totals can contribute indirectly wherever a rule reads final/order total, but item promotion eligibility/actions enumerate native items/units. Percentage/fixed allocation, minimum-order thresholds and promotion tax/refund interactions have no dedicated tests. The intended policy (discountable or excluded) is unknown; classify CFG-009 as a missing business integration rather than an automatic bug.

## 14. Payments

The custom positive adjustment contributes to native `order.total`. This is the right provider-neutral boundary for Payum/Mollie/Stripe/Adyen. Payment amount correctness still depends on resolving tax/promotion/shipping first. No provider-specific duplicate configured surcharge was found. Preserve this design (CFG-023).

## 15. Refunds

Refund Plugin routes/UI/credit memo use order-item units and shipment adjustments. Configured lines have neither units nor configured refund records. A full payment refund might be initiated provider-side/manual, but the application cannot model a configured line, quantity 250 partial refund, correct configured taxes, or a complete mixed credit memo. Do not manufacture fake units; extend refund calculation/persistence/UI with configured refund lines (CFG-004).

## 16. Inventory

No stock is decremented because configured lines are not variants/units. That is acceptable if all outputs are made to order. If materials/options have finite stock, availability is currently unenforced and requires a future component/material reservation model. This remains an explicit optional business decision (CFG-020), not a present Sylius defect.

## 17. Lead Times

Lead times store a non-negative integer `workingDays`, enabled/position/name/description and optional price rules; the order snapshot preserves code/name/days. No calendar computes weekends, holidays, shutdowns, cutoff, destination transit or promised date. A Production Calendar/Delivery Date Calculator is recommended only when customer-facing dates/SLAs are introduced (CFG-019).

## 18. Channel / Locale / Currency

Configurator availability is channel-assigned and server checked. Rules may be global or channel-specific and are currency-specific. Currency/channel cannot be selected by client. Configurator top-level content has translations; field/value/section/lead-time labels are single-language, while server/API/JS strings are mostly German. Snapshot locale is captured, but untranslated nested labels prevent trustworthy multi-locale orders. There is no currency conversion: each offered currency needs complete base rules, which is valid if enforced/diagnosed.

## 19. Security

Positive controls: role-protected admin controller, POST-only mutations, CSRF on shop mutations, current-cart ownership checks (IDOR resistance), server context for channel/currency, ignored client prices/tax/shipping, Twig autoescape and JS `textContent`, parameterized Doctrine queries.

Risks: ignored admin CSRF failures; public compute endpoint lacks RateLimiter; no request-body/selection/text/quantity global cap; broad exception messages in admin; and parallel cart requests have no idempotency/optimistic conflict control. Configurator-code substitution only selects another enabled configurator in the current channel and is then fully revalidated, so it is not by itself a price-tampering vector.

## 20. Admin UX

CRUD covers definition, structure, values, prices, dependencies, lead times, translations, channels, checkout settings, media and taxons. Missing operational safeguards are draft/publish/archive, preview, clone, revision/history, effective dates and aggregate preflight validation. The handmade index lacks Sylius Grid filtering/sorting/pagination affordances. These matter increasingly with catalogue size; adopting Grid for the list is recommended, whereas converting every entity to ResourceBundle is not.

## 21. Shop UX

The browser provides debounce, loading/invalid state, inline/general errors, dependency clearing, cancellation and disabled add until calculation. The core purchase flow requires JavaScript; for an interactive configurator this can be acceptable if clearly documented and monitored. Accessibility uses labels/errors and avoids raw HTML, but dynamic errors do not consistently move focus/announce status, dependency hiding can invalidate focus, and several German `aria-label`/notes are hard-coded. Mobile behavior has no E2E evidence.

## 22. JavaScript

Vanilla module code is scoped per `[data-configurator]`, uses `AbortController` and invalidates stale calculated payloads, which addresses the main price race. Debounce avoids request storms. There is no compelling reason to migrate solely to Stimulus; Stimulus would help lifecycle/connect-disconnect and testability if configurators become dynamically mounted. Add-button/double-submit/idempotency and network retry semantics need tests. The calculation endpoint being POST is correct; if it remains side-effect-free, CSRF is less critical than rate/body limits, while add remains CSRF protected.

## 23. Performance

Pricing loads an enabled aggregate and then applicable rules with eager joins. Ordered collections make calculation predictable but a large aggregate can hydrate many sections/fields/values before each calculate/add. No query/result cache exists. Safe cache candidates are immutable published configurator definitions/translations keyed by revision/channel/locale; never cache customer/B2B results without identity and pricing revision. Admin index/manual queries should be paginated. JSON snapshots can grow without bounds and are repeated per line.

## 24. Database / Doctrine

Mappings provide FKs, cascades and useful parent/lookup indexes. The configured relation is correctly owning on the item and initialized on order. Removal relies on orphan removal and does not need clearing the child's back-reference for deletion, though doing so would make in-memory invariants clearer. No optimistic version exists. JSON has no DB size constraint beyond the platform JSON representation. `doctrine:schema:validate` could not run because the checked-out vendor directory lacks Symfony Runtime.

## 25. Test Coverage

### Inventory

* Unit/source architecture: calculator/core, rule repository/resolver assumptions, decimal transformer, page architecture, standalone architecture.
* Order/checkout/template regression: configured processor, order cart contents, checkout metadata, configured checkout, presentation, cart/offcanvas/internal mail/post-order count.
* Admin input/template: controller input source checks and price form template.
* Missing: browser E2E, live HTTP/kernel pricing/cart, native taxation/shipping/promotion/payment/refund lifecycle, concurrency.

### Scenario matrix

| # | Scenario | Status |
|---:|---|---|
| 1 | Simple product | Present (unit) |
| 2 | Multiple options | Present (unit/partial) |
| 3 | Required field missing | Present (validator unit) |
| 4 | Invalid option | Present (validator unit) |
| 5 | Client manipulates price | Partial (architecture/source, no HTTP test) |
| 6 | Client manipulates total | Partial |
| 7 | Quantity 0 | Partial (controller/calculator paths) |
| 8 | Negative quantity | Partial |
| 9 | Extremely large quantity | Missing |
| 10 | Quantity tier | Present (unit) |
| 11 | Two surcharges | Present (unit) |
| 12 | Percentage surcharge | Present (unit; boundary matrix incomplete) |
| 13 | Lead-time surcharge | Present (unit) |
| 14 | Configured-only order | Partial (source/unit checkout) |
| 15 | Mixed order | Partial |
| 16 | shippingRequired true | Partial (gate only) |
| 17 | shippingRequired false | Partial (gate only) |
| 18 | Tax | Missing |
| 19 | Promotion | Missing |
| 20 | Payment | Partial (total processor only) |
| 21 | Refund | Missing |
| 22 | Multiple channels | Partial (rule unit) |
| 23 | Multiple currencies | Partial (rule unit) |
| 24 | Multiple locales | Partial (presentation only) |
| 25 | Historical snapshot | Present (unit/template) |
| 26 | Configurator changed after order | Partial (snapshot design, no lifecycle test) |
| 27 | Configurator deleted after order | Partial |
| 28 | Option deleted after order | Partial |
| 29 | Repeated order processing | Present (unit) |
| 30 | Parallel add-to-cart | Missing |

## 26. Observability

No configurator-specific structured logging was found for rejected payloads, ambiguous/missing pricing, snapshot creation or checkout processing. Public errors are intentionally generic, which is good for disclosure but leaves operations blind. Log event codes, configurator/revision/channel/currency and non-sensitive error classes; avoid selections that may contain personal/free text. Admin definition changes lack actor/old/new audit history.

## 27. Upgradeability

| Customization | Risk | Assessment |
|---|---|---|
| Order entity extension/interface | Medium | Supported application entity override, but lifecycle behavior must be regression-tested each Sylius update. |
| `sylius.order_processor` tag | Low | Public extension point; priority interaction needs a test. |
| Twig Hooks in `cardnext_twig_hooks.yaml` | Low–Medium | Preferred Sylius 2.2 extension mechanism; hook names can change. |
| Full cart items body override | High | Closely coupled to native cart markup/form/hook context. Seek a row/body hook when available. |
| Full order-confirmation email override | High | Copies a large vendor document to insert configured rows/totals; extract/use mail hooks if Sylius exposes adequate points. |
| Admin dashboard new-orders override | Medium | Small semantic change but full component copy. Prefer component/hook decoration. |
| Order show header override | Medium | Small file but tied to vendor context/name. |
| Manual admin CRUD | Low platform risk / Medium maintenance | Not dependent on Resource internals, but repeats framework concerns. |
| Refund absence | High business risk | Plugin upgrades will not add configured support automatically. |

## 28. Missing Business Features

**REQUIRED before relevant production scope:** configured taxation; physical fulfilment/shipping integration when `shippingRequired`; refund/credit-memo design; max quantity/value safeguards; explicit promotion/B2B policy; published-definition validation.

**RECOMMENDED:** draft/publish/archive plus preview, immutable revisions, clone, effective-dated pricing, nested label translations, audit history, configured reorder/copy, production export/webhook/ERP contract, structured logs.

**OPTIONAL:** rollback UI, import/export backup, raw-material inventory, production calendar/delivery-date promise, edit configuration in cart, internal production notes, production sheet, claim/reorder workflows. Invoice/delivery-note integration must be assessed alongside configured tax/refund work; no complete configured document adapter was found.

## 29. Detailed Findings

### CFG-001 — Configured taxation is absent
**Severity:** CRITICAL · **Category:** TAX · **Status:** NON_CONFORMANT  
**Evidence:** `src/OrderProcessing/ConfiguredItemsOrderProcessor.php:18-36`; `src/Entity/Order/ConfiguredOrderItem.php:50-56`; `src/Service/Configurator/ConfiguredOrderItemSnapshotFactory.php:46`; native Sylius taxation processors under `vendor/sylius/sylius/src/Sylius/Component/Core/OrderProcessing/`. **Class/method:** `ConfiguredItemsOrderProcessor::process()`.  
**Current:** a single plain order adjustment adds configured gross/undefined totals; tax category is only a string snapshot. **Problem/impact:** no zone/category/channel tax computation and no configured tax adjustment/taxTotal, causing legally and financially wrong totals across VAT/net/gross/reverse-charge/export cases. **Relevance:** bypasses the native taxable item/unit pipeline without replacement.  
**Recommendation:** first specify net/gross pricing semantics, then implement a configured taxation processor/calculator keyed by snapshot category, order zone/channel and tax-included setting; persist/display tax breakdown and make it idempotent. **Complexity:** XL · **Change risk:** High · **Depends on:** CFG-006, CFG-007.

### CFG-002 — Shipping gate has no fulfilment subjects
**Severity:** CRITICAL · **Category:** SHIPPING · **Status:** NON_CONFORMANT  
**Evidence:** `src/Entity/Order/Order.php:65-79`; no configured shipping processor in `src/`; configured entity has no weight/category/units. **Class:** `Order::isShippingRequired()`.  
**Current:** configured physical lines make the order “shipping required,” but native shipment creation/rules see native units only. **Impact:** empty/incomplete shipments, wrong availability/rates/free shipping and unfulfillable mixed orders. **Relevance:** extends a lifecycle predicate but not the corresponding Sylius shipment lifecycle.  
**Recommendation:** model configured fulfilment lines/attributes and adapt shipment creation/rate criteria/admin fulfilment; do not fake `OrderItemUnit`s. Explicitly cover configured-only/mixed/multi-shipment/digital cases. **Complexity:** XL · **Risk:** High · **Depends on:** CFG-007, CFG-009.

### CFG-003 — Admin mutations ignore invalid CSRF tokens
**Severity:** HIGH · **Category:** SECURITY · **Status:** NON_CONFORMANT  
**Evidence:** `src/Controller/Admin/ConfiguratorAdminController.php:147-153`, `228-248`, `274-280`; `validToken()` returns bool around lines 788-795. Similar delete/update routes must be audited together. **Methods:** `translationDelete()`, `imageUpdate()`, `imageDelete()`, `taxonDelete()` and analogous calls.  
**Current:** token result is called but not branched on; persistence follows. **Impact:** authenticated-admin cross-site request forgery can mutate/delete definitions/media. **Relevance:** violates Symfony's fail-closed CSRF contract.  
**Recommendation:** central assertion that throws access denied on false; enumerate every mutating route; functional invalid/missing/valid token tests. **Complexity:** S · **Risk:** Low · **Depends on:** none.

### CFG-004 — Refund and credit-memo model excludes configured lines
**Severity:** HIGH · **Category:** REFUND · **Status:** MISSING_BUSINESS_INTEGRATION  
**Evidence:** `src/Entity/Order/ConfiguredOrderItem.php:9-276` has no units/refund relation; no configured references in refund application code/templates. **Area:** Sylius Refund Plugin admin/refund/credit memo flows.  
**Current:** configured line/partial quantity cannot be selected or allocated in native refund UI. **Impact:** configured-only and mixed partial refunds, taxes, shipping allocation and credit memos are incomplete.  
**Recommendation:** configured refund-line abstraction, allocation calculator, UI/document/payment adapter; retain separate domain rather than fake units. **Complexity:** XL · **Risk:** High · **Depends on:** CFG-001, CFG-002, CFG-006.

### CFG-005 — Unbounded quantity and unchecked money arithmetic
**Severity:** HIGH · **Category:** SECURITY · **Status:** RECOMMENDED_IMPROVEMENT  
**Evidence:** `src/Controller/Shop/ConfiguredCartController.php:102-109` only requires `>=1`; `ConfiguratorCalculateController.php:45-58`; multiplication in `ConfiguratorPriceCalculator.php:75-112`.  
**Current:** no global/per-configurator maximum or checked overflow; public requests can trigger huge calculations/totals. **Impact:** abuse, overflow/TypeError/500, impossible orders and payment limits.  
**Recommendation:** configured maximum quantity and maximum total, safe multiplication/bounds, body limit and 422 errors. **Complexity:** M · **Risk:** Medium · **Depends on:** CFG-012.

### CFG-006 — Historical snapshots lack definition/pricing revision
**Severity:** HIGH · **Category:** DATA_INTEGRITY · **Status:** RECOMMENDED_IMPROVEMENT  
**Evidence:** `ConfiguredOrderItem::$snapshotVersion` is always 1 (`src/Entity/Order/ConfiguredOrderItem.php:91-92`); factory arguments at `src/Service/Configurator/ConfiguredOrderItemSnapshotFactory.php:46`.  
**Current:** rich labels/prices are saved, but no immutable configurator/rule-set revision/effective timestamp explains their origin. **Impact:** support/audit cannot prove which definition generated an order or migrate schema safely.  
**Recommendation:** revision/hash plus configuration/pricing schema versions; increment revision atomically on publish and snapshot it. **Complexity:** L · **Risk:** Medium · **Depends on:** CFG-013; migration required.

### CFG-007 — No executable native lifecycle coverage
**Severity:** HIGH · **Category:** TESTING · **Status:** RECOMMENDED_IMPROVEMENT  
**Evidence:** tests inventory under `tests/Configurator`, `tests/Checkout`, `tests/Order`, `tests/Template`; matrix above; no configured tax/shipping/refund functional tests.  
**Current:** strong source/unit regressions but no real kernel checkout completion proving processor ordering and plugin behavior. **Impact:** green tests can coexist with wrong tax/shipment/payment/refund.  
**Recommendation:** fixture-backed integration suite for configured-only/mixed order from add through completion, including repeated processing. **Complexity:** L · **Risk:** Low · **Depends on:** CFG-001/002/004 implementations.

### CFG-008 — Admin controller owns too many domain/persistence responsibilities
**Severity:** HIGH · **Category:** SYMFONY_CONFORMANCE · **Status:** NON_CONFORMANT  
**Evidence:** `src/Controller/Admin/ConfiguratorAdminController.php:42-810`, direct DQL at 303-305 and 417-418, manual `apply*` methods 645-727, broad catches.  
**Current:** 30+ CRUD endpoints parse raw form arrays, validate, query, mutate, upload and flush. **Impact:** inconsistent invariants/CSRF/error handling and difficult tests; some invalid aggregate states remain persistable.  
**Recommendation:** do not rewrite wholesale; extract typed request/application services per vertical slice, repositories for reusable queries, and aggregate validation. **Complexity:** L · **Risk:** Medium · **Depends on:** CFG-003, CFG-013.

### CFG-009 — Promotion semantics are undefined/unintegrated
**Severity:** MEDIUM · **Category:** PROMOTION · **Status:** MISSING_BUSINESS_INTEGRATION  
**Evidence:** only configured order adjustment in `src/OrderProcessing`; no promotion adapter/references; native item actions enumerate order items.  
**Current:** configured lines are not item-promotion subjects; order threshold/fixed/percentage effects and free shipping are unverified. **Impact:** coupons can exclude or allocate discounts unexpectedly in mixed orders.  
**Recommendation:** product decision matrix, then adapters/tests for allowed order promotions; deliberately reject unsupported coupons. **Complexity:** L · **Risk:** High · **Depends on:** CFG-001, CFG-002.

### CFG-010 — B2B prices do not include configured pricing policy
**Severity:** MEDIUM · **Category:** MISSING_BUSINESS_INTEGRATION · **Status:** MISSING_BUSINESS_INTEGRATION  
**Evidence:** `src/Calculator/B2BCatalogPriceCalculator.php`, `B2BProductVariantPriceCalculator.php` target variants; configurator rules only key channel/currency (`ConfiguratorPriceRuleRepository.php:21-25`).  
**Current:** no customer/group/price-list dimension reaches configured calculation. **Impact:** B2B customers may pay retail configured prices despite variant discounts.  
**Recommendation:** decide applicability; add explicit pricing context and configured customer/group rules, never post-hoc provider discounts. **Complexity:** L · **Risk:** High · **Depends on:** CFG-006.

### CFG-011 — Canonical hash preserves unordered multi-select order
**Severity:** MEDIUM · **Category:** DATA_INTEGRITY · **Status:** RECOMMENDED_IMPROVEMENT  
**Evidence:** `ConfigurationHashGenerator.php:19-30` preserves lists; multiple-choice accepts a list in `ConfiguratorValidator`.  
**Current:** associative keys sort, lists do not. **Impact:** identical checkbox sets submitted in differing order can produce distinct hashes/deduplication identities.  
**Recommendation:** canonicalize per field type (sort set-like value-code arrays, preserve genuinely ordered lists) and specify Unicode/numeric normalization. **Complexity:** S · **Risk:** Medium · **Depends on:** CFG-006.

### CFG-012 — Public calculation has no rate/body complexity control
**Severity:** MEDIUM · **Category:** SECURITY · **Status:** RECOMMENDED_IMPROVEMENT  
**Evidence:** public POST route `ConfiguratorCalculateController.php:30-81`; no `rate_limiter` configuration or limiter attribute/service for it.  
**Current:** any client can repeatedly force aggregate hydration/rule calculation with JSON. **Impact:** avoidable CPU/DB load. **Recommendation:** Symfony RateLimiter keyed by IP/session with proxy-aware policy, request-size/selection-count limits and metrics; keep calculate side-effect-free. **Complexity:** S · **Risk:** Low · **Depends on:** CFG-005.

### CFG-013 — Binary enabled flag is not a safe publication lifecycle
**Severity:** MEDIUM · **Category:** ARCHITECTURE · **Status:** RECOMMENDED_IMPROVEMENT  
**Evidence:** `Configurator::$enabled` (`src/Entity/Configurator/Configurator.php:27-28`); shop repository resolves enabled definition directly; admin writes live aggregate.  
**Current:** edits affect subsequent calculation immediately; no draft/preview/preflight/revision/rollback. **Impact:** half-edited configurations or prices can become sellable.  
**Recommendation:** start with draft versus published immutable revision and validation; a full workflow/state machine only if archive/scheduling/approvals need transitions. **Complexity:** XL · **Risk:** High · **Depends on:** CFG-006, CFG-014.

### CFG-014 — Aggregate logical validation is incomplete
**Severity:** MEDIUM · **Category:** DATA_INTEGRITY · **Status:** RECOMMENDED_IMPROVEMENT  
**Evidence:** controller validates fields locally; DB constraints in `migrations/Version20260817120000.php:19-29`; overlap service only covers price overlap.  
**Current:** DB protects references/codes but cannot reject enabled choice field without values, missing currency base tier, dependency cycles/dead rules or incomplete locales. **Impact:** enabled configurator can fail with 422 in shop.  
**Recommendation:** deterministic preflight validator invoked before publish and available as admin diagnostics. **Complexity:** L · **Risk:** Medium · **Depends on:** CFG-013.

### CFG-015 — Decimal constraint validation uses binary floats
**Severity:** MEDIUM · **Category:** PRICING · **Status:** RECOMMENDED_IMPROVEMENT  
**Evidence:** `ConfiguratorValidator::validateNumericConstraints()` casts decimal strings to float and uses `fmod` (`src/Service/Configurator/ConfiguratorValidator.php:174-193`).  
**Current:** tolerance `1e-9` masks common cases but decimal step/min/max semantics remain binary-float based. **Impact:** boundary values can validate differently from intended decimal rules or JS.  
**Recommendation:** normalize decimal inputs to scaled integers/string decimal arithmetic with configured scale; test locale-independent `0.1`, boundaries and large values. **Complexity:** M · **Risk:** Medium · **Depends on:** none.

### CFG-016 — Nested labels/errors are not fully localizable
**Severity:** MEDIUM · **Category:** UX · **Status:** RECOMMENDED_IMPROVEMENT  
**Evidence:** section/field/value/lead-time entities contain single `name`; German literals in shop controllers and `assets/shop/configurator.js:126-157`; catalogues only partly cover configured UI.  
**Current:** top-level configurator translation exists, nested definition and validation UI are German. **Impact:** multi-country/channel promise produces mixed-language pages/snapshots/emails.  
**Recommendation:** translate nested customer-facing metadata and all UI/errors; snapshot resolved locale with fallback policy. **Complexity:** XL · **Risk:** Medium · **Depends on:** CFG-006.

### CFG-017 — Large vendor template overrides increase upgrade risk
**Severity:** MEDIUM · **Category:** MAINTAINABILITY · **Status:** RECOMMENDED_IMPROVEMENT  
**Evidence:** full overrides listed in Architecture Map, notably cart items body and order-confirmation email; hooks at `config/packages/cardnext_twig_hooks.yaml:19-68`.  
**Current:** hooks are used where available, but several entire Sylius templates are copied for configured rows/count. **Impact:** Sylius 2.2 patch/minor markup/security/a11y changes may not propagate.  
**Recommendation:** maintain an override inventory/diff test; migrate only when an adequate hook/component exists. **Complexity:** M · **Risk:** Medium · **Depends on:** none.

### CFG-018 — Configurator operations lack structured observability/audit trail
**Severity:** LOW · **Category:** MAINTAINABILITY · **Status:** RECOMMENDED_IMPROVEMENT  
**Evidence:** no logger injection in configurator controllers/services; no definition-change event/audit entity/listener.  
**Current:** users get generic failures, operations cannot correlate missing/ambiguous rules or identify who changed a price. **Impact:** slow incident/support response.  
**Recommendation:** structured non-PII events and admin change audit tied to revision. **Complexity:** M · **Risk:** Low · **Depends on:** CFG-006.

### CFG-019 — Lead time is duration metadata, not a delivery promise
**Severity:** LOW · **Category:** OPTIONAL_FEATURE · **Status:** RECOMMENDED_IMPROVEMENT  
**Evidence:** `ConfiguratorLeadTime.php:28-45,84-94`; snapshot factory stores working days only.  
**Current:** integer working days omit calendars/transit/cutoff. **Impact:** acceptable until dates/SLA are shown; misleading if treated as arrival date. **Recommendation:** label as production duration; later calendar service keyed by facility/destination. **Complexity:** L · **Risk:** Medium · **Depends on:** CFG-002.

### CFG-020 — Inventory is deliberately absent but policy is undocumented
**Severity:** LOW · **Category:** OPTIONAL_FEATURE · **Status:** ACCEPTABLE_CUSTOM  
**Evidence:** configured entity has no variant/stock/component reference; snapshot selections only.  
**Current:** made-to-order configurations bypass Sylius Inventory. **Impact:** correct for unlimited production, wrong if options/materials are constrained. **Recommendation:** document policy; add component reservation only on real business need. **Complexity:** XL · **Risk:** High · **Depends on:** open question OQ-6.

### CFG-021 — Parallel cart mutation has no idempotency strategy
**Severity:** LOW · **Category:** ARCHITECTURE · **Status:** RECOMMENDED_IMPROVEMENT  
**Evidence:** `ConfiguredCartController::add()` attaches and flushes (`:45-57`) without idempotency key/version; quantity update is read/recalculate/flush.  
**Current:** double-click/two tabs can add duplicates or last-write-wins quantity. **Impact:** duplicate lines/confusing totals, not partial DB writes (single flush is transactionally atomic). **Recommendation:** disable submit plus server idempotency token for add; consider optimistic version only if observed. **Complexity:** M · **Risk:** Medium · **Depends on:** CFG-007.

### CFG-022 — Separate configured collection is coherent
**Severity:** INFO · **Category:** ARCHITECTURE · **Status:** CORRECT  
**Evidence:** `ConfiguredItemsAwareOrderInterface.php:11-17`; `Order.php:19-79`; configured FK/migration.  
**Current:** explicit collection with correct add/order link, persist/orphan removal, `isEmpty`. **Impact:** clean domain separation at cost of explicit adapters. **Recommendation:** retain; address missing integrations rather than converting to fake native items. **Complexity:** XS · **Risk:** Low · **Depends on:** CFG-001/002/004/009.

### CFG-023 — Adjustment-based provider-neutral payment integration is sound
**Severity:** INFO · **Category:** SYLIUS_CONFORMANCE · **Status:** CORRECT  
**Evidence:** `ConfiguredItemsOrderProcessor.php:12-36`, tagged priority 5 and rebuilding `cardnext_configured_items`.  
**Current:** native order total contains configured amount; providers need no special code. **Impact:** consistent payment boundary. **Recommendation:** retain and add priority/completion/history tests; taxation/promotion/refund need separate adjustments/allocation. **Complexity:** XS · **Risk:** Low · **Depends on:** CFG-001/007.

### CFG-024 — ResourceBundle/Grid absence is acceptable, Grid list is optional
**Severity:** INFO · **Category:** SYLIUS_CONFORMANCE · **Status:** ACCEPTABLE_CUSTOM  
**Evidence:** manual controller/index/templates; no configurator resource/grid config.  
**Current:** custom CRUD works without Sylius Resource events/factory/grid; creation is centralized where it matters for snapshots. **Impact:** no inherent correctness failure, though admin scale/filter/pagination suffer. **Recommendation:** do not migrate entities merely for convention; consider Sylius Grid for index when volume demands it. **Complexity:** M · **Risk:** Medium · **Depends on:** CFG-008.

## 30. Go-Live Blockers

### MUST FIX BEFORE GO-LIVE

1. CFG-003 CSRF failure handling.
2. CFG-005 quantity/total/body safety bounds.
3. CFG-001 taxation for every enabled market, or prevent configured checkout in taxable scope.
4. CFG-002 if any `shippingRequired=true` product is sold; otherwise force configured products to non-shipping scope.
5. CFG-004 refund/credit-memo operational path before accepting cancellable/refundable sales.
6. CFG-007 executable configured-only and mixed lifecycle tests.
7. Decide and enforce CFG-009/CFG-010 promotion/B2B behavior before advertising those benefits.

### SHOULD FIX SOON AFTER GO-LIVE

CFG-006 revision snapshots, CFG-012 rate limiting, CFG-013/014 publication/preflight, CFG-015 decimal semantics, CFG-016 localization, CFG-018 observability and CFG-021 idempotency.

### LATER / OPTIONAL

CFG-017 hook migration as supported, CFG-019 production calendar, CFG-020 component inventory, CFG-024 Grid adoption.

## 31. Recommended Roadmap

1. **PR — Fail closed on configurator admin CSRF.** Findings CFG-003. Scope: token assertion + route matrix tests. Non-scope: controller refactor. Risk Low; migration no; tests functional controller.
2. **PR — Bound configurator input and calculation load.** CFG-005/012. Scope: quantity/body/selection/total checks, checked arithmetic, limiter. Non-scope: price redesign. Risk Medium; migration only if per-configurator maximum persisted; tests unit/HTTP/rate.
3. **PR — Define and integrate configured taxation.** CFG-001/015. Scope: net/gross contract, zone/category processor, adjustments/snapshots. Non-scope: promotions/refunds. Risk High; migration likely; tests full tax matrix.
4. **PR — Model configured fulfilment for shipping.** CFG-002. Scope: shipment creation/rate input/admin fulfilment. Non-scope: inventory. Risk High; migration likely; tests configured-only/mixed/digital/zones/free-shipping baseline.
5. **PR — Add configured lifecycle integration tests.** CFG-007/023. Scope: real composite processor/checkout/payment amount/reprocessing. Non-scope: new behavior. Risk Low; migration no.
6. **PR — Add configurator published revisions and snapshot schema identifiers.** CFG-006/011/013. Scope: immutable revision/hash/canonical contract. Non-scope: full rollback UI. Risk Medium; migration yes; tests history/hash/concurrency.
7. **PR — Add aggregate publish preflight.** CFG-014/008. Scope: typed validator and admin diagnostics. Non-scope: wholesale Resource conversion. Risk Medium; migration no; tests invalid aggregate matrix.
8. **PR — Implement configured refund and credit memo lines.** CFG-004. Scope: full/partial configured and mixed allocation/UI/documents/payment. Non-scope: fake units. Risk High; migration likely; integration/plugin tests.
9. **PR — Decide and implement promotion/B2B policies.** CFG-009/010. Prefer two independent PRs after written decision. Risk High; migration depends on rule dimensions; matrix tests.
10. **PR — Localize configurator definition and UI.** CFG-016. Scope: nested translations/errors/fallback/snapshots. Risk Medium; migration yes; locale/template tests.
11. **PR — Add structured diagnostics and audit history.** CFG-018. Scope: non-PII logs and revision-linked actor changes. Risk Low–Medium; migration likely for audit trail; tests event payload/redaction.
12. **PR — Reduce upgrade-sensitive overrides opportunistically.** CFG-017. Scope one template per PR when supported hook exists. Risk Medium; migration no; visual/template tests.

## 32. Architecture Scores

| Dimension | Score | Evidence-based rationale |
|---|---:|---|
| Symfony conformance | 6/10 | Strong DI/routes/services; admin fat controller and ignored CSRF results are material. |
| Sylius conformance | 5/10 | Good processor/hook/order extension, but core tax/shipping/refund bridges absent. |
| Domain design | 7/10 | Clear aggregate/enums/services and justified custom line; lifecycle/revision gaps. |
| Pricing robustness | 7/10 | Server authoritative, integer, deterministic; bounds/decimal/revision gaps. |
| Security | 5/10 | Strong shop tamper/IDOR controls; admin CSRF and DoS controls need work. |
| Data integrity | 6/10 | Good DB constraints/snapshots; no revision/schema/size/publish validation. |
| Checkout integration | 6/10 | Configured-only visual/state gating exists; native fulfilment/tax not complete. |
| Tax integration | 1/10 | Category snapshot only; no configured taxation pipeline. |
| Shipping integration | 2/10 | Shipping intent only; no configured shipment subject/rating attributes. |
| Payment integration | 8/10 | Correct provider-neutral native total boundary, dependent on upstream totals. |
| Post-order integration | 5/10 | Email/admin visibility good; refund/docs/fulfilment incomplete. |
| Maintainability | 6/10 | Focused domain services, but large admin controller/overrides/manual translations. |
| Test coverage | 5/10 | Useful unit/template suite, little real lifecycle/browser coverage. |
| Upgradeability | 6/10 | Hooks and public processor tag good; several full overrides/plugin gaps. |
| Go-live readiness | 3/10 | Tax/shipping/refund and security/input blockers prevent broad launch. |

## 33. Commands / Test Results

All commands were read-only/non-destructive. No migration was executed.

| Command | Result |
|---|---|
| `php -r '...read composer.lock...'` | PASS: Sylius v2.2.8, Symfony FrameworkBundle v7.4.16. |
| `rg -l -i 'Configurator|ConfiguredOrderItem|...' src templates assets config migrations tests translations` | PASS: architecture inventory produced. |
| `vendor/bin/phpunit tests/Configurator` | NOT RUN: `vendor/bin/phpunit` absent. |
| `vendor/bin/phpunit tests/Checkout` | NOT RUN: same environment limitation. |
| `vendor/bin/phpunit tests/Order` | NOT RUN: same environment limitation. |
| `vendor/bin/phpunit tests/EventListener` | NOT RUN: same environment limitation (directory also not present). |
| `vendor/bin/phpunit tests/Template` | NOT RUN: same environment limitation. |
| `php bin/console lint:container` | NOT RUN: checked-out vendor lacks `symfony/runtime`; console fails before boot. |
| `php bin/console lint:twig templates` | NOT RUN: same limitation. |
| `php bin/console lint:yaml config` | NOT RUN: same limitation. |
| `php bin/console doctrine:schema:validate` | NOT RUN: same limitation; no destructive Doctrine command used. |
| `vendor/bin/ecs check` | NOT RUN: executable absent. |
| `vendor/bin/phpstan analyse --no-progress` | NOT RUN: executable absent. Existing repo-wide PHPStan debt is documented by project context but could not be re-measured; no unverified error is attributed to the configurator. |

## 34. Open Questions

1. Are configurator rule amounts defined net or gross per channel, and may tax-included channels differ?
2. Which countries/zones/customer VAT states must be supported at first launch?
3. Must configured goods always ship, and what physical attributes (weight, dimensions, shipping category, split capability) determine rates?
4. Are configured lines discountable by coupons/order promotions/item promotions, and do thresholds include them?
5. Do B2B price lists/customer groups apply to configurators, and at which rule dimension?
6. Are option/material stocks finite, or is made-to-order unlimited inventory an explicit policy?
7. What partial-refund granularity is contractual: quantity units, monetary partials, or production milestones?
8. Is multiple-choice order semantically meaningful, or should it be canonicalized as a set?
9. What maximum quantity, line total, payload size and calculation rate are commercially valid?
10. Must old configurations be reorderable exactly at old definitions/prices, or merely displayable?
11. Which admin roles beyond the broad default admin may edit prices versus content/publish?
12. Which documents (invoice, delivery note, production sheet) must enumerate configured selections/tax/refunds at launch?
