# C137 GC24 K2-P03 B12 — tenant-scoped exact reader and legacy cardinality contract

**2026-10-12 | SOURCE-ONLY, DORMANT, NOT DEPLOYED.** No live API edits, database migration, CI workflow, original B12 rerun, staging, production, AWS or cPanel access.

## Pinned project state

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- New isolated branch: `c137-gc24-k2p03-b12-reader-cardinality-source`, forked from verified HEAD `5a14e4ffbe4d13ed94e69c0932124a62d99f2544` (`c137-gc24-k2p03-b12-modal-async-tokens-source`).
- New dormant reader: `lib/daily_record_revision_reader.php` — exact locally linted/tested Git blob `d1599dc2bf87ecb4847a0e2a8fbdbab2561f58c8`.
- New source-only PDO-stub verifier: `tests/integration/k2p03_b12_reader_pure.php` — exact locally linted/tested Git blob `9362b28d7281330e816af2afc790c58f30dc95ea`.
- Current source dependencies were reconstructed at **identical** Git blobs: `lib/daily_record_revision_authority.php` `6f3b6dd323e8449cd9779675f18e2970492e6a8d`; `lib/daily_record_revision_form_tokens.php` `6f88be9e6077e7847425618f3da790ecf61f9619`.
- Offline PHP 8.4.24: `php -l` on new reader and verifier PASS; the PDO-stub pure verifier completed **100 PASS** assertions, `B12_READER_CARDINALITY_PURE=PASS`. No database connection, credential, customer data, route, or external network was involved.

## Two distinct questions — do not conflate

### 1. Authoritative read of one existing editable row

`daily_record_revision_reader_read_exact(PDO $pdo, string $type, int $farmId, mixed $rawRecordId, mixed $rawCycle, string $recordDate, ?string $animalType): array`

- The future authorized API controller must enforce login, **exact tenant farm from `requireCurrentFarmId()` (never submitted by browser)**, applicable module and Daily Record read/edit permissions, required explicit request fields, valid request method and normal response/cache policies **before** invoking this shared helper. No permission is granted by the reader.
- The query uses only the established closed three-table type map, parameterized `d.id = ? AND d.farm_id = ?`, and `LIMIT 2`; it does **not** select an arbitrary first same-date row.
- `LEFT JOIN production_cycles c ON c.id = d.cycle_id AND c.farm_id = d.farm_id` exposes a verified same-farm cycle owner. A non-NULL `cycle_id` without a matching tenant cycle **fails closed**; actual source foreign keys alone do not enforce this.
- Result count must be **exactly one**, and exact ID, source farm, cycle (including genuine NULL), date and normalized species must match the originally requested modal selection.
- The current shared `daily_record_revision_form_fields()` then requires a real durable source row with a valid `record_revision` and produces the six browser-loaded edit tokens. A missing/invalid revision or a display-only population activity placeholder is rejected. The verified cycle JOIN alias is removed from returned operational data.
- Client-visible IDs/revisions are intent **only**; the future save/delete handler must repeat tenant/permission/CSRF checks, lock the same row by ID and farm, compare immutable identity + **the original browser revision**, and use the shared CAS authority before downstream effects. Never obtain a fresh revision on POST and pretend it came from the browser.

### 2. Exact-scope existence/cardinality probe

`daily_record_revision_reader_check_scope(PDO $pdo, string $type, int $farmId, mixed $rawCycle, string $recordDate, ?string $animalType): array`

