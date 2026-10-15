# C137 / GC24 / K2-P03 / B12 — WS51 read-only preflight blocked by reference mismatch

**2026-10-15 — WS51 local audit BLOCKED, no backup read or database/Docker mutation. Root cause independently narrowed by read-only remote GitHub source inspection. This is NOT a restoration approval.**

## User-returned WS51 evidence

```text
#####COPY FROM HERE#####
AUDIT=B12_WS51_RESTORE_READONLY_PREFLIGHT
RESULT=BLOCKED
FAILED_STAGE=WS07_RUNNER_BLOB
REASON=COMMAND_FAILED
BACKUP_OPENED=NO
DATABASE_CONNECTIONS=0
SQL_EXECUTED=0
DOCKER_EXEC_EXECUTED=NO
DOCKER_RESOURCES_CHANGED=NO
BACKUP_RESTORED=NO
B12_CUTOVER=OFF
#####COPY TO HERE#####
```

**What passed:** original Mac source branch, HEAD, clean tree, origin remote and literal hard-OFF cutover gate verifications occur before the failed stage, and the failure was specifically at `git rev-parse HEAD:scripts/verify_k2p03_b12_ws07_offline.sh`. The script suppressed Git stderr and wrapped nonzero as `COMMAND_FAILED`. Therefore the returned log itself did not prove file corruption or source modification. The audit exited before backup open, local Docker inventory/storage preflight, any server connection or SQL.

## Independently verified remote GitHub cause

Repository `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.

- Original protected B12 checkout and branch `c137-gc24-k2p03-b12-cas-writer-cutover-source` HEAD `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`. GitHub Contents lookup for `scripts/verify_k2p03_b12_ws07_offline.sh` **at that exact commit** returned `NOT_FOUND / 404`. The original branch's literal `lib/daily_record_revision_cutover_gate.php` blob *did* resolve to expected `6e88c913ad5e5b371a9d0c92ce08870214ac1268`.
- WS50 latest evidence-only branch `c137-gc24-k2p03-b12-ws50-restoration-readiness-design-source`, HEAD `4b7930f1c967be7135422dad2b7e204e309c0a7a`, **does** contain `scripts/verify_k2p03_b12_ws07_offline.sh`, Git blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`.
- The local WS51 command used the **wrong ref** for the historical runner. Because the protected checkout is intentionally fixed to original B12 HEAD, requiring that later-history file via `HEAD:path` is invalid. Do not change original HEAD, copy/cherry-pick runner to source, weaken any protected branch hash, or treat file absence as corrupted backup.

The user-provided backup is `~/Downloads/database.sql`, reported production-derived, pinned 619163 bytes/SHA256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259` from earlier WS43–WS46. **WS51 did not open it**. WS50's complete design-only restoration plan remains in `docs/C137_GC24_K2_P03_B12_WS50_RESTORATION_READINESS_DESIGN.md` at parent HEAD. Previous WS49 synthetic test passed two query attempts on new disposable container/volume and confirmed full teardown; that does not authorize historical production-data import.

## WS51R corrective handling under existing WS51 read-only authorization

Propose a narrowly corrected repeat of the **unfinished** WS51 preflight, not repetition of completed WS42–WS49 operations:

1. Guard exact original Mac local source branch/HEAD/clean tree/Git remote and original B12 gate blob `6e88...`.
2. Validate the **expected absence** of historical WS07 runner in original B12 tree without stderr/raw path output (e.g. `git ls-tree -r --name-only HEAD -- scripts/verify_k2p03_b12_ws07_offline.sh` yielding empty). Reference the independently verified **evidence branch/commit** for the runner's expected blob; do not demand a local copy of a later-ref file in the original checkout. If an independently verifiable remote later-ref runner check is required, use the GitHub connector rather than modifying checkout or contacting a production host.
3. Complete the remaining authorized **read-only** checks: existing Docker Desktop context, engine/image fingerprint, WS32 isolated running container, protected original inventory, WS49 resource absence, current host/Docker backing storage metadata, original backup SHA256/mode/owner (without SQL content printing), and before/after inventories.
4. Output only sanitized marker-delimited evidence, with `SOURCE_WS07_ORIGINAL_PATH=ABSENT_EXPECTED`, `WS07_EVIDENCE_BRANCH_BLOB=PASS_REMOTE_GITHUB_PREVERIFIED` and accurate `SQL=0`, `DB_CONNECTIONS=0`, `DOCKER_EXEC=NO`, `BACKUP_RESTORED=NO`, `B12_CUTOVER=OFF`. Never falsely imply the script locally checked remote GitHub when it did not.
5. Fail closed on unanticipated source/ref, Docker, file or backup drift; do not run migrations, obtain secrets/logs or authorize import.

No new user authorization needed merely to fix the WS51 read-only preflight command's reference error; this does **not** authorize any stateful step. Only one controlled corrected attempt, and return a `BLOCKED` result for unexpected guards rather than proceeding.

## Immutable controls

- Original protected source SHA `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`; production `v320-renee-agrisuite-branding` SHA `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- B12 literal CAS writer gate **HARD-OFF**, migration 092 NOT AUTHORIZED, corrected real-MariaDB RC01–RC14 passes 0, prior original real concurrency failure `TWO_STALE_COMMITS`.
- D12 15 import gates BLOCKED, Stage D UNAUTHORIZED, 150 VU HOLD, 200 VU PROHIBITED.
- No backup copy/upload/import, SQL, DB queries or customer-data publication. No WS32 or protected Docker mutations, no production/staging/AWS/cPanel contact.
- Evidence Git procedure: this file only on one successor docs-only branch from WS50; independently confirm remote one added Markdown, ahead 1/behind 0, original/production branch SHAs, B12 gate and historical WS07 runner blob unchanged.

**WS51 = BLOCKED, corrected WS51R still pending; restoration and provisioning not authorized.**
