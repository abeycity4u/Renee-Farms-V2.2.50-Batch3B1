# C137 / GC24 / K2-P03 / B12 — WS52-JS remaining synthetic MariaDB 11.4 differential evidence

**Recorded:** 2026-10-15. **User-returned audit:** `WS52JS_ORACLE_COMPLETE_DIFFERENCES_FOUND`.  
**Finding:** `VARCHAR65535_POLICY_FALSE_ALLOW` under the bounded, synthetic MariaDB 11.4 fixture. This report records **a compatibility discrepancy, not an approved production SQL repair, parser certification, or historical backup import**. `B12_CUTOVER=HARD_OFF`; migration 092 not authorized; historical restore remains NO-GO.

## 1. Authorization and scope

The user expressly authorized WS52-JS **only** to resume the unfinished, previously approved WS52-J synthetic differential checks after the WS52-JR diagnostic. The approved step used **one new** isolated, networkless, disposable MariaDB 11.4 container + dedicated volume, synthetic SQL only, bounded to 12 client invocations, with strict identity and cleanup checks. WS52-JR had already resolved the **65-character table-name** mismatch: MariaDB rejected it with `1103 / 42000`, contrary to WS52-J's original overly strict `1059` expectation. WS52-JS did **not** repeat that already diagnosed table test. WS52-JS examined the remaining 65-character **column**, VARCHAR length cases, a very large numeric length and executable-comment effects.

The report is grounded in the **sanitized user-returned terminal output**, not an independently witnessed client execution. No production-derived SQL file was opened, copied, hashed, parsed or imported. No customer data, production credentials, protected WS32 database, original containers/volumes, staging, AWS, cPanel or live production were touched. The WS52-JS fixture was deleted and the original Docker inventory and container metadata were reported unchanged. The synthetic-only empty-root-password bootstrap is **not** a valid pattern for handling production-derived records and must never be reused for a real backup restore.

## 2. GitHub parent and protected invariants

| Checkpoint | Pinned ref |
|---|---|
| Repository | `abeycity4u/Renee-Farms-V2.2.50-Batch3B1` |
| Immediate parent WS52-JR evidence branch | `c137-gc24-k2p03-b12-ws52jr-identifier-error-1103-evidence-source` |
| Parent HEAD | `e526bc96b8d61c0dd8325c271580003dc049a844` |
| New WS52-JS documentation-only branch | `c137-gc24-k2p03-b12-ws52js-remaining-differential-evidence-source` |
| Original protected B12 branch | `c137-gc24-k2p03-b12-cas-writer-cutover-source` |
| Original protected B12 HEAD | `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af` |
| Protected production branch | `v320-renee-agrisuite-branding` |
| Protected production HEAD | `10041da3a3c4e4a7b8101f8183c243d056c7b128` |
| HARD-OFF cutover gate blob, `lib/daily_record_revision_cutover_gate.php` | `6e88c913ad5e5b371a9d0c92ce08870214ac1268` |
| Historical WS07 verifier blob, `scripts/verify_k2p03_b12_ws07_offline.sh` | `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb` |
| Unchanged WS52-I classifier blob | `tools/b12_ws52f_synthetic_allowlist.py`: `d59ab164d81749ad1be09ae4ad6cd15d8e2be839` |
| Unchanged WS52-I synthetic tests blob | `tests/test_b12_ws52f_synthetic_allowlist.py`: `2e0644ecbbdb78de315f32a99a5f3536e4276022` |

The historical WS07 verifier is **absent** at the original protected B12 code HEAD, but present in later evidence history; never assert its existence at that original HEAD. The preceding WS52-J GitHub branch creation had been blocked, so the WS52-J and WS52-JR audits were recorded together on the verified WS52-JR documentation branch.

## 3. Exact sanitized user-returned audit

