# C137 / GC24 / K2-P03 / B12 — WS40F static MariaDB image/container configuration

**2026-10-15 — User-returned sanitized Docker Desktop read-only metadata. WS40F is COMPLETE. This certifies neither database authentication nor root-password requirements.**

## Source and protected references

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Parent docs-only source: `c137-gc24-k2p03-b12-ws40e-selectors-evidence-source` HEAD `6a5f5ccc5b1573f6554f76968d242d4c49112379`.
- New docs-only source branch: `c137-gc24-k2p03-b12-ws40f-classification-evidence-source`.
- Original B12 source `c137-gc24-k2p03-b12-cas-writer-cutover-source` HEAD `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Protected production source `v320-renee-agrisuite-branding` HEAD `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- B12 hard-OFF gate `lib/daily_record_revision_cutover_gate.php` blob SHA `6e88c913ad5e5b371a9d0c92ce08870214ac1268`.
- Historical WS07 offline runner `scripts/verify_k2p03_b12_ws07_offline.sh` blob SHA `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`.

## Exact user-returned WS40F evidence

```text
#####COPY FROM HERE#####
AUDIT=B12_WS40F_STATIC_AUTH_CLASSIFICATION
SOURCE_GUARDS=PASS
LOCAL_DOCKER_DESKTOP=PASS
IMAGE_IDENTITY=PASS
IMAGE_ENTRYPOINT_CLASS=STANDARD_NAMED_ENTRYPOINT
IMAGE_DEFAULT_COMMAND_CLASS=MARIADBD_DEFAULT
IMAGE_HEALTHCHECK=NOT_CONFIGURED
IMAGE_DATADIR_VOLUME_DECLARED=YES
CONTAINER_ENTRYPOINT_MATCH_IMAGE=YES
CONTAINER_COMMAND_MATCH_IMAGE=YES
CONTAINER_USER_CONFIGURATION=IMAGE_DEFAULT
TARGET_CONTAINER_STATE=RUNNING
NETWORK_MODE=NONE
PUBLISHED_PORTS=NO
PRIVILEGED_CONTAINER=NO
DEDICATED_VOLUME=PASS
PROTECTED_RUNNING_CONTAINERS=2
PROTECTED_ORIGINAL_VOLUMES=3
RESOURCE_INVENTORY_STABLE=PASS
DATABASE_AUTHENTICATION_POLICY=NOT_DETERMINED
ROOT_PASSWORD_ACCESSED=NO
DOCKER_LOGS_READ=NO
DATABASE_CONNECTIONS=0
SQL_STATEMENTS_EXECUTED=0
BACKUP_OPENED=NO
BACKUP_RESTORED=NO
DOCKER_RESOURCES_CHANGED=NO
PRODUCTION_CONTACT=NO
B12_CUTOVER=OFF
RESULT=WS40F_STATIC_CLASSIFICATION_COMPLETE
#####COPY TO HERE#####
```

## Verified conclusions and limits

- The pinned `mariadb:11.4` AMD64 image uses the conventional named `docker-entrypoint.sh` entrypoint, and its default command is exactly `mariadbd` according to the WS40F classifier.
- This container has no entrypoint/command override relative to the image, uses the image-default user configuration, declares `/var/lib/mysql` as image volume, and has **no configured image-level Docker healthcheck**.
- The NEW separately created container `renee-b12-ws32-mariadb114-stopped` is **running** despite its static name, on `--network none`, with no published ports or container privileges, and uses dedicated `renee-b12-ws32-datadir`. Two originally protected running containers and three original protected volumes were preserved.
- This rules out an **entrypoint/command override** as an explanation for WS38/WS39 authentication failure; it **does not** identify the effective root authentication plugin, generated password value, initialization success, MariaDB user grants, or client error.
- WS40 initial combined Docker image Go-template failed; WS40D isolated `IMAGE_FULL_TEMPLATE`, WS40E verified seven selectors independently, and WS40F successfully obtained sanitized classifications by splitting selectors into individually bounded read-only inspections. Root cause of previous combined-template failure not independently established; no need to rerun closed successful metadata checks.

## Next dependency and separate authorization

WS36 `mariadb-admin ping` `SERVER_RESPONDS` does not prove authentication; WS37 showed Unix socket and MariaDB system directory, and guest datadir filesystem 213.14 GiB available, not guaranteed backed by host storage. WS38 single password-free `mysql` `SELECT 1` and WS39 single password-free `root` `SELECT 1` both returned `AUTHENTICATED_SELECT1=NOT_ESTABLISHED`; no error classification was collected. There is **no certified authenticated SQL session**, and attempts may not be repeated without fresh scope and authorization.

WS32 initialization originally requested `MARIADB_RANDOM_ROOT_PASSWORD=1`; that startup request is not proof of successful password generation, output location, password validity or current root grants. To progress, recommend designing an **explicitly authorized, carefully bounded local credential-handling preflight**. Reading the new container's Docker startup logs is a **new privacy/secret access** not covered by WS40F. Do **not** read Docker logs or `docker inspect` full environment, display/copy passwords to chat/GitHub, try more credentials, reset user accounts or change database config without authorization. A future feasible design could process only the new container's logs in local memory for the known MariaDB generated-root-password marker, output a **boolean/enum only**, and discard logs without revealing the random root password. Even that requires separate user consent; any subsequent use of a recovered secret for SQL needs separately bounded approval and safe passing of credentials without argv/histories.

Private reported production-origin backup `~/Downloads/database.sql`: 619163 bytes, SHA256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`; header says MariaDB 11.4 but source provenance and snapshot consistency unverified, 69 dangerous `DROP TABLE` statements. Do not copy, upload, read or import without separate consent. WS31 Mac backing FS had 166.89 GiB free earlier, whereas WS37 guest datadir reported 213.14 GiB—guest bytes are not guaranteed physically backed.

Global gates: B12 cutover **HARD-OFF**; migration 092 unauthorized; repaired real MariaDB RC01–RC14 passes ZERO, prior historical run FAILED `TWO_STALE_COMMITS`; D12 import gates BLOCKED, Stage D UNAUTHORIZED, 150 VU HOLD, 200 VU PROHIBITED; production, staging, AWS/cPanel, and historical C137 fixture untouched.

GitHub action contract: exactly one new Markdown evidence file; perform independent remote semantic diff against parent and re-verify protected branch SHA and gate/verifier Git blobs. No application, Docker, DB or backup changes.
