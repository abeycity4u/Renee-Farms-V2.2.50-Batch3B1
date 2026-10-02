# Renee AgriSuite v3.2 — Security Attack Surface

This document is the source-side attack-surface inventory for `v320-ga-hardening`. It records inspected controls and keeps source evidence separate from runtime evidence.

## 1. Trust boundaries

### Public / unauthenticated

- Sign in (`sign.php` through the canonical `/login.php` bridge)
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

### Platform Owner / privileged

- Tenant/farm administration
- Trial onboarding/provisioning
- Commercial product/plan controls
- Cross-tenant management surfaces
- Migrations/maintenance scripts when invoked deliberately

### External services

- MySQL
- Paystack
- Flutterwave
- SMTP provider
- Browser/client session cookie
- Filesystem-backed guest rate-limit bucket

## 2. Authentication and session controls

Source evidence inspected:

- Password verification uses the central password-security service and PHP `password_verify()`.
- Successful normal login regenerates the session identifier.
- PHP session strict mode is enabled.
- Session cookie is HttpOnly.
- SameSite is Lax.
- Secure is set when PHP detects HTTPS.
- Authenticated sessions have inactivity controls.
- Login uses CSRF validation and rate limiting.

### `/login.php` clarification

`/login.php` is intentional. It is the restricted subscription-recovery bridge for Farm Admins whose farm is in a designated billing-recovery state; requests that do not enter recovery fall through to `sign.php`.

The earlier GA-SEC-001 assumption that `/login.php` was missing was a false positive and is retired.

### GA-SEC-003 — subscription-recovery CSRF boundary

During GA source review, the recovery bridge was confirmed to inspect the special Farm Admin recovery login before `sign.php` reached its normal CSRF validation. This meant the recovery credential path did not share the same CSRF boundary as normal sign-in.

**Status:** REMEDIATED ON `v320-ga-hardening`.

The bridge now calls the shared `csrf_validate_request()` before recovery account lookup/password verification. A focused source regression contract requires the CSRF check to precede those operations, while the existing dedicated rate limit, password verification, session regeneration and normal `sign.php` fallthrough remain intact.

## 3. CSRF boundary

- `config.php` remains the low-level token authority for compatibility.
- `includes/csrf.php` is the shared form/request adapter.
- API mutation helpers delegate to the shared validator.
- The subscription-recovery bridge now validates CSRF before its special credential path.
- Sampled critical mutation routes enforce CSRF before mutation.

Endpoint coverage continues to be audited; helper existence alone is not treated as complete proof.

## 4. Tenant isolation / IDOR boundary

Positive source evidence from sampled high-risk routes:

- Sale deletion resolves the current farm and selects the sale with both `id` and `farm_id`, under `FOR UPDATE`, before lifecycle reversal/deletion.
- Inventory stock update resolves the current farm and locks the stock item with both `id` and `farm_id` before using the canonical stock service.
- Reviewed expense, financial-allocation, sale-revenue-allocation, user-management and permission-assignment paths use tenant-aware ownership rules.

This is positive source evidence, not runtime proof that every route is tenant-safe.

### Required staging IDOR classes

For each ID-bearing route/API:

1. Farm A user requests Farm B object ID.
2. Farm A user attempts mutation of Farm B object ID.
3. Lower-privilege tenant role invokes Farm Admin mutation.
4. Sales-only user invokes livestock operation.
5. Tenant user invokes Platform Owner surface.
6. Deleted/suspended user attempts to reuse an old session.

Expected result: no cross-tenant data disclosure or state mutation.

## 5. Billing/payment boundary

### Webhook route

Positive source evidence:

- POST-only handling.
- Session-independent by design.
- Provider event authentication before persistence.
- Provider event registration/de-duplication through billing audit services.
- Webhook payload is not treated as payment authority.
- Fresh provider-side payment verification using the frozen reference.
- Attempt row locking before application.
- Transactionally coordinated payment application/reconciliation behavior.

### Paystack adapter

Positive source evidence:

- HMAC-SHA512 over the raw payload using the configured secret.
- Timing-safe `hash_equals()` comparison.
- HTTPS provider verification rather than trusting callback/query status.

### Remaining billing review

- Flutterwave webhook verification.
- Return/recovery reference ownership and replay behavior.
- Callback URL authority.
- Amount/currency comparison against frozen attempts for every provider.
- TLS verification and bounded provider HTTP timeouts.

## 6. Output / browser security boundary

Source evidence:

- Central HTML escaping uses `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.
- Central JSON-for-script helper uses JSON hex escaping flags.
- `X-Content-Type-Options: nosniff`.
- `X-Frame-Options: SAMEORIGIN`.
- `Referrer-Policy: strict-origin-when-cross-origin`.
- Restrictive Permissions-Policy.
- HSTS when HTTPS is detected.
- Central enforcing Content-Security-Policy support.

Residual hardening debt:

- CSP currently retains compatibility allowances such as `unsafe-inline` / `unsafe-eval`. Tightening must follow browser E2E coverage rather than a blind source edit.

## 7. API error boundary

### GA-SEC-002 — exception-message disclosure candidate

`safe_api_exception_message()` suppresses PDO/database failures but allows selected non-PDO exception messages through.

**Status:** REVIEW CANDIDATE — not yet classified as a vulnerability.

Caller analysis must distinguish intentional domain-validation messages from unexpected implementation exceptions. The target end state is useful allowlisted validation errors plus generic fallback for internal failures.

## 8. PDF/report boundary

The centralized PDF service:

- disables remote resources;
- chroots Dompdf to the application root;
- strips script elements before rendering;
- sanitizes output filenames;
- now logs only the exception class and returns a generic 503 on PDF rendering failure rather than exposing raw exception text.

The previously identified PDF raw-exception disclosure issue is therefore **REMEDIATED ON THE GA BRANCH**. Runtime resource-exhaustion and authorization tests remain staging work.

## 9. Runtime schema mutation boundary

### GA-ARCH-001 — legacy runtime migration candidate

`includes/functions.php` contains schema helpers capable of `CREATE TABLE` / `ALTER TABLE` and a `runSchemaMigrations(PDO $pdo)` entry point.

**Status:** CANDIDATE — reachability audit required.

If reachable in normal HTTP requests, schema mutation should be removed from request handling and retained only in explicit migration/deployment tooling.

## 10. Rate-limit boundary

Observed architecture uses a shared guest/IP bucket with file locking and an authenticated user/session scope. A session fallback exists for availability if the shared guest bucket cannot be used.

Runtime requirements:

- verify the shared temp path is writable;
- monitor failures that force fallback;
- verify client-IP derivation/proxy topology;
- verify forwarded headers are trusted only from known infrastructure.

## 11. Server/file exposure boundary

- Production root `.htaccess` is intentionally untracked.
- `.htaccess.example` documents HTTPS canonicalization, HSTS, disabled indexes, reduced fingerprinting and unsupported-method rejection.
- `scripts/.htaccess` denies direct HTTP access under the current Apache policy.
- `.env`, `.env.*`, logs, root `.htaccess`, and runtime uploads are excluded by repository policy.

Runtime must still verify actual Apache inheritance and production/staging server configuration.

## 12. Dependency boundary

Composer direct dependency:

- `dompdf/dompdf ^3.0`

Current CI-resolved locked graph includes:

- `dompdf/dompdf v3.1.6`
- `dompdf/php-font-lib 1.0.2`
- `dompdf/php-svg-lib 1.0.2`
- `masterminds/html5 2.11.0`
- `sabberworm/php-css-parser v9.4.0`
- `thecodingmachine/safe v3.4.0`

The GA workflow validates Composer metadata, installs the lock file and runs `composer audit --locked`. Advisory status is time-dependent and must remain a recurring CI check.

## 13. Source scanner / CI evidence

The GA source vulnerability scanner is intentionally high-signal. It fails CI for dangerous execution/TLS/request-include patterns and emits lower-confidence matches as review items rather than automatically rewriting code.

False positives discovered in early runs (for example PDO `->exec()` being confused with PHP `exec()`, and an explicitly local/development `display_errors` branch) were corrected in the scanner rather than changing safe application behavior to satisfy a brittle check.

## 14. Remaining attack-surface work

- Flutterwave and remaining payment paths
- Billing return/recover/checkout ownership rules
- File upload/MIME/path handling
- Remaining report/export authorization
- Platform Owner/trial provisioning boundaries
- Read-only API IDOR surfaces
- Public/dev/test route exposure beneath web root
- Runtime proxy/TLS assumptions
- Cross-tenant runtime tests

## 15. Evidence standard

- **PASS (source):** source contract inspected and sufficient for the stated source claim.
- **PASS (runtime):** staging/production-like execution produced direct evidence.
- **CANDIDATE:** concerning source pattern requiring semantic/reachability analysis.
- **CONFIRMED SOURCE DEFECT:** incorrect behavior proven from repository evidence.
- **REMEDIATED:** confirmed defect patched on the GA branch with regression evidence.
- **BLOCKED:** requires staging, infrastructure, provider, or independent external access.

No source-only result is promoted to runtime certification without the corresponding runtime test.
