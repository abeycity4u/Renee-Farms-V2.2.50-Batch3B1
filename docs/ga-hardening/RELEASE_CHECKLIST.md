# Renee AgriSuite v3.2 GA Release Checklist

This checklist is the formal release gate for declaring Renee AgriSuite v3.2 General Availability. A checkbox may be marked only with evidence. `DEFERRED` is not equivalent to `PASS`.

## 1. Source / branch integrity

- [ ] Release candidate is on `v320-ga-hardening` or a dedicated release-candidate branch created from it.
- [ ] Protected `v320-renee-agrisuite-branding` baseline remains unchanged.
- [ ] Local/remote release-candidate HEAD match.
- [ ] Worktree is clean.
- [ ] GitHub semantic diff from protected baseline has been reviewed.
- [ ] No runtime secrets, production `.htaccess`, `.env`, logs, backups or uploaded tenant files are tracked.
- [ ] `composer.lock` is committed and matches `composer.json`.

## 2. Automated source gates

- [ ] Composer metadata validation passes.
- [ ] Locked dependency installation passes.
- [ ] `composer audit --locked` passes with no unaccepted advisory.
- [ ] PHP lint passes for all application PHP outside `vendor`.
- [ ] GA source-security contract passes.
- [ ] GA source vulnerability scan has zero high-confidence findings.
- [ ] Login/session security contract passes.
- [ ] Permission architecture contract passes.
- [ ] Navigation/permission parity contract passes.
- [ ] Tenant authorization contract passes.
- [ ] PDF security contract passes.
- [ ] Credential email / password-recovery contracts pass.
- [ ] Inventory/category and other required architecture regression contracts pass.
- [ ] Script/verifier directories remain HTTP denied.

## 3. Open-finding gate

- [ ] GA-SEC-001 resolved and regression-guarded.
- [ ] GA-PDF-001 resolved and regression-guarded.
- [ ] GA-CSP-001 either resolved or explicitly risk-accepted with E2E evidence and a dated removal plan.
- [ ] GA-REV-001 exception-disclosure review completed.
- [ ] GA-REV-002 remaining tenant-ownership review completed.
- [ ] GA-REV-003 remaining report/PDF review completed.
- [ ] No Critical/High security finding remains open.
- [ ] No unresolved financial/data-integrity finding remains open.

## 4. Authentication / account lifecycle

- [ ] Tenant login works with valid credentials.
- [ ] Invalid tenant/workspace credentials return generic failure.
- [ ] Platform login works for Platform Owner.
- [ ] Session ID rotates at login.
- [ ] Session timeout returns user to canonical sign-in route.
- [ ] Logout invalidates authenticated session.
- [ ] Activation token is expiring, single-use and superseding.
- [ ] Password reset request is enumeration safe.
- [ ] Password reset token is expiring and single-use.
- [ ] New password works after reset.
- [ ] Previous password is rejected after reset.
- [ ] Login/reset request throttling is verified in staging.

## 5. Tenant isolation / RBAC staging gate

Use at least two unrelated test farms and multiple roles.

- [ ] Farm A cannot read Farm B Sales records by guessed/known ID.
- [ ] Farm A cannot edit/delete Farm B Sales records.
- [ ] Farm A cannot read/update Farm B Inventory items/history.
- [ ] Farm A cannot read/edit/delete Farm B Expenses.
- [ ] Farm A cannot access Farm B Production Cycles.
- [ ] Farm A cannot access Farm B Poultry daily records.
- [ ] Farm A cannot access Farm B Ruminant animals/daily records.
- [ ] Farm A cannot mutate Farm B allocation workspaces.
- [ ] Farm A cannot access Farm B Team Users/Permissions.
- [ ] Farm A cannot access Farm B billing/account resources.
- [ ] Farm A cannot export Farm B reports/PDFs.
- [ ] Specialist role boundaries are enforced independently of navigation visibility.
- [ ] Sales-only tenant does not receive Poultry/Ruminant operational authority.

## 6. Critical business-flow E2E

- [ ] Sales-only onboarding → activation → login.
- [ ] Poultry tenant onboarding → activation → login.
- [ ] Ruminant tenant onboarding → activation → login.
- [ ] General Inventory receive → sell → edit → delete restores ledger correctly.
- [ ] Poultry sale lifecycle passes.
- [ ] Ruminant live-sale lifecycle passes.
- [ ] Slaughter lifecycle passes without unintended Sales revenue creation.
- [ ] Expense create/edit/delete passes for entitled roles.
- [ ] Shared financial allocation create/revise/retain-shared passes.
- [ ] Receivable sale + later debt payment passes.
- [ ] Permission assignment/revocation changes direct route access immediately.
- [ ] Team User invite/activate/edit/delete passes.
- [ ] Password recovery passes end-to-end.
- [ ] Billing checkout success path passes in provider test mode.
- [ ] Seat top-up exactly-once behavior passes in provider test mode.

