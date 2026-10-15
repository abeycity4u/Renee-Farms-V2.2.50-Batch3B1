# C137 / GC24 / K2-P03 / B12 — WS52-KH disabled RC01 isolation/cleanup source policy

**Date:** 2026-10-15  
**Authorization:** User approved *source-only* design and implementation of database isolation and guarded cleanup controls for a **preliminary, fabricated-data, non-certifying** RC01 experiment. This did **not** authorize an image pull, MariaDB/Docker/SQL run, runtime CAS workers, migration, historical backup or production contact. Offline Python tests on an ephemeral GitHub runner were within the source-only verification scope.  
**Outcome:** `WS52KH=PASS_OFFLINE_ONLY`; `FIXTURE_FREE_TESTS=12_OF_12_PASS`; `PHYSICAL_ISOLATION=NOT_ATTESTED`; `REVIEWED_MARIADB_IMAGE_DIGEST=NONE`; `ONE_USE_RUNTIME_AUTHORIZATION=NONE`; `RC01_EXECUTED=NO`; `B12_CUTOVER=HARD_OFF`.

## 1. Exact source lineage and changed-file scope

| Identity | GitHub reference |
|---|---|
| Repository | `abeycity4u/Renee-Farms-V2.2.50-Batch3B1` |
| Parent WS52-KG branch | `c137-gc24-k2p03-b12-ws52kg-gh-rc01-offline-verification` |
| Parent HEAD | `d0366e0e94c7c25267eb59c236ee813e6da7428a` |
| New WS52-KH branch | `c137-gc24-k2p03-b12-ws52kh-rc01-isolation-cleanup-disabled-source` |
| Added pure source-only policy | `tools/b12_ws52kh_rc01_isolation_cleanup_contract.py` blob **`f5f9b82453fae41cc8bcc070414d988f052c5e14`** |
| Added focused fixture-free verifier | `tests/test_b12_ws52kh_rc01_isolation_cleanup_contract.py` blob **`b2227d659a15999f25fcfc8ae4e4bdff68ac8444`** |
| Added one-use **offline-only** workflow | `.github/workflows/c137-b12-ws52kh-policy-source-only-once.yml` blob **`0918c68edcea19d0add62cc400eed81affadfda8`** |
| Added one-use offline approval | `docs/C137_GC24_K2_P03_B12_WS52KH_ONE_USE_OFFLINE_APPROVAL.txt` |
| One-use approval commit | `542b57e5f0af31d067c74766ab042fe73e8781b4` |
| Successful Actions run | **`38789819994`**, run 1/attempt 1, `completed/success` |
| GitHub run URL | `https://github.com/abeycity4u/Renee-Farms-V2.2.50-Batch3B1/actions/runs/38789819994` |
| Successful Actions job | **`116611166462`** (`synthetic-policy-source-contract`) |
| Existing disabled GH RC01 PHP candidate (unchanged) | `tests/integration/k2p03_b12_gh_rc01_preliminary_synthetic.php` blob `e78318509e270ccd24684bf41f5e854b74765f76` |
| Existing hard-disabled GH RC01 workflow (unchanged) | `.github/workflows/c137-b12-ws52kf-rc01-gh-candidate-disabled.yml` blob `f59974c2e626eed8d16f3dd73fbd181c1c63837f` |
| Existing original Mac RC01 harness (unchanged) | `tests/integration/k2p03_b12_repaired_rc01_real_mariadb.php` blob `6f17d1a4ba7e98dbf286d1c8e1a147e6ac3e4aad` |
| Shared CAS authority (unchanged) | `lib/daily_record_revision_authority.php` blob `5fbb90903b0cc801be45c684f6c3db85c660979a` |
| Hard-OFF product gate (unchanged) | `lib/daily_record_revision_cutover_gate.php` blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268` |
| Protected original B12 branch | `c137-gc24-k2p03-b12-cas-writer-cutover-source` at `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af` |
| Protected production branch | `v320-renee-agrisuite-branding` at `10041da3a3c4e4a7b8101f8183c243d056c7b128` |

The WS52-KH branch adds exactly **five new files** (the source policy module, its tests, one-use offline workflow, consumed offline-only approval file and this report), with no modifications to existing PHP tests, application services, schema, migrations, old CI workflows or protected original/production branches. No images, services, SQL or databases were created.

## 2. Source-only controls implemented

### Isolation preflight contract — pure Python, fail-closed

`tools/b12_ws52kh_rc01_isolation_cleanup_contract.py` is **intentionally not an executable Docker inspection wrapper**. It accepts only closed-shape, redacted proposed evidence and returns `PolicyResult(structure_valid, blockers, execution_authorized=False)`. It has **no subprocess, network, SQL or filesystem mutation path**, and never imports/calls production PHP. Its unconditional literal gates are:

```python
RC01_EXECUTION_ENABLED = False
APPROVED_MARIADB_IMAGE = None
APPROVED_RUNTIME_COMMIT = None
APPROVED_ONE_USE_RUN = None
```

It requires a **digest-form** OCI image reference `mariadb@sha256:<64 lowercase hex>` matching a *separately reviewed* expected digest, plus local image ID, container ID and a hashed Docker Engine identity, with no privileged container, no host/shared mounts and exactly one declared `127.0.0.1:3306 -> 3306/tcp` bind. It checks the proposed network mode `bridge` and demands a separate claimed restricted-egress attestation, not incorrectly equating loopback *publishing* to disabled outbound networking. It rejects malformed image identities, mutable `mariadb:10.11` tags, missing attestation, alternate network modes, port exposure, additional fields or embedded secrets. The submitted Git source SHA, CAS/gate/source-schema blobs, repository, exact source-branch provenance and run 1/attempt 1 must match fixed known values.

The metadata **shape contract** also checks a declared MariaDB **10.11.x** version, positive server ID and fixed disposable schema name `renee_b12_gh_rc01_synthetic_only`. The expected post-bootstrap synthetic schema layout is **69 base tables, 886 columns, three `record_revision` columns, three `SET NULL` cycle foreign keys**, and zero nonempty tables/views/triggers/routines/events. These counts are **not** claims that such a schema exists now; actual table contents, FK metadata visibility and the source baseline must be measured on the later, physically verified synthetic-only fixture.

**Critical trust boundary:** Any caller can fabricate a metadata dictionary. `structure_valid=True` proves only the *format and internal consistency* of an input, **not** physical container/engine provenance, real MariaDB privileges, egress policy, source authenticity or genuine SQL preflight evidence. There is currently **no reviewed image digest**, so the default entry point remains blocked even for well-formed fabricated metadata. Tests inject a clearly fictional digest for **shape-only simulation**. `execution_authorized` is **always false** even if the shape tests pass. A future runtime job requires an independent, reviewed *actual* Docker/host inspector, pinned digest and separately approved exact workflow/runner source.

### Teardown evidence contract — exact synthetic IDs, no DELETE executor

`assess_cleanup_evidence()` accepts a closed shape containing exact pre-approved **synthetic-only container**, farm, production cycle and Layer row identities. It enforces a `b12-rc01-<16 hex>` farm slug and `B12 RC01 disposable synthetic farm` literal name, exact identity-matched `deleted_layer_rows = deleted_cycle_rows = deleted_farm_rows = 1`, and reported zero remaining business rows, running workers and open transactions. It requires separately attested removal of the ephemeral container/volume (or explicit no-volume disposal equivalent) and barrier artifacts, plus `cleanup_status=INDEPENDENT_POSTCHECK_PASS`.

Missing/mismatched IDs, deleted-row counts other than one, a surviving worker, open transaction, residual rows, unremoved container/volume, a failed barrier directory cleanup or an unverified independent postcheck yields **`BLOCKED_MANUAL_REVIEW`**. It neither issues SQL nor calls `docker rm`, `rm`, `kill` or similar. A later exact-ID physical cleanup implementation will need its **own** read-only host/container attestation and tightly scoped deletion approval, with quarantine on unknown state; do not blindly drop an unidentified schema, container or volume. A forged `INDEPENDENT_POSTCHECK_PASS` string is **not authoritative evidence by itself**.

### Python-focused negative tests

The twelve fixture-free `unittest` cases verify the immutable hard-OFF gates; mandatory reviewed digest; **denial** of floating/mismatched images; denial of host mounts, privileged containers, host network, open ports or missing egress attestation; exact repo/branch/source and one-use guards; disallowance of secrets/unknown metadata keys; denial of wrong revision table counts, existing rows and triggers; precise deletion-ID matching; denial of surviving worker/unfinished cleanup; boolean/int type confusion; and a deliberately *fictional* positive-shape case that **still never authorizes execution**.

All test fixture image strings, container IDs, server IDs and record IDs are **invented and synthetic**, not discovered environment identities. There is **no claim that any physical MariaDB instance satisfies this policy**.

## 3. Independently verified offline GitHub Actions run

The source-only workflow is restricted to a path-specific one-use approval commit on this exact branch, with exact approval message and GitHub `run_number == 1`, `run_attempt == 1`; it has `permissions: {}`, **no database service, no Docker action, no secrets or checkout of product files**, and no broad dispatch/PR trigger. It fetches only the two exact Python files at immutable Git commit `3172ffec06a61c532f8635f90e07bf0d47fd2d1b`, verifies both Git object hashes, and runs only `python3 -B -m unittest discover` with the focused test selector.

**Run and job:** `38789819994` / `116611166462`, **completed/success**. Independently fetched decoded job logs contain the following exact sanitized evidence:

```text
#####COPY FROM HERE#####
AUDIT=B12_WS52KH_POLICY_SOURCE_ONLY
PINNED_PURE_POLICY_AND_TEST_BLOBS=PASS
FIXTURE_FREE_SOURCE_TESTS=PASS_12_OF_12
REVIEWED_MARIADB_IMAGE_DIGEST=NOT_ESTABLISHED
RUNTIME_APPROVAL=NOT_GRANTED
RC01_EXECUTION_FUSE=OFF
DATABASE_CONNECTIONS=0
DATABASE_SERVICES_CREATED=NO
DOCKER_OPERATIONS=NO
SQL_EXECUTED=NO
REAL_BACKUP_READ=NO
RC01_WORKERS_EXECUTED=NO
MIGRATION_092_EXECUTED=NO
REPAIRED_RC01_RC14_CERTIFIED_PASSES=0
B12_CUTOVER=OFF
RESULT=PASS_OFFLINE_ONLY
#####COPY TO HERE#####
```

The status is **verified** from both the GitHub run/job outcome and its decoded job logs, not inferred from the presence of the test file alone. `12/12 PASS` means *offline Python policy tests only*; no PHP RC01 source change, PHP 8.2 re-lint, server introspection, external fixture test or SQL.

## 4. Unresolved physical environment and execution gates

| Gate | Current state | Next separately controlled action |
|---|---|---|
| Immutable MariaDB image | **Not known**. `mariadb:10.11` in historical synthetic R2 is a **mutable** tag; no immutable image digest claimed | Discover and independently verify architecture-compatible OCI manifest digest in an explicitly approved runtime/registry-read-only step; record actual digest, not an example/test literal |
| GH ephemeral runtime / Docker Engine | **Not inspected** | Pin the future one-use GitHub runner and verify genuine Docker Engine/container ID, unprivileged state, mounts, image digest and network configuration from authoritative host/runtime APIs; env flags and JSON strings alone are insufficient |
| Network and database privileges | **Not certified** | Choose loopback publication versus isolated Unix socket and explicitly prove egress restrictions. Review root vs least-privileged synthetic actor and privileged observer's `INNODB_LOCK_WAITS` visibility; no customer/admin credentials |
| Baseline and revision DDL | **Not run** | Fresh unique synthetic schema from immutable `database_schema.sql`, exactly three synthetic `record_revision` ALTERs **only after** fresh nonproduction empty-schema identity proof; never execute migration 092 or the generic production migration runner |
| Real two-writer transaction and lock wait | **Not run** | Fresh exact SHA, one-use authorization for two actual MariaDB sessions and third observer; timed deterministic barrier, exact source CAS, one COMMIT, one stale conflict, revision +1. Fail on deadlock/timeout or unobserved lock |
| Exact cleanup and quarantine | **Not executed** | Future executable teardown must use recorded exact synthetic IDs and independently check zero tables/worker sessions; failed cleanup must return `MANUAL_REVIEW` and halt further tests, not relabel PASS |
| Release acceptance | **BLOCKED** | Independently isolated actual legacy-schema/NULL-cycle/cross-farm **read-only** audit still required before formal repaired RC01–RC14 certification; preliminary fabricated-only exercise, even if green, is not a substitute |

**Recommended next WS52-KI (separate approval):** A narrowly scoped, **read-only image-identity and synthetic environment attestation planning** step to resolve the immutable image digest and decide real host-side inspector/teardown provenance, **without** pulling/starting a MariaDB container or running SQL. Only then stage a reviewed executable wrapper and obtain a **new exact one-use** runtime/test/cleanup approval. Do not authorize GH runner execution by editing `RC01_EXECUTION_ENABLED` inside a workflow or faking `B12_RC01_GH_ISOLATION_ATTESTED=YES`.

## 5. Existing B12 release and legacy constraints unchanged

- Historic original B12 GitHub MariaDB run `38233373699`: **FAILED, TWO_STALE_COMMITS**. Its consumed one-use approval is not reusable. Corrected **RC01–RC14 certified runtime passes = 0**.
- Prior WS52-KD / WS52-KG PHP 8.2 hard-disabled syntax/exit-90 checks remain historically PASS on their respective exact old Mac/new GH harness blobs; no need to rerun them without source drift.
- The actual legacy-schema and NULL-cycle data audit is still **NOT established**; no inference from earlier synthetic R2/FK results to customer data.
- Historical production backup import/restore = **NO-GO**. No production/staging, Mac protected fixture, AWS capacity or customer data accessed under WS52-KH.
- `MIGRATION_092=NOT_AUTHORIZED`; `D12_IMPORT_GATES=15_BLOCKED`; `STAGE_D=UNAUTHORIZED`; `150_VU=HOLD`; `200_VU=PROHIBITED`; `B12_CUTOVER=HARD_OFF`.

## 6. Independent remote semantic-diff acceptance

Against exact parent `d0366e0e94c7c25267eb59c236ee813e6da7428a`, require **five added paths and zero modifications/deletions**: source-only Python policy, its focused unit tests, dedicated offline-only GitHub workflow, consumed offline approval text and this Markdown report. Confirm exactly one successful WS52-KH GitHub Actions run (no rerun), no changes to existing candidate/old harness/old workflows/schema/migrations/shared authority/HARD-OFF gate, and protected original B12 and production branch HEADs unchanged. Report commit must **not** match approval path or start another job.

```text
AUDIT=B12_WS52KH_ISOLATION_CLEANUP_SOURCE_POLICY
SOURCE_POLICY=STAGED_HARD_DISABLED
OFFLINE_SOURCE_TESTS=12_OF_12_PASS
GH_RUN=38789819994
GH_RUN_RESULT=SUCCESS
DOCKER_ENGINE_IDENTITY=NOT_ESTABLISHED
MARIA_DB_IMAGE_DIGEST=NOT_ESTABLISHED
PHYSICAL_FIXTURE=NOT_CREATED
DATABASE_CONNECTIONS=0
SQL_EXECUTED=NO
RC01_WORKERS=NOT_EXECUTED
ONE_USE_RUNTIME_APPROVAL=NOT_GRANTED
REPAIRED_RC01_RC14_CERTIFIED_PASSES=0
HISTORICAL_RESTORE=NO_GO
MIGRATION_092=NOT_AUTHORIZED
B12_CUTOVER=HARD_OFF
```
