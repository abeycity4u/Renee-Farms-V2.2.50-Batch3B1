# C137 / GC24 / K2-P03 / B12 — WS35 new MariaDB Docker runtime-health metadata

**2026-10-15. User-returned Docker metadata only. Container running does not certify MariaDB readiness. Docker image healthcheck is NOT CONFIGURED. No backup, SQL, runtime database access, production connection or source modifications in WS35.**

## Source protection and baseline

- Repository `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Parent `c137-gc24-k2p03-b12-ws34-mariadb-start-evidence-source` at `5bd4462b0a5275461c4290212342e1c97c8c4697`.
- WS35 source-only branch `c137-gc24-k2p03-b12-ws35-runtime-health-metadata-evidence-source`.
- Protected production `v320-renee-agrisuite-branding`: `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- Protected original B12 `c137-gc24-k2p03-b12-cas-writer-cutover-source`: `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- B12 hard-OFF gate `lib/daily_record_revision_cutover_gate.php` blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`.
- WS07 historical offline verification runner blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`.

## Exact user-returned WS35 audit

`AUDIT=B12_WS35_MARIADB_HEALTH_METADATA`, `RESULT=WS35_RUNTIME_METADATA_COMPLETE`.

| Reported field | Value |
| --- | --- |
| SOURCE_GUARDS | PASS |
| LOCAL_DOCKER_DESKTOP | PASS |
| MARIADB_IMAGE_11_4 / IMAGE_FINGERPRINT | PASS / PASS |
| CONTAINER_IDENTITY | PASS |
| CONTAINER_STATE | RUNNING |
| CONTAINER_RESTART_COUNT | 0 |
| CONTAINER_OOM_KILLED | NO |
| START_TIMESTAMP_PRESENT | YES |
| IMAGE_HEALTHCHECK_STATUS | **NOT_CONFIGURED** |
| DATABASE_INITIALIZATION_COMPLETION | **NOT_INDEPENDENTLY_CERTIFIED** |
| NETWORK_MODE | NONE |
| PUBLISHED_PORTS | NO |
| PRIVILEGED | NO |
| RESOURCE_LIMITS | PASS |
| DEDICATED_VOLUME | PASS |
| VOLUME_SHARED_WITH_PROTECTED | NO |
| EXISTING_RUNNING_CONTAINERS | 2 |
| PROTECTED_ORIGINAL_VOLUMES | 3 |
| TOTAL_RUNNING_CONTAINERS | 3 |
| TOTAL_VOLUMES | 4 |
| RESOURCE_INVENTORY_STABLE | PASS |
| INSPECTION | DOCKER_METADATA_ONLY |
| DOCKER_LOGS_READ | NO |
| ROOT_PASSWORD_DISPLAYED | NO |
| BACKUP_OPENED / BACKUP_RESTORED | NO / NO |
| DATABASE_CONNECTIONS_BY_AUDIT / APPLICATION_SQL_BY_AUDIT | 0 / 0 |
| RESOURCES_CHANGED_BY_AUDIT | NO |
| PRODUCTION_CONTACT / B12_CUTOVER | NO / OFF |

New container: `renee-b12-ws32-mariadb114-stopped`, started in explicitly authorized WS34 despite the static name suffix, with new dedicated volume `renee-b12-ws32-datadir`. The two prior containers and three prior volumes remain protected; no network ports, bind mounts, privilege or volume reuse are indicated by this metadata audit.

## Interpretation

1. **WS35 metadata inspection passed, not MariaDB readiness.** `IMAGE_HEALTHCHECK_STATUS=NOT_CONFIGURED` means Docker does not have a configured healthcheck for this image/container. Neither `RUNNING` nor zero restarts/OOM is proof that system-table bootstrap completed, mysqld can accept connections, or a schema can be imported.
2. The random root password may appear in initial MariaDB Docker startup logs. WS35 did NOT read logs; do not issue raw `docker logs`, inspect the secret in an unsafe terminal/share path, or print it to ChatGPT/GitHub. `MARIADB_RANDOM_ROOT_PASSWORD=1` was set in the approved WS32 container and WS34 initialized it; secret-handling remains unresolved.
3. WS31 proved 166.89 GiB free on the Mac backing filesystem but **not** Docker VM guest free storage. WS28 Docker configured RAM 3911 MiB, existing two containers 267.6 MiB at snapshot; this is not a safe free-memory guarantee.
4. A physically separated database clone has **not** been established. The new container is a separately configured instance on the same Docker engine; backup's claimed production origin still lacks independent corroboration, and data import has never occurred. Actual tenant data/count/integrity remains unknown.
5. The local `~/Downloads/database.sql` file remains private; user-returned pinned SHA `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`, 619163 bytes, owner-only 600. Reported MariaDB header 11.4, consistency/provenance NOT certified; 69 destructive `DROP TABLE` statements.

## Next separate authorization decision — WS36 minimal read-only readiness probe

WS34 one-use startup approval has been consumed. A **new, narrowly scoped approval** is required for any `docker exec` or MariaDB-client/socket operation, even to check responsiveness. The proposed WS36 would target **only the named new WS32/WS34 container** and its strictly local Unix socket, using a limited `mariadb-admin ping`/equivalent command with no root password, no query, no schema access, no logs and a bounded timeout. Importantly, a server ping may report server reachability even when authentication is refused; any result must be interpreted only as a *listener/process responsiveness indicator*, **not** proof of an authenticated connection, complete initialization, SQL correctness or restore compatibility. A separately scoped credential and authenticated schema/version probe would be needed for those claims.

Preflight must reassert source guard and local Docker engine/socket, container's image ID/fingerprint, network mode NONE, no ports, no binds, dedicated new volume, protected container/volume inventory and unchanged resource limits. On any drift, stop without executing probe. Emit only status codes/result classification, suppress client stdout/stderr that could contain sensitive data. After probe verify protected inventories unchanged; **never** read Docker startup logs or password, run SQL, copy/import backups, launch/stop/recreate containers or modify source. The operation will create a local database-socket connection, so do not report `DATABASE_CONNECTIONS=0` if performed. Request affirmative scope-specific user authorization before any such WS36 command.

If the client binary or socket path is unknown, first use only public image documentation/static metadata in a nonmutating way; no network pull or Docker exec fallback without explicit permission.

## Release gates unchanged

`B12_CUTOVER=HARD_OFF`, `MIGRATION_092=NOT_APPROVED`, `RC01_RC14_REAL_PASS_COUNT=0`, original historical concurrency run FAILED `TWO_STALE_COMMITS`. C137 D12 15 import gates BLOCKED; Stage D UNAUTHORIZED, 150 VU HOLD, 200 VU PROHIBITED. No production/staging/AWS/cPanel contact.

This is exactly one Markdown documentation addition on a new source-only branch. Confirm independent remote semantic diff and immutable protected refs; no product code, migration, CI, Docker resources or database were changed by WS35 evidence closeout.
