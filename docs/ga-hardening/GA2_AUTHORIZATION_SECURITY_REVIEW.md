# Renee AgriSuite v3.2 — GA-2 Authorization & Security Source Review

## Scope

GA-2 is a source-level review of tenant isolation, privilege boundaries and high-value mutation/read paths on `v320-ga-hardening`. It does not claim runtime IDOR certification; active cross-tenant tests remain reserved for isolated staging.

Reviewed evidence in this cut includes:

- `config.php`
- `sign.php`
- `api/api_helpers.php`
- `api/delete_sale.php`
- `api/update_stock.php`
- `api/get_stock_history.php`
- `api/update_expense.php`
- `api/update_financial_allocation.php`
- `api/update_sale_revenue_allocation.php`
- `admin/permissions_save.php`
- `management/users.php`
- `billing/paystack-webhook.php`
- `management/expense_report_pdf.php`
- `includes/pdf/PdfReportService.php`

A new regression verifier, `scripts/verify_v320_ga_tenant_authorization_contract.php`, records the reviewed source invariants so they cannot silently disappear.

## Authorization model observed

The reviewed paths consistently use four layers where applicable:

1. **Authentication** — `requireLogin()` / authenticated session context.
2. **Capability/role permission** — role/permission/entitlement checks.
3. **Tenant ownership** — current `farm_id` participates in object lookup and/or canonical service calls.
4. **Mutation protection** — POST/method enforcement and CSRF on browser-authenticated state changes.

Platform Owner behavior is explicitly exceptional where intended and should remain separately tested from tenant roles.

## Reviewed high-value paths

### Sale deletion — PASS (source)

`api/delete_sale.php` preserves:

- authenticated access;
- POST-only mutation;
- CSRF validation;
- `delete_sales` permission;
- farm-scoped record lookup;
- canonical sale lifecycle deletion rather than direct ad-hoc SQL.

This is the preferred pattern for destructive domain mutations.

### Inventory stock mutation — PASS (source)

`api/update_stock.php` preserves:

- authenticated access;
- POST-only mutation;
- CSRF validation;
- rate limiting;
- Farm Admin/Platform Owner or explicit Inventory + Update Stock permission;
- item lock by `id` **and** `farm_id`;
- item farm-type access checks for specialist roles;
- canonical `stock_apply_movement()` service;
- transaction rollback on failure.

Quantity/date/type/unit-cost validation is applied before the movement service is called.

### Inventory history/read — PASS (source)

`api/get_stock_history.php`:

- requires authentication;
- derives the current tenant through `requireCurrentFarmId()`;
- authorizes Inventory or relevant livestock access;
- resolves the item by `id` and `farm_id`;
- applies an additional item farm-type check for specialist users;
- scopes transaction, user and production-cycle joins by farm;
- constrains chart/history queries by both item and farm.

No source-level cross-tenant read path was identified in the reviewed handler.

### Expense update — PASS (source)

`api/update_expense.php` uses authenticated/POST/CSRF gates, tenant-scoped parent lookup, module/permission policy and farm-scoped updates. Production-cycle ownership is also validated within the current farm before persistence.

### Shared financial allocation — PASS with error-surface follow-up

`api/update_financial_allocation.php` uses the current farm ID and canonical financial-allocation workspace/service boundaries. Object ownership is delegated to those farm-aware services.

Its exception response behavior remains part of the GA exception-disclosure review because selected `RuntimeException` messages may be returned to the caller.

### Sale revenue allocation — PASS with error-surface follow-up

`api/update_sale_revenue_allocation.php`:

- requires login, POST and CSRF;
- derives the current farm;
- starts a transaction;
- locks/loads the parent sale with `farmId + saleId` through the canonical persistence service;
- evaluates workspace access before mutation;
- persists through the canonical farm-aware allocation service.

The reviewed persistence layer scopes parent sales, allocation rows and target production cycles by farm. Selected `RuntimeException` messages are returned to the caller and remain subject to the exception-disclosure review.

### Permission assignment — PASS (source)

`admin/permissions_save.php`:

- requires authentication, POST and CSRF;
- limits tenant access to Farm Admin / Platform Owner policy;
- resolves target users within the current farm;
- protects Farm Admin and Platform Owner targets;
- uses the canonical permission catalog and farm-aware permission applicability;
- writes permissions within a transaction.

### Team-user management — PASS for tenant/privilege ownership; integrity follow-up noted

`management/users.php`:

- requires authentication and user-management authority;
- defaults tenant operations to `requireCurrentFarmId()`;
- allows Platform Owner to select a non-owner tenant explicitly;
- validates CSRF on POST;
- constrains edit/delete/resend targets by `id + farm_id`;
- protects Farm Admin accounts from Team User operations;
- validates assignable roles against tenant entitlements;
- uses canonical account identity/pending-credential services for new users.

