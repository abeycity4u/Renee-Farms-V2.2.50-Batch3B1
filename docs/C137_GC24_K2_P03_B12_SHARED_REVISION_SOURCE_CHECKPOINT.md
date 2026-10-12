# C137 / GC24 / K2-P03 — B12 shared revision authority source checkpoint

**2026-10-12. STATE: DORMANT SHARED SOURCE SERVICE WRITTEN, NOT WIRED TO ROUTES; ALL NEW TESTS NOT EXECUTED; B12 REMAINS FAILED.**

## Pinned GitHub state and scope

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Branch: `c137-gc24-k2p03-b12-shared-revision-contract`.
- Direct parent checkpoint: `1d6e65d65107da997b49e71716221c006c2ee0ae`, source-only implementation preflight. This branch is isolated and **must not deploy to a live runtime**.
- Source-only new service: `lib/daily_record_revision_authority.php`, blob `905f967d7c72af30a78de4338b94cd2d66551a67`.
- Prepared, **not executed** offline pure contract: `tests/integration/k2p03_b12_shared_revision_pure.php`, blob `23c947030943434bafdb64545b10cf97f9a289a6`.
- Current file delta relative to preflight HEAD, before this checkpoint: exactly these two additions, three source commits ahead, zero behind. A documentation-only checkpoint adds this report.
- Original B12 failed GitHub run `38233373699` (attempt 1) remains consumed and failed with `B12_CONCURRENCY_OUTCOME=TWO_STALE_COMMITS`. No replay, no new B12 assertion count, and no certified B12 success.
- No change to existing Layer, Broiler, Ruminant, auth, API, UI, population, stock, sales, migration, SQL schema, workflow, production, staging, AWS, cPanel or D12.

## What the dormant shared service now implements

- Fixed internal table/type map for **Layer**, **Broiler**, **Ruminant** with distinct per-type patch-column allowlists.
- Scope validation: nonzero farm and record IDs, nullable versus positive cycle ID, exact valid calendar date and species only for Ruminant. Source row lookup uses tenant-scoped `SELECT ... FOR UPDATE` inside an already active caller transaction.
- Requires the **revision originally loaded by the editing client**, not a freshly read version substituted at save time. Parses browser revision with strict decimal syntax and refuses absent, zero, negative, exponent, float, whitespace and overflow values.
- Compares immutable source row farm/id/cycle/date/species and stored source `record_revision`; stale, deleted or moved sources cause a safe `DailyRecordRevisionConflict`.
- Performs one column-allowlisted `UPDATE ... record_revision = record_revision + 1 WHERE id=? AND farm_id=? AND record_revision=?`; exactly one affected row is required. No arbitrary table/field name from a request may enter SQL.
- Separately exposes a locked revision check and a revision-bound DELETE primitive for **future** authorized source-delete coordination. Existing feed reversal, mortality reversal and Layer egg-sale allocation remain owned by original shared services.
- **Does not** begin/commit a transaction; connect to a database; load application config; implement authentication, CSRF or tenant permission grants; create rows; invoke external writers; or modify installed schema.

## Verification and negative evidence

- GitHub remote source and semantic two-path comparison completed; the only code/file changes are the new service and prepared pure test.
- The helper contains no new PDO connection or caller commit and is not included by existing pages. Thus this branch cannot itself fix, regress or certify real Daily Record saves.
- A future offline pure test is prepared to check closed mapping, protected identity exclusion, revision parse failures and species/date scope. **No PHP lint, test invocation or CI was performed.** Do not count expected future assertions as passing today.
- The service **requires a future `record_revision` column in all three tables**. Since those columns do not yet exist in the frozen 69-table schema, live route integration without an audited migration would break saves. Such wiring is explicitly outside this checkpoint.

## Reviewed writer inventory — known, not exhaustive

