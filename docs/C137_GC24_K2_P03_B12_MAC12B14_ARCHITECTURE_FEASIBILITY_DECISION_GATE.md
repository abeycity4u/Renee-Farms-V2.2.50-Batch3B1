# C137 / B12 — MAC-12B14 feasibility review and human decision gate

**Date:** 2026-10-17. **Repository:** `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`. **Working branch:** `c137-gc24-k2p03-b12-mac12b1-disabled-rc01`. **Source HEAD reviewed before this document:** `8156890b71b3ceb1434b27bd8564621cca401861`.

**Disposition:** READ-ONLY SOURCE/ARCHITECTURE FEASIBILITY REVIEW COMPLETE; **SECURITY MODEL SELECTION PENDING USER DECISION**. This document does not authorize an R-A assumption, deploy R-B infrastructure, perform a key ceremony, create or sign an approval, open a network channel, inspect Docker/SQL, modify source/test code, enable either fuse, or execute MAC-12C. No user identity, signer role or staffing was inferred from a generic “proceed.”

## 1. Confirmed evidence and fixed scope

Source reviewed:
- `tools/b12_mac12b3_rc01_supervisor_disabled.py`: Git blob `9628679e80836cfc4c148f17790638c70c5e2cbf`. `EXECUTION_ENABLED = False`; `TRUSTED_APPROVER_PUBLIC_KEY_SHA256 = None`; RSA-SHA256 V1 fixed-path approval, <=15-minute window, synthetic-only scope, local `O_EXCL`/fsync claim; no independent human-authorization/revocation/time/ledger checks. Lab `~/renee-b12-rc-mac`, disposable database `renee_b12_disposable`, private Docker network `renee_b12_rc_mac_isolated`, existing Docker and database password access on the Mac. This supervisor is not activated.
- `tests/integration/k2p03_b12_mac_rc01_preliminary_disabled.php`: Git blob `2e288c611e0b43217eb1b8e15234ee823b65f940`; `B12_RC01_EXECUTION_ENABLED = false`. No MAC-12C workers ran.
- MAC-12B12-OFF **user-returned** result under Git HEAD `5f3dbe5035fbcc6ad827effe258cf9afdfddcfaa`: nine source/test blobs verified; Python syntax 4/4; 44/44 offline tests passed, zero skips, real *ephemeral test-only* RSA pass, swapped RSA failure, concurrent local claim pass, supervisor exit 90 and cleanup pass. Subsequent MAC-12B13 was documentation-only; no source/test drift reported. These tests **do not verify R-B independent enforcement**.
- Protected production `v320-renee-agrisuite-branding` HEAD `10041da3a3c4e4a7b8101f8183c243d056c7b128`; protected source and other release branch heads must be checked independently after committing this document. Repaired RC01–RC14 official certification = **0/14**. Migration 092 **NOT AUTHORIZED**, B12 cutover **HARD OFF**.
- MAC-12B13 source document `C137_GC24_K2_P03_B12_MAC12B13_RB_INDEPENDENT_SECURITY_ARCHITECTURE_DESIGN.md` is a **proposal**, not installed architecture. Neither credential broker, independent ledger, signer, separate approval role, nor external authority has been established.

## 2. What decision is actually being made?

**Decision limited to the one-time, disposable, *noncertifying* synthetic RC01 concurrency experiment**, not the security posture of commercial Renee AgriSuite, production/staging, tenancy, payment flows, formal GA certification, migrations, or future load tests. This decision does not itself approve any fixture, writer execution or signing-key setup.

