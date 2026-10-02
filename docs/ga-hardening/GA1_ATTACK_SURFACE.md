# Renee AgriSuite v3.2 — GA-1 Architecture & Attack-Surface Inventory

Status: COMPLETE AS READ-ONLY INVENTORY
Branch: `v320-ga-hardening`
Baseline: `10041da3a3c4e4a7b8101f8183c243d056c7b128`
Scope: repository/source attack-surface discovery only. No runtime exploit claims are made here.

## 1. Purpose and rules

GA-1 identifies security-relevant surfaces before patching. It does not certify them. A surface marked as having a protection means that protection was observed in source; GA-2 must still prove consistency, tenant binding, direct-route behavior and bypass resistance.

Architecture rule: **One fix, fit it all.** Shared security, tenancy, billing, lifecycle and output policies must stay centralized. Do not replace proven shared authority with page-local patches.

## 2. Application bootstrap / trust anchors

Observed central bootstrap and authority chain:

- `config.php` — environment-backed DB configuration, application timezone, security headers, HSTS on HTTPS, session cookie policy, inactivity timeout, PDO setup, farm identity, role resolution, farm-module checks, login enforcement and CSRF primitives.
- `init.php` — loads centralized entitlement, subscription, CSRF, output-security, password-security, permission-runtime, legacy-authorization, API-view-permission and tenant-guard helpers.
- `api/api_helpers.php` — JSON response policy, HTTP-method enforcement, CSRF wrapper, safe API exception handling and shared rate limiting.

Primary GA-2 question: do all routes actually pass through the appropriate shared authority, or are there direct-route gaps?

## 3. Public / guest surfaces

| Surface | Authority / observed protection | Tenant boundary | Mutation | Sensitivity | GA-2 priority |
|---|---|---|---|---|---|
| `index.php` | public | none | no | low | low |
| `sign.php` | guest; prepared login SQL; guest rate limit; credential-state gate; session regeneration | workspace slug + user farm relation | establishes authenticated session | critical | critical |
| `login.php` | legacy/bridge entry; must be traced to canonical login flow | authentication boundary | session/navigation | critical | high |
| `logout.php` | authenticated session termination surface | current session | destroys session | high | high |
| `account/activate.php` | public token flow using credential lifecycle services | pending/account token binding | activates account / password state | critical | critical |
| `account/forgot_password.php` | public recovery request | privacy-safe identity lookup expected | creates recovery workflow | critical | critical |
| `account/reset_password.php` | public token flow | token/account binding | changes credential | critical | critical |
| `csp-report.php` | public browser-report receiver | none | writes/records security telemetry | medium | medium |
| `billing/webhook.php` | session-independent provider authentication; POST only; provider verification; event registration; transaction | frozen billing attempt / farm commercial state | payment/subscription state | critical | critical |
| `billing/return.php` | provider/browser return flow | billing attempt binding | may reconcile payment state | critical | critical |
| `billing/checkout.php` | authenticated commercial flow | current farm | creates payment attempt | critical | critical |
| `billing/seat_topup_checkout.php` | authenticated commercial flow | current farm | creates seat payment attempt | critical | critical |
| `billing/sandbox_checkout.php` | test/sandbox surface | environment-dependent | billing test mutation | critical if reachable in prod | critical |

No top-level `trial/` directory exists in the baseline tree. Trial/onboarding logic must therefore be traced through management/account/subscription services rather than assumed to live under a dedicated public directory.

## 4. Authentication / session attack surface

Observed baseline controls:

- `session.use_strict_mode=1`.
- HttpOnly session cookie.
- Secure cookie when HTTPS is active.
- SameSite=Lax.
- 15-minute authenticated inactivity timeout.
- `session_regenerate_id(true)` after successful login.
- CSRF token generated with `random_bytes(32)` and compared with `hash_equals`.
- guest login/recovery limiter authority in `api/api_helpers.php`.
- canonical password/security and account-credential lifecycle helpers loaded from bootstrap.

GA-2 must test/prove:

1. session invalidation after reset and credential-state transitions;
2. login and recovery rate-limit bypass resistance;
3. activation/reset one-time use, expiry and supersession;
4. platform-owner versus tenant login isolation;
5. no account enumeration;
6. no credential/token leakage to logs, URLs or errors;
7. logout/session fixation behavior.

