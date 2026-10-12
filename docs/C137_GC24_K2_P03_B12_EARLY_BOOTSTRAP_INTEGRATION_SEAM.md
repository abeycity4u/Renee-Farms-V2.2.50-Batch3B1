# C137 GC24 K2-P03 B12 — early-bootstrap JSON authentication integration seam

**2026-10-12 | SOURCE-ONLY / DORMANT. No live bootstrap, login, subscription, billing, entitlement, API, migrations, test-workflow, staging, production, or customer-data changes.**

## Exact source checkpoint and verification

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Isolated branch: `c137-gc24-k2p03-b12-early-bootstrap-seam-source`, forked from `c137-gc24-k2p03-b12-json-auth-response-source` at verified `4231e577cda675de1fa829811db2f16b57ab8966`.
- New shared dormant helper: `includes/b12_auth_early_bootstrap_seam.php`, exact locally verified blob `750c99d26fd3de8c763f8e9e17f33d0648b5534a`.
- Focused offline test: `tests/integration/k2p03_b12_early_bootstrap_seam_pure.php`, exact locally verified blob `807d4cb9a9e26c38db713de313e9fe783c627a2f`.
- Prior pure response owner is unchanged: `includes/b12_auth_response_policy.php`, blob `2bbd275be0cedcdee940b84cd131cc2cd5265866`.
- Local PHP 8.4.24 syntax checks PASS for new helper + new test. New `B12_EARLY_BOOTSTRAP_SEAM_PURE=PASS`, **74/74** offline callback/response checks; previous `B12_AUTH_RESPONSE_POLICY_PURE=PASS`, **66/66** checks. No PHP session, database connection, network call, browser, HTTP response or GitHub Actions execution was needed.

## Actual observed legacy bootstrap behavior

1. In `config.php`, session parameters are established and `session_start()` occurs before database connection. `SESSION_TIMEOUT=900` causes identity teardown via `session_unset(); session_destroy();` and redirects to `/login.php?timeout=1`. Later code updates `LAST_ACTIVITY`, then creates a PDO connection. Its PDO connection failure currently ends with plain-text `die()`, an additional JSON-transport gap not resolved here.
2. `config.php::requireLogin()` checks the session and then uses the existing canonical tenant-scoped user/farm/role SQL to refresh user role/name and block deleted or suspended/cancelled accounts. Existing failure responses are HTML redirects, some after session teardown. **Do not duplicate or bypass this query.**
3. `requireCurrentFarmId()` destroys an invalid farm identity and redirects to `/sign.php`. The current API adapter resolves the farm from the authoritative session, not from any `$_GET` or `$_POST`.
4. `init.php` includes `includes/farm_entitlement_runtime.php`, which executes `subscription_lifecycle_request_guard()` **during bootstrap**, possibly before the individual endpoint calls `requireLogin()`. The guard calls `subscription_lifecycle_refresh_farm()`, which can perform a guarded transaction, update farms from active/trial to past_due and record billing history. It then enters restricted Farm Admin recovery via `subscription_recovery_start()` with session ID rotation, or clears normal/recovery identity; both routes redirect/exit. **These billing/session mutations must remain authoritative and cannot be duplicated in response formatting.**
5. `includes/farm_entitlement_runtime.php` denies modules according to established entitlement/role boundaries, with existing GET redirect or non-GET HTTP 403. Permission closures and the future adapter enforce later View/Edit boundaries. Current `init.php` also bootstraps legacy API permission bridges; they do not automatically register the future B12 API.
6. `api/api_helpers.php::send_json()` is the established JSON/no-store response writer. Do not introduce new header/encoding logic or direct HTML output in the future B12 route.

## What the new seam does, and does NOT do

`b12_auth_early_bootstrap_event_site(event)` maps each of eight already-defined denial events to its **canonical guard owner**: config session timeout/login/current farm, subscription lifecycle request guard, entitlement runtime module guard, or future Daily Record API permission guard. Unknown events throw before any callbacks.

`b12_auth_early_bootstrap_denial(scriptPath, method, event, canonicalSite, completeCanonical, emitPlan)` tests a future **decision / existing side-effects / transport** separation:

- Validates that a canonical event originated from the mapped guard site.
- Obtains a safe presentation plan using the previously verified `b12_auth_response_plan()` (which recognizes ONLY the exact future B12 endpoint for JSON).
- Calls a **canonical completion callback first**. Only after it explicitly returns `true` does it call the **response-emission callback**. An exception, false completion, wrong guard, unknown event or malformed method prevents emission.
- Does not decide whether the user is active, expire a session, start billing recovery, authorize a farm, query a database, output HTML/JSON, set headers, or terminate execution. The future server implementation must use existing services for all of those operations.
- The passing 74 offline checks use injectable callback traces to confirm ordering, all eight outcomes, unchanged HTML response choice, unchanged legacy API choice, wrong-site rejection, and no emission on a failed teardown/recovery operation. **They do not prove actual session teardown or billing side effects occurred.**

**Do not wire the new seam into existing guards as-is.** Their present `header()/exit()` calls cannot return from a completion callback; a coordinated shared refactor that separates canonical security decisions and business side effects from response termination is required. The test callbacks demonstrate ordering only and must NEVER stand in for actual permission or account validation.

## Future integration sequence — a design plan, not permission to execute

1. **Source-only next:** inspect all call sites of `requireLogin()`, `requireCurrentFarmId()`, the `SESSION_TIMEOUT` block, `subscription_lifecycle_request_guard()`, `subscription_lifecycle_redirect()`, and `subscription_recovery_start()`; define minimal **shared** decision/response seam with exact-route allowlist registered before bootstrap early termination. Preserve existing HTML behavior for every other route and require canonical completion before emitting JSON.
2. Account validity/role freshness must continue using the existing user/farm/role query. A prior session-expiry check must still clear identity; subscription transitions must keep their original transactions and append-only history. Farm Admin restricted billing recovery must preserve regenerated session ID and cleared normal identity. Non-admin past-due access remains denied.
3. Audit the PDO-connect-error path and early subscription guard exceptions: existing plain-text/redirect/exit outcomes must fail closed; decide an approved JSON-safe boundary without weakening subscription or auth checks. Prevent repeated response emission and route mismatch.
4. Only **after separate authorization** for migration, real legacy data audit, and all CAS writers/browser wiring should the future HTTP endpoint be activated. A response transport contract does not protect a Daily Record UPDATE/DELETE.
5. Future isolated MariaDB, real browser and competing-writer certification need separately approved one-use execution(s); no rerun of original failed B12 CI run without explicit consent. Remote GitHub semantic diff must accompany every source push.

## Release and evidence restrictions

- Prior disposable MariaDB **schema-only** R2 `38301126240`: **PASS**, authorization consumed.
- Original concurrent stale-write B12 `38233373699`: **FAILED/TWO_STALE_COMMITS**, authorization consumed; no recertification.
- Actual live legacy duplicate/cross-farm-cycle counts **UNKNOWN**; no actual tenant database queried.
- No revision migration `092` authored or run; no source CAS activated; D12 **15 import gates BLOCKED**, Stage D **UNAUTHORIZED**, 150 VU **HOLD**, 200 VU **PROHIBITED**.
- Production, staging, AWS nodes, cPanel, protected fixture DBs and customer data remain out of scope.

**One fix, fit it all:** one shared response presentation policy and explicit owner sequencing; canonical account, subscription, entitlement, permission, inventory, poultry and financial rules remain centralized and unchanged.
