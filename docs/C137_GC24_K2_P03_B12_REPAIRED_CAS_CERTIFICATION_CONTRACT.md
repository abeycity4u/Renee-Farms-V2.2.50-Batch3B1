# C137 / GC24 / K2-P03 / B12 — Repaired CAS certification contract

Date: 2026-10-14
Classification: SOURCE-ONLY TEST DESIGN / NO EXECUTION / NOT A CERTIFICATION
Pinned source branch: c137-gc24-k2p03-b12-cas-writer-cutover-source
Source parent HEAD: d092d0e7266521253d0adeb6179757787eb20a56
Protected production branch: v320-renee-agrisuite-branding
Protected production HEAD at preparation: 10041da3a3c4e4a7b8101f8183c243d056c7b128

## 1. Decision and historical boundary

The original B12 concurrent-update GitHub Actions run 38233373699, attempt 1, FAILED with TWO_STALE_COMMITS. Preserve that failure and its consumed one-use approval. Do not rerun, edit, relabel, or substitute its result.

tests/integration/k2p03_b12_real_mariadb.php is an intentionally historical Layer regression: it pins c137-gc24-k2p03-ci-mariadb-b12 and the old unversioned route sources, and issues a date/cycle-scoped UPDATE without the browser-loaded expected revision. It cannot prove that the currently staged shared revision authority prevents the original lost update. A NEW separately named harness and independent approval must target the current revision code and preserve the old harness as a negative control.

Existing synthetic schema evidence: R2 run 38301126240 PASS; independent read-only FK DDL attestation run 38454255713 PASS, both disposable synthetic MariaDB only. Neither supplies actual tenant schema/data evidence or repaired CAS certification. The hard-OFF source gate at lib/daily_record_revision_cutover_gate.php is literal false. No active page loads the B12 browser bootstrap.

## 2. Scope and architecture guard

One fix, fit it all. The harness must exercise the actual shared CAS/save/delete/continuity/seed services, never write a test-only replacement for a production CAS algorithm or duplicate module-specific business policy. Test fixture INSERTs and read-only ledger assertions are acceptable only in an independently approved disposable database; business mutations under test must run through existing shared services. Keep source, identity/tenant, CSRF/permission, transaction/rollback, feed, population, stock and Layer automatic egg-sale boundaries intact.

Production shared paths to cover:
- lib/daily_record_revision_authority.php: exact farm+id+immutable scope lock, expected revision comparison, single-row conditional UPDATE/DELETE.
- lib/daily_record_revision_save_gate.php and create_guard.php: explicit CREATE/EDIT intent; tenant/cycle/scope lock and unambiguous absence.
- lib/daily_record_revision_route_adapter.php, save_pipeline.php and persistence.php: caller-owned atomic save, downstream effects.
- lib/daily_record_revision_continuity_apply.php and continuity.php: materially changed later records bump revisions.
- lib/daily_record_revision_delete_pipeline.php and lib/ruminant_daily_record_deletion.php: shared DELETE before reversal and two Ruminant callers.
- lib/daily_record_revision_cycle_seed.php and management/production_cycles.php: new cycle opening entry revision 1 in caller transaction.
- Exact GET, browser source token and AJAX/error contracts must be certified separately in authenticated browser tests.

Seven writer categories: Layer save; Broiler save; Ruminant save; Poultry DELETE; privileged/delegated shared Ruminant DELETE; later-row continuity; cycle-opening record seed. management/farms.php is an explicit privileged tenant-purge lifecycle exception, not an eighth normal editor. Search for newly added direct/dynamic writers before authorizing a coordinated switch.

## 3. Pre-execution authorization and isolation (all mandatory)

1. Obtain a fresh, ONE-USE, explicit approval for the named proposed harness/commit, exact database host, database name, intended test-only operations and teardown. A generic "proceed" or an old approved Actions run is not a database authorization.
2. Verify an independently disposable isolated MariaDB target; record source of fixture, physical server identity, version, actual database identity, nonproduction credentials/grants and expected branch/code HEAD. Names alone do not establish isolation. No production/staging/AWS Nodes A/B/C/protected C137 fixtures/customer data.
3. First resolve actual-data-safe migration compatibility and legacy NULL-cycle policies through a separately approved read-only independently isolated clone audit. Do not infer customer duplicate counts from synthetic R2 fixtures.
4. For a fresh synthetic disposable concurrent-test database, independently validate a three-table record_revision schema and safe migration/recovery behavior. Do not use scripts/run_migrations.php on an unverified or partially applied schema; MariaDB multi-ALTER is not atomic.
5. Pin all application service blobs and test files in the new runner; reject any drift and gate-ON leakage. The current literal false gate remains unchanged during source preparation and is not proof of a deployed ON path.
6. Bound worker waits and lock waits, verify independent connection IDs and a deterministic start barrier; fail on timeout/deadlock/unknown exception. Record clean teardown even on failure. Any failure stops downstream certification.

## 4. Required real-connection test matrix (future, not executed)

