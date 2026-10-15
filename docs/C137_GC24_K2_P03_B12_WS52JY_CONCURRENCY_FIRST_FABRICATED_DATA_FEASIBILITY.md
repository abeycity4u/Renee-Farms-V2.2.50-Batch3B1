# C137 / GC24 / K2-P03 / B12 — WS52-JY fabricated-data concurrency-first feasibility decision

**Date:** 2026-10-15  
**Authorization:** The user explicitly said “proceed to your proposed next step,” referring only to a **bounded, design-only B12 concurrency-first feasibility assessment**, not a change to restoration acceptance criteria.  
**Result:** `DESIGN_REVIEW=COMPLETE`; `FABRICATED_DATA_RC01_PROBE=TECHNICALLY_PLAUSIBLE_BUT_NOT_AUTHORIZED`; `FULL_RC01_RC14_CERTIFICATION=BLOCKED`; `ACTUAL_LEGACY_SCHEMA_AUDIT=BLOCKED`; `HISTORICAL_BACKUP_RESTORE=NO_GO`; `B12_CUTOVER=HARD_OFF`.

No GitHub Actions execution, PHP/Python tests, SQL query, Docker action, Mac access, historic backup read/import, customer data handling, staging/production/AWS/cPanel contact, schema migration, application code change or writer-cutover action occurred in WS52-JY. **Only this Markdown evidence report** is added on a new GitHub documentation-only successor branch.

## 1. Pinned GitHub identity and source-derived references

| Item | Pinned GitHub reference / independently observed fact |
|---|---|
| Repo | `abeycity4u/Renee-Farms-V2.2.50-Batch3B1` |
| Immediate parent evidence branch | `c137-gc24-k2p03-b12-ws52jx-synthetic-client-frontend-feasibility-source` |
| Parent HEAD | `8018d40ec4a02dd2c42e2719e67668df95d22acf` |
| WS52-JY new documentation-only branch | `c137-gc24-k2p03-b12-ws52jy-concurrency-first-feasibility-source` |
| Protected original B12 source branch | `c137-gc24-k2p03-b12-cas-writer-cutover-source` |
| Original protected B12 HEAD | `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af` |
| Protected production branch | `v320-renee-agrisuite-branding` at `10041da3a3c4e4a7b8101f8183c243d056c7b128` |
| Literal hard-OFF writer gate (`lib/daily_record_revision_cutover_gate.php`) | Git blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268` |
| Repaired CAS certification contract (`docs/C137_GC24_K2_P03_B12_REPAIRED_CAS_CERTIFICATION_CONTRACT.md`) | Git blob `606c7a411f046aecd81e304e1d1341407068516c` |
| Historical B12 failure review (`docs/C137_GC24_K2_P03_CI_B12_FAILURE_REVIEW.md`) | Git blob `88e26c810b6e5c2b8d5984e8054ca87d2783ad37` |
| Repaired RC01 source-only review (`docs/C137_GC24_K2_P03_B12_RC01_SOURCE_ONLY_REVIEW.md`) | Git blob `b6c1652c5eb5b77c4402bcf138dab2bb484096ea` |
| Later-history dormant RC01 draft (`tests/integration/k2p03_b12_repaired_rc01_real_mariadb.php`) | Git blob `c30e772b3dc5a216c097fe579bc8c05beda70b7c` |
| Fresh-schema R2 synthetic evidence (`docs/C137_GC24_K2_P03_B12_SCHEMA_EVIDENCE_R2_SUCCESS.md`) | Git blob `25d69be7efb74b579023fef6d034811ce07e487a` |
| Synthetic schema cutover risks (`docs/C137_GC24_K2_P03_B12_SCHEMA_CUTOVER_DECISION.md`) | Git blob `5050088c67d5cba59819396080d49e75dadf31ca` |
| B12 old one-use MariaDB CI workflow (`.github/workflows/k2p03-mariadb-b12.yml`) | Git blob `7b4c810e40c963464f9c3a0e4f387ea43270341d` |
| Base application schema (`database_schema.sql`) | Git blob `e77f287d62c5812a3d55dc23b5b20f5d167db389` |
| Shared record revision authority (`lib/daily_record_revision_authority.php`) | Git blob `5fbb90903b0cc801be45c684f6c3db85c660979a` |
| Revision migration planner (`lib/daily_record_revision_schema_plan.php`) | Git blob `a0ddbec542e891d484b48d1adbdac17442eaadf8` |
| Later-history WS07 verifier | `scripts/verify_k2p03_b12_ws07_offline.sh` blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb` |
| Frozen WS52-JT SQL subset source | `tools/b12_ws52f_synthetic_allowlist.py` blob `c0cccf419ca78937c317f417c301fc51db002ef1` |
| Frozen WS52-JT policy tests | `tests/test_b12_ws52f_synthetic_allowlist.py` blob `bfc669f337bc39f2b5509d55e2ae2c1ad643986a` |
| Prior WS52-JX synthetic frontend | `tools/b12_ws52jx_synthetic_client_frontend.py` blob `652f43a0496682932daa54f157a0fb41d3723d26` |
| Prior WS52-JX frontend tests | `tests/test_b12_ws52jx_synthetic_client_frontend.py` blob `adece3714f7c9989cbfb15d245354e0c82ef22ff` |

