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

### Completed / verified in source

- isolated GA branch created from the exact protected v3.2 baseline;
- GA-1 attack-surface inventory completed;
- core authentication/session/password controls reviewed;
- `/login.php` confirmed as the intentional restricted subscription-recovery bridge rather than a stale route;
- subscription-recovery login CSRF boundary corrected on GA branch and protected by focused regression coverage;
- Paystack webhook authentication/locking pattern reviewed;
- Flutterwave adapter/source transport review confirms webhook hash verification and shared HTTPS-only provider transport;
- tenant-scoped source review completed for selected high-value Sales, Inventory, Expense, Allocation, Permissions and Team User paths;
- expense PDF tenant scope and Dompdf resource policy reviewed;
- PDF raw-exception disclosure corrected on GA branch and covered by regression contract;
- Composer metadata/locked dependency install/audit are part of GA CI;
- source-level tenant authorization regression contract present;
- source vulnerability scanner present and being calibrated toward behavior/high-signal findings;
- GA reachability audit added for runtime schema migration/API error-surface mapping;
- GA regression workflow expanded to run hardening contracts;
- API and architecture documentation are present under `docs/`;
- release checklist and GA changelog are present under `docs/ga-hardening/`.

### Remediated findings

| ID | Severity | Finding | Status |
|---|---:|---|---|
| GA-SEC-003 | Security boundary | Subscription-recovery bridge could inspect recovery credentials before normal sign-in CSRF validation | **REMEDIATED ON GA BRANCH** |
| GA-PDF-001 | Low–Medium | Raw PDF exception details could reach browser | **REMEDIATED ON GA BRANCH** |

### Retired false positive

The earlier GA-SEC-001 assertion that `/login.php` was missing/retired is withdrawn. `/login.php` is an intentional billing-recovery bridge that falls through to `sign.php`; removing it would break the designed recovery path.

### Open hardening debt / review candidates

| ID | Area | Status |
|---|---|---|
| GA-CSP-001 | CSP `unsafe-inline` / `unsafe-eval` compatibility allowances | DEFERRED UNTIL E2E/STAGING COVERAGE |
| GA-SEC-002 / GA-REV-001 | API exception normalization / domain-vs-internal messages | IN PROGRESS |
| GA-REV-002 | Remaining object-level tenant ownership surfaces | IN PROGRESS |
| GA-REV-003 | Remaining report/export authorization and escaping surfaces | IN PROGRESS |
| GA-ARCH-001 | Reachability of legacy runtime schema migration helper | IN PROGRESS |
| GA-INT-001 | Team User delete atomicity (integrity, not currently classified security) | REVIEW |

## CI / automated assurance

The GA workflow currently includes:

- Composer validation;
- locked dependency installation;
- `composer audit --locked`;
- repository-wide PHP lint;
- GA source-security contract;
- GA source vulnerability scan;
- GA reachability audit;
- login and subscription-recovery security contracts;
- tenant authorization contract;
- permission/navigation contracts;
- credential/password-recovery contracts;
- PDF security contract;
- verifier-directory HTTP-deny check;
- repository secret-file policy.

CI is considered GA-4 PASS only when the current hardening head completes green; historical green/failing runs are evidence but not a substitute for the latest-head result.

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