A non-security integrity improvement is worth considering later: the simple Team User delete path removes role rows then the user without an explicit local transaction in that branch. This was not classified as an authorization vulnerability because both statements are tenant-targeted and the target is protected, but atomic deletion would improve failure consistency.

### Paystack webhook — PASS (source)

The reviewed webhook uses POST-only handling, environment-backed secret resolution, HMAC SHA-512 over the raw body, `hash_equals()`, tracked-reference checks, a DB transaction and row locking before canonical finalization. Runtime replay/idempotency still requires staging evidence.

## PDF/report security review

### Tenant authorization and data scope — PASS for reviewed expense report

`management/expense_report_pdf.php` requires login and Expense authority, derives `tenantFarmId`, constrains all expense rows by that farm and uses prepared parameters for date/category/type filters.

Dynamic report values displayed in HTML are passed through `htmlspecialchars()` or numeric formatting before Dompdf receives the markup.

### Dompdf resource policy — PASS (source)

`includes/pdf/PdfReportService.php` explicitly configures:

- `isRemoteEnabled = false`;
- `chroot` to the application root;
- HTML5 parsing;
- controlled report CSS;
- sanitized PDF filename output.

These controls substantially reduce SSRF/remote-resource exposure from PDF rendering.

### GA-PDF-001 — PDF failure path can disclose exception details

**Severity:** Low-to-Medium information disclosure hardening issue

**Status:** OPEN on GA branch

`pdf_report_finish()` catches `Throwable` and emits:

`PDF generation unavailable: ` + the raw exception message.

Although known constructor failures are currently human-readable, Dompdf/runtime exceptions can contain implementation details. A public/user-facing PDF endpoint should return a generic error while logging the detailed exception server-side through the central logging/redaction mechanism.

Required remediation:

- log the detailed exception through the canonical safe logging path;
- send a generic 503 message to the browser;
- add a focused regression contract preventing raw exception output from returning.

## Existing findings carried from GA-1

### GA-SEC-001 — legacy `/login.php` redirects in central bootstrap

Still OPEN. `config.php` contains tenant unauthenticated/session-expiry redirects to retired `/login.php` while the canonical route is `sign.php`.

### Dependency lock / Composer audit — PASS

The protected baseline and GA branch both contain a committed `composer.lock`. The GA workflow validates Composer metadata, installs the locked dependency graph and runs `composer audit --locked`. The reviewed workflow completed those dependency steps successfully. No dependency-lock finding is open.

### GA-CSP-001 — `unsafe-inline` / `unsafe-eval`

Still OPEN as staged hardening debt. CSP must not be tightened blindly before browser E2E coverage proves compatibility.

## Error-disclosure review status

### GA-REV-001 — IN PROGRESS

Patterns identified:

- `safe_api_exception_message()` suppresses obvious DB/internal patterns but intentionally allows some other messages;
- allocation endpoints return selected domain `InvalidArgumentException` / `RuntimeException` messages directly;
- PDF failure handling currently exposes raw `Throwable::getMessage()` and is now a confirmed finding (GA-PDF-001).

Before changing the shared API helper, GA-2 will distinguish safe domain validation messages from implementation exceptions so useful user errors are not lost.

## Automated regression added

`scripts/verify_v320_ga_tenant_authorization_contract.php` verifies the reviewed high-value source invariants for:

- sale deletion;
- stock mutation;
- expense update;
- financial allocation;
- sale revenue allocation;
- permission assignment;
- Team User management;
- avoidance of bare ID-only direct mutation patterns on the reviewed high-value tables.

This is a source regression guard, **not** a substitute for runtime cross-tenant testing.

## GA-2 runtime test handoff requirements

The later staging runner must create at least two unrelated tenants and exercise both positive and negative authorization cases for:

- sale read/edit/delete;
- inventory item/history/update;
- expense read/edit/delete;
- allocation workspaces;
- production cycles;
- poultry daily records;
- ruminant records/animals;
- Team User management;
- permissions;
- billing/account resources;
- PDF/report exports.

For each object class, Farm A credentials must attempt Farm B object IDs through GET, POST and any API endpoints available. Expected result is denial/not-found without Farm B data disclosure or mutation.

## Current GA-2 conclusion

The reviewed architecture shows consistent tenant-aware patterns on the highest-value paths sampled so far. No confirmed cross-tenant IDOR was found in those source paths. GA-2 remains **IN PROGRESS** until the remaining mutation/report surfaces and exception-disclosure paths are reviewed and the confirmed GA findings are remediated or explicitly deferred with acceptance criteria.
