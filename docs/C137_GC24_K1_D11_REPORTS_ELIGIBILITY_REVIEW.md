# C137 GC24 K1/D11 — Reports eligibility, annual source prefetch, capacity evidence

**Scope:** Offline source-requirements review only. Neither Reports-eligible USED row counts nor GC24 capacity, real feed prices, authenticated actor, biological populations, runtime reporting performance or import authority are demonstrated. Calendar 2025 is a reporting-period design selection, not permission to move original 2026 events.

## Pinned source and lineage

- Certified Reports source: `c137-gc-reports-prefetch-weakmap` HEAD `322438870263825f9e739a12c4bd478983e67141`, immutable. Preparation branch parent: `c137-gc24-fixture-dryrun-z23` HEAD `1beb16cceeefb97371c9a93714fed2007c3a6c7c`.
- D10 immutable JSON Git blob `d9f06ccb357acca5790091665a60f33fc1d26b89`; R1 D10 spec `9ac9355b16253ad2f110d499798cf9243c721e6dfeefd59e71cb210b54f14c12`, R2 `37781fbde25095e6c667557c3f835151ab6f3817573c9af2d73d33c8f019abea`.
- Frozen **2026** R1/R2 event hashes `e31421ee40ce25b16feb698313fcf337e74126a8e63d8c5dadeedf74e30165f2` and `985341ed4315788ee8f3b9d62b327da66d38fc63ea16d406c5c52bc9949d3dfe` must never be backdated or regenerated.
- Database `renee_c137_gc24_http` and slug `c137-gc24-synthetic` are **references**, not verified live access or tenant identity.

## Exact canonical SQL eligibility

`lib/stock_consumption_economics.php::stock_consumption_economics_source_rows` selects a **single parent movement** where `t.farm_id=?`, `transaction_type='used'`, effective predicate (`is_reversed=0 AND reversal_of_id IS NULL`), inclusive business date range, and `total_cost IS NOT NULL`. Classification is the posted, immutable `stock_transactions.financial_classification`: Feed (`stock_feed_transaction_sql_predicate`) or eligible operating consumed stock from `inventory_operating_consumption_classifications()`. Stock item and category metadata are joined within the same farm. Ordering is business transaction date then movement ID.

**Important nuance:** The reader's SQL accepts a **non-null** cost; it does not require that cost to be strictly positive or show a dated priced stock receipt. A zero-cost row can therefore pass this SQL filter while being economically unsuitable as representative, verifiable feed consumption. The **shared stock-allocation parent contract** separately requires positive cost, effective USED, non-direct-cycle scope and authoritative source provenance. SQL row eligibility, attribution eligibility, and genuine representative economics must be audited separately. `stock_historical_unit_cost` also has a legacy fallback to current valuation if replay is incomplete, which is not automatically proof of a valid receipt-backed historical price.

The old 2026 candidate USED counts (R1 1,800; R2 3,600) are neither independently qualified 2025 data nor proof of an eligible costed Reports row count. The original Broiler Daily Record feed USED stream lacked demonstrated feed provenance. No new counts have been queried.

## What the 2,048 threshold actually does

The annual prefetch function defaults to **2,048 eligible rows per farm and reporting year**. The same canonical SQL source reader is called with `LIMIT maxRows+1` to detect overflow. At **2,048** eligible annual parents, the function may seed month-range source rows in the PDO-keyed `WeakMap` cache. At **2,049 or more**, the annual prefetch returns **no newly seeded month entries**, allowing the established month-by-month reporting queries to run instead. It must **not** silently cap financial totals, lose January/December rows, or cache partial source results.

Monthly date windows are partitioned and validated against the annual year; only month entries actually seeded by that request are removed in `farm_intelligence_monthly_series`' `finally` path. Existing canonical annual range entries remain available for other parts of the same request. Cache ownership follows PDO lifetime, farm ID and date-range; source writes invalidate relevant farm cache entries. Allocation rows remain separately fetched, not cached or synthesized by this optimization.

**2,048 is a memory/query prefetch design boundary**, not a target that must be filled with arbitrary data and not a concurrent virtual-user count. Both below-cap and overflow paths need later runtime profiling on a truthful isolated data set; neither is certified by this D11 static review.

## Reports and existing benchmark verification limits

`management/reports.php` resolves authorization, tenant and entitled farm type first, then releases its read-only session lock before running Reports. It delegates month-level financial results to `farm_intelligence_monthly_series`, which in turn delegates to `getProfitabilitySummary` rather than writing a separate profit formula. The existing `scripts/verify_c137_reports_prefetch_contract.php` expects **82 total SQL calls and 1 stock-source query** using its own small controlled C137 fixture (8 stock transactions), tests WeakMap cleanup, second-farm isolation, overflow and monthly accounting. These controlled fixtures are **not** the proposed GC24 R1/R2 dataset; their counts and times cannot be transferred to a different population or reused as load results.

**Do not run that PHP verifier during D11.** It opens a fixture database and executes queries; D11 permits only inert PHP text inspection by the Python verifier. `scripts/verify_c137_monthly_reporting_contract.php` and the session-lock verifier similarly describe existing regression contracts, not GC24 capacity certification.

## Evidence needed later (not produced here)

Before any representative performance claims: finish D01 date provenance, D02–D04 Layer/Broiler biology, D05 purchased/feed receipts and valuation, D06 authorized isolated tenant/actor, D07 Inventory, D08 Sales, D09 Daily Record continuity (including the documented `$sourceId` mismatch), and D10 allocation conservation. After explicit D12 import authorization, independently count real 2025 eligible and positive receipt-proven USED rows by tenant, scenario, month and category. Reconcile Ledger USED source ID to actual Daily Records, activity/sales/population and historical cost. Verify 2,048 and 2,049 boundary paths only on permitted isolated, authentic fixtures and ensure fallback produces **identical** monthly financial totals to canonical reads.

A later approved benchmark must record GC24-specific SQL query counts, cache footprint, peak memory, p95/p99 response latency, throughput, errors, CPU, DB query/IO and worker saturation under separately authorized load. Neither the small C137 verifier's 82/1 calls nor earlier nonrepresentative latency proves this. Never increase event volume solely to cross the prefetch boundary.

## Read-only D11 artifacts and safeguards

The machine-readable D11 requirements preserve **72 null/unknown fields** and 16 pinned PHP source contracts with **96 source markers**. The focused `scripts/verify_c137_gc24_k1_d11_reports_eligibility.py` recomputes the frozen D10 specification lineage, verifies canonical markers, checks the distinct SQL-versus-economic eligibility policies and rejects invented row counts, benchmark latency, illegal operations or false truncation semantics **in memory only**.

```sh
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/verify_c137_gc24_k1_d11_reports_eligibility.py --dry-run --database renee_c137_gc24_http --farm-slug c137-gc24-synthetic --legacy-year 2026 --as-of 2026-10-11 --reporting-year 2025 --certified-source-root /opt/renee-agrisuite-c137-gc
```

A guarded Node A PASS may close **D11 source requirements review only**. D12 import authority is next. No database connections, PHP execution, fixture generation, data migration, deployment, runtime report profiling or load testing is authorized here. **150 VU HOLD; 200 VU PROHIBITED.**
