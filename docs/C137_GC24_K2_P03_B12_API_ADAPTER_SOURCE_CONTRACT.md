# C137 GC24 K2-P03 B12 — guarded Daily Record read API adapter

**2026-10-12. SOURCE-ONLY, DORMANT. NOT WIRED TO AN HTTP ENDPOINT OR DEPLOYED. No real database or customer data accessed.**

## Pinned source and local evidence

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Isolated branch: `c137-gc24-k2p03-b12-api-adapter-source`; exact fork `bede8d087896cff38642f8901222f1b0e773a934`, the previously verified reader-cardinality source branch.
- Existing reader changed **only** to distinguish absent exact record identity from mismatched/ambiguous identities: `lib/daily_record_revision_reader.php` blob `c8a768951973d542b82a44e8a387a572c63c5c07`. `DailyRecordRevisionNotFound` extends `RuntimeException` (the prior `DailyRecordRevisionConflict` class is final), and the zero-row exact case throws this typed exception. Existing two-row/cross-tenant/stale cases still throw `DailyRecordRevisionConflict`.
- Existing focused reader verifier updated to recognize the typed 404 class: `tests/integration/k2p03_b12_reader_pure.php` blob `de2eecf2562ee5caf61dc255016debe1072f70c8`.
- **New shared API adapter:** `lib/daily_record_revision_api_adapter.php` blob `3ea3cde67cd4d5bf3c0d4e0f6466e34f4751c853`.
- **New pure API verifier:** `tests/integration/k2p03_b12_api_adapter_pure.php` blob `d562bf9fee7c022247e600e41120b1fed7cea1c7`.
- Local PHP **8.4.24** syntax lint PASS for all four changed/new PHP files, plus current `daily_record_revision_authority.php` and `daily_record_revision_form_tokens.php`.
- Offline PDO-stub test **PASS 100/100** for updated reader, marker `B12_READER_CARDINALITY_PURE=PASS`; new API adapter test **PASS 135/135**, marker `B12_API_ADAPTER_PURE=PASS`.
- All source hashes above were independently computed on the **same tested local files** and must be confirmed against GitHub before treating this as fully verified.
- No GitHub Actions workflow was created or run under this isolated development step.

## Read-only API contract

Proposed future `GET` route would initialize the ordinary application session and **call `requireLogin()` first**, then call `daily_record_revision_api_handle($pdo, $_SERVER['REQUEST_METHOD'], $_GET)`, then use the existing `send_json($result['body'], $result['http_status'])` helper, which emits JSON and `Cache-Control: no-store`.

This is a **contract**, not a live route. The new file is a shared helper and must not be included by existing live `api/get_record.php`, `api/check_record.php` or `api/check_ruminant_record.php` until the separately approved schema and CAS writer activation gate.

### Strict input and modes

The request requires exact lowercase `mode=exact|scope`, `type=layer|broiler|ruminant`, valid `record_date=YYYY-MM-DD`, and **present** `cycle_id` parameter. A positive canonical cycle ID selects one production cycle; an *explicitly submitted* empty `cycle_id=` denotes a legacy `NULL` cycle; `0`, omitted, negative, leading-zero, arrays and malformed numbers are rejected. Ruminant requires supported `animal_type` (cattle/goat/sheep/other). Poultry rejects `animal_type`. Exact EDIT loads must provide canonical `record_id`. Scope probes MUST NOT include `record_id`. Unknown keys (including `farm_id`), alternate method, and array-valued query inputs are rejected.

No request-supplied farm or permission name is accepted. `daily_record_revision_api_authorized_farm()` checks positive authenticated session ID and the existing initialized application's `isPlatformOwner`, `hasRole`, `checkAccess`, `hasPermission`, `getUserType`, `requireCurrentFarmId` functions, and fails closed when required runtime functions are unavailable. The existing Platform Owner/Farm Admin bypass is preserved. Delegated module readers require matching module access and Daily Record *View* permission. An immutable-row **edit loader** additionally requires the matching Daily Record *Edit* permission; no view-only role may receive its edit tokens. Future runtime should integrate existing canonical role helper centrally, with no new role grants.

The adapter checks all three `record_revision` column presences through a read-only `information_schema` SELECT before using the dormant reader. This is just a guard, **not certification of actual deployed schema compatibility or legacy records**. Without three revision columns it responds 503 rather than guessing a revision. The source-schema R2 synthetic run established these columns are absent from the pinned `database_schema.sql` baseline; thus this adapter is not ready to activate.

