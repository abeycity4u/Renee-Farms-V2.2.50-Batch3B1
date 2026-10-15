# C137 / GC24 / K2-P03 / B12 — WS52-G reserved-identifier compatibility mismatch

**Date:** 2026-10-15  
**User-returned audit:** `CLASSIFIER_ORACLE_MISMATCH_CONFIRMED`  
**Disposition:** `WS52G_SYNTHETIC_ORACLE_TEST_COMPLETE`; `WS52F_IDENTIFIER_COMPATIBILITY=DEFECT_CONFIRMED`; `WS52F_GENERAL_MARIADB_COMPATIBILITY=NOT_CERTIFIED`; `ACTUAL_BACKUP_IMPORT=NOT_AUTHORIZED`; `B12_CUTOVER=HARD_OFF`.  
**Scope:** A *user-authorized, narrowly bounded, synthetic-only* MariaDB 11.4 actual server/client oracle in a **new disposable networkless fixture**; no original backup/production records or protected containers/databases accessed. This document is a record of the user's sanitized output and the existing pinned WS52-F source review; no new local execution has occurred while producing this report.

## 1. Exact GitHub lineage, guards, and prior source state

| Item | Pinned evidence |
|---|---|
| Repo | `abeycity4u/Renee-Farms-V2.2.50-Batch3B1` |
| Parent isolated WS52-F implementation branch | `c137-gc24-k2p03-b12-ws52f-synthetic-allowlist-tool-source` |
| Parent HEAD | `58e93a5e22aabf6c0120f1e967b428d5a4a6720f` |
| New WS52-G **docs-only successor** | `c137-gc24-k2p03-b12-ws52g-identifier-mismatch-evidence-source` |
| Original B12 protected code branch | `c137-gc24-k2p03-b12-cas-writer-cutover-source` |
| Original B12 HEAD | `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af` |
| Protected production branch | `v320-renee-agrisuite-branding` |
| Production HEAD | `10041da3a3c4e4a7b8101f8183c243d056c7b128` |
| Literal hard-OFF cutover gate `lib/daily_record_revision_cutover_gate.php` | Git blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268` |
| Historical WS07 offline verifier `scripts/verify_k2p03_b12_ws07_offline.sh` | Git blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb` |
| Pinned WS52-F classifier `tools/b12_ws52f_synthetic_allowlist.py` | Git blob `83fb7ffa9a25421a2badc479f2366fd8c53a4191` |
| Pinned WS52-F corpus `tests/test_b12_ws52f_synthetic_allowlist.py` | Git blob `9ad69c5629765138ab441686065d77c39ceaf91c` |

The WS07 historical verifier exists in later evidence history but **not the original B12 code HEAD** (WS51R corrected the prior mistaken reference assertion). The original B12 worktree and source/production branches must remain unchanged.

## 2. Specific authorization and method

The user explicitly authorized WS52-G synthetic-only qualification after WS52-F. WS52-G was scoped to a **new local Docker Desktop, MariaDB 11.4, network-disabled, synthetic-only** instance and exclusive volume named `renee-b12-ws52g-oracle-mariadb114` and `renee-b12-ws52g-oracle-datadir`. WS52-G used pinned image fingerprint `2ee3c4a72fcac30a`, `--network none`, server `--skip-networking`, no published ports, resource limits and labels limited to `WS52G`.

The script checked the original source branch/HEAD/clean tree, hard-OFF gate and expected historical verifier absence, local Docker Desktop context/engine, WS32 separate running fixture and its dedicated volume, two older protected running containers and three original volumes. It used hardcoded synthetic DDL and tiny synthetic data only; a random new schema inside the WS52-G container, no backup file, no production credentials, no customer data. MariaDB client's **empty-root-password bootstrap is exclusively for this fresh networkless synthetic fixture**, **prohibited** for any future production-derived-data restore target. No client metacommands were tested. MariaDB acceptance and error-code results were sanitized.

The oracle intentionally included both: (a) SQL that *WS52-F appears to allow* but real MariaDB rejects, and (b) valid MariaDB SQL that the deliberately narrower prototype policy rejects. These require distinct interpretation.

All 13 expected-success synthetic MariaDB client invocations succeeded; two synthetic reserved-identifier negative invocations returned MariaDB **ERROR 1064**; no unexpected error was counted as a syntax test PASS. WS52-G then disposed of the exact identity-verified temporary container and volume and reported original Docker inventory/fingerprints unchanged.

## 3. Exact user-returned sanitized evidence

```text
#####COPY FROM HERE#####
AUDIT=B12_WS52G_RESERVED_IDENTIFIER_ORACLE
RESULT=CLASSIFIER_ORACLE_MISMATCH_CONFIRMED
FAILED_STAGE=NONE
REASON=NONE
MARIADB_SYNTHETIC_ACCEPTED=13
RESERVED_UNQUOTED_ERROR_1064=2
POLICY_REJECTS_VALID_MARIADB_SQL=1
WS52F_UNQUOTED_KEYWORD_IDENTIFIERS=ACCEPTED_BY_SOURCE_REVIEW
WS52F_MARIADB_COMPATIBILITY=NOT_CERTIFIED
WS52G_CONTAINER_DISPOSAL=PASS
WS52G_VOLUME_DISPOSAL=PASS
ORIGINAL_RESOURCE_INVENTORY=PASS
PROTECTED_METADATA_UNCHANGED=PASS
PRODUCTION_BACKUP_OPENED=NO
CUSTOMER_DATA_ACCESSED=NO
PRODUCTION_CONTACT=NO
SQL_EXECUTED=SYNTHETIC_ONLY
REAL_BACKUP_IMPORT_AUTHORIZED=NO
MIGRATION_092_EXECUTED=NO
B12_CUTOVER=OFF
#####COPY TO HERE#####
```

