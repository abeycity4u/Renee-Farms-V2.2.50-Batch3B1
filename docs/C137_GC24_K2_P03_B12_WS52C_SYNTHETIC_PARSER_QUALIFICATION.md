# C137 / GC24 / K2-P03 / B12 — WS52-C synthetic SQL-classifier prototype qualification

**Date:** 2026-10-15.  
**Scope and result:** `SYNTHETIC_PROTOTYPE_CORPUS_PASS_NOT_MARIADB_CERTIFIED`. This is a **self-contained synthetic-only Python prototype test in the assistant's isolated analysis environment**, **not** a MariaDB client/server test, not a Mac execution, not validation against the production-derived dump, and not a release/security certification.  
**Authorization:** User explicitly said `yes` to design and conduct a bounded, synthetic-only parser qualification with no access to real backup, database restoration or protected resources. This report and its new GitHub evidence-only branch are the only repository artifact changes; no application code or original source was changed.

## Branch lineage and immutable controls

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Exact parent documentation branch: `c137-gc24-k2p03-b12-ws52b-parser-guest-capacity-feasibility-source` at SHA `712b82ad66803a5e245bab1028a34f23867efd7b`.
- New successor branch: `c137-gc24-k2p03-b12-ws52c-synthetic-parser-qualification-source`.
- Protected original B12 code branch: `c137-gc24-k2p03-b12-cas-writer-cutover-source` SHA `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Protected production source: `v320-renee-agrisuite-branding` SHA `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- Original B12 literal HARD-OFF gate: `lib/daily_record_revision_cutover_gate.php` blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`.
- Historical WS07 offline verifier: `scripts/verify_k2p03_b12_ws07_offline.sh` blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb` in **later evidence history**, not in original B12 SHA. The WS51 historical wrong-ref assertion was fixed in WS51R; do not rewrite the original code checkout.

## What was actually run and where

The assistant constructed a **small, conservative, Python standard-library-only statement classifier** with a static corpus of **58 entirely synthetic SQL examples** and independently declared per-case expected `ALLOW` or `REJECT` outcomes. Tests were executed **in the assistant analysis Python sandbox**, not on the user's Mac or any Renee AgriSuite infrastructure. The classifier exists as prototype code in the transient analysis environment; it was **not** added to the product repository and is **not a packaged, audited or production-ready utility**.

The model handles a **deliberately limited subset**:

- Separates statements using semicolons outside recognized single-quoted strings and backtick-quoted identifiers.
- Tokenizes ASCII identifiers, numbers, limited punctuation and SQL line/block comments; rejects unexpected characters and unclosed quotes/comments.
- Recognizes one exact MariaDB sandbox-directive-shaped comment with position and duplicate restrictions.
- Attempts limited treatment of `/*!<guard> ... */` and `/*M!<guard> ... */` **for synthetic samples only**, rejecting guards classified as uncertain, unknown/unsupported bodies, nested executable comments and embedded semicolons. **No independent confirmation of MariaDB server/version-dependent semantics occurred here**.
- Uses synthetic `DEFAULT` and `NO_BACKSLASH_ESCAPES` input modes as explicit test parameters; it does **not** track every dynamic SQL_MODE change in a real dump.
- Permits a *strict syntactic subset* resembling `CREATE TABLE`, `DROP TABLE`, `INSERT INTO ... VALUES`, `ALTER TABLE ... ENABLE/DISABLE KEYS`, `LOCK/UNLOCK TABLES`, `SET SESSION SQL_MODE` and the single exact synthetic sandbox directive. It does **not** parse full column definitions, constraints, data types or storage engines to authoritative MariaDB 11.4 standards.
- Rejects qualified-schema access attempts (`other.table`, including backtick quoting), database switches, privilege/account/global commands, external-file and client script commands, unrecognized statements, suspicious versioned comments, malformed constructs and modes outside its defined contract.
- Never constructs or submits SQL to a server and never accesses any customer record.

**Important:** A reject-by-default prototype can correctly reject all synthetic bypass cases and still be **unsuitable as a real dump importer/parser**. It is not legitimate to equate prototype agreement with MariaDB-compatible grammar coverage or to use its results as authorization to import.

