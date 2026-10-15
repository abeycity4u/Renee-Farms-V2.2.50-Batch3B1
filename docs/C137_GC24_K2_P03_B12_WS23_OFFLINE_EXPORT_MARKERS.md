# C137 / GC24 / K2-P03 / B12 — WS23 offline export-marker inspection

**Date: 2026-10-15. Classification: user-returned local, hash-pinned, regex/heuristic inspection. Not backup-consistency, physical-schema, production-origin, clone or CAS certification.**

## Source identity and protected invariants

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- WS23 parent source: `c137-gc24-k2p03-b12-ws22-allowlisted-ddl-evidence-source` at `d731e4a5781c1243e2f4dd3cc426a0dc5e373392`.
- WS23 branch: `c137-gc24-k2p03-b12-ws23-export-marker-evidence-source`.
- Original B12 branch: `c137-gc24-k2p03-b12-cas-writer-cutover-source` at `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Production branch: `v320-renee-agrisuite-branding` at `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- B12 hard-OFF gate blob: `6e88c913ad5e5b371a9d0c92ce08870214ac1268`.
- WS07 immutable offline verifier blob: `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`.

## WS23 user-returned evidence

`AUDIT=B12_WS23_DUMP_EXPORT_MARKERS`, `RESULT=WS23_EXPORT_MARKERS_INSPECTED`, `FILE_IDENTITY=PASS`, `SHA256=PASS`, `FILE_PERMISSIONS=600`.

The inspected file was local `~/Downloads/database.sql`, 619,163 bytes, SHA-256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`; the command was limited to this pinned file.

| Heuristic marker | Report |
| --- | --- |
| phpMyAdmin header | NO |
| MySQL / MariaDB dump header | YES |
| Comment `-- Database:` in header | NO |
| Explicit `START TRANSACTION;` | NO |
| Explicit `COMMIT;` | NO |
| Dump completion marker | YES |
| Top-level `CREATE DATABASE` | NO |
| Top-level `USE database` | NO |
| Top-level `DROP DATABASE` | NO |
| Foreign-key-check directive | YES |
| `LOCK TABLES` statement | YES |
| `DELIMITER` directive | NO |

No raw SQL or customer data displayed; no SQL statements executed, database connections, production contact or file mutation. B12 cutover OFF.

## Interpretation and critical distinctions

- The dump has a recognizable MySQL/MariaDB format header and ending marker. **Neither proves snapshot consistency or completeness.**
- The absence of top-level `CREATE/USE/DROP DATABASE` markers lowers the *observed* database-switching risk in this specific heuristic, but **does not make the dump safe to execute**; `DROP TABLE`, `CREATE TABLE`, importer `LOCK TABLES` and MySQL versioned executable comments may still alter whatever active schema an import targets.
- The `LOCK TABLES` text is plausibly emitted for **restore-time** behavior and is not evidence that the **source** backup process held a consistent lock/snapshot. Absence of `START TRANSACTION/COMMIT` in the exported file says nothing conclusive about a dump client's server-side `--single-transaction` option.
- `FOREIGN_KEY_CHECKS` may be saved/disabled/reinstated through versioned `/*! ... */` statements. This report did not verify that directives are paired or that the dump can restore with valid FK constraints.
- No one has attested the exact database of origin, exporter command/options, DB version, off-host chain of custody or physical clone isolation. Production-related data may be present; never share the SQL file to ChatGPT, GitHub, CI, staging or an unspecified database.

## Next narrow WS24 preflight (offline only)

Perform **one bounded, non-executing, Mac-local inspection** of the hash-pinned owner-only file with predefined output fields only, to count (not display): `DROP TABLE IF EXISTS`, `CREATE TABLE`, `LOCK TABLES`, `UNLOCK TABLES`, `DISABLE/ENABLE KEYS` markers, `FOREIGN_KEY_CHECKS` saved/restore directives, recognized end marker, and sensitive constructs (`CREATE USER`, `GRANT`, `SET GLOBAL`, routines/triggers/events/`DEFINER`). Record apparent exporter server-version *presence only*, not host/database/username or other identifiers. No data rows or raw SQL may be printed. Fail closed on file identity, mode or checksum mismatch; report `HEURISTIC_ONLY`.

Only after these conditions and independently established backup origin/physical isolation may the team propose a target-specific **separately authorized** restore plan. A real production-derived dump must not be imported to the C137 HTTP fixture, restored Node B scratch instance, existing Docker synthetic DB, protected environment or default cPanel production DB.

## Unchanged gates

`WS23_FILE_MARKERS=INSPECTED`; `DUMP_CONSISTENCY=NOT_CERTIFIED`; `BACKUP_ORIGIN=UNVERIFIED`; `PHYSICAL_CLONE=NOT_ESTABLISHED`; `ACTUAL_TENANT_DATA_COUNTS=UNKNOWN`; `MIGRATION_092=NOT_APPROVED`; `REPAIRED_RC01_RC14_PASSES=0`; `B12_CUTOVER=HARD_OFF`. D12: 15 import gates BLOCKED, Stage D UNAUTHORIZED, 150 VU HOLD, 200 VU PROHIBITED.

No application, SQL, PHP, CI, migration, production, staging, AWS, Docker or customer backup file was changed as part of WS23 documentation.
