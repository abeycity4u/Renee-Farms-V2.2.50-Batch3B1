# C137 / GC24 / K2-P03 / B12 — WS52-B parser compatibility and guest-capacity feasibility review

**Date:** 2026-10-15.  
**Result:** `WS52B_READONLY_FEASIBILITY_REVIEW_COMPLETE`; `PARSER_EXECUTION_CERTIFIED=NO`; `GUEST_CAPACITY_ATTESTED=NO`; `RESTORE_AUTHORIZED=NO`; `B12_CUTOVER=HARD_OFF`.  
**Authorization:** User responded **"proceed"** to the specifically proposed **WS52-B read-only feasibility review** of MariaDB parser-compatibility and Docker guest-capacity verification methods. This review used existing, previously verified GitHub evidence and engineering analysis. **No local terminal command was issued, backup was opened, Docker process was accessed/executed, database connected, SQL executed, container/volume provisioned or deleted, credential examined, or production system contacted.** This is a **design/feasibility review, not a new local runtime test**.

## A. Checked repository identity and lineage

Repository `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.

| Item | Pinned value |
|---|---|
| Immediate parent docs-only branch | `c137-gc24-k2p03-b12-ws52a-synthetic-privilege-pass-source` |
| Verified parent HEAD | `efe4d3f3f3fb56772f43bad9bd3845465891ebe5` |
| New WS52-B docs-only branch | `c137-gc24-k2p03-b12-ws52b-parser-guest-capacity-feasibility-source` |
| Protected original B12 source branch | `c137-gc24-k2p03-b12-cas-writer-cutover-source` |
| Original B12 SHA | `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af` |
| Protected production branch | `v320-renee-agrisuite-branding` |
| Production SHA | `10041da3a3c4e4a7b8101f8183c243d056c7b128` |
| Literal HARD-OFF cutover gate | `lib/daily_record_revision_cutover_gate.php` blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268` |
| Historical WS07 offline verifier | `scripts/verify_k2p03_b12_ws07_offline.sh` blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb` |

The WS07 runner is present in **later evidence history** but **not** original B12 code HEAD. Do not repeat the WS51 initial incorrect `git rev-parse HEAD:<runner>` assertion in the original checkout. WS51R corrected it.

## B. What the closed audits really prove

- **WS43 / WS51S:** User-attested production-derived local `~/Downloads/database.sql` at last local check was **619163 bytes**, mode `0600`, SHA256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`. The static statement-aware *lexical* scan yielded **69 DROP TABLE, 69 CREATE TABLE, 65 INSERT INTO, 69 LOCK TABLES, 69 UNLOCK TABLES, 138 ALTER TABLE inside versioned comments, 225 SET inside versioned comments**, exactly 364 versioned comments (361 MySQL, 3 MariaDB), one MariaDB sandbox directive, `OTHER_STATEMENTS=0` and **zero scanner-specific lexical risk flags**. Lexical totals are not a proven complete, dialect-aware effective SQL execution manifest.
- **WS47–WS48:** MariaDB CLI 11.4 help included a sandbox reference and relevant CLI flags; among four searched offline/parser candidate option names `--parse-only`, `--syntax-check`, `--offline-parser`, `--dry-run`, **none appeared**. No database-free client parser was established. This is *not proof* that all possible offline parser approaches do not exist.
- **WS49:** New disposable MariaDB 11.4 network-disabled client/server test accepted **one baseline synthetic SELECT 1 and one hardcoded sandbox-directive plus SELECT 1**. Synthetic directive input acceptance **does not prove sandbox restrictions are enforced** or that the full original dump parses.
- **WS52-A:** A **new independent** network-none, no published-port, MariaDB 11.4 server was created for *synthetic-only* tests. Its Unix-socket-authenticated nonroot account had schema-only grants for `SELECT, INSERT, CREATE, DROP, INDEX, ALTER, LOCK TABLES`. **11 synthetic SQL client invocations** executed; allowed target operations passed and five negative privilege actions were denied with accepted error codes: forbidden-schema SELECT, CREATE, DROP, GLOBAL SET, CREATE USER. Synthetic forbidden sentinel integrity passed. WS52-A container and volume were removed and pre-existing Docker inventory/container fingerprints unchanged; WS32 and two old protected containers/three original named volumes untouched. **This is only a synthetic privilege proof**; no production-derived backup was read, copied or imported.
- **WS51S storage:** Local Mac host, backup and Docker-backing filesystem free **166.81 GiB** at last measurement (same backing pool, not additive); located Docker backing disk logical length **233.47 GiB**, allocated physical blocks **2.81 GiB**. Docker *guest* free bytes/inodes and a credible expanded data/index/temporary/log footprint remained **NOT_ATTESTED/UNKNOWN**. Measurements are historical, not re-measured in WS52-B.
- **Snapshot:** No globally consistent transaction boundary/proven export snapshot methodology established; historic table LOCK/UNLOCK operations do not prove a single point-in-time. Any eventual restore is at most a labeled *historical rehearsal*, not a coherent production consistency or B12 CAS certification.

