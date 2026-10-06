# Renee AgriSuite v3.2 — GA Hardening Changelog

This changelog describes work on `v320-ga-hardening`. It does not mean the changes are deployed to production.

Protected known-good baseline: `v320-renee-agrisuite-branding` at `10041da3c4e4a7b8101f8183c243d056c7b128`.

## Clean restart after interrupted prior run

The previous GA attempt had advanced `v320-ga-hardening` beyond the handoff while a network interruption made its evidence unreliable. Before redoing GA work:

- the interrupted state was preserved at `v320-ga-hardening-interrupted-20261002`;
- `v320-ga-hardening` was restored to the exact protected baseline;
- GA evidence was rebuilt from source rather than assuming the interrupted documents/tests were valid.

## GA-1 — Architecture & attack-surface inventory

Added:

- `docs/ga-hardening/GA1_ATTACK_SURFACE.md`

The inventory maps public credential routes, sessions, tenancy/authorization, APIs, billing, inventory/sales/financial flows, poultry, ruminant, management, SQL/output/PDF/config/dependency surfaces and risk-ranked GA-2 priorities.

No product code was changed during GA-1.

## GA-2/GA-3 — Source security hardening

### Password-change session revocation

Finding: password reset/admin password change updated the credential hash but there was no source-visible mechanism binding already-authenticated PHP sessions to the new credential state.

Implemented centrally in `includes/password_security.php`:

- one-way session fingerprint derived from the current stored password hash;
- binding on successful login;
- tenant-bound revalidation on protected requests;
- stale/missing fingerprint fails closed;
- any password hash change revokes older sessions on their next protected request;
- public credential routes can finish their PRG confirmation before stale-session destruction so recovery UX is preserved.

Updated:

- `sign.php`

Added verifier:

- `scripts/verify_v320_password_session_invalidation.php`

Operational note: when this hardening change is first deployed, authenticated sessions created by the older build do not have the new fingerprint and will be required to sign in again.

### Login CSRF

Finding: `sign.php` established authenticated session state without using the shared CSRF form contract.

Implemented:

- shared `require_valid_csrf_post()` on login POST;
- shared `csrf_field()` in the login form;
- existing guest rate limit, generic failure, prepared lookup, credential-state gate and session rotation remain in place.

Added verifier:

- `scripts/verify_v320_login_security_contract.php`

### PDF exception disclosure

Finding: PDF generation failure returned `$e->getMessage()` directly to the browser.

Updated `includes/pdf/PdfReportService.php`:

- server log records failure class only;
- browser receives generic temporary-unavailability message;
- existing Dompdf `isRemoteEnabled=false` and application-root `chroot` remain unchanged.

## GA-3 — Source/dependency vulnerability scanning framework

Added:

- `scripts/ga_source_security_scan.php`

The non-destructive scanner checks high-confidence secret/dangerous patterns and verifies key PDF/config security contracts. Review-only dangerous-function findings are separated from CI blockers so regex results are not misrepresented as penetration-test findings.

Composer dependency audit is executed in GitHub CI against `composer.lock`.

## GA-4 — Regression foundation / CI

Added:

- `scripts/run_v320_ga_regression.sh`
- `.github/workflows/ga-regression.yml`

Default runner:

- PHP lint across application source;
- GA source-security scan;
- new authentication/session security contracts;
- known source/static closed-workstream verifiers when present;
- Composer manifest validation;
- explicitly skips environment/DB/live-state certification by default.

GitHub workflow additionally runs Composer advisory audit and validates locked dependency installation without scripts.

Historical/destructive billing/migration/provisioning certification is intentionally excluded from automatic CI.

## GA-5 — Playwright E2E foundation

Added under `tests/e2e/`:

- Playwright package/config;
- reusable farm-login helper;
- public/valid/invalid authentication tests;
- cross-tenant inventory IDOR probe;
- direct denied-route probe;
- raw SQL/PHP error leakage probe;
- opt-in password-reset/session-revocation test for a disposable staging account.

Environment-dependent tests skip when staging credentials/fixtures are not provided. The destructive credential test requires an explicit opt-in flag.

## GA-7 preparation — Load harness

Added:

- `tests/load/k6_authenticated_read.js`

The harness is staging-only and requires pre-authenticated disposable session cookies. It supports a 10 → 25 → 50 → 100 VU ramp across configured authenticated read routes while recording error/latency thresholds.

It intentionally avoids using one shared PHP session because PHP session locking could produce misleading serialized performance results.

Controlled write-load for sales/inventory remains a staging execution task.

## GA-8 preparation — Disaster recovery

Added:

- `scripts/ga_dr_post_restore_verify.php`

The verifier:

- refuses to run without `GA_DR_ISOLATED_RESTORE=YES`;
- is read-only;
- checks core restored tables/tenant keys/migration marker;
- reports only aggregate counts, never tenant data or credentials;
- checks selected orphan relationships.

The infrastructure-dependent restore was subsequently executed and certified during GA runtime hardening. The isolated restore passed with a measured technical RTO of 3.449 seconds (reported as 4 seconds) on a pre-provisioned isolated target. Production RPO <= 6 hours was then operationalized with six-hour full backups, private off-host Backblaze B2 copies, retention automation and backup-freshness monitoring. See `RTO_RPO_CLOSEOUT.md`.

## GA-9 preparation — Monitoring/operations

Added:

- `docs/ga-hardening/MONITORING_OPERATIONS.md`

Defines application/security/commercial/infrastructure signals, secret-redaction policy, alert classes and runtime acceptance criteria for staging/Work.

## GA-10 — Documentation/release package

Added:

- `docs/ARCHITECTURE.md`
- `docs/API.md`
- `docs/ga-hardening/RELEASE_CHECKLIST_V3.2.md`
- this GA hardening changelog

Existing historical release/install notes are not blindly replaced.

## Production deployment status

GitHub presence alone still does not imply that every GA branch change is live. However, the private production backup workers, availability/backup monitoring components and production RPO cron automation described in the runtime closeout were deployed through guarded source-to-runtime integrity checks. Other GA changes retain their own deployment evidence and must not be inferred live solely from this changelog.
