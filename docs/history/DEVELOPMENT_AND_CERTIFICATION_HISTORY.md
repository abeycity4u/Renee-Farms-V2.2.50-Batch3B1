# Renee AgriSuite Development & Certification History

Status: HISTORICAL / FROZEN RECORD

This document consolidates historical Renee AgriSuite development and certification records that are retained for audit, lineage and regression context.

It is **not current deployment authority**.

For current V3.2 GA authority use:

- `docs/ARCHITECTURE.md`
- `docs/API.md`
- `INSTALLATION_AND_MIGRATION_GUIDE.md`
- `deployment/install_private_workers.sh`
- `deployment/show_cron_jobs.sh`
- current `v320-ga-hardening` branch documentation

Historical branch names, runtime HEADs, cron schedules, production counts and operational instructions below are preserved exactly as recorded at their original certification dates. They must not be interpreted as current V3.2 instructions.

---

## Part I — V2.3 Commercial Hardening Development History

Original source:
`deployment/V230_DEVELOPMENT_HISTORY.md`

Original SHA-256:
`e1204759b45d90b01c01c9bda754d44a52273d11138db20603ca6b074c2e69a1`

The following block preserves the original historical document unchanged.

<!-- BEGIN V230 ORIGINAL -->
# Renee Farms Platform V2.3 Development History

Audit snapshot: 2026-09-13

## Authoritative source

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`
- Branch: `v230-commercial-hardening-saas-readiness`
- Production runtime HEAD: `c4612670d93691a2131a620228388ba31d03a7c2`
- Production: `https://reneefarms.com`
- Server checkout: `~/renee-deploy`
- Live root: `~/public_html`

## Current deployment position

Production runtime is now carried forward through commit `c461267`, including the completed tenant payment-receipt PDF and branding closure, initialized subscription-attempt recovery, typed preservation of non-2xx billing-provider HTTP evidence, Composer `/vendor/` web-exposure hardening, and billing/payment-history retention across tenant deletion. Migration `048_billing_tenant_retention_integrity.sql` is applied in production: payment attempts now retain a farm foreign key with `ON DELETE RESTRICT`, the former CASCADE farm FK is absent, and the application layer blocks permanent deletion of farms with billing/payment history while directing the Platform Owner to suspension instead.

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
| 132 | `754711e` | Tenant paid-payment receipts | Add tenant billing payment receipts | Production runtime ancestor |
| 133 | `540b487` | Management Sales Records terminology | Rename Sales Report UI to Sales Records | Production runtime checkpoint |

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
- The initial HTML receipt milestone performed no database mutation, migration, payment-provider call, `.htaccess`, config or CSP change and did not approve production payment processing. The subsequent PDF receipt and branding work is now also COMPLETE / CLOSED; broader invoicing remains outside the receipt milestone.
- Commit `540b487` (`Rename Sales Report UI to Sales Records`) completed the Management sales-workspace terminology cleanup. Tenant-facing Management navigation, Sales Records page headings and PDF display titles now use `Sales Records`.
- The terminology change was deliberately UI-only: `/management/sales_records.php`, `sales_report_pdf.php`, `sales-report-` PDF filename prefixes, database identifiers, functions and URLs were preserved.
- Production deployment passed source/live hash verification and PHP lint for `navbar.php`, `management/sales_records.php` and `management/sales_report_pdf.php`. Rollback backup: `/home/renee/renee-deploy-backups/sales-records-terminology-runtime-20260912-141346`.
- Authenticated browser QA passed for the Management `Sales Records` menu label, Sales Records workspace heading and Sales Records PDF wording. This terminology milestone is COMPLETE / CLOSED.

## 2026-09-12 tenant payment receipt PDF and branding closure

- Commit `1b74066` added authenticated tenant payment-receipt PDF export through the existing centralized `PdfReportService`; no parallel receipt route was introduced.
- Receipt PDF access remains GET-only, Farm Admin authenticated, tenant-pinned and paid-only. Viewing a receipt remains read-only and payment-provider passive.
- Final product/platform branding is `Renee AgriSuite`.
- `Renee Farms Limited` remains the legal issuer.
- The authenticated tenant/farm remains the customer and is shown separately as `Customer / Farm`.
- Operational PDFs remain tenant-branded through the centralized farm-name resolver. The billing receipt is the deliberate exception and passes the Renee AgriSuite platform brand explicitly.
- Commit `15e321a` replaced the temporary receipt platform name with `Renee AgriSuite` while preserving issuer and customer separation.
- Commit `e5f0aaf` completed the centralized PDF visual hierarchy: the primary farm/platform brand is 16pt, report headings are subordinate and the page reserves sufficient top space.
- Commit `0791e98` completed the receipt-specific PDF title refinement so `Payment receipt` is smaller than `Renee AgriSuite` and visually aligned with the primary brand.
- Authenticated browser QA passed for the existing X2 Farm paid receipt. No new payment was executed for this verification.
- Existing operational PDF examples, including Sales Records and Ruminant Feed Transaction History, passed visual QA after the centralized hierarchy update.
- Final production rollback backups for this closure include:
  - `/home/renee/renee-deploy-backups/pdf-brand-hierarchy-runtime-20260912-153438`
  - `/home/renee/renee-deploy-backups/payment-receipt-title-runtime-20260912-155018`
- This closure required no database mutation, migration, payment-provider execution, config change, `.htaccess` change or CSP change.
- Production payment processing remains NOT APPROVED. Provider execution stays TEST/SANDBOX until explicit owner approval.
- Broader invoicing is not part of this closed payment-receipt milestone.
## 2026-09-12 initialized subscription-attempt recovery closure

