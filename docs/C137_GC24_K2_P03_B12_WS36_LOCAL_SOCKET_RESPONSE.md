# C137 / GC24 / K2-P03 / B12 — WS36 local MariaDB socket responsiveness

**Date: 2026-10-15. User-returned, separately approved, single unauthenticated responsiveness probe. NOT authenticated database access, completed bootstrap, backup restore or concurrency certification.**

## Source continuity and protected state

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Parent: WS35 documentation branch `c137-gc24-k2p03-b12-ws35-runtime-health-metadata-evidence-source` HEAD `26f8d4834da09b2d8af580685f7d25eee74408e1`.
- WS36 branch: `c137-gc24-k2p03-b12-ws36-socket-response-evidence-source`.
- Protected production source `v320-renee-agrisuite-branding` HEAD `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- Protected original B12 source `c137-gc24-k2p03-b12-cas-writer-cutover-source` HEAD `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Literal B12 cutover HARD-OFF gate `lib/daily_record_revision_cutover_gate.php` blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`.
- Historical WS07 offline runner blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`.

## Authorized scope and exact user-returned WS36 findings

WS36 was specifically authorized to attempt exactly one **password-free MariaDB-admin ping via Unix socket** inside only the newly created MariaDB 11.4 container `renee-b12-ws32-mariadb114-stopped` (static name is historical; container running). No SQL, credential retrieval, Docker startup logs or backup import allowed. Audit command suppressed probe stdout/stderr and checked post-probe Docker inventories.

`AUDIT=B12_WS36_MARIADB_SOCKET_RESPONSIVENESS` `RESULT=WS36_SOCKET_PROBE_COMPLETE`.

| Field | Observed |
| --- | --- |
| SOURCE_GUARDS | PASS |
| LOCAL_DOCKER_DESKTOP | PASS |
| IMAGE_11_4_IDENTITY | PASS |
| NEW_CONTAINER_IDENTITY | PASS |
| NEW_CONTAINER_STATE | RUNNING |
| NETWORK_MODE / PUBLISHED_PORTS / PRIVILEGED | NONE / NO / NO |
| DEDICATED_VOLUME | PASS |
| VOLUME_SHARED_WITH_PROTECTED | NO |
| PROTECTED_RUNNING_CONTAINERS | 2 |
| PROTECTED_ORIGINAL_VOLUMES | 3 |
| SOCKET_PROBE_INVOCATIONS | 1 |
| SOCKET_PROBE_RESULT | **SERVER_RESPONDS** |
| PROBE_AUTHENTICATION | NO_PASSWORD_SUPPLIED |
| AUTHENTICATED_DATABASE_ACCESS | **NOT_ESTABLISHED** |
| SERVER_INITIALIZATION_CERTIFIED | **NO** |
| DATABASE_CONNECTION_ATTEMPT | **YES** |
| SQL_STATEMENTS_BY_AUDIT | 0 |
| APPLICATION_MIGRATIONS | NO |
| BACKUP_OPENED / BACKUP_RESTORED | NO / NO |
| DOCKER_LOGS_READ / ROOT_PASSWORD_DISPLAYED | NO / NO |
| RESOURCE_INVENTORY_STABLE | PASS |
| CONTAINERS_CREATED_OR_STOPPED | NO |
| PRODUCTION_CONTACT | NO |
| B12_CUTOVER | OFF |

**Precise interpretation:** `mariadb-admin ping` can succeed even when authentication is denied. `SERVER_RESPONDS` is evidence the local MariaDB server responds at the container Unix socket to a client check; it is **not** evidence of valid credentials, authenticated access, successful SQL query, complete system schema initialization or restorability. The one local connection attempt must not be recorded as `DATABASE_CONNECTIONS=0`. This test did not connect to production, staging or protected synthetic MariaDB containers.

## Next separately authorized stage and narrow recommended boundary

WS36 one-use socket-probe approval is **consumed**. Do not repeat ping merely for another PASS. No application import, schema migration, writer enablement or production contact is permitted.

Propose **WS37 guest filesystem readiness and disk-capacity preflight** limited to the same *new* container only, requiring a fresh explicit approval because `docker exec` would be a new operation. It should:
1. Recheck the pinned original B12 checkout/HEAD/clean worktree and source remote, local Docker socket and image ID, new running container label/identity and `--network none`, no ports/privilege/host binds, dedicated new volume, both original running container IDs and four volume IDs.
2. Use **one bounded `docker exec` command** in the new container, limited to host-neutral, **read-only guest metadata**: `df -Pk /var/lib/mysql` or `stat -f` to report **guest filesystem free capacity** (no directory listing); `test -d /var/lib/mysql/mysql` to report only presence of the expected internal system directory; optionally `test -S /run/mysqld/mysqld.sock` to report socket presence. Print only Boolean presence flags and coarse GiB capacity, never paths from mounts, customer filenames, credentials, logs or raw program output. System directory presence alone cannot certify initialization/completeness.
3. Ensure no SQL, database-client connection or password, no backup opening/copy, no Docker logs and no changes to protected containers or volumes; verify post-probe state. If missing tools, return INCONCLUSIVE and do not auto-install software or substitute privileged shell access.

**WS37 authorization does not cover an authenticated `SELECT 1`**. An authenticated, read-only database SQL probe needs a later separate approval and a reviewed credential plan that does not disclose the random root password. Future actual production-origin attestation and privacy/risk-controlled SQL restore, 11-section legacy aggregate schema inventory, migration 092 and repaired RC01–RC14 each remain distinct gated activities.

## Unchanged global restrictions

The private Mac SQL file `~/Downloads/database.sql` (619163 bytes, owner-only mode 600, SHA256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`) has an unverified MariaDB 11.4 header and 69 potentially destructive `DROP TABLE` statements. **Its actual production origin and dump consistency remain independently unverified.** Do not upload its contents to GitHub/ChatGPT/CI or import into production, staging, the C137 fixture, or existing synthetic Docker container. Docker VM guest free space not yet attested; physical isolation not certified.

B12 cutover HARD-OFF, migration 092 unauthorized, repaired real MariaDB RC01–RC14 pass count zero, original historical concurrency attempt FAILED `TWO_STALE_COMMITS`. D12 15 import gates BLOCKED; Stage D UNAUTHORIZED; 150 VU HOLD; 200 VU PROHIBITED.

**GitHub change contract:** Add this one Markdown evidence file only; perform independent remote GitHub semantic diff and re-verify production/original branch, cutover gate and WS07 runner blobs. No product code, SQL, CI, production, AWS, cPanel, Docker environment or backup changes from this evidence commit.
