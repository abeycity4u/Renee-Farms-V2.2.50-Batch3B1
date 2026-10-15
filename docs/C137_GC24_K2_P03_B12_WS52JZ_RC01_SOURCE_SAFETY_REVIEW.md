# C137 / GC24 / K2-P03 / B12 — WS52-JZ independent repaired RC01 harness source-safety review

**Date:** 2026-10-15  
**Scope/authorization:** The user explicitly authorized **source-only independent review** of the already present, hard-disabled, repaired RC01 PHP MariaDB concurrency harness. No execution-fuse change, PHP CLI execution, PHP 8.2 lint, test fixture, SQL, Docker, database, GitHub Actions, backup, Mac, staging, production, cPanel or AWS action occurred. Exactly **one Markdown evidence file** added on a dedicated isolated GitHub documentation branch.  
**Decision:** `WS52JZ_SOURCE_REVIEW=COMPLETE`; `RC01_DRAFT_PRESENT_AND_HARD_DISABLED=YES`; `SOURCE_READINESS_FOR_DB_EXECUTION=BLOCKED_PENDING_TARGETED_HARNESS_REVIEW_FIXES`; `PHP82_LINT=NOT_EXECUTED`; `REPAIRED_RC01_RC14_CERTIFIED_PASSES=0`; `B12_CUTOVER=HARD_OFF`.

## 1. Verified checkpoints and sources

| Reference | Value |
|---|---|
| Repo | `abeycity4u/Renee-Farms-V2.2.50-Batch3B1` |
| Immediate parent branch | `c137-gc24-k2p03-b12-ws52jy-concurrency-first-feasibility-source` |
| Parent HEAD | `3b2709c466ad3f0786bab6de8f00de26b8b0d083` |
| WS52-JZ new docs-only successor | `c137-gc24-k2p03-b12-ws52jz-rc01-source-safety-review` |
| Protected original B12 code | `c137-gc24-k2p03-b12-cas-writer-cutover-source` @ `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af` |
| Protected production | `v320-renee-agrisuite-branding` @ `10041da3a3c4e4a7b8101f8183c243d056c7b128` |
| **Reviewed RC01 draft** | `tests/integration/k2p03_b12_repaired_rc01_real_mariadb.php`; Git blob `c30e772b3dc5a216c097fe579bc8c05beda70b7c`, **427 source lines** |
| Shared CAS authority | `lib/daily_record_revision_authority.php`; Git blob `5fbb90903b0cc801be45c684f6c3db85c660979a` |
| B12 hard-OFF gate | `lib/daily_record_revision_cutover_gate.php`; Git blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268` |
| Schema planner | `lib/daily_record_revision_schema_plan.php`; Git blob `a0ddbec542e891d484b48d1adbdac17442eaadf8` |
| Base fresh-install schema | `database_schema.sql`; Git blob `e77f287d62c5812a3d55dc23b5b20f5d167db389` |
| Repaired concurrency contract | `docs/C137_GC24_K2_P03_B12_REPAIRED_CAS_CERTIFICATION_CONTRACT.md`; Git blob `606c7a411f046aecd81e304e1d1341407068516c` |
| Existing RC01 source review | `docs/C137_GC24_K2_P03_B12_RC01_SOURCE_ONLY_REVIEW.md`; Git blob `b6c1652c5eb5b77c4402bcf138dab2bb484096ea` |
| Historical failed CI report | `docs/C137_GC24_K2_P03_CI_B12_FAILURE_REVIEW.md`; Git blob `88e26c810b6e5c2b8d5984e8054ca87d2783ad37` |
| Previously certified R2 synthetic schema | `docs/C137_GC24_K2_P03_B12_SCHEMA_EVIDENCE_R2_SUCCESS.md`; Git blob `25d69be7efb74b579023fef6d034811ce07e487a` |
| Later-history WS07 verifier | `scripts/verify_k2p03_b12_ws07_offline.sh`; Git blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb` |
| Frozen WS52-JT classifier/test | `c0cccf419ca78937c317f417c301fc51db002ef1` / `bfc669f337bc39f2b5509d55e2ae2c1ad643986a` |

