# Renee Farms Platform V2.3 Development History

Audit snapshot: 2026-09-12

## Authoritative source

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`
- Branch: `v230-commercial-hardening-saas-readiness`
- Production runtime HEAD: `60b4e89f837ed424f8de025f130613e587effc14`
- Production: `https://reneefarms.com`
- Server checkout: `~/renee-deploy`
- Live root: `~/public_html`

## Current deployment position

Production runtime is now carried forward through commit `754711e`. The 2026-09-11 to 2026-09-12 sequence completed commercial billing coordination and seat-change hardening, Stage 2I TEST/SANDBOX end-to-end payment proof, CSP Report-Only observation, production CSP enforcement, legacy/manual billing-period resilience, subscription-history date clarification and the first tenant-facing paid-payment receipt milestone.

CSP is now enforcing in production. The clean Report-Only observation and owner approval were followed by an initial enforcement attempt that failed safely because mixed OPcache generations allowed new `config.php` code to call a newer CSP emitter while an older cached policy file remained loaded. That attempt was rolled back. Commit `59f6e63` made the rollout OPcache-compatible, and the subsequent policy-only deployment passed repeated unauthenticated probes, authenticated browser smoke and post-smoke log observation.

Commercial payment execution remains TEST/SANDBOX only. Stage 2I sandbox proof is closed and its temporary launch gates were removed. Seat top-up, scheduled seat reduction/cancellation, farm-wide pending-attempt coordination and terminal reconciliation were completed and tested without approving live production payment processing.

Farm A LLC billing is resilient when legacy/manual commercial history has no authoritative paid-period lineage. Commit `a84e7ad` keeps the Billing workspace available while paid-period-dependent seat changes remain disabled. Commit `60b4e89` separates administrative `subscription_ends_at` from authoritative `current_period_ends_at` in Subscription History so a manually entered expiry cannot be mistaken for a paid billing period.

Targeted deployments continue to use zero-drift pre-flight guards, rollback backups, live/source hash checks and PHP lint. Production-specific `config.php` and `.htaccess` remain protected and are not blindly overwritten.

Development-only files such as scripts, tests, migrations, notes and deployment documentation are kept in source control but are not required inside the public web runtime.

Documentation-only commits may therefore exist after the production runtime HEAD without requiring another production deployment.

## Historical committed checkpoints

