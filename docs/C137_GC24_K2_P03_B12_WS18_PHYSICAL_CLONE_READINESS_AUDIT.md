# C137 / GC24 / K2-P03 / B12 — WS18 physical-clone and repaired-race prerequisite audit

**Date: 2026-10-15. Classification: source-only readiness decision; not database, concurrency, D12 or release certification.**

## 1. Immutable starting point and no-execution boundary

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- WS18 parent, independently source-verified WS17 HEAD: `725803c2f66a627c01b7296bf1d92d283d690738`.
- WS18 branch: `c137-gc24-k2p03-b12-ws18-physical-clone-readiness-audit-source`.
- Protected original B12 source: `c137-gc24-k2p03-b12-cas-writer-cutover-source` at `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Protected production source: `v320-renee-agrisuite-branding` at `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- B12 fuse: `lib/daily_record_revision_cutover_gate.php`, blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`, literal `return false;`.
- Protected consolidated offline runner: `scripts/verify_k2p03_b12_ws07_offline.sh`, blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`.
- WS17 user-returned isolated Mac evidence: `RESULT=WS17_CORRECTED_SOURCE_VERIFIED`; WS17 source assertions 24 PASS, two OFF/ON response fixtures PASS, Ruminant DELETE source browser PASS, no DB, no production contact.
- WS18 examined pinned GitHub source, documentation and historical recorded results. **It did not execute** PHP, SQL, MariaDB, Docker, GitHub Actions, authenticated browser, deployment, migrations, capacity tests, Mac/cPanel/AWS access or backup restore.

## 2. What has actually been established

| Evidence | Supported conclusion | Critical limitation |
| --- | --- | --- |
| `database_schema.sql`, blob `e77f287d62c5812a3d55dc23b5b20f5d167db389` | Snapshot contains three nullable Daily Record `cycle_id` columns; no `record_revision`; existing nullable composite unique keys and `ON DELETE SET NULL` cycle FKs | Snapshot is not a physical production/clone schema attestation |
| Historical B12 run `38233373699`, attempt 1 | FAILED with `TWO_STALE_COMMITS` and two verified distinct MariaDB connections | One-use authorization consumed; never rewrite, rerun or relabel |
| Synthetic schema R2 `38301126240` | PASS for fabricated NULL-cycle duplicates, ambiguous same-date UPDATE scopes and cycle/FK hazards | No evidence of corresponding real tenant/customer rows |
| Fresh empty synthetic schema inventory `38451673014` | Executed 11 SELECT sections against new empty MariaDB schema | Returned zero FK metadata rows under restricted reader; empty data counts are not actual-data evidence |
| Synthetic FK DDL attestation `38454255713` | PASS for fixed-identifier read-only `SHOW CREATE TABLE` fallback when `REFERENTIAL_CONSTRAINTS` is hidden | Synthetic MariaDB 10.11 only, not actual clone |
| `docs/C137_GC24_K2_P03_B12_READONLY_SCHEMA_INVENTORY.sql`, blob `6dd5614edb2dae2432d9e8628b43ba53177e7133` | Eleven SELECT-only aggregate/schema sections are already prepared | Not authorized for protected/current database |
| `scripts/k2p03_b12_schema_readonly_gate.php`, blob `6ad60d311b98f85141d259142534fd1404907005` | Default `--lint-only` does not connect; `--run` has explicit opt-in checks, fixed localhost/name constraints, read-only FK DDL fallback | Environment flags and database names do **not** independently establish isolation, provenance, grant safety or approval |
| `tests/integration/k2p03_b12_repaired_rc01_real_mariadb.php`, blob `c30e772b3dc5a216c097fe579bc8c05beda70b7c` | Repaired RC01 harness draft exists with `B12_RC01_EXECUTION_ENABLED = false` | Not run; verifies only shared CAS authority, not RC02 downstream transaction |
| `docs/C137_GC24_K2_P03_B12_RECORD_REVISION_MIGRATION_DRAFT.sql.txt`, blob `bf72d8795d053f5a9dae6661280a161871131d5a` | Commented-out `BIGINT UNSIGNED NOT NULL DEFAULT 1` proposal for three tables | Not executable, approved or applied; `migrations/` ends at `091_permission_architecture_alignment.sql`, **no `092`** |

