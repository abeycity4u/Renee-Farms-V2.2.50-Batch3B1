# C137 / GC24 / K2-P03 / B12 — WS52-I independent synthetic parser review and targeted corrections

**Date:** 2026-10-15  
**Outcome:** `WS52I_TARGETED_PARSER_HARDENING_PASS_SYNTHETIC` — **not** independent MariaDB 11.4 whole-grammar certification, full executable SQL allowlist, actual-dump compatibility or authorization to import production-derived data. **B12 cutover remains HARD-OFF.**

## Scope / user authorization

The user explicitly approved **WS52-I**: independently review the corrected WS52-H synthetic-only classifier, including identifier/backtick escaping, executable-comment and SQL-mode assumptions, and **fix narrowly demonstrated defects** on a new isolated implementation branch. WS52-I did **not** authorize access to `~/Downloads/database.sql`, any other customer data, MariaDB/Docker SQL execution, container/volume changes, SQL migration 092, staging, production, cPanel, AWS or deployment. This stage was performed against the **pinned GitHub WS52-H source/test blob bytes** copied into the assistant's own isolated Python workspace, **not** the user's Mac.

### Repository lineage and immutable references

| Ref | Verified SHA/blob |
|---|---|
| Repository | `abeycity4u/Renee-Farms-V2.2.50-Batch3B1` |
| Parent branch | `c137-gc24-k2p03-b12-ws52h-backtick-identifier-correction-source` |
| Parent HEAD | `cbedc91b8617dd7dbd7b9b78ef913b46cd7a6a86` |
| New isolated WS52-I branch | `c137-gc24-k2p03-b12-ws52i-identifier-length-numeric-cr-hardening-source` |
| Protected original B12 source branch HEAD | `c137-gc24-k2p03-b12-cas-writer-cutover-source` at `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af` |
| Protected production code branch HEAD | `v320-renee-agrisuite-branding` at `10041da3a3c4e4a7b8101f8183c243d056c7b128` |
| Literal B12 HARD-OFF gate | `lib/daily_record_revision_cutover_gate.php` blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268` |
| Historical WS07 verifier in **later** evidence history | `scripts/verify_k2p03_b12_ws07_offline.sh` blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb` |
| WS52-H parent source | `tools/b12_ws52f_synthetic_allowlist.py` blob `088e2b3ce0d859fb7a53c0607c95d8c909044099` |
| WS52-H parent tests | `tests/test_b12_ws52f_synthetic_allowlist.py` blob `b9d07daea23e22550f219fba788f08fcc7f8fb19` |

**Historical WS07 caution:** The runner exists only in **later evidence history**, not at the original B12 code branch's pinned HEAD; WS51R corrected the prior wrong-ref assertion.

## Independently reproduced findings before remediation

The assistant's isolated Python test directory `/mnt/data/ws52i_isolated` received a copy of the immediately previous WS52-H source/test files. Git blob hashing matched both pinned WS52-H values **before any patch**. Only fabricated SQL input strings were used.

| Probe, synthetic input | Unpatched WS52-H observed | Assessment |
|---|---|---|
| A backtick-quoted 65-character table identifier | `ALLOW_1` | **False-allow against MariaDB's documented 64-character table identifier length** (not freshly MariaDB-server-tested) |
| A backtick-quoted 65-character column identifier | `ALLOW_1` | **False-allow against MariaDB's documented 64-character column identifier length** (not freshly MariaDB-server-tested) |
| A 5,000-digit `VARCHAR(...)` size literal | Unhandled Python **ValueError**, not controlled `Rejected` | **Proven fail-closed exception contract defect** caused by unrestricted decimal conversion |
| A carriage return between an SQL line comment and a trailing statement | `ALLOW_1` | **Potential lexer/client newline desynchronization**; exact MariaDB carriage-return behavior was **not** checked. Reject ambiguity conservatively |

These are local Python behavior observations, not claims that an arbitrary SQL payload ran on a database. This is a static security/code review plus synthetic runtime checking, **not** comprehensive external security assurance.

## Minimal surgical correction on the new isolated branch

1. In `tools/b12_ws52f_synthetic_allowlist.py`, restrict existing `SAFE_NAME` from unlimited `[A-Za-z0-9_]*` to `[A-Za-z0-9_]{0,63}`, retaining the required initial ASCII letter/underscore. This permits 1–64-character ASCII backtick-quoted names but rejects longer names. This is intentionally narrower than complete MariaDB identifier rules.
2. Before converting the `VARCHAR` size token to an integer, reject decimal tokens **longer than five ASCII digits**; keep existing `1..65535` policy range. This prevents the Python 4,300-digit integer conversion exception; values beyond the bounded numeric representation return the ordinary non-sensitive `Rejected("UNSUPPORTED_OR_UNSAFE_SYNTHETIC_SQL")`. Leading-zero numeric tokens longer than five digits are intentionally rejected even if their eventual integer could be within bounds.
3. Reject all ASCII carriage returns `\r` (including CRLF) at the existing top-level input control filter. The lexer still permits `\n` and `\t`; refusing CR avoids guessing how all MariaDB clients/servers tokenize line comments across CR variants. This is a **conservative policy restriction**, not proof of an exploitable CR behavior in MariaDB.
4. Add one focused regression method in `tests/test_b12_ws52f_synthetic_allowlist.py` with **two permitted 64-character identifiers** plus **nine expected `Rejected` cases**: 65-character table/column names, three oversized decimal-size forms, bare CR in a line comment, CRLF after a line comment, CR within a string literal and trailing CR. Explicitly assert that the exception's message is the constant non-sensitive rejection string.
5. Do not broaden the SQL subset, disable the WS52-H backtick requirement, add a reserved-keyword blacklist, parse real customer backup files, add file/CLI input options, or touch any application/production route. All prior allowed/rejected synthetic fixtures remain unchanged.

