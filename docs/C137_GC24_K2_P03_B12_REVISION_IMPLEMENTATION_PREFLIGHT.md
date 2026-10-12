# C137 GC24 K2-P03 — B12 guarded implementation preflight
**2026-10-12 | Status: READ-ONLY DEPENDENCY AUDIT COMMITTED; APPLICATION CORRECTION NOT YET IMPLEMENTED.**

## Guard / provenance

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Isolated branch: `c137-gc24-k2p03-b12-revision-implementation-preflight`; begins exactly at `ea351024dd63171356bfb7c091313b85600ef042`, the source-only shared-revision architecture design.
- Failed isolated original B12 concurrency GitHub Actions: run `38233373699`, only attempt 1, `conclusion=failure`, `B12_CONCURRENCY_OUTCOME=TWO_STALE_COMMITS`. The original B12 branch ended its failure report at `39ab4dd71a896fa73e4f4513efa0bd1ab72263db` and must remain intact. No test rerun is authorized.
- This is a step-wise implementation **preflight** — not a claim that any revision column, PHP service, browser JS, schema, migration, or test fix has been deployed or executed.
- Primary design: `docs/C137_GC24_K2_P03_B12_STALE_EDIT_REMEDIATION_DESIGN.md`; review its no-silent-overwrite, tenant and lock-order requirements before editing.
- D12: fifteen import gates BLOCKED, 150 VU HOLD, 200 VU PROHIBITED, Stage D NOT AUTHORIZED. No production/staging, cPanel, protected fixture, AWS, backup or load work.

## Confirmed current save/write surfaces (reviewed actual frozen source)

| File | Immutable blob | Observed save/delete behavior |
|---|---|---|
| `poultry/layers_daily_record.php` | `87b7c6ace9bd577ad2303ea4aa50312a780412d2` | Reads existing ID before transaction, unversioned target UPDATE, then continuity, feed, mortality, Layer egg-sale allocation in one transaction. |
| `poultry/broiler_daily_record.php` | `2a797c7fec45d3f78e7b2f01cb72531b1c416699` | Same pretransaction ID read and unversioned target UPDATE; then shared continuity, feed, mortality. |
| `ruminant/ruminant_daily_record.php` | `27c6755f0ef9491a7019f94b6a62b602e8e8dcaa` | Same pretransaction ID read/update, with `LOWER(animal_type)` per species, shared continuity/feed/mortality; tagged animal exit policy distinct. Separate delete route in this file uses feed reversal and mortality reversal before deleting. |
| `api/delete_record.php` | `4a3b8d03b565f65c4a103e9f1fae304d6ac75fda` | Layer/Broiler API delete; checks CSRF, method, rate limit and granular permissions; deletes feed usage and mortality projection before source deletion; Layer also rebuilds pooled egg-sale allocation. No presented row revision. |
| `lib/daily_population_continuity.php` | `9503771b47c6c2fe26bb3237da69a17874e724a3` | Updates later Daily Record `opening_stock`, and Layer `laying_rate`, under sorted later-row locks and old-opening compare; raises conflict on rowCount ≠1. Does not update a row revision today. |
| `lib/daily_feed_sync.php` | `752707749fea0583249ec600bb5d624d416b8a47` | Requires caller transaction and authoritative saved source lock; order Daily Record -> stock movement. |
| `lib/stock_consumption_allocation_persistence.php` | `c94daf5b66b7ec2051f0f6fb8bd6aedef48d6172` | External stock-consumption allocation locks authoritative Daily Record before stock transaction and then cycle/projection state. |
| `lib/production_population_projection.php` | `c4cdae4c193b624a7469915e0451d41fef1b2f4b` | Cycle locks in deterministic order and source projection locks before baseline locks. Must not naively invert shared lock graph. |
| `lib/production_population.php` | `fff1b3e663bb0845c34ccf734a52f6c92eaae706` | Direct population movement locks cycle before baseline; cross-path ordering remains an untested separate contention question. |
| `lib/sales_allocation.php` | `1a701fff059189b6a9ad3fe1a190188be958a96b` | Layer correction rebuilds automatic pooled sale allocation; must remain in the same transaction after safe source CAS. |
| `api/get_record.php` | `05c1ef422fc7df908cc484846e59e042fdb1d282` | Scoped authenticated read of `SELECT *` for each type. Does NOT currently issue an explicit saved revision token; request may omit cycle ID, which is ambiguous when multiple cycle/date records exist. |
| `api/check_record.php` | `28e76a364f0f786376f3757cbb529a7e518a69ec` | Returns only `exists` for Layer/Broiler, not immutable row ID or revision. |
| `api/check_ruminant_record.php` | `337a5f07fa5d0b901a62e8a43d8e5fae7f7ccfa6` | Same for Ruminant, scoped by species and optionally cycle. |
| `assets/js/layers-daily-record.js` | `cf4a24803935b61046f8856c4ee220ed71146fee` | Fetches a row, fills modal, uses shared async check/create/edit flow; no expected source id/revision captured. |
| `assets/js/broiler-daily-record.js` | `d0f860e78983a96557ebb449c286d53593eb6692` | Same. |
| `assets/js/ruminant-daily-record.js` | `e9d46ca50972ce39068bd1a40174ea6665427e65` | Same plus species selector/hidden animal type; async response scope must be rechecked to avoid stale modal fill. |
| `lib/daily_record_workspace_scope.php` | `24c84d78a46e71e362ed94428ec7eb77c6697fee` | Read-model "All Active Cycles / Legacy Records" can include nullable-cycle records; selection cannot assume one cycle. |

