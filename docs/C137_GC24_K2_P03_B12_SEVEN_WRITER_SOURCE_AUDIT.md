# C137 GC24 K2-P03 B12 — seven-writer source consistency audit
**Classification:** SOURCE-ONLY / CUTOVER HARD-OFF / NOT DEPLOYABLE AS B12 CERTIFICATION
**Repository:** `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`
**Source branch:** `c137-gc24-k2p03-b12-cas-writer-cutover-source`
**Protected production reference at audit:** `v320-renee-agrisuite-branding` @ `10041da3a3c4e4a7b8101f8183c243d056c7b128`

## Audit methodology and limits
Reviewed exact GitHub source for the seven **literal existing Daily Record table mutation paths**, shared CAS authority, save gate, persistence, continuity, DELETE pipeline, cycle seed, permission runtime, and dormant GET/JS reader. A source inventory covers PHP modules and top-level PHP files and explicitly names tenant-purge and closed-table dynamic SQL exceptions. Static matching is not a concurrency proof and cannot detect arbitrary SQL in external callers, stored routines, or dynamic runtime code.

No customer DB connection, migration, staging/production deployment, load test, GitHub Actions execution, or native PHP verifier execution was authorized or performed for this audit. The new offline runner is committed but **not executed**; its result must not be reported as PASS until its command output is collected.

## Exactly seven currently recognized live legacy mutation paths

| # | Legacy writer path | Live operation | Staged B12 revision route | Remaining blocker |
|---|---|---|---|---|
| 1 | `poultry/layers_daily_record.php` | Layer INSERT / date-scoped UPDATE; continuity, feed, mortality, pooled egg sale rebuild | Guarded call to central `daily_record_revision_route_dispatch_json` → save gate / CAS persistence / revision continuity | Browser GET token resolution + AJAX POST submission / real revisions not activated |
| 2 | `poultry/broiler_daily_record.php` | Broiler INSERT / date-scoped UPDATE; continuity, feed, mortality | Same guarded central pipeline | Same browser/schema gates |
| 3 | `ruminant/ruminant_daily_record.php` | Ruminant species/date INSERT / UPDATE; continuity, feed, group mortality | Same guarded central pipeline. Locked cycle must now match **species**, including legacy chain | Browser/DB race and species tests not executed natively |
| 4 | `api/delete_record.php` | Layer + Broiler ID/tenant DELETE; feed/mortality reversals; Layer sale rebuild | Guarded `daily_record_revision_delete_transaction` with originally loaded row/revision | Poultry delete JS does not yet send original revision tokens |
| 5 | `lib/ruminant_daily_record_deletion.php` | Shared Ruminant DELETE, used by privileged page and delegated permission runtime | Guarded shared DELETE transaction; Ruminant form tokens added behind fuse | Native regression and original revision schema not certified |
| 6 | `lib/daily_population_continuity.php` | Later Daily Record opening-stock UPDATEs and Layer laying-rate recalculation | Separate staged `daily_record_revision_continuity_apply` calls `daily_record_revision_apply_continuity_change` to bump revision per durable later row | Legacy helper itself still updates without bump while fuse OFF; all consumers must migrate simultaneously |
| 7 | `management/production_cycles.php` | Initial Daily Record seed INSERT for Layer/Broiler/Ruminant during cycle setup | Guarded `daily_record_revision_seed_cycle_entry` initializes revision exactly 1, locked tenant-owned new cycle, same transaction | Three-table DDL and actual rollback/onboarding tests still missing |

## Known non-seven dynamic mutations
- `management/farms.php` performs privileged tenant deletion across a controlled table list. It is **not** a normal Daily Record writer; platform-owner protected-history/tenant purge policy must remain unchanged.
- `lib/daily_record_revision_authority.php` contains dormant allowlisted dynamic CAS UPDATE and DELETE statements.
- `lib/daily_record_revision_persistence.php` stages dynamic INSERT and calls shared CAS UPDATE.
- `lib/daily_record_revision_cycle_seed.php` stages dynamic initial INSERT with revision 1.
- `lib/daily_record_revision_continuity.php` stages dynamic later-row UPDATE with a revision increment.
- `lib/daily_record_revision_save_gate.php`, `lib/daily_record_revision_persistence.php`, and `lib/daily_record_revision_delete_pipeline.php` are the **only allowlisted external direct callers** of shared `daily_record_revision_lock_and_compare`, `daily_record_revision_write`, and `daily_record_revision_delete_locked`. Any additional direct caller fails the updated source inventory.

