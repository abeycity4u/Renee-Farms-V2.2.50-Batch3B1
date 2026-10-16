# C137 / GC24 / B12 — WS52-LL: single authorized GH read-only physical preflight BLOCKED safely

**Evidence date:** 2026-10-16. **Outcome:** ONE_USE_GUARD_NOT_VERIFIED; physical Docker contact NOT REACHED; no runtime certification and NO rerun authorization.

## Source and execution identities

- Repository `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Approved source parent WS52-LJ: `c137-gc24-k2p03-b12-ws52lj-unix-transport-offline-pass-evidence` HEAD `7f809364dd038487c9fff594d1d6c06fe19663ad`.
- One-use physical preflight branch `c137-gc24-k2p03-b12-ws52lk-gh-readonly-docker-preflight`.
- One-use read-only workflow created at `4ae17235654f4203fefde4d50650aee221ac4fbf`; `.github/workflows/c137-b12-ws52lk-gh-readonly-docker-one-use.yml` Git blob `d5ee15799e2b2b81075f27f85b4db947b091c3a2`.
- One-use approval document `docs/C137_GC24_K2_P03_B12_WS52LK_READONLY_PREFLIGHT_ONE_USE_APPROVAL.txt` Git blob `9c73c36e9a10f03aa346409cebcef239959925c7`; exact triggering approval commit `fbae8202d807ab6cdde9e359e7ebbd20638d4d96`, sole ADDED file versus prepared workflow source.
- GitHub Actions run `38857466870`, direct URL: `https://github.com/abeycity4u/Renee-Farms-V2.2.50-Batch3B1/actions/runs/38857466870`.
- GitHub recorded workflow name `C137 B12 WS52-LK single-use read-only Docker runner preflight`, event `push`, run number `1`, attempt `1`, head SHA `fbae8202d807ab6cdde9e359e7ebbd20638d4d96`, conclusion `failure`, status `completed`.
- Workflow job `116832045343`: setup completed success; `Validate one-use approval event; inspect runner and Docker read-only` failed; cleanup completed success.

## Verbatim sanitized RESULT lines extracted from job 116832045343

~~~text
#####COPY FROM HERE#####
AUDIT=B12_WS52LK_GITHUB_READONLY_PHYSICAL_PREFLIGHT
RESULT=BLOCKED
REASON=ONE_USE_OR_DOCKER_READONLY_PREFLIGHT_FAILED
ONE_USE_EVENT_GUARD=NOT_VERIFIED
GITHUB_HOSTED_RUNNER_CLAIM=NOT_VERIFIED
DOCKER_LOCAL_UNIX_SOCKET=NOT_VERIFIED
DOCKER_READONLY_COMMANDS=NOT_RUN
ENGINE_METADATA_READ=NO
ENGINE_ID_PRESENT=NOT_VERIFIED
DOCKER_CONTAINERS_EXIST=NOT_VERIFIED
DOCKER_VOLUMES_EXIST=NOT_VERIFIED
UNEXPECTED_NETWORKS_EXIST=NOT_VERIFIED
TCP_3306_LISTENER=NOT_VERIFIED
RUNNER_PROVENANCE=SELF_REPORTED_GITHUB_HOSTED_ONLY
ENGINE_PROVENANCE=DAEMON_SELF_REPORTED_ONLY
INDEPENDENT_ATTESTOR=NOT_ESTABLISHED
EGRESS_ISOLATION=NOT_ATTESTED
DOCKER_BROKER_PRIVILEGE_SEPARATION=NOT_CERTIFIED
IMAGE_DOWNLOADS=NO
REGISTRY_REQUESTS=0
CONTAINERS_CREATED=0
CONTAINERS_REMOVED=0
VOLUMES_CREATED=0
VOLUMES_REMOVED=0
DATABASE_CONNECTIONS=0
SQL_EXECUTED=NO
CUSTOMER_DATA_ACCESSED=NO
RC01_WORKERS_EXECUTED=NO
RUNTIME_RC01_APPROVAL=NOT_GRANTED
REPAIRED_RC01_RC14_CERTIFIED_PASSES=0
MIGRATION_092=NOT_AUTHORIZED
B12_CUTOVER=HARD_OFF
#####COPY TO HERE#####
~~~

## Findings and limits

- A real GitHub-hosted job was scheduled, but the Python workflow printed its BLOCKED evidence before setting `ONE_USE_EVENT_GUARD=PASS`. The workflow calls `guard()` before Docker socket checks or any subprocess, so **no Docker command or socket inspection occurred**. The evidence is of a rejected physical-preflight attempt, NOT physical isolation PASS.
- Independent GitHub compare from workflow commit `4ae17235654f4203fefde4d50650aee221ac4fbf` to approval commit `fbae8202d807ab6cdde9e359e7ebbd20638d4d96` reported exactly one new approval file, one commit, ahead 1/behind 0. GitHub reported expected commit message, run number 1 and attempt 1. These GitHub API facts do not establish which Python event/environment assertion failed.
- The single fallback exception handler intentionally emits a generic reason, so **precise guard failure is not established**; do not speculate or retroactively claim missing Docker, runner, network or container provenance. The one-use approval was consumed by this unsuccessful attempt.
- Existing original B12 work and prior offline WS52-LI 32/32 PASS remain unchanged. No production, staging, customer backup, historical-clone, migration, schema, SQL or RC01 activity occurred.

## Next permitted steps

- Source-only diagnosis: review Python `guard()` and GitHub `push` event/runner environment assumptions against available GitHub API facts. For any proposed replacement workflow, use privacy-preserving staged guard codes instead of an opaque catch-all; demonstrate all safety gates before attempting Docker access. This is preparation, not an approved second run.
- **Do not rerun run 38857466870**, reuse or edit the existing one-use approval file, bypass it, or dispatch a new physical workflow without a NEW explicit user authorization for its exact scope and fresh run.
- Any future safe read-only attempt must remain on an isolated ephemeral GitHub-hosted runner, exclude image pulls, container/volume creation/deletion, SQL, backups, RC01–RC14 and production/staging operations, and return a sanitized source/host/engine observation. Independent machine identity and egress isolation are not established by runner or daemon self-report.
- Original protected B12 branch `c137-gc24-k2p03-b12-cas-writer-cutover-source` HEAD `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`, production `v320-renee-agrisuite-branding` HEAD `10041da3a3c4e4a7b8101f8183c243d056c7b128` unchanged.
- `RUNTIME_RC01_APPROVAL=NOT_GRANTED`, `REPAIRED_RC01_RC14_CERTIFIED_PASSES=0`, `MIGRATION_092=NOT_AUTHORIZED`, `B12_CUTOVER=HARD_OFF`.
