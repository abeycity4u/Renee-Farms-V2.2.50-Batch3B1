# C137 GC24 K2-P03 B12 — dormant canonical authentication decision extraction

**2026-10-13 — SOURCE ONLY; NOT ACTIVATED.** This step does not alter live `config.php`, `init.php`, login handlers, subscription lifecycle, billing recovery, API endpoints, migrations or CI workflows.

## Verified baseline and new files

- Repo: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Isolated branch: `c137-gc24-k2p03-b12-canonical-auth-decisions-source`.
- Fork SHA: `e758db4e5d0c2de02819d5db5cad24ef8cb76ef1` from `c137-gc24-k2p03-b12-early-bootstrap-seam-source`.
- New dormant shared helper: `includes/b12_auth_core_decisions.php`, Git blob `77d2a038462329950dada89111677f9cb9d58c6f`.
- New focused test: `tests/integration/k2p03_b12_auth_core_decisions_pure.php`, Git blob `67b18530fa56ca5f3968b040fdfe20a5ff547bba`.
- PHP 8.4.24 `php -l` PASS for both; offline pure test `B12_AUTH_CORE_DECISIONS_PURE=PASS`, **39/39** checks. The committed files match exactly the locally tested Git blobs.
- No database connection, customer data, application bootstrap, HTTP requests, actions workflow or deployments were involved.

## Extracted pure decisions (not wired)

1. `b12_auth_core_session_timeout_event(session, now, timeoutSeconds)`: models the **strictly greater than 900-second** inactivity limit currently used by `config.php`. It returns `session_expired` when the server session carries both `user_id` and `LAST_ACTIVITY` and elapsed time exceeds the configured timeout, otherwise null. It rejects malformed timestamps and invalid clock conditions rather than silently casting them.
2. `b12_auth_core_farm_event(canonicalFarmId)`: classifies invalid server-derived farm identity as `farm_invalid`, without accepting client farm inputs or destroying sessions.
3. `b12_auth_core_account_outcome(account)`: takes **the result already returned by the existing tenant-scoped `requireLogin()` SQL**, not a second query; returns fresh `user_type` and `full_name` for the canonical caller to preserve existing session refresh. Missing account and suspended/cancelled non-platform account return `invalid_account`; an established Platform Owner account retains the original bypass. `past_due` is explicitly **not** classified here; it belongs to the canonical subscription lifecycle guard and restricted billing recovery service.

The helper intentionally has no `header()`, `exit()`, `session_unset()`, `session_destroy()`, `subscription_recovery_start()`, PDO queries, token emission, tenancy authorization or POST mutation. It is not imported into active application files.

## Source facts and activation gates

- `config.php` session timeout clears the session and redirects before PDO creation; missing farm identity likewise tears down and redirects; `requireLogin()` refreshes roles from its existing single authoritative SQL query. A decision helper **cannot** replace those side effects.
- `subscription_lifecycle_request_guard()` can transition active/trial farms to `past_due` in a transaction and append history. Eligible Farm Admin recovery uses session rotation and restricted billing identity, while non-admin access is cleared and denied. This step made **no** changes to these decisions or to billing.
- The prior source-only response policy and early-bootstrap seam require **canonical decisions and their required side effects to finish before JSON or HTML emission**. Those files remain dormant. Future shared transport integration needs to preserve the original redirect destinations for all existing pages.
- The pure helper currently accepts the existing account row's projected `is_platform_owner` value in canonical `0`/`1` form. It does not independently prove query tenant/farm scope; that must remain assured by the original SQL and an isolated integration verifier before activation.
- The isolated PHP tests are a design/decision contract, **not** a real database authentication, session-termination, subscription or browser transport certification. No full account lifecycle integration is claimed.

## Next controlled step

Perform a **source-only semantic audit and integration plan** for replacing the specific decision branches in `config.php` with a shared owner, carefully preserving their original session cleanup, account freshness query, and redirect behavior. In parallel document the precise, separate subscription lifecycle transport seam. Do **not** wire this helper into active bootstrap until the full HTML/JSON and account/paid-recovery tests are ready. Do not create speculative database migration `092` or activate the B12 Daily Record route.

Original B12 concurrency run `38233373699` is still **FAILED / TWO_STALE_COMMITS**. The separate disposable schema R2 run `38301126240` is **PASS**, not concurrency certification. D12 15 gates BLOCKED; Stage D UNAUTHORIZED; 150 VU HOLD; 200 VU PROHIBITED. No protected customer/staging/production/AWS/cPanel access authorized.

**One fix, fit it all:** central owner of decisions and responses, shared services, thin routes, focused tests, exact GitHub HEAD guards and remote semantic diff after each source push.
