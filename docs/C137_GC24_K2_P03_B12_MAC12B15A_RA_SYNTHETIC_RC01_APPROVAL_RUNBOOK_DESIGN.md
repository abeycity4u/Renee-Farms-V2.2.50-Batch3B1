# C137 / B12 — MAC-12B15A R-A limited synthetic RC01 approval runbook

**Prepared:** 2026-10-17. **Repository:** `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`. **Branch:** `c137-gc24-k2p03-b12-mac12b1-disabled-rc01`. **Verified source HEAD immediately preceding this documentation-only commit:** `f138f7a388d972731d3efb990581466b4d82688c`.

**Stage disposition:** RUNBOOK DESIGN ONLY. The user explicitly accepted the recommendation to use **R-A — restricted/cooperative-operator security assumptions for ONE disposable, preliminary, noncertifying Layer edit/edit RC01 experiment only** and authorized preparing this runbook. That **does not** authorize any signer/key generation, manifest signing, trust-anchor pin, fixture, Docker/SQL contact, source or runtime fuse change, worker, Mac environment change, production operation or cutover.

**Fixed authority boundary:** `EXECUTION_ENABLED = False`, `TRUSTED_APPROVER_PUBLIC_KEY_SHA256 = None`, `B12_RC01_EXECUTION_ENABLED = false`, `MIGRATION_092 = NOT_AUTHORIZED`, `B12_CUTOVER = HARD_OFF`, and repaired `RC01–RC14 certified passes = 0/14`. R-A does not replace Renee AgriSuite's commercial SaaS security requirements, tenancy protections, formal GA testing, or any R-B control where a malicious operator is in scope.

## A. Source-grounded facts; do not extrapolate

1. **Host supervisor:** `tools/b12_mac12b3_rc01_supervisor_disabled.py`, verified Git blob `9628679e80836cfc4c148f17790638c70c5e2cbf`; literal Python fuse OFF; signer fingerprint None. Host Mac lab `~/renee-b12-rc-mac`. Supervisor verifies Git branch, exact signed source commit, clean worktree, approved supervisor blob and the five immutable pinned PHP/schema file blobs, then disposable Docker identity/schema. Source snapshot copies the five pinned files into owner-only private tree with read-only binds and tamper checks before PHP command creation.
2. **Synthetic database / test image:** fixed `renee_b12_disposable`, Compose project `renee_b12_rc_mac`, internal network `renee_b12_rc_mac_isolated`; pinned PHP image `sha256:7399b946d775888f85299b9e1597a9ad633cd164cfc816b16f1f77e2da839445`. The current source expects disposable schema fingerprint **69 base tables, 886 columns, 193 referential constraints, three `record_revision` columns** and no test rows or side-effect routines; do not infer the current running container remains in this state without future explicitly authorized preflight. No staging, production or historical live DB restore.
3. **Hard-disabled PHP harness:** `tests/integration/k2p03_b12_mac_rc01_preliminary_disabled.php`, verified Git blob `2e288c611e0b43217eb1b8e15234ee823b65f940`. Literal PHP fuse OFF. In an authorized future **single** run only, harness creates a disposable farm, one Layer production cycle, and one Layer daily record for `2026-09-21` (opening stock 30, initial mortality 2, age 25), then uses two independent workers on the *same* record/revision. Host observer must independently witness actual InnoDB B-waiting-on-A lock metadata. Expected outcome: A commits; B returns stale conflict; stored revision 2 and mortality 4; deletes only the exact inserted record/cycle/farm, verifies complete empty synthetic database/barrier cleanup. This description is NOT permission to execute fixture SQL.
4. **Signed V1 approval contract:** `B12-MAC12C-RC01-ONE-USE-V1`; `ISOLATED_SYNTHETIC_LAYER_EDIT_EDIT_ONLY`. Exact **15** JSON fields: `contract`, `scope`, `run_id`, `issued_utc`, `expires_utc`, `repository`, `branch`, `source_commit`, `supervisor_blob`, `harness_blob`, `database`, `php_image_id`, `maximum_runs`, `synthetic_only`, `production_contact`. Unrecognized/duplicate keys denied; one 32-lowercase-hex `run_id`; strictly integer `maximum_runs=1`, Boolean `synthetic_only=true`, `production_contact=false`; expiry at most 15 minutes, checked at initial validation, after preflight and before launch. Signature verified via SHA-256/RSA with exact-byte PEM public-key digest source pin, not an environment override.
5. **Current V1 file contract (future only):** fixed `~/renee-b12-rc-mac/.b12-approvals/`: `mac12c_one_use.json`, `mac12c_one_use.sig`, `trusted_approver_public.pem` owner-only 0600; parent/claims directories owner-only 0700; no symlinks. Existing `claim_one_use` uses atomic `O_EXCL`, fsync(file), fsync(parent dir); keeps `claimed_<run_id>` on failure or expired approval after claim; no retry/claim reset. The claims directory is *not* an external tamper-proof ledger.
6. **Prior certified *offline-only* evidence:** MAC-12B12-OFF user copy-back for Git HEAD `5f3dbe5035fbcc6ad827effe258cf9afdfddcfaa`: nine verified blobs; Python 4/4 syntax; 44/44 regression tests, zero failures/skips; ephemeral test-only RSA signature and swapped-signature checks, local concurrent one-use claim test, supervisor exit 90, exact temp cleanup. MAC-12B13 and MAC-12B14 were documentation-only commits. A document-only commit does not require rerunning those closed tests absent source drift; a future source/activation change will.

