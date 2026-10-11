# GC24CG K2/P03 Stage B — Isolated canonical continuity test readiness

**Decision:** This checkpoint prepares evidence and a future real-database test matrix. **It does not execute tests**, grant DB read/write rights, create synthetic records, deploy application PHP or authorize import/dry run, restore, migration or load tiers.

## Verified lineage, source, and protection boundaries

- Branch starts from already Node A-verified K2/P02 HEAD `1f2a22f668788e075adce8ec0fee0d6b2648f924`. The optional `?int $sourceId = null` continuity correction remains the sole application-PHP difference from certified Reports.
- Frozen certified Reports `322438870263825f9e739a12c4bd478983e67141`; original K1/K2/P01 preparation `0d290c1f25b4bdb186f4cefbc61b7ade1bbcdf49`; protected D12 authorization JSON blob `8e131d1fb4d8996d5462cc46bd8045172bb39308`.
- Stage A Node A output **user-reported** `K2_P03_STAGE_A=PASS_NO_DB`: 38 fake-PDO behavioral, 14 canonical contract, 38 integration contract assertions; 5 PHP lint files. This is prior verified evidence, **not newly run in Stage B**.
- New Stage B checkout must be separate at `/opt/renee-c137-k2-p03b-src`, preserving the three previous worktrees. **Do not run PHP or contact Node B/C in this checkpoint.**
- Protected fixture `renee_c137_fixture`, restore DB `renee_c137_gc24_restore_z25`, and off-volume backup labeled `C137_GC24_Z25A_20261011T072212Z_31149.sql.enc` are **not revalidated**, not selectable as test targets, and must not be modified.

## Technical observation and test scope

The corrected `daily_population_continuity_preview` now receives saved `$sourceId` from `daily_population_continuity_apply`; the canonical branch invokes `daily_population_boundary_snapshots` with pending mortality and a locked projection. `daily_population_boundary_snapshots` draws on the cycle baseline, dated movements and source-owned projection overlay. For an absent V3 baseline or missing source ID, the documented legacy fallback remains. The later-record rewrite must leave original mortality untouched, reject invalid egg/mortality, and preserve source → continuity → Feed → mortality → Layer egg sales transaction ordering.

The source examination does **not** establish whether any of these operations work against a real MariaDB dataset. No example data, tenant, actor, permissions, target DB, credentials or eligible source ID has been certified. The proposed scenarios are **requirements**, not records or results.

## Planned isolated future regression scenarios (all NOT RUN)

- **B01 — canonical_layer_opening**: V3 layer baseline, saved Daily Record source ID and pending mortality. Required assertion: Canonical snapshot selected; later openings rebased; original mortality unchanged.
- **B02 — broiler_mixed_exits**: V3 Broiler baseline with dated Sale, Slaughter and Transfer movements. Required assertion: Later opening and closing derive from canonical physical movement order without double-counting.
- **B03 — ruminant_species_scope**: Ruminant cycle with multiple animal_type rows; saved source identity. Required assertion: Only the intended species' later Daily Records are corrected.
- **B04 — legacy_preview_fallback**: Legacy cycle without canonical V3 baseline and preview call omitting source ID. Required assertion: Legacy opening chain remains compatible; no unintended canonical write.
- **B05 — layer_eggs_revenue**: Layer later-date eggs, historical egg allocation and corrected opening. Required assertion: Eggs cannot exceed opening, laying rate recalculates, egg-sales allocation conserves pool.
- **B06 — invalid_future_mortality**: Later daily mortality exceeds corrected canonical opening. Required assertion: Explicit rejection; single transaction rolls back all daily/feed/mortality/allocations.
- **B07 — negative_canonical_balance**: Historical physical movement would yield a negative later population. Required assertion: Boundary guard blocks; no persisted negative population and no partially committed source.
- **B08 — mortality_replay_reversal**: Same Daily Record mortality corrected repeatedly and replayed. Required assertion: Versioned source projection/reversal idempotence, no duplicated physical reductions.
- **B09 — feed_and_stock_atomicity**: Daily feed usage and stock item with receipts/valuations. Required assertion: Linked USED/reversal source ownership intact; failed transaction reverses no stock without counterpart.
- **B10 — tenant_role_and_csrf**: Two unrelated synthetic tenant farms, actors, cycles, records and role boundaries. Required assertion: Unauthorized cross-farm, wrong-role and CSRF operations denied without data alteration.
- **B11 — rollback_at_mid_writer**: Controlled failure between continuity, feed, mortality and Layer egg-sales rebuild. Required assertion: Entire caller-owned transaction rolled back; all ledgers unchanged.
- **B12 — concurrent_correction_locks**: Two isolated concurrent edits targeting one cycle/date. Required assertion: Consistent lock order, one authoritative result or safe conflict, no silent overwrite.

