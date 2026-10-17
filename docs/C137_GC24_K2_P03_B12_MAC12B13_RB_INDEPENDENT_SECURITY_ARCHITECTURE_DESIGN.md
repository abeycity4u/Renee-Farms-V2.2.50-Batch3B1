# C137 / B12 — MAC-12B13 independent R-B security architecture (design only)

**Prepared:** 2026-10-17. **Repo:** `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`. **Branch:** `c137-gc24-k2p03-b12-mac12b1-disabled-rc01`. **Reviewed parent HEAD:** `5f3dbe5035fbcc6ad827effe258cf9afdfddcfaa`.

**Classification:** DESIGN ONLY, NO IMPLEMENTATION. The project owner previously authorized a design review, **not selection of R-B as the accepted operating threat model**. This document does not authorize an independent service, network egress, signer setup, key generation, approval files, credential movement, Docker/SQL, MAC-12C, or either execution fuse. R-A and R-B remain alternatives pending a separate decision.

## 1. Verified source baseline and invariant

- Supervisor `tools/b12_mac12b3_rc01_supervisor_disabled.py` has verified Git blob `9628679e80836cfc4c148f17790638c70c5e2cbf`.
- Source has `EXECUTION_ENABLED = False` and `TRUSTED_APPROVER_PUBLIC_KEY_SHA256 = None`. PHP harness `tests/integration/k2p03_b12_mac_rc01_preliminary_disabled.php` Git blob `2e288c611e0b43217eb1b8e15234ee823b65f940` has `B12_RC01_EXECUTION_ENABLED = false`.
- Existing `B12-MAC12C-RC01-ONE-USE-V1` contract accepts exactly 16 fields, signed RSA-SHA256, ≤15-minute issued/expires UTC window, fixed synthetic scope/database/branch/commit/blobs/image, one-use 32-hex `run_id` and persistent `O_EXCL` local claim; no `decision_id`, revocation, independent time attestation or remote one-use ledger exists in the current supervisor.
- MAC-12B12-OFF local evidence under parent HEAD: nine pinned source/test files PASS; 44/44 offline tests, 0 failures/skips; RSA positive/swapped-signature/concurrent local claim PASS; Python hard-off exit 90; owned staging cleaned. **No new tests have been executed for this document.**
- Official repaired RC01–RC14 certification: **0/14**. Migration 092: **NOT AUTHORIZED**. B12 cutover: **HARD OFF**. No protected production/staging/GA source or real customer database touched.

## 2. Security goals, actors and assumptions

**Goal B1, independent authorization:** a human's approval to operate a *single named synthetic RC01* must be bound to the exact signed manifest bytes and independently recorded outside the test Mac account; manifest signer alone cannot impersonate the project approver.

**Goal B2, revocation:** deny use of a compromised or administratively revoked signer for every authorization that has not yet crossed a clearly defined, independently enforced commit/launch boundary; never claim a static signed snapshot gives ongoing real-time revocation.

**Goal B3, anti-replay:** two operators, two processes or a restored Mac cannot both consume the same approved `run_id`. Atomicity/monotonicity must be enforced by an authority not writable by the same-UID Mac test account.

**Goal B4, source/scope/credential control:** neither tampered source nor a different disposable database/image/fixture may use an authorization; operator access to test DB credentials is a distinct bypass risk that a manifest signature cannot solve.

**Goal B5, availability and audit:** unavailable, stale, inconsistent, untrusted or rolled-back authorization information denies launch; preserve an independently retained append-only decision/claim record, including after failure.

**Actor separation:** (1) project human authorizer; (2) dedicated cryptographic manifest signer/key custodian; (3) independent decision/claim authority; (4) runner operator; (5) audit reviewer. All are **logical roles only**. Identity, staffing, secure custody, account separation, independent audit and acceptable compromised-host assumptions are **PENDING**. If roles collapse into the same administrative domain, R-B's claimed independence degrades.

