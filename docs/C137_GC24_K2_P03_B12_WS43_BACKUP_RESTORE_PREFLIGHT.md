# C137 / GC24 / K2-P03 / B12 — WS43 local production-derived backup pre-restore qualification

**Date: 2026-10-15. User-returned read-only preflight, exactly per separate authorization. Audit COMPLETE. This does NOT authorize or certify restore, production-origin attestation, snapshot consistency, schema/data compatibility, migration 092, or B12 CAS cutover.**

## Source checkpoint and isolation boundaries

- Repo: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Parent docs-only branch `c137-gc24-k2p03-b12-ws42-authenticated-select1-evidence-source`: `359e2c498fb82bacf07fbca2ddca24c4991ee7d7`.
- WS43 new docs-only branch: `c137-gc24-k2p03-b12-ws43-backup-preflight-evidence-source`.
- Protected original B12 source branch `c137-gc24-k2p03-b12-cas-writer-cutover-source`: `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Protected production source `v320-renee-agrisuite-branding`: `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- B12 literal HARD-OFF gate `lib/daily_record_revision_cutover_gate.php` blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`.
- Historical WS07 offline runner `scripts/verify_k2p03_b12_ws07_offline.sh` blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`.
- WS32 isolated new MariaDB 11.4 container `renee-b12-ws32-mariadb114-stopped` (running despite historical static name) and **exclusive** new named volume `renee-b12-ws32-datadir`, network none, no ports, unprivileged; 2 protected original running containers and 3 original volumes unchanged.
- WS42 previously certified authenticated root `SELECT 1` via isolated socket and ephemeral locally recovered generated secret; that one-use authorization is consumed, not a blanket permission for SQL or credential rereads.

## Authorized WS43 activity

The user said `proceed` in response to explicit **WS43 read-only local-only restoration-safety qualification** of the Mac file `~/Downloads/database.sql` and host free space, excluding import, SQL execution, DB connections, customer-data publication, backup upload/copy, Docker exec or startup log/credential rereads.

The Mac command guarded source branch/HEAD/clean worktree, local Docker Desktop context/engine, pinned cached MariaDB 11.4 image fingerprint, precise new container identity and dedicated volume, and all original Docker container/volume/network inventories before/after. It opened the reported SQL backup read-only, verified owner/mode/regular-file/single hard link and pinned length/hash, scanned locally for line-anchored structural and other potentially dangerous SQL keywords, checked file identity stability and existence, queried host filesystem free-space metadata, printed **only aggregate classifications and sanitized metadata**.

## User-returned WS43 audit

```text
#####COPY FROM HERE#####
AUDIT=B12_WS43_BACKUP_RESTORE_PREFLIGHT
SOURCE_GUARDS=PASS
LOCAL_DOCKER_DESKTOP=PASS
MARIADB_IMAGE_11_4=PASS
TARGET_CONTAINER_IDENTITY=PASS
TARGET_CONTAINER_STATE=RUNNING
NETWORK_MODE=NONE
DEDICATED_VOLUME=PASS
BACKUP_REGULAR_FILE=PASS
BACKUP_OWNER_AND_MODE=PASS
BACKUP_SIZE_BYTES=619163
BACKUP_SHA256=PASS
BACKUP_CHANGED_DURING_SCAN=NO
BACKUP_HEADER_MARIADB_11_4=REPORTED
BACKUP_FOOTER_MARKER=PRESENT
SQL_HEURISTIC_CREATE_TABLE=69
SQL_HEURISTIC_DROP_TABLE=69
SQL_HEURISTIC_INSERT_INTO=65
SQL_HEURISTIC_REPLACE_INTO=0
SQL_HEURISTIC_LOCK_TABLES=69
SQL_HEURISTIC_UNLOCK_TABLES=69
SQL_HEURISTIC_START_TRANSACTION=0
SQL_HEURISTIC_BEGIN=0
SQL_HEURISTIC_COMMIT=0
SQL_HEURISTIC_ROLLBACK=0
SQL_HEURISTIC_DROP_DATABASE=0
SQL_HEURISTIC_CREATE_DATABASE=0
SQL_HEURISTIC_USE_DATABASE=0
SQL_HEURISTIC_ALTER_TABLE=0
SQL_HEURISTIC_TRUNCATE_TABLE=0
SQL_HEURISTIC_LOAD_DATA=0
SQL_HEURISTIC_CREATE_USER=0
SQL_HEURISTIC_GRANT=0
SQL_HEURISTIC_SOURCE_COMMAND=0
SQL_HEURISTIC_VERSIONED_SQL_COMMENT_LINES=361
SQL_HEURISTIC_FK_CHECKS_REFERENCES=2
PREVIOUS_STRUCTURAL_COUNTS=MATCH
DESTRUCTIVE_OR_IMPORT_DIRECTIVES=DETECTED
TRANSACTION_MARKERS=NOT_DETECTED
CONSISTENT_SNAPSHOT=NOT_CERTIFIED
SQL_PARSING=LINE_ANCHORED_HEURISTIC_ONLY
MARIADB_RESTORE_COMPATIBILITY=NOT_CERTIFIED
MAC_HOME_FILESYSTEM_FREE_GIB=166.86
BACKUP_FILESYSTEM_FREE_GIB=166.86
DOCKER_BACKING_STORAGE=HOST_FILESYSTEM_LOCATED
DOCKER_BACKING_FREE_GIB=166.86
DOCKER_GUEST_WRITABLE_CAPACITY=NOT_REATTESTED
RESTORE_SPACE_SUFFICIENCY=NOT_CERTIFIED
BACKUP_READ_IN_LOCAL_PROCESS=YES
CUSTOMER_DATA_DISPLAYED=NO
SQL_IDENTIFIERS_DISPLAYED=NO
BACKUP_COPIED=NO
BACKUP_UPLOADED=NO
DATABASE_CONNECTIONS=0
SQL_EXECUTED=0
DOCKER_EXEC_EXECUTED=NO
DOCKER_LOGS_READ=NO
ROOT_PASSWORD_ACCESSED=NO
BACKUP_RESTORED=NO
MIGRATION_092_EXECUTED=NO
PROTECTED_RUNNING_CONTAINERS=2
PROTECTED_ORIGINAL_VOLUMES=3
RESOURCE_INVENTORY_STABLE=PASS
PRODUCTION_CONTACT=NO
B12_CUTOVER=OFF
RESULT=WS43_READONLY_PREFLIGHT_COMPLETE
#####COPY TO HERE#####
```

