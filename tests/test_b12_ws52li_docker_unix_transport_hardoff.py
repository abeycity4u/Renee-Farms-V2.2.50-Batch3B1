"""WS52-LI offline tests of real Unix HTTP reader code, no live socket.

The sole socket tests inject a dummy object; inspect_once is permanently OFF.
"""
import ast
from pathlib import Path
import sys
import unittest
from unittest import mock

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "tools"))

import b12_ws52kz_broker_projection_source_contract as kz
import b12_ws52li_docker_unix_transport_hardoff as li

CID = "a" * 64
VOL = "b12-rc01-vol-r321-" + "b" * 16


def metadata(index=0):
    ctx = {"container_id": CID, "volume_name": VOL}
    method, path = kz._routes(CID, VOL)[index]
    return ctx, {"method": method, "path": path}


def response(body=b'{"Version":"26.1.0","ApiVersion":"1.45"}',
             *, status=b"HTTP/1.1 200 OK", headers=(), length=None):
    if length is None:
        length = len(body)
    lines = (
        status, b"Content-Type: application/json",
        b"Content-Length: " + str(length).encode("ascii"),
        b"Connection: close",
    )
    return b"\r\n".join(lines + tuple(headers)) + b"\r\n\r\n" + body


class FakeSocket:
    def __init__(self, chunks, *, fail_connect=False):
        self.chunks = list(chunks)
        self.fail_connect = fail_connect
        self.path = None
        self.request = None
        self.timeout = None
        self.closed = False

    def __enter__(self):
        return self

    def __exit__(self, exc_type, exc, tb):
        self.closed = True

    def settimeout(self, value):
        self.timeout = value

    def connect(self, path):
        if self.fail_connect:
            raise OSError("DO_NOT_EXPOSE_THIS_ERROR")
        self.path = path

    def sendall(self, content):
        self.request = content

    def recv(self, maxbytes):
        if not self.chunks:
            return b""
        value = self.chunks.pop(0)
        if len(value) > maxbytes:
            self.chunks.insert(0, value[maxbytes:])
            return value[:maxbytes]
        return value


