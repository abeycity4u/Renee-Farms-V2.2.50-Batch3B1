# Renee AgriSuite v3.2 Architecture

## Purpose

Renee AgriSuite is a multi-tenant farm operations SaaS platform covering Poultry, Ruminant and Sales-only workspaces. The application combines operational records, inventory/stock ledgering, sales/receivables, expenses, profitability, billing/subscription management, permissions and account lifecycle management.

This document describes the source architecture on the `v320-ga-hardening` branch. It is a code-oriented architecture reference, not a deployment-secret document.

## Architectural principles

1. **One fix, fit it all** — shared rules belong in common services/helpers rather than page-local copies.
2. **Thin routes/pages** — HTTP pages and API endpoints coordinate authentication, authorization, input parsing and service calls; domain rules should live below them.
3. **Tenant scope is explicit** — tenant-owned data operations carry the current `farm_id` through lookup and persistence.
4. **Financial truth, physical truth and population truth are distinct** — a sale, stock movement, livestock exit and revenue allocation are related but are not interchangeable events.
5. **Reversals preserve history** — financial/stock lifecycle corrections favor reversal/replacement and immutable history over destructive reconstruction.
6. **Commercial product, entitlement, permission and navigation are separate layers** — product selection determines entitlements; permissions determine user authority; navigation reflects authority but is not the security boundary.
7. **Security policy is centralized** — session, CSRF, password, output-security, permission, credential-token and payment-webhook rules are shared authorities.

## Request/bootstrap layer

### `init.php`

Application entry bootstrap. It initializes the common runtime used by web pages and API routes.

### `config.php`

Central runtime bootstrap and security/session authority. Responsibilities include environment-backed database configuration, timezone/base URL, response security policy, session configuration, inactivity handling, database connection, current user/farm context, login/tenant/Platform Owner gates, role/module helpers and common CSRF/session behavior.

`config.php` is environment-sensitive. Production configuration must never be replaced wholesale from a development copy without preserving deployment-specific authority.

## Tenant model

The tenant boundary is a Farm. Typical authenticated session context contains `user_id`, `farm_id`, role-derived authority and authentication/session timing metadata.

Tenant-owned queries are expected to include `farm_id` directly or call a canonical service that requires a farm ID. Platform Owner is a deliberate global exception; tenant roles must not gain equivalent cross-farm behavior.

## Commercial product and entitlements

Commercial product authority is centralized under billing/commercial-product helpers. Current combinations include Sales-only, Poultry, Ruminant and Poultry + Ruminant.

Sales capability may also be effective for livestock products without becoming an independent duplicate product flag. Entitlements answer **what the farm has purchased/enabled**; they do not replace user permissions.

## Roles and permissions

Core authority is represented by `includes/permission_catalog.php`, `includes/permission_runtime.php`, shared resolution in `includes/functions.php`, and the permissions administration routes.

Permission applicability is farm-aware so a permission that is nonsensical for a tenant product is not assignable merely because its code exists. Navigation consumes canonical permissions but is not itself an authorization boundary; direct routes/APIs must independently enforce access.

## HTTP/UI organization

- Root pages provide shared surfaces such as Dashboard and Inventory.
- `management/` contains cross-domain management/reporting, Sales, Expenses, Profitability, cycles, users and reports.
- `poultry/` contains Layer/Broiler daily operations, feeds, health, expenses and slaughter processing.
- `ruminant/` contains animal registry, daily records, feeds, expenses and slaughter processing.
- `account/` contains public activation/forgot-password/reset-password pages.
- `billing/` contains subscription/payment routes, callbacks/webhooks and billing account workflows.
- `api/` contains JSON/async application endpoints, using `api/api_helpers.php` for shared gates/response behavior.

## Domain service layer

The `lib/` and `includes/` directories contain shared domain/service authorities.

### Sales lifecycle

Sales financial records are distinct from physical inventory and livestock population effects. Canonical lifecycle services own edit/delete/reversal behavior so routes do not independently recreate physical/financial side effects.

### Stock / Inventory

Canonical stock services maintain append-oriented movements and authoritative balances. Important invariants include tenant ownership, explicit receive/use movements, sale-driven ledger movement, reversible corrections, and separate cycle-consumption attribution.

### Financial allocation

Shared allocation services attribute revenue/expense/consumption between production cycles without rewriting the underlying source transaction. Source transaction, allocation projection and revision/provenance history remain distinct.

### Sale revenue allocation

Manual shared-revenue allocation is separate from automatic allocation such as Layer egg allocation. Persistence uses tenant-scoped parent loading, production-cycle validation, revision history and no-op detection.

### Production cycle services