## Valid inferences and remaining hazards

**Identity and general shape:** SHA256 matched the previously observed `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`, length 619163, owner-only 0600 mode, no identified changes during scan, footer marker present, header reporting MariaDB 11.4. There are 69 CREATE TABLE, **69 destructive DROP TABLE**, 65 INSERT INTO, and 69 each LOCK/UNLOCK TABLES, plus 361 versioned SQL comment lines and 2 foreign-key-check references. These are **line-anchored heuristic** counts, not a complete SQL AST. SQL may contain executable MariaDB conditional comments (`/*! ... */`); they have not been behaviorally classified. Do not assume exact semantics or safe execution from keyword counts.

**No SQL transaction markers detected** (`START TRANSACTION`, `BEGIN`, `COMMIT`, `ROLLBACK` each 0). Neither global snapshot-consistency nor inconsistency is thereby proven. `LOCK TABLES` pairs do not prove a coherent point-in-time backup across all 69 tables. User reports this SQL was generated from *live* Renee AgriSuite production, but origin and capture procedure remain **not independently attested**.

**No top-level CREATE DATABASE/USE detected**. Therefore do NOT assume the file creates/chooses the target database automatically. Before any future import, independently design and strictly pin a *fresh dedicated application database/schema* inside only the new WS32 container, never system schemas (`mysql`, `sys`, `information_schema`, `performance_schema`), existing production/staging/old test containers, or any preexisting application schema. The exact eventual import requires a separate new authorization, including any CREATE DATABASE/DDL, customer-data containment plan, rollback/disposal, and bounded credential approach.

**Storage:** WS43 reports 166.86 GiB of host/backing free space. WS37 earlier reported 213.14 GiB guest data-dir free, but guest sparse/virtual capacity is not necessarily physically backed, and free space has not been reattested from the guest. The 619163-byte compressed? No compression is established; do not equate dump file length with expanded database footprint or restoration capacity. Capacity sufficiency remains **NOT_CERTIFIED**.

**Privacy:** raw SQL identifiers and customer records were scanned only in local Python process. No customer data, identifiers, dump file, raw SQL, secrets or Docker logs were displayed to ChatGPT, copied or uploaded. Successful isolated authentication WS42 certifies one previous `SELECT 1`, not persistent credentials or permission for import.

## Next proposed authorization: WS44 read-only import-semantic risk check

Recommend focused **WS44 static executable-comment and schema-routing review**, rather than repeating WS43's hash/DDL-count work. Separately authorize a bounded **read-only** scan of this exact original SQL file to classify versioned comments / executable `SET`, `DEFINER`, stored routines/triggers/events, source/include, cross-schema names and explicit schema selection; output only allowlisted flags/counts, **no SQL lines, identifiers, literals, customer records or secret-like strings**, no copy or upload. Preserve current source/Docker/backup SHA guards. A line heuristic may still fail to certify complete semantic compatibility, so fail closed on unrecognized active directives. Use such evidence to design a later, separately authorized empty-schema-only and data-import plan. No MariaDB client/database connection, SQL execution, Docker exec, credential read, backup restoration, migration 092, live tests or Docker writes in WS44. Avoid expanding scope based on the success of WS43.

## Global B12 gates

- B12 daily record revision CAS cutover **HARD-OFF**.
- Migration 092 **NOT AUTHORIZED**.
- Repaired real MariaDB RC01–RC14 certified passes **0**, historical original real run failed `TWO_STALE_COMMITS`.
- D12 15 import gates BLOCKED; Stage D UNAUTHORIZED; 150 VU HOLD; 200 VU PROHIBITED.
- Production, staging, AWS/cPanel and historical C137 fixture untouched.

**Docs-only commit:** exactly this one Markdown evidence file on a newly created branch, followed by independent GitHub compare against parent and verification of original/production branches, hard-OFF gate and historical runner.
