# C137 GC24 Z27F — proposed canonical fixture V2 (design only)

**Status: PROPOSED_BLOCKED.** This is an independent, non-executable
specification. No V2 events, payloads, SQL, seeders, writes or imports exist.
Passing this offline design check must never be described as data import,
canonical PHP execution, security approval or representative-data load QA.

## Frozen authorities and lineage

- Certified Reports source: `c137-gc-reports-prefetch-weakmap`,
  `322438870263825f9e739a12c4bd478983e67141`; never changed.
- Preparation branch at start: `c137-gc24-fixture-dryrun-z23`,
  `41eb9b98ce53d1471af018ed9064555563b565bb`.
- Original Z23/Z26 scenario R1: 5,452 events,
  `e31421ee40ce25b16feb698313fcf337e74126a8e63d8c5dadeedf74e30165f2`.
- Original Z23/Z26 scenario R2: 13,770 events,
  `985341ed4315788ee8f3b9d62b327da66d38fc63ea16d406c5c52bc9949d3dfe`.
- Candidate namespaces `R1-V2-DESIGN`, `R2-V2-DESIGN`: **specification
  digests only**, computed from policy and classified legacy facts. No V2 event
  digest has been generated. New event digests require new, separately approved
  event streams. Do not modify/rebaseline the original digest.

## Canonical source contract and proposed fixes

| Subject | Source-derived rule | Proposed V2 design; not approved or implemented |
| --- | --- | --- |
| Cycle opening | `lib/production_cycle_service.php::production_cycle_create_v3` creates active cycles and baseline; Daily Record seeding belongs to caller | Preserve four symbols for lineage, but select valid codes, start/end, opening population, unit costs and lifecycle. Two broiler cycles continuously selling for 12 months may be unrealistic. |
| Attribution | `lib/attribution.php::attribution_scope`: cycle => cycle; concrete production without cycle => production_type; genuinely shared production => farm | Derive every scope from canonical function, never copy original `farm` labels for concrete layer/broiler. |
| Layer egg sales | `management/sales_records.php` rebuilds automatic allocations from unsold Layer Daily Records; `lib/sale_revenue_allocation_service.php` forbids manual Layer egg shared allocations | Add causally sufficient, date-aware Layer daily egg-production/sales evidence *through existing canonical orchestration*; retire original manual 60/40 egg child rows in V2. The automatic allocation result and child count are **unknown**, not 60/40. |
| Broiler sales | Sales route invokes `sale_population_effect_sync` and other domain side effects | Preserve candidate broiler price/quantity only after choosing source/population exit mode, validating sufficient stock and transaction provenance. Manual 60/40 revenue for non-egg shared broiler sales remains **a candidate**, subject to target validation. |
| Sales units/payment | `lib/sales_units.php` permits `Crate` and `Head`; sales POST enforces payment <= total and customer for outstanding balance | Candidate units: egg Crate, broiler Head. Candidate payment fully received (no fabricated debtor). Source authority/permission and standalone sale writer extraction remain open. |
| Poultry expense | `lib/poultry_expense_entry.php::poultry_expense_entry_create` and canonical expense catalogue; historical feed/medicine are not normal manual expenses | Candidate non-stock `labour` category (catalogue manual=true); canonical writer handles references/revision. Parent scope is production_type when concrete and cycle NULL; expense 60/40 rows remain candidates. |
| Inventory categories | `lib/inventory_financial.php` and `lib/inventory_category_role.php`: separate financial classification, inventory role, farm type and feed usage | Candidate Feed: operational/feed/poultry/layer. Candidate Operating: operational/consumables/poultry/general, default broiler. The literal `operating` classification is invalid; do not reinterpret existing ledgers. |
| Stock costs | `lib/stock_service.php::stock_apply_movement` requires transaction; `lib/stock_costing.php` replays historic receipt cost; normal outgoing cost override not allowed | Candidate receipt unit costs feed ₦250 and consumables ₦40; stock USED cost *must be derived* from posted, non-reversed priced receipts and verified (not injected). Confirm exact cost equivalence under historical replay before using Reports counts. |
| Stock source | `lib/stock_consumption_source_resolver.php` accepts `inventory_manual` movement-authoritative; rejects unsupported/reversal for shared allocation | Candidate `inventory_manual`, verify attribution, source ID and revision support. Manual stock allocation uses canonical persistence, parent with no cycle and correct pool scope. |
| Allocation money | Canonical persistence services accept `desiredRows` with `cycle_id` and authoritative `allocated_amount` | Derive amounts, do not write direct allocation tables; 60/40 describes planned synthetic input, **not** authoritative business allocation for Layer eggs. |
| Reporting dates | 2026 full calendar year extends beyond pinned 2026-10-11, and Inventory POST rejects future receipts | Gate must choose **either** a newly versioned completed historical reporting year **or** defer 2026 full-year insertion until after year-end; neither option is silently selected. |
| Reports cost coverage | Prefetch cap 2,048 eligible USED rows | R1 1,800 vs R2 3,600 are legacy candidate counts only; a future posted, costed eligibility audit must prove the actual in-limit/overflow paths. |

