# GC24CG K1/D08 — Transactional Sales writer: source-grounded requirements

**Status:** K1/D08 is an *offline source requirements review*. Transaction implementation, sales prices and quantities, 2025 sales/customer/payment facts, revenue allocations, and physical stock/population effects **are NOT approved or verified**. All scenario-specific unknowns remain null. Do not run application PHP, connect to any database, create fixture events, or start load tests.

## Pinned checkpoints

- Protected Reports source branch `c137-gc-reports-prefetch-weakmap`, HEAD `322438870263825f9e739a12c4bd478983e67141`, must stay unchanged.
- Preparation branch `c137-gc24-fixture-dryrun-z23`, pre-D08 parent `a5d3a0eb5955db34e482a902f4797a19c3c86efe`.
- D07 machine-readable JSON Git blob `5610a26f89b6e8da007831d8f1cf5885ec80cf46`; R1 `48267cd7c330243200cebfd837bd541dbb3aa44f51a72d9107ab52695417a63d`, R2 `991d5c5f14ffa462613744d4fd1491dbf6da2e83050a0e81f1c9dcf7a54d39c7`, all D07 specification hashes.
- Original 2026 R1 event digest `e31421ee40ce25b16feb698313fcf337e74126a8e63d8c5dadeedf74e30165f2`, R2 `985341ed4315788ee8f3b9d62b327da66d38fc63ea16d406c5c52bc9949d3dfe`, frozen and NOT migrated into 2025.
- D01 selected calendar reporting window **2025-01-01 through 2025-12-31** for new V2 *design only*. Date rules, per-sale physical and financial values, and any pre-2025 credit/stock/cycle carryforward still need approval.
- Database `renee_c137_gc24_http` and tenant slug `c137-gc24-synthetic` are references only. The actual actor, tenant and transaction state were not queried.

## Certified sources and authoritative boundaries

**Financial sale and transactional coordinator:** `management/sales_records.php` is currently the coordinating add/edit page. It requires authenticated, tenant-scoped actor and granular sales actions. It validates permitted Farm Type, normalized production type, optional cycle against `farm_id`, quantity x unit-price total, sale unit, payment received 0..total and customer name when outstanding > 0. The Add Sale route begins a PDO transaction; `record_reference_persistence_insert_new` issues a sale public reference with the financial row. In that same transaction it coordinates optional General Inventory usage, Slaughter Output Sale selection/consumption, ruminant animal allocations/exit outcomes, explicit bird population effects, automatic shared revenue allocation, layer egg chronological allocation rebuild, and initial credit receivable ledger entry. **Do not replace this workflow with an independent direct INSERT or invent a new page-specific duplicate of every service.** Any later shared writer extraction must preserve full orchestration, idempotency and rollback.

**Physical effects are separate:** `lib/sale_population_effects.php` explicitly states physical bird exits are not inferred from product name, quantity or sales unit. Financial-only sale leaves population unchanged; `remove_live_population` requires explicit supported cycle IDs and positive integer headcounts, bounded by the canonical population projection. `lib/general_sale_inventory.php` is a separate transaction-owned stock writer for physical General Inventory sales with reversal history. `lib/slaughter_output_sale_dispatch.php` separately tracks processed outputs and lot/provenance availability. `lib/ruminant_sale_animal_allocation.php` assigns ruminant revenue across identified animals; `lib/ruminant_animal_exit.php` owns their disposition, a different fact. A standalone Sales-only tenant is forced to General Inventory and `financial_only` population effects; do not let client overrides forge livestock exits.

**Egg sales and revenue allocation:** `lib/layer_egg_inventory.php` derives dated crate availability from Layer Daily Records and earlier sales. `lib/sales_allocation.php` auto-allocates *shared* Layer egg sales from that unsold production. When insufficient or units cannot be converted, the financial sale may still commit with a warning that its shared revenue remains **unallocated**. Direct cycle attribution is not equivalent to a fully evidenced pool; manual percentage splits must not substitute for biological egg production. Historical sale create/edit/delete uses `sales_rebuild_layer_egg_allocations` from the earliest affected business date, in sale-date/ID order. `lib/sale_revenue_allocation_service.php` and `lib/sale_revenue_allocation_persistence.php` separately guard authorized manual shared revenue and revision history.

