# Renee AgriSuite v3.2 GA Hardening Changelog

This changelog records changes made **after** the protected v3.2 baseline while preparing General Availability.

Protected baseline:

- Branch: `v320-renee-agrisuite-branding`
- Commit: `10041da3a3c4e4a7b8101f8183c243d056c7b128`

Active hardening branch:

- `v320-ga-hardening`

The protected baseline is intentionally not rewritten during hardening.

## Unreleased — v3.2 GA hardening

### Security assurance

- Added GA architecture/attack-surface inventory.
- Added source-level tenant authorization review for high-value Sales, Inventory, Expense, Allocation, Permissions and Team User paths.
- Added `verify_v320_ga_tenant_authorization_contract.php` to prevent reviewed tenant/method/CSRF/permission gates from silently regressing.
- Added source vulnerability scanner for high-confidence dangerous PHP/web constructs and review-level exception/CORS/SQL-adjacency patterns.
- Added PDF security regression contract.
- Extended GitHub GA regression workflow with the new tenant/PDF/source-security checks.

### PDF/report hardening

- Disabled raw exception detail in the shared PDF failure response.
- PDF failure now returns a generic user-facing 503 message.
- Server log retains a non-sensitive exception-class signal for diagnosis.
- Existing Dompdf safeguards remain: remote resources disabled, chroot to application root, sanitized output filename.

### Documentation

- Added `docs/ARCHITECTURE.md`.
- Added `docs/API.md`.
- Added GA hardening status dashboard.
- Added formal GA release checklist.
- Added GA-1 attack-surface inventory.
- Added GA-2 authorization/security review.

### Dependency assurance

- Confirmed a committed Composer lock exists in both protected baseline and GA branch.
- GA CI validates Composer metadata, installs the locked dependency graph and runs `composer audit --locked`.

## Open hardening items

### GA-SEC-001 — canonical sign-in redirect

Some central bootstrap redirects still reference retired `/login.php`; canonical public sign-in is `sign.php`.

Target resolution:

- centralize/correct sign-in redirect target;
- retain regression coverage preventing legacy route reintroduction.

### GA-CSP-001 — CSP compatibility allowances

Current script policy still permits `unsafe-inline` / `unsafe-eval`.

Target resolution:

- inventory runtime dependencies;
- establish browser E2E coverage;
- remove `unsafe-eval` if no supported dependency requires it;
- migrate inline execution toward nonce/hash/external assets before tightening `unsafe-inline`.

### GA-REV-001 — exception-disclosure audit

Review direct domain exception responses to distinguish safe validation messages from internal implementation errors.

### GA-REV-002 — remaining tenant ownership review

Continue source/runtime coverage across all ID-addressable domain objects and reports.

### GA-INT-001 — Team User deletion atomicity

The simple Team User delete path performs dependent deletes without an explicit local transaction. This is tracked as a data-integrity improvement, not currently classified as a tenant authorization defect.

## Runtime/staging work intentionally not claimed here

The source hardening changelog does not imply PASS for:

- runtime two-tenant IDOR probing;
- Playwright execution;
- DAST;
- load/stress results;
- disaster-recovery restore;
- live monitoring/alert delivery;
- active server header/.htaccess verification;
- independent external penetration testing.

Those require separate evidence before GA approval.

## Release-note rule

Before tagging v3.2 GA, this document must be updated with:

- final release-candidate SHA;
- all resolved GA finding IDs;
- any explicitly accepted residual risk;
- staging test evidence references;
- DR/load/monitoring evidence references;
- independent pentest status;
- final production-readiness decision.
