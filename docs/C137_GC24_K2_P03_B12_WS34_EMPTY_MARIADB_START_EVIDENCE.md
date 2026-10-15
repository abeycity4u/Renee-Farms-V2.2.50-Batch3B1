# C137 / GC24 / K2-P03 / B12 — WS34 new MariaDB 11.4 container start: user-returned evidence

**Date 2026-10-15. User-authorized, limited initialization of the previously WS32-created *new* isolated test container; user-returned Mac Docker metadata only. Not production provenance, database readiness, actual SQL schema inventory, restore or real CAS certification.**

## Source and protected immutable invariants

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Source-only WS34 evidence parent: `c137-gc24-k2p03-b12-ws33-isolated-container-evidence-source` at `0a9eb41d6af058309cb020b8a990da9cf97a6fdb`.
- WS34 doc branch: `c137-gc24-k2p03-b12-ws34-mariadb-start-evidence-source`.
- Original B12 source branch: `c137-gc24-k2p03-b12-cas-writer-cutover-source`, HEAD `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Protected production branch: `v320-renee-agrisuite-branding`, HEAD `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- Cutover HARD-OFF gate `lib/daily_record_revision_cutover_gate.php` blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`; historical WS07 offline runner blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`.
- WS32's one-use creation-only approval was consumed. A separate WS34 approval was expressly requested and granted with `proceed` immediately before its bounded user-executed start command.

## Exact user-returned WS34 evidence

`AUDIT=B12_WS34_EMPTY_MARIADB_INITIALIZATION`
`RESULT=WS34_CONTAINER_STARTED_METADATA_VERIFIED`

| Output field | Returned value |
| --- | --- |
| SOURCE_GUARDS | PASS |
| LOCAL_DOCKER_DESKTOP | PASS |
| IMAGE_VERSION | 11.4 |
| IMAGE_FINGERPRINT | PASS |
| WS32_CONTAINER_IDENTITY | PASS |
| DEDICATED_VOLUME_IDENTITY | PASS |
| CONTAINER_START_REQUESTED | YES |
| CONTAINER_START_CONFIRMED | YES |
| NEW_CONTAINER_STATE | RUNNING |
| NETWORK_MODE / PUBLISHED_PORTS / PRIVILEGED | NONE / NO / NO |
| MEMORY_LIMIT_MIB | 768 |
| ROOT_PASSWORD_CONFIGURATION / ROOT_PASSWORD_DISPLAYED | RANDOM / NO |
| STARTUP_LOGS_READ | NO |
| DATABASE_INITIALIZATION_COMPLETION | NOT_YET_VERIFIED |
| EXISTING_RUNNING_CONTAINERS_PRESERVED | 2 |
| EXISTING_PROTECTED_VOLUMES_PRESERVED | 3 |
| TOTAL_RUNNING_CONTAINERS / TOTAL_VOLUMES | 3 / 4 |
| NETWORK_INVENTORY_UNCHANGED | PASS |
| BACKUP_OPENED / BACKUP_COPIED / BACKUP_RESTORED | NO / NO / NO |
| APPLICATION_SQL_EXECUTED / MIGRATIONS_EXECUTED | NO / NO |
| PRODUCTION_CONTACT | NO |
| B12_CUTOVER | OFF |

**Interpretation:** the WS34 script started only `renee-b12-ws32-mariadb114-stopped`, which had been created under WS32 with `--network none`, no published ports, a 768 MiB memory limit, and private new volume `renee-b12-ws32-datadir`. Two earlier running containers and three original volumes remained preserved. The new container **runs** but neither an SQL health probe nor independently verified MariaDB system-table initialization took place in the user-returned checks. MariaDB may have executed **internal bootstrap SQL** during startup; `APPLICATION_SQL_EXECUTED=NO` covers script-initiated application SQL, not MariaDB's own initialization.

**Secret-handling hazard:** `MARIADB_RANDOM_ROOT_PASSWORD=1` was configured before first start. The generated root password **may be recorded in container startup logs**. Do not display, copy, upload or commit raw Docker logs, environment, database credentials, the backup or the Docker data volume. Do not run `docker logs` in subsequent read-only checks. If access is ever required, devise a separately authorized credential procedure that does not disclose the password to public services.

## WS35 next: separately bounded READ-ONLY metadata observation, not another start

Only inspect source guards and current local Docker Desktop metadata. Verify exact new container identity/image/security settings/volume and that the protected originals remain distinct, same 3 container IDs running and 4 named volumes; inspect `State.Status`, `State.OOMKilled`, `RestartCount`, and optionally Docker's configured `State.Health.Status` **without retrieving healthcheck logs**, environment values, root password or raw inspect JSON. Docker-native health status may be ABSENT, STARTING, HEALTHY or UNHEALTHY; do **not** confuse `RUNNING` with verified database readiness. No explicit `docker exec`, Docker logs, MariaDB socket/client, DB query or guest process probing under WS35.

An image-supplied automatic healthcheck may itself internally connect to MariaDB; if so, distinguish Docker's existing health result from queries initiated by the audit (none). Any readiness claim must remain scoped to the image's own healthcheck contract, not general database/schema/restore correctness.

**Separate authorization required** for any future guest filesystem assessment inside the container, database-ready probe, credential retrieval/use, admin account configuration, backup import, actual customer read, migration 092, or repaired RC01–RC14 execution. WS34 approval is **consumed**, and general progress updates do not expand it.

## Unchanged environment and gates

Local `~/Downloads/database.sql` (private, owner-only) reported 619163 bytes and SHA-256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`; header indicates MariaDB 11.4 but origin **not independently verified** and dump consistency **NOT CERTIFIED**. WS24 found 69 destructive `DROP TABLE` statements, so never restore into any existing or protected database. Docker VM guest disk free space `NOT_ATTESTED`; complete physical isolation `NOT_CERTIFIED`.

B12 revision cutover remains HARD-OFF, migration 092 NOT authorized, repaired real MariaDB RC01–RC14 certifications 0; earlier historical run FAILED `TWO_STALE_COMMITS`. D12 import gates BLOCKED, Stage D UNAUTHORIZED, 150 VU HOLD, 200 VU PROHIBITED. No production/staging/AWS/cPanel contact.

This branch must add exactly this Markdown document; perform independent remote semantic diff and re-verify immutable original B12/production/gate/runner refs. The GitHub evidence commit itself has **no** Docker, application, migration, backup or database side effects.
