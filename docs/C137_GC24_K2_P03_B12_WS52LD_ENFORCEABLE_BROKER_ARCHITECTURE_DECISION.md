# WS52-LD — Enforceable Docker evidence-broker architecture review (SOURCE ONLY)

**Date:** 2026-10-15. **Decision:** DESIGN_REVIEW_ONLY; no executable inspector, broker, Docker client, workflow, connection, SQL, cleanup or deployment authorized. **Predecessor:** WS52-LC exact HEAD `37c8a20fd02465c511955f2488402ee95b326e1c` in `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.

## 1. Grounded existing contracts (unchanged)

- `tools/b12_ws52kt_one_volume_identity_cleanup_contract.py` Git blob `e3c6b7b79bb979902b9d610234b6fd84f2120c2d`: pure one-new-volume / exact container and mount / full cleanup-inventory *shape* checks; every result `physical_attested=False`, `execution_authorized=False`. It is NOT an attestor.
- `tools/b12_ws52kv_physical_inspector_design_contract.py` blob `f6255fca70b6bdf959e77caf2b7a813c1afc0514`: six ordered fictional inspection checkpoints, hard-disabled; collector labels are NOT authenticated.
- `tools/b12_ws52kx_inspector_trust_boundary_design.py` blob `221809f072d661948d3dd02b8a69ad05f75ccc27`: six *nonexecutable* Docker metadata GET route templates and a proposed independent broker, not an API proxy or socket sandbox.
- `tools/b12_ws52kz_broker_projection_source_contract.py` blob `6bf2e8c2bf6a70838ffafe3d560e3f205c5da2ea`: exact mock request/context and pre-minimized projection; does NOT parse raw Docker JSON.
- `tools/b12_ws52lb_docker_redaction_adapter_source.py` blob `66727cccf56f3ead11610d55bb39cbe0fe760e12`: parses only already-minimized synthetic Docker-named JSON up to 4096 bytes and rejects real Docker response extras; NOT an actual Docker raw-response sanitizer.
- User-returned fixture-only Mac PASS evidence preserved in WS52-KU (KT 20/20), WS52-KW (KV 22/22), WS52-KY (KX 34/34), WS52-LA (KZ 29/29), and WS52-LC (LB 28/28). Source-blob-verified offline PASS is NOT Docker, DB, RC01 or runtime certification.
- Hard-off application gate `lib/daily_record_revision_cutover_gate.php` blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268` and CAS authority `lib/daily_record_revision_authority.php` blob `5fbb90903b0cc801be45c684f6c3db85c660979a` must remain unchanged.

## 2. Required physically enforceable architecture — *future*, not implemented

```text
Separately approved, immutable one-use manifest (no application-controlled values)
                    |
        isolated test control plane
                    |                       independently owned proof ledger
      ephemeral dedicated runner --------> prestate/attestation verifier
         |             |                               ^
         |             +-- test worker (NO Docker socket, no tokens)       |
         |                                                             |
         +-- isolated metadata broker (sole, separately controlled access) --+
                 |  server-side fixed method/path/resource allowlist
                 |  bounded raw parsing + projection BEFORE export
                 v
           disposable host Docker engine ONLY
                 |
       exactly one fresh container + explicitly named volume
                 |
          /var/lib/mysql mount, isolated MariaDB
```

**Threat-model boundary:** The Docker socket commonly grants authority approaching host administrator privilege; a GET-only *client promise* does not enforce read-only privilege against a general socket. Do not mount the engine socket into any PHP worker, source test, or untrusted GitHub job. A future broker must be the only engine-facing component, on an isolated disposable host containing no customer data, production credentials, persistent sensitive volumes, host bind mounts or shared capacity-test services. The broker's operating identity and permission enforcement must be validated independently; a Docker SDK with application-level `if method == GET` is insufficient as an isolation mechanism.

### Required control-plane capabilities before any real use