**Branch-ref distinction:** The hard-disabled RC01 draft is **present in the later WS52-JY evidence lineage**, but is **absent at original protected B12 code commit** `2327a22a...`; the earlier source review described a draft initially prepared only in a working container. The later-history WS07 verifier also is absent at original code HEAD. Neither presence in newer evidence history nor this documentation review proves deployment or execution.

## 2. Source-confirmed design and positive controls

All line references below refer to the immutable RC01 draft Git blob `c30e772b3dc5a216c097fe579bc8c05beda70b7c`.

- **Literal disabled fuse**: `B12_RC01_EXECUTION_ENABLED = false` at **line 16**; the top-level gate at **lines 408–411** emits `B12_RC01=SOURCE_ONLY_HARD_DISABLED` and exits **90** before routing to controller/worker. **Only static text was reviewed in WS52-JZ.** No new hard-disabled execution check was run; the prior source review reported such a check at an earlier checkpoint.
- **Source and cutover guards**: **lines 20–45** pin the exact shared authority, HARD-OFF gate, schema planner and base schema using Git blob-equivalent SHA1; call the literal OFF function before loading shared CAS source. The pinned paths and their current GitHub blobs were checked in this review. The future execution wrapper must independently pin the exact harness commit and physically identified test fixture.
- **Source-service correctness**: worker **lines 200–213** invokes the **real** `daily_record_revision_write()` (not a hand-written competing UPDATE), in its own PDO transaction with **client-preloaded expected revision** from **lines 187–193**; source `lib/daily_record_revision_authority.php` lines **288–340** performs `SELECT ... FOR UPDATE` followed by exact client revision check and canonical mismatch message; lines **353–404** issue the revision-guarded update and +1.
- **Correct stale message linkage**: RC01 worker **lines 215–226** permits role B to return `STALE_CONFLICT` only on the exact shared-service message `The Daily Record has changed. Reload it before saving.`, matching authority line **337**. Unexpected conflict and other exceptions produce failure classifications instead of successful retries.
- **Bounded conceptual barrier**: Parent waits for A/B ready, signals GO and observes A locked/B attempting (**lines 328–337**); role A retains transaction open until RELEASE (**lines 209–213**); distinct connection IDs and preloaded original revisions are asserted (**lines 346–355**).
- **Controlled RC01 test data**: Fixture creates one random-suffix test farm, one Layer production cycle and one Daily Record in a transaction (**lines 245–274**), and a specifically scoped DELETE of those three objects is scripted (**lines 277–297**). Source-level tests do not claim RC02 feed, sales, stock or broader downstream effects.
- **Independent durable read**: Parent uses its separate connection to assert revision 2 and winning mortality 4 (**lines 366–369**), avoiding counting a worker's self-report alone.
- **No automatic workflow trigger on review**: The historical `.github/workflows/k2p03-mariadb-b12.yml` is scoped to an **old exact branch and one-use approval-file-only push**; this new docs-only successor branch and filename cannot match that path/branch trigger. This is a source observation, not a claim that all possible repository workflows have been audited.

**Compatibility boundaries:** The draft expects **MariaDB 10.11.x** (**lines 69–72**) and PHP PDO MySQL with process functions. The MariaDB **11.4** restore-oriented WS52 parser oracles cannot be substituted for a repaired RC01 test on 10.11. The draft's **69 tables, 886 columns, three revision columns, three exact cycle SET NULL FKs** metadata preconditions (**lines 76–128**) are an independently falsifiable *test layout contract*, not evidence that an authorized current fixture exists. The draft's PHP **8.2 lint was NOT run** by WS52-JZ.

## 3. Independent findings; stop-run hazards until corrected or justified

### F1 — High: precondition does not establish an entirely empty 69-table fixture

**Evidence:** `rc01_metadata_gate()` checks total 69 tables and 886 columns (**lines 76–86**) but checks **row emptiness only for ten named tables** (**lines 105–114**), plus `schema_migrations` row count **line 115**; it does not establish zero data in the remaining business tables or absence of views/triggers/routines/events. The method name and exact table count should not be overinterpreted as a fully empty, exclusively fabricated database.

