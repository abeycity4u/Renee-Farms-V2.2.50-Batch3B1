"""WS52-KX: HARD-DISABLED, pure source-only inspector trust-boundary design.

NOT a Docker broker or API client. Models policy for a future independently
reviewed read-only collector. No sockets, Docker API, shell/subprocess, files,
SQL, application imports or production connections. ALL input is untrusted.
Even valid fixture shape is NOT physical attestation or runtime authorization.
Reuse WS52-KV observation stage ordering and WS52-KT mount/cleanup logic.
"""
from __future__ import annotations

from dataclasses import dataclass
from typing import Any

from b12_ws52kv_physical_inspector_design_contract import (
    READ_ONLY_INTENTS,
    STAGES,
)

INSPECTOR_EXECUTION_ENABLED = False
DOCKER_SOCKET_ACCESS_GRANTED = False
DOCKER_MUTATIONS_ALLOWED = False
SQL_ALLOWED = False
RC01_ALLOWED = False
APPROVED_ONE_USE_RUN = None
APPROVED_COLLECTOR_COMMIT = None
APPROVED_DOCKER_BROKER = None

REPOSITORY = "abeycity4u/Renee-Farms-V2.2.50-Batch3B1"
SOURCE_PARENT = "59b459432fa05726219ec8d7e8c43e89e1a807ef"
REVIEW_SCOPE = "SOURCE_ONLY_UNTRUSTED_HYPOTHETICAL"
NOT_COLLECTED = "NOT_COLLECTED"
NO_APPROVAL = "NOT_GRANTED"

# Illustrative, NONEXECUTABLE API path templates. No Docker client or socket.
# A later independently secured broker must redact raw responses *before*
# export. GET on a raw Docker socket is not a security boundary; Docker
# socket access may be equivalent to host-root privileges.
DOCKER_METADATA_ROUTES = (
    ("GET", "/version"),
    ("GET", "/info"),
    ("GET", "/containers/json"),
    ("GET", "/containers/{pinned_container_id}/json"),
    ("GET", "/volumes"),
    ("GET", "/volumes/{pinned_volume_name}"),
)
# Explicit absence: no create, exec, start, stop, rm, prune, archives, logs,
# events, secret-bearing env, full raw API payloads or mutating endpoints.

PLAN_KEYS = frozenset({
    "repository", "source_parent", "design_scope",
    "target_runner", "expected_workflow_state", "runner_token_permissions",
    "runner_secrets", "runner_kind_claim", "runner_claim_attested",
    "docker_socket_access", "docker_socket_to_test_process",
    "broker_approval", "broker_boundary",
    "collector_runtime_enabled", "host_mutation_capability",
    "network_egress_policy", "loopback_binding_observation",
    "sensitive_response_handling", "response_projection",
    "metadata_routes", "observation_intents", "phase_labels",
    "provenance", "one_use_approval",
})
PROVENANCE_KEYS = frozenset({
    "source_commit", "workflow_commit", "one_use_run_id",
    "run_attempt", "engine_id", "image_id",
    "container_id", "volume_id", "collector_identity",
    "independent_observer_identity", "host_inventory_signature",
})
# Permitted projected evidence labels, never raw Docker response fields.
PROJECTION = (
    "source_identity_status",
    "runner_identity_status",
    "engine_identity_status",
    "preinventory_status",
    "container_volume_link_status",
    "loopback_egress_status",
    "worker_quiescence_status",
    "postcleanup_inventory_status",
)
# Each field can only be reported as an UNATTESTED design placeholder.
EMPTY_PROVENANCE = {k: NOT_COLLECTED for k in PROVENANCE_KEYS}


@dataclass(frozen=True)
class BoundaryResult:
    shape_valid: bool
    blockers: tuple[str, ...]
    physical_attested: bool = False
    one_use_approved: bool = False
    network_isolated: bool = False
    cleanup_certified: bool = False
    rc01_certified: bool = False


def _result(errors: list[str]) -> BoundaryResult:
    return BoundaryResult(not errors, tuple(sorted(set(errors))))


def _keys(obj: Any, expected: frozenset[str]) -> bool:
    return type(obj) is dict and frozenset(obj) == expected


def _exact_tuple_list(obj: Any, expected: tuple[Any, ...]) -> bool:
    return type(obj) is list and tuple(obj) == expected