**Non-goal / major limitation:** privileged host control or a malicious process with direct Docker/DB credential access may bypass a Python policy gate by writing SQL or launching another container. The current Mac `.b12-secrets/` and Docker ownership must be considered reachable to the Mac operating principal. R-B cannot honestly claim strong execution enforcement against such an attacker until the credential/launch authority is separated or physically isolated. An independent approval registry alone does *not* provide that enforcement.

## 3. Feasibility alternatives — NONE DEPLOYED

| Option | Mechanism | Revocation/freshness | Compromised same-UID replay | Decision |
| --- | --- | --- | --- | --- |
| R-A restricted lab | Offline V1 signature + current local one-use marker + separately recorded human approval | Human stop + disabled fuses; not continuously enforced | Can delete marker, tamper host clock/runner; threat explicitly not defended | Existing limited model, NOT selected |
| R-B1 independent *online* authority | Host requests a fresh, signed/attested, nonce-bound allow/deny and atomic reservation from separate trust domain just before credential/launch | Can consult latest revocation state subject to final decision boundary; outage fails closed | Authority ledger rejects reused ID, but direct Mac credentials/Docker remain an independent bypass until brokered | **Preferred R-B design candidate only** |
| R-B2 independent *offline interactive* witness | Separate hardware/physical operator maintains a monotonic one-use ledger and authorizes each launch interactively; host cannot self-issue status | Can deny at the witness step, but cannot prove a later revocation not communicated to it | One-use only if witness and execution/credential broker cannot be bypassed | Candidate if networking forbidden; requires real hardware/actor design |
| Static signed allowlist or local `revoked=false` | Mac copies a signed status file | Only as fresh as issuance; vulnerable to rollback/clock games | Not independent when same Mac can replay/delete files | **REJECT as R-B** |

**Important isolation conflict:** B12 MAC-12B6 deliberately uses a private Docker network/no-public ports; the offline tests execute no Docker or SQL. R-B1's future control-plane connectivity would be **host-side only** to an explicitly approved independent endpoint, never egress from the PHP container or disposable database. It is *not presently authorized* even from the Mac. Network-fail/timeout policy is hard denial; a mocked offline test must not silently create an external connection. Before implementation, a separately approved network threat model, endpoint identity and egress policy are required.

## 4. Recommended logical R-B1 protocol — SPECIFICATION PROPOSAL ONLY

**Unmodified V1 manifest.** Continue signing/verifying exact V1 JSON bytes with the current fixed contract. A *separate*, strictly versioned authorization decision envelope is proposed; it must never be smuggled into V1 fields or accepted through environment variables or CLI flags.

**Candidate immutable request binding (no secrets):**
`contract_version`, `decision_id`, `run_id`, `sha256(raw_manifest_bytes)`, `sha256(detached_signature_bytes)`, `sha256(exact_signer_public_pem_bytes)`, signed `source_commit`, actual supervisor Git blob, harness blob, image digest, isolated database identifier, approved synthetic fixture-plan digest, operator identity, authority identity, trusted state version, and requested operation `PREPARE`/`RESERVE`/`LAUNCH`. All identifiers are exact and canonical. No credential, PEM private key, customer data, production endpoint or approval bypass switch may appear.

**Proposed future independent state machine:**
- `PROPOSED` -> `APPROVED` only after recorded human sign-off with independent verification of the request binding and bounded expiry.
- `APPROVED` -> `RESERVED` is one atomic compare-and-swap in the independent ledger keyed by `run_id` *and* manifest digest. Competing or replayed requests fail. Cancelled/expired/revoked requests cannot reserve.
- `RESERVED` -> `SPENT` is irreversible **before** any potentially mutating operation or credential release; record unique attempt and result-unknown status. A crash or timeout never resets `run_id` to `APPROVED`.
- `SPENT` -> `FINISHED` or `QUARANTINED` logs sanitized outcome; not another authorization. `REVOKED`/`CANCELLED` deny unspent requests and block further launches according to a policy explicitly settled before implementation.
- A manifest already `SPENT` can *never* be relaunched under the same identifier even if the local `claimed_*` marker was removed. A distinct new run requires a distinct fresh explicit authorization.