- **R-A:** limited trust model with a cooperative, non-malicious Mac operator and externally recorded human approval. Existing local claims and frozen source provide defense against accidental double invocation and drift; they **cannot** defeat deliberate same-UID claim deletion, host-clock tampering, bypass of the disabled Python supervisor by an operator already holding Docker/DB access, or prompt/live revocation after issuance. Any adoption must explicitly accept these risks and require all existing hard-fuses and one-use future authorizations.
- **R-B1:** independently administered *online* approval/revocation/one-use authority. For true adversarial execution control, this must be paired with a broker or separate host that withholds Docker/DB credentials until authorized. Authoritative ledger alone, while useful for audit, cannot prevent a malicious Mac operator using existing direct Docker/DB capabilities. A separate host-side approved network control channel would need a new connectivity review, distinct from the isolated Docker network. No endpoint or service exists in this record.
- **R-B2:** independent *offline interactive* witness with protected monotonic consumption and genuine credential/launcher separation. Potentially compatible with strict no-network lab constraints, but must establish separate hardware/OS security domain, human availability, time/revocation policy and audit. No such operator, device or broker exists in this record.

**Feasibility verdict:** R-A has an existing local substrate but is a weaker trust model; R-B1 and R-B2 are **architecture concepts requiring new ownership, components, credentials and validation**, not drop-in switches. Neither is independently proven achievable with present Mac lab permissions or budget. Avoid assumed costs or completion dates.

## 3. Conditional comparison for this test only

| Criterion | R-A restricted lab | R-B1 online | R-B2 offline witness |
| --- | --- | --- | --- |
| Existing isolated Mac lab | Can reuse after separately approved one-use safeguards | Can preserve isolated DB/PHP network; new **host-side** control channel & broker required | Can preserve isolated network; separate witness/credential broker required |
| Independent human approval | Out-of-band decision record, enforced operationally, **not by existing code** | Independently authenticated decision required at authority | Independently authenticated decision required at witness |
| Technical revocation freshness | Not established; stop manually and preserve fuses | Possible before defined atomic spend/launch boundary if status service fresh; offline/unreachable => deny | Only as current as witness state and communications; no real-time guarantee absent mechanism |
| Same-UID local claim deletion | **Not prevented** | Independent atomic spent-ID registry can deny replay, conditional on cannot bypass launch | Monotonic witness can deny replay, conditional on cannot bypass launch |
| Direct Docker/DB credential bypass | **Not prevented** | **Not prevented** until independent broker/credential separation | **Not prevented** until independent broker/credential separation |
| Host-clock manipulation | Existing source trusts Mac UTC | Future authority must define trusted time, skew and TTL; not implemented | Independent reliable time source/attestation needed; not implemented |
| Audit durability | Local plus independently retained manual evidence, not tamper-proof against compromised runner | Separate durable authenticated event/decision ledger required | Separate durable witness log/register required |
| New infrastructure or actors | Least new components, but user must accept narrow threat model | New service/custody/endpoint/broker/maintenance, and code | New witness/hardware/actor/broker/maintenance, and code |
| Current permission to enable or test | **NO** | **NO** | **NO** |

A signed approval alone proves possession of a key, not authorization by an independent human or revocation freshness. Neither an R-B “approval” service nor an append-only event log on the same compromised Mac may be described as independent, tamper-proof protection.

## 4. Proportionality recommendation (NOT a user decision)

For **diagnosing the disposable preliminary RC01 CAS race**, *if* the user explicitly accepts the cooperative-operator assumption and the existing Mac is not being defended against intentional same-UID compromise, **R-A is likely proportionate**. The narrow experiment is intentionally *noncertifying*. Mandatory independent human scope authorization, permanent claim use, source+database identity evidence, stop-on-failure, and no automatic retry still apply. R-A cannot stand in for eventual GA security or production hardening.

For **strong resistance to malicious local operators, compromised signing keys and cross-host replay**, **R-B** is the appropriate objective; prefer exploring an independently administered authority and a separate credential/launch boundary rather than a cosmetic revocation flag. This is a separate implementation/operations project, with its own design, staffing, lifecycle and negative/integration tests. Whether that level of assurance is mandatory for this synthetic test is an unresolved risk decision.

**Do not silently turn the commercial SaaS's future threat model into R-A** because this preliminary experiment can use R-A. Equally, do not indefinitely defer evidence about a disposable CAS race solely to build an unapproved privileged control-plane service.

## 5. Human decisions and evidence that MUST precede any next activation path