## Prototype iteration and test outcome

The **initial attempt** returned **7 false rejections** on intended-valid `INSERT` test examples: the classifier required at least six post-`INSERT INTO` token items when a simple valid synthetic `INSERT INTO t VALUES (1)` produces **five**. This was a **prototype bug** in the acceptance subset, not evidence of unsafe MariaDB behavior.

The token-width assertion was corrected to permit at least five synthetic items. The **complete 58-case corpus was rerun**, not just the seven previously failing cases:

```text
AUDIT=B12_WS52C_SYNTHETIC_SUBSET_CLASSIFIER
EXECUTION_ENVIRONMENT=ASSISTANT_ISOLATED_PYTHON
CORPUS_TOTAL=58
EXPECTED_ALLOW=23
OBSERVED_ALLOW=23
EXPECTED_REJECT=35
OBSERVED_REJECT=35
CASE_EXPECTATION_MISMATCHES=0
INITIAL_PROTOTYPE_FALSE_REJECTIONS=7
CORRECTION=INSERT_MIN_TOKEN_WIDTH_6_TO_5
DATABASE_CONNECTIONS=0
SQL_EXECUTED=0
DOCKER_OPERATIONS=0
USER_MAC_COMMANDS=0
REAL_BACKUP_OPENED=NO
REAL_BACKUP_COPIED=NO
CUSTOMER_DATA_ACCESSED=NO
PRODUCT_CODE_CHANGED=NO
MARIADB_RUNTIME_ORACLE=NOT_TESTED
COMPLETE_MARIADB_SQL_GRAMMAR=NOT_CERTIFIED
ACTUAL_DUMP_ALLOWLIST=NOT_CERTIFIED
DOCKER_GUEST_CAPACITY=NOT_ATTESTED
RESTORE_AUTHORIZED=NO
MIGRATION_092_EXECUTED=NO
B12_CUTOVER=OFF
RESULT=SYNTHETIC_PROTOTYPE_CORPUS_PASS_NOT_MARIADB_CERTIFIED
```

Corpus categories (each case has an explicit expected outcome):

| Synthetic category | Cases |
|---|---:|
| Client directives and metacommands | 9 |
| Comment structure | 6 |
| DDL target/table operations | 8 |
| Simple DML | 1 |
| Character encoding | 1 |
| External file/SELECT-style out-of-scope operations | 4 |
| Quoting/strings | 6 |
| SQL mode | 4 |
| Multiple statements | 1 |
| Qualified schema access | 6 |
| Privilege/account/global changes | 4 |
| Unfinished/unsupported syntax | 1 |
| Conditional versioned comments | 7 |
| **Total** | **58** |

Representative **synthetic-only** test classes:

- **Expected permit:** `CREATE TABLE t(id INT);`, `DROP TABLE IF EXISTS t;`, `INSERT INTO t VALUES ('a; b');` (semicolon inside quoted value), `ALTER TABLE t DISABLE KEYS;`, `LOCK TABLES t READ;`, `UNLOCK TABLES;`, `SET SESSION sql_mode='STRICT_ALL_TABLES';`, a small synthetic `/*!40101 ... */`/ `/*M!100000 ... */` example, and one exact sandbox directive followed by synthetic `CREATE TABLE`.
- **Expected reject:** `DROP TABLE other.t;`, `DROP TABLE \`other\`.\`t\`;`, `USE other;`, `GRANT ALL PRIVILEGES ON *.* TO u;`, `SET GLOBAL max_connections=100;`, `CREATE USER ...`, `LOAD DATA LOCAL INFILE ...`, `SELECT ... INTO OUTFILE ...`, `source ...`, `DELIMITER ...`, `\\. ...`, shell-meta commands, future/ambiguous version-guard comments, duplicate or modified sandbox directive, unterminated strings/comments, nested comments, unsupported double-quote/mode combinations, non-ASCII token handling and `CREATE TABLE ... AS SELECT`.

**Ephemeral prototype fingerprint (SHA-256 of corrected function definition string in the analysis session):** `7602e3d36451370bb834f9c4b98a0862ffd113b1eeed45b0058ba6f55b0c806c`.

