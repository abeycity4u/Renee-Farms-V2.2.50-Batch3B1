# C137 / B12 — MAC-12B11 offline trust-control implementation plan

**Prepared:** 2026-10-17. **Scope:** design and review only. **Repository:** `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`. **Working branch:** `c137-gc24-k2p03-b12-mac12b1-disabled-rc01`. **Inspected source HEAD:** `02d15a9b0857d2a9ea451cfa960f54bf8d8a4ba6`. The only permitted MAC-12B11 repository change is this document.

**Hard boundary:** `EXECUTION_ENABLED=False`, PHP `B12_RC01_EXECUTION_ENABLED=false`, `TRUSTED_APPROVER_PUBLIC_KEY_SHA256=None`, `B12_CUTOVER=HARD_OFF`, `MIGRATION_092=NOT_AUTHORIZED`, repaired `RC01–RC14=0/14 certified passes`. Nothing here permits generating a real signer key, creating approval files, connecting to Docker/SQL, opening production backups, executing a synthetic RC01, or touching protected branches.

## 1. What is verified versus what is a proposal

Source at inspected HEAD:
- `tools/b12_mac12b3_rc01_supervisor_disabled.py` blob `9628679e80836cfc4c148f17790638c70c5e2cbf`. Host acceptance: signer PEM *exact-byte* SHA-256 source literal (currently None), RSA-SHA256 over manifest bytes, strict 16-key JSON approval scope, 15-minute maximum window, two rechecks during preflight, source and disposable Docker/DB identity, irreversible local `O_EXCL` claim with file+parent-dir fsync, and frozen read-only PHP source bind snapshots.
- `tests/test_b12_mac12b5_signed_approval_offline.py` blob `4941f0f74bfd83190b159ad176eb38a293ccac20` and `tests/test_b12_mac12b3_host_supervisor_offline.py` blob `8e56ea684ba898abf222bde486dbb2f4cb7efbaa`: 16 + 18 test cases.
- `tests/integration/k2p03_b12_mac_rc01_preliminary_disabled.php` blob `2e288c611e0b43217eb1b8e15234ee823b65f940`, still hard-disabled.
- User-returned MAC-12B8D offline evidence: exact eight source blobs, syntax 3/3, 34/34 tests, zero skips, ephemeral RSA signature success/tamper rejection and supervisor exit 90; MAC-12B8E cleanup PASS. Do not claim those tests cover newly proposed gates.

**Gaps:** no independent human sign-off required by supervisor; no separate revocation authority/freshness, no protected external replay register; same-UID attack is not prevented by local claims; Mac UTC clock is not authenticated. MAC-12B10 IDs NT05–07, NT13, NT16–17 and NT23–24 are **design gaps**, not passing security tests. NT01–04 etc. have partial prior coverage, not a blanket certification.

## 2. Precisely bounded proposed trust policy

The already committed `B12-MAC12C-RC01-ONE-USE-V1` signed JSON contract has exactly 16 fields, no `decision_id`, `key_id`, or `revocation_epoch`. **Do not silently add keys or broaden the V1 schema.** A separate authorization attestation would have to bind to `sha256(exact signed manifest bytes)`, exact reviewed Git HEAD, approved signer public-key fingerprint, `run_id`, scope, database, PHP image, authorization decision ID, and fixed short expiry. Its trusted verification key and custody must be independent of the test executor; if the same signing key is used as the manifest signer, the attestation does not provide independent human approval.

Suggested future *separate attestation* fields, for design review only:
`contract`, `decision_id`, `manifest_sha256`, `run_id`, `source_commit`, `signer_public_pem_sha256`, `scope`, `database`, `expires_utc`, `decision`, `approved_fixture_digest`, `approval_authority_id`, `revocation_state_version`. The signature and key pinning mechanism for this new format are **not yet chosen**, nor is a signer/human/clock/audit authority. Do **not** generate or parse such files in MAC-12B11.

Minimum deny rule: if either independently authenticated human authorization or enforceable current trust status is required by the approved threat model but cannot be verified, **deny before the irreversible one-use claim and before any fixture/worker**. A valid RSA approval-manifest signature is never alone an operational authorization.

Required order for a future reviewed verifier (not implementation):
1. Literal Python and PHP fuses remain hard OFF until their own separately approved release; this design cannot switch them.
2. Verify source HEAD/clean tree, trusted identities and exact source blobs; load fixed-path bounded signature, public PEM and manifest; reject file identity and signature/field violations.
3. Verify independent human approval attestation *over exact manifest bytes*, with a different approved verification trust root; check approval and revocation state, freshness, clock policy and decision ID; deny any missing/stale/inconsistent evidence.
4. Check disposable container/DB identities and schema emptiness; revalidate authorization and revocation immediately before the exclusive claim.
5. Claim the run ID once; after claim, any expiry, revocation, clock drift or source problem consumes the grant. Revalidate again before credential transport/worker. No retry or claim deletion.
6. Quarantine and independently audit uncertain states. A worker outcome or manual result must not be reported as certified RC01–RC14.

