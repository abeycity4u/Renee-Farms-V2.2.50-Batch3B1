"""WS52-KM fixture-free redirect metadata contracts; NO network requests."""
import contextlib
import io
import json
from pathlib import Path
import sys
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "tools"))
import b12_ws52km_mariadb_config_redirect_metadata_readonly as probe


class RedirectMetadataTests(unittest.TestCase):
    def test_same_origin_https_location_is_not_followed(self):
        outcome = probe.classify_redirect(
            probe.BLOB_URL, 307, "/v2/library/mariadb/blobs/sha256:abc?secret=HIDDEN"
        )
        self.assertEqual(outcome["HTTP_STATUS"], "307")
        self.assertEqual(outcome["DESTINATION_SCHEME"], "https")
        self.assertEqual(outcome["DESTINATION_HOST"], "registry-1.docker.io")
        self.assertEqual(outcome["DESTINATION_CLASS"], "SAME_ORIGIN_NO_FOLLOW")
        self.assertNotIn("HIDDEN", str(outcome))
        self.assertNotIn("/v2/", str(outcome))

    def test_external_cdn_host_is_metadata_only(self):
        outcome = probe.classify_redirect(
            probe.BLOB_URL, 307,
            "https://downloads.example.invalid/long/private/path?X-Amz-Signature=secret"
        )
        self.assertEqual(outcome["DESTINATION_HOST"], "downloads.example.invalid")
        self.assertEqual(outcome["DESTINATION_CLASS"], "EXTERNAL_UNREVIEWED_NO_FOLLOW")
        self.assertNotIn("secret", str(outcome))
        self.assertNotIn("X-Amz", str(outcome))

    def test_non_https_destination_is_classified_without_hostname(self):
        outcome = probe.classify_redirect(
            probe.BLOB_URL, 302, "http://not-allowed.invalid/x?secret=one"
        )
        self.assertEqual(outcome["DESTINATION_SCHEME"], "http")
        self.assertEqual(outcome["DESTINATION_CLASS"], "NON_HTTPS")
        self.assertEqual(outcome["DESTINATION_HOST"], "NOT_ATTESTED")

    def test_userinfo_and_bad_port_fail_closed(self):
        for loc in (
            "https://username:password@external.invalid/path",
            "https://external.invalid:444/path",
            "https://external.invalid:invalid/path",
        ):
            with self.subTest(location=loc):
                data = probe.classify_redirect(probe.BLOB_URL, 307, loc)
                self.assertEqual(data["DESTINATION_HOST"], "NOT_ATTESTED")

    def test_invalid_location_and_status_fail_closed(self):
        for location in (None, "", "https://external.invalid/\r\nInjected:123",
                         "https://bad..invalid/x", "https://localhost/x",
                         "https://127.0.0.1/x", "https://é.example/x",
                         "https://example.invalid/" + "x" * 9000):
            with self.subTest(length=len(str(location))):
                result = probe.classify_redirect(probe.BLOB_URL, 302, location)
                self.assertEqual(result["DESTINATION_HOST"], "NOT_ATTESTED")
        invalid = probe.classify_redirect(probe.BLOB_URL, 200, "https://example.invalid")
        self.assertEqual(invalid["HTTP_STATUS"], "INVALID")

    def test_token_redirect_stage_is_specific(self):
        with patch.object(probe, "_opener", return_value=object()), \
             patch.object(probe, "_get_original_only",
                 side_effect=probe.RedirectSeen(
                     302, "https://auth.docker.io/token?token=SECRET"
                 )) as getter:
            result = probe.probe_readonly()
        self.assertEqual(getter.call_count, 1)
        self.assertEqual(result["REDIRECT_STAGE"], "PUBLIC_TOKEN_GET")
        self.assertEqual(result["RESULT"], "READONLY_REDIRECT_METADATA_OBSERVED")
        self.assertEqual(result["DESTINATION_HOST"], "auth.docker.io")
        self.assertNotIn("SECRET", str(result))

    def test_blob_redirect_stage_and_no_destination_request(self):
        with patch.object(probe, "_opener", return_value=object()), \
             patch.object(probe, "_get_original_only",
                side_effect=[
                    b'{"token":"TEST_NON_SECRET_TOKEN_123"}',
                    probe.RedirectSeen(
                        307, "https://cdn.example.invalid/secret?signature=HIDDEN"
                    ),
                ]) as getter:
            result = probe.probe_readonly()
        self.assertEqual(getter.call_count, 2)
        self.assertEqual(getter.call_args_list[0].args[1], probe.TOKEN_URL)
        self.assertEqual(getter.call_args_list[1].args[1], probe.BLOB_URL)
        self.assertEqual(result["REDIRECT_STAGE"], "PINNED_CONFIG_BLOB_GET")
        self.assertEqual(result["HTTP_STATUS"], "307")
        self.assertEqual(result["DESTINATION_HOST"], "cdn.example.invalid")
        self.assertNotIn("HIDDEN", str(result))

    def test_original_endpoints_200_never_claim_blob_verified(self):
        with patch.object(probe, "_opener", return_value=object()), \
             patch.object(probe, "_get_original_only",
                side_effect=[b'{"token":"TEST_NON_SECRET_TOKEN_123"}', None]):
            result = probe.probe_readonly()
        self.assertEqual(result["RESULT"], "NO_REDIRECT_OBSERVED")
        self.assertEqual(result["REDIRECT_STAGE"], "NONE")

    def test_duplicate_token_json_and_short_token_rejected(self):
        for body in (
            b'{"token":"abc","token":"abc"}',
            b'{"token":"abc"}',
            b'{"other":"value"}',
        ):
            with self.subTest(body=body):
                with self.assertRaises(probe.ProbeRejected):
                    probe._parse_public_token(body)

    def test_main_nonmac_fails_closed_without_network(self):
        output = io.StringIO()
        with patch.object(probe.sys, "platform", "linux"), \
             patch.object(probe, "probe_readonly",
                          side_effect=AssertionError("network prohibited")), \
             contextlib.redirect_stdout(output):
            rc = probe.main()
        self.assertEqual(rc, 2)
        self.assertIn("RESULT=BLOCKED", output.getvalue())
        self.assertIn("REASON=NOT_MACOS", output.getvalue())
        self.assertIn("CONFIG_BYTES_FETCHED=NO", output.getvalue())

    def test_source_has_only_allowlisted_gets_and_no_follow(self):
        source = (Path(__file__).resolve().parents[1]
                  / "tools/b12_ws52km_mariadb_config_redirect_metadata_readonly.py"
                 ).read_text("utf-8")
        self.assertIn("https://auth.docker.io/token?", source)
        self.assertIn("https://registry-1.docker.io/v2/library/mariadb/blobs/", source)
        self.assertIn("urllib.request.ProxyHandler({})", source)
        self.assertIn('method="GET"', source)
        self.assertIn("class NeverFollow", source)
        self.assertIn("raise RedirectSeen(", source)
        self.assertIn("if not token_body:", source)
        self.assertIn("return None", source)
        self.assertNotIn("subprocess.", source)
        self.assertNotIn("os.system", source)
        self.assertNotIn('method="POST"', source)
        self.assertNotIn('method="PUT"', source)
        self.assertNotIn('method="DELETE"', source)
        self.assertNotIn("docker.from_env", source)
        self.assertNotIn("/manifests/", source)
        self.assertEqual(probe.CONFIG_BYTES_EXPECTED, 8790)


if __name__ == "__main__":
    unittest.main()
