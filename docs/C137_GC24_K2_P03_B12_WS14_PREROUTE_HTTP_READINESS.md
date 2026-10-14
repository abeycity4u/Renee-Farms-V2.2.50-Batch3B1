# C137 K2-P03 B12 — WS14 Pre-route HTTP integration review

**Reviewed:** 2026-10-14. **Source baseline:** `c137-gc24-k2p03-b12-ws13-save-json-boundary-source` at `8fd913cf21993c184057ddb2fffc9a5d9cbf56d0`.
**WS14 scope:** GitHub source inspection and documentation only. No application code edits, PHP/Node execution, browser/HTTP calls, database, migration, protected environment or cutover activation. **Release status: HOLD; gate HARD-OFF.**

## 1. Trust boundaries and request ordering

Three save routes: `poultry/layers_daily_record.php`, `poultry/broiler_daily_record.php`, and `ruminant/ruminant_daily_record.php`. Each starts by loading `init.php` (which loads `config.php` if necessary), then calls `requireLogin()`, checks module access, resolves current farm and performs preparatory reads **before** entering its SAVE-specific POST branch. The shared B12 `daily_record_revision_save_http_require_ajax()` runs inside that branch, ahead of page CSRF/business-validation and the dormant CAS dispatch. Therefore it is a correct inner boundary but **not** a universal initial-request gate.

| Stage and source | Directly observed response/behavior | Source conclusion |
|---|---|---|
| `config.php` 67–82: session timeout | Clears/destroys the session; issues `Location: .../login.php?timeout=1` and exits | **OPEN:** redirect occurs before B12 JSON handler; fetch `redirect:'error'` fails closed, but cannot inform user specifically of session expiry |
| `config.php` 89–100: PDO startup failure | Logs an error and emits plain `Unable to connect...` using `die()` | **OPEN:** not uniform JSON/503 for B12; occurs before SAVE-specific handler, no current evidence of a business write |
| `init.php` 91–100 → `includes/farm_entitlement_runtime.php` 90–99 | Module entitlement gating may reject POST via `403` with plain text; route map covers all three Daily Records | **OPEN:** early subscription denial is not B12 JSON; entitlement MUST remain non-bypassable |
| `init.php` 105–113 → `includes/permission_runtime.php` 176–244 | Route View and operation Add/Edit authorization; stale/invalid `write_intent` is parsed while future gate ON. `permission_runtime_deny()` returns plain `403` on POST | **OPEN:** pre-route permission/intent failures are not consistent JSON; no code path should move privilege enforcement after a write |
| `config.php` 347–379: `requireLogin()` | Redirects to login when not logged in, deleted, or suspended/cancelled; all 3 pages call it | **OPEN:** cannot be normalized by the later SAVE handler; protected user state remains correctly invalidated |
| `config.php` 152–161: `requireCurrentFarmId()` | Clears session and redirects to sign-in when farm missing | **OPEN:** before future SAVE branch, so not a structured JSON response |
| Three pages: initial module `checkAccess()` | Redirects to dashboard before SAVE parsing | **OPEN:** early denial may present as redirected/blocked AJAX response; no evidence of unauthorized write |
| `lib/daily_record_revision_save_http.php` | After above gates, future ON rejects native/non-AJAX POST with 409; early CSRF and validation failures use common JSON 419/422; OFF preserves legacy response | **SOURCE VERIFIED:** WS13 original user-submitted offline result 9 PHP lints PASS, 5 suites PASS, 29 source assertions, 9 fixture cases PASS |
| `lib/daily_record_revision_route_adapter.php` 268–319 | Independently refuses hard-OFF and non-AJAX before transaction; shared JSON outcomes include conflict 409, invalid 422, storage 503 and generic failure 500 | **SOURCE VERIFIED:** inner transactional boundary, not a defense for earlier bootstrap exits |

Related early route enforcement is intentionally centralized: do NOT copy authorization checks page-by-page or bypass `farm_entitlement_runtime`, `permission_runtime`, `requireLogin()` or `requireCurrentFarmId()`. Treat POST transport markers (`X-Requested-With` and Accept) only as representation preferences; never as authentication, CSRF, tenant, role or revision authorization.

## 2. Browser handling and fail-closed state

- `assets/js/daily-record-revision-submit.js` sends same-origin POST with `redirect:'error'`, `Accept: application/json`, `Content-Type: application/x-www-form-urlencoded` and `X-Requested-With: XMLHttpRequest`. Non-JSON, rejected redirects, network failure, malformed response, non-200 success response and unrecognized 409 all produce `status:'blocked'`; only a genuine `409/record_conflict` yields the explicit conflict state; successful persisted identity/revision is verified before accepting `saved`.
- `assets/js/daily-record-revision-editor.js` (submit outcome path) revokes stale form authority but does not intentionally clear business fields on conflict/failure; stale async callback results are discarded for a changed selection. It does not currently surface distinct expired-session, permission or CSRF outcomes from all early failures.
- `assets/js/daily-record-revision-page-bootstrap.js` maps generic `submit_failed` to a user-facing save-failure message. It does not presently expose a dedicated session-expiry or permission-blocked recovery action from a JSON response.
- `lib/daily_record_revision_page_switch.php` and `assets/js/daily-record-revision-page-adapters.js` retain WS11 fail-closed HTML `inert` markers; record and Ruminant DELETE forms are released after handler binding, future All Cycles remains read-only. This is a DOM interaction guard, **not** substitute server authorization or a comprehensive protection against programmatic HTTP clients.
- `lib/daily_record_revision_delete_http.php` calls the same strict AJAX predicate introduced in WS13 through `lib/daily_record_revision_http_transport.php`, preserving the original DELETE JSON policy. `includes/permission_runtime.php`'s delegated Ruminant DELETE CSRF check (approx. lines 252–255) can still output a plain 419 *before* that DELETE-specific AJAX handler; out of scope for changing SAVE logic here, but should be part of future unified early-response certification.