| # | Commit | Recorded checkpoint | Actual Git subject | Lineage |
|---:|---|---|---|---|
| 01 | `a6790f6` | CSP external dependency Batch 1 | Reduce external asset dependencies | Current HEAD ancestor |
| 02 | `c1857c0` | CSP external dependency Batch 2 | Localize DataTables styling dependencies | Current HEAD ancestor |
| 03 | `88f5491` | CSP external dependency Batch 3 | Remove external Google Fonts dependency | Current HEAD ancestor |
| 04 | `01cc188` | Inline event-handler timing fix | Ensure CSP behavior loads before resource failures | Current HEAD ancestor |
| 05 | `18f0100` | Batch 14 - animal_view | Move membership modal behavior out of inline script | Current HEAD ancestor |
| 06 | `0ea2fae` | Batch 15 - sign | Move sign-in behavior out of inline script | Current HEAD ancestor |
| 07 | `0a3d8bb` | Batch 16 - index | Move homepage slideshow out of inline script | Current HEAD ancestor |
| 08 | `544adb8` | Batch 17 - animal_registry | Move ruminant registry behavior out of inline script | Current HEAD ancestor |
| 09 | `8f14a4a` | Batch 18 - poultry health | Move poultry health behavior out of inline script | Current HEAD ancestor |
| 10 | `2496234` | Batch 19 - broiler_expenses | Move broiler expenses behavior out of inline script | Current HEAD ancestor |
| 11 | `eddbc78` | Batch 20 - layer_expenses | Move layer expenses behavior out of inline script | Current HEAD ancestor |
| 12 | `fa3fc9c` | Batch 21 - ruminant_expenses | Move ruminant expenses behavior out of inline scripts | Current HEAD ancestor |
| 13 | `1bf1f87` | Batch 22 - ruminant feeds | Move ruminant feeds behavior out of inline script | Current HEAD ancestor |
| 14 | `e706a47` | Batch 23 - production cycles | Move production cycles behavior out of inline scripts | Current HEAD ancestor |
| 15 | `e897e91` | Batch 24 - farms | Move farm management behavior out of inline scripts | Current HEAD ancestor |
| 16 | `5ca7607` | Batch 25 - users | Move user management behavior out of inline scripts | Current HEAD ancestor |
| 17 | `b53f275` | Batch 26 - layer feeds | Move layer feeds behavior out of inline script | Current HEAD ancestor |
| 18 | `735fca9` | Batch 27 - poultry/ruminant report | Move poultry ruminant report behavior out of inline script | Current HEAD ancestor |
| 19 | `cafb94f` | Batch 28 - navbar | Move shared navbar behavior out of inline scripts | Current HEAD ancestor |
| 20 | `290afc7` | Batch 29 - permission runtime | Externalize runtime permission behavior for CSP | Current HEAD ancestor |
| 21 | `10f5247` | Batch 30 - production cycle view permissions | Externalize production cycle view permissions behavior | Current HEAD ancestor |
| 22 | `309a0d2` | Batch 31 - subscription plan farms | Externalize subscription plan seat UI for CSP | Current HEAD ancestor |
| 23 | `ebd1603` | Batch 32 - dashboard action permissions | Externalize dashboard stock permission behavior | Current HEAD ancestor |
| 24 | `7914007` | Batch 33 - inventory | Externalize inventory page behavior for CSP | Current HEAD ancestor |
| 25 | `6ee2230` | Batch 34 - management expenses | Externalize management expenses behavior for CSP | Current HEAD ancestor |
| 26 | `fb33867` | Batch 35 - management profitability | Externalize profitability filter behavior for CSP | Current HEAD ancestor |
| 27 | `30ce2de` | Batch 36 - management reports | Externalize management reports behavior for CSP | Current HEAD ancestor |
| 28 | `890d29a` | Batch 37 - management sales records | Externalize sales records behavior for CSP | Current HEAD ancestor |
| 29 | `258fe0b` | Batch 38 - navbar_head shared runtime | Externalize shared head runtime for CSP | Current HEAD ancestor |
| 30 | `9454970` | Batch 39 - API stock history | Externalize stock history behavior for CSP | Current HEAD ancestor |
| 31 | `3ee7fb7` | Batch 40 - dashboard | Externalize dashboard behavior for CSP | Current HEAD ancestor |
| 32 | `1c1b46e` | Batch 41 - broiler feeds initial | Externalize broiler feeds behavior for CSP | Current HEAD ancestor |
| 33 | `491714b` | Batch 41 correction | Fix broiler feeds ledger view URL | Current HEAD ancestor |
| 34 | `f964515` | Batch 42 - broiler daily record | Externalize broiler daily record behavior for CSP | Current HEAD ancestor |
| 35 | `dda4524` | Batch 43 - layer daily record | Externalize layer daily record behavior for CSP | Current HEAD ancestor |
| 36 | `ff21c28` | Batch 44 - ruminant daily record | Externalize ruminant daily record behavior for CSP | Current HEAD ancestor |
| 37 | `6cd93af` | Batch 45 - notifications | Externalize notification styles for CSP | Current HEAD ancestor |
| 38 | `e05121a` | Batch 46 - dashboard static CSS | Externalize dashboard styles for CSP | Current HEAD ancestor |
| 39 | `a7dae9f` | Batch 47 - public index | Externalize public home styles for CSP | Current HEAD ancestor |
| 40 | `4768a2c` | Batch 48 - sign | Externalize sign-in styles for CSP | Current HEAD ancestor |
| 41 | `dc2c541` | Batch 49 - inventory static CSS | Externalize inventory styles for CSP | Current HEAD ancestor |
| 42 | `eecbcd8` | Batch 50 - permissions | Externalize permission matrix styles for CSP | Current HEAD ancestor |
| 43 | `c9b3b7b` | Batch 51 - API stock history | Externalize stock history styles for CSP | Current HEAD ancestor |
| 44 | `63147f4` | Batch 52 - subscription recovery | Externalize subscription recovery styles for CSP | Current HEAD ancestor |
| 45 | `a8ba8c6` | Batch 53 - billing account | Externalize billing account styles for CSP | Current HEAD ancestor |
| 46 | `8233633` | Batch 54 - sandbox checkout | Externalize sandbox checkout styles for CSP | Current HEAD ancestor |
| 47 | `6b0d187` | Batch 55 - management users + Farms & Tenants wording | Externalize user management styles for CSP | Current HEAD ancestor |
| 48 | `c0fbc41` | Batch 56 - shared error pages | Externalize shared error page styles for CSP | Current HEAD ancestor |
| 49 | `61039a6` | Batch 57 - centralized feed ledger styles | Centralize feed ledger styles for CSP | Current HEAD ancestor |
| 50 | `aa3c03a` | Batch 58 - management workspace styles | Centralize management workspace styles for CSP | Current HEAD ancestor |
| 51 | `f7e3935` | Batch 59 - Sales Rep dashboard polish | Externalize Sales Rep dashboard styles for CSP | Current HEAD ancestor |
| 52 | `506dd57` | Batch 60 - permission prepaint | Externalize static permission prepaint styles for CSP | Current HEAD ancestor |
| 53 | `ccef780` | Batch 61 - expense action permission prepaint | Externalize expense permission prepaint styles for CSP | Current HEAD ancestor |
| 54 | `34009fa` | Batch 62 - global permission prepaint | Externalize global permission prepaint styles for CSP | Current HEAD ancestor |
| 55 | `6dbcab8` | Batch 63 - management action permissions | Render management action permissions server-side for CSP | Current HEAD ancestor |
| 56 | `fed5e31` | Batch 64 - tenant theme dynamic stylesheet | Serve tenant theme through CSP-safe stylesheet endpoint | Current HEAD ancestor |
| 57 | `3bb7a96` | Batch 65 - easy static style attrs | Replace static inline style attributes for CSP | Current HEAD ancestor |
| 58 | `46f0a47` | Batch 66 - shared static attrs | Extract shared static style attributes for CSP | Current HEAD ancestor |
| 59 | `8b8d3de` | Batch 67 - feed/report static attrs | Extract static feed and report styles for CSP | Current HEAD ancestor |
| 60 | `0bb2ce4` | Batch 68 - dashboard/report static widths | Extract static dashboard and report styles for CSP | Current HEAD ancestor |
| 61 | `49a0d0f` | Batch 69 - CSP-safe visibility conversion | CSP readiness: convert visibility state to classes | Current HEAD ancestor |
| 62 | `bfd4099` | Batch 70 - remaining static style utilities | CSP readiness: extract remaining static style utilities | Current HEAD ancestor |
| 63 | `a9d429d` | Batch 71 - CSP-safe dynamic inline style replacement | CSP readiness: replace dynamic inline styles with classes | Current HEAD ancestor |
| 64 | `d72c423` | Batch 72 - DataTables Responsive 2.2.9 vendoring | CSP readiness: vendor DataTables Responsive 2.2.9 | Current HEAD ancestor |
| 65 | `a14d623` | Batch 73 - Chart.js 4.4.1 vendoring | CSP readiness: vendor Chart.js 4.4.1 | Current HEAD ancestor |
| 66 | `4299005` | Batch 74 - Bootstrap Icons 1.11.3 vendoring | CSP readiness: vendor Bootstrap Icons 1.11.3 | Current HEAD ancestor |
| 67 | `93c1844` | CSP Report-Only baseline | CSP readiness: add Report-Only baseline | Current HEAD ancestor |
| 68 | `b6e720c` | CSP standalone homepage coverage | CSP readiness: cover standalone homepage | Current HEAD ancestor |
| 69 | `0125bcd` | Permanent V2.3 development history | Document V2.3 development and deployment history | Current HEAD ancestor |
| 70 | `d8ef687` | Feed audit controls and tenant attribution | Harden feed audit controls and tenant attribution | Current HEAD ancestor |
| 71 | `b93ffcb` | Platform-wide Recorded By contract and feed origins | Standardize Recorded By attribution and feed origins | Current HEAD ancestor |
| 72 | `e7b8326` | Investigation actor SQL quoting correction | Fix investigation actor SQL quoting | Production runtime checkpoint |
| 73 | `7b79010` | Development history update | Update V2.3 production development history | Current HEAD ancestor |
| 74 | `b1e72a7` | Runtime/documentation checkpoint clarification | Clarify V2.3 production runtime checkpoint | Current HEAD ancestor |
| 75 | `cccc969` | Homepage slideshow path correction | Fix homepage slideshow image paths | Current HEAD ancestor |
| 76 | `cb3fb9d` | Homepage duplicate preload correction | Avoid duplicate homepage hero image preload | Current HEAD ancestor |
| 77 | `87b74ca` | Homepage chick image metadata cleanup | Strip incompatible metadata from homepage chick image | Current HEAD ancestor |
| 78 | `bf6c0e2` | Poultry Health standards-mode correction | Fix standards mode for poultry health page | Current HEAD ancestor |
| 79 | `1b770ab` | Same-origin CSP report collector | Add same-origin CSP report collector | Current HEAD ancestor |
| 80 | `55971dd` | Vendor console-warning cleanup | Clean vendor asset console warnings | Current HEAD ancestor |
| 81 | `cc78e73` | Final Bootstrap source-map cleanup | Remove final Bootstrap source map reference | Current HEAD ancestor |
| 82 | `36d81d1` | Tooltip fallback initialization correction | Fix tooltip fallback initialization order | Current HEAD ancestor |
| 83 | `3b710ab` | Dashboard Popper regression correction | Remove Dashboard Popper tooltip trigger | Current HEAD ancestor |
| 84 | `70ab9d1` | Tenant-aware generated PDF branding | Brand generated PDFs with tenant farm name | Current HEAD ancestor |
| 85 | `3bba1dc` | Feed transaction PDFs / Expense PDF button visibility | Add feed history PDFs and improve expense PDF buttons | Production runtime checkpoint |
| 86 | `c1049f5` | Update V2.3 commercial hardening history | Update V2.3 commercial hardening history | Current HEAD ancestor |
| 87 | `4f6bfd3` | Add billing seat-change foundation | Add billing seat-change foundation | Current HEAD ancestor |
| 88 | `ce8025d` | Add billing payment purpose support | Add billing payment purpose support | Current HEAD ancestor |
| 89 | `a0cc7f4` | Add billing seat proration service | Add billing seat proration service | Current HEAD ancestor |
| 90 | `315af72` | Gate subscription application by payment purpose | Gate subscription application by payment purpose | Current HEAD ancestor |
| 91 | `79540c5` | Add authoritative billing seat-change request foundation | Add authoritative billing seat-change request foundation | Current HEAD ancestor |
| 92 | `80953b2` | Keep tenant purge compatible with billing seat changes | Keep tenant purge compatible with billing seat changes | Current HEAD ancestor |
| 93 | `f854aeb` | Harden billing seat quote migration FK upgrade | Harden billing seat quote migration FK upgrade | Current HEAD ancestor |
| 94 | `7fc0aaa` | Add guarded billing seat quote migration runner | Add guarded billing seat quote migration runner | Current HEAD ancestor |
| 95 | `94b84a0` | Harden billing seat quote migration preflight | Harden billing seat quote migration preflight | Current HEAD ancestor |
| 96 | `2368b18` | Support explicit billing migration database bootstrap | Support explicit billing migration database bootstrap | Current HEAD ancestor |
| 97 | `2d271dd` | Make billing seat quote migration recoverable on MariaDB | Make billing seat quote migration recoverable on MariaDB | Current HEAD ancestor |
| 98 | `e7aa6a2` | Harden billing migration environment bootstrap | Harden billing migration environment bootstrap | Current HEAD ancestor |
| 99 | `eae6b99` | Drain billing migration dynamic result sets | Drain billing migration dynamic result sets | Current HEAD ancestor |
| 100 | `e5897ad` | Add paid-purpose billing dispatcher | Add paid-purpose billing dispatcher | Current HEAD ancestor |
| 101 | `946c98e` | Add verified seat-top-up application service | Add verified seat-top-up application service | Current HEAD ancestor |
| 102 | `f66dbf0` | Wire seat top-up into paid dispatcher | Wire seat top-up into paid dispatcher | Current HEAD ancestor |
| 103 | `862e64d` | Add failed seat-top-up request cleanup | Add failed seat-top-up request cleanup | Current HEAD ancestor |
| 104 | `b871487` | Add seat top-up initiation foundation | Add seat top-up initiation foundation | Current HEAD ancestor |
| 105 | `88b3cbb` | Add seat top-up route request contract | Add seat top-up route request contract | Current HEAD ancestor |
| 106 | `662dbb0` | Add seat top-up checkout route | Add seat top-up checkout route | Current HEAD ancestor |
| 107 | `89ee95b` | Add seat top-up terminal reconciliation | Add seat top-up terminal reconciliation | Current HEAD ancestor |
| 108 | `f84bcbe` | Handle refunded seat top-up reconciliation | Handle refunded seat top-up reconciliation | Current HEAD ancestor |
| 109 | `4f6e6b3` | Wire terminal seat payment reconciliation | Wire terminal seat payment reconciliation | Current HEAD ancestor |
| 110 | `7904b20` | Make billing return purpose aware | Make billing return purpose aware | Current HEAD ancestor |
| 111 | `929036e` | Expose seat top-up in billing account | Expose seat top-up in billing account | Current HEAD ancestor |
| 112 | `0e12e23` | Add renewal seat target foundation | Add renewal seat target foundation | Current HEAD ancestor |
| 113 | `e185254` | Generalize renewal seat target status scope | Generalize renewal seat target status scope | Current HEAD ancestor |
| 114 | `ae1b3c8` | Add renewal seat application foundation | Add renewal seat application foundation | Current HEAD ancestor |
| 115 | `3890766` | Add commercial attempt coordination foundation | Add commercial attempt coordination foundation | Current HEAD ancestor |
| 116 | `4cae8a7` | Add scheduled seat reduction foundation | Add scheduled seat reduction foundation | Current HEAD ancestor |
| 117 | `10fef2e` | Add subscription checkout initiation foundation | Add subscription checkout initiation foundation | Current HEAD ancestor |
| 118 | `69b276c` | Add commercial attempt disposition foundation | Add commercial attempt disposition foundation | Current HEAD ancestor |
| 119 | `e55c906` | Enforce commercial attempt disposition | Enforce commercial attempt disposition | Current HEAD ancestor |
| 120 | `32948cd` | Add commercial attempt reconciliation foundation | Add commercial attempt reconciliation foundation | Current HEAD ancestor |
| 121 | `99c7710` | Add commercial reconciliation launcher | Add commercial reconciliation launcher | Current HEAD ancestor |
| 122 | `51f34e7` | Protect superseded paid return handling | Protect superseded paid return handling | Current HEAD ancestor |
| 123 | `2401cf7` | Add bounded commercial reconciliation orchestration | Add bounded commercial reconciliation orchestration | Current HEAD ancestor |
| 124 | `dead6d2` | Wire safe subscription replacement checkout | Wire safe subscription replacement checkout | Current HEAD ancestor |
| 125 | `3cf1aea` | Add scheduled seat reduction customer flow | Add scheduled seat reduction customer flow | Current HEAD ancestor |
| 126 | `2454926` | Add scheduled seat reduction cancellation flow | Add scheduled seat reduction cancellation flow | Current HEAD ancestor |
| 127 | `a103e05` | Harden seat top-up replacement checkout | Harden seat top-up replacement checkout | Current HEAD ancestor |
| 128 | `0139675` | Enforce CSP after clean Report-Only observation | Enforce CSP after clean Report-Only observation | Current HEAD ancestor |
| 129 | `59f6e63` | Make CSP enforcement rollout OPcache compatible | Make CSP enforcement rollout OPcache compatible | Current HEAD ancestor |
| 130 | `a84e7ad` | Keep billing available without paid period lineage | Keep billing available without paid period lineage | Current HEAD ancestor |
| 131 | `60b4e89` | Clarify subscription history date semantics | Clarify subscription history date semantics | Production runtime ancestor |
| 132 | `754711e` | Tenant paid-payment receipts | Add tenant billing payment receipts | Production runtime checkpoint |

