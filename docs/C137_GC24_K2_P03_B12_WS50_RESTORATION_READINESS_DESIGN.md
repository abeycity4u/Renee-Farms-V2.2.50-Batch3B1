# C137 / GC24 / K2-P03 / B12 — WS50 guarded historical backup restoration-readiness design

**Status: DESIGN COMPLETE; OPERATIONAL READINESS NOT CERTIFIED; RESTORATION NOT AUTHORIZED.**  
**Date:** 2026-10-15. **Authorization:** user explicitly approved preparation and review of this plan **without executing SQL, copying/opening the backup, reading credentials or logs, or changing Docker resources**. Only this new Markdown documentation file may be added to a fresh docs-only GitHub branch.

## 1. Control boundary and GitHub baseline

Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.

| Protected checkpoint | Established value |
|---|---|
| Immediate parent evidence branch | `c137-gc24-k2p03-b12-ws49-synthetic-client-server-evidence-source` |
| Parent HEAD | `a43657c9467fa2a78e349381df095b9a16b58bd7` |
| New plan-only branch | `c137-gc24-k2p03-b12-ws50-restoration-readiness-design-source` |
| Original B12 source branch | `c137-gc24-k2p03-b12-cas-writer-cutover-source` |
| Original B12 SHA | `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af` |
| Protected production source | `v320-renee-agrisuite-branding` |
| Protected production SHA | `10041da3a3c4e4a7b8101f8183c243d056c7b128` |
| Literal HARD-OFF gate blob | `lib/daily_record_revision_cutover_gate.php` at `6e88c913ad5e5b371a9d0c92ce08870214ac1268` |
| Historical WS07 offline runner blob | `scripts/verify_k2p03_b12_ws07_offline.sh` at `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb` |

**Nonnegotiable:** Preserve original source checkout branch/HEAD and clean tree, both protected branch SHAs, HARD-OFF gate, verifier, protected old containers/volumes and WS32 fixture. No live production, staging, cPanel, AWS or protected historical C137 fixture contact. No automatic approval for data restoration or migration.

## 2. Evidence accepted from WS42–WS49 (do not replay closed tests)

