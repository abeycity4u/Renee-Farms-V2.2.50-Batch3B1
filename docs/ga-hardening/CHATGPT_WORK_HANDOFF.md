# Renee AgriSuite v3.2 — ChatGPT Work / Infrastructure Handoff

Purpose: execute only the remaining infrastructure-dependent GA assurance work after repository preparation is complete.

## 1. Non-negotiable branch/runtime rules

Repository:
`abeycity4u/Renee-Farms-V2.2.50-Batch3B1`

Protected known-good baseline:
`v320-renee-agrisuite-branding`

Active GA branch:
`v320-ga-hardening`

Do not modify or force-move the protected baseline.

A preserved copy of the unreliable network-interrupted prior attempt exists as:
`v320-ga-hardening-interrupted-20261002`

Do not merge that branch wholesale. It exists for forensic/reference comparison only.

Before any staging deployment record:

- branch name;
- local HEAD;
- remote HEAD;
- clean worktree;
- final GitHub CI result;
- backup/rollback target.

Do not deploy to production first. Do not run destructive security/load/DR tests against production.

## 2. Repository-side work already prepared

Read and use these as the source of truth:

- `docs/ga-hardening/GA1_ATTACK_SURFACE.md`
- `docs/ga-hardening/GA2_SECURITY_REVIEW.md`
- `docs/ARCHITECTURE.md`
- `docs/API.md`
- `docs/ga-hardening/RELEASE_CHECKLIST_V3.2.md`
- `docs/ga-hardening/CHANGELOG_V3.2_GA.md`
- `docs/ga-hardening/MONITORING_OPERATIONS.md`
- `scripts/run_v320_ga_regression.sh`
- `scripts/ga_source_security_scan.php`
- `scripts/verify_v320_password_session_invalidation.php`
- `scripts/verify_v320_login_security_contract.php`
- `scripts/verify_v320_api_security_contract.php`
- `.github/workflows/ga-regression.yml`
- `tests/e2e/`
- `tests/load/k6_authenticated_read.js`
- `scripts/ga_dr_post_restore_verify.php`

Do not recreate these from scratch unless a proven defect exists.

## 3. Source security changes that require runtime confirmation

GA branch adds/fixes:

1. authenticated sessions are bound to current password credential state;
2. password change/reset revokes older sessions on their next protected request;
3. login form/POST now uses shared CSRF protection;
4. PDF failures no longer disclose raw exception messages.

Important deployment behavior: sessions created by the pre-hardening build have no credential fingerprint and will be asked to sign in again after the new code reaches a protected request. Treat this as an expected security rotation, not data loss.

## 4. Build an isolated staging environment

Required properties:

- separate database;
- sanitized/synthetic farm/user/business data;
- no production DB connection;
- no production filesystem uploads;
- staging-specific BASE/public URL;
- separate session cookies/domain/path;
- payment providers in TEST mode only;
- staging-only SMTP/test-recipient policy;
- obvious visual/environment marker;
- synthetic tenants for cross-tenant testing;
- disposable users for password/security tests.

Before any destructive test prove by configuration evidence that staging cannot reach production DB/payment/live storage.

## 5. Execute Playwright E2E

Read `tests/e2e/README.md`.

At minimum run:

### Public/auth

- sign-in page/recovery entry;
- invalid login generic error;
- valid farm login;
- Platform Owner login;
- logout;
- forgot-password privacy response;
- reset/activation landing and confirmation;
- old password rejected/new password accepted;
- password reset invalidates a previously authenticated session;
- admin password change invalidates target user's previous session.

### Tenant/authorization

Create at least two synthetic tenants A and B.

From Farm B credentials submit Farm A IDs for:

- user;
- cycle;
- Layer/Broiler daily record;
- ruminant animal;
- stock item;
- stock transaction/history;
- sale;
- expense;
- financial allocation;
- report/PDF;
- billing/subscription view/attempt where safely available.

Test read, edit and delete/action where the object supports them.

Expected: denied/not found; no Farm A content/state change.

### Privilege escalation

Prove:

- Sales Rep cannot become Farm Admin;
- Farm Admin cannot become Platform Owner;
- crafted permission/role POST is rejected;
- hidden navigation cannot be bypassed via direct URL/API;
- Platform Owner selected-tenant support view cannot be turned into tenant operational mutation.

### High-value business workflows

Using disposable records only:

- General Inventory receive → sale → edit/reversal/replacement → delete/restoration;
- receivable flow;
- general/poultry/ruminant expense create/edit/delete as role allows;
- financial allocation;
- sale revenue allocation;
- stock-consumption allocation;
- Layer daily record;
- Broiler daily record;
- poultry feed/expense;
- poultry slaughter/population outcome;
- ruminant registry/daily/feed/expense;
- ruminant slaughter/lifecycle exit;
- report/PDF access.