## 2026-09-10 feed audit and Recorded By closure

- Commit `d8ef687` centralized feed audit action/origin behavior and tenant actor attribution. Manual `manual_feed` Used movements remain editable/reversible for authorized Platform Owner/Farm Admin users; Daily Record and Inventory-owned movements remain controlled by their originating modules.
- Database tracing confirmed the questioned `1.30` Used transaction was `source_type='inventory_manual'`, so it is Inventory-owned rather than a Feed Record manual transaction.
- Commit `b93ffcb` expanded the canonical Recorded By contract across expenses, sales, receivables/debt reports, Poultry Health, Stock History, investigations, ruminant animal exit history, subscription history and Dashboard Recent Sales.
- Canonical synthetic account display now collapses examples such as `Farm A LLC` + legacy `Farm A` Farm Admin to `Farm A LLC — Farm Admin`; genuine person names remain visible as `Farm Name — Person Name (Role)`.
- The mandatory remote GitHub semantic review of `b93ffcb` caught an escaping problem in the double-quoted investigation follow-up SQL before production deployment.
- Commit `e7b8326` corrected that SQL and strengthened `scripts/verify_v230_recorded_by_contract.php` to prevent regression. A second remote GitHub semantic review passed.
- Production deployment backup: `/home/renee/renee-backups/recorded-by-feed-origin-20260910-073813`.
- Production deployment result: 20 runtime files deployed, 0 live hash failures, 19 PHP files linted, 0 lint failures.
- Targeted live UI QA passed for Inventory-origin Feed rows, genuine Manual Feed Used Edit/Reverse behavior, Expenses Recorded By, Poultry Health Recorded By and Dashboard Recent Sales attribution.
- The verifier script remains source/development-only and was not deployed to `public_html`.

