# C137 / GC24 / K2-P03 — B12 shared Ruminant Daily Record delete integration

**2026-10-12 | State: ISOLATED GITHUB SOURCE PATCH; PHP LINT AND REGRESSION TESTS NOT EXECUTED; NOT DEPLOYED.**

## Immutable starting guard

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- New isolated branch: `c137-gc24-k2p03-b12-ruminant-delete-unification`, forked at `52db65749843961ca4e0a55b6ed8f6bd308b3038`.
- The original B12 GitHub-hosted concurrency execution remains failed: run `38233373699`, attempt `1`, `B12_CONCURRENCY_OUTCOME=TWO_STALE_COMMITS`; original failed B12 branch `c137-gc24-k2p03-ci-mariadb-b12` is unchanged at `39ab4dd71a896fa73e4f4513efa0bd1ab72263db`.
- No new workflow, database fixture, migration, application deployment or AWS/cPanel action was authorized or performed.

## Root cause addressed in source

The seven-writer inventory found two separate Ruminant Daily Record deletion handlers:

1. `ruminant/ruminant_daily_record.php` (previously lines 60–108), for Farm Admin / Platform Owner, called `delete_daily_feed_usage`, `daily_population_remove_mortality`, then source DELETE in one transaction.
2. `includes/permission_runtime.php` (previously lines 202–253), for a delegated role holding `ruminant_daily_delete`, called `delete_daily_feed_usage` then source DELETE in its own transaction but **omitted the canonical mortality-projection removal**.

This was genuine duplicated business logic, not a harmless permission helper. The user-provided Git static scan found seven direct application write paths including both; GitHub read-only source inspection confirmed the difference. Ledger consequences in a real tenant have **not** been established by a database test.

## Change scope and intended behavior

| File | Change |
|---|---|
| `lib/ruminant_daily_record_deletion.php` | New single shared business delete service: require valid farm/record IDs and caller with no active transaction, begin and own transaction, `SELECT id, animal_type ... WHERE id=? AND farm_id=? FOR UPDATE`, validate species `cattle/goat/sheep/other`, call canonical feed reversal, call canonical mortality removal, delete source scoped to farm, demand exactly one deleted row, commit or rollback on any failure. The same source-first lock order remains. |
| `includes/permission_runtime.php` | Preserve delegated permission check, CSRF and post-delete redirect; replace duplicated SQL/reversal transaction with a call to the shared delete service. Do not grant new permissions. |
| `ruminant/ruminant_daily_record.php` | Preserve page CSRF, flash/error handling and redirect; replace privileged duplicated SQL/reversal transaction with shared service call. Delete button is now visible according to canonical `permission_runtime_has('ruminant_daily_delete')`, rather than an additional admin-only local UI rule. Delegated POSTs still intercept in the runtime permission layer; the shared deletion service is the same. |
| `scripts/verify_v301_daily_feed_delete_lock_order.php` | Adjust closed source verifier to inspect shared service's `source FOR UPDATE -> feed/stock -> mortality -> DELETE` order and ensure BOTH page and delegated handler call it; retain previous Poultry and allocation lock checks. |
| `tests/integration/k2p03_b12_daily_writer_inventory.php` | Update exact expected mutation paths: no direct Ruminant DELETE remains in `includes/permission_runtime.php`; canonical `lib/ruminant_daily_record_deletion.php` becomes the writer. Other known six writers remain; seven total. |

The shared service imports only the established feed and mortality projection authorities. It **does not** copy their internal SQL or change the lifecycle logic, SQL schema, stock-allocation revisions, tenant permission store, existing creation writers, Layer/Broiler routes or the dormant B12 shared revision service.

## Why B12 remains unresolved

This patch eliminates the two Ruminant delete business-logic variants and adds the missing canonical population-removal call on the delegated path. It **does not** implement the B12 stale editor/last writer wins solution.

- No three-table `record_revision` migration exists, and no client-loaded expected revision is passed with delete. A pretransaction stale browser delete cannot yet be fully rejected; the service's in-transaction source lock is only a lock-order and existence guard, not an optimistic version check.
- Concurrent create for `cycle_id IS NULL` remains unprotected by existing nullable-cycle unique keys.
- Historical delete's effect on later opening-stock continuity requires separate contract review; it was not changed here.
- A complete dynamic SQL/external writer inventory and legacy duplicate inspection remain pending.
- A source-only remote semantic diff is **not** PHP syntax lint and is **not** proof of runtime safety. No PHP verifier or database fixture was executed.

## Verification and release gates

1. Confirm the new branch exact HEAD, its base ancestry and exact-five-file application/verifier delta; remote diff must include no schema, migration, CI workflow or unrelated product changes.
2. Run PHP lint on modified PHP files and shared service in an isolated, full checkout of this exact HEAD. The user's Mac had Git installed but PHP unavailable on PATH; do not claim lint PASS until evidence exists.
3. Execute the updated offline source verifier `scripts/verify_v301_daily_feed_delete_lock_order.php` and the current inventory `tests/integration/k2p03_b12_daily_writer_inventory.php` only in an authorized source-check environment. Inspect unexpected writers. Existing closed QA should not be broadly replayed without regression evidence.
4. Future isolated disposable MariaDB tests require fresh branch/HEAD-specific one-use approval. Test delegated and privileged deletes against synthetic cases: nonzero mortality projection, linked feed stock return, wrong farm/role/CSRF rejection, deleted/unsupported IDs, transactional failure injection, row locks and rollback. No tests were run here.
5. Before the next B12 step, resolve migration/legacy-key and browser revision-token dependencies and re-read source authority across production cycles, population, inventory, allocations and tenancy. Do not deploy this unverified source change.
6. D12 remains blocked: fifteen import gates BLOCKED, Stage D UNAUTHORIZED, 150 virtual users HOLD, 200 virtual users PROHIBITED.

**One fix, fit it all:** this patch replaces duplicated Ruminant delete business code with one shared service, retaining narrow permissions and caller-owned HTTP concerns.