**Important history/ref caveat:** The RC01 draft **exists in the later WS52-JX GitHub evidence lineage but was absent at the original protected B12 HEAD** when specifically queried; the source-only review originally described an uncommitted draft at preparation time. Its later presence does not make it deployed or enabled. Likewise, the WS07 verifier belongs to later evidence history, not the protected original code HEAD. Do not rewrite the protected source tree to make a wrong-ref assertion pass.

## 2. Central evidence and exact scope separation

**Original historic B12 failure:** Isolated GitHub-hosted real MariaDB 10.11 workflow run **38233373699**, attempt 1, failed on **2026-10-12** with `B12_CONCURRENCY_OUTCOME=TWO_STALE_COMMITS`. Two independent workers each loaded the same Layer Daily Record, one waited for the other's target-row lock, and **both stale edits committed**. Downstream ledgers reconciled, but there was no required stale-target rejection. The one-use approval is consumed; **do not rerun, relabel, or count its 52 preceding individual PASS lines as a certified B12 success**. B01–B11's previously certified 1,195 assertions remain closed, not retested.

**What is staged now:** The shared authority `daily_record_revision_write()` is source-reviewed to lock, compare original client-loaded record revision and perform conditional CAS in a caller-owned transaction. The draft new RC01 harness targets that **real shared service**, not the historic unversioned page SQL; however, it is **hard-disabled** by `const B12_RC01_EXECUTION_ENABLED = false;` and immediately exits 90 before connecting. It pins the schema plan, original schema and shared authority Git blobs, expects a **MariaDB 10.11.x** server, the fixed disposable synthetic database identity, **69 tables / 886 columns**, three `record_revision` columns and three original SET NULL cycle FKs. It is designed to use two actual independent PHP/PDO/MariaDB connection IDs, deterministic barriers and independent durable-state reads. It has **not** been authorized or executed, and its PHP 8.2 lint plus physical Mac/Docker identity wrapper are still pending. Only the RC01 authority path is covered, not full downstream RC02 effects or authenticated browser behavior.

**Crucial version distinction:** The historical B12 CI and new RC01 draft target **MariaDB 10.11**, whereas the unrelated WS52 SQL restore dialect oracles used **MariaDB 11.4**. Their server versions, schema assumptions and certifications are not interchangeable. Neither Docker fixture should be repurposed. A future concurrency fixture must match the exact separately approved B12 runtime and source/schema fingerprint.

**Previous schema evidence:** Independently disposable **synthetic** R2 schema evidence (workflow run **38301126240**) and separate FK DDL attestation (run **38454255713**) passed. The R2 fabricated dataset intentionally demonstrated NULL-cycle collisions, a possible cross-tenant cycle FK relation and an `ON DELETE SET NULL` collision. Those examples prove possible *schema hazards*, **not actual live legacy row counts**. The user's historical backup was later attested to derive from production and was lexically screened under **a different** scoped authorization, but no restored tenant schema/data has been safely validated for B12. Do not infer real data safety, transactional snapshot coherence or migration readiness from fabricated data.

