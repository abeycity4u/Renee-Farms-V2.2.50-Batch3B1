# B12 WS52-LG — Inert broker ingress, response-bounding and closed-header controls

**2026-10-16 | SOURCE_COMMITTED_TESTS_NOT_RUN | No real Docker transport or runtime approval.**

## Exact checkpoint

- Repo: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Exact base: `c137-gc24-k2p03-b12-ws52lf-raw-projection-pass-evidence` @ `1c472fdb0f9b17b39f158fcf85a987f35140557c`.
- New branch: `c137-gc24-k2p03-b12-ws52lg-broker-ingress-disabled`.
- New functional **in-memory-only** ingress: `tools/b12_ws52lg_inert_broker_response_ingress.py`, Git blob `ada29d4993f205c3c62ca2debbfc83cbc0f938da`.
- New **28 authored, NOT EXECUTED** tests: `tests/test_b12_ws52lg_inert_broker_response_ingress.py`, Git blob `18a6dbd6ec0b1d5a9d7a3dc6ce02a277e0c35008`.
- Reuses previously offline-verified LE raw projection core blob `857c7e1bc0a9c85a26a3918b9a751f1d52102ba4` and its test-fixture helpers blob `801947caf61ba085206f914fef143a0e162e3155`, without rerunning the earlier 28/28 suite.
- Prior unchanged KT/KV/KX/KZ/LB shared pure contracts: `e3c6b7b79bb979902b9d610234b6fd84f2120c2d`, `f6255fca70b6bdf959e77caf2b7a813c1afc0514`, `221809f072d661948d3dd02b8a69ad05f75ccc27`, `6bf2e8c2bf6a70838ffafe3d560e3f205c5da2ea`, `66727cccf56f3ead11610d55bb39cbe0fe760e12`.
- Protected original B12 branch: `c137-gc24-k2p03-b12-cas-writer-cutover-source` @ `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`. Protected production: `v320-renee-agrisuite-branding` @ `10041da3a3c4e4a7b8101f8183c243d056c7b128`.

## What has been implemented, and what has not

- This code **does actually consume and validate caller-supplied in-memory synthetic bytes** in an exact tuple of at most 16 chunks. Each chunk must be literal `bytes`, non-empty, and at most 8,192 bytes, with accumulated size at most LE's `MAX_RAW_BYTES=65536`. The declared byte length must match the complete joined payload and be literal integer within range.
- Closed metadata fields require source_kind=`SYNTHETIC_IN_MEMORY_ONLY`, HTTP 200 integer, `application/json`, `identity` content encoding, transfer encoding `none`, no redirects, completed read, closed connection, broker approval None. Unexpected keys and all nonconforming metadata fail closed.
- The ingress delegates method/path/run/container/volume identity checks, raw JSON duplicate/limit parsing and strict minimized redaction to the unchanged WS52-LE/KZ/LB shared contracts. It avoids reimplementing resource validation and never returns raw payload, minimized IDs, secret fields or caller-supplied errors. All public `safe_summary` values are fixed and sanitized.
- 28 source-level tests cover all six inherited endpoints; hard-off gates; successful bounded splitting; missing/extra/invalid metadata; source-kind and approval claims; status/MIME/compression; redirects/truncated reads; chunk size/count/format; aggregate length; forged matching length; wrong request or resource ID; non-leakage; import purity. Test functions are authored, **not yet run or passed**.
- **Important limitation:** Synthetic envelope flags are self-reported and forgeable. The code does NOT perform any real HTTP read, enforce a streaming network read deadline, authenticate a Docker Engine or independently isolate/approve a broker. A caller could supply fabricated successful input. It does not certify actual host egress, volume ownership, cleanup or the repaired CAS race.

## Future real broker security gate (separate authorization)

- Only a separately controlled, least-privileged observer can establish actual host/runner/Docker provenance. A raw Docker socket is a powerful host control interface, and restricting an application to calling GET is not a complete security boundary.
- A later review must implement physical Unix-socket/API segregation from all PHP/test workers, exact-route access mediation, read deadlines and byte limits **during** raw HTTP reception, independent identity/snapshot proofs, broker-owned field minimization before export, immutable output evidence and distinct provisioning/teardown authority. This cannot be authorized by changing a Boolean in the source.
- MariaDB OCI index/config/layers, actual local volume mount, network egress/listener, historical backup clone, RC01–RC14 database race tests, and migration 092 review remain incomplete. Original GitHub race job `38233373699=FAILED_TWO_STALE_COMMITS`.

## Next acceptance criteria

- Run only new WS52-LG 28 fixture-only cases using Mac verification with exact pinned Git blobs (including the prior LE test module as importable fixture helper). Do NOT treat import of previous test module as executing its old unit suite.
- Before user returns evidence, keep result at `TESTS_AUTHORED_NOT_EXECUTED`. If PASS, preserve user-returned result in separate documentation-only branch and review remote diff/protected source IDs.
- WS52-LG remote comparison against exact base must show three added files only (in-memory ingress, tests, report), no modifications/deletions to existing source, migrations, GitHub workflows, CAS/cutover or production. No Actions runs.

## Unchanged release gates

`B12_CUTOVER=HARD_OFF`; `MIGRATION_092=NOT_AUTHORIZED`; `RUNTIME_APPROVAL=NOT_GRANTED`; `REPAIRED_RC01_RC14_CERTIFIED_PASSES=0`.
