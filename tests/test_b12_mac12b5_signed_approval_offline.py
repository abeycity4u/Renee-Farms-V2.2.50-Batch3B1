"""MAC-12B5 approval-contract tests. Offline; no database or Docker calls.

Only ephemeral RSA test keys are created in an automatically deleted temp dir.
They are NOT the production/testing approval signer and must never be reused.
"""
from datetime import datetime, timedelta, timezone
import hashlib
import importlib.util
import json
from pathlib import Path
import os
import shutil
import subprocess
import tempfile
import unittest
from unittest import mock

ROOT = Path(__file__).resolve().parents[1]
SUPERVISOR = ROOT / "tools/b12_mac12b3_rc01_supervisor_disabled.py"
SPEC = importlib.util.spec_from_file_location("mac12b5", SUPERVISOR)
M = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(M)

NOW = datetime(2026, 10, 16, 22, 0, tzinfo=timezone.utc)


def payload(now=NOW):
    def stamp(value):
        return value.strftime("%Y-%m-%dT%H:%M:%SZ")
    return {
        "contract": M.APPROVAL_CONTRACT,
        "scope": M.APPROVAL_SCOPE,
        "run_id": "a" * 32,
        "issued_utc": stamp(now - timedelta(minutes=1)),
        "expires_utc": stamp(now + timedelta(minutes=10)),
        "repository": "abeycity4u/Renee-Farms-V2.2.50-Batch3B1",
        "branch": M.REPO_BRANCH,
        "source_commit": "a" * 40,
        "supervisor_blob": "b" * 40,
        "harness_blob": M.SOURCE_BLOBS[M.HARNESS],
        "database": M.DATABASE,
        "php_image_id": M.PHP_IMAGE,
        "maximum_runs": 1,
        "synthetic_only": True,
        "production_contact": False,
    }


