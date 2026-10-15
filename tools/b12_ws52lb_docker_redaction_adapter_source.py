"""WS52-LB: PURE, hard-disabled Docker-named metadata REDACTION SPECIFICATION.

NO Docker client, raw socket, HTTP, files, SQL, subprocess, runtime, cleanup.
Consumes only small synthetic, *previously minimized* Docker-named JSON bytes.
A real Docker API response contains additional fields (Env, Mounts.Source,
Mountpoint, paths, labels etc) and is deliberately rejected. A future isolated
broker must independently parse/validate those actual bytes and minimize
BEFORE invoking any logic based on this reference shape. This module does
NOT prove that sanitization actually occurs at a real trust boundary.
"""
from __future__ import annotations

from dataclasses import dataclass
import json
import re
from typing import Any

from b12_ws52kz_broker_projection_source_contract import (
    assess_broker_fixture,
    ROUTE_NAMES,
    _routes,
)
from b12_ws52kt_one_volume_identity_cleanup_contract import CONFIG as PINNED_IMAGE_CONFIG

ADAPTER_EXECUTION_ENABLED = False
DOCKER_SOCKET_ACCESS_GRANTED = False
RAW_DOCKER_HTTP_ACCESS_GRANTED = False
NETWORK_ALLOWED = False
SQL_ALLOWED = False
RC01_ALLOWED = False
APPROVED_REAL_BROKER = None
APPROVED_ONE_USE_RUN = None

REPO = "abeycity4u/Renee-Farms-V2.2.50-Batch3B1"
SOURCE_PARENT = "8f6a4b5925074bcc8c2ee8e5966608c4714b554a"
MAX_RESPONSE_BYTES = 4096
MAX_COLLECTION_ELEMENTS = 8
MAX_NODES = 100
MAX_DEPTH = 6
VERSION = re.compile(r"\A[0-9]{1,3}(?:\.[0-9]{1,3}){1,3}\Z")
HEX64 = re.compile(r"\A[0-9a-f]{64}\Z")
SHA256 = re.compile(r"\Asha256:[0-9a-f]{64}\Z")
DOCKER_NAME = re.compile(r"\A[a-zA-Z0-9][a-zA-Z0-9_.-]{0,127}\Z")
VOLUME_NAME = re.compile(r"\Ab12-rc01-vol-r[1-9][0-9]{0,11}-[0-9a-f]{16}\Z")

MINIMIZED_KEYS = {
    "engine_version": frozenset({"Version", "ApiVersion"}),
    "engine_info": frozenset({"ID", "ServerVersion"}),
    "container_inventory": None,
    "single_container": frozenset({"Id", "Image", "HostConfig", "Mounts"}),
    "volume_inventory": frozenset({"Volumes"}),
    "single_volume": frozenset({"Name", "Driver", "Labels"}),
}
HOST_CONFIG = frozenset({"Privileged", "NetworkMode", "PortBindings"})
PORT = frozenset({"HostIp", "HostPort"})
MOUNT = frozenset({"Type", "Name", "Destination", "RW", "Driver"})
LABELS = frozenset({"run_id", "purpose"})
CONTAINER_LIST_ENTRY = frozenset({"Id"})
VOLUME_LIST_ENTRY = frozenset({"Name"})


@dataclass(frozen=True)
class SourceProjection:
    shape_valid: bool
    blockers: tuple[str, ...]
    source_only_status: str
    physical_attested: bool = False
    broker_executed: bool = False
    actual_raw_response_redacted: bool = False
    one_use_approved: bool = False
    cleanup_certified: bool = False


def _result(blockers: list[str]) -> SourceProjection:
    errors = tuple(sorted(set(blockers)))
    return SourceProjection(not errors, errors, (
        "SOURCE_ONLY_MINIMIZED_FIXTURE_SHAPE" if not errors
        else "BLOCKED_SOURCE_CONTRACT"
    ))


def _keys(obj: Any, keys: frozenset[str]) -> bool:
    return type(obj) is dict and frozenset(obj) == keys


def _matches(value: Any, pattern: re.Pattern) -> bool:
    return type(value) is str and pattern.fullmatch(value) is not None


