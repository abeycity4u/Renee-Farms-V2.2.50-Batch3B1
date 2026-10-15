"""WS52-KK: Mac-only, read-only integrity check of ONE public OCI CONFIG blob.

Fixed public anonymous token GET + exact registry config blob GET only.
No Docker, SQL, container, layer, private data, local file writes, or subprocess.
No redirects, env-proxy use, or printed token/config/history/Env contents.
A content digest is NOT an official image signature or runtime approval.
"""
from __future__ import annotations

import datetime
import hashlib
import json
import re
import ssl
import sys
import urllib.parse
import urllib.request

INDEX_DIGEST = "sha256:7db29378d4fdab73f8123bbc2b48905c90d1a4b00cf848b028f1e81e623257f2"
CHILD_DIGEST = "sha256:dc9dd57be7f46ad6f350c598a331d64f295899c4abac3ec5652eff831fc38a6c"
CONFIG_DIGEST = "sha256:41096ae9913468679bb5e05bbc19f44edcaf0eae45d67fcde224ffdeda9d3a1f"
CONFIG_SIZE = 8790
EXPECTED_DIFF_IDS = 8
MAX_TOKEN_BYTES = 16384
SHA256 = re.compile(r"\Asha256:[0-9a-f]{64}\Z")
TOKEN_URL = "https://auth.docker.io/token?" + urllib.parse.urlencode({
    "service": "registry.docker.io",
    "scope": "repository:library/mariadb:pull",
})
BLOB_URL = "https://registry-1.docker.io/v2/library/mariadb/blobs/" + CONFIG_DIGEST


class ConfigRejected(ValueError):
    """A fixed, non-secret error reason safe to include in sanitized output."""


def no_duplicate_keys(pairs):
    obj = {}
    for key, value in pairs:
        if key in obj:
            raise ConfigRejected("DUPLICATE_JSON_KEY")
        obj[key] = value
    return obj


def sha256_digest(raw: bytes) -> str:
    return "sha256:" + hashlib.sha256(raw).hexdigest()


def _declared_argv(value, name: str) -> str:
    if value is None:
        return "NOT_DECLARED"
    if not isinstance(value, list) or any(not isinstance(x, str) for x in value):
        raise ConfigRejected("CONFIG_" + name + "_TYPE_INVALID")
    return "NONEMPTY_ARRAY" if value else "EMPTY_ARRAY"


