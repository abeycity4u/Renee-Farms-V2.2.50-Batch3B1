# GC24CG K1/D06 — Tenant identity, actor permissions and isolated synthetic writer authorization

**Status: OFFLINE SOURCE REQUIREMENTS ONLY. Tenant/actor/credential/permission approval is NOT GRANTED.** This file is not a credential inventory, actual tenancy configuration, or proof of runtime cross-farm denial. 2025 is selected only as a reporting-period design year; all event-date rules remain unresolved.

## Pinned evidence

- Certified Reports branch `c137-gc-reports-prefetch-weakmap`, pinned HEAD `322438870263825f9e739a12c4bd478983e67141` unchanged.
- Preparation parent `c137-gc24-fixture-dryrun-z23` HEAD `e3bac113be5c0fd14a9fb318e16a921023c62204`; prior D05 requirements blob `9efeada390c09c122413d9f02ac83ee0a128b72b`.
- D05 R1/R2 specification hashes `ccad5a76825380d083991c8c305cf17dcdd3f6d11e35e8a76f58f816684741bf`, `58d4c14965a64b1fca1a8b0494ef7ae0763e9ec6a442e628d2044c239ac51930`.
- Original 2026 R1/R2 event SHA256 `e31421ee40ce25b16feb698313fcf337e74126a8e63d8c5dadeedf74e30165f2` / `985341ed4315788ee8f3b9d62b327da66d38fc63ea16d406c5c52bc9949d3dfe` remain unchanged.
- The isolated database name `renee_c137_gc24_http` and synthetic slug `c137-gc24-synthetic` are **references only**. The source review does not establish that a tenant record, account or permissions already exists there, and it does not query any database.

## Observed canonical tenancy and authorization rules

**Tenant identity and actor provenance.** `includes/farm_profile.php` says `farms.id` is immutable tenant identity; `farms.slug` can change and does not re-key tenant rows. `config.php` uses `$_SESSION['farm_id']` for request-level scope; `currentUserRoles()` joins user roles through `users` filtered by `u.farm_id`. `requireCurrentFarmId()` rejects missing identity. A **session farm_id guard alone does not prove** an authenticated actor belongs to that farm; the complete login/session + membership contract must be verified before any future fixture writes.

**Role and entitlement boundaries.** `includes/functions.php::hasPermission` supports tenant-specific permission rows overriding global defaults, catalog applicability and module/subscription checks. `includes/permission_catalog.php` constrains roles by target-farm entitlements. `includes/farm_entitlement_runtime.php` runs subscription lifecycle and module gate before legacy role checks. `includes/permission_runtime.php` provides route/action View/Add/Edit/Delete checks. Farm Admin and Platform Owner have privileged paths; that does **not** authorize special fixture creation or cancellation of the operational safety gates. Operational specialist roles cannot administer Team Users or Permissions via the central permission contract. CSRF token check in `includes/csrf.php` is additional to authentication and tenancy, not a substitute for either.

**Platform Owner support versus actor selection.** `includes/platform_owner_tenant_view.php` selects customer farm data explicitly for migrated **read-only** support pages, without changing session farm identity or impersonating tenant actors. `management/users.php` has an explicit owner-only target-farm selector; `includes/user_management_tenant_guard.php` checks edit target membership before edits to `user_roles` (whose rows lack a tenant farm_id). Do not infer that a Platform Owner tenant-view link authorizes writes or that a query-string farm_id is an authenticated actor identity.

**Tenant provisioning.** `includes/tenant_provisioning.php` defines the canonical transactional tenant creation and role/subscription record process; it requires a caller-owned transaction. We have **not** invoked that process or created a farm or user. Any future synthetic tenant actor selection, role limits, credentials and entitlements need explicit separate authorization and evidence.

**Shared writers and object identity.** `lib/production_cycle_service.php` looks up cycles by farm ID. `lib/stock_service.php` restricts item reads/movements to the farm and relies on central stock attribution. `lib/sale_population_effects.php` checks sale and source-cycle identity in one farm. Layer/Broiler Daily Record routes resolve `requireCurrentFarmId()` and validate cycle/item records in that tenant. `management/sales_records.php` uses farm scope and action permissions. These are **source-level findings**, not a completed automated runtime tenant-isolation test or permission/CSRF penetration test.

**Special contexts and risk.** Subscription-recovery/billing actors and Platform Owner support views are not general-purpose operation/import identities. `config.php` creates a PDO connection during bootstrap, so even invoking the normal PHP stack would violate this step's **zero database connections** rule. We inspect PHP **as inert text only**.

## Required negative test matrix before future authorization

On a separately authorized, verified isolated fixture, test at least: valid other-farm production-cycle ID, stock item, movement, sale, population effect and Daily Record ID; unauthorized specialist roles; disabled/suspended entitlement; forged session/context claim; invalid/missing CSRF; user assignment across tenants; and module-specific Add/Edit/Delete restrictions. All should be denied by appropriate canonical routes and services without writing outside the approved tenant. Also prove audit user ID and tenant ID come from durable authenticated authority rather than copied event fields.

No run is authorized now. The machine-readable D06 file lists **42 null/unknown actor identity and implementation fields**, no credentials, no actor IDs, and specific future runtime proofs. Proof obligations do not claim test results.

## Focused verifier and safety

`scripts/verify_c137_gc24_k1_d06_tenant_actor.py` checks D05 immutable blob and digest lineage, guards source markers in 20 certified PHP files, rejects invented farm/user IDs, role assignments or credentials and tests tamper rejection in memory. It does **not** call the PHP runtime or DB, create users, generate SQL or V2 events, deploy or run load tests.

```sh
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/verify_c137_gc24_k1_d06_tenant_actor.py --dry-run --database renee_c137_gc24_http --farm-slug c137-gc24-synthetic --legacy-year 2026 --as-of 2026-10-11 --reporting-year 2025 --certified-source-root /opt/renee-agrisuite-c137-gc
```

Node A success closes **D06 source requirements review only**. Runtime tenant/actor authorization remains unproven and unapproved.

**Safety**: no account creation, tenant provisioning, passwords, personal data, SQL connections/writes, PHP invocation, source rewrites, imports, deployments or load tests. Preserve 2026 event digests and 2025 design decision. **150 VU HOLD; 200 VU PROHIBITED.** Next: K1/D07 shared Inventory writer architecture, review-only.
