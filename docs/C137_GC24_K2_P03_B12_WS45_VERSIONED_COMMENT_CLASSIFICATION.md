# C137 / GC24 / K2-P03 / B12 — WS45 versioned executable SQL comment classification

**2026-10-15 — successful, separately user-authorized read-only local scanner; NO restore authorization.**

## Provenance and protected source state

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Immediate parent evidence branch `c137-gc24-k2p03-b12-ws44-sql-semantic-evidence-source`, SHA `1cad7dffc6096214cf7747dd9770e2ae841e5282`.
- New evidence-only branch `c137-gc24-k2p03-b12-ws45-versioned-comment-evidence-source`.
- Original protected B12 source branch `c137-gc24-k2p03-b12-cas-writer-cutover-source` SHA `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Protected production source `v320-renee-agrisuite-branding` SHA `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- Literal B12 HARD-OFF gate `lib/daily_record_revision_cutover_gate.php` blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`.
- Historical WS07 offline runner `scripts/verify_k2p03_b12_ws07_offline.sh` blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`.

## WS45 authorization and scope

After WS44 identified **one unclassified versioned comment** and **138 ALTER-class versioned comments**, the user explicitly authorized WS45 for a focused local-only, read-only, sanitized classification: no SQL execution, no Docker exec, no MariaDB connection or root credential/log reading, no backup copy/upload/import, no schema changes or customer disclosure. WS45 verified Mac source branch/commit and clean worktree, protected Docker Desktop context, pinned image fingerprint, exact new isolated MariaDB 11.4 container `renee-b12-ws32-mariadb114-stopped` (running), separate dedicated volume `renee-b12-ws32-datadir`, two original protected running containers and three original named volumes, plus pinned local backup file SHA256 and original metadata.

Backup remains `~/Downloads/database.sql`, size **619163 bytes**, SHA-256 **`c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`**, local 0600 file; user reports production extraction, but provenance and snapshot consistency **not independently certified**. Backup raw bytes were processed solely in the local Python scanner. Output included only allowlisted classifications and counts, no SQL text, identifiers, customer records or passwords.

## Exact user-returned audit

```text
#####COPY FROM HERE#####
AUDIT=B12_WS45_VERSIONED_COMMENT_CLASSIFICATION
SOURCE_GUARDS=PASS
LOCAL_DOCKER_DESKTOP=PASS
MARIADB_IMAGE_11_4=PASS
TARGET_CONTAINER_IDENTITY=PASS
TARGET_CONTAINER_STATE=RUNNING
NETWORK_MODE=NONE
DEDICATED_VOLUME=PASS
BACKUP_SHA256=PASS
BACKUP_IDENTITY_UNCHANGED=PASS
SQL_SCANNER=TARGETED_LEXICAL_HEURISTIC
VERSIONED_COMMENT_TOTAL=364
MYSQL=361
MARIADB=3
GUARD_ABSENT=0
GUARD_AT_OR_BELOW_110400=363
GUARD_ABOVE_110400=1
MYSQL_50700_99999_IGNORE_RANGE=0
SET=225
SET_GLOBAL_OR_PERSIST=0
ALTER=138
ALTER_DISABLE_KEYS=69
ALTER_ENABLE_KEYS=69
ALTER_OTHER=0
ALTER_QUALIFIED_TARGET=0
EMPTY_BODY=0
NUMERIC_ONLY_BODY=0
NONALPHABETIC_START=1
OTHER_UNKNOWN=0
OTHER_SQL_COMMAND=0
EMBEDDED_SEMICOLONS=0
NESTED_COMMENT_MARKERS=0
WS44_COMMENT_FAMILY_BASELINE=MATCH
WS44_ALTER_BASELINE=MATCH
ALTER_NON_KEY_OPERATIONS=NOT_FLAGGED
UNCLASSIFIED_COMMENT_COUNT=1
MARIADB_11_4_EXECUTION_PREDICATES=HEURISTIC_ONLY
EXECUTABLE_COMMENT_SAFETY=NOT_CERTIFIED
RESTORE_COMPATIBILITY=NOT_CERTIFIED
BACKUP_SNAPSHOT_CONSISTENCY=NOT_CERTIFIED
RESTORE_AUTHORIZED=NO
RAW_SQL_DISPLAYED=NO
SQL_IDENTIFIERS_DISPLAYED=NO
CUSTOMER_DATA_DISPLAYED=NO
BACKUP_COPIED=NO
BACKUP_UPLOADED=NO
DATABASE_CONNECTIONS=0
SQL_EXECUTED=0
DOCKER_EXEC_EXECUTED=NO
DOCKER_LOGS_READ=NO
ROOT_PASSWORD_ACCESSED=NO
DOCKER_RESOURCES_CHANGED=NO
BACKUP_RESTORED=NO
MIGRATION_092_EXECUTED=NO
PROTECTED_RUNNING_CONTAINERS=2
PROTECTED_ORIGINAL_VOLUMES=3
RESOURCE_INVENTORY_STABLE=PASS
PRODUCTION_CONTACT=NO
B12_CUTOVER=OFF
RESULT=WS45_READONLY_CLASSIFICATION_COMPLETE
#####COPY TO HERE#####
```

