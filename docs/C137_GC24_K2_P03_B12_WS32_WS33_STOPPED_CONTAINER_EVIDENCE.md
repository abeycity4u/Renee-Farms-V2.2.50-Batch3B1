# C137 / GC24 / K2-P03 / B12 — WS32 created, WS33 independently verified: stopped isolated MariaDB candidate

**Date: 2026-10-15. Evidence is user-returned Mac Docker metadata from expressly authorized creation-only WS32 and nonmutating WS33. Not a restored clone, runtime database validation, production backup identity attestation or B12 certification.**

## Immutable source and protected references

- Repo: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Parent: WS31 `c137-gc24-k2p03-b12-ws31-docker-storage-evidence-source` at `0c2931ea88d4363e330c78d1ffa1d797de245327`.
- WS32–WS33 evidence branch: `c137-gc24-k2p03-b12-ws33-isolated-container-evidence-source`.
- Original B12 source branch: `c137-gc24-k2p03-b12-cas-writer-cutover-source`, HEAD `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Protected production branch: `v320-renee-agrisuite-branding`, HEAD `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- Literal hard-OFF cutover gate `lib/daily_record_revision_cutover_gate.php` blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`; immutable WS07 runner `scripts/verify_k2p03_b12_ws07_offline.sh` blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`.

## WS32: user-authorized creation, user-returned output

Explicitly approved scope: create **one new stopped network-disabled MariaDB 11.4 container and one dedicated empty named volume**, without starting any DB or touching the SQL backup.

`AUDIT=B12_WS32_ISOLATED_CONTAINER_CREATION`; `RESULT=WS32_STOPPED_CONTAINER_CREATED_VERIFIED`.

| Attribute | Returned outcome |
| --- | --- |
| SOURCE_GUARDS / DOCKER_LOCAL_IDENTITY / IMAGE_FINGERPRINT | PASS / PASS / PASS |
| Image family, tag, platform | MARIADB, 11.4, LINUX_AMD64 |
| Local cached image fingerprint (WS29) | `2ee3c4a72fcac30a` |
| Container name | `renee-b12-ws32-mariadb114-stopped` |
| Container created, started | YES / NO |
| State | CREATED_STOPPED |
| Network mode; published ports; privileged | NONE; NO; NO |
| Memory limit; CPU | 768 MiB; 1 CPU |
| Dedicated volume | `renee-b12-ws32-datadir` |
| Dedicated volume created/mounted | YES / PASS |
| Existing containers / volumes preserved | 2 / 3 |
| Total containers / volumes after | 3 / 4 |
| Networks unchanged | PASS |
| Backup opened/copied; database initialized | NO / NO; NO |
| Database connections; SQL; migrations; restore | 0; 0; NO; NO |
| Production contact; B12 cutover | NO; OFF |

WS32 did change the **user's local Docker environment only** to create a new stopped container and named volume. Its verification did not query protected containers' DBs. No rollback was performed.

## WS33: independent read-only Docker metadata attestation

`AUDIT=B12_WS33_INDEPENDENT_CLONE_GUARDS`; `RESULT=WS33_INDEPENDENT_METADATA_VERIFIED`.