## C. MariaDB-compatible SQL parser / routing feasibility

### Observed: which mechanisms are and are not proven

| Mechanism | Supported finding | Boundary |
|---|---|---|
| MariaDB 11.4 CLI binary | WS47 help/WS49 actual synthetic query acceptance | No general parser-only mode established; executing untrusted SQL on a server is **not** a safe parser dry-run |
| WS51S custom Python scanner | Known source SHA, structural top-level statement/comment counts, zero heuristic risks | Not a complete MariaDB 11.4 SQL grammar/parser or effective client-command interpreter |
| Generic SQL tokenizer/parser library | Possible implementation *candidate only*; no specific installed/version-compatible parser validated | Generic MySQL support does **not** automatically imply MariaDB 11.4 executable `/*!...*/`, `/*M!...*/` and client sandbox semantics |
| Dedicated SQL-aware allowlist | **Design requirement**, not implemented or tested | Must fail closed on unsupported modes, executable comments, client metacommands, schema routing and parser ambiguities |
| Newly isolated server + schema-scoped nonroot grants | WS52-A **synthetic enforcement demonstrated** | Provides independent containment, but it cannot certify arbitrary actual dump parse paths or confidential import safety |

### Defined next qualification path — NOT EXECUTED / NOT AUTHORIZED

1. **Select/vet exact parser or fixed-dialect classifier** on known synthetic fixtures only. Prefer a **fully specified, pinned, auditable MariaDB 11.4-compatible implementation**. If using an external parser, verify its exact version, accepted syntax and documented gaps; do not assume `sqlparse`, `sqlglot` or another generic tokenizer automatically interprets all MariaDB versioned comments and client commands correctly. No parser package was installed in WS52-B.
2. **Synthetic conformance suite:** Verify quote/backtick escaping, comment delimiters, semicolon and client delimiter behavior, MariaDB client sandbox directive, conditional `/*!...*/` and `/*M!...*/` execution guards, including versions outside the executed ranges; SQL_MODE-specific string parsing (including `NO_BACKSLASH_ESCAPES`), embedded string comments, fully qualified `schema.table`, `USE` changes, permissions, client `source`/shell/other escapes, non-target DDL and unknown commands. Require both **expected allow** and **expected reject**; incomplete/ambiguous results => `PARSER_QUALIFICATION=BLOCKED`.
3. **Only if separately approved**, run a read-only, *local-only* full-file classification against the pinned original SHA256. This is sensitive-data access even if no SQL executes, so enforce file-owner/mode/hash guards, prevent raw content/identifiers/rows from leaving local memory, output only approved counts/status, handle errors without revealing SQL. Do **not** create a rewritten/sanitized copy of the production-derived dump as a shortcut.
4. Prove 100% coverage of *effective* SQL and client directives in the original dump and bind each operation to the designated schema. Reject unhandled statements, server privilege elevation, `DEFINER`, outside-schema routes, file-system access and arbitrary client scripting. **Zero WS51S lexical flags alone is insufficient.**
5. Before any real data stream, independently enforce an empty new target schema on a **new** no-network server with a nonroot schema-only importer; WS52-A proves feasibility using fabricated data but no real environment exists. Check the **69 destructive DROP TABLE statements** against the fresh empty target; explicit `--database` alone is **not a security boundary**.
6. If no trustworthy complete parser is available, the decision is **BLOCKED** until a separately designed, demonstrably constrained import mechanism is reviewed. Never turn incomplete classification into a PASS by downgrading the acceptance contract.

