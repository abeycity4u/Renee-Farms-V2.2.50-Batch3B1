# C137 / GC24 / K2-P03 / B12 — WS52-JT targeted conservative VARCHAR policy

**Date:** 2026-10-15.  
**Outcome:** `WS52JT_SYNTHETIC_VARCHAR_POLICY_FIX_PASS`; **not** an import allowlist certificate, complete MariaDB 11.4 grammar certification, real-backup import permission, deployment approval, or B12 cutover approval. **B12 remains HARD-OFF.**

## Scope, authorization and predecessor evidence

The user explicitly authorized WS52-JT to address the **specific compatibility false-allow** observed in the WS52-JS networkless synthetic MariaDB 11.4 oracle: `CREATE TABLE ... VARCHAR(65535)` was **rejected by MariaDB with error 1074 / SQLSTATE 42000**, although the WS52-I prototype accepted that length. In the same bounded oracle, `VARCHAR(255)` was accepted. WS52-JS performed 12 expected bounded synthetic SQL invocations, accepted/rejected cases with sanitized metadata, demonstrated the targeted discrepancy, removed its temporary fixture and reported original Docker resources unchanged. The WS52-JS **evidence-only** parent branch, not production code, is the WS52-JT base.

**WS52-JT is strictly synthetic-only:** New source/test changes are limited to the standalone prototype and its deterministic Python fixtures. No actual customer backup was opened, copied, parsed, hashed or imported; no MariaDB or Docker operations were used for this fix; no production/staging/AWS/cPanel contact and no live schema change occurred. The completed WS52-JS MariaDB oracle was **not rerun** without reason. The original historical backup and all protected B12 databases/resources remain unaccessed in this stage.

| Pinned reference | Value |
|---|---|
| Repo | `abeycity4u/Renee-Farms-V2.2.50-Batch3B1` |
| Immediate parent evidence branch | `c137-gc24-k2p03-b12-ws52js-remaining-differential-evidence-source` |
| Parent SHA | `22118e7253a08f800222f0b0eaf2c66b53af68de` |
| New isolated implementation branch | `c137-gc24-k2p03-b12-ws52jt-conservative-varchar-policy-source` |
| Original protected B12 code branch | `c137-gc24-k2p03-b12-cas-writer-cutover-source` |
| Original protected B12 SHA | `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af` |
| Production branch | `v320-renee-agrisuite-branding` |
| Production SHA | `10041da3a3c4e4a7b8101f8183c243d056c7b128` |
| Literal hard-OFF cutover gate `lib/daily_record_revision_cutover_gate.php` | Blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268` |
| Historical WS07 verifier `scripts/verify_k2p03_b12_ws07_offline.sh` | Blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb` (**only present in later evidence history**, not original B12 code HEAD) |
| Parent WS52-I source blob | `d59ab164d81749ad1be09ae4ad6cd15d8e2be839` |
| Parent WS52-I tests blob | `2e0644ecbbdb78de315f32a99a5f3536e4276022` |

## Minimal correction

In `tools/b12_ws52f_synthetic_allowlist.py`, change **only the `CREATE TABLE` VARCHAR subset**:

- Restrict synthetically classified VARCHAR length to **1–255 ASCII decimal digits**, with at most **three** digits of literal text, retaining the existing bounded-conversion protection. Examples of intentionally forbidden expressions: `VARCHAR(256)`, `VARCHAR(65535)`, `VARCHAR(0001)`, `VARCHAR(0)`. A conservative synthetic policy rejection is **not** a statement that every rejected declaration is invalid MariaDB SQL.
- Track `varchar_columns` inside each `CREATE TABLE` statement and fail closed if **more than one VARCHAR column** is declared in the statement. This bounds an important source of *aggregate* VARCHAR row-width growth instead of relying only on per-column maximum. Existing `INT` columns remain supported under pre-existing statement, token and byte limits. This still does not establish an InnoDB row-size guarantee across every charset, engine, indexing or storage setting.
- Preserve the WS52-H backtick-quoted table/column identifier requirement, 64-character ASCII identifier cap, no carriage returns, restrictive active versioned comments, SQL-mode allowlist, file/command/schema boundaries and literal semicolon checks. No product PHP, SQL migrations, original C137/WS32 resources or production code was edited.

In `tests/test_b12_ws52f_synthetic_allowlist.py`, keep every pre-existing fixture (32 allowed, 79 rejected, 6 invalid input-contract, plus 2 accepted and 9 rejected identifier/lexer regressions); add one focused `test_conservative_varchar_policy` method with **4 permitted** and **10 disallowed** new synthetic cases covering 1, 255, 256, 65535, 65536, padded numerals, zero length, multiple VARCHAR columns and a mixed INT/VARCHAR table.

## Exact tested source/test bytes and reproducibility