**Why it matters:** The future root-connected RC01 fixture (`lines 58–68`) would have unrestricted server account privileges. A misidentified/contaminated 69-table server could pass the selected row checks while containing unrelated objects or data. Host/network/source/fixture checks are necessary and independent; database name and a declared environment flag are not physical attestation.

**Required targeted fix/guard:** Under a fresh source-only authorization, add a closed-list **every-table zero-row invariant** (or independently verified entire schema empty before creating the fixture), enforce exact reviewed table/object metadata and no unexpected views/triggers/routines/events/schema drift, and pin test-only DB connection + host/container image, mounts, network, unique volume and engine IDs in a separate host-side guard. Consider a strictly scoped ephemeral test principal instead of relying on root, or justify root only inside a new physically isolated **fabricated-only** server. All future actual-data importer controls remain distinct.

### F2 — High: measured interval does not independently establish B was blocked on InnoDB row lock

**Evidence:** Role B publishes `B_ATTEMPT` **immediately before calling** `daily_record_revision_write()` (**lines 201–207**). Controller merely waits for marker, sleeps about **500 ms** and observes absence of `B_RESULT` (**lines 330–335**). The checked `elapsed_ms >= 300` (**lines 354–355**) includes scheduling delay and service/connection execution time, not a separately measured MariaDB lock-wait state. A delayed/preempted worker could produce the same marker/no-result pattern without demonstrably reaching the InnoDB row lock.

**Why it matters:** The repaired contract requires deterministic actual two-connection contention, not two sequential calls or a timing-only inference. Final one-winner/one-stale and durable revision conditions are necessary but insufficient to prove the exact interleaving.

**Required targeted fix/guard:** Retain the production CAS service unchanged and the two real independent workers, but add **independent bounded MariaDB transaction/lock-state evidence or a source-reviewed equivalent deterministic proof** that B entered actual lock contention while A held the target row; capture only aggregate timing/connection IDs/status, not SQL or tenant data. Do not fake lock acquisition in a test helper, substitute direct SQL for CAS, or count a generic timeout/deadlock as a stale conflict. The exact instrumentation method must be reviewed before a runtime permission request; no SQL probe is approved now.

### F3 — High: failure cleanup may mask the primary fault, leave synthetic rows, or lose diagnostics

**Evidence:** Controller's `finally` **lines 375–401** signals ABORT, terminates workers, calls `proc_close()`, then calls `rc01_cleanup()` and unlinks files. Cleanup `lines 277–297` may throw on any row count/transaction/foreign-key/lock problem. A thrown cleanup exception **skips later temp-dir removal**, may supersede the original exception, and no dedicated independent “quarantined fixture for review” status is emitted. `proc_close()`/child termination paths have no separately attested overall hard wall-clock cap. Temporary `A_OUT/A_ERR/B_OUT/B_ERR` files are unconditionally deleted on success path before a failure synopsis is assured.

**Why it matters:** A failed concurrent test could leave test records behind, lose first-failure classification and fail to meet exact disposal/rollback assertions. A DB connection with undisclosed unrelated data would make blindly broadening cleanup particularly unsafe.

**Required targeted fix/guard:** Independently bound the overall worker/container lifetime; preserve a sanitized immutable **first failure code** through cleanup; make separately protected best-effort worker stop, connection close, exact-ID fixture cleanup and directory disposal phases; **never** continue after a failed/forked isolation check or blindly delete unverified DB resources. Return `FIXTURE_CLEANUP=PASS/INCOMPLETE/MANUAL_REVIEW` plus no-records assertions with exact guarded identity and explicit quarantine policy for partial state. The present root SQL teardown must **not** be run until isolated fixture authority is approved.

### F4 — Medium: worker launch, status and exception paths need deterministic negative tests

**Evidence:** `proc_open` launches independent workers (**lines 316–327**), status reads depend on `proc_get_status()` (**lines 356–364**), and a worker exception prior to `rc01_result()` can produce no RESULT marker, causing parent waits up to **12 seconds** per marker (`lines 139–150`) before `finally`. `proc_get_status()` semantics must be verified on exact PHP 8.2; returned `exitcode` may be unavailable after repeat status queries or process race. Worker errors caught in inner transaction are reported as redacted status and class (**lines 215–243**); outer failure catches print class only (**lines 422–425**).