**Current mandatory CAS contract:** `docs/C137_GC24_K2_P03_B12_REPAIRED_CAS_CERTIFICATION_CONTRACT.md` `3 requires, *before its repaired real-connection certification*, a fresh one-use test approval naming host/database/operations/teardown; independently verified disposable MariaDB identity; and **separate read-only isolated clone audit of actual schema/legacy NULL-cycle policies**. Its `7 names that audit as the next database-dependent prerequisite. WS52-JY **does not waive or silently re-order these existing acceptance gates**.

## 3. Decision — what fabricated data can and cannot establish

### Candidate track S: non-certifying fabricated-data concurrency proof

**Technical feasibility: CONDITIONAL YES**. With independent new scoped authorization, an exact source-pinned, isolated database containing only fabricated Layer/Broiler/Ruminant, cycle, stock, population and sales fixtures can expose real **InnoDB transaction locking and shared-service CAS behavior**. Pure PHP/Node mocks cannot establish this. The synthetic source schema can be guarded and a test-only three-column `record_revision` layout checked under a newly approved migration/recovery plan. A new runner must invoke actual B12 authoritative services without copying business logic into the test; workers must load identical original revision tokens before race release; a separate connection verifies the durable result and downstream records.

**Contractual feasibility: NOT AUTHORIZED YET**. Because the existing repaired CAS contract explicitly places a read-only actual legacy/schema clone audit before real-connection certification, any preliminary synthetic run **ahead of that audit** needs either (1) the specified prerequisite evidence under its own approval, or (2) the owner's **explicit, narrowly documented acceptance of a non-certifying preliminary synthetic proof that does not close any RC01–RC14 certified gate**. This design report does not propose treating generic “proceed” as such a contract change.

### Track C: complete B12 release certification / production readiness

**NO-GO / independent gates.** Full B12 requires:
- Correct RC01–RC14 shared-service behavior with independently isolated real MariaDB connections, specified by the contract, and any required related Browser/HTTP evidence with real authenticated test identities.
- Separate actual deployed-schema and legacy NULL-cycle duplicate/cross-farm/FK/trigger information from a **physically verified read-only isolated clone or an explicitly approved alternative equivalent data audit** before the actual-data-safe migration and NULL-cycle domain rules can be certified.
- A reviewed three-table revision migration/recovery plan recognizing MariaDB DDL implicit commits; no blind `scripts/run_migrations.php` against partial/unknown state and no unapproved `migrations/092_*.sql`.
- Entire seven-writer Layer/Broiler/Ruminant save, Poultry DELETE, privileged/delegated shared Ruminant DELETE, continuity and cycle-seed transaction coverage; same-farm/cycle/species CSRF/role, feed, stock, mortality/population and Layer pooled egg-sales effects; one coordinated, separately approved browser/route cutover.
- Independent stage/D12 import gates, deployment and rollback authorizations; no old workflow rerun and no source-only assertion relabeled as B12 runtime success.

**The actual historical production-derived database backup is not technically required for a *preliminary fabricated-data CAS race proof*, but it cannot be declared unnecessary for full B12 readiness under the current approved contract.** A future explicit acceptance change might permit a different independently verified *real data/schema compatibility* source, but mere synthetic fixtures cannot establish actual tenant legacy conditions. No change to the historical-backup restoration criteria is authorized in WS52-JY.

## 4. RC01–RC14 feasibility matrix, separating synthetic behavior from actual-data/HTTP obligations

