# C137 / GC24 / K2-P03 / B12 — WS52-KS runtime isolation and MariaDB volume safety review

**Date:** 2026-10-15
**Status:** SOURCE_REVIEW_COMPLETE / PHYSICAL_RUNTIME_NO_GO / B12_CUTOVER=HARD_OFF.
**Task boundary:** documentation-only, grounded in independently fetched GitHub source and user-returned OCI configuration integrity evidence. No image pull, Docker engine, container, volume, GitHub Actions runtime, SQL, backup access, RC01 worker, migration, or live deployment was performed.

## 1. Exact source checkpoint and protection

| Source/reference | Verified SHA or identity |
|---|---|
| Repository | abeycity4u/Renee-Farms-V2.2.50-Batch3B1 |
| WS52-KR exact parent HEAD | 4e2bb60b34b1f3008f07bac23d75deae4ed4f25b |
| New documentation-only successor | c137-gc24-k2p03-b12-ws52ks-runtime-volume-isolation-source-review |
| WS52-KH fixture-only isolation/cleanup policy | tools/b12_ws52kh_rc01_isolation_cleanup_contract.py, blob f5f9b82453fae41cc8bcc070414d988f052c5e14 |
| Preliminary hard-disabled GH RC01 | tests/integration/k2p03_b12_gh_rc01_preliminary_synthetic.php, blob e78318509e270ccd24684bf41f5e854b74765f76 |
| User-returned verified OCI config report | docs/C137_GC24_K2_P03_B12_WS52KR_WS52KO_READONLY_CONFIG_BLOB_SHA256_PASS.md, blob 466c99868f07c097bb54886d74746110d59768a9 |
| Literal B12 disabled cutover | lib/daily_record_revision_cutover_gate.php, blob 6e88c913ad5e5b371a9d0c92ce08870214ac1268 |
| Shared CAS authority | lib/daily_record_revision_authority.php, blob 5fbb90903b0cc801be45c684f6c3db85c660979a |
| Protected original B12 branch | c137-gc24-k2p03-b12-cas-writer-cutover-source @ 2327a22a3cd7a95ffea42fc76dd94805f5b1e7af |
| Protected production branch | v320-renee-agrisuite-branding @ 10041da3a3c4e4a7b8101f8183c243d056c7b128 |

The WS52-KO registry/CDN SHA256 PASS is **user-returned Mac evidence**, not an independently reproduced external download or a GitHub Actions run. It identified immutable OCI index sha256:7db29378d4fdab73f8123bbc2b48905c90d1a4b00cf848b028f1e81e623257f2; selected linux/amd64 child sha256:dc9dd57be7f46ad6f350c598a331d64f295899c4abac3ec5652eff831fc38a6c; and config object sha256:41096ae9913468679bb5e05bbc19f44edcaf0eae45d67fcde224ffdeda9d3a1f (8,790 bytes). The hash-verified user-returned config describes linux/amd64, eight rootfs diff IDs, **one declared image VOLUME including /var/lib/mysql**, nonempty entrypoint and command arrays. No layer blob, running image, Docker engine, container, volume or provenance signature was attested.

## 2. BLOCKER A — effective-mount policy conflicts with image volume declaration

The pinned WS52-KH contract rejects ANY mounts: evidence["mounts"] must equal []. Otherwise it reports MOUNTED_HOST_OR_SHARED_VOLUMES. It intentionally leaves RC01_EXECUTION_ENABLED=False, APPROVED_MARIADB_IMAGE=None, APPROVED_RUNTIME_COMMIT=None, APPROVED_ONE_USE_RUN=None; even a simulated shape-valid fixture never grants execution_authorized=True.

MariaDB's independently hash-verified config **declares VOLUME /var/lib/mysql**. A Docker engine may create an anonymous data directory volume even if an operator omits an explicit mount. A declared VOLUME is not proof that a particular future engine created a mount; only effective runtime inspection can establish that. Consequently neither a fake empty mount list nor a declared VOLUME suffices as a real isolation attestation.

