# C137 / GC24 / K2-P03 / B12 — WS52-J blocked gate and WS52-JR isolated diagnostic

**Date:** 2026-10-15  
**WS52-J:** `RESULT=BLOCKED`, not a qualification PASS.  
**WS52-JR:** `RESULT=WS52JR_SERVER_ERROR_CAPTURED`, diagnostics completed without resource drift.  
**Corrected fact:** The specific synthetic **65-character table identifier** was rejected by MariaDB 11.4 with **error 1103, SQLSTATE 42000**, whereas the WS52-J test mistakenly required error **1059**. No 65-character table was created. The 64-character control table existed.  
**Release:** `REAL_BACKUP_ALLOWLIST=NOT_CERTIFIED`; `RESTORE_AUTHORIZED=NO`; `MIGRATION_092=NOT_AUTHORIZED`; `B12_CUTOVER=HARD_OFF`.

## Scope and explicit authorization

WS52-J was authorized as a **freshly isolated MariaDB 11.4 synthetic-only differential oracle** for identifier lengths, VARCHAR sizing and selected versioned comments. It stopped early at an exact numeric error-code gate. WS52-JR was then **separately authorized** for only the unresolved **65-character table-name diagnostic** in a *new* disposable, networkless MariaDB 11.4 container and volume; it did **not** authorize any wider differential retest. The bounded WS52-JR script permitted up to four synthetic SQL client invocations: new empty schema, 64-character table control, 65-character table attempt, and sanitized inventory check of whether either table existed. It did not access the original backup, copy or use customer records, read Docker logs, open the protected WS32 database, contact production/staging/AWS/cPanel, deploy product code or run migration 092.

All test findings in this report are derived from the **user-returned sanitized terminal output** (not independent remote attestation of their Mac runtime). The GitHub cross-check is independently performed against protected refs and blobs. The actual raw MariaDB error message was deliberately **not** displayed, so avoid asserting its exact English text or cause beyond the recorded numeric error/SQLSTATE.

## Pinned provenance and GitHub protections

| Evidence target | Pinned value |
|---|---|
| Repo | `abeycity4u/Renee-Farms-V2.2.50-Batch3B1` |
| Parent WS52-I branch | `c137-gc24-k2p03-b12-ws52i-identifier-length-numeric-cr-hardening-source` |
| Parent HEAD | `e2f52ef047b66baef329e6d780e693a302ece3e9` |
| New WS52-JR evidence-only branch | `c137-gc24-k2p03-b12-ws52jr-identifier-error-1103-evidence-source` |
| Original protected B12 branch HEAD | `c137-gc24-k2p03-b12-cas-writer-cutover-source` / `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af` |
| Protected production code branch HEAD | `v320-renee-agrisuite-branding` / `10041da3a3c4e4a7b8101f8183c243d056c7b128` |
| B12 HARD-OFF gate blob | `lib/daily_record_revision_cutover_gate.php` / `6e88c913ad5e5b371a9d0c92ce08870214ac1268` |
| Historical WS07 verifier blob | `scripts/verify_k2p03_b12_ws07_offline.sh` / `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`, in *later evidence history*, not original code HEAD |
| WS52-I classifier blob (unchanged) | `tools/b12_ws52f_synthetic_allowlist.py` / `d59ab164d81749ad1be09ae4ad6cd15d8e2be839` |
| WS52-I tests blob (unchanged) | `tests/test_b12_ws52f_synthetic_allowlist.py` / `2e0644ecbbdb78de315f32a99a5f3536e4276022` |

No original B12 source or WS52-I classifier/test modifications are called for by the WS52-JR result.

## WS52-J original blocked audit (user-returned, sanitized)

