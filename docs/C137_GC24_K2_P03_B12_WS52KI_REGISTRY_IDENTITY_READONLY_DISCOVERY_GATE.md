# C137 / GC24 / K2-P03 / B12 — WS52-KI registry image identity and runner attestation, read-only gate

**Date:** 2026-10-15  
**Scope:** User authorized **read-only** immutable MariaDB 10.11 image identity and ephemeral GitHub environment attestation planning, expressly without Docker image pull/start, SQL, customer backup, real CAS workers or product cutover. The currently available GitHub interface cannot query the Docker Hub registry directly. Therefore this phase produces a **strictly read-only registry-metadata resolver**, **offline negative tests**, and a reviewed plan; **no live OCI image digest is asserted**.

**Result:** `WS52KI=SOURCE_REVIEW_AND_OFFLINE_PARSER_PASS`; `OCI_INDEX_DIGEST=NOT_ESTABLISHED`; `LINUX_AMD64_MANIFEST_DIGEST=NOT_ESTABLISHED`; `RUNNER_CONTAINER_ID=NOT_CREATED`; `ACTUAL_MARIADB_DATABASE=NOT_CREATED`; `RC01_EXECUTED=NO`; `REPAIRED_RC01_RC14_CERTIFIED_PASSES=0`; `B12_CUTOVER=HARD_OFF`.

## 1. GitHub source and scope guards

| Object | Pinned identity |
|---|---|
| Repository | `abeycity4u/Renee-Farms-V2.2.50-Batch3B1` |
| WS52-KH parent branch | `c137-gc24-k2p03-b12-ws52kh-rc01-isolation-cleanup-disabled-source` |
| WS52-KH parent HEAD | `4666ef064b662a2d6383419b403f4151b74a1865` |
| New WS52-KI branch | `c137-gc24-k2p03-b12-ws52ki-registry-image-identity-source-review` |
| Standalone read-only OCI index parser | `tools/b12_ws52ki_mariadb_registry_manifest_readonly.py` Git blob **`ab109cd44b6c3965f284b3d53bc15a242a1fb970`** |
| Eight fixture-free parser tests | `tests/test_b12_ws52ki_mariadb_registry_manifest_readonly.py` blob **`05fcecfa428560bb0203e45452f8562609aff061`** |
| Offline-only workflow | `.github/workflows/c137-b12-ws52ki-manifest-parser-source-only-once.yml` blob **`cfdc0b15505d065157ad7ec82abde51f03bd72cc`** |
| One-use offline trigger/approval | `docs/C137_GC24_K2_P03_B12_WS52KI_ONE_USE_OFFLINE_APPROVAL.txt`, committed in `abff775181a7efedd6693bde214d1eb9aac45987` |
| Successful GitHub workflow | Run **`38791227852`** (run 1, attempt 1, completed/success); job **`116615990323`** |
| GitHub run URL | `https://github.com/abeycity4u/Renee-Farms-V2.2.50-Batch3B1/actions/runs/38791227852` |
| Protected B12 branch | `c137-gc24-k2p03-b12-cas-writer-cutover-source` @ `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af` |
| Protected production branch | `v320-renee-agrisuite-branding` @ `10041da3a3c4e4a7b8101f8183c243d056c7b128` |
| Existing GH RC01 hard-disabled candidate | `tests/integration/k2p03_b12_gh_rc01_preliminary_synthetic.php`, blob `e78318509e270ccd24684bf41f5e854b74765f76` |
| Existing RC01 Mac harness | `tests/integration/k2p03_b12_repaired_rc01_real_mariadb.php`, blob `6f17d1a4ba7e98dbf286d1c8e1a147e6ac3e4aad` |
| Shared CAS authority | `lib/daily_record_revision_authority.php`, blob `5fbb90903b0cc801be45c684f6c3db85c660979a` |
| Literal B12 gate | `lib/daily_record_revision_cutover_gate.php`, blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268` |
| WS52-KH policy | `tools/b12_ws52kh_rc01_isolation_cleanup_contract.py`, blob `f5f9b82453fae41cc8bcc070414d988f052c5e14` |

**Prior evidence verified:** WS29 user-returned Mac Docker read-only inventory reported a **locally cached Linux AMD64 MariaDB 10.11 image**, but only a 16-character **hash-of-local-image-ID fingerprint** `1fbb47444479daff`, **not** a full OCI registry digest. WS30's 11.4 backup-header marker and local MariaDB 11.4 candidate belong to a **different blocked historical-restore track**, not this RC01 GH synthetic 10.11 target. Prior R2 synthetic GitHub MariaDB **`mariadb:10.11`** used a **mutable tag**, not a registry-digest pin. Thus none establish an immutable **GitHub-hosted** MariaDB image.

## 2. Offline code prepared; no registry operation performed

The new standalone Python module has an explicit guard `sys.platform == 'darwin'` when run as a CLI. If the user elects to run it on their Mac, it requests **only** public anonymous metadata: an HTTPS GET of `https://auth.docker.io/token?service=registry.docker.io&scope=repository:library/mariadb:pull`, an HTTPS **HEAD** of `https://registry-1.docker.io/v2/library/mariadb/manifests/10.11`, and an HTTPS **GET** of the exact returned **digest-pinned OCI/Docker manifest index** from the same registry. It never fetches a child manifest/config/layer blob and never calls Docker, a local database, GitHub workflows, shell commands, a customer file, or application code. TLS validation is enabled; redirects and URL-proxy environment settings are excluded. The short-lived anonymous pull-scoped bearer token is used only in memory and **never printed**.