**WS52-B parser conclusion:** `DATABASE_FREE_DIALECT_PARSER=NOT_VERIFIED`, `ACTUAL_DUMP_EFFECTIVE_SQL_MANIFEST=NOT_CERTIFIED`, `FULL_BACKUP_PARSE_COMPATIBILITY=NOT_CERTIFIED`. No remaining uncertainty may be silently treated as passing.

## D. Docker guest-capacity assessment feasibility

### What existing measurements can and cannot tell us

| Evidence/method | What it indicates | Does it prove guest restore capacity? |
|---|---|---|
| macOS `statvfs` for home/backup/Docker backing filesystem | Physical backing free at time of measurement (~166.81 GiB WS51S) | **No** — still need guest free, inode status and reserve |
| `Docker.raw`/`Docker.qcow2` logical size and allocated blocks | Sparse virtual disk capacity *configuration/usage context* | **No** — nominal 233.47 GiB is not 233.47 GiB free; physical host block allocation can grow |
| `docker system df` / `docker info` read-only metadata (proposed only, **not run WS52-B**) | Engine image, container and volume usage, storage driver/root metadata | **No** — does not by itself attest `/var/lib/mysql` guest filesystem free bytes/inodes or upper-bound import growth |
| Direct read-only `df`/`stat -f` (bytes and inodes) inside a newly approved *disposable restore-capacity fixture* (proposed only) | Provides an empirical guest filesystem baseline associated with the target datadir/volume | **Partial** — guest baseline alone does not establish max expanded backup growth or host backing margin |
| Import growth estimate incl. rows, storage engines, indexes, temp, redo/undo, logs, collation | Needed to budget high-water space and IOPS/memory | **NOT AVAILABLE** from present evidence; no independent upper bound certified |

**Recommended capacity procedure, not authorized in WS52-B:**

1. Under a later specific approval, verify Mac source/backup integrity and the Docker daemon/context/pinned image, original inventories and existing resource fingerprints.
2. Create a **new, separately named disposable, network-disabled capacity-only container/volume** (or combine with a separately approved WS52 restore-only provisioning gate, without customer data), never repurpose WS32 or the two protected originals. Any Docker `run`, `exec`, resource creation, schema SQL or cleanup must be covered by the specific future authorization.
3. Read guest filesystem free bytes/inodes at the dedicated new datadir mount using bounded **non-SQL** metadata commands and capture only sanitized quantities. Ensure exact volume mount identity and guest/host backing pairing. If measured directly, the guest metric belongs to that specific fixture, not any other container.
4. Measure current Mac physical host headroom (not nominal sparse disk size) again; apply a **conservative, justified maximum import expansion + temporary/index/log reserve**. No unsupported fixed margin or arbitrary numeric PASS threshold. If expanded data bound cannot be credibly established, `RESTORE_SPACE_SUFFICIENCY=NOT_CERTIFIED`.
5. Do not expose backup rows, original SQL, credentials, Docker logs, or customer table names in capacity reporting. Cleanup may affect only exact new fixture IDs/labels, with separate authorization and before/after original inventory proof.

**WS52-B capacity conclusion:** `HOST_BACKING_LAST_MEASURED_GIB=166.81` (WS51S snapshot, not fresh), `DOCKER_GUEST_BYTES_INODES=NOT_ATTESTED`, `EXPANDED_IMPORT_FOOTPRINT=UNKNOWN`, `SPACE_SUFFICIENCY=NOT_CERTIFIED`.

## E. Confidentiality, failure and authorization gates

