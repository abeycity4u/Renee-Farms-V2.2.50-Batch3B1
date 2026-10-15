# C137 / GC24 / K2-P03 / B12 — WS52-JW full-dump validation feasibility and proof plan

**Date:** 2026-10-15  
**Authorization:** Design-only follow-up to WS52-JV, expressly approved by the user's "proceed". **No real backup inspection, no Docker/MariaDB or SQL execution, no customer data, no credentials, no Mac/AWS/cPanel/staging/production access, no application code changes or migrations.** Only one Markdown document on a new isolated evidence branch.
**Outcome:** `WS52JW_DESIGN_COMPLETE`, `FIRST_PROOF_PATH=A_CLIENT_EFFECTIVE_SQL_VALIDATION`, `A_IMPLEMENTATION=NOT_PROVEN`, `B_IMPLEMENTATION=NOT_PROVEN`, `SYNTHETIC_FEASIBILITY_PROOF=NOT_YET_EXECUTED`, `REAL_DUMP_VALIDATION=BLOCKED`, `WS53_IMPORT=NO_GO`, `B12_CUTOVER=HARD_OFF`.

## 1. Verified lineage and frozen synthetic checkpoint

| Reference | Pinned value |
|---|---|
| Repository | `abeycity4u/Renee-Farms-V2.2.50-Batch3B1` |
| Parent WS52-JV docs-only branch | `c137-gc24-k2p03-b12-ws52jv-import-safety-architecture-decision-source` |
| Parent SHA | `8560aa8dd5516ad4c06ce65a96578707a29423be` |
| New WS52-JW docs-only branch | `c137-gc24-k2p03-b12-ws52jw-full-dump-validation-feasibility-source` |
| Original protected B12 branch | `c137-gc24-k2p03-b12-cas-writer-cutover-source` |
| Original protected HEAD | `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af` |
| Protected production branch | `v320-renee-agrisuite-branding` |
| Protected production HEAD | `10041da3a3c4e4a7b8101f8183c243d056c7b128` |
| Hard-OFF writer gate `lib/daily_record_revision_cutover_gate.php` Git blob | `6e88c913ad5e5b371a9d0c92ce08870214ac1268` |
| Later-history WS07 verifier `scripts/verify_k2p03_b12_ws07_offline.sh` Git blob | `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb` |
| Frozen WS52-JT prototype `tools/b12_ws52f_synthetic_allowlist.py` Git blob | `c0cccf419ca78937c317f417c301fc51db002ef1` |
| Frozen WS52-JT tests `tests/test_b12_ws52f_synthetic_allowlist.py` Git blob | `bfc669f337bc39f2b5509d55e2ae2c1ad643986a` |
| Input baseline | WS51T import safety contract; WS52-E A–M gate matrix; WS52-JU closure; WS52-JV two-barrier design |

The historical WS07 verifier is **not present in the protected original B12 code HEAD**; its cited blob is available only in later evidence history. No step should alter the original to make an inaccurate HEAD:path assertion pass.

