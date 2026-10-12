# C137 GC24 K2-P03 B12 — shared Daily Record form identity presentation

**2026-10-12; SOURCE ONLY; NOT INTEGRATED into application pages, API, JavaScript, migration, GitHub Actions, database or deployment.**

## Baseline

- Repo: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- New isolated branch: `c137-gc24-k2p03-b12-form-identity-source-contract`; exact fork SHA `0c6261318639113c81875a6a44fb5a9b280c8fde`.
- Shared strict intent parser from prior work: `lib/daily_record_revision_authority.php`, blob `6f3b6dd323e8449cd9779675f18e2970492e6a8d`.
- New helper: `lib/daily_record_revision_form_tokens.php`, exactly verified blob `6f88be9e6077e7847425618f3da790ecf61f9619`.
- New pure verifier: `tests/integration/k2p03_b12_form_tokens_pure.php`, exactly verified blob `8f37a6a53a8a4011a3494e2673ef30fdf8aee987`.
- Offline isolated PHP 8.4.24: lint BOTH new files PASS; pure verifier **92/92 PASS** (`B12_FORM_TOKEN_PURE=PASS`). The installed full source archive at an earlier pinned HEAD was used to reconstruct the shared strict parser; its local reconstructed blob was verified **exactly** equal to the current GitHub strict-parser blob before testing.
- Prior synthetic MariaDB schema R2 run `38301126240`, attempt 1, **SUCCESS**; its authorization was consumed. Original B12 stale-write run `38233373699` remains **FAILED/TWO_STALE_COMMITS**; original B12 branch unchanged.

## Shared contract

`daily_record_revision_form_fields(string $type, int $farmId, ?array $row): array`

The helper validates `type` using the existing closed Layer/Broiler/Ruminant authority map, rejects nonpositive `farmId`, and emits one consistent set of browser hidden-field names:

| Field | Explicit CREATE | Durable EDIT snapshot |
|---|---|---|
| `write_intent` | `create` | `edit` |
| `record_id` | Empty | Canonical positive persisted source-row ID |
| `expected_revision` | Empty | Original client-loaded positive row revision |
| `original_cycle_id` | Empty | Canonical cycle ID, or empty for a true NULL legacy cycle |
| `original_record_date` | Empty | Valid immutable record date |
| `original_animal_type` | Empty | Normalized Ruminant species; empty for poultry |

A `null` row **means explicit CREATE**, not a date-based “record may exist” guess. For EDIT the caller must supply a real persisted row with `id, farm_id, cycle_id, record_date, record_revision` and `animal_type` for Ruminant. The helper rejects missing or malformed identities and revisions; rejects rows from another farm; rejects synthetic `is_population_activity_only` display rows; validates the exact cycle/date/species identity using the existing shared scope validator; and round-trips edit intent through the existing strict parser.

**Security meaning:** these browser hidden fields are all **untrusted**. They are only original client intent, not proof of row access or authority. On POST, permission and CSRF checks must remain independently authoritative, and the database must lock the row by immutable ID and farm, verify cycle/date/species/revision, then perform the CAS and all feed/stock/mortality/continuity/Layer egg-sale changes in the *same* transaction.

**Source-only limitation:** the deployed three tables do not yet have `record_revision`. Calling the helper on existing live source rows would fail closed, because `record_revision` is required. It must not be imported into live pages or API until migration, client wiring and transaction design are approved and tested together.

## Browser / API audit — exact integration points