def inspect_config_bytes(raw: bytes, digest: str, length: int,
                         expected_layers: int) -> dict[str, str]:
    """Pure/offline integrity and limited shape check: NEVER return raw config."""
    if not isinstance(digest, str) or SHA256.fullmatch(digest) is None:
        raise ConfigRejected("EXPECTED_CONFIG_DIGEST_INVALID")
    if type(length) is not int or length < 1 or length > 65536:
        raise ConfigRejected("EXPECTED_CONFIG_LENGTH_INVALID")
    if type(expected_layers) is not int or not 1 <= expected_layers <= 256:
        raise ConfigRejected("EXPECTED_LAYER_COUNT_INVALID")
    if not isinstance(raw, bytes) or len(raw) != length:
        raise ConfigRejected("CONFIG_RAW_LENGTH_MISMATCH")
    if sha256_digest(raw) != digest:
        raise ConfigRejected("CONFIG_CONTENT_SHA256_MISMATCH")
    try:
        doc = json.loads(raw.decode("utf-8"), object_pairs_hook=no_duplicate_keys)
    except (UnicodeError, TypeError, ValueError) as exc:
        raise ConfigRejected("CONFIG_JSON_INVALID") from exc
    if not isinstance(doc, dict):
        raise ConfigRejected("CONFIG_ROOT_TYPE_INVALID")
    if doc.get("os") != "linux" or doc.get("architecture") != "amd64":
        raise ConfigRejected("CONFIG_OS_ARCH_NOT_LINUX_AMD64")

    rootfs = doc.get("rootfs")
    if not isinstance(rootfs, dict) or rootfs.get("type") != "layers":
        raise ConfigRejected("CONFIG_ROOTFS_INVALID")
    diff_ids = rootfs.get("diff_ids")
    if not isinstance(diff_ids, list) or len(diff_ids) != expected_layers or any(
        not isinstance(item, str) or SHA256.fullmatch(item) is None
        for item in diff_ids
    ):
        raise ConfigRejected("CONFIG_DIFF_IDS_NOT_EIGHT_VALID_SHA256")

    config = doc.get("config")
    if not isinstance(config, dict):
        raise ConfigRejected("CONFIG_RUNTIME_SHAPE_INVALID")
    volumes = config.get("Volumes")
    if volumes is None:
        volumes = {}
    if not isinstance(volumes, dict) or any(
        not isinstance(key, str) or not key.startswith("/") or
        not isinstance(value, dict)
        for key, value in volumes.items()
    ):
        raise ConfigRejected("CONFIG_VOLUME_DECLARATION_INVALID")
    entrypoint_class = _declared_argv(config.get("Entrypoint"), "ENTRYPOINT")
    command_class = _declared_argv(config.get("Cmd"), "COMMAND")

    # Intentionally DO NOT inspect, print, transform, or return Env, history,
    # labels, entrypoint/command values, or any arbitrary JSON field.
    return {
        "OS_ARCH": "linux/amd64",
        "ROOTFS_DIFF_ID_COUNT": str(len(diff_ids)),
        "DECLARED_VOLUME_COUNT": str(len(volumes)),
        "MYSQL_DATADIR_VOLUME_DECLARED": (
            "YES" if "/var/lib/mysql" in volumes else "NO"
        ),
        "ENTRYPOINT_CLASS": entrypoint_class,
        "COMMAND_CLASS": command_class,
    }


class BlockRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, fp, code, msg, headers, newurl):
        raise ConfigRejected("HTTP_REDIRECT_BLOCKED")


def _opener():
    return urllib.request.build_opener(
        urllib.request.ProxyHandler({}),
        BlockRedirect,
        urllib.request.HTTPSHandler(context=ssl.create_default_context()),
    )


def _get_exact(opener, url: str, headers: dict[str, str], cap: int):
    request = urllib.request.Request(url, method="GET", headers=headers)
    with opener.open(request, timeout=12) as response:
        if response.status != 200 or response.geturl() != url:
            raise ConfigRejected("UNEXPECTED_RESPONSE_ORIGIN_OR_STATUS")
        encoding = response.headers.get("Content-Encoding", "identity").lower()
        if encoding != "identity":
            raise ConfigRejected("UNEXPECTED_CONTENT_ENCODING")
        raw = response.read(cap + 1)
        if not raw or len(raw) > cap:
            raise ConfigRejected("RESPONSE_SIZE_EXCEEDS_CAP")
        return response.headers, raw


def verify_public_config() -> dict[str, str]:
    """No local file or engine interaction; fixed HTTPS GETs, no redirects."""
    opener = _opener()
    _, token_bytes = _get_exact(opener, TOKEN_URL, {
        "Accept": "application/json",
        "Accept-Encoding": "identity",
        "User-Agent": "Renee-B12-WS52KK-readonly/1",
    }, MAX_TOKEN_BYTES)
    try:
        token_doc = json.loads(
            token_bytes.decode("utf-8"), object_pairs_hook=no_duplicate_keys
        )
        token = token_doc.get("token")
    except (ValueError, UnicodeError, AttributeError) as exc:
        raise ConfigRejected("PUBLIC_TOKEN_INVALID") from exc
    if not isinstance(token, str) or not 10 <= len(token) <= 8192:
        raise ConfigRejected("PUBLIC_PULL_TOKEN_UNAVAILABLE")

    response_headers, raw = _get_exact(opener, BLOB_URL, {
        "Authorization": "Bearer " + token,
        "Accept": "application/octet-stream, application/json",
        "Accept-Encoding": "identity",
        "User-Agent": "Renee-B12-WS52KK-readonly/1",
    }, CONFIG_SIZE)
    observed_header = response_headers.get("Docker-Content-Digest")
    if observed_header is not None and observed_header != CONFIG_DIGEST:
        raise ConfigRejected("REGISTRY_BLOB_DIGEST_HEADER_MISMATCH")
    content_length = response_headers.get("Content-Length")
    if content_length is not None and content_length != str(CONFIG_SIZE):
        raise ConfigRejected("REGISTRY_BLOB_LENGTH_HEADER_MISMATCH")
    return inspect_config_bytes(raw, CONFIG_DIGEST, CONFIG_SIZE, EXPECTED_DIFF_IDS)