- Page save INSERT/UPDATE: `poultry/layers_daily_record.php`, `poultry/broiler_daily_record.php`, `ruminant/ruminant_daily_record.php`.
- Explicit delete: `api/delete_record.php` (Layer/Broiler); `ruminant/ruminant_daily_record.php` (Ruminant).
- System-derived later-row updates: `lib/daily_population_continuity.php`, 3 per-type updates to opening stock and Layer laying rate, which will need one revision bump per changed saved row while retaining the prior opening comparison.
- Read and client intent: `api/get_record.php`, `api/check_record.php`, `api/check_ruminant_record.php`, and `assets/js/layers-daily-record.js`, `assets/js/broiler-daily-record.js`, `assets/js/ruminant-daily-record.js`.
- Other reviewed non-mutating shared modules include `daily_feed_sync`, `daily_population_sync`, `production_population_projection`, `production_population`, `sales_allocation` and `stock_consumption_allocation_persistence`. These have established independent lock contracts; **do not reorder locks speculatively**.
- GitHub connector code search for known-present source symbols returned zero matches, and a bulk whole-repository connector scan exceeded the tool limit. Therefore **complete writer inventory is still a pending gate**. The narrower inspected paths must not be represented as a verified comprehensive scan.

## Exact remaining implementation gates

1. **Comprehensive writer inventory:** full per-file scan of other production and support PHP/SQL, ensure every authoritative source table UPDATE/DELETE (including batch and admin paths) participates in revision discipline. Preserve direct source mutators which legitimately bypass browser edit with separately designed system-version bumps.
2. **Legacy create identity:** confirm existing nullable `cycle_id` rows and duplicate identities. Current Layer/Broiler unique (`cycle_id, record_date`) and Ruminant unique (`record_date, animal_type, cycle_id`) allow NULL duplicate records. Resolve legacy uniqueness with a source-reviewed, data-safe and separately authorized plan. Do not silently update whatever row matches.
3. **Schema:** plan candidate migration after existing `091`; exact numbering and DDL depend on fresh migration inventory. MariaDB multi-table ALTER is not atomic; require guarded pre/postconditions, fail-closed partial DDL behavior, phased rollout and rollback. The source file alone is inert until columns exist.
4. **Thin save/delete routes:** migrate existing-row updates and delete preflight to the shared service; fetch snapshot revision/id from current farm-scoped GET response, validate CSRF/role/cycle/species as before, and preserve current business validation. Move edit authoritative revision check inside the caller's transaction and before any continuity/feed/mortality/sales writes.
5. **Later continuity:** extend `daily_population_continuity_apply` to increment `record_revision` for each truly changed later Daily Record without disturbing existing sorted row locking or optimistic opening-stock compare.
6. **Browser/API:** carry `expected_record_id` and `expected_record_revision` from the originally loaded GET response to save/delete requests; reset on modal scope/date/cycle/species change, reject stale async results, fail closed on missing/old token, preserve typed data and show a styled safe conflict notice.
7. **Test-only isolated regression:** prepare a **new, separately named** disposable MariaDB fixture/workflow for Layer/Broiler/Ruminant two-editor, sequential stale, edit-delete, later-row invalidation, rollback injection, create/legacy and cross-farm/role/CSRF scenarios. No old B12 approval can authorize it.
8. **Remote semantic diff and new exact-head approval:** read actual remote code, guard unchanged modules, perform source/static checks as permitted, and require **fresh explicit one-use** user authorization for any database/PHP GitHub Actions run. Never rerun failed B12 run `38233373699`.

## Hard stop

**No production changes, schema changes, migration execution, PHP execution, GitHub Actions run, protected fixture access, deployment, AWS/cPanel action or capacity test in this checkpoint.** Historical B01–B11 1,195 certified assertions unchanged. D12 fifteen import gates BLOCKED; 150 VU HOLD; 200 VU PROHIBITED; Stage D NOT AUTHORIZED.

**Next controlled step:** finish source-only writer inventory and legacy uniqueness resolution; then prepare a migration + caller integration in staged, auditable commits. Do not claim this isolated helper alone resolves the observed stale overwrite.
