# C137 GC24 Z27J — shared-writer extraction contract review

**Status: SHARED_WRITER_EXTRACTION_REVIEW_BLOCKED.** This is a guarded,
read-only source inspection and an extraction design; no reusable writers
have been extracted, no canonical PHP has executed, no schema/database writes
or changes to certified Reports source have occurred. No R1/R2/V2 events
were generated, imported, altered or rebaselined.

## Provenance

- Certified source branch: `c137-gc-reports-prefetch-weakmap`, pinned commit
  `322438870263825f9e739a12c4bd478983e67141`.
- Preparation branch parent: `c137-gc24-fixture-dryrun-z23` HEAD
  `be79b4de52fe7bed1b9aaf7089967ddf5fad3d3a`.
- Z27I R1/R2 dependency digests:
  `b5a42222cbe00e127987de1f747e39c06e51b71edee299f290611ef1f204a770`;
  `c9463c26fcbaf434a66c6a5f85cf2309e1fad7962cd6aab41597cb1cea5c80e8`.
- Original R1/R2 event digests: certified as before and preserved.
- As-of 2026-10-11; 2025 is a **candidate only, not a selected year**.
- Verified-by-source assertions below concern the pinned Git checkout, not
  live PHP runtime or a completed implementation.

## 1 — Inventory category creation boundary

Source: `inventory.php`, `if (isset($_POST['add_category']))`:
- Owner/administrator authorization `$canManageInventory`;
- nonempty category name and enabled farm type;
- canonical `inventory_financial_classification_is_valid`;
- canonical `inventory_category_role_contract_errors`;
- page-local `INSERT INTO inventory_categories` farm-scoped SQL.
- The route currently handles a category insert directly; no reusable
  category-creation shared writer is established by this evidence.

**Future extraction candidate**: one `inventory_category_create` service
with explicit PDO, farm and actor/permission context, validated category
fields, farm type, financial classification and role. The caller must keep
CSRF, session redirect and UX concerns separate. Define an explicit ownership
for the outer transaction; do not assume the current direct category insert
provides item + receipt atomicity.

## 2 — Inventory item creation is actually duplicated

Source:
- `inventory.php` -> `add_item`;
- `includes/inventory_permission_hardening.php` ->
  `inventory_permission_handle_delegated_add_item`.

Both contain separate `INSERT INTO stock_items` flows and a conditional
`stock_apply_movement` initial receipt inside a transaction. They use
`inventory_category_item_contract_errors`,
`inventory_category_role_item_contract_errors`,
`inventory_normalize_default_production_type`, category farm scoping,
valid item unit, positive/nonnegative stock and unit-cost validation, and
slaughter-output initial-stock restrictions. The specialist/delegated branch
also constrains farm-module roles and shared Both item creation.

**Future extraction candidate**: one `inventory_item_create` service
owning the category lookup/lock as appropriate, canonical validity, the
insert with zero initial `current_stock`, and optional priced initial receipt
posted through `stock_apply_movement` in the SAME caller-controlled
transaction. It must not invent stock by updating `current_stock` directly.
Both owner and delegated routes must call the same service; roles/tenant
authority must not be weakened to allow fixture/test actors broad access.

Critical regression tests: farm and role mismatch; forged Both/module type;
non-feed category with feed usage; slaughter-output category initial stock;
negative/non-numeric price and quantity; future receipt; duplicate-item
exception; `stock_apply_movement` reference/transaction rollback and
historical purchase cost. Tests should use shared contract verifiers rather
than duplicating page-specific assertions.

## 3 — Sales creation is an orchestration boundary, not SQL only

Source: `management/sales_records.php` -> `add_sale`.

The existing route validates:
- `$canAddSales` and sales-only tenant restrictions;
- general inventory or livestock/slaughter source selection;
- quantity, sale unit, price, payment, customer receivable requirements;
- farm and cycle ownership, canonical attribution scope;
- explicit aggregate population-effect mode and ruminant/slaughter variants.

Inside one sale transaction it currently creates the row through
`record_reference_persistence_insert_new`; may call
`general_sale_inventory_sync`, `slaughter_output_sale_sync`,
`ruminant_sale_save_animal_allocations`,
`ruminant_sale_apply_exit_outcomes`; then calls
`sale_population_effect_sync`,
`sales_refresh_automatic_allocation`, and for Layer egg sales
`sales_rebuild_layer_egg_allocations`. It creates a receivable ledger entry
for eligible partial/credit sales and commits/rolls back accordingly.

**Future extraction candidate**: `sales_create` orchestration service
owning the entire safe business transaction: ID/reference, validation,
product/source type, stock/slaughter/ruminant effects, sale physical population,
allocation lifecycle, and receivables. Keep the route thin, but retain its
sales-only policy and general-farm isolation. **Do not extract just the
`INSERT INTO sales_records` and then bulk-import missing effects later.**

