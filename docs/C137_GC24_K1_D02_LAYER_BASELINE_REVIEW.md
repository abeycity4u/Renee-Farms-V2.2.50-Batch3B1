# C137 GC24 K1/D02 — Layer flock baseline and acquisition requirements

**Status: requirements captured; D02 NOT APPROVED.** This checkpoint is a
source-grounded, non-executable requirements specification for a new,
separately versioned V2 synthetic fixture covering the owner-selected
**2025 calendar reporting year**. No flock/headcount/age/price/date/lay
rate is selected, no cycle/event produced and no source or DB mutated.

## Immutable authority and selection

- Certified Reports source: branch `c137-gc-reports-prefetch-weakmap`,
  HEAD `322438870263825f9e739a12c4bd478983e67141`; read-only.
- Preparation branch previous HEAD:
  `cc186fa7799c048236a76c8dd58fcbd75c472e91`.
- D01 append-only year-selection Git blob:
  `db5ded90c63f35a59861e4e8a58ed07a0628b0db`.
- D01 decision-spec digests: R1
  `abf6ab785d80eef17af38cc36b036737b2ea08024b550a1202773964ca49dff8`;
  R2 `53b84e596c910473471b21926e634cb13d52e45bfe45c515b21e50dc0ff12091`.
- Original *2026* event digests unchanged: R1
  `e31421ee40ce25b16feb698313fcf337e74126a8e63d8c5dadeedf74e30165f2`;
  R2 `985341ed4315788ee8f3b9d62b327da66d38fc63ea16d406c5c52bc9949d3dfe`.

The two historic symbolic layer labels `layer-a` and `layer-b` are
not approved physical V2 cycles and must not determine a 2025 flock count.

## Canonical source findings — read-only review

1. `lib/production_cycle_service.php::production_cycle_create_v3` validates
   code/farm/type/date, accepts nonnegative `opening_headcount`, creates
   an **active** cycle and establishes a `cycle_opening` population
   baseline with that starting headcount/date. Do not directly insert a
   `planned` cycle from the old generator.
2. `lib/production_population.php::production_population_establish_baseline`
   requires the `cycle_opening` baseline to use exactly the cycle
   `start_date` and `opening_headcount`. Historical physical movement
   projections must remain nonnegative *at and after* a dated exit.
3. `lib/poultry_cycle_onboarding.php::poultry_cycle_onboarding_record_initial`
   records two separate facts: acquisition and initial biological phase.
   It requires `quantity > 0`, `age_days >= 1`, allowed acquisition type
   and phase, and `purchased_point_of_lay` must start in `production`.
4. `lib/poultry_cycle_acquisition.php` permits Layer `purchased`,
   `purchased_point_of_lay`, and `internal_transfer`. Purchased types
   require actual non-null acquisition `total_cost`; acquisition date may
   not precede cycle start or follow close date/first phase start.
   Request-token idempotency and historical correction provenance exist.
5. `lib/poultry_cycle_lifecycle.php` allows Layer `rearing` and
   `production`, with an explicit `rearing -> production` transition.
   Transition date must be after phase start, and the previous phase closes
   the day before; no artificial rearing phase for purchased POL birds.
6. **Important accounting distinction:** `poultry_acquisition_record`
   writes the durable acquisition fact; cycle creation separately establishes
   the population opening baseline. An acquisition record is *not itself*
   a new baseline or automatically a second credited population movement.
   The source does not, merely by recording that acquisition, prove the
   opening flock equals acquired quantity; reconcile those values from
   authoritative facts before any proposed fixture execution.
7. `lib/daily_population_continuity.php::daily_population_continuity_expected_opening`
   uses canonical dated population snapshots. The Layer form requires an
   active matching cycle in valid start/end dates, positive opening and age,
   `mortality <= opening_stock`, `egg_production <= opening_stock`,
   and stores `round(egg_production / 30, 2)` crates.
8. `lib/poultry_production_entry_snapshots.php` derives production-entry
   economic basis from canonical source provenance. An approved snapshot
   and any rearing feed/cost attribution require separate review. Do not
   fabricate rearing cost, mortality history or approval.

## Evidence needed before D02 can be marked approved

For **each proposed V2 Layer cycle**, obtain independently justified
cycle code, actual start date and expected end/close date, opening
headcount, acquisition type/date/quantity, age in days, real acquisition
cost for purchased birds, initial phase, any transition date, source and
reference provenance, and an explanation of opening/acquisition population
reconciliation. Review bird-unit cost vs total acquisition cost without
double-counting financial investment or physical headcount.

The reporting window covers 2025. **It does not require cycle acquisition
to start on January 1, 2025.** For a real Layer flock laying eggs on
January 1, pre-year onboarding or carry-forward might be necessary.
Whether independent synthetic prerequisite history outside 2025 is allowed
requires a **separate date-window decision and canonical-source review**.
No 2024 records or dates are authorized or generated here.

The D03 dated Layer Daily Record production and egg pool remains separate.
R1/R2 legacy crate-sale totals are financial workload hints, not
evidence of flock size, production, or a valid assumed laying rate.

## Source verification and safety

`docs/C137_GC24_K1_D02_LAYER_BASELINE_REQUIREMENTS.json` contains
all input placeholders set to `null`, canonical source summaries and
explicit design checks. `scripts/verify_c137_gc24_k1_d02_layer_baseline.py`
performs read-only anchored-source probes, pinned D01 Git-blob lineage,
unknown-value policy validation and targeted in-memory tamper tests.
It does **not** execute PHP or verify a biological fixture.

After guarding Node A and both clean worktrees:

```sh
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/verify_c137_gc24_k1_d02_layer_baseline.py --dry-run --database renee_c137_gc24_http --farm-slug c137-gc24-synthetic --legacy-year 2026 --as-of 2026-10-11 --reporting-year 2025 --certified-source-root /opt/renee-agrisuite-c137-gc
```

Expected `GC24CG_K1_D02_OFFLINE_VERIFIER=PASS`, unknowns remain
`null`, `K1_D02_APPROVAL=NOT_GRANTED`,
`DATABASE_WRITES=0`, `V2_EVENTS_GENERATED=NO`.
Only Node A verification can close this **review checkpoint**; it does
not close D02 as an approved flock model.

150 VU **HOLD**; 200 VU **PROHIBITED**; no DB access, import, deploy,
certified PHP changes, source migrations or protected-fixture cleanup.
