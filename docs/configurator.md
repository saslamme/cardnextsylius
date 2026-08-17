# Cardnext Configurator Core

## Scope and architecture

The configurator is a generic domain beside Sylius' variant model. A `Configurator` may reference one Sylius product, but sections, fields, values and rules describe a configuration without generating combinatorial variants. The core contains no product-code, plastic-card or lanyard branches and has no admin, storefront, cart, order or upload integration.

The aggregate is ordered deterministically: configurator → `ConfiguratorSection` → `ConfiguratorField` → `ConfiguratorValue`, section codes being unique per configurator, field codes being globally unique within one configurator, and value codes being unique within their field. `ConfiguratorLeadTime` prepares named production promises without embedding pricing. `ConfiguratorDependency` stores generic source predicates and target effects.

Field types are `single_choice`, `multiple_choice`, `boolean`, `integer`, `decimal`, `text`, `quantity` and `upload`. Upload is only a model type in this PR.

## Pricing model

A `ConfiguratorPriceRule` belongs to one configurator and optionally one value. A null value is a base rule; a value rule is an option charge. Every rule owns its inclusive `minimumQuantity` and nullable `maximumQuantity`, so every value can have arbitrary, independent tiers. `chargeCode` separates simultaneous charges such as production and setup.

`UNIT` and `FIXED` amounts are integer minor currency units. `PERCENT` amounts are integer basis points (20.00% = 2000). Floats are never used for price calculation. Percentage multiplication uses integer half-up rounding. Currency is mandatory and no EUR default is assumed.

For each qualified configurator/section/field/value source plus charge/type/multiplier/percentage-base dimension, a matching channel rule replaces (rather than adds to) its global channel-null rule. Currency dimensions are isolated. Disabled/out-of-range rules are ignored. More than one remaining rule is an ambiguity; a missing base `UNIT` tier is an error. Overlap validation groups on source, channel, currency, charge, price type, multiplier field/type and percentage base. Different charge codes may overlap.

Multiplier semantics:

* `NONE`: `UNIT` implicitly multiplies by order quantity; other types multiply by one.
* `QUANTITY`: multiply a `FIXED` charge by order quantity. It is rejected for `UNIT` and `PERCENT`.
* `FIELD_VALUE`: multiply by a selected non-negative integer from the referenced generic field. Thus a 3500-minor-unit setup with a field value of 3 is exactly 10500.

Percentage bases are `BASE` (base unit total), `OPTIONS` (option unit total), and `SUBTOTAL` (unit and fixed charges plus percentages already applied in deterministic priority order).

## Validation and dependencies

`ConfiguratorValidator` returns structured errors (`fieldCode`, `errorCode`, `message`, `metadata`). It checks configurator/section/field/value availability and ownership, required and choice cardinality, numeric bounds/steps, positive quantity, and generic dependencies. Operators include equality, set membership, comparisons and selected-state; effects include show/hide, enable/disable, require and forbid. REQUIRE and FORBID affect server-side validity at either field level or, when `targetValue` is set, only for that concrete single/multiple-choice value; presentation effects are available to future clients.

## Calculator flow and persistence

`ConfiguratorConfiguration` is JSON-serializable and carries configurator, quantity, channel, currency, typed selections and metadata. The calculator strictly verifies that its channel/currency arguments match the immutable configuration context, loads the complete dependency graph together with the configurator, validates it, resolves selected values, bulk-loads all relevant rules (including multiplier fields) in one repository query, resolves channel overrides in memory, computes unit/fixed charges, then percentage charges, and returns `ConfiguratorPriceResult`. The result includes base/option/unit/fixed/percentage totals and serializable breakdown lines with source, charge, rate/base, multiplier and amount.

`ConfigurationHashGenerator` recursively sorts object/map keys before SHA-256 hashing. List order remains meaningful. This allows a later order integration to store immutable configuration and price-breakdown JSON plus the configurator code and stable hash.

## Modelling examples (data, not core logic)

### Printed plastic cards

Create fields such as material, thickness, card color, front/back printing, magnetic stripe/type, signature panel, personalization, numbering and quantity. Store base `UNIT` rules at 100–249 = 89, 250–499 = 59, 500–999 = 42 and 1000+ = 31 minor units. The HiCo value independently owns 100–249 = 22, 250–499 = 16, 500–999 = 12 and 1000+ = 9. Numbering can own 100–499 = 14, 500–999 = 8 and 1000+ = 5, plus a `data_processing` FIXED rule of 2900. Dependencies can require magnetic type only when the enabling value is selected.

### Printed lanyards

Create fields such as print method, width, sidedness, color count, attachment, safety/accessory choices, design count and quantity. A trigger-clip value can own 100–249 = 18, 250–999 = 12 and 1000+ = 7 minor-unit `UNIT` rules, while screen print independently owns 100–499 = 25, 500–999 = 17 and 1000+ = 11. A 3500 FIXED setup rule with `FIELD_VALUE` referencing the generic design-count field yields 10500 for a selection of three. Dependencies express unavailable combinations without any lanyard-specific PHP.
## Release-candidate invariants

Money is represented exclusively as integers: UNIT and FIXED amounts are
currency minor units, while PERCENT amounts are basis points. Percentage
results use deterministic half-away-from-zero integer rounding. PERCENT rules
default to `SUBTOTAL`; only PERCENT rules may have a percentage base.

The allowed multiplier matrix is UNIT (`NONE`, `FIELD_VALUE`), FIXED (`NONE`,
`QUANTITY`, `FIELD_VALUE`) and PERCENT (`NONE`, `FIELD_VALUE`). A FIELD_VALUE
multiplier is a non-negative integer. UNIT already applies order quantity and
PERCENT already applies a monetary order base, so neither accepts QUANTITY.

Aggregate children have immutable technical codes and cannot be silently
reparented. Section codes and lead-time codes are unique per configurator,
field codes are globally unique per configurator, and value codes are unique
per field. Invalid or unattached relations fail with controlled domain errors.

Configurator loading intentionally uses two bounded queries: one fetches the
section/field/value graph and one fetches dependencies with their source and
target associations. Applicable price rules are fetched in one query using
association identities, preventing both N+1 loading and a dependency/value
cartesian product.
