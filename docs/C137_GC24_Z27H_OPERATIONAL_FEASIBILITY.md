# C137 GC24 Z27H — Physical and reporting feasibility, OFFLINE ONLY

## Guarded scope

**Design only**. This checkpoint only reads the certified original Z23
R1/R2 streams and the Z27F spec. It does not choose a reporting year, rewrite
dates, generate V2 events, construct import payloads, invoke PHP, or connect to
MariaDB. The two original 2026 stream digests and both Z27F proposal digests
must remain unchanged. 150 VU stays HOLD; 200 VU stays PROHIBITED.

As-of authority **2026-10-11**. Historical full-year **2025** is an
*unselected comparison candidate*, not a new authorized database or a renamed
2026 event. Another permitted design option is to defer 2026 complete-year
testing until it has ended. Re-dating the legacy stream would create an
entirely new business event model with new references and SHA-256 digests.

## Canonical source findings at immutable source SHA

Source `322438870263825f9e739a12c4bd478983e67141`:

1. `lib/production_cycle_service.php::production_cycle_create_v3` creates
   an active cycle and its population opening baseline. Four symbolic
   `planned` cycles from Z23 are not valid direct service output.
2. `lib/poultry_cycle_onboarding.php` separately records acquisition and
   explicit biological phase. `lib/poultry_cycle_lifecycle.php` recognizes
   Layer rearing/production and Broiler growing/harvest. Phases are *not*
   inferred from sale type or bird age. Cycle dates/closures must be respected.
3. `poultry/layers_daily_record.php` validates `egg_production <=
   opening_stock`, bird age and chronological population continuity, then
   triggers `sales_rebuild_layer_egg_allocations` in its transaction.
4. `lib/layer_egg_inventory.php` uses Daily Record crate counts or
   egg production / 30, with prior egg sales deducted in date/id order.
   `lib/sales_allocation.php::sales_auto_allocate_layer_egg` allocates only
   from eligible unsold egg stock, proportional to availability—not a chosen
   60/40 split. For a sale on a date, records for that date are included; same
   business date ordering and transaction orchestration matter.
5. `lib/sale_population_effects.php` never interprets a broiler sale product
   as a physical exit by itself. The source rows explicitly name cycle and
   positive headcount; `lib/production_population.php` rejects negative
   balances and movements outside a cycle's effective dates.
6. `poultry/broiler_daily_record.php` requires positive opening stock/age,
   valid active-cycle date, and canonical continuity.
7. `lib/daily_feed_sync.php` creates a stock-used ledger entry for a
   saved Daily Record's selected feed and quantity. Using another manual
   stock-used event for that same real consumption would double-count feed.
   Two possible fixture strategies are entirely Daily Record-owned feeding
   or separate, independently justified manual consumption; choose *one*
   provenance for each consumption fact.
8. `lib/stock_service.php` owns stock mutations, transaction boundary and
   historical cost authority. The original fixture only has one proposed
   `layer` feed category and `consumables` for the broiler operation;
   it contains **no Broiler feed item/consumption**. A credible poultry model
   requires a separately justified broiler feed inventory route and
   costed receipt, not an assumed outgoing cost.

## Deterministic profile extracted without new records

The analyzer derives per-date original Layer egg-crate sales, Broiler head
sales, daily maxima, historical stock-use counts, future-event totals and
direct broiler cycle distribution from the intact legacy in-memory stream.
These are accounting quantities and workload signals, not an endorsed
animal husbandry plan. Egg sales on a day do not prove same-day production:
carry-over from earlier Daily Records can be valid, but must be derived
chronologically. Likewise financial Head sales do not prove flock exits
without explicit effect rows.

The legacy generator has broiler sales on **all 365 days** in each scenario.
Direct broiler sales all resolve to `broiler-a`; `broiler-b` gets none.
Two January-starting broiler cycles plus no recorded replenishment/turnover
cannot be presumed representative of staggered commercial cohorts. The V2
designer must specify biological age, entry/acquisition, phase transitions,
harvest sale windows, live headcount at every exit, and closure/replenishment
as appropriate. A realistic staggered calendar may change the number of
cycles and the sale-date distribution; this must be separately versioned.

The minimum egg counts `egg_crates_sold * 30` are **sale equivalents**, not
a recommended flock size or evidence of eggs actually produced. Production
losses, unsold opening stock, birds' ages, laying rate and opening headcount
are not supplied by R1/R2. **Do not invent an 80% lay rate or retroactively
create daily production to make a sale pass.**

## Prescribed next design dependencies — not execution steps

1. **Reporting window:** select a completed-year comparison (2025 candidate)
   or defer until year-end 2026. Preserve both certified original streams.
2. **Laying evidence:** design day-by-day positive flock/age and egg-production
   records, conservation, date ordering and authoritative egg sale UOM.
3. **Broiler biology:** design legitimate staggered acquisition/initial phases,
   sale dates, explicit physical exits and population conservation, with
   enough feed and water provenance to match the domain.
4. **Inventory:** define additional Broiler feed category/item, price all
   RECEIVED rows, and choose exactly one authoritative stock-use writer per
   consumption. Historical replay must reproduce cost; do not pass an
   ordinary USED outgoing-cost override.
5. **Finance/provenance:** canonical writers for sale/category/item are still
   not reusable; independent extraction must preserve sales effects,
   tenancy/roles, record references, transactions, cost and revision policy.
   Preserve automatic egg allocation and manual-authoritative amount semantics.
6. **Workload fidelity:** after a new event stream exists, independently
   verify actual *eligible posted USED cost rows* against the Reports cap
   2,048 before any representative-data capacity claim.
7. **Release gate:** import, migrations, database restore/drop/cleanup,
   staging/production changes, merges and new VU tests remain unauthorized.

## Offline files and operator commands

`scripts/c137_gc24_operational_feasibility.py` produces only a compact
summary and a new **feasibility specification digest**, never a V2 event
digest. It includes an explicit `candidate_year_selected=False` guard.

`scripts/verify_c137_gc24_operational_feasibility.py` statically guards
its source for I/O/unsafe calls; verifies original event + prior spec hashes;
checks daily sales coverage, aggregate headcounts/crates, absence of Broiler
feed, future-date exposure and negative target/tenant/execution guards.

```sh
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/verify_c137_gc24_operational_feasibility.py
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/c137_gc24_operational_feasibility.py --dry-run --scenario R1 --database renee_c137_gc24_http --farm-slug c137-gc24-synthetic --year 2026 --as-of 2026-10-11 --candidate-year 2025
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/c137_gc24_operational_feasibility.py --dry-run --scenario R2 --database renee_c137_gc24_http --farm-slug c137-gc24-synthetic --year 2026 --as-of 2026-10-11 --candidate-year 2025
```

Expect `GC24CG_Z27H_OFFLINE_VERIFIER=PASS` and
`READINESS=FEASIBILITY_DESIGN_BLOCKED`. This file reports source-derived
rules and reasoned design constraints; **Node A runtime results are pending**
until the separate guarded evidence check.