```text
#####COPY FROM HERE#####
AUDIT=B12_WS52JS_REMAINING_SYNTHETIC_ORACLE
RESULT=WS52JS_ORACLE_COMPLETE_DIFFERENCES_FOUND
FAILED_STAGE=NONE
REASON=NONE
TEST_SERVER=MARIADB_11_4_PINNED
TEST_SCOPE=WS52JS_NEW_DISPOSABLE_ONLY
NETWORK_MODE=NONE
PUBLISHED_PORTS=NO
SERVER_TCP_NETWORKING=DISABLED
ROOT_AUTH=NETWORKLESS_SYNTHETIC_ONLY
SQL_CLIENT_INVOCATIONS=12
EXPECTED_SUCCESS_CHECKS=8
DIFFERENTIAL_OBSERVATIONS=4
COLUMN65_SERVER=REJECTED
COLUMN65_ERROR_CODE=1059
COLUMN65_SQLSTATE=42000
VARCHAR65535_SERVER=REJECTED
VARCHAR65535_ERROR_CODE=1074
VARCHAR65535_SQLSTATE=42000
VARCHAR65536_SERVER=REJECTED
VARCHAR65536_ERROR_CODE=1074
VARCHAR65536_SQLSTATE=42000
GIANT_NUMERIC_SERVER=REJECTED
GIANT_NUMERIC_ERROR_CODE=1439
GIANT_NUMERIC_SQLSTATE=42000
COLUMN65_TABLE_EXISTS=0
VARCHAR255_TABLE_EXISTS=1
VARCHAR65535_TABLE_EXISTS=0
VARCHAR65536_TABLE_EXISTS=0
GIANT_NUMERIC_TABLE_EXISTS=0
MYSQL_ACTIVE_EXISTS=1
MYSQL_SKIPPED_EXISTS=0
MARIADB_ACTIVE_EXISTS=1
MARIADB_SKIPPED_EXISTS=0
EXECUTABLE_COMMENT_EFFECTS=PASS
DIFFERENCES=VARCHAR65535_POLICY_FALSE_ALLOW
WS52I_PYTHON_POLICY_REEXECUTED=NO
WS52JS_CONTAINER_DISPOSAL=PASS
WS52JS_VOLUME_DISPOSAL=PASS
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
MARIADB_FULL_GRAMMAR=NOT_CERTIFIED
REAL_BACKUP_IMPORT_AUTHORIZED=NO
PRODUCTION_CONTACT=NO
MIGRATION_092_EXECUTED=NO
B12_CUTOVER=OFF
#####COPY TO HERE#####
```

## 4. Differential matrix and precise meaning

| Case | MariaDB server observation | WS52-I policy relationship | Finding |
|---|---|---|---|
| 65-character column identifier | **REJECTED 1059 / 42000**, no table created | Classifier rejects >64-character name | Consistent for tested case |
| 65-character **table** identifier (WS52-JR, not repeated here) | **REJECTED 1103 / 42000**, no table created | Classifier rejects >64-character name | Consistent for tested case; **do not conflate table/column error codes** |
| `VARCHAR(255)` single column | Accepted, table exists | Classifier accepts | Consistent for tested case |
| `VARCHAR(65535)` single column | **REJECTED 1074 / 42000**, table absent | Classifier permits `1..65535` for `VARCHAR` | **CONFIRMED FALSE ALLOW under this MariaDB configuration** |
| `VARCHAR(65536)` single column | **REJECTED 1074 / 42000**, table absent | Classifier rejects size >65535 | Consistent for tested case |
| 500-digit `VARCHAR` size literal | **REJECTED 1439 / 42000**, table absent | Classifier rejects numeric width >5 | Consistent for tested case |
| MySQL executable version-comment cases | Active marker created object; intentionally skipped marker did not | Prior prototype handles only a restricted version-comment subset | Pass for these **two** effects, not all server/client rules |
| MariaDB executable version-comment cases | Active marker created object; intentionally skipped marker did not | Restricted subset | Pass for these **two** effects, not all server/client rules |

**Important qualification:** The WS52-JS script executed SQL through a MariaDB 11.4 client/server, but **did not re-execute the WS52-I Python classifier** in that fixture. The classifier side is grounded in the previously tested pinned Python source and accepted fixture corpus. The discrepancy concerns the actual single-column tested `VARCHAR(65535)`, not a demonstrated exploit, privilege escalation, schema escape or arbitrary SQL execution.

