# C137 / GC24 / K2-P03 / B12 — WS44 local-only SQL restore-semantic risk scan

**2026-10-15 — User-authorized read-only static heuristic examination completed; restoration is NOT authorized.**

## Source / environment checkpoint

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Parent docs-only branch `c137-gc24-k2p03-b12-ws43-backup-preflight-evidence-source` head `7e384ed4d9d293b3f34a2e543702e8933dbfde18`.
- WS44 new docs-only evidence branch `c137-gc24-k2p03-b12-ws44-sql-semantic-evidence-source`.
- Original B12 protected source branch `c137-gc24-k2p03-b12-cas-writer-cutover-source` head `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Production protected source `v320-renee-agrisuite-branding` head `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- B12 literal hard-OFF gate `lib/daily_record_revision_cutover_gate.php` Git blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`.
- Historical WS07 offline runner `scripts/verify_k2p03_b12_ws07_offline.sh` Git blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`.
- Isolated new Mac Docker Desktop `mariadb:11.4` container: `renee-b12-ws32-mariadb114-stopped` (RUNNING despite legacy name), `--network none`, dedicated volume `renee-b12-ws32-datadir`; two original running containers and three original volumes protected. None was modified.
- Backup locally opened read-only: `~/Downloads/database.sql`, length 619163 bytes, SHA256 previously pinned as `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`. WS44 reports SHA256 PASS / unchanged. Its exact production origin and point-in-time consistency are **not independently certified**.

## Authorized scope and exact user-returned evidence

The user explicitly authorized a targeted, strictly read-only, on-Mac static classification of the unchanged SQL backup after WS43. No raw SQL, customer identifiers, customer values or Docker secrets were to be uploaded or displayed; no MariaDB client/database connection, migration, restore, protected-container contact or Docker write. The Python lexical scanner masked quoted sections, classified versioned executable SQL comments, and searched heuristically for schema selection, cross-schema references, routines, triggers, views, events, user permissions, external IO and client directives.

```text
#####COPY FROM HERE#####
AUDIT=B12_WS44_SQL_RESTORE_SEMANTIC_RISKS
SOURCE_GUARDS=PASS
LOCAL_DOCKER_DESKTOP=PASS
MARIADB_IMAGE_11_4=PASS
TARGET_CONTAINER_IDENTITY=PASS
TARGET_CONTAINER_STATE=RUNNING
NETWORK_MODE=NONE
DEDICATED_VOLUME=PASS
BACKUP_SHA256=PASS
BACKUP_IDENTITY_UNCHANGED=PASS
SQL_SCANNER=LEXICAL_HEURISTIC
QUOTE_SECTIONS_MASKED=16627
ORDINARY_BLOCK_COMMENTS=0
VERSIONED_MYSQL_COMMENTS=361
VERSIONED_MARIADB_COMMENTS=3
VERSIONED_SET=225
VERSIONED_ALTER=138
VERSIONED_CREATE=0
VERSIONED_OTHER=0
VERSIONED_UNKNOWN=1
VERSIONED_DEFINER_REFERENCES=0
VERSIONED_SQL_MODE_REFERENCES=2
TOP_LEVEL_SCHEMA_SELECTION=0
DATABASE_DDL=0
ROUTINE_DDL=0
TRIGGER_DDL=0
EVENT_DDL=0
VIEW_DDL=0
DEFINER_REFERENCES=0
PRIVILEGE_CHANGES=0
EXTERNAL_FILE_OR_PLUGIN_OPERATIONS=0
POTENTIAL_QUALIFIED_REFERENCES=0
MYSQL_CLIENT_DIRECTIVES=0
BACKSLASH_CLIENT_DIRECTIVES=0
UNRECOGNIZED_STATEMENT_HEADS=0
ADDITIONAL_SEMANTIC_REVIEW_NEEDED=YES
EXECUTABLE_COMMENT_SAFETY=NOT_CERTIFIED
CROSS_SCHEMA_ISOLATION=NOT_CERTIFIED
BACKUP_SNAPSHOT_CONSISTENCY=NOT_CERTIFIED
RESTORE_COMPATIBILITY=NOT_CERTIFIED
RESTORE_AUTHORIZED=NO
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
RESULT=WS44_READONLY_SEMANTIC_SCAN_COMPLETE
#####COPY TO HERE#####
```

