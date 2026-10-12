# C137 GC24 K2-P03 — B12 explicit Daily Record CREATE/EDIT intent (SOURCE ONLY)

**2026-10-12. Narrow isolated source contract; no application route wiring, migration, CI execution, protected database access or deployment.**

## Proven baseline and new source checkpoint

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Branch: `c137-gc24-k2p03-b12-edit-intent-source-contract`, forked from successfully documented R2 synthetic schema branch at `bf14633aa8446fdf053ea4593aedd8010e40f73b`.
- Dormant shared helper: `lib/daily_record_revision_authority.php`. Original blob `905f967d7c72af30a78de4338b94cd2d66551a67`; patched, locally tested exact blob `6f3b6dd323e8449cd9779675f18e2970492e6a8d`.
- Pure verifier: `tests/integration/k2p03_b12_shared_revision_pure.php`. Original blob `23c947030943434bafdb64545b10cf97f9a289a6`; patched, locally tested exact blob `2b42dba0e0bd5c290409ca45afc7066600a7ec27`.
- Isolated PHP 8.4.24: lint for both changed PHP files PASS; pure verifier **97 PASS checks** and final `K2_P03_B12_SHARED_REVISION_PURE=PASS`. No test connects to database, loads config.php, or runs application routes. This is NOT a PHP 8.2, runtime multi-process, or schema certification.
- Already completed separate disposable MariaDB schema R2: run `38301126240`, attempt 1, **SUCCESS**, proof of nullable-cycle duplicate keys and tenant-cycle FK risk on synthetic rows. Previous original B12 lost-update concurrency run `38233373699` remains **FAILED/TWO_STALE_COMMITS**.

## Implemented contract (pure, currently unwired)

New `daily_record_revision_parse_record_id(mixed $raw): int` accepts a strictly positive native integer or a **canonical decimal string** between 1 and PHP_INT_MAX. It rejects missing/negative/zero/ambiguous IDs, padded leading zeros, fractional/exponent values, booleans, arrays, floats, and overflow values. Invalid tokens fail with `DailyRecordRevisionConflict` before any database operation.

New `daily_record_revision_parse_write_intent(mixed $mode, mixed $recordId, mixed $expectedRevision): array`:

| Mode | Valid input | Parsed contract |
|---|---|---|
| `create` | exact lowercase mode, with both edit tokens absent (null or empty HTML string) | `mode=create`, `record_id=null`, `expected_revision=null` |
| `edit` | exact lowercase mode, canonical positive row ID and existing `daily_record_revision_parse_expected` original browser revision | `mode=edit`, positive `record_id`, positive `expected_revision` |
| missing, unknown, mixed, stale | any contradictory/malformed mode, row token or revision | `DailyRecordRevisionConflict` |

This contract prevents a future route from guessing edit intent by re-querying the date at POST time; **it does not currently protect any actual POST**, and it does not enforce row access or MariaDB uniqueness on CREATE. Existing source route behavior remains unchanged.

## Cross-module architecture reviewed