```text
#####COPY FROM HERE#####
AUDIT=B12_WS52J_SYNTHETIC_DIFFERENTIAL
RESULT=BLOCKED
FAILED_STAGE=CONTAINER_INSPECT
REASON=IDENTIFIER_65_TABLE_DENIAL_NOT_CERTIFIED
TEST_SERVER=MARIADB_11_4_PINNED
NEW_FIXTURE_SCOPE=WS52J_ONLY
NETWORK_MODE=NONE
PUBLISHED_PORTS=NO
SERVER_TCP_NETWORKING=DISABLED
ROOT_AUTH=NETWORKLESS_SYNTHETIC_ONLY
SYNTHETIC_SQL_CLIENT_INVOCATIONS=5
EXPECTED_POSITIVE_TESTS_PASS=4
EXPECTED_NEGATIVE_CODE_TESTS_PASS=0
IDENTIFIER_64_SERVER=ACCEPTED
IDENTIFIER_65_ERRORS=NOT_CERTIFIED
WS52I_VARCHAR_65535_POLICY=ALLOW_SYNTHETIC
MARIADB_VARCHAR_65535=NOT_TESTED
WS52I_GIANT_NUMERIC_POLICY=REJECT
MARIADB_GIANT_NUMERIC=NOT_TESTED
ACTIVE_AND_SKIPPED_COMMENT_EFFECTS=NOT_CERTIFIED
WS52I_POLICY_RUNTIME_REEXECUTED=NO
WS52I_SOURCE_POLICY=PREVIOUSLY_VERIFIED
WS52J_CONTAINER_DISPOSAL=PASS
WS52J_VOLUME_DISPOSAL=PASS
ORIGINAL_RESOURCE_INVENTORY=PASS
PROTECTED_METADATA_UNCHANGED=PASS
PRODUCTION_BACKUP_OPENED=NO
PRODUCTION_BACKUP_COPIED=NO
CUSTOMER_DATA_ACCESSED=NO
DOCKER_LOGS_READ=NO
SQL_EXECUTED=SYNTHETIC_ONLY
REAL_BACKUP_ALLOWLIST=NOT_CERTIFIED
MARIADB_FULL_GRAMMAR=NOT_CERTIFIED
RESTORE_SPACE_SUFFICIENCY=NOT_CERTIFIED
REAL_BACKUP_IMPORT_AUTHORIZED=NO
PRODUCTION_CONTACT=NO
MIGRATION_092_EXECUTED=NO
B12_CUTOVER=OFF
#####COPY TO HERE#####
```

**Important stage-reporting limitation:** The output's `FAILED_STAGE=CONTAINER_INSPECT` was overwritten during `finally` cleanup. The diagnostic failure gate is instead established by the `REASON=IDENTIFIER_65_TABLE_DENIAL_NOT_CERTIFIED`. Do not attribute the original block to an inspect command failure. The original test accepted a 64-character table identifier; its expected 65-character table rejection required error 1059 but did not match.

## WS52-JR actual diagnostic (user-returned, sanitized)

```text
#####COPY FROM HERE#####
AUDIT=B12_WS52JR_IDENTIFIER_65_DIAGNOSTIC
RESULT=WS52JR_SERVER_ERROR_CAPTURED
FAILED_STAGE=NONE
REASON=NONE
TEST_SCOPE=WS52JR_NEW_SYNTHETIC_ONLY
IMAGE=MARIADB_11_4_PINNED
NETWORK_MODE=NONE
PUBLISHED_PORTS=NO
SERVER_TCP_NETWORKING=DISABLED
ROOT_AUTH=NETWORKLESS_SYNTHETIC_ONLY
SQL_CLIENT_INVOCATIONS=4
POSITIVE_CONTROL_CHECKS=2
IDENTIFIER_65_DIAGNOSTIC=SERVER_SQL_ERROR_CAPTURED
IDENTIFIER_65_CLIENT_EXIT=NONZERO
IDENTIFIER_65_ERROR_CODE=1103
IDENTIFIER_65_SQLSTATE=42000
IDENTIFIER_65_UNEXPECTED_STDOUT=NO
IDENTIFIER_64_TABLE_EXISTS=1
IDENTIFIER_65_TABLE_EXISTS=0
WS52JR_CONTAINER_DISPOSAL=PASS
WS52JR_VOLUME_DISPOSAL=PASS
ORIGINAL_RESOURCE_INVENTORY=PASS
PROTECTED_METADATA_UNCHANGED=PASS
WS32_DATABASE_ACCESSED=NO
PRODUCTION_BACKUP_OPENED=NO
PRODUCTION_BACKUP_COPIED=NO
CUSTOMER_DATA_ACCESSED=NO
RAW_SQL_DISPLAYED=NO
RAW_ERROR_TEXT_DISPLAYED=NO
DOCKER_LOGS_READ=NO
SQL_EXECUTION=SYNTHETIC_ONLY
REAL_BACKUP_ALLOWLIST=NOT_CERTIFIED
RESTORE_AUTHORIZED=NO
PRODUCTION_CONTACT=NO
MIGRATION_092_EXECUTED=NO
B12_CUTOVER=OFF
#####COPY TO HERE#####
```

## Interpretation and root cause

