"""WS52-KI read-only public MariaDB registry *manifest metadata* check.

Does NOT pull image layers, call Docker, access a database or local backups,
run SQL, install software, write files, load credentials, or modify a registry.
Only anonymous HTTPS GET to auth.docker.io's public pull-scoped token endpoint,
then HEAD tag manifest and GET exact digest index from registry-1.docker.io.
This is not a supply-chain signature or host/container attestation.
Source must be pinned and reviewed before the user chooses to run it.
"""
from __future__ import annotations

import datetime
import hashlib
import json
import re
import ssl
import sys
import urllib.error
import urllib.parse
import urllib.request

IMAGE_REPO = "library/mariadb"
TAG = "10.11"
TOKEN_URL = (
    "https://auth.docker.io/token?"
    + urllib.parse.urlencode({
        "service": "registry.docker.io",
        "scope": "repository:library/mariadb:pull",
    })
)
REGISTRY = "https://registry-1.docker.io/v2/library/mariadb/manifests/"
INDEX_TYPES = frozenset((
    "application/vnd.oci.image.index.v1+json",
    "application/vnd.docker.distribution.manifest.list.v2+json",
))
CHILD_TYPES = frozenset((
    "application/vnd.oci.image.manifest.v1+json",
    "application/vnd.docker.distribution.manifest.v2+json",
))
ACCEPT = ", ".join((
    *sorted(INDEX_TYPES),
    *sorted(CHILD_TYPES),
))
DIGEST = re.compile(r"\Asha256:[0-9a-f]{64}\Z")
MAX_INDEX = 262144
MAX_TOKEN = 16384


class ManifestRejected(ValueError):
    pass


def _no_duplicate_json_pairs(pairs):
    out = {}
    for key, value in pairs:
        if key in out:
            raise ManifestRejected("DUPLICATE_MANIFEST_OBJECT_KEY")
        out[key] = value
    return out


def digest_of(payload: bytes) -> str:
    return "sha256:" + hashlib.sha256(payload).hexdigest()


def inspect_index(payload: bytes, claimed_digest: str) -> tuple[str, str]:
    """Pure offline content+linux/amd64 child validation, no I/O.

    Never treat a Docker image ID or local SHA256 string as a registry digest.
    Returns (index digest, child linux/amd64 digest) after integrity checks.
    """
    if not isinstance(claimed_digest, str) or not DIGEST.fullmatch(claimed_digest):
        raise ManifestRejected("INDEX_DIGEST_INVALID")
    if not isinstance(payload, bytes) or not 0 < len(payload) <= MAX_INDEX:
        raise ManifestRejected("INDEX_SIZE_INVALID")
    if digest_of(payload) != claimed_digest:
        raise ManifestRejected("INDEX_CONTENT_DIGEST_MISMATCH")
    try:
        index = json.loads(payload.decode("utf-8"),
                           object_pairs_hook=_no_duplicate_json_pairs)
    except (ValueError, UnicodeError, TypeError) as exc:
        raise ManifestRejected("INDEX_JSON_INVALID") from exc
    if not isinstance(index, dict) or index.get("schemaVersion") != 2 or (
        type(index.get("schemaVersion")) is not int
    ) or index.get("mediaType") not in INDEX_TYPES:
        raise ManifestRejected("NOT_REVIEWED_OCI_DOCKER_INDEX")
    manifests = index.get("manifests")
    if not isinstance(manifests, list) or not 1 <= len(manifests) <= 256:
        raise ManifestRejected("INDEX_MANIFEST_LIST_INVALID")
    amd64 = []
    for entry in manifests:
        if not isinstance(entry, dict) or not isinstance(entry.get("platform"), dict):
            raise ManifestRejected("INDEX_ENTRY_PLATFORM_INVALID")
        platform = entry["platform"]
        if platform.get("os") != "linux" or platform.get("architecture") != "amd64":
            continue
        if platform.get("variant") not in (None, ""):
            raise ManifestRejected("UNREVIEWED_LINUX_AMD64_VARIANT")
        child = entry.get("digest")
        if not isinstance(child, str) or not DIGEST.fullmatch(child) or (
            entry.get("mediaType") not in CHILD_TYPES
        ) or type(entry.get("size")) is not int or not 0 < entry["size"] < MAX_INDEX:
            raise ManifestRejected("LINUX_AMD64_CHILD_MANIFEST_INVALID")
        amd64.append(child)
    if len(amd64) != 1:
        raise ManifestRejected("LINUX_AMD64_CHILD_COUNT_NOT_ONE")
    return claimed_digest, amd64[0]


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, fp, code, msg, headers, newurl):
        raise ManifestRejected("HTTP_REDIRECT_UNEXPECTED")


def _make_opener():
    return urllib.request.build_opener(
        urllib.request.ProxyHandler({}),
        NoRedirect,
        urllib.request.HTTPSHandler(context=ssl.create_default_context()),
    )


def _read_response(opener, request, cap):
    with opener.open(request, timeout=12) as response:
        if response.status != 200:
            raise ManifestRejected("UNEXPECTED_HTTP_STATUS")
        blob = response.read(cap + 1)
        if not blob or len(blob) > cap:
            raise ManifestRejected("RESPONSE_TOO_LARGE")
        return response.headers, blob