## Shared contract review
- No browser-supplied farm ID grants tenancy or permission. All mutation handlers must enforce authenticated farm/module/view/operation/CSRF.
- Original `write_intent`, record ID, expected revision, original cycle/date/species must be browser-loaded; a fresh server SELECT cannot invent a replacement revision.
- EDIT: lock exact tenant-owned source row and compare immutable identity and original revision **before** stock/population/sale effects; use one conditional UPDATE and revision bump.
- CREATE: lock tenant/cycle before absence-check; no UPSERT and no 404/409/network-error fallback to CREATE. Legacy NULL-cycle data requires its own audited policy.
- DELETE: lock and compare original revision before feed/mortality reversals; final CAS DELETE and downstream Layer sales rebuild in one rollback-capable transaction.
- Continuity: same canonical/legacy downstream preview with later-row locks; update only materially changed rows and bump each changed later row's revision exactly once.
- Cycle seed: canonical cycle/baseline and initial Daily Record seed are inside the caller's transaction. The source-only revision writer supplies explicit `record_revision=1`.
- The authenticated `api/daily_record_revision_read.php` route returns 503 while the fuse is OFF. It reuses the existing strict scope/exact reader when enabled. The transport and bridge are not loaded by any of the three legacy pages.
- **Production remains unchanged**. `lib/daily_record_revision_cutover_gate.php` is a literal function returning `false`, not a runtime flag.

## Confirmed audit correction
Previously, `daily_record_revision_route_revalidate_locked` checked tenant, farm type, date, and active cycle, but a Ruminant cycle did not require exact species in its lock query. A legacy previous-row chain could otherwise bypass the canonical baseline species check. Fixed centrally: the locked cycle query now includes `LOWER(TRIM(production_type)) = ?` for both poultry and Ruminants, with an allowlisted Ruminant species. The matching fake-PDO regression was extended to reject Goat-to-Cattle and to distinguish a genuine stale-opening check from a species mismatch.

The original seven-writer verifier previously interpreted staged CAS-authority callers as unexpected live writers. It now uses an explicit three-caller allowlist, adds dynamic-SQL classification for the three future writers, and requires the gate to stay hard-off; unknown direct callers still fail closed.

## Outstanding gates, in recommended order
1. **Native source tests:** On a clean isolated checkout, execute `bash scripts/verify_k2p03_b12_offline.sh`. Inspect any failure; do not report green results from mocks or textual review alone. This is source verification, not a DB test.
2. **Complete browser cutover:** Replace legacy asynchronous date-only editors across Layer/Broiler/Ruminant with ticket-guarded scope→exact loader and same-origin transport; preserve feed selector, opening-stock lookup, modal UX, and draft on HTTP 409. Provide authenticated POST JSON save, appropriate CSRF, session/permissions and error handling; wire original revision tokens on **all** DELETE clients including Poultry. Never load new and legacy async writers together.
3. **Schema and data policy:** Verify duplicate logical keys, nullable legacy cycles, cross-tenant and cross-species identity, migration order, and `record_revision` default/limits. Draft DDL in a `.sql.txt` plan is not an approved migration.
4. **All writer and migration coordination:** Every one of the seven paths must use its revision-aware equivalent in one controlled cutover. Tenant purge stays an explicit privileged exception. The legacy continuity function must not remain an unguarded mutation route.
5. **Real concurrency and business effects:** Only with separate authorization, test at least two independent sessions/connections on an isolated disposable MariaDB fixture. Verify one winner/one 409, exact revision propagation, inventory/feed restoration, mortality/live population and pooled Layer egg sale allocation, no partial commits, no cross-tenant/species changes, and rollback. Do not run prior failed synthetic legacy test as a substitute for race certification.
6. **Release gates:** Record remote semantic diff after each push, verify production HEAD/branch isolation, successful native verifiers, schema authorization, source/runtime integrity and manual browser outcomes before any rollout. Stage D/150VU/200VU are outside current authorization.

## Offline verifier
```bash
# Run ONLY in a clean disposable B12 source checkout (not production runtime):
bash scripts/verify_k2p03_b12_offline.sh
```
Output contains `#####COPY FROM HERE#####` and `#####COPY TO HERE#####` markers around concise evidence. This script performs native PHP syntax checks and the enumerated offline pure PHP/Node tests. It expressly excludes `k2p03_b12_real_mariadb.php`, synthetic MariaDB schema fixtures, deployment and migration commands. It is **prepared, not executed** in the GitHub-only review session.

**Decision:** B12 stays OPEN. Keep cutover hard-disabled until all release gates are satisfied.
