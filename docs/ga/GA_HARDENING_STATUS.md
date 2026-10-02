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
| GA-1 | Architecture and attack-surface inventory | COMPLETE |
| GA-2 | Internal security assessment: tenancy, authorization, CSRF, XSS, injection, sessions, tokens, payment boundaries | IN PROGRESS |
| GA-3 | Source and dependency vulnerability scan | IN PROGRESS |
| GA-4 | Automated regression foundation / CI | IN PROGRESS |
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

- Password verification uses PHP `password_verify()` through the central password-security helper.
- Successful normal login regenerates the session identifier.
- Session strict mode is enabled.
- Session cookie is HttpOnly.
- Session cookie uses SameSite=Lax.
- Session cookie is marked Secure when the request is detected as HTTPS.
- Authenticated sessions have an inactivity timeout.
- Login and recovery surfaces use CSRF validation and rate limiting.
- Password recovery uses neutral responses to reduce account enumeration.

### Canonical login and subscription-recovery bridge

`/login.php` is intentionally retained. It is not a stale or missing route: it is the restricted subscription-recovery bridge for suspended/cancelled/past-due Farm Admins and falls through to `sign.php` for normal sign-in.

During GA-2 source review, the bridge was found to intercept farm-login POSTs before `sign.php` performed its CSRF validation. This was a real source-level boundary gap on the protected baseline. The GA branch now validates the shared CSRF token before recovery account lookup/password verification and contains a focused regression contract for that invariant.

### CSRF architecture

- Low-level token generation and verification remain in `config.php` for legacy compatibility.
- `includes/csrf.php` is a shared request/form adapter rather than a second token authority.
- API mutation helpers use the shared CSRF request validator.
- The subscription-recovery login bridge now validates CSRF before its special credential path.

### Tenant and mutation boundaries sampled

The following high-value mutation paths were sampled during GA-1/GA-2:

- Sale deletion resolves the current farm, looks up the sale by both `id` and `farm_id`, locks the row, and performs downstream lifecycle reversals inside a database transaction.
- Inventory stock updates resolve the current farm, lock stock items by both `id` and `farm_id`, enforce permission checks, validate movement input, and use the canonical stock service.
- Expense updates, permission assignment, user management, financial allocation and sale-revenue allocation all showed tenant-aware ownership patterns in the reviewed source.

These samples are positive source evidence, not proof that every route is tenant-safe. Runtime cross-tenant IDOR certification remains a staging requirement.

### Rate-limit architecture

- Guest rate limits use a server-side IP-keyed bucket stored outside the PHP session so changing PHPSESSID does not normally reset guest throttles.
- The bucket uses file locking for concurrent updates.
- An authenticated request uses an inexpensive user/session scoped counter.
- If the shared guest bucket cannot be written, the implementation falls back to session-based throttling; this is an availability-first fallback and remains a residual runtime consideration.

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

GA-2 continues verifying that high-risk output surfaces use these helpers or equivalent escaping consistently.

### Dependency surface

Composer currently declares one direct package family: `dompdf/dompdf ^3.0`. The lock file pins its dependency graph. The GA workflow validates Composer metadata, installs from the lock file and runs `composer audit --locked`. Advisory status must continue to be checked on every GA CI run because dependency advisories can change independently of source.

## Current source findings / review queue

### Remediated on GA branch

1. **GA-SEC-003 — subscription-recovery login CSRF boundary.** Baseline `/login.php` could enter the special suspended/cancelled Farm Admin recovery path before the normal `sign.php` CSRF validation. GA branch remediation validates CSRF before recovery account lookup/password verification and adds a focused regression contract.

### Open / under review

1. `safe_api_exception_message()` currently returns the message of some non-PDO `Throwable` instances. Caller analysis is required before deciding whether stricter normalization is necessary.
2. `includes/functions.php` still contains legacy runtime schema helper functions capable of `ALTER TABLE` / `CREATE TABLE`. Confirm whether `runSchemaMigrations()` is reachable during normal web requests.
3. Session cookie `Secure` depends on PHP's HTTPS detection. Confirm the production proxy/TLS topology so HTTPS cannot terminate upstream while PHP sees a non-HTTPS request.
4. Guest rate limiting falls back to a per-session bucket if the shared temp directory is unavailable. Confirm the production temp path is writable and monitored.
5. Continue provider/payment boundary review, including Flutterwave, return/recovery ownership, amount/currency binding and replay/idempotency.
6. Inventory public/dev/verification scripts reachable beneath the web root and confirm they are either non-production, authenticated, or server-blocked.
7. CSP still carries compatibility allowances (`unsafe-inline` / `unsafe-eval`) that should only be tightened after browser regression coverage proves compatibility.

## Automated assurance status

The GA branch already contains a GitHub Actions regression workflow that performs:

- Composer validation;
- locked dependency installation;
- `composer audit --locked`;
- PHP lint across repository PHP source;
- the GA source-security contract;
- focused architecture/security regression contracts;
- verifier-directory HTTP-deny checks;
- repository secret-file policy checks.

The workflow is being extended as GA findings are converted into durable regression contracts rather than one-off checks.

## Evidence rule

A workstream moves to PASS only when there is evidence appropriate to the claim. Source inspection may certify a source contract, but it does not certify staging/runtime behavior. Load, disaster recovery, active DAST, and independent penetration testing remain blocked until the required isolated environment or external party is available.