1. **Human-reviewed, immutable one-use approval manifest:** include pinned repository and source commit, approved broker/runner/workflow blobs, immutable OCI index/child/config and layer evidence, runner class/identity, run ID/attempt=1, TTL, exact container/volume names, synthetic-only database name, localhost binding, permitted inspection operations, maximum concurrent processes, timing and teardown/quarantine protocol. Sign outside the workload; the test job cannot self-approve by setting environment variables.
2. **Independent machine identity:** prove ephemeral runner and Docker Engine identity from an observer outside the test process and maintain pre-state inventories. A GitHub runner label, Docker label or environment variable is NOT cryptographic proof of host or resource provenance.
3. **Non-mutating broker operations:** enforce GET-only endpoint/method and exact path at the *broker transport boundary*, with no query/header extension, path traversal, encoded-separator normalization bypass, redirect, arbitrary Unix socket forwarding, streaming/archive/log/event/exec endpoint, or Docker engine API version override. Be aware that GET `/info` and container inspect still disclose high-value host/env metadata.
4. **Raw-response firewall:** broker takes real Docker HTTP bytes into a narrowly privileged process; cap bytes DURING stream receipt and timeouts BEFORE full buffering; reject compression unless separately bounded; decode UTF-8 and JSON with duplicate-field rejection, depth/nodes/collection limits; validate expected route envelope; extract ONLY fixed non-sensitive fields needed by KT/KV/KX; redact at broker before any log, artifact, child process, report or network export. Reject unreviewed fields or retain them only inside a sealed inaccessible transient broker buffer. Never use raw DTO/stringification as a fallback on failure.
5. **Exact resource ownership:** source of truth is independent before/after daemon inventory + individual inspect (not caller strings). Require EXACTLY one new per-run container and one new *explicitly named* local volume, effective sole writable mount at `/var/lib/mysql`, container ID/image/config/volume/labels bound to the same run, no host/bind/anonymous/protected mounts. Existing MariaDB OCI config declares VOLUME `/var/lib/mysql`; requesting no mount is not sufficient to avoid an implicit anonymous volume.
6. **Host and network isolation:** actual socket listener and outbound egress restrictions must be verified from a separate host/network observer; Docker `bridge` + published `127.0.0.1:3306` configuration alone does not prove blocking of unrestricted outbound connections or external exposure. Fail closed on any unrelated listener, privileged mode, shared network, unproven firewall or backend host leakage.
7. **Distinct provisioning and inspection privileges:** starting/stopping a container and deleting a volume are inherently mutating; keep them OUT of the read-only inspector. Provisioner/teardown control plane requires separate, exact-resource one-use approval. Inspection must never run `prune`, `rm -f` on unreviewed identities, wildcard deletion, schema migrations, or production service operations.
8. **Tamper-resistant evidence:** observer-owned prestate and poststate snapshots with hash, monotonic sequence, run/engine/host/image/container/volume linkage, timestamps and independent signer; raw sensitive values retained only in protected ephemeral observer custody if needed. An assistant-generated JSON fixture or self-reported `physical_attested=true` does NOT certify evidence.
9. **Fail-closed cleanup/quarantine:** if worker, DB transaction, barrier, identity, container, volume, network or inventory state is uncertain, STOP all next-stage work; quarantine exact ephemeral resources and request separate human diagnosis. Cleanup requires independent exact-ID absence checks, snapshot equality for preexisting assets, and zero synthetic residue; never assume success from command exit code alone.

## 3. Future raw-response projection contracts by endpoint

| Route (GET only) | Privileged raw fields to prevent export | Strictly necessary projected evidence | Additional required proof |
|---|---|---|---|
| `/version` | ancillary Components/build details | engine version and API version identifiers | matched independent Engine identity |
| `/info` | DockerRootDir, RegistryConfig, DriverStatus, plugins, host configuration | validated engine ID/version only | independent host/runner provenance |
| `/containers/json` | Names, Commands, Labels, network and host data of unrelated containers | set of container IDs, with no names/raw configs | before/after snapshot binding; preserve protected resources |
| `/containers/{exact_id}/json` | Config.Env, HostConfig extras, Mounts.Source, NetworkSettings and host paths | pinned ID, image config ID, privileged/network/ports, effective Mounts type/name/destination/read-write/driver | container actually new and one-use owned |
| `/volumes` | Mountpoint, Options, Labels outside synthetic scope | set of names only, matched to pre-existing inventory | no lost/extra volume |
| `/volumes/{exact_name}` | Mountpoint and driver Options; sensitive labels | pinned name, local driver, fixed run-scoped labels | independent link to actual effective Mounts |

**Caveat:** The current LB adapter demands already-minimized fields and will reject ordinary full Docker responses. A future real broker implementation must implement this field-selective raw projection *inside its protected process*, then prove that it never exports raw responses. No current code meets this runtime gate.

## 4. Independent negative verification and acceptance requirements

- Unit/source-only: unknown methods, query strings, path encoding aliases, stolen per-run IDs, extra/missing/raw sensitive fields, oversize/chunked/compressed/truncated responses, nested duplicate keys, parser bombs, invalid UTF-8, `NaN`/Infinity, secret reflection, redirects, stale snapshots, wrong engine image/config, unexpected anonymous volume and unauthorized socket access MUST fail closed.
- Integration in later, separately approved isolated disposable host: independent adversarial test tries to mutate the Docker engine through the broker and MUST be rejected at transport privilege boundary; inspection of pre-existing volume MUST be impossible using caller-supplied IDs; verify no real raw API object can leave sealed broker boundary or appear in logs.
- Physical proof must distinguish requested Docker flags from engine-returned effective Mounts; independent network/egress observations from user-supplied status; inspect-not-found from a deleted-resource claim; and actual worker/transaction absence from fake fixtures.
- Hold runtime activation pending all of the above plus signed one-use approval. Explicit review is mandatory before any Docker image pull, container create/start/stop/remove, SQL or GH workflow enablement.

## 5. Current verified disposition and next owner

- WS52-LD **architecture/design review COMPLETE** with *no code or host operation*. Stage 1 **implementation NOT COMPLETE**: no actual isolated broker, raw projection, signed manifests or physical attestations.
- Recommended next permitted work: source-only implementation of a pure raw-response projector with limits and a separately reviewed isolated broker skeleton on an inert branch; its source-only tests do NOT certify real isolation. Actual broker/socket/runner use remains a distinct explicit authorization gate.
- `B12_CUTOVER=HARD_OFF`; `MIGRATION_092=NOT_AUTHORIZED`; `RUNTIME_APPROVAL=NOT_GRANTED`; repaired RC01–RC14 certified PASS=0. Historical B12 race GitHub Actions run `38233373699` remains FAILED_TWO_STALE_COMMITS.
