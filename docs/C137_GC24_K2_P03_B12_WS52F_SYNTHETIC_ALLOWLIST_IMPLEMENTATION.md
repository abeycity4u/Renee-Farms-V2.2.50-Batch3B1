# C137 / GC24 / K2-P03 / B12 — WS52-F reproducible synthetic-only SQL allowlist prototype

**Date:** 2026-10-15. **Outcome:** `WS52F_SYNTHETIC_TOOL_IMPLEMENTED_AND_CORPUS_PASS`, **NOT** a complete MariaDB 11.4 parser, actual-backup compatibility certificate, import allowlist, production SQL safety attestation, deployment permission or release approval. **B12 CAS writer cutover remains HARD-OFF.**

## Scope / explicit authorization

The user approved implementing a **standalone, independently testable synthetic-only SQL validation tool**, with no production-derived backup access, no Docker/SQL/database provisioning, no production contact and no changes to protected original/production code. This newly named WS52-F feature branch was created from the previously verified WS52-E documentation evidence source; it is **not** a merge to any production/source baseline. Unlike prior documentation-only steps, WS52-F explicitly authorizes new standalone tool and test source files on the isolated evidence branch.

### Immutable checkpoints

| Item | Value |
|---|---|
| Repository | `abeycity4u/Renee-Farms-V2.2.50-Batch3B1` |
| WS52-E parent docs branch | `c137-gc24-k2p03-b12-ws52e-import-readiness-gap-matrix-source` |
| Parent HEAD | `f917c0ab4271b9a313899b3e1a63b81ddfb7b19a` |
| New WS52-F isolated implementation branch | `c137-gc24-k2p03-b12-ws52f-synthetic-allowlist-tool-source` |
| Protected original B12 branch | `c137-gc24-k2p03-b12-cas-writer-cutover-source` |
| Original B12 HEAD | `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af` |
| Protected production branch | `v320-renee-agrisuite-branding` |
| Production HEAD | `10041da3a3c4e4a7b8101f8183c243d056c7b128` |
| B12 HARD-OFF gate file | `lib/daily_record_revision_cutover_gate.php`, Git blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268` |
| Later WS07 historical verifier | `scripts/verify_k2p03_b12_ws07_offline.sh`, Git blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb` |

WS07 verifier is **absent in original B12 HEAD** but present in later evidence history. No attempt was made to alter the original checkout or reinterpret it.

## Delivered executable test artifacts — synthetic only

1. `tools/b12_ws52f_synthetic_allowlist.py` — **standalone Python 3.10+ stdlib-only fail-closed classifier**. Exact tested Git blob `83fb7ffa9a25421a2badc479f2366fd8c53a4191`. The CLI accepts **only `--self-test`**, which runs two fixed embedded synthetic cases. It has no `--file`, SQL dump pathname, stdin, network, MariaDB client, Docker or restore operation. Python module function `classify_synthetic(str)` exists only for explicit fabricated test fixtures; it must **not** be called on the user backup in this stage.
2. `tests/test_b12_ws52f_synthetic_allowlist.py` — separate, deterministic Python `unittest` suite. Exact tested Git blob `9ad69c5629765138ab441686065d77c39ceaf91c`; **30 expected-allow, 67 expected-reject**, plus six input-contract invalid/oversized variants.
3. `docs/C137_GC24_K2_P03_B12_WS52F_SYNTHETIC_ALLOWLIST_IMPLEMENTATION.md` — this bounded-scope implementation evidence.

**Approved review command, synthetic fixtures only, from an isolated checkout of WS52-F branch** (NOT original protected code checkout):

```bash
python3 -B tools/b12_ws52f_synthetic_allowlist.py --self-test
python3 -B -m unittest discover -s tests -p test_b12_ws52f_synthetic_allowlist.py -v
```

Do **not** pass a real SQL dump, copy the user's backup into this branch, or feed SQL to a database. The unit tests read **only the Python tool module and hardcoded fabricated fixture strings**.

## Precisely tested behavior

- **Allow subset:** small unqualified `CREATE TABLE ... (INT/VARCHAR)`, `DROP TABLE [IF EXISTS]`, `INSERT INTO ... VALUES` with narrowly defined literals, `ALTER TABLE ... ENABLE/DISABLE KEYS`, single `LOCK TABLES ... READ/WRITE`, `UNLOCK TABLES`, and `SET SESSION sql_mode` restricted to empty or `STRICT_ALL_TABLES`. Explicit semicolon terminators and a strict statement/token/input size budget are required.
- **Lexer handling:** single-quoted strings, doubled single quotes, limited default-mode backslash escapes; simple backtick identifiers, comments, a single exact MariaDB sandbox directive at document start, and a deliberately limited family of active versioned SQL comments. Ambiguous version ranges or unsupported comments are rejected rather than silently skipped. Statements are classified, **never executed**.
- **Fail-closed rejects:** qualified `schema.table`, `USE`, database and account DDL, privilege/global changes, client `source`/`DELIMITER`/`\\.`/`\\!`, file/OUTFILE/LOAD operations, procedures/triggers/views/dynamic SQL, unknown quote/mode/version semantics, broken/nested comments, unrecognized commands, malformed SQL, additional trailing tokens, no semicolon, control/non-ASCII input, overlong input and inputs not typed as strings.
- **No data or credentials:** user-reported original production-derived `~/Downloads/database.sql` **not opened, hashed, copied, parsed, uploaded or imported** in WS52-F. MariaDB server **not contacted**. No user Mac, Docker resources, WS32, two protected older containers, volumes, production, staging, AWS or cPanel were contacted.
- **Output safety:** `Rejected` carries only a constant code and does not echo SQL, SQL identifiers, user values, secrets or file paths.

