# Renee AgriSuite v3.2 — Security Attack Surface

This document is the source-side attack-surface inventory for the v3.2 GA hardening branch. It records what has actually been inspected and separates source evidence from runtime evidence.

## 1. Trust boundaries

### Public / unauthenticated

- Sign in (`sign.php`)
- Account activation (`account/activate.php`)
- Password recovery request (`account/forgot_password.php`)
- Password reset (`account/reset_password.php`)
- Billing provider webhook (`billing/webhook.php`)
- Billing provider return/recovery routes
- Public/static assets

### Authenticated tenant

- Dashboard and reports
- Poultry and ruminant operational pages
- Inventory and stock ledger
- Sales and receivables
- Expenses and profitability
- User/permission administration
- Billing/account management
- JSON/API mutation and read endpoints

### Platform-owner / privileged

- Tenant/farm administration
- Trial onboarding/provisioning
- Commercial product/plan controls
- Cross-tenant management surfaces
- Migrations/maintenance scripts when invoked from CLI

### External services

- MySQL
- Paystack
- Flutterwave
- SMTP provider
- Browser/client session cookie
- Filesystem-backed guest rate-limit bucket

## 2. Authentication and session controls

Source evidence inspected:

- Password verification uses `password_verify()`.
- Successful login regenerates the session identifier.
- PHP session strict mode is enabled.
- Session cookie is HttpOnly.
- SameSite is Lax.
- Secure is set when PHP detects HTTPS.
- Authenticated inactivity timeout is 15 minutes.
- Login uses CSRF validation and rate limiting.

### Finding GA-SEC-001 — stale login redirect target

`config.php::requireLogin()` and the inactivity-timeout path redirect to `/login.php`, while the repository has no `login.php`; the canonical public login route is `sign.php`.

**Status:** CONFIRMED SOURCE DEFECT — remediation required on GA branch.

**Risk:** unauthenticated/expired-session navigation can be sent to a non-existent route instead of the sign-in surface, depending on production server rewrites.

**Runtime dependency:** production rewrite rules could mask this defect, but `.htaccess.example` does not document such a rewrite. The source contract should not depend on an undocumented rewrite.

## 3. CSRF boundary

- `config.php` remains the low-level token authority for compatibility.
- `includes/csrf.php` is the shared form/request adapter.
- API mutation helpers delegate to the shared validator.
- Sampled critical mutation routes enforce CSRF before mutation.

No duplicate token authority was identified in the inspected path.

## 4. Tenant isolation / IDOR boundary

Positive source evidence from sampled high-risk routes:

- Sale deletion resolves current farm and selects the sale with both `id` and `farm_id`, under `FOR UPDATE`, before lifecycle reversal/deletion.
- Inventory stock update resolves current farm and locks the stock item with both `id` and `farm_id` before applying the canonical stock service.
- Both sampled routes require permissions, CSRF, rate limiting, validation, and transactions.

This is evidence of good architecture, not proof that every read/write route is tenant-safe.

### GA-2 required test classes

For each ID-bearing route/API:

1. Farm A user requests Farm B object ID.
2. Farm A user mutates Farm B object ID.
3. Lower-privilege tenant role invokes Farm Admin mutation.
4. Sales-only user invokes livestock operation.
5. Tenant user invokes Platform Owner surface.
6. Deleted/suspended user reuses an old session.

Expected outcome: no cross-tenant data disclosure or state mutation.

## 5. Billing/payment boundary

### Webhook route

`billing/webhook.php` was inspected.

Positive evidence:

- POST only.
- Session independent by design.
- Provider signature/hash is verified before provider event persistence.
- Provider event is de-duplicated/registered through the billing audit layer.
- A webhook payload is not treated as payment authority.
- The route performs a fresh provider-side payment verification using the frozen reference.
- Attempt row is locked before application.
- Verification, refund reconciliation, seat reconciliation, paid-attempt dispatch and event completion are transactionally coordinated.

### Paystack adapter

`PaystackBillingProviderAdapter::verifyWebhook()` was inspected.

Positive evidence:

- Requires a 128-hex-character `x-paystack-signature`.
- Computes HMAC-SHA512 over the raw payload using the configured secret.
- Uses `hash_equals()` for comparison.
- Payment verification uses Paystack's HTTPS verification endpoint rather than trusting callback/query status.

### Remaining GA-2 billing work

