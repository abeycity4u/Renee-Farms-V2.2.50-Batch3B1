# Renee AgriSuite v3.2 — GA-1 Architecture & Attack-Surface Inventory

## Scope

This document records the GA-1 source review of `v320-ga-hardening`, created from protected baseline commit `10041da3a3c4e4a7b8101f8183c243d056c7b128`.

GA-1 does not authorize production mutation, destructive testing, load testing, database migration, or changes to `v320-renee-agrisuite-branding`.

Status vocabulary:

- **PASS** — reviewed source directly supports the control.
- **FINDING** — source supports a concrete defect.
- **REMEDIATED** — a confirmed defect has been corrected on the GA branch with regression coverage.
- **REVIEW** — deeper source/runtime evidence is required before classification.
- **DEFERRED** — staging/runtime/infrastructure evidence is required.

## Branch isolation

- Protected baseline: `v320-renee-agrisuite-branding`
- Baseline commit: `10041da3a3c4e4a7b8101f8183c243d056c7b128`
- Active hardening branch: `v320-ga-hardening`
- GA branch starting commit: exact protected baseline commit above

The baseline remains the known-good fallback and is not a GA work branch.

## Primary attack surfaces

### Public authentication and credential lifecycle

- `/login.php` — canonical sign-in entry / restricted subscription-recovery bridge
- `sign.php` — normal sign-in implementation and form
- `account/activate.php`
- `account/forgot_password.php`
- `account/reset_password.php`
- shared credential lifecycle/delivery helpers

Security boundaries include account-enumeration resistance, password verification/rehashing, throttling, session establishment/regeneration, activation/reset token lifecycle and CSRF.

### Tenant authorization and permissions

Primary authority surfaces include:

- `config.php`
- `includes/permission_catalog.php`
- `includes/permission_runtime.php`
- `includes/functions.php`
- `admin/permissions.php`
- `admin/permissions_save.php`
- route/API gates

Security boundaries include tenant isolation, role/permission applicability, module entitlements, horizontal object access, vertical privilege escalation and navigation/runtime parity.

### API surface

Authenticated/public endpoints under `api/` cover Inventory, expenses, reporting, financial allocation, records and sales lifecycle operations. `api/api_helpers.php` centralizes authentication, permission, method, CSRF, rate-limit and response helpers.

Priority runtime classes remain cross-tenant object IDs, cross-role access, mutation without CSRF, method confusion, exception leakage, replay/stale requests and over-posting.

### Billing/payment

Public provider webhooks/returns and authenticated billing/account routes are high-value boundaries. Reviewed Paystack code uses authenticated provider events, provider-side payment verification, transaction/locking and canonical billing services. Flutterwave and remaining callback/return paths remain in the review queue.

### Sales, Inventory and financial allocation

High-value mutations include sales create/edit/delete, inventory receive/use/restore, stock consumption allocation, sale revenue allocation, expenses and profitability/reporting. Main risks are tenant scope, authorization, financial integrity, reversal/exactly-once behavior and parameter tampering.

### Poultry and ruminant lifecycle

High-value mutations include daily records, cycles, animal registry, slaughter processing, live-population effects and feed consumption. Risks include farm/cycle ownership, impossible transitions, stale object references and cross-farm direct-object access.

### Reports/PDF/export

The application uses Dompdf. Security review covers report authorization, tenant-scoped inputs, output escaping, remote/local resource policy and resource-exhaustion behavior.

## Verified source controls

### Session and login security — PASS

Observed controls include secure-cookie behavior under HTTPS, HttpOnly, SameSite=Lax, strict session mode, cookie-only sessions, inactivity controls, session ID regeneration, prepared login queries, generic failure wording, central password verification/rehashing and timing resistance for nonexistent accounts.

### Password recovery — PASS (source + prior browser certification)

The recovery flow uses generic account-safe responses, rate limiting, CSRF, expiring reset tokens and one-time consumption. The end-to-end browser flow was already certified on the protected baseline.

### CSRF — PASS as central control