## B. R-A trust acceptance and its non-negotiable limits

**User decision:** `D1=R-A_LIMITED_SYNTHETIC_ONLY`, accepted for the sole preliminary disposable RC01 experiment. No assertion that a production system, its staff, the Mac, Docker or the signer can withstand a malicious same-UID operator. The Mac operator is assumed **cooperative and trusted**, with direct Docker/DB capability; if that assumption cannot be maintained, STOP and reconsider R-B. The current single-key RSA validation plus local claims cannot prevent a malicious operator from deleting claims, invoking SQL directly, manipulating host clock or bypassing Python. A signed manifest alone is not independent human authorization or real-time revocation. Operational human sign-off is mandatory and must be separately recorded, but the current V1 supervisor **does not independently enforce a human decision attestation**.

**No silent relaxation:** both hard fuses and signer-none remain unchanged throughout this B15A step. Risk acceptance does not mean enabling them, broadening to more RC tests, allowing production data or running 150/200 VU load tests. No networked R-B authority, remote signer, extra DB user, shell credential or new source tool is installed.

## C. Decision ownership, evidence and approvals (all future actions separate)

**Roles remain UNASSIGNED:** project human approver, manifest signing-key custodian, Mac operator and independent evidence keeper. A sole-owner/same-person case may be documented, but do not call it independent: an external dated approval record adds traceability, not malicious-operator resistance. No name, second account, signing device, escrow, safe, outside auditor, access route or contact is inferred.

**A future written human authorization record must explicitly include**:
- Named authorizer and operator; any overlapping signer/evidence roles; authorization decision ID and time; R-A threat acceptance restricted to disposable preliminary Layer edit/edit RC01 only.
- Repository and branch; exact Git source HEAD **at future approval time** (do not reuse the B15A parent commit blindly); supervisor Git blob after any future reviewed change; five immutable source PHP/schema blob pins; PHP harness blob; Docker and disposable DB/image/network identities.
- One *new* unclaimed `run_id`, signed manifest digest, RSA public PEM exact-byte SHA-256 fingerprint and trusted source pin, issued/expiry UTC window <=15 minutes; no signer key in Git/chat/Mac worker environment.
- Exact approved fixture DML and cleanup scope: one synthetic farm, cycle and record; two test workers and independent host observer; bounded wait time and expected single commit/stale conflict; no production/staging/cross-tenant writes; required evidence/stop conditions.
- Mac/Docker/database preflight, empty schema attestation, captured source and test blob inventory, signed approval validity, trusted operator confirmation, evidence preservation destination and quarantine/recovery owner.
- Explicit **one-use launch permission** separate from permission to create a signer/keypair and separate from R-A model selection. If any field is unresolved or stale, DENY; do not reinterpret `proceed` as a wildcard run authorization.

**No approval yet exists under this runbook.** Do not create, sign, copy, chmod, upload or print future real approval files or signing credentials in this stage.

## D. Future staged procedure — not executable instructions

All gates below require their **own explicit authorization before execution**, independent source/operational review, and no repeated prompts to reapprove previously certified stages.

| Gate | Mandatory prerequisite | Evidence expected | Current state |
| --- | --- | --- | --- |
| B15A (this step) | R-A limited risk selection | Runbook Git-only commit and remote semantic diff; all source fuses off | DOCUMENTATION ONLY |
| B15B signer-ceremony **design** | Named custody/approval roles, threat assumptions and durable evidence location | Key algorithm/storage/recovery/revocation proposal; no key generated | NOT AUTHORIZED |
| B15C real key ceremony | New explicit authorization and independent review of provenance/private-key custody | Public PEM exact-byte digest and private-key absence from repo/chat/runner; no activated fuse | NOT AUTHORIZED |
| B15D source trust pinning | Key ceremony approved, exact verified public PEM bytes, frozen Git source | Source-only trusted public fingerprint change; Python/PHP execution OFF; fresh offline negative tests, exact semantic diff | NOT AUTHORIZED |
| B15E safe Mac approval-file provisioning | Separately approved owner, files, directories and source HEAD | No symlinks; expected 0700/0600; immutable evidence; public key only on runner | NOT AUTHORIZED |
| B15F runtime preflight and separately reviewed fuse changes | Explicit sole synthetic RC01 approval, exact disposable schema and Docker identity, committed source, reviewed fixture/teardown and hard-off-fuse changes | Two-fuse code review, branch freeze, source/test proof, signed one-use grant; no unapproved retries | NOT AUTHORIZED |
| MAC-12C single preliminary synthetic RC01 | Unique fresh run ID, approved Docker/SQL fixtures/worker execution/cleanup, independent operator sign-off and verified elapsed window | Host-observed actual InnoDB lock wait; one commit and one stale; durable revision 2; full fixture/barrier/owned container cleanup or quarantine | NOT AUTHORIZED |
| Certification/cutover | Distinct full RC01–RC14 production-relevant tests, later migration 092 and release approval | 0/14 today; synthetic one-off PASS would not count as official certification | HARD OFF |

