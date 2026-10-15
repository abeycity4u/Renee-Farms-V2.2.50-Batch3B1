# C137 / GC24 / K2-P03 / B12 — WS38 password-free authenticated SQL readiness attempt

**2026-10-15. User-returned result. Read-only `SELECT 1` was submitted once using an expressly authorized, password-free MariaDB `mysql` account attempt inside only the new isolated test container. Authenticated execution was NOT established.**

## Source continuity and protected refs

- GitHub: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Source-only WS38 branch: `c137-gc24-k2p03-b12-ws38-authenticated-probe-evidence-source`.
- Parent WS37 documentation branch `c137-gc24-k2p03-b12-ws37-guest-filesystem-evidence-source`, HEAD `49a8078b400125a1b003237f1968a91bc22fca02`.
- Original B12 branch `c137-gc24-k2p03-b12-cas-writer-cutover-source`, HEAD `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Protected production branch `v320-renee-agrisuite-branding`, HEAD `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- B12 hard-OFF gate `lib/daily_record_revision_cutover_gate.php`, Git blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`.
- Original WS07 offline runner `scripts/verify_k2p03_b12_ws07_offline.sh`, Git blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`.

## Specific WS38 authorization and attempted command

The user approved a narrow **one-attempt authenticated, read-only `SELECT 1`** to the WS32-created MariaDB 11.4 instance `renee-b12-ws32-mariadb114-stopped`, now running; it did **not** approve root access, additional login attempts, password extraction, backup import or migration. The user-run command:
- verified original B12 source and local Docker Desktop identity, pinned MariaDB 11.4 image, dedicated new volume `renee-b12-ws32-datadir`, network-none, no published ports, unprivileged container, resource limits, two protected running containers and three original protected volumes before and after;
- ran `docker exec --user mysql` with `mariadb --no-defaults --protocol=socket --socket=/run/mysqld/mysqld.sock --user=mysql --connect-timeout=5 --batch --skip-column-names --execute='SELECT 1'` with blank password-related environment, outputs sanitized and client errors suppressed;
- submitted no changes or credentials and did not inspect Docker startup logs, backup file, customer rows, production services or any protected container's database.

## Exact user-returned WS38 evidence

`AUDIT=B12_WS38_AUTHENTICATED_READONLY_PROBE`, `RESULT=WS38_AUTHENTICATED_PROBE_COMPLETE`.

| Audit field | Returned |
| --- | --- |
| SOURCE_GUARDS / LOCAL_DOCKER_DESKTOP | PASS / PASS |
| MARIADB_IMAGE_11_4 / IMAGE_FINGERPRINT | PASS / PASS |
| TARGET_CONTAINER_IDENTITY / TARGET_CONTAINER_STATE | PASS / RUNNING |
| NETWORK_MODE / PUBLISHED_PORTS / PRIVILEGED | NONE / NO / NO |
| DEDICATED_VOLUME / VOLUME_SHARED_WITH_PROTECTED | PASS / NO |
| QUERY_ATTEMPTS | **1** |
| QUERY_TYPE | SCALAR_READONLY |
| QUERY | SELECT_1 |
| AUTHENTICATION_METHOD | LOCAL_SOCKET_NO_PASSWORD |
| DATABASE_USER_ATTEMPTED | MYSQL |
| AUTHENTICATED_SELECT1 | **NOT_ESTABLISHED** |
| ROOT_PASSWORD_RETRIEVED / DOCKER_LOGS_READ | NO / NO |
| BACKUP_OPENED / BACKUP_RESTORED | NO / NO |
| SQL_STATEMENTS_SUBMITTED | **1** |
| SCHEMA_CHANGES / MIGRATIONS_EXECUTED | NO / NO |
| PROTECTED_RUNNING_CONTAINERS / PROTECTED_ORIGINAL_VOLUMES | 2 / 3 |
| RESOURCE_INVENTORY_STABLE | PASS |
| PRODUCTION_CONTACT / B12_CUTOVER | NO / OFF |

**Interpretation:** Client status was nonzero, with stderr deliberately suppressed. This audit cannot distinguish a nonexistent or unauthorized `mysql` database account, socket-account authentication policy, client option rejection, or another client error. It does **not** prove MariaDB is unhealthy, or that `mysql` exists with password-free privileges. The one `SELECT 1` statement was *submitted to the client*, but **no authenticated database session or execution of the statement is proven**. Do not report `SELECT_1=PASS` or server-side SQL statement execution, and do not repeat the same failed attempt speculatively.

## Next decision: separate approval for new authentication scope

WS38's one-use authorization is **consumed**. Do not retrieve the generated MariaDB root password from Docker startup logs, image layers, SQL dumps, environment secrets or password files; do not reset/change credentials, recreate the initialized volume, use root SQL or run another query without a separate, precisely scoped authorization. No backup restoration based on this result.

Recommendation for the next controlled step is **WS39 single local root Unix-socket authentication feasibility probe**, subject to **new explicit approval** because it would use the container's root operating-system identity and attempt MariaDB `root`. Limit to only the new container and one fixed read-only `SELECT 1` with `--no-defaults`, explicit local socket, no password, no network, no SQL or retries beyond this statement. Fail closed if the image uses password-based root authentication; do not expose/guess any root password, scrape logs or fall back to credential-reset methods. Suppress raw output and verify protected resource inventories before/after. Even PASS proves only local authenticated scalar-query responsiveness, not complete schema/restore readiness, physical isolation or data validity.

If users prefer zero further authentication attempts, an alternative is a purely static non-secret image-entrypoint authentication-policy review using already available source metadata, without connecting to the database; do not infer MariaDB user entries from image metadata. Both choices must be scoped and authorized appropriately.

## Unchanged B12 and data gates

- WS36 single `mariadb-admin ping` returned `SERVER_RESPONDS` (not authenticated access).
- WS37 filesystem at `/var/lib/mysql` reported 213.14 GiB guest free, system directory present, Unix socket present. Host backing filesystem WS31 free 166.89 GiB in earlier snapshot; **guest reported free is not guaranteed host-backed free**.
- Local `~/Downloads/database.sql` 619163 bytes SHA256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`, owner-only mode 600; unverified MariaDB 11.4 header; origin and dump consistency unverified; 69 potentially destructive DROP TABLE statements. No upload/import/copy authorized.
- B12 cutover **HARD-OFF**; migration 092 NOT APPROVED; repaired real RC01–RC14 certification passes **0**; original historical real MariaDB attempt FAILED `TWO_STALE_COMMITS`. D12 import gates BLOCKED; Stage D UNAUTHORIZED; 150 VU HOLD; 200 VU PROHIBITED. Production, AWS, cPanel, staging and historical fixture untouched.

**Documentation-only contract:** Add exactly this one Markdown evidence document on the new WS38 branch; independently verify remote semantic diff and unchanged protected refs. No application, infrastructure, Docker, SQL, migration or backup changes arise from the GitHub commit.
