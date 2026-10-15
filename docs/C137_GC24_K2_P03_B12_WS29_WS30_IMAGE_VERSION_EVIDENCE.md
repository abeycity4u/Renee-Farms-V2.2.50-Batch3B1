# C137 GC24 K2-P03 B12 — WS29 local MariaDB images and WS30 SQL exporter-version marker

**2026-10-15 — source-only evidence consolidation from user-returned metadata. No code, database, server or Docker runtime changes authorized or executed here.**

## Source identity and protected invariants

- Repository `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Parent: WS28 `c137-gc24-k2p03-b12-ws28-memory-headroom-evidence-source`, `6e2dee1a2cfa9996e4f2edab15fcf5e4ed94bb91`.
- Current branch `c137-gc24-k2p03-b12-ws30-image-export-version-evidence-source`; WS29 and WS30 are explicitly consolidated into one documentation record to avoid duplicate source-only commits.
- Protected original B12 branch `c137-gc24-k2p03-b12-cas-writer-cutover-source`: `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Protected production branch `v320-renee-agrisuite-branding`: `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- B12 hard-OFF gate blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`; immutable WS07 runner blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`.

## WS29: user-returned local Docker image metadata

`AUDIT=B12_WS29_LOCAL_IMAGE_READINESS`, `RESULT=WS29_IMAGE_METADATA_COMPLETE`.

- `SOURCE_GUARDS=PASS`, `LOCAL_DOCKER_DESKTOP=PASS`, `ENGINE_ARCHITECTURE=AMD64`.
- `PROTECTED_CONTAINERS=2`, `PROTECTED_VOLUMES=3`.
- `DATABASE_IMAGE_CANDIDATES=2`: local MariaDB 10.11, Linux AMD64, SHA-256-of-local-image-ID fingerprint `1fbb47444479daff`; local MariaDB 11.4, Linux AMD64, SHA-256-of-local-image-ID fingerprint `2ee3c4a72fcac30a`.
- `VERSIONED_MARIADB_CANDIDATES=2`, `LOCAL_IMAGE_CANDIDATE=IDENTIFIED`, `DOCKER_VM_DISK_FREE=NOT_ATTESTED`.
- `IMAGES_PULLED=NO`, `CONTAINERS_CREATED=NO`, `VOLUMES_CREATED=NO`, `EXISTING_RESOURCES_CHANGED=NO`, `BACKUP_OPENED=NO`, `DATABASE_CONNECTIONS=0`, `DATABASE_QUERIES=0`, `RESTORE_EXECUTED=NO`, `PRODUCTION_CONTACT=NO`, `PHYSICAL_CLONE_PROVENANCE=NOT_ESTABLISHED`, `B12_CUTOVER=OFF`.

The image fingerprints are *only 16-character prefixes of a hash of the full local image ID*, not the Docker image ID itself or a registry-verified digest. They aid comparison but do not make a tag immutable. At any future authorization gate, resolve the local full image ID/digest and verify Linux AMD64, expected image fingerprint, offline/no-pull mode.

## WS30: user-returned, hash-pinned, offline export-version inspection

`AUDIT=B12_WS30_DUMP_VERSION_COMPATIBILITY`, `RESULT=WS30_VERSION_METADATA_COMPLETE`.

- Mac-local `~/Downloads/database.sql` confirmed regular owner-only mode `600`, 619163 bytes, pinned SHA-256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`; backup not uploaded or included in GitHub.
- `FILE_IDENTITY=PASS`, `SHA256=PASS`, `OWNER_ONLY_PERMISSIONS=PASS`, `SERVER_VERSION_MARKER_FOUND=YES`, `REPORTED_SERVER_MAJOR_MINOR=11.4`.
- `MATCHING_LOCAL_IMAGE_CANDIDATE=MARIADB_11_4`.
- `BACKUP_VERSION_SOURCE=UNVERIFIED_HEADER`, `RUNTIME_COMPATIBILITY=NOT_CERTIFIED`.
- `RAW_SQL_DISPLAYED=NO`, `CUSTOMER_ROWS_DISPLAYED=NO`, `DATABASE_CONNECTIONS=0`, `SQL_STATEMENTS_EXECUTED=0`, `BACKUP_FILES_CHANGED=NO`, `PRODUCTION_CONTACT=NO`, `CLONE_CREATED=NO`, `B12_CUTOVER=OFF`.

**Decision:** Use **MariaDB 11.4** as the *preferred prospective isolated target version* instead of the earlier provisional 10.11 candidate, because the dump's own *unverified* source-server header reports 11.4 and a corresponding local AMD64 image is cached. No restoration or runtime compatibility is certified. The header cannot independently confirm production provenance, MariaDB patch version, original dump options, table consistency or row integrity. Do not conflate the historical C137 test database backup `renee_c137_gc24_http` with this separate local SQL file.

## WS31 remaining constraints before authorization

- Locally verify Docker Desktop's backing filesystem free space using **read-only Mac filesystem metadata only**; do not equate Mac filesystem free space with free space inside the Docker VM. If Docker VM free-space cannot be independently established, say `NOT_ATTESTED`, and require a narrowly scoped capacity-safety plan before any write.
- Prepare a named, exclusively new and initially empty disposable container with **network none**, no published ports, no privileged mode, no host/previous named or anonymous protected volume mounts, no live credentials, no application writers, and resource limits/teardown; explicitly audit that creation never affects the two protected containers or three protected volumes. The `docker run` operation must require a **separate, specific user authorization** and a follow-up identity check; do not sneak creation into metadata inspections.
- Keep backup data unhandled pending independent user-controlled origin/custody verification, consistency/restore-risk review, a privacy and cleanup plan, and **another explicit authorization** for copying/importing `database.sql`. Never restore on production, staging, C137 fixture/restore, existing synthetic B12 MySQL family container, shared CI or default Docker infrastructure.
- The B12 11-section aggregate read-only schema inventory and repaired real MariaDB RC01–RC14 tests are later, separate approval scopes. No migration 092, no writer cutover, and no capacity/load test.

## Status

`WS29_LOCAL_IMAGE=IDENTIFIED`, `WS30_DUMP_VERSION_MATCH=MARIADB_11_4_PRELIMINARY`; `DOCKER_VM_CAPACITY=NOT_ATTESTED`; `BACKUP_PROVENANCE=UNVERIFIED`; `PHYSICAL_CLONE=NOT_ESTABLISHED`; `RC01_RC14_PASSES=0`; `MIGRATION_092=NOT_APPROVED`; `B12_CUTOVER=HARD_OFF`; D12 import gates BLOCKED, Stage D UNAUTHORIZED, 150 VU HOLD, 200 VU PROHIBITED.

This GitHub commit changes **only this Markdown evidence document**; no Docker, SQL, PHP, JS, migration, CI, backup, protected branch, production or staging changes.
