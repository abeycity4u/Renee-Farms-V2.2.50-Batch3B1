# Renee AgriSuite v3.2 — GA-1 Architecture & Attack-Surface Inventory

## Scope

This document records the read-only GA-1 review of the `v320-ga-hardening` branch created from the protected v3.2 baseline commit `10041da3a3c4e4a7b8101f8183c243d056c7b128`.

GA-1 does not authorize production mutation, destructive testing, load testing, database migration, or changes to the protected `v320-renee-agrisuite-branding` branch.

Status vocabulary:

- **PASS** — the reviewed source provides direct evidence of the control.
- **FINDING** — a concrete issue is supported by source/repository evidence.
- **REVIEW** — an area requires deeper source/runtime testing before classification.
- **DEFERRED** — requires staging/runtime/infrastructure evidence and is intentionally left for the runtime hardening phase.

## Protected baseline and hardening branch

- Protected baseline branch: `v320-renee-agrisuite-branding`
- Protected baseline commit: `10041da3a3c4e4a7b8101f8183c243d056c7b128`
- GA hardening branch: `v320-ga-hardening`
- GA branch starting commit: `10041da3a3c4e4a7b8101f8183c243d056c7b128`

The baseline is treated as a known-good fallback and must remain unchanged during GA hardening.

## Primary attack surfaces

### Public authentication and credential lifecycle

Routes/services reviewed or identified:

- `sign.php`
- `account/activate.php`
- `account/forgot_password.php`
- `account/reset_password.php`
- shared credential lifecycle/delivery helpers under `includes/`

Security boundaries:

- account enumeration resistance
- password verification and rehashing
- login throttling
- session establishment and regeneration
- activation/reset token issuance, expiry, supersession and one-time consumption
- CSRF on state-changing browser requests

### Tenant authorization and permissions

Primary authority surfaces:

- `config.php`
- `includes/permission_catalog.php`
- `includes/functions.php`
- `admin/permissions.php`
- `admin/permissions_save.php`
- route/API permission gates

Security boundaries:

- farm/tenant isolation
- role and permission applicability
- module entitlement enforcement
- horizontal object access (IDOR)
- vertical privilege escalation
- navigation/runtime permission parity

### API surface

The repository contains authenticated and public endpoints under `api/`, including inventory, expenses, reporting, financial allocation, record operations and sale lifecycle endpoints.

Shared API controls are implemented in `api/api_helpers.php`, including login checks, permission checks, request-method enforcement, CSRF validation, JSON responses and no-store headers.

Highest-value API security tests for later stages:

- cross-tenant object IDs
- cross-role access
- state changes without CSRF
- method confusion
- over-posting/mass assignment
- exception/error disclosure
- stale/replayed requests

### Billing and payment callbacks/webhooks

Identified billing attack surfaces include public Paystack callback/webhook routes and authenticated billing/account routes.

`billing/paystack-webhook.php` was reviewed and currently demonstrates the expected security pattern:

- POST-only handling
- secret obtained through environment resolution
- HMAC SHA-512 verification over the raw body
- `hash_equals()` comparison
- generic rejection responses
- tracked-reference validation
- transactional processing
- row lock (`FOR UPDATE`) before finalization
- canonical finalization helper

Runtime replay/idempotency tests remain a staging concern.

### Sales, inventory and financial allocation

High-value mutation surfaces include:

- sale create/edit/delete
- inventory receive/use/restore
- stock consumption allocation
- sale revenue allocation
- expenses
- profitability/reporting

Security concerns include tenant scoping, authorization, financial integrity, exactly-once/reversal behavior, parameter tampering and unauthorized cross-cycle/cross-farm allocation.

### Poultry and ruminant lifecycle

High-value mutation surfaces include:

- daily records
- production cycles
- animal registry
- slaughter processing
- live-population effects
- feed/inventory consumption

Security and integrity concerns include authorization, farm/cycle ownership, impossible population transitions, stale object references and direct-object access between farms.

### Reporting/export/PDF

The application uses `dompdf/dompdf` and contains reporting/export surfaces. Review areas include:

- authorization before report generation
- tenant-scoped query inputs
- reflected/stored HTML content entering generated reports
- local/remote resource handling by PDF generation
- resource-exhaustion behavior for large reports

## Verified source controls

### Session security — PASS

`config.php` centrally configures session behavior, including:

- secure cookie behavior when HTTPS is active
- HttpOnly cookies
- SameSite=Lax
- strict session mode
- cookie-only sessions
- inactivity timeout
- hard lifetime
- periodic session ID regeneration

`sign.php` regenerates the session ID before establishing authenticated session context.

### Login security — PASS

The reviewed login flow uses:

- canonical throttling
- prepared queries
- generic failure wording
- canonical password verification
- password-hash upgrade/rehash support
- timing-resistance behavior for nonexistent accounts
- farm/user active-state checks
- session regeneration before authentication context is written

### Password security — PASS

`includes/password_security.php` centralizes password hashing and verification, preferring Argon2id when available and otherwise using the runtime default algorithm. Password rehashing is supported centrally.

### Password recovery privacy and lifecycle — PASS (source + prior browser certification)

The public recovery flow uses generic account-safe responses, rate limiting, CSRF protection, expiring reset tokens and one-time token consumption. The end-to-end browser flow has previously been certified on the protected baseline.

### CSRF — PASS as central control

CSRF behavior remains centrally defined through the application bootstrap/config path and is consumed by browser/API mutations. Same-origin/request-token checks are present in the reviewed source.

Later GA stages must still test endpoint coverage, not merely helper existence.

