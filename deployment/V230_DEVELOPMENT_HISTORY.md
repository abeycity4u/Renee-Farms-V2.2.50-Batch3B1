# Renee Farms Platform V2.3 Development History

Audit snapshot: 2026-09-10

## Authoritative source

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`
- Branch: `v230-commercial-hardening-saas-readiness`
- Audit HEAD: `b6e720cd6a74f2f0faab1bb8e6482ba76c7b81d1`
- Production: `https://reneefarms.com`
- Server checkout: `~/renee-deploy`
- Live root: `~/public_html`

## Current deployment position

Production runtime was reconciled against commit `b6e720c`. Runtime application files were verified against the current HEAD. Production-specific `config.php` and `.htaccess` remain protected and are not blindly overwritten.

Development-only files such as scripts, tests, migrations, notes and deployment documentation are kept in source control but are not required inside the public web runtime.

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

## Important interpretation

A checkpoint being in the current lineage means its committed work was carried forward into later commits. Later commits may legitimately modify the same files, so production should use the latest descendant version rather than an old intermediate snapshot.

The numbered CSP verifier filename series ends at Batch 68. Working development continued through Batches 69-74 and then moved into CSP readiness / Report-Only milestones. The absence of verifier filenames named batch69 through batch74 does not mean those commits are missing.

## Major closed areas from hand-off

- Commercial billing lifecycle and recovery hardening
- Tenant and permission hardening
- Receivables, upfront-cash and overpayment protections
- CSRF, session, login, redirect, CORS, IDOR, SQL injection, XSS/output encoding, SSRF and path/file hardening
- CSP inline-handler, inline-script, inline-style and style-attribute reduction
- Same-origin/vendored browser dependency hardening
- CSP Report-Only baseline

## Current roadmap position

1. Full current runtime deployment: COMPLETE.
2. Manual regression smoke test after full deployment: PENDING.
3. CSP Report-Only browser observation across major roles/flows: NEXT.
4. Fix genuine CSP violations centrally without `unsafe-inline` or `unsafe-eval`.
5. Enforce CSP only after Report-Only observation is clean.
6. Continue remaining V2.3 commercial/SaaS hardening and commercial QA.

## Safety rules

- Never work from `main` or an older ZIP/branch.
- Never run migration 003.
- Never use `rsync --delete`.
- Preserve production `.htaccess`.
- Treat production `config.php` surgically.
- Payment remains TEST until explicit owner approval.
- Preserve protected QA/billing evidence.
- Prefer shared helpers/services and thin routes.
- Add focused verifiers for important contracts.
- Remote-review every pushed GitHub change.
