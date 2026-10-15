# C137 / GC24 / K2-P03 / B12 — WS51T import safety and privilege contract

**Date:** 2026-10-15  
**Decision:** `DESIGN_CONTRACT_DEFINED`; operational `IMPORT_AUTHORIZATION=NO`; `RESTORE_READINESS=BLOCKED_PENDING_EVIDENCE`; `B12_CUTOVER=HARD_OFF`.  
**Authorization:** User explicitly approved **design-only** finalization of parser-compatibility, destination-isolation and restricted-import-privilege requirements. WS51T performs no SQL, shell execution, Docker mutation, production data access, secret handling or restoration. This Markdown file is the **only** allowed source change, on a new docs-only evidence branch.

## 1. Immutable provenance and source baseline

- GitHub repo: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- WS51T parent docs-only source: `c137-gc24-k2p03-b12-ws51s-static-sql-safety-evidence-source`, HEAD `64b1728b918da134f028736e209e24409d83b1cc`.
- WS51T successor docs-only branch: `c137-gc24-k2p03-b12-ws51t-import-safety-contract-source`.
- Protected original B12 source branch: `c137-gc24-k2p03-b12-cas-writer-cutover-source`, HEAD `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Protected production branch: `v320-renee-agrisuite-branding`, HEAD `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- Literal writer-cutover HARD-OFF gate: `lib/daily_record_revision_cutover_gate.php`, Git blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`.
- Historical WS07 offline verifier: `scripts/verify_k2p03_b12_ws07_offline.sh`, Git blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`; located in **later evidence history**, **not** in the original B12 code SHA (WS51 wrong-ref assertion corrected in WS51R). Do not alter the original code checkout to accommodate it.

All protected refs, original code and production remain unchanged throughout this design. Any future step must independently guard exact source/remote, Docker daemon/context, pinned image and old resource identities before and after.

## 2. Evidence baseline — do not relabel lexical screening as certification