**Required targeted fix/guard:** Perform **offline PHP 8.2 lint and hard-disabled exit-90 check only under a fresh offline execution approval**; add synthetic fixture-free negative tests of worker launch failure, readiness/barrier timeout, pre-result crash, early status, duplicate signals, parent abort and SIGTERM/SIGKILL paths. Preserve original error categorization and bound total durations. Do not equate “two processes spawned” with distinct live DB sessions; record independent actual connection IDs if a later run is approved.

### F5 — Medium: source hash checks do not, alone, pin all execution and privilege conditions

**Evidence:** `rc01_source_gate()` verifies four source blobs, but not its own harness file, exact branch HEAD, runtime PHP binary/modules, configured database account or Docker engine identity. `rc01_connection()` tests an **environment flag** plus a socket path and database name (**lines 48–73**); it uses the test fixture's `root` account, with a nonblank password via environment. These are useful local checks but not physical proof of an isolated server, nonproduction data, least privilege or safe secret handling.

**Required targeted fix/guard:** Host-side one-use approval binds exact runner commit/blob, *all* required shared service/schema files, PHP 8.2 runtime, active database/engine IDs, fresh disposable fixture name/volume, network disabled/no published ports, privileged principal scope and test-only operations, including teardown/retention. Disallow arbitrary `B12_RC01_ISOLATED_MAC_ATTESTED` being treated as evidence. If a root bootstrap principal is required for synthetic schema setup, avoid using it for the shared-service race where possible. No change to the production source gate.

### F6 — Medium: RC01 source-service-only test is not full B12 or UI certification

**Evidence:** `rc01_worker()` invokes the shared **revision authority** directly, with only `mortality` as the altered business field, rather than full `daily_record_revision_save_pipeline.php`. The draft acknowledges its intended limit in comments **lines 12–13**. It never exercises feed, sales, continuity, permissions/CSRF or browser-origin record tokens at the HTTP page boundary; the test-created expected revision is read from the fixture by both workers.

**Required guard:** Classify any future passing RC01 as **narrow CAS authority transaction proof** with demonstrated DB interleaving, not as RC02–RC14, authenticated browser coverage, tenant legacy/migration assurance or deployment readiness. Full contract also requires correct create/create, delete, normalized legacy NULL-cycle, downstream rollback, lock ordering, seven-writer coordinated activation and exact GET/POST token behavior.

## 4. Controls that should not be changed or re-tested unnecessarily

- Preserve literal **`B12_RC01_EXECUTION_ENABLED=false`**, top-level exit-90 gate, and `daily_record_revision_cutover_enabled()===false` hard-OFF source; do not create an “optional enable” CLI/ENV override in a safety patch. A future fuse change requires a new reviewed commit **and a specific one-use host/fixture execution authorization**; not implied by source lint.
- Preserve shared authoritative `daily_record_revision_write()`, canonical conflict class/messages, and source-owned caller transactions. Do **not** add Layer-only CAS SQL to the harness and call it a product fix.
- Do not alter, rerun or reinterpret original failed GitHub Actions B12 run `38233373699` (`TWO_STALE_COMMITS`), the old `tests/integration/k2p03_b12_real_mariadb.php`, or its consumed one-use approval commit.
- Do not infer true legacy duplicates/cross-farm FKs from synthetic R2. The repaired CAS certification contract **`3 and `7** explicitly places actual-schema/legacy read-only isolated clone audit before full real-database repaired certification. An earlier synthetic-only proof would need a **distinct non-certifying scope agreement**; it does not waive the actual-data compatibility gate.
- Do not import `~/Downloads/database.sql`, attach/live mount backup, read customer records, run migration 092, activate seven writers/UI in fragments, connect protected WS32, touch cPanel/AWS/staging/production or resume D12/150–200 VU work from this review.
- Distinguish fixed MariaDB **10.11** B12 concurrency target from WS52 **11.4** synthetic restoration/parser oracles. No old synthetic oracle counts become RC01 passes.
- Avoid rerunning already closed B01–B11/R2/FK synthetic QA absent regression evidence; use focused source-only negative tests following a minimal documented harness patch, then independently review remote semantics.

