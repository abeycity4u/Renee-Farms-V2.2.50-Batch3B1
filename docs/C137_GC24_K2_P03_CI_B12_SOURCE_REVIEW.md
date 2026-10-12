# C137 / GC24 / K2-P03 — B12 dormant CI source-preparation review

**Review: 2026-10-12. STATE: SOURCE PREPARED; REAL-MARIADB B12 NOT EXECUTED AND NOT CERTIFIED.**

## Provenance and historical protections

Repository: abeycity4u/Renee-Farms-V2.2.50-Batch3B1. B12 branch: c137-gc24-k2p03-ci-mariadb-b12, branched from the certified B11 evidence HEAD 7c7dd69dffc29cf46316312e26e50d2f4d887427. Existing B11 Actions run 38227697928 was one successful attempt; B01-B11 cumulative recorded certification remains 1,195 assertions and has NOT been replayed.

The authoritative B12 requirement is two concurrent corrections targeting one cycle/date with consistent lock order, one authoritative result or safe conflict, and **NO SILENT OVERWRITE**.

## Four scoped source paths

1. ADDED docs/C137_GC24_K2_P03_B12_SOURCE_ARCHITECTURE.md (blob 0dfd77f45a052f98e1c6e023dc536c4bfe530652): locking review and explicit unproven stale-editor hypothesis.
2. MODIFIED tests/integration/k2p03_ci_isolation.php (blob 6f783f07bc46811bb53a1b3c9b40262341ed771f): distinct B12 branch, disposable renee_k2p03_ci_b12 schema, fake CI password, original strict GitHub-hosted/loopback/driver guards and prior mappings preserved.
3. ADDED tests/integration/k2p03_b12_real_mariadb.php (blob 67e9c98c22d48daee9d9c0db266c0cc89613439a): two real independent-process PDO actors, held first transaction, bounded competing updater, scoped reconciliation and fail-closed conflict classification.
4. ADDED .github/workflows/k2p03-mariadb-b12.yml (blob 7b4c810e40c963464f9c3a0e4f387ea43270341d): dormant exact-approval-file-only GitHub Actions workflow with pinned schema/route/services/test/helper, empty synthetic database guard, MariaDB 10.11 and PHP 8.2.

NO production PHP, library service, schema, migration, cPanel, AWS, staging, deployment or default branch changed.

## Proposed B12 runtime assertions — NOT yet runtime proof

The fixture seeds a private synthetic farm and Layer cycle, three dated Daily Records and original canonical mortality projections, a later physical addition, feed stock receipt and source-owned daily feed usage, an automatic pooled egg-sale allocation, plus a separate tenant farm to prove scope.

Two separate PHP worker processes establish separate MySQL PDO connections and both read the original target data before A starts writing. Actor A performs the route-equivalent affected-column target UPDATE and holds its uncommitted row lock. Actor B then attempts another update of exactly that farm/cycle/date from the same stale read while A still holds the lock. Controlled barriers and a timed competing UPDATE establish overlap. Both use the ORIGINAL shared continuity -> feed -> mortality -> egg-sale allocation writers and caller-owned transactions.

The test reproduces the route's relevant target update predicate and absence of an expected prior value; it does not introduce an artificial optimistic lock just to get a PASS. The fixture is a **scoped route-semantic integration**, NOT authenticated HTTP/browser E2E.

The controller requires distinct MariaDB connection IDs; matching observed isolation levels; bounded demonstrable contention; completed worker processes; exactly ONE committed correction plus ONE explicitly recognized optimistic conflict; and exact other-tenant durable snapshots. Arbitrary errors, deadlocks, lock timeouts or unexplained outcomes FAIL. Even when the double-commit hypothesis occurs, the fixture separately reconciles final active mortality source, source-owned USED feed, stock balance, later canonical opening records, and pooled sale authority. **Ledger consistency does not excuse a stale silent overwrite.** If both stale editors commit, the fixture explicitly FAILS with TWO_STALE_COMMITS.

All worker transactions are closed and subprocesses cleaned before a PASS marker; cleanup/worker failure cannot count as a valid PASS. The worker synchronization directory is private, temporary, and removed by the controller. GitHub job resource/service and network cleanup still require final runtime log verification after separate authorization.

The actual Layer route's target UPDATE is not expected-version guarded, while the locked continuity comparison safeguards later rows. Whether the target silently loses a concurrent user change is an **unverified risk hypothesis**; no production bug has been claimed certified and no production patch is implied or authorized.

## Dormant workflow and one-time approval contract

The B12 workflow has ONLY a push trigger on c137-gc24-k2p03-ci-mariadb-b12 restricted to the NEW file docs/C137_GC24_K2_P03_B12_EXECUTION_APPROVAL.txt. No workflow_dispatch, pull-request or default-branch test trigger exists. Source pushes or this report must not trigger the B12 job.

A future approval requires a separate and explicit user decision naming the exact final B12 reviewed HEAD, fresh disposable DB renee_k2p03_ci_b12, one GitHub-hosted synthetic MariaDB run, no retry, and protected environment exclusions. Only AFTER approval, add a commit modifying ONE newly added approval file with exact subject: ci-b12(run): authorize isolated B12. The file must have exactly TWO lines:
  CI_B12_EXECUTION_APPROVED=YES
  CI_B12_APPROVED_PARENT=<EXACT_USER_APPROVED_REVIEW_HEAD>

The workflow validates the new-only one-file diff, that its parent equals the approved HEAD in that file, attempt one, exact source/test/helper Git blobs, GitHub-hosted loopback environment, MariaDB identity and EMPTY disposable database before schema import. The human operator must separately confirm that the file's stated parent is the HEAD explicitly authorized by the user; the workflow alone cannot establish user intent. A failure consumes approval; NEVER auto-retry or reuse B11's approval.

## Evidence limits and hard protections

This is a GitHub SOURCE review, NOT a PHP lint/test result. No PHP, MariaDB, SQL, GitHub Actions integration, schema import, fixture creation, network check or capacity test was executed in preparing this branch; no runtime assertion count exists. Any potential PHP syntax/runtime errors remain unverified until a separately authorized disposable CI run.

All fifteen D12 import gates remain BLOCKED; 150 VU HOLD, 200 VU PROHIBITED, Stage D NOT AUTHORIZED. Never use protected AWS Nodes A/B/C, cPanel, live/staging endpoints, protected renee_c137_fixture or renee_c137_gc24_restore_z25, backups, tenant credentials, or email/payment integrations for B12.

Next: verify final B12 remote HEAD, exact five changed paths including this docs report, B11 parent and frozen application blobs, dormant workflow pins, no approval file and zero B12 runs. Then obtain exact-HEAD one-run execution authority, not a generic "proceed".
