# C137 GC24 K2-P03 B12 — shared modal async selection/token contract

**2026-10-12 | SOURCE-ONLY AND UNWIRED. No application pages, API routes, database schemas, CI workflows, protected environments, or deployments changed.**

## Immutable source boundary and evidence

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Isolated branch: `c137-gc24-k2p03-b12-modal-async-tokens-source`, forked at `0f57290a33bc0049fc5dc3bfe209e57f26330bfa` from the prior form-identity source branch.
- New standalone browser script: `assets/js/daily-record-revision-modal.js` (locally tested Git blob `ea978477273d2f5577254faa70fde8e4cc2f6b82`).
- New pure Node test: `tests/integration/k2p03_b12_modal_async_tokens.test.cjs` (locally tested Git blob `b47bec4342d2c34d28ea0b230ca5c1b2024bf063`).
- Local Node v22.16.0: `node --check` on both files **PASS**; `node tests/integration/k2p03_b12_modal_async_tokens.test.cjs` **40/40 PASS**, marker `B12_MODAL_ASYNC_TOKEN_PURE=PASS`. No browser, database, app runtime or external service was used for tests.
- Previously completed MariaDB **synthetic schema-only** R2 run `38301126240` **SUCCESS**. Original **concurrent stale-writes** B12 run `38233373699` remains **FAILED/TWO_STALE_COMMITS**. No CI rerun authorized.

## Shared browser controller (not loaded by any runtime page)

`window.ReneeDailyRecordRevisionModal.createController({form, type, readScope})`

- `type` is the closed set `layer`, `broiler`, `ruminant`.
- `form` must contain *exactly one* hidden input per server-established field: `write_intent`, `record_id`, `expected_revision`, `original_cycle_id`, `original_record_date`, `original_animal_type`. Missing/duplicated controls make initialization fail closed.
- `readScope()` MUST return current user-selected `{record_date, cycle_id, animal_type}` from actual modal controls (or trusted page cycle selection). Ruminant species is trimmed/lowercased. The NULL cycle identity is exactly empty string, **not zero**. Dates must be valid calendar dates.
- IDs and revisions are canonical positive decimal strings (safe numbers also accepted), compared lexically against 64-bit PHP upper bounds; no JavaScript floating-point coercion or invented current revision.
- Every `begin()` clears all original tokens, enters pending state, creates a new identity-bound ticket even if the user later revisits the same date/species/cycle.
- `isCurrent(ticket)` checks **both** ticket identity and current form selection. An old asynchronous result cannot authorize a new selection.
- `loadEdit(ticket, tokens)` accepts the six exact fields from the server's future `daily_record_revision_form_fields(...)` durable EDIT snapshot only when it belongs to the current selection. Missing/mismatched date, cycle, species, ID or revision fails closed and clears all tokens. A display-only population activity row is never an edit.
- `allowCreate(ticket)` explicitly marks CREATE with empty original tokens only while the selection is still pending. **The caller must independently prove the CREATE path is allowed**, including legacy uniqueness/cardinality checks; this controller cannot decide whether a source record exists.
- `reject(ticket)` on a current failed load and `invalidate()` on modal close, reset, date/species/cycle change, or selection replacement clear all tokens. A failed EDIT must NEVER fall back to CREATE.
- `isReady()` requires a committed CREATE/EDIT state, current selection and unchanged hidden fields. Pending or tampered/changed selection returns false; a future submit handler must block the POST unless true.

A ticket is browser state **only**. It is not a tenant-grant, a CSRF token, a persistent lock or a guarantee against another user's database edit. Server-side immutable row-id + farm-scoped revision compare remains mandatory.

## Cross-module future integration requirements (none performed)

1. **Schema first, after separate authorization:** three authoritative Daily Record `record_revision` columns and a legacy nullable-cycle uniqueness/SET NULL collision strategy. Source R2 synthetic findings do **not** establish actual tenant duplicate counts; production/staging remain closed.
2. `api/get_record.php` must stop using a potentially ambiguous `fetch()` over farm/date/(species) with optional cycle. A future edit loader must return exactly one authorized immutable tenant-scoped row with its originally observed `id`, revision, cycle/date/species. No server POST-time revision refresh. No invented revisions.
3. All three Daily Record forms must render the six hidden inputs from `lib/daily_record_revision_form_tokens.php` only **after** the schema/writer activation gate. Existing calendar/modal display-only `is_population_activity_only` entries must remain non-editable.
4. Integrate the shared controller once per modal: `begin()` on every new selection, `isCurrent(ticket)` **before filling ANY operational fields, population panels or tokens in each async callback**, and `loadEdit` only after an authoritative unique row response. On error/ambiguity `reject(ticket)` and report a styled conflict. Do not treat `check_record` existence as an edit identity.
5. The shared `assets/js/edit-modal.js` currently copies `data-*` attributes; use a common six-field token map for actual persisted-row edit button loads. `assets/js/layers-daily-record.js`, `broiler-daily-record.js`, `ruminant-daily-record.js`, and `daily-population-movement-ui.js` async fills must all be ticket-protected. Cancel and modal hidden events must invalidate, not merely `form.reset()`.
6. Prevent submit until `isReady()`; server separately validates CSRF/tenant/permission plus client-loaded immutable ID and expected revision. Only the shared CAS authority should own source UPDATE/DELETE policy; existing feed, inventory, mortality, continuity and Layer egg-sale side effects remain one atomic transaction.
7. Continuity changes to later Daily Records must bump their revisions. Shared Ruminant privileged/delegated deletion must still own the same feed and mortality reversal. Do not issue any route-level duplicated business SQL.

## Evidence boundaries and release holds

- The **40 Node tests are isolated controller unit checks**, not real browser E2E, PHP runtime integration or DB CAS certification. Neither the new browser script nor new hidden fields are included by existing pages. No app behavior changed.
- This module relies on browser event integration to call `begin()` on every selection change and to guard **all** async side effects; it cannot stop unrelated legacy AJAX callbacks until wired.
- The original failed B12 concurrency workflow was not retriggered. No `092` migration, deduplication, index/foreign key modification, protected tenant audit or production deployment was authorized.
- D12 15 import gates **BLOCKED**, Stage D **UNAUTHORIZED**, 150 VU **HOLD**, 200 VU **PROHIBITED**. Production/staging/AWS/cPanel/fixtures protected.

## Next controlled source-only item

Design the authoritative **read-one-row API and cardinality contract** for Layer, Broiler and Ruminant, handling duplicate legacy NULL-cycle keys, selected-cycle ambiguity, tenant permission, revision token response, and matching the browser ticket scope. Do not wire until migration and explicit DB gates are satisfied.

**One fix, fit it all:** central form UI identity, central PHP revision authority, no duplicate page-level policy, and remote semantic diff required after every source push.