| Existing writer | Current source fact | Required later integration |
|---|---|---|
| `poultry/layers_daily_record.php` | Tests existence by farm/date, optionally cycle; can update by farm/date without immutable row ID; check happens before transaction | Form must carry `create` vs `edit`; EDIT must pass browser-loaded `id` and `record_revision`, SELECT-for-update and CAS a **single row**, then continuity/feed/mortality/Layer egg allocations in the same transaction |
| `poultry/broiler_daily_record.php` | Same pretransaction date existence check and date-scoped UPDATE risk | Shared intent and row-scoped CAS; preserve broiler feed, mortality and population logic |
| `ruminant/ruminant_daily_record.php` | Checks by farm/date/LOWER(animal_type), optionally cycle; unscoped UPDATE may hit multiple same-date/species rows | Preserve species and exact cycle identity, shared form intent, immutable row-id CAS, feed and canonical mortality logic |
| `management/production_cycles.php` | Seeds Daily Record at cycle creation | CREATE only, initialized revision from later schema default; no forged browser edit intent |
| `lib/daily_population_continuity.php` | Updates later rows by immutable ID, tenant, cycle and old opening stock, with Layer laying-rate recalculation | When revision columns exist, increment each actually changed later row's authoritative revision atomically; preserve row-count guard and order; stale forms for those rows must conflict |
| `api/delete_record.php` | Poultry DELETE reverses feed and mortality, rebuilds Layer egg sales, but does not presently lock/compare an originally loaded revision before reversals | Move source-first lock and shared revision check ahead of stock/population updates; preserve current authorization, CSRF, transaction and Layer allocation rebuild; use immutable row ID |
| `lib/ruminant_daily_record_deletion.php` | Shared delegated/privileged Ruminant deletion locks source, reverses feed/mortality, deletes; no browser-loaded revision yet | Add caller-supplied revision and immutable identity to shared service after schema/form wiring, without duplicating or bypassing delete policy |
| `management/farms.php` | Protected Platform Owner entire-tenant purge; dynamically deletes Daily Records | Keep separately classified terminal tenant-lifecycle operation and commercial-history protections, not ordinary editor CAS |
| `lib/daily_record_revision_authority.php` | Dormant three-table closed map, strictly parsed revision, source locking and CAS logic | Existing contract remains dormant until real schema + UI + all writer revisions are present |

Read-only display-only population activity rows (`daily_population_continuity_activity_records`) are NOT durable Daily Record rows. They cannot be edited/deleted by manufacturing an ID or optimistic revision token.

## Unresolved MariaDB and lifecycle design blockers

The R2 synthetic MariaDB test confirmed all three tables permit two equal farm/date/(species) rows with `cycle_id IS NULL`, and a plain source-cycle FK permits a row for farm B to point to farm A's cycle. Deleting a cycle `ON DELETE SET NULL` can create additional legacy NULL-cycle collisions. The current schema has no `record_revision` columns.

**Do not add a speculative unconditional unique key or composite FK.** The current cycle FK uses `ON DELETE SET NULL`; a naive composite `FOREIGN KEY (farm_id, cycle_id) ... ON DELETE SET NULL` attempts to NULL the nonnullable `farm_id`. A candidate nullable-cycle uniqueness solution requires real data duplicate preflight, valid same-day multi-cycle preservation and explicit cycle-delete collision policy. A candidate species-normalized uniqueness key requires examination of legacy casing/whitespace and custom animal-type semantics.

Revision columns, numeric range, guard ordering, CREATE serialization, client-load tokens, side-effect rollback and migrator partial-DDL recovery must be resolved before activation. A future `092` migration remains **uncreated and unauthorized**; no real legacy tenant duplicate counts have been established.

## Precisely next steps (not executed)

1. Read-only source design: define the canonical row-token/intent presentation on each Daily Record form and delete action; distinguish stable operational rows from synthesized population-only rows. Review farm/cycle/date/species immutable scope, tenant grants and permission/CSRF semantics.
2. Source-only draft the unified row-scoped writer/delete transaction integration, explicitly preserving every downstream feed, inventory, mortality, continuity and egg-allocation effect; do not wire live routes until schema + migration + browser token design are complete.
3. Separately approved isolated *read-only actual legacy database* audit is required before any uniqueness key or migration is authorized. R2 synthetic findings alone cannot certify real deployed data.
4. Any later disposable MariaDB multi-process concurrency execution requires separate explicit new one-use authorization and a pinned branch, CI workflow and synthetic-only fixture, **never** a rerun of the failed original B12 run.
5. For every source push: exact branch/HEAD guards, focused verifiers, remote GitHub semantic diff, no broad QA reruns without regression evidence; use central services, thin routes, never duplicate business policy.

**Release limits remain:** Original B12 concurrency **FAILED**; D12 15 import gates BLOCKED; Stage D UNAUTHORIZED; 150 virtual users HOLD; 200 virtual users PROHIBITED; production/staging/cPanel/AWS protected.