**Implementation warning:** adding independent attestations to a future runner changes its execution policy. Such code requires a new, reviewed commit and freshly executed negative tests. It must not be placed into the current source as inert controls that could later be mistaken for activated enforcement.

## 3. Honest revocation decision (cannot be solved by an offline static signature)

A purely offline, previously signed “not revoked” blob proves status only *at the blob's issuance time*. It cannot prove that a key was not revoked later. A same-UID attacker can replay an older blob, delete local claim files, and potentially move the host clock. Consequently:

**Candidate R-A — restricted non-adversarial local synthetic lab:** rely on an externally recorded explicit human approval and both hard fuses, with local one-use claims, manual stop on signer compromise, and no assertion that adversarial same-UID replay, live revocation or compromised-host clock attacks are defeated. This option is **weaker** and needs explicit user acceptance of the narrowed threat model before any activation. Local same-UID compromise, clock rollback and emergency revocation remain residual blockers against a malicious runner.

**Candidate R-B — independent enforcement authority (preferred when A2/A3/A4 attacks are in scope):** introduce a separate protected approval/revocation register with authenticated fresh responses or externally anchored monotonic state that is consulted just before claim and launch. Define its trust root, owner, transport and availability, replay-resistant challenge, signed status/version, pin/epoch, monotonic time, append-only consumed-ID record, failure semantics and response-to-manifest digest binding. No product/network/plugin is selected. Such an authority is **not available in existing source** and adding it is new architecture; it would require separate authorization and security review. Offline fixture tests must mock it; never connect to real services during negative tests.

**No silent downgrade:** never describe R-A as equivalent to R-B, never add a locally writable `revoked=false` flag and label it independent, and never claim tamper-proof exactly-once under a compromised Mac account.

## 4. Deterministic offline test contracts and file plan

**Proposed phase B11-T1 (separate code authorization required):**
- New `tests/test_b12_mac12b11_policy_negatives_offline.py` to test *existing* unmodified supervisor behavior via isolated mocks and ephemeral files; no Docker/DB/network, key files limited to auto-deleted test-only RSA temp directories. No real approvals, no signer configuration.
- Cover actual V1 parser and identity boundaries with fail-closed assertions; *do not* test future unimplemented authorization/revocation mechanisms as though they exist. The existing 34-test suite remains mandatory and must be rerun on any changed test/source HEAD.
- NT mappings for existing implementation: NT01, NT03–04, NT08–12, NT14–15, NT18–22. For cases requiring absent controls, produce an explicit **DESIGN_GAP** in the report, not a test result marked PASS, skip or expected failure.
- Test names and assertions must encode the boundary, e.g. `test_wrong_pem_bytes_denied_before_signature`, `test_signature_swap_denied_before_claim`, `test_duplicate_nested_json_key_denied`, `test_concurrent_same_run_id_exactly_one_local_claim`, `test_post_claim_failure_preserves_claim`, `test_symlink_and_extra_snapshot_denied_before_mount`, and `test_hardoff_exits_before_any_subprocess`. These are **proposals**, not tests already committed.
- Mock subprocess/Docker sentinel side effects to ensure no accidental execution; use a mocked clock, not host time modifications. Do not use `skipTest`, `xfail` or a test “pass” that merely notes the security gap.

**Proposed phase B11-T2 (separate architecture authorization required):**
- New pure, dependency-free policy module (candidate `lib`-equivalent under `tools/`, naming to be reviewed) with deterministic interface `verify_scope(manifest_bytes, manifest, decision, trust_state, observed_time, source_identity)`. It must return a *denial* with fixed reason codes on any missing/mismatch/revocation/expiry or freshness uncertainty; it must perform no I/O.
- Offline test data must use in-memory synthetic signed artifacts; generate test-only ephemeral RSA keys in private temporary directories only when verifying cryptographic integration. No production trusted public key or signer credential.
- Deny/permit matrix for the new gate: NT05 no human approval, NT06 revoked post-signature, NT07 old signer after rotation, NT13 clock rollback/skew, NT16 deleted local claim with external consumed marker, NT17 compromised signer without independent decision, NT23 stale/unavailable/invalid independent register and NT24 rolled-back executable source. **All remain NOT_IMPLEMENTED until new reviewed source exists**.
- Future I/O adapter (distinct phase) would obtain independently authenticated status and user decision. Pure policy tests cannot prove adapter correctness, revocation freshness or tamper-resistant audit; require separate integration evidence before activation.
- Preserve the 16-field V1 manifest *or* formally version a new contract with strict rejection of old schema; do not change runtime parsing in an unrelated test-only patch.