**Ephemeral synthetic corpus fingerprint (SHA-256 of serialized ordered test cases in that session):** `aeffe66f66203748a05dd0db0b3c60b693aace8a4a27c121a8ce150df5e19080`.

These fingerprints document session provenance but **do not make this prototype reproducible on their own**: the complete standalone source/test suite was not published as an executable artifact and has not been independently reviewed. The synthetic acceptance oracle and implementation were both authored within the same session, so independence is limited.

## Implications: no false certification or privilege escalation

**Supported conclusion:** A constrained SQL-classification approach can implement useful fail-closed allow/reject rules for a **small synthetic subset**; after correction it matched all 58 expected synthetic examples. WS52-A independently proved schema-only MariaDB privileges denied five synthetic non-target operations and preserved its forbidden sentinel, but that evidence comes from an **independent test**, not from this parser prototype.

**Still unresolved:**

1. **Real MariaDB parser semantics:** The synthetic prototype is **not** the MariaDB 11.4 parser, and has not been checked against a separately pinned MariaDB server/client oracle across its full input corpus. The exact behavior of MySQL-style vs MariaDB-style executable comments at tricky version guards and under SQL_MODE transitions must not be inferred from a custom classifier. WS49 proved *only* synthetic sandbox input acceptance.
2. **Real backup effective SQL allowlist:** The user-reported production-derived `~/Downloads/database.sql` SHA256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`, 619163 bytes, containing **69 `DROP TABLE`** lexical statements, was **NOT opened or analyzed in WS52-C**. WS51S zero lexical risk flags do not certify these statements against arbitrary MariaDB grammar/client behavior. Do **not** run the prototype directly on this file and declare a PASS.
3. **Guest capacity & expanded footprint:** WS51S last observed **166.81 GiB** host physical backing free, Docker sparse backing logical **233.47 GiB**, allocated **2.81 GiB**; actual new guest free bytes/inodes, import expansion, table/index/temp/log growth and data-residency controls **not attested**.
4. **Historical consistency:** Production export origin is user-attested; a coherent historical snapshot is not established. A future successful import would at most be a **historical rehearsal**, not proof that B12 concurrency bug `TWO_STALE_COMMITS` was fixed.
5. **Product release locks:** Original real MariaDB repaired RC01–RC14 PASS count **0**; migration 092 NOT AUTHORIZED; D12 fifteen import gates BLOCKED; Stage D UNAUTHORIZED; 150 VU HOLD; 200 VU PROHIBITED; daily-record CAS writer cutover literal **HARD-OFF**.

## Recommendation: next controlled gate (NOT AUTHORIZED)

Propose **WS52-D — independent synthetic MariaDB oracle/capacity test**, with **separate explicit permission** for one *new*, uniquely named, network-disabled, disposable test container/volume and a **fixed bounded, entirely synthetic** SQL client/server statement corpus (and non-SQL `df`/inode metadata on that dedicated guest datadir). Do not repurpose WS32, access the user's backup, create a real restore target or connect to production. Distinguish **server syntax acceptance**, **client command acceptance** and **effective privilege denial** as different outcomes. A MariaDB SQL error is not necessarily a parser defect; use strict expected error-code/class criteria rather than treating arbitrary nonzero exits as a pass. Guard exact original Docker/volume and source identities before and after; teardown only new scope with identity verification; no broad deletions; sanitized copy-back.

The independently run synthetic oracle could validate or falsify some of the prototype's assumptions and measure guest capacity in that **particular** disposable fixture, but would **not** establish full actual-backup compatibility, data-at-rest confidentiality or sufficient import footprint. Further repository implementation of a fully auditable parser and any local production-derived backup read would require separately scoped authorization and conformance review.

**WS52-C GitHub policy:** one new evidence-only Markdown file on a new successor from WS52-B SHA `712b82ad66803a5e245bab1028a34f23867efd7b`, followed by independent remote semantic diff: ahead 1, behind 0, sole addition this report. Re-attest original/production branch HEADs, gate and historical verifier SHA. No protected source, backup, user Docker infrastructure or customer data changed.
