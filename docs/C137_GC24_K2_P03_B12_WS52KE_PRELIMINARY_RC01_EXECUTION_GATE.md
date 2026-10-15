# C137 / GC24 / K2-P03 / B12 — WS52-KE preliminary fabricated-data RC01 execution preflight

**Date:** 2026-10-15  
**Disposition:** `PREFLIGHT=COMPLETE`, `PRELIMINARY_RC01_EXECUTION=BLOCKED_PENDING_EXACT_ONE_USE_APPROVAL_AND_RUNNER_ADAPTATION`, `CURRENT_RC01_FUSE=OFF`, `REPAIRED_RC01_RC14_CERTIFIED_PASSES=0`, `HISTORICAL_RESTORE=NO_GO`, `B12_CUTOVER=HARD_OFF`.

## 1. User intent and immutable authorization boundary

The user replied **"proceed"** to a proposal to consider a *preliminary, non-certifying* fabricated-data Layer RC01 stale EDIT/EDIT concurrency experiment ahead of the independently required actual legacy-schema read-only audit. This indicates permission to prepare this experiment **without waiving the real legacy-data gate**. The existing repaired CAS certification contract, however, explicitly requires a fresh **one-use runtime permission** naming the host/database, exact reviewed runner revision, fixture operations, worker execution and teardown. It says a generic "proceed" does **not** authorize database execution.

Therefore this WS52-KE stage is **source and design only**: no MariaDB, Docker, GitHub Actions run, backup, customer data, migration, production/SSH/cPanel or AWS operation. No execution fuse, protected source, product code, schema or test runner was modified. No expired old B12 CI approval may be reused. A future preliminary synthetic test, even if PASS, is **non-certifying**, and does not count toward formally repaired RC01–RC14 acceptance until the contractual prerequisite is met.

## 2. Verified GitHub source checkpoints