def read_only_registry_identity() -> tuple[str, str, str]:
    """Public anonymous registry METADATA ONLY; no layers or Docker operations."""
    opener = _make_opener()
    _, token_payload = _read_response(
        opener,
        urllib.request.Request(
            TOKEN_URL,
            headers={"Accept": "application/json",
                     "User-Agent": "Renee-B12-WS52KI-readonly/1"},
            method="GET",
        ),
        MAX_TOKEN,
    )
    try:
        token_json = json.loads(token_payload.decode("utf-8"))
        token = token_json.get("token")
    except (ValueError, UnicodeError, AttributeError) as exc:
        raise ManifestRejected("PUBLIC_TOKEN_RESPONSE_INVALID") from exc
    if not isinstance(token, str) or not 10 <= len(token) <= 8192:
        raise ManifestRejected("PUBLIC_PULL_SCOPED_TOKEN_UNAVAILABLE")

    def manifest_req(ref: str, method: str):
        return urllib.request.Request(
            REGISTRY + ref,
            method=method,
            headers={
                "Authorization": "Bearer " + token,
                "Accept": ACCEPT,
                "User-Agent": "Renee-B12-WS52KI-readonly/1",
            },
        )

    with opener.open(manifest_req(TAG, "HEAD"), timeout=12) as response:
        if response.status != 200:
            raise ManifestRejected("TAG_HEAD_STATUS")
        digest = response.headers.get("Docker-Content-Digest", "")
        tag_media_type = response.headers.get("Content-Type", "").split(";")[0].strip()
    if not DIGEST.fullmatch(digest) or tag_media_type not in INDEX_TYPES:
        raise ManifestRejected("TAG_NOT_DIGEST_INDEX")
    get_headers, payload = _read_response(
        opener, manifest_req(digest, "GET"), MAX_INDEX
    )
    observed = get_headers.get("Docker-Content-Digest", "")
    actual_type = get_headers.get("Content-Type", "").split(";")[0].strip()
    if observed != digest or actual_type not in INDEX_TYPES:
        raise ManifestRejected("DIGEST_FETCH_HEADER_DRIFT")
    index_digest, linux_amd64_child = inspect_index(payload, digest)
    return index_digest, linux_amd64_child, actual_type


def main() -> int:
    reason = "NONE"
    result = "BLOCKED"
    index_digest = "NOT_ESTABLISHED"
    child_digest = "NOT_ESTABLISHED"
    media_type = "NOT_ESTABLISHED"
    if sys.platform != "darwin":
        reason = "NOT_MACOS"
    else:
        try:
            index_digest, child_digest, media_type = read_only_registry_identity()
            result = "READONLY_REGISTRY_METADATA_PASS"
        except Exception:
            # No raw URL, response, auth token, HTTP error or local path printed.
            reason = "REGISTRY_METADATA_NOT_ATTESTED"
    now = datetime.datetime.now(datetime.timezone.utc).isoformat(timespec="seconds")
    print("#####COPY FROM HERE#####")
    for key, value in (
        ("AUDIT", "B12_WS52KI_READONLY_MARIADB_OCI_INDEX"),
        ("RESULT", result),
        ("REASON", reason),
        ("SOURCE_TAG", "docker.io/library/mariadb:10.11_MUTABLE"),
        ("REGISTRY_SOURCE", "registry-1.docker.io/library/mariadb"),
        ("OBSERVED_UTC", now),
        ("OCI_INDEX_DIGEST", index_digest),
        ("LINUX_AMD64_CHILD_MANIFEST_DIGEST", child_digest),
        ("INDEX_CONTENT_TYPE", media_type),
        ("MANIFEST_HASH_VALIDATION", "PASS" if result.endswith("PASS") else "NOT_ESTABLISHED"),
        ("OCI_IMAGE_ID", "NOT_OBSERVED"),
        ("SIGNATURE_PROVENANCE", "NOT_ATTESTED"),
        ("DOCKER_ENGINE_IDENTITY", "NOT_INSPECTED"),
        ("CONTAINER_IDENTITY", "NOT_CREATED"),
        ("IMAGE_LAYER_DOWNLOAD", "NO"),
        ("DOCKER_PULL_OR_OPERATIONS", "NO"),
        ("SQL_EXECUTED", "NO"),
        ("DATABASE_CONNECTIONS", "0"),
        ("RC01_WORKERS_EXECUTED", "NO"),
        ("HISTORICAL_BACKUP_OPENED", "NO"),
        ("APPROVED_RUNTIME_IMAGE", "NOT_APPROVED"),
        ("ONE_USE_RUNTIME_APPROVAL", "NOT_GRANTED"),
        ("REPAIRED_RC01_RC14_CERTIFIED_PASSES", "0"),
        ("MIGRATION_092", "NOT_AUTHORIZED"),
        ("B12_CUTOVER", "HARD_OFF"),
    ):
        print(key + "=" + value)
    print("#####COPY TO HERE#####")
    return 0 if result.endswith("PASS") else 2


if __name__ == "__main__":
    raise SystemExit(main())
