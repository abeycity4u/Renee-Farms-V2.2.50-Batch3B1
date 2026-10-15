"""WS52-LG inert ingress tests; imports pinned WS52-LE fixture helper only.

No Docker socket, HTTP, SQL, file mutation, service calls, or threads.
"""
import ast
from pathlib import Path
import sys
import unittest

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "tools"))
sys.path.insert(0, str(ROOT / "tests"))

from test_b12_ws52le_raw_docker_response_projection_core import fixture, encoded
import b12_ws52le_raw_docker_response_projection_core as le
import b12_ws52lg_inert_broker_response_ingress as lg


def prepared(route=0, *, chunks=None):
    context, request, raw_object = fixture(route)
    raw = encoded(raw_object)
    if chunks is None:
        chunks = (raw,)
    envelope = {
        "source_kind": "SYNTHETIC_IN_MEMORY_ONLY",
        "http_status": 200,
        "content_type": "application/json",
        "content_encoding": "identity",
        "transfer_encoding": "none",
        "content_length": sum(len(part) for part in chunks),
        "redirects_followed": False,
        "read_completed": True,
        "connection_closed": True,
        "broker_approval": None,
    }
    return context, request, envelope, chunks


class InertBrokerIngressTests(unittest.TestCase):
    def blocked(self, c, req, envelope, chunks, expected=None):
        result = lg.ingest_inert_response(c, req, envelope, chunks)
        self.assertFalse(result.shape_valid)
        self.assertFalse(result.physical_attested)
        self.assertFalse(result.actual_http_collected)
        self.assertFalse(result.socket_accessed)
        self.assertFalse(result.raw_exported)
        self.assertFalse(result.one_use_approved)
        self.assertFalse(result.rc01_certified)
        self.assertEqual(
            lg.safe_summary(result)["RESULT"], "BLOCKED_SOURCE_CONTRACT"
        )
        if expected:
            self.assertEqual(result.reason, expected)

    def test_six_real_shaped_fixture_routes_are_processed_without_io(self):
        for i in range(6):
            with self.subTest(i=i):
                c, req, env, chunks = prepared(i)
                result = lg.ingest_inert_response(c, req, env, chunks)
                self.assertTrue(result.shape_valid, result.reason)
                self.assertEqual(result.reason, "INERT_FIXTURE_INGRESS_SHAPE_ONLY")
                self.assertFalse(result.physical_attested)
                self.assertFalse(result.actual_http_collected)
                self.assertFalse(result.socket_accessed)
                self.assertFalse(result.raw_exported)
                self.assertFalse(result.one_use_approved)
                self.assertFalse(result.rc01_certified)

    def test_even_accepted_report_cannot_claim_runtime_or_cutover(self):
        c, req, env, chunks = prepared(3)
        report = lg.safe_summary(lg.ingest_inert_response(c, req, env, chunks))
        self.assertEqual(report["RESULT"], "INERT_FIXTURE_INGRESS_SHAPE_ONLY")
        self.assertEqual(report["DOCKER_SOCKET_ACCESS"], "NO")
        self.assertEqual(report["PHYSICAL_DOCKER_INSPECTION"], "NO")
        self.assertEqual(report["RAW_DOCKER_EXPORT"], "NO")
        self.assertEqual(report["RUNTIME_APPROVAL"], "NOT_GRANTED")
        self.assertEqual(report["MIGRATION_092"], "NOT_AUTHORIZED")
        self.assertEqual(report["B12_CUTOVER"], "HARD_OFF")

    def test_every_runtime_fuse_remains_disabled(self):
        self.assertIs(lg.TRANSPORT_IMPLEMENTED, False)
        self.assertIs(lg.DOCKER_SOCKET_ACCESS_GRANTED, False)
        self.assertIs(lg.NETWORK_ALLOWED, False)
        self.assertIs(lg.RAW_DOCKER_EXPORT_ALLOWED, False)
        self.assertIs(lg.IMAGE_PULL_ALLOWED, False)
        self.assertIs(lg.SQL_ALLOWED, False)
        self.assertIs(lg.RC01_ALLOWED, False)
        self.assertIsNone(lg.APPROVED_ONE_USE_RUNTIME)
        self.assertIs(le.TRANSPORT_IMPLEMENTED, False)

    def test_shared_core_identity_and_byte_budget_reused(self):
        self.assertEqual(lg.SOURCE_PARENT, "1c472fdb0f9b17b39f158fcf85a987f35140557c")
        self.assertEqual(lg.MAX_BODY_BYTES, le.MAX_RAW_BYTES)
        self.assertEqual(lg.MAX_BODY_BYTES, 65536)
        self.assertEqual(lg.MAX_CHUNKS, 16)
        self.assertEqual(lg.MAX_CHUNK_BYTES, 8192)

    def test_multi_chunk_reassembly_is_bounded_and_exact(self):
        c, req, env, chunks = prepared(3)
        raw = chunks[0]
        smaller = tuple(raw[i:i+45] for i in range(0, len(raw), 45))
        self.assertLessEqual(len(smaller), lg.MAX_CHUNKS)
        env["content_length"] = len(raw)
        result = lg.ingest_inert_response(c, req, env, smaller)
        self.assertTrue(result.shape_valid, result.reason)

    def test_missing_envelope_key_rejected(self):
        c, req, env, chunks = prepared()
        del env["content_length"]
        self.blocked(c, req, env, chunks, "ENVELOPE_KEYS_INVALID")

    def test_unexpected_envelope_key_rejected_without_echo(self):
        c, req, env, chunks = prepared()
        env["Authorization"] = "TOKEN_SECRET"
        self.blocked(c, req, env, chunks, "ENVELOPE_KEYS_INVALID")
        self.assertNotIn("TOKEN_SECRET", str(lg.safe_summary(
            lg.ingest_inert_response(c, req, env, chunks))))

    def test_invalid_envelope_types_rejected(self):
        c, req, env, chunks = prepared()
        for invalid in (None, [], "secret", 123, True):
            self.blocked(c, req, invalid, chunks, "ENVELOPE_KEYS_INVALID")

    def test_source_kind_must_remain_synthetic(self):
        for value in ("REAL_DOCKER", "LIVE_APPROVED", "", True):
            c, req, env, chunks = prepared()
            env["source_kind"] = value
            self.blocked(c, req, env, chunks,
                         "ENVELOPE_PREMATURE_EXECUTION_OR_UNTRUSTED")

    def test_broker_approval_cannot_be_claimed(self):
        for value in ("APPROVED", True, False, 1, ""):
            c, req, env, chunks = prepared()
            env["broker_approval"] = value
            self.blocked(c, req, env, chunks,
                         "ENVELOPE_PREMATURE_EXECUTION_OR_UNTRUSTED")

    def test_redirects_incomplete_read_or_open_connection_rejected(self):
        for name, invalid in (
            ("redirects_followed", True),
            ("read_completed", False),
            ("connection_closed", False),
            ("read_completed", 1),
            ("connection_closed", 1),
        ):
            c, req, env, chunks = prepared()
            env[name] = invalid
            self.blocked(c, req, env, chunks,
                         "ENVELOPE_PREMATURE_EXECUTION_OR_UNTRUSTED")

    def test_http_status_must_be_literal_integer_200(self):
        for bad in (201, 301, 404, 500, "200", True, 200.0, None):
            c, req, env, chunks = prepared()
            env["http_status"] = bad
            self.blocked(c, req, env, chunks, "HTTP_RESPONSE_SHAPE_UNREVIEWED")

    def test_mime_encoding_and_transfer_flags_closed(self):
        for field, bad in (
            ("content_type", "application/json; charset=utf-8"),
            ("content_type", "text/plain"),
            ("content_type", None),
            ("content_encoding", "gzip"),
            ("content_encoding", "br"),
            ("content_encoding", True),
            ("transfer_encoding", "chunked"),
            ("transfer_encoding", "gzip"),
            ("transfer_encoding", 0),
        ):
            c, req, env, chunks = prepared()
            env[field] = bad
            self.blocked(c, req, env, chunks, "HTTP_RESPONSE_SHAPE_UNREVIEWED")

    def test_content_length_noninteger_rejected(self):
        for bad in (True, False, "12", 12.0, None):
            c, req, env, chunks = prepared()
            env["content_length"] = bad
            self.blocked(c, req, env, chunks, "CONTENT_LENGTH_INVALID")

    def test_content_length_outside_limits_rejected(self):
        for bad in (0, -1, lg.MAX_BODY_BYTES + 1):
            c, req, env, chunks = prepared()
            env["content_length"] = bad
            self.blocked(c, req, env, chunks, "CONTENT_LENGTH_INVALID")

    def test_tuple_only_no_generator_or_callable(self):
        c, req, env, chunks = prepared()
        for bad in (list(chunks), iter(chunks), lambda: chunks,
                    chunks[0], None, "secret"):
            self.blocked(c, req, env, bad, "BOUNDED_CHUNK_COUNT_INVALID")

    def test_empty_chunks_tuple_rejected(self):
        c, req, env, chunks = prepared()
        self.blocked(c, req, env, (), "BOUNDED_CHUNK_COUNT_INVALID")

    def test_chunk_count_excess_rejected(self):
        c, req, env, chunks = prepared()
        self.blocked(c, req, env, tuple(b"x" for _ in range(17)),
                     "BOUNDED_CHUNK_COUNT_INVALID")

    def test_chunk_must_be_nonempty_literal_bytes(self):
        for value in (b"", "str", bytearray(b"x"), 1, None, [], b"a" * 8193):
            c, req, env, chunks = prepared()
            self.blocked(c, req, env, (value,), "BOUNDED_CHUNK_INVALID")

    def test_combined_payload_exceeding_global_cap_rejected(self):
        c, req, env, chunks = prepared()
        env["content_length"] = lg.MAX_BODY_BYTES
        huge = tuple(b"x" * lg.MAX_CHUNK_BYTES for _ in range(9))
        self.blocked(c, req, env, huge, "BODY_CAP_EXCEEDED")

    def test_truncated_payload_fails_closed(self):
        c, req, env, chunks = prepared(3)
        chunks = (chunks[0][:-1],)
        self.blocked(c, req, env, chunks, "BODY_LENGTH_MISMATCH")

    def test_appended_junk_fails_closed(self):
        c, req, env, chunks = prepared(3)
        chunks = (chunks[0] + b"JUNK",)
        self.blocked(c, req, env, chunks, "BODY_LENGTH_MISMATCH")

    def test_forged_matching_length_does_not_bypass_json_validation(self):
        c, req, env, chunks = prepared(3)
        raw = chunks[0][:-1]
        env["content_length"] = len(raw)
        self.blocked(c, req, env, (raw,), "RAW_PROJECTION_REJECTED")

    def test_forged_matching_length_does_not_bypass_ownership_validation(self):
        c, req, env, chunks = prepared(3)
        altered = chunks[0].replace(b'"RW":true', b'"RW":false')
        env["content_length"] = len(altered)
        self.blocked(c, req, env, (altered,), "RAW_PROJECTION_REJECTED")

    def test_wrong_request_verb_never_reaches_production(self):
        c, req, env, chunks = prepared(3)
        req["method"] = "POST"
        self.blocked(c, req, env, chunks, "RAW_PROJECTION_REJECTED")

    def test_wrong_container_identity_rejected_by_existing_core(self):
        c, req, env, chunks = prepared(3)
        c["container_id"] = "b" * 64
        self.blocked(c, req, env, chunks, "RAW_PROJECTION_REJECTED")

    def test_sensitive_raw_fields_never_appear_in_safe_summary(self):
        c, req, env, chunks = prepared(3)
        self.assertIn(b"VERY_SECRET", chunks[0])
        outcome = lg.ingest_inert_response(c, req, env, chunks)
        sanitized = str(lg.safe_summary(outcome))
        for raw in ("VERY_SECRET", "MYSQL_ROOT", "/home/", "Source", "Config",
                    c["container_id"], c["volume_name"]):
            self.assertNotIn(raw, sanitized)

    def test_source_has_no_runtime_io_or_main(self):
        src = (
            Path(__file__).resolve().parents[1]
            / "tools/b12_ws52lg_inert_broker_response_ingress.py"
        ).read_text("utf-8")
        tree = ast.parse(src)
        imported = set()
        for node in ast.walk(tree):
            if isinstance(node, ast.Import):
                imported.update(n.name.split(".")[0] for n in node.names)
            elif isinstance(node, ast.ImportFrom):
                imported.add((node.module or "").split(".")[0])
        allowed = {
            "__future__", "dataclasses", "typing",
            "b12_ws52le_raw_docker_response_projection_core",
        }
        self.assertTrue(imported <= allowed, imported)
        self.assertNotIn("def main(", src)
        self.assertNotIn('if __name__ == "__main__":', src)

if __name__ == "__main__":
    unittest.main()
