# C137 / GC24 / K2-P03 / B12 — WS52-KD one-use PHP 8.2 hard-disabled verification

**Date:** 2026-10-15  
**Status:** `WS52KD=PASS_OFFLINE_ONLY`; `PHP82_LINT=PASS`; `DISABLED_EXIT_CODE=90`; `HARD_DISABLED_STDERR_EXACT=PASS`; `B12_CUTOVER=HARD_OFF`.  
**Authorization:** User approved a new isolated GitHub-hosted PHP 8.2 verification job limited to the exact pinned RC01 harness syntax lint and literal disabled exit. No real database, Docker service, data restore, customer SQL, production access, existing one-use B12 CI rerun, migration, or execution-fuse change authorized.

## 1. Exact provenance and one-use trigger

| Field | Verified value |
|---|---|
| Repo | `abeycity4u/Renee-Farms-V2.2.50-Batch3B1` |
| New isolated WS52-KD branch | `c137-gc24-k2p03-b12-ws52kd-php82-offline-gha` |
| Parent WS52-KC HEAD | `29105ac45c9c318992575b70d764a1a618285297` |
| New workflow | `.github/workflows/c137-b12-ws52kd-php82-hardoff-once.yml` |
| One-use path | `docs/C137_GC24_K2_P03_B12_WS52KD_ONE_USE_OFFLINE_APPROVAL.txt` |
| Workflow staging commit (did not run) | `733ecffcfb6024cf99203a23122ed12c592d3492` |
| Approval commit (started one run) | `c94ae3f2451b46de52693b20c0e3da2f01b6ae81` |
| GHA run ID / attempt / number | `38769348137` / `1` / `1` |
| GitHub event | `push` on exact WS52-KD branch |
| GitHub run page | `https://github.com/abeycity4u/Renee-Farms-V2.2.50-Batch3B1/actions/runs/38769348137` |
| GitHub run outcome | `completed / success` (independently fetched from API) |
| Job ID/name | `116540775540` / `php82-hardoff-offline` |
| Job result | `completed / success`; setup-PHP and hard-OFF verification steps both `success` |
| Setup-PHP action | `shivammathur/setup-php@eb7c497e18156a6bbabfef1d3a82760b9eda3962` (pinned Git commit, verified from signed `v2` tag resolution) |
| Runtime | GitHub-hosted `ubuntu-24.04`, PHP **8.2 CLI** validated using `php -n -v`; full patch version not published in sanitized result |
| Exact linted source origin | Immutable raw URL at `1c373f9f730bec1d2908ee06be1a2e299ac17cb6`, `tests/integration/k2p03_b12_repaired_rc01_real_mariadb.php` |
| Exact Git blob verified inside job | `6f17d1a4ba7e98dbf286d1c8e1a147e6ac3e4aad` |
| Source-only Python tests | `7/7 PASS` in earlier user-attested Mac WS52-KB; not rerun in WS52-KD |

**One-use guard:** The new workflow has a `push` trigger for this branch **only** and a path filter for **only** the new approval file. Its job guard also checks the exact approval commit message, event, branch, `run_number==1`, and `run_attempt==1`. There is **no** `workflow_dispatch`, `pull_request`, wide push trigger, database service, checkout of product tree, environment secret, deployment step, or migration. Workflow permissions are `{}` (no explicit token permissions), PHP setup uses a pinned public action and empty action token input. It fetches **only** the exact publicly committed RC01 PHP file via pinned `raw.githubusercontent.com` URL and validates the Git object hash before running PHP. A future report/docs commit does not match the approval-path trigger. An attacker with repo write permission could still modify the workflow itself in a later commit; approval and branch protections remain a separate operational control. The reference run is the single authorized attempt, not a reusable blanket license.

## 2. Independently fetched job evidence

The GitHub workflow run and job were both fetched. The decoded job log contained the following consecutive sanitized output lines. GitHub timestamp prefixes were omitted here; no raw PHP source, SQL, database names, customer records, machine secrets, or unredacted exception text were exposed.

```text
#####COPY FROM HERE#####
AUDIT=B12_WS52KD_PHP82_HARDOFF_CI
GITHUB_JOB_SCOPE=ONE_USE_SOURCE_ONLY
EXACT_RC01_SOURCE_BLOB=PASS
PHP82_CLI=PASS
RC01_LITERAL_EXECUTION_FUSE=OFF
PHP82_LINT=PASS
PHP82_DISABLED_EXIT_CODE=90
HARD_DISABLED_STDERR_EXACT=PASS
PYTHON_7_TESTS=PREVIOUSLY_PASSED_NOT_RERUN
DATABASE_CONNECTIONS=0
DOCKER_OR_SERVICES_USED=NO
REAL_BACKUP_ACCESS=NO
MIGRATION_092_EXECUTED=NO
REPAIRED_RC01_RC14_CERTIFIED_PASSES=0
B12_CUTOVER=OFF
RESULT=PASS_OFFLINE_ONLY
#####COPY TO HERE#####
```