## Test evidence: exact corrected source and test blobs

The final tested local files match the exact Git object identifiers used when committed on WS52-I:

| File | New tested blob SHA |
|---|---|
| `tools/b12_ws52f_synthetic_allowlist.py` | `d59ab164d81749ad1be09ae4ad6cd15d8e2be839` |
| `tests/test_b12_ws52f_synthetic_allowlist.py` | `2e0644ecbbdb78de315f32a99a5f3536e4276022` |

Isolated local synthetic test commands:

```bash
python3 -B tools/b12_ws52f_synthetic_allowlist.py --self-test
python3 -B -m unittest discover -s tests -p 'test_b12_ws52f_synthetic_allowlist.py' -v
git hash-object tools/b12_ws52f_synthetic_allowlist.py tests/test_b12_ws52f_synthetic_allowlist.py
```

The smoke test printed `RESULT=PASS`, `REAL_BACKUP_ACCESS=NO`, `SQL_EXECUTED=NO`, `MARIADB_GRAMMAR_CERTIFIED=NO`, `B12_CUTOVER=OFF`; the unittest run passed **all four test methods** with zero errors or failures in about 0.011 seconds. The full synthetic contract now covers **32 prior expected-allow plus 2 new boundary allows = 34 allows**; **79 prior expected-reject plus 9 new boundary rejects = 88 rejects**; and the **six** unchanged invalid/oversized input-contract cases. Total **128 synthetic subcases**. This is not 128 independent MariaDB oracle cases; all were executed entirely by Python without connecting to MariaDB.

**Repository integrity:** Both source and test files were modified on a new isolated WS52-I branch and verified by comparing the remote Git content blob SHA to the **previously tested local bytes**, before documentation was added. The protected original and production branches must be checked again after recording this evidence.

## Still not certified — fail closed

- This classifier implements a tiny syntactic subset. It is **not** MariaDB's dialect-aware parser and has not been qualified against the complete SQL language, executable MySQL/MariaDB conditional comments, all SQL modes, real client metacommands, full identifier semantics, charsets, collations, schemas, types or engine constraints. The WS52-G oracle covered only previously selected synthetic cases; **no MariaDB server oracle was run in WS52-I**.
- The finite `MAX_BYTES=8192` and token/statement limits bound this prototype's input surface; they cannot establish actual-backup compatibility, whose last pinned local file size was **619163 bytes** and SHA256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`. **That backup was not opened or rehashed**.
- WS51S found 69 `DROP TABLE` statements in the user-attested production-derived SQL. A safe future historical import still requires independently proven effective SQL/client directive allowlist **and** a new network-disabled, empty target schema with a restricted non-root importer. WS52-I changes do not satisfy either real-import gate.
- The previous WS52-A privilege and WS52-D MariaDB/guest-capacity test fixtures were *synthetic only and disposed*. Guest `212.98 GiB` free, 15,252,481 free inodes and Mac host `166.80 GiB` are from WS52-D and **not additive**; expanded actual restore footprint remains unknown.
- At-rest encryption, backup/snapshot retention, confidential error handling, rollback, disposal/key erasure and coherent original production export snapshot remain uncertified.
- B12's prior real MariaDB concurrency failed `TWO_STALE_COMMITS`; corrected RC01–RC14 certified pass count remains **zero**. D12's 15 import gates remain BLOCKED; Stage D not authorized; 150 VU HOLD, 200 VU prohibited; migration 092 unauthorized; B12 writer cutover HARD-OFF.

## Next controlled decision (not authorized by WS52-I)

Recommend **WS52-J independent synthetic-only differential qualification of the corrected subset** against *only newly selected cases* on an independently approved disposable MariaDB fixture, or a separate **design-only** review of executable-comment and session SQL mode behavior before requesting such infrastructure. The immediate validated corrections remove the identified local hazards, but they do not make the SQL tool safe for real customer data. No user backup read, data stream, server provisioning, production contact, migration or cutover is implied.

### GitHub semantic-diff acceptance

Compare `c137-gc24-k2p03-b12-ws52h-backtick-identifier-correction-source` SHA `cbedc91b8617dd7dbd7b9b78ef913b46cd7a6a86` to new WS52-I branch. Expect **only three paths changed**: two modified `tools/b12_ws52f_synthetic_allowlist.py` and `tests/test_b12_ws52f_synthetic_allowlist.py`, plus **this single added Markdown** `docs/C137_GC24_K2_P03_B12_WS52I_INDEPENDENT_SYNTHETIC_PARSER_REVIEW.md`. Require `behind_by=0`, positive number of forward commits, zero application source changes, and recheck original/production protected HEADs, HARD-OFF gate, WS07 historical verifier and exact final tool/test blobs. **WS52-I must not be merged into protected branches by implication.**
