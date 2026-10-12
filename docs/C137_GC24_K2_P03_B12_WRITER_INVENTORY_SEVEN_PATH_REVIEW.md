# C137 / GC24 / K2-P03 — B12 Daily Record source writer audit

**2026-10-12. SOURCE REVIEW ONLY. NOT A B12 CERTIFICATION, NOT A PHP OR DATABASE EXECUTION.**

## Evidence and pinned source

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Isolated branch: `c137-gc24-k2p03-b12-shared-revision-contract`.
- Initial scan HEAD: `c9a0354df1e72c09dc38e56d8c491b7fb6fc1921`.
- User's Mac Git 2.39.5, macOS 15.8.1, PHP not on PATH. Verified by user output: remote HEAD and checkout guards PASS. `git grep` returned `DIRECT_MATCH_FILES=24` and `APPLICATION_MATCH_FILES=7`, with five originally expected and two newly discovered PHP files. `SCAN_METHOD=STATIC_GIT_GREP`, dynamic/multiline coverage pending, PHP inventory verifier not executed.
- Read-only GitHub connector inspection of `includes/permission_runtime.php` blob `333cc31f937d4f474a6effc42c8d32ffff016d24`; `management/production_cycles.php` blob `dc624266d43acfa79498e0b609ca915167e43468`; `init.php` source included.
- The original B12 run `38233373699` has failed with the one approved attempt. Do not retry, substitute its approval, or claim certification.

## Confirmed seven directly matching application mutation paths

| Source path | Function | Revision integration treatment |
|---|---|---|
| `api/delete_record.php` | Layer/Broiler delete | Check originating client record ID/revision under source lock, before reversing feed/mortality. |
| `includes/permission_runtime.php` | Delegated Ruminant Manager delete | **Real DELETE**, not a benign permission query. Must use the same central Ruminant delete authority as admin. |
| `lib/daily_population_continuity.php` | Corrects later Daily Record opening stock (and Layer laying rate) | Increment changed later rows' `record_revision` while preserving chronological locks and prior source-opening checks. |
| `management/production_cycles.php` | Seeds initial Layer/Broiler/Ruminant Daily Record at new cycle start | **Real INSERTS**. Future revision column default must initialize these rows, without granting an unsafe browser overwrite path or changing cycle transaction behavior. |
| `poultry/broiler_daily_record.php` | Broiler create/edit | Explicit create vs client-loaded revision-protected edit. |
| `poultry/layers_daily_record.php` | Layer create/edit | Same, retaining shared feed/population/egg-sale effects. |
| `ruminant/ruminant_daily_record.php` | Ruminant create/edit and Farm Admin/Owner delete | Shared revision-protected write/delete, including species and canonical population projection. |

This inventory is a **verified direct, same-line static SQL writer candidate inventory**, not a proof of exhaustive mutation coverage of every possible dynamic, multiline, indirect or external writer.

## Critical additional finding: delegated deletion bypass

- `init.php` loads `includes/permission_runtime.php` before the Ruminant Daily Record page executes.
- In `includes/permission_runtime.php` lines 202–254, a POST `delete_record` on the Ruminant Daily Record route checks `ruminant_daily_delete`, then for a nonprivileged permitted actor runs a separate delegated delete path.
- In that path (lines 228–243), source lookup is outside the transaction, then `delete_daily_feed_usage` runs and `DELETE FROM ruminant_daily_records WHERE id=? AND farm_id=?` commits without source revision comparison.
- In contrast, `ruminant/ruminant_daily_record.php` lines 61–108 has the privileged admin/owner delete: `delete_daily_feed_usage`, **`daily_population_remove_mortality`**, then source DELETE, in one transaction.
- **Observed discrepancy:** the delegated delete path does not explicitly invoke the canonical mortality-removal operation, unlike the privileged route. This is an **unresolved potential population-projection correctness issue** in addition to stale-delete concurrency. Do not infer exact runtime ledger impact without a scoped authorized test and full downstream behavior review.
- Do not patch a second page-local copy of delete logic. Investigate centralizing both paths in one shared, transactional, tenant-scoped delete authority, preserving CSRF and granular permission checks.

## Production Cycle opening record behavior

- `management/production_cycles.php` lines 272–347 begins one PDO transaction, calls `production_cycle_create_v3`, then explicitly inserts a dated, newly created Daily Record according to production type: Layer 329–333, Broiler 336–340, Ruminant 343–347.
- These seeded records are not browser edit requests; they should receive initial persisted `record_revision=1` using guarded schema default, then enter the **same revision-protected edit rules** as any other Daily Record.
- Do not change baseline creation, poultry onboarding, cycle start age, opening headcount, or transaction order as an incidental stale-edit fix.

## Gate status and exact next step

- `tests/integration/k2p03_b12_daily_writer_inventory.php` expected writer list has been updated to the seven known source paths. This is a **source-only update**; PHP has not been run or linted on the user's Mac.
- Still pending: run the seven-writer verifier against a complete pinned checkout with PHP where available; audit indirect/dynamic SQL, root and deployment worker scripts, external callers and potential trigger/cascade behavior.
- After reconciling all write paths, inspect legacy nullable-cycle duplicates and data-safe uniqueness strategy **before** preparing/releasing schema migration `092` candidate.
- Keep B12 failed, historical B01–B11 assertion counts unchanged, D12 fifteen import gates blocked, Stage D unauthorized, 150 VU hold, 200 VU prohibited. No live DB, protected fixture, infrastructure, CI or deployments.
