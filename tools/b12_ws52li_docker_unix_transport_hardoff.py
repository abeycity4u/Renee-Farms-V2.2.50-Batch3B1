"""B12 WS52-LI — actual Unix-domain HTTP transport code, HARD-DISABLED.

Unlike WS52-LG, this contains a real Unix-socket GET implementation.
It is NOT available to a caller while RUN_ENABLED=False and independent
approval/engine provenance gates are absent. No attempt to use the socket,
Docker, SQL, image registry, databases, or workflow is made by importing.
All runtime authorization is deliberately impossible in this revision.

A Docker socket can control the host; GET-only code is NOT an operating-system
capability boundary. A future deployment needs a separate privileged broker,
independent observer, immutable one-use approval, and isolated disposable host.
"""
from __future__ import annotations

from dataclasses import dataclass
import socket
from typing import Any

from b12_ws52kz_broker_projection_source_contract import (
    ROUTE_NAMES, _routes, assess_broker_fixture,
)
from b12_ws52le_raw_docker_response_projection_core import (
    MAX_RAW_BYTES, project_raw_response,
)

RUN_ENABLED = False
DOCKER_SOCKET_PERMISSION = False
APPROVAL_VERIFIER_IMPLEMENTED = False
INDEPENDENT_ENGINE_ATTESTED = False
ISOLATED_HOST_ATTESTED = False
SQL_ALLOWED = False
RC01_ALLOWED = False
ONE_USE_APPROVAL = None

SOURCE_PARENT = "202bbe63387da8d6dc430bb0eae2316941a918a0"
SOCKET_PATH = "/var/run/docker.sock"  # Never reached with runtime gate OFF.
MAX_HEADER_BYTES = 8192
MAX_WIRE_BYTES = MAX_HEADER_BYTES + MAX_RAW_BYTES + 4
READ_CHUNK = 4096
SOCKET_TIMEOUT_SECONDS = 2.0
ALLOWED_HEADERS = frozenset({
    "content-type", "content-length", "content-encoding", "connection",
    "date", "server", "api-version", "docker-experimental", "ostype",
})


@dataclass(frozen=True)
class WireBody:
    """Internal only: must never be used as an exported status or artifact."""
    body: bytes
    status: int
    content_type: str
    encoding: str


@dataclass(frozen=True)
class TransportStatus:
    accepted: bool
    status: str
    physical_attested: bool = False
    engine_identity_proven: bool = False
    socket_contacted: bool = False
    raw_exported: bool = False
    rc01_certified: bool = False


def _fail(label: str) -> TransportStatus:
    return TransportStatus(False, label)


def _request_for_path(path: Any) -> bytes:
    """Fixed syntax; callers cannot supply absolute URLs or custom headers."""
    if type(path) is not str or not path.startswith("/") or len(path) > 180:
        raise ValueError("REQUEST_PATH_INVALID")
    if not all(ch.isascii() and (
        ch.isalnum() or ch in "/._-"
    ) for ch in path):
        raise ValueError("REQUEST_PATH_INVALID")
    if "//" in path or "/../" in path or "/./" in path:
        raise ValueError("REQUEST_PATH_INVALID")
    return (
        "GET " + path + " HTTP/1.1\r\n"
        "Host: docker\r\n"
        "Accept: application/json\r\n"
        "Accept-Encoding: identity\r\n"
        "Connection: close\r\n\r\n"
    ).encode("ascii")


def _parse_http_wire(data: Any) -> WireBody:
    """Parse supplied COMPLETE HTTP/1.1 response; never leak raw text."""
    if type(data) is not bytes or not 0 < len(data) <= MAX_WIRE_BYTES:
        raise ValueError("WIRE_SIZE_INVALID")
    pos = data.find(b"\r\n\r\n")
    if pos < 0 or pos > MAX_HEADER_BYTES:
        raise ValueError("HEADER_BOUNDARY_INVALID")
    head, body = data[:pos], data[pos + 4:]
    if len(body) == 0 or len(body) > MAX_RAW_BYTES:
        raise ValueError("BODY_SIZE_INVALID")
    # No obs-fold, lone LF or CR, control bytes, non-ASCII or redirects.
    try:
        lines = head.split(b"\r\n")
        if any(b"\n" in line or b"\r" in line for line in lines):
            raise ValueError("HTTP_FRAMING_INVALID")
        textlines = [line.decode("ascii", "strict") for line in lines]
    except UnicodeDecodeError:
        raise ValueError("HEADER_ASCII_INVALID") from None
    if not textlines or textlines[0] != "HTTP/1.1 200 OK":
        raise ValueError("HTTP_STATUS_INVALID")
    if len(textlines) > 18:
        raise ValueError("HEADER_LINE_CAP")
    found: dict[str, str] = {}
    for line in textlines[1:]:
        if not line or line[0] in " \t" or ":" not in line:
            raise ValueError("HTTP_HEADER_FORMAT")
        key, value = line.split(":", 1)
        if (not key or len(key) > 64 or
                not all(ch.isascii() and (
                    ch.isalnum() or ch == "-"
                ) for ch in key)):
            raise ValueError("HTTP_HEADER_NAME")
        lower = key.lower()
        if lower not in ALLOWED_HEADERS or lower in found:
            raise ValueError("HTTP_HEADER_UNKNOWN_OR_DUPLICATE")
        value = value.strip(" ")
        if (len(value) > 256 or not value or
                any(not (32 <= ord(ch) <= 126) for ch in value)):
            raise ValueError("HTTP_HEADER_VALUE")
        found[lower] = value
    if (found.get("content-type") != "application/json" or
            found.get("content-encoding", "identity") != "identity" or
            found.get("connection", "").lower() != "close"):
        raise ValueError("HTTP_HEADER_POLICY")
    length = found.get("content-length")
    if (length is None or not length.isascii() or
            not length.isdecimal() or str(int(length)) != length or
            int(length) != len(body)):
        raise ValueError("HTTP_LENGTH_INVALID")
    return WireBody(body, 200, "application/json", "identity")


