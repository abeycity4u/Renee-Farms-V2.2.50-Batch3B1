# B12 — Read-only actual database evidence gate (not executed)

**Status on 2026-10-12: PREPARED ONLY. No database access, schema alteration, fixture, production/staging contact, GitHub Actions or B12 rerun.**

## Branch and authority

- Repo: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Branch: `c137-gc24-k2p03-b12-legacy-readonly-db-preflight`, forked from `c137-gc24-k2p03-b12-ruminant-delete-unification` at `5cbaf15178cff1cb6426ba024d75d987a97606ee`.
- Read-only query plan: `docs/C137_GC24_K2_P03_B12_READONLY_SCHEMA_INVENTORY.sql`. It is deliberately **outside `migrations/`** and not referenced by an application route, migration runner or CI workflow.
- All real counts remain UNKNOWN. The repository's `database_schema.sql` is a source snapshot, not evidence of the physical state of any MariaDB server.
- The original isolated B12 concurrency run `38233373699` had ONE failed attempt (`TWO_STALE_COMMITS`); prior run approval is consumed.

## Approval and safety preconditions — fail closed

1. Obtain **new explicit approval** identifying a specific disposable isolated MariaDB instance/clone, physical database name, read-only purpose and output destination. Generic "proceed" does not choose an environment or permit access to production, staging, cPanel, AWS Nodes A/B/C, `renee_c137_fixture`, `renee_c137_gc24_restore_z25`, or other protected instances.
2. Independently confirm instance hostname, server identity, database name, application-relevant MariaDB version, absence of access to protected data, and source clone provenance. **Do not infer safety from a database name alone.** If any identity is uncertain, STOP.
3. Use a **SELECT/metadata-only database account**, not production credentials or a user with write rights; do not pass passwords on command lines or print them. Use TLS/SSH only through separately approved routes; none is approved by this document.
4. Inspect source SQL prior to execution. It contains SELECT statements only; no `SET`, `START TRANSACTION`, DDL, DML, `CALL`, triggers, migrations or table mutations. Run only in a known stable isolated clone, because these separate queries are not an atomic consistent snapshot. Abort if concurrent writers are active, tables are missing, query time is excessive or a permission error occurs.
5. Review output before copying it into chat. It is aggregate counts plus schema metadata and must contain no farm/customer identifiers, rows, connection strings or secrets. Do not export raw records.
6. Run only the exact pinned reviewed SQL in the approved environment after verifying Git HEAD and file SHA; never run `scripts/run_migrations.php` for this gate.

## Output fields and interpretation

| Section | Expected evidence | Stop conditions |
|---|---|---|
| 01 database identity | Database name + MariaDB version | Wrong/unapproved instance, incompatible version |
| 02 required tables | All 3 Daily Records plus `production_cycles` | Missing or wrong engine/schema |
| 03 identity columns | id, farm_id, nullable cycle_id, date, animal_type for Ruminant, and presence/absence/type of `record_revision` | Mismatch or unexpected existing revision schema |
| 04 index inventory | Actual unique/secondary indexes and column order | An unreviewed unique key or missing key |
| 05 FK delete rules | Whether cycle removal uses `SET NULL` and other on-delete actions | Unexpected FK policy |
| 06 row and cycle counts | Per-table total rows, null-cycle and linked-cycle rows | Reconciliation errors |
| 07 null-cycle duplicates | Number of duplicate logical legacy key groups/excess rows, by table | **Nonzero: no automatic unique-key migration, no silent deduplication** |
| 08 unscoped update ambiguity | Per-table counts of farm/date(/species) groups with multiple rows irrespective of cycle | **Nonzero: existing unscoped edit path needs safe row-id design; never update all matching rows** |
| 09 tenant/cycle integrity | Per-table orphan and cross-tenant cycle link counts | **Any nonzero: investigate before migration** |
| 10 trigger counts | Table-level trigger counts for 3 sources and production cycles | **Any nonzero: independent trigger-body/manual writer audit first** |
| 11 species shape | Blank normalized Ruminant species and distinct species count | Blank/unexpected values require review |

**Important nuance:** a nonzero all-cycle ambiguity count is not proof of duplicate business records: different production cycles may legitimately have records for the same date. It is evidence that current date-based UPDATE predicates can be ambiguous without an immutable row ID.

**Index behavior:** MariaDB unique keys permit repeated composite tuples if any indexed component is `NULL`. The source schema has `cycle_id NULL` with unique keys for Layer `(cycle_id,record_date)`, Broiler `(cycle_id,record_date)`, Ruminant `(record_date,animal_type,cycle_id)`. A change to a cycle FK or `ON DELETE SET NULL` also needs collision treatment; do not change current FK behavior speculatively.

## Interpretation and later implementation decisions

Only after a correctly authorized isolated inventory may the team decide:

- Whether a three-table guarded `record_revision` addition is compatible with the actual schema, including default/backfill, integer range and partial-DDL recovery.
- Whether a DB uniqueness strategy can safely handle genuine legacy `NULL` rows **without** outlawing legitimate same-day cycles, accidentally merging data or breaking `ON DELETE SET NULL`.
- How the POST action distinguishes CREATE from EDIT; EDIT must supply the original client-loaded `record_id`, scope and revision. Never derive the expected revision from a fresh POST-time read.
- How to CAS-update a single row by `id, farm_id, record_revision`, protect the full existing transaction, and revision-bump continuity-adjusted later records.
- How to apply the shared revision check to **both** Ruminant delete callers and poultry delete endpoints, preserving permissions/CSRF, feed stock reversals, canonical mortality and Layer egg-sale rebuild.
- How a future `092` migration (NOT CREATED) will preflight and recover from MariaDB implicit DDL commits. Do not treat the current generic `scripts/run_migrations.php` transaction wrapper as multi-table atomicity.

## Remaining restrictions

The inventory SQL does **not** prove absence of external writers, grants, stored routines, off-host jobs or dynamically invoked code; additional audit is necessary. The last source-only writer inventory covered 301 PHP application files, identified seven direct mutation paths and two named dynamic paths (tenant purge and dormant revision helper).

This branch makes no application runtime changes, no SQL migration changes and no database connection. B12 and D12 limits remain: B12 FAILED/uncertified; D12 15 import gates BLOCKED; Stage D UNAUTHORIZED; 150 VU HOLD; 200 VU PROHIBITED.