| Evidence | Verified fact | Unresolved limitation |
|---|---|---|
| WS42 | One authenticated read-only `SELECT 1` against a network-isolated WS32 MariaDB 11.4 server; secret handled locally | That credential use was separately authorized and consumed; it does not authorize further SQL or secret recovery |
| WS43 | Local `~/Downloads/database.sql` reported as production-derived by user; 619163 bytes; SHA256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`; file mode 0600; reported MariaDB 11.4 header and end marker; heuristically 69 CREATE TABLE, **69 DROP TABLE**, 65 INSERT INTO, 69 LOCK/UNLOCK pairs; no detected transaction begin/commit; host backing free **166.86 GiB** then | Production origin not independently attested; table counts from line-anchored heuristic; snapshot consistency and expanded import storage not certified |
| WS44 | Local lexical scan: 361 MySQL + 3 MariaDB versioned comments; 225 SET, 138 ALTER, one unknown; no top-level schema selection or schema-qualified references flagged | Negative lexical matches do not guarantee arbitrary SQL isolation or all server/client semantics |
| WS45 | 138 ALTER versioned comments classified as 69 DISABLE KEYS and 69 ENABLE KEYS; one NONALPHABETIC_START unresolved | Lexical classification, not complete execution semantics |
| WS46S | Sole unknown comment classified as MariaDB client sandbox directive, with the only above-11.4.0 guard; Docker arch `x86_64` confirmed | Client handling and server conditional logic not certified by lexical classification |
| WS47 | Isolated MariaDB CLI client major/minor family 11.4; sandbox reference and protocol/socket/batch options observed | Help text is not parser execution |
| WS48 | No verified offline parser entry point among scoped candidates; fail-closed `BLOCKED_NO_VERIFIED_OFFLINE_PARSER` | Does not mean directive is invalid or parser support impossible |
| WS49 | **New standalone disposable MariaDB 11.4 server** accepted synthetic sandbox-directive input followed by SELECT 1; separate baseline SELECT 1 passed; **exactly two synthetic query attempts**; test container/volume removed; original inventory recovered | Does not prove sandbox enforcement or full backup import compatibility |

**Current Mac Docker reference inventory as of WS49:** 3 original running containers: 2 older protected plus 1 WS32 isolated disposable `renee-b12-ws32-mariadb114-stopped` (RUNNING notwithstanding its name); 4 named volumes total: 3 older protected plus `renee-b12-ws32-datadir`; no WS49 resources remain. WS32 image pinned cached `mariadb:11.4` Linux/amd64 image-ID fingerprint `2ee3c4a72fcac30a`, `--network none`, no published ports, dedicated datadir volume.

The WS49 blank-root-password setting was acceptable **only in the freshly created synthetic, networkless WS49 fixture**; **NEVER** reuse passwordless root for production-derived customer data.

## 3. Recommended restoration target and trust separation

**Decision: recommend a NEW disposable dedicated restoration instance, NOT WS32.** WS32 was an isolated authentication/readiness fixture with different provenance and an already consumed password-handling authorization. Leaving it intact avoids irreversible modification of a verified checkpoint. The original two protected containers, old volumes and all live production/staging databases are excluded categorically.

**Proposed resource topology (names are design placeholders, not created):**

- One separately approved new local Docker Desktop **Linux/amd64 MariaDB 11.4** container under a unique restore-only scope, with an independent disposable named volume mounted solely at `/var/lib/mysql`.
- Docker `--network none`, no exposed/published ports, server `--skip-networking`, no bind mount of original backup or host home folders, no other containers on its network, no restart policy, unprivileged/privilege restrictions, resource limits, only original pinned cached image after identity review.
- One **new, randomly and uniquely named target database** created in that new container, confirmed to have zero pre-existing user objects immediately before import. Never reuse an existing production, staging, WS32, archived fixture or system database.
- Exact restore-container ID, image SHA, exclusive volume name and mount destination, new schema identifier (kept out of public evidence), Docker context/engine ID and before-inventory are to be pinned and verified **before and after each future stateful action**.
- Original `~/Downloads/database.sql` stays on the Mac, mode 0600, unchanged and never committed/uploaded. Any future one-time copy/stream of its bytes is a sensitive data movement requiring **independent explicit authorization** and a host-to-guest containment design. No stream is authorized by this plan.

**Why separate server AND restricted account?** The 69 DROP TABLE statements are inherently destructive if misrouted. An entirely new server physically separates database namespace/datadir from protected resources, while a schema-limited importer reduces consequences of unexpected routing within the new server. Neither protection should substitute for the other.

## 4. SQL routing and import privileges: fail-closed conditions

Before approving any import:

1. **Parser-aware allowlist**: inspect pinned dump in a separately approved confidential/local dry-review step using a SQL-aware tokenizer/parser that understands quoted names/values, executable MySQL/MariaDB versioned comments, semicolon boundaries, `DELIMITER`/client directives and MariaDB 11.4 version rules. Classification only; never emit SQL source, table names, credentials or customer records. Existing WS43–WS46 regular-expression/lexical checks are useful evidence but **insufficient alone** for complete routing proof.
2. **Supported command classes**: explicitly review/allow only expected backup schema/data/session operations for the fresh new schema (CREATE TABLE, DROP TABLE, INSERT and required session SET, LOCK/UNLOCK, KEY enable/disable). **Reject/block** CREATE/USE of any other database, cross-schema fully-qualified operations, stored procedures/triggers/events/views not reviewed, `DEFINER`, privilege or user changes, global/persist variables, LOAD DATA/LOAD_FILE, file/outfile/plugin operations, shell/meta source commands, unknown active executable comments, or any other unreviewed statement. If parser cannot classify an active directive: **BLOCK**, not a presumed pass.
3. **Client command handling**: use an approved MariaDB 11.4 client capable of interpreting the identified sandbox directive. WS49 shows synthetic input acceptance, not blanket import/parser certification. Do not silently strip sandbox directives or switch to a mismatched legacy MySQL client.
4. **Mandatory explicit destination**: because earlier screening found no `CREATE DATABASE` or `USE`, client invocation must select the exact verified new schema, and the dump must be checked not to change database context. Explicit selection **alone does not prevent** `db.table` or client command escape; enforce server-level least-privilege controls as well.
5. **Least-privilege ephemeral import account**: an independently authorized bootstrap flow creates one account with required rights **only on the new schema** (potentially CREATE, DROP, ALTER, INDEX, INSERT, SELECT, LOCK TABLES and other narrowly justified operations). No rights on system/other schemas; no `SUPER`, `FILE`, `GRANT OPTION`, `CREATE USER`, `SET GLOBAL` or broad `*.*`. Validate grants server-side before any data stream. Root credential should only provision/revoke this constrained account, never be the importer.
6. **Database denylist**: `mysql`, `sys`, `information_schema`, `performance_schema`, plus any pre-existing or protected database schema; prohibit imports into them regardless of client arguments. The strongest cross-environment safety remains the fresh isolated server itself.
7. **Guarding destructive statements**: the 69 DROP TABLE operations are allowed **only** on the provably empty new schema, where they have no pre-existing application/customer tables to destroy. Unexpected DROP targets or pre-existing objects => abort. Ideally inspect the effective allowed target set before import, then pin schema identity throughout.
8. **Query budget**: future import will submit many SQL statements and potentially nontransactional DDL. A WS42 `SELECT 1` permission or WS49 two-synthetic-query budget cannot be reused. Define an explicit bounded import operation, timeout, connection/command identity, maximal data transfer and abort predicates in its own approved step.

## 5. Credential hygiene and confidential customer data

- **New unique credentials** scoped strictly to the future disposable restore server; never copy production credentials or read WS32 root logs again. Use secure ephemeral secret input so passwords never appear in pasted shell commands, terminal output, host process argv, GitHub files, captured tool outputs or the Docker container environment/inspect metadata. Where operationally unavoidable, document OS-level transient exposure and limit privileges. Any secrets file/mount should be access-restricted, temporary, separately approved and removed only after resource-identity checks.
- Do **not** use WS49's disposable empty-root-password method when loading live-production-derived data.
- Treat every record in the backup as potentially personal/customer-sensitive. Do not display raw dump, SQL lines, schema/table names, rows, payloads, emails, phone numbers, payment data, credentials, tenant identifiers or application secrets in copy-back output or GitHub Markdown. Even metadata may be sensitive; publish only pre-approved aggregate counts/status codes.
- Prevent inadvertent exposure through Docker logs, command history, crash dumps, filesystem snapshots/backups, CI artifacts, screenshot sharing, terminal scrollback and error dumps. Assess encryption-at-rest and host-backup retention for the new data volume **before** moving real data. Docker volume removal is not cryptographic erasure; high-assurance disposal requires a separately considered encryption/key-destruction and retention approach.
- Keep host backup immutable at its pinned path/hash and 0600 mode; don't edit, duplicate or transfer it without a specific authorization. A local import transfer is still a disclosure/movement event within the host threat model and requires an approved method.
- Give future operators a sanitized report contract with only `PASS`/`BLOCKED`, relevant aggregate counts, hashed identifiers if necessary, stage tags, explicit contact flags and bounded cleanup state. No raw SQL/customer/secret strings.

## 6. Resource capacity, snapshot integrity, and operational constraints

**Capacity:** WS43 measured 166.86 GiB free on the Mac's physical/backing filesystem at that moment; historical WS37 observed a larger **213.14 GiB** guest volume free figure. Sparse Docker Desktop guest capacity does not guarantee physical backing. Before any import, reattest **current** physical host free space, Docker disk backing free/limits, new container datadir free/inode counts, CPU/memory/timeout and import growth needs. The 619163-byte dump length **does not determine** expanded table/index/log footprint. Require defensible headroom for expanded tables, indices, redo/undo/binlogs, temporary files and evidence, plus failure cleanup; if no credible upper bound or margin, BLOCK. No arbitrary tiny dump-size-based PASS.

**Consistency:** No global `START TRANSACTION`/`COMMIT` markers were detected, and per-table `LOCK TABLES`/`UNLOCK TABLES` does not certify that all 69 tables represent the same production point in time. User reported live production extraction, but original capture method, server transactional engine behavior and consistent snapshot window remain unverified. The fixture may support **carefully labeled historical rehearsal**, but may not be treated as authoritative for business totals, cross-table integrity or B12 CAS correctness absent data-level consistency and provenance checks. Do not modify or re-export production without separate permission.

**Compat:** MariaDB client/server major/minor 11.4 is supported by WS47/WS49 synthetic evidence; exact full import compatibility, collation/charset, engine, constraints, sql_mode and conditional statements must be checked in an actual scoped test, with a clear stop if unexpected. Missing foreign keys or orphan relationships should be assessed with sanitized aggregate diagnostics, not disclosed raw references.

## 7. Future execution phases — each separately authorized, no SQL now

| Proposed gate | Allowed only after specific authorization | Objective and pass/block evidence |
|---|---|---|
| WS51 — nonmutating live preflight and import safety contract | Read-only checks only (when separately approved) | Re-verify source/remote HEAD, Docker daemon/context and 2+WS32 original resources, volume IDs, fresh capacity, backup identity, offline parser-aware allowlist feasibility, handling of secrets and record containment. Block on any drift. |
| WS52 — controlled infrastructure provisioning | Explicit approval for new container/volume, root/account/schema bootstrap SQL and credential handling | Create unique isolated target and **empty** schema, restrictive account/grant verification and cleanup rollback; **do not** read/import backup. Never touch WS32/protected resources. |
| WS53 — controlled backup stream/import | Separate, explicit permission for reading/streaming original production-derived data and running destructive/schema/data statements into exactly that verified empty schema | Pin all resource IDs, backup hash and import-account grants; bound transfer, disallow other targets, suppress output, stop on first unexpected SQL/privilege/schema error. |
| WS54 — contained post-import assessment and disposal | Separately approved aggregate read-only validation and tightly scoped deletion/encrypted-key disposal | Validate table/object counts and key integrity aggregates, categorize import success/error, preserve sanitized evidence, remove only identities created for this rehearsal under approved disposal plan, then prove original inventory unchanged. |
| Later B12 concurrency/RC certification | Fresh standalone authorization | Repaired real MariaDB RC01–RC14 must be run under their own gates; importing a historical backup is **not** evidence that concurrency test failure is resolved. |

The labels WS51–WS54 are **planning names**, not approvals. Do not assume all stages may be chained automatically; no provisioning, SQL, restoration, data scanning/streaming or destruction under WS50.

### Acceptance / abort requirements for future approval

- Before each stage: exact resources and permissions, expected reads/writes, approved command/API list, maximum query/data budget, safety checks, what is returned, rollback procedure and **explicit user authorization**.
- On any identity mismatch, unexpected pre-existing target objects, SQL outside reviewed allowlist, privilege escalation requirement, non-zero import/client error, Docker inventory drift, insufficient space, customer-data disclosure risk or unreadable evidence => **ABORT**, not skip a guard.
- On failure, do not immediately delete potentially evidentiary/newly sensitive data without a scoped disposal plan; keep container network-isolated, record only sanitized incident status and separately authorize cleanup. Never remove any unknown or protected resource.
- After each approved step, record *only non-sensitive* evidence in its own docs-only branch and verify independent remote semantic diff: exactly one Markdown file, ahead 1/behind 0; original and production refs, B12 HARD-OFF gate and WS07 runner blobs unchanged.

## 8. Current decision and exact next action

**WS50 status = PLAN_PREPARED_ONLY.** The evidence supports proceeding to a separately authorized **WS51 read-only capacity/target/backup safety contract preflight**, *not* directly to import. The eventual destination should be a **fresh network-isolated restore-only instance**, not the previously authenticated WS32 fixture. WS49 compatibility testing is closed; do not repeat WS42–WS49 absent new regression evidence.

Do not request/consume the original production dump or show customer records to the agent or GitHub.

**Unchanged release locks:** `B12_CUTOVER=HARD_OFF`; migration 092 **NOT AUTHORIZED**; repaired real MariaDB RC01–RC14 certified passes **0**; original real concurrency attempt **FAILED `TWO_STALE_COMMITS`**; D12's 15 import gates BLOCKED; Stage D UNAUTHORIZED; 150 VU HOLD; 200 VU PROHIBITED. No deployment, production mutation, AWS/cPanel/staging action or protected historical C137 fixture contact.

**WS50 GitHub delivery policy:** create one new docs-only branch from WS49 verified HEAD and add this Markdown document only. Independently verify the remote semantic diff and pinned protected refs/gate/runner after writing.