- Inspect Flutterwave webhook hash verification.
- Inspect return/recovery routes for reference ownership and replay behavior.
- Confirm checkout callback URLs cannot be attacker-selected.
- Confirm amount/currency comparison against frozen attempt for every provider.
- Confirm provider HTTP transport validates TLS and has bounded timeouts.

## 6. Output / browser security boundary

Source evidence:

- Central HTML escaping uses `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.
- Central JSON-for-script helper uses JSON hex escaping flags.
- Application emits `X-Content-Type-Options: nosniff`.
- Application emits `X-Frame-Options: SAMEORIGIN`.
- Referrer-Policy is `strict-origin-when-cross-origin`.
- Permissions-Policy is restrictive.
- HSTS is emitted when HTTPS is detected.
- CSP is emitted centrally in enforcing mode.

Residual review item:

- CSP currently permits some `unsafe-inline` behavior for compatibility. Treat this as defense-in-depth debt, not an automatic defect. Remove only after affected inline script/style surfaces are migrated and regression-tested.

## 7. API error boundary

### Finding GA-SEC-002 — exception-message disclosure candidate

`safe_api_exception_message()` suppresses PDO/SQLSTATE errors but returns arbitrary messages from other `Throwable` instances.

**Status:** CANDIDATE — not yet a confirmed vulnerability.

Before remediation, trace every caller and distinguish intentional validation exceptions from internal exceptions. The desired end state is an allowlisted validation-error contract plus generic fallback for unexpected internal errors.

## 8. Runtime schema mutation boundary

### Finding GA-ARCH-001 — legacy request-time migration candidate

`includes/functions.php` contains schema helpers capable of `CREATE TABLE` / `ALTER TABLE` and a `runSchemaMigrations(PDO $pdo)` entry point.

**Status:** CANDIDATE — reachability not yet proven.

GA-2 must prove whether any normal HTTP request path calls this function. If reachable, runtime schema mutation should be removed from request handling and retained only in explicit migration/deployment tooling.

## 9. Rate-limit boundary

Observed architecture uses a shared guest/IP bucket with file locking and an authenticated user/session scope. A session fallback exists for availability if the shared guest bucket cannot be used.

Runtime requirements:

- Verify the shared temp path is writable.
- Monitor failures that force fallback.
- Verify client-IP derivation cannot be spoofed through untrusted forwarded headers.
- Verify production proxy topology before trusting X-Forwarded-* headers.

## 10. Server/file exposure boundary

- Repository-root production `.htaccess` is intentionally untracked.
- `.htaccess.example` documents HTTPS canonicalization, HSTS, disabled indexes, reduced fingerprinting and unsupported-method rejection.
- `scripts/.htaccess` denies HTTP access under Apache 2.4 and 2.2 compatibility syntax.
- `.env`, `.env.*`, logs, root `.htaccess`, and uploaded runtime files are excluded by `.gitignore` policy.

Runtime must still verify Apache inheritance and production `.htaccess`; source examples cannot certify server behavior.

## 11. Dependency boundary

Composer direct dependency:

- `dompdf/dompdf ^3.0`

Current lock snapshot inspected:

- `dompdf/dompdf v3.1.6`
- `dompdf/php-font-lib 1.0.1`
- `dompdf/php-svg-lib 1.0.0`
- `masterminds/html5 2.10.0`
- `sabberworm/php-css-parser 8.9.0`

Version inspection is not a vulnerability audit. `composer audit` is required in CI and again on the GA release candidate.

## 12. Remaining attack-surface inventory

GA-1 is not complete until these are mapped:

- Flutterwave adapter and webhook verification
- Billing return/recover/checkout ownership rules
- File upload endpoints and MIME/path handling
- PDF/report generation and remote-resource policy
- Platform Owner/trial provisioning boundaries
- Read-only API IDOR surfaces
- Export/download endpoints
- Any debug/dev/test route beneath web root
- Session termination/cookie invalidation details
- Runtime proxy/TLS assumptions

## 13. Evidence standard

- **PASS (source):** source contract inspected and sufficient for the claim.
- **PASS (runtime):** staging/production-like execution produced evidence.
- **CANDIDATE:** concerning source pattern requiring caller/reachability analysis.
- **CONFIRMED SOURCE DEFECT:** behavior is incorrect from repository evidence without relying on speculation.
- **BLOCKED:** requires staging, infrastructure, provider, or independent external access.

No GA security claim should be upgraded from source-only evidence to runtime PASS without executing the corresponding runtime test.
