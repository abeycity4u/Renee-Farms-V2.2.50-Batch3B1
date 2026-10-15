# C137 / GC24 / K2-P03 / B12 — WS21 local SQL dump structure evidence

**Date: 2026-10-15. Source/documentation only; user-reported offline inspection, NOT validated physical production schema, approved clone, or B12 certification.**

## Source identity and protected gates

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- WS21 parent: `c137-gc24-k2p03-b12-ws20-backup-fingerprint-source`, `dc2cd0679dbb3649dc1246c28b8ceed3c62b5947`.
- WS21 branch: `c137-gc24-k2p03-b12-ws21-offline-dump-structure-evidence`.
- Protected production branch: `v320-renee-agrisuite-branding`, `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- Original B12 branch: `c137-gc24-k2p03-b12-cas-writer-cutover-source`, `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Cutover hard-OFF gate blob: `6e88c913ad5e5b371a9d0c92ce08870214ac1268`.
- WS07 immutable offline runner blob: `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`.

## User-returned Mac results

`AUDIT=B12_WS21_OFFLINE_SQL_STRUCTURE`, `RESULT=WS21_STRUCTURE_INSPECTION_COMPLETE`.

The previously owner-only secured, regular Mac-local `~/Downloads/database.sql` is pinned to:
- Length: 619,163 bytes.
- SHA-256: `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`.
- Mode `600` after WS20 hardening, user-reported ownership, SHA and unchanged bytes.
- Dump format marker: YES; total `CREATE TABLE` statements detected: 69; `ALTER TABLE` statements: 0; `INSERT` statements: 65; `REPLACE` statements: 0.
- Detected `CREATE TABLE` for `layer_daily_records`, `broiler_daily_records`, `ruminant_daily_records`, and `production_cycles`: YES for all four.
- Detected one `INSERT` statement for each of those four table names (not one row).
- `cycle_id` DDL mention: YES for all three Daily Record tables.
- `record_revision` DDL mention: NO for all three Daily Record tables.
- Result marks `STRUCTURAL_INSPECTION=HEURISTIC_ONLY`; no customer SQL displayed, zero database connections, zero SQL statements executed, no production contact or backup mutation.

The command's simple multiline DDL aggregation and regex cannot conclusively assert column absence, nullable column properties, unique key shapes, FK referential action, InnoDB engine, row counts or entire dump restorability. DDL *mention* is not the same as an attested column definition. `65 INSERT` counts SQL statements, **not** inserted rows. The presence of 69 tables does not uniquely identify a database: historical source schema and separate C137 fixtures also contained 69 tables. SHA validates the local bytes; it is not independent evidence of production origin, snapshot consistency, database identity or safe restore. No application database was read.

## WS21 conclusion and narrow WS22 direction

`PINNED_FILE_METADATA=PASS`; `FOUR_REQUIRED_CREATE_TABLE_DECLARATIONS=DETECTED`; `REVISION_COLUMN_DETECTED=NO_BY_HEURISTIC`; `LIVE_PRODUCTION_ORIGIN=UNVERIFIED`; `PHYSICAL_CLONE=NOT_ESTABLISHED`; `ACTUAL_CUSTOMER_COUNTS=UNKNOWN`; `REPAIRED_RC01_RC14_PASS_COUNT=0`; `B12_CUTOVER=HARD_OFF`.

WS22 may do a **separate Mac-local, owner-only, hash-pinned, offline, allowlisted DDL-only structural inspection** of exactly the four table definitions. It must not print dump contents, filenames with credentials, user/tenant data, unbounded identifiers or row values. It should report only boolean/categorical expected properties for `cycle_id`, `record_revision`, expected unique index column sequences, `ON DELETE SET NULL` FKs and InnoDB engine, plus fail-closed/inconclusive parse states. All findings remain file-level, not deployed-schema certification.

Before any physical clone import or read-only SQL `--run`, a separate explicitly identified and independently isolated environment, approved backup provenance, privacy/retention and database grants are mandatory. No migration `092` or B12 real RC01–RC14 has been authorized. Do not activate B12, repurpose C137 databases, contact production/staging/AWS/cPanel, or start D12 capacity testing (15 import gates blocked; Stage D unauthorized; 150 VU HOLD; 200 VU prohibited).
