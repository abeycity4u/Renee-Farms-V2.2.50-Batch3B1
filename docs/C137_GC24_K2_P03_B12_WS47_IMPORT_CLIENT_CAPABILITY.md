# C137 / GC24 / K2-P03 / B12 — WS47 isolated MariaDB import-client preflight

**2026-10-15 — user-authorized, bounded, nonconnecting, read-only client-capability audit COMPLETE. This is NOT parser certification, SQL import authorization, or B12 cutover approval.**

## Git/source and isolation checkpoint

- Repo: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Immediate parent evidence-only branch: `c137-gc24-k2p03-b12-ws46s-sandbox-directive-evidence-source`, SHA `70526a016a896041449cd981ef74dbd4518ab3b6`.
- New WS47 docs-only branch: `c137-gc24-k2p03-b12-ws47-import-client-capability-evidence-source`.
- Protected original B12 source branch `c137-gc24-k2p03-b12-cas-writer-cutover-source`: `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Protected production source branch `v320-renee-agrisuite-branding`: `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- Literal B12 cutover HARD-OFF gate `lib/daily_record_revision_cutover_gate.php` blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`.
- Historical WS07 offline runner `scripts/verify_k2p03_b12_ws07_offline.sh` blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`.
- Isolated new test container `renee-b12-ws32-mariadb114-stopped` (historical name; actually RUNNING), image `mariadb:11.4`, `--network none`, no published ports; dedicated new volume `renee-b12-ws32-datadir`, disposable WS32 scope. Two original protected running containers and three original named volumes unchanged. Docker Desktop engine arch reports `x86_64`; cached pinned image is Linux `amd64`.
- The original user-reported production-derived SQL backup `~/Downloads/database.sql` was **NOT opened in WS47**. Earlier WS43/44/45/46S verified size 619163 bytes and pinned SHA-256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`. Live production origin and consistency have not been independently attested.

## Explicit authorization and bounded operations

User answered `yes` when asked to authorize WS47 to inspect **only the MariaDB client version and non-sensitive capabilities inside the new isolated test container**, without accessing the database or production-derived SQL backup. A guarded Mac Python command verified original source branch/head/worktree and GitHub remote; local Docker Desktop context/engine and image fingerprint; the new target state/isolation, dedicated volume and shared-resource exclusion, stable Docker container/volume/network inventories.

Exactly **two nonconnecting client commands** were invoked in the isolated test container, as unprivileged guest UID/GID `65534:65534`, with option files disabled by `--no-defaults` and HOME set to `/nonexistent`: `mariadb --no-defaults --version` and `mariadb --no-defaults --help`. Tool captured output locally and emitted only allowlisted aggregated classifications. No SQL, connection attempt, `mysql` schema read, password, Docker log, backup file, or customer data access. No image download or container/volume configuration modification.

## Exact user-returned audit

```text
#####COPY FROM HERE#####
AUDIT=B12_WS47_IMPORT_CLIENT_CAPABILITY
SOURCE_GUARDS=PASS
LOCAL_DOCKER_DESKTOP=PASS
IMAGE_IDENTITY=PASS
TARGET_CONTAINER_IDENTITY=PASS
TARGET_CONTAINER_STATE=RUNNING
NETWORK_MODE=NONE
DEDICATED_VOLUME=PASS
DOCKER_ENGINE_ARCH_CLASS=X86_64
CLIENT_BINARY=MARIADB
CLIENT_VERSION_INVOCATIONS=1
CLIENT_HELP_INVOCATIONS=1
CLIENT_VERSION_FAMILY=11_4
CLIENT_IMAGE_FAMILY_MATCH=YES
HELP_PROTOCOL_FLAG=OBSERVED
HELP_SOCKET_FLAG=OBSERVED
HELP_BATCH_FLAG=OBSERVED
HELP_EXECUTE_FLAG=OBSERVED
HELP_SANDBOX_REFERENCE=OBSERVED
CLIENT_PROCESSES_UNPRIVILEGED=YES
CLIENT_OPTION_FILES_DISABLED=YES
DATABASE_CONNECTIONS=0
SQL_EXECUTED=0
CUSTOMER_DATA_ACCESSED=NO
BACKUP_OPENED=NO
BACKUP_COPIED=NO
BACKUP_RESTORED=NO
DOCKER_LOGS_READ=NO
ROOT_PASSWORD_ACCESSED=NO
RAW_CLIENT_HELP_DISPLAYED=NO
RAW_CLIENT_VERSION_DISPLAYED=NO
DOCKER_RESOURCE_CONFIGURATION_CHANGED=NO
PROTECTED_RUNNING_CONTAINERS=2
PROTECTED_ORIGINAL_VOLUMES=3
RESOURCE_INVENTORY_STABLE=PASS
SANDBOX_DIRECTIVE_PARSER_SUPPORT=NOT_CERTIFIED
RESTORE_COMPATIBILITY=NOT_CERTIFIED
RESTORE_AUTHORIZED=NO
MIGRATION_092_EXECUTED=NO
PRODUCTION_CONTACT=NO
B12_CUTOVER=OFF
RESULT=WS47_READONLY_CLIENT_PREFLIGHT_COMPLETE
#####COPY TO HERE#####
```

## Findings, boundaries and what is NOT proved

1. WS47 confirms the **available client binary is MariaDB, major/minor family 11.4**, matching the isolated `mariadb:11.4` container image family. It advertises `--protocol`, `--socket`, `--batch`, `--execute` in help and has **a sandbox reference** in help. The audit did not report the full patch version, raw version or complete help.
2. WS46S previously identified **one** above-11.4.0 guarded `/*M! ... */` comment as a **MariaDB client sandbox directive** by an exact allowlisted local lexical classifier; it is the sole formerly unknown category out of 364 versioned comments. WS45 classified the 138 `ALTER` versioned directives as 69 `DISABLE KEYS`, 69 `ENABLE KEYS`; 225 `SET` directives were separately classified. These lexical results do not simulate the MariaDB client parser or MySQL/MariaDB server conditional-comment semantics.
3. **A help reference to sandbox is not proof of directive handling.** Actual client parser behavior, compatibility with the dump command and client/server safety remain `NOT_CERTIFIED`. The absence of client connection and SQL is a deliberate safety boundary, not an omission to gloss over.
4. WS43 previously confirmed 69 top-level `DROP TABLE` (destructive), 69 `CREATE TABLE`, 65 `INSERT INTO` and 69 `LOCK`/`UNLOCK TABLES` pairs, but no global transaction begin/commit markers. **Coherent point-in-time snapshot** remains unproven. The header reports MariaDB 11.4 but that is not complete import-schema compatibility.
5. WS42 authenticated one read-only `SELECT 1` in this isolated test server using an ephemeral locally protected generated root secret; that was a separately authorized, already consumed permission. WS47 neither used nor exposed that credential. Earlier host free space (166.86 GiB WS43) and reported virtual guest capacity do **not** prove expanded import storage sufficiency.
6. No backup was restored and no SQL/schema migration/cutover was executed; original test resources, production, staging, AWS/cPanel, and any customer data remain untouched.

## Narrow next proposed authorization: WS48 synthetic client parsing behavior, NOT authorized yet

Recommend a separately authorized, clearly bounded **synthetic, database-free client parser probe** using only hardcoded non-customer input representing the MariaDB sandbox directive; **do not open, stream, copy or import production-derived backup data**. Preflight independently guards original source/Git remote, pinned image and exact isolated Docker target, network none and protected resources. Probe must be designed so it **cannot connect to a database or execute any SQL**, e.g. an isolated nonconnecting parser-only mode if available; if no reliable client-only parsing path exists, report **BLOCKED / NOT_AVAILABLE**, not a guessed PASS. Never access the random root secret, startup logs or database and never send raw client output into chat; report allowlisted status only. No Docker write side effects or uncontrolled resource creation. A version/help observation alone does not satisfy WS48. Do not re-run completed backup scans.

After potential parser qualification, **an actual restore still requires separate, explicit permission and carefully reviewed isolation plan**: fresh dedicated empty named schema inside only the WS32 network-isolated MariaDB server; fail-closed system/protected schema denylist and definite target schema selection; containment of real production-derived customer information; host and guest disk capacity; import error/rollback/disposal; origin/snapshot-consistency limitations. Never run 69 `DROP TABLE` operations against production, staging, old protected containers or any existing application schema.

## Global locked gates

- B12 daily-record CAS writer cutover literal **HARD-OFF**.
- Migration 092 **NOT AUTHORIZED**; corrected real MariaDB RC01–RC14 certification: **0** passes; prior original real concurrency test **FAILED `TWO_STALE_COMMITS`**.
- D12 15 import gates BLOCKED, Stage D UNAUTHORIZED, 150 VU HOLD, 200 VU PROHIBITED.
- No contact with production, staging, cPanel/AWS, or protected original C137 fixture.
- GitHub record contract: exactly **one docs file added** on new WS47 branch; independently compare remote to immediate parent (ahead 1, behind 0) and verify protected branch SHAs and gate/runner blob fingerprints.
