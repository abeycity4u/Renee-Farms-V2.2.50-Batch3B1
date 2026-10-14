# C137 / B12 — Daily Record Page Handler Cutover Contract (SOURCE ONLY)

**Evidence baseline:** `d810b92b7f6903674aa5dc15a6c739614c04b937` on `c137-gc24-k2p03-b12-cas-writer-cutover-source` (2026-10-14). This is a source inspection and a proposed activation contract, **not live browser/DB certification**.

## Mandatory safety invariant

- `lib/daily_record_revision_cutover_gate.php` must continue to return literal `false`. No current browser page may load a B12 JavaScript controller or switch away from its legacy page controller.
- No production or staging deployment, schema migration, database mutation, HTTP load, permission broadening, or race-certification claim is authorized by this document.
- A future cutover requires separately approved three-table `record_revision` DDL, seven coordinated CAS writers (save, DELETE, cycle seed, downstream continuity), focused verifiers, authenticated browser E2E, and real isolated MariaDB concurrent-writer evidence.
- An all-cycles `cycle_id=0` is **not** a legitimate edit scope. The browser workspace requires an exact positive string cycle ID. Legacy NULL-cycle rows must not be implicitly treated as an all-cycles selection.

## Existing page/browser ownership (observed)

| Page | Legacy page JS (currently loaded) | Date/existence and edit owners | Population/feed side effects |
| --- | --- | --- | --- |
| `poultry/layers_daily_record.php` | `assets/js/layers-daily-record.js` | `openRecordModal`, `checkExistingRecord`, `check_record.php`, `get_record.php`, and shared `attachEditModal` | `get_previous_stock.php` / `DailyPopulationMovementUI`; input normalization; laying-rate and crates calculations; feed item |
| `poultry/broiler_daily_record.php` | `assets/js/broiler-daily-record.js` | Same date-only existence/edit flow and shared edit-modal helper | `get_previous_stock.php` / population display; feed item; stock-readonly policy |
| `ruminant/ruminant_daily_record.php` | `assets/js/ruminant-daily-record.js` | `check_ruminant_record.php`, `get_record.php`, `attachEditModal`, species-change listener | `get_previous_stock.php` / population boundary; species/feed-unit handling; stock-readonly policy |

All three pages also load `assets/js/main.js`, `assets/js/edit-modal.js`, and `assets/js/daily-population-movement-ui.js`. The generic `main.js` can attach to forms labeled `data-record-form="true"`; future page controls must not accidentally enable that handler in parallel with B12. `assets/js/confirmations.js` registers a document-level capture-phase listener for `form[data-confirm]`, whose implementation can call **native `form.submit()`** after confirmation; a B12 Ruminant AJAX DELETE form MUST NOT retain `data-confirm`.

Layer and Broiler DELETE buttons currently use `data-poultry-daily-delete-type` and `data-poultry-daily-delete-id`. Gate-conditional original-row `data-b12-delete-tokens` is already staged in both pages, but no active script uses it. The Ruminant DELETE form likewise has gate-conditional original tokens, while the active form still carries `data-confirm` and sends a traditional POST. Privileged and delegated Ruminant handlers share the canonical delete transaction and staged HTTP 409 JSON policy, but the AJAX sender/binder remains unloaded.

## Dormant B12 browser modules (source prepared, not activated)

- `daily-record-revision-modal.js` — selection tickets; immutable original identity; never promote failed EDIT lookup to CREATE.
- `daily-record-revision-transport.js` — same-origin GET-only scope/exact API; fail closed on redirects/HTML/errors.
- `daily-record-revision-editor.js` — shared field map, guarded AJAX submit, no native POST, revoke tokens on conflict while preserving entered business fields.
- `daily-record-revision-submit.js` — shared POST with form snapshot and `save_record`, strict revision checks, no redirect.
- `daily-record-revision-workspace.js` — opt-in composition, exact positive cycle, callback contract; does not bind automatically.
- `daily-record-revision-delete.js` — original-row Poultry DELETE and original-form Ruminant DELETE; no delete-time revision lookup.
- `daily-record-revision-ruminant-delete.js` — opt-in Ruminant form binder; refuses forms retaining `data-confirm`, native POST interception, confirmation, conflict reload requirement.
- `daily-record-revision-page-lifecycle.js` — newly staged and **unloaded** ticket-bound population GET/page lifecycle: clears prior side panels on pending/selection change, renders stock/movement only if the original editor ticket remains current, rejects HTML/non-200/malformed data, prevents a delayed CREATE boundary from overwriting a user-edited opening-stock field, and revokes revision tokens on a current failed boundary without deleting draft entries. `onPopulation` is display-only: future thin adapters must not use it as an authority to mutate business fields. Save UI must remain disabled until `onPopulationReady` for the currently selected ticket.