- Parameters unambiguously select *one scope*: farm/date/**explicit cycle**, or farm/date/**explicit NULL cycle**, additionally normalized Ruminant species. No omitted-cycle “all cycles” lookup is allowed by the future HTTP route. `cycle_id=0`, negative, padded and ambiguous numeric strings are invalid. The internal nullable `null`/empty string means an intentionally selected **legacy NULL cycle**, **not** missing request data.
- SQL adds `d.cycle_id IS NULL` or `d.cycle_id = ?`, not a dynamic omitted WHERE clause; Ruminant adds `LOWER(TRIM(d.animal_type)) = ?`; all use `LIMIT 2`.
- **0 rows** gives `{status: absent, record_id: null}`: *not* a lock, uniqueness reservation, or authorization to INSERT. **1 row** gives `{status: unique, record_id: n}`: only an immutable ID for a subsequent **separately authorized exact row read**, not edit tokens or permission. **2 rows** throw `DailyRecordRevisionConflict`: never pick the first duplicate.
- Scope cardinality cannot solve concurrent CREATE or the `ON DELETE SET NULL` collision. Those need schema/data policy and separately authorized MariaDB tests.

## Evidence and architecture boundaries

The existing `api/get_record.php` performs date-based `fetch()`, optionally constrains by cycle, and may arbitrarily select a row when multiple legacy records match. `api/check_record.php` and `api/check_ruminant_record.php` also collapse cardinality to a Boolean. **None of those live endpoints were changed by this step.** Current Layer/Broiler/Ruminant save routes still do pre-transaction existence checks and may update by date rather than immutable ID.

The new reader deliberately imports `daily_record_revision_form_tokens.php`, which requires future `record_revision` columns on all three tables. **DO NOT CALL IT from existing routes before those columns and transactional CAS writers are activated together.** This is source-only code, not a production regression fix yet.

The pure verifier uses overridden PDO/PDOStatement test doubles, not MariaDB. It checks SQL plans, farm-bound parameters, legacy NULL separation, normalized Ruminant species, absent/unique/ambiguous responses, exact token generation, ID/date/cycle/farm/species mismatches, missing revision, same-farm parent-cycle validation, malformed selections, and no arbitrary table names. This **does not** prove a real database executes every query correctly or preserve concurrency under multiple workers.

Previously authorized disposable MariaDB schema R2 run `38301126240` PASSED, confirming synthetic NULL-cycle duplicate identities, cross-tenant cycle references and `ON DELETE SET NULL` collisions. It did not inspect actual tenant rows or solve B12 concurrent lost writes. Original B12 failed run `38233373699` remains **FAILED / TWO_STALE_COMMITS**; its single authorization remains consumed.

## Exact next work and gates

1. **Source-only:** design a thin, permission-preserving API adapter/response envelope that requires explicit `record_id`, `cycle_id` (empty only when intentionally legacy NULL), date, species for Ruminant, and server-resolved farm identity. Choose errors: 400 invalid scope, 403 lack of authority, 404 missing/inaccessible row, 409 ambiguity/stale identity, 503 missing schema; never leak other tenants' source existence.
2. **Before activation:** complete the real-data legacy duplicate/cycle-tenant audit under a separately approved read-only environment, settle cycle-delete/nullable uniqueness/CREATE serialization, and separately authorize + verify a guarded three-table `record_revision` migration. No speculative migration `092` was created.
3. **Source integration:** wire all three modal scripts to the shared async tickets, add six hidden inputs, provide exact row and expected revisions on edit/delete actions, and update save/delete routes to use the shared CAS and preserve feed, inventory, continuity, mortality, Layer egg-sale allocations, and tenant/role/CSRF protections. Continuity writes must increment revisions on all affected downstream durable rows.
4. **Verification:** narrow source/permission tests, followed by separately authorized disposable MariaDB and browser E2E evidence. **No original failed B12 rerun** without explicit new approval. After any GitHub source push, remote semantic diff and exact HEAD review.

**Holds remain:** D12 15 import gates BLOCKED, Stage D UNAUTHORIZED, 150 VU HOLD, 200 VU PROHIBITED. Production, staging, protected fixtures, AWS/cPanel untouched.

**One fix, fit it all:** one closed shared reader, one shared form-token authority, one shared modal state controller; keep application routes thin, with no copied finance/stock/population policy.
