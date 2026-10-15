# C137 / GC24 / K2-P03 / B12 — WS31 Docker Desktop storage metadata and container-preparation authorization boundary

**2026-10-15. Status: user-returned Mac storage metadata recorded; no Docker guest free-space attestation, capacity approval, container creation or production-derived restore.**

## Protected source checkpoints

- Repo: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Parent: `c137-gc24-k2p03-b12-ws30-image-export-version-evidence-source` at `080ae42c1e565c08f68a13bd0da31ee1e73ebdaf`.
- WS31 branch: `c137-gc24-k2p03-b12-ws31-docker-storage-evidence-source`.
- Protected production branch: `v320-renee-agrisuite-branding` at `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- Protected original B12 branch: `c137-gc24-k2p03-b12-cas-writer-cutover-source` at `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- B12 hard-OFF gate blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268` and immutable WS07 runner blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`.

## Exact user-returned Mac evidence

`AUDIT=B12_WS31_DOCKER_STORAGE_PREFLIGHT`, `RESULT=WS31_STORAGE_METADATA_COMPLETE`.

| Field | Observed |
| --- | --- |
| Source guards / Docker Desktop local | PASS / PASS |
| Protected existing/running containers | 2 |
| Protected volumes | 3 |
| Mac HOME filesystem free | `166.89 GiB` |
| Docker Desktop VM backing-file candidate | ONE_BACKING_FILE_CANDIDATE |
| Backing-file *logical* size | `233.47 GiB` |
| Backing-file *allocated host disk blocks* | `2.77 GiB` |
| Backing filesystem free | `166.89 GiB` |
| Docker VM **guest** free disk space | `NOT_ATTESTED` |
| Disk capacity approved | NO |
| Storage inspection | METADATA_ONLY |
| Protected resources unchanged | PASS |
| Containers/volumes created, backup opened | NO / NO / NO |
| DB connections/queries, restore | 0 / 0 / NO |
| Production contact, B12 cutover | NO / OFF |

The 233.47 GiB value is a sparse backing-file logical size, **not** currently used physical space and not the VM's usable free storage. The 2.77 GiB allocated host blocks are likewise **not** the guest's internal used space. 166.89 GiB on the host backing filesystem provides encouraging storage headroom **outside** the VM only. Neither the current Docker guest free space nor Docker storage-layer quotas, per-volume free bytes, or likely database restore requirements were proven. Do not infer a guest capacity PASS from this. `df` against `$HOME` is not Docker guest `df`.

## Cross-step context

- WS28 Docker configured RAM 3911 MiB; the two existing containers used 267.6 MiB (6.8%) in one snapshot. No guarantee this remainder is usable after VM/guest overhead.
- WS29 two locally cached MariaDB image tags `10.11` and `11.4`, Linux AMD64. The latter had SHA256-of-image-ID prefix `2ee3c4a72fcac30a`; these short fingerprints are metadata hints, **not** full immutable image digests.
- WS30 Mac-local owner-only `~/Downloads/database.sql` header reports MariaDB `11.4`; local file SHA-256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`, size 619163 bytes. Independent origin and snapshot consistency remain unverified; dump has 69 potentially destructive `DROP TABLE` statements.
- Protected container inventory: 2 running containers (one tagged MySQL-family, one other/unknown); both Docker network mode `none`, nonprivileged, no published ports, two mounts each. **Do not reuse or inspect data within either**, and do not repurpose the three volumes.

## Decision and next authorization boundary

WS31 closes **metadata-only host storage discovery**; further minor Mac `stat` loops are not a substitute for actual VM capacity certification. A new empty, stopped container may be scoped for **future separate one-use user authorization**, with creation strictly limited to:
1. Reverify original B12 source branch+HEAD+clean worktree, Docker local socket/engine, all two existing running containers, three existing volumes, and exact cached **MariaDB 11.4 full image ID**, without pulling images.
2. Create **only one new disposable named storage volume** and **one new initially STOPPED container** using exclusively that new volume, an unprivileged image, `--network none`, no published ports, no live application credentials, no existing/protected mounts or writers. Do not start MariaDB or let a DB initialize during this scope. Use fresh non-colliding resource names, no host mounts. Preserve all original Docker identities and require a post-create invariant check.
3. Stop and report if the resource guard or engine compatibility/capacity checks fail; leave protected containers and volumes unchanged. Even creation requires explicit approval naming scope and resource identities; a general user `proceed` for analysis cannot substitute for that.
4. **Separately later** verify this new container and fresh volume are isolated, assess exact guest storage/free space safely on an expressly approved test-only environment (never exec on protected containers), establish backup origin, privacy/retention and restore plan. Importing SQL, even to a new stopped container, requires *another* specific approval; no restoration under the creation-only scope. No `docker cp`, SQL statements or database connections now.

Alternative: complete remaining isolated-target approval planning before creating anything. No upload of the potentially customer-derived `database.sql` to ChatGPT, public GitHub, CI, staging, protected C137 or other systems. `scripts/k2p03_b12_schema_readonly_gate.php --run`, real RC01–RC14, migration 092, B12 cutover and capacity/load testing remain blocked.

**Gates:** `WS31_MAC_STORAGE_METADATA=PASS`; `DOCKER_VM_GUEST_CAPACITY=UNKNOWN`; `PHYSICAL_CLONE=NOT_ESTABLISHED`; `BACKUP_ORIGIN=UNVERIFIED`; `REPAIRED_RC01_RC14_PASS_COUNT=0`; `B12_CUTOVER=HARD_OFF`; `D12_STAGE_D=UNAUTHORIZED`; `150_VU=HOLD`; `200_VU=PROHIBITED`.

This documentation-only branch must change **only this report**. After commit, verify remote GitHub semantic diff and immutable protected refs; do not modify product source, deployments, Docker resources or the Mac backup.
