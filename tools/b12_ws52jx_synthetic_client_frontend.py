#!/usr/bin/env python3
"""WS52-JX: fabricated-input-only MariaDB client-script feasibility probe.

NOT a MariaDB client interpreter, complete SQL parser or real-backup validator.
No file / stdin SQL input, Docker, network, MariaDB or import function.
Unknown syntax/client behaviors fail closed; no partial statement execution.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import re
from dataclasses import dataclass

from b12_ws52f_synthetic_allowlist import classify_synthetic, Rejected as PolicyRejected

MAX_SCRIPT_BYTES = 32768
MAX_STATEMENTS = 64
MAX_COMMENTS = 128
MARIADB_TEST_VERSION = 110400  # selected synthetic MariaDB 11.4 reference only
SANDBOX = r"/*M!999999\- enable the sandbox mode */"
GUARD = re.compile(r"^(\d{5,6})\s+(.+)$", re.DOTALL | re.ASCII)
ERROR = "UNSUPPORTED_OR_UNSAFE_SYNTHETIC_SCRIPT"


class Blocked(ValueError):
    """Reject only with constant message: never leak input or identifiers."""


def deny() -> None:
    raise Blocked(ERROR)


@dataclass(frozen=True)
class Summary:
    classes: tuple[str, ...]
    active_guards: int
    skipped_guards: int
    sandbox_directive: bool
    regular_comments: int
    bytes_accounted: int
    # Digest is based on redacted categories, not private SQL contents.
    policy_digest: str


def _active(kind: str, version: int) -> bool:
    if kind == "MYSQL" and 50700 <= version < 100000:
        # MariaDB ignores MySQL 5.7+ versioned comments in this band.
        return False
    return version <= MARIADB_TEST_VERSION


def classify_fabricated_script(script: str) -> Summary:
    """Pure synthetic-only preflight; full string checked before return.

    This intentionally rejects most valid client grammar. The old frozen
    WS52-JT classifier owns the finite SQL statement allowlist.
    """
    if not isinstance(script, str) or not script.isascii():
        deny()
    if not (0 < len(script) <= MAX_SCRIPT_BYTES):
        deny()
    if any(ord(c) < 32 and c not in "\n\t" for c in script):
        deny()

    n = len(script)
    i = 0
    buf: list[str] = []
    classes: list[str] = []
    regular = active = skipped = 0
    sandbox = False
    only_skipped_in_statement = False
    saw_nonspace = False

    while i < n:
        c = script[i]

        # Permit exactly the known first-of-script sandbox header, but this
        # never asserts actual MariaDB client sandbox enforcement.
        if script.startswith(SANDBOX, i):
            if sandbox or saw_nonspace or "".join(buf).strip():
                deny()
            sandbox = True
            saw_nonspace = True
            i += len(SANDBOX)
            buf.append(" ")
            continue

        if c == "#" or (script.startswith("--", i) and (
            i + 2 == n or script[i + 2] in " \t\n"
        )):
            # Normal comments are never executable. Other dash syntax
            # is sent to the restricted SQL policy which rejects it.
            end = script.find("\n", i)
            regular += 1
            if regular + active + skipped > MAX_COMMENTS:
                deny()
            i = n if end < 0 else end
            buf.append(" ")
            continue

        if script.startswith("/*", i):
            end = script.find("*/", i + 2)
            if end < 0 or "/*" in script[i + 2:end]:
                deny()
            raw_body = script[i + 2:end]
            i = end + 2
            if raw_body.startswith(("!", "M!")):
                is_maria = raw_body.startswith("M!")
                inner = raw_body[2:] if is_maria else raw_body[1:]
                m = GUARD.fullmatch(inner)
                if m is None:
                    deny()
                version, body = int(m.group(1)), m.group(2)
                if version <= 0 or "/*" in body or "*/" in body or ";" in body:
                    deny()
                if "".join(buf).strip() or only_skipped_in_statement:
                    # No inline executable SQL concatenation; narrower than
                    # actual MariaDB, which is deliberate and fail-closed.
                    deny()
                if _active("MARIA" if is_maria else "MYSQL", version):
                    active += 1
                    buf.append(" ")
                    buf.append(body)
                    buf.append(" ")
                    saw_nonspace = True
                else:
                    skipped += 1
                    only_skipped_in_statement = True
                    buf.append(" ")
            else:
                regular += 1
                buf.append(" ")
            if regular + active + skipped > MAX_COMMENTS:
                deny()
            continue

        # Strings/backticks must be retained exactly; don't interpret
        # semicolons, comment syntax or client commands inside literals.
        if c in ("'", "`"):
            if only_skipped_in_statement:
                deny()
            quote = c
            start = i
            i += 1
            closed = False
            while i < n:
                q = script[i]
                if q == quote:
                    if i + 1 < n and script[i + 1] == quote:
                        i += 2
                        continue
                    i += 1
                    closed = True
                    break
                if q == "\\":
                    # Backticks never support backslash escape in this
                    # prototype; for single quotes default escaping only.
                    if quote == "`" or i + 1 >= n:
                        deny()
                    i += 2
                else:
                    i += 1
            if not closed:
                deny()
            buf.append(script[start:i])
            saw_nonspace = True
            continue

        if c == ";":
            effective = "".join(buf).strip()
            if effective:
                if only_skipped_in_statement:
                    deny()
                try:
                    result = classify_synthetic(effective + ";")
                except (PolicyRejected, ValueError, TypeError):
                    deny()
                if len(result) != 1:
                    deny()
                classes.append(result[0])
                if len(classes) > MAX_STATEMENTS:
                    deny()
            elif not only_skipped_in_statement:
                # Reject unexplained empty statements.
                deny()
            buf.clear()
            only_skipped_in_statement = False
            i += 1
            continue

        if c == "\\" or c == '"':
            # Any bare client escape or unmodelled ANSI/double-quote mode.
            deny()

        if only_skipped_in_statement and not c.isspace():
            deny()
        buf.append(c)
        if not c.isspace():
            saw_nonspace = True
        i += 1

    if "".join(buf).strip() or only_skipped_in_statement or not classes:
        deny()  # no silent trailing content and no empty/ignored-only input

    # Guard exact original script length; all bytes were consumed in the
    # single monotonic cursor pass (including discarded comments).
    categorical = {
        "classes": classes, "active": active, "skipped": skipped,
        "sandbox_seen": sandbox, "comments": regular,
        "total_bytes_accounted": n,
    }
    fingerprint = hashlib.sha256(json.dumps(
        categorical, sort_keys=True, separators=(",", ":")
    ).encode("ascii")).hexdigest()
    return Summary(tuple(classes), active, skipped, sandbox, regular, n, fingerprint)


def main() -> int:
    p = argparse.ArgumentParser(description="WS52-JX fabricated-only preflight")
    p.add_argument("--self-test", action="store_true", required=True)
    p.parse_args()  # no file path or SQL text argument
    good = classify_fabricated_script("CREATE TABLE `demo` (`id` INT);")
    try:
        classify_fabricated_script("source /tmp/private.sql;")
    except Blocked:
        bad = True
    else:
        bad = False
    if good.classes != ("CREATE_TABLE",) or not bad:
        raise SystemExit(1)
    print("AUDIT=B12_WS52JX_SYNTHETIC_CLIENT_FEASIBILITY_SMOKE")
    print("RESULT=PASS")
    print("SOURCE=HARDCODED_SYNTHETIC_ONLY")
    print("REAL_BACKUP_READ=NO")
    print("SQL_EXECUTED=NO")
    print("MARIADB_CLIENT_GRAMMAR_CERTIFIED=NO")
    print("B12_CUTOVER=OFF")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
