# GC24CG K1/D05 — Poultry feed receipts, consumed-stock costing and Reports eligibility

**Status: OFFLINE REQUIREMENTS REVIEW ONLY. D05 source-contract review is not permission to enter financial or stock data.** Selected independent V2 design reporting window: **2025-01-01 through 2025-12-31**. Dates for acquisition, feed use, receipts and cross-period opening stock remain unresolved. Layer and Broiler feed quantities, item costs, opening inventory value, and actual eligible USED-row counts remain unknown.

## Immutable source and provenance

- Certified Reports branch: `c137-gc-reports-prefetch-weakmap`, pinned HEAD `322438870263825f9e739a12c4bd478983e67141`. **Never edit**.
- Preparation parent: `c137-gc24-fixture-dryrun-z23` HEAD `f33721bf64927461fee123730d0bb9b62cacf99c`.
- Prior D04 requirements Git blob `5be072cf0383641f437e865aa508649e3112358c`. Prior D04 R1/R2 specification digests `6a7427e6ca34ecec707318ccb644e8f92acd648130502fe276e50fc1f07ccf69`, `173e9136ea3ce342ac220fcf4786181dca77d1472b528b16c91595d8d9816761`.
- Original 2026 R1/R2 event SHA-256 `e31421ee40ce25b16feb698313fcf337e74126a8e63d8c5dadeedf74e30165f2`, `985341ed4315788ee8f3b9d62b327da66d38fc63ea16d406c5c52bc9949d3dfe`; frozen.
- Earlier candidate 1,800/3,600 stock-use events are **not verified, posted, costed, effective or Reports eligible**. Original Broiler Daily Record feed-use workload was zero; never invent it to force a load target.

## Observed certified PHP contracts (16 source files)

**A. Feed Daily Records and exactly one USED authority**

`poultry/layers_daily_record.php` and `poultry/broiler_daily_record.php` call `sync_daily_feed_usage` in their caller-owned transaction. `lib/daily_feed_sync.php` first locks/resolves its authoritative Daily Record and requires the actual saved cycle to match the requested cycle. It checks the existing effective `used` movement for the same source type and record ID. If item/quantity/cycle/date are unchanged, it is idempotent. If they change, it reverses the old ledger movement then posts the replacement through `stock_apply_movement`. No extra manual `used_stock` must duplicate the same feed fact. Separate source IDs for `daily_layer_record` and `daily_broiler_record` prevent auto-increment collision. Business dates and stock-item units must match canonical item and cycle attribution rather than made-up daily usage.

**B. Priced RECEIVED entries and financial roles**

`lib/stock_service.php::stock_apply_movement` is the shared ledger writer. It requires an existing transaction for atomic public reference assignment, validates positive quantity, checks farm/category/role and canonical attribution, and locks the item. It forbids used quantity greater than available stock. A receipt may own an exact `incomingTotalCost` and an up-to-four-decimal unit-cost snapshot; a normal receipt from `inventory.php` requires actual unit cost. The source inserts a transaction with immutable business date, effective type, quantity, stock balance and `unit_cost` / `total_cost` snapshots. The receipt cost is a financial event, not an additional `farm_expenses` row for the same purchase. Financial type `feed` comes from the posted transaction's classification; descriptive category names and later item-category changes must not relabel historical stock economics. Any category/item writer extraction is still a separately authorized step.

**C. Historical valuation, reversal and adjustments**

`lib/stock_costing.php::stock_weighted_cost_replay` processes effective (not reversed) stock history ordered by transaction business date, creation time and ID. It incorporates exact receipt totals and append-only signed receipt cost adjustments, and subtracts consumed cost snapshots. Replay can be incomplete due to uncosted, negative-quantity or negative-value history; `stock_historical_unit_cost` then falls back to a known current valuation. **A non-null used total cost alone does not certify the correctness of historical cost for a newly designed synthetic fixture**: the new scenario must show priced receipt provenance and a complete chronological replay, without relying on an unsupported fallback.

