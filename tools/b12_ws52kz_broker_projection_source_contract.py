"""WS52-KZ: pure, HARD-DISABLED hypothetical Docker metadata broker contract.

NO physical broker, Docker/socket connection, HTTP/SQL, filesystem, runner,
subprocess, mutation, or runtime authorization is implemented. Inputs can be
forged. Shape validity is NEVER provenance, redaction proof or permission.
The contract only describes future broker request and export restrictions.
Do not pass Docker's raw API objects to workers or logs.
"""
from __future__ import annotations

from dataclasses import dataclass
import re
from typing import Any

from b12_ws52kx_inspector_trust_boundary_design import DOCKER_METADATA_ROUTES

BROKER_EXECUTION_ENABLED = False
DOCKER_SOCKET_ACCESS_GRANTED = False
DOCKER_MUTATIONS_ALLOWED = False
NETWORK_ALLOWED = False
SQL_ALLOWED = False
RC01_ALLOWED = False
PHYSICAL_BROKER_APPROVED = None
APPROVED_ONE_USE_RUN = None

REPO = "abeycity4u/Renee-Farms-V2.2.50-Batch3B1"
SOURCE_PARENT = "9da2dff0c3db1420524ac184a886354e217582b1"
RUN_RE = re.compile(r"\A[1-9][0-9]{0,11}\Z")
CONTAINER_RE = re.compile(r"\A[0-9a-f]{64}\Z")
VOLUME_RE = re.compile(r"\Ab12-rc01-vol-r[1-9][0-9]{0,11}-[0-9a-f]{16}\Z")

CONTEXT_KEYS = frozenset({
    "repository", "source_parent", "runner_scope", "run_id",
    "container_id", "volume_name", "review_status", "broker_state",
    "socket_access", "worker_socket_access", "one_use_approval",
    "inherited_secrets", "runner_permissions", "engine_provenance",
    "egress_provenance",
})
REQUEST_KEYS = frozenset({
    "method", "path", "query", "headers", "body", "follow_redirects",
    "broker_token", "request_state",
})
RESPONSE_KEYS = frozenset({
    "route_class", "http_status", "content_type", "content_encoding",
    "fixture_class", "projected_keys", "projection_state",
})
# These are only names of NONEXECUTABLE future observation intents.
ROUTE_NAMES = (
    "engine_version",
    "engine_info",
    "container_inventory",
    "single_container",
    "volume_inventory",
    "single_volume",
)


@dataclass(frozen=True)
class SourceDecision:
    shape_valid: bool
    blockers: tuple[str, ...]
    physical_attested: bool = False
    broker_executed: bool = False
    one_use_approved: bool = False
    raw_response_exported: bool = False
    cleanup_certified: bool = False


def _result(errors: list[str]) -> SourceDecision:
    return SourceDecision(not errors, tuple(sorted(set(errors))))


def _keys(value: Any, expected: frozenset[str]) -> bool:
    return type(value) is dict and frozenset(value) == expected


def _routes(container_id: str, volume_name: str) -> tuple[tuple[str, str], ...]:
    # Expand only the six route *templates* already reviewed in WS52-KX.
    return tuple(
        (method, path.replace("{pinned_container_id}", container_id).replace(
            "{pinned_volume_name}", volume_name))
        for method, path in DOCKER_METADATA_ROUTES
    )


