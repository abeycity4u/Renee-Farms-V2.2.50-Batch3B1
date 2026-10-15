# C137 / GC24 / K2-P03 / B12 — WS39 root Unix-socket password-free SQL attempt

**2026-10-15 — User-returned evidence of separately authorized, single SQL scalar probe against exclusively the NEW isolated local MariaDB 11.4 container. Authenticated execution was NOT established. Neither cause nor server readiness can be inferred from this result.**

## Protected source controls and GitHub branch

- Repo: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Parent source-only evidence branch: `c137-gc24-k2p03-b12-ws38-authenticated-probe-evidence-source` at `34e1aac62e51465266b02c4fb957cec1dd3cec46`.
- New WS39 evidence branch: `c137-gc24-k2p03-b12-ws39-root-socket-evidence-source`.
- Protected original B12 branch `c137-gc24-k2p03-b12-cas-writer-cutover-source` HEAD `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Protected production branch `v320-renee-agrisuite-branding` HEAD `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- B12 literal cutover HARD-OFF gate `lib/daily_record_revision_cutover_gate.php` Git blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`.
- Historical WS07 offline runner `scripts/verify_k2p03_b12_ws07_offline.sh` Git blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`.

## WS39 exact authorized scope and observed audit

The user approved the suggested WS39 operation (`proceed to next recommendation`) after being asked for a single **password-free root Unix-socket** authenticated read-only `SELECT 1` attempt. The command targeted only `renee-b12-ws32-mariadb114-stopped` (static name, but container now running), using `docker exec --user 0` to run `mariadb --no-defaults --protocol=socket --socket=/run/mysqld/mysqld.sock --user=root --connect-timeout=5 --batch --skip-column-names --execute='SELECT 1'` with password variables emptied. Only the expected scalar was compared; raw client errors were suppressed. No second attempt, fallback account, password lookup or backup access.

User returned:

`AUDIT=B12_WS39_ROOT_SOCKET_AUTHENTICATION`, `RESULT=WS39_ROOT_SOCKET_PROBE_COMPLETE`.

| Returned field | Value |
| --- | --- |
| SOURCE_GUARDS / LOCAL_DOCKER_DESKTOP | PASS / PASS |
| MARIADB_IMAGE_11_4 / IMAGE_FINGERPRINT | PASS / PASS |
| TARGET_CONTAINER_IDENTITY / TARGET_CONTAINER_STATE | PASS / RUNNING |
| NETWORK_MODE / PUBLISHED_PORTS / PRIVILEGED_CONTAINER | NONE / NO / NO |
| DEDICATED_VOLUME / VOLUME_SHARED_WITH_PROTECTED | PASS / NO |
| QUERY_ATTEMPTS | **1** |
| QUERY_TYPE / QUERY | SCALAR_READONLY / SELECT_1 |
| OPERATING_SYSTEM_USER | ROOT |
| DATABASE_USER_ATTEMPTED | ROOT |
| AUTHENTICATION_CHANNEL | LOCAL_UNIX_SOCKET |
| PASSWORD_VALUE_SUPPLIED | NO |
| AUTHENTICATED_SELECT1 | **NOT_ESTABLISHED** |
| AUTHENTICATION_PLUGIN | NOT_DETERMINED |
| ROOT_PASSWORD_RETRIEVED / ROOT_PASSWORD_DISPLAYED | NO / NO |
| DOCKER_LOGS_READ | NO |
| BACKUP_OPENED / BACKUP_COPIED / BACKUP_RESTORED | NO / NO / NO |
| SQL_STATEMENTS_SUBMITTED | **1** |
| SCHEMA_CHANGES / MIGRATIONS_EXECUTED | NO / NO |
| PROTECTED_RUNNING_CONTAINERS / PROTECTED_ORIGINAL_VOLUMES | 2 / 3 |
| RESOURCE_INVENTORY_STABLE | PASS |
| PRODUCTION_CONTACT / B12_CUTOVER | NO / OFF |

