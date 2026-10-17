# C137 / B12 — MAC-12B10 trust-anchor threat model and negative-test contract

**Date:** 2026-10-17. **Scope:** source/design only. **Repository:** `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`. **Branch:** `c137-gc24-k2p03-b12-mac12b1-disabled-rc01`. **Source reviewed before this document:** `4521efdbbb87a6ea2450b0ba7b493fc867827106`.

**Classification:** NONEXECUTING / NONCERTIFYING. This file does not authorize a signer, a signer ceremony, signature issuance, approval files, trust-anchor pinning, PHP/Python fuse changes, Docker or database operations, fixtures, MAC-12C or B12 cutover. It describes proposed future security requirements and test cases, **not tests that have been implemented or passed**. All unassigned actors, key custody and independent registry decisions remain **PENDING HUMAN APPROVAL**.

## 1. Verified implemented baseline

- Supervisor `tools/b12_mac12b3_rc01_supervisor_disabled.py` blob `9628679e80836cfc4c148f17790638c70c5e2cbf` uses `EXECUTION_ENABLED = False`, `TRUSTED_APPROVER_PUBLIC_KEY_SHA256 = None`, RSA-SHA256 signature verification of bounded fixed-path snapshots, a *single* exact-byte public-PEM SHA-256 source pin, exact schema/branch/commit/blob/image/scope validation, two additional expiration checks, and a local exclusive `claimed_<run_id>` file with file+directory fsync. It has no independently anchored revocation status, time attestation or external anti-replay authority.
- PHP `tests/integration/k2p03_b12_mac_rc01_preliminary_disabled.php` blob `2e288c611e0b43217eb1b8e15234ee823b65f940`: literal `B12_RC01_EXECUTION_ENABLED = false`.
- `tests/test_b12_mac12b5_signed_approval_offline.py` blob `4941f0f74bfd83190b159ad176eb38a293ccac20`: negative payload cases, wrong signer, tampered RSA signature, replay, durability, late expiry. `tests/test_b12_mac12b3_host_supervisor_offline.py` blob `8e56ea684ba898abf222bde486dbb2f4cb7efbaa`: mount and private snapshot safety; all test runs remain offline.
- User-returned MAC-12B8D: **PASS_OFFLINE_ONLY 34/34, zero skips**, RSA ephemeral test and exit-90 hard disable; MAC-12B8E removed both old source-only evidence folders. This does not verify *any* real signer, independent approval, identity, clock or runtime race.
- The hard boundary remains `REPAIRED_RC01_RC14_CERTIFIED_PASSES=0`; `MIGRATION_092=NOT_AUTHORIZED`; `B12_CUTOVER=HARD_OFF`. Preserve the five original pinned PHP/schema blobs, protected branch and isolated lab.

## 2. Security properties and out-of-scope adversaries

**Assets:** isolated MariaDB fixture integrity, operator's approval decision, signer private key, trusted public PEM fingerprint, unsigned/signed manifest bytes, local claim log, source snapshots, clean Git checkout, PHP host/DB barrier, and immutable evidence chain.

**Trust boundaries:** [human authorizer] -> [signer/key custodian] -> [approval artifacts] -> [Mac verifier] -> [isolated synthetic runner] -> [local claim/auditor]. A valid RSA signature proves the artifact was signed by the corresponding private key, **not** that a human made a specific approval, that a key is currently uncompromised, or that the Mac is uncompromised.

**Attacker/failure models to test independently:**
- A1: malformed artifact or unintended scope; no control of pinned signer.
- A2: attacker obtains signing material or tricks the custodian (valid signature on unapproved work).
- A3: same-UID Mac process can delete/replace local claim, source snapshot, approval file or manipulate clock.
- A4: lost/stale/swapped key after a revoked-key decision; rollback to older Git source or prior public PEM.
- A5: concurrently invoked verification processes or crash/power loss between claim creation and worker launch.
- A6: corrupt/late/forged observation, root-permission mismatch, unexpected Docker/db network or cleanup residue.
- A7: a malicious privileged host administrator controls Docker/Mac or the independent register. **Current source does not defend against A7**; do not certify a cryptographic guarantee against a fully compromised host.

**Desired invariants (proposals pending adoption):** no production contact; exactly the reviewed isolated synthetic RC01 scope; no execution until *separate* human authorization + eligible signer + source fuses; no retry after a consumed run ID; deny unknown/revoked signer; deny stale/altered/source-mismatched grants; quarantine uncertain residue; audited evidence retained outside the runner account.

## 3. Candidate independent sign-off workflow — NOT enacted

