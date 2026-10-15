# C137 / GC24 / K2-P03 / B12 — WS52-H targeted fail-closed identifier correction

**Date:** 2026-10-15.  
**Status:** `WS52H_SYNTHETIC_IDENTIFIER_FIX_TESTS_PASS`; `MARIADB_FULL_GRAMMAR_CERTIFIED=NO`; `ACTUAL_BACKUP_ALLOWLIST_CERTIFIED=NO`; `RESTORE_AUTHORIZED=NO`; `B12_CUTOVER=HARD_OFF`.

## Authorization and protected lineage

The user explicitly approved WS52-H: correct the **specific WS52-G unquoted reserved-identifier false-allow** in the standalone **synthetic-only** SQL classifier, update existing and new regression tests, and verify the isolated GitHub branch. This approval does **not** include SQL server access, Docker, the production-derived backup, customer data, staging, AWS, cPanel, a live restore, migration 092, production release or edits to the protected original B12 code branch.

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Immediate parent evidence-only branch: `c137-gc24-k2p03-b12-ws52g-identifier-mismatch-evidence-source` at `78fda89db8201c4a7f61f71222bc4cfa9604705c`.
- New successor implementation branch: `c137-gc24-k2p03-b12-ws52h-backtick-identifier-correction-source`.
- Protected original B12 branch `c137-gc24-k2p03-b12-cas-writer-cutover-source` at `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Protected production `v320-renee-agrisuite-branding` at `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- B12 writer hard-OFF gate `lib/daily_record_revision_cutover_gate.php`: Git blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`.
- Historical WS07 runner `scripts/verify_k2p03_b12_ws07_offline.sh`: blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`, present in later evidence history but *absent* at the protected original B12 source commit.

The parent WS52-F tool/source blobs were pre-verified as `83fb7ffa9a25421a2badc479f2366fd8c53a4191` (tool) and `9ad69c5629765138ab441686065d77c39ceaf91c` (tests). Protected references and branch-existence gate passed before the new WS52-H branch was created.

## Root cause and minimal implementation

WS52-G used real MariaDB 11.4 **synthetic-only** test statements to show unquoted SQL identifiers `SELECT` and `ORDER` generate **ERROR 1064**, although WS52-F's `Cursor.ident()` classified any ASCII lexical `WORD` satisfying `SAFE_NAME` as an identifier.

**Targeted fix:** In `tools/b12_ws52f_synthetic_allowlist.py`, `Cursor.ident()` now requires both **`Token.kind == "IDENT"`** and the pre-existing `SAFE_NAME` pattern. The lexer assigns `IDENT` only to backtick-quoted identifier tokens. This requires explicit backticks for table and column positions in the intentionally narrow subset; ordinary unquoted SQL command tokens such as `CREATE`, `DROP`, `INSERT`, `TABLE`, `INT`, `VALUES` still use `WORD` and are not incorrectly reinterpreted as object names.

The embedded smoke-test success case was changed to `CREATE TABLE \`demo\` (\`id\` INT)`; its rejected case explicitly includes `CREATE TABLE SELECT (\`id\` INT)`. In `tests/test_b12_ws52f_synthetic_allowlist.py`, all previously allowed cases using unquoted table/column names were changed to their backtick-quoted equivalents; their policy classifications remain the same. Added synthetic accepted cases for `CREATE TABLE \`SELECT\` (\`id\` INT)` and `CREATE TABLE \`ORDER\` (\`id\` INT)`. Added twelve new expected rejection cases for unquoted and mixed-quoted table/column names and the real WS52-G reserved-name discrepancy. Existing 67 rejection cases were retained unchanged. No product route, migration, billing, SQL execution path or protected source was modified.

**Conservative behavior is intentional:** Valid but unsupported MariaDB statements remain rejected. Backtick-only identifiers avoid relying on a partial reserved-word blacklist, but do **not** prove full MariaDB grammar compatibility or guarantee all syntactically accepted subset statements execute. No production dump is an approved input.

## Exact reproducible test evidence

Both final files were reconstructed in an isolated Python environment, with their **Git blob object hashes matching the bytes returned by GitHub**:

| File | Final tested Git blob |
|---|---|
| `tools/b12_ws52f_synthetic_allowlist.py` | `088e2b3ce0d859fb7a53c0607c95d8c909044099` |
| `tests/test_b12_ws52f_synthetic_allowlist.py` | `b9d07daea23e22550f219fba788f08fcc7f8fb19` |

The isolated notebook's initial attempt to launch a Python subprocess stalled and was interrupted; **no test PASS was claimed from that attempt**. A separate bounded direct local shell execution against the same verified source and test files produced:

```text
AUDIT=B12_WS52F_SYNTHETIC_SMOKE
RESULT=PASS
REAL_BACKUP_ACCESS=NO
SQL_EXECUTED=NO
MARIADB_GRAMMAR_CERTIFIED=NO
B12_CUTOVER=OFF
SMOKE_RC=0
```

The initial full suite run exposed one incomplete test migration: expected-allow fixture `DROP TABLE IF EXISTS demo;` (case 5) still had an unquoted identifier, causing one expected-allow ERROR. All other cases, including the new negative cases, passed. The one test fixture was corrected to `DROP TABLE IF EXISTS \`demo\`;`, and its revised bytes were independently matched to Git blob `b9d07daea23e22550f219fba788f08fcc7f8fb19`.

**Final bounded full test suite rerun after correction**:

```text
test_approved_subset ... ok
test_fail_closed_input_contract ... ok
test_rejected_cases ... ok

Ran 3 tests in 0.008s
OK
UNITTEST_RC=0
SMOKE_RC=0
ALLOW_CASES=32
REJECT_CASES=79
BAD_INPUT_CONTRACT_CASES=6
MARIADB_CLIENT_CONNECTIONS=0
DOCKER_OPERATIONS=0
REAL_BACKUP_OPENED=NO
PRODUCTION_CONTACT=NO
```

Commands used inside the assistant's temporary isolated `/mnt/data/ws52h_isolated` test directory (not the user's protected Mac checkout):

```bash
python3 -B tools/b12_ws52f_synthetic_allowlist.py --self-test
python3 -B -m unittest discover -s tests -p test_b12_ws52f_synthetic_allowlist.py -v
git hash-object tools/b12_ws52f_synthetic_allowlist.py tests/test_b12_ws52f_synthetic_allowlist.py
```

No Docker or SQL server was called. No live source file or backup was mounted, parsed or transmitted. Test outcomes are synthetic Python-policy evidence only; MariaDB runtime was **not** rerun after this patch. The earlier WS52-G MariaDB server result remains evidence for why the fix was needed.

## Limitations and next controlled action

- The WS52-H patch eliminates the *specific unquoted reserved-word false-allow path* by disallowing all unquoted object identifiers. It does **not** certify other MariaDB reserved-name corner cases, SQL modes, versioned comments or dump-execution/client-command syntax.
- It does not make the prototype capable of classifying the actual 619163-byte user-reported production-derived historical SQL backup. The last pinned dump remains at hash `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`; **the dump was not opened or rehashed in WS52-H**.
- The original backup has 69 `DROP TABLE` directives according to the earlier limited lexical screen; a permissive SQL import could destroy data if routed to the wrong target. An independently proven effective SQL/client directive allowlist plus new networkless server and empty schema with non-root schema-only privileges are both mandatory before any real import.
- The data-at-rest/snapshot retention, safe key-erasure, import credentials, confidential logging, storage growth bound (WS52-D's guest 212.98 GiB free vs Mac physical ~166.80 GiB not additive), and transactionally consistent historical backup remain uncertified.
- Original real MariaDB B12 concurrency failed `TWO_STALE_COMMITS`; corrected RC01–RC14 PASS count remains 0. Migration 092 and CAS writer cutover **NOT AUTHORIZED**.
- **Proposed next separately authorized step:** WS52-I focused independent *synthetic-only* conformance review for backtick identifier edge cases, including versioned comments and ambiguity, or a narrowly targeted static security review of the postpatch parser; no reason to repeat WS52-G container fixture unless newly supported specific regressions warrant it. No actual dump read, Docker operations or SQL execution without separate user permission.

**Hard operational locks:** `B12_CUTOVER=HARD_OFF`, `MIGRATION_092=NOT_AUTHORIZED`, D12 15 import gates `BLOCKED`, Stage D `UNAUTHORIZED`, 150 VU `HOLD`, 200 VU `PROHIBITED`. No production/staging/AWS/cPanel contact; no protected original C137 fixture or WS32 modification.

## GitHub semantic-diff verification contract

WS52-H is a **targeted implementation stage**, not documentation-only. Expected delta vs parent WS52-G `78fda89db8201c4a7f61f71222bc4cfa9604705c`: **two modified prototype files** (`tools/b12_ws52f_synthetic_allowlist.py`, `tests/test_b12_ws52f_synthetic_allowlist.py`) plus **one newly added Markdown evidence report** (`docs/C137_GC24_K2_P03_B12_WS52H_BACKTICK_IDENTIFIER_CORRECTION.md`). Multiple small commits are allowed, but **no other file change**. Independently compare branch head to parent (ahead > 0, behind 0), inspect all three expected paths only, re-fetch final tested blob hashes and protected original/production HEADs, hard-OFF gate blob and historical verifier blob. Never merge this branch into protected B12 or production without a separate plan and permission.
