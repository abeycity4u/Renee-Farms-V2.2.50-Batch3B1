# C137 GC24 K2-P03 B12 — centralized JSON-aware authentication response policy

**2026-10-12. SOURCE-ONLY, DORMANT, UNWIRED.** This step did not change `config.php`, `init.php`, `includes/farm_entitlement_runtime.php`, `includes/subscription_lifecycle.php`, `api/` routes, deployment artifacts, migrations, CI workflows or real database state.

## Source checkpoint

- Repo: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Source branch: `c137-gc24-k2p03-b12-json-auth-response-source` forked from `c137-gc24-k2p03-b12-http-route-contract-source` at `f946e576dda8e72bc05442b739f6b7cfc588fb69`.
- New dormant shared response policy: `includes/b12_auth_response_policy.php`, **locally linted/tested exact Git blob** `2bbd275be0cedcdee940b84cd131cc2cd5265866`.
- New pure test: `tests/integration/k2p03_b12_auth_response_policy_pure.php`, **locally linted/tested exact Git blob** `4728b4c7f1aaba24a60adbc36a79ef3f135b175c`.
- Local PHP **8.4.24** syntax checks PASS for both; `php tests/integration/k2p03_b12_auth_response_policy_pure.php` **66/66 PASS** with `B12_AUTH_RESPONSE_POLICY_PURE=PASS`. No application bootstrap, DB access, network, session, CI, browser, request headers or actual HTTP responses involved.
- Separate original B12 concurrent-writer run `38233373699` remains **FAILED / TWO_STALE_COMMITS**, attempt 1 consumed. Synthetic schema-only MariaDB R2 `38301126240` remains **PASS**, attempt 1 consumed.

## What the source review established

These are **existing canonical checks**, not new authorizations created by this policy:

1. `config.php` invokes `session_start()`, checks idle-session `SESSION_TIMEOUT=900`, destroys expired identity and redirects to `/login.php?timeout=1`. Its bootstrap precedes any future route code.
2. `requireLogin()` in `config.php` redirects guests to `/login.php`. For logged-in requests it executes the existing **single canonical** user+farm+role query, refreshes role/name, and rejects missing accounts or suspended/cancelled non-platform accounts by clearing session and redirecting to `/login.php`.
3. `requireCurrentFarmId()` clears invalid-farm identity and redirects to `/sign.php`.
4. `init.php` loads `includes/farm_entitlement_runtime.php`; this invokes `subscription_lifecycle_request_guard()`, which may refresh status and **redirect/exit during bootstrap**. On `past_due`, it starts restricted recovery for an eligible Farm Admin before redirecting to `/billing/recover.php`; otherwise clears normal/recovery identity and redirects to `/sign.php`.
5. The existing entitlement runtime denies unentitled modules. Legacy API View permissions and granular `hasPermission()` are separate boundaries. A new B12 API path is **not** automatically covered by the legacy route map.
6. The existing `api/api_helpers.php::send_json()` owns JSON response headers, `Cache-Control: no-store`, safe encoding and response status. The new policy never duplicates that emission.

## New response contract (pure; no session or business-policy mutations)

`b12_auth_response_is_json_route(string $scriptPath): bool` recognizes **only** the proposed `/api/daily_record_revision_read.php`, optionally nested beneath safe app directory segments. A path, `Accept` header, or GET flag cannot opt some other page or API into JSON. The proposed endpoint does not currently exist.

`b12_auth_response_event_policy(string $event): array` accepts a closed set of canonical **already-decided** denial events. It maps each event to a safe JSON status/code/message and the observed HTML redirect target. Unknown events fail closed with `InvalidArgumentException`. It does **not** make an account active, decide subscription eligibility, clear identity, authorize edits or start recovery.

`b12_auth_response_plan(string $scriptPath, string $event, string $method='GET'): array` returns a data-only transport plan. For the future B12 route it returns `{transport:json,http_status,body:{success:false,code,error}}`; there is no Location header or tenant data. For existing HTML paths it returns the legacy redirect location/status, or `html_forbidden`/403 for non-GET permission or entitlement denial. It never emits headers, echoes content or exits.

