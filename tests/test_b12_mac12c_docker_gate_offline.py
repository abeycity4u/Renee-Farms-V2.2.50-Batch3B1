"""Offline negative tests for MAC-12C pinned Docker safeguards.

No real Docker, SQL, controller, signing, or worker operations.
"""
import copy
import importlib.util
import os
from pathlib import Path
import tempfile
import unittest
from unittest import mock

ROOT = Path(__file__).resolve().parents[1]
PATH = ROOT / "tools/b12_mac12b3_rc01_supervisor_disabled.py"
SPEC = importlib.util.spec_from_file_location("mac12c_docker_guard", PATH)
M = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(M)


class DockerGateOffline(unittest.TestCase):
    def test_hardening_fuse_remains_off(self):
        self.assertIs(M.EXECUTION_ENABLED, False)

    def test_docker_command_uses_exact_host(self):
        self.assertEqual(
            M.docker_argv("ps"),
            [
                M.DOCKER_EXECUTABLE,
                "--host=unix://" + str(M.DOCKER_SOCKET),
                "ps"
            ]
        )

    def test_remote_override_rejected_before_docker(self):
        with mock.patch.object(M.sys, "platform", "darwin"),              mock.patch.dict(
                 os.environ,
                 {"DOCKER_HOST": "tcp://example.invalid:2375"},
                 clear=True
             ),              mock.patch.object(
                 M, "docker",
                 side_effect=AssertionError("Docker reached")
             ):
            with self.assertRaises(M.Blocked):
                M.verify_docker()

    def test_missing_socket_rejected_before_docker(self):
        with tempfile.TemporaryDirectory() as tmp:
            missing = Path(tmp) / "no-socket"
            with mock.patch.object(M.sys, "platform", "darwin"),                  mock.patch.dict(os.environ, {}, clear=True),                  mock.patch.object(M, "DOCKER_SOCKET", missing),                  mock.patch.object(
                     M, "docker",
                     side_effect=AssertionError("Docker reached")
                 ):
                with self.assertRaises(M.Blocked):
                    M.verify_docker()

    def sample_mounts(self, lab):
        return [
            {
                "Destination": "/var/lib/mysql",
                "Type": "volume",
                "Name": M.VOLUME,
                "RW": True
            },
            {
                "Destination": "/run/secrets/root_password",
                "Type": "bind",
                "Source": str(lab / ".b12-secrets/root_password"),
                "RW": False
            },
            {
                "Destination": "/run/secrets/test_password",
                "Type": "bind",
                "Source": str(lab / ".b12-secrets/test_password"),
                "RW": False
            }
        ]

    def test_exact_three_mounts_accepted(self):
        lab = Path("/Users/synthetic/renee-b12-rc-mac")
        with mock.patch.object(M, "LAB", lab):
            self.assertIsNone(
                M.verify_database_mounts(self.sample_mounts(lab))
            )

    def test_extra_mount_rejected(self):
        lab = Path("/Users/synthetic/renee-b12-rc-mac")
        mounts = self.sample_mounts(lab)
        mounts.append({
            "Destination": "/unexpected",
            "Type": "bind",
            "Source": "/tmp/extra",
            "RW": False
        })
        with mock.patch.object(M, "LAB", lab):
            with self.assertRaises(M.Blocked):
                M.verify_database_mounts(mounts)

    def test_writable_secret_rejected(self):
        lab = Path("/Users/synthetic/renee-b12-rc-mac")
        mounts = copy.deepcopy(self.sample_mounts(lab))
        mounts[2]["RW"] = True
        with mock.patch.object(M, "LAB", lab):
            with self.assertRaises(M.Blocked):
                M.verify_database_mounts(mounts)

    def test_wrong_secret_source_rejected(self):
        lab = Path("/Users/synthetic/renee-b12-rc-mac")
        mounts = copy.deepcopy(self.sample_mounts(lab))
        mounts[1]["Source"] = "/tmp/untrusted_password"
        with mock.patch.object(M, "LAB", lab):
            with self.assertRaises(M.Blocked):
                M.verify_database_mounts(mounts)

    def test_no_unpinned_docker_commands(self):
        source = PATH.read_text(encoding="utf-8")
        self.assertNotIn('["docker",', source)
        self.assertNotIn('"docker", "run"', source)
        self.assertIn('docker_argv("exec"', source)
        self.assertIn('docker_argv("stop"', source)


if __name__ == "__main__":
    unittest.main()
