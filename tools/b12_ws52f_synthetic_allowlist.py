#!/usr/bin/env python3
"""WS52-F: SYNTHETIC-ONLY SQL allowlist prototype.

No file/stdin input, SQL client, network access, Docker operations, or import.
Subset != MariaDB grammar, effective backup manifest, or execution approval.
"""
from __future__ import annotations

import argparse
import re
from dataclasses import dataclass

MAX_BYTES = 8192
MAX_STATEMENTS = 128
MAX_TOKENS = 512
SAFE_NAME = re.compile(r"[A-Za-z_][A-Za-z0-9_]*\Z", re.ASCII)
SANDBOX = r"/*M!999999\- enable the sandbox mode */"


class Rejected(ValueError):
    """Fail-closed synthetic SQL classification. Never include input text."""


@dataclass(frozen=True)
class Token:
    kind: str
    value: str


def _reject() -> None:
    raise Rejected("UNSUPPORTED_OR_UNSAFE_SYNTHETIC_SQL")


def _lex(s: str, *, comments: bool = True) -> tuple[list[Token], bool]:
    tokens: list[Token] = []
    i, n = 0, len(s)
    sandbox = False
    while i < n:
        c = s[i]
        if c in " \r\n\t":
            i += 1
            continue
        if c == "#" or (s.startswith("--", i) and (i + 2 == n or s[i + 2].isspace())):
            if not comments:
                _reject()
            j = s.find("\n", i)
            i = n if j == -1 else j + 1
            continue
        if s.startswith("/*", i):
            if not comments:
                _reject()
            j = s.find("*/", i + 2)
            if j == -1:
                _reject()
            part = s[i:j + 2]
            if part == SANDBOX:
                if sandbox or tokens:
                    _reject()
                sandbox = True
            elif s.startswith(("/*!", "/*M!"), i):
                prefix = 4 if s.startswith("/*M!", i) else 3
                m = re.fullmatch(r"([0-9]{5,6})\s+(.+)", part[prefix:-2], re.DOTALL)
                if not m:
                    _reject()
                version, body = int(m.group(1)), m.group(2)
                # Conservative: do not infer MariaDB's special MySQL
                # 5.7+ comment rules or silently drop future SQL.
                if version > 110400 or (
                    prefix == 3 and version >= 50700
                ):
                    _reject()
                nested, nested_sandbox = _lex(body, comments=False)
                if nested_sandbox or not nested or any(
                    t.value == ";" for t in nested
                ):
                    _reject()
                tokens.extend(nested)
            elif "/*" in part[2:-2]:
                _reject()
            i = j + 2
            continue
        if c in ("'", "`"):
            quote = c
            i += 1
            content = []
            closed = False
            while i < n:
                d = s[i]
                if d == quote:
                    if i + 1 < n and s[i + 1] == quote:
                        content.append(quote)
                        i += 2
                    else:
                        closed = True
                        i += 1
                        break
                elif d == "\\":
                    if quote == "`" or i + 1 >= n:
                        _reject()
                    # Support only a bounded known default-SQL_MODE
                    # escape shape; reject unknown escape sequences.
                    escaped = s[i + 1]
                    if escaped not in ("\\", "'", '"', "n", "r", "t", "0"):
                        _reject()
                    content.append({"n": "\n", "r": "\r", "t": "\t", "0": "\0"}.get(escaped, escaped))
                    i += 2
                else:
                    content.append(d)
                    i += 1
            if not closed:
                _reject()
            value = "".join(content)
            tokens.append(Token("IDENT" if quote == "`" else "STRING", value))
            continue
        if c.isascii() and (c.isalpha() or c == "_"):
            j = i + 1
            while j < n and s[j].isascii() and (s[j].isalnum() or s[j] == "_"):
                j += 1
            tokens.append(Token("WORD", s[i:j].upper()))
            i = j
            continue
        if c.isascii() and c.isdigit():
            j = i + 1
            while j < n and s[j].isascii() and s[j].isdigit():
                j += 1
            tokens.append(Token("INTEGER", s[i:j]))
            i = j
            continue
        if c in "(),=;.":
            tokens.append(Token("SYMBOL", c))
            i += 1
            continue
        _reject()
        # Bound complexity before any untrusted text could be used.
        if len(tokens) > MAX_TOKENS:
            _reject()
    if len(tokens) > MAX_TOKENS:
        _reject()
    return tokens, sandbox


