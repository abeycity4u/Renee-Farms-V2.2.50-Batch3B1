# C137 / GC24 / K2-P03 / B12 — WS16 integration-closure and security source audit

**Reviewed:** 2026-10-15. **Classification:** SOURCE-ONLY / READ-ONLY INVESTIGATION / NO APPLICATION PATCH. **Release decision:** B12 HOLD; cutover HARD-OFF. No MariaDB connection, migration, authenticated HTTP/browser, CI run, production or staging contact, Mac filesystem access, or runtime execution was performed in this WS16 audit.

## 1. Source and safety identities

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`
- Immutable reviewed base: `c137-gc24-k2p03-b12-ws15-preroute-json-source` at `52957f1a18d90f26a43dd87733eb5dc08f256fe1`.
- WS16 source-audit branch: `c137-gc24-k2p03-b12-ws16-integration-closure-audit-source`; created from that exact base. The document is the only intentional addition.
- Protected production: `v320-renee-agrisuite-branding` at `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- Protected original B12: `c137-gc24-k2p03-b12-cas-writer-cutover-source` at `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Cutover fuse: `lib/daily_record_revision_cutover_gate.php`, blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`; `daily_record_revision_cutover_enabled(): bool` literally returns `false`.
- Protected WS07 offline runner blob: `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`.
- The first three remote branch heads, gate and runner matched these identities before creation. A fresh post-commit remote diff and anchor check is required to close this document checkpoint.

## 2. Exact SAVE route call graph and response classification

Three exact pages (`poultry/layers_daily_record.php`, `poultry/broiler_daily_record.php`, `ruminant/ruminant_daily_record.php`) load `init.php` first. The common bootstrap obtains `config.php`/session/PDO, evaluates subscription lifecycle and module entitlement, loads CSRF functions, enforces the permission runtime and other legacy guard modules. Page code then calls `requireLogin()`, module `checkAccess()`, `requireCurrentFarmId()`, and preparatory tenant data queries **before** its own SAVE POST handler. Within that SAVE handler, `daily_record_revision_save_http_require_ajax()` precedes CSRF/business validation and the dormant shared CAS transaction dispatcher.

The WS15 `lib/daily_record_revision_preroute_http.php` hook is **response representation only**: gated exact SCRIPT_NAME suffix for three named PHP SAVE routes, POST plus `save_record` trigger. It does not grant access or issue SQL. The canonical denial/cleanup happens before a WS15 hook is called. GET, DELETE, unrelated POST and lookalike pathname suffixes do not match. `config.php` loads the responder before session expiry/PDO connection, and early denial hooks exist in `config.php`, `includes/farm_entitlement_runtime.php`, `includes/permission_runtime.php`, `includes/subscription_lifecycle.php`, and the three page module-denial branches.

| SAVE failure class | Future gate-ON JSON source contract | Source disposition / limits |
| --- | --- | --- |
| Missing/expired/deleted/suspended login, missing farm | 401 `authentication_required` | WS15 source hook after canonical login/session decisions; authenticated HTTP NOT RUN |
| Subscription lifecycle or module entitlement denial | 403 `subscription_access_required` | Canonical lifecycle/entitlement decisions preserved; exception paths not exhaustively classified |
| Missing View/Add/Edit/module permission | 403 `forbidden` | Early and page handlers source-wired; no role check removed |
| Malformed/stale explicit `write_intent` in permission runtime | 409 `record_conflict` | Source parse occurs before operation-permission lookup, but after route View gate; no privilege is granted; actual role/scoping integration NOT RUN |
| Non-AJAX/native B12 SAVE reaching inner handler | 409 `revision_client_required` | WS13 central transport plus transaction-dispatcher preflight; does not substitute canonical auth |
| SAVE invalid CSRF or business fields | 419 `invalid_csrf`; 422 `invalid_record` | WS13 page-local invocation of shared response policy; gate-OFF legacy outputs remain |
| Startup PDO failure | 503 `storage_unavailable` | WS15 `config.php` catch hook only; mid-request PDO and unhandled PHP/extension exceptions are NOT normalized by this source proof |
| Transactional conflict / invalid / PDO / generic failure | 409 / 422 / 503 / 500 | `lib/daily_record_revision_route_adapter.php` catches and responds through shared transport after inner dispatcher; real rollback and durability require independent DB certification |

