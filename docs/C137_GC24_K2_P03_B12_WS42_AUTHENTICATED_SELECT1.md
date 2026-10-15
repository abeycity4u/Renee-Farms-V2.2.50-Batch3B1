# C137 / GC24 / K2-P03 / B12 — WS42 isolated MariaDB authenticated SQL readiness

**2026-10-15 — SUCCESSFUL ONE-TIME, USER-AUTHORIZED, PASSWORD-BASED LOCAL-SOCKET `SELECT 1` against ONLY the new isolated WS32 MariaDB 11.4 container. No import, migrations, CAS concurrency pass or cutover authorization.**

## Provenance and immutable source gates

- Repo: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Parent evidence-only source `c137-gc24-k2p03-b12-ws41-random-password-marker-evidence-source` at `f1a55079d524b69a54f4b57bf87004ed57f5a650`.
- New WS42 evidence-only source branch `c137-gc24-k2p03-b12-ws42-authenticated-select1-evidence-source`.
- Protected original B12 source `c137-gc24-k2p03-b12-cas-writer-cutover-source` at `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Protected production source `v320-renee-agrisuite-branding` at `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- Literal B12 cutover HARD-OFF gate `lib/daily_record_revision_cutover_gate.php`, Git blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`.
- Historical WS07 offline runner `scripts/verify_k2p03_b12_ws07_offline.sh`, Git blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`.

## User authorization and exact operational boundary

User explicitly approved WS42's two separately described operations: (1) a second single bounded local Docker startup-log read retrieving the generated password into ephemeral local process memory, and (2) a single authenticated `SELECT 1` through Unix socket inside the NEW `renee-b12-ws32-mariadb114-stopped` container (static name but running). All user-visible artifacts were required to be sanitized. No backup copy/import, migration, root credential modification, production contact, customer data, protected-container SQL, or other query was authorized.

The shell-driven Python script verified source identity, Mac-local Docker Desktop, pinned Linux AMD64 `mariadb:11.4` image fingerprint, new container/network-none/port-none/non-privileged security constraints, dedicated new volume `renee-b12-ws32-datadir`, and pre/post inventories (two preexisting running containers, three preexisting named volumes, plus the new container/volume). Only one `docker logs --tail all <exact-target-ID>` call occurred, with a 4 MiB / 20-second bound; it found exactly one `GENERATED ROOT PASSWORD:` marker and parsed a candidate credential only in ephemeral local memory. No actual secret value is recorded in this document or supplied to ChatGPT/GitHub.

The recovered credential was piped via `docker exec -i` **stdin** into an ephemeral in-container shell, exported briefly as `MYSQL_PWD` in the isolated client's process environment, and used for exactly one read-only SQL `SELECT 1` attempt as MariaDB user `root` over the container-local Unix socket. Raw stdout/stderr were not printed; stdout was checked for the exact byte value `1`. The Python bytearray received a best-effort zeroing on exit. Note that unavoidable transient process copies or privileged introspection of guest environment cannot be guaranteed absent; no forensic memory-erasure claim is made.

## Complete user-returned WS42 audit

```text
#####COPY FROM HERE#####
AUDIT=B12_WS42_SECRET_CONTAINED_SELECT1
SOURCE_GUARDS=PASS
LOCAL_DOCKER_DESKTOP=PASS
MARIADB_IMAGE_11_4=PASS
IMAGE_FINGERPRINT=PASS
TARGET_CONTAINER_IDENTITY=PASS
TARGET_CONTAINER_STATE=RUNNING
NETWORK_MODE=NONE
PUBLISHED_PORTS=NO
PRIVILEGED_CONTAINER=NO
DEDICATED_VOLUME=PASS
VOLUME_SHARED_WITH_PROTECTED=NO
LOG_INVOCATIONS=1
PASSWORD_MARKER_COUNT=1
PASSWORD_EXTRACTED_IN_LOCAL_MEMORY=YES
PASSWORD_VALUE_DISPLAYED=NO
PASSWORD_IN_HOST_COMMAND_ARGUMENTS=NO
PASSWORD_IN_HOST_SHELL_HISTORY=NO
PASSWORD_TRANSFER=DOCKER_EXEC_STDIN
PASSWORD_IN_GUEST_CLIENT_ENVIRONMENT=TRANSIENT
RAW_LOGS_DISPLAYED=NO
RAW_LOGS_SAVED_TO_NEW_FILE=NO
RAW_LOGS_SENT_TO_CHAT_OR_GITHUB=NO
QUERY_ATTEMPTS=1
QUERY=SELECT_1
DATABASE_USER=ROOT
AUTHENTICATION_CHANNEL=LOCAL_UNIX_SOCKET
AUTHENTICATED_SELECT1=PASS
SQL_STATEMENTS_SUBMITTED=1
ADDITIONAL_SQL=NO
SCHEMA_CHANGES_BY_AUDIT=NO
APPLICATION_MIGRATIONS=NO
BACKUP_OPENED=NO
BACKUP_COPIED=NO
BACKUP_RESTORED=NO
PROTECTED_RUNNING_CONTAINERS=2
PROTECTED_ORIGINAL_VOLUMES=3
RESOURCE_INVENTORY_STABLE=PASS
PRODUCTION_CONTACT=NO
B12_CUTOVER=OFF
RESULT=WS42_SQL_READINESS_PROBE_COMPLETE
#####COPY TO HERE#####
```