## Required coordinated future page switch (NOT IMPLEMENTED)

1. Complete schema and all writer certification first; ensure server cutover gate, PHP GET API and three POST routes agree on immutable row identity, CSRF, farm, permission, and HTTP envelope. **Do not enable gate for partial testing.**
2. For each page, replace—not stack—the old page-specific date-only modal controller and `attachEditModal` hookup. Retire `check_record.php` / `check_ruminant_record.php` and `get_record.php` edit-authority reads in the B12 controller. Retain nonconflicting calendar/month/cycle navigation and calculator/feed/stock/display behavior under guarded shared callbacks.
3. Load shared B12 modules in dependency order, with one thin page adapter providing: `onPending` (disable Save and stale side panels), `onReady` (populate only with current ticket, show correct mode), `onBlocked` (styled actionable feedback without clearing draft), `onSaved` (confirmation/refresh), and `fetchImpl` (same origin). Disallow `cycle_id=0`, duplicates, missing/invalid inputs, and permission errors without fallback to legacy save.
4. Load the B12 DELETE transport only behind the coordinated page switch. Layer/Broiler DELETE must use the `data-b12-delete-tokens` rendered with the source row and a single confirmed handler. Ruminant must remove `data-confirm` from its B12 form and add `data-b12-revision-delete="ruminant"` before invoking the staged Ruminant binder. **Do not leave both capture-phase confirmation and B12 binding on one form.**
5. Preserve existing formulas, feed-item quantities/units, opening-stock policy, selected date/species, population activity placeholders, permissions, CSS/toasts and delete confirmations. All asynchronous `get_previous_stock.php` and population side-panel results must obey the same selection ticket as the source-record reader; stale responses cannot overwrite new form values or display.
6. Never transmit active revision tokens via a native form fallback. An aborted, rejected, stale, 401/403/409/419/422/500/503, network-failed or HTML/redirect response must retain unsaved fields and disallow unverified writes. For DELETE conflicts, require an explicit original-row reload; do not reissue a DELETE from new server tokens automatically.
7. Before activation, produce focused pure-JS fake-DOM tests, PHP source/permissions verifiers, browser POST/DELETE E2E for all three animal types, and isolated two-connection MariaDB CAS/continuity/multiwriter tests. Perform a **remote GitHub semantic diff** after every commit; do not repeat closed QA absent evidence of regression.

## Current blockers / unverified dependencies

- The three old modal controllers are still loaded. The new `daily-record-revision-page-adapters.js` shared owner and thin `daily-record-revision-{layer,broiler,ruminant}-page.js` entries are now **staged and unloaded**, not wired or browser certified. They explicitly refuse loaded legacy controllers, native Ruminant confirmation, missing original Poultry DELETE tokens and unapproved handler ownership.
- Ruminant page still has legacy `data-confirm` and no `data-b12-revision-delete`; the source-only binder correctly refuses that state.
- Need complete browser rules for calendar edit placeholders and asynchronous population-side-panel updates, not only source identity and POST.
- Actual browser and MariaDB concurrency behavior is unverified; native fake-fetch regressions cannot certify those.
- No conclusion is made that the code is production-ready, that migration is approved, or that the cutover may be enabled.

**Current next step:** run the focused native `k2p03_b12_thin_page_adapters.test.cjs`, `k2p03_b12_thin_page_adapters_source.php`, and the affected lifecycle/editor tests in the isolated source checkout. The browser page controller switch, formula/feed-unit/calculator parity, real authenticated E2E, three-table schema, seven-writer and real MariaDB concurrency gates remain OPEN. No live PHP page has loaded a B12 module.