## 2026-09-10 commercial runtime hardening continuation

- Homepage runtime was stabilized through `cccc969`, `cb3fb9d` and `87b74ca`; targeted browser QA closed the path/preload/image warning investigation without broad asset replacement.
- Poultry Health returned to standards mode through `bf6c0e2`.
- `1b770ab` deployed the same-origin CSP Report-Only collector. At that 2026-09-10 checkpoint, CSP enforcement remained intentionally paused pending the longer report-observation review; that later review subsequently completed cleanly and enforcement is now live as recorded below.
- `55971dd` and `cc78e73` removed stale vendor-console/source-map noise without changing Bootstrap/Chart.js executable behavior.
- `36d81d1` and `3b710ab` closed the Dashboard tooltip/Popper regression; Smart Stock Control browser retest passed.
- `70ab9d1` centralized tenant-aware generated PDF branding. Live proof passed with Farm A LLC and Farm B LTD.
- `3bba1dc` added one shared Feed transaction-history PDF renderer for Layer, Broiler and Ruminant pages and improved daylight contrast of the operational Expense PDF buttons.
- Live Feed PDF QA passed for Operational View and Full Audit, and the three Expense-page PDF controls passed browser QA.
- No CSP enforcement, payment-mode change, financial formula change or database migration was introduced by these runtime refinements.

