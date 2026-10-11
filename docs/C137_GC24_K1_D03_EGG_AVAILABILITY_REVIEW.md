# C137 GC24 K1/D03 — Layer egg production and sale availability requirements

**Status: requirements captured, D03 biological and egg-pool evidence not approved.** This is an OFFLINE, non-executable source review only. The selected new V2 reporting window is **January 1–December 31, 2025**; exact acquisition, production and sale date rules are still pending. No new events, physical flock values, prices, database mutations, PHP execution, source deployment or load runs are permitted.

## Pinned provenance and closed checkpoints

- Certified Reports source: `c137-gc-reports-prefetch-weakmap` HEAD `322438870263825f9e739a12c4bd478983e67141`; keep immutable.
- Preparation parent: `5e1d1e403c279a84418e9ff29d11aa5039baef92` after D02's one-line static false-positive correction and Node A PASS.
- Frozen D02 requirements Git blob: `3ded482cf99d9e96cc8806b7e8ebbb02672a7078`.
- D02 R1/R2 spec digests `c3c63b0c41d739f43ecdcb738b8e91089c1d354768ce4aacf927cc3c72dafb80`, `28528c2853db293b3210c54ca32447731699120d34386ba210669ca304391fe9`.
- D01 year-selection Git blob `db5ded90c63f35a59861e4e8a58ed07a0628b0db`; selected 2025 for design only.
- Original 2026 R1/R2 event digests `e31421ee40ce25b16feb698313fcf337e74126a8e63d8c5dadeedf74e30165f2`, `985341ed4315788ee8f3b9d62b327da66d38fc63ea16d406c5c52bc9949d3dfe`; unchanged.

All D02 numeric biological fields remain unknown. Legacy original crate SALES (R1 16,419, R2 49,269) and maximum daily sales (54/144 crates) are financial workload volumes, **not proof of production or a reason to choose a flock size/laying rate**. D02–D12 remain unapproved.

## Canonical contract findings from certified PHP (7 files)

**Daily production:**
`poultry/layers_daily_record.php` accepts a dated active Layer cycle, requires strictly positive opening flock and birds age, rejects mortality or egg production above opening population, derives laying rate as `round(eggs / opening * 100,2)` and stores crate count as `round(eggs / 30,2)`. V3 population opening comes from `daily_population_continuity_expected_opening`. In one Daily Record save transaction, it applies continuity, `sync_daily_feed_usage`, mortality and downstream pooled-egg allocation rebuild. `lib/daily_population_continuity.php` also rejects historical corrections that would leave later egg output or mortality above revised canonical opening, recalculating later laying rates.

**Egg pool and date order:** `lib/layer_egg_inventory.php` does **not** store a separate mutable egg-stock ledger. It derives unsold crates by cycle from Layer Daily Records dated **on or before** the sale date, using stored `crates_count` when positive or raw eggs/30 otherwise. It deducts *earlier* Layer egg sales ordered by `sale_date, id`; direct-cycle sales debit that cycle, while prior shared sales debit persisted allocated quantities or percentage fallback. Same-date production is eligible for a sale even if input timing is different; a future design must explicitly approve day-level causal assumptions. Returned `available_crates` is clamped at zero and rounded to 4 decimals, so an independently verified oversale check remains necessary rather than trusting nonnegative presentation alone.

**Units and precision:** Canonical egg conversion is **30 eggs/crate**. Unknown/blank historical sales UOM means crates. Crate/tray count as crates; egg/piece counts divide by 30; dozen quantities multiply by 12/30. Unknown UOM is **not** guessed: earlier sales with unconvertible units are ignored in derived pool deductions and a new shared sale using an unconvertible unit returns unallocated. Daily stored crates are rounded to two decimals; pool output to four; shared auto-allocation allows a 0.01-crate tolerance. A future design needs a separate precision/equality policy, not a silent conversion to whole eggs or an invented rounding method.

**Auto allocation versus financial sale:** `lib/sales_allocation.php` automatically allocates *shared* Layer egg sale amount/quantity according to accumulated unsold pool, enforcing nonzero pool and quantity not above available + 0.01 crates. If no pool, insufficient pool or unknown unit, it returns `unallocated`. **This is not the same as rejecting a financial sale:** `management/sales_records.php` commits a shared sale and can show a post-save warning that the revenue remained unallocated. For direct-cycle sales, the sales-allocation status reports `direct` and bypasses the shared automatic pool check, although the egg-pool calculation counts prior direct-cycle sales as consumption. Hence neither `direct` nor a nonnegative *clamped* pool alone proves physical egg inventory validity. Any representative V2 fixture would need all dated sales reconciled separately and every intended automatic shared sale to be allocated.

**Historical replay:** A Layer Daily Record save invokes `sales_rebuild_layer_egg_allocations` from the record date; Sale edit/creation invokes rebuild starting at the earlier relevant sale date. Shared Layer egg sale rebuilding sorts by `sale_date,id`. Manual 60/40 Layer egg revenue allocation cannot substitute for derived production. Existing manual shared-revenue history must not be wiped by an importer.

**Feed:** Daily feed stock USED is sourced through `sync_daily_feed_usage` and must not be independently duplicated by manual inventory events.

## Missing evidence for a valid new V2 production model

Before any event generation, the owner/engineering team must approve per-cycle opening flock, acquisition and production start date, daily ages/mortality/eggs/lay rate (or an explicitly evidence-backed generation rule), and inventory of unsold eggs existing at 2025 start. Reconcile every dated sale unit and its chronological egg availability with source-derived production, including prior direct-cycle sale effects, nonconvertible UOM, crate precision and historical corrections. Do not retrofit original 2026 sales into 2025.

The 2025 reporting-year choice does not automatically allow 2024 pre-year egg production. Carry-forward or pre-year initial biological history requires separate explicit date-policy approval and canonical source checks. The new egg rules and biological approvals are **NOT** granted by this review.

## Verifier and limitations

`docs/C137_GC24_K1_D03_LAYER_EGG_AVAILABILITY.json` stores source-derived design rules and unknown biological data as `null`. `scripts/verify_c137_gc24_k1_d03_egg_availability.py` checks frozen D01/D02 Git blobs, recomputes D02 spec digests, probes canonical PHP as text, rejects fabricated numeric assumptions and permissive write modes, and emits R1/R2 **design-spec hashes**.

It is NOT an executable implementation, a PHP runtime behavioral test, a full sales stock-consistency proof, or import readiness certification.

```sh
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/verify_c137_gc24_k1_d03_egg_availability.py --dry-run --database renee_c137_gc24_http --farm-slug c137-gc24-synthetic --legacy-year 2026 --as-of 2026-10-11 --reporting-year 2025 --certified-source-root /opt/renee-agrisuite-c137-gc
```

Expected: `GC24CG_K1_D03_OFFLINE_VERIFIER=PASS`, D02 lineage, source probes, R1/R2 D03 design digests, `DATABASE_WRITES=0`, `NEW_V2_EVENTS_GENERATED=NO`, and `K1_D03_EGG_PRODUCTION_APPROVAL=NOT_GRANTED`. Only Node A independent verification closes the **requirements review checkpoint**.

**Safety:** Protected databases `renee_c137_fixture`, `renee_c137_gc24_restore_z25`, `renee_c137_gc24_http` unchanged; 150 VU HOLD, 200 VU PROHIBITED; certified source unchanged.