The pure parser validates index byte length, exact SHA-256 of returned bytes versus the tag HEAD's `Docker-Content-Digest` **and** the digest GET response header; rejects JSON with duplicate object keys, incorrect schemaVersion or index content media type; and accepts exactly **one** reviewed `linux/amd64` child manifest with valid SHA-256 and OCI/Docker v2 media type. It emits only the index digest and platform-specific child digest (if validated), an observation timestamp and fixed status fields—no bearer token, response body, cookies, local file paths or credential strings.

**Important nuance:** Registry tag `10.11` is mutable, so a validated index digest identifies **the manifest observed at one instant**. It is not a cryptographic publisher signature, proof of trusted official-image provenance, a pinned running container ID, local Docker image ID, or evidence that a future runner will pull/run that exact index. Manifest-list/index and per-architecture **child** SHA256 are different objects; a config-derived local image ID is different again. A digest from the user's Mac's read-only Docker Hub query is **only a candidate for an independently reviewed future Linux AMD64 GH job**, not authorization to use it. Always use the immutable index digest explicitly; independently attest the chosen platform and resulting image ID on the actual future runtime.

**Current evidence restriction:** The assistant did **not** perform the public registry network request. Any missing or unexpected HTTP response yields `REGISTRY_METADATA_NOT_ATTESTED`; there is no fallback to a guessed digest, an earlier local fingerprint, tag-only `mariadb:10.11`, or a different 11.4 image.

## 3. Genuine offline unit-test result

One new one-use GitHub Actions workflow was source-checked before an exact one-file approval commit. Its only job has `permissions: {}`, no MariaDB or Docker service, no checkout of application data, no repository credential use, and only fetches the two exact parser/test files from immutable GitHub commit `bca718d8da92e91301da1c59e15ae306b14a293a` by exact Git blob IDs. It runs Python **only on fictional JSON fixtures**, never invoking the network-facing `main()`.

**Run:** `38791227852` and job `116615990323`, status `completed`, conclusion `success` (both job and run independently fetched). Exact decoded log evidence:

```text
#####COPY FROM HERE#####
AUDIT=B12_WS52KI_OCI_INDEX_PARSER_OFFLINE
TWO_EXACT_SOURCE_BLOBS=PASS
PURE_OCI_INDEX_TESTS=PASS_8_OF_8
REGISTRY_REQUESTS=0
MARIADB_OCI_DIGEST=NOT_ESTABLISHED
DOCKER_OPERATIONS=NO
DATABASE_CONNECTIONS=0
SQL_EXECUTED=NO
RC01_WORKERS_EXECUTED=NO
IMAGE_LAYERS_DOWNLOADED=NO
BACKUP_ACCESSED=NO
RC01_RUNTIME_AUTHORIZATION=NO
REPAIRED_RC01_RC14_CERTIFIED_PASSES=0
MIGRATION_092=NOT_AUTHORIZED
B12_CUTOVER=OFF
RESULT=PASS_OFFLINE_ONLY
#####COPY TO HERE#####
```

The eight tests exercise correct fictional Linux AMD64 child selection and rejection of altered bytes, duplicate/missing architecture, wrong media type and schema version, invalid digest and child metadata, oversized input and duplicate JSON keys. The eighth also checks source-stated allowlisted public endpoints/methods and absence of explicit Docker/SQL operations. These **offline source tests are not an external registry resolution test**.

## 4. Concrete host-side design and remaining blockers

The WS52-KH synthetic contract is deliberately **shape-only**; users can fabricate all its input values. Its fields `APPROVED_MARIADB_IMAGE=None`, `APPROVED_RUNTIME_COMMIT=None`, `APPROVED_ONE_USE_RUN=None`, `RC01_EXECUTION_ENABLED=False` **remain unset**, and `execution_authorized=False` for all inputs.

A future runtime inspector, separately approved for source/physical checks, must:

1. **Source and run identity:** Confirm source commit, exact branch and one-use run/attempt, GitHub-hosted ephemeral runner scope, fetched **digest-pinned** MariaDB manifest, Linux AMD64 platform, immutable image ID, actual Docker Engine/container IDs, and the reviewed CAS/gate/schema Git blobs; do not trust environment declarations alone.
2. **Mount/network design:** Check real container runtime config against a closed list before writing test data: no existing/protected volume, no host/backup mounts, no privileged container, bounded resources, no public port, independently verified egress/network restrictions. The current GH RC01 candidate assumes `127.0.0.1:3306` TCP, i.e. **loopback publication—not network-disabled**. A `bridge` network with `restricted_egress_attested=True` in a synthetic policy object proves nothing; test actual controls. If strict network-none is required, the PHP test and runner transport must be changed in a **new reviewed hard-disabled** source branch and re-linted; never spoof an isolation flag.
3. **Image volumes caveat:** Docker Official MariaDB images may declare a data-directory `VOLUME`, which can result in a **new anonymous volume** even when the workflow has no explicit mount. The current WS52-KH synthetic policy requires `mounts=[]`, and a real official-image container might **fail that strict check**. Do not silently disable it or assert empty mounts. Instead independently inspect the **reviewed 10.11 image** and either use an explicit, identity-scoped exclusively new disposable volume with reviewed teardown, or stop and amend the policy through another source-only review. **Do not touch the existing protected Mac volumes.**
4. **Privileges:** A real `INNODB_LOCK_WAITS`/transaction observation on MariaDB 10.11 may require elevated observer privileges. Validate scoped test DB user and metadata observer grants in a **separately authorized** disposable test context; `root` throughout is not assumed safe/necessary. MariaDB 11.4 restoration precedent is irrelevant.
5. **Synthetic-only schema:** Pin `database_schema.sql` blob `e77f287d62c5812a3d55dc23b5b20f5d167db389` and independently attest a fresh, otherwise empty 69-table schema before exactly three *test-only* revision-column ALTER operations. MariaDB DDL is non-atomic; partial success must fail closed. No production migration `092` or generic migration script.
6. **Exact scoped cleanup:** Bound total runner, transaction, process and lock waits; independent third observer, post-commit persisted state, exact fixture teardown, zero business rows/transactions/workers, complete cleanup of the **new** image-derived container/volume and barrier files. Failed identity or cleanup => **MANUAL_REVIEW/STOP**, not automatic retry. No backup/customer data, raw secrets or identifiable rows in logs.
7. **Separate authorization:** Before any Docker pull/container creation, actual MariaDB connection, SQL or two-worker RC01 race, prepare a separate reviewed source commit and user-authorized **one-use** exact image/host/database/fixture/cleanup scope. The original historical B12 run `38233373699` remains FAILED `TWO_STALE_COMMITS`. A preliminary synthetic-only RC01 result is **NON-CERTIFYING**, never a replacement for the independently required actual legacy-schema/NULL-cycle/cross-farm read-only clone audit.

**Next specific safe user-side action:** Run the exact *source-Git-blob-pinned* WS52-KI read-only manifest query on your Mac and return only its sanitized `#####COPY FROM HERE#####` block. It downloads a verified copy of this standalone Python code **into memory only** and makes the three above-described public registry metadata requests; no local Docker resource, image, volume, SQL or backup is accessed. This will resolve either a specific **candidate OCI index+Linux AMD64 child digest** or a clearly reported read-only registry blocker. A successful query **does not** satisfy runner/container physical identity, publisher provenance or runtime approval.

## 5. Frozen B12 release status / remote semantic diff

- `REPAIRED_RC01_RC14_CERTIFIED_PASSES=0`; `HISTORIC_B12_RUN=38233373699_FAILED_TWO_STALE_COMMITS`.
- `REAL_LEGACY_SCHEMA_AUDIT=NOT_COMPLETED`; `HISTORICAL_RESTORE=NO_GO`; `MIGRATION_092=NOT_AUTHORIZED`.
- `D12_IMPORT_GATES=15_BLOCKED`; `STAGE_D=UNAUTHORIZED`; `150_VU=HOLD`; `200_VU=PROHIBITED`; `B12_CUTOVER=HARD_OFF`.
- Compare WS52-KI branch against exact parent `4666ef064b662a2d6383419b403f4151b74a1865`: expect five **added** paths only—read-only registry checker, eight source-contract tests, one-use offline-only workflow, consumed one-use offline approval and this report. Exactly one completed successful offline WS52-KI Actions run, **zero registry requests from that CI job**, no product, source CAS, old harness, schema, migrations or protected branch modification, no database service or network registry lookup by the assistant. Independently recheck both protected HEADs and all frozen source blobs.