## 5. Tenancy / authorization attack surface

Canonical identities and boundaries are spread across central helpers loaded by `init.php`, including:

- farm/session identity in `config.php`;
- farm entitlements and runtime entitlement enforcement;
- permission runtime;
- legacy authorization closure;
- legacy API View permission bridge;
- user-management tenant guard;
- platform-owner selected-tenant read-only support helpers;
- production-cycle view/mutation separation;
- granular expense, receivable and feed permission helpers.

High-value tenant objects to probe in GA-2:

- users / roles / permissions;
- farms / tenant profile;
- poultry cycles and daily records;
- ruminant animals and daily records;
- feed records;
- expenses;
- inventory items/categories/ledger;
- sales / receivables;
- financial allocations;
- reports / PDFs;
- subscription/billing records and attempts.

Required test pattern: valid object ID from Farm A presented by an authenticated Farm B user, for read, edit and delete paths, including direct API requests.

## 6. API surface

`api/` contains shared helpers plus read and mutation endpoints including record checks, stock reads, chart data, General Expense creation/update/delete, Sales deletion, financial allocation updates, sale revenue allocation updates, stock updates and stock-consumption allocation updates.

GA-2 rule for **every `api/*.php` route**:

- authentication required where appropriate;
- current-farm resolution;
- correct permission family/action;
- CSRF for browser-authenticated mutation;
- explicit HTTP method;
- input validation;
- object belongs to authorized tenant;
- parameterized SQL;
- safe JSON/output encoding;
- no exception/SQL/path leakage.

No API is certified merely because `api_helpers.php` exists.

## 7. Billing / commercial attack surface

`billing/` exposes account, checkout, receipt, recovery, return, sandbox checkout, seat reduction, seat top-up and webhook routes.

`includes/` contains a substantial centralized billing architecture: payment foundation/audit state, provider contracts/adapters, provider selection, Paystack/Flutterwave integrations, commercial attempt coordination/disposition/reconciliation, paid-attempt dispatch, pricing/currency policy, refund resolution, seat change handling and subscription records.

Webhook source already shows important controls: POST-only, provider signature/authentication before event persistence, fresh provider payment verification, frozen-attempt lookup, row locking/transactional application, event terminal-state handling and exactly-once dispatcher integration.

GA-2/GA-3 must still test/review:

- signature bypass;
- replay/idempotency;
- provider reference uniqueness;
- amount/currency/plan/farm mismatch;
- callback versus webhook race behavior;
- sandbox reachability in production;
- secret handling;
- immutable commercial history.

Do not mutate historical live billing state for certification.

## 8. Inventory / sales / financial attack surface

Observed primary surfaces:

- root `inventory.php` — large shared inventory workspace;
- `inventory/add_category.php`, `category_list.php`, `delete_category.php` — thin/legacy inventory entry points;
- stock and ledger read/update APIs under `api/`;
- sales lifecycle pages/services and receivables under management/shared services;
- expense allocation, financial allocation, profitability and reporting services/pages.

Security focus:

- tenant predicates on every item/category/ledger/sale/allocation object;
- permission parity between navbar/page/API;
- edit/delete reversals do not allow cross-tenant movement;
- financial-only versus physical stock/population effects remain separated;
- reports/PDFs cannot be used as cross-tenant read paths.

## 9. Poultry attack surface

Observed `poultry/` routes:

- `layers_daily_record.php`
- `broiler_daily_record.php`
- `layer_feeds.php`
- `broiler_feeds.php`
- `expenses.php` plus compatibility expense routes
- `health.php`
- `slaughter_processing.php`

Primary boundaries: farm, poultry entitlement, production cycle, granular permission/action, inventory linkage and livestock population/lifecycle effects.

Highest GA-2 risks: crafted cycle IDs, record IDs, expense/feed IDs, slaughter population effects and direct mutation without the UI permission path.

## 10. Ruminant attack surface

Observed `ruminant/` routes:

- `animal_registry.php`
- `animal_view.php`
- `ruminant_daily_record.php`
- `ruminant_expenses.php`
- `ruminant_feeds_record.php`
- `slaughter_processing.php`

Primary boundaries: farm, ruminant entitlement, species/tag identity, animal ownership, lifecycle state, feed/inventory linkage and granular permission/action.

