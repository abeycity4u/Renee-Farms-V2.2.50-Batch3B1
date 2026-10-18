"""MAC-12B12-OFF: offline negatives for the existing hard-disabled V1 contract.

Never configure a trusted signer, enable either execution fuse, contact Docker or
MariaDB, or claim that independent revocation/human-approval gates exist.
RSA keys in this test are disposable test-only fixtures, not approval signers.
"""
import contextlib
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime, timedelta, timezone
import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import subprocess
import tempfile
import threading
import unittest
from unittest import mock

ROOT = Path(__file__).resolve().parents[1]
SUPERVISOR = ROOT / "tools/b12_mac12b3_rc01_supervisor_disabled.py"
SPEC = importlib.util.spec_from_file_location("mac12b12_offline", SUPERVISOR)
M = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(M)


def grant(now=None):
    if now is None:
        now = datetime.now(timezone.utc)

    def utc(value):
        return value.strftime("%Y-%m-%dT%H:%M:%SZ")

    return {
        "contract": M.APPROVAL_CONTRACT,
        "scope": M.APPROVAL_SCOPE,
        "run_id": "e" * 32,
        "issued_utc": utc(now - timedelta(minutes=1)),
        "expires_utc": utc(now + timedelta(minutes=9)),
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


class MAC12B12ExistingV1Negatives(unittest.TestCase):

    def test_modified_public_pem_bytes_denied_before_crypto(self):
        with tempfile.TemporaryDirectory() as tmp:
            lab = Path(tmp)
            approval = lab / M.APPROVAL_DIR_NAME
            approval.mkdir(mode=0o700)
            valid_key = b"-----BEGIN PUBLIC KEY-----\nAA==\n-----END PUBLIC KEY-----\n"
            for name, data in (
                ("mac12c_one_use.json", b"{}"),
                ("mac12c_one_use.sig", b"placeholder"),
                ("trusted_approver_public.pem", valid_key + b"\n"),
            ):
                path = approval / name
                path.write_bytes(data)
                path.chmod(0o600)

            trusted = hashlib.sha256(valid_key).hexdigest()
            with mock.patch.object(M, "LAB", lab), \
                 mock.patch.object(M, "TRUSTED_APPROVER_PUBLIC_KEY_SHA256",
                                   trusted), \
                 mock.patch.object(M, "command",
                                   side_effect=AssertionError("crypto reached")), \
                 mock.patch.object(M, "docker",
                                   side_effect=AssertionError("Docker reached")):
                with self.assertRaises(M.Blocked) as caught:
                    M.load_signed_approval()
            self.assertEqual(str(caught.exception),
                             "UNTRUSTED_APPROVAL_SIGNER")

    def test_swapped_rsa_signature_denied_before_claim_or_worker(self):
        with tempfile.TemporaryDirectory() as tmp:
            lab = Path(tmp)
            approval = lab / M.APPROVAL_DIR_NAME
            approval.mkdir(mode=0o700)
            private_a = approval / "test_only_a.pem"
            private_b = approval / "test_only_b.pem"
            public_a = approval / "trusted_approver_public.pem"
            manifest = approval / "mac12c_one_use.json"
            signature = approval / "mac12c_one_use.sig"

            for args in (
                ["openssl", "genrsa", "-out", str(private_a), "2048"],
                ["openssl", "genrsa", "-out", str(private_b), "2048"],
                ["openssl", "rsa", "-in", str(private_a),
                 "-pubout", "-out", str(public_a)],
            ):
                subprocess.run(args, check=True, capture_output=True,
                               timeout=20)

            manifest.write_text(json.dumps(grant(), sort_keys=True) + "\n",
                                encoding="utf-8")
            subprocess.run(
                ["openssl", "dgst", "-sha256", "-sign", str(private_b),
                 "-out", str(signature), str(manifest)],
                check=True, capture_output=True, timeout=20
            )
            for path in (private_a, private_b, public_a, manifest, signature):
                path.chmod(0o600)

            fingerprint = hashlib.sha256(public_a.read_bytes()).hexdigest()
            with mock.patch.object(M, "LAB", lab), \
                 mock.patch.object(M, "TRUSTED_APPROVER_PUBLIC_KEY_SHA256",
                                   fingerprint), \
                 mock.patch.object(M, "verify_source",
                                   side_effect=AssertionError("source reached")), \
                 mock.patch.object(M, "verify_docker",
                                   side_effect=AssertionError("Docker reached")), \
                 mock.patch.object(M, "claim_one_use",
                                   side_effect=AssertionError("claim reached")):
                with self.assertRaises(M.Blocked) as caught:
                    M.execute_one_use()

            self.assertIn(
                str(caught.exception),
                {"COMMAND_FAILED_RAW_OUTPUT_SUPPRESSED",
                 "APPROVAL_SIGNATURE_INVALID"}
            )
            self.assertFalse((approval / "claims").exists())

    def test_nested_duplicate_json_key_denied(self):
        duplicate = '{"outer":{"run_id":"a","run_id":"b"}}'
        with self.assertRaises(M.Blocked) as caught:
            json.loads(duplicate, object_pairs_hook=M.reject_duplicate_json_keys)
        self.assertEqual(str(caught.exception), "DUPLICATE_APPROVAL_JSON_KEY")

    def test_two_simultaneous_claims_accept_exactly_one(self):
        with tempfile.TemporaryDirectory() as tmp:
            approval = Path(tmp)
            claims = approval / "claims"
            claims.mkdir(mode=0o700)
            claims.chmod(0o700)
            barrier = threading.Barrier(2)
            sample = grant()

            def attempt():
                barrier.wait(timeout=15)
                try:
                    M.claim_one_use(sample)
                    return "CLAIMED"
                except M.Blocked as error:
                    return str(error)

            with mock.patch.object(M, "approval_directory",
                                   return_value=approval):
                with ThreadPoolExecutor(max_workers=2) as pool:
                    first = pool.submit(attempt)
                    second = pool.submit(attempt)
                    results = [first.result(timeout=20),
                               second.result(timeout=20)]

            self.assertEqual(sorted(results),
                             ["CLAIMED", "ONE_USE_ALREADY_CLAIMED_NO_RETRY"])
            marker = claims / ("claimed_" + sample["run_id"])
            self.assertEqual(marker.read_text(encoding="ascii"),
                             sample["source_commit"] + "\n")
            self.assertEqual(marker.stat().st_mode & 0o777, 0o600)

    def test_wrong_signed_source_commit_denied_before_docker(self):
        sample = grant()
        def fake_git(argv, timeout=15):
            if argv[-2:] == ["branch", "--show-current"]:
                return M.REPO_BRANCH
            if argv[-2:] == ["rev-parse", "HEAD"]:
                return "f" * 40
            raise AssertionError("unexpected git command")

        with mock.patch.object(M, "command", side_effect=fake_git), \
             mock.patch.object(M, "docker",
                               side_effect=AssertionError("Docker reached")):
            with self.assertRaises(M.Blocked) as caught:
                M.verify_source(sample)
        self.assertEqual(str(caught.exception), "SOURCE_COMMIT_DRIFT")

    def test_approval_public_key_symlink_rejected_before_crypto(self):
        with tempfile.TemporaryDirectory() as tmp:
            lab = Path(tmp)
            approval = lab / M.APPROVAL_DIR_NAME
            approval.mkdir(mode=0o700)
            for name in ("mac12c_one_use.json", "mac12c_one_use.sig"):
                path = approval / name
                path.write_bytes(b"test-only")
                path.chmod(0o600)
            (approval / "trusted_approver_public.pem").symlink_to(
                approval / "mac12c_one_use.json")
            with mock.patch.object(M, "LAB", lab), \
                 mock.patch.object(M, "TRUSTED_APPROVER_PUBLIC_KEY_SHA256",
                                   "f" * 64), \
                 mock.patch.object(M, "command",
                                   side_effect=AssertionError("crypto reached")):
                with self.assertRaises(M.Blocked) as caught:
                    M.load_signed_approval()
            self.assertEqual(str(caught.exception), "APPROVAL_FILE_NOT_PRIVATE")

    def test_extra_snapshot_file_denied_before_mount(self):
        with tempfile.TemporaryDirectory() as tmp:
            lab = Path(tmp)
            snapshot = Path(tempfile.mkdtemp(
                prefix="b12_mac12b8_src_", dir=lab))
            with mock.patch.object(M, "LAB", lab):
                M.stage_source_snapshot(snapshot)
                unwanted = snapshot / "lib" / "injected.txt"
                unwanted.write_text("test-only", encoding="ascii")
                with self.assertRaises(M.Blocked) as caught:
                    M.php_command("unused", "b12-mac12b3-" + "a" * 16,
                                  lab, snapshot)
            self.assertEqual(str(caught.exception),
                             "SNAPSHOT_CONTENT_MANIFEST")
            self.assertTrue(snapshot.exists())

    def test_snapshot_symlink_denied_before_mount(self):
        with tempfile.TemporaryDirectory() as tmp:
            lab = Path(tmp)
            snapshot = Path(tempfile.mkdtemp(
                prefix="b12_mac12b8_src_", dir=lab))
            with mock.patch.object(M, "LAB", lab):
                M.stage_source_snapshot(snapshot)
                victim = snapshot / M.HARNESS
                victim.unlink()
                victim.symlink_to(snapshot / "database_schema.sql")
                with self.assertRaises(M.Blocked) as caught:
                    M.php_command("unused", "b12-mac12b3-" + "a" * 16,
                                  lab, snapshot)
            self.assertEqual(str(caught.exception), "SNAPSHOT_ENTRY_UNSAFE")

    def test_snapshot_permission_drift_denied_before_mount(self):
        with tempfile.TemporaryDirectory() as tmp:
            lab = Path(tmp)
            snapshot = Path(tempfile.mkdtemp(
                prefix="b12_mac12b8_src_", dir=lab))
            with mock.patch.object(M, "LAB", lab):
                M.stage_source_snapshot(snapshot)
                victim = snapshot / M.HARNESS
                victim.chmod(0o600)
                with self.assertRaises(M.Blocked) as caught:
                    M.php_command("unused", "b12-mac12b3-" + "a" * 16,
                                  lab, snapshot)
            self.assertEqual(str(caught.exception), "SNAPSHOT_SOURCE_DRIFT")

    def test_failure_after_real_local_claim_keeps_spent_run_id(self):
        with tempfile.TemporaryDirectory() as tmp:
            lab = Path(tmp)
            approval = lab / M.APPROVAL_DIR_NAME
            approval.mkdir(mode=0o700)
            claims = approval / "claims"
            claims.mkdir(mode=0o700)
            sample = grant()

            with mock.patch.object(M, "LAB", lab), \
                 mock.patch.object(M, "approval_directory",
                                   return_value=approval), \
                 mock.patch.object(M, "load_signed_approval",
                                   return_value=sample), \
                  mock.patch.object(M, "verify_human_launch_decision",
                                    return_value="f"*64), \
                 mock.patch.object(M, "verify_source"), \
                 mock.patch.object(M, "verify_docker",
                                   return_value="test-only-db"), \
                 mock.patch.object(M, "verify_schema_empty"), \
                 mock.patch.object(M, "docker", return_value=""), \
                 mock.patch.object(M, "stage_source_snapshot",
                                   side_effect=M.Blocked(
                                       "INJECTED_SNAPSHOT_FAILURE")), \
                 mock.patch.object(M.subprocess, "Popen",
                                   side_effect=AssertionError("worker reached")):
                with contextlib.redirect_stderr(io.StringIO()):
                    with self.assertRaises(M.Blocked) as caught:
                        M.execute_one_use()

            self.assertEqual(str(caught.exception),
                             "INJECTED_SNAPSHOT_FAILURE")
            marker = claims / ("claimed_" + sample["run_id"])
            self.assertTrue(marker.is_file())
            self.assertEqual(marker.read_text(encoding="ascii"),
                             sample["source_commit"] + "\n")
            self.assertEqual(marker.stat().st_mode & 0o777, 0o600)


if __name__ == "__main__":
    unittest.main()
