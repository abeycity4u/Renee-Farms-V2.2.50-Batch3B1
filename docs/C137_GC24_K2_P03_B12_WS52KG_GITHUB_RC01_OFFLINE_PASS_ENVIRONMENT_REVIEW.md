# C137 / GC24 / K2-P03 / B12 — WS52-KG GitHub-hosted RC01 offline-only PASS; MariaDB environment design review

**Date:** 2026-10-15  
**Scope authorized:** Source-only verification of newly staged GitHub-hosted, disabled RC01 candidate: seven fixture-free Python tests; PHP **8.2** syntax lint; exact hard-disabled CLI exit-code **90** and stderr; design-only MariaDB 10.11 environment/cleanup requirements. **No** SQL, PHP RC01 worker, MariaDB service, Docker, production/staging contact, historical customer backup read/import or migration.  
**Outcome:** `WS52KG_OFFLINE=PASS`, `PYTHON_SOURCE_TESTS=7/7_PASS`, `GH_CANDIDATE_PHP82_LINT=PASS`, `DISABLED_EXIT90=PASS`, `MARIADB_10_11_RUNTIME_IMAGE_DIGEST=UNVERIFIED`, `ENVIRONMENT_RUN_APPROVAL=NOT_GRANTED`, `PRELIMINARY_RC01_EXECUTED=NO`, `REPAIRED_RC01_RC14_CERTIFIED_PASSES=0`, `B12_CUTOVER=HARD_OFF`.

## 1. Source and one-use GitHub Actions provenance

