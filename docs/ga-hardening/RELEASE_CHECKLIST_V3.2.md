# Renee AgriSuite v3.2 — GA Release Checklist

This checklist is evidence-driven. Mark an item PASS only when the linked source/runtime evidence exists. `BLOCKED` and `NOT TESTED` are valid states; do not convert them to PASS by assumption.

## A. Source/branch integrity

- [ ] `v320-renee-agrisuite-branding` remains frozen at the protected baseline.
- [ ] GA work is only on `v320-ga-hardening`.
- [ ] working tree is clean before release candidate tag/merge.
- [ ] local/remote HEAD match.
- [ ] remote semantic diff from protected baseline reviewed.
- [ ] no production-only `config.php` replacement is included.
- [ ] no secrets/tokens/runtime uploads/logs are committed.

## B. Source quality and regression

- [ ] PHP lint PASS for all tracked application PHP outside vendor/runtime folders.
- [ ] `scripts/ga_source_security_scan.php` PASS with review-only findings classified.
- [ ] `scripts/verify_v320_password_session_invalidation.php` PASS.
- [ ] `scripts/verify_v320_login_security_contract.php` PASS.
- [ ] static closed-workstream verifiers in the GA runner PASS.
- [ ] `composer validate` PASS.
- [ ] GitHub GA regression workflow PASS on final HEAD.

## C. Dependency assurance

- [ ] `composer.lock` is committed and corresponds to `composer.json`.
- [ ] `composer audit --locked --no-dev` PASS on final HEAD, or each advisory has documented disposition.
- [ ] dependency install from lock succeeds without scripts.
- [ ] no abandoned dependency creates an unresolved high-risk condition.
- [ ] Dompdf configuration keeps remote loading disabled and application-root chroot enabled.

## D. Authentication and credential security

- [ ] valid/invalid farm login E2E PASS.
- [ ] Platform Owner login E2E PASS.
- [ ] login CSRF contract PASS.
- [ ] login/session fixation test PASS (`session_regenerate_id`).
- [ ] session cookies verified Secure/HttpOnly/SameSite on staging HTTPS.
- [ ] inactivity timeout verified.
- [ ] forgot-password privacy response verified.
- [ ] activation/reset token expiry/one-time/supersession verified.
- [ ] password reset revokes previously authenticated session.
- [ ] administrator password change revokes target user's previous sessions.
- [ ] old password fails/new password succeeds after reset.
- [ ] no raw credential token/password in logs.

## E. Tenancy / authorization

- [ ] Farm A object IDs denied to Farm B for reads.
- [ ] Farm A object IDs denied to Farm B for edits.
- [ ] Farm A object IDs denied to Farm B for deletes.
- [ ] tested object families include users, cycles, daily records, animals, inventory, stock transactions, sales, expenses, allocations, reports and billing views.
- [ ] Sales Rep cannot escalate to Farm Admin/Platform Owner.
- [ ] Farm Admin cannot escalate to Platform Owner.
- [ ] crafted role/permission POST cannot assign unauthorized privilege.
- [ ] navbar/page/API permission parity verified.
- [ ] Platform Owner selected-tenant read-only support cannot escape into tenant mutation.

## F. CSRF / request safety

- [ ] all browser-authenticated mutations require correct HTTP method.
- [ ] all browser-authenticated mutations require CSRF.
- [ ] invalid/missing CSRF rejected consistently.
- [ ] JSON/form validation rejects negative/out-of-range/foreign IDs as appropriate.
- [ ] unexpected exceptions do not expose SQL/path/stack traces.

## G. XSS / output

- [ ] stored XSS probes completed on customer/remarks.
- [ ] stored XSS probes completed on farm/user names.
- [ ] stored XSS probes completed on category/item names.
- [ ] stored XSS probes completed on animal/tag fields.
- [ ] report/PDF rendering does not execute/inject scripts.
- [ ] JavaScript-context JSON escaping reviewed.
- [ ] CSP/header runtime evidence captured.

## H. Billing/commercial

Do not rewrite historical certified billing data merely to rerun tests.

- [ ] webhook invalid signature rejected.
- [ ] replay/idempotency test PASS in staging/test provider mode.
- [ ] amount/currency/plan/farm mismatch rejected.
- [ ] callback/webhook race test does not double-apply commercial state.
- [ ] seat top-up exactly-once regression remains intact.
- [ ] test credentials used for automated billing work.
- [ ] sandbox checkout is not dangerously reachable/configured in production.

## I. Inventory / Sales / finance