The user previously identified an artifact as a backup generated from the live Renee AgriSuite production database. **User-reported origin does not establish a physical clone, a safe restoration location, verified backup integrity, grants, isolated network or approved inspection of identifiable customer records.** Do not import or connect to that backup under this audit.

## 3. Current physical-clone and compatibility gate

**Disposition: BLOCKED — independently verified isolated physical clone provenance NOT ESTABLISHED in the available evidence; actual three-table schema and aggregate tenant counts UNKNOWN.**

The last reported Mac metadata-only historical clone discovery found no alternative local database-container candidates and `HISTORICAL_CLONE_PROVENANCE=NOT_ESTABLISHED`. This historical negative discovery is **not a current Docker inventory**, a statement that a clone cannot exist elsewhere, or authorization to inspect any host.

Before asking for any new `--run`, require an explicit one-use approval package identifying:

1. Exact disposal-eligible isolated MariaDB host/container and physical identity, **its origin and restore provenance**, version, database name, isolation boundaries and intended destruction/retention rules.
2. Backup provenance and SHA/checksum/chain of custody if real-derived; safeguard privacy, confidentiality, access and retention. Do not copy a live backup into CI, an untrusted personal container or a shared service merely to accelerate testing.
3. Independent proof that the target does not route to prod/staging, AWS Nodes A/B/C, protected C137 fixtures or other live systems; no app writers on the audited instance.
4. SELECT/metadata-only account and verified effective grants, secure credential file, no SQL/password in CLI arguments or copied output; approved local loopback access only. Environment flags alone are never proof of authorization.
5. Pin expected database identity, host, source SHA, eleven-section SQL SHA and runner SHA. Confirm the runner's `--run` restrictions are satisfied without modifying source or rerouting to another host.
6. Explicit approval for **aggregate-only** scan and output destination. Review result for identifiers/secrets before sharing. If any host/grant/schema/FK/trigger metadata or provenance guard is unverified, STOP.

No physical clone preparation or data access is implied by a general instruction to "go ahead" with readiness assessment.

## 4. Existing read-only inventory acceptance (once separately approved)

| Step | Evidence required | Failure/decision rule |
| --- | --- | --- |
| Identity | `01_DATABASE_IDENTITY`: physical server identity attested out-of-band, exact approved DB and MariaDB version | Wrong/unproven host or DB: STOP |
| Objects | `02_REQUIRED_TABLES`: Layer/Broiler/Ruminant + `production_cycles`, expected InnoDB types | Missing/mismatched object: STOP |
| Columns | `03_IDENTITY_COLUMNS`: row id, farm id, date, nullable cycle, Ruminant species, any existing revision columns and types | Unexpected revision shape: STOP |
| Indexes | `04_UNIQUE_AND_RELEVANT_INDEXES`: actual unique keys/order | Unknown constraint or incompatible key: STOP |
| Cycle FKs | `05_FOREIGN_KEY_DELETE_RULES` plus fixed-identifier `SHOW CREATE TABLE` fallback where metadata visibility restricts SELECT | Must establish exactly one expected SET NULL FK per table; empty section alone is not a PASS |
| Counts | `06_ROW_AND_CYCLE_COUNTS`: total / NULL-cycle / linked rows | No client identifiers; reconcile counts |
| Legacy duplicates | `07_NULL_CYCLE_DUPLICATES`: duplicate logical legacy groups/excess | Nonzero: domain adjudication; **never auto-deduplicate** |
| Same-day scope | `08_UNSCOPED_UPDATE_AMBIGUITY`: multi-row date(/species) update groups across all cycles | Nonzero may be legitimate multiple cycles, but old date-scoped writes cannot be treated as unique |
| Cycle/tenant integrity | `09_CYCLE_TENANT_INTEGRITY`: orphaned or cross-tenant cycle refs | Nonzero: STOP for investigation |
| Triggers | `10_TRIGGER_COUNTS`: source/cycle trigger counts and metadata visibility attestation | Unexpected triggers or inaccessible metadata require a separate writer audit |
| Species | `11_RUMINANT_SPECIES_DATA_SHAPE`: empty/normalized shape and distinct species | Blank/unexpected values: domain review |

