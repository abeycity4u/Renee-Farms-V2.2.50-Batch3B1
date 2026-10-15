"""WS52-KO public read-only, one-redirect OCI config integrity candidate.

Fixed HTTPS public token GET -> fixed registry digest GET -> exactly ONE 307
redirect to one reviewed HTTPS CDN hostname and digest-specific blob path.
No Docker image pull, layer GET, SQL, worker, backup, files, or subprocess.
No raw URL, auth token, signed query, config JSON/Env/history or exceptions printed.
Not a publisher signature or approval to run a MariaDB container.

SOURCE-ONLY BY DEFAULT: main() is Mac-only and makes public requests only
when an operator explicitly invokes the pinned, hash-reviewed Python file.
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

INDEX_DIGEST = "sha256:7db29378d4fdab73f8123bbc2b48905c90d1a4b00cf848b028f1e81e623257f2"
CHILD_DIGEST = "sha256:dc9dd57be7f46ad6f350c598a331d64f295899c4abac3ec5652eff831fc38a6c"
CONFIG_DIGEST = "sha256:41096ae9913468679bb5e05bbc19f44edcaf0eae45d67fcde224ffdeda9d3a1f"
CONFIG_SIZE = 8790
EXPECTED_LAYER_COUNT = 8
CDN_HOST = "production.cloudfront.docker.com"
HASH_HEX = CONFIG_DIGEST.split(":", 1)[1]
# Deliberately strict: if real Docker CDN Location differs, BLOCK and review.
CDN_PATH = "/registry-v2/docker/registry/v2/blobs/sha256/" + HASH_HEX[:2] + "/" + HASH_HEX + "/data"
TOKEN_URL = "https://auth.docker.io/token?" + urllib.parse.urlencode({
    "service": "registry.docker.io",
    "scope": "repository:library/mariadb:pull",
})
REGISTRY_URL = "https://registry-1.docker.io/v2/library/mariadb/blobs/" + CONFIG_DIGEST
TOKEN_LIMIT = 16384
REDIRECT_URL_LIMIT = 8192
SHA256 = re.compile(r"\Asha256:[0-9a-f]{64}\Z")
SAFE_QUERY = re.compile(r"\A[A-Za-z0-9._~%+/:=&-]{1,4096}\Z")
PERCENT = re.compile(r"%(?![0-9a-fA-F]{2})")
ALLOWED_MIME = frozenset(("application/octet-stream", "application/json", "binary/octet-stream"))


class Reject(ValueError):
    """Use only hardcoded public-safe failure labels."""


class RedirectObserved(Exception):
    def __init__(self, status: int, location: object):
        super().__init__("UNFOLLOWED_REDIRECT")
        self.status = status
        self.location = location


class NeverFollow(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, fp, code, msg, headers, newurl):
        # Do NOT create another Request or call opener for the redirect.
        raise RedirectObserved(code, headers.get("Location"))


def opener_no_proxy():
    return urllib.request.build_opener(
        urllib.request.ProxyHandler({}),
        NeverFollow,
        urllib.request.HTTPSHandler(context=ssl.create_default_context()),
    )


def get_once(opener, url: str, headers: dict[str, str], read_cap: int | None):
    """Only GET original URL; no redirects, bounded response if bytes requested."""
    request = urllib.request.Request(url, method="GET", headers=headers)
    try:
        with opener.open(request, timeout=12) as response:
            if response.status != 200 or response.geturl() != url:
                raise Reject("RESPONSE_ORIGIN_OR_STATUS_INVALID")
            if response.headers.get("Content-Encoding", "identity").lower() != "identity":
                raise Reject("UNEXPECTED_CONTENT_ENCODING")
            if read_cap is None:
                return response.headers, None   # Registry: never download config.
            declared = response.headers.get("Content-Length")
            if declared is not None:
                if not declared.isdecimal() or int(declared) > read_cap:
                    raise Reject("DECLARED_RESPONSE_SIZE_EXCEEDS_CAP")
            data = response.read(read_cap + 1)
            if not 0 < len(data) <= read_cap:
                raise Reject("ACTUAL_RESPONSE_SIZE_EXCEEDS_CAP")
            return response.headers, data
    except urllib.error.HTTPError as exc:
        if 300 <= exc.code < 400:
            raise RedirectObserved(
                exc.code, exc.headers.get("Location")
            ) from None
        raise Reject("ORIGIN_HTTP_STATUS_REJECTED") from None


def unique_pairs(pairs):
    obj = {}
    for key, val in pairs:
        if key in obj:
            raise Reject("DUPLICATE_JSON_PROPERTY")
        obj[key] = val
    return obj


def parse_token(raw: bytes) -> str:
    try:
        doc = json.loads(raw.decode("utf-8"), object_pairs_hook=unique_pairs)
        token = doc.get("token")
    except (UnicodeError, ValueError, AttributeError) as exc:
        raise Reject("PUBLIC_PULL_TOKEN_INVALID") from exc
    if not isinstance(token, str) or not 10 <= len(token) <= 8192:
        raise Reject("PUBLIC_PULL_TOKEN_UNAVAILABLE")
    return token


def validate_exact_cdn_location(location: object, status: int) -> str:
    """Return original signed URL IN MEMORY ONLY after exact bound checks."""
    if type(status) is not int or status != 307:
        raise Reject("REGISTRY_REDIRECT_STATUS_NOT_307")
    if not isinstance(location, str) or not 1 <= len(location) <= REDIRECT_URL_LIMIT:
        raise Reject("REGISTRY_LOCATION_LENGTH_INVALID")
    if any(ord(ch) < 33 or ord(ch) > 126 for ch in location):
        raise Reject("REGISTRY_LOCATION_CHARSET_INVALID")
    # Reject relative/scheme-relative URL; no implicit origin change.
    try:
        parsed = urllib.parse.urlsplit(location)
        port = parsed.port
    except ValueError as exc:
        raise Reject("REGISTRY_LOCATION_PARSE_INVALID") from exc
    if (parsed.scheme != "https" or parsed.hostname != CDN_HOST or
            port not in (None, 443) or
            parsed.username is not None or parsed.password is not None or
            parsed.fragment or parsed.path != CDN_PATH):
        raise Reject("REGISTRY_CDN_OR_DIGEST_PATH_NOT_ALLOWLISTED")
    if not parsed.query or SAFE_QUERY.fullmatch(parsed.query) is None:
        raise Reject("REGISTRY_SIGNED_QUERY_INVALID")
    if PERCENT.search(parsed.query):
        raise Reject("REGISTRY_SIGNED_QUERY_ESCAPE_INVALID")
    if parsed.netloc.lower() not in (CDN_HOST, CDN_HOST + ":443"):
        raise Reject("REGISTRY_CDN_AUTHORITY_NOT_CANONICAL")
    return location


def _argv_class(value) -> str:
    if value is None:
        return "NOT_DECLARED"
    if not isinstance(value, list) or any(not isinstance(x, str) for x in value):
        raise Reject("CONFIG_COMMAND_SHAPE_INVALID")
    return "ARRAY_NONEMPTY" if value else "ARRAY_EMPTY"


def inspect_verified_config(raw: bytes) -> dict[str, str]:
    """Hash before JSON parsing; return only classifications, never raw JSON."""
    if not isinstance(raw, bytes) or len(raw) != CONFIG_SIZE:
        raise Reject("CONFIG_EXACT_LENGTH_MISMATCH")
    if "sha256:" + hashlib.sha256(raw).hexdigest() != CONFIG_DIGEST:
        raise Reject("CONFIG_SHA256_MISMATCH")
    try:
        doc = json.loads(raw.decode("utf-8"), object_pairs_hook=unique_pairs)
    except (ValueError, UnicodeError, TypeError) as exc:
        raise Reject("CONFIG_JSON_REJECTED") from exc
    if not isinstance(doc, dict) or doc.get("os") != "linux" or doc.get("architecture") != "amd64":
        raise Reject("CONFIG_PLATFORM_NOT_LINUX_AMD64")
    rootfs = doc.get("rootfs")
    if not isinstance(rootfs, dict) or rootfs.get("type") != "layers":
        raise Reject("CONFIG_ROOTFS_INVALID")
    diff_ids = rootfs.get("diff_ids")
    if not isinstance(diff_ids, list) or len(diff_ids) != EXPECTED_LAYER_COUNT or any(
        not isinstance(s, str) or SHA256.fullmatch(s) is None for s in diff_ids
    ):
        raise Reject("CONFIG_DIFF_ID_DESCRIPTOR_INVALID")
    config = doc.get("config")
    if not isinstance(config, dict):
        raise Reject("CONFIG_RUNTIME_SHAPE_INVALID")
    volumes = config.get("Volumes")
    if volumes is None:
        volumes = {}
    if not isinstance(volumes, dict) or any(
        not isinstance(k, str) or not k.startswith("/") or not isinstance(v, dict)
        for k, v in volumes.items()
    ):
        raise Reject("CONFIG_VOLUMES_INVALID")
    return {
        "OS_ARCH": "linux/amd64",
        "DIFF_ID_COUNT": str(len(diff_ids)),
        "DECLARED_VOLUME_COUNT": str(len(volumes)),
        "MYSQL_DATADIR_VOLUME_DECLARED": "YES" if "/var/lib/mysql" in volumes else "NO",
        "ENTRYPOINT_CLASS": _argv_class(config.get("Entrypoint")),
        "COMMAND_CLASS": _argv_class(config.get("Cmd")),
    }


def retrieve_one_config_readonly() -> dict[str, str]:
    # Two independent handlers. Bearer token NEVER crosses to CDN request.
    auth_opener = opener_no_proxy()
    _, token_raw = get_once(auth_opener, TOKEN_URL, {
        "Accept": "application/json",
        "Accept-Encoding": "identity",
        "User-Agent": "Renee-B12-WS52KO-readonly/1",
    }, TOKEN_LIMIT)
    token = parse_token(token_raw)
    try:
        # MUST receive exactly one 307. Do not read registry blob response.
        get_once(auth_opener, REGISTRY_URL, {
            "Authorization": "Bearer " + token,
            "Accept": "application/octet-stream",
            "Accept-Encoding": "identity",
            "User-Agent": "Renee-B12-WS52KO-readonly/1",
        }, None)
        raise Reject("EXPECTED_SINGLE_REGISTRY_307_ABSENT")
    except RedirectObserved as event:
        signed_url = validate_exact_cdn_location(event.location, event.status)
    # Fresh client, no Authorization. The validated signed URL is never printed.
    cdn_opener = opener_no_proxy()
    try:
        headers, raw = get_once(cdn_opener, signed_url, {
            "Accept": "application/octet-stream",
            "Accept-Encoding": "identity",
            "User-Agent": "Renee-B12-WS52KO-readonly/1",
        }, CONFIG_SIZE)
    except RedirectObserved:
        raise Reject("CDN_SECOND_REDIRECT_REJECTED") from None
    declared = headers.get("Content-Length")
    if declared is not None and declared != str(CONFIG_SIZE):
        raise Reject("CONFIG_RESPONSE_DECLARED_SIZE_NOT_EXACT")
    media = headers.get("Content-Type", "").split(";", 1)[0].strip().lower()
    if media and media not in ALLOWED_MIME:
        raise Reject("CONFIG_RESPONSE_MEDIA_TYPE_UNREVIEWED")
    return inspect_verified_config(raw)


def main() -> int:
    result, reason = "BLOCKED", "NOT_MACOS"
    fields = {}
    if sys.platform == "darwin":
        try:
            fields = retrieve_one_config_readonly()
            result, reason = "READONLY_CONFIG_BLOB_SHA256_PASS", "NONE"
        except Reject as exc:
            reason = str(exc)    # Fixed labels only; never raw URL exceptions.
        except RedirectObserved:
            reason = "PUBLIC_TOKEN_REDIRECT_REJECTED"
        except Exception:
            reason = "NETWORK_OR_SOURCE_NOT_ATTESTED"
    print("#####COPY FROM HERE#####")
    for k, v in (
        ("AUDIT", "B12_WS52KO_SINGLE_ALLOWLISTED_307_CONFIG"),
        ("RESULT", result),
        ("REASON", reason),
        ("OBSERVED_UTC", datetime.datetime.now(
            datetime.timezone.utc).isoformat(timespec="seconds")),
        ("SOURCE_INDEX_DIGEST", INDEX_DIGEST),
        ("SOURCE_CHILD_DIGEST", CHILD_DIGEST),
        ("CONFIG_DIGEST", CONFIG_DIGEST),
        ("EXPECTED_CONFIG_BYTES", str(CONFIG_SIZE)),
        ("CONFIG_BYTES_SHA256_VERIFIED", "YES" if fields else "NOT_ATTESTED"),
        ("OS_ARCH", fields.get("OS_ARCH", "NOT_ATTESTED")),
        ("ROOTFS_DIFF_ID_COUNT", fields.get("DIFF_ID_COUNT", "NOT_ATTESTED")),
        ("DECLARED_VOLUME_COUNT", fields.get("DECLARED_VOLUME_COUNT", "NOT_ATTESTED")),
        ("MYSQL_DATADIR_VOLUME_DECLARED", fields.get(
            "MYSQL_DATADIR_VOLUME_DECLARED", "NOT_ATTESTED")),
        ("ENTRYPOINT_CLASS", fields.get("ENTRYPOINT_CLASS", "NOT_ATTESTED")),
        ("COMMAND_CLASS", fields.get("COMMAND_CLASS", "NOT_ATTESTED")),
        ("CDN_SECOND_REDIRECT_FOLLOWED", "NO"),
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
        print(k + "=" + v)
    print("#####COPY TO HERE#####")
    return 0 if fields else 2


if __name__ == "__main__":
    raise SystemExit(main())
