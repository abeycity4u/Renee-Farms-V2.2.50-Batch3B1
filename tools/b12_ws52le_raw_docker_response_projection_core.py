"""WS52-LE: real-shaped Docker JSON -> minimized evidence projection CORE.

Runs pure, in memory on supplied bytes. NO Docker client, Unix socket, HTTP
requests, files, subprocess, network, SQL, or runtime authorization.
A separately approved, truly isolated future broker must control the
transport and apply its own read caps BEFORE delivering bytes here.
This is a testable implementation of extraction, NOT a deployed safe broker,
independent physical attestor, or permission to enable the B12 cutover.
"""
from __future__ import annotations

from dataclasses import dataclass
import json
from typing import Any

from b12_ws52kz_broker_projection_source_contract import (
    ROUTE_NAMES, _routes, assess_broker_fixture,
)
from b12_ws52lb_docker_redaction_adapter_source import (
    assess_minimized_fixture,
    _no_duplicates, _reject_constant,
)

BROKER_RUNTIME_ENABLED = False
DOCKER_SOCKET_ACCESS_ALLOWED = False
TRANSPORT_IMPLEMENTED = False
IMAGE_PULL_ALLOWED = False
SQL_ALLOWED = False
RC01_ALLOWED = False
APPROVED_ONE_USE_RUNTIME = None

SOURCE_PARENT = "9dcf25a4907d676a8c27070eab60603744c4ad2d"
MAX_RAW_BYTES = 65536
MAX_RAW_NODES = 1024
MAX_RAW_DEPTH = 12
MAX_RAW_COLLECTION = 96
MAX_RAW_TEXT = 4096
MAX_RAW_FIELD_NAME = 128

_EMPTY_RESPONSE = {
    "http_status": 200,
    "content_type": "application/json",
    "content_encoding": "identity",
    "fixture_class": "SYNTHETIC_SCHEMA_ONLY",
    "projected_keys": [],
    "projection_state": "NOT_COLLECTED",
}


@dataclass(frozen=True)
class CoreResult:
    shape_valid: bool
    reason: str
    # Only a minimized, allowlisted internal record; may contain opaque IDs.
    # Never serialize this record to chat, logs or client-facing artifacts.
    minimized: Any = None
    physical_attested: bool = False
    real_broker_executed: bool = False
    one_use_approved: bool = False
    actual_raw_response_redaction_certified: bool = False
    cleanup_certified: bool = False


def _blocked(reason: str) -> CoreResult:
    return CoreResult(False, reason)


def _safe_string(value: str) -> bool:
    return not any(0xD800 <= ord(ch) <= 0xDFFF for ch in value)


def _bounded_load(raw: Any) -> tuple[Any, str | None]:
    if type(raw) is not bytes or not 0 < len(raw) <= MAX_RAW_BYTES:
        return None, "RAW_SIZE_OR_TYPE_INVALID"
    try:
        obj = json.loads(
            raw.decode("utf-8", "strict"),
            object_pairs_hook=_no_duplicates,
            parse_constant=_reject_constant,
        )
    except (ValueError, UnicodeError, RecursionError, OverflowError):
        return None, "RAW_JSON_INVALID"

    nodes = 0
    stack = [(obj, 0)]
    while stack:
        current, depth = stack.pop()
        nodes += 1
        if nodes > MAX_RAW_NODES or depth > MAX_RAW_DEPTH:
            return None, "RAW_NODES_OR_DEPTH_INVALID"
        if type(current) is dict:
            if len(current) > MAX_RAW_COLLECTION:
                return None, "RAW_COLLECTION_LIMIT"
            if any(
                type(k) is not str or len(k) > MAX_RAW_FIELD_NAME or
                not _safe_string(k) for k in current
            ):
                return None, "RAW_KEY_INVALID"
            stack.extend((v, depth + 1) for v in current.values())
        elif type(current) is list:
            if len(current) > MAX_RAW_COLLECTION:
                return None, "RAW_COLLECTION_LIMIT"
            stack.extend((v, depth + 1) for v in current)
        elif type(current) is str:
            if len(current) > MAX_RAW_TEXT or not _safe_string(current):
                return None, "RAW_STRING_INVALID"
        elif type(current) is float:
            return None, "RAW_FLOAT_INVALID"
        elif type(current) is int:
            if len(str(current)) > 24:
                return None, "RAW_INTEGER_INVALID"
        elif current is not None and type(current) is not bool:
            return None, "RAW_SCALAR_INVALID"
    return obj, None


def _pick(source: Any, names: tuple[str, ...]) -> dict[str, Any]:
    if type(source) is not dict:
        raise ValueError("NON_OBJECT")
    return {key: source[key] for key in names}


def _pick_entries(source: Any, names: tuple[str, ...]) -> list[dict[str, Any]]:
    if type(source) is not list:
        raise ValueError("NON_ARRAY")
    return [_pick(x, names) for x in source]