## 7. Payment / billing integrity

- [ ] Invalid webhook signature rejected.
- [ ] Valid tracked webhook accepted.
- [ ] Untracked provider reference rejected/ignored according to contract.
- [ ] Duplicate/replayed webhook is idempotent.
- [ ] Paid attempt cannot be finalized twice.
- [ ] Subscription history remains immutable.
- [ ] Trial → paid transition preserves canonical dates/entitlements.
- [ ] Seat proration rules match plan/interval authority.
- [ ] Refund/reversal administrative workflow is tested where applicable.

## 8. HTTP/application security

- [ ] HTTPS enforced by deployment.
- [ ] Secure + HttpOnly + SameSite session cookie observed in staging.
- [ ] HSTS observed over HTTPS.
- [ ] CSP observed on representative public/authenticated pages.
- [ ] `X-Content-Type-Options` observed.
- [ ] frame protection observed.
- [ ] Referrer Policy observed.
- [ ] Permissions Policy observed.
- [ ] Sensitive paths (`config.php`, `.env`, `.git`, `vendor`, `migrations`, `scripts`, docs, backups/logs) are denied from HTTP.
- [ ] Error pages do not reveal stack traces/SQL/file paths/secrets.
- [ ] Upload paths do not execute PHP/server-side code.

## 9. Dependency / source security

- [ ] Composer advisory audit current on release day.
- [ ] Secret-file/source scan current on release day.
- [ ] No hard-coded production API/SMTP/database credentials in tracked source.
- [ ] No dangerous web-reachable `eval`, shell/process execution or request-controlled include detected.
- [ ] Raw exception-response review completed.
- [ ] PDF remote-resource policy remains disabled/chrooted.

## 10. Performance / load

Run only against isolated staging or an approved performance environment.

- [ ] Production-like dataset prepared with sanitized data.
- [ ] Mixed workload covers login, dashboard, inventory, sales, daily records and reports.
- [ ] Baseline concurrency target documented.
- [ ] p50 latency recorded.
- [ ] p95 latency recorded and within acceptance target.
- [ ] p99 latency recorded.
- [ ] HTTP error rate within acceptance target.
- [ ] DB connection usage observed.
- [ ] CPU/memory observed.
- [ ] Slow-query evidence reviewed.
- [ ] No duplicate financial/stock writes under concurrency.

## 11. Disaster recovery

- [ ] Fresh application backup produced.
- [ ] Fresh database backup produced.
- [ ] Backup checksums recorded.
- [ ] Clean restore target prepared.
- [ ] Application files restored.
- [ ] Database restored without using production DB credentials.
- [ ] Required environment values recreated securely.
- [ ] Migrations/schema marker state verified.
- [ ] Login works on restored environment.
- [ ] Tenant count/user bindings verified.
- [ ] Subscription/billing history verified.
- [ ] Inventory ledger/current balances reconcile.
- [ ] Sales/receivables reconcile.
- [ ] Livestock current population agrees with lifecycle records.
- [ ] RPO measured/documented.
- [ ] RTO measured/documented.

## 12. Monitoring / operations

- [ ] Application error logging centralized.
- [ ] Sensitive values are redacted from logs.
- [ ] Failed-login/rate-limit security signals observable.
- [ ] Payment webhook/finalization failures alertable.
- [ ] Queue/outbox failures observable.
- [ ] HTTP health check available.
- [ ] DB health signal available without exposing secrets.
- [ ] Disk/storage threshold monitoring enabled.
- [ ] Backup freshness monitoring enabled.
- [ ] Alert delivery tested to an owned operational channel.
- [ ] Log retention and access policy documented.

## 13. Documentation

- [ ] Architecture document reviewed.
- [ ] API/async route guide reviewed.
- [ ] Installation/migration guide matches release.
- [ ] Release notes/changelog completed.
- [ ] Backup/restore runbook completed.
- [ ] Monitoring/incident runbook completed.
- [ ] Staging E2E/load instructions completed.
- [ ] Known limitations documented.

## 14. Independent security assurance

- [ ] Internal GA security review complete.
- [ ] Runtime DAST/staging security exercise complete.
- [ ] Independent third-party penetration test complete.
- [ ] Critical/High pentest findings remediated and retested.
- [ ] Accepted lower-severity findings documented with owner/date.

## 15. Final release decision

- [ ] Release candidate commit SHA recorded.
- [ ] All release blockers resolved.
- [ ] Rollback package/procedure verified.
- [ ] Production backup taken immediately before deploy.
- [ ] Controlled deployment plan approved.
- [ ] Post-deploy smoke test defined.
- [ ] Production readiness review says **GO**.

Only after every mandatory gate has evidence may the release be labelled **Renee AgriSuite v3.2 GA**.