Highest GA-2 risks: animal/tag IDOR, cross-farm animal mutation, lifecycle exit/slaughter transitions, direct feed/expense mutation and stale object IDs.

## 11. Admin / management attack surface

Observed sensitive management surfaces include:

- `management/farms.php`
- user/role/permission management surfaces;
- `management/platform_tenant_view.php`
- `management/billing_refund_reviews.php`
- expenses / expense allocation;
- poultry cycle management;
- profitability/intelligence/investigation;
- reporting/PDF routes.

Platform-owner selected-tenant support is intended to be read-only and must be specifically tested for mutation escape or session farm-identity confusion.

## 12. SQL / data access

The baseline uses PDO with exception mode and many prepared statements in core auth/tenant code. That is a positive architectural signal, not blanket certification.

GA-3 source scan must enumerate:

- interpolated SQL containing request/session values;
- dynamic identifiers/order clauses;
- UPDATE/DELETE without tenant predicates;
- object lookup by ID without farm binding;
- transaction/row-lock coverage in financial, billing, stock and lifecycle mutations.

## 13. XSS / output attack surface

`init.php` loads a canonical output-security contract and CSP-related helpers. `sign.php` visibly uses `htmlspecialchars` for user-visible error/brand output.

GA-3 must trace user-controlled values in:

- customer and remarks fields;
- animal/tag values;
- category/item names;
- farm/user names;
- reports/PDFs;
- JSON embedded in HTML/JavaScript;
- dynamic HTML attributes.

## 14. Files / PDF / downloads

Composer declares `dompdf/dompdf ^3.0`; `composer.lock` pins `dompdf/dompdf v3.1.6` with transitive PDF/HTML/CSS libraries.

PDF/report routes are a distinct boundary because HTML-to-PDF libraries can interact with remote/local resources depending on configuration. GA-3 must inspect the shared PDF service and every report input for remote-fetch, local-file, path/traversal and untrusted HTML behavior.

Uploads, if any, must be inventoried for path, MIME/extension, executable placement, tenant ownership and download authorization.

## 15. Config / secrets / browser security

Observed baseline:

- DB credentials are environment-backed in source `config.php`.
- security headers: nosniff, SAMEORIGIN, strict-origin-when-cross-origin, Permissions-Policy;
- HSTS emitted on HTTPS;
- cache disabled for application responses;
- centralized CSP helper is loaded.

GA-3 must check repository history/current tree for hardcoded DB, SMTP, Paystack/Flutterwave, API keys, tokens and credentials. Runtime secrets are out of source scope and must not be printed by tests.

## 16. Dependency surface

Direct Composer dependency:

- `dompdf/dompdf ^3.0`

Locked packages observed include at least:

- `dompdf/dompdf v3.1.6`
- `dompdf/php-font-lib 1.0.2`
- `dompdf/php-svg-lib 1.0.2`
- `masterminds/html5 2.11.0`
- `sabberworm/php-css-parser v9.4.0`

GA-3 must use actual advisory evidence (`composer audit`/equivalent in a suitable environment). Do not invent CVEs from version numbers alone.

## 17. Risk-ranked GA-2 test order

### Critical
1. cross-tenant IDOR on users, sales, stock, animals, cycles, expenses, allocations, reports and billing;
2. platform-owner / Farm Admin / manager / Sales Rep vertical privilege escalation;
3. auth/reset/activation token replay or cross-account binding;
4. billing webhook/callback spoofing, replay and mismatch handling;
5. state-changing API CSRF/method/permission/tenant binding;
6. report/PDF cross-tenant reads and dangerous resource loading.

### High
7. stored/reflected XSS;
8. SQL injection/dynamic SQL review;
9. session invalidation/fixation and guest rate-limit bypass;
10. slaughter/lifecycle and stock-movement authorization.

### Medium
11. information disclosure/error leakage;
12. CSP/header/cookie runtime verification;
13. monitoring/audit completeness.

## 18. GA-1 exit criteria

GA-1 is complete when this inventory is accepted as the source map for GA-2/GA-3. It does **not** mean the listed surfaces are secure.

Next stage: **GA-2 — Internal security assessment / tenancy & authorization**, beginning with direct-route cross-tenant and privilege-boundary source tracing before any patch.