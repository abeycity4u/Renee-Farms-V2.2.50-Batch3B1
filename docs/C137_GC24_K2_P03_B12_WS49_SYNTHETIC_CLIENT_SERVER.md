# C137 / GC24 / K2-P03 / B12 — WS49 synthetic MariaDB client/server sandbox-directive acceptance

**2026-10-15 — user-authorized, network-isolated, disposable, synthetic-only two-query test passed; disposable resources removed. This does not authorize or establish production-backup import, sandbox security enforcement, migration 092, RC01–RC14 certification or B12 cutover.**

## Protected GitHub source

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Immediate parent evidence-only branch: `c137-gc24-k2p03-b12-ws48-offline-parser-blocked-evidence-source`, SHA `0b27d3d59b82ea79fd01f6256286d87a5f5b0672`.
- New docs-only WS49 branch: `c137-gc24-k2p03-b12-ws49-synthetic-client-server-evidence-source`.
- Original protected B12 source branch: `c137-gc24-k2p03-b12-cas-writer-cutover-source`, SHA `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Protected production branch: `v320-renee-agrisuite-branding`, SHA `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- Literal B12 HARD-OFF gate blob: `lib/daily_record_revision_cutover_gate.php`, `6e88c913ad5e5b371a9d0c92ce08870214ac1268`.
- Historical WS07 offline runner blob: `scripts/verify_k2p03_b12_ws07_offline.sh`, `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`.

## Authorization and exact bounded test

The user explicitly said `yes` to WS49 creation of a **new independently isolated disposable MariaDB 11.4 client/server container and volume**, with only hardcoded synthetic input, limited socket-based test queries, and guarded teardown. That authorization **does not include** touching the original user-reported production-derived `~/Downloads/database.sql`, real user/customer data, pre-existing WS32 database, protected old containers/volumes, credentials, actual restoration or app migrations.

The supplied Mac Python executor preflighted protected original source branch/HEAD/clean worktree and Git remote; local Docker Desktop context/engine and cached pinned MariaDB Linux/amd64 image fingerprint `2ee3c4a72fcac30a`; original three running containers, four named volumes and network inventory, including WS32 running `renee-b12-ws32-mariadb114-stopped` with exclusive `renee-b12-ws32-datadir`, and two older protected running containers with three older named volumes. It required an unused dedicated WS49 container/volume name and host storage headroom.

The test-created, separately labeled resources:

- Disposable container `renee-b12-ws49-sandbox-mariadb114`, matching pinned cached `mariadb:11.4` image.
- Disposable volume `renee-b12-ws49-sandbox-datadir`, exclusive `/var/lib/mysql` mount; labels `renee.b12.scope=WS49`, `renee.b12.class=disposable`.
- `--network none`, no published ports; server `--skip-networking`. Guest local Unix socket only, `--restart no`, memory/memory-swap 768 MiB, CPU 1, PIDs 128, nonprivileged, `no-new-privileges`.
- `MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=1` **ONLY for this newly disposable, network-isolated test server**. This should **never** be copied to production, staging, WS32 or future real customer-data tests.
- Exactly 2 client query attempts against *only the new WS49 Unix socket*: (1) `SELECT 1;`, (2) hardcoded `/*M!999999\\- enable the sandbox mode */` followed by `SELECT 1;`. Pass predicate required process exit zero and sole output `1` in each case. No DDL or non-synthetic data.
- Teardown code checked the exact Docker context/engine ID, new resource container-ID/name/labels and new volume name/labels before deleting **only WS49 container/volume**. It then compared container, running-container, volume and network inventories against the original baseline. User-returned audit says all disposal and baseline-recovery flags PASS.

## Exact user-returned WS49 audit

```text
#####COPY FROM HERE#####
AUDIT=B12_WS49_SYNTHETIC_CLIENT_SERVER
RESULT=SYNTHETIC_CLIENT_TEST_PASS
FAILED_STAGE=NONE
REASON=NONE
SOURCE_GUARDS=PASS
TEST_IMAGE=MARIADB_11_4_PINNED
TEST_CONTAINER_SCOPE=WS49_ONLY
NETWORK_MODE=NONE
PUBLISHED_PORTS=NO
SERVER_TCP_NETWORKING=DISABLED
ROOT_AUTH_MODE=DISPOSABLE_LOCAL_EMPTY_PASSWORD
SYNTHETIC_SOCKET_READY=YES
SYNTHETIC_BASELINE_SELECT1=PASS
SYNTHETIC_SANDBOX_SELECT1=PASS
SYNTHETIC_QUERY_ATTEMPTS=2
SANDBOX_DIRECTIVE_ACCEPTED=YES
SANDBOX_MODE_ENFORCEMENT=NOT_TESTED
FULL_BACKUP_IMPORT_COMPATIBILITY=NOT_CERTIFIED
WS49_CONTAINER_DISPOSAL=PASS
WS49_VOLUME_DISPOSAL=PASS
ORIGINAL_RESOURCE_INVENTORY=PASS
PROTECTED_ORIGINAL_CONTAINERS=2
PROTECTED_ORIGINAL_VOLUMES=3
WS32_DATABASE_ACCESSED=NO
PRODUCTION_BACKUP_OPENED=NO
PRODUCTION_BACKUP_COPIED=NO
PRODUCTION_BACKUP_RESTORED=NO
PRODUCTION_CREDENTIALS_ACCESSED=NO
PRODUCTION_CONTACT=NO
MIGRATION_092_EXECUTED=NO
B12_CUTOVER=OFF
#####COPY TO HERE#####
```

