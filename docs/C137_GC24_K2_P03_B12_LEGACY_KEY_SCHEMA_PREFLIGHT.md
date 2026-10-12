# C137 GC24 K2-P03 B12 — Daily Record nullable-cycle schema preflight

**2026-10-12. SOURCE-ONLY DESIGN REVIEW. NO DATABASE QUERIES, MIGRATION, RUNTIME B12 OR DEPLOYMENT.**

## Pinned evidence

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1` (without invoking an application runtime).
- Isolated branch: `c137-gc24-k2p03-b12-ruminant-delete-unification`. Preflight source HEAD: `9c2ed1553093de6491091a0f5aa2c28ad7c67e1a`.
- Baseline schema snapshot: `database_schema.sql`, blob `e77f287d62c5812a3d55dc23b5b20f5d167db389`. It is source evidence, **not proof that any deployed tenant DB currently has that exact schema**.
- Migration runner: `scripts/run_migrations.php`, blob `ce46170e0a6d8d548d4629e4dc2fb72211a42f02`.
- Migration manifest: 95 SQL migration files through `migrations/091_permission_architecture_alignment.sql`; there is currently **no `092_*.sql`** on this branch.
- Full source archive SHA-256: `13b8587e47f3839081aa24bdc0f69d79709ae57d3eedd55fcaf5c51936f4ce4c`; `config.php` excluded after user-verified no direct Daily Record table references.

## Existing source schema (not inferred deployed state)

| Table | Nullable cycle scope | Existing unique key | Referential action |
|---|---|---|---|
| `layer_daily_records` | `cycle_id INT DEFAULT NULL` | `uniq_layer_cycle_record(cycle_id, record_date)` | production cycle `ON DELETE SET NULL` |
| `broiler_daily_records` | `cycle_id INT DEFAULT NULL` | `uniq_broiler_cycle_record(cycle_id, record_date)` | production cycle `ON DELETE SET NULL` |
| `ruminant_daily_records` | `cycle_id INT DEFAULT NULL` | `uniq_ruminant_cycle_animal(record_date, animal_type, cycle_id)` | production cycle `ON DELETE SET NULL` |

All three tables contain `farm_id` and primary key `id`; the listed unique keys do not include `farm_id`. In MariaDB, unique keys do not exclude repeated combinations when a component is `NULL`. Multiple legacy rows for the same logical farm/date/(animal_type) can therefore coexist under the source schema. The three tables contain **no `record_revision`** column in the pinned schema snapshot.

Do not create a new key until existing data and the meaning of legitimate same-day records across multiple cycles are established. `ON DELETE SET NULL` introduces a further compatibility concern: deleting a cycle may turn previously distinct cycle records into colliding legacy-scope rows. No automatic deduplication, merging, backdating, destructive repair or FK behavior change is authorized.

## Route behavior that new revision/create contract MUST address

- `poultry/layers_daily_record.php` and `poultry/broiler_daily_record.php` test existence by `farm_id, record_date`, adding `cycle_id` only for a selected active cycle; without that selection, their current UPDATE statements are also scoped only by `farm_id, record_date`, **not by an immutable row id**. These statements could match more than one same-day row across different cycles. Do not infer that the UI always selects an unambiguous cycle.
- `ruminant/ruminant_daily_record.php` likewise tests `farm_id, record_date, LOWER(animal_type)` and adds `cycle_id` only when present; the unscoped UPDATE can match more than one row for that date/species. Preserve animal scope on all revised paths.
- All three perform their existence check *before* opening their save transaction. Treat CREATE intent and EDIT intent as distinct operations; a current existence check must not silently promote a stale create request into an edit.
- `management/production_cycles.php` seeds one initial Daily Record during canonical cycle creation. Those inserts need an initial revision default under a future migration; they are not interactive edits.
- `lib/daily_population_continuity.php` updates later record opening population and Layer laying rate. Once revision protection is wired, each true source-row change must increment the same authoritative revision, atomically with continuity.
- `management/farms.php` has an explicitly scoped Platform Owner farm purge via `deleteFarmRows($pdo,$table,$farmId)`. It is a terminal tenant-lifecycle operation, not an ordinary edit; preserve commercial-history retention and tenant authorization.
- `lib/daily_record_revision_authority.php` is a dormant, closed table-map CAS helper. It has no active application callsite in this source scan. It cannot by itself serialize nullable-cycle legacy CREATE requests.

## Suggested duplicate and scope inventories — SQL EXAMPLES ONLY, NOT EXECUTED

For a separately authorized isolated read-only database session after confirming actual deployed schema, inspect at minimum:

```sql
SELECT farm_id, record_date, COUNT(*) AS rows_found
  FROM layer_daily_records WHERE cycle_id IS NULL
 GROUP BY farm_id, record_date HAVING COUNT(*) > 1;

