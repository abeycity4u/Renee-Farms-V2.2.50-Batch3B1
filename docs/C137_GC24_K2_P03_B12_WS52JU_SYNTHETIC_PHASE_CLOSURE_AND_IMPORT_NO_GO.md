# C137 / GC24 / K2-P03 / B12 — WS52-JU synthetic-classifier phase closure and import NO-GO decision

**Review date:** 2026-10-15  
**Outcome:** `WS52JU_SYNTHETIC_PHASE_REVIEW_COMPLETE`; `SYNTHETIC_PROTOTYPE_PHASE=CLOSED_WITH_LIMITATIONS`; `ACTUAL_BACKUP_ALLOWLIST=NOT_CERTIFIED`; `HISTORICAL_RESTORE=NO_GO`; `B12_CUTOVER=HARD_OFF`.

This is a **docs-only**, independently reviewable evidence reconciliation of the previously authorized WS50–WS52-JT work. It does **not** certify MariaDB grammar, scan or upload any historical database dump, access the user's Mac/Docker, execute SQL, restore databases, run production tests, authorize migration 092, merge application code or change a protected GitHub branch. No new runtime evidence was generated in WS52-JU.

## 1. Provenance, protected boundaries and scope

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Parent, previously verified WS52-JT synthetic source branch: `c137-gc24-k2p03-b12-ws52jt-conservative-varchar-policy-source`; HEAD `cc949f5491b033c5ff79cf2ab4581eb2d6995315`.
- New **documentation-only** WS52-JU successor branch: `c137-gc24-k2p03-b12-ws52ju-synthetic-closure-decision-source`. Only this Markdown may be added.
- Original protected B12 code: branch `c137-gc24-k2p03-b12-cas-writer-cutover-source`, HEAD `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Protected production code: `v320-renee-agrisuite-branding`, HEAD `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- Literal writer hard-OFF gate: `lib/daily_record_revision_cutover_gate.php` Git blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`.
- Later-history offline WS07 verifier `scripts/verify_k2p03_b12_ws07_offline.sh` Git blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`. **This file is absent in the original B12 code commit**; rechecking the later evidence history is valid, claiming it is present at the protected original commit is invalid.
- Final standalone subset classifier `tools/b12_ws52f_synthetic_allowlist.py` Git blob `c0cccf419ca78937c317f417c301fc51db002ef1`.
- Final deterministic synthetic regression suite `tests/test_b12_ws52f_synthetic_allowlist.py` Git blob `bfc669f337bc39f2b5509d55e2ae2c1ad643986a`.
- Last authoritatively recorded original SQL backup identity, from **prior user-authorized WS51S local read-only screening** (NOT checked in WS52-JU): `~/Downloads/database.sql`, production-derived by user attestation, **619163 bytes**, mode **0600**, SHA256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`. Export provenance and transactional/global snapshot consistency were **not independently established**.
- The three protected running original containers + four named original volumes inventory includes original network-disabled `renee-b12-ws32-mariadb114-stopped` (**actually running**) and volume `renee-b12-ws32-datadir`. Reported identity preservation belongs to the respective earlier local synthetic audits; WS52-JU did not query Docker.

**Authorization boundary:** The user's “proceed” approved this previously recommended **WS52-JU documentation-only closure**. No customer data file, shell, SQL/client, MariaDB container, WS32, production environment or backup operation was authorized by that instruction. No real backup contents, source table names, credentials, synthetic passwords, raw SQL error messages or customer records are included here.

## 2. What was actually accomplished — evidence by stage

| Stage / type | Verified outcome | Scope limitation |
|---|---|---|
| WS50, WS51R, WS51S, WS51T — preflight and import design | Backup source identity and prior read-only lexical counts, Docker/source identity controls, independent import safety contract | Neither trustworthy effective SQL grammar nor safe live-data restoration has been proved |
| WS51S — real backup **read-only lexical screen** | 69 `DROP TABLE`, 69 `CREATE TABLE`, 65 `INSERT`, 69 `LOCK` and 69 `UNLOCK`, 138 conditional `ALTER`, 225 conditional `SET`, 364 versioned comments, one MariaDB sandbox directive, zero scanner-specific lexical flags | These are **limited tokenization counts**, not exhaustive MariaDB/client-command classification; all 69 drops are destructive if misdirected |
| WS52-A — **synthetic** privilege isolation | 11 SQL client calls, target schema operations and 5 forbidden actions denied with expected permission errors, unrelated sentinel intact, disposable fixture removed | No restricted importer has been established for any real-data restore; its blank-root-password bootstrap belongs only to a network-disabled disposable synthetic server |
| WS52-B/C — feasibility and ephemeral prototype | Offline parser option gap identified; corrected ephemeral Python subset matched **58 expected synthetic cases** | Not a reusable grammar-complete parser; later WS52-F introduced a separately versioned runnable prototype |
| WS52-D — **synthetic** actual MariaDB 11.4 oracle/space | 18 SQL calls, 12 expected accepts + 6 expected 1064 syntax errors; guest 212.98 GiB free and 15,252,481 inodes, Mac host physical ~166.80 GiB before | Fixture deleted; Docker guest free and host free cannot be added; real restore expanded footprint unknown |
| WS52-E — readiness decision | Formal A–M gate matrix: real-backup restoration **NO-GO** | Documentation, not operational authorization |
| WS52-F — reproducible synthetic standalone classifier | GitHub-pinned Python tool and fixtures; first corpus 30 expected permits, 67 expected rejections, 6 invalid inputs | Subset, not SQL dump parser; no file/stdin/backup CLI option and no SQL execution path |
| WS52-G — isolated MariaDB differential oracle | Unquoted reserved `SELECT`/`ORDER` false-allow identified, plus deliberately valid-but-excluded BLOB case; fixture removal/inventory PASS | Actual MariaDB grammar only checked for selected samples |
| WS52-H — targeted source correction | Require backtick-quoted identifiers, add reserved-name regression; synthetic tests PASS | Other MariaDB syntax still not certified |
| WS52-I — focused source hardening | Bound ASCII quoted identifier to 64 characters, bounded decimal parsing before `int()`, reject carriage returns; synthetic tests PASS | Carriage-return difference was a conservative ambiguity, not independently demonstrated MariaDB exploit |
| WS52-J — bounded MariaDB oracle | **BLOCKED** at 65-character table error-code assertion after four successful positive calls; fixture cleanup and originals PASS | Original failed-stage reporting was overwritten by cleanup; not a full oracle PASS |
| WS52-JR — narrow new MariaDB diagnostic | 65-character **table name** rejected with numeric **1103**, SQLSTATE `42000`, object absent; 64-character control exists; fixture cleanup PASS | Corrected an **oracle expectation**, not classifier source; do not globally map all 65-character identifiers to 1103 |
| WS52-JS — remaining MariaDB differential oracle | 12 SQL calls; 65-character **column** rejected with **1059**; `VARCHAR(255)` accepted; `VARCHAR(65535)` rejected with **1074**; `VARCHAR(65536)` rejected **1074**; giant numeric rejected **1439**; specified MySQL/MariaDB active/ignored comment effects PASS; disposal PASS | Demonstrated exactly **one VARCHAR false-allow under this server configuration**, not every possible row/column or SQL-mode behavior |
| WS52-JT — synthetic-only targeted correction | One VARCHAR column maximum per CREATE TABLE; `VARCHAR(1..255)` declared length only, at most 3 ASCII decimal characters; five `unittest` methods PASS, smoke PASS, **142 deterministic synthetic subcases**; remote source/test Git blobs matched tested bytes | `VARCHAR(256)` may be valid MariaDB SQL yet is deliberately rejected. Restriction doesn't certify all permitted aggregate INT+VARCHAR row formats |

**No broad test rerun is warranted merely to close documentation.** Independent synthetic oracles remain narrowly scoped. Do not mix the 142 offline policy cases with actual MariaDB client/server invocation counts, or combine results from distinct disposed fixtures as though they comprised a single coherent restore.

### Final WS52-JT 142-case accounting (Python only)

- Original WS52-H corpus carried through: **32 allowed**, **79 rejected** = 111.
- Original bad-input contract: **6**.
- WS52-I length/numeric/CR regression: **2 allowed**, **9 rejected** = 11.
- WS52-JT VARCHAR boundary/multiple-column regression: **4 allowed**, **10 rejected** = 14.
- **111 + 6 + 11 + 14 = 142 synthetic subcases**; five test methods, zero reported failure, smoke PASS.
- Exact byte-identical final source Git blob `c0cccf419ca78937c317f417c301fc51db002ef1`, test blob `bfc669f337bc39f2b5509d55e2ae2c1ad643986a`, previously tested in isolated assistant Python environment. **No Python or MariaDB execution took place in WS52-JU**.
- Subset limits include `MAX_BYTES=8192`, `MAX_TOKENS=512`, `MAX_STATEMENTS=128`, restrictive quoted identifiers, specific statement forms, limited active comments and SQL modes; a **619163-byte** user-attested real dump cannot be responsibly classified with this unchanged primitive and has never been an authorized input. The function is a demonstrator, **not a backup importer**.

## 3. Formal WS52-JU status matrix — preserve separate GO decisions

The A–M gate taxonomy below follows the WS52-E readiness matrix and updates *only* the concrete synthetic evidence generated through WS52-JT. `PASS_SYNTHETIC` must **never** be interpreted as `PASS_REAL_DATA`.

| Gate | Current disposition | Required before a future real-data stage |
|---|---|---|
| A. Protected Git/production source immutability and cutover HARD-OFF | **PASS (remote-review preflight); REVERIFY AT EACH NEW STAGE** | Exact original/production HEADs, clean guarded source tree, gate blobs, protected fixture identities |
| B. Original backup file identity and provenance | **PRIOR LOCAL FILE IDENTITY AT WS51S; SOURCE CONSISTENCY NOT CERTIFIED** | Independently refresh symlink/owner/mode/hash when **separately approved to access** original file; attest export origin/snapshot or explicitly accept historical-only interpretive limits |
| C. Separate networkless restore server/dedicated volume | **PASS_SYNTHETIC; REAL-DATA FIXTURE ABSENT** | A newly approved restore-only isolate, unique empty datadir, no ports/network/backup binds, pinned identities, strong non-blank auth, disposal plan |
| D. Truly empty target and schema-scoped non-root importer | **PASS_SYNTHETIC (WS52-A); REAL-DATA ACCOUNT ABSENT** | Fresh unpredictable target schema with zero objects, exact scoped grants + negative non-target/global/file/admin capability verification; imported SQL must run as the restricted user |
| E. Entire effective MariaDB SQL + client-command classification | **BLOCKED** | Independently audited complete dialect/client-aware SQL treatment (versioned and client directives, modes, escaping, unknown constructs), or comparably provable *safe constrained execution alternative*; every actual statement and target resolved; **zero unknowns** |
| F. Destructive SQL and target confinement | **BLOCKED** | Pre-prove all 69 reported DROP targets confined to a newly empty exclusive schema; independent reject/privilege guards for qualified references, schema changes, file I/O, client scripting and privileged/global operations |
| G. Full real-backup MariaDB compatibility | **NOT TESTED** | Only a separately authorized appropriately isolated **real-data** validation/import after security, data protection and capacity preconditions are all met |
| H. Guest/host storage and import expansion | **BLOCKED** | Current *host physical* and Docker guest capacities/inodes, backing/quota constraints and defensible expanded table/index/redo/undo/temp/log overhead with failure reserve. Old WS52-D host/guest numbers are not a current guarantee and not additive |
| I. Confidentiality, encryption, retention, credentials | **BLOCKED** | Verify host + Docker backing encryption, backups/snapshots/retention, no accidental dump/log/history leak, audited strong credential channels, minimization and disposal/key destruction plan |
| J. Abort, rollback and disposal | **PASS_SYNTHETIC; REAL_DATA_PLAN UNCERTIFIED** | Address non-transactional DDL/partial import, rollback and sensitive-volume snapshots; identity-guarded deletion **only after separate disposal approval** |
| K. Historical production export consistency | **USER-ATTESTED ORIGIN; SNAPSHOT UNVERIFIED** | Consistent historical export proof, or explicit limited historical-rehearsal acceptance; no claim of full cross-table point-in-time consistency without evidence |
| L. Repaired B12 concurrency RC01–RC14 and cutover | **BLOCKED INDEPENDENTLY** | Corrected real MariaDB RC01–RC14 must pass in a distinct authorized stage; original test failed `TWO_STALE_COMMITS`, corrected certified passes **0**; restore alone never certifies CAS writer |
| M. Sensitive-data, target, import and release authorizations | **NOT AUTHORIZED** | Distinct explicit permission per sensitive file scan/read, real-data fixture and least-privilege bootstrap, historical SQL execution/import, follow-up verification/retention/disposal, migration/cutover |

**Overall:** `SYNTHETIC_CLASSIFIER_IMPLEMENTATION=PASS_WITH_LIMITATIONS` for the specifically observed cases. **`REAL_BACKUP_READINESS=NO_GO`** and `B12_PRODUCTION_RELEASE=NO_GO` regardless of the synthetic regression pass.

## 4. Risk register, ordered by blocking priority

| Priority | Residual risk | Mitigation required |
|---|---|---|
| P0 | Real dump contains unvalidated active SQL, MariaDB executable comments or client commands; 69 destructive DROP operations could target wrong schema | Independent effective SQL/client-aware allowlist or validated containment architecture **plus** restricted-server privilege controls; fail closed if any unknown behavior |
| P0 | Real data may be exposed through unencrypted Docker backing, snapshots, logs, diagnostic output, copied dump or retained volumes | Security/retention threat model with verified encryption, data minimization, secrets lifecycle, no raw SQL/error messages in evidence and separately approved secure disposal |
| P0 | B12 live concurrency correctness still unproven | Authorized repaired RC01–RC14 real MariaDB trials, explicitly separate from historical backup rehearsals |
| P1 | Host backing space/inodes too small for expanded imported tables, indexes, transaction/redo/undo and temp/log growth | Defensible independent upper-bound and **current** backing/guest headroom checks; never add host and guest measurements |
| P1 | Production-derived dump may be inconsistent/stale; missing coherent transactional snapshot proof | Attest original export and consistency or limit any rehearsal claims explicitly |
| P1 | Partial DDL import / DROP TABLE cannot simply be rolled back transactionally | Explicit fail-safe import order, exclusive empty schema, preverified abort and confidential-disposal plan |
| P2 | Prototype syntactic accepts might still fail real MariaDB combinations; narrow policy rejects valid dump features | Keep prototype isolated; build or select independently verifiable real-dump-aware parser/alternative separately; don't repeatedly patch subset to force arbitrary real SQL through |
| P2 | Over-retesting closed synthetic cases obscures progress and risks test-fixture drift | Freeze scoped WS52-F→JT evidence; reopen only with specific regression/compatibility evidence or new risk requirements |

## 5. Exact safe continuation: separate gates, not an automatic import

**Decision: close WS52-JU documentation and freeze this prototype checkpoint.** Do not modify the existing source, test files, protected original B12/production refs, migration 092 or any Docker resources. The next task should address the **P0 execution and confidentiality architecture gap**, not another open-ended synthetic parser chase.

**Proposed WS52-JV (requires separate authorization):** design-only resolution matrix comparing (a) complete MariaDB 11.4+client-aware static/executable-SQL validation of the *actual dump* (without opening it at design stage) against (b) independently proven confinement by brand-new network-disabled server, empty sole target and a strongly authenticated schema-scoped non-root importer. The design must choose explicit **two independent enforcement points** (source effective-SQL policy + server grants), decide how to handle conditional comments and client metacommands, define unknown=>deny and all 69 destructive target checks, and specify redacted verification evidence. Also finalize encryption/snapshot/retention and physical-space/cleanup attestations with a clear feasibility decision. **No live backup scan, Docker, MariaDB, production connection, credentials or file access** in the WS52-JV design step.

Subsequent possible phases are **separately approved** and contingent, not implied by closing WS52:
1. Independently approve and perform strictly limited original-backup **sensitive** source inspection/classification, preserving only safe aggregate output.
2. Approve new *real-data* fixture, strong non-root target-scoped importer and capacity/data-protection checks.
3. Only after all blockers close, separately approve any actual production-derived backup import (WS53), including risk of destructive SQL, followed by separately approved aggregate validation/retention/disposal (WS54).
4. Independently approve corrected B12 RC01–RC14 and stage/release controls only when safe, never enabling cutover by inference.

**Absolute standing release locks:** `B12_CUTOVER=HARD_OFF`; `MIGRATION_092=NOT_AUTHORIZED`; `D12_IMPORT_GATES=15_BLOCKED`; `STAGE_D=UNAUTHORIZED`; `150_VU=HOLD`; `200_VU=PROHIBITED`. Original MariaDB concurrency `TWO_STALE_COMMITS` failure remains; corrected RC01–RC14 **0 certified real-database passes**.

## 6. Remote semantic-diff acceptance and handoff

WS52-JU creates **exactly one Markdown evidence file** `docs/C137_GC24_K2_P03_B12_WS52JU_SYNTHETIC_PHASE_CLOSURE_AND_IMPORT_NO_GO.md` on its newly isolated branch from WS52-JT HEAD `cc949f5491b033c5ff79cf2ab4581eb2d6995315`. Confirm `ahead_by=1`, `behind_by=0`, one **added** Markdown and no other changes. Independently re-fetch branch HEADs/blobs listed in §1 and ensure source/tool Git blobs unchanged. Do **not** claim a local Mac clean-tree/Docker state was rechecked by this docs-only step.

A future agent should resume **from WS52-JU closure**, not redo WS52-F→JT or claim restored data. Until one of the defined P0 blockers is separately resolved, the next decision remains **NO-GO for real-data import and production cutover**.