**Recommended logical role split** (actual people and custody PENDING):
1. Project scope authorizer: records explicit approval of one specific scope, pinned source commit/blobs, disposable DB identity, maximum one run, controlled timeframe, expected mutations/teardown, acceptable residual risk, and stop-on-failure.
2. Independent key custodian: independently checks the signed-off scope against the exact JSON before issuing a *single* detached signature. Does not provide private key or passphrase to Mac/CI/chat/GitHub; no generic signing of arbitrary content.
3. Isolated Mac operator: checks the trusted **public** PEM exact-byte digest, signed manifest, source/DB/container identities, freshness, and claim state; executes only if all independent gates have *later* been activated by separate authorization.
4. Independent audit reviewer: retains approval decision ID, UTC audit time, SHA-256 of exact manifest/signature/public PEM, Git commit, run ID, source test evidence, consumed-claim outcome and sanitized results outside the runner's writable account.

**Candidate decision record fields (not a new manifest schema):** `decision_id`, approver identity/role, signer custodian identity/role, evidence of separation or approved exception, scope, exact repository/branch/commit, supervisor/harness blob IDs, test image/db identifiers, proposed run ID, not-before/expiry, approved SQL fixture list and intended cleanup, no-prod assurance, emergency revoke contact, decision outcome, independent audit location, and record integrity proof. Do not store passwords or key material.

**Rules:** a signature without a separately recorded human authorization is insufficient; approval cannot authorize cutover, migration, production, certification or repeated runs. No signer or auditor is assumed to exist just because this document defines the roles. If one individual combines roles, require a separately reviewed compensating control rather than silently asserting independence.

## 4. Candidate emergency revocation and recovery — NOT implemented

**Immediate fail-closed policy under present source:** retain both fuses OFF, stop issuing approvals, quarantine but never delete suspicious manifests/signatures/claims, freeze current evidence, revoke **operational authorization** with an out-of-band decision record, investigate any used `run_id`, and require a newly reviewed source trust-anchor patch before future use.

**Trust-anchor semantics:** current implementation has one literal exact-byte PEM SHA-256 pin and no live revocation check, key-ID field, revocation epoch or trust service. A still-valid old signature remains cryptographically verifiable if that same pinned key is later re-enabled. **Do not claim revocation is technically enforced by a human notice alone.** A future revoked/rotated state needs an independent verifiable denial gate or a separately approved replacement/remove-key commit and proof no older activated source is used.

**Preferred no-network candidate for design review:** an independent, tamper-resistant authorization/revocation ledger under different custody, with a fail-closed pre-launch attestation binding ledger decision ID, signer fingerprint, exact manifest digest, run ID and reviewed Git HEAD. Its update/availability/freshness and race semantics MUST be specified before it can become a code requirement. Simply adding a locally writable “revoked” flag under `.b12-approvals/` is insufficient against A3. No networked revocation service or new dependency is authorized here.

**Rotation candidate:** new key ceremony only after separate approval; verify and review one replacement public PEM digest under a source-only commit while execution fuses remain OFF; deny all old-key signatures in negative tests; document rollback protections and compromised-key response. Never accept simultaneous old/new signers by default. No reuse of ephemeral unit-test RSA keys.

**Clock failure policy:** current validation trusts Mac UTC, including <=15 minute lifetime. Define trusted clock evidence and a maximum skew **before** any later executable run. Clock rollback, fast-forward and expiration between preflight and launch must fail closed. Do not mutate host time as part of an offline test.

## 5. Proposed deterministic *future* negative-test matrix (not executed)

All cases must use mock clocks/files/processes and disposable **ephemeral test-only** keys as needed, with **no Docker, real database, approved signer or worker**. Reject cases must demonstrate the verifier does **not** proceed to the next privileged boundary; do not assert only generic process exit codes.