| Surface | Current source finding | Required next controlled integration |
|---|---|---|
| `poultry/layers_daily_record.php` | `SELECT *` monthly rows; edit buttons contain fields but not `data-record-id` or `data-expected-revision`; modal POST has date/cycle/CSRF but no write intent | When schema exists, render shared durable-row tokens into **edit** button dataset and modal hidden inputs; reset CREATE fields for new records; never edit display-only population movement entries |
| `poultry/broiler_daily_record.php` | Same monthly edit/dynamic calendar pattern; no persisted revision in modal | Same shared token generation; suppress edit when revision is not available and surface a reload/conflict response, not a fallback edit |
| `ruminant/ruminant_daily_record.php` | Edit button preserves animal type but lacks record revision/identity; existing delete form supplies `record_id` only | Add original revision and full original scope to edit + delete after schema/migration; keep canonical delegated + admin Ruminant deletion service |
| `assets/js/edit-modal.js` | Shared `attachEditModal` copies button `dataset` values to matching `fieldMap` selectors; offers `onShow` callback | Add shared field map for the six identity tokens only during integration; clear stale tokens before each CREATE/date/species/cycle selection and after any failed async record fetch |
| `assets/js/broiler-daily-record.js` and `assets/js/ruminant-daily-record.js` | `check_record` determines “edit” from date; `get_record` returns field values but does not currently populate immutable identity/revision token | Replace ambiguous date-based inference with durable row-id/resolved scope; protect against stale async responses and fail closed if no unique row/revision; no browser-side guessed revision |
| `api/get_record.php` | `SELECT *` by farm/date and optional cycle, plus species for Ruminant; `fetch()` returns a single arbitrary matching row when legacy/multiple cycles are present | **Do not** return an arbitrary row for editing. Enforce explicit immutable source-row selection and correct role/module policy; provide only browser-loaded token data for one tenant row; do not trust client-supplied farm |
| `api/check_record.php`, `api/check_ruminant_record.php` | Existence probes are currently part of create/edit inference | Existence result must never silently change a submitted CREATE into EDIT; use deterministic uniqueness handling and conflict if multiple logical rows |
| `api/delete_record.php` | Layer/Broiler DELETE has ID and CSRF but no expected revision / locked identity compare, with shared feed/mortality and Layer egg-sale rebuild | Add client-loaded revision and exact source lock **before** downstream reversal; preserve all existing permissions and transaction effects |
| `lib/daily_population_continuity.php` | Can adjust later Daily Record opening-stock values and Layer laying rate; synthetic display rows marked with `is_population_activity_only` | Revision-bump actually modified later rows; synthesized activity entries must **never** receive EDIT tokens |

## Known blocked gates and data semantics

- The synthetic MariaDB R2 test proved `cycle_id IS NULL` duplicates, date-only multiple-cycle ambiguity, plain cycle FK cross-tenant linkage, and potential additional NULL collision on `ON DELETE SET NULL`; actual tenant counts remain **UNKNOWN**.
- A naive `FOREIGN KEY(farm_id,cycle_id) REFERENCES production_cycles(farm_id,id) ON DELETE SET NULL` would attempt to null nonnullable `farm_id`; it is not an approved fix.
- Reject speculative migration `092`, index replacement, historical deduplication or change to cycle deletion policy until a separate authorized legacy schema/data audit. DDL in MariaDB is not transactionally atomic across multiple ALTERs.
- Browser token representation alone cannot prevent last-writer-wins until three-table revisions exist and every relevant shared writer increments and validates them. Strictly guard against a POST-time server read being mistaken for the browser-loaded revision.
- No source-only form helper grants tenant scope, delete permission, stock authority, billing privileges or bypasses CSRF; this helper is **unwired**.
- No B12 concurrency rerun or CI approval file was created in this step. D12 fifteen gates BLOCKED, Stage D UNAUTHORIZED, 150 VU HOLD, 200 VU PROHIBITED. Production, staging, cPanel and AWS protected.

## Next work (requires continued source-only review; NO runtime activation)

1. Design shared modal JavaScript token-clearing and immutable identity selection contract for both HTML button edits and asynchronous fetches; avoid stale Promise responses replacing newer selected dates/cycles.
2. Define API response cardinality and record-id loading guards for legacy NULL-cycle dates, in the existing shared permission model.
3. Draft multi-module CAS integration order and continuity revision bumps together, then separately authorize any migration, schema change, synthetic multi-process CI or deployment.

**One fix, fit it all:** form fields and identity validation are centralized, routes remain thin, and no duplicate financial/stock/population policy is introduced.
