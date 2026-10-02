# Renee AgriSuite v3.2 — GA-2 Authorization & Security Source Review

## Scope

GA-2 is a source-level review of tenant isolation, privilege boundaries and high-value mutation/read paths on `v320-ga-hardening`. It does not claim runtime IDOR certification; active cross-tenant tests remain reserved for isolated staging.

Reviewed evidence includes authentication/recovery boundaries, shared API controls, sale/inventory/expense/allocation mutations, user/permission administration, payment webhooks and PDF/report generation.

Focused regression contracts preserve reviewed invariants, including `scripts/verify_v320_ga_tenant_authorization_contract.php`, `scripts/verify_v320_subscription_recovery_login_security.php` and `scripts/verify_v320_pdf_security_contract.php`.

## Authorization model observed

Reviewed paths generally apply four layers where relevant:

1. **Authentication** — authenticated session context.
2. **Capability/role permission** — role/permission/entitlement policy.
3. **Tenant ownership** — current `farm_id` participates in object lookup/canonical service calls.
4. **Mutation protection** — request method and CSRF validation for browser-authenticated state changes.

Platform Owner behavior remains an intentional exception and must be tested separately from tenant roles.

## Authentication / subscription recovery

### Normal sign-in — PASS (source)

`sign.php` uses shared CSRF validation, throttling, prepared account lookup, central password verification, generic failure wording and session regeneration before authenticated context is established.

### Restricted subscription-recovery bridge — REMEDIATED

`/login.php` is intentional. It can establish a billing-only recovery session for eligible Farm Admins whose farm is in a designated subscription state, then falls through to `sign.php` for normal login.

On the protected baseline, this special farm POST path could inspect the recovery candidate/password before `sign.php` reached its normal CSRF validation. This was a real source-level boundary gap.

GA branch remediation now validates the shared CSRF token before recovery account lookup/password verification. The dedicated recovery rate limit, password-before-session ordering, session regeneration and normal fallthrough remain intact. `verify_v320_subscription_recovery_login_security.php` makes this a durable regression contract.

The earlier GA-SEC-001 statement that `/login.php` was missing/retired is withdrawn as a false-positive architectural assumption.

## Reviewed high-value tenant paths

### Sale deletion — PASS (source)

The reviewed sale deletion path preserves authenticated access, method/CSRF protection, delete permission, farm-scoped lookup/locking and canonical lifecycle deletion rather than ad-hoc state mutation.

### Inventory stock mutation — PASS (source)

The reviewed stock update path preserves authenticated access, method/CSRF protection, throttling, permission policy, item lock by `id + farm_id`, farm-type checks, canonical stock movement service and transactional rollback.

### Inventory history/read — PASS (source)

The reviewed stock-history path derives the current tenant, authorizes Inventory/relevant livestock access, resolves the item by `id + farm_id`, applies specialist scope checks and scopes transaction/user/cycle joins by farm.

No source-level cross-tenant read path was identified in that handler.

### Expense update — PASS (source)

The reviewed expense update route uses authenticated/method/CSRF gates, tenant-scoped parent lookup, module/permission policy and farm-scoped persistence. Production-cycle ownership is validated inside the current farm.

### Shared financial allocation — PASS with error-surface follow-up

The reviewed financial-allocation route derives current farm context and delegates ownership/persistence to canonical farm-aware workspace/services. Exception response behavior remains part of the GA exception-disclosure review.

### Sale revenue allocation — PASS with error-surface follow-up

The reviewed route requires login/method/CSRF, derives current farm, locks/loads the parent sale through the farm-aware persistence service, evaluates workspace access and persists through the canonical allocation service.

### Permission assignment — PASS (source)

The reviewed permission-save path requires authentication/method/CSRF, restricts authority, resolves targets within the current farm, protects Farm Admin/Platform Owner targets, uses the canonical catalog/applicability policy and writes transactionally.

### Team-user management — PASS for tenant/privilege ownership

The reviewed user-management path scopes tenant operations, protects privileged users, validates CSRF, constrains edit/delete/resend by `id + farm_id`, checks assignable roles against entitlements and uses canonical credential/identity services.

