# C137 / GC24 / K2-P03 / B12 — WS46S MariaDB sandbox-directive classification

**2026-10-15 — successful user-authorized, local, read-only, sanitized comment classification. NOT a database-import authorization or MariaDB client parser certification.**

## Source provenance and protected state

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Parent evidence-only branch `c137-gc24-k2p03-b12-ws46r-engine-guard-blocked-evidence-source`, SHA `aec6bf717647e2a046bbb634005999db27249f50`.
- WS46S evidence-only branch: `c137-gc24-k2p03-b12-ws46s-sandbox-directive-evidence-source`.
- Protected original B12 source branch `c137-gc24-k2p03-b12-cas-writer-cutover-source` SHA `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Protected production branch `v320-renee-agrisuite-branding` SHA `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- Literal B12 cutover HARD-OFF gate `lib/daily_record_revision_cutover_gate.php` blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`.
- Original historical WS07 offline runner `scripts/verify_k2p03_b12_ws07_offline.sh` blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`.

## Why WS46S was required

Earlier WS44/WS45 local backup lexical audits had identified 364 versioned comments: 361 MySQL `/*! ... */`, 3 MariaDB `/*M! ... */`; 225 `SET`; 138 `ALTER` (69 each `DISABLE KEYS` / `ENABLE KEYS`); exactly one unknown category `NONALPHABETIC_START`, with one version predicate above 110400 but no prior correlation between those findings.

First WS46 script had an assistant-authored unpacking bug `family, guard, body = unknown` instead of `unknown[0]`. Corrected WS46R script then had an overly strict Docker architecture guard: exactly `amd64` accepted, versus earlier established `amd64` or `x86_64`. User output for WS46R was `BLOCKED / FAILED_STAGE=DOCKER_ENGINE / REASON=DOCKER_ENGINE_MISMATCH`; backup scanning was never reached. Both failed attempts were documented; no earlier comment conclusions should be inferred from them.

WS46S returned the architecture class `X86_64`, satisfying the original approved two-form Docker engine policy, and fixed the one-tuple unpack bug. The user had approved local-only, read-only classification of the *single* remaining comment. No database connection, SQL, Docker exec/logs, credentials, backup import, Docker writes or production contact were included. It guarded the Mac B12 source head/worktree, Docker Desktop local context and pinned image/container/dedicated volume, 2 protected original containers/3 protected named volumes, and the unmodified backup hash. Its local lexical classification did not expose raw SQL, table names, customer records or credential values.

## Exact user-returned sanitized audit

```text
#####COPY FROM HERE#####
AUDIT=B12_WS46S_UNKNOWN_COMMENT_RESOLUTION
SOURCE_GUARDS=PASS
DOCKER_ENGINE_ARCH_CLASS=X86_64
LOCAL_DOCKER_DESKTOP=PASS
IMAGE_IDENTITY=PASS
TARGET_CONTAINER_IDENTITY=PASS
DEDICATED_VOLUME=PASS
BACKUP_SHA256=PASS
VERSIONED_COMMENT_TOTAL=364
UNCLASSIFIED_COMMENT_COUNT=1
UNCLASSIFIED_COMMENT_FAMILY=MARIADB
UNCLASSIFIED_COMMENT_CATEGORY=MARIADB_CLIENT_SANDBOX_DIRECTIVE
UNCLASSIFIED_GUARD_CLASS=ABOVE_110400
UNKNOWN_IS_ABOVE_GUARD_COMMENT=YES
ABOVE_VERSION_GUARD_COUNT=1
SERVER_EXECUTION_SEMANTICS=NOT_CERTIFIED
IMPORT_CLIENT_COMPATIBILITY=NOT_CERTIFIED
SQL_SCANNER=TARGETED_LEXICAL_HEURISTIC
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
BACKUP_RESTORED=NO
DOCKER_RESOURCES_CHANGED=NO
RESOURCE_INVENTORY_STABLE=PASS
PRODUCTION_CONTACT=NO
MIGRATION_092_EXECUTED=NO
B12_CUTOVER=OFF
RESULT=WS46S_CLASSIFICATION_COMPLETE
#####COPY TO HERE#####
```

