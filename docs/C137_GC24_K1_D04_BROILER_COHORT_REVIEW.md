# GC24CG K1/D04 — Broiler cohort, lifecycle and physical sale-exit requirements

**Review status: K1/D04 source requirements drafted; biological and population evidence NOT APPROVED.** This is a documentation/offline-verifier checkpoint, not a fixture creation or database-import authorization.

## Pinned authority

- Certified Reports branch `c137-gc-reports-prefetch-weakmap` HEAD `322438870263825f9e739a12c4bd478983e67141`, unchanged.
- Prep parent branch `c137-gc24-fixture-dryrun-z23` HEAD `6da0d69d7b94bdb1dddb47d71b1f2cc8adfcfedb`.
- Selected 2025 reporting period: **2025-01-01 to 2025-12-31**, D01 design choice only; technical date rules unresolved.
- Completed D03 review, Git blob `8c02f1a6fd0eb30944a38a28adce8c15968ea0be`; R1 spec `6d0e142f51c92c80d7031dedb3e47897d7b029d67f05c1095a62287e35268a44`; R2 spec `0a33b8f8cbe21dc49c7e24577141e5d837904fe2a3e3c6a35d86e5b6b539d6a8`.
- Original *2026* event hashes R1 `e31421ee40ce25b16feb698313fcf337e74126a8e63d8c5dadeedf74e30165f2`, R2 `985341ed4315788ee8f3b9d62b327da66d38fc63ea16d406c5c52bc9949d3dfe`; preserve exactly.

## Verified certified source architecture

**Cycle onboarding:** `production_cycle_create_v3` creates an active Broiler cycle and a dated cycle-opening population baseline; `poultry_cycle_onboarding_record_initial` separately records acquisition and starting phase in its atomic boundary. Acquisition types for Broilers are `purchased` and `internal_transfer`, not purchased Point-of-Lay. Purchase requires actual cost; quantity must be positive, age >= 1 day, and the acquisition/cycle dates must obey certified constraints. Do not credit the same acquisition twice to physical population.

**Biological lifecycle:** `poultry_lifecycle_allowed_phases` permits Broiler `growing` and `harvest`, with a `growing -> harvest` transition. These are durable explicit dated facts. The service **does not infer phases** from age, feed, mortality or sales. A staged-cohort benchmark is an unapproved synthetic design objective, **not** a phase schedule supplied by source code. No specific harvest age or placement interval has been chosen.

**Daily Records:** `poultry/broiler_daily_record.php` requires active same-farm Broiler cycle with a date inside its valid bounds. Daily opening birds and bird age must be positive; mortality cannot exceed opening birds; V3 opening population comes from the canonical boundary. Each transaction coordinates saving the operational row, population continuity, `sync_daily_feed_usage`, and `daily_population_sync_mortality`. The last two have separate durable sources, so daily mortality must not be posted twice and canonical feed stock consumption must not be duplicated by manual USED. No dated Broiler feed/use values exist in the original 2026 scenarios.

**Sales versus physical exits:** `management/sales_records.php` invokes `sale_population_effect_rows_from_post` then `sale_population_effect_sync`. `sales_records` is the financial fact; `sale_population_effects` is the durable physical-effect source. Missing `population_effect_mode` means **financial_only**, not live removal. `remove_live_population` requires explicit, positive integer headcounts and cycle IDs. For an exactly cycle-attributed sale, the physical source must be that cycle alone; multi-cycle physical removals require shared sale attribution. Cycle farm/production-type, date, and nonnegative population projections are enforced. Edits and cancellation reverse/reconcile prior effects with retained source history.

**Slaughter:** the population ledger contains an independently named `slaughter` outflow/source; it must not be double-counted alongside the Sales physical effect. K1/D04 does **not** claim the slaughter subsystem is implemented or verified by this source subset—trace its actual writer before designing slaughter transactions.

**Closure:** `production_cycle_close_v3` uses canonical remaining population as of a valid close date and rejects a close before any later population movements. When zero birds remain, Broiler Daily Record form rules require positive opening birds; do not fabricate extra daily rows after exhaustion merely to fill a 365-day calendar.

## Why the original volume cannot define 2025 biology

The Z27H/Z27I legacy stream showed financial Broiler Head sales of **5,469 R1 and 16,422 R2**; none were proven to remove living population. Original Broiler feed USED count was zero; assigning operating consumables as feed is invalid. Direct old Broiler sales were attributed to `broiler-a` with no matching `broiler-b` sales. Those are workload inputs **from the original 2026 stream**, not 2025 flock quantities, exit rows, cohorts or accepted production rates.

A representative *new* 2025 dataset requires independently justified cycle-count, dates, opening headcounts, age, acquisition prices, mortality, growing/harvest timing, buyer/financial sale quantity, explicit sale live-exit whole heads, feed and its dated priced receipts, closing balances, and stock/cost reconciliation. Any pre-2025 acquisition history requires an additional explicit cross-period date-rule decision. All numeric inputs and dates remain `null` in the machine-readable design.

## Checklist and what is still blocked

The new requirements file `docs/C137_GC24_K1_D04_BROILER_COHORT_REQUIREMENTS.json` distinguishes **observed canonical source contracts** from **proposed future V2 fixture proof obligations**. Its source probes inspect 11 PHP files as text; it does not run PHP, validate actual live population, seed a cohort or exercise a transaction. Negatives reject invented headcounts, ages, feed, early harvest dates and forged DB write permission.

Next after this requirements-review checkpoint is **K1/D05 — Layer/Broiler feed and historical stock costing**, still read-only; D02/D03/D04 owner-approved physical models and D01 date-rule closure remain pending. Actual 2025 events, shared-writer extraction, SQL inserts, load runs, deployments, migrations, production and protected fixtures remain prohibited.

After Node A guards, the focused verifier command is:

```sh
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/verify_c137_gc24_k1_d04_broiler_cohorts.py --dry-run --database renee_c137_gc24_http --farm-slug c137-gc24-synthetic --legacy-year 2026 --as-of 2026-10-11 --reporting-year 2025 --certified-source-root /opt/renee-agrisuite-c137-gc
```

Required `GC24CG_K1_D04_OFFLINE_VERIFIER=PASS`, original hashes unchanged, D04 approval NOT_GRANTED, physical exits UNPROVEN, zero database connections/writes/source writes and no new V2 events.

**Operational hold:** 150 VU HOLD, 200 VU PROHIBITED. No capacity testing or runtime fixture certification.