### Output and response security — PASS as central control

`includes/output_security.php` centralizes:

- HTML escaping helpers
- attribute escaping
- JSON-in-script encoding helper
- JSON response helper
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- restrictive `Permissions-Policy`
- `Referrer-Policy`
- HSTS on HTTPS
- Content Security Policy emission

### Sensitive-path web-server policy — PASS in example configuration / DEFERRED for live verification

`.htaccess.example` denies direct access to sensitive paths and file classes, including configuration files, environment files, dependency manifests, Git metadata, migrations, vendor internals, documentation, backups, logs and SQL/shell artifacts.

Live production/staging equivalence must be checked separately; the example file is not proof of the active server configuration.

### Payment webhook authentication — PASS in reviewed source

The Paystack webhook uses cryptographic signature validation and transactional/locking behavior as described above.

### Dependency lock and Composer audit — PASS

Both the protected baseline and GA branch contain a committed `composer.lock`. The GA workflow runs `composer validate --strict`, installs the locked graph and executes `composer audit --locked`. The reviewed workflow run completed the Composer validation/install/audit steps successfully.

The lock currently pins `dompdf/dompdf` to a concrete release and therefore provides reproducible Composer resolution from Git. Dependency risk must still be re-audited continuously as advisories change.

## Confirmed GA findings

### GA-SEC-001 — Retired `/login.php` redirects remain in central bootstrap

**Severity:** Medium availability/authentication-control defect

**Status:** OPEN on GA branch

Source `config.php` still redirects tenant session expiry and unauthenticated tenant requests to `/login.php`, while the repository has no root `login.php`; the current public login route is `sign.php`.

Impact:

- expired sessions or `requireLogin()` flows can be redirected to a nonexistent/retired route;
- authentication controls remain protective, but recovery from an unauthenticated/expired state can fail with an incorrect destination;
- this also conflicts with the existing GA source-security rule that detects legacy `/login.php` references.

Required resolution:

- centralize the canonical sign-in route and replace remaining legacy redirects;
- add/retain a regression contract preventing the retired route from returning.

### GA-CSP-001 — Script CSP still allows `unsafe-inline` and `unsafe-eval`

**Severity:** Medium hardening debt

**Status:** OPEN / staged remediation required

`includes/csp_policy.php` currently permits `unsafe-inline` and `unsafe-eval` in `script-src`; `style-src` also permits `unsafe-inline`.

This materially reduces CSP's ability to mitigate XSS. It is not safe to remove these directives blindly because existing UI code may depend on inline script/style execution.

Required resolution:

- inventory inline scripts/styles and dynamic-eval dependencies;
- introduce nonce/hash/external-script migration where practical;
- remove `unsafe-eval` first if no runtime dependency requires it;
- tighten `unsafe-inline` only after staging browser regression coverage exists.

## Review candidates — not yet classified as vulnerabilities

### GA-REV-001 — API exception normalization coverage

`api/api_helpers.php` includes `safe_api_exception_message()`, which suppresses common database/internal patterns but may return other exception messages to callers.

Next review step:

- enumerate all call sites;
- identify which exception types/messages can contain internal identifiers, paths, SQL fragments, tenant context or other sensitive details;
- only then decide whether the helper requires stricter allow-list behavior.

### GA-REV-002 — Endpoint-level tenant ownership enforcement

Shared permission/login helpers exist, but GA-2 must verify that object-level handlers also scope every record by the authenticated farm/tenant rather than authorizing only by role/permission.

Priority objects:

- sales
- inventory items and ledger movements
- expenses
- production cycles
- poultry daily records
- ruminant animals/records
- financial allocations
- users/permissions
- billing/account resources

### GA-REV-003 — PDF/report input and resource policy

`dompdf/dompdf` is present and reporting surfaces exist. GA-2/GA-3 must verify tenant authorization, HTML escaping/sanitization, remote-resource configuration and resource limits around PDF generation.

## Existing automated assurance discovered during GA-1

The branch already contains `.github/workflows/ga-regression.yml` and `scripts/ga_source_security_contract.php`.

The security contract currently checks for several source-level regression classes, including:

- legacy `/login.php` route references
- authenticated APIs missing login enforcement
- mutating APIs without CSRF guards
- password hashing outside canonical helpers
- raw JSON error leakage
- mutation without a POST/method guard
- `display_errors` exposure

There is also a focused login-security contract covering session regeneration, throttling, generic errors, password upgrade behavior and session timeout enforcement.

These assets will be retained and strengthened rather than replaced.

## Runtime/infrastructure checks intentionally deferred

The following cannot be certified from repository source alone and must be executed against an isolated staging/runtime environment:

- cross-tenant IDOR attempts using real sessions and object IDs
- browser E2E execution
- DAST/runtime security probing
- load/stress testing
- live HTTP header/CSP inspection
- active `.htaccess` equivalence
- database least-privilege inspection
- backup restore / disaster-recovery drill
- monitoring and alert delivery
- infrastructure patch/version state
- external independent penetration test

## GA-1 exit criteria

GA-1 can be considered complete when:

1. primary public/authenticated attack surfaces are inventoried;
2. existing central security authorities are identified;
3. confirmed source findings are recorded without speculative classification;
4. runtime-only checks are explicitly deferred rather than assumed;
5. GA-2 receives a prioritized authorization/tenancy review queue.

At the time of this document, all five criteria are satisfied. GA-1 is **COMPLETE**. GA-2 continues with object-level tenant ownership, legacy login-route remediation, exception-disclosure review and PDF/report hardening.