Inventory is multiple independent SELECT statements, **not an atomic snapshot**. A quiescent isolated clone with no concurrent writers is required. The source-only audit must not infer zeros or schema absence from invisible metadata. Other off-host or dynamic writers cannot be excluded solely from these eleven outputs.

## 5. What is NOT yet authorized or solved

- `record_revision` migration has not been approved or run; DDL implicit commits mean three ALTERs cannot be rolled back as one transaction. Need precise preconditions, per-table postconditions, partial-apply recovery/roll-forward approval and existing-field compatibility tests.
- The legacy NULL uniqueness policy and cycle `ON DELETE SET NULL` domain rule remain unresolved. A proposed normalized tenant/date/cycle(/species) identity must not merge legitimate distinct-cycle records or break cycle deletion.
- Full coordinated W1–W7 writer behavior is prepared as dormant source, not physically exercised end to end. Cross-module stock, population, feed and Layer pooled egg-sales effects require atomic database certification.
- **RC01–RC14 repaired real-connection results: zero certified.** Independently verify two connection IDs, same previously loaded revision, bounded deterministic barrier, exactly one commit/one stale conflict, durable downstream rollback invariants and teardown on a newly authorized synthetic database only.
- `B12_RC01_EXECUTION_ENABLED=false` is a hard-disabled draft, not a ready-to-run test. Any change requires a fresh separately reviewed branch, approved fixture, source pins, host guard, timeouts and one-use permission.
- Authenticated browser/API checks, genuine HTML/JSON response parity, native-fallback rejection, permissions and end-to-end user draft survival remain unrun.
- D12's 15 import gates remain BLOCKED; Stage D UNAUTHORIZED; 150 VU HOLD; 200 VU PROHIBITED. Capacity testing is a separate pending workstream.

## 6. Decision and next controlled action

**WS18_READINESS_AUDIT=SOURCE_REVIEW_COMPLETE; PHYSICAL_CLONE=UNATTESTED; ACTUAL_SCHEMA=UNKNOWN; LEGACY_COUNTS=UNKNOWN; MIGRATION=UNAUTHORIZED; REPAIRED_RC01_RC14=0; B12_CUTOVER=HARD_OFF.**

The next low-risk operation may be a **separate exact-HEAD `--lint-only` source-contract check** of the existing runner in a network-disabled disposable PHP environment. It must make zero DB connections, may not use `--run`, and must not repeat closed WS17 QA. It can verify SQL read-only parsing and FK fail-closed fixtures; it cannot resolve physical clone provenance.

After that, obtain an independently established, isolated clone identity and a *fresh host-, database-, operation- and output-specific approval* before any read-only physical audit. Only then decide the migration/NULL-policy and the separately authorized repaired MariaDB race harness. Do not activate the gate or stage load testing.

## Primary pinned references

- `docs/C137_GC24_K2_P03_B12_DATABASE_EVIDENCE_GATE.md`
- `docs/C137_GC24_K2_P03_B12_LEGACY_KEY_SCHEMA_PREFLIGHT.md`
- `docs/C137_GC24_K2_P03_B12_SCHEMA_EVIDENCE_R2_SUCCESS.md`
- `docs/C137_GC24_K2_P03_B12_FK_READONLY_DDL_ATTESTATION_PASS.md`
- `docs/C137_GC24_K2_P03_B12_REPAIRED_CAS_CERTIFICATION_CONTRACT.md`
- `docs/C137_GC24_K2_P03_B12_WS12_SOURCE_READINESS_CERTIFICATION_GAP.md`
- `docs/C137_GC24_K2_P03_B12_WS16_INTEGRATION_CLOSURE_SOURCE_AUDIT.md`
- Actual current source and user-returned WS17 offline PASS, not inferred production behavior.
