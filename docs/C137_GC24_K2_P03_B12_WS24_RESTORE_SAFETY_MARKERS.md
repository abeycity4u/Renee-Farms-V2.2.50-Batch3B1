# C137 / GC24 / K2-P03 / B12 — WS24 offline restore-safety marker audit

**2026-10-15 — documentation/source-only, based on user-returned Mac inspection. NOT a verified production backup, a restore, physical MariaDB clone, or B12 concurrency certification.**

## Immutable context and guards

- Repo: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- WS24 forked from `c137-gc24-k2p03-b12-ws23-export-marker-evidence-source`, `0d63bec3bfa1f82ac3e9088fc8274f140e385d61`.
- WS24 branch: `c137-gc24-k2p03-b12-ws24-restore-safety-evidence-source`.
- Protected original B12 source branch: `c137-gc24-k2p03-b12-cas-writer-cutover-source` at `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Protected production source: `v320-renee-agrisuite-branding` at `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- Literal B12 hard-OFF gate `lib/daily_record_revision_cutover_gate.php`, blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`.
- Historical WS07 runner `scripts/verify_k2p03_b12_ws07_offline.sh`, blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`.
- Local backup candidate, not uploaded: `~/Downloads/database.sql`, size `619163` bytes, SHA256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`, mode `600`. Its actual production origin remains independently unverified.

## User-supplied WS24 outcome (not server-executed)

`AUDIT=B12_WS24_RESTORE_SAFETY_PREFLIGHT`, `RESULT=WS24_RESTORE_PREFLIGHT_RECORDED`; pinned file identity / SHA / owner-only permission PASS.

| Heuristic count | Result |
| --- | ---: |
| DROP TABLE statements | 69 |
| CREATE TABLE statements | 69 |
| LOCK TABLES statements | 69 |
| UNLOCK TABLES statements | 69 |
| INSERT statements | 65 |
| REPLACE / UPDATE / DELETE / TRUNCATE statements | 0 each |
| ALTER TABLE DISABLE KEYS markers | 69 |
| ALTER TABLE ENABLE KEYS markers | 69 |

- `DUMP_TRAILER_PRESENT=YES`, `SERVER_VERSION_COMMENT_PRESENT=YES`, `HOST_DATABASE_HEADER_PRESENT=YES`.
- `FK_CHECKS_SAVED=YES`, `FK_CHECKS_DISABLED_EXPLICITLY=YES`, `FK_CHECKS_RESTORED=YES`.
- `LOCK_UNLOCK_COUNTS_MATCH=YES`, `DISABLE_ENABLE_KEY_COUNTS_MATCH=YES`, `FOREIGN_KEY_SAVE_RESTORE_MATCH=YES`.
- `CREATE_USER_PRESENT=NO`, `GRANT_PRESENT=NO`, `SET_GLOBAL_PRESENT=NO`, `ROUTINE_TRIGGER_EVENT_MARKER=NO`, `DEFINER_MARKER=NO`, `LOAD_DATA_MARKER=NO`, `OUTPUT_FILE_MARKER=NO` (only under predefined regex detection).
- `INSPECTION=HEURISTIC_ONLY`; `RESTORE_EXECUTED=NO`, `RAW_SQL_DISPLAYED=NO`, `CUSTOMER_ROWS_DISPLAYED=NO`, `DATABASE_CONNECTIONS=0`, `SQL_STATEMENTS_EXECUTED=0`, `BACKUP_FILES_CHANGED=NO`, `PRODUCTION_CONTACT=NO`, `B12_CUTOVER=OFF`.

## Restore-safety interpretation

1. The matching counts indicate conventional-looking dump statements. They **do not** prove that each lock/unlock/key pairing applies to the corresponding table, that the full dump is syntactically valid, that all constraints can be restored, or that customer rows have a consistent snapshot. Exactly 65 INSERT **statements** were detected, not 65 records.
2. **69 DROP TABLE statements are destructive.** Never execute this dump on any existing/protected database, production, staging, C137 historical fixture/restore, synthetic B12 container or unspecified host. MySQL executable comments may contain effective directives even when the simple regex does not classify them.
3. The saved/disabled/restored FK check markers are heuristics, **not** verification that referential integrity holds after a future import, nor that live production used a consistent backup mechanism.
4. File-level pre-revision DDL evidence from WS22 indicates no recognized `record_revision` columns for Layer/Broiler/Ruminant, with nullable cycle FK identity; this is not physical MariaDB `information_schema` evidence. No customer duplicate, orphan, cross-tenant or trigger counts have been read.
5. Filename, hash, header database marker and user-reported origin do not independently establish chain of custody, original database ID, exporter server version/consistency flags, or safe handling of potential customer data. Do not upload the SQL file, commit it, or disclose its contents/secrets.

## Next controlled step — WS25 metadata-only environment discovery

Prepare a nonmutating, bounded **Mac-local Docker CLI metadata preflight**, with no `docker run/start/exec/inspect container secrets`, `docker cp`, Compose, database access, backup import, SSH or network pull. Before using the Docker CLI, ensure environment/context cannot route to an SSH/TCP remote engine. The Mac source checkout `$HOME/renee-b12-synthetic-work/source` must remain pinned, on protected B12 branch `c137-gc24-k2p03-b12-cas-writer-cutover-source` at `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`, clean if available. If a source guard fails, STOP without contacting Docker. Emit only coarse, allowlisted metadata and no container names, volume paths, images, credentials, private IP addresses or raw inspect JSON. Existing synthetic database containers are protected: discovery must not create, repurpose or mutate them.

An existing engine context that is local does **not** prove a new disposable clone. Never infer clone provenance from container name, `docker info`, exit status or empty candidate counts.

After host/container identity is separately attested, actual clone creation/restoration or customer aggregate read requires a distinct, explicitly approved and controlled environment/backup provenance, access grant, privacy and teardown plan; general "start"/"proceed" only starts this metadata-only investigation. `scripts/k2p03_b12_schema_readonly_gate.php --run` is still disallowed. Repaired RC01–RC14 are still unrun, 092 revision migration not authorized, B12 literal hard-OFF, D12 15 import gates BLOCKED, Stage D UNAUTHORIZED, 150 VU HOLD, 200 VU PROHIBITED.

**WS24 closeout:** user-returned offline marker result recorded. This commit must touch only this documentation file, with remote semantic diff and protected guards re-verified.
