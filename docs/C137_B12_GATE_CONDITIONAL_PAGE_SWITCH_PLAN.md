# C137 B12 — Gate-conditional Daily Record page integration plan

**State: SOURCE ONLY / NOT IMPLEMENTED / CUTOVER OFF.** Inspected source: \`c137-gc24-k2p03-b12-cas-writer-cutover-source\` at \`65f08f14082a78b756bf5c5d481f25d18e17c7ba\` (2026-10-14). Future asset plan subsequently staged at \`lib/daily_record_revision_page_asset_plan.php\`. This is NOT production or browser readiness certification.

## 1. Authority and blocking safety conditions

1. \`daily_record_revision_cutover_enabled()\` remains literal \`false\`. This work does not edit active PHP Daily Record pages, load B12 browser controllers, execute any DB query/migration, or modify protected production.
2. The asset plan is inert metadata. It does **not** print \`<script>\` tags or initialize a browser controller. With the gate OFF, \`daily_record_revision_page_candidate_assets_if_enabled()\` returns \`[]\` for every species.
3. Gate activation is **prohibited** until separately certified: three-table \`record_revision\` DDL, every seven coordinated CAS writer/surrounding continuity contracts, isolated real two-session MariaDB races, authenticated browser E2E with three species/roles, safe rollback, and explicit user authorization.
4. Complete future page switch atomically per authorized rollout: the new page owner replaces, NEVER runs beside, the legacy modal, date-only existence/edit readers, DELETE confirmation, save-form POST path or other competing handlers. Do not activate a partial page. The current PHP source continues to serve the legacy pages with unchanged behavior.
5. Any still-unverified integration is a blocker, not evidence of passing functionality. Pure-Node synthetic events do not prove the behavior of Bootstrap, actual browser forms or MariaDB.

## 2. Existing source-specific page ownership

| Species | PHP page | Config marker | Active legacy controller | Current DELETE |
| --- | --- | --- | --- | --- |
| Layer | \`poultry/layers_daily_record.php\` | \`#layerDailyConfig\` | \`assets/js/layers-daily-record.js\` | \`[data-poultry-daily-delete-type="layer"]\` button with legacy handler |
| Broiler | \`poultry/broiler_daily_record.php\` | \`#broilerDailyConfig\` | \`assets/js/broiler-daily-record.js\` | \`[data-poultry-daily-delete-type="broiler"]\` button with legacy handler |
| Ruminant | \`ruminant/ruminant_daily_record.php\` | \`#ruminantDailyConfig\` | \`assets/js/ruminant-daily-record.js\` | native \`form[data-confirm]\` POST with CSRF |

Across all three, the visible top “Add Today's Record” button is **\`[data-open-record-modal]\`**, separate from the \`.add-record-btn\` and \`.edit-record-btn\` calendar actions. Both the \`#monthSelector\` and \`#activeCycleSelector\` are navigational. The \`#recordForm\` modal contains \`#selectedDate\`, hidden \`#recordDate\`, \`#openingStock\`, and Save. Ruminant additionally has \`#animalType\` and hidden \`#animalTypeHidden\`.

**Observed gap in staged B12 adapter:** \`assets/js/daily-record-revision-page-adapters.js\` binds the two calendar button classes but not \`[data-open-record-modal]\`. Before any page switch, add a single guarded top-button path using an explicit validated current date (existing \`ReneeCalendar.today\` / \`appToday()\` convention), and for Ruminant the selected cycle's supported species. No date/animal guess or all-cycles scope. Cover it with fake-DOM + authenticated browser tests. Do **not** replace legacy top-button behavior while the gate is OFF.

\`#selectedDate\` has \`data-check-existing-record\` on each existing page. Retire the date-only lookup trigger in the future B12 branch; it must not coexist with the authoritative revision lookup. Keep navigation, calendar month/cycle UI and shared nonconflicting \`main.js\` behavior. The \`edit-modal.js\` helper may remain on the page as a utility, but no legacy \`attachEditModal\` handler may be attached to the B12 form.

## 3. Future guarded asset manifest and script order

\`lib/daily_record_revision_page_asset_plan.php\` records the prospective JS dependency order for \`layer\`, \`broiler\`, and \`ruminant\`; all paths must exist and use existing \`versioned_asset()\`/BASE_URL behavior when future PHP rendering is approved. Never load a legacy per-type Daily Record script alongside these assets.

**Ordered shared scripts**:

1. \`daily-record-revision-modal.js\`
2. \`daily-record-revision-transport.js\`
3. \`daily-record-revision-submit.js\`
4. \`daily-record-revision-editor.js\`
5. \`daily-record-revision-workspace.js\`
6. \`daily-record-revision-page-lifecycle.js\`
7. \`daily-record-revision-page-fields.js\`
8. \`daily-record-revision-delete.js\`
9. Ruminant only: \`daily-record-revision-ruminant-delete.js\`
10. \`daily-record-revision-page-delete.js\`
11. \`daily-record-revision-page-adapters.js\`
12. One of \`daily-record-revision-{layer,broiler,ruminant}-page.js\`

These modules are **factories**, not automatic initializers. A thin, reviewed and separately verified future browser initializer is still missing. It must construct exactly one page adapter and pass the existing config values, same-origin fetch, Bootstrap modal, sold-stock display, \`AppConfirm\` and \`AppNotify\` callbacks. It must also strictly verify \`data-b12-revision-page="{species}"\`, retired legacy handlers, exact positive selected cycle, original-row DELETE ownership, and allowed role/tenant server policy. Do not set any of those ownership flags merely to satisfy a failing test.

## 4. Exact future PHP page switch boundaries (NOT APPLIED)

The following are proposed **gate-conditional** changes, for implementation **after** the blockers in §1 are closed:

- Preserve the existing hard-OFF PHP branch and its current script order, \`#recordForm\`, page header/top button, calendar table, feed selectors, month/cycle navigation, styled confirmations/toasts and delegated permissions. OFF must be byte-for-byte behavior-equivalent for users; use a focused OFF-branch source verifier and authenticated regression.
- Under the separately authorized B12 gate **only**, render the new explicit page marker in the existing per-species config element and load the manifest assets in dependency order. Retire the matching legacy \`{layers,broiler,ruminant}-daily-record.js\` script, its date-only \`check_record.php\`/\`check_ruminant_record.php\` and \`get_record.php\` edit-authority behavior, plus its \`attachEditModal\` callbacks.
- Do not attach multiple \`submit\` handlers to \`#recordForm\`. New save POST belongs solely to \`daily-record-revision-editor.js\` + \`daily-record-revision-submit.js\`, with strict AJAX, CSRF, scope, original revision, and population-readiness guard. Server AJAX-only enforcement must prevent native navigation/fallback even if scripts fail to load.
- Layer/Broiler DELETE: preserve pre-rendered \`data-b12-delete-tokens\` only for authorized deletable source rows; keep exact \`csrf_token\` from the page's existing config; bind only \`daily-record-revision-page-delete.js\` through the page adapter. No duplicate global \`deleteLayerDailyRecord\`/\`deleteBroilerDailyRecord\` ownership and no DELETE-time token-refresh GET.
- Ruminant DELETE: under the future B12 branch only, remove \`data-confirm\` native-submit ownership from the DELETE forms, add exact \`data-b12-revision-delete="ruminant"\` to each authorized DELETE form, keep original hidden revision/CSRF fields, bind through the staged AJAX-only Ruminant binder, and ensure both delegated and privileged PHP handlers enforce same JSON/409 policy. Gate OFF continues unchanged with native confirmation.
- \`#selectedDate\` change and Ruminant \`#animalType\` change must revoke the old selection's revision tokens; manual hidden fields may mirror the new selection, but Save stays disabled until a fresh exact-scope read AND current population boundary both pass. Late requests and pending discard confirmations must not clear new drafts. Zero/all-cycles cannot be silently treated as an editable cycle.
- Keep Layer egg mass/crates/laying calculations and Ruminant feed item unit from the shared staged \`page-fields.js\`; keep feed-stock, population movement side-panels, mortality, role-dependent opening stock, notifications and DELETE confirmation behavior. Values from a stale or failed GET cannot render or become POST authority.
- For broad shared scripts (\`main.js\`, \`edit-modal.js\`, \`confirmations.js\`, \`daily-population-movement-ui.js\`) inspect actual selector collisions and Bootstrap event behavior before attaching any B12 listeners. \`form[data-confirm]\` uses a document-level native-submit capture handler; this must not remain on B12 DELETE forms. Confirm no old generic \`form[data-record-form="true"]\` submit owner.

## 5. Pre-activation acceptance evidence still required

1. Source contract: gate OFF; active PHP pages unchanged; existing controls/markers preserved; asset order correct; no legacy/B12 script overlap in a rendered branch; all three page configs mapped; zero duplicate listeners; top-header Add Record is covered.
2. Focused JS + PHP contracts: full selection ticket lifecycle, original row DELETE tokens, Ruminant marker/native confirm exclusion, 409 blocked/reload, valid/invalid cycle and animal type, same-date/same-species and changed-date/species races, success/blocked notification states, user edits retained.
3. Real authenticated browser E2E for Layer, Broiler, Ruminant across actual farm roles: create, edit, save, DELETE, stale conflict, draft cancel, browser redirects/HTML/CSRF, calendar top button, navigation, feed unit, opening-stock permission, movement side panel, modal open/close. Test both gate-OFF legacy and separately authorized gate-ON behavior.
4. Explicitly approved isolated fixture DB schema and two-connection MariaDB CAS/continuity/multiple-write races; all seven writer paths, tenancy/permissions, migrations/rollback evidence. Never use live customer DB, production URLs or load tests for this staging work.
5. Remote GitHub semantic diff after every implementation commit; safe branch + exact HEAD guard; local/native focused proofs only when affected; fail closed on any unexpected source drift.

**Current milestone:** dormant three-type manifest and source verifier only. **No active PHP page modification, no script tag, no test of gate-ON rendering, no database mutation, and no cutover authorization.** Next safe step after native manifest tests: design and test an inert top-header Add Record handler that the shared page owner can bind exactly once, then verify future bootstrapping callbacks in a fake DOM before considering active page changes.
