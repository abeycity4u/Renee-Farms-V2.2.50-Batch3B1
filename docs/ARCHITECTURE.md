# Renee AgriSuite v3.2 Architecture

## Purpose

Renee AgriSuite is a multi-tenant farm operations SaaS platform covering Poultry, Ruminant and Sales-only workspaces. The application combines operational records, inventory/stock ledgering, sales/receivables, expenses, profitability, billing/subscription management, permissions and account lifecycle management.

This document describes the source architecture on the `v320-ga-hardening` branch. It is a code-oriented architecture reference, not a deployment-secret document.

## Architectural principles

The current architecture follows these project rules:

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

Central runtime bootstrap and security/session authority. Responsibilities include:

- environment-backed database configuration;
- application timezone;
- base URL resolution;
- security headers / CSP emission;
- PHP session configuration;
- inactivity handling;
- database connection and MySQL timezone alignment;
- current-user/current-farm helpers;
- login/tenant/Platform Owner gates;
- role/module/farm-type helpers;
- common CSRF/session behavior.

`config.php` is intentionally environment-sensitive. Production configuration must never be replaced wholesale from a development copy without preserving deployment-specific authority.

## Tenant model

The tenant boundary is a Farm.

Typical authenticated session context contains:

- `user_id`;
- `farm_id`;
- `user_type` / role-derived authority;
- authentication/session timing metadata.

Tenant-owned queries are expected to include `farm_id` directly or call a canonical service that requires a farm ID.

Platform Owner is a deliberate global exception. Tenant roles must not gain equivalent cross-farm behavior.

## Commercial product and entitlements

Commercial product authority is centralized under the billing/commercial-product helpers.

Current product combinations include:

- Sales-only;
- Poultry;
- Ruminant;
- Poultry + Ruminant.

Sales capability may also be effective for livestock products without becoming an independent duplicate product flag.

Entitlements answer **what the farm has purchased/enabled**. They do not replace user permissions.

## Roles and permissions

Core permission authority is represented by:

- `includes/permission_catalog.php`;
- shared permission resolution in `includes/functions.php`;
- `admin/permissions.php`;
- `admin/permissions_save.php`.

Permission applicability is farm-aware so a permission that is nonsensical for a tenant product is not treated as assignable merely because its code exists.

Navigation (`navbar.php`) consumes canonical permissions but is not itself an authorization boundary. Direct routes/APIs must independently enforce access.

## HTTP/UI organization

### Root pages

Root pages provide shared workspace surfaces such as Dashboard and Inventory.

### `management/`

Cross-domain management/reporting surfaces, including Sales Records, Expenses, Profitability, production-cycle management, user management, billing/refund review and PDF/report endpoints.

### `poultry/`

Poultry-specific operational surfaces including Layer/Broiler daily records, feeds, expenses, health and slaughter processing.

### `ruminant/`

Ruminant-specific operational surfaces including animal registry, daily records, feeds, expenses and slaughter processing.

### `account/`

Public credential lifecycle pages:

- activation;
- forgot password;
- password reset.

### `billing/`

Subscription/payment routes, callbacks/webhooks and billing account workflows.

### `api/`

JSON/async application endpoints. `api/api_helpers.php` supplies shared API gates and response/error behavior.

## Domain service layer

The `lib/` and `includes/` directories contain shared domain/service authorities. Important service families include the following.

### Sales lifecycle

Sales financial records are distinct from physical inventory and livestock population effects.

Canonical sale lifecycle services own edit/delete/reversal behavior so direct page SQL does not independently recreate physical/financial side effects.

### Stock / inventory

Canonical stock services maintain an append-oriented movement ledger and authoritative current balance.

Important invariants:

- stock item ownership is tenant-scoped;
- received and used movements are explicit;
- sale-driven inventory usage is represented by ledger movement rather than an unexplained balance edit;
- corrections/restorations preserve audit history;
- shared/cycle consumption attribution is handled separately from the physical stock movement itself.

### Financial allocation

Shared financial-allocation services attribute revenue/expense/consumption between production cycles without rewriting the underlying source transaction.

The application distinguishes:

- source financial transaction;
- allocation projection;
- allocation revision/provenance history.

### Sale revenue allocation

Manual shared-revenue allocation is a separate contract from automatic allocation such as Layer egg allocation.

