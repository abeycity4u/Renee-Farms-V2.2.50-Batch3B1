# C137 / GC24 / K2-P03 / B12 — WS28 local Docker memory headroom metadata

**2026-10-15. Status: USER-RETURNED, NONMUTATING, LOCAL DOCKER METADATA ONLY. No physical clone attestation, database access, restore or B12 certification.**

## Exact pinned continuity / source guards

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- WS28 parent: `c137-gc24-k2p03-b12-ws27-disposable-feasibility-evidence-source` commit `772f41415e24049055ffe212864e2618fbcac02a`.
- WS28 source-only branch: `c137-gc24-k2p03-b12-ws28-memory-headroom-evidence-source`.
- Protected production branch `v320-renee-agrisuite-branding` HEAD `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- Protected original B12 branch `c137-gc24-k2p03-b12-cas-writer-cutover-source` HEAD `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Literal hard-OFF gate `lib/daily_record_revision_cutover_gate.php` blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`.
- Historical WS07 offline runner `scripts/verify_k2p03_b12_ws07_offline.sh` blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`.

## Exact user-returned result

`AUDIT=B12_WS28_DOCKER_MEMORY_HEADROOM` `RESULT=WS28_MEMORY_METADATA_COMPLETE`:

| Allowlisted result field | User-returned value |
| --- | --- |
| SOURCE_GUARDS | PASS |
| LOCAL_DOCKER_IDENTITY | PASS |
| ENGINE_MEMORY_CLASS | `3_TO_BELOW_4_GIB` |
| ENGINE_MEMORY_MIB | `3911` |
| RUNNING_CONTAINER_MEMORY_MIB | `267.6` |
| RUNNING_CONTAINER_MEMORY_PERCENT | `6.8` |
| PROTECTED_CONTAINERS | `2` |
| PROTECTED_VOLUMES | `3` |
| CONTAINER_STATE_STABLE | PASS |
| MEASUREMENT | `POINT_IN_TIME` |
| OS_AND_OTHER_MEMORY_OVERHEAD | `NOT_MEASURED` |
| ACTUAL_FREE_MEMORY | `NOT_CERTIFIED` |
| RESOURCE_CAPACITY_APPROVED | NO |
| CONTAINERS_CHANGED / DOCKER_SETTINGS_CHANGED | NO / NO |
| BACKUP_OPENED / RESTORE_EXECUTED | NO / NO |
| DATABASE_CONNECTIONS / DATABASE_QUERIES | 0 / 0 |
| PRODUCTION_CONTACT / B12_CUTOVER | NO / OFF |

## Interpretation and decision gate

- Docker engine configuration is 3911 MiB (approximately 3.82 GiB); the two protected running containers consumed 267.6 MiB according to one `docker stats --no-stream` snapshot, 6.8% of configured engine memory. This does **not** prove that the arithmetic remainder is available to a new database: Linux guest overhead, filesystem page cache, workloads, fragmentation and peak demand are not measured or certified. Do not alter Docker Desktop memory settings or interrupt protected containers based on this.
- WS27 reports at least 4 GiB of free space on the Mac `$HOME` filesystem, **not** verified free space in the Docker Desktop virtual-machine disk. There are two matching locally cached image-repository entries, but no verified version, CPU-platform digest or suitability yet.
- A small new disposable MariaDB instance may be feasible, but **no container/volume/network creation or backup import is authorized** by these measurements. The two running containers and three volumes, including the prior synthetic MySQL-family resource, remain protected. Treat image sharing as distinct from data/volume isolation.
- The privately retained `~/Downloads/database.sql` file is 619163 bytes with SHA-256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`, mode 600 per user-returned WS20–WS24 Mac audits. Its **production origin and dump consistency remain independently UNVERIFIED**; it includes 69 `DROP TABLE` statements, so never restore into an existing or protected DB.
- Hard-OFF B12, migration 092 unauthorized, real repaired RC01–RC14 zero passes, actual tenant schema integrity and duplication counts UNKNOWN. D12 15 import gates BLOCKED; Stage D UNAUTHORIZED; 150 VU HOLD; 200 VU PROHIBITED.

## Next controlled action: WS29 exact cached image and Docker VM capacity metadata, then authorization boundary

A narrowly scoped, MAC-LOCAL nonmutating metadata preflight may inspect **locally present** MariaDB-family repository:tag labels and validated image architecture to select an exact immutable candidate image ID/digest for a future disposable target. Print only coarse allowlisted image brand, major/minor tag/version, architecture, a deterministic SHA-256 of image ID if needed for identity (never image env or history), Docker disk free-space category and protected resource counts. Never pull images, expose credentials, print raw inspect JSON, access protected volumes, open `database.sql`, or start/exec/create containers. Source branch/HEAD/context gates must be asserted first. If no suitable locally cached immutable image is identified, mark INCONCLUSIVE rather than silently downloading one.

After WS29 and independently corroborating chain of custody, provide a concrete **one-use scoped authorization request** specifying only a fresh container/network/volume with explicit `--network none`, no existing volume mounts, privilege escalation or published ports; no SQL import or reads in that authorization step. Later backup copy/restore to a newly created empty disposable target, aggregation-only schema inventory, and RC01–RC14 each need separate approval and verifier checks. No physical isolation is established just because Docker Desktop is local, engine free-memory stats are low, or an image exists in cache. Preserve all protected source, production, C137 and synthetic B12 resources.

This WS28 branch contains documentation only. Verify GitHub semantic diff and protected references after commit; no application PHP, SQL, JS, migrations, CI, deployment, Docker runtime or Mac data modified.
