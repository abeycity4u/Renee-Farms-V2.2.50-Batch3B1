"""WS52-LG: inert, bounded metadata-response ingress for the WS52-LE core.

This IS functional validation of caller-supplied, in-memory tuples of bytes.
It is NOT a Docker client, HTTP client, socket broker, streaming HTTP parser,
runner, trusted attestor or runtime authorization. Caller metadata and bytes
are completely untrusted. No network/FS/SQL/threads/subprocess are used.
A future, independently authorized isolated transport must obtain and verify
real HTTP response headers, enforce limits DURING read, and prevent socket
privilege and raw response leakage before using this stage.
"""
from __future__ import annotations

from dataclasses import dataclass
from typing import Any

from b12_ws52le_raw_docker_response_projection_core import (
    MAX_RAW_BYTES,
    project_raw_response,
)

TRANSPORT_IMPLEMENTED = False
DOCKER_SOCKET_ACCESS_GRANTED = False
NETWORK_ALLOWED = False
RAW_DOCKER_EXPORT_ALLOWED = False
IMAGE_PULL_ALLOWED = False
SQL_ALLOWED = False
RC01_ALLOWED = False
APPROVED_ONE_USE_RUNTIME = None

SOURCE_PARENT = "1c472fdb0f9b17b39f158fcf85a987f35140557c"
MAX_CHUNKS = 16
MAX_CHUNK_BYTES = 8192
MAX_BODY_BYTES = MAX_RAW_BYTES
ENVELOPE_KEYS = frozenset({
    "source_kind", "http_status", "content_type", "content_encoding",
    "transfer_encoding", "content_length", "redirects_followed",
    "read_completed", "connection_closed", "broker_approval",
})


@dataclass(frozen=True)
class IngressResult:
    shape_valid: bool
    reason: str
    physical_attested: bool = False
    actual_http_collected: bool = False
    socket_accessed: bool = False
    raw_exported: bool = False
    one_use_approved: bool = False
    rc01_certified: bool = False


def _stop(reason: str) -> IngressResult:
    return IngressResult(False, reason)


def ingest_inert_response(
    context: Any,
    request: Any,
    envelope: Any,
    byte_chunks: Any,
) -> IngressResult:
    """Accept bounded *synthetic* byte chunks; never fetch anything.

    Successful shape is not a trust-root attestation. No raw or minimized
    projection is returned from this boundary. Do not pass generators,
    arbitrary iterators or transport callbacks into this source-only core.
    """
    if type(envelope) is not dict or frozenset(envelope) != ENVELOPE_KEYS:
        return _stop("ENVELOPE_KEYS_INVALID")
    if (envelope["source_kind"] != "SYNTHETIC_IN_MEMORY_ONLY"
            or envelope["broker_approval"] is not None
            or envelope["redirects_followed"] is not False
            or envelope["read_completed"] is not True
            or envelope["connection_closed"] is not True):
        return _stop("ENVELOPE_PREMATURE_EXECUTION_OR_UNTRUSTED")
    if (type(envelope["http_status"]) is not int
            or envelope["http_status"] != 200
            or type(envelope["content_type"]) is not str
            or envelope["content_type"] != "application/json"
            or type(envelope["content_encoding"]) is not str
            or envelope["content_encoding"] != "identity"
            or type(envelope["transfer_encoding"]) is not str
            or envelope["transfer_encoding"] != "none"):
        return _stop("HTTP_RESPONSE_SHAPE_UNREVIEWED")
    claimed = envelope["content_length"]
    if (type(claimed) is not int or not 0 < claimed <= MAX_BODY_BYTES):
        return _stop("CONTENT_LENGTH_INVALID")
    if (type(byte_chunks) is not tuple or
            not 1 <= len(byte_chunks) <= MAX_CHUNKS):
        return _stop("BOUNDED_CHUNK_COUNT_INVALID")
    total = 0
    for chunk in byte_chunks:
        if type(chunk) is not bytes or not 0 < len(chunk) <= MAX_CHUNK_BYTES:
            return _stop("BOUNDED_CHUNK_INVALID")
        total += len(chunk)
        if total > MAX_BODY_BYTES:
            return _stop("BODY_CAP_EXCEEDED")
    if total != claimed:
        return _stop("BODY_LENGTH_MISMATCH")
    # No prior read or external transport. Even synthetic chunks may be
    # malicious. Reuse the reviewed LE raw projection rather than redoing
    # any method/path, content or identity validation.
    try:
        projected = project_raw_response(
            context, request, b"".join(byte_chunks),
            http_status=200,
            content_type="application/json",
            content_encoding="identity",
        )
    except (TypeError, ValueError, OverflowError, RecursionError):
        return _stop("RAW_PROJECTION_UNEXPECTED_FAILURE")
    if not projected.shape_valid:
        return _stop("RAW_PROJECTION_REJECTED")
    return IngressResult(True, "INERT_FIXTURE_INGRESS_SHAPE_ONLY")


def safe_summary(result: Any) -> dict[str, str]:
    """Exactly fixed status labels; never include raw bytes or identifiers."""
    accepted = type(result) is IngressResult and result.shape_valid
    return {
        "AUDIT": "B12_WS52LG_INERT_BROKER_INGRESS",
        "RESULT": ("INERT_FIXTURE_INGRESS_SHAPE_ONLY" if accepted
                   else "BLOCKED_SOURCE_CONTRACT"),
        "PHYSICAL_DOCKER_INSPECTION": "NO",
        "DOCKER_SOCKET_ACCESS": "NO",
        "RAW_DOCKER_EXPORT": "NO",
        "RUNTIME_APPROVAL": "NOT_GRANTED",
        "REPAIRED_RC01_RC14_CERTIFIED_PASSES": "0",
        "MIGRATION_092": "NOT_AUTHORIZED",
        "B12_CUTOVER": "HARD_OFF",
    }