- Commit `be054da` (`Recover interrupted initialized subscription checkouts`) closes the provider-known interrupted-checkout gap where a subscription payment attempt was durably committed as `initialized` but execution stopped before the provider result was safely recorded locally.
- Recovery is tenant-scoped, subscription-purpose only and limited to commercially eligible `initialized` attempts.
- Provider registration and verification occur outside the database transaction. After an authoritative provider fact is obtained, the payment attempt is re-locked and tenant identity, purpose, commercial disposition and exact `initialized` state are revalidated before canonical audit mutation.
- Ambiguous provider/network exceptions remain `initialized_blocked`. They are not translated into failed, cancelled, abandoned or reference-absent states and are not expired solely by age.
- Verified `pending` remains blocking. Verified `paid` uses centralized `billing_paid_attempt_dispatch()` and requires the exactly-once subscription application linkage. Verified `failed` or `cancelled` may be commercially superseded only with verified-terminal audit evidence. Verified `refunded` settles without subscription application.
- Checkout recovery runs before terminal-attempt reconciliation, provider selection and fresh checkout preparation, preventing a replacement payment from racing an unresolved initialized attempt.
- Focused pre-commit regression verification passed 105 checks with 0 failures across commercial coordination, commercial reconciliation, reconciliation launcher, subscription checkout initiation, initialized-attempt recovery and checkout-route integration. All changed PHP files linted successfully and `git diff --check` passed.
- The exact remote GitHub commit was semantically reviewed after push. The active branch `v230-commercial-hardening-saas-readiness` was one commit ahead of parent `f2ee336` and zero behind, with exactly four intended files changed and no migration, config, `.htaccess`, provider-adapter or unrelated runtime change.
- Production deployment was deliberately limited to `billing/checkout.php` and `includes/billing_initialized_attempt_recovery.php`. Verifier scripts remained development-only.
- Production rollback backup: `/home/renee/renee-deploy-backups/initialized-attempt-recovery-20260912-185759`.
- Deployed production hashes matched the reviewed source exactly:
  - `billing/checkout.php`: `19a7ad00fad7f4328775a6299d83feb55bb1a7b1b7d66dc1a60a24a878005e2f`
  - `includes/billing_initialized_attempt_recovery.php`: `0b72047d6c8b78e3ad2d9de00ca5d40f6ebb6260eec793207d8a1bc613fd1360`
- Production PHP lint passed for both runtime files. Unauthenticated HTTPS smoke returned `303` to `/login.php` with no 5xx response.
- Read-only QA candidate inspection found Farm 5 and Farm 6 as clean trial tenants with zero payment attempts and no open subscription attempts; neither was mutated.
- Provider-facing recovery execution was intentionally NOT performed because the deployed environment reported `BILLING_PAYMENT_MODE=disabled`, `LIVE_PAYMENTS_ENABLED=NO` and `NEW_CHECKOUT_ALLOWED=NO`. Paystack and Flutterwave both reported not ready. Production payment settings were not changed merely to force a recovery test.
- The remaining provider-specific edge case where an initialized reference may never have reached the provider is still fail-closed: current transport semantics cannot safely distinguish authoritative provider absence from an ambiguous non-2xx/network failure. Do not guess or auto-expire that state without provider-specific evidence.
- No database migration, production payment, payment-mode activation, config change, `.htaccess` change, protected Farm A mutation or Farm 15 mutation occurred during this closure.
- Production payment processing remains NOT APPROVED. Provider execution remains TEST/SANDBOX only until explicit owner approval.

## 2026-09-12 billing-provider HTTP error evidence closure

- Commit `61ddad9` (`Preserve billing provider HTTP error evidence`) adds a typed `BillingHttpResponseException` that remains a `RuntimeException` for compatibility with existing fail-closed callers.
- Provider network failures remain generic failures. For completed HTTP responses, non-2xx status codes are now preserved together with valid decoded JSON when available; invalid or non-JSON bodies remain opaque.
- This milestone does NOT classify HTTP 404, 503 or any other non-2xx response as payment failure, cancellation, reference absence or commercial supersession. Provider-specific interpretation remains a separate evidence-gated step.
- No Paystack or Flutterwave payment/provider request was executed for this change.
- Focused verification passed before commit: provider-adapter verifier 45 checks / 0 failures, SSRF/outbound-control verifier 10 checks / 0 failures, PHP lint clean and `git diff --check` clean.
- Remote GitHub semantic review passed after push. Branch `v230-commercial-hardening-saas-readiness` pointed exactly to `61ddad930b87fb5bba372958d00d0d249c891e68`, one commit ahead and zero behind parent `aab63c927848fb35e19222ab4760228823248df3`, with only the transport and its verifier changed.
- Production deployment was limited to `includes/billing_http_transport.php`.
- Production rollback backup: `/home/renee/renee-deploy-backups/billing-http-typed-error-20260912-191546`.
- Previous production transport hash: `a51fc50c3abc59885e2fbd74f8054d716a898c855aed75200aebc1a1964c3fe8`.
- Deployed production transport hash: `4b0e828a2cc53051212932d2a41414fea3ee42aec1ffb419a6bb2a0d1ea897c2`, matching the reviewed source exactly.
- Production lint passed. Offline live contract smoke passed all five typed-error checks with `LIVE_TRANSPORT_SMOKE_FAILURES=0`.
- Unauthenticated billing checkout smoke returned HTTP `303` with `Location: /login.php` and `CURL_RC=0`; no 5xx regression was observed.
- Preflight found an existing production copy of `scripts/verify_v230_billing_provider_adapters.php`. This deployment did not copy, modify or delete that pre-existing verifier. Any cleanup of development-only files must be handled separately with evidence.
- No database migration, database mutation, payment-mode change, provider execution, config change, `.htaccess` change, Farm A mutation or Farm 15 mutation occurred.
- Production payment processing remains NOT APPROVED. Provider execution remains TEST/SANDBOX only until explicit owner approval.
- The initialized-reference provider-absence edge remains fail-closed. Preserving HTTP evidence is infrastructure only; authoritative provider-specific absence semantics must be proven before any recovery logic is allowed to unblock or supersede an `initialized` attempt.

