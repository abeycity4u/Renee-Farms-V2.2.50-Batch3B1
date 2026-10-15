# C137 / GC24 / K2-P03 / B12 — WS52-KF disabled GitHub-hosted preliminary RC01 candidate

**Date:** 2026-10-15  
**Approval scope:** User said “proceed” to prepare a separate **GitHub-hosted, test-only** MariaDB 10.11 RC01 runner, **initially disabled**, with independently reviewed source and a future distinct exact one-use execution approval. This is **preparation only**, not authorization to enable a test, run SQL, create a database service, import a customer backup, execute a migration or contact production.  
**Decision:** `WS52KF_SOURCE_CANDIDATE=STAGED`; `WORKFLOW_JOB=DISABLED`; `ONE_USE_APPROVAL_FILE=ABSENT`; `MARIA_DB_TEST_EXECUTED=NO`; `RC01_CERTIFICATION_PASSES=0`; `B12_CUTOVER=HARD_OFF`.

## 1. Source, branch and hard-OFF provenance

| Object | Pinned value |
|---|---|
| Repo | `abeycity4u/Renee-Farms-V2.2.50-Batch3B1` |
| Parent WS52-KE docs branch | `c137-gc24-k2p03-b12-ws52ke-preliminary-rc01-execution-preflight` |
| Parent HEAD | `701c4cea6bf0aa56d979c853a54280e44ad74cfa` |
| New implementation-only branch | `c137-gc24-k2p03-b12-ws52kf-github-rc01-candidate-disabled` |
| Protected original B12 HEAD | `c137-gc24-k2p03-b12-cas-writer-cutover-source` @ `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af` |
| Protected production HEAD | `v320-renee-agrisuite-branding` @ `10041da3a3c4e4a7b8101f8183c243d056c7b128` |
| Original dormant Mac RC01 harness (unchanged) | `tests/integration/k2p03_b12_repaired_rc01_real_mariadb.php` blob `6f17d1a4ba7e98dbf286d1c8e1a147e6ac3e4aad` |
| New GH-only dormant candidate | `tests/integration/k2p03_b12_gh_rc01_preliminary_synthetic.php` blob `e78318509e270ccd24684bf41f5e854b74765f76` |
| New GH-only disabled workflow | `.github/workflows/c137-b12-ws52kf-rc01-gh-candidate-disabled.yml` blob `f59974c2e626eed8d16f3dd73fbd181c1c63837f` |
| New fixture-free offline candidate source verifier | `tests/test_b12_ws52kf_gh_rc01_disabled_source.py` blob `c75c62216b6557eb58aeb82a2ee5e5f4c325560c` |
| Shared authoritative CAS PHP source, unchanged | `lib/daily_record_revision_authority.php` blob `5fbb90903b0cc801be45c684f6c3db85c660979a` |
| Literal writer cutover gate, unchanged | `lib/daily_record_revision_cutover_gate.php` blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268` |
| Synthetic source-schema file, unchanged | `database_schema.sql` blob `e77f287d62c5812a3d55dc23b5b20f5d167db389` |
| Historical repaired CAS contract, unchanged | `docs/C137_GC24_K2_P03_B12_REPAIRED_CAS_CERTIFICATION_CONTRACT.md` blob `606c7a411f046aecd81e304e1d1341407068516c` |

The **original protected B12 branch never contained the later-history RC01 draft**; no attempted backfill, cherry-pick or deployment occurred. The original B12 CI failure `TWO_STALE_COMMITS` from one-use workflow run `38233373699` still stands. The successful WS52-KD job `38769348137` proved PHP 8.2 syntax **and disabled exit-90 only for the ORIGINAL Mac-specific harness**; it does **not** establish PHP lint for the new GH candidate.

## 2. Candidate implementation and exactly what changed

**New PHP file (593 lines):** A source-only fork of the hard-disabled Mac RC01 harness, adapted **only** in the header, fixed environment constants and `rc01_connection()` function. From `function rc01_metadata_gate(PDO $pdo): void` onward, all metadata verification, barrier, two workers, INNODB_LOCK_WAITS observation, canonical shared CAS invocation, exact synthetic farm/cycle/Layer fixture, durable result check, bounded shutdown, quarantine and cleanup code is **byte-identical** to the original guarded RC01 harness at blob `6f17d1a4...`. No product SQL, permission, billing, inventory, population, expense, lifecycle or PHP application service is duplicated or changed.

The GH-specific connection guard now expects a **GitHub-hosted** runner (`GITHUB_ACTIONS`, `RUNNER_ENVIRONMENT`, repo/ref, first attempt), an independently issued `B12_RC01_GH_ISOLATION_ATTESTED=YES`, a fixed **loopback TCP host 127.0.0.1**, fixed **port 3306**, fixed test database `renee_b12_gh_rc01_synthetic_only`, a test-only nonempty root password, MariaDB version **10.11.x** and `@@server_id` equal to a previously approved numeric server identifier. It **does not spoof** the Mac-only `B12_RC01_ISOLATED_MAC_ATTESTED` or Unix-socket path. It retains the exact runner-Git-blob environment approval check, the pinned shared CAS service, and literal application HARD-OFF gate.

**Critical design warning:** GitHub-hosted runner environment variables, a loopback address and `@@server_id` checks are **not themselves proof of an independently isolated database container**. The still-unwritten future one-use workflow must verify the actual service image **digest**, container ID/mount/network exposure, exact source and database schema, available privileges, no non-synthetic mounts and host isolation before setting any attestation flag. A GitHub-hosted MariaDB service reached by loopback TCP is **not** equivalent to a network-disabled MariaDB process. If network-disabled operation is required for the final consent, select and independently test a Unix-socket/networkless design before execution, rather than claiming loopback to be networkless. At-rest guest backing, snapshots and retention controls also require review even for fabricated fixtures if a different scope is later proposed.

**Candidate not activated:** The PHP constant remains literally `const B12_RC01_EXECUTION_ENABLED = false;`; the top-level execution path returns **90** before any DB connection or fixture mutation. This is **not** a runtime-enabled build. Any later source adjustment to enable a run requires a **new reviewed Git blob and explicit, one-use, exact environment/operations/teardown approval**. No dynamic CLI flag or environment override can enable this candidate today.

**Workflow template:** A separate `push` workflow is scoped only to `c137-gc24-k2p03-b12-ws52kf-github-rc01-candidate-disabled` and to a **nonexistent** `docs/C137_GC24_K2_P03_B12_WS52KF_ONE_USE_RC01_APPROVAL.txt` path. Its only job has `if: ${{ false }}`, **no MariaDB service**, no checkout, no secret, no SQL, no remote fetch, an invalid/empty approval blob/password and `B12_RC01_GH_ISOLATION_ATTESTED=NO`. Even if its approval file is created, the job is skipped; if a future reviewer mistakenly overrides `if` without reviewing the sentinel, its only step emits `BLOCKED_SOURCE_ONLY` and exits **86**. There is no `workflow_dispatch`, `pull_request` or broad push trigger. Its staging commits cannot run the actual test.

## 3. Source-only verification done and still required

**Independent GitHub-fetched byte comparison and static assertion:** Immediately after staging the three source files, the assistant fetched the original Mac harness, GH candidate, workflow and Python verifier by branch ref and independently confirmed their Git blob identifiers. The 13-check independent JS source audit reported **13/13 PASS**:
- Original harness hash unchanged; shared race/cleanup/metadata suffix byte-for-byte equal.
- Both harnesses' literal execution fuse remains OFF; new harness introduces no ON switch.
- GitHub-specific attestation and pinned exact source checks exist; no use of the Mac attestation flag or socket.
- Fixed loopback transport, MariaDB 10.11 family and server ID check present.
- Original canonical shared CAS service call preserved.
- Job `if: false` and additional `exit 86`; no DB service/secret configured, absent one-use approval path, no manual dispatch trigger.
- New source-only Python regression verifier is present.

**Not executed:** Python `unittest` of the new verifier, `php8.2 -l` of the new 593-line candidate, hard-disabled PHP exit-90 smoke of the new candidate, MariaDB server/container creation, identity preflight, SQL schema creation or any concurrent worker. Unlike WS52-KD, there is **no new GitHub Actions runtime result** for this candidate. This report is a staged design with source assertions, **not test execution or readiness certification**.

**Proposed safe offline-only verification (requires separate approval):** On a trusted isolated source snapshot, validate the exact new Git blob and workflow YAML, run `python3 -B -m unittest discover -s tests -p 'test_b12_ws52kf_gh_rc01_disabled_source.py' -v`, then use an isolated pinned PHP 8.2 CLI for `php -n -l` and confirm the new candidate's exit **90** and exact constant stderr. No other GH job/test runner or DB service; no need to repeat original WS52-KD checks.

## 4. Hard release gates and future one-use test contract

**Before an actual synthetic race**, a separately reviewed exact runner/workflow revision must prove:
1. A uniquely identified disposable GitHub-hosted runner and digest-pinned MariaDB **10.11** service or independent strictly networkless equivalent, with only fabricated records, no product credentials/backup, no connection to real production or the protected WS32 fixture, exact source image, engine and transport.
2. A fresh **empty** test database with the guarded `database_schema.sql` (69 tables/883 initial columns expected from prior synthetic evidence; **reverify rather than assume**), then three *synthetic-only* revision-column ALTER statements to produce exactly 886 columns. This is **not** migration 092, cannot create or alter production records, and must stop on partially applied DDL. No legacy or customer data permitted.
3. Reviewed one-use approval file and commit; exact PHP harness Git blob, SHA/GitHub run number, synthetic-only password lifecycle and DB host/port/socket, restricted local service identity, worker/observer IDs, bounded lock waits and whole-run timeout. Code/approval-file staging commits must not accidentally trigger tests.
4. One synthesized farm/cycle/Layer daily record, two independent worker connections loading identical original `record_revision=1`, one independent observer verifies InnoDB lock wait; exactly one commit, other explicit stale conflict, revision 2, winner-only values, no losing write.
5. Independent post-run metadata and zero-row assertions, database/volume teardown by exact resource identity, sanitized error/progress evidence, no raw SQL/data/credentials or unbounded logs. If cleanup is not provable, quarantine/STOP and require separately approved remediation; **no automatic rerun**.
6. A separately approved runtime action expressly labelled **PRELIMINARY/NON-CERTIFYING**. The repaired CAS certification contract requires actual legacy-schema/NULL-cycle/cross-farm audit before formal repaired RC01–RC14 certification. A preliminary synthetic pass cannot discharge that prerequisite or authorize production writer cutover, Stage D, load tests or migration 092.

**Decisions still open before requesting the one-use runtime consent:** Pin an immutable MariaDB 10.11 image digest; choose and independently verify loopback-only or strictly networkless service isolation; implement/inspect schema bootstrap and three-column DDL only for new fabricated schema; verify server metadata access for `INNODB_LOCK_WAITS`; and run offline tests on **this** new candidate. Resolve any failures with a narrowly reviewed source revision. Do not fabricate a PHP 8.2 PASS from the prior Mac-specific harness's WS52-KD job.

```text
AUDIT=B12_WS52KF_GITHUB_RC01_CANDIDATE
RESULT=SOURCE_ONLY_DISABLED_CANDIDATE_STAGED
SOURCE_STATIC_CHECKS=13_OF_13_PASS
GITHUB_WORKFLOW_HARD_DISABLED=YES
GITHUB_ONE_USE_APPROVAL_FILE=ABSENT
NEW_RC01_CANDIDATE_PHP82_LINT=NOT_EXECUTED
NEW_RC01_CANDIDATE_PYTHON_VERIFIER=NOT_EXECUTED
DATABASE_SERVICES_CREATED=NO
SQL_EXECUTED=NO
RC01_WORKERS_EXECUTED=NO
REPAIRED_RC01_RC14_CERTIFIED_PASSES=0
REAL_LEGACY_SCHEMA_AUDIT=BLOCKED
HISTORICAL_BACKUP_RESTORE=NO_GO
MIGRATION_092=NOT_AUTHORIZED
D12_IMPORT_GATES=15_BLOCKED
STAGE_D=UNAUTHORIZED
150_VU=HOLD
200_VU=PROHIBITED
B12_CUTOVER=HARD_OFF
```

## 5. Exact independent remote-diff acceptance

Compare the new WS52-KF branch against pinned parent `701c4cea6bf0aa56d979c853a54280e44ad74cfa`. Expect **four additions only**: the GH PHP candidate, hard-disabled workflow YAML, fixture-free source verifier and this Markdown report. No existing source/test/workflow, migration/DDL/schema, old B12 workflow, old Mac RC01 runner or product file may be changed. Independently verify full protected original and production branch HEADs, literal hard-OFF gate and canonical shared CAS blobs, the exact new GH PHP/workflow/test blobs and absence of any execution runs on this branch. The only approved outcome of this WS52-KF task is **staging**, not a runtime PASS or one-use authorization.
