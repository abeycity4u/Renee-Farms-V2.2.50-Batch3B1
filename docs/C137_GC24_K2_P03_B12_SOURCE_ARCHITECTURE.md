# C137 / GC24 / K2-P03 B12 — source-only concurrency architecture checkpoint

**State (2026-10-12): INVESTIGATION ONLY; B12 NOT IMPLEMENTED, NOT EXECUTED, NOT CERTIFIED.** No execution approval exists in this branch. This document records a read-only review and proposed tests, not a runtime finding.

## Provenance / scope

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`
- B12 branch `c137-gc24-k2p03-ci-mariadb-b12` created directly from **certified B11** HEAD `7c7dd69dffc29cf46316312e26e50d2f4d887427`.
- B11 run `38227697928`: GitHub Actions completed **success**, attempt 1, at one-use approval commit `b9908b2afc8392f586c7a7409acf265714d7dcbe`. B01–B11 cumulative reported certification: 1,195 assertions. Do **not** rerun those scenarios.
- Authoritative requirement from `docs/C137_GC24_K2_P03B_TEST_MATRIX.md` (blob `3d4192819e26148446986f8672464313e5c63a14`): two isolated concurrent edits targeting one cycle/date; **consistent lock order, one authoritative result or safe conflict, no silent overwrite**.
- Read-only GitHub inspection only; no PHP/SQL, CI, protected credentials, tenant data, infrastructure, approval file, application mutation, database imports or capacity tests.

## Certified parent source paths reviewed

| File | Git blob SHA at B11 parent |
| --- | --- |
| `poultry/layers_daily_record.php` | `87b7c6ace9bd577ad2303ea4aa50312a780412d2` |
| `lib/daily_population_continuity.php` | `9503771b47c6c2fe26bb3237da69a17874e724a3` |
| `lib/daily_population_boundary.php` | `38b346a177788a439355cd18205ab16c47065310` |
| `lib/production_population.php` | `fff1b3e663bb0845c34ccf734a52f6c92eaae706` |
| `lib/production_population_projection.php` | `c4cdae4c193b624a7469915e0451d41fef1b2f4b` |
| `lib/daily_population_sync.php` | `ff4b045000910af222c5203347419c7a7cb66e6a` |
| `lib/daily_feed_sync.php` | `752707749fea0583249ec600bb5d624d416b8a47` |
| `lib/stock_consumption_source_resolver.php` | `d9eeaeb53225f3ac1797d5065cc15bb773558200` |
| `lib/stock_service.php` | `b9e7b021020ffaa92fa201800572451f8762d520` |
| `lib/sales_allocation.php` | `1a701fff059189b6a9ad3fe1a190188be958a96b` |
| `database_schema.sql` | `e77f287d62c5812a3d55dc23b5b20f5d167db389` |

## Actual read/write and lock path, not a certified concurrency outcome

1. The Layer route `poultry/layers_daily_record.php` performs its existing record lookup **before** `PDO::beginTransaction()` (about lines 268–281). Inside the transaction it issues an `UPDATE layer_daily_records` conditioned on farm/date/(cycle when selected) **without** an expected prior row version/value comparison (about lines 284–304), or inserts a new row, then calls continuity, feed, mortality projection, and egg allocation, and commits; exceptions roll back (about lines 281–362). The UPDATE necessarily obtains its InnoDB target-row write lock, but the review alone does not demonstrate safe stale-editor rejection.
2. `daily_population_continuity_apply` demands an open caller transaction, rebuilds the plan using `daily_population_continuity_preview(..., true, sourceId)`, and later-row `SELECT ... ORDER BY record_date ASC, id ASC FOR UPDATE` (about lines 172–227, 1112–1227). Later opening-stock changes have optimistic `AND opening_stock = ?` comparisons and require rowCount 1; this comparison is on **later rows**, not necessarily the **target row** being edited.
3. Canonical preview `daily_population_boundary_snapshots` reads `production_population_state` and aggregate active movement rows; when pending mortality has `for_update`, it locks the active source projection row. The state/baseline and movement aggregation reads in these inspected functions are ordinary `SELECT`, not an established single global ordered lock graph.
4. `production_population_projection_sync` obtains source snapshot then locks sorted production cycles, active projection movement, sorted baselines and movement version; it uses shared canonical mutation and reversal services. `production_population_lock_cycle` explicitly describes a canonical write lock order beginning at the cycle row; `production_population_lock_baseline` also locks with `FOR UPDATE`. Whether mixed writers induce a cycle-versus-Daily-Record lock inversion is **unproven**.
5. `sync_daily_feed_usage` first resolves/locks its authoritative saved Daily Record, then the active source-owned stock `used` movement, and invokes the canonical stock reversal/application functions that lock stock transaction/item records. It does not accept unverified external cycle attribution.
6. `sales_rebuild_layer_egg_allocations` enumerates eligible sale IDs by date/id, then `sales_refresh_automatic_allocation` locks each selected parent sale and associated allocation state in the caller transaction. The initial enumeration itself is not a `FOR UPDATE` scan. Do not assume sale ordering alone resolves every potential cross-module contention pattern.
7. The schema defines `uniq_layer_cycle_record(cycle_id, record_date)`, `uniq_population_baseline_cycle(farm_id,cycle_id)`, source/version and reversal unique constraints on population movements, plus source-scoped stock and sale allocation uniqueness. **Uniqueness of cycle/date prevents duplicate nonnull-cycle rows; it is not an optimistic stale-value comparison on an existing Daily Record.**

## Hypothesis to falsify, NOT a proven bug

Two users may load the same Layer target source, edit different values from that common baseline, and submit overlapping save transactions. The first `UPDATE` can lock and commit; a later unconditional `UPDATE` by the second request might then silently overwrite the first user’s values even though downstream continuity, inventory, and projection each maintain individually valid state. This is a **source-derived risk hypothesis**, not a demonstrated concurrency defect. Likewise, a cycle/baseline-versus-Daily-Record lock inversion remains only a candidate until observed in genuine overlapping MariaDB transactions.

Do not implement production changes from these hypotheses without a deliberately scoped reproducible failing test, tenancy/permission impact review, a new source pin, and separate authorization for any application modification.

## Proposed B12 isolated test contract (not prepared or run)

- Separate **two real independent PDO/MySQL sessions and worker processes** on a brand-new guarded disposable GitHub-hosted MariaDB 10.11 database, proposed `renee_k2p03_ci_b12`, only after a uniquely approved one-shot execution gate. Never point workers at staging, AWS Nodes A/B/C, cPanel, protected D12, or real tenant databases.
- Synthetic same farm + Layer cycle + target date + durable target Daily Record + canonical baseline; create later Daily Records and feed/sale movement context mirroring original B11 patterns where useful. No synthetic fixture is implied to exist by this document.
- Arrange a deterministic barrier: both actors read the same pre-edit state; actor A acquires the target write lock and pauses; actor B independently attempts the same date/cycle edit while A has not committed; release A, observe whether B safely conflicts or produces a serializable result without stale silent overwrite. Record bounded lock wait/interleaving evidence, not merely that two PHP calls were made sequentially.
- Run the **original shared production functions** in the verified order under each actor’s own PDO transaction. A test may orchestrate pauses, but must not add a second production business-rule implementation or weaken source/version uniqueness.
- Assert final single authoritative Daily Record, conserved canonical population, source-version/reversal identity, feed stock balance and source-owned USED/reversal entries, and pooled egg-sale allocation with **no duplicate source projections, silent lost updates, partial writes, cross-farm contamination, or negative balances**. Check a known safe conflict path explicitly. Use deterministic before/after farm-scoped snapshots of the nine B11 ledger sections and a cycle/baseline reconciliation where relevant.
- Bound the worker handoff, lock wait and job runtime; classify unexpected deadlock, timeout, non-retryable writer error or partial persisted ledger as FAIL, not a PASS injection. Any failure/abort must roll back and clean up independent transactions and ephemeral GitHub service/network. Avoid logging credential/session details.
- Do not claim source inspection proves `READ COMMITTED` or `REPEATABLE READ` behavior. Record actual MariaDB isolation under the approved test when run. Investigate interaction with other cycle/population writers as a separate bounded scope if a lock-order concern is confirmed.

## Future source-only preparation sequence

1. Confirm certified B11 identity and this B12 branch parent/diff; verify no B12 run and no approval file.
2. Add a distinct B12 mapping to `tests/integration/k2p03_ci_isolation.php` preserving existing mappings; add a focused B12 dual-worker synthetic fixture, plus a dormant *separate* workflow. Use branch, test/helper/schema blob hashes, empty disposable schema and fake-credential guards, and a one-file approval-only trigger. Source-only pushes must not execute it.
3. Review new test implementation for truly concurrent independent connections, deterministic bounded synchronization, correct original-service order, negative paths, rollback integrity and cleanup even on process failures. Review actual **remote** GitHub diff and frozen product/schema blobs.
4. Ask for **new explicit user authorization** naming finalized B12 branch and exact HEAD, one isolated GitHub-hosted execution, disposable database, no automatic retries, and complete protected-infrastructure exclusions. Only then add exactly one new approval commit. A failed approved run consumes approval.
5. After an actually successful approved run, record real assertion count and outcome via a docs-only certification commit. B12 success never authorizes D12 imports, 150 VU, 200 VU, Stage D or deployment.

## Current hard gates

**B12:** No PHP execution; no SQL connection or import; no Actions execution; no approval file; no runtime PASS claims. **D12:** all 15 import gates BLOCKED; 150 VU HOLD; 200 VU PROHIBITED; Stage D NOT AUTHORIZED. B01–B11 approval and prior runs are fully consumed; no replay.
