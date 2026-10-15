# C137 / GC24 / K2-P03 / B12 — WS52-JX synthetic-only client-aware frontend feasibility

**Date:** 2026-10-15  
**Authorization:** User explicitly approved bounded WS52-JX implementation/testing of a **fabricated-SQL-only** frontend on a new isolated GitHub branch, without Docker, MariaDB/database SQL, real backup inspection, customer data, Mac/protected runtime/production contact, application code changes, migrations or cutover.  
**Disposition:** `WS52JX_SYNTHETIC_FRONTEND_TESTS_PASS`; `JX_CLIENT_AWARE_PREFLIGHT_FEASIBILITY=PARTIAL_DEMONSTRATION`; `FULL_CLIENT_EFFECTIVE_SQL_PARSER=NOT_PROVEN`; `ACTUAL_BACKUP_MANIFEST=NOT_GENERATED`; `WS53_IMPORT=NO_GO`; `B12_CUTOVER=HARD_OFF`.

## 1. Pinned lineage and non-mutation

| Reference | Verified value |
|---|---|
| Repository | `abeycity4u/Renee-Farms-V2.2.50-Batch3B1` |
| Parent WS52-JW evidence branch | `c137-gc24-k2p03-b12-ws52jw-full-dump-validation-feasibility-source` |
| Parent HEAD | `bd33372690d841c255ea8cecdf40d1b122ecc97e` |
| New isolated WS52-JX branch | `c137-gc24-k2p03-b12-ws52jx-synthetic-client-frontend-feasibility-source` |
| Protected original B12 branch | `c137-gc24-k2p03-b12-cas-writer-cutover-source` HEAD `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af` |
| Protected production branch | `v320-renee-agrisuite-branding` HEAD `10041da3a3c4e4a7b8101f8183c243d056c7b128` |
| Literal B12 HARD-OFF gate, `lib/daily_record_revision_cutover_gate.php` | Git blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268` |
| Historical WS07 verifier in **later evidence history** only, `scripts/verify_k2p03_b12_ws07_offline.sh` | Git blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb` |
| Frozen WS52-JT policy classifier, `tools/b12_ws52f_synthetic_allowlist.py` | Git blob `c0cccf419ca78937c317f417c301fc51db002ef1` |
| Frozen WS52-JT policy tests, `tests/test_b12_ws52f_synthetic_allowlist.py` | Git blob `bfc669f337bc39f2b5509d55e2ae2c1ad643986a` |

The historical WS07 runner is **not present at the protected original B12 code HEAD**; it is correctly verified in later evidence history. WS52-JX adds only one new prototype source file, one new fabricated-corpus test file, and this Markdown evidence file to its isolated successor branch. No protected code, existing synthetic tool/test, migration or production file has been edited.

## 2. Deliverables and design boundaries

1. `tools/b12_ws52jx_synthetic_client_frontend.py` — exact independently local-tested and GitHub-retrieved blob **`652f43a0496682932daa54f157a0fb41d3723d26`**. A pure Python 3 stdlib scanner plus **delegation** to the frozen WS52-JT policy classifier, not a second SQL authorization policy. Its CLI accepts **only `--self-test`** and contains no SQL-file path/stdin input feature. It performs **no SQL execution, DB client call, Docker action, import or network operation**. Pure `classify_fabricated_script(str)` exists for tests with intentionally hardcoded fabricated inputs only.
2. `tests/test_b12_ws52jx_synthetic_client_frontend.py` — exact independently local-tested and GitHub-retrieved blob **`adece3714f7c9989cbfb15d245354e0c82ef22ff`**. Deterministic unittest suite of fabricated SQL/client-command shapes only; contains no customer records and no original dump read.
3. This report is documentation-only evidence for **tested narrow subset behavior** and the unresolved feasibility gates.

**Frontend subset demonstrated:** bounded ASCII input (maximum **32768 bytes**, **64 approved statements**, **128 comments**); monotonic whole-input character traversal; single-quoted and backtick-quoted token handling; line and ordinary block comments; separate treatment of a single exactly known MariaDB sandbox marker at the front of the synthetic input; deliberately restricted MySQL `/*!##### ... */` and MariaDB `/*M!###### ... */` conditional comments, distinguishing selected active and skipped cases at a pinned conceptual **MariaDB 11.4** version. It explicitly **rejects unknown modes, unsupported client/script commands and inline executable-comment concatenation**, and sends recognized effective single statements into the **unchanged WS52-JT classifier** (rather than duplicating the statement policy). A script containing an unsupported later statement is rejected by the pure preflight; it has **no execution path to run the earlier accepted prefix**.

**Important:** “client-aware” here means recognizing/rejecting **selected hazardous client-script shapes**. This is **not actual MariaDB client interpretation**, and no real MariaDB 11.4 oracle was executed in WS52-JX. The parser deliberately handles a very small grammar and only an approximation of active-comment version semantics. A preflight decision does **not** confer import or operational SQL authorization.