| ID | Actor race / invariant | Required durable outcome |
| --- | --- | --- |
| RC01 | Layer EDIT A and EDIT B load identical original row id/revision before either mutation; A holds row lock and commits before B completes | Exactly ONE commit, ONE explicit stale conflict; stored revision +1, no silent B write; independent connection IDs and measured blocked interval |
| RC02 | Same Layer race with feed, mortality, later continuity and pooled egg sales | Winner's ledger/accounting effects exactly once; loser has zero durable effects |
| RC03 | Broiler stale EDIT/EDIT, plus stock and mortality | One winner/one conflict, single atomic resulting feed/population state |
| RC04 | Ruminant same cycle/date/species stale EDIT/EDIT | Same rule, including group/tag handling; other species unaffected |
| RC05 | Different tenant, cycle and Ruminant species probes | No cross-scope row, movement, feed or sale mutation; invalid original scope fails closed |
| RC06 | Same logical scope CREATE/CREATE: positive cycle and legacy NULL-cycle variants | No implicit EDIT, no silent duplicate; source parent lock and data-safe normalized uniqueness policy demonstrated |
| RC07 | EDIT against DELETE and duplicate concurrent DELETE, using browser-origin original tokens | At most one valid mutation and explicit stale/not-found conflict; no double reversal |
| RC08 | Privileged Farm Admin and delegated Ruminant Manager DELETE | Both route through the same shared canonical transaction and permission/CSRF boundaries, with mortality reversal and stock rollback consistency |
| RC09 | Earlier-date correction changes later durable opening_stock/Layer laying_rate while later-date EDIT is open | Changed later row revision bumps once; older later-date editor conflicts, with no lost biological state |
| RC10 | Cycle opening record seed concurrently with cycle creation/baseline and retry | Exactly one revision-1 opening record committed with its cycle; failure rolls back caller transaction |
| RC11 | Inject failure after CAS and between downstream services | Rollback restores source revision, feed, stock, mortality, later rows and Layer pooled allocations to before state |
| RC12 | Lock-order contention with stock consumption allocation and projection writer | No silent overwrite or incomplete transaction; known deadlock/timeout is reported as FAIL, not accepted as stale conflict |
| RC13 | Reject ambiguous legacy NULL-cycle duplicates and cross-farm cycle links | No guessed row, no silent dedupe, no "EDIT becomes CREATE" on 404/409/timeout |
| RC14 | No-op EDIT, revision overflow boundary and unsupported animal type/column | Explicit contract result; no false success, wraparound, bypass or cross-species mutation |

Scope RC01-RC14 is an acceptance plan, not a claim of fixture availability, implementation, PASS count or production test run. Each test must read identical loaded token BEFORE the race barrier, exercise the authoritative production service (not a test-patched SQL UPDATE), and assert after COMMIT from an independent read connection.

## 5. Browser and HTTP acceptance (separate, authenticated)

Test each of Layer/Broiler/Ruminant and representative farm roles against genuinely rendered pages, not fake DOM alone. Assert exact GET origin row token; explicit POST CREATE vs EDIT; 401/403/409/419/422/500/503/HTML/redirect/network denial; current-ticket population boundary; draft retained after cancelled selection and 409; no automatic stale retry; Ruminant species/units; Layer derived fields; selected-cycle and top-header/calendar navigation; original-row DELETE tokens; native data-confirm retired in B12; only one submit and one DELETE owner. Gate-OFF legacy parity and a separately approved coherent ON integration must be certified in isolated environments; never enable ON piecemeal just for a browser test.

## 6. Evidence required to say repaired B12 PASSED

- An approved isolated MariaDB fixture whose identity/schema are attested, with explicit independence from customer data; fresh one-use approval provenance.
- Exact branch/commit/code-source SHA guards and a nontruncated changed-file inventory.
- A new test harness that invokes production shared revision services with at least two actual distinct DB connections; deterministic race ordering evidence.
- Per-test winner/loser classification, actual stored revisions, cross-tenant controls, affected downstream ledger snapshots, rollback/teardown results, and zero unexpected commits.
- Full focused native source tests for code affected, fresh MariaDB run logs, authenticated browser integration evidence, post-run cleanup and remote semantic diff after any source commit.
- Separate migration authorization, runtime deployment controls and rollback before a production release; no old failed run may be relabeled as PASS.

Never count pure PHP/Node mock assertions, source checks or R2/FK synthetic checks as independent-worker repaired CAS certification. A current full offline suite PASS must be supported by its own run output; do not infer it from separately passed focused phases.

## 7. Operational stop and next dependency

The next database-dependent prerequisite is an independently verified read-only isolated clone audit of actual schema/aggregate legacy data, using docs/C137_GC24_K2_P03_B12_READONLY_SCHEMA_INVENTORY.sql and scripts/k2p03_b12_schema_readonly_gate.php under separate explicit environmental authorization. The 11-section inventory is preferred over the alternate row-level SQL.txt diagnostic; do not print identifiable tenant rows or secrets. Foreign-key metadata may be hidden from limited users; preserve the fixed-identifier SHOW CREATE TABLE attestation rather than mistaking an empty metadata set for absent FKs. Verify trigger-metadata visibility separately if needed.

Until those prerequisites exist, B12 cutover = OFF; no migrations, CI, clone creation, DB queries, authenticated browser tests, AWS capacity work, or production deployment are authorized from this contract. D12 15 import gates BLOCKED; Stage D NOT AUTHORIZED; 150VU HOLD; 200VU PROHIBITED.

Document outcome: a traceable source-only acceptance design has been prepared. ZERO RC01-RC14 runtime cases executed, ZERO implementation-source files changed, and the original B12 failure remains unresolved.
