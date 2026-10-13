# B12 config.php authentication equivalence — source-only checkpoint

**2026-10-13.** Isolated branch `c137-gc24-k2p03-b12-auth-equivalence-offline`, forked from `eb4ed7bd19bed9f12fb89c4c21cb4b5ec36820dc`. No live source/bootstrap, API, DB, CI, migration, or deployment changes.

## Comparator
`tests/integration/k2p03_b12_config_auth_equivalence_pure.php` is an isolated PHP test importing only the dormant `includes/b12_auth_core_decisions.php`. It models the original `config.php` arithmetic, farm-int cast result, and account gate using **source-derived expressions**, not a second account query.

Expected parity cases: timeout elapsed 0/899/900/901/1200 seconds, absent session keys, farm IDs -1/0/1/4, absent account, account active/trial/past_due/suspended/cancelled/unknown, Platform Owner flag 0/1.

Known differences deliberately surfaced: future LAST_ACTIVITY timestamps (legacy subtraction is nonexpiring; helper raises), malformed last activity (PHP runtime arithmetic error vs helper InvalidArgumentException), and absent account projection keys (legacy null defaults/coercion vs helper rejects). These MUST be resolved before active integration; missing/invalid account data cannot be allowed to bypass auth.

**Verification state:** test source committed. Do not mark it executed or PASS until the exact GitHub blob is run with PHP 8.4 and its output captured. The existing prior 39/39 core-decision tests passed on the previous branch, but they do NOT prove this comparator.

## Next exact safe check
Run `php -l tests/integration/k2p03_b12_config_auth_equivalence_pure.php` then `php tests/integration/k2p03_b12_config_auth_equivalence_pure.php` in an isolated local source checkout (no app bootstrap or DB config). Confirm test exits 0 and records >=2 differences; do not silently normalize discrepancies. Next, decide explicit server-side malformed session fail-closed policy and compare actual bootstrap transport using separately authorized isolated runtime. Preserve subscription lifecycle / restricted billing recovery, HTML redirects, farm identity and account query.

B12 concurrent stale-write certification remains FAILED (run 38233373699); synthetic schema-only R2 remains PASS (run 38301126240). D12 blocked; Stage D unauthorized; 150VU HOLD, 200VU prohibited. Protected environments untouched.