Sources cited above refer to the certified GitHub commit, not assumptions about
runtime MariaDB behavior. The dates, category labels and receipt prices in the
right-hand column are *proposals* derived from Z27C/Z27E, not executed evidence.

## Deterministic event classification (no event rewrite)

`scripts/c137_gc24_fixture_v2_design.py` reads the original in-memory
Z23 events, passes the Z26 original digest/target/financial/stock validation,
then classifies sales into direct/shared Layer eggs and direct/shared broilers.
It identifies original manual Layer egg allocation child rows that a future V2
**must not replay**. Existing manual broiler, expense and stock allocations
are candidates only. It counts invalid `operating` use and future receipts.

The output contains aggregate buckets plus a distinct **design/spec digest**;
it never emits a V2 event list or an executable writer payload. A new V2 event
stream would require new reference identifiers, separate hashes and focused
verification of all 28 Z27E blocker classes, plus any newly discovered blocker.

The verifier statically guards the generator's no-IO code, asserts original
digests/stream immutability, allocation classification completeness and
negative paths (tampering, other tenant, protected DB, time authority and
`--execute` flags). Its success cannot approve import.

## Unresolved gates and next controlled checkpoint

1. Choose permissible report year / date authority; never backdate or
   future-date records just to force a benchmark pass.
2. Resolve cycle population, broiler turnover and operational Daily Record
   egg-production quantity and order; define how original 4 cycles relate to
   a representative farm over a full year.
3. Decide which source effects are actually modeled; preserve egg automatic
   revenue rules and livestock population, not manually invented allocation.
4. Design category/item/sale writer extraction into shared services, keeping
   thin pages and no duplicate SQL/authorization/financial policy.
5. Resolve tenant-specific actor IDs, roles, transaction boundaries, source
   references, permissions and audit/revision chains.
6. Build separately versioned candidate V2 events **only after** the above
   business decisions; obtain independently reviewed new event digests.
7. No insertion/cleanup/restore/drop/load test/production/GA changes without
   new explicit authorization; 150 VU HOLD, 200 VU PROHIBITED; protected
   `renee_c137_fixture` and scratch `renee_c137_gc24_restore_z25` unchanged.

Safe read-only preview from prepared checkout:

```sh
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/verify_c137_gc24_fixture_v2_design.py
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/c137_gc24_fixture_v2_design.py --dry-run --scenario R1 --database renee_c137_gc24_http --farm-slug c137-gc24-synthetic --year 2026 --as-of 2026-10-11
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/c137_gc24_fixture_v2_design.py --dry-run --scenario R2 --database renee_c137_gc24_http --farm-slug c137-gc24-synthetic --year 2026 --as-of 2026-10-11
```

**Result expectation:** `GC24CG_Z27F_OFFLINE_VERIFIER=PASS` and each
design `READINESS=PROPOSED_BLOCKED`; genuine V2 event digests remain
`NOT_GENERATED`. Node A verifier output must be captured before closing Z27F.