**Interpretation:** `AUTHENTICATED_SELECT1=NOT_ESTABLISHED` means the client returned nonzero; because its error was suppressed, whether the failure involved authentication policy, missing account, socket/client options or another error is **unknown**. Do **not** call this a verified `ACCESS_DENIED` or proof that MariaDB is unhealthy, that root has password authentication, or that the SQL reached the server. A single statement was submitted to the *client*, not proven executed on the server.

WS38 had already returned `AUTHENTICATED_SELECT1=NOT_ESTABLISHED` for unprivileged `mysql` OS+database account. WS39 similarly did not establish authenticated access with local `root`. Two approved password-free probes are consumed; do not brute-force, improvise new accounts, repeat these failures or access secrets without a separate scoped approval.

## What IS established from prior steps

- WS29–WS30: cached MariaDB 11.4 AMD64 image matches unverified dump header server version 11.4. Source backup chain of custody and dump consistency NOT established.
- WS31 Mac backing filesystem 166.89 GiB free at earlier snapshot; WS37 guest data-directory filesystem 213.14 GiB reported available. **Guest free bytes are virtual/sparse and exceed earlier host free; not guaranteed physical restore capacity**.
- WS32 authorized one **new** dedicated volume `renee-b12-ws32-datadir` and one newly created stopped container; WS33 verified Docker isolation metadata. WS34 separately authorized blank MariaDB container start; WS35 returned no configured Docker healthcheck and no independent bootstrap certification.
- WS36 one password-free local `mariadb-admin ping` reported `SERVER_RESPONDS`, but `ping` can pass on authentication rejection; not proof of readiness.
- WS37 MariaDB system directory and Unix socket were present. Neither proves authenticated SQL, schema readiness or future restore compatibility.

## Next decision — WS40 *non-secret authentication diagnostics* planning

The WS39 one-use authorization is consumed. Next should be a bounded **read-only authentication diagnostics stage** rather than further blind login attempts:

1. Prefer checking only existing, non-secret Docker image and runtime metadata (container image ID, entrypoint/command, process state, access restrictions) and static image/entrypoint policy as feasible. No database credentials, no Docker logs, no `/var/lib/mysql` contents or protected container data. Static image defaults do not certify effective user grants or the true cause of the failed attempts.
2. If actual client-error classification is necessary, request fresh explicit approval for exactly **one** bounded repeated connection attempt with **allowlisted classification of the error inside a local process**, never raw output, credentials, logs or arbitrary SQL. Do not present that as already approved.
3. If MariaDB root requires the randomly generated password, create a *separate* explicit locally secret-contained credential-recovery/use design first, addressing that MariaDB's random root password can appear in Docker startup logs. Do not read logs, display/commit/upload secrets, reset root credentials or recreate volume under WS39 approval.
4. Only after authenticated readiness, backup provenance/privacy assessment, fresh host storage headroom, and separately approved exclusive restore into newly isolated test target should SQL import or actual B12 real database tests be considered.

**No automatic retry or change** is authorized now.

## Global safeguards

Local owner-only `~/Downloads/database.sql`: user-returned 619163 bytes and SHA256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`, unverified 11.4 dump header, 69 potentially destructive DROP TABLE statements, not opened/imported/copied/uploaded in WS39. Backup provenance and consistency unverified.

B12 cutover **HARD-OFF**, migration 092 NOT APPROVED, repaired real MariaDB RC01–RC14 passes ZERO (historical original attempt FAILED `TWO_STALE_COMMITS`); D12 15 import gates BLOCKED, Stage D UNAUTHORIZED, 150 VU HOLD, 200 VU PROHIBITED. Production, staging, AWS/cPanel and C137 fixture untouched.

**Remote evidence contract:** Exactly one Markdown document added on this branch. Verify the independent GitHub semantic diff, protected branch HEADs and gate/verifier blobs before claiming WS39 evidence closed. GitHub documentation action itself changes no database, Docker resource, application code, migration or backup.
