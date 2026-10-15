# C137 GC24 K2-P03 B12 — WS41 generated root-password marker inspection

**2026-10-15 — successful, separately user-authorized, ONE read-only local startup-log stream inspection of ONLY the new isolated MariaDB 11.4 container. Marker present. Password was not extracted or disclosed.**

## Source continuity and immutable guard state

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Parent docs-only evidence branch `c137-gc24-k2p03-b12-ws40f-classification-evidence-source` HEAD `082b153e699277a35cf87cc1c40d5edb5a1a1948`.
- New WS41 docs-only evidence branch `c137-gc24-k2p03-b12-ws41-random-password-marker-evidence-source`.
- Protected original B12 source branch `c137-gc24-k2p03-b12-cas-writer-cutover-source` HEAD `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Protected production source branch `v320-renee-agrisuite-branding` HEAD `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- Literal B12 hard-OFF gate `lib/daily_record_revision_cutover_gate.php` Git blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`.
- Historical WS07 offline verifier `scripts/verify_k2p03_b12_ws07_offline.sh` Git blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`.

## WS41 scope, exactly as authorized

User said `proceed` after explicitly being asked to allow local inspection of the **new** MariaDB container startup logs for a generated-root-password marker without disclosing any password. WS41 command rechecked source/Docker/target image `mariadb:11.4`, Docker socket, network mode none/no published ports/unprivileged, new dedicated volume `renee-b12-ws32-datadir`, two original running containers/three protected named volumes before/after, and executed exactly **one** bounded `docker logs --tail all <exact-target-container-id>` via local subprocess stream.

The raw logs contained potentially secret-bearing bytes and were actually read in the user's local Python process. The script searched for uppercase `GENERATED ROOT PASSWORD:`, using a 4 MiB cumulative byte cap and 20-second time limit; it returned marker **presence only**, did not extract or show any password value, did not save raw logs to a new file, and did not transmit raw logs to ChatGPT/GitHub. It did not use `docker exec`, SQL, MariaDB clients, backup data or production services.

## Complete user-returned evidence

```text
#####COPY FROM HERE#####
AUDIT=B12_WS41_RANDOM_ROOT_PASSWORD_MARKER
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
DOCKER_LOG_INVOCATIONS=1
LOG_SCAN=COMPLETE
LOG_SCAN_LIMIT_MIB=4
GENERATED_ROOT_PASSWORD_MARKER=PRESENT
PASSWORD_VALUE_EXTRACTED=NO
PASSWORD_VALUE_DISPLAYED=NO
RAW_LOGS_READ_IN_LOCAL_PROCESS=YES
RAW_LOGS_DISPLAYED=NO
RAW_LOGS_SAVED_TO_NEW_FILE=NO
RAW_LOGS_SENT_TO_CHAT_OR_GITHUB=NO
DATABASE_CONNECTIONS=0
SQL_STATEMENTS_EXECUTED=0
DOCKER_EXEC_EXECUTED=NO
BACKUP_OPENED=NO
BACKUP_RESTORED=NO
PROTECTED_RUNNING_CONTAINERS=2
PROTECTED_ORIGINAL_VOLUMES=3
RESOURCE_INVENTORY_STABLE=PASS
DOCKER_WRITES_BY_AUDIT=NO
PRODUCTION_CONTACT=NO
B12_CUTOVER=OFF
RESULT=WS41_MARKER_INSPECTION_COMPLETE
#####COPY TO HERE#####
```

## Precisely supported interpretation

The **expected generated-root-password marker is present in the available Docker startup logs** of the new isolated MariaDB instance. This supports that initialization attempted or reported generating a root password. It offers a plausible explanation for WS38's `mysql` and WS39's `root` password-free local Unix-socket `SELECT 1` failures (`AUTHENTICATED_SELECT1=NOT_ESTABLISHED` for both) but **does not prove** their cause, nor that a password from the log would authenticate now. There is still NO certified authenticated SQL session. No MariaDB grants, plugin, runtime health, password value or backup data were read by WS41.

The prior WS40F static metadata confirmed conventional `docker-entrypoint.sh`, `mariadbd`, no container override, no configured image Docker healthcheck, isolated target state. WS36 socket ping responded (not an authenticated query); WS37 confirmed system directory and Unix socket, with 213.14 GiB virtual guest free space not guaranteed physically host-backed.

## Next step requires separate new consent and a secret-safe plan

WS41's single-use log inspection authorization is **consumed**. Do not replay this command, read additional log lines, display/export/save secrets, try another login, reset root credentials, or operate on any old/protected containers without new, explicit, narrowly scoped permission.

Recommend **WS42 one-use local secret-contained authenticated SQL proof** *only after separately obtaining approval* for (1) a second, narrowly bounded local log read and extraction of the password text into ephemeral memory (unlike WS41), and (2) a single read-only local `SELECT 1` attempt to the same new isolated container via its Unix socket, without placing the password in host command-line arguments, Docker exec arguments, shell history, files, or user-visible output. Do not infer success from marker presence. Password bytes inevitably exist transiently in local process memory and possibly inside the isolated test container during an authenticated client attempt; disclose this before approval. No fallback credential guesses or auto-retries; retain no password beyond process exit and output only sanitized outcome/guards. If a secure transfer channel cannot be ensured, fail closed and review a safer alternate authentication design rather than expose the secret. Any future least-privilege provisioning is another separately gated operation.

Production-origin `~/Downloads/database.sql` remains unopened/unimported by WS41; user-reported file 619163 bytes, SHA256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`, original consistency/provenance independently unverified and destructive `DROP TABLE` statements present. Do not upload, restore or migrate without fresh approval and verified target.

**Global hard constraints:** `B12_CUTOVER=HARD_OFF`, migration 092 NOT APPROVED, repaired real MariaDB RC01–RC14 PASS count 0 (historical original attempt FAILED `TWO_STALE_COMMITS`), D12 15 import gates BLOCKED, Stage D UNAUTHORIZED, 150 VU HOLD, 200 VU PROHIBITED. No production, staging, AWS/cPanel or protected legacy fixture contact.

This evidence commit is **documentation-only**, containing this one Markdown addition; perform independent remote GitHub semantic diff against parent and reverify original/protected branch refs plus gate/runner blobs.
