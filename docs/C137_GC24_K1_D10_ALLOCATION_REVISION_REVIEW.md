# C137 GC24 K1/D10 — Shared financial allocation revisions and reconciliation

**Status:** Offline source requirements review, not runtime/biology/transaction authorization. All 2025 financial parent amounts, per-cycle allocations, actor/tenant IDs, past balances, revision identities and actual Profitability totals remain **unknown**.

## Verified source lineage and boundaries

Certified Reports remains protected at `322438870263825f9e739a12c4bd478983e67141` (`c137-gc-reports-prefetch-weakmap`). The preparation branch `c137-gc24-fixture-dryrun-z23` starts this checkpoint at `4095c2abfccee4899cb0e395fc683e4260993d71`. Frozen D09 JSON Git blob `c391a3de0853468209afa9e2b8a40186b05374b4`; R1 D09 spec SHA-256 `b07d009a87b0132fcd675e4aaf66e21a6594d086a871a6d84563c087c3128af6`, R2 `013098e94b600544fb25651cb7154fb7e25e86096f70ebdb964b0e0a0bced94d`. Frozen original **2026** R1/R2 event SHA-256 remain `e31421ee40ce25b16feb698313fcf337e74126a8e63d8c5dadeedf74e30165f2`, `985341ed4315788ee8f3b9d62b327da66d38fc63ea16d406c5c52bc9949d3dfe`. January–December **2025** is design-only; no event dates approved.

## Three independent parent / projection / revision families

**Manual shared expenses:** `farm_expenses` owns actual source cost; `financial_allocations` is current cycle attribution; `farm_expense_revisions` is durable historical revision record. `financial_allocation_service.php` validates shared parent and same-farm target scope; already-cycle-expensed rows and historical manual Feed expenses are not eligible. A cost allocated to tagged animals cannot also be cycle-allocated. `financial_allocation_integrity.php` guards edits through other expense writers. `financial_allocation_persistence.php` owns projection mutation, revision lineage, correct lock order and semantic no-ops. The allocated money amount is causal economic authority; allocation percentage is derived metadata.

**Manual shared sale revenue:** `sales_records` owns revenue; `sales_allocations` is cycle projection; `sales_allocation_revisions` plus `sales_allocation_revision_rows` retain append-only provenance. Automatic Layer egg revenue is separately allocated from real egg production by `sales_allocation.php`; it is not a manual split target. `sale_revenue_allocation_service.php` guards cycle and target eligibility; `sale_revenue_allocation_provenance.php` constructs causal and state manifests/fingerprints. `sale_revenue_allocation_persistence.php` refuses inconsistent current projections, supports explicit `retain_shared` and exact no-op, and records meaningful revisions. Cash receipts are not additional sales revenue parents.

**Consumed-stock allocation:** A single effective, correctly costed `USED stock_transactions` movement is the parent fact. `stock_consumption_allocations` is the current projection; `stock_consumption_allocation_revisions` is revision history. Direct-cycle usage, reversed or unpriced usage, and Daily Record attribution drift cannot be manually allocated as shared consumption. `stock_consumption_allocation_service.php` owns eligibility; `stock_consumption_allocation_provenance.php` owns manifests; `stock_consumption_allocation_persistence.php` locks the authoritative Daily Record then stock parent and target cycles as needed. The `stock_service.php` reversal path coordinates closure through `stock_consumption_allocation_persistence_before_source_reversal`. Never invent extra USED rows to meet test scale.

## Conservation and Profitability

The canonical `shared_cost_contract.php` enforces scope, target, meaningful pre-cycle explanations and **parent cents = explicitly allocated cents + visible unallocated cents**. Do not silently assign an unallocated remainder to a cycle, and do not treat the remainder as another cash/revenue/expense transaction. No speculative 60/40 split is acceptable.

Each allocation API is a thin authenticated POST/CSRF/rate-limited transaction coordinator; Expense and Revenue authorization is checked against a locked parent. Separate workspace readers do not own allocation SQL. Exact economic no-op is not authority to manufacture a revision, and a retainer decision is explicit, not a default.

`profitability_unallocated_shared.php` discloses shared amounts and remainders without changing overall Profit/Loss. `stock_consumption_economics.php` ensures the effective stock-use parent is counted once farm-wide, then decomposed across eligible cycles/shared balance, not double charged. Inventory Feed purchase cash outlay is separate from the cost of consumed feed.

## Future proofs (none run now)

Before a V2 fixture import, independently approve tenant/actor (D06), inventory costs (D05/D07), actual sales and egg biology (D03/D08), and Daily Record correctness (D09). Prove each actual parent in 2025, chronological cycle eligibility, per-cycle cents, authorization, no source double-count, revision actor/reason/chain, clear/retain/no-op semantics, and source reversal closure. Show monthly revenue/cost and shared remainder reconciliation without mixing cash receipts into Sales. Verify failed CSRF, tenant mismatch, revision tamper, and concurrency. D09’s continuity-signature mismatch remains unresolved separately.

D10's machine-readable requirements retain **66 values as null**, index **20 certified files and 106 source probes**, and forbid writes and load tests. Its verifier reads only text; passing says nothing about actual posted financial rows.

```sh
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/verify_c137_gc24_k1_d10_allocation_revisions.py --dry-run --database renee_c137_gc24_http --farm-slug c137-gc24-synthetic --legacy-year 2026 --as-of 2026-10-11 --reporting-year 2025 --certified-source-root /opt/renee-agrisuite-c137-gc
```

Next after Node A PASS: **K1/D11 Reports eligibility/prefetch/source-row capacity review**. K1/D12 import authorization remains pending. **150 VU HOLD; 200 VU PROHIBITED.** No SQL, application PHP, synthetic event generation, imports, deployments or new load tests.
