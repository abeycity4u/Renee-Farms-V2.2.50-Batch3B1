# C137 / GC24 / K2-P03 / B12 — WS51R corrected read-only restoration preflight

**2026-10-15 — WS51R local authorized read-only preflight COMPLETE.** Result `WS51R_READONLY_PREFLIGHT_COMPLETE`. The **preflight** passed; **restoration readiness remains NOT CERTIFIED**. No SQL, credentials, DB connection, Docker exec/configuration change, backup copy/import or production contact was authorized or performed.

## GitHub provenance and immutable references

- Repo: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Parent blocked-result evidence branch `c137-gc24-k2p03-b12-ws51-runner-reference-blocked-evidence-source`, SHA `4b1a92b1346a6654e41c2b0d06f3a7ef7d30c05c`.
- This documentation-only successor: `c137-gc24-k2p03-b12-ws51r-readonly-preflight-evidence-source`.
- Protected original B12 code `c137-gc24-k2p03-b12-cas-writer-cutover-source` SHA `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Protected production `v320-renee-agrisuite-branding` SHA `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- Literal HARD-OFF cutover-gate file `lib/daily_record_revision_cutover_gate.php` blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`.
- Historical runner `scripts/verify_k2p03_b12_ws07_offline.sh` blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb` **exists in later evidence history but is absent in the pinned original B12 commit**.

## WS51 failure and WS51R correction

Original WS51 attempted `git rev-parse HEAD:scripts/verify_k2p03_b12_ws07_offline.sh` in a Mac worktree deliberately pinned to the original B12 SHA. User returned `RESULT=BLOCKED`, `FAILED_STAGE=WS07_RUNNER_BLOB`, `REASON=COMMAND_FAILED`; backup never opened, SQL and DB connections zero. Independent remote GitHub check confirmed the runner path **404 at original B12 SHA**, while the later WS50 evidence ref contained its exact expected blob. Therefore the failure was an **assistant-introduced wrong-ref assertion**, not established verifier drift.

Corrected WS51R retained the original code HEAD/clean tree and HARD-OFF gate guard, changed the local runner test to assert `git ls-tree ... HEAD -- scripts/verify_k2p03_b12_ws07_offline.sh` has **no file** (expected), and reports the later-evidence blob as `VERIFIED_SEPARATELY_ON_GITHUB` (an independent remote verification, **not a local checkout inspection**). No original repo change or cherry-pick occurred.

WS51R completed the remainder of WS51's already-authorized, read-only checks. Source checkout remained unchanged. Docker Desktop local daemon/context and pinned image fingerprint, running WS32 container `renee-b12-ws32-mariadb114-stopped` (RUNNING despite its name), network none, limits/security options and exclusive volume `renee-b12-ws32-datadir` passed. WS49 disposable resources remain absent. Two older protected original running containers and three older protected original volumes remained intact. The backup `~/Downloads/database.sql` was opened **read-only only to hash**, not parsed for SQL/customer data; size, owner/mode (0600), pinned SHA256 and before/after identity passed.

## Exact sanitized user-returned WS51R audit

```text
#####COPY FROM HERE#####
AUDIT=B12_WS51R_CORRECTED_READONLY_PREFLIGHT
SOURCE_GUARDS=PASS
HARD_OFF_GATE_BLOB=PASS
WS07_ORIGINAL_PATH=ABSENT_EXPECTED
WS07_EVIDENCE_BLOB=VERIFIED_SEPARATELY_ON_GITHUB
LOCAL_DOCKER_DESKTOP=PASS
DOCKER_ARCH_CLASS=X86_64
IMAGE_FINGERPRINT=PASS
WS32_IDENTITY=PASS
WS32_NETWORK_MODE=NONE
WS32_RESOURCE_POLICY=PASS
WS32_SECURITY_POLICY=PASS
WS32_DEDICATED_VOLUME=PASS
WS32_VOLUME_SHARED_WITH_PROTECTED=NO
WS49_RESOURCES_ABSENT=PASS
PROTECTED_RUNNING_CONTAINERS=2
PROTECTED_ORIGINAL_VOLUMES=3
RESOURCE_INVENTORY_STABLE=PASS
BACKUP_SIZE_BYTES=619163
BACKUP_OWNER_AND_MODE=PASS
BACKUP_SHA256=PASS
BACKUP_UNCHANGED_DURING_READ=PASS
MAC_HOST_FREE_GIB=166.82
BACKUP_FILESYSTEM_FREE_GIB=166.82
DOCKER_BACKING_STATUS=HOST_FILE_LOCATED
DOCKER_BACKING_FREE_GIB=166.82
DOCKER_DISK_LOGICAL_GIB=233.47
DOCKER_DISK_ALLOCATED_GIB=2.81
GUEST_CAPACITY=NOT_ATTESTED
EXPANDED_IMPORT_FOOTPRINT=UNKNOWN
RESTORE_SPACE_SUFFICIENCY=NOT_CERTIFIED
SQL_AWARE_ALLOWLIST=NOT_CERTIFIED
SNAPSHOT_CONSISTENCY=NOT_CERTIFIED
NEW_RESTORE_TARGET=NOT_CREATED
RESTRICTED_IMPORT_ACCOUNT=NOT_CREATED
CUSTOMER_DATA_CONTAINMENT=PLAN_ONLY
RESTORE_ROLLBACK=PLAN_ONLY
BACKUP_COPIED=NO
BACKUP_UPLOADED=NO
CUSTOMER_DATA_DISPLAYED=NO
DATABASE_CONNECTIONS=0
SQL_EXECUTED=0
DOCKER_EXEC_EXECUTED=NO
DOCKER_LOGS_READ=NO
ROOT_PASSWORD_ACCESSED=NO
DOCKER_RESOURCES_CHANGED=NO
BACKUP_RESTORED=NO
PRODUCTION_CONTACT=NO
RESTORE_AUTHORIZED=NO
MIGRATION_092_EXECUTED=NO
B12_CUTOVER=OFF
RESULT=WS51R_READONLY_PREFLIGHT_COMPLETE
#####COPY TO HERE#####
```