| ID | Scenario | Required negative property | Status at B10 |
| --- | --- | --- | --- |
| NT01 | Unset trusted signer with well-formed signed-looking files | Reject **before** approval directory/Docker access | Already represented in B8 tests; no new run |
| NT02 | Same key, alternate PEM whitespace/encoding | Exact-PEM pin rejects mismatched bytes | Proposed new test |
| NT03 | Valid-looking manifest under unknown RSA signer | Reject signer before signature acceptance | Partially covered by wrong fingerprint test; extend |
| NT04 | Modified manifest after signing / swapped signature | Reject cryptographic verification, no claim or worker | Tampered-manifest test already exists; signature-swap case new |
| NT05 | Valid signature, missing independent human approval | Block *before claim* in proposed future sign-off gate | **Design gap**; current verifier lacks human-decision check |
| NT06 | Human approval explicitly revoked after signing | Reject even if signature remains mathematically valid | **Design gap**; no revocation gate |
| NT07 | Old key used after reviewed key rotation | Deny old signature and any rollback-to-old-source | **Design gap**; needs rollback policy |
| NT08 | Approval data mismatches repo/branch/commit/blobs/image/DB | Reject before any claim | Existing source preflights/partial tests; expand matrix |
| NT09 | Wrong contract/scope, extra or duplicate keys, boolean/float maximum_runs | Reject deterministically before claim | Covered at payload/parser level |
| NT10 | Future/expired/grant wider than 15 minutes | Reject before claim | Covered at payload level |
| NT11 | Clock reaches expiry during source/DB preflight | Reject before claim | Existing mocked expiry test |
| NT12 | Clock reaches expiry after claim before worker | Preserve spent claim; reject before worker | Existing mocked expiry test |
| NT13 | Clock rollback or skew exceeds reviewed tolerance | Deny rather than re-enable stale approval | **Design gap**; no trusted-time mechanism |
| NT14 | Two contenders use identical run ID | Exactly one exclusive claim; loser never executes | Local sequential replay tested; simultaneous contention test new |
| NT15 | Claim-dir fsync fails / process dies after claim | Preserve claim, stop and never retry automatically | Partial directory-fsync and persistence tests; crash test new |
| NT16 | Same-UID attacker deletes/replaces old claim | Do not claim robust replay resistance from local storage | **Design gap**; independent audit/monotonic mechanism needed |
| NT17 | Signer key exfiltration with syntactically valid signature | Independent decision/revocation gate denies unauthorized scope | **Design gap**; signatures alone cannot solve |
| NT18 | Source snapshot symlink/extra file/hash/permissions changed | Reject before Docker mount | Partial tamper/private directory tests exist; extend |
| NT19 | Docker context/image/db identity/DB nonempty changes | Reject before synthetic fixture/worker | Existing source guards; no B10 runtime test |
| NT20 | Host observer proof forged/absent; wait direction wrong | Reject as non-proof; no certification | Existing B3 offline tests; real wait remains unverified |
| NT21 | Cleanup incomplete or unowned container discovered | Quarantine; never stop unowned resources or auto-retry | Existing supervisor guards; no B10 runtime test |
| NT22 | Invalid public key, failed OpenSSL, unexpected verifier stdout | Reject without claim; no skip allowed | Real ephemeral RSA PASS in B8; additional negative cases proposed |
| NT23 | Audit ledger unavailable/stale/inconsistent | Fail closed before worker (not merely log warning) | **Design gap**; no ledger implementation |
| NT24 | Source rollback reintroduces enabled fuses or prior signer | Independent source/head allowlist blocks activation | **Design gap**; trusted external allowlist not implemented |

**Required evidence for a future test implementation:** exact source Git blob, each test ID mapped to one deterministic case, no skipped tests, zero network/DB/Docker calls, negative denial at correct boundary, enforced never-claim/never-worker assertions, fresh no-skip ephemeral RSA positive, hard-disabled supervisor exit 90, and remote semantic diff. Do not reuse B8 34/34 as a pass for newly written tests. Implement only under separate source-only change authorization.

## 6. Staged acceptance gates and unresolved decisions

| Gate | Minimum future evidence | B10 disposition |
| --- | --- | --- |
| G1 Source and scope | Frozen branch/commit and protected PHP/CAS pins; no execution change | REVIEWED |
| G2 Authorship | Named human project authorizer, independent key custodian and executor | PENDING USER DECISION |
| G3 Custody | Approved offline/hardware-backed private-key environment, protection, recovery/rotation policy | PENDING USER DECISION |
| G4 Revocation | Explicit key compromise/notice authority; unambiguous enforceable deny path, stale-ledger behavior | DESIGN NOT IMPLEMENTED |
| G5 Sign-off evidence | Separate approval record, immutable/out-of-band storage, signed manifest binding | DESIGN NOT IMPLEMENTED |
| G6 Replay and time | Same-UID/local-claim attack assumptions, reliable clock/skew and audit fail-closed plan | DESIGN NOT IMPLEMENTED |
| G7 Offline tests | Fresh test source and full deterministic NT coverage with no skips, no workers | NOT IMPLEMENTED |
| G8 Trust setup | Independently reviewed public PEM exact-byte digest and new source pin, both fuses OFF | NOT AUTHORIZED |
| G9 One-use runtime | New human authorization, separate signed manifest, code review and explicit single-run permission | NOT AUTHORIZED |
| G10 Production | RC01–RC14 certification, migration 092 and cutover | HARD OFF / NOT AUTHORIZED |

**Potential minimum scope adjustment:** if no independent auditor or hardware custody is available, this must be a documented **unmet** gate, not silently bypassed by a signer stored on the same Mac. A simpler local signer may be reviewed later only as an explicit, weaker threat model; it must not be called independent or protect against A3.

## 7. B10 disposition and next exact step

**MAC-12B10 = SOURCE-ONLY THREAT MODEL & NEGATIVE-TEST CONTRACT REVIEW COMPLETE**, NOT a runnable security suite, operational revocation or approval to generate keys. Exactly one new documentation artifact is expected on this branch. No test source, signer literal, PHP fuse, database file, production branch or runtime worker should be touched.

**Recommended MAC-12B11:** independent source-only review/implementation plan for deterministic *offline* negative tests and the minimum enforceable human sign-off/revocation gate. Treat NT05–NT07, NT13, NT16–NT17, NT23–NT24 as **known design gaps**, not passing tests. Require explicit role/custody decisions before a later key ceremony, and separate authorization for any code changes or execution. Stop if source HEAD, fuses, pin or scope drifts.