`stock_reverse_transaction` appends compensating movement while retaining original audit history and coordinates closure of any live consumed-stock allocation within the same transaction. Effective reporting uses `is_reversed=0 AND reversal_of_id IS NULL`; it excludes reversed originals and reversal rows while preserving replacements.

**D. Reports source eligibility versus allocation**

`lib/stock_consumption_economics.php::stock_consumption_economics_source_rows` selects **effective** `used` rows within a date window, with `total_cost IS NOT NULL`, and a posted Feed classification or an operating classification (`medication_vaccine`, `supplement`, `consumables`). A counted candidate `used_stock` is not eligible merely because it exists; date, effective reversal state, category/type, tenant and cost all matter. Actual eligible counts must be demonstrated from legitimate posted rows, not the old planning counts.

The annual prefetch default `2048` is a bounded memory optimization; when the annual reader yields more than that many rows, it returns an empty seeded-month list and **falls back to ordinary monthly DB reads**. It does not truncate economic totals and **it is not a minimum-volume requirement**. Any representative fixture needs to separately prove the real qualifying rows and any prefetch/overflow behavior relevant to the intended test.

`lib/stock_consumption_allocation_service.php` enforces an **additional** eligibility boundary for manually splitting broader pooled cost: effective `USED`, non-direct-cycle source, operating classification, positive `total_cost`, and authoritative Daily Record source attribution when applicable. A directly attributed cycle movement is not a pooled parent. `stock_consumption_allocation_persistence.php` retains revision history; source reversals must close allocations without erasing provenance. The basic Reports source reader and the shared allocation parent check are related but **not interchangeable**.

**E. Purchase and profitability separation**

`lib/inventory_financial.php` surfaces receipt purchases in cash/spending intelligence without inserting duplicate expenses. Cost reaches farm Profitability through consumption/other source-specific cost rules. Reconcile all dated receipt cash totals, effective USED costs and explicit pooled allocations; do not treat stocking a warehouse as immediately consuming its value or count a receipt and its usage twice.

## Still blocked — required future evidence

Item identities, feed categories, unit conversions, dated stock receipts, actual unit and total costs, Daily Record feed quantities, reversal history, historical valuation and physical quantity conservation are all absent. It is not yet established how an opening 2025 inventory balance should be sourced; independent pre-year 2024 history would require a separate date-policy decision. Nor are prices or specific stock-usage costs authorized.

The proposed 2025 V2 fixture must eventually show each feed USE associated with its exact canonical Daily Record; each item must have financially classified, documented priced receipts and sufficient stock before use. Any pooled USED cost must have appropriate current revisions and authoritative source attribution. Reports eligibility must be checked from real persisted, effective, correctly classified, costed `used` rows, with no unsupported override of ordinary outgoing unit cost.

## Offline verifier and certification boundary

`docs/C137_GC24_K1_D05_FEED_STOCK_COST_REQUIREMENTS.json` holds **35 unknown fields as null**, pinned D04 provenance, 16 source-file marker lists, observed source contracts, future verification gates, and all write/import/load restrictions. `scripts/verify_c137_gc24_k1_d05_feed_costing.py` checks original D04 Git blob and SHA-256 specs, verifies source markers as text, rejects fabricated quantities/prices and bypass of load/write restrictions. It creates no events or stock rows.

```sh
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/verify_c137_gc24_k1_d05_feed_costing.py --dry-run --database renee_c137_gc24_http --farm-slug c137-gc24-synthetic --legacy-year 2026 --as-of 2026-10-11 --reporting-year 2025 --certified-source-root /opt/renee-agrisuite-c137-gc
```

Expected `GC24CG_K1_D05_OFFLINE_VERIFIER=PASS`, unchanged legacy digests, `K1_D05_FEED_COST_APPROVAL=NOT_GRANTED`, zero database connections or writes, no PHP executions or new load tests. Only a verified Node A result closes the D05 **requirements-review checkpoint**, not biological or financial evidence approval.

Next: D06 tenant/actor and authorization scope review, design-only. **150 VU HOLD, 200 VU PROHIBITED.**