**Freshness and replay challenge:** authorization response must bind a server-generated challenge or nonce, exact request digest, authenticated caller, independently signed authority identity, monotonically increasing authority state, and expiry. The verifier must check signature, pin, challenge, status, digest and timing against a reviewed trust policy. A stored `APPROVED` blob alone cannot be replayed to reserve. If a full-host compromise can arbitrarily patch the verifier, only brokered access credentials or an external launch gate can prevent bypass.

**Revocation semantics must be honest:** revocation is guaranteed to deny subsequent *unspent* reservations; whether a just-reserved launch can be revoked before `SPENT` needs a reviewed atomic transition and enforcement boundary. A check followed by a local worker start has an unavoidable time-of-check/time-of-use gap. Revocation cannot retroactively undo a mutation already performed or automatically stop a running SQL transaction. Avoid an unqualified “instant kill” promise.

**Trusted time and signed responses:** server-side trustworthy clock and nonce state must govern reservation/expiry; client wall-clock skew or rollback fails the policy, not silently extends V1 validity. Define tolerance, authority response lifetime, monotonic source, epoch persistence and startup recovery. The V1 <=15-minute limit is the current source rule; a *shorter* authority response TTL is recommended but exact seconds are **PENDING** review.

**Fail-closed results:** missing approval, absent role attestation, revoked signer, unknown fingerprint, stale/rollback state, invalid response signature, wrong fixture/source/scope, lost audit durability, duplicated run ID, timeout, unavailable endpoint or uncertain post-claim state -> NO LAUNCH; append/quarantine independent evidence if possible. Do not automatically retry a `RESERVED`/`SPENT` request after timeouts.

## 5. Mandatory execution/credential boundary for strong R-B assurance

As currently designed, the test Mac owns Docker and local `test_password`/database credentials. A hostile same-UID operator can potentially bypass the supervisor even when an independent authority says DENY. To achieve **enforced** R-B rather than audit-only R-B, select and separately approve **one** security boundary before activation:
- An independent launch/credential broker running under an inaccessible OS principal or physically separate host; it releases a *single constrained synthetic-only database capability* only upon an authoritative `SPENT` decision and executes the exact frozen launcher, not arbitrary user commands. Its Docker/socket, volume and SQL credential access must not be available to the Mac operator.
- Alternatively physically separate execution inside a controlled runner whose administrative identity and network are outside the local user's trust domain; the Mac remains a read-only request client.
- If neither is feasible, document as **R-B coordination/audit only**, not full same-UID execution enforcement. R-A may be more proportionate for disposable lab experiments, but only after an explicit decision accepting the weaker threat model.

Any broker is a **new privileged component**. The current B12 environment has no such broker; do not create it as part of a source-only design milestone.

## 6. Proof obligations: deterministic offline policy tests, not yet implemented

Future pure verification function should accept immutable in-memory manifest bytes, parsed validated V1, an independently authenticated human decision object, a separately authenticated authority-state response, signer identity, immutable source/fixture identity and a controllable time view. It must return explicit ALLOW/DENY codes with **zero I/O**. A future adapter obtains signatures/status and audit evidence and must be tested separately; a pure test cannot certify a networked, hardware-backed, or privileged broker.