def _no_duplicates(pairs: list[tuple[str, Any]]) -> dict[str, Any]:
    result: dict[str, Any] = {}
    for key, value in pairs:
        if key in result:
            raise ValueError("DUPLICATE_FIELD")
        result[key] = value
    return result


def _reject_constant(_constant: str) -> Any:
    raise ValueError("JSON_NONFINITE")


def _bounded_json(data: Any) -> tuple[Any, str | None]:
    if type(data) is not bytes or not 0 < len(data) <= MAX_RESPONSE_BYTES:
        return None, "RESPONSE_BYTES_INVALID_OR_EXCESSIVE"
    try:
        root = json.loads(
            data.decode("utf-8", "strict"),
            object_pairs_hook=_no_duplicates,
            parse_constant=_reject_constant,
        )
    except (ValueError, UnicodeError, RecursionError, OverflowError):
        return None, "JSON_MALFORMED_DUPLICATED_OR_UNSAFE"

    nodes = 0
    stack = [(root, 0)]
    while stack:
        obj, depth = stack.pop()
        nodes += 1
        if nodes > MAX_NODES or depth > MAX_DEPTH:
            return None, "JSON_DEPTH_OR_NODE_CAP"
        if type(obj) is dict:
            if len(obj) > MAX_COLLECTION_ELEMENTS:
                return None, "JSON_COLLECTION_LIMIT"
            stack.extend((v, depth + 1) for v in obj.values())
            if any(type(k) is not str or len(k) > 64 for k in obj):
                return None, "JSON_FIELD_NAME_LIMIT"
        elif type(obj) is list:
            if len(obj) > MAX_COLLECTION_ELEMENTS:
                return None, "JSON_COLLECTION_LIMIT"
            stack.extend((v, depth + 1) for v in obj)
        elif type(obj) is str and len(obj) > 256:
            return None, "JSON_VALUE_LIMIT"
        elif type(obj) is int and len(str(obj)) > 24:
            return None, "JSON_VALUE_LIMIT"
        elif type(obj) is float:
            return None, "JSON_FLOAT_NOT_PERMITTED"
        elif obj is not None and type(obj) not in (str, int, bool):
            return None, "JSON_VALUE_TYPE"
    return root, None


def _list_of_unique_names(items: Any, field: str, pattern: re.Pattern) -> bool:
    if type(items) is not list or len(items) > MAX_COLLECTION_ELEMENTS:
        return False
    seen: set[str] = set()
    for obj in items:
        if not _keys(obj, frozenset({field})):
            return False
        value = obj[field]
        if not _matches(value, pattern) or value in seen:
            return False
        seen.add(value)
    return True


def _schema_errors(kind: str, root: Any, context: dict[str, Any]) -> list[str]:
    errors: list[str] = []
    cid = context["container_id"]
    vol = context["volume_name"]
    run = context["run_id"]
    if kind == "container_inventory":
        if not _list_of_unique_names(root, "Id", HEX64):
            errors.append("CONTAINER_INVENTORY_NOT_MINIMIZED")
        return errors
    if not _keys(root, MINIMIZED_KEYS[kind]):
        return ["UNEXPECTED_DOCKER_FIELDS_OR_STRUCTURE"]
    if kind == "engine_version":
        if not (_matches(root["Version"], VERSION) and
                _matches(root["ApiVersion"], VERSION)):
            errors.append("ENGINE_VERSION_FORMAT")
    elif kind == "engine_info":
        if not (type(root["ID"]) is str and
                1 <= len(root["ID"]) <= 128 and
                all(32 <= ord(ch) <= 126 for ch in root["ID"]) and
                _matches(root["ServerVersion"], VERSION)):
            errors.append("ENGINE_INFO_FORMAT")
    elif kind == "volume_inventory":
        if not _list_of_unique_names(root["Volumes"], "Name", DOCKER_NAME):
            errors.append("VOLUME_INVENTORY_NOT_MINIMIZED")
    elif kind == "single_volume":
        if (root["Name"] != vol or root["Driver"] != "local" or
                not _keys(root["Labels"], LABELS) or
                root["Labels"] != {"run_id": run, "purpose": "b12-rc01-disposable"}):
            errors.append("VOLUME_IDENTITY_OR_LABELS_MISMATCH")
    elif kind == "single_container":
        hc = root["HostConfig"]
        mounts = root["Mounts"]
        if (root["Id"] != cid or root["Image"] != PINNED_IMAGE_CONFIG):
            errors.append("CONTAINER_IMAGE_OR_ID_MISMATCH")
        if not _keys(hc, HOST_CONFIG):
            errors.append("HOST_CONFIG_NOT_MINIMIZED")
        elif (hc["Privileged"] is not False or hc["NetworkMode"] != "bridge"
              or hc["PortBindings"] != {
                  "3306/tcp": [{"HostIp": "127.0.0.1", "HostPort": "3306"}]
              }):
            errors.append("PRIVILEGE_OR_PORT_NOT_ALLOWED")
        if (type(mounts) is not list or len(mounts) != 1 or
                not _keys(mounts[0], MOUNT)):
            errors.append("EFFECTIVE_MOUNT_NOT_EXACTLY_ONE")
        elif (mounts[0] != {
            "Type": "volume", "Name": vol, "Destination": "/var/lib/mysql",
            "RW": True, "Driver": "local",
        }):
            errors.append("MOUNT_IDENTITY_OR_PERMISSIONS_MISMATCH")
    else:
        errors.append("UNREVIEWED_ROUTE_CLASS")
    return errors


