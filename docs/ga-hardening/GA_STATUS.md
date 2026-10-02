# Renee AgriSuite v3.2 GA Hardening Status

## Branch policy

- Protected fallback: `v320-renee-agrisuite-branding`
- Protected baseline commit: `10041da3a3c4e4a7b8101f8183c243d056c7b128`
- Active hardening branch: `v320-ga-hardening`
- Production deployment from the GA branch is prohibited until the production-readiness gate is approved.

## Stage status

| Stage | Scope | Status |
|---|---|---|
| GA-1 | Architecture & attack-surface inventory | **COMPLETE** |
| GA-2 | Authorization, tenancy & source security review | **IN PROGRESS** |
| GA-3 | Dependency/source vulnerability scanning | **IN PROGRESS** |
| GA-4 | Automated regression foundation | **IN PROGRESS** |
| GA-5 | Critical-path Playwright E2E code | **NOT STARTED** |
| GA-6 | Staging environment certification | **DEFERRED TO RUNTIME/STAGING** |
| GA-7 | Load/performance testing | **DEFERRED TO RUNTIME/STAGING** |
| GA-8 | Backup/restore disaster-recovery drill | **DEFERRED TO RUNTIME/STAGING** |
| GA-9 | Monitoring & operational readiness | **NOT STARTED** |
| GA-10 | Architecture/API/release documentation | **IN PROGRESS** |
| GA-11 | Final production-readiness review | **BLOCKED BY PRIOR STAGES** |
| GA-12 | Independent third-party penetration test | **EXTERNAL / NOT STARTED** |

## Current evidence

### Completed

- isolated GA branch created from exact protected v3.2 baseline;
- GA-1 attack-surface inventory committed;
- core authentication/session/password controls reviewed;
- Paystack webhook authentication/locking pattern reviewed;
- tenant-scoped source review completed for selected high-value Sales, Inventory, Expense, Allocation, Permissions and Team User paths;
- expense PDF tenant scope and Dompdf resource policy reviewed;
- Composer metadata/locked dependency install/audit are part of GA CI;
- source-level tenant authorization regression contract added;
- PDF security regression contract added;
- source vulnerability scanner added;
- GA regression workflow expanded to run new hardening contracts.

### Confirmed open findings

| ID | Severity | Finding | Status |
|---|---:|---|---|
| GA-SEC-001 | Medium | Central bootstrap still redirects some unauthenticated/expired tenant sessions to retired `/login.php` instead of canonical sign-in route | OPEN |
| GA-CSP-001 | Medium hardening debt | CSP script policy still permits `unsafe-inline` and `unsafe-eval`; staged browser-safe migration required | OPEN / DEFERRED UNTIL E2E |
| GA-PDF-001 | Low–Medium | Raw PDF exception details could reach browser | **SOURCE FIXED; CI/semantic verification pending** |

### Review candidates

| ID | Area | Status |
|---|---|---|
| GA-REV-001 | API exception normalization / raw domain-vs-internal exception responses | IN PROGRESS |
| GA-REV-002 | Remaining object-level tenant ownership surfaces | IN PROGRESS |
| GA-REV-003 | Remaining report/PDF authorization and escaping surfaces | IN PROGRESS |
| GA-INT-001 | Team User delete atomicity (integrity, not currently classified security) | REVIEW |

## Runtime evidence still required

The following must be executed in an isolated staging/runtime environment and cannot be marked PASS from source inspection alone:

- two-tenant IDOR/privilege-escalation probing;
- Playwright execution against a deployed build;
- DAST/runtime security probing;
- payment webhook replay/idempotency exercise;
- live HTTP security header/CSP verification;
- active web-server sensitive-file denial verification;
- database least-privilege verification;
- load/stress and slow-query/performance measurement;
- backup restore into a clean environment;
- monitoring/alert delivery validation;
- external independent penetration test.

## GA release rule

`v3.2 GA` must not be declared while a release-blocking security/data-integrity finding is open or while required runtime gates are unexecuted. Deferred items need explicit acceptance criteria and evidence; they must not be silently treated as PASS.