CSRF is centrally defined and consumed by browser/API mutations. GA review additionally found and fixed the special subscription-recovery login bridge so that it now validates the shared CSRF token before recovery account lookup/password verification.

### Output/browser controls — PASS as central controls

Central helpers provide HTML escaping, safe JSON-for-script encoding, `nosniff`, frame protection, Permissions-Policy, Referrer-Policy, HSTS on HTTPS and enforcing CSP support.

### Sensitive-path server policy — PASS in source example / DEFERRED live

`.htaccess.example` documents sensitive-path protection. `scripts/.htaccess` denies direct web access to verifier/maintenance scripts. Active production/staging server equivalence still requires runtime evidence.

### Dependency lock/audit — PASS in CI step

The GA workflow validates Composer metadata, installs the committed lock file and runs `composer audit --locked`. Advisory status must remain continuously checked.

## Confirmed GA finding

### GA-SEC-003 — subscription-recovery login CSRF boundary

**Severity:** security boundary defect

**Status:** REMEDIATED ON GA BRANCH

`/login.php` intentionally intercepts farm-login POSTs for suspended/past-due/cancelled Farm Admin billing recovery before falling through to `sign.php`. On the protected baseline, this special path could reach account lookup/password verification before the normal `sign.php` CSRF check.

GA remediation now calls the shared CSRF validator before recovery account lookup. A focused regression contract verifies ordering, dedicated recovery throttling, password-before-session semantics, session regeneration and normal fallthrough.

### Retired false positive: former GA-SEC-001

The earlier assertion that `/login.php` did not exist was incorrect. The route is intentional and required for subscription recovery. It must not be removed or redirected wholesale to `sign.php`; doing so would break the billing-recovery design.

## Hardening debt / review candidates

### GA-CSP-001 — compatibility allowances

CSP retains `unsafe-inline` / `unsafe-eval` compatibility allowances. This is staged hardening debt rather than an automatic defect. Tightening must follow E2E browser coverage.

### GA-SEC-002 / GA-REV-001 — API exception normalization

`safe_api_exception_message()` suppresses PDO/database details but can allow selected non-PDO exception messages through. Caller analysis must distinguish intentional validation errors from internal implementation failures before central behavior is changed.

### GA-ARCH-001 — runtime schema mutation reachability

`includes/functions.php` contains schema helpers and `runSchemaMigrations(PDO $pdo)`. Reachability from normal HTTP requests is not yet proven. If reachable, request-time schema mutation should be retired in favor of explicit deployment migrations.

### GA-REV-002 — endpoint-level tenant ownership

Shared authentication/permission helpers are strong, but object-level handlers still require systematic tenant ownership review and runtime IDOR attempts.

### GA-REV-003 — report/PDF policy

The central PDF service disables remote resources, chroots Dompdf and now returns generic browser errors while logging a non-sensitive class indicator. Remaining runtime work is authorization/resource-exhaustion testing.

## Existing automated assurance

The GA branch contains `.github/workflows/ga-regression.yml` plus focused security/architecture contracts. Current CI includes Composer validation/audit, repository-wide PHP lint, source security scanning, login/recovery contracts, tenant authorization contracts, PDF security contracts, permission/navigation contracts, credential/password-recovery contracts, web-denial policy and repository secret-file policy.

Early scanner false positives are treated as scanner defects when behavior is actually safe; application code is not changed merely to satisfy brittle pattern matching.

## Runtime/infrastructure checks deferred

The following require an isolated staging/runtime environment:

- cross-tenant IDOR with real sessions/object IDs
- browser E2E execution
- DAST/runtime probing
- load/stress tests
- live HTTP header/CSP verification
- active `.htaccess` equivalence
- database least-privilege review
- backup restore/DR drill
- monitoring/alert delivery
- infrastructure patch/version state
- external independent penetration test

## GA-1 exit criteria

GA-1 requires attack-surface inventory, identification of central security authorities, evidence-based source findings, explicit runtime deferrals and a prioritized GA-2 queue.

All criteria are satisfied. **GA-1 is COMPLETE.** GA-2 continues with tenant ownership, exception disclosure, payment boundaries, debug/dev exposure and runtime-migration reachability.
