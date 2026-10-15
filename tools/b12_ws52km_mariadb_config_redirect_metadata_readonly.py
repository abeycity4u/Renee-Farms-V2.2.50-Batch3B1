"""WS52-KM — Mac-only, public read-only OCI config redirect METADATA probe.

It identifies which exact HTTPS GET redirects and prints only safe redirect
metadata. It NEVER follows a redirect or requests its destination, fetches
config/layer bytes, calls Docker/SQL, accesses backups, or writes local files.
This is discovery, NOT runtime-image authorization or B12 certification.
"""
from __future__ import annotations

import datetime
import json
import re
import ssl
import sys
import urllib.error
import urllib.parse
import urllib.request

CONFIG_DIGEST = "sha256:41096ae9913468679bb5e05bbc19f44edcaf0eae45d67fcde224ffdeda9d3a1f"
INDEX_DIGEST = "sha256:7db29378d4fdab73f8123bbc2b48905c90d1a4b00cf848b028f1e81e623257f2"
CHILD_DIGEST = "sha256:dc9dd57be7f46ad6f350c598a331d64f295899c4abac3ec5652eff831fc38a6c"
CONFIG_BYTES_EXPECTED = 8790
TOKEN_URL = "https://auth.docker.io/token?" + urllib.parse.urlencode({
    "service": "registry.docker.io",
    "scope": "repository:library/mariadb:pull",
})
BLOB_URL = "https://registry-1.docker.io/v2/library/mariadb/blobs/" + CONFIG_DIGEST
TOKEN_LIMIT = 16384
LOCATION_LIMIT = 8192
HOST_RE = re.compile(r"\A[a-z0-9.-]{1,253}\Z")
LABEL_RE = re.compile(r"\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\Z")


class ProbeRejected(ValueError):
    pass


class RedirectSeen(Exception):
    def __init__(self, code: int, location: object):
        super().__init__("REDIRECT_NOT_FOLLOWED")
        self.code = code
        self.location = location


class NeverFollow(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, fp, code, msg, headers, newurl):
        # Never even create a request for the Location destination.
        raise RedirectSeen(code, headers.get("Location"))


def _opener():
    return urllib.request.build_opener(
        urllib.request.ProxyHandler({}),
        NeverFollow,
        urllib.request.HTTPSHandler(context=ssl.create_default_context()),
    )


def _get_original_only(opener, url: str, bearer: str | None = None,
                       *, token_body: bool):
    headers = {
        "Accept": "application/json" if token_body else "application/octet-stream",
        "Accept-Encoding": "identity",
        "User-Agent": "Renee-B12-WS52KM-redirect-metadata/1",
    }
    if bearer is not None:
        headers["Authorization"] = "Bearer " + bearer
    try:
        request = urllib.request.Request(url, headers=headers, method="GET")
        with opener.open(request, timeout=12) as response:
            if response.status != 200 or response.geturl() != url:
                raise ProbeRejected("ORIGIN_OR_STATUS_MISMATCH")
            if response.headers.get("Content-Encoding", "identity").lower() != "identity":
                raise ProbeRejected("CONTENT_ENCODING_NOT_IDENTITY")
            if not token_body:
                # Close immediately on 200. Do NOT read config bytes.
                return None
            declared = response.headers.get("Content-Length")
            if declared is not None and (
                not declared.isdecimal() or int(declared) > TOKEN_LIMIT
            ):
                raise ProbeRejected("PUBLIC_TOKEN_BODY_LIMIT")
            body = response.read(TOKEN_LIMIT + 1)
            if not 0 < len(body) <= TOKEN_LIMIT:
                raise ProbeRejected("PUBLIC_TOKEN_BODY_LIMIT")
            return body
    except urllib.error.HTTPError as exc:
        # Includes 3xx that urllib declined to follow BEFORE redirect_request.
        if 300 <= exc.code <= 399:
            raise RedirectSeen(
                exc.code, exc.headers.get("Location") or exc.headers.get("URI")
            ) from None
        raise ProbeRejected("ORIGIN_HTTP_ERROR") from None


def _unique_json_pairs(pairs):
    obj = {}
    for name, value in pairs:
        if name in obj:
            raise ProbeRejected("DUPLICATE_TOKEN_JSON_KEY")
        obj[name] = value
    return obj


def _parse_public_token(body: bytes) -> str:
    try:
        document = json.loads(
            body.decode("utf-8"), object_pairs_hook=_unique_json_pairs
        )
        token = document.get("token")
    except (UnicodeError, ValueError, AttributeError) as exc:
        raise ProbeRejected("PUBLIC_TOKEN_PARSE_INVALID") from exc
    if not isinstance(token, str) or not 10 <= len(token) <= 8192:
        raise ProbeRejected("PUBLIC_TOKEN_UNAVAILABLE")
    return token