Cycle services centralize lifecycle, production-entry economics and cycle-aware attribution rather than allowing independent pages to derive competing state.

### Livestock population

Population effects are explicit domain events. A financial sale does not automatically imply population exit unless the selected lifecycle contract says animals physically left live population. Slaughter owns its physical lifecycle independently from sales revenue creation.

## Authentication and credentials

### Password security

`includes/password_security.php` is the canonical password hashing/verification authority. It prefers Argon2id where available and supports hash rehash/upgrade.

### Canonical login entry

`/login.php` is the canonical public login target. It is intentionally a thin bridge rather than a duplicate login implementation.

For ordinary login requests it falls through to `sign.php`. For an eligible Farm Admin whose farm is in a designated billing-recovery state, it may establish a restricted billing-only recovery session. The bridge uses the shared CSRF validator before recovery account lookup/password verification, retains dedicated throttling and relies on the shared recovery service for session regeneration/authorization.

`sign.php` owns the normal sign-in implementation and form, including CSRF validation, throttling, tenant/platform account resolution, central password verification, generic failure messaging and session ID regeneration before authenticated context is established.

This separation is deliberate: `/login.php` must not be removed as a stale route, and `sign.php` must not duplicate billing-recovery policy.

### Activation/reset lifecycle

Credential tokens use shared credential lifecycle/delivery services. Invariants include expiring tokens, one-time consumption, same-purpose supersession, account-enumeration-safe reset requests and centralized multipart HTML/plain-text email delivery.

## Billing/payment architecture

Provider integration is separated from commercial/subscription state.

The payment architecture uses authenticated provider events, server-side payment verification, frozen provider references/quotes, database transactions/row locking, canonical paid-attempt dispatch and immutable billing/subscription history.

Shared HTTP transport restricts billing provider requests to HTTPS and known provider hosts, enables TLS peer/host verification, disables redirects and applies bounded timeouts/response size. Paystack uses HMAC-SHA512/timing-safe webhook verification; Flutterwave uses its configured verification hash with timing-safe comparison.

Exactly-once behavior and immutable history are release-critical invariants.

## Reporting and PDF

Application PDFs use `includes/pdf/PdfReportService.php` and Dompdf. Current policy includes remote resources disabled, filesystem chroot to the application root, authorized/scoped report construction, sanitized filenames and generic user-facing PDF failure responses on the GA branch.

Individual report routes remain responsible for tenant authorization and escaping dynamic content before rendering.

## Security architecture

Central controls include:

- strict/session cookie configuration;
- HttpOnly + SameSite session cookie policy;
- session ID rotation and inactivity/lifetime handling;
- CSRF validation;
- role/permission/entitlement enforcement;
- tenant-scoped object access;
- output escaping helpers;
- CSP and response security headers;
- login/request rate limiting;
- password hashing/rehash policy;
- credential-token expiry/supersession/consumption;
- payment webhook authentication;
- safe API exception normalization;
- repository secret-file policy;
- web denial rules for verifier/internal paths.

CSP still contains compatibility allowances (`unsafe-inline` / `unsafe-eval`) tracked as GA hardening debt and gated on E2E coverage before tightening.

## Data integrity patterns

Recurring patterns include PDO prepared statements, explicit transactions, `FOR UPDATE` on critical state, tenant IDs carried through reads/writes, immutable/append-oriented history, reversal/replacement semantics and shared services for domain transitions.

## Automated assurance

GA regression is defined in `.github/workflows/ga-regression.yml`. Pipeline categories include Composer validation/audit, repository-wide PHP lint, source-security and source-vulnerability scanning, reachability analysis, login/recovery contracts, permission/navigation contracts, credential/recovery contracts, tenant-authorization and PDF contracts, verifier-directory web-denial policy and repository secret-file policy.

Runtime E2E, load, DAST and disaster-recovery testing are deliberately separate because they require an isolated deployed environment.

## Deployment model

Production is cPanel/Apache/PHP/MySQL based. Source and runtime are separate directories. Deployment practice uses expected branch/HEAD guards, clean-worktree checks, source verification, backup, controlled copy, lint, source/runtime integrity hashes, runtime verification, rollback and focused browser QA.

GA hardening must not deploy directly from `v320-ga-hardening` until the release-readiness gate authorizes it.

## Runtime assurance still required for GA

Source architecture alone cannot prove cross-tenant IDOR resistance under real sessions, runtime browser compatibility, actual web-server policy, live security headers, payment replay behavior, performance/concurrency limits, backup restore correctness, monitoring/alert delivery or independent third-party penetration results.

Those remain explicit staging/external gates rather than assumed passes.
