# C137 / GC24 / K2-P03 / B12 — WS52-KC read-only PHP 8.2 discovery evidence

**Evidence recorded:** 2026-10-15  
**User authorization:** Read-only Mac PHP 8.2 *discovery*, explicitly excluding software installation, Docker use, SQL or database access, GitHub source changes, historical backup access or production contact.  
**Outcome:** `WS52KC_DISCOVERY=COMPLETE`, `PHP82_AVAILABLE_IN_CHECKED_LOCATIONS=NO`, `ELIGIBLE_EXECUTABLES_VERSION_CHECKED=0`; **`PHP82_LINT=NOT_EXECUTED`; `DISABLED_EXIT_90=NOT_TESTED`; `RC01_CERTIFIED_PASSES=0`; `B12_CUTOVER=HARD_OFF`.**

## 1. Exact user-returned sanitized Mac Terminal evidence

```text
#####COPY FROM HERE#####
AUDIT=B12_WS52KC_READONLY_PHP82_DISCOVERY
RESULT=PHP82_NOT_FOUND_IN_CHECKED_LOCATIONS
REASON=NONE
DISCOVERY_MODE=READ_ONLY
PHP82_AVAILABLE=NO
PHP82_VERSION=NOT_FOUND
PHP82_SOURCE_CLASS=NONE
PHP82_BINARY_SHA256=NOT_AVAILABLE
EXECUTABLES_VERSION_CHECKED=0
INSTALLATION_ATTEMPTED=NO
DOCKER_OPERATIONS=NO
SQL_EXECUTED=NO
RC01_RUNNER_EXECUTED=NO
RC01_FUSE_CHANGED=NO
GITHUB_FILES_CHANGED=NO
PRODUCTION_BACKUP_OPENED=NO
PRODUCTION_CONTACT=NO
PHP82_LINT=NOT_EXECUTED
REPAIRED_RC01_RC14_CERTIFIED_PASSES=0
MIGRATION_092_EXECUTED=NO
B12_CUTOVER=OFF
#####COPY TO HERE#####
```

**Interpretation:** The bounded discovery searched selected system, package-manager, MAMP, Herd, ServBay and version-manager paths plus PATH directories, resolving candidates and excluding shims/non-native executable magic. **Zero** candidates reached the version-check stage. This is **not** proof that PHP 8.2 is absent from all local software; alternate installation locations, script launchers, and unusual executable formats were not exercised. No PHP 8.2 version or binary fingerprint was established. The user-returned command reports no Docker, SQL, software installation, production-backup access or RC01 execution. This observation is supplied by user-visible local output; GitHub verification does not independently attest the user's Mac state.

## 2. Pinned source and GitHub invariants

| Reference | Exact GitHub value |
|---|---|
| Repository | `abeycity4u/Renee-Farms-V2.2.50-Batch3B1` |
| WS52-KC parent evidence branch | `c137-gc24-k2p03-b12-ws52kb-php82-missing-evidence-source` |
| Parent SHA | `2b19d61c9e3714c379fb9ed2272e22a9aab88fb8` |
| WS52-KC docs-only branch | `c137-gc24-k2p03-b12-ws52kc-readonly-php82-discovery-evidence-source` |
| Hard-disabled repaired RC01 PHP source | `tests/integration/k2p03_b12_repaired_rc01_real_mariadb.php`, blob `6f17d1a4ba7e98dbf286d1c8e1a147e6ac3e4aad` |
| Frozen fixture-free Python verifier | `tests/test_b12_ws52ka_rc01_offline_contract.py`, blob `7ea282ae90d132eab5b1522b3f6e736a69949edd` |
| Hard-OFF writer gate | `lib/daily_record_revision_cutover_gate.php`, blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268` |
| Shared CAS service | `lib/daily_record_revision_authority.php`, blob `5fbb90903b0cc801be45c684f6c3db85c660979a` |
| Original protected B12 branch | `c137-gc24-k2p03-b12-cas-writer-cutover-source` at `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af` |
| Protected production branch | `v320-renee-agrisuite-branding` at `10041da3a3c4e4a7b8101f8183c243d056c7b128` |

An independent GitHub preflight returned the pinned parent, harness, verifier, gate, shared service and protected branch identities intact. WS52-KC commits no modified source/test file and neither touches nor invokes the old consumed B12 MariaDB CI workflow. The earlier WS52-KB **7/7 Python PASS** is preserved as prior user-returned evidence, but **its PHP 8.2 lint and disabled-runner exit check remain unperformed**.

## 3. No-go and safe continuation

**WS52-KC discovery is complete as a negative limited-scope check; WS52-KB remains blocked.** No test is authorized to use PHP 8.4 in place of the mandatory PHP 8.2, or to enable the dormant RC01 harness.

**Proposed WS52-KD — separately authorized environment-only resolution:** Prefer an isolated, tightly permission-restricted PHP 8.2 execution environment for **two unfinished tests only**: (1) exact RC01 PHP Git-blob-pinned syntax lint and (2) hard-disabled PHP CLI exit status **90**, confirming no output beyond the expected constant marker and no database access. An explicitly approved ephemeral GitHub-hosted job on a separate branch with `permissions: contents: read`, no secrets/DB/service containers and no changes to production or original protected branches is one possible path; its runner, dependency provenance and PHP 8.2 availability must first be checked. Alternative: a separately verified installed local PHP 8.2 executable identified without modifying the Mac. No workflow creation, dispatch, installation, Docker pull, or runner execution is implied by WS52-KC approval.

**Preserve completed tests:** Do not repeat the 7 passing fixture-free Python tests if the source and verifier blobs are unchanged; do not rerun original failed `TWO_STALE_COMMITS` B12 CI. Even a PHP 8.2 lint and hard-disabled smoke PASS would **not** certify concurrency or waive actual-schema/legacy NULL-cycle prerequisite.

Standing locks: `MIGRATION_092=NOT_AUTHORIZED`, `HISTORICAL_BACKUP_IMPORT=NO_GO`, `REPAIRED_RC01_RC14_CERTIFIED_PASSES=0`, `D12_IMPORT_GATES=15_BLOCKED`, `STAGE_D=UNAUTHORIZED`, `150_VU=HOLD`, `200_VU=PROHIBITED`, `B12_CUTOVER=HARD_OFF`.

## 4. Remote diff acceptance

Independent GitHub compare of the new WS52-KC docs-only branch against parent `2b19d61c9e3714c379fb9ed2272e22a9aab88fb8` must show one commit ahead, zero behind, and exactly this **added** file `docs/C137_GC24_K2_P03_B12_WS52KC_READONLY_PHP82_DISCOVERY_NOT_FOUND.md`; original, production, hard-OFF gate, shared CAS, dormant harness and fixture-free Python verifier unchanged. **Do not report WS52-KB or PHP lint as PASS.**
