# C137 / B12 — MAC-12B3 source-only host observer and supervisor

**Date:** 2026-10-16. **Classification:** SOURCE ONLY; no approved or executed RC01 race, SQL fixture, additional privileged grant, database mutation, Docker PHP execution, or B12 activation.

## Parent and changed files

- Repo: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`
- Working branch: `c137-gc24-k2p03-b12-mac12b1-disabled-rc01`
- Previous reviewed checkpoint: `d5416d6b292db5c44c54e47156411759b54bedc9`.
- **Modified only:** `tests/integration/k2p03_b12_mac_rc01_preliminary_disabled.php`, Git blob `2e288c611e0b43217eb1b8e15234ee823b65f940`. PHP remains `B12_RC01_EXECUTION_ENABLED=false`; it now reads the **test-only password** from `/b12-private/test_password` (container-local tmpfs, required 0600 mode) instead of Docker environment. The fixed `B12_RC01_DB_PASSWORD_FILE` environment value exposes only this path, not the credential.
- **New:** `tools/b12_mac12b3_rc01_supervisor_disabled.py`, Git blob `620c32da7afc34bf79c00b329444d4687946763f`. Python remains `EXECUTION_ENABLED=False`, exits 90 before any Docker, database, network, filesystem or worker access. Three separate approval sentinels remain unset; a future, independently reviewed one-use source/launcher approval will be necessary. They cannot be enabled by simply adding a runtime environment flag.
- **New:** `tests/test_b12_mac12b3_host_supervisor_offline.py`, Git blob `bfaf8a487defaddca8aa6236e037e3ab9f169577`. **16 focused offline tests added** for hard-OFF, pinned authorities, true lock-wait query direction, malformed/matched session IDs, forged marker, atomic marker, missing wait, scoped PHP binds/secret handling and refusal to stop an unowned container.
- Original B12 CAS remains `5fbb90903b0cc801be45c684f6c3db85c660979a`; cutover remains the hard-OFF `6e88c913ad5e5b371a9d0c92ce08870214ac1268`; source schema remains `e77f287d62c5812a3d55dc23b5b20f5d167db389`.

## Execution architecture staged, not executed

The future Mac Python supervisor is designed to verify its exact reviewed source/blob, Git checkout branch and clean tree, isolated Docker context, exact MariaDB pinned image/container/volume/internal network, PHP image ID, and a **fully empty** disposable 69-table/886-column schema before fixtures. It selects exactly one ephemeral owned PHP container attached to the existing DB container's **network namespace** (no published ports). It mounts only five pinned PHP/schema source files read-only and an owned private barrier directory writable. The test-only password is piped from the existing DB container's test credential file to the PHP container's 0600 tmpfs file, not exposed through `docker inspect`; root credentials never leave the DB container.

PHP's existing controller and worker code must independently establish two PDO server connection IDs. The host observer polls `INNODB_LOCK_WAITS` joined to `INNODB_TRX` with DB-local root access, accepts only **B blocked on A**, and only then writes `OBSERVER_PASS` atomically with payload `<A_ID>:<B_ID>\n`. Merely seeing `B_ATTEMPT` or a manually written proof marker is **not** evidence; a forged or stale marker blocks the supervisor. It verifies the expected six canonical PHP RC01 evidence lines and post-run schema emptiness, plus the PHP barrier being removed. Stopping is confined to the uniquely named and labeled owned container. Unexpected state causes quarantine/manual review, never blind fixture SQL cleanup, cross-project resource deletion, or automatic retry.

## Evidence and unresolved blockers

**Already completed and not repeated in this source stage (user-returned Mac evidence):** MAC-10B2 (69/883 baseline); MAC-11 (69/886 revision schema); MAC-12A4 (PHP 8.2.34 + pdo_mysql image `sha256:7399b946d775888f85299b9e1597a9ad633cd164cfc816b16f1f77e2da839445`); MAC-12A5 (PDO test-user DB TCP and DB-local root lock-metadata SELECT permissions); MAC-12B2 (old Mac harness six Python offline tests, PHP lint, hard-disabled exit90). **MAC-12B2 does not test the newly changed PHP blob or newly authored Python source.**

**Still required:** Execute the 16 new offline unit tests and new PHP lint/hard-OFF exit90 in an isolated runtime with the exact newly pinned Git blobs (MAC-12B4). Resolve any negative-test failures in source before another commit. Independently review one-use approval and source-self-pin mechanics; runtime identity, PHP container permissions, tmpfs and barrier mount assumptions still require a controlled dry-run under a separate authorization. Review whether the future runner should use an independently signed/recorded approval manifest rather than modifying its own commit/blob sentinels, which cannot be self-referential literal SHA values. No actual lock wait has yet been observed.

**Formal scope limit:** A successful later fully fabricated RC01 would be **preliminary/non-certifying**, not the legacy tenant/nullable-cycle audit, cross-module browser/role verification, RC01–RC14 certification or production cutover. `REPAIRED_RC01_RC14_CERTIFIED_PASSES=0`; `MIGRATION_092=NOT_AUTHORIZED`; `B12_CUTOVER=HARD_OFF`. No historical production-derived backup was opened or restored.

## Required next gate

MAC-12B4: one offline local source-blob verification and 16 tests + new PHP lint/hard-OFF smoke, no SQL and no worker execution. Then independent host observer/runner review, separate authorization for the exact one-use synthetic fixture inserts/CAS attempts/cleanup, and only then potential preliminary real two-connection test.
