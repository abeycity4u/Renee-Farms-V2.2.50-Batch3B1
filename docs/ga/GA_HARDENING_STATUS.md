# Renee AgriSuite v3.2 GA Hardening Status

## Branch policy

- Protected known-good baseline: `v320-renee-agrisuite-branding`
- Protected baseline SHA: `10041da3a3c4e4a7b8101f8183c243d056c7b128`
- Active hardening branch: `v320-ga-hardening`
- The GA hardening branch was created from the exact protected baseline SHA.
- GA hardening changes must not be written directly to the protected baseline branch.
- Destructive, load, disaster-recovery, and active security testing must use an isolated staging environment where practical.

## GA workstreams

| Stage | Scope | Status |
|---|---|---|
| GA-1 | Architecture and attack-surface inventory | IN PROGRESS |
| GA-2 | Internal security assessment: tenancy, authorization, CSRF, XSS, injection, sessions, tokens, payment boundaries | NOT STARTED |
| GA-3 | Source and dependency vulnerability scan | NOT STARTED |
| GA-4 | Automated regression foundation / CI | NOT STARTED |
| GA-5 | Critical-path browser E2E suite | NOT STARTED |
| GA-6 | Staging environment certification | EXTERNAL RUNTIME REQUIRED |
| GA-7 | Load/performance testing | EXTERNAL RUNTIME REQUIRED |
| GA-8 | Backup restore / disaster-recovery drill | EXTERNAL RUNTIME REQUIRED |
| GA-9 | Monitoring and operational readiness | PARTLY REPOSITORY / PARTLY RUNTIME |
| GA-10 | API, architecture, release, and change-control documentation | NOT STARTED |
| GA-11 | Formal production-readiness review | NOT STARTED |
| GA-12 | Independent third-party penetration test | EXTERNAL INDEPENDENT PARTY REQUIRED |

## GA-1 evidence gathered

### Authentication and session baseline

Observed in the current hardening branch:

- Password verification uses PHP `password_verify()`.
- Successful normal login regenerates the session identifier.
- Session strict mode is enabled.
- Session cookie is HttpOnly.
- Session cookie uses SameSite=Lax.
- Session cookie is marked Secure when the request is detected as HTTPS.
- Authenticated sessions have a 15-minute inactivity timeout.
- Login and recovery surfaces use CSRF validation and rate limiting.
- Password recovery uses neutral responses to reduce account enumeration.

### CSRF architecture

- Low-level token generation and verification remain in `config.php` for legacy compatibility.
- `includes/csrf.php` is a shared request/form adapter rather than a second token authority.
- API mutation helpers use the shared CSRF request validator.

### Tenant and mutation boundaries sampled

The following high-value mutation paths were sampled during GA-1:

- Sale deletion resolves the current farm, looks up the sale by both `id` and `farm_id`, locks the row, and performs downstream lifecycle reversals inside a database transaction.
- Inventory stock updates resolve the current farm, lock stock items by both `id` and `farm_id`, enforce permission checks, validate movement input, and use the canonical stock service.

These samples are positive evidence, not proof that every route is tenant-safe. GA-2 must inventory and test all object-ID mutation/read surfaces.

### Rate-limit architecture

- Guest rate limits use a server-side IP-keyed bucket stored outside the PHP session so changing PHPSESSID does not normally reset guest throttles.
- The bucket uses file locking for concurrent updates.
- An authenticated request uses an inexpensive user/session scoped counter.
- If the shared guest bucket cannot be written, the implementation falls back to session-based throttling; this is an availability-first fallback and should be recorded as a residual security consideration.

### Browser security headers

The application source emits:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Referrer-Policy: strict-origin-when-cross-origin`
- restrictive Permissions-Policy
- HSTS when HTTPS is detected
- an enforcing Content-Security-Policy through the centralized CSP helper

The production `.htaccess` is intentionally not stored in the repository. `.htaccess.example` contains HTTPS canonicalization, HSTS, reduced fingerprinting, disabled directory indexes, unsupported method rejection, and secret placeholders. Runtime verification is still required because the example file cannot certify production server configuration.

### Output encoding

`includes/output_security.php` provides central helpers for:

- HTML text/attribute escaping using `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`;
- safe JSON embedding in script blocks using JSON hex escaping flags.

GA-2 must verify that high-risk output surfaces actually use these helpers or equivalent escaping consistently.

### Dependency surface

Composer currently declares one direct package family: `dompdf/dompdf ^3.0`. The lock file currently pins `dompdf/dompdf v3.1.6` and its transitive packages. GA-3 must run an actual advisory audit in CI/staging; package versions alone are not treated as proof of vulnerability status.

## Preliminary architecture observations

### Positive

1. Authentication, CSRF, tenancy, permissions, stock lifecycle, sale lifecycle, billing, and credential delivery increasingly resolve through shared authorities instead of page-local policy.
2. Sensitive sampled mutation endpoints use prepared statements, explicit farm scoping, transactions, row locks, CSRF, rate limits, and permission checks.
3. Credential recovery/activation uses centralized token and delivery services with single-use/expiry behavior already browser-certified before GA hardening.
4. CSP is centralized and enforcing despite the retained historical helper name `app_emit_csp_report_only_header()`.

### Items requiring GA-2 verification

1. `safe_api_exception_message()` currently returns the message of any non-PDO `Throwable` to the caller. This may be intentional for validation exceptions, but it creates a possible information-disclosure boundary if an internal exception message reaches it. Do not change it until all callers and expected validation messages are traced.
2. `includes/functions.php` still contains legacy runtime schema helper functions capable of `ALTER TABLE` / `CREATE TABLE`. Confirm whether `runSchemaMigrations()` is reachable during normal web requests. Runtime schema mutation should be retired from request paths if still active.
3. Session cookie `Secure` depends on PHP's HTTPS detection. Confirm the production proxy/TLS topology so HTTPS cannot be terminated upstream while PHP sees a non-HTTPS request and emits a non-Secure session cookie.
4. Guest rate limiting falls back to a per-session bucket if the shared temp directory is unavailable. Confirm the production temp path is writable and monitored so an attacker cannot bypass IP throttling by forcing/finding this fallback state.
5. Audit payment-provider callback/verification entry points and signature/reference validation before GA.
6. Inventory all public/dev/verification scripts reachable beneath the web root and confirm they are either non-production, authenticated, or server-blocked.

## Evidence rule

A workstream moves to PASS only when there is evidence appropriate to the claim. Source inspection may certify a source contract, but it does not certify staging/runtime behavior. Load, disaster recovery, active DAST, and independent penetration testing remain blocked until the required isolated environment or external party is available.