**Decision D1 — choose lab threat model** (a single explicit choice, not inferred from “proceed”):
- `D1=R-A_LIMITED_SYNTHETIC_ONLY`: accept no protection against deliberate same-UID Docker/DB access, claim deletion or host-clock spoofing during a *single preliminary* RC01, while preserving all independent written approvals and hard gates.
- `D1=R-B1_INDEPENDENT_ONLINE`: require separate authenticated authority, owned host egress constraints and credential/launch broker, with explicit funding/role decisions before code.
- `D1=R-B2_INDEPENDENT_OFFLINE`: require witness, independent monotonic register and broker, with explicit role/device decisions before code.
- `D1=UNDECIDED`: no changes to runtime or signer; no one-use execution.

**Decision D2 — responsibility:** identify project authorizer, independent signing-key custodian, Mac runner, independent auditor, plus authority/broker operator if R-B; document any same-person role overlaps. Without confirmed people and real separation, a policy flow is not an independently enforced security guarantee.

**Decision D3 — connectivity and authority custody:** keep existing Docker internal/private. For R-B1, separately authorize any host egress, endpoint and identity; no current authorization exists. For R-B2, describe independent offline witness and communications. For R-A, explicitly state that no outside status check is enforced and prohibit claiming otherwise.

**Decision D4 — privileged credential model:** accept local disposable lab compromise risk if choosing R-A; for R-B, identify a credential/launch boundary inaccessible to Mac runner before claiming same-UID enforcement. No DB/Docker credential copying, privilege changes or infrastructure execution in B14.

**Decision D5 — evidence, clock and expiry:** decide independent record custody, failure quarantine, replay-log survival, max clock skew, revocation-response latency and escalation contacts. Current manifest expiry remains <=15 minutes and immutable until a separately reviewed source change.

**Decision D6 — independent authorization for any later operation:** formal review of exact new Git HEAD, five PHP/schema blob pins, disposable DB identity, fixture writes, teardown, timeout, one-use signer record, approval ID and no-production guarantee. This requires separate explicit signed/human one-use permission. MAC-12B14 cannot preapprove it.

## 6. Recommended next authorized work units after D1

**If D1 = R-A:** MAC-12B15A may draft an *exact source-only operational runbook* describing manual human approval evidence and constrained signer ceremony **without producing a real key**, followed by separately approved key setup and preliminary RC01 only after all required preflight/evidence. Every transition needs its own authorization. Do not remove either fuse as part of a runbook.

**If D1 = R-B1 or R-B2:** MAC-12B15B may draft the exact host/witness/credential boundary, independent ledger protocol, failure semantics and pure offline policy tests; only after owner/custodian/authority decisions may a separate implementation be authorized. Never create an “R-B” shim that can be bypassed by the same Mac account's existing Docker/DB control.

**If D1 undecided:** stop before signer/key, execution, network, and SQL; retain MAC-12B12-OFF 44/44 baseline. Source-only design documentation can be closed without implying D1 approval.

## 7. MAC-12B14 final disposition

`MAC12B14_FEASIBILITY_REVIEW=COMPLETE_DESIGN_ONLY`  
`D1_SECURITY_MODEL=UNDECIDED`  
`HUMAN_ROLES_AND_CUSTODY=PENDING`  
`R_B_AUTHORITY_AND_BROKER=NOT_IMPLEMENTED`  
`TRUSTED_SIGNER=UNCONFIGURED`  
`PYTHON_FUSE=OFF`  
`PHP_FUSE=OFF`  
`MAC12C=NOT_AUTHORIZED`  
`MIGRATION_092=NOT_AUTHORIZED`  
`B12_CUTOVER=HARD_OFF`  
`REPAIRED_RC01_RC14_CERTIFIED_PASSES=0_OF_14`

**B14 may close on documentation/diff evidence, but the human decision gate remains OPEN until the user explicitly selects a limited R-A or independently enforced R-B model.** No real key ceremony, protected environment changes or synthetic RC01 run follows from this document.