Persistence uses tenant-scoped parent sale loading, production-cycle validation, revision history and no-op detection.

### Production cycle services

Cycle services centralize lifecycle, production-entry economics and cycle-aware attribution rather than allowing independent pages to derive competing cycle state.

### Livestock population

Population effects are explicit domain effects. A financial sale does not automatically imply a population exit unless the selected sale/lifecycle contract says that animals physically left the live population.

Slaughter processing similarly owns its physical lifecycle independently from sales revenue creation.

## Authentication and credentials

### Password security

`includes/password_security.php` is the canonical password hashing/verification authority. It prefers Argon2id where available and supports hash rehash/upgrade.

### Login

`sign.php` performs:

- CSRF validation;
- rate limiting;
- tenant/platform account resolution;
- password verification;
- generic authentication failure messaging;
- session ID regeneration before authenticated context is established.

### Activation/reset lifecycle

Credential tokens use the shared credential lifecycle/delivery services. Design invariants include:

- expiring tokens;
- one-time consumption;
- supersession of previous same-purpose tokens;
- account-enumeration-safe reset requests;
- centralized HTML + plain-text email delivery.

## Billing/payment architecture

Payment provider integration is separated from commercial/subscription state.

The Paystack webhook flow includes:

1. raw webhook receipt;
2. HMAC authentication using the provider secret;
3. tracked-reference validation;
4. independent provider/payment verification where required by canonical billing service;
5. transaction/row locking;
6. canonical finalization;
7. immutable subscription/billing history.

Exactly-once behavior and immutable history are release-critical invariants.

## Reporting and PDF

Application-generated PDFs use `includes/pdf/PdfReportService.php` and Dompdf.

Current security/resource policy includes:

- remote resources disabled;
- filesystem chroot to application root;
- report HTML generated only after route authorization/data scoping;
- sanitized output filename;
- generic user-facing failure handling on the GA hardening branch.

Individual report routes remain responsible for tenant authorization and escaping their dynamic content before rendering.

## Security architecture

Central controls currently include:

- strict/session cookie configuration;
- HttpOnly + SameSite session cookie policy;
- session ID rotation;
- inactivity/session lifetime handling;
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
- web denial rules for verifier/migration/internal paths.

CSP still contains compatibility allowances (`unsafe-inline` / `unsafe-eval`) that are tracked as GA hardening debt and must only be tightened after E2E browser coverage exists.

## Data integrity patterns

The codebase uses several recurring integrity patterns:

- PDO prepared statements;
- explicit transactions around multi-row state transitions;
- `FOR UPDATE` on critical parent/balance rows;
- tenant ID carried through reads and writes;
- immutable or append-only history for important billing/allocation events;
- reversal/replacement rather than silent mutation of historical physical movements;
- canonical shared services for domain transitions.

## Automated assurance

GA source regression is defined in `.github/workflows/ga-regression.yml`.

Current pipeline categories include:

- Composer validation;
- locked dependency installation;
- Composer advisory audit;
- PHP lint;
- source-security contract;
- source vulnerability scan;
- login security contract;
- permission/navigation contracts;
- credential/recovery contracts;
- tenant authorization contract;
- PDF security contract;
- protected verifier-directory policy;
- repository secret-file policy.

Runtime E2E, load, DAST and disaster-recovery tests are deliberately separate because they require an isolated deployed environment.

## Deployment model

The current production deployment is cPanel/Apache/PHP/MySQL based. Source and production runtime are separate directories. Production deployment practice uses:

- expected-branch/expected-HEAD guards;
- clean-worktree checks;
- source verification;
- runtime backup;
- controlled file copy;
- PHP lint;
- source/runtime integrity hashes;
- runtime contract verification;
- rollback on failure;
- focused browser QA.

GA hardening must not deploy directly from `v320-ga-hardening` until the release readiness gate authorizes it.

## Runtime assurance still required for GA

Source architecture alone cannot prove:

- cross-tenant IDOR resistance under real sessions;
- runtime browser compatibility;
- actual Apache/server policy equivalence;
- runtime CSP/header behavior;
- payment replay/idempotency under provider-like traffic;
- concurrency/performance limits;
- backup restore correctness;
- monitoring/alert delivery;
- third-party independent penetration results.

Those remain explicit GA staging/external gates rather than assumed passes.