class SignedApprovalOfflineContract(unittest.TestCase):
    def test_source_fuse_and_signer_default_to_disabled(self):
        self.assertIs(M.EXECUTION_ENABLED, False)
        self.assertIsNone(M.TRUSTED_APPROVER_PUBLIC_KEY_SHA256)
        self.assertFalse(hasattr(M, "APPROVED_RUNTIME_COMMIT"))
        self.assertFalse(hasattr(M, "APPROVED_SUPERVISOR_BLOB"))

    def test_no_key_refuses_before_io_and_docker(self):
        with mock.patch.object(M, "approval_directory",
                               side_effect=AssertionError("filesystem touched")):
            with mock.patch.object(M, "docker",
                                   side_effect=AssertionError("docker touched")):
                with self.assertRaises(M.Blocked):
                    M.load_signed_approval()

    def test_exact_valid_scope_and_signed_commit_fields(self):
        good = payload()
        self.assertIs(M.validate_approval_payload(good, NOW), good)

    def test_reject_wrong_scope_and_extra_or_missing_fields(self):
        for key, value in (
            ("database", "production"),
            ("scope", "production"),
            ("branch", "main"),
            ("synthetic_only", False),
            ("production_contact", True),
            ("maximum_runs", 2),
        ):
            bad = payload()
            bad[key] = value
            with self.subTest(key=key):
                with self.assertRaises(M.Blocked):
                    M.validate_approval_payload(bad, NOW)
        for change in ("extra", "missing"):
            bad = payload()
            if change == "extra":
                bad["allow_prod"] = True
            else:
                bad.pop("source_commit")
            with self.assertRaises(M.Blocked):
                M.validate_approval_payload(bad, NOW)

    def test_false_or_boolean_maximum_runs_fails(self):
        for x in (False, True, 0, 2, -1, 1.0, "1"):
            bad = payload()
            bad["maximum_runs"] = x
            with self.subTest(value=x):
                with self.assertRaises(M.Blocked):
                    M.validate_approval_payload(bad, NOW)

    def test_expired_future_and_overwide_grants_fail(self):
        cases = [
            (NOW - timedelta(minutes=20), NOW - timedelta(minutes=5)),
            (NOW + timedelta(minutes=1), NOW + timedelta(minutes=5)),
            (NOW - timedelta(minutes=1), NOW + timedelta(minutes=30)),
        ]
        for issued, expires in cases:
            bad = payload()
            bad["issued_utc"] = issued.strftime("%Y-%m-%dT%H:%M:%SZ")
            bad["expires_utc"] = expires.strftime("%Y-%m-%dT%H:%M:%SZ")
            with self.subTest(issued=issued, expires=expires):
                with self.assertRaises(M.Blocked):
                    M.validate_approval_payload(bad, NOW)

    def test_reject_bad_digest_and_run_id(self):
        for field, value in (
            ("run_id", "../" * 8),
            ("run_id", "a" * 31),
            ("source_commit", "0" * 64),
            ("supervisor_blob", "ZZ" * 20),
            ("harness_blob", "0" * 39),
        ):
            bad = payload()
            bad[field] = value
            with self.subTest(field=field):
                with self.assertRaises(M.Blocked):
                    M.validate_approval_payload(bad, NOW)

    def test_duplicate_json_keys_rejected(self):
        with self.assertRaises(M.Blocked):
            json.loads('{"run_id":"a","run_id":"b"}',
                       object_pairs_hook=M.reject_duplicate_json_keys)

    def test_one_use_claim_persists_and_replay_fails(self):
        with tempfile.TemporaryDirectory() as tmp:
            base = Path(tmp)
            claims = base / "claims"
            claims.mkdir(mode=0o700)
            claims.chmod(0o700)
            with mock.patch.object(M, "approval_directory", return_value=base):
                M.claim_one_use(payload())
                marker = claims / ("claimed_" + "a" * 32)
                self.assertEqual(marker.read_text(), "a" * 40 + "\n")
                self.assertEqual(marker.stat().st_mode & 0o777, 0o600)
                with self.assertRaises(M.Blocked):
                    M.claim_one_use(payload())
                self.assertTrue(marker.exists())

    def test_missing_private_claim_directory_rejected(self):
        with tempfile.TemporaryDirectory() as tmp:
            with mock.patch.object(M, "approval_directory",
                                   return_value=Path(tmp)):
                with self.assertRaises(OSError):
                    M.claim_one_use(payload())

    def test_real_offline_rsa_signature_and_tampering(self):
        if not shutil.which("openssl"):
            self.skipTest("openssl not installed; separate gate remains unverified")
        with tempfile.TemporaryDirectory() as tmp:
            lab = Path(tmp)
            directory = lab / M.APPROVAL_DIR_NAME
            directory.mkdir(mode=0o700)
            directory.chmod(0o700)
            priv = directory / "ephemeral_private.pem"
            pub = directory / "trusted_approver_public.pem"
            manifest = directory / "mac12c_one_use.json"
            sig = directory / "mac12c_one_use.sig"
            for command in (
                ["openssl", "genrsa", "-out", str(priv), "2048"],
                ["openssl", "rsa", "-in", str(priv),
                 "-pubout", "-out", str(pub)],
            ):
                subprocess.run(command, check=True, capture_output=True,
                               timeout=20)
            now = datetime.now(timezone.utc)
            manifest.write_text(json.dumps(payload(now), sort_keys=True) + "\n",
                                encoding="utf-8")
            subprocess.run(
                ["openssl", "dgst", "-sha256", "-sign", str(priv),
                 "-out", str(sig), str(manifest)],
                check=True, capture_output=True, timeout=20
            )
            for path in (priv, pub, manifest, sig):
                path.chmod(0o600)
            fingerprint = hashlib.sha256(pub.read_bytes()).hexdigest()
            with mock.patch.object(M, "LAB", lab):
                with mock.patch.object(
                        M, "TRUSTED_APPROVER_PUBLIC_KEY_SHA256",
                        fingerprint):
                    good = M.load_signed_approval()
                    self.assertEqual(good["run_id"], "a" * 32)
                    tampered = manifest.read_text().replace(
                        M.DATABASE, "renee_b12_disposablf"
                    )
                    self.assertNotEqual(tampered, manifest.read_text())
                    manifest.write_text(tampered, encoding="utf-8")
                    manifest.chmod(0o600)
                    with self.assertRaises(M.Blocked):
                        M.load_signed_approval()

    def test_wrong_signer_fingerprint_fails_before_crypto(self):
        with tempfile.TemporaryDirectory() as tmp:
            directory = Path(tmp) / M.APPROVAL_DIR_NAME
            directory.mkdir(mode=0o700)
            directory.chmod(0o700)
            for name in ("trusted_approver_public.pem",
                         "mac12c_one_use.json",
                         "mac12c_one_use.sig"):
                file = directory / name
                file.write_bytes(b"not-trusted")
                file.chmod(0o600)
            with mock.patch.object(M, "LAB", Path(tmp)):
                with mock.patch.object(
                        M, "TRUSTED_APPROVER_PUBLIC_KEY_SHA256", "a" * 64):
                    with mock.patch.object(
                            M, "command",
                            side_effect=AssertionError(
                                "signature verification should not run")):
                        with self.assertRaises(M.Blocked):
                            M.load_signed_approval()


if __name__ == "__main__":
    unittest.main()
