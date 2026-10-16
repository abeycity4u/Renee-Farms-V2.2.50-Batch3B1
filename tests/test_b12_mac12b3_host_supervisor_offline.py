"""Offline-only safety tests for hard-disabled MAC-12B3 host supervisor."""
import contextlib
import importlib.util
import io
from pathlib import Path
import tempfile
import unittest
from unittest import mock

ROOT = Path(__file__).resolve().parents[1]
CANDIDATE = ROOT / "tools/b12_mac12b3_rc01_supervisor_disabled.py"
SPEC = importlib.util.spec_from_file_location("b12_mac12b3", CANDIDATE)
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)


class SupervisorOfflineContract(unittest.TestCase):
    def test_literal_fuse_and_approval_sentinels_are_off(self):
        self.assertIs(MODULE.EXECUTION_ENABLED, False)
        self.assertIsNone(MODULE.APPROVED_ONE_USE_RUN)
        self.assertIsNone(MODULE.APPROVED_RUNTIME_COMMIT)
        self.assertIsNone(MODULE.APPROVED_SUPERVISOR_BLOB)

    def test_off_path_has_no_docker_network_or_db_calls(self):
        with mock.patch.object(MODULE.subprocess, "run",
                               side_effect=AssertionError("subprocess invoked")):
            with mock.patch.object(MODULE.subprocess, "Popen",
                                   side_effect=AssertionError("worker invoked")):
                with mock.patch.object(MODULE, "execute_one_use",
                                       side_effect=AssertionError("execution invoked")):
                    stderr = io.StringIO()
                    with contextlib.redirect_stderr(stderr):
                        self.assertEqual(MODULE.main(), 90)
                    self.assertEqual(stderr.getvalue(),
                                     "B12_MAC12B3=SOURCE_ONLY_HARD_DISABLED\n")

    def test_pinned_authority_and_cutover_unchanged(self):
        pinned = {
            "lib/daily_record_revision_authority.php":
                "5fbb90903b0cc801be45c684f6c3db85c660979a",
            "lib/daily_record_revision_cutover_gate.php":
                "6e88c913ad5e5b371a9d0c92ce08870214ac1268",
            "database_schema.sql":
                "e77f287d62c5812a3d55dc23b5b20f5d167db389",
        }
        for path, expected in pinned.items():
            with self.subTest(path=path):
                self.assertEqual(MODULE.git_blob((ROOT / path).read_bytes()),
                                 expected)

    def test_mac_harness_pinned_and_still_disabled(self):
        path = ROOT / MODULE.HARNESS
        self.assertEqual(
            MODULE.git_blob(path.read_bytes()),
            "2e288c611e0b43217eb1b8e15234ee823b65f940"
        )
        source = path.read_text(encoding="utf-8")
        self.assertIn("const B12_RC01_EXECUTION_ENABLED = false;", source)
        self.assertIn("B12_RC01_DB_PASSWORD_FILE", source)
        self.assertIn("'/b12-private/test_password'", source)
        self.assertNotIn("getenv('B12_RC01_DB_PASSWORD')", source)
        self.assertIn("rc01_confirm_host_observed_lock_wait", source)

    def test_reject_bad_connection_ids_without_query(self):
        with mock.patch.object(MODULE, "db_select",
                               side_effect=AssertionError("root SQL invoked")):
            for a, b in [(0, 4), (4, 4), (4, -1), (4, 10**11)]:
                with self.subTest(a=a, b=b):
                    with self.assertRaises(MODULE.Blocked):
                        MODULE.actual_server_wait("unused", a, b)

    def test_real_observer_sql_direction_and_scope(self):
        def sample(_, sql, *, root=False):
            self.assertTrue(root)
            self.assertIn("INNODB_LOCK_WAITS", sql)
            self.assertIn("INNODB_TRX", sql)
            self.assertIn("waiting.trx_mysql_thread_id=23", sql)
            self.assertIn("blocking.trx_mysql_thread_id=21", sql)
            self.assertNotIn("DELETE", sql)
            self.assertNotIn("UPDATE", sql)
            return "1"
        with mock.patch.object(MODULE, "db_select", side_effect=sample):
            self.assertTrue(MODULE.actual_server_wait("test", 21, 23))

    def test_no_observation_does_not_count_as_proof(self):
        with mock.patch.object(MODULE, "db_select", return_value="0"):
            self.assertFalse(MODULE.actual_server_wait("test", 21, 23))

    def test_invalid_lock_metadata_fails_closed(self):
        with mock.patch.object(MODULE, "db_select", return_value="ACCESS DENIED"):
            with self.assertRaises(MODULE.Blocked):
                MODULE.actual_server_wait("test", 21, 23)

    def test_ready_session_requires_bounded_number(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            for value in ("0\n", "-1\n", "1", "1.0\n",
                          "10000000000\n", "1\n2\n"):
                (root / "A_READY").write_text(value, encoding="ascii")
                with self.subTest(value=value):
                    with self.assertRaises(MODULE.Blocked):
                        MODULE.parse_session(root, "A")
            (root / "A_READY").write_text("23\n", encoding="ascii")
            self.assertEqual(MODULE.parse_session(root, "A"), 23)

    def test_barrier_name_and_symlink_restrictions(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            (root / "wrong_barrier").mkdir()
            with self.assertRaises(MODULE.Blocked):
                MODULE.find_barrier(root)
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            (root / ("b12_rc01_" + "a"*20)).mkdir()
            self.assertEqual(MODULE.find_barrier(root).name,
                             "b12_rc01_" + "a"*20)
            (root / ("b12_rc01_" + "b"*20)).mkdir()
            with self.assertRaises(MODULE.Blocked):
                MODULE.find_barrier(root)

    def test_atomic_observer_proof_exact_pair(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            barrier = root / ("b12_rc01_" + "a"*20)
            barrier.mkdir()
            MODULE.publish_observer_proof(barrier, 15, 17)
            self.assertEqual((barrier / "OBSERVER_PASS").read_bytes(),
                             b"15:17\n")
            self.assertFalse((barrier / "OBSERVER_PASS.tmp").exists())
            with self.assertRaises(MODULE.Blocked):
                MODULE.publish_observer_proof(barrier, 15, 17)

    def test_forged_marker_is_rejected_by_supervisor(self):
        with tempfile.TemporaryDirectory() as tmp:
            barrier = Path(tmp) / ("b12_rc01_" + "a"*20)
            barrier.mkdir()
            for name, content in {
                "A_READY": "15\n", "B_READY": "17\n",
                "A_LOCKED": "1\n", "B_ATTEMPT": "1\n",
                "OBSERVER_PASS": "15:17\n",
            }.items():
                (barrier / name).write_text(content, encoding="ascii")
            fake = mock.Mock()
            fake.poll.return_value = None
            with mock.patch.object(MODULE, "actual_server_wait",
                                   side_effect=AssertionError("must not query")):
                with self.assertRaises(MODULE.Blocked):
                    MODULE.independently_observe(
                        "none", Path(tmp), fake,
                        MODULE.time.monotonic() + 0.2
                    )

    def test_proof_not_written_for_absent_wait(self):
        with tempfile.TemporaryDirectory() as tmp:
            barrier = Path(tmp) / ("b12_rc01_" + "a"*20)
            barrier.mkdir()
            for name, content in {
                "A_READY": "15\n", "B_READY": "17\n",
                "A_LOCKED": "1\n", "B_ATTEMPT": "1\n",
            }.items():
                (barrier / name).write_text(content, encoding="ascii")
            fake = mock.Mock()
            fake.poll.return_value = None
            with mock.patch.object(MODULE, "actual_server_wait",
                                   return_value=False):
                with self.assertRaises(MODULE.Blocked):
                    MODULE.independently_observe(
                        "none", Path(tmp), fake,
                        MODULE.time.monotonic() + 0.05
                    )
            self.assertFalse((barrier / "OBSERVER_PASS").exists())

    def test_no_secrets_or_root_mount_in_php_command(self):
        with tempfile.TemporaryDirectory() as tmp:
            cmd = MODULE.php_command(
                "db-identity", "b12-mac12b3-" + "a"*16, Path(tmp)
            )
        joined = " ".join(cmd)
        self.assertIn("--pull=never", joined)
        self.assertIn("--network=container:db-identity", joined)
        self.assertIn("--read-only", joined)
        self.assertIn("--cap-drop=ALL", joined)
        self.assertIn("B12_RC01_DB_PASSWORD_FILE", joined)
        self.assertNotIn("root_password", joined)
        self.assertNotIn("B12_RC01_DB_PASSWORD=", joined)
        self.assertNotIn("docker.sock", joined)
        self.assertNotIn("--publish", joined)
        self.assertIn("test_password; chmod 600", joined)

    def test_unowned_container_must_not_be_stopped(self):
        with mock.patch.object(MODULE, "docker",
                               return_value="b12-mac12b3-" + "a"*16):
            with mock.patch.object(MODULE, "inspect", return_value={
                "Config": {"Labels": {"b12.owner": "other"}},
                "Name": "/b12-mac12b3-" + "a"*16,
                "State": {"Running": True}
            }):
                with mock.patch.object(MODULE, "command",
                                       side_effect=AssertionError("stop called")):
                    with self.assertRaises(MODULE.Blocked):
                        MODULE.quarantine_or_stop_only_owned_container(
                            "b12-mac12b3-" + "a"*16
                        )

    def test_source_only_execution_sentinels_reject_direct_entry(self):
        with mock.patch.object(MODULE, "verify_source",
                               side_effect=AssertionError("source check reached")):
            with self.assertRaises(MODULE.Blocked):
                MODULE.execute_one_use()


if __name__ == "__main__":
    unittest.main()