**Static scan coverage limitation:** named save/delete pages, read APIs, browser forms, continuity, feed, stock allocation and canonical population were inspected. GitHub code-search indexing returned zero matches for known-present symbols, so it **did not** provide exhaustive proof that no other Daily Record table writers exist. Before product patch, obtain a complete scoped source text scan of PHP/SQL files or independently verify every direct `INSERT`/`UPDATE`/`DELETE` Daily Record reference. Do not claim this table enumerates every writer in the repository.

## Schema/migration facts (critical correctness gate)

- `database_schema.sql` frozen blob `e77f287d62c5812a3d55dc23b5b20f5d167db389`, 69-table B12 source schema. `layer_daily_records`, `broiler_daily_records` and `ruminant_daily_records` have **no** source `record_revision` column today.
- Layer/Broiler unique constraints: `(cycle_id, record_date)`. Ruminant: `(record_date, animal_type, cycle_id)`.
- All three tables allow `cycle_id NULL`. MariaDB UNIQUE indexes permit multiple NULL-key rows, so no current unique index closes duplicate **legacy** inserts for the same farm/type/date. Ruminant uses `LOWER(animal_type)` in the route but its unique index is based on stored `animal_type`; collation semantics need explicit scrutiny.
- Migration directory currently includes files through **`091_permission_architecture_alignment.sql`**; `092` is merely the next candidate name, **not** an assigned or written migration.
- `scripts/run_migrations.php` blob `ce46170e0a6d8d548d4629e4dc2fb72211a42f02`: scans sorted migration files, records `schema_migrations`, splits semicolon-delimited SQL and ignores MySQL driver codes 1060/1061/1050/1091. DDL can auto-commit in MariaDB despite caller `beginTransaction`. Design must fail closed on partial application and verify schema postconditions before marking migration.
- Existing `085_account_credential_lifecycle.sql` and `090_general_expense_category_storage.sql` show in-place ALTER migration patterns, but neither establishes a fully reversible multi-table DDL transaction. Avoid promising atomic three-table rollout.
- Do not introduce `record_revision` as a substitute for population movement source versions or stock allocation provenance revisions. They protect different entities.

## Required decisions before code (safety / correctness)

1. **Full writer audit:** identify every direct write to each of three Daily Record tables, including imported or maintenance paths, deletes and system-owned later opening updates. A shared revision guard is incomplete if an unversioned writer can silently change rows.
2. **Legacy create-key resolution:** settle concurrent `cycle_id IS NULL` inserts and duplicate historical rows BEFORE claiming end-to-end race-safe create. Options must preserve existing data; do not add unique keys without a guarded duplicate/colllation inventory and explicit migration authorization. A create intent must never become a silent edit.
3. **Migration and deployment compatibility:** reviewed candidate monotonic unsigned revision column, preflight existing data/SQL modes, phased migration/write/read rollout, old clients without revision fail safely with preserved form inputs, rollback for partial DDL. No production migration or schema change in this checkpoint.
4. **Editor intent:** both `expected_record_id` and `expected_record_revision` from saved GET, not from a fresh server read at submit; reset on modal/date/cycle/species change and ignore stale asynchronous responses. Save rejects zero/missing/stale expected version if row exists.
5. **CAS in one transaction:** authoritative scope by current tenant farm + immutable record ID + cycle/date/species + revision; row lock source first, conditional version increment with exactly one affected row, then original continuity, feed, mortality, automatic Layer sales. Entire rollback on downstream exception.
6. **System-derived changes:** later records whose opening changes must also bump revision exactly once; a user editing such a later row must get an explicit conflict.
7. **Delete race:** Layer/Broiler API delete and Ruminant page delete must reject old revision **before** stock reversal/mortality change. Existing scoped CSRF/roles and tenant checks must remain intact.
8. **Other lock graphs:** retain Daily Record -> stock order; do not move all writers to cycle-first because consumed-stock allocation holds source before cycles. Potential cycle/record inversion is a **separate unproven** issue, requiring a narrowly authorized later test.
9. **Regression isolation:** new, separately named branch/schema/approval workflow for repair tests; original B12 run `38233373699` must remain as documented failure. No automatic retries or overwrite allowances.

## Candidate minimal patch set — PLANNED, NOT YET CHANGED

- Dedicated shared PHP revision authority service (closed type/table/column map, scoped GET identity, revision validation, source-first lock, caller-owned conditional update), reused by Layer/Broiler/Ruminant and delete paths.
- Separately audited migration (candidate `092`) to add monotonic source revision to all three Daily Record tables, with source schema changes and explicit pre/postcondition verifiers. Do not write or execute it before resolving the above gates.
- Three thin save routes; Layer/Broiler delete API and Ruminant delete route; system-owned later-row continuity update.
- Farm-scoped `api/get_record.php` and three modal scripts/forms to transport expected id+revision from the originally loaded row. Existing `check_record` endpoints should not be treated as authoritative for saving.
- New isolated two-actor regression fixture and dormant approval-only workflow with test cases for stale sequential/concurrent edit, later-row derived invalidation, concurrent create, legacy null-cycle, edit-v-delete, rollback injection and role/tenant isolation.
- A full remote semantic diff review plus static source verifier BEFORE any execution approval request. No production deployment or running checks from this planning branch.

## End of this controlled step

**Only a documentation preflight was prepared.** No active code, migration, schema, PHP/SQL test, browser, GitHub Actions job, cPanel, AWS or D12 action was performed. An implementation branch may proceed in a subsequent controlled, scope-reviewed step after these outstanding dependencies are fully addressed. Preserving the demonstrated B12 failure and preventing regressions take priority over a speculative page-only patch.