def _bounded_socket_get(request_bytes: bytes) -> bytes:
    """Engine-touching primitive; NEVER CALL without separate code/host review.

    A general Docker socket grants powerful mutating capability even if this
    client sends GET. No live calls occur from the public hard-off entrypoint.
    """
    raw = bytearray()
    # socket is created ONLY inside this internal primitive, not at import.
    with socket.socket(socket.AF_UNIX, socket.SOCK_STREAM) as connection:
        connection.settimeout(SOCKET_TIMEOUT_SECONDS)
        connection.connect(SOCKET_PATH)
        connection.sendall(request_bytes)
        while True:
            part = connection.recv(READ_CHUNK)
            if not part:
                break
            if len(raw) + len(part) > MAX_WIRE_BYTES:
                raise ValueError("WIRE_LIMIT_EXCEEDED_DURING_READ")
            raw.extend(part)
            # If headers are present, reject any size excess early.
            split = raw.find(b"\r\n\r\n")
            if split >= 0:
                if split > MAX_HEADER_BYTES:
                    raise ValueError("HEADER_LIMIT_EXCEEDED_DURING_READ")
                if len(raw) - split - 4 > MAX_RAW_BYTES:
                    raise ValueError("BODY_LIMIT_EXCEEDED_DURING_READ")
            elif len(raw) > MAX_HEADER_BYTES + 4:
                raise ValueError("HEADER_LIMIT_EXCEEDED_DURING_READ")
    return bytes(raw)


def inspect_once(context: Any, request: Any) -> TransportStatus:
    """EXPLICIT HARD OFF. No env variable or input can grant authority."""
    if not (
        RUN_ENABLED is True and DOCKER_SOCKET_PERMISSION is True and
        APPROVAL_VERIFIER_IMPLEMENTED is True and
        INDEPENDENT_ENGINE_ATTESTED is True and
        ISOLATED_HOST_ATTESTED is True and
        ONE_USE_APPROVAL is not None
    ):
        return _fail("TRANSPORT_HARD_OFF")
    # This path is inaccessible at this source revision. A future reviewed
    # change must replace source constants with independently checked,
    # cryptographically bound approval and runner/engine attestation.
    try:
        if type(context) is not dict or type(request) is not dict:
            return _fail("REQUEST_CONTEXT_INVALID")
        cid, vol = context["container_id"], context["volume_name"]
        routes = _routes(cid, vol)
        pair = (request.get("method"), request.get("path"))
        idx = routes.index(pair)
        kind = ROUTE_NAMES[idx]
        envelope = {
            "route_class": kind, "http_status": 200,
            "content_type": "application/json", "content_encoding": "identity",
            "fixture_class": "SYNTHETIC_SCHEMA_ONLY",
            "projected_keys": [], "projection_state": "NOT_COLLECTED",
        }
        if not assess_broker_fixture(context, request, envelope).shape_valid:
            return _fail("REQUEST_POLICY_REJECTED")
        raw = _bounded_socket_get(_request_for_path(pair[1]))
        wire = _parse_http_wire(raw)
        projected = project_raw_response(
            context, request, wire.body,
            http_status=wire.status, content_type=wire.content_type,
            content_encoding=wire.encoding,
        )
        if not projected.shape_valid:
            return _fail("RAW_PROJECTION_REJECTED")
        # Even a successful projection alone cannot certify provenance.
        return _fail("INDEPENDENT_ATTESTATION_NOT_IMPLEMENTED")
    except (KeyError, ValueError, TypeError, IndexError, OSError,
            OverflowError, RecursionError, TimeoutError):
        return _fail("TRANSPORT_OR_PROJECTION_BLOCKED")


def safe_summary(value: Any) -> dict[str, str]:
    """Fixed output only: never serialize WireBody, raw JSON or identifiers."""
    return {
        "AUDIT": "B12_WS52LI_UNIX_TRANSPORT_SOURCE",
        "RESULT": "TRANSPORT_HARD_OFF",
        "DOCKER_SOCKET_CONTACTED": "NO",
        "DATABASE_CONNECTIONS": "0",
        "SQL_EXECUTED": "NO",
        "RUNTIME_APPROVAL": "NOT_GRANTED",
        "REPAIRED_RC01_RC14_CERTIFIED_PASSES": "0",
        "MIGRATION_092": "NOT_AUTHORIZED",
        "B12_CUTOVER": "HARD_OFF",
    }
