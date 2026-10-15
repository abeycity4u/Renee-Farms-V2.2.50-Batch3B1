"""WS52-KO fixture-free security contracts. NO network/Docker/SQL/actions."""
import ast
import contextlib
import hashlib
import io
import json
from pathlib import Path
import sys
import unittest
from unittest.mock import patch, call

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "tools"))
import b12_ws52ko_allowlisted_redirect_config_readonly as ko


def signed_location(query="expires=111&signature=SAFE&version=2"):
    return "https://" + ko.CDN_HOST + ko.CDN_PATH + "?" + query


def fake_config(**changes):
    cfg = {
        "os": "linux",
        "architecture": "amd64",
        "rootfs": {
            "type": "layers",
            "diff_ids": ["sha256:" + format(i, "064x") for i in range(1, 9)],
        },
        "config": {
            "Volumes": {"/var/lib/mysql": {}},
            "Entrypoint": ["secret-entrypoint-value"],
            "Cmd": ["secret-command-value"],
            "Env": ["SENSITIVE_ENV_VALUE"],
        },
        "history": [{"created_by": "SENSITIVE_HISTORY_VALUE"}],
    }
    cfg.update(changes)
    raw = json.dumps(cfg, separators=(",", ":")).encode("utf-8")
    if len(raw) > ko.CONFIG_SIZE:
        raise AssertionError("test data exceeded fixed config length")
    return raw + b" " * (ko.CONFIG_SIZE - len(raw))