Atomicity of some simple user-deletion operations remains an integrity improvement opportunity but is not classified as an authorization vulnerability.

## Payment boundary

### Paystack webhook — PASS (source)

Reviewed code authenticates the provider event using HMAC SHA-512/timing-safe comparison, independently verifies payment with the provider, uses tracked references, transactions/locking and canonical billing finalization.

Runtime replay/idempotency remains a staging requirement.

### Remaining payment review

- Flutterwave verification and TLS policy
- return/recovery ownership and replay behavior
- frozen amount/currency comparison for all providers
- callback authority
- provider HTTP timeout/TLS behavior

## PDF/report security

### Tenant authorization/data scope — PASS for reviewed report

The reviewed expense report requires authenticated Expense authority, derives tenant farm ID, constrains expense rows by farm and uses prepared filter parameters. Dynamic report values are escaped/formatted before rendering.

### Dompdf resource policy — PASS (source)

The central PDF service configures remote resources off, chroot to the application root, controlled CSS and sanitized filenames.

### GA-PDF-001 — raw exception disclosure — REMEDIATED

The earlier PDF failure path returned `Throwable::getMessage()` to the browser. GA branch remediation now:

- logs only a non-sensitive exception-class signal;
- returns HTTP 503;
- returns a generic user-facing message;
- prevents raw Dompdf/runtime exception details from reaching the browser.

`verify_v320_pdf_security_contract.php` protects this behavior.

## Error-disclosure review

### GA-SEC-002 / GA-REV-001 — IN PROGRESS

`safe_api_exception_message()` suppresses PDO/database details but allows selected non-PDO messages. Some API endpoints also intentionally surface domain validation/runtime messages.

No blanket replacement should be made until caller semantics are classified. GA-2 will distinguish expected user-correctable domain errors from implementation exceptions and centralize an allowlisted contract if necessary.

## Runtime schema mutation review

### GA-ARCH-001 — IN PROGRESS

`includes/functions.php` contains legacy schema helpers and `runSchemaMigrations(PDO $pdo)`. The remaining task is call-site/reachability analysis. If normal HTTP requests can execute it, schema mutation must move to explicit deployment tooling.

## CSP hardening debt

### GA-CSP-001 — DEFERRED UNTIL BROWSER COVERAGE

CSP retains compatibility allowances (`unsafe-inline` / `unsafe-eval`). This weakens defense in depth but should not be tightened blindly. Removal is gated on critical-path E2E coverage and staging regression evidence.

## Automated source assurance

Current GA CI covers:

- Composer validation and locked dependency audit;
- repository-wide PHP lint;
- GA source-security contract;
- GA high-confidence source vulnerability scan;
- login and subscription-recovery security contracts;
- tenant authorization contract;
- permission/navigation contracts;
- credential/password-recovery contracts;
- PDF security contract;
- verifier-directory HTTP-deny policy;
- repository secret-file policy.

Scanner assertions are maintained as behavior-oriented checks. False positives are corrected in the scanner rather than changing safe application behavior merely to satisfy textual patterns.

## GA-2 runtime handoff requirements

A later isolated staging runner must create at least two unrelated tenants and exercise positive/negative authorization for:

- sale read/edit/delete;
- Inventory item/history/update;
- expense read/edit/delete;
- financial allocation workspaces;
- production cycles;
- poultry daily records;
- ruminant records/animals;
- Team User management;
- permissions;
- billing/account resources;
- PDF/report exports.

For each object class, Farm A credentials must attempt Farm B object IDs through applicable GET/POST/API routes. Expected result is denial/not-found without Farm B disclosure or mutation.

## Current GA-2 conclusion

The reviewed source shows consistent tenant-aware patterns on the highest-value paths sampled so far. No confirmed cross-tenant IDOR has been found in those reviewed paths.

Confirmed source issues found during GA-2 have been handled conservatively on the hardening branch: the subscription-recovery CSRF boundary and PDF raw-error disclosure are remediated with focused regression coverage. GA-2 remains **IN PROGRESS** while exception-callers, runtime migration reachability, remaining payment paths, dev/debug exposure and additional object classes are reviewed.
