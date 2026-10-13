# B12 guarded schema and seven-writer cutover — decision record

**Source-only as of 2026-10-13. No SQL execution, migration or deployed change.**

Repo: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`. Base: `3baf4c4d99afc5eec771fdf17f1aeaff4a0c657d`, branch `c137-gc24-k2p03-b12-schema-preflight-source`.

## Source-verified baseline
The pinned `database_schema.sql` blob `e77f287d62c5812a3d55dc23b5b20f5d167db389` has InnoDB Layer/Broiler/Ruminant Daily Records with nullable cycle_id, no record_revision, existing unique keys on cycle/date (plus species for Ruminant), and `ON DELETE SET NULL` cycle FKs. NULL values do not satisfy uniqueness across nullable-key tuples. Cycle FK references only `production_cycles.id`, not `(farm_id,id)`. Actual database schema may differ: no protected DB inspected.

## Read-only diagnostic artifact
`docs/C137_GC24_K2_P03_B12_READONLY_SCHEMA_PREFLIGHT.sql.txt` enumerates:
1. Revision/cycle columns metadata.
2. Duplicated legacy NULL-cycle identities.
3. Full identities normalized by cycle `COALESCE(cycle_id,0)` and Ruminant normalized species.
4. Cross-farm/orphan cycle references.
5. Conservative potential collision groups on cycle deletion; these are **risk groups**, not an assertion every group will collide on every single deletion.

The SQL has no mutations and was **not executed**. Only a separately authorized isolated/read-only database session may run it; copy-back must exclude client/customer records where not necessary and use aggregate counts by default.

## Candidate migration gate — NOT YET APPROVED
- Keep existing primary keys and referencing application behavior until historical duplicates and cross-farm references are adjudicated; never auto-deduplicate customer daily production records.
- Add `record_revision BIGINT UNSIGNED NOT NULL DEFAULT 1` across all three tables **only after** actual schema compatibility/rollback and transaction behavior are proven in a disposable clone, and readers/writers activate together.
- Canonical uniqueness candidate: tenant + date + a **normalized not-null cycle identity** such as generated `COALESCE(cycle_id,0)`, additionally normalized species for Ruminant. The actual DDL and index expression support must be tested on the deployed MariaDB version; do not assume functional indexes are supported. DO NOT implement until all preflight collisions are resolved.
- `ON DELETE SET NULL` plus enforced legacy NULL uniqueness can reject cycle deletions. Resolve with a separately approved domain rule (restrict cycle deletion, retain/tombstone referenced cycles, or migrate identity), **not by dropping the constraint silently**.
- Run a read-only cross-tenant FK audit and separately evaluate a composite FK `(farm_id,cycle_id)` only after schema/index and lifecycle constraints are understood.

## Required atomic writer cutover
Seven source entry points: `poultry/layers_daily_record.php`, `poultry/broiler_daily_record.php`, `ruminant/ruminant_daily_record.php`, `api/delete_record.php`, `lib/ruminant_daily_record_deletion.php`, `lib/daily_population_continuity.php`, `management/production_cycles.php`.

Each editor:
1. Enforce canonical auth, tenant role/module, operation-level permission, CSRF and business validation.
2. Require explicit browser CREATE or EDIT mode. EDIT must carry original immutable `record_id` plus `expected_revision` from an earlier authorized exact row read; never substitute a newly fetched revision at POST time.
3. For CREATE inside a caller-owned transaction: obtain the same tenant lock/order as every other participating creator, validate same-farm parent cycle and scope cardinality, then INSERT and update downstream effects **before commit**. Source-only guard `daily_record_revision_create_lock_scope()` exists but does not protect current writers; it is NOT yet proof against concurrent CREATE.
4. For EDIT: shared `daily_record_revision_lock_and_compare()` then `daily_record_revision_write()` CAS update by `id/farm_id/record_revision`, preserving original business-field validation, feed inventory, mortality projection, continuity and pooled Layer egg-sale allocations inside the same transaction.
5. For DELETE: same original revision lock and compare **before** any feed/mortality reversal; canonical CAS DELETE; rollback all side effects if conflict.
6. Continuity adjustments of any downstream durable daily rows must also increment revisions atomically; no stale modal may overwrite new biological state.
7. Account/session JSON auth transport and browser async token state must be activated with the coordinated deployment, not piecemeal.

## What has NOT been certified
No revision migration, baseline tenant data audit, policy resolution for cycle deletion, web API live wiring, seven-writer CAS conversion, MariaDB multiworker test, or D12 gate verification. Original B12 run `38233373699` remains FAILED `TWO_STALE_COMMITS`; synthetic R2 schema evidence `38301126240` PASSED separately. D12 fifteen import gates remain BLOCKED, Stage D UNAUTHORIZED, 150VU HOLD, 200VU PROHIBITED.

**Next prerequisite:** authorized actual-schema/duplicate evidence, followed by one coordinated schema + writer cutover in an isolated integration environment. Do not describe static/SQL-text artifacts as completed fixes, and do not execute migration or capacity load without separate operational authorization.
