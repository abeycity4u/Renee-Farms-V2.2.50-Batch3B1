# GC24CG K1/D07 — Shared Inventory category, item and ledger writer architecture

**Checkpoint status: OFFLINE REQUIREMENTS REVIEW only, awaiting independent Node A verification.** Inventory writer implementation, tenant/actor authorization, item quantities, prices, and 2025 physical stock remain **unapproved**. Do not execute application PHP, open any database connection or create fixture records.

## Source and specification continuity

- Certified Reports branch: `c137-gc-reports-prefetch-weakmap`, pinned HEAD `322438870263825f9e739a12c4bd478983e67141`; do not modify.
- Preparation branch `c137-gc24-fixture-dryrun-z23`, pre-D07 parent `18dc2476b5675f442a23dc3d2326c9e0d1378619`.
- Frozen D06 requirements JSON Git blob: `c50ba051dbb6e6da88b0de5ffa4d51ab252d5c08`. R1/R2 D06 spec digests `fdebc8ef7e2ef694721a62ff4cd15f416ab855c777ccc33c59e320daa2c2e940` and `ca29f1602575204c060f13904868fce14001760d6fc61f46e7420b08afd7958c`.
- Original 2026 event SHA-256 digests R1 `e31421ee40ce25b16feb698313fcf337e74126a8e63d8c5dadeedf74e30165f2` and R2 `985341ed4315788ee8f3b9d62b327da66d38fc63ea16d406c5c52bc9949d3dfe`, unchanged.
- New selected reporting period **2025-01-01 to 2025-12-31** remains a design choice. 2024 opening stock receipt and earlier-history policy is **not authorized**. Future import database name `renee_c137_gc24_http` and slug `c137-gc24-synthetic` are references only, not proof of actual tenant/actor records.

## Existing Inventory architecture: two separate item insertion paths

1. `inventory.php` handles **privileged (Platform Owner/Farm Admin)** Category creation/administration, Add Item, item archive/restore, deletion and stock updates. Category insert is `INSERT INTO inventory_categories` gated by module/category/financial-role contracts. The Add Item handler loads the selected category constrained by `farm_id`, validates its Financial Type, usage and Inventory Role with centralized policy functions, parses item name, unit, initial stock, minimum stock, cost and received business date, then begins a transaction.
2. `includes/inventory_permission_hardening.php::inventory_permission_handle_delegated_add_item` intercepts `inventory.php` Add Item **for delegated managers** in the shared bootstrap, after Inventory View, Add Item permission and CSRF checks, and before the legacy privileged Add Item handler. Its own code **duplicates most of the input parsing, validation, INSERT INTO stock_items and opening receipt flow**, though the existing shared validation helpers are reused. It also handles module scope and guards shared/Both mutations. Category administration remains privileged.
3. Both paths insert `stock_items` with `current_stock=0`, then if `initialStock > 0`, call `stock_apply_movement(...,'received',...)` using actual input business date, cost and source `inventory_manual`, within the same transaction. No direct initial stock balance field or direct ledger `INSERT` is a supported substitute.

**Do not now rewrite or collapse the handlers.** In the later authorized implementation phase, use a single shared *item-creation authority* with thin routes, explicit actor authorization, canonical category/item validation, a single transaction and stock-service delegation. This is a future design obligation, not a permission to introduce a new service in D07.

## Central shared policies that must survive future consolidation

`lib/inventory_financial.php` owns category-to-item Farm Type, Financial Type, feed usage and default production classification: Layer and Broiler feed must be Poultry; Feed Financial Type requires compatible Layer/Broiler/Ruminant feed usage. `lib/inventory_category_role.php` owns Operational versus Slaughter Output role, forbids manual initial physical stock for Slaughter Output and forbids role changes that would reinterpret existing items. A Slaughter Output RECEIVED is source-owned by Slaughter Processing, not ordinary Inventory.

`lib/stock_service.php` owns received/used movements, farm-scoped stock item identity and row locking, positive quantities, stock conservation, unit and total cost snapshots, append-and-reverse corrections, record references, and current historical valuation. `lib/stock_movement_attribution.php` owns cycle association, Farm Type and production type; no request may use an arbitrary cycle ID from another farm or silently relabel Layer feed as Broiler/shared. `lib/stock_costing.php` owns business-date historical valuation, not today’s guessed replacement price.

`api/update_stock.php` independently implements a **transport/authorization endpoint** for JSON update stock: POST, CSRF, rate limit, Inventory View and Update Stock permissions, item farm scope and `stock_apply_movement`; it does **not** independently insert ledger rows. Likewise `lib/daily_feed_sync.php` delegates Daily Record feed USE to the stock ledger and is not a separate item-creation authority. Retired category pages use `includes/inventory_category_legacy_redirect.php`: GET 302/old POST 303 to the Inventory workspace, not a second category writer.

## Source-grounded discrepancy — needs separate runtime verification

`inventory.php` Add Item checks `allowedInventoryFarmTypes()`; the delegated hardening path checks `allowedFarmTypes()` and `inventory_permission_user_can_access_farm_type()`. General/Sales-only Inventory can be in the former set but not the latter, so **delegated General item creation may be denied despite an assigned Inventory/Add Item capability**. This is a source-level discrepancy; do not assert it is a confirmed runtime bug or alter certified PHP in the preparation checkpoint.

Another known limitation: a nonnegative `unit_cost` may be input as zero and pass item-creation validation. That does not establish the actual purchase price. The 2025 representative workload needs documented priced receipts and effective stock history under D05; no arbitrary zero-valued receipt is authorized by this review.

## Future required negative and integrity proofs

A later, separately authorized isolated runtime phase must demonstrate: valid/invalid category and item farm IDs; owner versus manager Inventory View/Add/Update permissions; Sales-only General and shared/Both eligibility; correct category-role transition; slaughter outputs requiring canonical source; zero initial balance then an atomic priced `received` entry; no manual duplication of Daily Record feed `USED`; cross-farm cycle/stock denial; consistent financial classification and historical-cost replay; category/item ID, name and receipt provenance; save rollback and idempotency policy; and exact eligible used-row proofs for Reports. Route/CSRF/status tests must not assume a direct call to the stock writer grants authenticated actor rights.

All new category and item identities, costs, stock dates and quantities, tenant and actor values remain `null` in the D07 JSON. **No actual receipts, item creation or source code changes are authorized.** Existing protected databases, original event streams, GA source and Reports source remain untouched.

## Read-only verifier

`scripts/verify_c137_gc24_k1_d07_inventory_writer.py` checks the immutable D06 JSON Git blob and recomputes R1/R2 D06 spec hashes, verifies the machine-readable D07 gates, reads 14 pinned PHP files as text, rejects tampered category validation and fabricated farm/price/stock data in memory, and emits R1/R2 D07 **specification** hashes. Source-text probes do not certify full PHP behavior or runtime permissions.

```sh
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/verify_c137_gc24_k1_d07_inventory_writer.py --dry-run --database renee_c137_gc24_http --farm-slug c137-gc24-synthetic --legacy-year 2026 --as-of 2026-10-11 --reporting-year 2025 --certified-source-root /opt/renee-agrisuite-c137-gc
```

Expected `GC24CG_K1_D07_OFFLINE_VERIFIER=PASS` only after Node A guard and execution, while `K1_D07_INVENTORY_WRITER_APPROVAL=NOT_GRANTED` and no V2 events, PHP, SQL, deployments or load tests occurred.

Next design checkpoint: K1/D08 — transactional Sales writer. **150 VU HOLD; 200 VU PROHIBITED.**
