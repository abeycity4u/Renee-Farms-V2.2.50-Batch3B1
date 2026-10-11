# C137 GC24 K1/D09 — Layer and Broiler Daily Record writer requirements

**Status: OFFLINE REQUIREMENTS ONLY; no V2 fixture events or runtime certification.** Calendar 2025 is a design reporting-year selection; all dated acquisition and physical daily facts still require independent evidence and approval.

## Pinned provenance
- Preparation parent `3d5da697d26813a15e956fe9a727c33c0dd550af` on `c137-gc24-fixture-dryrun-z23`; protected Reports source `322438870263825f9e739a12c4bd478983e67141` on `c137-gc-reports-prefetch-weakmap`.
- D08 JSON Git blob `b54a007e03ae2026c94fbef8dcf7e1a45fe0e9c1`; R1 D08 spec `929fd1cd4fcdba1ab999e3bd26eb566e77b815d59d61644cd7b01c2f4362f768`, R2 D08 spec `3294886470fc943a54bde83836d02fb9f336c2f221b581272cb9a5fbacfc822a`.
- Original frozen 2026 R1/R2 event SHA256 `e31421ee40ce25b16feb698313fcf337e74126a8e63d8c5dadeedf74e30165f2` and `985341ed4315788ee8f3b9d62b327da66d38fc63ea16d406c5c52bc9949d3dfe`; not backdated or altered.

## Shared and page-specific contracts

`poultry/layers_daily_record.php` and `poultry/broiler_daily_record.php` contain their own INSERT/UPDATE and numeric parsing paths, but use shared `daily_population_continuity_apply`, `sync_daily_feed_usage`, and `daily_population_sync_mortality` within the caller's transaction. Both validate CSRF, farm-scoped active same-production Poultry cycles, permitted record date, positive opening and age, mortality <= opening, nonnegative feed/water, and a feed item if consuming feed. V3 cycle opening derives from the canonical population boundary; legacy modes use prior record closing, not a certified 2025 baseline.

Layers also validate eggs <= opening stock and derive two-decimal laying rate and crates as round(eggs/30,2). Layer edits trigger `sales_rebuild_layer_egg_allocations` from the affected date. Broiler writers do not record eggs or run Layer revenue rebuilding. The downstream owner `lib/layer_egg_inventory.php` derives egg pools from dated production and earlier sales; save success does not prove biologically representative rates or egg sales.

`lib/daily_population_boundary.php` builds opening and closing physical balances from acquisition baseline and dated mortality, sale, slaughter and transfer movements. `lib/daily_population_continuity.php` rebases later recorded opening stock while preserving later mortality and operational source facts; it rejects later zero opening, mortality too high and Layer eggs above revised opening. `lib/daily_population_sync.php` alone posts and reverses mortality projection revisions to `lib/production_population_projection.php`. Physical sales/slaughter are separate durable sources, not inferred from daily mortality.

`lib/daily_feed_sync.php` first locks and resolves the saved record as authoritative for farm/cycle, then makes unchanged feed use idempotent or reverses the previous effective USED and posts one new stock movement via the canonical ledger. `api/delete_record.php` reverses the feed and mortality source before deleting the row in the same transaction; for Layer, it rebuilds downstream egg sale allocation. Deletion of an older Daily Record still needs future proof that later opening remains correct. `includes/permission_runtime.php` selects different Add and Edit capabilities based on row existence; the DELETE API additionally gates method, CSRF, rate, View and Delete rights.

`lib/daily_record_workspace_scope.php` distinguishes monthly operational activity from aggregate active-cycle current stock. Virtual rows used to display standalone population movements are not daily feed or production source facts.

## Source-confirmed discrepancy requiring independent follow-up

The certified source `lib/daily_population_continuity.php` defines `daily_population_continuity_preview(..., bool $forUpdate = false)` without a `$sourceId` parameter, yet its body passes `$sourceId` to `daily_population_continuity_canonical_preview`, and `daily_population_continuity_apply` passes `true, $sourceId` as arguments to the preview. The source mismatch is documented, **not patched**. The runtime effect, warnings/exceptions and whether a V3 update falls back to legacy continuity **have not been verified**. Future authorized, narrowly scoped testing must resolve it before relying on historical synthetic Daily Record corrections.

## Pending inputs and future proofs

The D09 JSON records **56 unapproved fields as null**: flock counts, ages, dates, mortality, egg production, feed quantities/items, stock receipts, water/medical details, tenant/actor IDs, reversals, later openings, and historical sales. D09 future tests must verify chronology, canonical biological conservation, historical-rebase invariants, physical sale effects, idempotency, reversals, tenant permissions and transactional rollback. The 2025 selection cannot supply biological quantities. Do not infer them from the old 2026 workload.

A future authorized writer refactor should follow **One fix, fit it all**: central shared validation, orchestration, permissions and ledger policies; thin routes and no duplicate SQL. There is no authorization now to refactor the pages or fix certified source.

The static verifier `scripts/verify_c137_gc24_k1_d09_daily_record_writer.py` checks immutable D08 lineage, 15 PHP text contracts with 104 source markers, unapproved state, in-memory negative tests, and the source-level parameter mismatch. It cannot certify runtime behavior, actually connect to the database, modify a row, or import anything.

```sh
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/verify_c137_gc24_k1_d09_daily_record_writer.py --dry-run --database renee_c137_gc24_http --farm-slug c137-gc24-synthetic --legacy-year 2026 --as-of 2026-10-11 --reporting-year 2025 --certified-source-root /opt/renee-agrisuite-c137-gc
```

Next planned design review after Node A verification: K1/D10, financial allocation revision contracts. **150 VU HOLD and 200 VU PROHIBITED.** No DB, PHP, deployment, fixture events or new load tests.