Layer eggs must use dated unsold-egg Daily Record allocation authority;
manual Layer egg 60/40 allocation remains forbidden. Broiler financial Head
sales do NOT imply exits; require explicit cycle/headcount and population
conservation. Protected manual revenue revisions also require proper
persistence lifecycle, not a direct table insert.

## 4 — Daily Record creation and update orchestration

Sources: `poultry/layers_daily_record.php` and
`poultry/broiler_daily_record.php`. Both validate cycle/date, age,
opening stock, mortality, feed item and farm scoped continuity; then start
one transaction for Daily Record insert/update,
`daily_population_continuity_apply`,
`sync_daily_feed_usage`, and `daily_population_sync_mortality`.
**Layer** additionally invokes `sales_rebuild_layer_egg_allocations`
before commit. Shared helpers own projections/stock/economics; pages
currently still own the complete form-driven persistence orchestration.

**Future extraction candidate**: poultry Daily Record shared creation/update
service parameterized by production type, delegating to existing helper
contracts. Preserve the Layer-specific egg count limit, historical rebuild,
and Broiler-specific absence of egg-product fields. CSRF/UI access checks,
cycle/date validation and permission policy must remain unchanged. Reversals,
edits and deletions must still preserve feed source IDs and lock ordering.
No fixture importer may directly insert Daily Record rows or independently
post a second inventory `used` movement for the same feed.

## 5 — Controlled implementation stages after this review

- **Stage J1:** decide reporting period and biologically defensible layer and
  broiler fixtures; source review does not resolve these decisions.
- **Stage J2:** implement shared category/item creation writer on an
  explicitly authorized implementation branch, with one owner + delegated
  end-to-end regression verifier, accounting classification and ledger costing.
- **Stage J3:** extract the unified sale transaction preserving every
  existing source variant and post-sale side effect. Regression cover:
  sales-only, general inventory COGS, slaughter, ruminant, poultry sale exits,
  Layer automatic allocations, receivable ledger, reference concurrency and
  manual allocation lifecycle.
- **Stage J4:** extract Daily Record create/update orchestration preserving
  continuity, inventory/feed, mortality and Layer egg allocation rebuild.
- **Stage J5:** separately version and verify any *new* V2 event streams,
  records, authorization and actual Reports eligible-cost-row counts.
- **Stage J6:** only with explicit permission and certified backup / isolation
  evidence, consider database insertion. **Not authorized today.**

Avoid broad rewrites, duplicated SQL/role/accounting policy, modifying
certified source or retesting previously closed QA absent regression evidence.
After any push, review GitHub semantic diff and Node A guard evidence.

## Offline verifier

`scripts/c137_gc24_z27j_shared_writer_review.py` reads only 16 pinned
PHP files under `/opt/renee-agrisuite-c137-gc`. Its probes verify category,
item, sale and Daily Record source contract markers, duplicated item writer
SQL shape, source ordering and single-authority feed. It consumes the
previous Z27I design digest for lineage but performs no runtime business
validation.

`scripts/verify_c137_gc24_z27j_shared_writer_review.py` tests in-memory
tampering, wrong farm/database/source/date flags and original stream
immutability. Probe success means **source contract detected**, NOT that the
extraction is done or the new fixture is safe to import.

Safe commands (run only after certified source HEAD and worktree guards):

```sh
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/verify_c137_gc24_z27j_shared_writer_review.py
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/c137_gc24_z27j_shared_writer_review.py --dry-run --scenario R1 --database renee_c137_gc24_http --farm-slug c137-gc24-synthetic --year 2026 --as-of 2026-10-11 --candidate-year 2025 --certified-source-root /opt/renee-agrisuite-c137-gc
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/c137_gc24_z27j_shared_writer_review.py --dry-run --scenario R2 --database renee_c137_gc24_http --farm-slug c137-gc24-synthetic --year 2026 --as-of 2026-10-11 --candidate-year 2025 --certified-source-root /opt/renee-agrisuite-c137-gc
```

Expected `GC24CG_Z27J_OFFLINE_VERIFIER=PASS`, two
`READINESS=SHARED_WRITER_EXTRACTION_REVIEW_BLOCKED`,
`DATABASE_WRITES=0`, `SOURCE_WRITES=0`, `NEW_EVENT_COUNT=0`.
Z27J remains pending until independently verified on Node A.

**Operational hold remains unchanged:** 150 VU HOLD, 200 VU PROHIBITED;
no DB import, migration, protected fixture cleanup, source deploy or load test.
