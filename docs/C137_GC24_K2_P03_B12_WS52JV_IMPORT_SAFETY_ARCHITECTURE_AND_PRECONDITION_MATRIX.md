# C137 / GC24 / K2-P03 / B12 — WS52-JV real-backup import safety architecture and prerequisite decision

**Review date:** 2026-10-15  
**Scope:** User-authorized **DESIGN-ONLY** review after WS52-JU synthetic-phase closure. No Mac, backup, SQL, Docker, cloud, cPanel, production, staging, customer data or credentials accessed. No source code, tool/test files, migrations or protected branches changed. This is a **single Markdown evidence artifact** on a new isolated GitHub successor branch.

**Decisions:** `WS52JV_DESIGN_REVIEW=COMPLETE`; `PREFERRED_SECURITY_ARCHITECTURE=TWO_INDEPENDENT_BARRIERS_PLUS_CONFIDENTIALITY_GATES`; `IMPLEMENTATION_FEASIBILITY=UNPROVEN`; `FULL_EFFECTIVE_SQL_POLICY=BLOCKED`; `ENCRYPTED_REAL_DATA_HANDLING=BLOCKED`; `STORAGE_FIT=BLOCKED`; `REAL_DATA_IMPORT=NO_GO`; `B12_CUTOVER=HARD_OFF`.

## 1. Baseline, traceability and nonnegotiable operational scope

| Evidence or guard | Verified reference |
|---|---|
| Repository | `abeycity4u/Renee-Farms-V2.2.50-Batch3B1` |
| Immediate parent evidence branch | `c137-gc24-k2p03-b12-ws52ju-synthetic-closure-decision-source` |
| Parent WS52-JU HEAD | `5ad8a19acdefb53ab112a81c708bde687033f3c2` |
| New docs-only WS52-JV branch | `c137-gc24-k2p03-b12-ws52jv-import-safety-architecture-decision-source` |
| Original protected B12 source branch | `c137-gc24-k2p03-b12-cas-writer-cutover-source` |
| Original protected B12 HEAD | `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af` |
| Protected production code branch | `v320-renee-agrisuite-branding` |
| Protected production HEAD | `10041da3a3c4e4a7b8101f8183c243d056c7b128` |
| Literal B12 hard-OFF gate | `lib/daily_record_revision_cutover_gate.php` blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268` |
| WS07 verifier in **later evidence history**, not original B12 HEAD | `scripts/verify_k2p03_b12_ws07_offline.sh` blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb` |
| Frozen WS52-JT standalone synthetic classifier | `tools/b12_ws52f_synthetic_allowlist.py` blob `c0cccf419ca78937c317f417c301fc51db002ef1` |
| Frozen WS52-JT deterministic synthetic tests | `tests/test_b12_ws52f_synthetic_allowlist.py` blob `bfc669f337bc39f2b5509d55e2ae2c1ad643986a` |
| WS52-JU closure report | `docs/C137_GC24_K2_P03_B12_WS52JU_SYNTHETIC_PHASE_CLOSURE_AND_IMPORT_NO_GO.md` |
| WS51T import protection contract | `docs/C137_GC24_K2_P03_B12_WS51T_IMPORT_SAFETY_CONTRACT.md` |
| WS52-E A–M readiness gate baseline | `docs/C137_GC24_K2_P03_B12_WS52E_FINAL_IMPORT_READINESS_GAP_MATRIX.md` |