## 2026-09-13 production web-exposure and Composer vendor closure

- A read-only production exposure audit confirmed the existing protected development surfaces remain fail-closed: `scripts/` and `migrations/` return HTTP 403, while `deployment/`, `tests/` and `notes/` are absent from the public production tree.
- Root-level sensitive-artifact review found no deployed `.git`, `.env`, `.env.local`, `.env.production`, PHPUnit configuration, README or backup/archive artifacts outside the intentionally deployed SQL migration/schema files. `composer.json` and `composer.lock` return HTTP 403.
- Root `database_schema.sql` is intentionally present but protected by the production root `.htaccess`; an HTTPS GET returned HTTP 403 and the live file hash matched source.
- Production `config.php` returned HTTP 200 only because PHP executed it; the response body was exactly 0 bytes with no PHP-source or sensitive-data markers. The root `error_log` returned HTTP 403.
- A genuine exposure gap was found under the Composer dependency directory: `/vendor/` returned HTTP 200 with a directory listing while `/vendor/autoload.php` executed with an empty body.
- Source dependency review confirmed browser-facing third-party assets use the separate `/assets/vendor/` tree. Composer `/vendor/` is server-side only, with application usage through `vendor/autoload.php`.
- Commit `ac132e5` (`Deny public access to Composer vendor dependencies`) added the narrowly scoped `vendor/.htaccess` rule `Require all denied`.
- Remote GitHub semantic review passed after push. Branch `v230-commercial-hardening-saas-readiness` pointed exactly to `ac132e53d70099b782e3391272271bda161f8a67`, whose parent is `94bed617ab3b1c37ea712c2e6d0da6e2410d1d89`, with only `vendor/.htaccess` changed.
- Production deployment was limited to `public_html/vendor/.htaccess`. Source and live hashes matched at `7b025e2cffa6b71ed8b898ebb9cf04731354e07108f41c9c4a2a745dad7f796a`; live mode/owner was `644 renee:renee`.
- Post-deployment HTTPS verification passed: `/vendor/` returned HTTP 403, `/vendor/autoload.php` returned HTTP 403, and the legitimate browser asset `/assets/vendor/jquery/jquery.min.js` remained HTTP 200.
- No database migration, database mutation, provider execution, payment-mode change, root `.htaccess` replacement, CSP change, Farm A mutation or Farm 15 mutation occurred.
- This production web-exposure / Composer vendor milestone is COMPLETE / CLOSED.

## 2026-09-13 billing-history tenant-retention integrity closure

- A commercial audit-integrity defect was identified in the tenant deletion path. `management/farms.php` intentionally purges tenant data, including subscriptions, while the original `billing_payment_attempts.farm_id -> farms.id` relationship used `ON DELETE CASCADE`. A permanent farm deletion could therefore destroy durable payment-attempt history and detach surviving provider-event evidence.
- Live read-only preflight confirmed the defect was real in production: the payment-attempt farm FK was `CASCADE`, there were 13 billing payment attempts across 3 farms, 7 provider events and 0 orphan payment attempts.
- Commit `310c34c` (`Preserve billing history across tenant deletion`) added the application-level billing-history deletion guard, migration 048, a targeted migration runner, billing-foundation delete-rule readiness checks and expanded static verification.
- The application guard was deployed first. `management/farms.php` now refuses permanent tenant deletion when billing payment history exists and tells the Platform Owner to suspend the farm instead. The centralized guard executes before tenant purge rows are deleted, so all callers fail closed.
- Commit `ee2c8bd` (`Make billing retention FK replacement atomic`) attempted to replace the old CASCADE FK with a same-name RESTRICT FK in one `ALTER TABLE`.
- The first production migration-048 execution failed safely with `RUN_RC=255`. MariaDB `11.4.13-MariaDB-cll-lve-log` returned SQLSTATE `HY000`, error 1005 / errno 121 (`Duplicate key on write or update`) because the same FK constraint name was being dropped and recreated inside one ALTER statement.
- Post-failure verification proved there was no partial migration: the legacy FK remained `CASCADE`, migration 048 remained unrecorded, billing payment attempts remained 13, provider events remained 7 and orphan payment attempts remained 0.
- Commit `c461267` (`Harden billing retention FK migration`) replaced the incompatible same-name strategy with a fail-closed two-step design using the permanent retained constraint `fk_billing_attempt_farm_restrict`.
- The hardened migration first establishes `fk_billing_attempt_farm_restrict` with `ON DELETE RESTRICT`. Only after information-schema verification proves that safe retained relationship exists may it drop the legacy `fk_billing_attempt_farm`. If the second DDL fails, RESTRICT protection remains active.
- Mandatory remote GitHub semantic review passed before the corrected production rollout. The branch pointed exactly to `c4612670d93691a2131a620228388ba31d03a7c2`, with parent `ee2c8bdd4f595250fc46262904a3020cd7455e03` and only the four intended retention-integrity files changed.
- The hardened migration and targeted runner were deployed with exact source/live hash matching. Migration 048 then executed successfully with `RUN_RC=0`.
- Final production schema state:
  - legacy `fk_billing_attempt_farm`: ABSENT
  - retained `fk_billing_attempt_farm_restrict`: PRESENT
  - retained FK relationship: `billing_payment_attempts(farm_id) -> farms(id)`
  - retained FK delete rule: `RESTRICT`
  - migration 048 recorded: YES
