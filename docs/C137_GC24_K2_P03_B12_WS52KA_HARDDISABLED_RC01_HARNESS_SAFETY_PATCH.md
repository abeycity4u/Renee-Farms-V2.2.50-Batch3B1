# C137 / GC24 / K2-P03 / B12 — WS52-KA hard-disabled RC01 source safety correction

**Date:** 2026-10-15. **Scope:** Explicitly authorized targeted source-only correction of the dormant repaired RC01 harness, plus source-only tests and a traceable Markdown report. **No MariaDB, Docker, SSH, backup, customer data, CI, migration, Mac runtime, cPanel/AWS/staging/production or PHP database execution.**

**Decision:** `WS52KA_SOURCE_PATCH=COMMITTED`; `SOURCE_STATIC_ASSERTIONS=19_OF_19_PASS`; `PHP82_LINT=NOT_EXECUTED`; `PYTHON_VERIFIER_IN_REPO=YES_NOT_CLAIMED_EXECUTED`; `INNODB_LOCK_RUNTIME_ORACLE=NOT_TESTED`; `REAL_RC01_CONCURRENCY_CERTIFIED=NO`; `B12_CUTOVER=HARD_OFF`.

## 1. Pinned lineage and change budget

| Item | Fixed identity |
|---|---|
| Repository | `abeycity4u/Renee-Farms-V2.2.50-Batch3B1` |
| Parent WS52-JZ evidence branch | `c137-gc24-k2p03-b12-ws52jz-rc01-source-safety-review` |
| Parent HEAD | `a025ee4b2af7305347e0b423583c5e0a68689b0a` |
| New WS52-KA source-only branch | `c137-gc24-k2p03-b12-ws52ka-rc01-harness-source-hardening` |
| Original protected B12 source branch | `c137-gc24-k2p03-b12-cas-writer-cutover-source` at `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af` |
| Protected production branch | `v320-renee-agrisuite-branding` at `10041da3a3c4e4a7b8101f8183c243d056c7b128` |
| Literal hard-OFF gate | `lib/daily_record_revision_cutover_gate.php` blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268` |
| Frozen real shared CAS authority | `lib/daily_record_revision_authority.php` blob `5fbb90903b0cc801be45c684f6c3db85c660979a` |
| Original dormant RC01 draft blob | `tests/integration/k2p03_b12_repaired_rc01_real_mariadb.php` blob `c30e772b3dc5a216c097fe579bc8c05beda70b7c` |
| Patched dormant RC01 draft blob | `tests/integration/k2p03_b12_repaired_rc01_real_mariadb.php` blob **`6f17d1a4ba7e98dbf286d1c8e1a147e6ac3e4aad`** |
| Added offline source verifier blob | `tests/test_b12_ws52ka_rc01_offline_contract.py` blob **`7ea282ae90d132eab5b1522b3f6e736a69949edd`** |
| Source-only WS52-JZ findings | `docs/C137_GC24_K2_P03_B12_WS52JZ_RC01_SOURCE_SAFETY_REVIEW.md` blob `790e46f439dd93f724e371d2d4ccbed60e552777` |
| Original B12 failed historic CI | `TWO_STALE_COMMITS`, one-use run `38233373699`, consumed authorization |

**Only three permitted changed files relative to the parent:** one **modified** dormant RC01 harness, one **added** Python fixture-free source-contract verifier, and this **added** Markdown evidence report. No workflow, source PHP service, production code, migration/DDL/schema, historical failed harness, deployment or release-gate file changed. The original B12 protected code HEAD did **not contain** this later-history RC01 draft; do not claim the patched harness is on the protected branch or deployed. The WS07 verifier is likewise in later evidence history only.

## 2. Targeted source corrections and remaining nuance

| WS52-JZ finding | WS52-KA correction | Limitation, to resolve before any approved runtime |
|---|---|---|
| F1: Only ten of 69 tables checked empty | Enumerate all **69** base-table objects from information_schema, strictly validate table identifiers and require zero rows in **every table**; fail on view, trigger, routine or event metadata; retain exact 886-column and three-revision-column/FK checks | This is an **unexecuted** preflight; future host identity, exact source schema/engine and database/data isolation must be independently proved before allowing any reads |
| F2: B_ATTEMPT marker/elapsed time does not prove actual lock wait | Both READY signals now include strictly validated numeric MariaDB connection IDs; independent controller PDO session polls MariaDB **10.11** `INNODB_LOCK_WAITS` joined with `INNODB_TRX` to demand B is waiting on A before release; exact recorded IDs checked against child results | Not yet independently tested on a real MariaDB 10.11. Metadata visibility/PROCESS privilege is necessary; if unavailable, **fail closed**, not accept wall-clock timing |
| F3: Error cleanup might overwrite root cause / leave fixture | Preserve first Throwable; give ABORT/worker stop/DB exact-ID cleanup and file cleanup independent catch boundaries; do **not** clean DB fixture while a worker is still running; report incomplete teardown and preserve private barrier artifacts for manual review when cleanup unverifiable | Source-only design cannot guarantee process kill, transaction rollback, retention, secure disposal or final cleanup on a real server. Any incomplete cleanup requires explicit guarded review, not blind rerun |
| F4: Worker/process exit race and missed resources | Track a created process immediately; bound TERM/KILL shutdown; inspect worker exit and preserve timeout/early failure classifications. New source-contract mutation-negative tests prevent regression of guard shape | Actual PHP **8.2** `proc_get_status` semantics, failure injection, wall-clock behavior and OS/process cleanup still need separately approved offline tests and later database-appropriate trials |
| F5: Harness source itself not pinned | Future `rc01_source_gate()` requires an externally supplied **exact Git-blob SHA** in `B12_RC01_APPROVED_HARNESS_BLOB` and rehashes its own file bytes before any connection; existing shared authority, schema and cutover hashes unchanged | Env flag and configured root password **do not prove physical Docker isolation**; root is only ever potentially tolerable in a separately approved new fabricated-only server. Need a host-side one-use identity/grant/secret/volume wrapper before future execution |
| F6: RC01 tests shared authority only | Preserve invocation of **unchanged** `daily_record_revision_write()` with preloaded original revision and canonical message; explicit hard fuse remains `false` and exit `90` | RC02–RC14 downstream save/delete/stock/feed/continuity/egg-sale and authenticated browser/CSRF/permissions remain untested |

**Engineering distinction:** The PHP runner source remains structurally suitable for *further review*, but is **NOT cleared for runtime**. Requiring `B12_RC01_APPROVED_HARNESS_BLOB` makes the future executing blob independently pinned; do not pass a guessed/partial SHA or silently change this guard to execute exploratory tests. A host-side wrapper must verify Docker Engine/volume/image/container identity, MariaDB 10.11 schema and fixture provenance before setting any attestation flag.

**Test code remains literal HARD-DISABLED:**

- `const B12_RC01_EXECUTION_ENABLED = false;` remains exactly present.
- Top-level `if (!B12_RC01_EXECUTION_ENABLED)` still exits **90** before controller/worker execution or database connection. No environment, CLI, or test-only bypass was introduced.
- Shared product CAS service unchanged; original failed workflow, legacy code, authorizations and product business rules unchanged.
- No implementation claims for the exact future lock-wait query beyond source review; metadata relation availability, permission, timing and version requirements are unresolved until separately approved runtime checks.

## 3. Source test evidence and honest limitations

**Executed:** An independent GitHub-fetched source contract scan in the assistant's isolated JS orchestration compared the remote RC01 harness and new verifier against their **exact returned Git blob SHAs**, asserted **15 affirmative source invariants and 4 mutation-negative probes**, including:

1. Literal fuse OFF and exit 90 **before** any top-level controller routing;
2. Existing cutover/source pin unchanged and actual shared CAS call preserved;
3. Enumerated whole-table zero-row checks and program-object absence guard;
4. InnoDB wait lookup and observer/worker real MariaDB session ID matching;
5. First-error persistence, process-stopping and diagnostic quarantine;
6. Git-blob-equivalent self-pin, correct NUL separator and PHP evidence newline literal;
7. Standalone source-only verifier presence, expected files and no ON switch;
8. Parenthesis/bracket/brace and quote/comment lexical balance of the fetched PHP source;
9–12. Four deliberately mutated source variants (fuse enabled, lock observer removed, cleanup-quarantine omitted, all-table row verification removed) each violated the corresponding independent invariant.

**Observed result:** `19/19 PASS`; fetched harness Git blob **`6f17d1a4ba7e98dbf286d1c8e1a147e6ac3e4aad`**, test blob **`7ea282ae90d132eab5b1522b3f6e736a69949edd`**; lexical delimiter balance PASS.

**Not executed:** PHP **8.2** `php -l` on exact changed harness, native PHP 8.2 hard-disabled exit-90 smoke, new Python `unittest` against checked-out branch, any PHP worker/`proc_open` failure simulation, MariaDB lock instrumentation, any DB schema preflight or transaction. Lexical balance and JS static checks **are not** a substitute for PHP 8.2 grammar lint or dynamic tests. Do not report `PHP82_LINT=PASS` or `RC01=PASS`.

**Committed repeatable fixture-free Python verifier:** `tests/test_b12_ws52ka_rc01_offline_contract.py` can be executed only in an independently attested offline checkout of this branch; it reads the RC01 harness as **source text only**, makes no connections and runs no PHP/Docker/SQL. Proposed approved next local syntax-only commands once the executor, PHP 8.2 CLI and branch/HEAD are independently guarded:

```bash
php -v                       # inspect only; require 8.2.x for formal contract
php -l tests/integration/k2p03_b12_repaired_rc01_real_mariadb.php
python3 -B -m unittest discover -s tests -p 'test_b12_ws52ka_rc01_offline_contract.py' -v
```

These are **instructions**, not evidence that they were executed. Do not execute the dormant RC01 runner in controller/worker mode, toggle its fuse, trigger the old one-use GitHub CI or query any database without a later specifically approved test scope.

## 4. Certification and safety blockers unchanged

- Original B12 GitHub Actions real MariaDB 10.11 race `TWO_STALE_COMMITS` **FAILED**. The corrected shared revision code is staged on a source branch and is not certified. `REPAIRED_RC01_RC14_CERTIFIED_PASSES=0`; no real concurrent session trial in WS52-KA.
- The repaired CAS certification contract requires independent **actual-schema/legacy NULL-cycle/cross-tenant** read-only isolated clone audit before *full* repaired certification, plus coherent three-table revision DDL/migration and synchronized seven-writer/browser activation; no waiver has been given. A fabricated-only RC01 first experiment would require an explicit **non-certifying** exception and a separate one-use exact host/database/operations/teardown approval.
- Historical production-derived backup remains sensitive; restore **NO-GO** due incomplete client-effective SQL classification, encryption/retention/headroom and provenance gates. No WS52-JT/JX parser success permits reading or importing the real backup.
- Schema revision migration `092` remains **NOT AUTHORIZED**. D12 fifteen import gates remain BLOCKED; Stage D UNAUTHORIZED; 150 VU HOLD; 200 VU PROHIBITED. B12 literal writer gate stays **HARD-OFF**.
- Any future physical database operation must guard Docker engine/container/volume/server/database identities, immutable protected original/production Git HEADs, permission boundaries, cleanup and explicit one-use consent. Never reuse protected WS32, old historical workflows or consumed B01–B11/B12 approvals.

## 5. Remote semantic diff and next safe action

**WS52-KA acceptance:** compare new source branch against parent `a025ee4b2af7305347e0b423583c5e0a68689b0a`. Expect exactly one modified dormant RC01 runner, one added fixture-free Python verifier and this one added Markdown report; no existing app service, test or workflow changes. Independently verify exact reviewed blobs and both protected original/production HEADs, literal hard-OFF gate, old historical B12 runner and frozen synthetic parser/test identities. Any remote divergence or unknown changed path => BLOCKED.

**Next recommended milestone WS52-KB (separately authorize):** independently verify exact committed harness syntax and its fixture-free verifier on a trusted offline environment with **PHP 8.2**. First check that the complete source/approval guards match, then `php -l` and a **hard-OFF only** exit-90 smoke with no DB/environment secrets. If any checker fails, apply a narrow **new source-only patch** and perform another remote semantic diff; never enable the fuse merely to run tests. Afterwards separately decide whether the previously required legacy-schema audit or a specifically non-certifying synthetic RC01-only fixture should be pursued. None is automatically approved here.

```text
AUDIT=B12_WS52KA_RC01_SOURCE_HARDENING
STATIC_SOURCE_CHECKS=19_OF_19_PASS
RUNNER_HARD_DISABLED=YES
SOURCE_PATCH=COMMITTED_TO_ISOLATED_BRANCH
PHP82_LINT=NOT_EXECUTED
OFFLINE_PYTHON_UNITTEST=NOT_EXECUTED
REAL_MARIADB_CONNECTIONS=0
SQL_EXECUTED=NO
DOCKER_OPERATIONS=NO
REAL_BACKUP_OPENED=NO
PRODUCTION_CONTACT=NO
HISTORIC_B12_CI=TWO_STALE_COMMITS_FAIL
REPAIRED_RC01_RC14_CERTIFIED_PASSES=0
RESTORE=NO_GO
MIGRATION_092=NOT_AUTHORIZED
B12_CUTOVER=HARD_OFF
```