**Safety classification:** Source review finds these browser paths designed to **block** unexpected statuses rather than interpret them as success. This is not proof of full native-browser safety, full HTTP uniformity, error UX, or absence of unrelated side effects in installed runtime.

## 3. Seven writer paths and side-effect boundary regression check

| Writer family | Dormant B12 authority still present | Unresolved runtime proof |
|---|---|---|
| W1 Layer SAVE; W2 Broiler SAVE; W3 Ruminant SAVE | All three gate-conditional shared route/cycle/record CAS calls remain; WS13 puts common AJAX check before per-page SAVE validation | Real authenticated requests, real schema, 2-connection RC01–RC04, ledger rollback |
| W4 Poultry DELETE | `api/delete_record.php` future gated `daily_record_revision_delete_transaction` | Real DELETE/EDIT and DELETE/DELETE races, role/CSRF parity |
| W5 Ruminant DELETE (privileged and delegated) | Canonical `ruminant_daily_record_delete_canonical`; shared revision transaction/JSON handler remains gated | Both caller roles, early delegated CSRF response, mortality/feed reversal |
| W6 Later-row continuity | Shared revision-aware continuity apply and changed-row bump, guarded by same coordinated cutover | RC09/11/12, lock graph with stock and population |
| W7 Cycle seed | `management/production_cycles.php` future shared `daily_record_revision_cycle_seed.php` | RC10 real seed transaction/rollback |

The seven paths are the recognized direct-source categories from `docs/C137_GC24_K2_P03_B12_SEVEN_WRITER_SOURCE_AUDIT.md`; exhaustiveness over triggers, off-host jobs and dynamic SQL is not established. Terminal Platform Owner farm purge is a distinct, privileged lifecycle exception.

## 4. Decision matrix

| Requirement | Status |
|---|---|
| WS13 common SAVE AJAX/JSON validation and OFF preservation | **SOURCE VERIFIED** from returned Mac pinned logs; no rerun in WS14 |
| WS11 default-inert browser ownership | **SOURCE VERIFIED** through previous focused fixtures, retained as staged code |
| Pre-bootstrap POST response uniformly JSON for 401/403/419/422/503 | **OPEN:** config/entitlement/permission/login paths may return redirect/text before SAVE helper |
| Server rejects unauthorized and native B12 SAVE before transactional commit | **SOURCE-SUPPORTED inner boundary**, but early paths require additional integrated HTTP checks; real authenticated certification pending |
| Browser preserves drafts and guides the user across actual login expiry/network/permissions | **OPEN:** simulated browser contracts only; auth-specific recovery not yet demonstrated |
| Production/real data three-table revision schema and nullable-cycle resolution | **BLOCKED/UNAUTHORIZED**; SQL draft not a migration |
| Repaired B12 RC01–RC14 two-MariaDB-connection certification | **NOT RUN**; original failed Actions run 38233373699 remains FAILED TWO_STALE_COMMITS |
| Live/staging/browser, migration, source/runtime switch and capacity | **UNAUTHORIZED**; D12 fifteen import gates BLOCKED, Stage D UNAUTHORIZED, 150 VU HOLD, 200 VU PROHIBITED |

## 5. Recommended WS15, narrow and source-only

1. Prepare a **shared pre-route B12 response classification** for the three exact SAVE routes, preserving existing canonical authorization order and semantics. Audit the top-level config/session expiry before selecting a safe seam; never casually modify global login/logout/entitlement behavior to suit one AJAX client.
2. Normalize only specifically authorized and characterized B12 SAVE early failures to JSON, with explicit status/code mapping such as `401 authentication_required`, `403 forbidden`, `409 record_conflict`/`revision_client_required`, `419 invalid_csrf`, `422 invalid_record`, and `503 unavailable`. Do not reveal farm/session/user identifiers; do not turn an old redirect or missing account into a successful write.
3. For future ON branch only, ensure `permission_runtime_daily_save_permission()` parse conflicts get structured responses while retaining Add/Edit permission enforcement. Treat entitlement, logout, missing farm and database-unavailable exits as separately audited boundaries; if initialization cannot safely produce JSON in a narrow patch, document a deliberate fail-closed non-JSON response instead of broad global rewrite.
4. Expand focused source and isolated fake HTTP negative tests to cover the exact bootstrap ordering, role denial and native POST fallback. Evaluate browser blocked-reason mapping separately, and preserve draft fields/immutable token invalidation. Run controlled pinned Mac Docker verification only when source has changed, then remote semantic diff.
5. **Do not** flip `lib/daily_record_revision_cutover_gate.php` (blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`), execute DB, stage RC01, change production, touch protected Mac source, rerun historic failed CI, deploy or create migration. Real authenticated HTTP certification and real schema/concurrency tests require distinct explicit approval.

## 6. Provenance and limits

Baseline WS13 HEAD `8fd913cf21993c184057ddb2fffc9a5d9cbf56d0`; production protected `v320-renee-agrisuite-branding` at `10041da3a3c4e4a7b8101f8183c243d056c7b128`; original Mac source branch protected at `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`; WS07 original consolidated runner blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`. Repaired B12 HAS NOT passed a real MariaDB race or authenticated browser test. WS14 documents a source finding, not a runtime regression or a release certificate.