## Valid interpretation

`AUTHENTICATED_SELECT1=PASS` establishes **one authenticated SQL session in the isolated MariaDB 11.4 server**, with the generated credential accepted and fixed `SELECT 1` returning scalar `1`. This fulfills the narrow SQL responsiveness/authentication gate previously blocked in WS38 and WS39. It does **not** establish a diagnosis of those earlier no-password failures or effective root authentication plugin, and does **not** certify all database initialization, application schema, dump consistency, backup provenance, integrity, fixture correctness or simultaneous writer CAS semantics.

**WS42 authorization has been consumed.** Do not re-run log scans, expose/extract or reuse root credentials, or perform additional SQL operations without new explicit authorization. Temporary log/credential handling was confined to the local WS32 test environment; no password values are or should be in this repository.

## Next safe stage proposal — WS43 local-only pre-restore qualification, NOT YET APPROVED

Before planning an actual import, require a new explicit user authorization for a **read-only, local-only inspection of production-origin `~/Downloads/database.sql`** (no customer records printed, copied, uploaded or extracted) paired with updated host backing-storage headroom and target identity. Scope to (a) pinned backup file path/owner permissions, size and SHA256; (b) incremental stream validation/structured classification of CREATE/INSERT/DROP/TABLE/transaction boundaries, hazardous directives, compatibility with MariaDB 11.4, without exposing schema names, table/column identifiers, data values, or unredacted SQL lines; (c) compare previous baseline and fail closed on changed hashes; (d) inspect updated host free space and isolated Docker target metadata, preserving two original running containers/three original named volumes; (e) estimate conservatively whether a **separately authorized** import could fit and whether a transaction-consistent snapshot can be established from syntax alone (often cannot). No `docker exec`, SQL client, DB query, container writes, backup copy, Docker logs, password access, SQL migration or import in WS43. Whether contents themselves qualify for restoration is unresolved.

The user previously reported that this file was generated from live production Renee AgriSuite, but this report is **not independent provenance attestation**. Prior local checks: file 619163 bytes SHA256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`, 0600 access mode; textual header appears MariaDB 11.4, 69 CREATE TABLE / 69 destructive DROP TABLE and 65 INSERT clauses, no observed transaction framing, dump footer present. No content access was made during WS42. Host backing free space at earlier WS31 snapshot was ~166.89 GiB; WS37 guest `/var/lib/mysql` advertised 213.14 GiB, which is NOT a host-backed free-space promise. Any planned import requires a **separate explicit authorization** limited to the new target; never run the destructive dump in production, staging or any protected fixture.

## Standing B12 gates

B12 Daily Record revision writer `B12_CUTOVER=HARD_OFF`; migration 092 unauthorized; repaired real MariaDB RC01–RC14 pass count **ZERO**; original historical real MariaDB attempt **FAILED `TWO_STALE_COMMITS`**. D12 15 import gates BLOCKED, Stage D UNAUTHORIZED, 150 VU HOLD, 200 VU PROHIBITED. Production, cPanel, staging, AWS and protected existing Docker test containers untouched.

**GitHub contract:** This WS42 evidence commit adds exactly this Markdown file; independent GitHub remote semantic diff must report one new doc and protected branch refs, hard-OFF gate, and historical offline runner immutable.
