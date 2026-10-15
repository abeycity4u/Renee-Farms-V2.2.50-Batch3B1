# C137 / GC24 / K2-P03 / B12 — WS25 Mac Docker Desktop metadata preflight

**2026-10-15. Classification: user-returned local metadata only. No isolated MariaDB clone proven, no database access authorized, no B12 concurrency certification.**

## Source branch protection

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Parent: WS24 `c137-gc24-k2p03-b12-ws24-restore-safety-evidence-source`, commit `5f002aa7591a90aa8a44d8ed58aa2df345549fc8`.
- WS25 branch: `c137-gc24-k2p03-b12-ws25-docker-metadata-evidence-source`.
- Protected original B12 source: `c137-gc24-k2p03-b12-cas-writer-cutover-source` at `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Protected production source: `v320-renee-agrisuite-branding` at `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- B12 literal hard-OFF gate blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`.
- Historical WS07 runner blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`.

## Exact user-returned WS25 evidence

`AUDIT=B12_WS25_MAC_DOCKER_ISOLATION_PREFLIGHT`, `RESULT=WS25_LOCAL_METADATA_PREFLIGHT_COMPLETE`.

| Field | User-returned value |
| --- | --- |
| Mac identity | PASS |
| Original local B12 branch / pinned HEAD / clean worktree | PASS / PASS / CLEAN |
| GitHub remote identity | PASS |
| Docker environment overrides | NONE |
| Docker local socket | PASS |
| Docker Desktop engine | PASS |
| Container count, all/running | 2 / 2 |
| Docker local volumes | 3 |
| Local image entries | 5 |
| Existing containers/volumes changed | NO / NO |
| Backup opened or restored | NO / NO |
| DB connections, queries, writes | 0 / 0 / NO |
| New clone created | NO |
| Production contact | NO |
| Physical clone provenance | NOT ESTABLISHED |
| B12 cutover | OFF |

The user-side command checked source identity before communicating with a local Docker Desktop engine via an allowlisted Unix socket, gathered aggregate Docker metadata only, and did not execute in or connect to any container/database. All counts refer solely to this Mac context at the time of observation, not the production cloud or C137 infrastructure.

**Do not infer:** whether either container is MariaDB; whether volumes contain data; which container belongs to a protected synthetic fixture; whether any volume contains customer data; whether Docker Desktop virtual-network isolation or backup provenance are sufficient for a new disposable test environment. Two running containers and three local volumes must be regarded as protected, not available for repurposing.

## WS26 controlled follow-up (metadata-only)

Using the same pinned local Docker context, inspect only container metadata needed to characterize the two existing containers without exposing names, image references, host/volume mount paths, port numbers, IPs, env vars, process arguments or secrets. Print each container's coarse image family (**heuristic**), running status, privilege flag, published-port presence, mount count and network-mode category (never its actual custom network name). Do **not** enumerate contents of volumes, processes or database structures. Abort on unexpected context, remote engine overrides, status drift or Docker errors. Preserve the original checkout; no re-running of closed source suites.

Classify the existing containers as **PROTECTED_REGARDLESS_OF_CLASS**. Never start/stop/exec/copy into them, reuse existing named or anonymous volumes, create a network/container/volume, import `database.sql`, or run SQL as part of this preflight.

If an apparently unused local Docker Desktop installation exists, that alone is **not** approved isolation for a production-derived SQL dump. A separately reviewed, specifically identified target with independent network, credentials/grants, data provenance, encryption/privacy controls, quiescent writers, backup/teardown and one-use authorization is still mandatory for any future restore or read-only aggregate inventory. `scripts/k2p03_b12_schema_readonly_gate.php --run` remains disallowed.

## B12/D12 gates unchanged

- Local `database.sql` candidate: 619,163 bytes, SHA-256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`, owner-only mode `600`; SQL backup **not uploaded**.
- WS22 predefined file-level DDL markers matched nullable cycle keys, `SET NULL` FKs and no recognized `record_revision` declaration; this is not a physical database proof.
- WS24 offline restore-safety markers recorded; 69 destructive `DROP TABLE` statements, so NEVER import into an existing or protected database.
- Physical B12 actual-data clone not attested; real legacy duplicate counts unknown; `092` migration not authorized; RC01–RC14 real MariaDB certification ZERO; historical B12 run `38233373699` remains FAILED `TWO_STALE_COMMITS`.
- B12 gate remains HARD-OFF; D12 import gates blocked, Stage D unauthorized, 150 VU HOLD, 200 VU prohibited.

**Scope of WS25 commit:** this document only. GitHub remote semantic diff and hard-OFF/protected branch checks required. No application PHP/JS, migrations, database, Mac files, Docker state, production/staging/AWS/cPanel or backups are modified by this commit.