## 5. Formal go/no-go and next narrowly scoped work

| Gate | WS52-JZ finding | Decision |
|---|---|---|
| G1 — RC01 draft present, hard fuse OFF and shared service called | GitHub source **PASS** | SAFE TO REVIEW, NOT EXECUTE |
| G2 — source hash pin and literal cutover gate | Current pinned GitHub blobs **PASS** | Recheck at any future execution |
| G3 — independently empty, physically isolated MariaDB 10.11 fixture | Only partial SQL metadata prechecks in draft, no host attestation | **BLOCKED** |
| G4 — demonstrated two-connection lock overlap | Source barrier intent, no runtime lock instrumentation | **BLOCKED** |
| G5 — deterministic cleanup/quarantine on all failure paths | Potential exception masking and skipped cleanup | **BLOCKED** |
| G6 — PHP 8.2 lint, no DB execution | No PHP 8.2 runtime invoked in this review | **NOT TESTED** |
| G7 — complete repaired RC01–RC14 | Zero corrected runtime passes; UI and real legacy are separate | **BLOCKED** |
| G8 — historical backup restore/privacy/capacity | Prior WS52-JX/JY report gates remain unclosed | **NO-GO** |

**Recommended next step WS52-KA (requires fresh authorization):** minimal **source-only** harness safety patch confined to the existing **hard-disabled RC01 draft and focused fixture-free verifier(s)** on a new isolated implementation branch; target specifically F1–F5 with no product CAS/schema/deployment file modifications. Add independently reproducible PHP 8.2 syntax/static checks if an offline PHP 8.2 binary exists, and prove hard fuse remains disabled before any such execution. The prototype must remain permanently nonexecuting pending a subsequent fresh, **separately scoped runtime** approval; a source-only patch is not database permission. Carefully constrain edits rather than introducing an expansive framework or retesting closed workflows.

**Alternative sequencing:** Separately request read-only *actual-schema* audit under its required protected clone safeguards **before** full repaired certification. If an early fabricated-data RC01 experiment is preferred, obtain an explicit **non-certifying** scope exception without waiving the migration/legacy audit. WS52-JZ itself cannot change that acceptance contract.

**Standing status:**
```text
AUDIT=B12_WS52JZ_RC01_SOURCE_SAFETY_REVIEW
RESULT=SOURCE_REVIEW_COMPLETE_EXECUTION_BLOCKED
RC01_HARNESS_FUSE=OFF
RC01_SOURCE_PATCH_APPLIED=NO
PHP82_LINT_EXECUTED=NO
SQL_EXECUTED=NO
DATABASE_CONNECTIONS=0
DOCKER_OPERATIONS=0
REAL_BACKUP_ACCESSED=NO
HISTORIC_B12_CI=TWO_STALE_COMMITS_FAIL
REPAIRED_RC01_RC14_CERTIFIED_PASSES=0
MIGRATION_092=NOT_AUTHORIZED
HISTORICAL_RESTORE=NO_GO
D12_IMPORT_GATES=15_BLOCKED
STAGE_D=UNAUTHORIZED
150_VU=HOLD
200_VU=PROHIBITED
B12_CUTOVER=HARD_OFF
PRODUCTION_CONTACT=NO
```

## 6. Remote GitHub semantic-diff contract

The WS52-JZ branch adds **exactly one Markdown document** `docs/C137_GC24_K2_P03_B12_WS52JZ_RC01_SOURCE_SAFETY_REVIEW.md` from parent `3b2709c466ad3f0786bab6de8f00de26b8b0d083`. GitHub compare must report `ahead_by=1`, `behind_by=0` and this one **added file only**. Recheck protected original/production branch HEADs, hard-OFF gate, old CAS source and schema planner, RC01 harness Git blob, historical contract, later-history WS07 and frozen WS52-JT/JX synthetic tool/test blob identities. No product source modification, deployment, DB activity, CI or original file/memory data access is part of this deliverable.