| ID | Simulated case | Required result | Currently implemented? |
| --- | --- | --- | --- |
| RB01 | Absent human decision, but valid V1 RSA signature | DENY before reserve/claim/credential | NO |
| RB02 | Human decision signed by same compromised V1 key, without independent trust | DENY untrusted human authority | NO |
| RB03 | Valid decision for different exact manifest bytes or run ID | DENY digest/scope mismatch | NO |
| RB04 | Attestation of signer changed after key rotation | DENY old fingerprint | NO |
| RB05 | Signed approval before signer revocation; fresh authority status now revoked | DENY regardless of valid old signature | NO |
| RB06 | Old signed “active” status replayed after later revocation epoch | DENY stale/rollback status | NO |
| RB07 | Duplicate parallel RESERVE on same run ID | Exactly one atomic reservation in mock independent ledger | NO |
| RB08 | Local `claimed_*` deleted but independent ledger says SPENT | DENY re-launch | NO |
| RB09 | Authority times out, corrupts signature, returns no response or stale response | DENY, no fallback to R-A | NO |
| RB10 | Client clock moved backward/forward | DENY until externally trusted time acceptable | NO |
| RB11 | Approved HEAD/source digest or fixture-plan digest changes | DENY exact binding mismatch | NO |
| RB12 | Crash after authoritative SPENT and before worker | Preserve SPENT; quarantine; no auto-retry | NO |
| RB13 | Revocation races with reserve/spend | Deterministic atomic ordering; no grant outside defined boundary | NO |
| RB14 | Decision available but credential broker absent/denies | DENY; no independent direct credential path | NO |
| RB15 | Authority key compromised or rotated; old authority signature replayed | DENY old trust anchor/epoch | NO |
| RB16 | Lost append-only audit durability | DENY before privileged operation | NO |
| RB17 | Operator tries alternate Docker/SQL command with same-UID local credentials | **Architecture gap**; require actual credential/launcher isolation, not mock-only claim | NO |

Existing 44 V1 offline tests from MAC-12B12 remain independent baseline evidence, and their successful result **cannot be counted** toward RB01–RB17. Do not implement “tests” that assume an unimplemented service grants or enforces capabilities. No skipped or expected-failure cases should be reported as PASS.

## 7. Decision and capacity checkpoint

Before code or deployment, obtain an explicit decision about:
1. **Is R-B actually required** for an isolated, disposable *noncertifying* RC01, or may the user explicitly accept bounded R-A? Keep real production release/security gates distinct from this preliminary exercise.
2. Who are the **independent authorizer, signer custodian, authority operator, broker/runner operator and auditor**, and what administrative/credential separation is real?
3. Can an **independent host-side approval channel** be permitted, or is air-gap/no external network immutable? Which actor will operate and back up the monotonic register?
4. Is the threat objective merely **out-of-band audit and one-use sign-off**, or actually **prevent malicious same-UID execution**? Only the latter requires removing Docker/DB capability from the operator.
5. What is the maximum acceptable approval/revocation latency, clock skew, audit retention and failure/quarantine process? None are assumed here.
6. If a new server, HSM, custody domain or credential broker is needed, who owns its cost, patching, incident response and independent verification?

**Recommended decision policy:** do not make an unbounded security infrastructure project a hidden prerequisite of preliminary synthetic CAS diagnosis. Complete the R-B feasibility check and either select it explicitly with a concrete authority/broker and budget, or accept/reject R-A's narrow lab risk in writing. Neither choice authorizes B12 cutover, migration 092 or production traffic.

## 8. Stage verdict and next gate

**MAC-12B13 = R-B DESIGN & FEASIBILITY REQUIREMENTS DOCUMENTED_ONLY**, not R-B selected, not deployed, not trusted, not audited or security-certified. No new test, key, network endpoint, manifest, credential release, Docker, database or runtime worker has been created.

**Next proposed milestone:** `MAC-12B14` **read-only architecture feasibility and decision gate**: select R-A or R-B for the disposable synthetic test, record operator/custodian/audit and permitted host-network constraints; if R-B chosen, resolve exact authority/credential broker ownership and trust boundaries. Only after these decisions may a separate source-only pure policy implementation be authorized. No automatic transition to key ceremony, fuse activation or MAC-12C.

**Immutable safeguards:** `TRUSTED_APPROVER_PUBLIC_KEY_SHA256=None`; Python and PHP fuses OFF; `MIGRATION_092=NOT_AUTHORIZED`; `B12_CUTOVER=HARD_OFF`; `RC01–RC14=0/14`.
