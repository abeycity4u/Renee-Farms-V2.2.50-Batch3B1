# C137 / GC24 / K2-P03 / B12 — WS51S read-only import-safety lexical screening

**2026-10-15 — user-authorized, strictly local, static, read-only audit COMPLETE: `WS51S_READONLY_SCREEN_COMPLETE`.** This is evidence of matching previously observed *lexical* SQL structure and no risk flags from this scanner; it does **NOT** establish a MariaDB SQL-grammar-certified allowlist, safe import destination, disk-capacity sufficiency, consistency of the historic production-derived dump, restoration authorization, migration 092 approval, or B12 cutover.

## Identity and branch lineage

- Repo: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Immediate parent evidence branch: `c137-gc24-k2p03-b12-ws51r-readonly-preflight-evidence-source`, SHA `3c7ebdf37eed3b1ea851a944390a0e9119d2d300`.
- New WS51S evidence-only branch: `c137-gc24-k2p03-b12-ws51s-static-sql-safety-evidence-source`.
- Protected original B12 source branch `c137-gc24-k2p03-b12-cas-writer-cutover-source`: `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Protected production branch `v320-renee-agrisuite-branding`: `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- Original cutover HARD-OFF gate `lib/daily_record_revision_cutover_gate.php` blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`.
- WS07 historical verifier `scripts/verify_k2p03_b12_ws07_offline.sh` blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`, available in **later documentation/evidence history**, **absent** in the protected original B12 commit. Do not require `HEAD:path` for this later file in the original checkout.

## Authorization and method

User explicitly authorized WS51S additional **read-only** local SQL-import-risk and storage preflight. No SQL was submitted to any client/server; no database connection or Docker exec; no database/volume/container creation or alteration; no credentials or production network systems accessed.