### Run evidence in isolated assistant Python environment

The exact tool and independent test-source byte sequences were written and executed **only in the assistant's isolated Python test sandbox** with hardcoded synthetic SQL. `unittest` reported three passing test methods: `test_approved_subset` (30 fixture subcases), `test_rejected_cases` (67 fixture subcases) and `test_fail_closed_input_contract` (six invalid inputs), with **zero assertion failures**. The tool's embedded `--self-test` returned `RESULT=PASS`, `REAL_BACKUP_ACCESS=NO`, `SQL_EXECUTED=NO`, `MARIADB_GRAMMAR_CERTIFIED=NO`, `B12_CUTOVER=OFF`. A Python environment startup emitted unrelated spreadsheet-package diagnostic output; the WS52-F `unittest` runner nevertheless returned **exit code 0** and all three methods **OK**. This environment did not connect to an actual MariaDB server.

**Important distinctions:** The 97 fixtures count per-case synthetic allow/reject expectations; the 6 input-contract variants are additional. The negative examples are expected policy rejections, **not** MariaDB error-code tests. Both fixtures and classifier are authored in the same stage, so acceptance expectations are *not independently certified* as matching MariaDB's full grammar. Source and test blobs were read back from GitHub and matched **exactly the locally tested bytes**, which makes this prototype auditable/reproducible but **not fully grammar-authoritative**.

## Limitations and security boundary

**Strict NO-GO for actual import remains.** The classifier intentionally refuses many valid SQL constructs and is not designed to process the live-production-derived backup. It does not support full MariaDB datatypes/constraints/indexes, complete MySQL/MariaDB executable comments, changing SQL modes, all quoted identifier cases, real client directive parsing, complex DDL, triggers, stored routines, collation, charset or server-side full syntax rules. It may classify a limited syntactic subset that MariaDB itself rejects or interprets differently. Static classification is not a server-backed execution or privilege-sandbox guarantee. Merely recognizing `DROP TABLE` is not proof that the target schema is empty or safe.

Other independent blockers remain:
- WS51S's 69 historical `DROP TABLE`, 69 `CREATE TABLE`, 65 `INSERT`, 364 versioned comments and zero heuristic flags **do not** amount to a complete effective SQL/client-command allowlist.
- WS52-A's five denied non-target synthetic privilege operations, and WS52-D's 12 positive + 6 negative actual MariaDB 11.4 synthetic tests, establish only their specifically tested behaviors; they do not certify this entire classifier against a MariaDB 11.4 oracle.
- WS52-D measured synthetic guest `212.98 GiB` free and `15,252,481` free inodes, with Mac host physical `166.80 GiB` free before test, **not additive** and not sufficient to infer expanded import size. That disposable instance was removed.
- Real data at-rest encryption/backups/retention and authenticated confidential import-account lifecycle; secure key destruction/erasure; full-dump expanded tables/indexes/temp/log headroom; export snapshot consistency; real import compatibility and rollback/disposal **remain unresolved**.
- Real B12 repaired RC01–RC14 certified passes **0** and original concurrency `TWO_STALE_COMMITS` failure remain independent of any historical backup rehearsal.

## Next gated decision — not approved by WS52-F

**WS52-G, proposed only:** independently review WS52-F tool coverage and design an expanded dialect-conformance suite. If authorized, add a narrowly scoped **synthetic-only** reference-oracle comparison against MariaDB 11.4, with deliberately non-sensitive sample SQL, bounded queries and disposable infrastructure. Keep the tool synthetic-only and do not touch the real dump. Any new Docker provisioning/SQL, package installation, code reuse in production, reading the real backup, or new fixture teardown requires separately scoped authorization.

An actual backup scan/import would require a substantially more complete MariaDB/client-aware parser or proven alternative, a second independent isolated server plus schema-restricted account, and closure of confidentiality and storage blockers. That requires **separate** explicit approvals; the mere existence of this prototype creates no entitlement to execute source SQL.

## Independent remote GitHub semantic diff contract

For this WS52-F **explicit tool-implementation** stage, the authorized change is exactly **three added files** (new tool, new deterministic test suite, and this Markdown report) on **one new isolated branch** from WS52-E SHA `f917c0ab4271b9a313899b3e1a63b81ddfb7b19a`. Expect **three commits ahead, zero behind, three new paths and no modified preexisting application/product files**. After writing, independently fetch branch head and `compare_commits`, recheck source/test blob hashes and the two protected branch heads, original HARD-OFF gate and historical runner hashes. No pushing or merging to original B12 or production branches.

**Release locks:** `B12_CUTOVER=HARD_OFF`, migration 092 `NOT_AUTHORIZED`, D12 15 import gates `BLOCKED`, Stage D `UNAUTHORIZED`, 150 VU `HOLD`, 200 VU `PROHIBITED`. No production/staging/AWS/cPanel contact, customer data usage or schema import.
