# Renee AgriSuite v3.2 — Architecture

Status: GA-hardening reference architecture
Branch: `v320-ga-hardening`
Protected baseline: `v320-renee-agrisuite-branding`

## 1. Architectural principle

Renee AgriSuite follows the project rule **“One fix, fit it all.”** Business authority belongs in shared helpers/services; route files should remain thin orchestration surfaces. SQL, tenancy, permissions, pricing, billing, inventory, lifecycle, financial allocation and visual/security policy should not be reimplemented per page.

The application is a server-rendered PHP/MySQL SaaS platform with JSON API helpers for asynchronous browser actions. It is multi-tenant at the farm boundary and combines operational livestock/poultry workflows with inventory, sales, financial allocation, reporting and commercial SaaS billing.

## 2. Bootstrap and request lifecycle

### `config.php`

Owns foundational runtime policy:

- environment-backed database configuration;
- timezone authority;
- security headers and CSP bootstrap;
- session cookie policy and inactivity timeout;
- PDO creation and error mode;
- current farm/session primitives;
- role/farm identity helpers;
- login enforcement;
- CSRF primitives.

### `init.php`

Loads the canonical cross-cutting services used by application routes, including:

- entitlements and subscription state;
- CSRF/output/CSP helpers;
- password security;
- permission runtime and legacy authorization closure;
- API View-permission bridge;
- user-management tenant guard;
- production-cycle authority;
- feed/expense/receivable action permissions;
- platform-owner selected-tenant read-only support.

Routes should include the shared bootstrap rather than re-create these contracts.

## 3. Tenant model

The security/data boundary is `farm_id`.

Core tenant-owned records include users, production cycles, daily records, ruminant animals, inventory, stock transactions, sales, expenses, financial allocations, subscriptions and billing attempts.

Tenant-scoped object access should satisfy both:

1. the authenticated session resolves to an allowed farm; and
2. the target object query is bound to that same farm.

An object ID alone is never sufficient authority.

### Platform Owner

Platform Owner is a platform-level role. Selected-tenant support views must not silently impersonate a farm administrator. Mutation paths need their own explicit authority; a selected tenant in the UI is not a write entitlement.

### Farm Admin and operational roles

Farm Admin has tenant administration authority. Operational roles include Poultry Manager, Ruminant Manager, Sales Representative and Viewer, with permission applicability constrained by farm product/module entitlements.

## 4. Authorization chain

The intended authorization chain is:

`commercial product / farm module`
→ `entitlement`
→ `permission applicability`
→ `role/permission grant`
→ `navigation visibility`
→ `route authorization`
→ `API/action authorization`
→ `tenant-bound object lookup`

Navigation is presentation only; backend authorization remains authoritative.

`includes/permission_runtime.php`, the permission catalog and related granular permission helpers are the canonical runtime layer. Historical permission aliases are compatibility surfaces, not new authority.

## 5. Authentication and credentials

Primary public credential surfaces:

- `sign.php`
- `account/activate.php`
- `account/forgot_password.php`
- `account/reset_password.php`
- `logout.php`

Credential lifecycle characteristics:

- password verification/hashing is centralized in `includes/password_security.php`;
- activation/reset tokens are generated randomly and stored by hash;
- tokens are purpose-bound, expire, are one-time and supersede prior outstanding tokens;
- password-recovery responses are privacy-safe;
- guest credential routes use shared rate limiting;
- successful login rotates the PHP session identifier;
- GA hardening binds authenticated sessions to a one-way fingerprint of the current stored password hash so password changes revoke older sessions on their next protected request.

Public reset/activation pages do not expose tenant data and may complete PRG confirmation before stale authenticated session destruction.

## 6. Poultry and ruminant domain architecture

### Poultry

Core surfaces cover Layer/Broiler cycles, daily records, feeds, expenses, health, slaughter/processing, production population and cycle lifecycle.

### Ruminant

Core surfaces cover animal registry/profile, daily records, feeds, expenses, species-scoped tag identity, slaughter/processing, cycle membership and animal lifecycle exits.

Lifecycle effects should be handled by shared services rather than inferred from labels in UI forms.

## 7. Population, inventory and sales separation

A central domain invariant is that these concepts are different:

**financial sale ≠ inventory movement ≠ livestock population movement ≠ revenue allocation**

A sale does not automatically imply a physical livestock exit. Likewise slaughter output is not implicitly Sales revenue.

Shared services own stock movements and reversals, sale lifecycle effects, population effects, sale revenue allocation and stock-consumption allocation.

This separation is required to preserve historical corrections, reversals and profitability attribution.

## 8. Inventory architecture