## 3. Exact test results and reproducibility

The assistant's isolated `/mnt/data/ws52jx` Python environment used a **verified frozen WS52-JT classifier** copy: `git hash-object` returned `c0cccf419ca78937c317f417c301fc51db002ef1` (the same GitHub blob). No real backup, source database, credentials, other application fixture, user Mac or network was accessed.

**Locally executed commands, from the isolated directory:**

```bash
git hash-object tools/b12_ws52jx_synthetic_client_frontend.py tests/test_b12_ws52jx_synthetic_client_frontend.py tools/b12_ws52f_synthetic_allowlist.py
python3 -B tools/b12_ws52jx_synthetic_client_frontend.py --self-test
python3 -B -m unittest discover -s tests -p test_b12_ws52jx_synthetic_client_frontend.py -v
```

**Returned code blobs:** `652f43a0496682932daa54f157a0fb41d3723d26` / `adece3714f7c9989cbfb15d245354e0c82ef22ff` / frozen `c0cccf419ca78937c317f417c301fc51db002ef1`. The GitHub server independently returned identical new source/test blob identifiers after commit.

**Smoke:** `RESULT=PASS`, `REAL_BACKUP_READ=NO`, `SQL_EXECUTED=NO`, `MARIADB_CLIENT_GRAMMAR_CERTIFIED=NO`, `B12_CUTOVER=OFF`.  
**Unittest:** five test methods, **5 PASS / 0 FAIL / 0 ERRORS**, approximately 0.014 seconds (a second bounded run also PASS). Cases:
- **30 selected expected-approved fabricated scripts** (including several active/ignored guard and harmless comment variants);
- **63 expected-rejected fabricated scripts** (including `source`, `\.`, shell/client escapes, `DELIMITER`, qualified tables, schema switches, privileges, file access, executable-comment smuggling, malformed/truncated input and later disallowed operations);
- **6 input type/size rejection cases**;
- one deterministic redacted comment-count and sandbox-header behavior test;
- one static CLI absence-of-input-options test.

These are synthetic **policy tests, not 101 independent MariaDB 11.4 oracle tests**. The tests themselves share assumptions with the prototype and have not established dialect-complete reference behavior. The new code, not a live SQL server, assigned statement categories.

## 4. WS52-JW JX1–JX9 independently scoped acceptance review

| WS52-JW gate | What WS52-JX objectively established | Status |
|---|---|---|
| JX1 pinned frontend provenance | New reproducible Git blobs plus frozen exact WS52-JT dependency hash, stdlib-only | **PASS limited provenance** |
| JX2 *total* input byte/client directive accounting | A bounded scanner advances monotonically, reports total input length and selected comment/statement counts | **PARTIAL**: input-length reporting is not a formal independently verified per-byte effective/client-token coverage proof |
| JX3 client/server version and SQL-mode behavior | Hardcoded conceptual MariaDB 11.4 guard interpretation; unsupported SQL modes rejected by existing policy; a single recognized sandbox marker | **PARTIAL / NOT CLIENT ORACLE CERTIFIED** |
| JX4 reject synthetic forbidden operations | 63 forbidden/malformed fixture cases rejected via the frontend and frozen policy | **PASS for this corpus only** |
| JX5 actual MariaDB 11.4 client/server effect comparison | No SQL, Docker or MariaDB call authorized/performed | **NOT TESTED / BLOCKED** |
| JX6 no partial execution on later deny | Pure evaluator has no executor/client/streaming method; selected allowed-prefix + forbidden-suffix cases reject | **PASS for offline prototype only**; future importer preflight/TOCTOU barrier not implemented |
| JX7 redaction and data safety | Consistent static rejection reason and categorical counts/summary; no raw SQL printed by ordinary success/reject path; no path/stdin CLI | **PASS for selected synthetic paths; no comprehensive leak assessment** |
| JX8 reproducible independent synthetic audit | Exact code/test blob hashes and five locally passing methods; GitHub blob matching | **PASS for bounded tests; independent grammar reference absent** |
| JX9 realistic whole-dump viability | Script maximum 32768 bytes; historic dump was 619163 bytes with 364 versioned comments and SQL forms beyond frozen policy | **NOT PROVEN / BLOCKED** |

**Manifest caution:** the prototype's `policy_digest` is a digest of **redacted aggregate categories and counts**, **not** a collision-resistant binding to the actual SQL input bytes; different SQL scripts with the same classification summary can intentionally produce the same digest. Its `bytes_accounted` field reports the input string length, not a formally audited per-byte provenance map. **Neither metric may be promoted to an actual-backup completeness certificate**. These are explicitly tracked engineering gaps, not permissions to access a live dump.