## 2026-09-11 to 2026-09-12 CSP enforcement and commercial billing closure

- CSP Report-Only observation completed cleanly. The earlier reminder to inspect `[CSP_REPORT]` entries before deciding whether to enable enforcement is superseded; that decision and rollout are already complete.
- Commit `0139675` introduced enforcement after the clean observation and owner approval.
- The first production enforcement attempt exposed an OPcache mixed-generation compatibility problem and was safely rolled back. It did not become the final production state.
- Commit `59f6e63` (`Make CSP enforcement rollout OPcache compatible`) retained `app_emit_csp_report_only_header()` as a compatibility shim and enabled an OPcache-safe policy-only rollout.
- Production CSP enforcement then passed repeated route probes and authenticated browser smoke. The post-smoke CSP log contained the previously known synthetic collector trace and no real browser CSP violations. Production CSP mode is now ENFORCING and the Report-Only phase is CLOSED.
- CSP enforcement rollback backup: `/home/renee/renee-deploy-backups/csp-enforcement-runtime-20260911-123734/includes/csp_policy.php`.
- Stage 2I TEST/SANDBOX billing proof completed successfully and its temporary sandbox gates were removed. The successful payment proof must not be repeated merely for reassurance.
- Commercial seat top-up, scheduled seat reduction, cancellation, farm-wide pending-attempt coordination and reconciliation were completed and tested. Migration 046 and migration 047 are CLOSED/PASS and must not be rerun without actual regression evidence.
- Commit `a84e7ad` (`Keep billing available without paid period lineage`) prevents legacy/manual active tenants from losing the Billing workspace when the latest commercial history has no `current_period_ends_at`. Paid-period-dependent seat top-up and reduction remain unavailable until a real paid period exists.
- Farm A LLC browser QA passed after that deployment. Billing resilience backup: `/home/renee/renee-deploy-backups/billing-legacy-period-runtime-20260912-122352`.
- Read-only lineage inspection proved Farm A LLC has zero payment attempts, zero applied paid subscription records and zero non-null `current_period_ends_at` history rows. Its historical `18 Sep 2026` value exists only as `subscription_ends_at` on a `platform_owner_update` snapshot, so no paid-period date was fabricated or backfilled and immutable history was preserved.
- Commit `60b4e89` (`Clarify subscription history date semantics`) changed Subscription History to show separate `Subscription end` and `Paid period end` columns. Production browser QA confirmed the old `18 Sep 2026` value appears only under Subscription end while Paid period end remains `—`.
- Subscription-history clarification backup: `/home/renee/renee-deploy-backups/billing-history-date-semantics-20260912-124518`.
- No database rewrite, historical-row deletion, paid-period backfill, payment-provider production activation or CSP rollback was performed during the Farm A billing/history closure.
- Commit `754711e` (`Add tenant billing payment receipts`) completed the first tenant-facing paid-payment receipt increment. Billing > Recent payments exposes `View receipt` only for paid attempts; failed/non-paid attempts do not receive a receipt action.
- Receipt lookup is read-only and tenant-pinned by authenticated Farm Admin `farm_id`, payment-attempt id and `status = paid`. General payment history continues to hide provider references and transaction identifiers; those identifiers are exposed only inside the tenant-pinned receipt detail.
- Remote GitHub semantic review passed for `47ec39b` -> `754711e`. Production runtime deployment and hash/lint verification passed for `billing/account.php`, `billing/receipt.php` and `includes/billing_payment_receipt.php`.
- Production receipt rollback backup: `/home/renee/renee-deploy-backups/billing-payment-receipt-runtime-20260912-133733`.
- Authenticated browser QA passed with X2 Farm: failed rows displayed no receipt action, existing paid rows displayed `View receipt`, receipt details rendered correctly, and `Back to Billing` returned normally to Farm Admin Billing.
- This receipt milestone performed no database mutation, migration, payment-provider call, `.htaccess`, config or CSP change. It does not approve production payment processing. Printable/PDF receipt polish and broader invoicing remain outside this first increment.