**Prior local evidence, not refreshed here:** User-attested production-derived `~/Downloads/database.sql`, 619163 bytes, mode 0600, SHA256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`, was previously read-only lexically screened at WS51S. Reported counts: 69 DROP TABLE, 69 CREATE TABLE, 65 INSERT, 69 LOCK, 69 UNLOCK, 138 conditionally embedded ALTER, 225 conditionally embedded SET, 364 versioned comments and one MariaDB sandbox directive. **These numbers are lexer-specific, not a MariaDB-11.4/client-effective authorization manifest.** Source transactional consistency, actual destructive targets, export method and expanded import footprint remain unverified. **This review neither opened nor rehashed the file.**

The WS52-JT code and tests previously passed **142 Python synthetic subcases** in an isolated environment. Actual synthetic MariaDB client/server oracles across WS52 stages checked *separately scoped samples*. WS52-JS found the VARCHAR(65535) difference under one pinned server configuration, prompting the conservative one-VARCHAR-column / 1–255 prototype policy. **Do not conflate synthetic-case counts with actual MariaDB grammar completeness**, and do not enlarge the toy parser merely to force through the real 619163-byte dump.

## 2. Choice and present feasibility verdict — not a claim of delivery

| Route | What needs to be independently proved | Known feasibility constraint | Verdict now |
|---|---|---|---|
| **A — client-effective SQL validator** | Complete, deterministic interpretation of the *specific* pinned MariaDB 11.4 client + server dialect, active conditional comments, modes, client commands, statement boundaries and all source-to-target effects. Reject any unknown and produce a confidential aggregate manifest | No qualifying full client+server parser or demonstrably complete constrained interpreter has been identified, implemented or independently tested; the frozen toy and WS51S heuristics are insufficient | **FIRST SYNTHETIC FEASIBILITY PATH / NOT YET PROVEN** |
| **B — typed, canonical data-only transformation** | Safe extraction of *all* needed typed rows **without executing untrusted dump code**; independently known schema/constraints, 1:1 row/object/column/type mapping, lossless encoding and tenancy integrity; transformed DML independently verified | A dump is still executable SQL; extracting rows/schema safely requires a trustworthy parser or equivalent trusted source, so B **does not bypass the first security barrier**. No canonical schema or verified extractor is presently evidenced | **FALLBACK RESEARCH ONLY / NOT YET PROVEN** |
| **C — raw import into fresh isolated server using schema grants alone** | Would need to prove no client-file commands, file I/O, leaks, definer/admin or unclassified SQL execute; privileges alone cannot establish this | Default schema / `--one-database` is not a security boundary; client-side commands may run before server grants enforce targets | **REJECTED AS STANDALONE** |
| **D — WS51S lexical counts or WS52-JT Python subset as full scanner** | Would need full effective SQL and real backup coverage | No full dialect support; strict 8192-byte per-input limit and finite supported SQL forms, vs historic 619163-byte dump | **REJECTED** |

**Selection is an investigation priority, not an implementation claim.** **Route A** is the preferred subject of a *separately authorized synthetic-only feasibility spike* because it preserves the dump's apparent representation and would avoid introducing an additional typed reconstruction layer. **Route B** remains a conditional alternative only when its own safe data extraction and completeness proofs can be satisfied; failing A does not automatically authorize B or a raw import. If no independently repeatable proof emerges, **STOP / NO-GO**.

### Critical logical separation

**Syntax acceptance is not an allowlist PASS.** An SQL command can be valid MariaDB grammar but forbidden (e.g. `GRANT`, schema switches, filesystem access); it can be policy-allowed by an incomplete lexer and still fail MariaDB (WS52-G/JS demonstrated this). The proposed test harness must record *both* (1) syntax/dialect outcome from a deliberately isolated MariaDB oracle when explicitly authorized and (2) source policy decision. They are separate fields, with expected MariaDB error categories verified precisely rather than treating every nonzero return as a valid negative test.

**Confinement is not permission to read the backup.** Even a never-networked MariaDB instance with schema-restricted non-root grants does not prevent data disclosure through raw parser diagnostics, local client commands, Docker backing snapshots, host logging or unsafe retention. Route A/B validation and server privileges remain **two independently validated barriers plus confidentiality controls**, not alternative one-of-two options.

## 3. Proposed WS52-JX synthetic-only proof — explicit test design, NOT authorization

The proposed proof is restricted to **fabricated SQL and known harmless marker objects**, with no original backup file, no user Mac runtime unless separately authorized, no customer records, no real import, and no protected fixture contact.

### Implementation boundary / reproducibility

1. On a new isolated evidence/prototype branch, document a **pinned evaluator implementation identity**, dependency versions/hashes and a self-contained fabricated corpus. No packages installed in the protected original B12 source checkout; no production code/branch changes.
2. Keep a **clearly separate input frontend** for MariaDB *client* directives (file commands, sandbox and delimiter state) and *server-effective* SQL grammar. Do not let an SQL-only parser claim it safely covers a client script. Prefer an explicitly forbidden-directive mode wherever possible, but separately prove the actual MariaDB client's command handling cannot bypass the declaration.
3. Source frontend must apply a fully defined version guard and SQL_MODE/charset model to active MySQL/MariaDB conditional comments, all escapes and statement boundaries. If any guard/mode/client command is unsupported => **BLOCKED**, not silently stripped. A mocked engine alone cannot certify the real client's behavior.
4. Every non-comment, non-whitespace input byte is either assigned to exactly one recognized effective token/statement/directive or rejected, including text within active conditional comments. A coverage map must detect unaccounted bytes, duplicate consumption and truncation; evidence must be hashed and summarize counts **without raw SQL**.
5. Maintain two separate, deterministic representations: (a) restricted internal statement/effect manifest with *local-only* normalized identifiers and exact effect destinations, and (b) **redacted aggregate public summary**. Never put identifiers, values, SQL fragments, DB stderr, secrets, customer rows or local backup path into GitHub or ChatGPT.
6. Independently re-run a synthetic suite against the same pinned code and corpus, reproduce byte-for-byte summary digests and expected allow/deny/effective-statement counts; reject environment/version drift.
7. Any user-origin file input path/streaming option must remain **disabled/absent in WS52-JX**. Do not build an implicit "read real backup" command into the proof or include example live paths. A future production-derived source reader requires separate scoped authorization, privacy plan and source-integrity checks.
8. If a trusted grammar/effect evaluator remains unavailable, document the gap and **end the spike as BLOCKED**. Do not simulate success by adding more toy-positive examples or claiming full-language support.

### Bounded synthetic adversarial acceptance families

The exact fabricated corpus will be specified and pinned in the *separately approved* implementation stage; this WS52-JW report creates no tests and no SQL. Coverage obligations:

| Family | Synthetically controlled behavior to prove | Accept/reject rule |
|---|---|---|
| F1: client commands and sandbox | `source`, client `\\.`, shell escapes, client delimiter commands, sandbox directive recognized separately from server syntax, nested/escaped client commands | Any file/OS/client-command behavior not specifically proven harmless => REJECT |
| F2: guard-dependent SQL | `/*!##### ... */`, `/*M!###### ... */`, future/ignored guards and misleading ordinary comments | Active effective SQL always parsed/policy checked; skipped executable branches verified as not effective; unknown guard => REJECT |
| F3: quote/escape/state | Single/doubled quotes, backticks, mixed escapes, string semicolons, SQL_MODE changes, `NO_BACKSLASH_ESCAPES`, ANSI quoting and charset changes | No mis-splitting or reinterpretation; unsupported change => REJECT |
| F4: statement boundaries | Empty, truncated, nested, long and multi-statement input, line endings, comments/literals containing semicolons | Complete deterministic parse/coverage or REJECT |
| F5: schema targeting | Unqualified vs qualified `db.table`, client default DB, `USE` redirection, schema-level DDL, multiple or quoted qualifications | Only exactly reviewed single empty target allowed; other scope => REJECT |
| F6: destructive operations | Hardcoded representative `DROP`, `CREATE`, `ALTER`, multi-target and conditional DDL | Target and side effects individually accounted; no unknown modifier or second target |
| F7: privileges and external I/O | `GRANT`, `REVOKE`, `CREATE USER`, global `SET`, FILE/OUTFILE/LOCAL INFILE, replication/admin operations | REJECT irrespective of server syntax validity |
| F8: routines, definers and indirection | VIEW, TRIGGER, EVENT, PROCEDURE, DEFINER, PREPARE/EXECUTE/dynamic SQL, expressions that could do filesystem access | REJECT unless separately formally reviewed in narrower architecture |
| F9: row data/literals | Quoted/escaped numeric/text/binary/NULL, embedded comments, multirow `INSERT` and long encoded values | No value interpolation or public emission; complete typed interpretation or REJECT |
| F10: denial and partial effects | Disallowed command in active comment, late disallowed command after many allowed statements, inconsistent SQL mode | Entire input is declared BLOCKED *before any client executes a statement*; cannot admit permitted prefix |
| F11: version/engine drift | MariaDB 11.4 vs other version guards and pinned client behavior; syntax vs policy disagreements | Version mismatch => BLOCK; retain separate oracle classifications |
| F12: resource/output robustness | Length limits, malformed input, unexpected Unicode/bytes, decimal sizes, exception and timeout paths | Fail closed and redact, no traceback/SQL leak, bounded memory/time |

**Differential acceptance:** For a separate explicitly authorized synthetic MariaDB 11.4 fixture, compare frontend-claimed client-effective operations with *actual* server-side effects in a new disposable schema. Compare object-count differences, exact expected error **codes and SQLSTATE** where material (e.g. WS52-JR observed table-length code **1103**, WS52-JS observed column-length **1059**), and selected session state. Distinguish genuinely invalid syntax from deliberate policy exclusions, as in the prior BLOB/VARCHAR observations. Identity-guarded teardown only for the *new authorized* fixture, no existing WS32 contact. **WS52-JW itself runs none of this.**

### Binary acceptance rubric for the future proof

| Gate | Exact required proof | Result at WS52-JW |
|---|---|---|
| JX1 frontend and dependency provenance | Pinned source/dependencies, no opaque fetching and no unreviewed source, independent blob/hash verification | NOT BUILT |
| JX2 total byte + directive accounting | Every fabricated input classified without unaccounted active bytes, and malformed/truncated cases rejected | NOT BUILT |
| JX3 state/version determinism | Defined client/server version, SQL_MODE and executable-comment semantics; failures stop pre-execution | NOT BUILT |
| JX4 false-allow prevention | All fabricated forbidden schema/file/global/dynamic/client-command cases rejected, including active guarded bodies | NOT BUILT |
| JX5 policy-versus-engine oracle | Independently documented selected synthetic MariaDB 11.4 comparisons, exact expected errors/effects; no fabricated inference | NEEDS SEPARATE RUNTIME APPROVAL |
| JX6 no partial execution on denial | Evaluator cannot stream or execute an allowed prefix before seeing a later forbidden operation; any unknown => preflight FAIL | NOT BUILT |
| JX7 redaction/privacy | No SQL, identifiers, values, secrets or raw error contents in output, repo, logs or exceptions | NOT BUILT |
| JX8 reproducible independent audit | Exact corpus/source fingerprints, deterministic manifest and unchanged original/production SHA + remote semantic diff | NOT BUILT |
| JX9 full dump viability | Evidence that the selected architecture *could* account for required statement classes, not merely the current toy subset | **UNPROVEN; STOP IF UNRESOLVED** |

**No combination of JX synthetic PASS flags alone grants real-backup compatibility or import approval.** A real-backup read and complete manifest would remain a later sensitive-data gate.

## 4. Proposed redacted manifest schema, confidentiality constraints

All fields below are **categories or cryptographic fingerprints**, never SQL strings or names. Author an explicit JSON schema only in a separately approved implementation stage. Suggested summary fields:

```text
AUDIT=B12_EFFECTIVE_SQL_VALIDATION
INPUT_CLASS=SYNTHETIC | SENSITIVE_LOCAL   # SENSITIVE_LOCAL not enabled in JX
SOURCE_IDENTITY_MATCH=YES | NO | NOT_CHECKED
CLIENT_VERSION_PIN=HASH_OR_ENUM
SERVER_VERSION_PIN=HASH_OR_ENUM
PARSER_IMPLEMENTATION_SHA256=HASH
FIXTURE_CORPUS_SHA256=HASH
MANIFEST_SHA256=HASH
INPUT_BYTE_ACCOUNTING=PASS | BLOCKED
ACTIVE_SQL_STATEMENT_COUNT=NONNEGATIVE_INTEGER
APPROVED_EFFECTIVE_COUNTS_BY_CLASS=AGGREGATE_ONLY
PROHIBITED_EFFECTIVE_COUNT=NONNEGATIVE_INTEGER
UNKNOWN_EFFECTIVE_COUNT=NONNEGATIVE_INTEGER
CLIENT_DIRECTIVE_POLICY=PASS | BLOCKED
VERSIONED_COMMENT_POLICY=PASS | BLOCKED
SQL_MODE_STATE_POLICY=PASS | BLOCKED
TARGET_SCHEMA_SCOPE=PASS | BLOCKED | NOT_TESTED
ERROR_DETAILS_SUPPRESSED=YES
SQL_EXECUTED=NO | SYNTHETIC_ONLY | SEPARATELY_AUTHORIZED_REAL
RESULT=PASS_SYNTHETIC_ONLY | BLOCKED
REAL_BACKUP_IMPORT_AUTHORIZED=NO
B12_CUTOVER=OFF
```

Do not emit actual backup SHA, path or internal names in a public manifest unless explicitly reviewed as safe metadata; prior SHA is historical evidence here, not the key to authorize a new read. Hashes do **not** prove independent review or SQL safety. Counters must be derived from a trusted implementation with total input-byte accounting; the WS51S regex counts cannot simply be copied into a PASS summary.

For route B, additionally specify and independently verify synthetic source-to-output **schema fingerprint**, typed column inventory, value/NULL/charset preservation, primary/foreign key compatibility, **per-table row-count vector held privately**, aggregate cardinality equality, missing/duplicated records, tenant-scope preservation, deterministic ordering where relevant, and lossless round-trip checks. None has been performed. Do not mistake aggregate row-count agreement for transactional snapshot consistency.

## 5. Separate host/data confidentiality attestation design (no host probe now)

The source backup may contain credentials and tenant/customer information. Data security is an independent **P0 stop gate** even if route A/B syntax validation becomes feasible.

| Control | Future bounded, separately approved host metadata assertion | If unavailable |
|---|---|---|
| Disk encryption | Active host-at-rest encryption and Docker backing/volume encryption including scratch/swap; key custody documented without exposing keys | BLOCK |
| Snapshot / backup retention | Enumerate backup/sync/snapshot coverage for Docker disk image and temporary files; prove safe exclusion or per-fixture encrypted key with finite retention | BLOCK |
| Storage budget | *Current* physical host free, backing growth limits, Docker guest free bytes+inodes and credible expanded table/index/temp/redo/undo/log worst-case plus margin; don't add host+guest readings | BLOCK |
| Data transfer | Preapproved local-only one-way stream, zero backup host mounts to container, no cloud/network or terminal SQL echo; zero direct file input in synthetic proof | BLOCK |
| Credentials | Strong ephemeral non-root scoped socket import identity, no reused production credentials, no argv/env/history/log/inspect secret leak | BLOCK |
| Log and error redaction | Safe numeric error classes, fail-closed unexpected exception path, no rows/table names/SQL in logs, traces or copy-back | BLOCK |
| Retention and erasure | Written partial-DDL quarantine and retention, explicit disposal permission, encrypted-key erasure or accepted residual encrypted backups; `docker volume rm` alone is not secure erase proof | BLOCK |
| Historical snapshot claims | Verify original coherent export or accept limited historical rehearsal with explicit inconsistency caveat; no inferred cross-table proof | UNVERIFIED |

**WS52-JW checks none of these systems.** A future metadata inventory should have an exact scope, user permission and safe evidence schema. Neither old guest `212.98 GiB` nor Mac `166.80 GiB` figures establish current capacity; they cannot be summed. Backup file size (about 619 KB SQL text) does **not** bound its expanded storage demand. Avoid invented multipliers, dates or release estimates.

## 6. Decision gate sequence and runnable continuation without automatic authorization

**Disposition:** `WS52JW_DESIGN_COMPLETE`, `FIRST_NEXT_FEASIBILITY_PROOF=ROUTE_A`, `ROUTE_B_FALLBACK_UNPROVEN`, `SYNTHETIC_PROTOTYPE_PHASE_FROZEN`, `REAL_BACKUP_READ=NOT_AUTHORIZED`, `RESTORE=NO_GO`. Don't restart WS52-F through WS52-JT QA unless a *specific* new regression appears.

**Proposed WS52-JX (NOT authorized by WS52-JW):** Implement a limited independently auditable **synthetic-only** proof of an *actual client-aware effective-statement frontend* (not another extension of the 8192-byte toy). Start with a version-pinned, offline corpus and a mandatory STOP on unrecognized client commands, SQL modes or conditional syntax. Evaluate the candidate's feasibility against JX1–JX9 and report **NOT VIABLE** if grammar completeness or independent oracle cannot be established. Scope no SQL database or Docker run by default; if selected synthetic client/server checks are needed they require explicit additional scoped approval before that runtime. No real backup access, protected code changes or container creates are implied.

**In parallel only with separate consent:** inventory host encryption, Docker backing backup/snapshot retention, metadata-only current storage constraints; do not retrieve backup contents. This is not a shortcut for JX parser proof.

**After JX proves a trustworthy route, and *only* after privacy and capacity gates independently close:**
1. Seek specific authorization for **read-only sensitive actual-backup classification** on the Mac, with fresh owner/mode/no-symlink/hash guard and sanitized aggregate manifest; no file upload or SQL execution. If any unknown => BLOCK.
2. Separately authorize creation of a new, network-disabled, strongly authenticated **real-data-capable** MariaDB fixture with an empty target and schema-scoped non-root importer, plus bounded synthetic denied-operation checks; don't use WS32 or synthetic blank root.
3. Obtain a further distinct **WS53** user approval for the real production-derived data stream and execution, referencing the actual checked backup fingerprint, 69 historical DROP risk, target identity, encryption, storage, error suppression, failure-quarantine and disposition. Creating a fixture does not authorize copying/importing records.
4. Obtain separate **WS54** permission for sanitized aggregate post-import checks, confidential retention and verified disposal. This does not certify corrected B12 concurrency.
5. Independently conduct the corrected real MariaDB **B12 RC01–RC14** concurrency certification only under a separate authorized test plan. The older test failed `TWO_STALE_COMMITS`; the corrected real pass count remains **0**. A synthetic or historic rehearsal never justifies turning the CAS writer on.

**Explicit design fork:** If B12's intended RC certification can be done wholly with fabricated data, separately consider prioritizing it before any real backup exposure. This may accelerate the actual correctness goal; it only replaces a historical-restore prerequisite if the project owner expressly approves a revision of the acceptance contract. No stage in WS52-JW authorizes that change.

**Standing locks:**
```text
B12_CUTOVER=HARD_OFF
MIGRATION_092=NOT_AUTHORIZED
D12_IMPORT_GATES=15_BLOCKED
STAGE_D=UNAUTHORIZED
150_VU=HOLD
200_VU=PROHIBITED
WS53_REAL_BACKUP_IMPORT=NOT_AUTHORIZED
WS54_REAL_DATA_VALIDATION_DISPOSAL=NOT_AUTHORIZED
PRODUCTION_CONTACT=NO
```

## 7. Source-integrity and GitHub evidence contract

Create **only** `docs/C137_GC24_K2_P03_B12_WS52JW_FULL_DUMP_VALIDATION_FEASIBILITY_PROOF_PLAN.md` on a new evidence-only successor branch from `8560aa8dd5516ad4c06ce65a96578707a29423be`. After writing, GitHub remote compare must report **one commit ahead, zero behind, exactly one added Markdown** and no modified source, test, dependency, migration, schema, application or deployment files. Independently recheck protected B12/production HEADs, hard-OFF gate blob, later-history WS07 blob and frozen WS52-JT tool/test hashes. **This docs-only review does not re-attest the user's local Mac checkout or Docker inventory.**

**Final decision:** the engineering proof path has been selected and its falsifiable acceptance contract defined; **no real-backup safety gate has been operationally discharged**. Continue only with a separately approved bounded synthetic implementation feasibility proof or another explicit high-priority gate. Real backup import and B12 cutover remain **NO-GO**.
