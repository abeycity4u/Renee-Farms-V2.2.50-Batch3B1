"""WS52-KI fixture-free OCI manifest index tests: absolutely no HTTP calls."""
import json
import sys
from pathlib import Path
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "tools"))
import b12_ws52ki_mariadb_registry_manifest_readonly as reader

_FAKE_CHILD = "sha256:" + "a" * 64


def fake_index(**changes):
    document = {
        "schemaVersion": 2,
        "mediaType": "application/vnd.oci.image.index.v1+json",
        "manifests": [
            {
                "mediaType": "application/vnd.oci.image.manifest.v1+json",
                "digest": _FAKE_CHILD, "size": 3501,
                "platform": {"os": "linux", "architecture": "amd64"},
            },
            {
                "mediaType": "application/vnd.oci.image.manifest.v1+json",
                "digest": "sha256:" + "b" * 64, "size": 3400,
                "platform": {"os": "linux", "architecture": "arm64"},
            },
        ],
    }
    document.update(changes)
    payload = json.dumps(document, separators=(",", ":")).encode("utf-8")
    return payload, reader.digest_of(payload)


class OCIRegistryReadOnlyTests(unittest.TestCase):
    def test_valid_fictional_index_and_exact_amd64_selection(self):
        body, digest = fake_index()
        self.assertEqual(reader.inspect_index(body, digest), (digest, _FAKE_CHILD))

    def test_modified_manifest_bytes_fail_hash_guard(self):
        body, digest = fake_index()
        with self.assertRaises(reader.ManifestRejected):
            reader.inspect_index(body + b" ", digest)

    def test_reject_missing_or_multiple_amd64_entries(self):
        data, _ = fake_index()
        manifest = json.loads(data)
        for children in (
            manifest["manifests"][1:],
            manifest["manifests"] + [manifest["manifests"][0]],
        ):
            with self.subTest(children=len(children)):
                body, digest = fake_index(manifests=children)
                with self.assertRaises(reader.ManifestRejected):
                    reader.inspect_index(body, digest)

    def test_malformed_index_and_unsupported_media_type_fail(self):
        for document in (
            {"schemaVersion": 1},
            {"schemaVersion": True},
            {"schemaVersion": 2, "mediaType": "application/vnd.oci.image.manifest.v1+json"},
        ):
            with self.subTest(document=document):
                body, digest = fake_index(**document)
                with self.assertRaises(reader.ManifestRejected):
                    reader.inspect_index(body, digest)

    def test_reject_unreviewed_amd64_variant_or_bad_child_digest(self):
        original, _ = fake_index()
        index = json.loads(original)
        for extra in (
            {"platform": {"os": "linux", "architecture": "amd64", "variant": "v1"}},
            {"digest": "sha256:bad"},
            {"mediaType": "application/x-unreviewed"},
            {"size": True},
        ):
            with self.subTest(extra=extra):
                document = json.loads(original)
                document["manifests"][0].update(extra)
                body, digest = fake_index(**document)
                with self.assertRaises(reader.ManifestRejected):
                    reader.inspect_index(body, digest)

    def test_reject_duplicate_json_keys_even_when_digest_matches(self):
        body = (
            b'{"schemaVersion":2,"mediaType":"application/vnd.oci.image.index.v1+json",'
            b'"manifests":[],"manifests":[]}'
        )
        with self.assertRaises(reader.ManifestRejected):
            reader.inspect_index(body, reader.digest_of(body))

    def test_reject_oversized_index_nonsha_digest_and_nonbytes(self):
        body, digest = fake_index()
        for bad_data, bad_hash in (
            (b"x" * (reader.MAX_INDEX + 1), digest),
            (body, "sha256:not-a-real-digest"),
            (body, "sha256:" + "A" * 64),
            (body.decode(), digest),
        ):
            with self.subTest(hash=str(bad_hash)[:15]):
                with self.assertRaises(reader.ManifestRejected):
                    reader.inspect_index(bad_data, bad_hash)

    def test_only_reviewed_network_origins_and_methods_in_source(self):
        source = (
            Path(__file__).resolve().parents[1]
            / "tools/b12_ws52ki_mariadb_registry_manifest_readonly.py"
        ).read_text("utf-8")
        self.assertIn("https://auth.docker.io/token?", source)
        self.assertIn("https://registry-1.docker.io/v2/library/mariadb/manifests/", source)
        self.assertIn('manifest_req(TAG, "HEAD")', source)
        self.assertIn('manifest_req(digest, "GET")', source)
        self.assertIn("urllib.request.ProxyHandler({})", source)
        self.assertIn("NoRedirect", source)
        self.assertNotIn("subprocess.", source)
        self.assertNotIn("os.system", source)
        self.assertNotIn("docker pull", source.lower())
        self.assertNotIn("POST", source)
        self.assertNotIn("PUT", source)
        self.assertNotIn("DELETE", source)


if __name__ == "__main__":
    unittest.main()