- User-reported production-derived `~/Downloads/database.sql`, last checked WS51S read-only: **619163 bytes**, mode `0600`, owner match, SHA256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`. Production origin was user-attested, not independently certified.
- WS51S **statement-aware lexical screen** (not a full MariaDB grammar parser): `DROP TABLE=69`, `CREATE TABLE=69`, `INSERT INTO=65`, `LOCK TABLES=69`, `UNLOCK TABLES=69`, `ALTER TABLE=138` (in versioned comments; WS45 found 69 DISABLE KEYS plus 69 ENABLE KEYS), `SET=225` (versioned comments), `OTHER_STATEMENTS=0`. **361 MySQL + 3 MariaDB = 364** versioned comments; one MariaDB sandbox directive and the same one above-110400 version-guard comment. Risk-heuristic counts all zero. These are scoped *negative lexical findings*, **not** evidence that every MariaDB 11.4/client language construct or execution path is accounted for.
- WS49: synthetic MariaDB 11.4 local socket server accepted one baseline `SELECT 1` and one synthetic sandbox directive followed by `SELECT 1`; standalone test container/volume were subsequently deleted, original inventory restored. Sandbox restriction enforcement and full dump compatibility **not** tested.
- WS51R/WS51S: 2 original protected running containers, 3 original named volumes plus 1 separate WS32 disposable MariaDB 11.4 container `renee-b12-ws32-mariadb114-stopped` (**RUNNING**) and volume `renee-b12-ws32-datadir` remain isolated; WS49 resources absent. Mac host/backup/Docker-backing host filesystem **166.81 GiB free** at WS51S, all the same backing pool, not additive; Docker.raw/qcow2 logical **233.47 GiB**, physically allocated **2.81 GiB**. Actual Docker guest free bytes/inodes and expanded imported data volume **unknown**.
- Backup global point-in-time consistency remains **not established** (no detected global transactional boundaries). Source data may be stale/inconsistent. Restoration, even if completed, cannot certify B12 concurrency semantics: earlier original real MariaDB failed `TWO_STALE_COMMITS`; repaired RC01–RC14 PASS count **0**.

## 3. Required security invariants: both isolation AND authorization

**Mandatory defense-in-depth, no substitutions:**

1. **Distinct isolated server**: a *new* restorer-only disposable MariaDB 11.4 instance, NOT WS32, NOT WS49 (gone), NOT the two protected originals; unique immutable container ID and exclusive new disposable datadir volume. Local Docker Desktop only; `--network none`, server `--skip-networking`, zero published ports, no backup/host bind mounts, nonprivileged, no-new-privileges, bounded memory/CPU/PIDs, restart no, locally pinned image ID. No Docker daemon, network or resource modifications beyond an expressly approved new, uniquely labeled scope. The source file stays on Mac (no mount/copy under WS51T).
2. **New target schema**: one cryptographically unpredictable schema name created *only* on this new server; confirm it does not preexist, then check it has **zero user objects** before any backup statement. Do not select `mysql`, `sys`, `information_schema`, `performance_schema`, or any old/protected/pre-existing database. Pin schema identity and compare before every separately approved stateful stage.
3. **Schema-scoped non-root importer**: create a fresh account limited to the unique target schema, authenticated **only through the intended isolated local socket**. No wildcard or `*.*` grants, no privileges over other schemas; prohibit privileges such as `FILE`, `SUPER`, `CREATE USER`, `GRANT OPTION`, global/persist system-variable writes and replication/administrative capabilities. No blank-password root for production-derived data: WS49's disposable synthetic exception is not transferable. Importer must not be `root`/admin.
4. **Executable-content control**: independently review/validate the entire pinned dump using a trustworthy MariaDB 11.4 **dialect- and client-directive-aware** parser or another proven constrained execution mechanism. Regex/token-only WS51S is not sufficient. Restrict execution to reviewed schema-local table/schema/data/session operations; unrecognized active SQL or client commands => **BLOCK**.
5. **Two independent enforcement points**: require *both* full source statement/target validation **and** actual negative capability tests of the constrained importer. A client default database argument (`-D`, positional schema, or `--one-database`) is **not a security boundary**: explicit qualified object references and client commands can bypass simple selection/filtering. Schema-limited server grants plus fresh isolated server remain mandatory even if parser review passes.

The future schema and credential identifiers are sensitive internal test details: do not put them, root secrets, raw SQL, table names, customer records or access tokens into copy-back markers or GitHub evidence.

## 4. Import grammar / parser compatibility acceptance contract

### Accept ONLY proven-effective categories for the pinned dump

For MariaDB 11.4 client/server, the implementer must identify effective statements **after** honoring server and client versioned-comment rules, SQL modes and relevant import-client command rules. A reviewed allowlist may contain: unqualified `DROP TABLE` (only in demonstrably empty target), `CREATE TABLE`, `INSERT INTO`, `LOCK TABLES` / `UNLOCK TABLES`, specifically verified `ALTER TABLE ... DISABLE KEYS` / `ENABLE KEYS` and session-scoped `SET` restoring dump modes. **These categories are proposed; not pre-approved for execution.** Unknown modifiers, dynamic SQL, additional permissions, side effects or qualified/non-target identifiers => stop.

All quoted/backticked identifiers, escaping behavior, `NO_BACKSLASH_ESCAPES` and related SQL_MODE transformations, client delimiter/meta-command recognition, executable MySQL/MariaDB comment version predicates, comment bodies and statement boundaries require assessment. **Do not** infer versioned SQL execution simply because a lexical scanner counted its text. The MariaDB sandbox directive is a **client-level** concern (WS49 synthetic acceptance), not a blanket guarantee of safe execution or enforcement.

### Reject unless separately justified, reviewed and explicitly authorized

- Any database switch/creation/deletion outside the single new target; qualified cross-schema identifiers, synonyms/explicit `db.table` bypass, `USE` to any different schema.
- Account or privilege operations (`GRANT`, `REVOKE`, `CREATE/ALTER USER`); global/persist variable writes; role changes, replication or server-admin operations.
- `LOAD DATA LOCAL INFILE`, external `LOAD DATA`, `SELECT ... INTO OUTFILE`, `DUMPFILE`, `LOAD_FILE`, plugin/file/OS access; shell escapes, client `source`/\`.\` commands, commands that read arbitrary files.
- Views/triggers/events/routines/definers or `DELIMITER`/dynamic SQL not explicitly classified and contained; unexpected functions with external side effects; unknown active executable-comment content.
- Unexpected destructive statements, targets, `DROP DATABASE`, database/schema-qualified table DDL, or any statement needing global/root privileges.
- Any parser uncertainty (including versioned executable comment handling, nested quotes/comments, extended literals, invalid encoding or indeterminate client directives) => `ALLOWLIST=BLOCKED`, not optimistic admission.

### Evidence to close the gate

Produce an independently reviewed, **locally evaluated** classifier/manifest bound to the fixed SHA256. Report **only approved aggregate counts/status**, never statement snippets or target names. Demonstrate every statement is accounted for with zero unknown active executable statements or client directives and one exact target-schema boundary. If a full dialect parser is unavailable, **do not label the lexical scanner complete**. A candidate alternative constrained execution mechanism must be explicitly designed and then proved with synthetic negative cases; it requires separate approval and documented residual risk before considering customer-data import.

## 5. Importer account grants and synthetic negative-test matrix

A future bootstrap (NOT WS51T) creates two distinct identities:

- **Bootstrap administrator** exists only inside new, physically isolated server, used to create a unique empty target schema and an ephemeral non-root importer. Provisioning/authentication must use *new* secrets, never reuse or expose WS32/production secrets; credential delivery via a controlled local secret channel, not argv/history/log/inspect/environment exposure when avoidable. Bootstrap privilege removed from the import process.
- **Importer account** is restricted to `target_schema.*` with **only** demonstrably needed rights. Candidate privileges (not automatically granted): `CREATE`, `DROP`, `ALTER`, `INDEX`, `INSERT`, `LOCK TABLES`, `SELECT`, and other precisely justified table-level operations if encountered. Do not confer `CREATE TEMPORARY TABLES`, `REFERENCES` or `TRIGGER` by default; justify each from classified effective SQL. No `ALL PRIVILEGES`, `*.*` or server-global admin rights.

**Mandatory test assertions** with fabricated database objects only, on the new target and a separate deliberately created *synthetic forbidden namespace* within the new isolated server, never WS32/protected or real production records:

| Test | Allowed outcome / acceptance |
|---|---|
| Connect using intended Unix socket and importer without root privileges | PASS under exact pinned client/server and identity |
| Inspect effective grants/current account/default schema | Exact known schema grants only; no wildcard/system/admin privilege |
| Target schema `CREATE TABLE` / `INSERT` / `ALTER` / `DROP TABLE`, plus required `LOCK TABLES` under controlled disposable sample objects | Expected permitted operations succeed without privilege escalation |
| Attempt create/read/update/drop in a synthetic *non-target* database (synthetic sentinel) | **Denied**; no data/object side effect in that namespace |
| Attempt schema switch/fully qualified target outside allowed schema | Either rejected by pre-parser or blocked by DB grants with **no protected side effect** |
| File/global privilege and account-mutation attempts | Denied; no filesystem/server privilege changes |
| Client sandbox directive and hardcoded benign input | Acceptance already observed WS49; enforcement of sandbox restrictions remains **NOT TESTED** and needs separately justified negative verification if relied on as a control |
| Reset/dispose synthetic fixtures | Exact new-resource IDs only; old inventories and root/hard-OFF gates remain unchanged |

These are **requirements only**. None of the negative test cases have been run as WS51T. A test that involves an actual synthetic SQL operation needs separate approval and hardcoded statements/connection budget. Synthetic denial by MariaDB grants is an important second line of defense but does not waive parsing of the actual file.

## 6. Data confidentiality, controlled transfer and rollback

- Treat the backup and all imported rows as *real potentially sensitive production-derived customer data*. Never display SQL, table names, rows, personal identifiers, access tokens or customer secrets to ChatGPT, terminal output markers, GitHub, CI, Docker logs or support tickets. Preserve pinned 0600 file mode/owner/hash and original path; do not edit/sanitize the original in place.
- Future WS53 import (NOT APPROVED) must define a **single one-way local confidential stream** from pinned source to **new** isolated container via approved host-to-guest mechanism, no external network, no shell-history disclosure or dumping content on error. The number of bytes, source hash, target container/image/volume, schema, client options, importer grants, timeout, output suppression, and abort/rollback policy must be attested immediately beforehand. Any copy/stream, including on the same laptop, counts as separately authorized sensitive data movement.
- Pre-import confidentiality decision needed: host volume encryption and Docker backing-file inclusion in automatic backups/snapshots, key destruction/retention rules and authorization to hold copied customer data locally. Docker volume deletion alone is **not proven secure deletion**. Define safe retention or encrypted-key disposal before importing.
- Import uses schema DDL (including `DROP TABLE`) that may autocommit. A failed import must not be described as rolled back merely because an SQL transaction is aborted. Use **whole disposable-instance containment**, a known scope/identity, and separate approval to dispose of confidential artifacts. On failure keep network-off and report sanitized outcome; do not broadly clean up or delete evidence on speculative identity.
- Redact client stderr and avoid showing raw dump errors, object names or query text. Public evidence may contain counts/status categories only. Secrets must not appear in command lines, Docker container environment/inspect metadata, shell history, logs, pasted outputs or repository.
- A successful restore would be a *historical rehearsal* not a consistency certification. No coherent point-in-time snapshot established, so B12 assertions involving historical cross-table relationships must be labeled conditionally and cannot establish production correctness without further evidence.

## 7. Storage and failure gates

WS51S local host filesystem free 166.81 GiB at measurement; Docker sparse backing file logical 233.47 GiB, allocated 2.81 GiB. These are not separate usable capacities. WS50–WS51S **do not attest** new guest filesystem space/inodes or maximal expanded import footprint. Before importing require new container guest free/inodes and Docker daemon storage quota/sparse backing status, physical host reserve and credible bounds for tables+indexes+temporary+redo/undo+logs, with documented margin. No claim of PASS from 619163-byte SQL dump length alone. A capacity/inode shortfall, unknown quota, insufficient margin or inability to estimate => **BLOCK**.

Additional stop gates: identity/resource drift, non-empty target database, unmatched backup hash, unexpected active SQL/client meta, access outside schema, grant scope failure, unsupported client/server mode, file read exception, client return/error, unexpected customer-data exposure or uncontrolled retention, lack of secure disposal, schema/DB test failure, or parser classification ambiguity.

## 8. Stage-specific authorization and go/no-go matrix

| Gate | Design status WS51T | Separate approval / evidence to advance |
|---|---|---|
| WS51T *contract* | **DEFINED / CLOSED AS DESIGN** | Commit this document only; remote diff verifies just one Markdown addition |
| Import grammar & client dialect qualification | **BLOCKED** | Independently verify full effective SQL/client directive classification of pinned dump or explicit alternative constrained mechanism, and its unresolved assumptions; **no SQL now** |
| New restore-only networkless server/volume/unique empty target | **NOT CREATED** | Explicit WS52 approval for exact Docker creates, time/resource limits, privileged bootstrap, schema creation, secret lifecycle and cleanup; no backup touched |
| Schema-restricted importer and negative synthetic privilege checks | **NOT IMPLEMENTED / NOT TESTED** | Separate WS52-permitted test SQL/credentials/fixture-object budget, allow/deny outcomes and no-protected-resource guard; no customer records |
| Guest+host capacity/inodes and confidentiality plan | **NOT CERTIFIED** | Fresh, measured capacity and acceptable margin plus secure handling/retention/disposal before any data stream |
| Backup consistency | **NOT VERIFIED** | Explicitly accept only use as **historical rehearsal**, do not assert coherent source snapshot or conflate with B12 concurrency proof |
| WS53 sensitive-data stream + isolated import | **NOT AUTHORIZED** | Fresh, separate written approval naming the single customer-data import, destructive statements, target identifiers, privileges, limits, failure/disposal policy |
| WS54 post-import aggregate validation and disposal | **NOT AUTHORIZED** | Fresh permission for named aggregate SQL and verified-scope disposal; preserve sanitized evidence |
| B12 RC01–RC14 and daily-record writer cutover | **BLOCKED / HARD-OFF** | Independent real MariaDB concurrency certification and controlled release gates; no cutover from a backup rehearsal |

**Recommended immediate next engineering action, not authorized:** an *independently authorized synthetic-only privilege/containment proof* on a fresh disposable server (a bounded WS52-A or equivalent), with no backup access, preceded by a zero-side-effect Docker/resource gate. This can establish effective import-account restrictions and guest capacity without exposing customer records. **It cannot itself close the SQL parser gate.** Alternatively finish a narrow parser-feasibility implementation review before any provisioning. Do not silently chain WS52-A to WS53/WS54.

## 9. Nonnegotiable release locks and Git evidence contract

- `B12_CUTOVER=HARD_OFF`, migration `092` **NOT APPROVED**, repaired real MariaDB RC01–RC14 passes **0**, prior real test **FAILED `TWO_STALE_COMMITS`**.
- D12 15 import gates BLOCKED; Stage D UNAUTHORIZED; 150 VU HOLD; 200 VU PROHIBITED.
- No contact with production, staging, AWS, cPanel, existing protected historic C137 fixture or two old original Docker containers/three volumes. WS32 remains untouched; no WS49 resources exist.
- `WS51T` changes **only this Markdown file** on a new successor evidence-only branch from WS51S SHA `64b1728b918da134f028736e209e24409d83b1cc`. Independently verify remote diff `ahead=1`, `behind=0`, `files=[this added document]`; separately re-fetch protected original/production branch SHAs, hard-OFF gate blob and later-history WS07 verifier blob. No raw dump/customer data or application changes in GitHub.

**Final design judgement:** contract is written and traceable; **import system is not ready or authorized**. Future provisioning, SQL privileges/tests, any backup read/stream, restoration, validation and resource disposal require individually scoped authorization. Preserve `One fix, fit it all`, shared helpers, thin routes, and no changes to closed verified scope without regression evidence.