**Decision: retain the existing shape-only zero-mount policy and its HARD_OFF state. Do not silently relax it. Real runtime remains NO_GO until a replacement explicitly models and physically checks the effective data volume.**

**Recommended future design (NOT approved or executed):** allocate exactly one fresh, per-run, unguessable, uniquely named Docker-managed local disposable volume; attach solely to /var/lib/mysql; disallow all other effective mounts, bind mounts, host backups and pre-existing/protected volumes. An image-created additional anonymous volume is a STOP condition. Before provisioning, independently confirm the proposed volume does not exist on that engine. Before SQL, independently inspect actual container Mounts for Type=volume, precise source/name, target=/var/lib/mysql, permitted driver, writable mode, no additional entries; tie it to exact newly-created container/run identity. The present policy must only be changed in a separate reviewed hard-disabled source step; synthetic test inputs alone prove nothing. Do not assume tmpfs or network-none variants are compatible with MariaDB entrypoint and the existing PHP TCP harness.

## 3. Storage and container proof required BEFORE a real one-use fixture

1. **Host and source identity:** independently attest exact GitHub-hosted ephemeral runner, repository, protected source commit, separately approved one-use run/attempt, Docker engine, immutable OCI index/AMD64 child/config, actual fetched image ID and created container ID. Do not infer a physical image ID merely from a config digest or reuse mutable mariadb:10.11 by tag.
2. **Pre-creation inventory:** snapshot real container/volume identifiers, listeners, relevant isolation state and planned synthetic resource IDs. Prove no pre-existing protected/matching volume, host mount or port conflict; fail closed on any shared or unrecognized Docker host.
3. **New exact ownership:** establish unique per-run volume/container IDs and creation provenance on the **actual engine**. A regex-matching name, label or JSON declaration does not prove ownership. Never touch customer, Mac, AWS production, cPanel, staging, backup or unrelated volumes.
4. **Physical mount gate:** inspect the effective actual Mounts list **before any fixture data/SQL**; permit only one *newly proven* writable local volume at /var/lib/mysql, and **zero additional mounts/binds/protected paths**. Unexpected anonymous/second volume, driver/source drift, wrong target, privileged container, ambiguous metadata or unrecognized host => STOP.
5. **Network gate:** source harness requires 127.0.0.1:3306, whereas WS52-KH shape-only policy stipulates bridge, exactly one localhost published TCP port, and restricted_egress_attested=True. Bridge by itself does NOT block egress; a boolean flag is not real egress proof. Future physical inspection must confirm loopback-only reachability, no public exposure, firewall/egress restrictions, host isolation and absence of listener collision. Network-none cannot be substituted while retaining this TCP transport without a reviewed source redesign.
6. **Worker/SQL gate:** separate explicit one-use approval is mandatory for fresh DB, schema and two-worker race. Candidate PHP uses root credentials and independent INNODB_LOCK_WAITS observer: review least-privilege fixture DB user, separately scoped observer privileges and MariaDB 10.11 server identity; do not assume PROCESS grants are harmless. No actual connection or grants in WS52-KS.
7. **Teardown ordering:** stop and independently prove A/B workers, observer and transactions quiescent; remove exact synthetic farm/cycle/layer fixtures only after workers end; stop/remove exact observed container ID, then dispose **only** the independently proven one-use volume. No prune, wildcard deletion, inherited project volume deletion or emergency unscoped cleanup.
8. **Post-teardown independent comparison:** check original engine resource prestate restored, exact new container/volume gone, no extra anonymous volumes, no PHP barrier artifacts, zero residual workers/transactions and zero synthetic business data. Preserve sanitized evidence only. Any identity drift, incomplete cleanup, unexpected mount, privilege/network mismatch or timeout => BLOCKED_MANUAL_REVIEW; quarantine, STOP, never blindly retry/remove unknown resources.

**Gap in present source:** WS52-KH cleanup accepts a boolean ephemeral_volume_removed=True but has no mandatory volume ID, before/after volume inventory, actual container-mount identity linkage or independent cleanup proof. These need a reviewed physical inspector before a runtime teardown could be trusted. Do not declare cleanup complete from the boolean.