def assess_broker_fixture(context: Any, request: Any, projected_response: Any) -> SourceDecision:
    """Reject unrecognized input rather than projecting any raw Docker data.

    A future actual broker MUST separately enforce socket/API isolation, the
    HTTP method/path and resource identity, preexport schema minimization,
    response byte/time caps and independent operator approval. This source
    does not implement a client and cannot validate the truth of fixture data.
    """
    if not _keys(context, CONTEXT_KEYS):
        return _result(["CONTEXT_KEYS_INVALID"])
    if not _keys(request, REQUEST_KEYS):
        return _result(["REQUEST_KEYS_INVALID"])
    if not _keys(projected_response, RESPONSE_KEYS):
        return _result(["PROJECTED_RESPONSE_KEYS_INVALID"])
    problems: list[str] = []
    expected = (
        ("repository", REPO),
        ("source_parent", SOURCE_PARENT),
        ("runner_scope", "FUTURE_EPHEMERAL_GITHUB_HOSTED_ONLY"),
        ("review_status", "SOURCE_ONLY_UNTRUSTED"),
        ("broker_state", "NOT_CREATED"),
        ("one_use_approval", "NOT_GRANTED"),
        ("engine_provenance", "NOT_COLLECTED"),
        ("egress_provenance", "NOT_COLLECTED"),
    )
    if any(context[k] != v for k, v in expected):
        problems.append("UNPINNED_OR_PREMATURE_CONTEXT")
    if (context["socket_access"] is not False
            or context["worker_socket_access"] is not False
            or type(context["runner_permissions"]) is not dict
            or context["runner_permissions"] != {}
            or type(context["inherited_secrets"]) is not list
            or context["inherited_secrets"] != []):
        problems.append("FORBIDDEN_RUNTIME_PRIVILEGE_OR_SECRET")
    run = context["run_id"]
    cid = context["container_id"]
    vol = context["volume_name"]
    if (type(run) is not str or RUN_RE.fullmatch(run) is None
            or type(cid) is not str or CONTAINER_RE.fullmatch(cid) is None
            or type(vol) is not str or VOLUME_RE.fullmatch(vol) is None
            or not vol.startswith("b12-rc01-vol-r" + run + "-")):
        problems.append("RESOURCE_IDENTITY_UNBOUND")

    if (request["method"] != "GET" or type(request["method"]) is not str
            or type(request["path"]) is not str
            or type(cid) is not str or type(vol) is not str
            or (request["method"], request["path"]) not in _routes(cid, vol)):
        problems.append("UNALLOWLISTED_METHOD_PATH_OR_ID")
        route_class = None
    else:
        route_class = ROUTE_NAMES[_routes(cid, vol).index(
            (request["method"], request["path"]))]
    if (request["query"] != "" or type(request["query"]) is not str
            or type(request["headers"]) is not dict or request["headers"] != {}
            or request["body"] is not None or request["follow_redirects"] is not False
            or request["broker_token"] is not None
            or request["request_state"] != "NOT_EXECUTED_SOURCE_ONLY"):
        problems.append("REQUEST_HAS_UNREVIEWED_PARAMETERS_OR_EXECUTION")

    # This is a mock *already minimized* response envelope, NOT Docker JSON.
    # Treat any additional key or projected value as unredacted/unreviewed.
    if (projected_response["route_class"] != route_class or
            projected_response["http_status"] != 200 or
            type(projected_response["http_status"]) is not int or
            projected_response["content_type"] != "application/json" or
            projected_response["content_encoding"] != "identity" or
            projected_response["fixture_class"] != "SYNTHETIC_SCHEMA_ONLY" or
            type(projected_response["projected_keys"]) is not list or
            projected_response["projected_keys"] != [] or
            projected_response["projection_state"] != "NOT_COLLECTED"):
        problems.append("UNREVIEWED_RESPONSE_OR_RAW_DATA")
    return _result(problems)


def sanitized_static_output(context: Any, request: Any, response: Any) -> dict[str, str]:
    """Never concatenate input values, secrets, object reprs or raw endpoints."""
    decision = assess_broker_fixture(context, request, response)
    return {
        "AUDIT": "B12_WS52KZ_BROKER_SOURCE_BOUNDARY",
        "RESULT": ("SOURCE_ONLY_SHAPE_NOT_ATTESTED" if decision.shape_valid
                   else "BLOCKED_SOURCE_CONTRACT"),
        "PHYSICAL_DOCKER_INSPECTION": "NO",
        "RAW_DOCKER_RESPONSE_EXPORTED": "NO",
        "RUNTIME_APPROVAL": "NOT_GRANTED",
        "B12_CUTOVER": "HARD_OFF",
    }