## What is established, and what remains NOT certified

**Supported:** A new disposable MariaDB 11.4 client/server accessed via its local Unix socket accepted the supplied synthetic input containing the sandbox directive and then returned `1` for a synthetic `SELECT 1`. The baseline `SELECT 1` also succeeded; query-attempt count was exactly 2. New WS49 container and dedicated volume were removed according to the audit, and the exact Docker resource inventories were restored. Existing WS32 and two old protected containers/volumes stayed outside test scope. No production backup, customer data or production credentials were accessed or moved.

**Not established:** A successful query following a directive does **not independently prove the sandbox-mode restrictions were enforced**, nor distinguish in every case client interpretation from ignoring a comment. Real MariaDB import-client/server parsing behavior for every line of the production-derived backup, full backup restore compatibility, schema isolation, migration readiness and coherent historical production snapshot are still NOT CERTIFIED.

Preceding milestones retained without redundant retests:

- WS42: exactly one separately authorized authenticated `SELECT 1` in new isolated WS32 server, using locally contained ephemeral root secret, no other SQL authorization.
- WS43: production-derived original SQL backup 619163 bytes, pinned SHA256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`; line-anchored heuristics counted 69 `CREATE TABLE`, **69 destructive `DROP TABLE`**, 65 `INSERT INTO`, 69 each `LOCK TABLES` / `UNLOCK TABLES`, no `START TRANSACTION`/`COMMIT` observed; Mac backing free 166.86 GiB, which does not certify expanded import footprint. Source reported as real live-production-derived by user, capture consistency and independent origin NOT verified.
- WS44–WS45: 364 versioned SQL comment items: 225 SET, 138 ALTER classified as 69 each DISABLE/ENABLE KEYS, one formerly unclassified.
- WS46S: the one previously unclassified versioned comment identified by a fixed pattern as a MariaDB **client sandbox directive**, with the sole above-version-11.4.0 guard; client-versus-server conditional handling still relevant.
- WS47: available MariaDB 11.4 client with help sandbox reference; no parser certification.
- WS48: no verified offline/parser-only mode found among four candidate option names; fail-closed `BLOCKED_NO_VERIFIED_OFFLINE_PARSER`. WS49 now demonstrates synthetic input acceptance using a separate disposable server, **not** comprehensive sandbox enforcement.

## Next decision / proposed WS50 (NOT AUTHORIZED)

Recommend a **read-only restoration-readiness design gate** rather than immediately authorizing import. Define an enforceable, audited plan for eventual handling of production-derived backup data: target only the current dedicated WS32 network-isolated server (or newly approved fully separate ephemeral import target), create **only a freshly named and demonstrably empty application schema**, denylist all system/previous/protected application schemas, ensure client-side sandbox-command compatibility, pin target container ID/volume/image and schema before any SQL, review and contain the 69 destructive DROP TABLE statements, establish bounded root credential handling without startup log re-exposure, determine host/guest free-space and expanded data footprint, protect real customer records (including post-test storage and disposal), capture error/rollback strategy and avoid misrepresenting dump snapshot consistency. **Do not run SQL, copy/import the backup, read existing credentials/logs, change Docker or migrate in WS50 unless separately authorized with concrete operations.** A concise static plan may be prepared first; subsequent non-read-only stages each require explicit user authorization. No retrospective retesting of closed WS42–WS49 unless new regression evidence.

## Standing release and security locks

- B12 CAS writer cutover **HARD-OFF**, no flag lift.
- Migration **092 NOT APPROVED**.
- Corrected real MariaDB RC01–RC14 passes: **0**; historical original real test **FAILED `TWO_STALE_COMMITS`**.
- D12 15 import gates **BLOCKED**, Stage D **UNAUTHORIZED**, 150 VU **HOLD**, 200 VU **PROHIBITED**.
- No production, staging, AWS/cPanel or protected historical fixture operations. Original source/worktree and protected branches unchanged.
- Docs-only evidence branch must add **exactly one Markdown file**; independently verify remote ahead 1/behind 0 and unchanged production/original branch SHAs plus HARD-OFF gate and WS07 runner blobs.
