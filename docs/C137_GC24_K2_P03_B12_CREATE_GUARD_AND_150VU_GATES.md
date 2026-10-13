# B12 concurrency remediation and 150/200 VU readiness — guarded checkpoint

**2026-10-13. Source-only; no live writer integration, migrations, CI, protected database, staging, AWS, cPanel or capacity-test execution.**

## Verified branch and source

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Isolated branch: `c137-gc24-k2p03-b12-create-tenant-lock-source` forked from `c137-gc24-k2p03-b12-malformed-auth-state-source` SHA `93592739d5c0daba5fab110247150af9473113af`.
- New dormant `lib/daily_record_revision_create_guard.php`; identical locally PHP-linted/tested blob `dfb0e4f64e67c106a755a7f62b60e1643b8090d7`.
- New `tests/integration/k2p03_b12_create_guard_pure.php`; identical locally PHP-linted/tested blob `d1500b2767edd40cd5ee7febd4d52363354c21b9`.
- Local PHP 8.4.24 offline PDO-stub test `B12_CREATE_GUARD_PURE=PASS`, **14/14** checks; caught and fixed a real `??`/null ambiguity before GitHub commit. No real MariaDB or concurrent worker test.

## Specific source defect and guarded contract

Pinned current source still performs existence checks before `beginTransaction()` in:
- Layer: `poultry/layers_daily_record.php` (existence around line 270; transaction starts around 281; update/insert 285/311).
- Broiler: `poultry/broiler_daily_record.php` (existence 247; transaction 258; update/insert 262/284).
- Ruminant: `ruminant/ruminant_daily_record.php` (existence 396; transaction 404; update/insert 407/416).

The new **NOT-WIRED** shared helper requires the existing caller-owned transaction and server-resolved farm ID, validates scope using the existing shared reader, locks exactly that `farms.id` row `FOR UPDATE`, verifies an explicit same-farm cycle row under lock for non-NULL cycles, then runs the canonical 0/1/>1 cardinality probe. It permits only an explicit absent scope and returns `locked_absent` as an internal continuation marker, **not a standalone INSERT permission**. The original caller must INSERT in the same transaction before COMMIT.

**Critical limitation:** This controls concurrent CREATE only if **all writers for the same identity consistently acquire that parent lock before checking/inserting**, under a compatible transactional engine. Current legacy writers do NOT, and the schema's nullable cycle/ON DELETE SET NULL behavior can create duplicates. No safety claim until the whole writer family, schema policy and MariaDB multi-worker tests have been jointly certified. This guard is not intended as a substitute for reviewed DB uniqueness where feasible.

## Full writer inventory verified at source HEAD

| Writer | Pinned blob | Required later integration |
|---|---|---|
| `poultry/layers_daily_record.php` | `87b7c6ace9bd577ad2303ea4aa50312a780412d2` | Explicit CREATE/EDIT mode; originally loaded ID/revision; source lock; same-transaction stock/feed/population/egg-sale effects |
| `poultry/broiler_daily_record.php` | `2a797c7fec45d3f78e7b2f01cb72531b1c416699` | Same, without Layer egg allocation |
| `ruminant/ruminant_daily_record.php` | `b77cb8c5222c597fadc2e03c03f5dd4c0583cf83` | Per-species exact identity and lifecycle effects |
| `api/delete_record.php` | `4a3b8d03b565f65c4a103e9f1fae304d6ac75fda` | Expected revision/immutable ID before feed+population reversals and DELETE |
| `lib/ruminant_daily_record_deletion.php` | `74fbe999ee3c822c3ad27c183da33fc9497cbf08` | Shared CAS delete and original reversal ordering |
| `lib/daily_population_continuity.php` | `9503771b47c6c2fe26bb3237da69a17874e724a3` | Increment revision whenever recomputing persisted downstream Daily Records |
| `management/production_cycles.php` | `dc624266d43acfa79498e0b609ca915167e43468` | Authorize and serialize managed cycle initial-record creation |

All seven remain unchanged and require a coordinated cutover. The source reader, form-token helper, stale-async controller, shared revision CAS authority, B12 API adapter, JSON auth response policy and core-auth helpers are **dormant**, not certified runtime fixes.

## Six major stages and unclosed gates

1. **Authentication integration:** core, malformed state, response and bootstrap seam source-only done; safe real session/HTML/JSON integration **not performed**, with compatibility and PDO error paths outstanding.
2. **Legacy schema/data audit:** disposable synthetic schema R2 evidence PASS, but actual tenant duplicate, orphan/cross-farm cycle, nullable identity and SET NULL impact **unknown**; requires separately authorized read-only inspection.
3. **Three-table schema and concurrency writers:** revision migration **not designed/applied** under guarded live-data evidence; CAS source-only; concurrent CREATE guard now source-only; all seven writer integrations outstanding.
4. **Browser/API and business-effect integration:** six edit tokens, exact immutable ID reads, delete/modify permissions, stock/feed/mortality/population/continuity/egg allocations and cross-type behavior **not activated**.
5. **Certification:** real MariaDB multi-worker stale/CREATE/DELETE, browser role/CSRF/E2E and recovery tests **not run**. Existing failed workflow `38233373699` remains FAILED `TWO_STALE_COMMITS`; no rerun permitted by its consumed one-use authorization.
6. **D12 and capacity readiness:** fifteen D12 import gates remain BLOCKED; no evidence of re-opening Stage D or validating deployment/rollback. **150 VU HOLD and 200 VU PROHIBITED** until specific readiness and authorization evidence.

No truthful numerical number of remaining commits/engineering steps can be promised from pure source review. All six cannot be certified solely by pushing source code: actual deployed schema, transaction invariants and concurrency need protected evidence and explicit operational gates.

## Safe next technical priority

Review legacy FK/index policy in the pinned schema and source migrations; settle cycle deletion semantics and historical NULL-cycle identity, then formulate one **migration+writer cutover** with rollback and executable disposable MariaDB tests. Complete source integration of all seven writer paths in a coordinated branch, preserving existing tenant, permissions, CSRF, source-of-truth and side effects. Do not turn on one writer while six still use date-based, pretransaction paths.

**Operational hard stops:** no production/staging mutation, AWS capacity load, paid recovery manipulation, original failed workflow rerun, or protected tenant data query based solely on this source-only authorization. Once needed, request exact narrowly scoped environment action and evidence.

**One fix, fit it all:** shared authority, centralized serialization policy, thin routes, remote semantic diff after every push, no speculative broad rewrite.