| Item | Exact independently grounded reference |
|---|---|
| Repository | `abeycity4u/Renee-Farms-V2.2.50-Batch3B1` |
| Source parent WS52-KF disabled branch | `c137-gc24-k2p03-b12-ws52kf-github-rc01-candidate-disabled` |
| Source parent commit | `99538d136d06228fdff1351b676af828feb9942c` |
| WS52-KG verification branch | `c137-gc24-k2p03-b12-ws52kg-gh-rc01-offline-verification` |
| Dedicated offline workflow | `.github/workflows/c137-b12-ws52kg-php82-rc01-hardoff-once.yml` Git blob `9d535dc67520724e7422ace5147b6c0e08c37416` |
| Workflow preparation commit | `e1c925fb5b227af0d21e400a6fd0debdc439dc9c` |
| Only approval file | `docs/C137_GC24_K2_P03_B12_WS52KG_ONE_USE_OFFLINE_APPROVAL.txt` |
| Explicit one-use approval commit | `aa4551a5ec14722a9268292b2769b057ba262d6f` |
| GitHub Actions run | **`38788384262`**, run #1 / attempt #1, **completed / success**, event `push` |
| Job | `116606223258`, `php82-gh-rc01-source-only`, **completed / success** |
| Run evidence URL | `https://github.com/abeycity4u/Renee-Farms-V2.2.50-Batch3B1/actions/runs/38788384262` |
| Pinned setup-PHP provider action | `shivammathur/setup-php@eb7c497e18156a6bbabfef1d3a82760b9eda3962` |
| GH PHP RC01 candidate (unchanged) | `tests/integration/k2p03_b12_gh_rc01_preliminary_synthetic.php`, blob **`e78318509e270ccd24684bf41f5e854b74765f76`** |
| Original Mac RC01 harness (unchanged) | `tests/integration/k2p03_b12_repaired_rc01_real_mariadb.php`, blob **`6f17d1a4ba7e98dbf286d1c8e1a147e6ac3e4aad`** |
| New GH candidate verifier (unchanged) | `tests/test_b12_ws52kf_gh_rc01_disabled_source.py`, blob **`c75c62216b6557eb58aeb82a2ee5e5f4c325560c`** |
| Source-only disabled GH candidate workflow (unchanged) | `.github/workflows/c137-b12-ws52kf-rc01-gh-candidate-disabled.yml`, blob **`f59974c2e626eed8d16f3dd73fbd181c1c63837f`** |
| Shared CAS authoritative service (unchanged) | `lib/daily_record_revision_authority.php`, blob `5fbb90903b0cc801be45c684f6c3db85c660979a` |
| Writer hard-OFF gate (unchanged) | `lib/daily_record_revision_cutover_gate.php`, blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268` |
| Protected original B12 branch | `c137-gc24-k2p03-b12-cas-writer-cutover-source` at `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af` |
| Protected production branch | `v320-renee-agrisuite-branding` at `10041da3a3c4e4a7b8101f8183c243d056c7b128` |

**Workflow isolation:** It runs only after the *one-use approval-file path* is committed on the exact new WS52-KG branch with exact approval message. The job checks `run_number=1` and `run_attempt=1` and GitHub repository/branch identity; `permissions: {}`, **no** checkout of live product data, no environment secrets, no `services:`, no SQL client, no `workflow_dispatch`, no `pull_request`, no database or Docker access. It retrieves **four exact source files** from immutable public GitHub commit `99538d...` and rehashes each Git blob before running offline checks. This job did **not** enable either PHP harness; the original WS52-KF workflow remains nontriggerable with `if: ${{ false }}` and missing approval file.

## 2. Independently inspected decoded GitHub job log

The GitHub API reported run `38788384262` and job `116606223258` successful; each execution step concluded `success`. The assistant also read the job's decoded logs and verified the exact output markers (GitHub timestamp prefixes omitted below):

```text
#####COPY FROM HERE#####
AUDIT=B12_WS52KG_GH_RC01_OFFLINE_ONLY
FOUR_SOURCE_BLOBS=PASS
GH_RC01_EXECUTION_FUSE=OFF
PYTHON_SOURCE_CONTRACT=PASS_7_OF_7
PHP82_CLI=PASS
PHP82_LINT=PASS
HARD_OFF_SMOKE=PASS_EXIT_90
EXACT_EXPECTED_STDERR=PASS
GH_RC01_WORKERS_EXECUTED=NO
DATABASE_CONNECTIONS=0
DATABASE_SERVICES_CREATED=NO
SQL_EXECUTED=NO
REAL_BACKUP_READ=NO
MIGRATION_092_EXECUTED=NO
REPAIRED_RC01_RC14_CERTIFIED_PASSES=0
B12_CUTOVER=OFF
RESULT=PASS_OFFLINE_ONLY
#####COPY TO HERE#####
```

The seven genuine `unittest` tests ran and passed against the exact new candidate, original Mac harness and disabled workflow source. PHP **8.2** (`php -n`) linted the candidate successfully. The **new** 593-line candidate, previously untested by PHP 8.2, exited **90** with empty stdout and the exact hard-disable marker on stderr, without reaching its controller or worker code. The run did not exercise `PDO` or `INNODB_LOCK_WAITS`. A green source-only job is **not** a DB service proof, and no previously closed B01–B11/R2/FK QA was unnecessarily rerun.

## 3. MariaDB 10.11 ephemeral design: reviewed facts, still-open prerequisites

**Existing source-derived precedent:** The separate one-use **R2 synthetic schema** GitHub Actions run `38301126240` on its own isolated branch **PASS** used a GitHub-hosted MariaDB **`mariadb:10.11`** service with `127.0.0.1:3306`, a distinct synthetic schema `renee_b12_schema_synthetic_r2` and disposable nonsecret credentials. Its source passed fresh empty-schema and referenced FKs/NULL-cycle risk checks, but it is **not** a revised RC01 race, did not validate the GH candidate, and used a **mutable image tag rather than an independently verified image digest**.

**New GH candidate's exact source assumptions:** source blob `e78318509e270ccd24684bf41f5e854b74765f76` requires fixed branch `c137-gc24-k2p03-b12-ws52kf-github-rc01-candidate-disabled`, repository ref, GitHub-hosted runner, first attempt, `B12_RC01_GH_ISOLATION_ATTESTED=YES`, fixed `127.0.0.1:3306`, unique schema `renee_b12_gh_rc01_synthetic_only`, password length >=16 for an ephemeral `root` DB account, MariaDB version beginning `10.11.`, an expected `@@server_id`, exact own Git blob approval and a literally hard-OFF product source gate. The original **Mac-only** socket/attestation must **not** be spoofed. This GH source candidate remains literally `const B12_RC01_EXECUTION_ENABLED = false;`, and its unit-test parity suffix is byte-identical to the original Mac RC01 race and cleanup. **The future runtime version must not merely use `sed` to flip that constant at execution time.**

| Precondition | Current evidence / exact next gate |
|---|---|
| GitHub-hosted ephemeral runner | Offline job proves GitHub can execute PHP 8.2 there; does **not** attest a MariaDB runtime, node network or mounted volumes |
| MariaDB 10.11 image | `mariadb:10.11` has successful prior synthetic **version-family precedent**, but is a floating tag; **specific `mariadb@sha256:...` manifest for reviewed runner architecture remains UNVERIFIED**. Do not invent a digest or call it pinned |
| Network exposure | Prior R2 published service on `127.0.0.1:3306`; loopback-only exposure is **not the same as network-disabled**, nor proof of a networkless runner. Require an explicit independently reviewed network policy |
| Server identity | Future first-use environment guard needs ephemeral container ID, verified `@@server_id` and engine/server version; no current container or server ID exists. Ensure container ID, host/guest file mounts and output do not refer to existing WS32/private fixtures |
| Schema identity and DDL | Verify truly empty fresh 69-table base schema from pinned `database_schema.sql` blob `e77f287d62c5812a3d55dc23b5b20f5d167db389`. Verify base 883 columns **from new attestation rather than assuming R2 proves it**, then allow only three specifically reviewed **test-schema-only** `record_revision BIGINT UNSIGNED NOT NULL DEFAULT 1` ALTERs to reach expected 886. Under no circumstances run production migration 092 or generic `scripts/run_migrations.php` |
| DB privilege surface | Existing RC01 candidate uses **root**. Full MariaDB `information_schema.INNODB_LOCK_WAITS` metadata may require privileged observer access; do not assume a limited account can see locks or silently substitute separate unreviewed UPDATEs. Separate least-privileged write/test account and suitably scoped observer would require another reviewed source change |
| New test-only fixture | After independently attested empty schema, allow only one fabricated farm, one cycle and one Layer record under exact scoped IDs. Zero real customer data, secrets, production backup or foreign volume mounts |
| Race oracle | Two independently verified PDO connection IDs must load same record revision 1 **before** contention; A acquires InnoDB row lock, B's actual wait on A is observed by a third connection using server metadata; release and prove exactly one commit + one canonical stale conflict, winner-only data, revision 2. Deadlock/timeout/unknown errors are **FAIL**, not stale |
| Failure and teardown | Cap total job and worker lifetime; signal ABORT, terminate/attest child processes; clean only the three exact fabricated rows; repeat zero-row metadata checks; then dispose of only the exact ephemeral DB instance/image resources. If any cleanup or identity guard fails, **quarantine and block**; do not blindly delete a database or automatically rerun. Preserve sanitized failure classification without printing credentials or records |
| Authorship/one-use approval | A future reviewed source commit and **new one-use approval** must name exact harness blob, workflow blob, digest-pinned MariaDB image, ephemeral fixture identities, schema-only DDL, worker operations, runtime timeouts, observer grants and disposal. General "proceed", WS52-KG's consumed approval and old B12 failed authorization are insufficient |
| Formal certification | Current contract §§3 and 7 independently requires an actual legacy-schema/NULL-cycle read-only audit before repaired RC01–RC14 **certification**. A hypothetical fabricated-only RC01 result is **PRELIMINARY/NON-CERTIFYING** and does not waive that requirement |

**Explicit environment conclusion:** MariaDB 10.11 as an engine family is a grounded **candidate**, not yet a **pinned executable environment**. No immutable MariaDB image digest, exact container/server/runtime identity, permission/observer contract or complete disposal attestation was established during WS52-KG. This is an *unresolved runtime gate*, not an offline test failure.

**Recommended next controlled step WS52-KH (not yet authorized):** A **source-only** design and implementation pass for an independently guarded **new ephemeral synthetic MariaDB 10.11** service bootstrap/teardown contract and narrowly revised GH candidate, keeping runtime fuses OFF. Prefer digest-pinned image, fresh source-pin preflight, only exact test schema/DDL and no privileged root race unless isolated/justified, closed artifact/log outputs, and a **strict one-use race trigger that does not exist or run until separately authorized**. Offline source verifiers should be focused; no actual DB image pull, SQL or Docker service action is implied. If digest cannot be verified, leave runtime blocked and document what independent attestation will supply it.

## 4. Standing risk/release locks

- Original B12 GitHub real MariaDB race `38233373699` **FAILED**: `TWO_STALE_COMMITS`. Repaired **RC01–RC14 certified passes remain 0**. This KG success does not recertify the old run.
- Real legacy-schema/NULL-cycle and cross-farm row audit on an independently isolated clone remains **not completed**; fabricated-data success cannot prove actual tenant uniqueness or migration safety.
- Historic production-derived backup remains **NO-GO for import** (client-effective SQL parsing, sensitive-data provenance, encrypted storage/retention and isolation gates not all met).
- `MIGRATION_092=NOT_AUTHORIZED`; `D12_IMPORT_GATES=15_BLOCKED`; `STAGE_D=UNAUTHORIZED`; `150_VU=HOLD`; `200_VU=PROHIBITED`; `B12_CUTOVER=HARD_OFF`.
- No source/product/migration/old B12 runner changes made; no DB, Docker, customer backup or production contact occurred. No background monitoring or recurring Actions job was created.

```text
AUDIT=B12_WS52KG_GH_RC01_OFFLINE_VERIFICATION
RESULT=PASS_OFFLINE_ONLY
GITHUB_RUN=38788384262
ONE_USE_RUNS=1
FOUR_PINNED_FILES=PASS
PYTHON_TESTS=7_OF_7_PASS
NEW_GH_RC01_PHP82_LINT=PASS
HARD_OFF_EXIT_90=PASS
MARIADB_IMAGE_DIGEST=UNVERIFIED
PHYSICAL_DB_IDENTITY=NOT_ESTABLISHED
RC01_WORKERS_EXECUTED=NO
SQL_EXECUTED=NO
REAL_BACKUP_READ=NO
HISTORICAL_RESTORE=NO_GO
REPAIRED_RC01_RC14_CERTIFIED_PASSES=0
MIGRATION_092=NOT_AUTHORIZED
B12_CUTOVER=HARD_OFF
```

## 5. Independent GitHub semantic-diff acceptance

Compare the new WS52-KG branch with its exact source parent commit `99538d136d06228fdff1351b676af828feb9942c`. The **only three permitted added files** are (1) `.github/workflows/c137-b12-ws52kg-php82-rc01-hardoff-once.yml`, (2) `docs/C137_GC24_K2_P03_B12_WS52KG_ONE_USE_OFFLINE_APPROVAL.txt`, and (3) this Markdown evidence/design report `docs/C137_GC24_K2_P03_B12_WS52KG_GITHUB_RC01_OFFLINE_PASS_ENVIRONMENT_REVIEW.md`. No source/production/old workflow or dormant candidate change, no new approval-path event from the report commit. Verify `ahead=3`, `behind=0`, **exactly one completed successful WS52-KG workflow run**, and original/production HEADs, shared CAS, HARD-OFF gate, GH PHP candidate, Mac RC01 runner, source verifier and dormant GH workflow Git blobs unchanged.
