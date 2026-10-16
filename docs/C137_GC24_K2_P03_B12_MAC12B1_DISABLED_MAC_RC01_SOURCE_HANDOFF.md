# B12 MAC-12B1 — hard-disabled Mac-specific preliminary RC01 harness

**Date:** 2026-10-16  
**Scope:** User-authorized source-only staging on an isolated successor GitHub branch. **No RC01 execution authorization**, no synthetic SQL, no PHP runtime invocation, no Docker actions, no migration, no production contact and no change to the original B12 CAS authority.

## Exact source provenance

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`
- Parent B12 source/evidence ref: `c137-gc24-k2p03-b12-ws52lz-oneuse-readonly-preflight` at `4e8f78c247862082693be6781fd5f292fe428f05`
- New branch: `c137-gc24-k2p03-b12-mac12b1-disabled-rc01`
- **New candidate only:** `tests/integration/k2p03_b12_mac_rc01_preliminary_disabled.php`, blob `736d6c0210b97a46bbf360e6e40931ee3f0751ac`
- **New focused source verifier:** `tests/test_b12_mac12b1_disabled_rc01_contract.py`, blob `76aee490263aa4291195707649f23699466ee266`
- Unmodified original RC01: `tests/integration/k2p03_b12_repaired_rc01_real_mariadb.php`, blob `6f17d1a4ba7e98dbf286d1c8e1a147e6ac3e4aad`
- Unmodified shared CAS: `lib/daily_record_revision_authority.php`, blob `5fbb90903b0cc801be45c684f6c3db85c660979a`
- Unmodified cutover gate: `lib/daily_record_revision_cutover_gate.php`, blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268` — always returns `false`
- Unmodified source install schema: `database_schema.sql`, blob `e77f287d62c5812a3d55dc23b5b20f5d167db389`

## Mac runtime baseline (user-returned evidence; not re-run in this source stage)

MAC-10B2: isolated MariaDB 10.11 baseline imported `69` application tables, `883` columns, `193` foreign keys.  
MAC-11: exactly three test-only `record_revision BIGINT UNSIGNED NOT NULL DEFAULT 1` columns added; final `886` columns, `69` tables, `193` FKs, unchanged indexes and nullable-cycle source definitions.  
MAC-12A4: separate cached PHP 8.2.34 + `pdo_mysql` test image, local ID `sha256:7399b946d775888f85299b9e1597a9ad633cd164cfc816b16f1f77e2da839445`.  
MAC-12A5: `b12_test` TCP connection inside database network namespace and DB-local root `INNODB_LOCK_WAITS` / `INNODB_TRX` query permission both PASS; actual lock contention was **not** observed. DB has no published host ports.

## Exact candidate delta

- Literal `B12_RC01_EXECUTION_ENABLED = false` remains at the top-level and exits `90` before any worker or DB operation.
- PHP PDO connects as `b12_test` to `127.0.0.1:3306` / `renee_b12_disposable` **only if** future independent Mac isolation, TCP, DB name, and closed barrier-parent flags are supplied. These env flags are inputs, not independent attestations.
- Root password is **not** accepted by the PHP harness. All root-observation access must remain inside the existing DB container, under a separately approved and reviewed host-side observer.
- Original PHP shared CAS call, two-worker barrier ordering, source blob guards, precise synthetic fixture, winner-only durable-state assertions and scoped teardown remain in the candidate.
- PHP controller now requires a host-observable `/b12-run/b12_rc01_*` private barrier directory, waits for the exact `OBSERVER_PASS` marker with payload `<A_CONNECTION_ID>:<B_CONNECTION_ID>\n`, and rejects a mismatched payload.
- **Security boundary:** a file marker is **not evidence** of an actual InnoDB wait unless a separately reviewed host observer independently observes `B` blocked on `A` in `INNODB_LOCK_WAITS` + `INNODB_TRX` and only then creates that exact marker. This host executor has **not** been implemented in MAC-12B1. Do not claim an RC01 PASS using a manually created marker.

## Source stage verification / missing evidence

- Verified GitHub source identities and structural assertions during authoring; exact new blobs re-fetched from GitHub.
- Focused fixture-free Python unittest added but **not yet executed** against a full local checkout.
- New PHP candidate **not yet PHP 8.2 linted** and hard-OFF exit `90` **not yet independently smoke-tested**. Previously passed GitHub PHP 8.2 smoke covered the **original** blob only.
- No GitHub Actions execution authorized by this stage. No new workflow, service, executable one-use approval or production change.

## Required future gates (not covered by current authorization)

1. Perform offline Python focused-verifier run and isolated PHP 8.2 syntax + literal disabled-exit checks on the **exact pinned new source blob**; confirm the hard-disable path has no connections.
2. Implement and review a separate Mac host one-use supervisor: guard local Docker context, exact DB/image/container/network/volume, host runner source blob, new PHP harness blob, PHP image ID, test-only credential pipe, shared private barrier directory, worker/container lifecycle, root observer over DB-local socket, precise observer session-pair query, bounded timeouts, failure quarantine, no auto-retry, and independent post-run zero-record cleanup checks. No docker.sock or root credential mount inside PHP.
3. Confirm complete synthetic empty 69-table preflight at actual execution time; confirm MariaDB `PROCESS`-level observer can see a **real** B-on-A transaction wait, rather than only read zero-row metadata. Validate any fixture/schema assumptions against the already pinned `69/886` environment.
4. Obtain a **fresh, explicit one-use authorization** naming exact runner/harness commits and blobs, the only allowed three synthetic fixture inserts, worker CAS updates, exact per-fixture cleanup and quarantine handling. Altering this literal PHP fuse requires a separately reviewed source commit, not runtime sed or unapproved flags.
5. A passing fabricated-only RC01 would be **preliminary/non-certifying**. The original legacy tenant/nullable-cycle gate, full save/deletes/browser path tests and formal RC01–RC14 certification remain separate, and must not be claimed closed by synthetic-only evidence.

**Unchanged policy:** `MIGRATION_092=NOT_AUTHORIZED`; `B12_CUTOVER=HARD_OFF`; `REPAIRED_RC01_RC14_CERTIFIED_PASSES=0`; no historical production-derived backup restore; no customer data or protected production/staging/AWS environment access.