**Cash, receivables and payment history:** `payment_received` on `sales_records` is a **sale-time upfront cash snapshot**. A `customer_ledger_entries` sale row captures the credit portion; subsequent `payment` rows are separate cash facts. `lib/sales_receivables.php` recalculates sale edits and rejects lowering the sale total below existing cash receipts, or deleting sales with linked payments. `includes/sales_receivable_permissions.php` adds granular payment/receivables permissions and CSRF to the legacy Sales form; Sales Add permission is not universal permission to edit/delete receivables. A future fixture must reconcile total sale, upfront, debt, later payments and outstanding by customer and invoice, without double-counting cash and revenue.

**Deletion and corrections:** `api/delete_sale.php` checks method, CSRF, rate limit, permissions, tenant farm and protected allocation/payment/population/slaughter effects. It reconciles/reverses dependent sources and updates Layer egg allocations transactionally before committing. The sales edit branch must preserve upstream shared allocation/provenance, stock and population effects and credit history before changing the sale record.

## Proposed conditions before any authorized V2 event generation

- Explicit owner-approved or independently evidenced 2025 sale event dates, units, quantities, per-unit prices, identities and no real customer PII. A chosen reporting year is not permission to backdate frozen 2026 workload.
- Valid tenant/actor permissions (D06), Inventory item and receipt source (D07/D05), Layer egg production availability (D02/D03), Broiler acquisition/exit biology (D04), and real eligible transaction evidence under D11.
- An explicit yes/no physical effect for every livestock sale; physical removals cannot be inferred from financial sale count. All General/Slaughter item movements must use domain-owned stock writers.
- Credit sale reconciliation: `total = quantity * price`; `upfront + initial debt = total`; cumulative later payments cannot exceed debt; chronological adjustment and deletion policies approved.
- Automatic Layer egg revenue must be supported by dated egg supply, not manual 60/40; missing availability means that sale is **not yet representative**, even if source UI saved it.
- Test direct/shared allocations, historical changes, duplicate public references, reruns/idempotency, reversed stock, missing CSRF, unauthorized actor, cross-farm IDs, deletion blocked by payments, and transaction rollback before any fixture import. Those are future test obligations, not tests performed in D08.
- Do not bypass PHP transaction/business policies through speculative standalone SQL or duplicated routes. The large Sales page currently remains the coordinator; future thin writer extraction is a separately approved implementation task.

## Focused offline verifier and limitations

`docs/C137_GC24_K1_D08_TRANSACTIONAL_SALES_REQUIREMENTS.json` has 59 null/unknown fact fields, 17 PHP source contracts, future integrity gates, critical financial/physical separations, and explicit no-write/no-load authorizations. `scripts/verify_c137_gc24_k1_d08_sales_writer.py` checks immutable D07 Git blob and digests, matches source text markers and rejects in-memory fabricated sale amounts/people/payment data or permission escalation.

```sh
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/verify_c137_gc24_k1_d08_sales_writer.py --dry-run --database renee_c137_gc24_http --farm-slug c137-gc24-synthetic --legacy-year 2026 --as-of 2026-10-11 --reporting-year 2025 --certified-source-root /opt/renee-agrisuite-c137-gc
```

This does **not** run PHP, test sales in the database, prove multi-table transactional behavior, certify egg pools, physically move livestock, approve a Sales writer, or create any event stream. Node A PASS will close the **D08 requirements-review checkpoint only**. D09 Daily Record writer is next design review.

**Capacity gates:** **150 VU HOLD; 200 VU PROHIBITED.** Protected source, databases and old 2026 event artifacts remain untouched.