## 4. Adjacent source blockers — do not skip architecture dependencies

| Area | Verified source finding | Required separate review |
|---|---|---|
| Harness fuse | Preliminary GH RC01 defines B12_RC01_EXECUTION_ENABLED=false and exits 90 before DB access; WS52-KH never authorizes runtime. | Keep OFF; only separately reviewed one-use source + explicit execution approval may change it. |
| OCI integrity | Index, child, config user-returned hashes are established, but 8 config rootfs diff IDs are NOT downloaded/verified OCI layers, no actual engine/container and no signature. | Pin immutable OCI objects and independently attest physical image/engine and provenance; no implicit image authorization. |
| DB transport | PHP connects root to 127.0.0.1:3306; policy expects bridge/loopback and synthetic egress flag. | Prove live bindings, isolation, constrained credentials and observer grants before any SQL. |
| Synthetic schema | GH candidate checks otherwise empty 69-table source schema, 886 columns, three test revision columns, three SET NULL cycle FKs; schema blob e77f287d62c5812a3d55dc23b5b20f5d167db389. | Only a separate one-use fake DB may get exactly three reviewed test-only revision ALTERs; no migration 092, customer rows or historical restore. |
| Concurrency proof | GH RC01 uses A/B proc_open PHP workers, separate observer checking MariaDB INNODB_LOCK_WAITS, barrier under sys_get_temp_dir(); source is preliminary, not certifying. | Bound processes/locks/time; prove real independent sessions, barrier ownership and exact termination; RC02/downstream workflow remains separate. |
| Cleanup flow | PHP attempts worker termination then exact row cleanup and barrier quarantine on failure; it does NOT own the Docker volume or host container lifecycle. | New host-level exact-identity cleanup/preflight required with fail-closed manual-review behavior. |

The historically failed original B12 run 38233373699 remains FAILED_TWO_STALE_COMMITS. Even a later preliminary synthetic two-worker success would not certify the real legacy NULL-cycle, cross-farm and FK behavior or the full RC01–RC14 matrix. Historical production-derived database restore remains NO_GO without distinct approval.

## 5. Ordered future plan — not executed in WS52-KS

1. **NEXT SAFE SOURCE-ONLY STEP:** design a separate *hard-disabled* physical one-volume attestation/teardown policy and fixture-only negative tests. Keep WS52-KH original contract unmodified until deliberate reviewed replacement. The policy must make exact volume identity, pre/post engine inventory, actual Mounts and independent proof mandatory; mere fixture-shaped booleans insufficient.
2. Review immutable runner/workflow, OCI and build toolchain pinning, least privilege, network/port and egress controls, explicitly marked as noncertifying synthetic-only.
3. Review a complete one-use approval package with exact new Git commit, ephemeral host, intended image/volume, timeout/resource caps, test-only schema/SQL, observer grants and rollback/quarantine conditions. **Do not infer this approval from a generic 'proceed'.**
4. Only after explicit separate one-use authorization may a future GH runner create a fresh disposable image/container/volume, pass physical preflight, perform synthetic SQL/RC01 race, and independently prove teardown.
5. Actual legacy-schema read-only clone audit remains a distinct prerequisite before real B12 release certification. Migration 092 and B12 cutover stay prohibited.

## 6. Frozen result and GitHub evidence acceptance

**WS52KS=SOURCE_REVIEW_COMPLETE; PHYSICAL_MOUNT_ATTESTED=NO; ONE_USE_RUNTIME_APPROVAL=NOT_GRANTED; REAL_RC01=NOT_EXECUTED; REPAIRED_RC01_RC14_CERTIFIED_PASSES=0; MIGRATION_092=NOT_AUTHORIZED; B12_CUTOVER=HARD_OFF.**

This WS52-KS step is **one new Markdown report only** on a new successor branch based exactly on WS52-KR HEAD. The remote diff must be ahead one/behind zero with no code, test, workflow, migration, approval or protected branch changes and zero new Actions runs. Do not rerun already-passed WS52-KO user tests without regression evidence.