- Final production audit evidence remained unchanged at 13 billing payment attempts and 7 provider events, with 0 orphan payment attempts and 3 farms represented in payment history.
- `includes/billing_payment_foundation.php` was deliberately deployed only after migration 048 succeeded. Runtime verification returned `BILLING_FOUNDATION_READY=YES`.
- Static tenant-retention verification passed 28 checks with 0 failures. Production PHP lint passed for `management/farms.php`, `includes/billing_payment_foundation.php` and the targeted migration-048 runner.
- No production farm was deleted to prove the protection. `PRODUCTION_FARM_DELETE_TEST=NOT_PERFORMED`; verification used source contracts, live schema inspection, row-count preservation and runtime readiness checks instead.
- Production rollback backups created during this closure:
  - `/home/renee/renee-deploy-backups/tenant-retention-20260912-195859`
  - `/home/renee/renee-deploy-backups/tenant-retention-safe-fk-20260912-201011`
  - `/home/renee/renee-deploy-backups/tenant-retention-foundation-20260912-201148`
- Migration 048 and the billed-tenant permanent-deletion defect are COMPLETE / CLOSED. Do not rerun migration 048, restore CASCADE semantics, erase payment-attempt history, or perform a production tenant deletion merely for QA unless actual regression evidence requires a separately reviewed recovery plan.

## Important interpretation

A checkpoint being in the current lineage means its committed work was carried forward into later commits. Later commits may legitimately modify the same files, so production should use the latest descendant version rather than an old intermediate snapshot.

The numbered CSP verifier filename series ends at Batch 68. Working development continued through Batches 69-74 and then moved into CSP readiness / Report-Only milestones. The absence of verifier filenames named batch69 through batch74 does not mean those commits are missing.

## Major closed areas from hand-off

- Commercial billing lifecycle and recovery hardening
- Billing/payment-history tenant-retention integrity
- Tenant paid-payment receipt / proof-of-payment visibility
- Management Sales Records terminology consistency
- Tenant and permission hardening
- Receivables, upfront-cash and overpayment protections
- CSRF, session, login, redirect, CORS, IDOR, SQL injection, XSS/output encoding, SSRF and path/file hardening
- CSP inline-handler, inline-script, inline-style and style-attribute reduction
- Same-origin/vendored browser dependency hardening
- CSP Report-Only observation and production CSP enforcement

## Current roadmap position

1. Current production runtime lineage through `c461267`: COMPLETE.
2. Feed audit / platform-wide Recorded By targeted production QA: COMPLETE.
3. Homepage, Poultry Health, vendor-console, Dashboard Popper, tenant PDF branding and Feed PDF targeted production QA: COMPLETE.
4. CSP Report-Only observation: COMPLETE / CLOSED.
5. Production CSP enforcement and authenticated browser smoke: COMPLETE / LIVE.
6. Stage 2I TEST/SANDBOX billing end-to-end proof and temporary-gate removal: COMPLETE / CLOSED.
7. Commercial seat top-up, scheduled reduction/cancellation, coordination and reconciliation hardening: COMPLETE / CLOSED.
8. Migration 046 and migration 047 production verification: COMPLETE / CLOSED. Do not rerun absent regression evidence.
9. Farm A LLC legacy/manual billing-period resilience: COMPLETE / CLOSED.
10. Subscription-history `Subscription end` versus `Paid period end` clarification: COMPLETE / CLOSED.
11. Tenant paid-payment receipt / proof-of-payment visibility, PDF export and final branding hierarchy: COMPLETE / CLOSED. Do not repeat payment execution for QA.
12. Management Sales Records terminology cleanup: COMPLETE / CLOSED. UI wording only; routes, filenames, database identifiers, functions and URLs remain unchanged.
13. Initialized subscription-attempt recovery through `be054da`: DEPLOYED / STRUCTURALLY VERIFIED / CLOSED. Provider-facing recovery execution remains deferred while payment mode is disabled; do not activate production billing merely to repeat QA.
14. Billing-provider typed HTTP error evidence through `61ddad9`: DEPLOYED / VERIFIED / CLOSED. This preserves non-2xx evidence only and does not yet classify provider-specific reference absence.
15. Production web-exposure and Composer `/vendor/` hardening through `ac132e5`: DEPLOYED / VERIFIED / CLOSED.
16. Billing/payment-history tenant-retention integrity through `c461267`: DEPLOYED / MIGRATED / VERIFIED / CLOSED. Migration 048 is complete; do not rerun absent actual regression evidence.
17. Continue remaining V2.3 commercial/SaaS hardening and commercial QA: NEXT.
18. Production payment processing remains NOT APPROVED; keep provider execution TEST/SANDBOX until explicit owner approval.

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
- Do not enable production payment mode merely to exercise initialized-attempt recovery QA; provider-facing recovery proof must use an explicitly approved TEST/SANDBOX environment.
- Do not interpret a preserved provider HTTP status or error body as authoritative reference absence without provider-specific verified semantics; ambiguous responses remain fail-closed.
- Do not reopen or repeat tenant payment-receipt QA absent actual regression evidence.
- Preserve the Sales Records terminology change as UI-only; do not rename its routes, filenames, database identifiers, functions or URLs merely for wording consistency.
- Migration 048 is CLOSED/PASS; do not rerun it absent actual regression evidence and a separately reviewed reason.
- Never restore `billing_payment_attempts.farm_id -> farms.id` to `ON DELETE CASCADE`; durable billing/payment history must survive tenant lifecycle operations.
- Farms with billing/payment history cannot be permanently deleted under the current commercial audit model. Suspend them instead, and never delete a production tenant merely to QA this protection.
- Preserve protected QA/billing evidence.
- Prefer shared helpers/services and thin routes.
- Add focused verifiers for important contracts.
- Remote-review every pushed GitHub change.
<!-- END V230 ORIGINAL -->

---

## Part II — V3.1 Account Credential Worker Closure

Original source:
`docs/v310-credential-worker-closure-handover.txt`

Original SHA-256:
`21fe54a39988280881e88242df093051f03ebfd84b5d7bf0103f3ed61876f6b8`