**Do not oversimplify VARCHAR:** A declared maximum of 65535 characters does not guarantee MariaDB table creation; character set, row representation, engine limits and the full column combination matter. Conversely, observing `VARCHAR(255)` succeed **once** does not prove that every combination of multiple `VARCHAR(255)` columns will be valid. The evidence does **not** establish a universally safe maximum across all SQL modes, character sets and engines. A quick change to `<=255` can be a conservative restriction for this small prototype, but is **not full compatibility certification** and must be scoped/tested before claims of success.

## 5. No-go state, exact recommended next step

**WS52-JS is CLOSED as a bounded synthetic runtime audit with one genuine compatibility mismatch.** The original terminated WS52-J and its diagnostic WS52-JR remain separate historical events; their evidence is retained and not reinterpreted as full WS52-J PASS. WS52-JS correctly continues only remaining cases.

**Proposed WS52-JT — separately authorized targeted synthetic-only fix and regression qualification:**

1. Begin a fresh implementation branch from this WS52-JS documentation commit and preserve the pinned original and production code refs and literal HARD-OFF gate. Do not mutate either protected branch.
2. Narrow `VARCHAR` declarations in the synthetic-only allowlist to an explicitly conservative bounded width such as **1..255**, *after* fixing tests that formerly expected 65535 to be allowed. Add synthetic positive cases for `VARCHAR(255)`, negative cases for `VARCHAR(256)` and `VARCHAR(65535)`, and preserve the existing long-decimal/malformed input regressions. Do not claim a 256-character declaration is invalid MariaDB SQL merely because policy refuses it.
3. Assess combined row-width growth: if multiple VARCHAR columns are still allowed, a per-column bound alone will not establish MariaDB acceptance. Either derive a conservative per-table byte/column budget with explicit engine/charset assumptions and independent testing, or deliberately limit the synthetic subset further (e.g. one VARCHAR column), with documented fail-closed reductions. An untested row-width cap cannot be called certified.
4. Re-run the **full existing WS52-I synthetic corpus** plus new targeted policy regressions against the exact tested Git blobs, then independently verify semantic diff: only authorized classifier and tests changed plus one evidence Markdown. No need to repeat already certified synthetic MariaDB version-comment effects without new evidence.
5. Maintain an independent real MariaDB/client SQL syntax oracle for selected new cases only if **separately authorized**; do not assume a classifier pass is a safe real restore permission. Do not read/import the user's production-derived backup, provision live-data restore resources or communicate with staging/production.

**Unresolved restoration blockers:** Complete effective MariaDB/client directives and SQL allowlist for the user-attested live-production-derived dump (historically **69 DROP TABLE**, plus other active SQL comments) remains uncertified; expanded database/index/temp/log size bound, Docker host physical reserve, customer data-at-rest protection, backup/snapshot retention, secure cleanup/key disposal, consistent export provenance and execution safety remain unproven. WS52-A/WS52-D synthetic privileges/storage are not real-import certification. The prior actual B12 concurrency attempt failed **TWO_STALE_COMMITS** and corrected RC01–RC14 certified passes remain **0**.

**Absolute locks:** `B12_CUTOVER=HARD_OFF`, `MIGRATION_092=NOT_AUTHORIZED`, `RESTORE_AUTHORIZED=NO`; D12 fifteen import gates BLOCKED; Stage D UNAUTHORIZED; 150 VU HOLD; 200 VU PROHIBITED. No production/staging/AWS/cPanel contact, no protected original/WS32 container access, no customer data output.

## 6. GitHub evidence-only acceptance

WS52-JS requires **one** added Markdown document on a **new** docs-only branch from verified immediate parent WS52-JR HEAD `e526bc96b8d61c0dd8325c271580003dc049a844`. Independent compare must show exactly **one commit ahead, zero behind, one added `docs/C137_GC24_K2_P03_B12_WS52JS_REMAINING_SYNTHETIC_ORACLE_EVIDENCE.md`**, with unchanged classifier/test blobs, original and production protected HEADs, HARD-OFF gate blob, later-history WS07 verifier blob. **No source, container, data, production or migration mutation** accompanies this report.