| Canonical denial event | Future B12 JSON response | Existing browser redirect |
|---|---|---|
| `unauthenticated` | 401 `authentication_required` | 302 `/login.php` |
| `session_expired` | 401 `session_expired` | 302 `/login.php?timeout=1` |
| `invalid_account` | 401 `authentication_required` | 302 `/login.php` |
| `farm_invalid` | 401 `authentication_required` | 302 `/sign.php` |
| `subscription_recovery` | 403 `subscription_recovery_required` | 303 `/billing/recover.php` |
| `subscription_expired` | 403 `subscription_access_required` | 303 `/sign.php` |
| `entitlement_denied` | 403 `forbidden` | GET redirects to `/no_access.php`; non-GET 403 |
| `permission_denied` | 403 `forbidden` | GET redirects to `/no_access.php`; non-GET 403 |

Redirect destinations reflect the **reviewed relevant existing handlers or their required integration behavior**, not a blanket guarantee about every legacy page. The policy is intentionally narrower than the general application auth system.

## Critical activation ordering — no wiring was performed

An HTTP route-local guest check does **not** fix early redirects in `config.php` or `subscription_lifecycle_request_guard()`. Later integration must introduce a **central, early-loaded JSON-format decision** before a guard can redirect. Do not disable or skip any of the current session expiry, canonical account DB freshness, subscription status refresh, restricted recovery seeding, tenant identity, module entitlements or granular View/Edit permission checks. Keep their existing ordering and side effects and change **only response transport** for the expressly approved B12 route.

- Account validity remains owned by `requireLogin()`'s canonical user/farm/role query, not duplicated in the response policy or a new route.
- Any `session_unset()`/`session_destroy()` needed by an existing denial must execute **before** returning JSON, as it did before the redirect. Recovery-mode seeding/clearing remains with its canonical billing service.
- No fail-open response when the database is unavailable. Even `config.php` PDO-connect error has an existing plain-text `die()`; a separate reviewed JSON-safe exception/error boundary is needed if strict JSON is to hold for all failure cases.
- Event mapping must be wired only after exact-route registration and exact HEAD guards. Legacy browser routes and existing legacy API route responses must remain unchanged.
- The proposed B12 HTTP route draft remains `docs/C137_GC24_K2_P03_B12_HTTP_ROUTE_DRAFT.php.txt`, a deliberately inert text file starting with 503+exit; **do not deploy it or simply rename it into `api/`**.

## Verification boundary and next step

The 66 offline pure cases cover exact/safe route recognition, all eight event classifications, HTTP status, safe JSON envelope, lack of redirects/tenant IDs in JSON, existing HTML redirect paths, non-GET deny, and invalid event/method rejection. **They do not certify** early-bootstrap headers, PHP session teardown, recovery transitions, entitlement/database behavior or browser fetch response format. Those require further narrowly scoped source contracts and separately authorized disposable runtime tests.

**Next controlled source-only step:** design an *early-bootstrap integration specification and test seam* for `config.php` + `subscription_lifecycle_request_guard()` that preserves canonical account and recovery decisions while selecting response format. Review special billing recovery scenarios, current subscription lifecycle writes, login redirect destinations and integration tests before any live bootstrap modification.

Further gates: separately authorized actual **read-only** legacy data/schema audit; cycle uniqueness and `ON DELETE SET NULL` strategy; guarded three-table `record_revision` migration; all writer CAS, continuity revision increments, financial/feed/population reversal invariants; disposable DB/browser concurrent-writer recertification. **No migration 092 created, no protected environment touched, no original B12 rerun.**

Release holds unchanged: D12 fifteen import gates BLOCKED, Stage D UNAUTHORIZED, 150 VU HOLD, 200 VU PROHIBITED. **One fix, fit it all** — centralized response presentation; canonical authentication and business decisions remain shared, not copied into pages or APIs.