**Historical observations—not fresh verification:** Prior user-approved WS51S read-only screen pinned a user-attested live-production-derived `~/Downloads/database.sql` of **619163 bytes**, mode `0600`, SHA256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`. It counted **69 DROP TABLE**, **69 CREATE TABLE**, **65 INSERT**, **69 LOCK TABLES**, **69 UNLOCK TABLES**, **138 conditional ALTER**, **225 conditional SET**, **364 versioned comments**, and one MariaDB sandbox directive, under a *limited lexical scanner*. Zero scanner-specific flags do **not** certify actual MariaDB/client-effective statements. Export provenance, consistent snapshot and detailed table/index expansion remain **UNVERIFIED**. No backup file was opened/rehashed/reviewed at WS52-JV.

Earlier WS52-A synthetic restricted-importer tests passed 11 bounded SQL client calls with five denied foreign operations. WS52-D synthetic MariaDB 11.4 oracle passed 12 positive and six negative syntax-error cases in 18 calls, reported 212.98 GiB *guest* free/15,252,481 inodes and ~166.80 GiB *Mac physical* free **at that prior test**; measurements cannot be added and are no longer a current storage guarantee. WS52-F→JT produced a frozen *deliberately incomplete* Python SQL subset classifier passing **142 deterministic synthetic policy cases** at WS52-JT, not a real-backup validator. WS52-G/H/I and WS52-J/JS/JT found and corrected specifically evidenced prototype compatibility errors; they did not establish full dialect/client grammar. No WS52-JV runtime tests occurred.

## 2. Threat model and security decision

**Assume the entire SQL dump is untrusted executable input despite the user-attested production origin.** It can contain:
- Destructive operations, explicit or implicit schema selection/qualified `db.table` references, table/column identifiers with difficult escaping, session/global state changes, privileges/accounts, security definers, views/triggers/routines/events and executable dynamic SQL.
- MariaDB/MySQL **conditional executable comments** (different server-version gates), normal comments containing misleading statements, intentionally skipped/future comments, MariaDB sandbox directive, client `source`, `\.`, `\!` and other client commands, `DELIMITER`, changed SQL modes/charsets, multiline constructs and embedded semicolons.
- Credentials/customer records as row literals; SQL error text, table names, Docker logs, backups, shell history and volumes can leak them even with network ports disabled.
- A technically valid import that is incoherent as a cross-table snapshot; imported schema DDL is not universally transactional.

**Selected reference architecture (MANDATORY TWO BARRIERS):**

**Barrier 1 — independently reviewable, deterministic *client-effective SQL allowlist* or equally demonstrable constrained translation.** Every input byte must be accounted for under pinned MariaDB 11.4 **client plus server** semantics; all active statements/targets, SQL modes and client commands must be resolved. An unknown, unsupported, qualified non-target, dynamic, privileged/file/OS operation or unexpected branch => **STOP**. Produce a signed/fingerprinted local classification manifest bound to the exact backup hash and parser/toolchain versions, but **publish only aggregate category counts and PASS/BLOCKED**. The current 142-case subset, a regex scanner, `--one-database` or a default database flag does not meet this requirement. Source classification of real backup bytes needs *separate sensitive-data authorization*.

**Barrier 2 — genuinely distinct, network-disabled restore-only MariaDB server, exclusively new datadir volume, cryptographically unique **empty** destination schema and an independently tested, authenticated, non-root importer with **only precisely required privileges on that schema**.** Server-scoped account grants must deny operations outside the target regardless of an allowlist mistake. Root/bootstrap identity does not import customer data. Never reuse the original WS32 or two protected older containers/volumes. Unprivileged container, `--network none`, MariaDB `--skip-networking`, zero published ports, local Unix socket only, no backup/host bind mounts, pinned image ID, identity+mount checks and resource budgets are mandatory.

**Additional independent gates:** verified host and Docker backing encryption/backup retention, strong ephemeral credentials, sensitive-output suppression, defensible storage bounds, reproducible abort/retention/cryptographic-disposal plan, explicit authorization per stateful stage, and separately established B12 concurrency correctness. **Neither barrier substitutes for any of these protections.**

### Threat / enforcement mapping

| Threat | Barrier 1 expected reject/validation | Barrier 2 / data control | Status |
|---|---|---|---|
| 69 potentially destructive `DROP TABLE` | Exact unqualified single-target name resolution and count; target confined to new empty schema; unknown action => deny | Exclusive new schema, zero preexisting user objects, schema-only DROP rights; nontransactional-DDL abort plan | BLOCKED for real dump |
| Qualified/switching/cross-schema SQL | Reject unexpected `USE`, `db.table`, client re-targeting, qualified target names, database DDL outside reviewed new schema | Deny all non-target namespace access; verify external sentinel intact | Synthetic deny feasibility only |
| MySQL/MariaDB executable comments and modes | Evaluate active guard bodies, version/mode transitions, quote/escape semantics; skipped comments cannot silently bypass policy | Fixed client/server identity; deny harmful operations regardless of syntax tricks | Selected synthetic oracle only |
| MariaDB client metacommands/files | Deny or explicitly constrain client command dialect including `source`, `\.`, shell/OS/file paths, `DELIMITER`, local infile, sandbox semantics | Disable risky client features, FILE/global privilege absent, no host bind mounts or network | BLOCKED in full actual SQL |
| Privilege escalation/definers/dynamic SQL | Reject account/global/grant/role/DEFINER/routine/trigger/view/event operations unless specifically reviewed | Importer non-root; no global roles/FILE/admin; negative controls | BLOCKED for real dump |
| Confidential data extraction | Do not log raw SQL, errors, table names or data; exact one-way local stream only under explicit approval | Encrypted host/backing/volume, secret channel, no logs/history/snapshots leakage, authorized retention and key disposal | BLOCKED |
| Disk filling or partial DDL | Classify table/index operations, prebound expansion and no surprise objects | Physical host/guest limits with reserve, timeout/abort policy, quarantine if partial; later authorized disposal | BLOCKED |
| Incorrect B12 CAS concurrency | Import analysis does not claim CAS correctness | Separate real MariaDB repaired RC01–RC14 evidence | BLOCKED independently |

## 3. Import architecture alternatives — design-only decision

| Option | Advantages | Fundamental limitations | WS52-JV decision |
|---|---|---|---|
| **A. Full MariaDB 11.4 *client-effective* dump classifier** with independent grammar/oracle and local sanitized manifest | Retains the SQL dump as-is; can account for versioned comments, client directives, destructive target manifest if complete | Requires trustworthy **client+server** semantics, changes in SQL modes, full DDL/DML coverage, independent audit, zero unclassified operations; WS52-JT subset is insufficient | **Candidate architecture, UNIMPLEMENTED/NOT PROVEN**. Preferred if demonstrably complete and reproducible |
| **B. Canonical, typed data-only transformation** into pre-reviewed schema and bounded DML; discard original executable SQL | Potentially minimizes unsafe SQL and destructive DDL if semantics and schema can be fully verified | Requires authenticated, independently reviewed schema/data extraction and transformation for all relevant tables, relationships and type encodings, proof of no omitted data or rewritten tenant scope; sensitive source read still required | **Alternative candidate, UNIMPLEMENTED**. No automatic downgrade of provenance/integrity requirements |
| **C. Rely solely on dedicated server+schema grants and run unchecked dump through MariaDB client** | Easy to set up and synthetic privilege controls have demonstrated feasibility | Unreviewed client directives, file input, definer/global operations, privileged bootstrap mistakes, session/schema changes and data leakage are not eliminated; grants alone do not prove exact effects | **REJECTED AS SUFFICIENT; NEVER THE ONLY DEFENSE** |
| **D. Trust WS51S regex counts or WS52-JT 142-case toy classifier and stream real dump** | Low engineering effort | Not full MariaDB grammar/client-command coverage, toy 8192-byte budget vs 619163-byte dump, unknown executable operations remain | **REJECTED** |
| **E. Independently certify B12 corrected concurrency on synthetic data rather than restore customer backup** | May avoid exposure of real records and move concurrency validation independently | Does not satisfy a separately required historical production-derived import rehearsal or prove snapshot consistency; test coverage must meet real RC01–RC14 acceptance criteria | **Worth an independent design decision; not a substitute unless B12 owners explicitly approve that scope** |

**Architecture conclusion:** Choose **two barriers plus confidentiality/capacity**, with **A or B remaining an unverified implementation choice**. Do not mislabel the architecture as implemented or select option C as an import shortcut. Before sensitive real-data classification, a separate engineering decision must demonstrate how A or B will be independently tested with fabricated representative patterns; if neither is feasible, **STOP / NO-GO**. The next stage should pursue the smallest reproducible proof of complete effective SQL safety instead of expanding the WS52-JT toy by trial and error.

## 4. Executable-SQL/client manifest — minimum future acceptance contract

**No WS52-JV backup parsing was performed.** A separately authorized future local tool must:

1. Validate before any input that source path is an ordinary file, not symlink, correct owner/mode, exact expected size and **fresh** SHA256, with no raw bytes or source path emitted to remote systems. No content is submitted to GitHub, ChatGPT, CI, production or staging.
2. Pin MariaDB client/server versions, client-command/sandbox interpretation, encoding, SQL_MODE transitions and executable MySQL/MariaDB comment predicates. Track statement boundaries over comments, literals, backticks, quoted strings, escaped delimiters and alternate modes. Reject unrecognized versions, semantics or syntax, not silently ignore.
3. Maintain an exhaustive **effective** statement manifest with every statement/class and destination; class-specific syntactic and semantic rules: reviewed local `DROP/CREATE/INSERT/LOCK/UNLOCK/ALTER KEYS/SET SESSION` are *candidates*, **not automatically allowed**. Validate permissions, parser origin and session effects. Reject views/triggers/routines/definers/privilege or arbitrary client commands unless a separately approved architecture proves them safe.
4. Resolve each destructive target to the **single new target schema** or mark BLOCKED; do **not** accept “default database” or `--one-database` as a boundary. Require equality checks for all qualified/unqualified targets and zero unaccounted object creations/drops; the prior **69** count is a historical lexical observation, not a target certification.
5. Treat client sandbox directive as a distinct *client feature* requiring independently demonstrated enforcement if relied upon. Do not infer that synthetic acceptance of the directive enforces a sandbox; source file imports, shell escapes and `LOCAL INFILE` remain forbidden by default.
6. Handle parser/client errors and incomplete input as `UNKNOWN_BLOCKED`; never issue partially classified statements. Reproduce synthetic active/ignored comment test cases and new adversarial escapes with a separately approved MariaDB 11.4 oracle. Compare intended policy to actual behavior, not arbitrary nonzero status codes.
7. Restrict output to an approved redacted manifest: **source-hash match YES/NO**, tool/version digest, number of effective statements by reviewed class, aggregate unknown count, aggregate prohibited/destination-escape count, mode/client-command audit PASS/BLOCKED, manifest hash and a status field. Never show SQL, table/column names, schema identifiers, rows, secrets, SQL strings, raw database stderr or tracebacks with input bytes.
8. Independent reviewer checks deterministic run reproducibility against the **same pinned bytes** (if authorized), secure temporary artifacts, no unreviewed dependencies or network access, and no ambiguity; any mismatch => `CLASSIFIER=BLOCKED`.

The exact executable SQL/client-aware validation method is **not selected or built yet**. A separate proof that confinement by the importer is effective is still required even if all source assertions pass. If direct grammar completeness is unachievable, option B must satisfy equivalent independently reviewable source-to-target semantics and safe typed data accounting, rather than bypass the first barrier.

## 5. Real-data target and privilege design contract (NOT provisioned)

- **Fresh restore-only host context:** verify current exact Docker Desktop engine, executable client, pinned local MariaDB image, protected original IDs/volumes and safe host destination. Distinct unique container name, engine+image ID, datadir volume, labels, no network/ports/host binds, resource caps. Guard every mutation with pre/post inventories and avoid a shared backing volume or unexpected mounts.
- **Schema lifecycle:** cryptographically generated unique target within new server; confirm nonexistent then empty (zero user tables, views, routines, triggers, events as relevant). Create synthetic **forbidden** namespace and sentinel only inside that new fixture for grants testing. Schema/target identity can be kept locally, but never copied into public evidence.
- **Accounts:** a strong authenticated bootstrap administrator used only for creation/configuration; separate strong, ephemeral **non-root importer** with precisely justified rights on `target_schema.*`, local Unix socket access and no `*.*` or global FILE/admin, `GRANT OPTION`, account changes or replication. Importer must fail negative operations on synthetic forbidden schema; verify no altered forbidden sentinel. Use controlled secret delivery, never command argv/history/inspect environment if avoidable. The synthetic passwordless-root pattern is **PROHIBITED** for production-derived data.
- **Execution:** approved single local source-to-client stream directly into the new isolated target as restricted importer, only after manifest check, encryption and space PASS, and a *distinct* authorization naming the input fingerprint, source movement, destructive SQL, schema/volume, secret handling, max time/size and quarantine/disposal strategy. SQL on synthetic fixtures is a separate action from real import.
- **Failure:** SQL DDL autocommits; do not promise transaction rollback. At any error, stop execution and isolate/quarantine the exact new instance/volume while retaining private evidence per an agreed retention plan. No deleting or reusing resources before checking identity and obtaining the **separate** data-sensitive disposal approval. Production/WS32/other protected containers and databases are always out of bounds.
- **Postrestore:** under separate WS54 permission, verify only approved redacted aggregate schema/row/structural checks and B12 dependencies; do not emit object names/data. A historical rehearsal cannot independently certify source snapshot consistency, data completeness or CAS writer correctness.

**Critical data-boundary distinction:** A database physically isolated from production is not inherently safe for real customer data. The Docker backing disk, host swap, snapshots, OS backups, crash reporting, shell command history and diagnostic logs may expose the imported records. New resources are not approved until those flows are understood.

## 6. Confidentiality, retention, storage and disaster-recovery attestation design

### Confidential-data acceptance checklist — every item requires evidence

| Control | Required future evidence | If unproven |
|---|---|---|
| Host and Docker backing at-rest encryption | Verified active whole-volume/key-backed encryption on all backing locations, swap and temporary locations; identify host/Docker disk images, key custody, screenlock/access boundaries | **BLOCK** |
| Snapshots/backup/retention | Identify Time Machine, cloud sync, Docker/Desktop backups, other snapshots and log collectors; prove relevant new volume/data is excluded **or** encrypted with separately erasable key and documented retention | **BLOCK** |
| Data movement channel | Only locally approved one-way stream into isolated container; no host-mount of backup, cloud transfer, downloadable copy, shell echoed SQL, GitHub upload or CI execution | **BLOCK** |
| Import-account secret lifecycle | Strong ephemeral non-root socket credentials, local secret channel, no reuse of production/root passwords; sanitized logs, explicit poststage revocation | **BLOCK** |
| Redacted evidence/error capture | Preapproved safe output schema, no raw client SQL/stderr/tracebacks/tables/customer rows/passwords in copy-back; unexpected errors only numerical class and STOP | **BLOCK** |
| Partial failure/quarantine | No auto cleanup that loses evidence or leaks; fixed target IDs, explicit retention with storage bounds and disposal authorization | **BLOCK** |
| Secure disposal | Proven encryption key revocation/cryptographic erase covering backing and snapshots **or** explicitly accepted residual encrypted retention; `docker volume rm` alone **does not prove secure erase** on SSD/backups | **BLOCK** |

**Storage/headroom design:** Prior guest `212.98 GiB` and Mac host `166.80 GiB` readings are from a **deleted synthetic fixture** and cannot be added. For a future identified target, define and justify a worst-case requirement `MAX_EXPANDED_TABLE_DATA + MAX_INDEXES + MAX_TMP_SORT + MAX_REDO_UNDO_LOGS + OTHER_REQUIRED_GROWTH + RESERVED_FAILURE_MARGIN`. Current Mac host physical free and Docker backing sparse growth limit, guest free bytes and **inodes**, hard filesystem/quota limits, and pre-existing protected-volume reserves must all independently exceed the relevant budget. The **619163-byte text dump size is not a defensible expanded-size estimate**. If upper bound or effective host backing reserve cannot be proved *before* any customer-data stream, report `CAPACITY=BLOCKED`. Do not derive an invented capacity multiplier here.

**Snapshot integrity:** Source was user-attested as from live production but no global transactionally consistent snapshot was proven. A future restore could be an explicitly accepted historical **rehearsal only**, with all cross-table and temporal conclusions qualified. A new coherent production export would be a *different* production action requiring separate consent.

## 7. Current A–M readiness mapping and feasibility verdict

| Gate from WS52-E / WS52-JU | Design coverage established in WS52-JV | Operational status |
|---|---|---|
| A. Git/source HARD-OFF immutability | Exact refs and guard contract | **PASS remote preflight only; future host guards required** |
| B. File identity/export provenance | Fresh local hash/owner/mode/no-symlink and snapshot proof specified | **UNVERIFIED fresh source; snapshot BLOCKED** |
| C. New networkless restore server/volume | Identity and isolation architecture specified | **REAL TARGET ABSENT / BLOCKED** |
| D. Unique empty schema+non-root grants | Exact privilege/deny contract specified, WS52-A synthetic demonstrated feasibility | **REAL IMPORTER ABSENT / BLOCKED** |
| E. Complete active SQL/client command manifest | A and B alternative approaches assessed; unknown=>deny selected | **BLOCKED: no complete implementation / dump qualification** |
| F. Destructive and cross-schema containment | Independent input and privilege defenses specified | **BLOCKED: 69 actual targets not classified/validated** |
| G. Full real backup compatibility | Distinct, separately approved import validation contract described | **NOT TESTED / BLOCKED** |
| H. Storage capacity/expanded growth | Independent physical+guest/inode/budget formula defined | **BLOCKED: no measured target/capacity upper bound** |
| I. Encryption, secrets and retention | Necessary attestations and redacted outputs listed | **BLOCKED: no environment attestation** |
| J. Aborts, rollback, secure disposal | Quarantine and identity-key-erasure contract specified | **BLOCKED for real data** |
| K. Coherent snapshot/provenance | Independent attestation or historical-only concession specified | **UNVERIFIED** |
| L. Corrected RC01–RC14 concurrent CAS correctness | Kept separate; baseline failure `TWO_STALE_COMMITS`, **0 certified corrected real MariaDB passes** | **BLOCKED INDEPENDENTLY** |
| M. Stage authorizations | Distinct consent for classifier read, real fixture, WS53 import, WS54 validation/disposal and any cutover | **NOT AUTHORIZED** |

**Feasibility verdict:**
- `DESIGN_CONTRACT_COMPLETE=YES` for the **requirements and gated sequence**, **not for a runnable full parser, real-data target or secure-storage attestation**.
- `OPTION_A_COMPLETE_CLIENT_EFFECTIVE_SQL_PARSER=UNPROVEN`; `OPTION_B_CANONICAL_DATA_TRANSFORM=UNPROVEN`.
- `TWO_BARRIERS_REQUIRED=YES`; `GRANTS_ALONE_IMPORT_APPROVAL=NO`; `TOY_CLASSIFIER_REAL_DUMP_APPROVAL=NO`.
- `PRIVACY_ENCRYPTION_RETENTION=BLOCKED`; `EXPANDED_IMPORT_CAPACITY=BLOCKED`; `EXPORT_CONSISTENCY=UNVERIFIED`.
- **`WS53_IMPORT_GO=NO`; `B12_CUTOVER_GO=NO`.**

This design review does **not** claim any real-data blocker has been closed; it establishes the next independently testable decisions and their proof requirements.

## 8. Ordered, separately authorized next actions — avoid repeated generic synthetic QA

**Recommended next step WS52-JW — design-only proof plan for the executable-SQL/client command route, no dump access.** A new evidence-only decision should select how to independently prove **Option A** complete client-effective parsing or **Option B** typed canonical translation, define version-pinned test coverage and clearly state any unsolved MariaDB 11.4/client grammar dependency. Produce a deterministic manifest schema and acceptance rubric, bounded synthetic adversarial test corpus design, and concrete dependency/feasibility verdict. If completeness cannot be convincingly established, report BLOCKED and do not expand WS52-JT's toy as an import shortcut. Concurrently **design** a confidential storage attestation checklist (without accessing host data).

If WS52-JW selects a viable architecture, the future gate sequence is:

1. **Specific authorization for a non-sensitive implementation and additional *synthetic-only* proof**, separate from WS52-JV, without actual backup or production contact. Guarded source and remote semantic diff; pin exact hashes and test contract.
2. **Separate explicit approval for current host privacy/encryption/backup-retention and capacity metadata inventory** (not for reading or streaming customer backup). This may expose sensitive host configuration; return sanitized statuses only.
3. **Separate explicit authorization for any actual pinned production-derived backup read/classification** on the Mac; source identity reverified first; do not upload or print content; return only blinded aggregate manifest and STOP on unknowns. This is a sensitive-data stage independent from Docker provisioning.
4. **Separate explicit consent to create a real-data-capable dedicated networkless MariaDB instance, empty target and strong socket importer**, and run bounded synthetic grant-denial tests; no real import authority follows. Confirm confidentiality/snapshot and current headroom.
5. **Only if gates B–K and separate risk approvals pass:** request specific **WS53** sensitive-data import authorization naming 69 destructive operations, backup fingerprint, new target identity, confidentiality, failure/retention plan and scope. No user silence or earlier “proceed” authorizes WS53.
6. **Separate WS54** sanitized aggregate validation, containment/retention and confidential disposal decision. Never auto-delete real data on a guessed cleanup path.
7. **Separate B12 corrected RC01–RC14 real MariaDB concurrency certification** and release approvals; historical restore alone cannot prove fixed CAS semantics.

**Do not preempt the independent B12 concurrency gate**: if its acceptance criteria can be met without real customer data, propose that as a separately approved synthetic/staging proof path rather than unnecessarily expose the production backup. Whether this replaces the *historical rehearsal requirement* is a distinct explicit project decision—not a presumption in WS52-JV.

## 9. Nonnegotiable invariants and change audit

- `B12_CUTOVER=HARD_OFF`; `MIGRATION_092=NOT_AUTHORIZED`; `D12_IMPORT_GATES=15_BLOCKED`; `STAGE_D=UNAUTHORIZED`; `150_VU=HOLD`; `200_VU=PROHIBITED`.
- Original B12 source and production branches unchanged; no Mac source checkout, WS32, older protected containers/volumes, live data or production/staging/AWS/cPanel operations. Preserve “One fix, fit it all”, centralized shared services/thin routes and avoid unnecessary broad QA. After any future *authorized* source push perform independent remote semantic diff.
- WS52-JV must change **exactly one added Markdown file** `docs/C137_GC24_K2_P03_B12_WS52JV_IMPORT_SAFETY_ARCHITECTURE_AND_PRECONDITION_MATRIX.md` on the new docs-only successor branch from WS52-JU SHA `5ad8a19acdefb53ab112a81c708bde687033f3c2`. Require `ahead=1`, `behind=0`, only this new document, unchanged tool/test and literal hard-OFF/WS07 Git blobs, unchanged original/production HEADs.
- **No proposal here is execution consent.** Current final verdict: `WS52JV_DESIGN_COMPLETE`, `REAL_DATA_IMPORT_NO_GO`, `B12_CUTOVER_HARD_OFF`.
