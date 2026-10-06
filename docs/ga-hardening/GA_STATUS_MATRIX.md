# Renee AgriSuite v3.2 — GA Evidence / Status Matrix

Status vocabulary:

- **PASS** — evidence exists for the stated scope.
- **PASS WITH RESIDUAL RISK** — evidence is positive but an explicit residual condition remains.
- **FAIL** — a tested requirement failed and remains unresolved.
- **BLOCKED** — cannot be completed from repository/source alone; infrastructure/external access is required.
- **NOT TESTED** — planned test has not yet been executed.

This matrix deliberately distinguishes source evidence from runtime certification.

## Final repository CI evidence

A complete GitHub Actions run on GA commit `47e12977c69be2896344400b126798b9890ded3b` completed successfully before this documentation-only status update:

- workflow run: `37072381430` / run #64;
- Source regression: **SUCCESS**;
- Dependency assurance: **SUCCESS**;
- Composer manifest validation: **SUCCESS**;
- `composer audit --locked --no-dev`: **SUCCESS**;
- locked production dependency install with `--no-scripts`: **SUCCESS**.

Because this status file itself changes the branch HEAD, GitHub CI must also pass once more on the documentation-only checkpoint before runtime handoff. No product, security, billing, inventory, lifecycle or schema code is changed by this status update.

