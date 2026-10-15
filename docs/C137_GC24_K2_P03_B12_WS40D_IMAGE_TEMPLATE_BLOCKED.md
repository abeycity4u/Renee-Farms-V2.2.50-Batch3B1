# C137 / GC24 / K2-P03 / B12 — WS40D image Go-template failure isolated

**2026-10-15. Source-only evidence from user's Mac terminal. This does NOT resolve the failing template field or MariaDB authentication.**

## Provenance and protected refs

- Repository `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Parent docs-only branch `c137-gc24-k2p03-b12-ws40-blocked-evidence-source`, SHA `01ff99f4203e0a5608bd05764ca2a1af3d1f5776`.
- New docs-only branch `c137-gc24-k2p03-b12-ws40d-template-blocked-evidence-source`.
- Original B12 source `c137-gc24-k2p03-b12-cas-writer-cutover-source`, SHA `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Production branch `v320-renee-agrisuite-branding`, SHA `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- Literal hard-OFF gate `lib/daily_record_revision_cutover_gate.php` Git blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`.
- Original historical WS07 offline runner `scripts/verify_k2p03_b12_ws07_offline.sh` Git blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`.

## Exact user evidence

```text
#####COPY FROM HERE#####
AUDIT=B12_WS40D_METADATA_FAILURE_ISOLATION
RESULT=BLOCKED
FAILED_STAGE=IMAGE_FULL_TEMPLATE
REASON=COMMAND_NONZERO
DATABASE_CONNECTIONS=0
SQL_STATEMENTS_EXECUTED=0
DOCKER_RESOURCES_CHANGED=NO
BACKUP_OPENED=NO
B12_CUTOVER=OFF
#####COPY TO HERE#####
```

Earlier WS40 had `RESULT=BLOCKED`, `REASON=COMMAND_FAILED` without a stage. WS40D is a targeted diagnostic which identified `IMAGE_FULL_TEMPLATE`: the Docker image-inspect command returned nonzero when asked to render combined Go-template fields:

- `{{.Id}}`
- `{{.Os}}`
- `{{.Architecture}}`
- `{{json .Config.Entrypoint}}`
- `{{json .Config.Cmd}}`
- `{{json .Config.Healthcheck}}`
- `{{json .Config.Volumes}}`

No component-specific error was exposed. Do not assert that one particular field, `json` function, image ID, or MariaDB configuration caused the error. The diagnostic passed earlier guards to reach the named stage, but was blocked before finishing the image/container auth-policy review.

## Narrow next correction

Run **WS40E isolated template-selector compatibility diagnostic** (read-only) against only locally cached `mariadb:11.4` using Docker CLI `image inspect --format` per selector. Return for each fixed public **field name only** `PASS`, `NONZERO` or `TIMEOUT`; suppress all actual selector values and Docker stderr. Pin original B12 source branch/HEAD/clean worktree, its remote, local Docker Desktop Unix context, expected image fingerprint `2ee3c4a72fcac30a`; inventory two original + one test container, four volumes, and network IDs before/after. Do not execute SQL, Docker exec, logs, secret-bearing env or raw inspect output. Distinguish single-field failures from combined-template-only incompatibility, and only then design a supported template adjustment.

WS38 unprivileged `mysql` and WS39 `root` one-off password-free `SELECT 1` attempts both returned `NOT_ESTABLISHED`; client errors deliberately discarded, cause unknown. WS36 `mariadb-admin ping` returned `SERVER_RESPONDS` but cannot establish authentication. WS37 saw expected Unix socket and system directory, and 213.14 GiB guest datadir free, not actual host-backed capacity. Do not retry SQL, read the generated root password from Docker logs, reset credentials or upload/import `~/Downloads/database.sql` in this step.

Remaining B12 protections: HARD-OFF cutover, migration 092 NOT APPROVED, repaired real RC01–RC14 PASS count zero, historical original MariaDB concurrency run FAILED `TWO_STALE_COMMITS`, D12 import gates BLOCKED, Stage D unauthorized, 150 VU HOLD, 200 VU PROHIBITED. Preserve two protected running containers and three pre-existing Docker volumes, plus distinct running WS32 MariaDB and dedicated WS32 volume. No production, staging, AWS/cPanel or protected fixture contact.

This commit changes **only this Markdown evidence file**; perform independent remote semantic diff and check protected references.