Preserve traces/screenshots only when useful and ensure they do not expose secrets.

## 6. Runtime security / DAST

Use isolated staging only.

Test, without destructive production targeting:

- IDOR/cross-tenant access;
- SQL injection against input parameters;
- reflected/stored XSS;
- CSRF missing/invalid token behavior;
- session fixation and stale-session revocation;
- role/permission escalation;
- account enumeration;
- reset/activation token replay, expiry, supersession;
- guest rate-limit behavior;
- HTTP headers/CSP/cookie attributes;
- path/file/download behavior if upload/download routes exist;
- PDF resource loading/path behavior;
- information disclosure/stack traces;
- webhook invalid signature/replay/mismatch using provider TEST mode.

Do not label this an independent pentest. A separate third party is still required for the “independent penetration test” item.

## 7. Load/performance execution

Use `tests/load/k6_authenticated_read.js` as the prepared read workload.

Requirements:

- staging only;
- multiple pre-authenticated synthetic sessions, not one shared PHP session;
- measure at 10, 25, 50 and 100 concurrent virtual users;
- include dashboard, inventory and representative reporting reads;
- add controlled write workload for sales/inventory only with disposable fixtures;
- record p50/p95/p99, throughput and error rate;
- simultaneously collect CPU, memory, PHP workers, MySQL connections, slow queries and deadlock/lock-wait evidence.

Do not simply report “100 users passed.” Retain the actual metrics and bottleneck evidence.

Initial read harness thresholds are engineering starting points, not contractual SLA:

- request failures < 1%;
- p95 < 2 s;
- p99 < 5 s;
- checks > 99%.

If the real service objective differs, document it rather than silently changing the historical evidence.

## 8. Disaster-recovery drill

Use a known backup and a clean isolated target.

Record:

1. backup timestamp/source;
2. restore start time;
3. clean target path/database;
4. file restore;
5. DB restore;
6. environment application;
7. `GA_DR_ISOLATED_RESTORE=YES php scripts/ga_dr_post_restore_verify.php` output;
8. login test;
9. representative subscription/inventory/sales/expenses/poultry/ruminant/report verification;
10. restore completion time;
11. RTO;
12. source backup age / RPO;
13. blockers/repairs.

Never point the verifier at production intentionally. The environment flag is a human safety gate, not proof of isolation by itself.

## 9. Monitoring/operations deployment

Follow `docs/ga-hardening/MONITORING_OPERATIONS.md`.

At minimum prove in staging:

- controlled application error reaches collector;
- test alert reaches intended channel/person;
- 5xx and latency dashboards work;
- failed-login/security signal is visible without password/token/session content;
- invalid TEST webhook rejection signal is visible without provider secret;
- release SHA/environment are attached;
- CPU/memory/disk/PHP worker/MySQL/slow-query metrics are available;
- backup-age/success and TLS expiry are monitored;
- sensitive-value redaction is inspected from real captured events.

Monitoring secrets belong in environment/secret storage, not Git.

## 10. Independent external pentest

After internal/staging hardening is complete, arrange a genuinely independent security tester.

Provide the tester:

- isolated staging target;
- written authorization/scope;
- test tenant roles;
- rate/availability constraints;
- excluded production assets;
- architecture/API docs;
- known remediated findings, without directing them away from those areas.

Require retest of critical/high findings after remediation.

## 11. Evidence format

For every runtime test record:

- exact GA commit SHA;
- environment identity;
- date/time;
- test name;
- fixture identity (non-secret);
- expected result;
- observed result;
- PASS / PASS WITH RESIDUAL RISK / FAIL / BLOCKED / NOT TESTED;
- artifact/log/report location;
- issue/fix commit when failed;
- retest result.

Do not copy passwords, tokens, sessions, CSRF secrets, DB credentials, SMTP keys or payment secrets into reports.

## 12. Rules if a runtime test fails

Do not let Work “fix everything automatically.”

For each failure:

1. prove/reproduce it;
2. identify shared architecture and cross-module effects;
3. check whether behavior is already a certified contract;
4. patch only `v320-ga-hardening`;
5. add/update focused verifier/E2E coverage;
6. run non-destructive GA regression;
7. perform semantic diff review;
8. redeploy staging with backup/rollback;
9. retest only the affected behavior plus relevant regression boundary;
10. update GA evidence matrix.

Never patch production directly.

## 13. Final return package to the chat agent/user

Return:

- staging release SHA;
- E2E summary + artifacts;
- cross-tenant/privilege security results;
- DAST summary;
- load metrics;
- DR RPO/RTO and verifier output;
- monitoring validation;
- external pentest status/findings;
- unresolved risks;
- any GA-branch commits created by Work;
- final clean/remote HEAD;
- recommendation evidence for the formal readiness review.

Do not merge to the protected baseline or deploy GA to production unless the user explicitly approves the final release step.
