# C137 GC24 K2-P03 B12 — WS40E Docker image template selectors (read-only)

**2026-10-15 — user-returned sanitized Docker Desktop metadata results, not a database authentication or image-entrypoint policy certification.**

## Protected source checkpoint

- Repository `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Parent docs-only branch `c137-gc24-k2p03-b12-ws40d-template-blocked-evidence-source`, head `7859ea05098cd61dcd657c116fe39973071d7715`.
- New source-only evidence branch `c137-gc24-k2p03-b12-ws40e-selectors-evidence-source`.
- Protected B12 source branch `c137-gc24-k2p03-b12-cas-writer-cutover-source`, head `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Protected production `v320-renee-agrisuite-branding`, head `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- Hard-OFF cutover gate blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`, historical WS07 runner blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`.

## Context

WS40 static metadata preflight returned `BLOCKED / COMMAND_FAILED`. WS40D fail-closed staged diagnostic isolated this to the `IMAGE_FULL_TEMPLATE` command, whose Docker Go template contained image ID, OS, architecture, and `json` renditions of entrypoint, command, healthcheck, volumes. WS40D did not expose an error or identify a particular failing selector.

WS40E then evaluated all seven selectors **individually**, suppressing their output and Docker stderr and returning only success/failure classifications. Its user-returned output:

```text
#####COPY FROM HERE#####
AUDIT=B12_WS40E_IMAGE_TEMPLATE_SELECTORS
SOURCE_GUARDS=PASS
LOCAL_DOCKER_DESKTOP=PASS
IMAGE_IDENTITY=PASS
TARGET_CONTAINER_IDENTITY=PASS
IMAGE_ID_TEMPLATE=PASS
IMAGE_OS_TEMPLATE=PASS
IMAGE_ARCHITECTURE_TEMPLATE=PASS
IMAGE_ENTRYPOINT_TEMPLATE=PASS
IMAGE_COMMAND_TEMPLATE=PASS
IMAGE_HEALTHCHECK_TEMPLATE=PASS
IMAGE_VOLUMES_TEMPLATE=PASS
FAILED_SELECTORS=0
RAW_IMAGE_VALUES_DISPLAYED=NO
DOCKER_ERRORS_DISPLAYED=NO
DATABASE_CONNECTIONS=0
SQL_STATEMENTS_EXECUTED=0
DOCKER_EXEC_EXECUTED=NO
ROOT_PASSWORD_ACCESSED=NO
DOCKER_LOGS_READ=NO
BACKUP_OPENED=NO
DOCKER_RESOURCES_CHANGED=NO
RESOURCE_INVENTORY_STABLE=PASS
PRODUCTION_CONTACT=NO
B12_CUTOVER=OFF
RESULT=WS40E_SELECTOR_DIAGNOSTIC_COMPLETE
#####COPY TO HERE#####
```

**Verified conclusion:** Each of `{{.Id}}`, `{{.Os}}`, `{{.Architecture}}`, `{{json .Config.Entrypoint}}`, `{{json .Config.Cmd}}`, `{{json .Config.Healthcheck}}`, `{{json .Config.Volumes}}` returned successful Docker CLI status when evaluated separately against locally cached `mariadb:11.4`. The earlier combined template command failed at the time of WS40D, but the specific cause is **NOT established**: could be combination/parsing behavior or a transient condition, not necessarily any individual field. WS40E reports no actual values for entrypoint, command, healthcheck or image volumes. Do NOT claim a standard entrypoint or default command from the field success statuses.

## Next bounded read-only metadata classification

Rather than repeat the broken combined Go template, run WS40F gathering the seven already verified image selectors in **separate read-only `docker image inspect --format` calls** and parsing/allowlisting the locally obtained values without displaying raw image config or secret-bearing environment fields. Specifically classify:
- image entrypoint as `STANDARD_NAMED_ENTRYPOINT` iff a single basename equals `docker-entrypoint.sh`; else `OTHER_OR_UNVERIFIED`.
- image command as `MARIADBD_DEFAULT` iff exactly `["mariadbd"]`; otherwise `OTHER_OR_UNVERIFIED`.
- healthcheck configured/not-configured only; declared `/var/lib/mysql` volume and no unexpected image-declared volumes.
- run matching **container** entrypoint and command classifications via `docker inspect --type container --format`, plus container image ID/state/restrictions/labels/mount, protected resources, and typed allowlisted output only, not raw values or logs.

WS40F should reuse original B12 source (branch/head/worktree, origin remote), local Docker Desktop socket/engine, pinned MariaDB 11.4 image fingerprint `2ee3c4a72fcac30a`, exact new container `renee-b12-ws32-mariadb114-stopped` (static name; running), one new dedicated volume `renee-b12-ws32-datadir`; protect two preexisting running containers and three original volumes. Abort if guards drift; no database exec, access, SQL, password, Docker logs, backup, Docker write, container restart or re-creation. Do not query Docker's full env or startup logs.

MariaDB account/grants **cannot** be verified from image defaults. WS38 `mysql` and WS39 `root` one-use password-free `SELECT 1` both returned `NOT_ESTABLISHED`, error suppressed, without confirming cause. WS36 server responded to `mariadb-admin ping`, but that can pass without login success. WS37 saw system DB directory and socket, guest data-dir `df` free 213.14 GiB; these cannot certify authenticated readiness nor actual host-backed restore capacity.

Backup still private on user's Mac: `~/Downloads/database.sql`, 619163 bytes, SHA-256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`, version header reports MariaDB 11.4 but production origin and consistency not independently established, has 69 potentially destructive DROP TABLE statements. No upload/import/copy authorized.

All B12 protections unchanged: `B12_CUTOVER=HARD_OFF`; migration 092 unauthorized; repaired real RC01–RC14 pass count 0, historical original attempt FAILED TWO_STALE_COMMITS; D12 15 import gates BLOCKED; Stage D UNAUTHORIZED; 150 VU HOLD; 200 VU PROHIBITED; no production, AWS, cPanel, staging or C137 fixture contact.

**Documentation-only change:** exactly this Markdown file to be added, with independent remote GitHub semantic diff and protected Git refs rechecked. No product code, Docker resource or database state changes.