The Mac Terminal script guarded original checkout identity, gate, expected original WS07 runner absence, local Docker Desktop context/engine, pinned cached `mariadb:11.4` image fingerprint, pre-existing WS32 identity/network-none/exclusive volume, two original protected running containers and three original protected named volumes. It checked before/after Docker resource inventories and engine/context. The locally stored, user-reported production-derived `~/Downloads/database.sql` was **opened for read-only in-memory screening** (not copied or uploaded); prior pinned size **619163 bytes**, SHA256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`, owner/mode checks passed, with no SQL text, SQL identifiers, customer records, credentials or table names printed.

The script implements its own **statement-aware lexical screen, NOT a full MariaDB grammar parser**. It distinguishes quoted text/backticked identifiers, line/block/versioned comments, semicolon boundaries and selected leading SQL words; recognizes one MariaDB sandbox-directive comment; and records a fixed set of risk heuristics. It does **not** certify every MariaDB SQL-mode, client-directive, conditional-comment or version-specific execution rule. The classification of `ALTER` and `SET` inside versioned comments is structural, not a guarantee of unconditional execution.

## Exact user-returned sanitized evidence

```text
#####COPY FROM HERE#####
AUDIT=B12_WS51S_IMPORT_SAFETY_STATIC
SOURCE_GUARDS=PASS
HARD_OFF_GATE=PASS
WS07_ORIGINAL_PATH=ABSENT_EXPECTED
LOCAL_DOCKER_DESKTOP=PASS
IMAGE_IDENTITY=PASS
WS32_ISOLATION=PASS
PROTECTED_RUNNING_CONTAINERS=2
PROTECTED_ORIGINAL_VOLUMES=3
RESOURCE_INVENTORY_STABLE=PASS
BACKUP_SHA256=PASS
BACKUP_SIZE_BYTES=619163
BACKUP_OWNER_AND_MODE=PASS
MAC_HOST_FREE_GIB=166.81
BACKUP_FILESYSTEM_FREE_GIB=166.81
DOCKER_BACKING_STATUS=HOST_FILE_LOCATED
DOCKER_BACKING_FREE_GIB=166.81
DOCKER_DISK_LOGICAL_GIB=233.47
DOCKER_DISK_ALLOCATED_GIB=2.81
DOCKER_GUEST_CAPACITY=NOT_ATTESTED
EXPANDED_IMPORT_FOOTPRINT=UNKNOWN
RESTORE_SPACE_SUFFICIENCY=NOT_CERTIFIED
SCREEN_METHOD=STATEMENT_AWARE_LEXICAL
MYSQL_GRAMMAR_PARSER=NOT_ESTABLISHED
VERSIONED_COMMENT_TOTAL=364
MYSQL_COMMENTS=361
MARIADB_COMMENTS=3
SANDBOX_DIRECTIVE_COUNT=1
ABOVE_VERSION_GUARD_COUNT=1
COMMENT_BASELINE=MATCH
DROP_TABLE=69
CREATE_TABLE=69
INSERT_INTO=65
LOCK_TABLES=69
UNLOCK_TABLES=69
ALTER_TABLE=138
SET=225
OTHER_STATEMENTS=0
TABLE_OPERATION_BASELINE=MATCH
SENSITIVE_KEYWORD_HITS=0
QUALIFIED_NAME_CANDIDATES=0
CLIENT_BACKSLASH_OUTSIDE_COMMENTS=0
UNKNOWN_COMMENT_BODIES=0
EMBEDDED_COMMENT_SEMICOLONS=0
UNTERMINATED_STATEMENTS=0
TOTAL_LEXICAL_RISK_FLAGS=0
SQL_PARSER_AWARE_ALLOWLIST=NOT_CERTIFIED
SQL_DESTINATION_CONTAINMENT=NOT_CERTIFIED
BACKUP_SNAPSHOT_CONSISTENCY=NOT_CERTIFIED
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
PRODUCTION_CONTACT=NO
RESTORE_AUTHORIZED=NO
MIGRATION_092_EXECUTED=NO
B12_CUTOVER=OFF
RESULT=WS51S_READONLY_SCREEN_COMPLETE
#####COPY TO HERE#####
```

## Findings, implications and limits

### Structural matches

| Lexically identified type | WS51S count | Why it matters |
|---|---:|---|
| DROP TABLE | **69** | Destructive if routed to an existing database; must only target a demonstrably new empty disposable application schema |
| CREATE TABLE | 69 | Mirrors DROP count structurally; not proof of valid full-DDL import |
| INSERT INTO | 65 | May contain production-derived personal/tenant data; do not display values or table names |
| LOCK TABLES / UNLOCK TABLES | 69 / 69 | Does **not** establish point-in-time transactional snapshot |
| ALTER TABLE (versioned comments) | 138 | Earlier WS45 classified as 69 DISABLE KEYS and 69 ENABLE KEYS; actual execution predicates remain version-dependent |
| SET (versioned comments) | 225 | Earlier screens found no GLOBAL/PERSIST candidate, but full effective server/client semantics not certified |
| OTHER_STATEMENTS | 0 | Only within this scanner's tokenization, not proof of universal SQL safety |

Exactly 364 versioned comments: 361 `/*! ... */` (MySQL) and 3 `/*M! ... */` (MariaDB); 1 synthetic-previously-rehearsed MariaDB client sandbox directive and exactly one guard above `110400`. Both high-level comment and table-operation baselines **MATCH** earlier WS44–WS46 checks.

### Risk flags

`SENSITIVE_KEYWORD_HITS=0`, `QUALIFIED_NAME_CANDIDATES=0`, `CLIENT_BACKSLASH_OUTSIDE_COMMENTS=0`, `UNKNOWN_COMMENT_BODIES=0`, `EMBEDDED_COMMENT_SEMICOLONS=0`, `UNTERMINATED_STATEMENTS=0`, `TOTAL_LEXICAL_RISK_FLAGS=0`. These zeroes are **negative findings from the narrow lexical heuristics only** and are not authoritative proof that the backup cannot contain a dangerous execution path. Do not promote these findings to a certified MariaDB parser-aware allowlist.

### Space

Current Mac host/backup filesystem/Docker-backing host filesystem free space each reported **166.81 GiB**; these are overlapping measurements **not three additive pools**. Located Docker backing file logical size **233.47 GiB**, allocated **2.81 GiB**. Actual Docker guest free/inode/quota state and expanded table/index/redo/temporary storage consumption **NOT_ATTESTED / UNKNOWN**. No storage sufficiency certification can be made by comparing compressed SQL dump bytes to free host space.

### Persisting blockers

- Complete executable SQL grammar and MariaDB client/server comment semantics; enforceable allowlist and fail-closed destination isolation **NOT CERTIFIED**.
- New restore-only container/volume and unique provably empty target schema **NOT CREATED**. Restricted import account, grants, key/credential lifecycle, data protection, cleanup and rollback **PLAN ONLY** (WS50).
- Coherent historical production snapshot, independent production export provenance and data-level consistency **NOT CERTIFIED**.
- Production-derived customer data import **NOT AUTHORIZED**, even after WS49's successful synthetic directive+SELECT 1 acceptance.
- Original B12 real MariaDB concurrency: prior FAILED `TWO_STALE_COMMITS`, repaired RC01–RC14 pass count remains **0**.

## Recommended next controlled decision — NOT YET AUTHORIZED

**Do not proceed directly to WS53 import.** Prior to any restoration, close executable SQL routing/allowlist and data-containment gaps, or prove that a separate new server plus strictly schema-scoped importer enforces the required boundaries and obtain deliberate user acceptance of remaining parser uncertainty.

A practical next **WS51T** step would be a limited **design/feasibility gate** for dialect-aware SQL parser or import-account SQL restrictions and a strictly bounded risk contract, leveraging existing WS51S evidence without needlessly re-running WS42–WS49. It should be read-only unless separately authorized, and output only capability/status enums. On failure, remain blocked. A later **WS52 infrastructure-only stage** may be proposed under separately explicit authorization *only if its permitted changes, rollback, zero customer-data access, original resource guards, and remaining import blockers are clearly isolated from any WS53 import permission*. User approval of WS51S does not approve WS51T, WS52, WS53 or WS54.

**Security locks unchanged:** `B12_CUTOVER=HARD_OFF`; migration 092 NOT AUTHORIZED; D12 15 import gates BLOCKED; Stage D UNAUTHORIZED; 150 VU HOLD; 200 VU PROHIBITED; no production/staging/AWS/cPanel or protected original C137 operations.

**GitHub docs-only evidence contract:** exactly one new Markdown file on one new successor branch, independently verified remote diff against WS51R immediate parent (ahead 1, behind 0), plus unchanged production/original branch HEADs, hard-OFF gate blob and historical WS07 runner evidence blob. No raw original SQL backup, names, rows, secrets or source-code changes added to GitHub.
