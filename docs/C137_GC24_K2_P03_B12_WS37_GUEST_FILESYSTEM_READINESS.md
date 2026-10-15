# C137 / GC24 / K2-P03 / B12 — WS37 guest filesystem readiness evidence

**2026-10-15 — One separately user-authorized read-only guest-filesystem metadata check against the new MariaDB 11.4 container only. NOT a database authentication, SQL readiness, backup-origin, restore or real CAS certification.**

## Protected source state

- Repo: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Evidence parent branch: `c137-gc24-k2p03-b12-ws36-socket-response-evidence-source` at `64b20e513727fd6155cc29349c3b1092ab6afdf4`.
- WS37 evidence branch: `c137-gc24-k2p03-b12-ws37-guest-filesystem-evidence-source`.
- Original B12 branch `c137-gc24-k2p03-b12-cas-writer-cutover-source`: `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Protected production branch `v320-renee-agrisuite-branding`: `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- B12 literal HARD-OFF gate `lib/daily_record_revision_cutover_gate.php` blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`; immutable historical offline runner `scripts/verify_k2p03_b12_ws07_offline.sh` blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`.

## Authorization and user-returned WS37 result

User said `i authorize` after explicitly being asked to authorize exactly one read-only guest-filesystem inspection using `docker exec` against only new `renee-b12-ws32-mariadb114-stopped` (the static container name; presently running). Audit issued a single `df -Pk /var/lib/mysql` and checked presence with `test -d /var/lib/mysql/mysql`, `test -S /run/mysqld/mysqld.sock` as Linux user `mysql`. It did not open SQL data files or list their names. User returned:

`AUDIT=B12_WS37_GUEST_FILESYSTEM_READINESS`, `RESULT=WS37_GUEST_STORAGE_METADATA_COMPLETE`.

| Exact returned field | Outcome |
| --- | --- |
| SOURCE_GUARDS / LOCAL_DOCKER_DESKTOP | PASS / PASS |
| MARIADB_IMAGE_11_4 / IMAGE_FINGERPRINT | PASS / PASS |
| TARGET_CONTAINER_IDENTITY | PASS |
| TARGET_CONTAINER_STATE | RUNNING |
| NETWORK_MODE / PUBLISHED_PORTS / PRIVILEGED | NONE / NO / NO |
| DEDICATED_VOLUME / VOLUME_SHARED_WITH_PROTECTED | PASS / NO |
| GUEST_EXEC_ATTEMPTS | **1** |
| GUEST_EXEC_USER | MYSQL |
| GUEST_INSPECTION | READONLY_METADATA |
| DATADIR_FILESYSTEM_FREE_GIB | **213.14** |
| DATADIR_FILESYSTEM_FREE_CLASS | AT_LEAST_4_GIB |
| MARIADB_SYSTEM_DIRECTORY_PRESENT | **YES** |
| MARIADB_UNIX_SOCKET_PRESENT | **YES** |
| DOCKER_VM_GLOBAL_FREE_SPACE | **NOT_CERTIFIED** |
| DATABASE_INITIALIZATION_CERTIFIED | **NO** |
| AUTHENTICATED_DATABASE_ACCESS | NOT_ATTEMPTED |
| DATABASE_CONNECTIONS_BY_AUDIT | 0 |
| SQL_STATEMENTS_BY_AUDIT | 0 |
| ROOT_PASSWORD_ACCESSED / DOCKER_LOGS_READ | NO / NO |
| BACKUP_OPENED / BACKUP_COPIED / BACKUP_RESTORED | NO / NO / NO |
| PROTECTED_RUNNING_CONTAINERS / PROTECTED_ORIGINAL_VOLUMES | 2 / 3 |
| TOTAL_RUNNING_CONTAINERS / TOTAL_VOLUMES | 3 / 4 |
| RESOURCE_INVENTORY_STABLE | PASS |
| PRODUCTION_CONTACT / B12_CUTOVER | NO / OFF |

**Interpretation:** Inside the running isolated *new* MariaDB container, the guest filesystem housing `/var/lib/mysql` reported 213.14 GiB available, the system database directory exists, and the expected Unix socket exists. These are *limited initialization-progress indicators*, not authenticated SQL success, clean schema initialization, restore compatibility, 100% MariaDB health or data integrity. WS36 previously reported `mariadb-admin ping` `SERVER_RESPONDS`, but that may succeed despite authentication denial.

**Capacity caution:** WS31's Mac backing filesystem had **166.89 GiB free**, and its Docker sparse image had logical size 233.47 GiB and physically allocated 2.77 GiB. The guest's `df` report of 213.14 GiB **is larger than host free space measured earlier**, precisely illustrating why virtual guest advertised capacity cannot be equated to actual physical host-backed free space. Host space could have changed since WS31. Neither guest global free space, restore budget, future thin-provisioning behavior nor guaranteed writable bytes are attested. Do **not** authorize a backup restore based on 213.14 GiB alone.

## Next safe boundary: WS38 authenticated readiness plan, NOT yet approved

WS37's one-use `docker exec` inspection approval is **consumed**. A new authorization is required before any further `docker exec` or database client query—even `SELECT 1`.

Recommend crafting a narrow WS38 *authenticated SQL readiness probe* that targets **only** `renee-b12-ws32-mariadb114-stopped` through its local Unix socket; uses no production credentials, Docker logs, customer data or SQL backup; executes a single nonmutating fixed scalar query only after safely proving local authentication identity. Avoid ever displaying/committing the MariaDB random root password, which may be in Docker startup logs. A password-free OS `mysql` / MariaDB Unix-socket account may be available but is NOT verified; if not, fail closed rather than scraping logs, trying passwords, resetting credentials or opening config files. Use `--no-defaults`, `--protocol=socket`, a strict short timeout, explicit target socket, suppression of raw SQL/error output, and a precise result classification: authenticated `SELECT 1` success vs denied/inconclusive. A successful query certifies only the scalar SQL session, not backup integrity or B12 CAS correctness. Guard protected resources and image identity before/after, count the one DB connection and one SQL statement if attempted, and require explicit WS38 approval first.

Separate future stages still needed: actual source-backup provenance, preservation/privacy plan, restore-specific approval, database import only into this fresh separate target (the dump has 69 destructive DROP TABLE statements), relevant 11-section schema aggregate inventory, migration 092 isolated migration safety and repaired RC01–RC14 real MariaDB concurrency certification. Any update to 150/200 virtual-user gates is forbidden without evidence.

## Current gate state

`B12_CUTOVER=HARD_OFF`, `MIGRATION_092=NOT_APPROVED`, `RC01_RC14_REAL_PASS_COUNT=0` (original historical real attempt FAILED `TWO_STALE_COMMITS`), D12 15 import gates BLOCKED, Stage D UNAUTHORIZED, 150 VU HOLD, 200 VU PROHIBITED. No production/staging/AWS/cPanel contact or data changes. User's private `~/Downloads/database.sql` source, hash `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`, 619163 bytes, not imported or shared; reported header MariaDB 11.4 remains UNVERIFIED PROVENANCE.

This evidence closes WS37 as a **single docs-only GitHub addition**. Independently verify remote semantic diff and immutable refs; no PHP, app, SQL, CI, container or volume modifications made by this source commit.