def _project(route: str, raw: Any) -> Any:
    # Every raw field not explicitly listed is DROPPED before validation/output.
    # Never log unexpected fields, especially Env, Source, Mountpoint, Config,
    # AuthConfig, Plugins, Commands, Labels of unrelated containers, etc.
    if route == "engine_version":
        return _pick(raw, ("Version", "ApiVersion"))
    if route == "engine_info":
        return _pick(raw, ("ID", "ServerVersion"))
    if route == "container_inventory":
        return _pick_entries(raw, ("Id",))
    if route == "volume_inventory":
        return {"Volumes": _pick_entries(_pick(raw, ("Volumes",))["Volumes"], ("Name",))}
    if route == "single_volume":
        volume = _pick(raw, ("Name", "Driver", "Labels"))
        volume["Labels"] = _pick(volume["Labels"], ("run_id", "purpose"))
        return volume
    if route == "single_container":
        values = _pick(raw, ("Id", "Image", "HostConfig", "Mounts"))
        host = _pick(values["HostConfig"], ("Privileged", "NetworkMode", "PortBindings"))
        # Do not copy any HostConfig secret fields. Only expose minimal port
        # binding labels, and let the LB strict schema reject extras.
        bindings = host["PortBindings"]
        if type(bindings) is not dict or frozenset(bindings) != frozenset({"3306/tcp"}):
            raise ValueError("PORT_BINDINGS_UNEXPECTED")
        host["PortBindings"] = {
            "3306/tcp": _pick_entries(bindings["3306/tcp"], ("HostIp", "HostPort"))
        }
        values["HostConfig"] = host
        mounts = _pick_entries(values["Mounts"],
                               ("Type", "Name", "Destination", "RW", "Driver"))
        values["Mounts"] = mounts
        return values
    raise ValueError("ROUTE_NOT_REVIEWED")


def project_raw_response(
    context: Any,
    request: Any,
    raw_bytes: Any,
    *,
    http_status: Any = 200,
    content_type: Any = "application/json",
    content_encoding: Any = "identity",
) -> CoreResult:
    """Actual extraction of selected fields from *supplied raw-looking bytes*.

    Validates WS52-KZ request+fixture scope BEFORE decoding bytes. Direct
    caller parameters are untrusted; a true isolated broker needs separate
    host and run identity proofs and transport enforcement.
    """
    if type(context) is not dict or type(request) is not dict:
        return _blocked("REQUEST_CONTEXT_INVALID")
    try:
        cid, vol = context["container_id"], context["volume_name"]
        if type(cid) is not str or type(vol) is not str:
            return _blocked("RESOURCE_ID_INVALID")
        pair = (request.get("method"), request.get("path"))
        routes = _routes(cid, vol)
        idx = routes.index(pair)
        route = ROUTE_NAMES[idx]
        envelope = dict(_EMPTY_RESPONSE, route_class=route)
        approval = assess_broker_fixture(context, request, envelope)
    except (KeyError, IndexError, TypeError, ValueError):
        return _blocked("BROKER_REQUEST_INVALID")
    if not approval.shape_valid:
        return _blocked("BROKER_POLICY_REJECTED")
    if (type(http_status) is not int or http_status != 200 or
            content_type != "application/json" or type(content_type) is not str or
            content_encoding != "identity" or type(content_encoding) is not str):
        return _blocked("HTTP_RESPONSE_ENVELOPE_REJECTED")
    raw, problem = _bounded_load(raw_bytes)
    if problem is not None:
        return _blocked(problem)
    try:
        minimized = _project(route, raw)
        # Do not export unvalidated records, even those with allowed key names.
        minimized_bytes = json.dumps(
            minimized, ensure_ascii=True, allow_nan=False, separators=(",", ":")
        ).encode("ascii")
        validated = assess_minimized_fixture(context, request, minimized_bytes)
    except (KeyError, ValueError, TypeError, OverflowError, RecursionError):
        return _blocked("PROJECTION_SHAPE_INVALID")
    if not validated.shape_valid:
        return _blocked("MINIMIZED_CONTRACT_REJECTED")
    return CoreResult(True, "SOURCE_ONLY_RAW_PROJECTION_SHAPE", minimized)


def safe_status(result: Any) -> dict[str, str]:
    """Constant safe evidence; no untrusted data, identifiers or raw bytes."""
    valid = type(result) is CoreResult and result.shape_valid
    return {
        "AUDIT": "B12_WS52LE_RAW_PROJECTION_CORE",
        "RESULT": ("SOURCE_ONLY_RAW_PROJECTION_SHAPE"
                   if valid else "BLOCKED_SOURCE_CONTRACT"),
        "BROKER_RUNTIME": "DISABLED",
        "PHYSICAL_DOCKER_INSPECTION": "NO",
        "RAW_HTTP_EXPORT": "NO",
        "RUNTIME_APPROVAL": "NOT_GRANTED",
        "MIGRATION_092": "NOT_AUTHORIZED",
        "B12_CUTOVER": "HARD_OFF",
    }
