# B12 Malformed authentication state — source-only policy

**2026-10-13. Inactive contract, no runtime integration.**

## Source lineage
- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Isolated branch: `c137-gc24-k2p03-b12-malformed-auth-state-source`, forked from `b82d71f9d2496ee27ae328887435e8d940eac715`.
- Added dormant helper: `includes/b12_auth_malformed_state_policy.php`.
- Added offline pure test: `tests/integration/k2p03_b12_malformed_auth_state_pure.php`.
- No live `config.php`, account SQL, billing lifecycle, auth, API, CI workflow, migrations, or deployment changes.

## Decided contract
A malformed server-side `LAST_ACTIVITY` timestamp (invalid type, ambiguous decimal, negative, overflow) or a future timestamp produces the existing canonical `invalid_account` event. The canonical account/session guard must first clear the normal identity **before** delegating to the existing JSON/HTML response plan. This is an intentional stricter policy, **not equivalent** to all legacy weak-coercion behaviors.

Missing or incomplete authoritative account projections likewise map to `invalid_account`. Valid projection status `past_due` remains with `subscription_lifecycle_request_guard()`; this classifier never bypasses restricted Farm Admin billing recovery or duplicates subscription queries.

The helper has **no session, DB, header, exit, permission, or billing side effects** and is not imported by live files. Exact input origin must be a canonical, existing server session or the result of the established tenant-scoped `requireLogin()` lookup; it must never accept browser-supplied timestamps, farm IDs, or owner flags.

## Evidence boundary / next step
The test file is committed as source. It must be run with isolated PHP using `php -l` on both new files and `php tests/integration/k2p03_b12_malformed_auth_state_pure.php` before claiming a successful regression verification. Compare the actual output and pin tested file blobs.

Next, source-review a unified `config.php` integration design showing exactly how malformed state triggers existing session teardown, current user/farm validation, and format-specific output while preserving all existing HTML routes and subscription recovery. **Do not activate** without broader isolated account/session/billing integration evidence.

Original B12 concurrency workflow `38233373699` remains FAILED. R2 synthetic schema `38301126240` remains PASS. D12 fifteen gates BLOCKED; Stage D UNAUTHORIZED, 150VU HOLD, 200VU PROHIBITED. Protected systems untouched.