The prior WS52-I source and test files were verified by `git hash-object` in the assistant's **isolated synthetic Python test workspace** before copying. The modified files were tested directly in `/mnt/data/ws52jt_isolated` with no external network, customer data, database, Docker or SQL-client contact. Subsequently, the GitHub connector updated the same isolated WS52-JT branch using byte-identical replacements; new remote blob SHAs matched the locally tested SHA objects.

| File | Exact locally tested and GitHub-confirmed blob |
|---|---|
| `tools/b12_ws52f_synthetic_allowlist.py` | `c0cccf419ca78937c317f417c301fc51db002ef1` |
| `tests/test_b12_ws52f_synthetic_allowlist.py` | `bfc669f337bc39f2b5509d55e2ae2c1ad643986a` |

Commands from the isolated test directory (with no real dump or external inputs):

```bash
git hash-object tools/b12_ws52f_synthetic_allowlist.py tests/test_b12_ws52f_synthetic_allowlist.py
python3 -B tools/b12_ws52f_synthetic_allowlist.py --self-test
python3 -B -m unittest discover -s tests -p test_b12_ws52f_synthetic_allowlist.py -v
```

The standalone smoke test printed `RESULT=PASS`, `REAL_BACKUP_ACCESS=NO`, `SQL_EXECUTED=NO`, `MARIADB_GRAMMAR_CERTIFIED=NO`, `B12_CUTOVER=OFF`. The Python `unittest` suite reported **5 methods, all PASS**, zero errors/failures, about 0.010 s. Explicit synthetic subcases:

| Category | Cases |
|---|---:|
| Existing expected allow cases | 32 |
| Existing expected reject cases | 79 |
| Existing invalid-input contract | 6 |
| WS52-I boundary accepts/rejects | 2 + 9 |
| New WS52-JT VARCHAR policy accepts/rejects | 4 + 10 |
| **Total executed deterministic synthetic subcases** | **142** |

**Do not call this '142 MariaDB server tests'.** WS52-JT only executed the standalone Python classifier; the specific MariaDB oracle findings come from prior separately approved WS52-JS.

## Important residuals and completion criteria

- A single VARCHAR(255) with some INT columns passed the **synthetic classifier**; the only pinned actual MariaDB WS52-JS successful test used **a single VARCHAR(255) column**. It did **not** certify all allowed combinations of INT columns and VARCHAR under every engine, charset, SQL mode, index or row format. The restricted parser still may false-allow other syntactically shaped statements; it does not substitute for MariaDB/client-aware full statement parsing.
- The prototype cannot process the real production-derived SQL backup: limited SQL grammar, max `MAX_BYTES=8192` and `MAX_TOKENS=512`, rejects many valid real dump constructs, and does not implement complete client directives/executable-comment behavior. It is **not authorized to read real SQL backup files**.
- Prior WS51S's limited static audit reported 69 destructive `DROP TABLE` directives in a user-attested production-derived backup (previous pinned size 619163 bytes and SHA256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`). The backup was **not** read or rehashed by WS52-JT. Exact full-dump effective SQL/client allowlist, target schema isolation, import privileges, expanded footprint, backup consistency, encryption, retention, secure disposal/key erasure, rollback, and independently authorized real-data inspection/import remain **blocked**.
- Separate B12 concurrency requirements are not discharged by any parser test: original `TWO_STALE_COMMITS` failure; corrected real MariaDB RC01–RC14 certified pass count **0**. D12 fifteen import gates blocked, Stage D unauthorized, 150 VU hold, 200 VU prohibited.
- `B12_CUTOVER=HARD_OFF`, `MIGRATION_092=NOT_AUTHORIZED`, `RESTORE_AUTHORIZED=NO`, `PRODUCTION_CONTACT=NO`. The original code/production protected branches must remain untouched until separately authorized.
- **Next possible step (not authorized here):** WS52-JU documentation-only synthetic classifier closure/release-evidence matrix and residual-risk inventory, or independent limited dialect-conformance review of any newly identified false allows. Neither a full real-backup scan nor a production rollout automatically follows.

## Remote semantic-diff contract

Compare WS52-JT branch against pinned WS52-JS parent `22118e7253a08f800222f0b0eaf2c66b53af68de`; expected **two modified files** (`tools/b12_ws52f_synthetic_allowlist.py`, `tests/test_b12_ws52f_synthetic_allowlist.py`) and **this single added Markdown report** (`docs/C137_GC24_K2_P03_B12_WS52JT_CONSERVATIVE_VARCHAR_POLICY.md`), `ahead_by>0`, `behind_by=0`; all protected original, production, hard-OFF gate and later-history WS07 blobs stable; final tool/test blobs exactly equal above locally tested values. Never merge this branch into protected production or original B12 by implication.
