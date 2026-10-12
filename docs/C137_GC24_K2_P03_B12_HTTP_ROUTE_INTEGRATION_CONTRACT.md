# C137 GC24 K2-P03 B12 — thin HTTP route integration contract (source-only)

**2026-10-12. NOT WIRED, NOT DEPLOYED, NO RUNTIME HTTP ROUTE CREATED. No production, staging, cPanel, AWS, or protected database access.**

## Exact checkpoint and files

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Isolated working branch: `c137-gc24-k2p03-b12-http-route-contract-source`.
- Forked from source-only API adapter branch `c137-gc24-k2p03-b12-api-adapter-source` at pinned HEAD `361c7abcd4fcdc71cfc91e6b971e8996d4f3b746`.
- New **non-routable and deliberately inert** implementation blueprint: `docs/C137_GC24_K2_P03_B12_HTTP_ROUTE_DRAFT.php.txt`, blob `705d39b8e81e9078bdd382da58c15c42363dfb90`.
- New focused PHP-token semantic verifier: `tests/integration/k2p03_b12_http_route_draft_pure.php`, blob `b095281fb3b9c35bbb77b13c1bc1d4602b94309e`.
- PHP 8.4.24 `php -l` PASS for both; new semantic verifier **18/18 PASS** (`B12_HTTP_ROUTE_DRAFT_PURE=PASS`). Prior pure API adapter test **135/135 PASS**, prior exact reader regression **100/100 PASS** in isolated local workspace. This is not a live HTTP or MariaDB integration certification.
- Source guard: draft is a `.php.txt` file outside `api/`, and begins with **unconditional `http_response_code(503); exit;` before any bootstrap**. Even accidental execution must not connect to the database or expose the future route. NEVER simply rename/copy this draft into `api/`.

## What the existing source actually does

- `init.php` bootstraps `config.php`, request-scoped subscription entitlements, legacy permission closures and the legacy API View permission bridge.
- `config.php` starts the PHP session, may redirect to login upon inactivity, and contains `requireLogin()`, which **redirects to HTML login** on missing, deleted or suspended/cancelled account (it also refreshes role/name from the real DB). `requireCurrentFarmId()` likewise destroys a missing-farm session and redirects. These redirects do **not** satisfy JSON status expectations.
- `includes/farm_entitlement_runtime.php` and the subscription lifecycle guard run during bootstrap and can terminate a request before an individual HTTP endpoint responds. The new proposed route is not in their historical route lists, but that is not evidence the subscription lifecycle guard can be ignored.
- `includes/legacy_api_view_permissions.php` explicitly protects older `get_record.php` and `check_record.php` paths. Its path-specific mapping is **not** a security grant for any future route. The new `daily_record_revision_api_adapter.php` independently checks module and per-record permissions; future route MUST preserve the canonical subscription and account boundary.
- `api/api_helpers.php` provides `send_json(array,int)`, setting HTTP status, JSON Content-Type and `Cache-Control: no-store`. Reuse this responder rather than duplicating headers or emitting HTML.
- The current live `api/get_record.php` is date-based and still can select an arbitrary legacy duplicate. It has not been modified.

## Intended future thin HTTP endpoint — design only

Potential path: `api/daily_record_revision_read.php` (NOT PRESENT in this step).

After **separately approved** canonical JSON-capable account/subscription guard, three-table revision migration, nullable-cycle uniqueness policy, writer CAS integration and browser-token integration, the runtime endpoint should:

1. Load the canonical request bootstrap, enforce current subscription/account/session freshness with a **shared JSON-aware security policy** (do not copy account SQL or bypass `requireLogin()` semantics), and ensure an unauthorized request cannot reach operational database reads.
2. Permit only `GET`, pass exact `$_GET` and runtime method to the existing shared `daily_record_revision_api_handle($pdo, $method, $_GET)`; do not accept `farm_id`, hidden client permission flags, or alternate POST fallbacks. No mutation and no CSRF-bearing GET write.
3. Delegate type validation, exact `record_id`, explicit `cycle_id` including intentionally empty NULL, valid date and normalized Ruminant species, 0/1/>1 cardinality, module View/Edit gates, server-resolved farm, and schema readiness to the already tested adapter/reader.
4. Return `send_json($result['body'], $result['http_status'])` with no extra output. Preserve 400/401/403/404/405/409/503 error semantics and prevent raw SQL/tenant/account details in errors. In particular, **scope absent is NOT CREATE authorization**, and exact edit data must include the *browser-loaded* revision only.
5. No business effects should occur in this read endpoint. All later writes require the shared transactional CAS authority and preserved feed, inventory, population, mortality, continuity and Layer egg-sale effects in one transaction.

### STOP: JSON/authentication mismatch must be resolved before activation

Calling the current `requireLogin()` in an HTTP endpoint does not guarantee JSON 401/403: it redirects to login for invalid accounts. An `isLoggedIn()` test placed ahead of `requireLogin()` handles only simple guests, **not** deleted/suspended users, expired sessions, or redirects that can happen inside `init.php` before endpoint code. DO NOT “fix” this by skipping canonical account validation or by copying the account SQL into the API. A separately reviewed, shared JSON-capable authorization boundary must preserve the existing fresh-account, farm, subscription and session protections. Prove these cases with real browser/API tests after separately authorized activation.

The inert `.php.txt` blueprint intentionally makes this blocked integration visible and **cannot be treated as deployable PHP code**.

## Next controlled work and gates

- **Source only next:** review central `requireLogin()` / `requireCurrentFarmId()` and subscription lifecycle response handling to identify a minimal shared JSON-aware policy without changing the behavior of the many existing HTML routes; verify 401/403/redirect interactions using focused off-line test doubles.
- **Separate protected-data authorization:** real read-only legacy uniqueness, cycle-tenant and deployed schema inventory; synthetic R2 run alone does not establish live duplicates. No migration `092`, index replacement, deduplication, or protected DB change has been approved.
- **Separate migration/runtime authorization:** revisions initialized across all three Daily Record tables; coordinated immutable row-ID/loaded-revision browser EDIT, CREATE serialization, shared CAS UPDATE/DELETE and population-continuity revision bumps, transactional feed/mortality/Layer egg-sale side effects.
- **Separate test authorization:** future isolated MariaDB contention and browser E2E evidence; never silently rerun original failed B12 run `38233373699` (`TWO_STALE_COMMITS`). Prior disposable synthetic schema run `38301126240` remains a separate **PASS**.
- D12 15 import gates **BLOCKED**, Stage D **UNAUTHORIZED**, 150 VU **HOLD**, 200 VU **PROHIBITED**. Do not deploy this draft, expose it as an endpoint, or relax release holds.

**Architecture principle — One fix, fit it all:** shared auth/permission services, thin future route, closed shared reader/adapter and browser token owner; no duplicated SQL, authorization, billing, stock, population or financial policy. Remote semantic diff and expected HEAD guard mandatory.