def classify_redirect(origin: str, status: int, location: object) -> dict[str, str]:
    """Pure function: return status + sanitized hostname, NEVER raw Location."""
    fixed = {
        "HTTP_STATUS": str(status) if type(status) is int and 300 <= status <= 399 else "INVALID",
        "DESTINATION_SCHEME": "NOT_ATTESTED",
        "DESTINATION_HOST": "NOT_ATTESTED",
        "DESTINATION_CLASS": "INVALID_LOCATION",
    }
    if fixed["HTTP_STATUS"] == "INVALID":
        return fixed
    if not isinstance(location, str) or not 0 < len(location) <= LOCATION_LIMIT:
        return fixed
    if any(ord(char) <= 32 or ord(char) == 127 for char in location):
        return fixed
    try:
        absolute = urllib.parse.urljoin(origin, location)
        target = urllib.parse.urlsplit(absolute)
        hostname = target.hostname
        port = target.port
    except (ValueError, TypeError):
        return fixed
    if target.scheme not in ("http", "https"):
        fixed["DESTINATION_CLASS"] = "UNSUPPORTED_SCHEME"
        return fixed
    fixed["DESTINATION_SCHEME"] = target.scheme
    if target.username is not None or target.password is not None:
        fixed["DESTINATION_CLASS"] = "URL_USERINFO_REJECTED"
        return fixed
    if target.scheme != "https":
        fixed["DESTINATION_CLASS"] = "NON_HTTPS"
        return fixed
    if port not in (None, 443) or not isinstance(hostname, str):
        fixed["DESTINATION_CLASS"] = "UNAPPROVED_PORT_OR_HOST"
        return fixed
    try:
        hostname.encode("ascii")
    except UnicodeError:
        fixed["DESTINATION_CLASS"] = "NON_ASCII_HOST_REJECTED"
        return fixed
    hostname = hostname.lower()
    if (not HOST_RE.fullmatch(hostname)
            or any(LABEL_RE.fullmatch(label) is None for label in hostname.split("."))):
        fixed["DESTINATION_CLASS"] = "INVALID_HOST_SYNTAX"
        return fixed
    # IPv4 literal and localhost are not recognized public hostnames here.
    if hostname == "localhost" or re.fullmatch(r"[0-9.]+", hostname):
        fixed["DESTINATION_CLASS"] = "IP_OR_LOCAL_HOST_REDACTED"
        return fixed
    fixed["DESTINATION_HOST"] = hostname
    expected = urllib.parse.urlsplit(origin).hostname
    if hostname == expected:
        fixed["DESTINATION_CLASS"] = "SAME_ORIGIN_NO_FOLLOW"
    elif hostname in ("auth.docker.io", "registry-1.docker.io"):
        fixed["DESTINATION_CLASS"] = "OTHER_REVIEWED_ORIGIN_NO_FOLLOW"
    else:
        fixed["DESTINATION_CLASS"] = "EXTERNAL_UNREVIEWED_NO_FOLLOW"
    return fixed


def probe_readonly() -> dict[str, str]:
    opener = _opener()
    stage = "PUBLIC_TOKEN_GET"
    try:
        token_body = _get_original_only(opener, TOKEN_URL, token_body=True)
        token = _parse_public_token(token_body)
        stage = "PINNED_CONFIG_BLOB_GET"
        _get_original_only(opener, BLOB_URL, bearer=token, token_body=False)
    except RedirectSeen as redir:
        return {
            "RESULT": "READONLY_REDIRECT_METADATA_OBSERVED",
            "REASON": "REDIRECT_NOT_FOLLOWED",
            "REDIRECT_STAGE": stage,
            **classify_redirect(TOKEN_URL if stage == "PUBLIC_TOKEN_GET" else BLOB_URL,
                                redir.code, redir.location),
        }
    return {
        "RESULT": "NO_REDIRECT_OBSERVED",
        "REASON": "NONE",
        "REDIRECT_STAGE": "NONE",
        "HTTP_STATUS": "NONE",
        "DESTINATION_SCHEME": "NONE",
        "DESTINATION_HOST": "NONE",
        "DESTINATION_CLASS": "NONE",
    }


def main() -> int:
    details = {
        "RESULT": "BLOCKED",
        "REASON": "NOT_MACOS",
        "REDIRECT_STAGE": "NOT_ATTESTED",
        "HTTP_STATUS": "NOT_ATTESTED",
        "DESTINATION_SCHEME": "NOT_ATTESTED",
        "DESTINATION_HOST": "NOT_ATTESTED",
        "DESTINATION_CLASS": "NOT_ATTESTED",
    }
    if sys.platform == "darwin":
        try:
            details = probe_readonly()
        except ProbeRejected:
            details["REASON"] = "TOKEN_OR_REGISTRY_PREFLIGHT_REJECTED"
        except Exception:
            # No exceptions containing URLs, bearer strings or signed query.
            details["REASON"] = "NETWORK_OR_REDIRECT_METADATA_NOT_ATTESTED"
    print("#####COPY FROM HERE#####")
    for name, value in (
        ("AUDIT", "B12_WS52KM_READONLY_REDIRECT_DISCOVERY"),
        ("RESULT", details["RESULT"]),
        ("REASON", details["REASON"]),
        ("OBSERVED_UTC", datetime.datetime.now(
            datetime.timezone.utc).isoformat(timespec="seconds")),
        ("REDIRECT_STAGE", details["REDIRECT_STAGE"]),
        ("REDIRECT_HTTP_STATUS", details["HTTP_STATUS"]),
        ("REDIRECT_DESTINATION_SCHEME", details["DESTINATION_SCHEME"]),
        ("REDIRECT_DESTINATION_HOST", details["DESTINATION_HOST"]),
        ("REDIRECT_DESTINATION_CLASS", details["DESTINATION_CLASS"]),
        ("SOURCE_INDEX_DIGEST", INDEX_DIGEST),
        ("SOURCE_CHILD_DIGEST", CHILD_DIGEST),
        ("EXPECTED_CONFIG_DIGEST", CONFIG_DIGEST),
        ("EXPECTED_CONFIG_SIZE", str(CONFIG_BYTES_EXPECTED)),
        ("CONFIG_BYTES_FETCHED", "NO"),
        ("CONFIG_BYTES_HASH_VERIFIED", "NOT_ATTESTED"),
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
        print(name + "=" + value)
    print("#####COPY TO HERE#####")
    return 0 if details["RESULT"] != "BLOCKED" else 2


if __name__ == "__main__":
    raise SystemExit(main())