## 4. Finding: false allow on reserved unquoted identifiers

**Observed against MariaDB 11.4:** `CREATE TABLE SELECT (id INT)` and `CREATE TABLE ORDER (id INT)` were each rejected by the real MariaDB client/server with syntax error **1064**; the quoted form `CREATE TABLE \`SELECT\` (id INT)` was accepted (within the 13 positive synthetic cases).

**Grounded WS52-F code mechanism:** `_lex()` maps an unquoted ASCII word into `Token("WORD", UPPERCASE_VALUE)`; `Cursor.ident()` accepts **either `WORD` or `IDENT`** whenever the string matches `SAFE_NAME`. It does **not** independently screen MariaDB reserved keywords when the token is an unquoted `WORD`. Thus the prototype's current policy can classify those examples as `CREATE_TABLE` even though MariaDB rejected them. This is a **false-allow compatibility defect**, not proof of privilege escalation or out-of-schema execution.

**Different deliberate limitation:** MariaDB accepted a synthetic `CREATE TABLE ... (id BLOB)` statement that WS52-F rejects. Restricting a prototype to fewer valid SQL constructs is an intentional **fail-closed policy exclusion**, not an unsafe false-allow. The policy may remain narrow until exact parser compatibility can be supported.

**Root cause and impact:** The synthetic classifier conflates lexical identifier shape with the dialect's reserved-keyword rules. A false allow could cause an eventual import to fail unexpectedly if mistakenly promoted into an executable allowlist; the WS52-F classifier remains **unsuitable for real backup scanning or importing**. The review has identified two reserved words, not exhaustively checked every keyword or SQL mode. Do not treat a two-case correction as MariaDB grammar certification.

## 5. Immediate scoped recommendation: WS52-H (NOT YET AUTHORIZED)

**Recommended safe remediation:** build a targeted synthetic-only change on a *new isolated successor implementation branch*, not the original B12 code or production branch. First create red-case regression fixtures asserting `CREATE TABLE SELECT ...` and `CREATE TABLE ORDER ...` are `Rejected`, and asserting the backtick-quoted equivalent remains permitted. Then decide a clearly fail-closed identifier policy **based on token kind**:

- A robust conservative option is **requiring backtick-quoted identifiers** within the deliberately limited prototype subset. That would require updating the existing synthetic positive fixtures accordingly and explicitly documenting reduction in accepted unquoted syntax. It avoids treating an incomplete reserved-keyword blacklist as safe; however it does **not** make the resulting parser complete.
- If unquoted identifiers must remain supported, use a **pinned, independently validated MariaDB 11.4 reserved-name/lexer contract** (or an explicitly finite safe-name allowlist) with synthetic positive and negative tests; a quick blacklist containing only `SELECT` and `ORDER` is not sufficient to establish general compatibility.
- Preserve protected original/production Git references, B12 hard-OFF gate and WS07 verifier; keep WS52-G as *evidence only*. Do not automatically modify WS52-F branch (already a checkpoint).
- Rerun the full prior WS52-F synthetic corpus plus the targeted new regressions, and independently verify the source/test Git blob hashes and remote semantic diff of *only intended files*. Do not reopen real backup, issue SQL on original/WS32, contact production, or conduct broad repeat of closed WS52-D.
- The existing 97-case WS52-F corpus validates narrow synthetic expectations; changing the identifier policy will require explicitly updating relevant expected passes. Avoid weakening rejection of qualified `db.table`, `USE`, privilege/global/client commands or unknown syntax.

**Explicitly retain blockers even after future WS52-H:** complete actual MariaDB 11.4/client-effective-SQL allowlist, original production-derived dump compatibility and destination safety (69 historical `DROP TABLE` directives), confidential data-at-rest/backups/snapshot retention, strong real import credentials, rollback/key erasure, credible expanded footprint and space reserve, original historical coherent snapshot, corrected B12 RC01–RC14 proof. The original prior actual MariaDB concurrency failed `TWO_STALE_COMMITS`, corrected passes remain **0**.

## 6. Persistent hard stops and WS52-G GitHub change contract

`REAL_BACKUP_IMPORT_AUTHORIZED=NO`, `MIGRATION_092=NOT_AUTHORIZED`, `B12_CUTOVER=HARD_OFF`, `D12_IMPORT_GATES=BLOCKED_15`, `STAGE_D=UNAUTHORIZED`, `150_VU=HOLD`, `200_VU=PROHIBITED`.

No production, staging, AWS, cPanel or protected historic C137 fixture contact; no copying, displaying or importing potentially sensitive user backup. WS32 and two older original containers/three old original volumes untouched. WS52-G's disposable container and volume removed, verified by the user-returned audit.

WS52-G GitHub action is exactly **one new Markdown evidence file** on the newly created branch from WS52-F HEAD `58e93a5e22aabf6c0120f1e967b428d5a4a6720f`. Independent compare must show **one commit ahead, zero behind, sole added `docs/C137_GC24_K2_P03_B12_WS52G_RESERVED_IDENTIFIER_ORACLE_MISMATCH.md`**; protected original/production SHA, hard-OFF gate, WS07 runner, WS52-F classifier/test blobs unchanged. **This evidence report does not authorize WS52-H remediation.**