- Original backup lives only at user-reported Mac path, unchanged, no raw SQL/rows/identifiers exposed. The local backup is **real potentially sensitive production-derived data**, not a synthetic fixture. Reading it for parser classification, streaming it to a container or importing it each requires explicit, narrow permission; WS52-B has no such local data operations.
- Future real restoration must use **fresh strong bootstrap credentials**, ephemeral schema-scoped nonroot importer, no production or WS32 credentials, no passwordless root, no secrets in command line/history/Docker inspect/log output, network isolation, pinned exact container ID/image/volume/schema and SQL quotas. WS52-A passwordless root exception was only for synthetic, network-disabled fixture.
- Determine Docker backing data-at-rest encryption, local host backup/snapshot retention, audit output/data retention and separately authorized disposal/secure erasure **before** any production-derived copy or import. Removing a Docker volume is not cryptographic erasure.
- A completed SQL import containing DDL cannot be assumed atomic or reversible with a transaction. Use scoped disposable-instance rollback/disposal; on error freeze/contain isolated new server and produce sanitized evidence, never delete unknown/old resources or print raw SQL.
- Unapproved SQL, DB connections, Docker exec, new resources, import/replay, destructive statements, migration 092 and B12 cutover remain prohibited.
- Continuing product readiness is separate: prior *real* MariaDB concurrency test **FAILED `TWO_STALE_COMMITS`**; corrected real RC01–RC14 certified passes = **0**. D12 15 import gates BLOCKED, Stage D UNAUTHORIZED, 150 VU HOLD, 200 VU PROHIBITED.

## F. Final go/no-go and precise next proposed stage

| Requirement | WS52-B outcome |
|---|---|
| Existing docs/branch/hard-OFF protection | **PASS remote evidence review**, no local changes |
| WS52-A synthetic schema-limited privilege feasibility | **PASS (historical audit)** |
| MariaDB 11.4 sandbox directive input acceptance | **PASS (WS49 synthetic scope only)** |
| A verified MariaDB/client-dialect-aware offline parser | **NOT VERIFIED** |
| Actual dump's complete effective SQL allowlist/routing | **NOT CERTIFIED** |
| Existing Docker original resources and backup hash fresh in WS52-B | **NOT RECHECKED LOCALLY** — latest WS52-A and WS51S attestations retained |
| Guest datadir free bytes/inodes | **NOT ATTESTED** |
| Actual expanded backup import footprint/space reserve | **UNKNOWN / NOT CERTIFIED** |
| Secure handling of production-derived data at rest and disposal | **PLAN ONLY / NOT APPROVED** |
| New restore-only container/schema/restricted import account | **NOT CREATED** |
| Historic snapshot point-in-time consistency | **NOT CERTIFIED** |
| Production-derived backup import and B12 cutover | **NOT AUTHORIZED / HARD-OFF** |

**WS52-B's conclusion is a feasibility review, not an operational ready-to-restore gate.**

**Recommended single next step: WS52-C synthetic parser-conformance design and test-harness qualification**, prior to another local full-dump scan or any import. Prefer a fixed known synthetic corpus that exercises tricky MariaDB versioned comments, SQL mode, quoting, qualified-schema bypasses, client meta-commands, and rejected unexpected statement classes. First inspect the proposed parser's precise semantics and deployment footprint; any local package installation, command execution, backup read, Docker operation or SQL needs fresh, explicitly scoped permission. This is a gap-closing qualification, **not** repetition of WS48 or WS49 and **not** permission to restore the production backup. If the user instead prioritizes guest storage measurement, separately propose a read-only host/guest-capacity operation with an exact authorized Docker exec target or new fixture and resource cleanup; do not silently use existing WS32.

**Source evidence consulted:** GitHub WS48 offline parser blocked report and WS52-A synthetic privilege evidence at verified parent `efe4d3f3f3fb56772f43bad9bd3845465891ebe5`, together with previously verified WS43–WS51T outcomes represented in those reports. WS52-B does not create new runtime evidence. One new Markdown report only, on new docs-only branch; independent GitHub semantic diff must show ahead 1/behind 0, sole addition this report, and original/production heads, gate and runner unchanged.