| GA area | Current status | Evidence / result | Next owner |
|---|---|---|---|
| GA-1 Architecture & attack-surface inventory | PASS | `GA1_ATTACK_SURFACE.md` rebuilt from protected baseline; major public/auth/API/billing/domain/config/PDF surfaces catalogued | Chat agent complete |
| GA-2 Internal source security review | PASS WITH RESIDUAL RISK | `GA2_SECURITY_REVIEW.md`; source review found and fixed stale-session revocation gap, login CSRF gap and PDF exception disclosure; runtime adversarial testing still required | Chat agent source complete / Work runtime |
| Cross-tenant IDOR source tracing | PASS WITH RESIDUAL RISK | Representative inventory, daily record, expense, sale, allocation, Platform Owner routes are farm-bound in reviewed source; API-wide minimum contract verifier added | Work must execute two-tenant probes |
| Privilege-boundary source review | PASS WITH RESIDUAL RISK | Canonical permission runtime, tenant guard, Platform Owner read-only support view and permission save boundary inspected | Work runtime escalation probes |
| Credential token source lifecycle | PASS WITH RESIDUAL RISK | Hash-only token storage, expiry, one-time use, supersession, row lock/transaction observed; session revocation added | Work replay/expiry/multi-session E2E |
| Login CSRF | PASS at source | `sign.php` uses shared CSRF field + POST verifier; focused source contract exists | Work browser negative test |
| Password-change session revocation | PASS at source | shared credential fingerprint + tenant-bound revalidation; focused verifier exists | Work disposable staging E2E |
| PDF error disclosure | PASS at source | raw exception removed; generic browser response; remote loading remains disabled and chroot remains enabled | Work PDF negative probes |
| API minimum entry-point contract | PASS | Final source regression on commit `47e12977...` passed after verifier was aligned with canonical API helpers | Work direct-route/IDOR runtime probes |
| GA-3 source security scan | PASS | Included in successful final source regression on `47e12977...` | Work runtime DAST remains separate |
| Dependency advisory scan | PASS | Independent CI dependency-assurance job; Composer locked audit succeeded on `47e12977...` | Re-run automatically on subsequent GA pushes/PRs |
| Dependency lock install | PASS | Locked production dependency install with `--no-scripts` succeeded on `47e12977...` | Re-run automatically on subsequent GA pushes/PRs |
| GA-4 deterministic regression runner | PASS | Non-destructive runner succeeded on `47e12977...`; destructive historical/live certification excluded by design | GitHub CI on every GA push/PR |
| Automated GitHub CI | PASS | Source regression and dependency assurance are isolated jobs and both succeeded on `47e12977...` | Automatic on subsequent GA changes |
| GA-5 Playwright suite authoring | PASS for prepared foundation | auth, invalid login, tenant-boundary/direct-route probes, error leakage and opt-in session-revocation test prepared | Work execution/extension |
| Critical business E2E execution | PASS | authenticated staging browser regression completed; 7 scenarios total, 5 passed and 2 intentionally skipped | Complete |
| Staging certification | PASS | isolated GA staging runtime was deployed and used for browser, security, DR-adjacent and operational certification work | Complete |
| GA-7 load/stress harness | PASS for preparation | k6 authenticated read harness prepared with multi-session design and 10→25→50→100 VU ramp | Work execution + write workload |
| Load/stress results | PASS WITH RESIDUAL RISK | bounded shared-host runtime load certification completed with no HTTP failures in the certified envelope; maximum-capacity breaking point not established | Capacity ceiling remains operational follow-up |
| GA-8 DR post-restore verifier | PASS for preparation | isolated-read-only verifier checks core tables/tenant keys/migration marker/aggregate counts/orphans | Work actual restore drill |
| DR restore/RPO/RTO evidence | PASS | isolated restore PASS; measured technical RTO 3.449 seconds (reported 4 seconds), restore class PREPROVISIONED_ISOLATED_TARGET; production RPO <= 6 hours is automated with off-host backup and freshness monitoring; see `RTO_RPO_CLOSEOUT.md` | Complete |
| GA-9 monitoring contract | PASS for design | signals, secret-redaction policy, alert classes and runtime acceptance criteria documented | Work deploy/validate |
| Monitoring runtime validation | PASS WITH RESIDUAL RISK | production/staging availability monitoring and production backup-freshness monitoring are deployed and reproducible; broader infrastructure/dashboard coverage remains an observability follow-up | Operations |
| Architecture documentation | PASS | `docs/ARCHITECTURE.md` | Chat agent complete |
| API documentation | PASS | `docs/API.md` | Chat agent complete |
| Formal release checklist | PASS | `RELEASE_CHECKLIST_V3.2.md` | Chat agent complete; update with runtime evidence later |
| GA changelog | PASS | `CHANGELOG_V3.2_GA.md` records only fresh GA work; explicitly not production deployment | Chat agent complete |
| ChatGPT Work handoff | PASS | `CHATGPT_WORK_HANDOFF.md` constrains runtime execution and evidence | Ready for Work after documentation-only checkpoint CI passes |
| Runtime DAST/security probing | PASS WITH RESIDUAL RISK | tenant IDOR, authorization, credential/session, password recovery/reset, PDF and runtime exposure probes completed; independent third-party pentest remains separate | External pentest remains |
| Independent third-party pentest | BLOCKED | must be genuinely independent | External tester |
| Formal production-readiness review | PASS WITH EXTERNAL-PENTEST CONDITION | internal technical GA hardening, bounded load, DR, formal RTO/RPO and runtime monitoring evidence are complete; independent external pentest remains outside this internal certification | User / external tester |
| v3.2 General Availability | TECHNICALLY READY WITH EXTERNAL CONDITIONS | internal technical readiness is positive; independent external pentest remains a release-governance condition, live billing remains a launch decision while billing is in TEST mode, and maximum-capacity breaking-point benchmarking is not established | User / external tester |

## Known source fixes introduced by fresh GA run

1. Stale authenticated sessions revoked after any password-hash change on next protected request.
2. Login POST/form uses canonical CSRF protection.
3. PDF failure no longer exposes raw exception messages.
4. Security/static scanning, API contracts and deterministic regression are CI-enforced.

## Expected operational effect when GA auth hardening is first deployed

Sessions created by the old build do not carry the new credential fingerprint. They will fail closed on the next protected request and users will need to sign in again. This is expected credential/session rotation, not account deletion or password change.

## Do-not-repeat / frozen evidence

Absent regression evidence, do not re-run destructive historical billing/seat certification, migrations 089/090/091, or historical certified tenant provisioning merely to populate this matrix. Existing certified contracts remain evidence; new GA work should test only changed/risk-relevant boundaries.

## Next status transition

After the documentation-only checkpoint CI succeeds, repository-side preparation is complete. Runtime work should continue from `CHATGPT_WORK_HANDOFF.md` on isolated staging. The protected baseline `v320-renee-agrisuite-branding` remains untouched.