The following block preserves the original historical closure report unchanged.

Important current-context note: the V3.1 report's branch and cron statements are historical certification facts. Current scheduled-operation authority is `deployment/show_cron_jobs.sh`, and current deployment authority belongs to the V3.2 GA documentation.

<!-- BEGIN V310 ORIGINAL -->
RENEE FARMS / RENEE AGRISUITE
V3.1 ACCOUNT CREDENTIAL WORKER
PERMANENT CLOSURE / HANDOVER REPORT

Report date:
2026-09-27

======================================================================
1. AUTHORITATIVE SOURCE
======================================================================

Repository:
abeycity4u/Renee-Farms-V2.2.50-Batch3B1

Authoritative branch:
v310-account-credential-lifecycle

Runtime-certification source HEAD:
1919618a960189773c639a5905bba439ba4c6f24

IMPORTANT:
The documentation commit containing this report will become the next Git HEAD.
That documentation-only commit does not change certified runtime behaviour.

Do not restart this credential-worker implementation from an older branch or
older V3.1 commit.

======================================================================
2. STAGE STATUS
======================================================================

V3.1 credential lifecycle:
CLOSED / PASS

Credential schema:
PASS

Shared credential services:
PASS

Pending-user writer:
PASS

Credential delivery service:
PASS

Credential outbox:
PASS

Private CLI authority bridge:
PASS

Private credential worker:
PASS

Central mailer privacy:
PASS

End-to-end SMTP/inbox QA:
PASS

Disposable QA cleanup:
PASS

Production launcher:
PASS

Production cron:
PASS

Scheduled runtime:
PASS

Production logging:
PASS

Final closure audit:
PASS

Stage18I12N8A:
STAGE18I12N8A_V310_CREDENTIAL_WORKER_CLOSURE_AUDIT=PASS

This stage must not be reopened without concrete regression evidence.

======================================================================
3. DATABASE / MIGRATION CONTRACT
======================================================================

Migration 085:
migrations/085_account_credential_lifecycle.sql

Purpose:
- users.credential_state
- account credential token lifecycle
- activation/password-reset credential state support

Migration 086:
migrations/086_account_credential_delivery_outbox.sql

Purpose:
- durable credential-delivery outbox
- retry state
- processing state
- bounded attempts
- available_at scheduling
- neutral persisted error classification

Both migrations are live and verified.

Required live structures verified:
- users.credential_state
- account_credential_tokens
- account_credential_delivery_outbox
- outbox.user_id
- outbox.purpose
- outbox.status
- outbox.attempt_count
- outbox.available_at
- outbox.processed_at
- outbox.last_error_code

======================================================================
4. SHARED CREDENTIAL ARCHITECTURE
======================================================================

Certified shared services:

includes/account_credential_lifecycle.php
includes/account_credential_delivery.php
includes/account_credential_outbox.php
includes/account_credential_request.php
includes/account_identity_policy.php
includes/account_pending_user.php
includes/password_security.php
includes/platform_public_url.php
includes/platform_mailer.php

All listed shared services were confirmed source/live parity at closure.

Architecture rule:
Keep credential logic centralized.

Do not create page-local copies of:
- activation logic
- reset-token logic
- pending-user creation
- identity rules
- outbox processing
- public URL generation
- mail transport
- retry policy
- credential-state transitions

Pages/routes must remain thin consumers of shared services.

======================================================================
5. CREDENTIAL LIFECYCLE CONTRACT
======================================================================

Supported purposes:
- activation
- password_reset

Activation accounts must be:
credential_state = pending_activation

Successful activation changes state to:
credential_state = active

New Farm Admin and Team User lifecycle uses:
- credential email
- pending_activation
- activation link
- user chooses password

Plaintext initial passwords are not part of the new lifecycle.

Existing legacy accounts may still have nullable email where historically
permitted.

Forgot/reset flows must preserve generic public responses and avoid account
enumeration.

======================================================================
6. SHARED PENDING-USER WRITER
======================================================================

Shared pending-account creation is centralized in:

includes/account_pending_user.php

This service must remain the authority for pending user creation.

It owns the shared lifecycle contract rather than individual management pages
duplicating password placeholders, credential state, activation enqueueing,
or related policy.

Both Farm Admin and Team User flows were migrated to the shared account
credential architecture during V3.1.

======================================================================
7. OUTBOX CONTRACT
======================================================================

Service:
includes/account_credential_outbox.php

Maximum delivery attempts:
5

Processing lease:
300 seconds

Retry sequence:
attempt 1 -> 60 seconds
attempt 2 -> 300 seconds
attempt 3 -> 900 seconds
attempt 4 -> 3600 seconds
attempt 5 -> terminal outcome / no further normal retry

Persisted outbox data is intentionally neutral.

Do not persist:
- raw activation/reset tokens
- mail body
- SMTP credentials
- recipient identity in operational error classifications
- transport exception text containing sensitive context

Outbox processor:
account_credential_outbox_process_one(...)

Actual mail generation/delivery is delegated to the shared credential delivery
service.

======================================================================
8. CENTRAL MAIL PRIVACY REPAIR
======================================================================

Central mail service:
includes/platform_mailer.php

Privacy repair commit:
bfbb430ff8e901613f07316ddc01c161c11d16b9

The central mailer was repaired so failure logging does not include recipient
identity.

Closure verified:
MAILER_RECIPIENT_FAILURE_LOG=NONE
MAILER_SECRET_OR_BODY_LOG=NONE
CENTRAL_MAIL_PRIVACY_CONTRACT=PASS

Do not reintroduce:
- recipient email addresses in transport failure logs
- SMTP passwords in logs
- message bodies in logs
- usernames/tokens in generic mailer failure logging

======================================================================
9. PRIVATE CLI AUTHORITY BRIDGE
======================================================================

Source:
scripts/v310_private_cli_bridge.php