## Supported conclusions, without overstating

- **All 138 versioned `ALTER` directives were locally classified by the WS45 scanner as `ALTER TABLE ... DISABLE KEYS` (69) and `ALTER TABLE ... ENABLE KEYS` (69).** No other `ALTER` subtype or qualified target was flagged. This is strong evidence they are ordinary dump-style key-management statements and narrows the WS44 concern. It is still a lexical classification rather than full execution certification.
- The 364 total comments split as 361 MySQL-style `/*! ... */` and 3 MariaDB-style `/*M! ... */`. 225 are `SET` and zero of those matched `GLOBAL`/`PERSIST` keyword tests. No embedded semicolons or nested comment markers were identified.
- **Exactly one comment remains unclassified**, now specifically `NONALPHABETIC_START=1` after numeric guard stripping/quote masking; there were no empty, numeric-only, or other recognized SQL command bodies. Do not assert this comment is harmless, active, inactive, or tied to the one above-version guard: **the audit did not correlate these two categories by comment**.
- Numeric guard heuristic: 363 version numbers at/below nominal `110400`, one above; none in the special 50700–99999 MySQL range. **The result does not implement authoritative MariaDB version-comment execution semantics**. Do not conclude that 363 statements will execute or that the above-version statement will not execute without correctly validating comment family and version handling.
- WS44's screening found zero top-level `USE`, database DDL, routine/trigger/event/view DDL, grants, external file/plugin operations and apparent cross-schema qualified references. Those are heuristic zeros, not a complete SQL parse. WS43 found 69 `DROP TABLE` and 69 `CREATE TABLE`, 65 `INSERT INTO`, 69 `LOCK`/`UNLOCK TABLES` pairs, and no transaction begin/commit marker. **Global production snapshot consistency and import compatibility are not proven.**
- WS42 previously confirmed a single authenticated SQL `SELECT 1` using an ephemeral locally handled root credential in the new network-isolated container. That permission is consumed; WS45 did not repeat log/SQL access. No production, staging, protected C137 fixture, AWS/cPanel or user data was contacted or modified.

## Next controlled gate: WS46 proposed, NOT YET AUTHORIZED

Obtain separate authorization for **one narrow local read-only classifier** to inspect *only the one `NONALPHABETIC_START` comment* (not re-run broad SQL scans), reporting its safe punctuation/whitespace/token-type class and whether it coincides with the single above-version-guard comment, **without printing source SQL, identifiers, strings, passwords or the comment's raw bytes**. Validate family and numeric guard precisely and cross-check the classification against the MariaDB 11.4 executable-comment rules; the actual semantic model may require more than lexical inspection. Preserve original source/Docker/backup identities and hard-OFF. No connection to any database, SQL, root credential extraction, Docker exec/logs, Docker writes, data import, migration 092 or cutover.

Even if the single comment resolves benignly, **restoration requires a separately approved plan** for a wholly new named schema inside only the isolated test container, strict destination pinning/denylist, containment of production-derived personal/customer records, import error/rollback handling, host/guest storage headroom, and later controlled destructive SQL authorization. Dump with `DROP TABLE` must never run against protected databases.

## Standing B12 gates

`B12_CUTOVER=HARD_OFF`; migration 092 NOT APPROVED; repaired real MariaDB RC01–RC14 passes **ZERO**, historical original real test FAILED `TWO_STALE_COMMITS`; D12 15 import gates BLOCKED; Stage D UNAUTHORIZED; 150 VU HOLD; 200 VU PROHIBITED. Production, staging, AWS/cPanel and protected original Docker resources unaffected.

**GitHub semantic-diff contract:** add only this Markdown file on a docs-only successor branch; independently confirm remote ahead 1/behind 0, exact single documentation-file addition, protected original and production refs unchanged, and hard-OFF gate + historical WS07 blobs unchanged.
