# C137 / GC24 / K2-P03 B12 — single authorized MariaDB concurrency failure

**Result: B12 FAIL. Execution authority consumed. No reruns. No application fixes in this checkpoint.**

## Exact GitHub evidence

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`
- Branch: `c137-gc24-k2p03-ci-mariadb-b12`
- Explicitly approved source review HEAD: `bd5b7de86b7622f7929928f6072d0a9074af87cc`
- Only execution authorization commit: `c26beac3e74256d071bcc453516b9cb6592bd024`
- Approval commit parent: reviewed HEAD above; **only new file** `docs/C137_GC24_K2_P03_B12_EXECUTION_APPROVAL.txt`; exactly two lines.
- Workflow run: [GitHub Actions B12 run 38233373699](https://github.com/abeycity4u/Renee-Farms-V2.2.50-Batch3B1/actions/runs/38233373699)
- Job: `114757993448`
- Date: **2026-10-12**, one push-triggered GitHub-hosted execution, **attempt 1**. The run reached `completed` with `conclusion=failure` at `2026-10-12T09:59:47Z`.
- Disposable database: `renee_k2p03_ci_b12`; GitHub Actions MariaDB 10.11 service; fake, synthetic CI-only identity and data.
- Execution did not use production, shared staging, cPanel, AWS Nodes A/B/C, protected D12 fixtures, restore databases, tenant credentials or capacity/load tiers.

## Passing isolation and setup boundaries

- One-use authorization commit guard: PASS.
- Source pin, ephemeral target and empty-schema guard: `EPHEMERAL_DATABASE_IDENTITY=PASS`, `EPHEMERAL_SCHEMA_INITIAL_EMPTY=PASS`, `PINNED_SOURCE_AND_SCHEMA=PASS`.
- Guarded 69-table disposable MariaDB schema import: COMPLETE.
- PHP 8.2 lint: both `tests/integration/k2p03_ci_isolation.php` and `tests/integration/k2p03_b12_real_mariadb.php` had **no syntax errors**.
- One verified real PDO mysql driver and MariaDB server. InnoDB checks for protected source tables passed.
- Two independent child PHP processes, **two distinct actual MariaDB connection IDs**, and matching observed isolation values passed.
- Both workers initially read the same original Daily Record mortality. The second UPDATE demonstrably waited on A's held uncommitted target-row lock.
- Both child processes exited normally and returned structured outcomes; the controller had no open transaction.
- All nine recorded ledger scopes of the **other synthetic farm** remained identical.
- Final source-owned mortality movement, feed USED movement, feed inventory balance, later opening stock and population ledger each reconciled. One pooled Layer egg allocation remained, without spurious manual allocation revision. The sale source record was unchanged.
- The test emitted **52 logged `PASS:` lines before its intentional failing assertion**. These are supporting checkpoints, not B12 certification or an addition to historical certified counts.
- `B12_LOCAL_WORKER_CLEANUP=COMPLETE` and GitHub's job `Stop containers` passed; container and network removal are visible in the GitHub job log.

## Exact failed assertion / first decisive failure

```text
B12_CONCURRENCY_OUTCOME=TWO_STALE_COMMITS
FAILURE_STAGE=NO_SILENT_OVERWRITE
FAILURE_CLASS=RuntimeException
K2_P03_B12_REAL_MARIADB=FAIL
B12_LOCAL_WORKER_CLEANUP=COMPLETE
```

The source-route-equivalent B12 test committed **both** editors' stale writes on the **same Layer farm/cycle/date** after one waited for the other's record lock. Existing downstream continuity, feed and canonical projection constraints left the durable ledgers internally reconciled, but **did not refuse the second editor's stale target update**.

This is a repeatable-candidate **demonstrated once** in isolated MariaDB with the B12 source-route-equivalent test; **do not claim HTTP E2E, production impact, other modules, every isolation level or a general deadlock diagnosis was tested.** The test did not invoke the entire browser route, authenticate a user session, or exhaustively establish concurrency behavior of Broiler/Ruminant writers.

## Source-derived cause / safe follow-up boundaries

- `poultry/layers_daily_record.php` performs the existing source lookup before its caller transaction and executes the target Layer UPDATE without an expected previous-value or revision predicate.
- `daily_population_continuity_apply` locks and guards **later** record rewrites, but this does not by itself reject the target record's stale editor.
- The lock wait confirms mutual exclusion while the first edit is in-flight; it **does not imply stale editor rejection** after the first commits.
- Follow-up needs a separate **source-only architectural correction design** preserving `One fix, fit it all`: inspect authoritative source-version or explicit expected-state compare mechanisms, begin a safe transaction boundary before authoritative validation, and map consistent lock order across source, cycle/baseline, projection, feed/stock and automatic sale.
- Do not immediately patch only the Layer page or copy business rules to Broiler/Ruminant. Study shared write services and permissions/CSRF first; preserve farm/cycle/actor/tenant authority and user-friendly safe-conflict UI. No blanket retriable overwrite.
- A future correction + regression run requires **a new, explicit, pinned one-time authorization** on a separately prepared and remotely audited isolated branch. This run's one-use approval has been consumed. **Do not retry or rerun** this test automatically.

## Unchanged protected gates

B01–B11 remain at previously recorded **1,195 cumulative certified assertions**, not retested. **B12 is NOT CERTIFIED**; no certified assertion total increases. D12's 15 import gates remain BLOCKED; 150 VU HOLD; 200 VU PROHIBITED; Stage D NOT AUTHORIZED. No source product/schema/migration was changed, and no infrastructure deployment occurred.

**This failure-report commit is documentation-only and must not trigger the B12 approval-path workflow.**