**Safety/compatibility caution:** Selected MySQL 5.7+ and MariaDB future-comment ignoring, sandbox-directive acceptance and SQL_MODE behavior are inferred/restricted in code, **not** independently demonstrated against the actual MariaDB client. Client metacommands have special parsing rules not fully modeled here. Unknown/unsupported constructs are intended to fail closed; complete language coverage, absence of false accepts and true nonexecution of a shell/client escape remain **UNPROVEN** outside the hardcoded synthetic corpus.

## 5. Design feasibility verdict and stopping rule

**This proves that a separately isolated, policy-delegating, redacted synthetic frontend can be implemented and tested. It does not prove that Route A in WS52-JW is ready for an actual MariaDB 11.4 dump.** There is currently **no** independently reviewed complete MariaDB *client-effective* validator, no complete grammar source, no authorized real-dump manifest, and no fully demonstrated sandbox/client metacommand constraints. Treat the full-dump design path as **BLOCKED** until a substantially stronger and independently audited client+server interpretation method becomes available.

Do **not** continue growing WS52-JX or WS52-JT with arbitrary positive examples until it appears to “work on the dump.” More synthetic fixtures can only certify the tested cases; a finite fabricated corpus does not establish all client/dialect effects. The next substantive decision is whether to acquire/build a **version-pinned independently referenceable MariaDB client-aware grammar/toolchain**, or to reevaluate the requirement for processing historical production-derived data at all.

**Possible next distinct decisions, each requiring explicit permission:**

1. **WS52-JY (recommended decision-only):** architecture feasibility decision based on JX1–JX9, with a *concrete external/toolchain availability check only if approved and available*. If no verifiable complete client-aware parser/typed alternative is feasible, preserve a hard **NO-GO**, do not ask for sensitive dump read. Do not use the two toy tools as a substitute.
2. **B12 concurrency-first option:** with separately authorized test planning and new, protected-resource-safe synthetic MariaDB trials, pursue corrected RC01–RC14 concurrency certification on fabricated data without exposing the real backup, if the project's acceptance contract permits this independent track. This may reduce unnecessary privacy risk but **does not automatically waive** the historical-backup rehearsal requirement.
3. **Host confidentiality/capacity metadata-only readiness:** if separately authorized, verify disk/Docker encryption, snapshots, retention and current host/guest capacity and credible expansion bounds **without opening the original backup**. That is an independent P0 gate, not an SQL parser workaround.
4. Only after a genuine client-effective source validation barrier, independent importer-grant containment, confidentiality and capacity gates independently pass may a future user authorize a tightly bounded **read-only source inspection**, then distinct real-data fixture provisioning, then specifically consented **WS53** SQL/data import and **WS54** postrestore/disposal. A future importer must never execute an allowed prefix before complete independent preflight.

**Separate restored-data and cutover blockers:** The user-attested original backup file was last screened under earlier explicit WS51S authorization: size 619163, mode 0600, SHA256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`, reported **69 DROP TABLE** statements. It was **NOT opened or rehashed in WS52-JX**. Export transactional consistency, exact effective target constraints, expanded import storage, Docker/host at-rest encryption, backup/snapshot retention, strong restricted real importer, secure disposal and rollback remain **BLOCKED**. The prior actual B12 concurrency run failed `TWO_STALE_COMMITS`; corrected real MariaDB RC01–RC14 certified passes remain **0**.

## 6. Release locks and independent GitHub comparison contract

```text
B12_CUTOVER=HARD_OFF
MIGRATION_092=NOT_AUTHORIZED
D12_IMPORT_GATES=15_BLOCKED
STAGE_D=UNAUTHORIZED
150_VU=HOLD
200_VU=PROHIBITED
WS53_REAL_BACKUP_IMPORT=NOT_AUTHORIZED
WS54_REAL_DATA_VALIDATION_DISPOSAL=NOT_AUTHORIZED
REAL_BACKUP_READ_IN_WS52JX=NO
PRODUCTION_CONTACT=NO
```

WS52-JX must compare against the exact parent WS52-JW commit `bd33372690d841c255ea8cecdf40d1b122ecc97e` and show **only three new files**: `tools/b12_ws52jx_synthetic_client_frontend.py`, `tests/test_b12_ws52jx_synthetic_client_frontend.py` and **this Markdown evidence file** `docs/C137_GC24_K2_P03_B12_WS52JX_SYNTHETIC_CLIENT_FRONTEND_FEASIBILITY.md`. Expect three forward commits, zero behind, no edits to existing code/tool/test/migration or protected branches, and exact checked source/test blobs. Independently re-fetch both protected branch HEADs, hard-OFF gate, later-history WS07 and frozen WS52-JT hashes. No real-data or local Mac/Docker identity is attested by this GitHub-only step.

**Final verdict:** `WS52JX_BOUNDED_SYNTHETIC_PROOF=PASS`, `WS52JX_CLIENT_EFFECTIVE_DUMP_FEASIBILITY=NOT_PROVEN`, `REAL_RESTORE=NO_GO`, `B12_WRITER_CUTOVER=HARD_OFF`.
