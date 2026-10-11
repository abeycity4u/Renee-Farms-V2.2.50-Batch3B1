# C137 GC24 Z27I — Canonical dependency architecture (offline)

**Status: DEPENDENCY_ARCHITECTURE_BLOCKED**; this is design evidence, not a
fixture implementation. Reported dependency order is only a conceptual
precondition DAG, **not a standalone executable workflow**, database
transaction recipe or permission to write. No new event stream was emitted.

## Authorities and frozen lineage

- Immutable certified Reports source:
  `c137-gc-reports-prefetch-weakmap`,
  `322438870263825f9e739a12c4bd478983e67141`.
- Preparation parent HEAD: `40c7b0d641c191e3c1cee58c04304228e858d69b`.
- Original Z23 R1 and R2: 2026, 5,452 / 13,770 events; SHA-256 digests
  `e31421ee40ce25b16feb698313fcf337e74126a8e63d8c5dadeedf74e30165f2`
  and `985341ed4315788ee8f3b9d62b327da66d38fc63ea16d406c5c52bc9949d3dfe`.
- Z27H feasibility digests:
  R1 `baad0d0fcfe63168abba7d44945142ced7dc0b3bee16b732add8b7f738b71b7d`,
  R2 `f156d00323a391233759bd74ad2e5a075916f362206faf82b9f98cd852980cb0`.
- Z27I digests are new hashes of a **dependency specification**, never hashes
  of new events. Z27F/Z27H scenarios stay byte-for-byte identical.

## Date authority: decision, not an implicit conversion

As-of 2026-10-11 the original 2026 scenario still has R1 1,180 and R2
2,994 future-dated events. An independent **2025 completed-year candidate**
is a possible design, but `historical_year_selected=False`. The alternative
is to defer 2026 full-year ingestion until completion and separate approval.
Nothing in this design silently moves sale or stock dates to 2025, licenses
backdating, or replaces the certified 2026 stream. An eventual V2 stream must
have a separately reviewed date policy and its own event identifiers/hashes.

## Canonical production graph and transactional caveats

`scripts/c137_gc24_v2_dependency_contract.py` defines a read-only,
deterministically topologically sorted dependency DAG:

1. **Calendar + tenant/actor authority:** explicit year, isolated tenant ID,
   authorized roles and canonical transaction owner; both open gates.
2. **Cycle opening + poultry onboarding:** invoke
   `production_cycle_create_v3` for active baseline within correct transaction,
   then `poultry_cycle_onboarding_record_initial` for acquisition and explicit
   layer/broiler biological phase. Actual opening headcount, ages, cohort
   schedule, purchase values and close dates remain unresolved.
3. **Inventory category/item + priced receipts:** extract reusable shared
   category/item writer from the existing page flow, respecting financial type,
   inventory role and feed usage; add a genuinely separate broiler feed
   specialization where justified. `stock_apply_movement` owns posted cost.
4. **Layer Daily Records and pooled egg inventory:** use the existing Daily
   Record orchestration for canonical opening population, age, egg count and
   feed, respecting `egg_production <= opening_stock`. The derived unsold
   pool must exist *before* processing a sale. The sale must use canonical
   egg sale units and `sales_auto_allocate_layer_egg`, **never synthetic
   manual 60/40 allocations**.
5. **Broiler Daily Records and sales with explicit population effects:**
   distinguish financial sale identity from physical exit. Sales writer must
   obtain sale ID before `sale_population_effect_sync`; effect must be in
   the **same enclosing financial sale transaction** where supported by
   existing route orchestration. Validate farm, production type, cycle, sale
   date, positive headcount and nonnegative future population projection.
   No direct sale route may invent a population exit from product description.
6. **Expenses and shared manual allocations:** canonical poultry expense
   entry and relevant shared expense/revenue/used-stock allocation persistence
   services handle allocation money, revision, references and permissions.
   Broiler shared revenue remains a candidate only after source validity.
   Layer egg revenue is automatically allocated from dated unsold egg stock.
7. **Stock consumption:** choose each fact's single canonical owner.
   `sync_daily_feed_usage` already invokes the stock ledger for Daily Record
   feed consumption; no duplicate `inventory_manual` used movement for
   the same feed. Independently documented manual consumables can legitimately
   use `stock_apply_movement`. Every monetary cost is derived from priced,
   non-reversed receipt history; normal outbound price overrides prohibited.
8. **Reports eligibility and authorization:** only actually posted, costed,
   eligible stock USED rows qualify for the 2,048-row prefetch boundary.
   Legacy 1,800/3,600 used-stock counts are *workload candidates*, not
   proven eligible rows. No import authorization exists.

**DAG edges are logical dependencies, not independent database commits.**
`sales_auto_allocate_layer_egg`, `sale_population_effect_sync`, sale
reference persistence, and ledger sync have existing transaction/orchestration
contracts; future engineering must trace them fully and preserve atomic
grouping. The graph must not be naively executed node-by-node.

## Risk and next controlled engineering step

- Existing Layer 16,419/49,269 crate sales are **sale equivalents**, not
  physically measured egg production. Neither opening bird counts nor
  daily laying rate are known; no synthetic production should be invented
  merely to pass a financial assertion.
- Original Broiler 5,469/16,422 Head sales are **commercial quantities**, not
  established physical exits. No broiler feed is present and direct sales
  route exclusively to `broiler-a`; a representative V2 requires new cohorts,
  explicit sale-effect sources, acquisition/harvest phases and a priced feed
  ledger. The new topology/volume must be independently versioned.
- **Next candidate Z27J:** source-level contract extraction *design review*
  for category/item/sales shared writers plus a **decision dossier** for
  the reporting year and Layer/Broiler biological assumptions. This is not
  an instruction to rewrite those routes or seed rows.
- Before any new V2 fixture events: confirm explicit reporting period,
  independently justified biological input assumptions, all shared-domain
  writer boundaries, permission and transaction semantics, and a separate
  source-model/digest review. Never change the legacy digests.

## Safety rules and exact offline verification

Source root remains untouched, as do the protected isolated databases.
No GitHub certified branch change, database connection, SQL, insertion,
restore/drop/cleanup, migration, credential reading, PHP invocation or load
test. 150 VU **HOLD**. 200 VU **PROHIBITED**.

```sh
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/verify_c137_gc24_v2_dependency_contract.py
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/c137_gc24_v2_dependency_contract.py --dry-run --scenario R1 --database renee_c137_gc24_http --farm-slug c137-gc24-synthetic --year 2026 --as-of 2026-10-11 --candidate-year 2025
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/c137_gc24_v2_dependency_contract.py --dry-run --scenario R2 --database renee_c137_gc24_http --farm-slug c137-gc24-synthetic --year 2026 --as-of 2026-10-11 --candidate-year 2025
```

Required output from verifier:
`GC24CG_Z27I_OFFLINE_VERIFIER=PASS`.
Required scenario output:
`READINESS=DEPENDENCY_ARCHITECTURE_BLOCKED`,
`NEW_EVENT_DIGEST_SHA256=NOT_GENERATED`,
`DATABASE_CONNECTIONS=0`, `DATABASE_WRITES=0`.
Z27I is not closed until Node A confirms these safeguards.
