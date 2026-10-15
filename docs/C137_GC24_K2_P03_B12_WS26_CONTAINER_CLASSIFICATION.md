# C137 GC24 K2-P03 B12 — WS26 Mac Docker container classification

**2026-10-15 — User-returned, Docker Desktop metadata only. SOURCE-ONLY EVIDENCE RECORD; not database connection, backup restoration, replica provenance, network-security attestation or B12 certification.**

## Source, state and protected boundaries

- Repo `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- WS26 parent: `c137-gc24-k2p03-b12-ws25-docker-metadata-evidence-source` at `6602b433c6e0b7af1b9a5cda78162b971b164ad1`.
- WS26 branch: `c137-gc24-k2p03-b12-ws26-container-classification-evidence-source`.
- Original B12 branch `c137-gc24-k2p03-b12-cas-writer-cutover-source`, `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Protected production `v320-renee-agrisuite-branding`, `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- Hard-OFF cutover `lib/daily_record_revision_cutover_gate.php`, blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`.
- Historical WS07 offline runner blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`, unchanged.

## User-returned metadata

`AUDIT=B12_WS26_CONTAINER_CLASSIFICATION`, `RESULT=WS26_METADATA_CLASSIFICATION_COMPLETE`.

| Property | Container 1 | Container 2 |
| --- | --- | --- |
| Heuristic image family | OTHER_OR_UNKNOWN_IMAGE | MYSQL_FAMILY_IMAGE |
| Docker network mode category | NONE | NONE |
| Privileged flag | NO | NO |
| Mount count | 2 | 2 |
| Configured published-port presence | NO | NO |
| Runtime state | Running | Running |

Aggregate: `CONTAINER_TOTAL=2`, `CONTAINER_RUNNING=2`, `DATABASE_IMAGE_CANDIDATES=1`; `MAC_IDENTITY=PASS`, `SOURCE_GUARDS=PASS`, `LOCAL_DOCKER_DESKTOP=PASS`; `ALL_EXISTING_CONTAINERS=PROTECTED`. `CONTAINERS_CHANGED=NO`, `VOLUMES_CHANGED=NO`, `BACKUP_OPENED=NO`, `DATABASE_CONNECTIONS=0`, `DATABASE_QUERIES=0`, `DATABASE_WRITES=NO`, `CLONE_CREATED=NO`, `PRODUCTION_CONTACT=NO`, `PHYSICAL_CLONE_PROVENANCE=NOT_ESTABLISHED`, `B12_CUTOVER=OFF`.

**Interpretation:** The image-string categorization is heuristic, not a running-service identity or proof of customer-data provenance. Docker `NetworkMode=none` and absent configured port mappings are meaningful container-network restrictions but not a complete host/engine, mounted-resource, Unix-socket or data-isolation attestation. The two existing containers (including the MySQL-family image) and their mounts and volumes remain **protected**, even if apparently isolated. Do not repurpose, stop, start, exec into, copy from, or query them.

## Next: WS27 independent disposable environment feasibility, nonmutating

Read-only local metadata can establish whether a **separate new** disposable MariaDB container is feasible, without opening or reusing protected resources. Required checks:
1. Reassert Mac OS, original B12 branch/HEAD, clean checkout and exact repository remote; reject Docker environment context, host, TLS/cert overrides. Confirm a pinned local Docker Desktop Unix socket and recognized Docker Desktop engine.
2. Use Docker metadata only: engine architecture/OS, resource memory configured, local **image availability** (do not pull), local free disk-space threshold, container count drift (two existing/running), and volume count drift (three) from WS25. Do not display image identifiers, container names, ports, network details, mount paths, secrets, raw `docker inspect` or private IP addresses.
3. Print minimum necessary allowlisted classification/capability summary, no passwords and no production/client data. Never create/start/stop/pull/exec containers, networks or volumes. Never open or copy `~/Downloads/database.sql`.
4. Even a capability PASS is not physical-isolation certification. A future production-derived restore needs independent backup provenance, fresh host/container/volume/namespace isolation plan, identity/non-reuse checks, privacy and encryption/retention, backup verification, grants, no application writers, teardown, and **explicit one-use approval** for named resource and actions. Do not set `B12_SCHEMA_APPROVED_RUN` or run `scripts/k2p03_b12_schema_readonly_gate.php --run` on WS27.

The local candidate SQL dump remains 619,163 bytes and pinned SHA-256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`, mode 600, production origin not independently attested; contains 69 potentially destructive `DROP TABLE` statements. Its data must not be sent to GitHub/CI, ChatGPT, other services or existing databases. WS22 file DDL markers are not physical DB schema proof.

## Unchanged gates

The historical B12 MariaDB concurrency run `38233373699` remains FAILED `TWO_STALE_COMMITS`; fresh repaired RC01–RC14 pass count 0. No `092` revision migration is authorized; coordinated reader/writer/browser cutover is OFF. D12 15 import gates BLOCKED, Stage D UNAUTHORIZED, 150 VU HOLD, 200 VU PROHIBITED.

WS26 implementation scope: this documentation file only. No application, SQL, migration, GitHub workflow execution, protected backup, database or infrastructure modification.
