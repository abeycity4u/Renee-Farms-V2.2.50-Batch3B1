"""WS52-KK: fixture-free tests, no HTTP, no Docker, no SQL, no secrets."""
import contextlib
import io
import json
from pathlib import Path
import sys
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "tools"))
import b12_ws52kk_mariadb_config_blob_readonly as checker


def fake_image(**overrides):
    image = {
        "architecture": "amd64",
        "os": "linux",
        "rootfs": {
            "type": "layers",
            "diff_ids": ["sha256:" + format(i, "064x") for i in range(1, 9)],
        },
        "config": {
            "Volumes": {"/var/lib/mysql": {}},
            "Entrypoint": ["docker-entrypoint.sh"],
            "Cmd": ["mariadbd"],
            "Env": ["SECRET_SHOULD_NEVER_BE_PRINTED=XYZ"],
        },
        "history": [{"created_by": "SENSITIVE_HISTORY_MUST_STAY_PRIVATE"}],
    }
    image.update(overrides)
    raw = json.dumps(image, separators=(",", ":")).encode()
    return raw, checker.sha256_digest(raw)


def inspect_fake(raw, digest=None):
    return checker.inspect_config_bytes(
        raw,
        digest if digest is not None else checker.sha256_digest(raw),
        len(raw),
        8,
    )


class ReadOnlyConfigBlobTests(unittest.TestCase):
    def test_valid_fictional_oci_image_config_and_safe_classifications(self):
        raw, digest = fake_image()
        result = inspect_fake(raw, digest)
        self.assertEqual(result, {
            "OS_ARCH": "linux/amd64",
            "ROOTFS_DIFF_ID_COUNT": "8",
            "DECLARED_VOLUME_COUNT": "1",
            "MYSQL_DATADIR_VOLUME_DECLARED": "YES",
            "ENTRYPOINT_CLASS": "NONEMPTY_ARRAY",
            "COMMAND_CLASS": "NONEMPTY_ARRAY",
        })
        self.assertNotIn("SECRET_SHOULD_NEVER_BE_PRINTED", str(result))
        self.assertNotIn("SENSITIVE_HISTORY", str(result))
        self.assertNotIn("mariadbd", str(result))

    def test_mutated_blob_and_bad_digest_fail(self):
        raw, digest = fake_image()
        for body, claim in (
            (raw + b" ", digest),
            (raw, "sha256:" + "0" * 64),
            (raw, "sha256:invalid"),
            (raw.decode(), digest),
        ):
            with self.subTest(case=str(claim)[:15]):
                with self.assertRaises(checker.ConfigRejected):
                    checker.inspect_config_bytes(body, claim, len(raw), 8)

    def test_reject_incorrect_expected_size_and_layer_count(self):
        raw, digest = fake_image()
        for length, count in ((len(raw)+1, 8), (len(raw), 7),
                              (True, 8), (len(raw), False)):
            with self.subTest(length=length, count=count):
                with self.assertRaises(checker.ConfigRejected):
                    checker.inspect_config_bytes(raw, digest, length, count)

    def test_reject_wrong_os_and_architecture(self):
        for overrides in ({"os": "windows"}, {"architecture": "arm64"},
                          {"os": None}, {"architecture": None}):
            raw, _ = fake_image(**overrides)
            with self.subTest(overrides=overrides):
                with self.assertRaises(checker.ConfigRejected):
                    inspect_fake(raw)

    def test_reject_invalid_rootfs_shape_and_diffids(self):
        cases = (
            {"rootfs": {"type": "layers", "diff_ids": []}},
            {"rootfs": {"type": "layers", "diff_ids": ["bad"] * 8}},
            {"rootfs": {"type": "layers", "diff_ids": [True] * 8}},
            {"rootfs": {"type": "not-layers", "diff_ids": []}},
            {"rootfs": None},
        )
        for overrides in cases:
            raw, _ = fake_image(**overrides)
            with self.subTest(overrides=overrides):
                with self.assertRaises(checker.ConfigRejected):
                    inspect_fake(raw)

    def test_reject_duplicate_json_keys_even_with_correct_hash(self):
        raw = b'{"os":"linux","os":"linux","architecture":"amd64"}'
        with self.assertRaises(checker.ConfigRejected):
            checker.inspect_config_bytes(raw, checker.sha256_digest(raw), len(raw), 8)

    def test_reject_invalid_volume_and_argument_types(self):
        configs = (
            {"Volumes": ["/var/lib/mysql"]},
            {"Volumes": {"relative/path": {}}},
            {"Volumes": {"/var/lib/mysql": "NOT_A_VOLUME_DESCRIPTOR"}},
            {"Entrypoint": "docker-entrypoint.sh"},
            {"Cmd": [1, "mariadbd"]},
        )
        for bad in configs:
            standard, _ = fake_image()
            document = json.loads(standard)
            document["config"].update(bad)
            raw = json.dumps(document).encode()
            with self.subTest(bad=bad):
                with self.assertRaises(checker.ConfigRejected):
                    inspect_fake(raw)

    def test_missing_optional_volume_or_command_is_classified(self):
        raw, _ = fake_image()
        doc = json.loads(raw)
        doc["config"].pop("Volumes")
        doc["config"]["Cmd"] = None
        doc["config"]["Entrypoint"] = []
        payload = json.dumps(doc).encode()
        classification = inspect_fake(payload)
        self.assertEqual(classification["DECLARED_VOLUME_COUNT"], "0")
        self.assertEqual(classification["MYSQL_DATADIR_VOLUME_DECLARED"], "NO")
        self.assertEqual(classification["COMMAND_CLASS"], "NOT_DECLARED")
        self.assertEqual(classification["ENTRYPOINT_CLASS"], "EMPTY_ARRAY")

    def test_public_request_source_cannot_expand_scope(self):
        source = (Path(__file__).resolve().parents[1] /
                  "tools/b12_ws52kk_mariadb_config_blob_readonly.py").read_text()
        self.assertIn("https://auth.docker.io/token?", source)
        self.assertIn("https://registry-1.docker.io/v2/library/mariadb/blobs/", source)
        self.assertIn('BLOB_URL = "https://registry-1.docker.io', source)
        self.assertIn("urllib.request.ProxyHandler({})", source)
        self.assertIn("BlockRedirect", source)
        self.assertIn('method="GET"', source)
        self.assertNotIn("subprocess.", source)
        self.assertNotIn("os.system", source)
        self.assertNotIn('method="POST"', source)
        self.assertNotIn('method="DELETE"', source)
        self.assertNotIn('method="PUT"', source)
        self.assertNotIn("docker.from_env", source)
        self.assertNotIn("import pymysql", source)
        self.assertNotIn("import mysql", source)
        self.assertNotIn("/manifests/", source)
        self.assertEqual(checker.CONFIG_SIZE, 8790)
        self.assertEqual(checker.EXPECTED_DIFF_IDS, 8)

    def test_main_nonmac_fails_closed_without_network(self):
        output = io.StringIO()
        with patch.object(checker.sys, "platform", "linux"), \
             patch.object(checker, "verify_public_config",
                          side_effect=AssertionError("must never access network")), \
             contextlib.redirect_stdout(output):
            self.assertEqual(checker.main(), 2)
        self.assertIn("RESULT=BLOCKED", output.getvalue())
        self.assertIn("REASON=NOT_MACOS", output.getvalue())
        self.assertIn("CONFIG_BYTES_HASH_VERIFIED=NOT_ATTESTED", output.getvalue())


if __name__ == "__main__":
    unittest.main()