class UnixTransportHardoffTests(unittest.TestCase):
    def test_all_six_allowlisted_request_builds_are_fixed_get_only(self):
        for route in range(6):
            with self.subTest(route=route):
                ctx, req = metadata(route)
                raw = li._request_for_path(ctx, req)
                self.assertTrue(raw.startswith(
                    ("GET " + req["path"] + " HTTP/1.1\r\n").encode()
                ))
                self.assertTrue(raw.endswith(b"Connection: close\r\n\r\n"))
                self.assertEqual(raw.count(b"GET "), 1)
                self.assertNotIn(b"Authorization:", raw)
                self.assertNotIn(b"POST", raw)

    def test_builder_blocks_mutating_methods(self):
        for verb in ("POST", "DELETE", "PUT", "PATCH", "HEAD", None):
            c, req = metadata(3)
            req["method"] = verb
            with self.assertRaises(ValueError):
                li._request_for_path(c, req)

    def test_builder_blocks_unreviewed_docker_endpoints(self):
        for path in ("/containers/prune", "/containers/create",
                     "/containers/json?all=1", "/images/create",
                     "/exec/123/start", "/containers/abc/logs"):
            c, req = metadata(3)
            req["path"] = path
            with self.assertRaises(ValueError):
                li._request_for_path(c, req)

    def test_builder_blocks_traversal_aliases_and_newlines(self):
        for path in ("/containers/../volumes", "/containers/%2e%2e/json",
                     "/volumes//secret", "/version\r\nX-Injected: yes",
                     "//info", "http://host/version", "/Version"):
            c, req = metadata()
            req["path"] = path
            with self.assertRaises(ValueError):
                li._request_for_path(c, req)

    def test_builder_blocks_wrong_type_context_or_missing_identity(self):
        for context in (None, [], "ctx", {}, {"container_id": CID}):
            _, req = metadata()
            with self.assertRaises(ValueError):
                li._request_for_path(context, req)
        c, _ = metadata()
        with self.assertRaises(ValueError):
            li._request_for_path(c, None)

    def test_wire_parser_accepts_exact_minimal_json_response(self):
        wire = li._parse_http_wire(response())
        self.assertEqual(wire.status, 200)
        self.assertEqual(wire.content_type, "application/json")
        self.assertEqual(wire.encoding, "identity")
        self.assertEqual(wire.body, b'{"Version":"26.1.0","ApiVersion":"1.45"}')

    def test_wire_parser_accepts_case_insensitive_reviewed_header_names(self):
        wire = li._parse_http_wire(response(headers=(b"Date: Thu, 01 Jan 1970 00:00:00 GMT",)))
        self.assertEqual(wire.status, 200)

    def test_wire_parser_rejects_empty_oversized_or_nonbytes(self):
        for raw in (b"", None, "", {}, b"x" * (li.MAX_WIRE_BYTES + 1)):
            with self.assertRaises(ValueError):
                li._parse_http_wire(raw)

    def test_wire_parser_rejects_wrong_status_redirect_and_http_version(self):
        for status in (b"HTTP/1.1 204 No Content", b"HTTP/1.1 301 Moved Permanently",
                       b"HTTP/1.1 401 Unauthorized", b"HTTP/2 200 OK",
                       b"HTTP/1.0 200 OK"):
            with self.assertRaises(ValueError):
                li._parse_http_wire(response(status=status))

    def test_wire_parser_rejects_missing_boundary_and_lone_lf(self):
        for wire in (
            response().replace(b"\r\n\r\n", b"\n\n"),
            response().replace(b"\r\n", b"\n"),
            b"HTTP/1.1 200 OK\r\nContent-Type: application/json",
        ):
            with self.assertRaises(ValueError):
                li._parse_http_wire(wire)

    def test_wire_parser_rejects_header_folding(self):
        wire = response(headers=(b" X-Continuation: forbidden",))
        with self.assertRaises(ValueError):
            li._parse_http_wire(wire)

    def test_wire_parser_rejects_duplicate_headers(self):
        for duplicate in (b"content-length: 39", b"CONTENT-TYPE: application/json",
                          b"Connection: close"):
            with self.assertRaises(ValueError):
                li._parse_http_wire(response(headers=(duplicate,)))

    def test_wire_parser_rejects_transfer_encoding_and_redirect_location(self):
        for header in (b"Transfer-Encoding: chunked", b"Location: /containers",
                       b"Set-Cookie: token=private"):
            with self.assertRaises(ValueError):
                li._parse_http_wire(response(headers=(header,)))

    def test_wire_parser_rejects_gzip_or_unapproved_media_type(self):
        for header in (b"Content-Encoding: gzip", b"Content-Type: text/plain"):
            with self.assertRaises(ValueError):
                li._parse_http_wire(response(headers=(header,)))

    def test_wire_parser_rejects_nonclose_connection(self):
        wire = response().replace(b"Connection: close", b"Connection: keep-alive")
        with self.assertRaises(ValueError):
            li._parse_http_wire(wire)

    def test_wire_parser_rejects_broken_content_lengths(self):
        for length in (0, 1, 50000):
            with self.assertRaises(ValueError):
                li._parse_http_wire(response(length=length))

    def test_wire_parser_rejects_leading_zero_content_length(self):
        data = response().replace(b"Content-Length: 39", b"Content-Length: 039")
        # The fixture body has 39 bytes; replace using its actual number.
        raw = response()
        old = b"Content-Length: " + str(len(li._parse_http_wire(raw).body)).encode()
        new = b"Content-Length: 0" + str(len(li._parse_http_wire(raw).body)).encode()
        with self.assertRaises(ValueError):
            li._parse_http_wire(raw.replace(old, new))

    def test_wire_parser_rejects_embedded_control_in_header(self):
        with self.assertRaises(ValueError):
            li._parse_http_wire(response(headers=(b"Server: okay\x00secret",)))

    def test_wire_parser_rejects_nonascii_header(self):
        with self.assertRaises(ValueError):
            li._parse_http_wire(response(headers=(b"Server: \xff",)))

    def test_wire_parser_rejects_long_header_block(self):
        lines = tuple(b"Server: " + b"a" * 200 for _ in range(45))
        with self.assertRaises(ValueError):
            li._parse_http_wire(response(headers=lines))

    def test_wire_parser_rejects_oversized_body_even_if_self_consistent(self):
        body = b"x" * (li.MAX_RAW_BYTES + 1)
        with self.assertRaises(ValueError):
            li._parse_http_wire(response(body))

    def test_bounded_socket_reader_fake_receives_exact_get_and_no_real_socket(self):
        ctx, req = metadata(0)
        request_bytes = li._request_for_path(ctx, req)
        raw = response()
        fake = FakeSocket((raw[:7], raw[7:]))
        with mock.patch.object(li.socket, "socket", return_value=fake):
            received = li._bounded_socket_get(ctx, req)
        self.assertEqual(received, raw)
        self.assertEqual(fake.path, li.SOCKET_PATH)
        self.assertEqual(fake.request, request_bytes)
        self.assertEqual(fake.timeout, li.SOCKET_TIMEOUT_SECONDS)
        self.assertTrue(fake.closed)

    def test_bounded_socket_reader_rejects_overlong_header_before_eof(self):
        ctx, req = metadata(0)
        fake = FakeSocket((b"A" * (li.MAX_HEADER_BYTES + 20),))
        with mock.patch.object(li.socket, "socket", return_value=fake):
            with self.assertRaises(ValueError):
                li._bounded_socket_get(ctx, req)
        self.assertTrue(fake.closed)

    def test_bounded_socket_reader_rejects_oversized_payload_before_eof(self):
        ctx, req = metadata(0)
        payload = response(b"x" * (li.MAX_RAW_BYTES + 1))
        fake = FakeSocket((payload,))
        with mock.patch.object(li.socket, "socket", return_value=fake):
            with self.assertRaises(ValueError):
                li._bounded_socket_get(ctx, req)
        self.assertTrue(fake.closed)

    def test_internal_socket_boundary_rejects_unallowlisted_requests_before_socket(self):
        for path in ("/containers/prune", "/containers/create",
                     "/volumes/not-run-volume", "/info?all=1"):
            ctx, req = metadata(0)
            req["path"] = path
            with mock.patch.object(li.socket, "socket", side_effect=AssertionError("SOCKET_TOUCHED")):
                with self.assertRaises(ValueError):
                    li._bounded_socket_get(ctx, req)

    def test_internal_socket_boundary_rejects_invalid_resource_identity(self):
        for value in ("../etc/passwd", "not-hex", "b" * 63):
            ctx, req = metadata(3)
            ctx["container_id"] = value
            with mock.patch.object(li.socket, "socket", side_effect=AssertionError("SOCKET_TOUCHED")):
                with self.assertRaises(ValueError):
                    li._bounded_socket_get(ctx, req)

    def test_public_entrypoint_never_contacts_socket_even_when_passed_valid_ids(self):
        ctx, req = metadata(0)
        with mock.patch.object(li.socket, "socket", side_effect=AssertionError("SOCKET_TOUCHED")):
            result = li.inspect_once(ctx, req)
        self.assertFalse(result.accepted)
        self.assertEqual(result.status, "TRANSPORT_HARD_OFF")
        self.assertIs(result.socket_contacted, False)
        self.assertIs(result.physical_attested, False)

    def test_public_entrypoint_never_contacts_socket_on_malicious_context(self):
        with mock.patch.object(li.socket, "socket", side_effect=AssertionError("SOCKET_TOUCHED")):
            for context in (None, [], {}, "secret", True):
                result = li.inspect_once(context, None)
                self.assertEqual(result.status, "TRANSPORT_HARD_OFF")

    def test_all_runtime_approval_gates_remain_hard_off(self):
        self.assertIs(li.RUN_ENABLED, False)
        self.assertIs(li.DOCKER_SOCKET_PERMISSION, False)
        self.assertIs(li.APPROVAL_VERIFIER_IMPLEMENTED, False)
        self.assertIs(li.INDEPENDENT_ENGINE_ATTESTED, False)
        self.assertIs(li.ISOLATED_HOST_ATTESTED, False)
        self.assertIs(li.SQL_ALLOWED, False)
        self.assertIs(li.RC01_ALLOWED, False)
        self.assertIsNone(li.ONE_USE_APPROVAL)
        self.assertEqual(li.SOURCE_PARENT, "202bbe63387da8d6dc430bb0eae2316941a918a0")

    def test_safe_report_constant_values_cannot_reflect_secrets(self):
        for payload in (None, {}, "PASSWORD=private", object()):
            status = li.safe_summary(payload)
            self.assertEqual(status["B12_CUTOVER"], "HARD_OFF")
            self.assertEqual(status["RUNTIME_APPROVAL"], "NOT_GRANTED")
            self.assertEqual(status["DOCKER_SOCKET_CONTACTED"], "NO")
            self.assertNotIn("private", str(status))
            self.assertNotIn(CID, str(status))

    def test_ast_source_only_no_execution_on_import(self):
        src = (Path(__file__).resolve().parents[1] /
               "tools/b12_ws52li_docker_unix_transport_hardoff.py").read_text("utf-8")
        root = ast.parse(src)
        imports = set()
        for node in ast.walk(root):
            if isinstance(node, ast.Import):
                imports.update(n.name.split(".")[0] for n in node.names)
            elif isinstance(node, ast.ImportFrom):
                imports.add((node.module or "").split(".")[0])
        allowed = {
            "__future__", "dataclasses", "socket", "typing",
            "b12_ws52kz_broker_projection_source_contract",
            "b12_ws52le_raw_docker_response_projection_core",
        }
        self.assertTrue(imports <= allowed, imports)
        self.assertNotIn('if __name__ == "__main__":', src)
        self.assertNotIn("def main(", src)


if __name__ == "__main__":
    unittest.main()