## Important interpretation

A checkpoint being in the current lineage means its committed work was carried forward into later commits. Later commits may legitimately modify the same files, so production should use the latest descendant version rather than an old intermediate snapshot.

The numbered CSP verifier filename series ends at Batch 68. Working development continued through Batches 69-74 and then moved into CSP readiness / Report-Only milestones. The absence of verifier filenames named batch69 through batch74 does not mean those commits are missing.

## Major closed areas from hand-off

- Commercial billing lifecycle and recovery hardening
- Tenant paid-payment receipt / proof-of-payment visibility
- Tenant and permission hardening
- Receivables, upfront-cash and overpayment protections
- CSRF, session, login, redirect, CORS, IDOR, SQL injection, XSS/output encoding, SSRF and path/file hardening
- CSP inline-handler, inline-script, inline-style and style-attribute reduction
- Same-origin/vendored browser dependency hardening
- CSP Report-Only observation and production CSP enforcement

## Current roadmap position

1. Current production runtime lineage through `754711e`: COMPLETE.
2. Feed audit / platform-wide Recorded By targeted production QA: COMPLETE.
3. Homepage, Poultry Health, vendor-console, Dashboard Popper, tenant PDF branding and Feed PDF targeted production QA: COMPLETE.
4. CSP Report-Only observation: COMPLETE / CLOSED.
5. Production CSP enforcement and authenticated browser smoke: COMPLETE / LIVE.
6. Stage 2I TEST/SANDBOX billing end-to-end proof and temporary-gate removal: COMPLETE / CLOSED.
7. Commercial seat top-up, scheduled reduction/cancellation, coordination and reconciliation hardening: COMPLETE / CLOSED.
8. Migration 046 and migration 047 production verification: COMPLETE / CLOSED. Do not rerun absent regression evidence.
9. Farm A LLC legacy/manual billing-period resilience: COMPLETE / CLOSED.
10. Subscription-history `Subscription end` versus `Paid period end` clarification: COMPLETE / CLOSED.
11. Tenant paid-payment receipt / proof-of-payment visibility: COMPLETE / CLOSED. HTML receipt milestone only; do not repeat payment execution for QA.
12. Continue remaining V2.3 commercial/SaaS hardening and commercial QA: NEXT.
13. Production payment processing remains NOT APPROVED; keep provider execution TEST/SANDBOX until explicit owner approval.

## Safety rules

- Never work from `main` or an older ZIP/branch.
- Never run migration 003.
- Never use `rsync --delete`.
- Preserve production `.htaccess`.
- Treat production `config.php` surgically.
- Payment/provider execution remains TEST/SANDBOX until explicit owner approval for production processing.
- CSP enforcement is already live; do not revert to Report-Only or weaken policy without actual regression evidence and a controlled review.
- Migration 046 and migration 047 are CLOSED/PASS; do not rerun them absent actual regression evidence.
- Do not repeat the completed Stage 2I sandbox payment proof merely for reassurance.
- Do not reopen or repeat tenant payment-receipt QA absent actual regression evidence.
- Preserve protected QA/billing evidence.
- Prefer shared helpers/services and thin routes.
- Add focused verifiers for important contracts.
- Remote-review every pushed GitHub change.