class Cursor:
    def __init__(self, tokens: list[Token]):
        self.tokens, self.i = tokens, 0

    def word(self, value: str) -> bool:
        if self.i < len(self.tokens) and self.tokens[self.i] == Token("WORD", value):
            self.i += 1
            return True
        return False

    def expect(self, kind: str, value: str | None = None) -> Token:
        if self.i >= len(self.tokens):
            _reject()
        t = self.tokens[self.i]
        if t.kind != kind or (value is not None and t.value != value):
            _reject()
        self.i += 1
        return t

    def ident(self) -> None:
        if self.i >= len(self.tokens):
            _reject()
        t = self.tokens[self.i]
        # Fail-closed: table/column identifiers must be backtick-quoted.
        if t.kind != "IDENT" or not SAFE_NAME.fullmatch(t.value):
            _reject()
        self.i += 1

    def value(self) -> None:
        if self.i >= len(self.tokens):
            _reject()
        t = self.tokens[self.i]
        if t.kind in ("INTEGER", "STRING") or t == Token("WORD", "NULL"):
            self.i += 1
        else:
            _reject()

    def end(self) -> None:
        if self.i != len(self.tokens):
            _reject()


def _classify_stmt(tokens: list[Token]) -> str:
    cur = Cursor(tokens)
    if cur.word("DROP"):
        cur.expect("WORD", "TABLE")
        if cur.word("IF"):
            cur.expect("WORD", "EXISTS")
        cur.ident()
        result = "DROP_TABLE"
    elif cur.word("CREATE"):
        cur.expect("WORD", "TABLE")
        cur.ident()
        cur.expect("SYMBOL", "(")
        while True:
            cur.ident()
            if cur.word("INT"):
                pass
            elif cur.word("VARCHAR"):
                cur.expect("SYMBOL", "(")
                size = cur.expect("INTEGER")
                if not 1 <= int(size.value) <= 65535:
                    _reject()
                cur.expect("SYMBOL", ")")
            else:
                _reject()
            if cur.i < len(tokens) and tokens[cur.i] == Token("SYMBOL", ","):
                cur.i += 1
                continue
            break
        cur.expect("SYMBOL", ")")
        result = "CREATE_TABLE"
    elif cur.word("INSERT"):
        cur.expect("WORD", "INTO")
        cur.ident()
        cur.expect("WORD", "VALUES")
        cur.expect("SYMBOL", "(")
        cur.value()
        while cur.i < len(tokens) and tokens[cur.i] == Token("SYMBOL", ","):
            cur.i += 1
            cur.value()
        cur.expect("SYMBOL", ")")
        result = "INSERT_VALUES"
    elif cur.word("ALTER"):
        cur.expect("WORD", "TABLE")
        cur.ident()
        if not (cur.word("ENABLE") or cur.word("DISABLE")):
            _reject()
        cur.expect("WORD", "KEYS")
        result = "ALTER_KEYS"
    elif cur.word("LOCK"):
        cur.expect("WORD", "TABLES")
        cur.ident()
        if not (cur.word("READ") or cur.word("WRITE")):
            _reject()
        result = "LOCK_TABLES"
    elif cur.word("UNLOCK"):
        cur.expect("WORD", "TABLES")
        result = "UNLOCK_TABLES"
    elif cur.word("SET"):
        cur.expect("WORD", "SESSION")
        cur.expect("WORD", "SQL_MODE")
        cur.expect("SYMBOL", "=")
        mode = cur.expect("STRING").value
        if mode not in ("", "STRICT_ALL_TABLES"):
            _reject()
        result = "SET_SQL_MODE"
    else:
        _reject()
    cur.end()
    return result


def classify_synthetic(sql: str) -> tuple[str, ...]:
    """Allowlisted categories only; rejects everything else. No I/O."""
    if not isinstance(sql, str) or not sql.isascii() or (
        len(sql.encode("ascii")) > MAX_BYTES
    ):
        _reject()
    if any(ord(ch) < 32 and ch not in "\r\n\t" for ch in sql):
        _reject()
    tokens, sandbox = _lex(sql)
    statements: list[list[Token]] = []
    current: list[Token] = []
    for token in tokens:
        if token == Token("SYMBOL", ";"):
            if not current:
                _reject()
            statements.append(current)
            current = []
        else:
            current.append(token)
        if len(statements) > MAX_STATEMENTS:
            _reject()
    if current:
        _reject()  # explicit semicolon required
    if not statements:
        _reject()
    if sandbox and sql[:sql.find(SANDBOX)].strip():
        # Sandbox directive is allowed only as the very first content.
        _reject()
    return tuple(_classify_stmt(stmt) for stmt in statements)


def main() -> int:
    p = argparse.ArgumentParser(description="WS52-F: synthetic-only subset validator")
    p.add_argument("--self-test", action="store_true", required=True)
    a = p.parse_args()
    assert a.self_test
    if classify_synthetic("CREATE TABLE `demo` (`id` INT);") != ("CREATE_TABLE",):
        raise SystemExit(1)
    try:
        classify_synthetic("CREATE TABLE SELECT (`id` INT);")
    except Rejected:
        pass
    else:
        raise SystemExit(1)
    print("AUDIT=B12_WS52F_SYNTHETIC_SMOKE")
    print("RESULT=PASS")
    print("REAL_BACKUP_ACCESS=NO")
    print("SQL_EXECUTED=NO")
    print("MARIADB_GRAMMAR_CERTIFIED=NO")
    print("B12_CUTOVER=OFF")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