**Important process constraint:** at the first source commit changing trusted signer, fuses or test plumbing, previous exact-head offline tests must be rerun against the new Git blob pins. Do not certify a runtime result from a frozen design document alone.

## E. Future preflight acceptance checklist (observe/verify only when separately permitted)

1. Confirm the isolated Mac operating user and lab path, clean Git branch and reviewed exact HEAD; source pinned PHP/schema blobs unchanged; compare against frozen reviewed manifest. Reject dirty/drifted checkout, symlinks, unknown files or misplaced permissions.
2. Verify only the expected Docker Desktop context, pinned Linux/amd64 PHP image, single private MariaDB container, private network without published ports and exact named volume and disposable credentials; verify no production host or external DB exposure. The existing worker accesses DB only over private synthetic container network; do not introduce an R-B host network side channel in R-A.
3. Verify `renee_b12_disposable` table/column/FK/revision fingerprint and zero rows, triggers/routines/events; no customer data or historical production restore. A previous metadata result does not substitute for preflight.
4. Review source-staged read-only file mounts, private barrier/tmpfs, UID/memory/pid limits, source files, host observer read-only query and synthetic credential handoff; never print, pipe into chat, expose in argv or copy root/test DB passwords.
5. Verify approval record, signed exact manifest and signature, pinned *public* PEM SHA-256, unique unspent run ID, timestamps including timezone/clock consistency, expiry before claim and before worker. Do not claim that the local clock is independently trusted or keys currently configured.
6. Verify intended fixture DML and cleanup statements, SQL guard restrictions, one-use claim behavior, post-failure quarantine, exact response sentinels and that no unrelated app/customer route is callable.
7. Require written one-use operator approval naming fixture inserts, two worker updates, independent InnoDB observer, stop/teardown and acceptable result; then **only after separate activation authorization** may a single controller be invoked. MAC-12B15A does not call it.

## F. Fail-closed/cleanup runbook and interpretation

**Stop without automatic retry** for signer mismatch, absent human authorization, expired grant, failed fsync, duplicate run ID, ambiguous database or Docker identity, source/image/manifest drift, unexpected schema rows, improper file/snapshot mode, unobserved actual wait, wrong worker session pairing, result mismatch, process timeout or teardown uncertainty.

**Local claim consumed once:** After an irreversible `claimed_<run_id>` appears it must never be removed, reused or reset, even if no worker started. A new attempt would need a new decision and signed manifest. Any manual remediation must be narrowly scoped, evidence-backed, separately authorized and must not destroy quarantined artifacts.

**Cleanup only on proven identity and success:** The harness should remove exact disposable farm/cycle/record and private barrier, then verify empty schema; supervisor should verify worker exit and that only its owned container and exact private snapshot/run directories can be cleaned. Preserve first failure and owned diagnostic artifacts when verification is uncertain; do not clean arbitrary containers, volumes or production data.

**No premature PASS:** A future preliminary success requires independent two-connection lock-wait observation, exactly one CAS commit, explicit stale conflict, durable revision 2 and complete fixture teardown; otherwise `FAIL_CLOSED_MANUAL_REVIEW`. Even this would be **PRELIMINARY_NONCERTIFYING_PASS**, not official repaired RC01–RC14 certification, migration authority or permission to resume 150/200 VU.

**Safe evidence format:** Operator commands, when separately reviewed, must print concise evidence only between literal `#####COPY FROM HERE#####` / `#####COPY TO HERE#####`; those markers surround **output only**, not commands. Never print secrets, raw database rows, signing private material or unnecessary stderr; store separate protected incident evidence only if approved.

## G. Current disposition and exact next decision

`MAC12B15A=RUNBOOK_SOURCE_ONLY_COMPLETE` upon a verified one-document Git commit; `D1_SECURITY_MODEL=R-A_LIMITED_SYNTHETIC_ONLY`; `SIGNER_ACTOR=PENDING`; `KEY_CEREMONY=NOT_AUTHORIZED`; `TRUST_ANCHOR=UNCONFIGURED`; `HUMAN_ONE_USE_APPROVAL=NOT_ISSUED`; `PYTHON_FUSE=OFF`; `PHP_FUSE=OFF`; `MAC12C=NOT_AUTHORIZED`; `MIGRATION_092=NOT_AUTHORIZED`; `B12_CUTOVER=HARD_OFF`; `RC01_RC14_CERTIFICATION=0_OF_14`.

**Recommended subsequent limited milestone:** design a signer-custody/approval-evidence checklist and exact **pre-ceremony** safety review with roles left unassigned unless the user supplies them. Do not perform a real key ceremony or source pin in response to a generic “proceed”; require explicit authority and custody choices first.
