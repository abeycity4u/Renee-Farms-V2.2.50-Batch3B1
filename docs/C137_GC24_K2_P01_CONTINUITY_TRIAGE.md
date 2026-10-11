# GC24CG K2/P01 — Pre-implementation readiness entry and continuity triage

**Review status:** source-only; no PHP runtime, DB query, fixture creation, import, backup restore or load test. K1/D01–D12 **offline** reviews were reported PASS by the user's Node A evidence, ending `GC24CG_K1_D12_NODE_A=PASS`, but that does not grant runtime permissions or approved economic facts.

## Immutable starting point

- Preparation branch `c137-gc24-fixture-dryrun-z23` parent commit `501bc6b6c2a86833aff151e0b003d18def20cfea`.
- Protected Reports `c137-gc-reports-prefetch-weakmap` HEAD `322438870263825f9e739a12c4bd478983e67141`.
- D12 JSON blob `8e131d1fb4d8996d5462cc46bd8045172bb39308`, and D12 R1/R2 spec SHA256 `0f9c108376a850439e26540bc9c011da7df4f430bc1e691d050bdabc9ccf27f7` / `3ff116b4d8f2c29c59f8f4c9d7d2ae19093574431c8aa4f3ea5bbce8cdd2b73c`.
- Original frozen 2026 R1/R2 events `e31421ee40ce25b16feb698313fcf337e74126a8e63d8c5dadeedf74e30165f2`, `985341ed4315788ee8f3b9d62b327da66d38fc63ea16d406c5c52bc9949d3dfe`. No event remap, generated V2 stream or live data access.

D12's frozen JSON still records `D12_requirements=PENDING_NODE_A`, which is the *historical pre-verification authored snapshot*. It is not changed here. The more recent user-provided D12 PASS is evidence of completion of that offline review, not a change to D12's explicit operational HOLD gates. All 15 authorization entries remain blocked.

## First narrowly grounded source risk

The certified `lib/daily_population_continuity.php` Git blob `acbbae9a9796b8d87e4bb6cd1fc069ed3623aabc` has a deterministic parameter seam:

1. `daily_population_continuity_apply(..., ?int $sourceId = null)` invokes `daily_population_continuity_preview(..., $animalType, true, $sourceId)`.
2. `daily_population_continuity_preview` declares `bool $forUpdate = false` as its last parameter; its body nevertheless reads `$sourceId`.
3. `daily_population_continuity_canonical_preview` takes `?int $sourceId` and returns `null` if absent or nonpositive. In that case the parent preview proceeds to its legacy downstream continuity calculation.
4. This creates a **source-level risk of bypassing the V3 canonical population snapshots** for historical Daily Record corrections. Whether the undefined variable is escalated into an exception, and whether any actual transaction commits, are **not runtime verified**. Do not claim a successful or failed run.

**Proposed narrowly scoped future fix (NOT AUTHORIZED):** Add a trailing optional `?int $sourceId = null` parameter after the `bool $forUpdate = false` parameter in `daily_population_continuity_preview`. Do not edit the Layer/Broiler routes, SQL, stock or allocation services as a speculative workaround. Trace all preview callers and the ruminant path before any source edit; respect the existing source row → continuity → feed → mortality ordering, locking, farm/tenant boundary and revision semantics. A separately authorized patch would be tested on an explicitly isolated target, without touching certified Reports HEAD.

## Regression evidence required after separately authorized runtime work

- **signature_compatibility** — Confirm preview optional trailing ?int $sourceId = null after bool $forUpdate=false; preserve all existing earlier arguments [not run]
- **canonical_V3_exact_source** — With authorized isolated V3 baseline and saved Daily Record ID, confirm canonical preview receives the saved record ID and applies authoritative population snapshots [not run]
- **legacy_without_source_id** — With authorized isolated legacy cycle, absence of sourceId preserves old legacy contract without warnings or changed unrelated row data [not run]
- **corrected_historical_mortality** — Verify later daily opening rebases, later mortality remains source-owned; rollback on invalid negative population [not run]
- **sale_slaughter_transfer_boundary** — Verify same-date movement ordering and physical exits are incorporated without double-counted mortality [not run]
- **layer_egg_revenue_replay** — Verify Layer egg production bound, derived laying rate, revenue allocation rebuild, and transaction rollback semantics [not run]
- **broiler_feed_stock_rollback** — Verify feed USED and mortality projections remain atomic with Daily Record save [not run]
- **tenant_and_permissions** — Reject cross-farm record IDs, invalid cycle and unauthorized actors [not run]

No tests in that matrix are represented as passed today. The first source-only review is deliberately **not** a substitute for proving V3 continuity.

## Priority dependency and decision register

- **P01-A — Pin continuity source-signature remediation design:** Source-confirmed D09 preview does not declare sourceId; canonical path requires it. Depends on D09. Status: DOCUMENTED_PATCH_NOT_AUTHORIZED.
- **P01-B — Approve 2025 per-event date and pre-period context:** Calendar selection alone is not source dates, cohort ages or stock carryforward. Depends on D01, D02, D04, D05. Status: BLOCKED.
- **P01-C — Ground dated biology, receipts, stock and sales:** Layer eggs, Broiler populations, historical feed costs, physical exits and receivables are unknown. Depends on D02, D03, D04, D05, D07, D08, D09. Status: BLOCKED.
- **P01-D — Verify isolated actor, tenant and protection boundaries:** Synthetic farm identity, actor, backup and DB safety are references, not approved active facts. Depends on D06, D12. Status: BLOCKED.
- **P01-E — Validate allocation conservation and Reports eligibility:** Actual cents, 2025 receipt-backed USED source counts and monthly reconciliations not measured. Depends on D10, D11. Status: BLOCKED.
- **P01-F — Seek separate importer and load-test authorizations:** D12 review passes cannot grant database access, fixture creation/import, restore or capacity tier. Depends on D12. Status: BLOCKED.

Use D12's original 15 gate rows for the authoritative pre-import approval checklist; this P01 document only **orders** those blockers and identifies the first feasible *source-only* seam. Do not duplicate D12's SQL schema, migration or backup scripts. Before future database activity, independently validate exact tenant/actor, isolate target host/DB, protected backup, original fixture and restoration boundaries. `renee_c137_gc24_http` and `c137-gc24-synthetic` remain reference-only identifiers.

## Verification and hard-stop rules

The focused Python `scripts/verify_c137_gc24_k2_p01_readiness.py` inspects the D12 JSON and four certified PHP files *as inert text*, verifies the exact D12 prior digest and D09 seam, tests negative permission tampering in memory and prints only scope evidence. It never opens a DB connection or runs PHP.

```sh
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/verify_c137_gc24_k2_p01_readiness.py --dry-run --certified-source-root /opt/renee-agrisuite-c137-gc --year 2025
```

After independent Node A PASS, K2/P01 may close as an **offline readiness entry**. The next action is a separate user decision on a **minimal source patch to a new isolated preparation branch**, preceded by caller inventory and a test plan. No runtime patch, PHP execution, DB read/write or representative fixture is authorized by P01. **150 VU HOLD; 200 VU PROHIBITED.**