| Item | Identity |
|---|---|
| Repo | `abeycity4u/Renee-Farms-V2.2.50-Batch3B1` |
| Parent WS52-KD branch | `c137-gc24-k2p03-b12-ws52kd-php82-offline-gha` |
| Parent HEAD | `a4ba78f84cd025103820afde8416e60cdff7937c` |
| New docs-only preflight branch | `c137-gc24-k2p03-b12-ws52ke-preliminary-rc01-execution-preflight` |
| Protected original B12 HEAD | `c137-gc24-k2p03-b12-cas-writer-cutover-source` @ `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af` |
| Protected production HEAD | `v320-renee-agrisuite-branding` @ `10041da3a3c4e4a7b8101f8183c243d056c7b128` |
| Reviewed, dormant RC01 harness | `tests/integration/k2p03_b12_repaired_rc01_real_mariadb.php` Git blob `6f17d1a4ba7e98dbf286d1c8e1a147e6ac3e4aad` |
| Offline fixture-free Python verifier | `tests/test_b12_ws52ka_rc01_offline_contract.py` blob `7ea282ae90d132eab5b1522b3f6e736a69949edd` |
| Shared CAS authority | `lib/daily_record_revision_authority.php` blob `5fbb90903b0cc801be45c684f6c3db85c660979a` |
| Literal hard-OFF cutover gate | `lib/daily_record_revision_cutover_gate.php` blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268` |
| Revision schema plan | `lib/daily_record_revision_schema_plan.php` blob `a0ddbec542e891d484b48d1adbdac17442eaadf8` |
| Base SQL schema | `database_schema.sql` blob `e77f287d62c5812a3d55dc23b5b20f5d167db389` |
| Repaired CAS certification contract | `docs/C137_GC24_K2_P03_B12_REPAIRED_CAS_CERTIFICATION_CONTRACT.md` blob `606c7a411f046aecd81e304e1d1341407068516c` |
| Prior successful PHP 8.2 offline-only run | GitHub Actions `38769348137`, run 1/attempt 1, `completed/success`, job `116540775540` |

Prior PHP 8.2 job independently verified the exact dormant harness syntax, literal disabled exit code **90**, exact stderr, and absence of database work; original Mac's seven Python source-contract tests passed. These do **not** constitute any actual real MariaDB RC01 test. The historical B12 one-use test `38233373699` remains a FAILED `TWO_STALE_COMMITS` result. The repaired certified RC01–RC14 count remains **0**.

## 3. Readiness findings: why a generic GitHub CI launch would be invalid

The 566-line reviewed RC01 harness is explicitly **hard-disabled** at line 16 with `const B12_RC01_EXECUTION_ENABLED = false;`; its top-level disabled path exits 90 before worker/controller dispatch. A running preliminary experiment would require a **separate reviewed test-only source revision and explicit one-use authorization naming its resulting SHA**, not a runtime sed replacement, an unreviewed alternate test script, or a CLI/environment bypass.

The runner also has **specific local Mac / Docker execution assumptions**:

- `B12_RC01_ISOLATED_MAC_ATTESTED=YES` must mean a true independently verified Mac/Docker fixture, not an arbitrary boolean set on a GitHub-hosted runner. The PHP code itself checks the flag and a pinned `B12_RC01_APPROVED_HARNESS_BLOB`, but cannot by itself attest the Docker engine or mounted fixture provenance.
- `B12_RC01_SOCKET=/run/mysqld/mysqld.sock` and `B12_RC01_DATABASE=renee_b12_disposable_synthetic` are fixed. The suite expects **MariaDB 10.11.x** on that socket and a server-local empty **69-table/886-column** synthetic schema with exactly three revision columns, three cycle SET NULL foreign keys, zero prior business rows and zero stored programs. It uses nonblank, test-only root authentication under `B12_RC01_DB_PASSWORD`.
- GitHub-hosted Ubuntu 24.04 + MariaDB 10.11 **service container** is not interchangeable with the required socket, privileged user and Mac attestation; simply exporting the Mac flag on a GitHub job would fabricate an isolation claim. An Ubuntu host-installed MariaDB or a pair of ephemeral networkless containers is *only a candidate*, not a verified existing arrangement. Neither code path has been prepared, pinned, or approved for RC01 execution.
- The source revision must be independently reviewed under the "One fix, fit it all" approach: use the actual shared `daily_record_revision_write()`, no surrogate CAS SQL or product service modifications. One process holds an InnoDB row lock, the other demonstrates actual waiting using `information_schema.INNODB_LOCK_WAITS` and session IDs, then exactly one COMMIT, one genuine stale conflict, revision 2 and original revision tokens loaded before contention.
- Correct **physical fixture isolation**, strong test-only credentials, exact source input and schema hashes, no customer data/sensitive mounts, safe process shutdown and identity-scoped teardown need proofs. The source cleanup currently preserves private barrier files for manual review on uncertain failure; a fresh runner must define retention and teardown responsibility without hiding a failed test or silently deleting unverified resources.
- The source checks all 69 business tables have zero rows *before* inserting a fixture. To reach its expected synthetic 886-column layout, any test-only revision-column DDL must be confined to the new fabricated-only schema under a separate one-use approval. **Migration 092 remains unauthorized**; no production or historical data schema change is implied. The exact 10.11 image/engine, host environment and code references must be pinned before runtime.

**Critical contractual gate:** The repaired CAS certification contract §§3 and 7 requires read-only *actual legacy-schema / NULL-cycle collision* evidence from an independently isolated clone before **formal real-connection repaired RC01–RC14 certification**. A synthetic-first RC01 experiment can be separately approved as **preliminary/non-certifying** only; the real legacy-data obligation survives.

## 4. Narrowly actionable plan / fresh approval prerequisites

**Step A — Isolated source-only adaptation (not yet implemented):** Choose one environment, then prepare an independent **test-only**, source-reviewed and hard-OFF-by-default RC01 variant on a new branch. For a GitHub-hosted path, replace Mac-specific attestations with independently verifiable **GitHub ephemeral-runner / actual local fixture identity** checks instead of spoofing the Mac flag. Require an explicit reviewed fuse transition in a new commit; keep the **product** B12 writer cutover gate literally OFF and preserve the original disabled harness as immutable reference. Demonstrate PHP 8.2 CLI/pcntl-PDO requirements, the exact socket and MariaDB 10.11 **network-disabled** service, empty schema, no access to protected historical data and source/fixture provenance. The old one-use B12 workflow cannot be reused or rerun. No live DB work in Step A.

**Step B — One-use execution approval for an actual, pinned test** must explicitly identify all of the following: (1) the exact proposed runner and one-use workflow **commit SHA / Git blob**, (2) host or ephemeral runner and MariaDB 10.11 image/server fingerprint, (3) fixed new **fabricated-only** schema `renee_b12_disposable_synthetic` and any unique instance/volume identities, (4) test-only creation of schema and three `record_revision` columns, no `092` migration, (5) insertion/deletion of exactly one fabricated farm/cycle/layer record, (6) two independent worker sessions plus a third observer reading InnoDB waits, (7) hard wall-clock bound, lock/worker timeouts and redacted result schema, (8) separate guarded cleanup or quarantine for any incomplete step, and (9) precise **non-certifying** classification without waiving legacy audit or permitting production cutover. A generic "proceed" is not adequate under the existing contract.

**Step C — Execute once only after explicit Step B authorization:** Verify source and host runtime preflight before mutation; no unrelated database/schemas, no customer data, no accessible production secrets/host mounts or network. Run the bounded fabricated-only RC01 worker experiment once, fail closed on unobserved lock state, unexpected MariaDB error, deadlock or lock timeout. Verify posttest zero user rows and exact resource disposal. Preserve only redacted totals and exact image/source/test fingerprints. No automatic rerun, even if failure. A source-only PASS, PHP lint PASS, synthetic privilege smoke or successful Docker service startup must **not** be counted as corrected RC01 certification.

**Step D — Independently review** the actual run, failures and disposal, remote semantic diff and protected Git identities. Then return to the actual legacy read-only audit requirement before full RC01–RC14 certification; historical SQL restoration remains separately gated and NO-GO.

**Alternatives and decision:** If the named target environment cannot credibly meet source assumptions or identity guards, do not enable the RC01 fuse. A source-only design adjustment, followed by a new reviewed exact SHA and one-use approval, is preferable to adapting the existing Mac attestation flag by name alone. If a suitable networkless test runtime remains unavailable, record `PRELIMINARY_RC01=BLOCKED_ENVIRONMENT` rather than manufacturing a concurrency PASS.

## 5. Completion scope for WS52-KE

Exactly one new Markdown report `docs/C137_GC24_K2_P03_B12_WS52KE_PRELIMINARY_RC01_EXECUTION_GATE.md` on a new docs-only successor branch from parent `a4ba78f84cd025103820afde8416e60cdff7937c`. Independent remote compare must report one added report, one commit ahead, zero behind, and unchanged protected original/production branch HEADs, disabled RC01 runner Git blob, shared CAS source and writer hard-OFF gate. This document does **not** run or approve any SQL.

```text
AUDIT=B12_WS52KE_PRELIMINARY_RC01_PREFLIGHT
SOURCE_PRECHECK=PASS
ENVIRONMENT_SUFFICIENCY=NOT_ESTABLISHED
ONE_USE_DATABASE_EXECUTION_APPROVAL=NOT_YET_GRANTED_FOR_EXACT_RUNNER_AND_FIXTURE
PRELIMINARY_RC01_EXECUTED=NO
REPAIRED_RC01_RC14_CERTIFIED_PASSES=0
HISTORIC_B12_CI=TWO_STALE_COMMITS_FAIL
REAL_LEGACY_SCHEMA_AUDIT=BLOCKED
HISTORICAL_BACKUP_RESTORE=NO_GO
MIGRATION_092=NOT_AUTHORIZED
D12_IMPORT_GATES=15_BLOCKED
STAGE_D=UNAUTHORIZED
150_VU=HOLD
200_VU=PROHIBITED
B12_CUTOVER=HARD_OFF
```