class WS52KOOfflineTests(unittest.TestCase):
    def test_approved_exact_cloudfront_digest_path_and_signed_url(self):
        url = signed_location()
        self.assertEqual(ko.validate_exact_cdn_location(url, 307), url)
        self.assertIn(ko.CONFIG_DIGEST.split(":")[1], url)

    def test_reject_other_hosts_and_bearer_userinfo(self):
        for value in (
            "https://evil.invalid" + ko.CDN_PATH + "?signature=X",
            "https://user:password@" + ko.CDN_HOST + ko.CDN_PATH + "?signature=X",
            "https://" + ko.CDN_HOST + ".evil.invalid" + ko.CDN_PATH + "?signature=X",
            "https://127.0.0.1" + ko.CDN_PATH + "?signature=X",
        ):
            with self.subTest(value=value[:35]):
                with self.assertRaises(ko.Reject):
                    ko.validate_exact_cdn_location(value, 307)

    def test_reject_wrong_blob_digest_path_or_encoded_path(self):
        good = signed_location()
        bad = (
            good.replace(ko.HASH_HEX, "0" * 64),
            good.replace("/data?", "/other?"),
            good.replace("/registry-v2/", "/%72egistry-v2/"),
            good.replace("/data?", "/../data?"),
        )
        for value in bad:
            with self.subTest(value=value[:55]):
                with self.assertRaises(ko.Reject):
                    ko.validate_exact_cdn_location(value, 307)

    def test_reject_other_status_schemes_and_ports(self):
        good = signed_location()
        for status in (301, 302, 303, 308, 200, True):
            with self.subTest(status=status):
                with self.assertRaises(ko.Reject):
                    ko.validate_exact_cdn_location(good, status)
        for uri in (
            good.replace("https:", "http:", 1),
            good.replace(ko.CDN_HOST, ko.CDN_HOST + ":444", 1),
            good + "#fragment",
            "//" + ko.CDN_HOST + ko.CDN_PATH + "?signature=X",
        ):
            with self.subTest(uri=uri[:35]):
                with self.assertRaises(ko.Reject):
                    ko.validate_exact_cdn_location(uri, 307)

    def test_reject_missing_malformed_or_too_long_query(self):
        values = (
            "https://" + ko.CDN_HOST + ko.CDN_PATH,
            signed_location("a=%GG"),
            signed_location("a=evil%"),
            signed_location("a=%00\n"),
            signed_location("x=" + "a" * 4200),
            signed_location("a=valid") + " ",
        )
        for value in values:
            with self.subTest(uri=value[:50]):
                with self.assertRaises(ko.Reject):
                    ko.validate_exact_cdn_location(value, 307)

    def test_pure_fictional_config_verified_shape_and_no_secret_data(self):
        raw = fake_config()
        digest = "sha256:" + hashlib.sha256(raw).hexdigest()
        with patch.object(ko, "CONFIG_DIGEST", digest):
            result = ko.inspect_verified_config(raw)
        self.assertEqual(result, {
            "OS_ARCH": "linux/amd64",
            "DIFF_ID_COUNT": "8",
            "DECLARED_VOLUME_COUNT": "1",
            "MYSQL_DATADIR_VOLUME_DECLARED": "YES",
            "ENTRYPOINT_CLASS": "ARRAY_NONEMPTY",
            "COMMAND_CLASS": "ARRAY_NONEMPTY",
        })
        for sensitive in ("SENSITIVE_ENV_VALUE", "SENSITIVE_HISTORY_VALUE",
                          "secret-entrypoint-value", "secret-command-value"):
            self.assertNotIn(sensitive, str(result))

    def test_real_pinned_digest_rejects_fictional_bytes(self):
        with self.assertRaises(ko.Reject):
            ko.inspect_verified_config(fake_config())

    def test_reject_tamper_and_size_even_with_fictional_digest(self):
        raw = fake_config()
        digest = "sha256:" + hashlib.sha256(raw).hexdigest()
        with patch.object(ko, "CONFIG_DIGEST", digest):
            for mutated in (raw + b"x", raw[1:], b"x" + raw[1:]):
                with self.subTest(size=len(mutated)):
                    with self.assertRaises(ko.Reject):
                        ko.inspect_verified_config(mutated)

    def test_reject_invalid_rootfs_and_os_even_with_valid_matching_hash(self):
        variants = (
            {"architecture": "arm64"},
            {"os": "windows"},
            {"rootfs": {"type": "layers", "diff_ids": ["bad"] * 8}},
            {"rootfs": {"type": "layers", "diff_ids": []}},
        )
        for variant in variants:
            raw = fake_config(**variant)
            digest = "sha256:" + hashlib.sha256(raw).hexdigest()
            with self.subTest(variant=str(variant)[:30]), \
                 patch.object(ko, "CONFIG_DIGEST", digest):
                with self.assertRaises(ko.Reject):
                    ko.inspect_verified_config(raw)

    def test_reject_duplicate_config_properties(self):
        raw = b'{"os":"linux","os":"linux"}'
        raw += b" " * (ko.CONFIG_SIZE - len(raw))
        digest = "sha256:" + hashlib.sha256(raw).hexdigest()
        with patch.object(ko, "CONFIG_DIGEST", digest):
            with self.assertRaises(ko.Reject):
                ko.inspect_verified_config(raw)

    def test_token_duplicate_or_short_rejected(self):
        for raw in (
            b'{"token":"abcdefghi"}',
            b'{"token":"1234567890","token":"1234567890"}',
            b'{"unknown":"value"}',
        ):
            with self.assertRaises(ko.Reject):
                ko.parse_token(raw)

    def test_success_flow_sends_no_authorization_to_cdn(self):
        fake_payload = fake_config()
        with patch.object(ko, "opener_no_proxy",
                          side_effect=[object(), object()]) as opening, \
             patch.object(ko, "get_once",
                          side_effect=[
                              ({}, b'{"token":"TEN_DIGITS_123456"}'),
                              ko.RedirectObserved(307, signed_location()),
                              ({"Content-Length": str(ko.CONFIG_SIZE),
                                "Content-Type": "application/octet-stream"}, fake_payload),
                          ]) as getter, \
             patch.object(ko, "inspect_verified_config",
                          return_value={"OS_ARCH": "linux/amd64"}) as inspector:
            result = ko.retrieve_one_config_readonly()
        self.assertEqual(result["OS_ARCH"], "linux/amd64")
        self.assertEqual(opening.call_count, 2)
        self.assertEqual(getter.call_count, 3)
        calls = getter.call_args_list
        self.assertEqual([c.args[1] for c in calls], [
            ko.TOKEN_URL, ko.REGISTRY_URL, signed_location(),
        ])
        self.assertIn("Authorization", calls[1].args[2])
        self.assertNotIn("Authorization", calls[2].args[2])
        self.assertIsNone(calls[1].args[3])
        self.assertEqual(calls[2].args[3], ko.CONFIG_SIZE)
        inspector.assert_called_once_with(fake_payload)

    def test_cdn_second_redirect_rejected_no_third_request(self):
        with patch.object(ko, "opener_no_proxy",
                          side_effect=[object(), object()]), \
             patch.object(ko, "get_once", side_effect=[
                 ({}, b'{"token":"TEN_DIGITS_123456"}'),
                 ko.RedirectObserved(307, signed_location()),
                 ko.RedirectObserved(302, "https://evil.invalid/do-not-follow"),
             ]) as getter:
            with self.assertRaisesRegex(ko.Reject, "CDN_SECOND_REDIRECT_REJECTED"):
                ko.retrieve_one_config_readonly()
        self.assertEqual(getter.call_count, 3)

    def test_registry_direct_response_fails_closed_without_cdn(self):
        with patch.object(ko, "opener_no_proxy", return_value=object()) as opening, \
             patch.object(ko, "get_once", side_effect=[
                 ({}, b'{"token":"TEN_DIGITS_123456"}'),
                 ({}, None),
             ]) as getter:
            with self.assertRaisesRegex(ko.Reject, "EXPECTED_SINGLE_REGISTRY_307_ABSENT"):
                ko.retrieve_one_config_readonly()
        self.assertEqual(opening.call_count, 1)
        self.assertEqual(getter.call_count, 2)

    def test_main_nonmac_no_network_and_no_certification(self):
        output = io.StringIO()
        with patch.object(ko.sys, "platform", "linux"), \
             patch.object(ko, "retrieve_one_config_readonly",
                          side_effect=AssertionError("NETWORK_MUST_NEVER_EXECUTE")), \
             contextlib.redirect_stdout(output):
            rc = ko.main()
        self.assertEqual(rc, 2)
        self.assertIn("RESULT=BLOCKED", output.getvalue())
        self.assertIn("B12_CUTOVER=HARD_OFF", output.getvalue())
        self.assertIn("REPAIRED_RC01_RC14_CERTIFIED_PASSES=0", output.getvalue())

    def test_source_network_and_execution_boundaries(self):
        source = (Path(__file__).resolve().parents[1] /
                  "tools/b12_ws52ko_allowlisted_redirect_config_readonly.py").read_text()
        self.assertIn("https://auth.docker.io/token?", source)
        self.assertIn("https://registry-1.docker.io/v2/library/mariadb/blobs/", source)
        self.assertIn('CDN_HOST = "production.cloudfront.docker.com"', source)
        self.assertIn("urllib.request.ProxyHandler({})", source)
        self.assertIn("class NeverFollow", source)
        self.assertIn('method="GET"', source)
        self.assertIn("if sys.platform == \"darwin\"", source)
        self.assertIn("response.read(read_cap + 1)", source)
        # Parse Python source, not docstrings/comments containing "subprocess.".
        syntax = ast.parse(source)
        forbidden = {"subprocess", "os", "docker", "pymysql", "mysql"}
        for node in ast.walk(syntax):
            if isinstance(node, ast.Import):
                for imported in node.names:
                    self.assertNotIn(imported.name.split(".")[0], forbidden)
            elif isinstance(node, ast.ImportFrom):
                self.assertNotIn((node.module or "").split(".")[0], forbidden)
        self.assertNotIn("os.system", source)
        self.assertNotIn('method="POST"', source)
        self.assertNotIn('method="PUT"', source)
        self.assertNotIn('method="DELETE"', source)
        self.assertNotIn("docker.from_env", source)
        self.assertEqual(ko.CONFIG_SIZE, 8790)


if __name__ == "__main__":
    unittest.main()