SELECT farm_id, record_date, COUNT(*) AS rows_found
  FROM broiler_daily_records WHERE cycle_id IS NULL
 GROUP BY farm_id, record_date HAVING COUNT(*) > 1;

SELECT farm_id, record_date, LOWER(TRIM(animal_type)) AS species, COUNT(*) AS rows_found
  FROM ruminant_daily_records WHERE cycle_id IS NULL
 GROUP BY farm_id, record_date, LOWER(TRIM(animal_type))
HAVING COUNT(*) > 1;
```

Also inventory cycle-id versus farm-id mismatches using tenant-scoped joins, duplicate cases likely after cycle `SET NULL`, existing unique constraints/indexes via `information_schema`, and actual data type/default of any preexisting revision columns. These examples supply **no counts or data outcomes**; none of these queries was run.

## Migration compatibility and fail-closed release requirements

- Runner `scripts/run_migrations.php` sorts SQL filenames and splits SQL on semicolons; it records completed filenames in `schema_migrations` and ignores MySQL error codes 1060, 1061, 1050, 1091. It wraps statements in PDO transactions, but MariaDB DDL auto-commits: an ALTER sequence across three tables is **not atomic**.
- A future migration must have explicit preflight for actual schema and duplicate logical keys, idempotent postconditions for partial apply/retry, an appropriate `record_revision` integer type/default/overflow policy, and an explicit halt on incompatible data or indexes. Do not rely on the runner's ignored duplicate-column/index errors to certify correctness.
- A `092` name is only a possible future filename, not assigned, created, applied or approved here. No schema change is being made by this review.
- The unique-key strategy must preserve legitimate multiple production cycles per farm/date, protect intended `NULL` legacy identity, preserve correct species normalization, and account for cycle `ON DELETE SET NULL`. Any implementation requiring a functional/generated key must be checked against the exact supported MariaDB release and deployed schema before committing.
- An edit should require an originally client-loaded row id, immutable tenant/cycle/date/species identity and revision. On a conflict, rollback all related feed, stock, population, allocation and Layer egg-sale effects. A newly fetched revision at POST time is not valid client intent.

## Inventory verifier evidence and remaining boundaries

The committed `tests/integration/k2p03_b12_daily_writer_inventory.php` was expanded to classify and fail closed on two named dynamic SQL exceptions:
1. `management/farms.php` — protected Platform Owner tenant purge;
2. `lib/daily_record_revision_authority.php` — dormant shared revision helper.

PHP 8.4.24 lint **PASS**, 301 application PHP files read, seven known direct mutations matched, zero unexpected service callers, named exception audit PASS. The temporary source verification confirmed two negative controls: removing the tenant-purge signature **fails closed** and introducing an unexpected revision caller **fails closed**, followed by a clean baseline rerun. These are source-only facts; unknown dynamic SQL patterns, DB triggers, cron/external writers and runtime authorization have not been certified.

## Next authorization gate

The next stage is **design only** until the actual three-table schemas and legacy duplicates can be verified through a separately authorized isolated, read-only MariaDB session. No database credentials, protected fixtures, cPanel/VPS, Mac SSH keys, prod/staging sites, migrations, B12 concurrency CI or deployment may be accessed from this source review.

B12 original run `38233373699` remains FAILED (`TWO_STALE_COMMITS`, one attempted execution). B01–B11 certified historical totals remain unchanged; D12 15 import gates BLOCKED, Stage D UNAUTHORIZED, 150 VU HOLD, 200 VU PROHIBITED.