**Important write-side nuance:** `includes/farm_entitlement_runtime.php` invokes `subscription_lifecycle_request_guard($pdo)` before page SAVE dispatch. `subscription_lifecycle_refresh_farm()` can update an expired tenant's `farms.subscription_status` to `past_due` and capture subscription history in its own transaction. Therefore a rejected SAVE request is **not** proved to be completely database-read-only across global subscription lifecycle processing. This is an existing canonical subscription transition, not evidence of a Daily Record CAS write; do not remove or bypass lifecycle logic to make JSON uniform.

**Remaining source/runtime boundary:** page preparatory database reads and shared initialization outside the known catches can still throw before the SAVE handler. No source evidence proves that arbitrary PHP errors, upstream proxies, rate limiting, or every auxiliary middleware abort produces a JSON envelope. Treat these as separately testable authenticated HTTP scenarios, not as a finding that a protected deployment is exploitable.

## 3. Delegated and privileged Ruminant DELETE: confirmed response gap

- Delegated path: `includes/permission_runtime.php`, the `delete_record` POST branch for nonprivileged users. It first checks `ruminant_daily_delete`, then calls `verify_csrf_token()` and on false sends plain text HTTP 419 and exits. Only **after** that check does it enable future gate-ON `daily_record_revision_delete_require_ajax()` and the canonical `ruminant_daily_record_delete_canonical()`.
- Privileged page path: `ruminant/ruminant_daily_record.php`, the `delete_record` POST branch when `$canDeleteRecords`; it also sends plain text HTTP 419 on invalid CSRF **before** calling the B12 DELETE AJAX/JSON module.
- Common transaction: `lib/ruminant_daily_record_deletion.php`; future failures/success go through `lib/daily_record_revision_delete_http.php`. There is no independent second mutation pipeline between the two callers.
- The shared `lib/daily_record_revision_delete_http.php` currently maps success=200, conflicts/native-client=409, permission=403, invalid selection=422, generic failure=500, but does **not** own a specific `419 invalid_csrf` entry or callable boundary.
- This is a **source-confirmed future-ON response-consistency gap**, not proof of a CSRF bypass: both callers reject invalid tokens before mutation. `assets/js/daily-record-revision-delete.js` rejects non-JSON/unexpected HTTP statuses with `status:'blocked'`, rather than treating 419 plaintext as DELETE success. Its DELETE browser error categorization is generic; this is fail-closed UX, not normalized 419 guidance.

**Recommended narrow follow-up (WS17 or separately named focused patch):** preserve existing canonical CSRF check and legacy gate-OFF plain-text response; add one shared future-ON DELETE CSRF JSON policy producing `419`, `success:false`, `code:'invalid_csrf'`, and an allowlisted safe message. Call it in BOTH delegated and privileged branches only *after* `verify_csrf_token()` rejects, and before transaction. Do not copy policy into both pages or relax permissions. Add focused source/temporary gate-ON fixture assertions for both branches, unchanged OFF response, no DELETE without CSRF, no native POST write, and no ambiguous success in browser. Do not broaden WS15 SAVE matcher to DELETE.

## 4. Browser fail-closed and draft preservation, source only

`assets/js/daily-record-revision-submit.js` sends same-origin, credentials same-origin, `redirect:'error'`, `Accept: application/json`, and `X-Requested-With: XMLHttpRequest`. Only known status/code pairs are assigned a human-readable reason. 409 `record_conflict` becomes conflict, but other unrecognized/non-JSON/redirect/network/malformed responses remain blocked. A save is accepted only on verified 200 JSON success containing valid original identity, revision progression, and continuity count; it never auto-retries a stale EDIT as CREATE.