| Contract ID | Synthetic-data concurrency feasibility | Still unresolved outside that synthetic test |
|---|---|---|
| **RC01** Layer EDIT A/B stale token race | **GOOD EARLY CANDIDATE.** Draft already exists, hard-disabled. One commit / one stale conflict, distinct connection IDs, revision exactly +1, loser no durable overwrite | Needs separately approved fixture, PHP 8.2 review, exact 10.11/version/schema and physical host guards; not tested; browser source-token loading separate |
| **RC02** Layer feed, mortality, later continuity, pooled egg sales | Feasible with fabricated inventory/egg sales and actual shared save pipeline; assert no loser ledger effects | Staged pipeline must be invoked unchanged; real data reconciliation and browser not proven |
| **RC03** Broiler stale EDIT/EDIT, stock/mortality | Feasible with fabricated Broiler and item/stock fixtures | Full save pipeline/permissions and actual legacy schema remain separate |
| **RC04** Ruminant stale EDIT/EDIT per cycle/date/species | Feasible with fabricated species/group/tag fixtures | Real tenant/species normalization and delegated UI policy remain separate |
| **RC05** Cross-tenant/cycle/species scope isolation | Feasible with two synthetic farms/cycles and negative source-owner probes | Real deployed FK/integrity scope not established |
| **RC06** Same-scope CREATE/CREATE | Non-NULL cycle case feasible with source parent-lock/unique-constraint tests | **Legacy NULL-cycle duplicate counts, normalized uniqueness and cycle-delete SET NULL policy require real schema/data evidence** |
| **RC07** EDIT/DELETE and duplicate DELETE race | DB/service side feasible with fabricated records and unchanged shared DELETE path | Original **browser-loaded** tokens and HTTP/CSRF behavior need authenticated integration |
| **RC08** Farm Admin versus delegated Ruminant Manager DELETE | Shared-pipeline synthetic fixture feasible with test roles, without bypassing canonical authorization | Actual authenticated role/CSRF route checks remain independent; do not introduce test-only privilege bypass |
| **RC09** Earlier-date change vs later-row editor | Feasible with fabricated daily timelines and exact later revision/reconciliation assertions | Real existing source rows and historical continuity are not verified |
| **RC10** Concurrent cycle creation and opening seed | Feasible with fabricated cycle/baseline data, same caller transaction and retry | Production creation/rollback route wiring and lifecycle decisions remain independent |
| **RC11** Failure after CAS/between downstream services | Feasible with controlled synthetic failure injection and complete rollback snapshots | Ensure failure injection does not change production shared service semantics; real-stock/accounting data effects unproven |
| **RC12** Stock allocation versus projection lock ordering | Feasible with isolated simultaneous MariaDB workers and real shared services | Deadlock/timeout must be **FAIL**, never counted as stale conflict; broad cross-module performance not certified |
| **RC13** Ambiguous NULL-cycle duplicates / cross-farm cycle links | Synthetic *reject/negative* fixture viable; R2 has already demonstrated possible hazards | **Actual deployed legacy collision/linkage state and domain migration policy remain UNKNOWN** |
| **RC14** No-op EDIT, max revision and invalid animal type/column | Feasible with synthetic exact-boundary rows | PHP/MariaDB version/cast behavior and exact client policies must be qualified |

**No RC case was executed or certified in WS52-JY.** Even if selected synthetic MariaDB cases eventually pass, do not globally mark all RC01–RC14 completed or count test assertions as proof of the authenticated UI or legacy-data schema compatibility.

## 5. Proposed safe, smallest next actions (NOT approved for execution by this review)

**Recommendation: source-first RC01 preparation, not immediate database execution.**