## Supported interpretation, narrowly

- The sole previously `NONALPHABETIC_START` comment was categorized by a **fixed allowlisted full-match local lexical pattern** as `MARIADB_CLIENT_SANDBOX_DIRECTIVE`, of `MARIADB` comment family. Therefore the previous unknown *category* is resolved; this is not server/client behavior certification.
- That **same** comment was the **one** carrying an `ABOVE_110400` guard. This proves correlation of the two WS45 categories, not actual execution or absence of client-side interpretation. MariaDB dump-client sandbox directives need import-client-aware handling even if server-version conditional SQL predicates would skip text.
- WS45 already classified all 138 versioned `ALTER` directives into 69 `DISABLE KEYS` and 69 `ENABLE KEYS`; no other ALTER subtype identified in the local lexical screen. Other 225 comments were classified SET; WS44 top-level heuristic reported no `USE`, cross-schema candidate, triggers/routines, privileges or external loading; those negative findings are heuristic only, not a complete parser.
- The user's local `~/Downloads/database.sql` is unchanged according to pinned SHA256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`, 619163 bytes. It includes **69 destructive `DROP TABLE`** statements (WS43), 69 CREATE TABLE, 65 INSERT, 69 LOCK/UNLOCK TABLES pairs, with no observed global transaction boundaries. User reports production origin, but point-in-time consistency and origin remain **NOT independently certified**.
- WS42 separately established **one** successful password-based authenticated root `SELECT 1` on the isolated MariaDB 11.4 server; WS46S had zero SQL and did not reread credentials. Past guest free-space figures and WS43 Mac host backing free 166.86 GiB do not certify import footprint or host capacity.
- New target `renee-b12-ws32-mariadb114-stopped` (RUNNING in spite of name), `mariadb:11.4`, `--network none`, exclusive disposable named `renee-b12-ws32-datadir`. Two original running containers and three original volumes unchanged.

## Next gate: client compatibility qualification, proposed WS47 (NOT authorized)

The remaining **specific import-client dependency** is how the chosen client/version handles the MariaDB dump sandbox directive, especially if an older MariaDB or MySQL client would reject it. A separately user-authorized WS47 should be bounded to **read-only import-client capability/version inspection** in ONLY the new isolated container (e.g. one nonconnecting `mariadb --version` invocation and non-sensitive help/feature validation), without opening the production-derived SQL backup anew or connecting to the server, fetching the root secret, or interpreting/executing dump contents. Do not claim that a version string alone certifies all client parser behavior; if needed, propose separately scoped non-database synthetic parsing tests. No DB query/DDL, dump copy, Docker startup logs, import or migration. Preserve branch/source/container/volume and protected-resource identities.

Any later actual restoration requires **separate explicit authorization**, a freshly named empty application schema inside only the network-isolated new WS32 database container, strong allow/deny-list validation to prevent the 69 DROP TABLE statements from targeting system/old/production schemas, import-client sandbox compatibility, limited handling of live production-derived customer records, rollback/disposal strategy, host/guest storage capacity, and honest limitations about snapshot consistency. The source backup should never be modified or uploaded.

## Standing B12 gates

- `B12_CUTOVER=HARD_OFF`; migration 092 NOT APPROVED.
- Repaired real MariaDB RC01–RC14 pass count **0**; prior historical original MariaDB test **FAILED TWO_STALE_COMMITS**.
- D12 15 import gates BLOCKED; Stage D UNAUTHORIZED; 150 VU HOLD; 200 VU PROHIBITED.
- No production, staging, cPanel, AWS or historic protected C137 fixture contact.
- Github documentation-only evidence: exactly **one new Markdown file** committed on the WS46S branch, independently verify remote semantic diff ahead 1/behind 0 and unchanged original/production protected refs and gate/runner Git blobs.