def assess_inspector_boundary(plan: Any) -> BoundaryResult:
    """Only review a proposed collector trust boundary and safe output shape.

    This cannot confirm identities of runners, the Docker engine, a Docker
    broker, firewall, TLS peer, volume, independent observer, or cleanups.
    A dict's truthiness, GitHub env vars, action YAML or Docker labels alone
    can never grant capability. Refuse unexpected fields or raw data.
    """
    if not _keys(plan, PLAN_KEYS):
        return _result(["TRUST_BOUNDARY_KEYS_INVALID"])
    failures: list[str] = []
    for k, expected in (
        ("repository", REPOSITORY),
        ("source_parent", SOURCE_PARENT),
        ("design_scope", REVIEW_SCOPE),
        ("target_runner", "FUTURE_EPHEMERAL_GITHUB_HOSTED_ONLY"),
        ("expected_workflow_state", "NOT_CREATED"),
        ("runner_kind_claim", "UNVERIFIED_GITHUB_HOSTED_DESIGN"),
        ("broker_boundary", "REVIEWED_ISOLATED_READONLY_BROKER_REQUIRED"),
        ("network_egress_policy", "PENDING_INDEPENDENT_NETWORK_MEASUREMENT"),
        ("loopback_binding_observation", "PENDING_INDEPENDENT_SOCKET_MEASUREMENT"),
        ("sensitive_response_handling", "BROKER_ALLOWLIST_REDACTS_BEFORE_EXPORT"),
        ("one_use_approval", NO_APPROVAL),
    ):
        if plan[k] != expected:
            failures.append("INVALID_DESIGN_" + k.upper())
    if plan["runner_token_permissions"] != {} or plan["runner_secrets"] != []:
        failures.append("UNREVIEWED_RUNNER_TOKENS_OR_SECRETS")
    if (plan["runner_claim_attested"] is not False or
            plan["docker_socket_access"] is not False or
            plan["docker_socket_to_test_process"] is not False or
            plan["broker_approval"] is not None or
            plan["collector_runtime_enabled"] is not False or
            plan["host_mutation_capability"] is not False):
        failures.append("REAL_RUNTIME_OR_PRIVILEGE_CLAIM_PRESENT")
    if not _exact_tuple_list(plan["metadata_routes"], DOCKER_METADATA_ROUTES):
        failures.append("DOCKER_METADATA_ROUTE_ALLOWLIST_DRIFT")
    if not _exact_tuple_list(plan["observation_intents"], READ_ONLY_INTENTS):
        failures.append("OBSERVATION_INTENT_DRIFT")
    if not _exact_tuple_list(plan["phase_labels"], tuple(stage for stage, _, _ in STAGES)):
        failures.append("OBSERVATION_PHASE_DRIFT")
    if not _exact_tuple_list(plan["response_projection"], PROJECTION):
        failures.append("UNREDACTED_OR_INCOMPLETE_RESPONSE_PROJECTION")
    if not _keys(plan["provenance"], PROVENANCE_KEYS):
        failures.append("PROVENANCE_KEYS_INVALID")
    elif any(type(plan["provenance"][k]) is not str or
             plan["provenance"][k] != NOT_COLLECTED
             for k in PROVENANCE_KEYS):
        failures.append("PHYSICAL_PROVENANCE_NOT_AUTHORIZED_TO_CLAIM")
    return _result(failures)


def safe_evidence_summary(plan: Any) -> dict[str, str]:
    """No output derives from raw untrusted values or sensitive source data."""
    review = assess_inspector_boundary(plan)
    if not review.shape_valid:
        return {
            "AUDIT": "B12_WS52KX_INSPECTOR_TRUST_BOUNDARY",
            "RESULT": "BLOCKED_SOURCE_CONTRACT",
            "RUNTIME_APPROVAL": "NOT_GRANTED",
            "PHYSICAL_DOCKER_INSPECTION": "NO",
        }
    return {
        "AUDIT": "B12_WS52KX_INSPECTOR_TRUST_BOUNDARY",
        "RESULT": "SOURCE_DESIGN_SHAPE_ONLY_NOT_ATTESTED",
        "RUNTIME_APPROVAL": "NOT_GRANTED",
        "PHYSICAL_DOCKER_INSPECTION": "NO",
    }