Private deployed copy:
/home/renee/renee-private/v310-credential-worker/v310_private_cli_bridge.php

Bridge implementation commit:
d1f46d840cd304b164919981d89317797f601df2

The bridge imports only explicitly allowlisted runtime authority variables
from the live Apache authority source.

Authority source:
/home/renee/public_html/.htaccess

The bridge exists because Apache SetEnv values are not automatically present
in CLI cron execution.

Required authority categories:
- database
- canonical public URL
- mail transport
- SMTP

The cron line contains paths only.
It does not contain SMTP or database secret values.

======================================================================
10. PRIVATE CREDENTIAL WORKER
======================================================================

Source:
scripts/run_v310_account_credential_outbox.php

Private deployed location:
/home/renee/renee-private/v310-credential-worker/run_v310_account_credential_outbox.php

Permission:
600

Behaviour:
- CLI only
- defaults to DRY-RUN
- SEND requires explicit --send
- bounded maximum job processing
- uses shared account credential outbox processor
- fails closed if SEND authority is incomplete
- aggregate operational output only
- no recipient/account/token details in normal worker output
- local flock concurrency protection

The worker is NOT deployed under public_html.

======================================================================
11. PRIVATE PRODUCTION LAUNCHER
======================================================================

Source:
scripts/run_v310_credential_worker_production.sh

Source implementation commit:
1919618a960189773c639a5905bba439ba4c6f24

Private deployed location:
/home/renee/renee-private/v310-credential-worker/run_v310_credential_worker_production.sh

Permission:
700

Responsibilities:
- private launcher-level lock
- invocation of the certified worker
- bounded private logging
- one-generation log rotation
- explicit production SEND invocation
- maximum 25 jobs per invocation

It does NOT duplicate:
- SQL processing logic
- mail logic
- token logic
- retry rules
- identity policy
- credential-state rules

Default launcher mode:
DRY-RUN

Production cron passes:
--send

Production maximum jobs:
25

======================================================================
12. PRIVATE LOGGING CONTRACT
======================================================================

Current log:
/home/renee/renee-private/v310-credential-worker/worker.log

Rotated log:
/home/renee/renee-private/v310-credential-worker/worker.log.1

Current log permission:
600

Maximum current-log threshold:
1048576 bytes (1 MiB)

Rotations retained:
1

When threshold is reached:
- previous worker.log.1 is removed
- worker.log becomes worker.log.1
- new worker.log is created privately

Closure verified:
- no authority variable names containing secret material
- no email-address shapes
- no URL values
- no token hashes
- no password values
- bounded current log
- private file permissions

System logrotate was not available on the host.
Bounded rotation is therefore owned by the private launcher.

======================================================================
13. PRODUCTION CRON
======================================================================

Production cadence:
Every 1 minute

Exact certified cron block:

# BEGIN RENEE V310 CREDENTIAL WORKER
* * * * * /usr/bin/env RENEE_CREDENTIAL_APP_ROOT=/home/renee/public_html RENEE_CREDENTIAL_ENV_SOURCE=/home/renee/public_html/.htaccess RENEE_CREDENTIAL_PHP_BIN=/usr/local/bin/php /bin/sh /home/renee/renee-private/v310-credential-worker/run_v310_credential_worker_production.sh --send
# END RENEE V310 CREDENTIAL WORKER

Closure state:
- exactly one credential-worker cron job
- exactly one BEGIN marker
- exactly one END marker
- no direct worker cron
- no SMTP secret inline
- no database password inline

The cron invokes the private launcher, not the worker directly.

======================================================================
14. PRODUCTION CRON ROLLBACK
======================================================================

Exact pre-install crontab backup:

/home/renee/renee-backups/v310-n7e-cron-20260926-201230/preinstall.crontab

Backup permission:
600

Rollback command:

crontab /home/renee/renee-backups/v310-n7e-cron-20260926-201230/preinstall.crontab

IMPORTANT:
Do not run the rollback command during normal operation.

Use it only when intentionally disabling/reverting the credential worker or
when a confirmed production regression requires rollback.

======================================================================
15. END-TO-END DELIVERY QA
======================================================================

A dedicated disposable tenant/farm/user fixture was used.

QA account characteristics:
- isolated temporary farm
- disposable tenant user
- viewer role
- credential_state pending_activation
- activation outbox purpose
- no existing real tenant account used
- no existing real farm used
- no plaintext password

The QA proved:

1. Shared pending-user writer created the account correctly.
2. Exactly one activation outbox row existed.
3. Worker SEND authority was ready.
4. Private worker processed exactly one job.
5. SMTP transport accepted the message.
6. Outbox became sent.
7. attempt_count became 1.
8. Exactly one activation token was created.
9. Account remained pending_activation before link consumption.
10. Message arrived in the controlled inbox.
11. Activation link was deliberately NOT clicked.
12. QA token/outbox/user/role/farm were completely removed.
13. Original baseline was restored.

End-to-end result:
PASS

======================================================================
16. QA CLEANUP RESULT
======================================================================

Disposable QA cleanup removed:
- QA outbox
- QA activation token
- QA user role
- QA user
- QA farm

Final residue:
QA farm = 0
QA user = 0
QA outbox = 0
QA token = 0
QA role = 0

Closure production baseline:

Users:
14

Active users:
14

Pending users:
0

Credential outbox rows:
0

Credential token rows:
0

Tenant farms:
7

======================================================================
17. FIRST REAL SCHEDULED EXECUTION
======================================================================

The production cron was installed and allowed to execute naturally.

The audit did NOT manually invoke the launcher or worker.

Certified scheduled execution:

Launcher mode:
SEND

Worker mode:
SEND

Maximum jobs:
25

Processed:
0

Sent:
0

Retry scheduled:
0

Discarded:
0

Terminal failed:
0

Worker errors:
0

Launcher exit:
0