## Interpretation: passed integrity ≠ ready-to-restore

**Verified**: user-reported backup remains identical to pinned SHA256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`, 619163 bytes, unchanged mode 0600; original source/hard-OFF gate and WS32/protected isolation unchanged. WS49 cleaned up correctly. Current Mac host and Docker backing host filesystem free space 166.82 GiB. Docker backing file located with logical length **233.47 GiB** and allocated blocks **2.81 GiB**. The logical size is **not** actual physical storage headroom; both host free readings are the same 166.82 GiB filesystem figure, not additive quantities.

**Not yet certified**:
- Docker guest datadir free bytes/inodes/current quota, and credible worst-case expanded import footprint, tables, indices, temporary files and redo/undo/logs.
- A complete **SQL-aware, executable-comment-aware** allowlist for the pinned dump and fail-closed destination safety of its known **69 destructive DROP TABLE** directives (WS43 heuristic).
- User-reported production-derived dump's transactionally consistent point-in-time snapshot and independent provenance. Global transaction boundaries not observed earlier.
- Client's complete original dump compatibility. WS49 verified exactly two synthetic SELECT 1 trials, the second preceded by a MariaDB sandbox directive. It did not test enforcement of sandbox restrictions or every SQL line.
- A wholly new restore-only container/volume, unique empty schema, ephemeral schema-restricted import account/grants, secure non-root secret transfer, confidentiality/encryption/retention and disposal/rollback plan **implemented** (WS50 is design-only).
- Genuine real MariaDB B12 concurrency certification. Prior original real test FAILED `TWO_STALE_COMMITS`; corrected RC01–RC14 pass count remains 0.

## Recommended next gate (not authorized): WS51S SQL/import-risk static contract + guest-capacity metadata

Before provisioning a new restore environment, request a narrowly scoped **additional read-only evidence step** verifying capacity *without Docker exec/SQL* where possible and reviewing parser-aware SQL allowlist feasibility on the already-pinned local backup with output limited to sanitized risk-class counts/booleans. The 69 DROP statements and unknown SQL/comment/client features must be fail-closed; a simplistic lexical regex alone cannot certify full import safety. This step requires exact authorization for any **content parsing** beyond a hash; source, backup and Docker identity guards must remain intact. If full safe classification cannot be established, report BLOCKED and do not authorize WS52 provisioning or WS53 import. A separate authorization is needed for any Docker exec, guest file reads, state changes, credentials, schema SQL, customer records or data import.

The WS50 planned later steps WS52–WS54 remain **planning labels only**, not automatically approved: new isolated disposable restore server/schema/restricted account, separately authorized sensitive-data import, and contained validation/disposal. All existing original resources including WS32 must remain untouched. No re-testing of closed WS42–WS49 without regression evidence.

## Standing stop gates

`B12_CUTOVER=HARD_OFF`; migration 092 NOT AUTHORIZED; D12 15 import gates BLOCKED; Stage D unauthorized; 150 VU HOLD; 200 VU PROHIBITED. No production/staging/AWS/cPanel or protected historical C137 fixture contact. No production-derived data copies/uploads to GitHub/ChatGPT. This WS51R evidence consists of **exactly one new Markdown file** on a new docs-only branch, followed by independent remote semantic diff **one commit ahead, zero behind**, and rechecks of the two protected branch SHAs, B12 gate and WS07 runner blobs.