**Test mechanics and claim boundaries:**
- Exact source bytes obtained at immutable repo commit `1c373f...`; `git hash-object --no-filters` returned exactly `6f17d1a4...`. The workflow itself has no SQL client/database fixture. There was no copy of historical customer data.
- `php -n -v` confirmed the **8.2** CLI family. `php -n -l <pinned-script>` completed successfully with the expected no-syntax-errors output. The absence of external php.ini is deliberate for this source syntax check.
- Running **that same script** under `php -n` returned exact **exit code 90**; stdout empty; stderr byte-for-byte `B12_RC01=SOURCE_ONLY_HARD_DISABLED\n`. As the literal fuse remains `false` and exits before controller/worker dispatch, this is an **offline execution of only the hard-OFF path**, not a concurrency-worker run.
- Prior 7/7 Mac Python tests were not repeated; GitHub job success is not falsely used to claim another Python run.
- The result does **not** prove PHP process shutdown, transaction correctness, server-side InnoDB lock observation, MariaDB 10.11 privilege availability, fixture emptiness, resource cleanup under failure, browser/role/CSRF logic, RC02–RC14, or original tenant legacy data compatibility. Those remain independent blocked gates.

## 3. Protected source and release status

| Guard | Required invariant |
|---|---|
| Protected original B12 branch | `c137-gc24-k2p03-b12-cas-writer-cutover-source` remains `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af` |
| Protected production branch | `v320-renee-agrisuite-branding` remains `10041da3a3c4e4a7b8101f8183c243d056c7b128` |
| Literal cutover gate blob | `lib/daily_record_revision_cutover_gate.php`: `6e88c913ad5e5b371a9d0c92ce08870214ac1268` |
| Shared CAS authority blob | `lib/daily_record_revision_authority.php`: `5fbb90903b0cc801be45c684f6c3db85c660979a` |
| Patched dormant RC01 harness blob | `tests/integration/k2p03_b12_repaired_rc01_real_mariadb.php`: `6f17d1a4ba7e98dbf286d1c8e1a147e6ac3e4aad`, with literal fuse **OFF** |
| Offline Python source verifier blob | `tests/test_b12_ws52ka_rc01_offline_contract.py`: `7ea282ae90d132eab5b1522b3f6e736a69949edd` |

**WS52-KD resolution:** The PHP 8.2 prerequisite that blocked WS52-KB/WS52-KC has been satisfied **on the isolated GitHub runner**, not installed or found on the user's Mac. The two deferred checks now have GitHub-verified runtime evidence. `WS52KB=PARTIAL_BLOCKED_HISTORICAL` remains an accurate statement about that earlier Mac attempt; `WS52KD=PASS_OFFLINE_ONLY` records the separate later completion.

**Safe next decision (not authorized by WS52-KD):** The repaired CAS certification contract still requires a guarded actual legacy-schema/NULL-cycle read-only audit before *full* RC01–RC14 certification. A **separately approved preliminary, non-certifying fabricated-data RC01 race** could be considered first only with explicit acceptance that it closes no legacy-data or production cutover gate, followed by a one-use runtime/container/fixture approval. Its MariaDB concurrency target is 10.11, not the unrelated WS52 restore oracle at 11.4. Do not rerun the old B12 GitHub CI authorization (`TWO_STALE_COMMITS`).

```text
AUDIT=B12_WS52KD_PHP82_OFFLINE_VERIFICATION
RESULT=PASS_OFFLINE_ONLY
PHP82_CLI=PASS
PINNED_HARNESS_SOURCE=PASS
PHP82_SYNTAX=PASS
HARD_DISABLED_EXIT90=PASS
RC01_WORKERS=NOT_EXECUTED
SQL_EXECUTED=NO
REAL_MARIADB_CONNECTIONS=0
REAL_BACKUP_IMPORT=NO_GO
REPAIRED_RC01_RC14_CERTIFIED_PASSES=0
MIGRATION_092=NOT_AUTHORIZED
D12_IMPORT_GATES=15_BLOCKED
STAGE_D=UNAUTHORIZED
150_VU=HOLD
200_VU=PROHIBITED
B12_CUTOVER=HARD_OFF
```

## 4. Independent semantic-diff acceptance

Compare WS52-KD branch against exact WS52-KC parent `29105ac45c9c318992575b70d764a1a618285297`. Expected exactly **three additions**: `.github/workflows/c137-b12-ws52kd-php82-hardoff-once.yml`, `docs/C137_GC24_K2_P03_B12_WS52KD_ONE_USE_OFFLINE_APPROVAL.txt`, and **this report** `docs/C137_GC24_K2_P03_B12_WS52KD_PHP82_HARDOFF_VERIFIED_GHA_EVIDENCE.md`. No changes to application code, existing harness, tests, migrations, B12 gating, protected branches or old workflows. Report commit **must not** match the one-use approval file path and therefore **must not** trigger the job again. Re-fetch exact branch HEADs and relevant Git blobs after report commit. Real B12 readiness remains **NO-GO**.