The outbox was empty, so no message was sent.

Database remained unchanged.

Result:
PRODUCTION_WORKER_FULL_CERTIFICATION=PASS

======================================================================
18. CLOSURE AUDIT RESULT
======================================================================

Stage18I12N8A closure audit confirmed:

V310_CREDENTIAL_SCHEMA=PASS
V310_CREDENTIAL_SHARED_SERVICES=PASS
V310_CREDENTIAL_OUTBOX=PASS
V310_PRIVATE_WORKER_STACK=PASS
V310_PRODUCTION_CRON=PASS
V310_PRODUCTION_LOGGING=PASS
V310_MAIL_PRIVACY=PASS
V310_END_TO_END_DELIVERY_QA=PASS
V310_QA_CLEANUP=PASS
V310_CREDENTIAL_WORKER_STAGE=CLOSED

This is the authoritative closure state.

======================================================================
19. DO NOT REPEAT / DO NOT REOPEN
======================================================================

Do NOT repeat these completed items without regression evidence:

- migrations 085 and 086
- credential schema implementation
- activation lifecycle implementation
- password-reset lifecycle implementation
- shared pending-user writer conversion
- Farm Admin pending credential conversion
- Team User pending credential conversion
- central mail privacy repair
- CLI authority bridge implementation
- private worker implementation
- private worker deployment
- empty-outbox dry-run
- disposable SEND QA fixture creation
- one-message SMTP SEND QA
- human inbox receipt confirmation
- QA fixture cleanup
- production launcher implementation
- launcher sandbox certification
- private launcher deployment
- private launcher real dry-run
- cron contract audit
- production cron installation
- first scheduled execution audit
- credential-worker closure audit

A future failure must be diagnosed as a regression against these closed
contracts rather than restarting the entire implementation.

======================================================================
20. DEVELOPMENT / ARCHITECTURE RULES FOR FUTURE AGENT
======================================================================

Project rule:
"One fix, fit it all."

Before changing a page or module, trace:
- tenancy
- permissions
- account identity
- credential lifecycle
- billing
- production cycles
- population
- inventory
- expenses
- sales
- financial allocations
- profitability
- APIs
- UI
- migrations
- verifiers

Prefer central shared helpers/services.

Do not duplicate:
- SQL policy
- validation policy
- permission rules
- billing logic
- pricing logic
- credential lifecycle logic
- identity policy
- mail transport policy
- retry policy
- UI visual policy

Keep routes/pages thin.

Use focused contract verifiers.

Proceed one controlled stage at a time.

Do not rerun closed QA absent regression evidence.

Use targeted runtime deployments only.

Never:
- deploy whole repository into public_html
- use rsync --delete against live root
- force-push
- overwrite live config.php
- overwrite live .htaccess
- expose SMTP/database secrets
- place private worker scripts in public_html
- put development verifiers/migrations/notes into public_html

======================================================================
21. SECURITY / OPERATIONS BACKLOG OUTSIDE THIS CLOSED STAGE
======================================================================

These are separate from the now-closed credential-worker implementation.

Security housekeeping still recorded for later controlled work:

1. Rotate previously exposed SMTP credential if not already rotated.
   Never paste replacement secrets into chat or source control.

2. Review/move public backup artifacts outside public_html:
   api/delete_record.php.bak-before-d8f6caa-20260916-102655
   .htaccess.pre-stage2i-20260907-014956
   lib/poultry_rearing_economics.php.bak-before-b8388f6-20260916-091048

3. Review/protect public error-log exposure.

4. Root .htaccess hardening may be considered later, but it contains live
   runtime authority variables and must never be overwritten blindly.

These backlog items do NOT invalidate credential-worker closure.

======================================================================
22. CURRENT AUTHORITATIVE OPERATIONAL STATE
======================================================================

Repository:
abeycity4u/Renee-Farms-V2.2.50-Batch3B1

Branch:
v310-account-credential-lifecycle

Runtime-certification HEAD:
1919618a960189773c639a5905bba439ba4c6f24

Live root:
/home/renee/public_html

Private worker root:
/home/renee/renee-private/v310-credential-worker

Credential worker cadence:
Every 1 minute

Maximum jobs per production invocation:
25

Maximum attempts:
5

Processing lease:
300 seconds

Retry sequence:
60, 300, 900, 3600 seconds

Production worker:
ENABLED AND CERTIFIED

Production cron:
ENABLED AND CERTIFIED

Credential worker stage:
CLOSED

======================================================================
23. HANDOVER INSTRUCTION
======================================================================

A new development agent must start from the current
v310-account-credential-lifecycle branch and the latest Git HEAD.

Do not return to an older V2.x/V3.0/V3.1 branch or an earlier credential
worker commit.

Before new development:
1. confirm branch
2. confirm local HEAD equals origin branch HEAD
3. confirm worktree clean
4. identify the next unfinished V3.1 objective
5. audit its architecture/cross-module effects
6. continue from that point only

The V3.1 credential-worker delivery stage is complete.


======================================================================
24. SECURITY HOUSEKEEPING CLOSURE
======================================================================

Security housekeeping audit/remediation sequence:
O1 through O5DRGR.

Final technical status:
PASS / CLOSED.

Public backup artifacts:
CLOSED.
Exactly three historical backup artifacts were moved outside public_html.
Current public backup-like file count: 0.

Historical public PHP error logs:
CLOSED.
Exactly five historical error logs were quarantined outside public_html.
No natural recreation was observed after quarantine.
Current public error-log file count: 0.

Public migrations directory:
CLOSED / RETAIN PROTECTED.
Do not delete or move public_html/migrations as a cleanup action.
It remains an operational CLI dependency for migration/applicator scripts.
migrations/.htaccess denies HTTP access.
Relocation would require a coordinated migration-runner architecture change.