def assess_minimized_fixture(
    context: Any, request: Any, normalized_json_bytes: Any
) -> SourceProjection:
    """Consume SYNTHETIC *pre-minimized* Docker-shaped bytes; never raw API.

    Input never leaves memory or becomes a Docker operation. Reject unknown
    field names (including Env, Mounts.Source, Mountpoint, Config, credentials).
    A real isolated broker, not this design, must safely parse and reduce its
    real raw API responses before any export. All outputs are fixed labels.
    """
    if type(context) is not dict or type(request) is not dict:
        return _result(["BROKER_CONTEXT_OR_REQUEST_INVALID"])
    try:
        cid, vol = context["container_id"], context["volume_name"]
        routes = _routes(cid, vol) if type(cid) is str and type(vol) is str else ()
        pair = (request.get("method"), request.get("path"))
        route_kind = ROUTE_NAMES[routes.index(pair)] if pair in routes else None
        # Reuse WS52-KZ's immutable broker context/request policy verbatim.
        envelope = {
            "route_class": route_kind,
            "http_status": 200,
            "content_type": "application/json",
            "content_encoding": "identity",
            "fixture_class": "SYNTHETIC_SCHEMA_ONLY",
            "projected_keys": [],
            "projection_state": "NOT_COLLECTED",
        }
        reviewed = assess_broker_fixture(context, request, envelope)
    except (KeyError, ValueError, TypeError, IndexError):
        return _result(["BROKER_REQUEST_CONTEXT_UNREVIEWED"])
    if not reviewed.shape_valid:
        return _result(["WS52KZ_BROKER_SHAPE_REJECTED"])
    body, reason = _bounded_json(normalized_json_bytes)
    if reason is not None:
        return _result([reason])
    if route_kind not in MINIMIZED_KEYS:
        return _result(["ROUTE_CLASS_NOT_REVIEWED"])
    try:
        problems = _schema_errors(route_kind, body, context)
    except (KeyError, TypeError, ValueError):
        return _result(["MINIMIZED_SCHEMA_VALUE_INVALID"])
    return _result(problems)


def safe_report(projection: Any) -> dict[str, str]:
    """Never include raw JSON, IDs, labels, paths, secrets or exceptions."""
    passed = type(projection) is SourceProjection and projection.shape_valid
    return {
        "AUDIT": "B12_WS52LB_REDACTION_ADAPTER_SOURCE",
        "RESULT": ("SOURCE_ONLY_MINIMIZED_FIXTURE_SHAPE"
                   if passed else "BLOCKED_SOURCE_CONTRACT"),
        "RAW_DOCKER_RESPONSE_EXPORTED": "NO",
        "PHYSICAL_DOCKER_INSPECTION": "NO",
        "BROKER_EXECUTION": "NO",
        "RUNTIME_APPROVAL": "NOT_GRANTED",
        "B12_CUTOVER": "HARD_OFF",
    }
