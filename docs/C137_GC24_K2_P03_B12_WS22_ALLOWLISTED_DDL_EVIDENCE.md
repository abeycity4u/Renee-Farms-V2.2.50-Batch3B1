# C137 / GC24 / K2-P03 / B12 — WS22 allowlisted backup DDL inspection

**Date: 2026-10-15. Classification: USER-RETURNED, MAC-LOCAL, HASH-PINNED FILE-LEVEL DDL MARKERS. Not a deployed schema attestation, production-origin verification, backup restoration or B12 certification.**

## Identity and source boundary

- WS21 parent: `c137-gc24-k2p03-b12-ws21-offline-dump-structure-evidence`, commit `eecba270e0673bafdd5761a009fe0eb8d39fa6e9`.
- WS22 branch: `c137-gc24-k2p03-b12-ws22-allowlisted-ddl-evidence-source`.
- Original B12 branch `c137-gc24-k2p03-b12-cas-writer-cutover-source`: `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Protected production branch `v320-renee-agrisuite-branding`: `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- Literal B12 hard-OFF gate blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`; historical WS07 runner blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`.
- Candidate Mac-local file `~/Downloads/database.sql`: user-returned size `619163` bytes, SHA-256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`, regular file, mode `600` after WS20. Do NOT upload or commit this SQL file.

## Exact WS22 user-returned findings

The user returned `AUDIT=B12_WS22_ALLOWLISTED_DDL`, `RESULT=WS22_PRE_REVISION_DDL_MARKERS_MATCH` with `FILE_IDENTITY=PASS`, `EXPECTED_SHA256=PASS`, and `OWNER_ONLY_PERMISSIONS=PASS`.

| Predefined allowlisted DDL assertion | Layer | Broiler | Ruminant | Production cycles |
| --- | --- | --- | --- | --- |
| CREATE TABLE count | 1 | 1 | 1 | 1 |
| InnoDB marker | YES | YES | YES | YES |
| `id` column detected | YES | YES | YES | YES |
| `farm_id` column detected | YES | YES | YES | YES |
| `cycle_id` column detected | YES | YES | YES | Not checked |
| `cycle_id` nullable under script heuristic | YES | YES | YES | Not checked |
| `record_revision` column detected | NO | NO | NO | Not checked |
| Exact predefined legacy unique-key name/columns | YES | YES | YES | Not checked |
| Predefined `cycle_id` FK `ON DELETE SET NULL` | YES | YES | YES | Not checked |

The SQL was neither printed nor executed: `CUSTOMER_ROWS_DISPLAYED=NO`, `RAW_SQL_DISPLAYED=NO`, `SQL_STATEMENTS_EXECUTED=0`, `DATABASE_CONNECTIONS=0`, `BACKUP_FILES_CHANGED=NO`, `PRODUCTION_CONTACT=NO`, `B12_CUTOVER=OFF`.

## Interpretation without overclaiming

These matches strengthen **file-level** evidence for the older schema contract: 3 Daily Record tables with nullable cycle identities, legacy nullable-composite unique indexes and `ON DELETE SET NULL` cycle FKs, but no recognized `record_revision` declarations. This agrees with the separately pinned source `database_schema.sql` shape but does not independently establish that this SQL file originated from production. It is not an actual MariaDB `information_schema` inventory, a dump parse/restore certificate or a consistent-snapshot guarantee.

The WS22 inspector extracts bounded `CREATE TABLE` blocks using line/regex heuristics. Passing means its **expected markers matched**; it does not certify SQL grammar completeness, server version, existence of triggers/external writers, dump authenticity, actual tenant duplicate/null-cycle counts, live grants, all constraints or dependency behavior. A `NO` on `record_revision` is absence within this regex's recognized DDL block, not a query of any current running database.

Consequences for future B12 planning:
1. Do not ship or execute a 092 migration based on this file alone. A revision DDL candidate remains a commented-out `.sql.txt` draft.
2. Do not treat `(cycle_id,record_date)` (and Ruminant species variant) uniqueness as protecting NULL-cycle duplicates; `ON DELETE SET NULL` complicates any replacement uniqueness policy.
3. Source-only next work may prepare a metadata-only isolated target/provenance checklist, a schema/backup compatibility **review**, and a separately approved environment-specific physical clone plan. Do not import the file to an existing C137 fixture, staging, production, CI or unspecified Docker instance.
4. Read-only actual data/cycle-integrity aggregate queries remain blocked until the physical target, data chain of custody, credentials/grants, zero active writers, and explicit host/DB/operation-specific authorization are established.

## Decision

`WS22_FILE_DDL_MATCH=PASS`; `ACTUAL_PRODUCTION_ORIGIN=UNVERIFIED`; `PHYSICAL_CLONE=NOT_ESTABLISHED`; `BACKUP_CONSISTENCY=NOT_CERTIFIED`; `LEGACY_ROW_COUNTS=UNKNOWN`; `REPAIRED_RC01_RC14=0`; `MIGRATION_092=NOT_APPROVED`; `B12_CUTOVER=HARD_OFF`. D12 15 import gates BLOCKED; Stage D UNAUTHORIZED; 150 VU HOLD; 200 VU PROHIBITED.

WS22 documentation commits must not modify application PHP, SQL, migrations, Docker, credentials, production/staging, C137 restored/test DB or the existing hard-OFF gate. A subsequent independent physical clone approval cannot be implied from "proceed" or from file-level DDL PASS.