def main() -> int:
    result = "BLOCKED"
    reason = "NOT_MACOS"
    facts = {}
    if sys.platform == "darwin":
        try:
            facts = verify_public_config()
            result = "READONLY_CONFIG_BLOB_BYTES_PASS"
            reason = "NONE"
        except ConfigRejected as exc:
            reason = str(exc)  # fixed labels only, never raw network exceptions
        except Exception:
            reason = "REGISTRY_BLOB_NOT_ATTESTED"

    print("#####COPY FROM HERE#####")
    for key, value in (
        ("AUDIT", "B12_WS52KK_READONLY_CONFIG_BLOB"),
        ("RESULT", result),
        ("REASON", reason),
        ("OBSERVED_UTC", datetime.datetime.now(
            datetime.timezone.utc).isoformat(timespec="seconds")),
        ("SOURCE_INDEX_DIGEST", INDEX_DIGEST),
        ("SOURCE_CHILD_DIGEST", CHILD_DIGEST),
        ("EXPECTED_CONFIG_DIGEST", CONFIG_DIGEST),
        ("EXPECTED_CONFIG_SIZE", str(CONFIG_SIZE)),
        ("CONFIG_BYTES_HASH_VERIFIED", "YES" if facts else "NOT_ATTESTED"),
        ("OS_ARCH", facts.get("OS_ARCH", "NOT_ATTESTED")),
        ("ROOTFS_DIFF_ID_COUNT", facts.get("ROOTFS_DIFF_ID_COUNT", "NOT_ATTESTED")),
        ("DECLARED_VOLUME_COUNT", facts.get("DECLARED_VOLUME_COUNT", "NOT_ATTESTED")),
        ("MYSQL_DATADIR_VOLUME_DECLARED", facts.get(
            "MYSQL_DATADIR_VOLUME_DECLARED", "NOT_ATTESTED")),
        ("ENTRYPOINT_CLASS", facts.get("ENTRYPOINT_CLASS", "NOT_ATTESTED")),
        ("COMMAND_CLASS", facts.get("COMMAND_CLASS", "NOT_ATTESTED")),
        ("IMAGE_LAYERS_DOWNLOADED", "NO"),
        ("DOCKER_PULL_OR_OPERATIONS", "NO"),
        ("DOCKER_ENGINE_INSPECTED", "NO"),
        ("DATABASE_CONNECTIONS", "0"),
        ("SQL_EXECUTED", "NO"),
        ("RC01_WORKERS_EXECUTED", "NO"),
        ("HISTORICAL_BACKUP_ACCESSED", "NO"),
        ("RUNTIME_IMAGE_APPROVED", "NO"),
        ("ONE_USE_RUNTIME_APPROVAL", "NOT_GRANTED"),
        ("REPAIRED_RC01_RC14_CERTIFIED_PASSES", "0"),
        ("MIGRATION_092", "NOT_AUTHORIZED"),
        ("B12_CUTOVER", "HARD_OFF"),
    ):
        print(key + "=" + value)
    print("#####COPY TO HERE#####")
    return 0 if facts else 2


if __name__ == "__main__":
    raise SystemExit(main())
