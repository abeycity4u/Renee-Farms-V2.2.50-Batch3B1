# B12 stages 1–7 — source-evidence readiness and authorization ledger (WS52-LD)

**2026-10-15 | Read-only GitHub source inspection only. No physical Docker, SQL, customer backup, production or staging contact.** This ledger identifies *completed evidence* versus *unproven prerequisites*. No gate below should be marked PASS based merely on an offline fixture or source-diff success.

## A. Source lineage / evidence type

- Repository `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`, WS52-LD parent WS52-LC exact commit `37c8a20fd02465c511955f2488402ee95b326e1c`.
- Protected original B12 `c137-gc24-k2p03-b12-cas-writer-cutover-source` HEAD `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`; production `v320-renee-agrisuite-branding` HEAD `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- Critical unchanged Git blobs: always-OFF cutover `6e88c913ad5e5b371a9d0c92ce08870214ac1268`; CAS authority `5fbb90903b0cc801be45c684f6c3db85c660979a`; hard-disabled preliminary RC01 `e78318509e270ccd24684bf41f5e854b74765f76`.
- User-returned Mac evidence preserved separately: WS52-KT `20/20`, KV `22/22`, KX `34/34`, KZ `29/29`, LB `28/28` offline test suites at pinned exact source blobs. No physical runtime PASS recorded. Do not rerun closed offline tests without regression evidence.
- User-returned WS52-KO pinned 8,790-byte OCI config SHA256 `41096ae9913468679bb5e05bbc19f44edcaf0eae45d67fcde224ffdeda9d3a1f` and linux/amd64 child manifest digest `dc9dd57be7f46ad6f350c598a331d64f295899c4abac3ec5652eff831fc38a6c`, index digest `7db29378d4fdab73f8123bbc2b48905c90d1a4b00cf848b028f1e81e623257f2`. Config declares `VOLUME /var/lib/mysql`; this is config-byte/content evidence only, NOT a downloaded/layer-verified runnable image.

## B. Seven major stage acceptance ledger

| Stage | Needed to close | Current fact and resulting status |
|---|---|---|
| 1. Isolated broker | Implement independently privilege-isolated broker, fixed exact Docker metadata routes, bounded parser/real raw response minimizer and protected evidence ledger; review code + adversarial tests | WS52-KT/KV/KX/KZ/LB source-only contracts and WS52-LD architecture review exist; **BROKER IMPLEMENTATION/ISOLATION PENDING** |
| 2. Runtime dependencies | Independently authenticate full OCI manifest/layers/signatures as applicable, immutable runner source/host, actual engine identity, outbound network restriction, localhost listener and resource inventory | OCI config-only hash reported PASS; layer / runtime / runner / egress physical provenance **NOT ATTESTED** |
| 3. Historical DB audit | Independently verify authoritative provenance and scope of production-derived backup; use a properly isolated *read-only approved historical clone* to assess legacy NULL-cycle rows, cross-farm/cycle mismatches and FK consistency without restoring over any running dataset | Backup was identified by user as production-derived; no approved restoration, clone provenance, queries or customer-row access; **PENDING AUTHORIZED ISOLATION** |
| 4. One-use synthetic runtime | Bind source/image/broker/runner/DB schema/privileged observer to specific human approval, never self-approved env strings; provision from synthetic-only data, no production secrets | **NOT_AUTHORIZED; NO ENVIRONMENT ACTIVATION** |
| 5. Physical isolation certification | Independent pre/post container/volume inventories, actual sole writable named `/var/lib/mysql` mount, localhost-only listener, constrained egress and exact safe teardown, with quarantine on uncertainty | KT/KV/KX/KZ/LB only hypothetical shape tests; **PHYSICAL_ATTESTATION=NO** |
| 6. Repair race tests RC01–RC14 | Distinct deterministic real MariaDB worker/observer concurrency tests, assert exactly-once revision behavior, independent locks, race loser rejection, cleanup and regression evidence, no production access | Original GitHub Actions `38233373699` failed TWO_STALE_COMMITS; repaired certified RC01–RC14 PASS=**0** |
| 7. Release decision | Independent full evidence review; explicit decision on migration 092 and B12 cutover after all prior gates; rollback/recovery and tenancy-impact review | `MIGRATION_092=NOT_AUTHORIZED`, `B12_CUTOVER=HARD_OFF`, release **NOT READY** |

Seven rows represent completion *gates*, not seven predictable commands or guaranteed short tasks. Stage 3 historical data may run independently of synthetic-only runtime preparation, subject to **separate approval**, but cannot use production data as a surrogate for a synthetic RC01 database.

## C. Stage 2 — precise immutable image and runner gaps

- Present evidence: one previously user-reported immutable OCI child manifest digest, one exact config digest and declared VOLUME `/var/lib/mysql`; config blob Git evidence and declared layer diff IDs are NOT independent verification of compressed image-layer blobs, manifests+layers cross-link, publisher signature provenance, runnable entrypoint behavior or Docker engine platform negotiation.
- Before later physical approval: verify registry manifest chain at exact digest, compressed layer digests and uncompressed chain, OCI image config/architecture/OS, publisher/registry trust policy, content lengths and content hashes, local image digest-to-container image ID linkage, and absence of unexpected package/build variance. Registry/CDN access was NOT granted by source-only 'proceed'.
- Runner: GitHub Actions `GITHUB_*` environment variables in a test are self-reporting claims. Require separately trusted ephemeral runner and engine identity, no shared persistent protected resources, no production credentials, approved one-use run ID/attempt and audit trail.
- Network: Docker bridge mode and `127.0.0.1:3306` config are not proof of outbound network egress block or actually isolated listener; validate host and container namespace state independently before SQL.
- Volume: WS52-KH old zero-mount model conflicts with MariaDB config's declared default VOLUME; WS52-KT proposes an explicit named one-use local volume model, but no real engine has confirmed that replacing the implicit anonymous volume prevents second mounts. Exact pre/post inventories needed.

## D. Stage 3 — historical database audit specification (read-only and separately authorized)

**Non-negotiable before any data access:** exact authorized owner/source of the production-derived backup, expected dump timestamp and logical schema, encrypted copy handling, customer-data controls, verified restore *only into an approved isolated clone with no production/staging networks*, operator approval, read-only DB identity and audit log. Do not run SQL or restore based on this document alone.

Safe future audit questions, to be answered with aggregates/redacted evidence rather than customer rows:

1. Inventory actual historical schema name, server/version, table and column names and migration markers; compare to reviewed source migration state, without presuming production matches current `database_schema.sql`.
2. In the three Daily Record tables, determine legacy records with NULL `cycle_id`, missing/invalid cycle references and any farm ownership mismatch across record, cycle, species, and effective farm tenancy. Identify whether audit would require left-join semantics (foreign keys do not prevent NULL).
3. Identify duplicate logical daily-record keys, historical revision column values and negative/overflow revisions, without constructing destructive repair SQL or changing permissions.
4. Check foreign-key constraints, orphan rows and scope of any migration 092 predicates, including zero cross-farm updates/joins; determine whether indexes and constraints uphold the same invariants as the shared CAS implementation.
5. Record counts plus independently verified query plans/locks/transaction isolation so eventual migration review cannot surprise production. Do not export personal/customer identifiers, farm names or individual database rows into chat.
6. Produce read-only audit artifacts, condition list and a *separate* migration decision proposal; no DDL, UPDATE, DELETE, triggers, grants, runtime cutover, customer secrets or production contact.

**Current outcome:** Stage 3 cannot be certified from GitHub source or config evidence. A physical customer-data read-only audit requires a separately approved historical clone. Previous local Docker discovery expressly did not establish historical clone provenance or execute any queries.

## E. Stage 4–6 RC01 candidate source observations, not runtime PASS

- `tests/integration/k2p03_b12_gh_rc01_preliminary_synthetic.php` is **HARD-DISABLED** (`B12_RC01_EXECUTION_ENABLED=false`) and has source pins, a GitHub-specific runner identity gate, expected database `renee_b12_gh_rc01_synthetic_only` on `127.0.0.1:3306`, MariaDB `10.11.x`, and expected `@@server_id` guard.
- Its source metadata gate expects **69** empty synthetic base tables, **886** columns and **three** Daily Record revision columns and preserved **three** source cycle foreign keys. It checks zero stored side-effect objects and an empty migrations table. These counts are conditions asserted by source, **not** observed from a newly created DB in this step.
- Candidate has two independent PHP worker processes and third observer using `information_schema.INNODB_LOCK_WAITS`/INNODB_TRX; obtaining observer permission and true lock proof is not established. Source has synthetic fixture teardown and lock-wait barrier; no real execution PASS.
- The old preflight hard-off and zero physical-ready counters prevent executing RC01 by source-only 'proceed'. Authorization to run **one** synthetic attempt does not automatically authorize all RC02–RC14 tests or the final migration.

## F. STOP and authorization matrix

- **Permitted now:** read GitHub sources, source-only design/static review, create files on isolated feature branch, inspect Git blobs, build offline fixtures, independently review remote semantic diffs and record evidence.
- **Separate explicit approval required:** pull/check Docker image layers, inspect host Docker metadata or network, create/stop/delete containers or volumes, create/restore/use any synthetic MariaDB DB, run SQL or RC01–RC14 workers, invoke capacity tests, access historical production-derived clone, run a GitHub Actions physical workflow, alter staging/production, or authorize migration/cutover.
- In any failed host/data/volume identity guard, insufficient permission, unknown hidden mount, ambiguity about provenance, unexpected extra volume/container, stale worker/open transaction or source commit mismatch: **STOP, preserve protected resources, quarantine only with approved plan**. Never broad-prune or infer success.
- Maintain `RUNTIME_APPROVAL=NOT_GRANTED`, `REPAIRED_RC01_RC14_CERTIFIED_PASSES=0`, `MIGRATION_092=NOT_AUTHORIZED`, `B12_CUTOVER=HARD_OFF` until the appropriate independently evidenced authorization gates change.

## G. Immediate next allowed work

WS52-LD finishes the actionable broker design review and closes the **source-only readiness inventory**, not actual Stage 1/2/3 certification. Next useful source-only action is implement/review an enforceable isolated broker and live raw-response minimization at code level *without granting access or running it*. In parallel assemble a redacted historical clone provenance/access approval request, without touching the existing backup. Avoid new layers of unexecuted fictional status models that obscure the physical gaps.
