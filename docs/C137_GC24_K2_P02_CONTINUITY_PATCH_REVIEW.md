# GC24CG K2/P02 — Minimal `$sourceId` continuity signature correction

**Authorization:** User said “proceed” after being offered the separate **source-only** K2/P02 correction. This does **not** authorize deploy, PHP execution, database connection, import, restoration, fixture generation or load testing.

## Protected lineage and independent branch

Base K2/P01 preparation SHA: `0d290c1f25b4bdb186f4cefbc61b7ade1bbcdf49` on `c137-gc24-fixture-dryrun-z23`, which remains unchanged. Certified Reports branch `c137-gc-reports-prefetch-weakmap` remains `322438870263825f9e739a12c4bd478983e67141`, original `lib/daily_population_continuity.php` Git blob `acbbae9a9796b8d87e4bb6cd1fc069ed3623aabc`. K2/P02 is committed only to `c137-gc24-k2-p02-continuity-sourceid`. Do **not** change the certified Reports checkout or the K1/K2-P01 preparation worktree. On Node A, verify the patch in a separate source-only Git worktree, never the live runtime.

## Exact source correction

`lib/daily_population_continuity.php` previously defined `daily_population_continuity_preview(..., ?string $animalType = null, bool $forUpdate = false)` without `$sourceId`, but its body passed `$sourceId` to `daily_population_continuity_canonical_preview`. `daily_population_continuity_apply` supplied that source ID as a tenth positional argument and canonical code requires a positive source ID.

The patch appends **one trailing optional parameter** `?int $sourceId = null` after `bool $forUpdate = false`. It changes neither SQL, transaction boundaries, canonical population logic nor allocation logic. Existing direct calls with seven or eight arguments stay compatible; locked apply now passes the saved Daily Record source ID to the canonical planner by declaration. This is source-level remediation only.

## Existing caller and regression inventory

- `poultry/layers_daily_record.php` supplies `$dailyRecordId`, then synchronizes Feed and canonical mortality, rebuilds egg-sale allocations, and commits in one transaction.
- `poultry/broiler_daily_record.php` supplies `$dailyRecordId`, synchronizes Feed and canonical mortality, and commits; no Layer egg rebuild.
- `ruminant/ruminant_daily_record.php` supplies `$dailyRecordId` with its animal type and follows the same shared Feed/mortality transaction order.
- `scripts/verify_v301_daily_population_continuity.php` directly calls preview without new source ID in legacy fake-PDO cases, confirming why the new parameter remains optional.
- `scripts/verify_v301_daily_population_canonical_continuity.php` already tests apply-side forwarding but previously omitted checking the preview declaration. The new focused **Python** verifier covers the missing declaration and rejects in-memory tampering.

Future **separately authorized runtime** tests must cover valid V3 baseline + saved source ID, later opening-stock rebase after sale/slaughter/transfer, Layer eggs and future-day mortality bounds, Ruminant animal type, legacy fallback with null ID, rollback and idempotent Feed/Mortality/egg-sales synchronization. PHP and DB tests have not run in K2/P02.

## Verification boundary

`scripts/verify_c137_gc24_k2_p02_continuity_signature.py` uses Python text reads only, checks the exact certified blob, proves the patched PHP file differs by **only the minimal signature change**, checks all three writers’ source-ID forwarding and relative transaction call order, and tests that dropping/mistyping source ID or erasing forwarding is rejected. It does not import `subprocess`, execute PHP, connect to SQL or perform filesystem writes.

**Important:** The earlier K2/P01 verifier intentionally asserts the *old* missing-parameter source state and belongs to the prior, unchanged preparation branch. Do **not** rerun that historical verifier against the corrected branch as if it were a current runtime test.

Node A requires a separate `/opt/renee-c137-k2-p02-src` source-only worktree, leaving both `/opt/renee-agrisuite-c137-gc` and `/opt/renee-c137-z23-dryrun-src` intact. A guarded independent Node A PASS closes only K2/P02 source-fix verification, **not PHP runtime correctness**.

**Safety:** 150 VU HOLD; 200 VU PROHIBITED. Import and import dry run NOT AUTHORIZED; no new V2 events or database reads/writes.