## Independent approval prerequisites (all unknown / unapproved)

- **target_host_identity** — Independently establish exact isolated test DB host and network route; not Node B by assumption.
- **target_database_name** — Exact new disposable isolated database identity; no protected fixture, restore, shared staging or production database.
- **database_grants** — Least-privilege actor and schema-specific CREATE/READ/WRITE capabilities separately approved.
- **application_test_root** — Exact noncertified isolated runtime checkout with source HEAD and nonproduction config.
- **verified_no_production_network_route** — Evidence network/credentials cannot reach production or protected databases.
- **tenant_farm_and_actor** — Dedicated synthetic tenant, farm/cycle/actor permission and cross-tenant denial matrix.
- **source_and_schema_contract** — Exact schema version, eligible PHP runtime and source SHA integrity proof.
- **test_ledger_baseline** — Dated layer/broiler/ruminant V3 baselines and planned movement sources.
- **source_record_ids** — Actual synthetic daily row IDs and matching projection source IDs, not guessed.
- **test_data_authorization** — Explicit approval to create minimal isolated test records and transaction writes.
- **recovery_point** — Verified test-only rollback/reseed or approved encrypted backup evidence; no protected restore.
- **failure_atomicity_method** — Planned rollback and replay/idempotence checks without leaving durable damage.
- **audit_and_reconciliation** — Expected ledger movement, daily stock, Feed, egg-sale and mortality reconciliation.
- **operator_window_and_stop** — Named operator, explicit execution window, abort conditions, stop and containment procedure.
- **secrets_and_integrations** — No production API keys, mail, payments, cron, session secrets or monitoring callbacks.
- **explicit_runtime_approval** — Separate written user authorization naming actual host, DB, tenant, commands and allowed writes.

### Authorization sequence and stop conditions

1. **Separate read-only identification authority** must first specify the host, network path, exact disposable DB, source/tenant/actor and permitted evidence. No network connection before that permission is expressly given. Do not treat `renee_c137_gc24_http` or `c137-gc24-synthetic` as verified current identities.
2. **Separate transactional test authority** must then name an approved isolated database, dedicated nonproduction app checkout, synthetic fixture ID/manifest, test operator/time window, scope of SQL read/writes, rollback/seeding and failure containment. A test-writing permission is **not** granted by a read-only identification permission.
3. Preserve tenant and farm scoping, transaction locks, mortality projection versioning/reversal, Feed stock source ownership, Layer egg allocation and population effects from Sales/Slaughter/Transfers. Require rejection tests for cross-farm, wrong role, CSRF, negative balance, concurrent correction and replay.
4. Evidence on runtime errors must include the earliest failing step, sanitized log, pre/post movement ledger and Daily Record balances, scoped farm/cycle IDs, transaction rollback and committed-row/no-side-effect proof. No production identifiers, passwords, full connection strings or access tokens in chat evidence.
5. Protected fixture/restore DBs, backups, production, shared staging, certified source checkout and capacity nodes are *not* test resources. Halt immediately for unknown identity, credential route, tenancy, unexpected production reachability, schema drift, application source drift, partial write or rollback failure.
6. Keep all fifteen D12 import gates **BLOCKED**, independent of this targeted continuity test readiness. Even separately approved isolated regression execution **will not itself authorize fixture imports, load tiers or changes to a protected checkout**.

**Capacity:** 150 VU HOLD; 200 VU PROHIBITED. **Current database connections/writes, PHP executions, imports, restores, load tests: zero.**

Run the companion Python source-only verifier in a separately guarded Stage B checkout, with `python3 -B` and `PYTHONDONTWRITEBYTECODE=1`. It reads ten pinned source files and frozen D12 JSON as inert text, verifies 12 NOT_RUN future scenarios, 16 null permission prerequisites, rejects approval tampering in memory and does not execute PHP, SQL, network or other app tests.