Inventory uses `stock_items` for current stock identity and `stock_transactions` as the movement ledger. Financial classification and inventory role distinguish operational inventory from outputs such as slaughter inventory.

Stock changes should flow through the canonical stock movement service. Edit/delete flows reverse/replace prior movements rather than silently rewriting history.

Tenant and permission checks must precede movement application.

## 9. Financial allocation and profitability

Renee AgriSuite supports farm-wide/shared financial records and cycle-specific attribution. Shared workspaces/services cover:

- expense allocation;
- sale revenue allocation;
- stock-consumption allocation;
- profitability and unallocated-item investigation.

Allocation history/provenance is retained so a current answer can be reconciled to the business event that produced it.

## 10. Billing/commercial architecture

Commercial SaaS behavior is centralized under billing/subscription helpers and provider adapters.

Key concepts include:

- commercial product/plan;
- subscription records/history;
- seat limits and top-ups;
- payment attempts;
- provider references;
- callback/webhook reconciliation;
- commercial attempt disposition/supersession;
- refund/reversal handling;
- exactly-once paid-attempt dispatch.

`billing/webhook.php` is session-independent and must authenticate the payment provider before trusting event content. Provider verification and the frozen local payment attempt are both part of the acceptance boundary.

Historical billing records are audit evidence and must not be rewritten merely to rerun certification.

## 11. Reporting and PDF boundary

Application-generated PDFs use the shared `includes/pdf/PdfReportService.php` service.

Security policy:

- Dompdf remote resource loading is disabled;
- Dompdf is chrooted to the application root;
- generated filenames are normalized;
- raw PDF-engine exception messages are not returned to browsers;
- tenant branding is resolved centrally.

Report routes are tenant read surfaces and require the same authorization rigor as normal pages/APIs.

## 12. Database and migrations

MySQL is accessed through PDO in the modern application path. Sensitive lifecycle mutations use transactions and, where needed, row locks.

Schema changes are versioned in `migrations/` and recorded in `schema_migrations`. Migrations 089, 090 and 091 are already certified/applied in the production lineage and must not be rerun manually for GA testing.

The baseline schema includes tenant keys across operational/financial records and uses historical/provenance tables for several mature workflows.

## 13. API architecture

Browser APIs live under `api/` and share `api/api_helpers.php` for JSON policy, HTTP method enforcement, CSRF wrappers, safe exception handling and rate limiting.

Every API remains independently responsible for the correct combination of:

- login/authentication;
- farm scope;
- entitlement/permission;
- HTTP method;
- CSRF for browser-authenticated mutation;
- validation;
- tenant-bound object lookup;
- parameterized SQL;
- safe output/error handling.

Existence of a shared helper does not certify a route that does not call it.

## 14. Release and assurance architecture

The source repository contains a large focused-verifier suite. GA hardening adds:

- `docs/ga-hardening/GA1_ATTACK_SURFACE.md`;
- `scripts/ga_source_security_scan.php`;
- `scripts/run_v320_ga_regression.sh`;
- focused security-contract verifiers;
- `.github/workflows/ga-regression.yml`;
- Playwright staging tests under `tests/e2e/`;
- k6 staging load harness under `tests/load/`;
- DR post-restore verification.

The default GA regression runner is intentionally non-destructive. Live billing, migrations, provisioning, load, DAST and DR execution require isolated/staging context and explicit approval.

## 15. Deployment model

Source checkout: `/home/renee/renee-deploy`

Production runtime: `/home/renee/public_html`

Production `config.php` intentionally differs from source and must not be wholesale replaced.

Controlled deployment requires branch/HEAD guards, clean source state, backup, selected-file deployment, lint/verifiers, source/runtime integrity comparison, rollback capability and focused browser QA.

## 16. Frozen/certified architectural contracts

Absent regression evidence, do not reopen:

- sales population separation;
- ruminant sales allocation modes/lifecycle outcomes;
- General Inventory product applicability;
- Layer egg allocation;
- sales stock create/edit/delete reversal lifecycle;
- billing K2–K6/seat top-up exactly-once history;
- trial onboarding historical certification;
- password-recovery lifecycle already certified before GA session-revocation hardening.

## 17. GA trust boundaries still requiring runtime evidence

Repository review cannot by itself certify:

- cross-tenant behavior against real seeded tenants;
- browser E2E across all valuable workflows;
- concurrent performance and database contention;
- backup restoration onto a clean target;
- production/staging monitoring delivery;
- runtime DAST;
- independent third-party penetration testing.

Those are explicitly handed to isolated staging/ChatGPT Work after repository preparation is complete.