- **Confirmed oracle expectation mismatch:** MariaDB server/client was observed rejecting the tested 65-character *table* name with numeric **1103 / 42000**, while WS52-J's hard-coded `deny(..., 1059, ...)` expected another error. This was sufficient for WS52-J to abort before remaining tests. The server returned a nonzero code, printed no unexpected stdout, and the verification found no 65-character table. A valid 64-character table existed.
- **Do not generalize codes:** This result demonstrates the correct expectation **only for this precise synthetic table-name test on the pinned MariaDB 11.4 fixture**. The 65-character *column* rejection was not tested by WS52-JR and could have a distinct code. Do not replace all expected 1059 codes globally, nor treat every nonzero SQL-client return as evidence of appropriate rejection. Preserve specific numeric code and object noncreation evidence.
- **WS52-I's conservative identifier-length check is aligned for this one case:** The Python prototype rejects names above 64 characters and MariaDB likewise rejected the particular 65-character table identifier. However, WS52-JR did **not** re-run the WS52-I Python policy on the Mac, nor certify all identifiers, quoting, modes or MariaDB grammar.
- **Unresolved original WS52-J matrix:** The 65-character column test; VARCHAR 255, 65535 and 65536 behavior; 500-digit numeric literal handling; active/skipped executable versioned comments and effect inventory all remain **NOT TESTED** in WS52-J. Four previous positive cases and their evidence should not be repeated merely for convenience.
- **Environment disposal and safeguards:** Both the original WS52-J test and WS52-JR diagnostic reported disposable container/volume deletion `PASS` and original Docker inventory/metadata `PASS`. Both ran on new networkless synthetic-only fixtures; WS32, production backup, protected containers and customer records were not accessed.

## Recommended *next* controlled stage — not authorized by WS52-JR

**WS52-JS (focused continuation)** — a *separately authorized*, new networkless disposable MariaDB 11.4 fixture with exact Docker identity protection and deletion. Correct the **65-character table** expected numeric code to **1103**, but **do not rerun that already diagnosed test**. Focus only on unfinished WS52-J matrix:
1. 65-character column identifier: inspect exact SQL error code and SQLSTATE and verify no table was created rather than assuming 1059.
2. `VARCHAR(255)`, `VARCHAR(65535)`, `VARCHAR(65536)`: report independently their controlled acceptance/errors; clearly distinguish server charset/engine limits from the Python policy.
3. Giant 500-digit `VARCHAR` numeric literal: capture bounded numeric SQL-error metadata without any raw SQL/error-text exposure or crash.
4. MySQL/MariaDB active and intentionally ignored future/5.7 comments: confirm effects with sanitized object-existence counts, without accepting a no-op as execution proof.
5. Preserve fixed SQL-call budget, no client metacommands, network none, no log reads, no backup, pinned Git/source/Docker resource baselines, strict cleanup and final source/volume guard invariants.

**WS52-JS must not be treated as authorized merely because WS52-JR ran.** If the future diagnostic reveals a new compatibility mismatch, document it as such; do not patch classifier source speculatively. If required fixture setup needs a small number of repeat schema-create calls, separate **setup-only** calls from repeated oracle cases, and bound the total. Do not re-test previously closed external QA without evidence.

## Long-standing B12 and restore hard locks

- Original real MariaDB B12 concurrency attempt failed `TWO_STALE_COMMITS`; corrected RC01–RC14 certified passes remain **0**.
- D12 fifteen import gates `BLOCKED`, Stage D `UNAUTHORIZED`, 150 VU `HOLD`, 200 VU `PROHIBITED`, migration 092 `NOT AUTHORIZED`, writer cutover `HARD_OFF`.
- The historical SQL backup was user-attested as extracted from live Renee AgriSuite production; prior pinned byte count 619163, SHA256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`. It was **not** opened/rehashed here. Complete SQL/client directive allowlist, storage expansion bounds, confidential-data-at-rest/retention/disposal, provenance and snapshot consistency remain unproven. No real restoration approval follows.
- No production/staging/AWS/cPanel contact. No WS32 database or existing protected container/volume operations.

## GitHub documentation-only evidence contract

Create exactly **one** new Markdown report on a **fresh docs-only branch** from original WS52-I HEAD `e2f52ef047b66baef329e6d780e693a302ece3e9`. The prior WS52-J branch-creation attempt was blocked, **so no WS52-J report commit was confirmed**; this WS52-JR report records both supplied outputs as a docs-only successor of WS52-I. Compare remote branch to WS52-I: `ahead_by=1`, `behind_by=0`, exactly one added Markdown file `docs/C137_GC24_K2_P03_B12_WS52JR_IDENTIFIER_65_ERROR_1103_DIAGNOSTIC.md`. Recheck protected original/production branch SHAs, B12 HARD-OFF gate, historical verifier and WS52-I classifier/test blobs. Product files must remain unchanged.