- [ ] General Inventory receive/sale/edit/delete lifecycle E2E PASS on disposable data.
- [ ] stock transaction ledger reconciles after reversal/replacement.
- [ ] cross-tenant stock item and stock transaction access denied.
- [ ] receivable lifecycle E2E PASS.
- [ ] expense create/edit/delete E2E PASS.
- [ ] financial allocation update E2E PASS.
- [ ] sale revenue allocation E2E PASS.
- [ ] stock-consumption allocation E2E PASS.
- [ ] profitability report totals reconcile to known staging fixture.

## J. Poultry / ruminant

- [ ] Layer daily record critical flow E2E PASS.
- [ ] Broiler daily record critical flow E2E PASS.
- [ ] Poultry feeds/expenses critical flows E2E PASS.
- [ ] poultry slaughter lifecycle/population test PASS on disposable staging data.
- [ ] Ruminant registry/daily/feed/expense critical flows E2E PASS.
- [ ] Ruminant slaughter/lifecycle exit test PASS on disposable staging data.
- [ ] species-scoped tag contract remains intact.
- [ ] no cross-tenant cycle/animal mutation.

## K. Staging certification

- [ ] isolated staging application exists.
- [ ] separate staging DB confirmed.
- [ ] only sanitized/synthetic data used.
- [ ] test Paystack/provider credentials confirmed.
- [ ] safe SMTP/test-recipient policy confirmed.
- [ ] staging BASE_URL/session boundary confirmed.
- [ ] obvious staging marker present.
- [ ] no staging job points at production DB/storage/payment secrets.

## L. Playwright E2E

- [ ] dependencies installed from `tests/e2e/package.json`.
- [ ] Chromium/browser runtime installed.
- [ ] public auth tests PASS.
- [ ] tenant-boundary tests PASS.
- [ ] role/permission direct-route tests PASS.
- [ ] credential-revocation test PASS on disposable account.
- [ ] high-value sales/inventory/financial/livestock scenarios completed.
- [ ] traces/screenshots for failures reviewed and resolved/dispositioned.

## M. Load/performance

- [ ] k6/Locust executed only against isolated staging.
- [ ] representative pre-authenticated sessions prevent single-session serialization distortion.
- [ ] 10 → 25 → 50 → 100 concurrent-user ramp executed.
- [ ] dashboard/inventory/reporting read load measured.
- [ ] controlled sales/inventory writes measured with disposable data.
- [ ] p50/p95/p99 recorded.
- [ ] throughput/error rate recorded.
- [ ] CPU/memory/PHP workers/DB connections/slow queries recorded.
- [ ] no unresolved deadlock/lock-contention/capacity blocker.

## N. Disaster recovery

- [ ] known backup selected with timestamp.
- [ ] clean isolated filesystem + DB target created.
- [ ] files restored.
- [ ] DB restored.
- [ ] environment configuration applied without production secret leakage.
- [ ] `GA_DR_ISOLATED_RESTORE=YES php scripts/ga_dr_post_restore_verify.php` PASS.
- [ ] login and representative business flows PASS on restored target.
- [ ] subscription/inventory/sales/expenses/poultry/ruminant/report state verified.
- [ ] restore duration recorded as RTO evidence.
- [ ] backup age/data-loss window recorded as RPO evidence.

## O. Monitoring / operations

- [ ] central collector receives staging application error.
- [ ] 5xx/latency dashboards available.
- [ ] security events represented without secrets.
- [ ] billing/provider failures monitored.
- [ ] email/outbox failures monitored.
- [ ] CPU/memory/disk/PHP workers/MySQL connections/slow queries monitored.
- [ ] backup age/success and TLS expiry monitored.
- [ ] alert destination receives controlled test alert.
- [ ] event redaction inspected.
- [ ] monitoring retention/access policy documented.

## P. Documentation/change control

- [ ] `docs/ARCHITECTURE.md` current.
- [ ] `docs/API.md` current.
- [ ] GA attack-surface inventory current.
- [ ] monitoring/operations contract current.
- [ ] release checklist current.
- [ ] GA status/evidence matrix current.
- [ ] release notes/changelog summarize GA source changes.
- [ ] rollback plan documented.

## Q. Independent security assurance

- [ ] internal source/security review complete.
- [ ] runtime DAST/staging probes complete.
- [ ] independent third-party penetration test complete.
- [ ] critical/high findings remediated and retested.
- [ ] accepted residual risks documented with owner/date.

## R. Final readiness decision

Each category must be recorded as one of:

- PASS
- PASS WITH RESIDUAL RISK
- FAIL
- BLOCKED
- NOT TESTED

Do **not** call v3.2 General Availability while an unresolved Critical/High security, tenant-isolation, billing-integrity, data-integrity, recovery or capacity blocker remains.