## Supported findings — not certification of full SQL semantics

- 361 `/*! ... */` MySQL versioned comments plus 3 `/*M! ... */` MariaDB versioned comments = 364. Locally classified 225 `SET`, 138 `ALTER`, and **ONE UNKNOWN**. No `CREATE` or other identified versioned command.
- `VERSIONED_UNKNOWN=1` prevents claiming that executable versioned-comment semantics are fully accounted for; even the classified `ALTER` directives require subtype classification before considering restoration. It is **not yet known** whether the unknown is harmful, benign, version-guarded, a formatting artifact, or an unsupported lexical shape.
- Zero indicated for explicit `USE`, database-level DDL, routines, triggers, events, views, `DEFINER`, privilege changes, external file/plugin operations, potential qualified cross-schema references, MySQL client/backslash directives or unrecognized statement heads. These are **heuristic zeros**; not a formal proof that the dump is safe or devoid of cross-schema effects.
- Versioned SQL comments can be executable under MySQL/MariaDB-specific version conditions. WS44 did **not** fully simulate MariaDB 11.4 conditional-comment dispatch or parse multi-statement SQL grammar.
- WS43 previously found 69 destructive `DROP TABLE` statements, 69 `CREATE TABLE`, 65 `INSERT INTO`, and no `BEGIN`/`COMMIT` transaction markers. The backup is potentially destructive. `LOCK TABLES` per-table markers cannot certify one point-in-time snapshot; global snapshot consistency and full restoration compatibility remain unresolved.
- WS43 host backing filesystem free 166.86 GiB and image headers reporting MariaDB 11.4 do not certify expanded database footprint or sufficient space. WS42 separately established **one** password-based, authenticated root `SELECT 1` to new isolated container; that single-use credential access authorization is consumed. WS44 did not touch passwords or SQL.

## Narrow next gate: proposed WS45 (NOT authorized)

Prior to contemplating any SQL import, propose a **separately authorized local-only, read-only targeted scanner refinement** restricted to the **single unknown versioned comment and the 138 `ALTER` versioned-comment classes**, with careful treatment of version predicates, MariaDB 11.4 dispatch, `ALTER TABLE` operation subtypes (such as key toggles versus changing table structure), and any hidden object names redacted from output. It may output only fixed allowlisted categories and counts: no SQL source lines, literals, table names, customer identifiers, or password-like strings. Verify identical pinned file SHA, exact B12 source clean-state/commit, new isolated Docker target and two protected original containers/three original named volumes; **no SQL queries, Docker exec, logs, credential extraction/use, backup copy/upload or restore**, migration 092 or cutover. A static heuristic refinement is still not a full SQL parser or independent snapshot attestation. If unknown remains unclassifiable, fail closed and seek a source-review/controlled parser design before import.

**Do not repeat closed WS43 identity and schema-count audits unnecessarily.** Only the scoped new semantic gap should be checked after unchanged identity guards.

## Standing project gates

B12 writer cutover `HARD-OFF`; migration 092 NOT APPROVED; new corrected real-MariaDB RC01–RC14 pass count ZERO; historical original real MariaDB attempt FAILED `TWO_STALE_COMMITS`; D12 15 import gates BLOCKED; Stage D UNAUTHORIZED; 150 VU HOLD; 200 VU PROHIBITED. No production, staging, cPanel/AWS or protected legacy C137 fixture contact permitted.

**GitHub evidence rule:** branch from WS43, add **only this Markdown file**, then independently remotely compare to parent and recheck protected production/original branches, HARD-OFF gate and historical runner hashes.