- `SOURCE_GUARDS=PASS`, `LOCAL_DOCKER_DESKTOP=PASS`, `MARIADB_IMAGE_11_4=PASS`, `IMAGE_FINGERPRINT=PASS`, `NEW_CONTAINER_IDENTITY=PASS`.
- `NEW_CONTAINER_STATE=CREATED_STOPPED`; `CONTAINER_NEVER_STARTED_MARKERS=PASS` (current metadata markers only, not independent historical evidence).
- `NETWORK_MODE=NONE`, `PUBLISHED_PORTS=NO`, `PRIVILEGED=NO`, `HOST_BIND_MOUNTS=NO`, `RESOURCE_LIMITS=PASS`, `RESTART_POLICY=NO`.
- `RANDOM_PASSWORD_CONFIGURATION=PASS` (configured for possible future start; **no password generated/printed here**).
- `NEW_VOLUME_IDENTITY=PASS`, `NEW_VOLUME_SCOPE_LABELS=PASS`, `NEW_VOLUME_DEDICATED_MOUNT=PASS`, `NEW_VOLUME_SHARED_WITH_PROTECTED=NO`.
- `EXISTING_RUNNING_CONTAINERS=2`, `EXISTING_PROTECTED_VOLUMES=3`, `TOTAL_CONTAINERS=3`, `TOTAL_VOLUMES=4`, `RESOURCE_INVENTORY_STABLE=PASS`.
- `INSPECTION=DOCKER_METADATA_ONLY`, `DOCKER_VM_GUEST_FREE_SPACE=NOT_ATTESTED`, `PHYSICAL_ISOLATION_CERTIFIED=NO`.
- `BACKUP_OPENED=NO`, `BACKUP_RESTORED=NO`, `DATABASE_INITIALIZED=NO`, `DATABASE_CONNECTIONS=0`, `SQL_STATEMENTS_EXECUTED=0`, `RESOURCES_CHANGED_BY_AUDIT=NO`, `PRODUCTION_CONTACT=NO`, `B12_CUTOVER=OFF`.

**Interpretation:** WS33 independently confirms *configuration* of the new stopped target, two prior running containers and three prior volumes are protected, the new named volume is not shared with protected containers, and the new container currently has no networking or published ports. This does NOT establish complete physical isolation (host engine and kernel still shared), volume filesystem emptiness from a content scan, actual Docker guest free space, full image provenance or backup chain of custody.

## Next authorization gate — WS34 initialization-only, NOT covered by WS32 approval

The prior one-use WS32 approval has been **consumed**. Do **not** run `docker start`, `docker exec`, `docker cp`, SQL or migrations based on WS32/WS33 PASS alone.

A proposed independently authorized WS34 would:
1. Re-verify original B12 checkout and exact protected container identities and volumes, the local Docker Desktop Unix socket, full cached MariaDB 11.4 image ID/fingerprint, and exact named WS32 stopped container and dedicated volume/limits/security settings. Abort on drift.
2. Specifically request permission to **start only the new WS32 container** to initialize a **blank** MariaDB data directory in its dedicated volume, without importing `database.sql`, other data, SQL, schema or app writers. Keep `--network none`, no published ports and no privileges.
3. Decide beforehand how to securely establish access without printing, committing or leaking any autogenerated root password from MariaDB startup logs. Use only a separately scoped access plan; avoid `docker logs` dumping secrets and never expose image secrets or raw environment. No customer records or source database credentials.
4. Check only coarse ready/health and resource metadata, no data queries or unauthorized protected-container probes. Decide safe stop/teardown handling and if initialization or limited resource availability fails, preserve resources and report rather than rerunning broad operations.
5. Future guest-volume disk free-space attestation, SQL backup provenance, privacy, actual customer-derived restore, aggregate-only schema inventory, migration 092 testing, and repaired RC01–RC14 require **distinct, explicit approvals** and remain blocked here.

The local private `~/Downloads/database.sql` remains protected by mode 600, 619163 bytes, SHA-256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`. It reports server version 11.4 in unverified header and contains 69 destructive `DROP TABLE` statements, but origin and snapshot consistency remain unverified. **Never upload it to GitHub/CI/ChatGPT or import into protected environments.**

**Standing restrictions:** B12 literal HARD-OFF; migration 092 NOT approved; no fresh repaired RC01–RC14 certification (historical original real MariaDB run failed TWO_STALE_COMMITS); C137 D12 15 import gates BLOCKED, Stage D UNAUTHORIZED, 150 VU HOLD, 200 VU PROHIBITED. Preserve "One fix, fit it all", focused source guards, thin routes, no duplicate policy, no speculative product changes.

This GitHub source-only evidence commit must contain exactly this new Markdown document, verify independent remote semantic diff and all protected refs, without modifying code/migrations/CI/deployments.