### Successful response envelopes

- Exact EDIT: `http_status=200`; JSON `{success:true, mode:"exact", data:{record:{...}, form_tokens:{write_intent:"edit", record_id:"...", expected_revision:"...", original_cycle_id:"...", original_record_date:"...", original_animal_type:"..."}}}`. The `record` object exposes only the closed per-type editor field allowlist plus immutable display identity, not internal `farm_id`, `user_id`, audit timestamps, raw revision or joined-cycle metadata.
- Explicit scope: `http_status=200`; JSON `{success:true, mode:"scope", data:{status:"absent"|"unique", record_id:null|N}}`. Absence is **not** an INSERT permit or CREATE reservation; uniqueness only yields an ID for a subsequent independent exact authorized read. Ambiguity (`LIMIT 2` detecting two) fails 409. No edit field or revision token ever returned from a scope probe.
- Exact row SELECT remains `WHERE d.id=? AND d.farm_id=?` and tenant-qualified parent-cycle validation. A row that does not belong to the authorized farm cannot be read merely by knowing its ID.

### Fail-closed status policy

| HTTP | Code | Condition |
|---|---|---|
| 400 | `invalid_request` | Invalid/absent/ambiguous request parameters, no server-side silent coercion |
| 401 | `authentication_required` | No valid authenticated session |
| 403 | `forbidden` | Missing module, View/Edit permission, trusted current farm, or runtime authorization context |
| 404 | `record_not_found` | Exact tenant-scoped ID SELECT found no row (including inaccessible/wrong-farm ID) |
| 405 | `method_not_allowed` | Any non-GET request |
| 409 | `record_conflict` | Multiple scope rows, immutable source identity mismatch, invalid source revision, wrong-tenant cycle link, stale selection |
| 503 | `schema_unavailable` or `reader_unavailable` | Missing three-table revision precondition or PDO infrastructure failure |
| 500 | `internal_error` | Unexpected internal failure; generic message only |

No raw SQL errors, tenant IDs, credentials or internal PDO error strings go into error response bodies. No mutation occurs.

## Exact test coverage and limitations

The new 135-check isolated verifier uses mock `PDO` / `PDOStatement`, fake runtime permission functions and synthetic rows. It verifies Layer/Broiler/Ruminant authorization, delegated View and Edit separation, Farm Admin bypass, farm-scoped SQL parameters, success envelope allowlist, exact record 404, malformed and unauthorized 400/401/403/405, missing `record_revision` schema 503, ambiguous and cross-farm cycle 409, NULL-cycle scope and duplicate cardinality, safe generic DB failures and no CREATE authorization from a scope probe. The updated existing reader's 100-check source contract also passes.

**This is NOT a deployed HTTP handler, MariaDB compatibility test, browser E2E test, active role/CSRF certification, or multi-worker lost-update fix.** PHP unit mocks do not establish that all database queries or permissions behave correctly against an actual tenant. No `092` migration was written or applied.

## Next controlled gates

1. Continue **source-only** review of the browser → future thin route interaction, including `requireLogin()`, GET-only semantics, existing `api/api_helpers.php` no-store response policy, delegated tenant authorization, and field-level permissions. No live API rewrite yet.
2. Only after separate authorization, determine actual deployed three-table schema/legacy duplicates/cycle-to-farm mismatches in a permitted **read-only** environment, decide nullable-cycle uniqueness and `ON DELETE SET NULL` collision policy, then design an explicitly guarded migration.
3. Integrate schema revision initialization, CREATE-versus-EDIT, immutable ID/revision tokens, all mutation writers and downstream feed/stock/population/continuity/Layer egg-sale effects within transactions; browser async ticket controller must guard every callback.
4. A new explicitly authorized disposable MariaDB + browser E2E and multi-worker contention certification is required. The original B12 failed GitHub run **38233373699** `TWO_STALE_COMMITS` is not eligible for silent rerun. R2 synthetic *schema* evidence run **38301126240** PASS does not imply B12 concurrency PASS.

**Release holds:** D12 15 import gates BLOCKED; Stage D UNAUTHORIZED; 150 VU HOLD; 200 VU PROHIBITED. Production/staging/AWS Nodes A/B/C/cPanel protected.

**One fix, fit it all:** one shared row authority + form tokens + async controller + exact reader + response adapter, no page-level duplicated SQL, authorization, pricing or financial side-effect policy. Remote GitHub semantic diff required for this commit chain.
