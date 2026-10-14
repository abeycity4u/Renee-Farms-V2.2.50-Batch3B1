# Renee AgriSuite B12 — Repaired RC01 source-only harness review

**Prepared:** 2026-10-14
**Status:** SOURCE-ONLY DRAFT — no concurrency certification and no fixture execution
**Pinned source branch:** `c137-gc24-k2p03-b12-cas-writer-cutover-source`
**Pinned source HEAD:** `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`
**Suggested destination after source review:** `tests/integration/k2p03_b12_repaired_rc01_real_mariadb.php` on a separately guarded B12-only source branch.

## Verified existing architecture

- `lib/daily_record_revision_authority.php`: the authoritative `daily_record_revision_write()` locks a tenant-owned row, compares original revision, performs a conditional update, and increments revision once in the caller-owned transaction.
- `lib/daily_record_revision_save_pipeline.php`: orchestration for save + continuity + feed + mortality + Layer sales, staged and not wired; must be tested in RC02 and subsequent cases, not replaced with test-specific SQL.
- `lib/daily_record_revision_cutover_gate.php`: literal `false` and must remain hard OFF.
- Historical `tests/integration/k2p03_b12_real_mariadb.php`: historical failed lost-update regression; do not modify, rerun or relabel its result.
- `docs/C137_GC24_K2_P03_B12_REPAIRED_CAS_CERTIFICATION_CONTRACT.md`: RC01–RC14 acceptance scope. This draft targets RC01 only.

## Prepared draft

`tests/integration/k2p03_b12_repaired_rc01_real_mariadb.php` contains:

1. A **literal hard-disabled execution fuse**, currently `false`: the code exits 90 before opening a DB connection.
2. Pinned blob guards for the shared CAS authority, cutover gate, schema plan and base schema.
3. A prepared isolated Unix-socket PDO connection contract and 69-table / 886-column / three-revision-column checks.
4. Future *test-only* synthetic Layer fixture creation in a transaction, and restricted teardown.
5. Two independent child PHP processes each loading the same original Layer row and revision before a barrier; A obtains and holds the row lock, B enters the competing shared CAS service and must produce an explicit stale conflict.
6. Separate MariaDB connection ID assertions; durable revision, winning mortality and losing-value exclusion checks; time-bounded barrier and worker termination.

**Only RC01's canonical source-level CAS operation** is under test. It does not cover RC02's full downstream pipeline, operation permissions, HTTP/browser token wiring, authenticated end-to-end flows, create/create and delete races, or actual legacy tenant data.

## Work actually performed

- Created this new draft **only in the working container**, not in GitHub.
- PHP CLI 8.4 syntax check: **PASS** (`php -l`). PHP 8.2 compatibility lint on the dedicated Mac PHP image remains pending.
- Executed the hard-OFF fuse only: emitted `B12_RC01=SOURCE_ONLY_HARD_DISABLED` with exit code **90**, without a database connection.
- **No Docker/MariaDB connections, inserts, schema DDL, migrations, test workers, CI actions, deployments, Git commits or Git pushes** were performed for this draft.
- The Mac user's previously authorized three revision-column DDL is a distinct, completed milestone; it does not authorize this harness's fixture insertion or concurrency run.

## Required gates before any runtime execution

1. Independently review the draft, including fixture transaction/cleanup failure paths and race determinism. Keep the hard-OFF gate and historical failed test unchanged.
2. Commit on a narrowly scoped source-only branch at a fresh reviewed commit, pin its SHA and remote semantic diff; verify no CI trigger. Do not touch source deployment branches.
3. Separately authorize the exact **isolated disposable Mac MariaDB** schema and its operation list (synthetic farm/cycle/Layer fixture inserts; two worker transactions; deterministic barriers; scoped fixture teardown) and exact source commit. Do **not** reuse the earlier schema-import or three-column DDL authorizations.
4. Recheck physical container identity, network disabled, Unix socket-only access, database identity, source blobs, schema, three approved revision columns, FKs, row emptiness and no protected data. The in-PHP environment attestation is not sufficient by itself; a guarded Mac wrapper must enforce the physical Docker identity.
5. Approve a reviewed change to the literal execution fuse **only after** step 3; do not simply set it to `true` for exploratory execution.
6. Run PHP 8.2 lint and reviewed source/behavior tests before any fixture writes. Reconcile source HEAD after any new commit; avoid the old one-use Actions workflows.
7. Require exact one-commit/one-stale-conflict evidence, final revision +1, unique connection IDs, bounded waits, worker exit codes, zero unexpected success, post-teardown zero fixture records. Treat deadlocks/timeouts as FAIL, not stale conflict.
8. Independently perform a separately approved read-only actual-data compatibility audit on a physically isolated clone before any deployment or production migration proposal.

## Notes for review

- The harness draft should not be represented as RC01 PASS: **no real concurrent workers have run**.
- The code's `B12_RC01_ISOLATED_MAC_ATTESTED` environment flag is an intended *secondary* check; a separate authorization-bound host-side guard is mandatory before it can be set.
- The historical B12 CI failure remains `TWO_STALE_COMMITS`, and does not become a PASS through this separate source draft.
- No change to production or staging is allowed. B12 cutover remains OFF.

## Second-pass source review before first commit

- The historical `k2p03_b12_real_mariadb.php` and the seven live mutation paths are unchanged.
- Tightened RC01 conflict classification: only B’s precise shared-authority expected-revision error is accepted; other conflicts fail closed.
- Preflight checks all three Daily Record row sets, critical dependent tables, and three named cycle foreign keys as well as revision-column shapes.
- The harness retains its literal `B12_RC01_EXECUTION_ENABLED = false` fuse, and its use of `daily_record_revision_write()` does not claim RC02 business-pipeline certification.
- No fixture, worker, DDL, or database access was performed for this review. Only source lint and the hard-disabled execution path are eligible until fresh authorization.