`assets/js/daily-record-revision-editor.js` invalidates immutable edit authority after a failed or conflicted SAVE and preserves business form values; stale asynchronous selection responses are ignored. `assets/js/daily-record-revision-page-bootstrap.js` supplies fixed guidance on auth, permissions, entitlement, CSRF, storage, input and revision-client problems. `assets/js/daily-record-revision-delete.js` uses originally loaded DELETE tokens, AJAX and strict returned record identity; errors are blocked. `lib/daily_record_revision_page_switch.php` emits future-only `inert` markers and a one-owner client switch. These are source and simulated-browser contracts, **not** real browser behavior or server authorization proof.

## 5. Other middleware and writer-inventory disposition

- `includes/csrf.php` provides generic plain-text `require_valid_csrf_post()` for legacy routes. Its reviewed consumers do not establish a separate pre-route CSRF owner for the three exact SAVE pages, whose handlers use `verify_csrf_token()`/WS13 response policy. Future isolated authenticated HTTP call-graph tests must re-evaluate every include path.
- `includes/legacy_authorization_closure.php` adds permission/CSRF rules for Sales Records, Animal Profile and Investigation surfaces; no reviewed top-level conditional directly targets the three Daily Record SAVE pages. This is limited source observation, not an exhaustive proof about all included dependencies.
- `api/delete_record.php` (Poultry W4) calls common init/login, POST, CSRF and rate-limiting before its future revision DELETE dispatcher. It uses API-specific `send_json` policy. WS16 has not certified exact API status/code parity for every early denial; treat separately from the Ruminant page DELETE seam.
- Inherited direct-source inventory documents seven recognized writers: Layer SAVE, Broiler SAVE, Ruminant SAVE, Poultry DELETE, shared Ruminant DELETE (two callers), later-row continuity update and production-cycle initial seed. A tenant-purge exception and allowlisted dynamic CAS/persistence/seed/continuity SQL are separately documented. WS16 reviewed that inventory and selected source files; **it did not execute a repository-wide exhaustive mutation search or examine live triggers/stored programs/external jobs**. No assertion of global writer exhaustiveness is authorized.
- Migration planning must retain tenant/cycle/species identity, NULL-cycle and FK deletion-domain decisions, stock/feed/egg-sale side effects and canonical farm→cycle→source lock ordering. These need real, separately authorized data and concurrency evidence.

## 6. Status and next safe work

| Requirement | At WS16 |
| --- | --- |
| Pinned WS15 source identity + protected gate/runner | REMOTE CHECKED before audit branch |
| WS15 early exact SAVE responses | SOURCE IMPLEMENTED; previous isolated Mac PASS; not retested |
| Inner SAVE transport/CSRF/JSON/CAS dispatch | SOURCE IMPLEMENTED; previous isolated Mac PASS; not retested |
| Future-ON Ruminant DELETE invalid-CSRF JSON parity | SOURCE GAP CONFIRMED; NO FIX IN WS16 |
| Browser fail-closed/draft/token path | SOURCE-SUPPORTED; real authenticated browser NOT RUN |
| Subscription lifecycle after early denial | Independent canonical lifecycle write MAY occur; integration/runtime NOT RUN |
| Early exceptions in arbitrary modules, rate-limit/proxy errors | NOT CERTIFIED |
| Dynamic/trigger/external writer exhaustiveness | NOT CERTIFIED |
| Actual physical MariaDB customer schema / NULL-cycle aggregates | UNKNOWN / NO ACCESS |
| Approved three-table DDL / partial apply and rollback | NO |
| Repaired real RC01–RC14 | ZERO PASS; original TWO_STALE_COMMITS still FAILED |
| Authenticated HTTP/browser and coordinated seven-writer cutover | NOT RUN / UNAUTHORIZED |
| B12 cutover gate | HARD-OFF |

**Next controlled source task:** narrowly fix the shared Ruminant DELETE invalid-CSRF 419 future-ON representation gap with focused isolated tests and a guarded remote semantic diff; leave WS15 and historical runners unchanged. Separately plan real HTTP/browser/DB verification, only under fresh explicit host-, fixture- and operation-specific approval. Never treat this document or temporary gate-ON fixtures as release certification.

**Audit method:** GitHub connector file reads at the pinned immutable WS15 SHA plus remote identity checks. No PHP/Node tests, Mac Docker, production, SQL, staging, CI or migration. This document records observation, not runtime proof.
