# C137 / GC24 / K2-P03 / B12 — WS27 Mac disposable database feasibility metadata

**2026-10-15 — User-returned, metadata-only assessment. Screening requires resource review; this is not failure or approval to create/restore a production-derived physical clone.**

## Immutable source/protection boundaries

- Repo `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`; WS27 parent `c137-gc24-k2p03-b12-ws26-container-classification-evidence-source` at `c401b0000843ada38dcf7fc8e6efac1339616f1f`.
- WS27 branch `c137-gc24-k2p03-b12-ws27-disposable-feasibility-evidence-source`.
- Protected production branch `v320-renee-agrisuite-branding` at `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- Original B12 branch `c137-gc24-k2p03-b12-cas-writer-cutover-source` at `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- B12 literal hard-OFF gate `lib/daily_record_revision_cutover_gate.php` blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`.
- Historical WS07 offline runner blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`.

## Exact WS27 Mac output supplied by user

`AUDIT=B12_WS27_DISPOSABLE_ENV_FEASIBILITY`, `RESULT=WS27_FEASIBILITY_METADATA_COMPLETE`:

| Safe metadata fact | Value |
| --- | --- |
| Original source guards | PASS |
| Docker Desktop local engine | PASS |
| Docker Linux engine architecture | AMD64 |
| Docker engine configured memory | BELOW_4_GIB |
| Mac HOME filesystem free space | AT_LEAST_4_GIB |
| Local database image hint | YES |
| Matching image repository entries | 2 |
| Existing containers / running | 2 / 2 |
| Protected Docker volumes | 3 |
| Resource review | CAPACITY_REVIEW_REQUIRED |
| Existing containers changed | NO |
| Image pulls, networks/volumes/containers created | NO |
| Backup opened | NO |
| DB connections/queries | 0 / 0 |
| Restore executed, production contact | NO / NO |
| New clone provenance | NOT_ESTABLISHED |
| B12 cutover | OFF |

The WS27 4 GiB threshold is only a **conservative resource-screening heuristic**. `BELOW_4_GIB` does not imply MariaDB cannot run or that more memory must be assigned. The Docker engine's precise memory and protected existing workloads' current consumption are not reported. The Mac `$HOME` filesystem space does not attest Docker Desktop VM/container storage headroom, and an image repository name match does not prove an exact MariaDB version or architecture suitability. **Do not change Docker Desktop resource allocations:** that action could restart or disrupt the two protected running containers.

The two existing running containers and three volumes remain protected. WS26 saw `NetworkMode=none` and no configured published ports for both containers, with one MySQL-family image; that is not a host/data isolation certificate, nor permission to repurpose their mounts, network, images or files.

## Narrow WS28 candidate: capacity-headroom read, not resource change

An optional user-executed Mac-local Docker **metadata/stats-only** preflight may reassert the source guards and socket, retrieve exact Docker Desktop engine memory to a coarse MiB/GiB range and use `docker stats --no-stream` for coarse current container memory usage (no process execution, logs, mounted filesystem inspection, SQL or backup read). Guard counts: exactly 2 existing/running containers and 3 existing protected volumes; on drift stop. Do not output container names/IDs, image refs, ports, usernames or stats JSON. If runtime stats are unavailable, mark INCONCLUSIVE; do not fabricate headroom. Runtime memory snapshots can change and do not by themselves establish safe performance allocation. No Docker memory-setting changes.

WS28 must not issue `docker run/start/stop/exec/cp/pull`, Docker Compose, volume creation/removal, MariaDB connections, `database.sql` restore or any migration. It must not touch protected synthetic/C137 containers or AWS/cPanel/staging/production. The separately user-reported `database.sql` candidate (SHA-256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`) remains private and is independently **unverified** as a live production dump despite matching pre-revision DDL markers; the dump contains 69 destructive `DROP TABLE` statements.

## Release and approval gates remain

Before any newly created clone, restore, actual database inventory or repaired RC01–RC14 run, independently establish backup chain of custody, a new disposable target not sharing protected data/volumes, physical/container/network isolation, scoped user approval, DB credential grants, privacy/retention/teardown, and separate execution scope. `scripts/k2p03_b12_schema_readonly_gate.php --run` remains unauthorized. Do not substitute historical C137 `renee_c137_gc24_http` backup for a production-derived source.

No migration `092` or cutover is authorized. Repaired RC01–RC14 pass count ZERO; original historical failed two-stale-commits run is not certified. D12 15 import gates BLOCKED, Stage D UNAUTHORIZED, 150 VU HOLD, 200 VU PROHIBITED.

**WS27 doc-only branch commit:** This file only; remote semantic diff and protected-branch/gate checks required. No database, Mac, Docker, CI or PHP/SQL product changes.