Root .htaccess:
HARDENED AND CLOSED.
Current live mode: 644.
Current live SHA-256:
9feae17b77716cbaf77e7078e1fdbf91497af031ec334d4fa51a65db6a406a08

The original runtime-authority content and all SetEnv entries were preserved.
One managed hardening block was appended:
Options -Indexes

Directory autoindex is disabled.
Root application health, HTTPS redirect, existing security headers,
protected directories, database access, and credential-worker authority
were regression-certified after the change.

Root .htaccess rollback snapshot:
/home/renee/renee-backups/v310-o5c-root-htaccess-20260927-035247/root.htaccess.pre-o5c

Production credential-worker cron:
ENABLED AND CERTIFIED.
Cadence: every 1 minute.
Exactly one active worker cron job.
Current certified crontab snapshot SHA-256:
5a84ea75029f25f93a0c32789c8ba30d96ab7c7bd395708b0eb1d5549a70c29b

A temporary schedule drift to every five minutes was detected during
security final certification and was repaired back to the certified
every-minute contract. Natural scheduled activity after repair was
observed and certified.

Cron drift rollback snapshot:
/home/renee/renee-backups/v310-o5drf-cron-20260927-040308/pre-repair.crontab

Credential database closure baseline:
users=14
active=14
pending=0
outbox=0
tokens=0

Credential-worker stage remains CLOSED.
Do not reopen credential-worker implementation, scheduling, migrations,
mail delivery, activation/reset lifecycle, Farm Admin conversion, Team
User conversion, or .htaccess hardening without regression evidence.

Remaining security item:
SMTP credential rotation is an OPERATOR-ONLY action because the
previous SMTP password was exposed historically.

The replacement SMTP password must never be pasted into chat, committed
to Git, printed by verification commands, or included in handover
documentation.

After the operator privately rotates the SMTP credential in the
authoritative hosting/provider configuration, verify only presence,
configuration readiness, and mail delivery behavior. Never print or
compare the secret value.

Technical security housekeeping status:
CLOSED.



======================================================================
25. SMTP CREDENTIAL ROTATION CLOSURE
======================================================================

Operator SMTP credential rotation:
PASS / CLOSED.

The SMTP credential that had previously been exposed historically was
rotated privately at the SMTP provider.

The replacement SMTP credential was entered only through a silent
terminal prompt and was not printed in chat, command output,
documentation, or Git.

Live authority update:
PASS.

Only the PLATFORM_SMTP_PASSWORD value changed in the live root
.htaccess runtime authority.

Current live root .htaccess mode:
644

Current live root .htaccess SHA-256:
9feae17b77716cbaf77e7078e1fdbf91497af031ec334d4fa51a65db6a406a08

All 19 SetEnv directives remained present.
All non-secret .htaccess content remained byte-equivalent after
redaction of PLATFORM_SMTP_PASSWORD.
The existing Options -Indexes hardening remained intact.

SMTP authentication-only certification:
PASS.

The replacement credential was accepted by the SMTP provider through
authentication-only certification.
No email message was sent by the credential-rotation certification.

Credential database baseline after rotation:
users=14
active=14
pending=0
outbox=0
tokens=0

Production credential-worker cron after rotation:
ENABLED AND CERTIFIED.
Cadence: every 1 minute.
Exactly one active worker cron job.
Certified crontab SHA-256:
5a84ea75029f25f93a0c32789c8ba30d96ab7c7bd395708b0eb1d5549a70c29b

Pre-rotation private rollback snapshot:
/home/renee/renee-backups/v310-o7a-smtp-rotation-20260927-043110/root.htaccess.pre-smtp-rotation

The previous exposed SMTP credential is retired.

Do not paste, print, document, commit, log, or otherwise expose the
current replacement SMTP credential.

Security housekeeping final status:
FULLY CLOSED.

Closed security areas:
- public backup artifact cleanup
- historical public error-log cleanup
- protected public migration retention
- root .htaccess hardening
- credential-worker production scheduling
- SMTP credential rotation

Do not reopen any of these security stages without regression evidence.



======================================================================
26. V3.1 RELEASE CLOSURE
======================================================================

V3.1 implementation status:
COMPLETE.

Release implementation scope:
Account Credential Lifecycle.

Authoritative predecessor:
V3.0.1 / v301-manage-cycle-workspace-restore.

V3.1 branch:
v310-account-credential-lifecycle

V3.1 repository delta at release-readiness review:
20 commits beyond the V3.0.1 branch.

V3.1 schema frontier:
085_account_credential_lifecycle.sql
086_account_credential_delivery_outbox.sql

Completed V3.1 capabilities include:
- centralized account credential lifecycle
- secure account activation
- secure password-reset request and consumption
- credential-state enforcement during sign-in
- pending-account creation
- Farm Admin pending activation
- Team User pending activation
- centralized account identity policy
- asynchronous credential-delivery outbox
- private CLI authority bridge
- bounded production credential worker
- certified every-minute production scheduling
- mail-log privacy hardening
- root runtime security hardening
- public backup/error-log cleanup
- protected retention of operational migration files
- SMTP credential rotation and authentication certification

Credential worker:
CLOSED.

Security housekeeping:
FULLY CLOSED.

SMTP credential rotation:
CLOSED.

Release-readiness audits P1 through P3R found no separately documented
or explicitly evidenced unfinished V3.1 product objective after removal
of audit false positives.

Do not add unrelated billing, subscription, inventory, sales, poultry,
ruminant, reporting or other product work to this V3.1 branch merely to
extend the release.

Do not reopen closed credential-worker or security stages without
regression evidence.

Release metadata status after this documentation stage:
Release notes documented.
Git release/freeze tag not yet created.

The next controlled release action may prepare and create the V3.1
freeze/release tag only after source identity and documentation closure
are reconfirmed.


END OF REPORT
<!-- END V310 ORIGINAL -->