1. **Propose WS52-JZ or equivalent — docs/source-only independent RC01 harness safety review:** At the new WS52-JY source checkpoint, inspect the *already present* later-history hard-disabled RC01 draft. Check source/service blob pins, exact original code refs, deterministic two-worker barrier, no raw error/customer-data leak, partial fixture creation and cleanup on every failure, PHP 8.2 compatibility, race/timeout semantics, and no hidden CI trigger. The historical test and literal OFF gate remain untouched. A source-only design review/optional isolated `php -l` could be separately authorized without any MariaDB call or fuse change. If a narrow source edit is demonstrably needed, request separate scope and use a new guarded branch plus semantic diff.
2. **Resolve the contract sequencing question explicitly before any synthetic runtime:** Either first complete the originally required sensitive **read-only, independently isolated legacy clone audit** under its own approval, **or** obtain fresh explicit user approval for an *early non-certifying fabricated-data RC01 probe only* before that audit, clearly preserving every real-legacy/migration/cutover gate as BLOCKED. WS52-JY does **not** change the current contract.
3. **Only then**, after a separate one-use approval naming exact source commit and test-only database/container identity and disposal policy, create/use a **brand-new** independently guarded network-disabled MariaDB **10.11** schema with no imported customer data and pin 69 tables/886 columns, exact revision schema, FKs and zero pre-existing business rows. Do **not** use protected WS32 or prior synthetic/restoration fixtures; never assume a DB name implies isolation.
4. Prepare the synthetic fixture and run two independently connected actual MariaDB workers invoking the shared CAS authority, both with the preloaded original revision before barrier. A must commit; B must report the *exact* expected stale CAS conflict. Independently verify revision +1, winner-only business values, no loser durable effects, distinct MariaDB connection IDs and measured blocking interval. Unknown errors, deadlocks and lock timeouts are **FAIL**. Abort/teardown must be scoped, identity-verified and reported; synthetic fixture cleanup not assumed.
5. Proceed to RC02–RC14 incrementally only after RC01 evidence review and separate approvals, using shared pipeline/service integration and targeted evidence rather than duplicating SQL/business logic. Separately qualify authenticated HTTP/browser flows and actual-data-safe schema/migration. Do not rerun original B12 CI workflow `38233373699` or its consumed approval file.

**Potential faster critical-path fork:** If the user wishes to prioritize *B12 CAS proof* over *historical customer-backup restore*, ask for a separately scoped acceptance-contract decision; record what is only preliminary and what full deployment still requires. That request must not silently discard actual schema and legacy collision audit. Restoring the user's old production-derived database remains an independent **NO-GO** workstream because complete SQL/client-effective safety validation, confidential handling, storage and provenance are unproven.

## 6. One-fix-fit-it-all safety and release locks

The seven live writers, shared revision/save/delete/continuity/cycle seeding and all stock/population/feed/sales effects must follow shared canonical services. Keep route handlers thin; never patch Layer only, bypass permissions, duplicate SQL/validation or let a later revision overwrite an earlier writer silently. A future source/fixture change requires focused tests, unchanged protected branches, an independent GitHub remote semantic diff, pinned exact input/source versions and guarded cleanup. A GitHub-only design review must **not** claim a Mac Docker/runtime identity preflight or locally executed concurrent test.

```text
WS52JY_DESIGN_FEASIBILITY=COMPLETE
FABRICATED_RC01_PROBE_EXECUTED=NO
REPAIRED_RC01_RC14_CERTIFIED_PASSES=0
HISTORIC_B12_CI=TWO_STALE_COMMITS_FAIL
LEGACY_NULL_CYCLE_AND_REAL_SCHEMA_AUDIT=UNVERIFIED
HISTORICAL_BACKUP_RESTORE=NO_GO
MIGRATION_092=NOT_AUTHORIZED
B12_CUTOVER=HARD_OFF
D12_IMPORT_GATES=15_BLOCKED
STAGE_D=UNAUTHORIZED
150_VU=HOLD
200_VU=PROHIBITED
PRODUCTION_CONTACT=NO
```

**GitHub evidence acceptance:** One new Markdown file `docs/C137_GC24_K2_P03_B12_WS52JY_CONCURRENCY_FIRST_FABRICATED_DATA_FEASIBILITY.md` on the separately named WS52-JY docs-only branch, based on parent WS52-JX SHA `8018d40ec4a02dd2c42e2719e67668df95d22acf`. Independent remote compare must show `ahead=1`, `behind=0`, **exactly this added document**, and unchanged original B12/production HEADs, hard-OFF gate, later-history WS07 verifier, frozen WS52-JT source/test and existing WS52-JX frontend/test blobs. No customer SQL or data, account secret, exact local Mac path, host credential, actual DB query, version switch or source patch may be introduced.

**Decision:** Preliminary real-MariaDB CAS correctness with fabricated data is a viable *separately approved test strategy*, not an approved replacement for a real historical/legacy-schema audit. WS52-JY documents this fork; it does not execute it.