**Acceptance for any subsequent offline test commit:** pinned Git HEAD and every touched blob; offline Python syntax; full previous tests + new exact deterministic count, zero skip, zero failures; real ephemeral RSA positive and negative cases, hard-OFF supervisor exit 90; no Docker commands/SQL/network/worker, independent source diff showing no protected PHP/CAS/blob change; exact temporary source cleanup. If a test fails, preserve only its owned temporary directory, diagnose one error at a time and never auto-rerun as though permission was renewed.

## 5. Proposed implementation sequence and gate owners

| Gate | Proposed unit of work | Entry condition | Exit condition | Authorization |
| --- | --- | --- | --- | --- |
| B11 (this document) | Review threat model and future deterministic test/approval contracts | Source B10 verified | Design committed and diff reviewed | Current user “proceed”; **no code/runtime** |
| B12-OFF | Add standalone V1 negative tests only | Explicit source-only authorization | Fresh offline suite PASS, no skipped tests; known design gaps remain explicitly open | Separate source change review |
| B13-POLICY | Design/pure implementation of independent sign-off/revocation policy, if chosen | Decide R-A vs R-B and roles | Pure tests and denial contracts, fuses OFF | Separate architecture authorization |
| B14-ADAPTER | Review external decision/clock/ledger enforcement and storage threat model | Independent authority specified and trusted | No downgrade paths and testable fail-closed behavior | Separate adapter authorization |
| B15-SIGNER | Key ceremony and trusted public-key pin | Named custodian, approved storage/revocation, separate signed authorization | Public PEM exact-byte fingerprint verified and reviewed source-only pin; fuses still OFF | **Explicit key-ceremony approval** |
| B16-RUN | One-use preliminary RC01 activation and disposable fixture/race/cleanup | Exact actor approval, review all evidence, independent signer and fresh run ID | Independently attested synthetic pass/failure, quarantine and teardown evidence | **Explicit one-use execution approval** |
| GA-PATH | RC01–RC14 real full certification, migration 092, 150/200 VU resume, production | Prior gates and separate release controls | Independent formal certification; not inferred from synthetic preliminary run | Distinct approvals |

This is a *dependency plan*, not an instruction to execute milestones in sequence automatically. B12/B13/etc. here are proposed working names under MAC, **not** the protected B12 program step numbers or authority to reinterpret any existing B12 evidence.

## 6. Decisions that remain open (do not infer from “proceed”)

1. Whether the project owner accepts weaker R-A for a disposable synthetic test or requires R-B independent, tamper-resistant revocation/replay assurance.
2. Who is the signer/key custodian, separate human approval authority, runner and independent auditor; how separation is actually enforced.
3. Whether/how the local Mac can trust UTC and tolerate clock skew; whether an external monotonic counter/trust service is available.
4. The exact storage, authentic signer identity, freshness and rollback resistance of human approval/revocation evidence, including absent-service behavior.
5. Whether an external independent actor must co-sign every manifest or whether the signed decision record is a separate cryptographic capability.
6. Key algorithm/length/storage/recovery and emergency revocation policy, all pending a different authorization.

**Nothing is answered by this document alone.** Do not create a real RSA key, signer directories or manifest until these have been explicitly resolved and a separate approved ceremony is in scope.

## 7. Final expected disposition

`MAC-12B11=DESIGN_COMPLETE_ONLY`; `OFFLINE_NEGATIVE_TEST_IMPLEMENTATION=NOT_STARTED`; `HUMAN_SIGNOFF_ENFORCEMENT=NOT_IMPLEMENTED`; `LIVE_REVOCATION=NOT_IMPLEMENTED`; `TRUSTED_SIGNER=UNCONFIGURED`; `MAC12C=NOT_AUTHORIZED`; `MIGRATION_092=NOT_AUTHORIZED`; `B12_CUTOVER=HARD_OFF`; `REPAIRED_RC01_RC14_CERTIFIED_PASSES=0`.

**Recommended next action:** after MAC-12B11 commit/diff review, perform the smallest possible source-only work unit `MAC-12B12-OFF`: add tests for existing V1 negative cases, no policy changes, no keys/containers/DB. Do not label missing R-B features PASS; keep them as documented blockers. Human trust-model selection must precede source implementation of R-A or R-B.
