"""WS52-KA source-only contract checks; never imports or executes the RC01 PHP runner.

Run with: python3 -B -m unittest discover -s tests -p 'test_b12_ws52ka_rc01_offline_contract.py' -v
PHP 8.2 lint must be performed separately against the exact committed PHP blob.
"""
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[1]
RUNNER = ROOT / "tests/integration/k2p03_b12_repaired_rc01_real_mariadb.php"


def invariants(text: str) -> dict[str, bool]:
    """All checks are purely in-memory string contracts, NOT PHP/DB execution."""
    disabled = "const B12_RC01_EXECUTION_ENABLED = false;" in text
    gate = text.find("if (!B12_RC01_EXECUTION_ENABLED) {")
    controller = text.find("rc01_controller();", gate) if gate >= 0 else -1
    return {
        "hard_off": disabled and "B12_RC01_EXECUTION_ENABLED = true" not in text,
        "fuse_precedes_routing": gate >= 0 and controller > gate
            and 'exit(90);' in text[gate:controller],
        "pinned_cas_blob": "'lib/daily_record_revision_authority.php'"
            in text and "'5fbb90903b0cc801be45c684f6c3db85c660979a'"
            in text,
        "pinned_gate_blob": "'6e88c913ad5e5b371a9d0c92ce08870214ac1268'"
            in text,
        "runner_hash_approval": "B12_RC01_APPROVED_HARNESS_BLOB" in text
            and 'sha1(' in text and 'hash_equals($approvedHarness' in text,
        "all_tables_preflight": "foreach ($objects as $object)" in text
            and "all synthetic tables initially empty" in text
            and "count($objects) === 69" in text,
        "no_unreviewed_programs": all(x in text for x in (
            "'TRIGGERS' => 'trigger_schema'",
            "'ROUTINES' => 'routine_schema'",
            "'EVENTS' => 'event_schema'")),
        "server_observed_lock_wait": all(x in text for x in (
            "information_schema.INNODB_LOCK_WAITS",
            "information_schema.INNODB_TRX",
            "rc01_confirm_innodb_lock_wait($pdo, $aSession, $bSession);",
            "'B_ATTEMPT'")),
        "worker_id_bound": "rc01_ready_connection_id($dir, 'A')" in text
            and "rc01_ready_connection_id($dir, 'B')" in text
            and "$result['A']['connection_id'] === $aSession" in text
            and "$result['B']['connection_id'] === $bSession" in text,
        "canonical_shared_cas": "daily_record_revision_write(" in text
            and "['mortality' => $role === 'A' ? 4 : 3]" in text,
        "preserve_first_error": "$firstFailure = $error;" in text
            and "throw $firstFailure;" in text,
        "no_cleanup_with_live_workers": "if ($fixture !== null && $workersStopped)" in text
            and "WORKER_STILL_RUNNING" in text
            and "FIXTURE_QUARANTINE_REQUIRED" in text,
        "barrier_quarantine": "BARRIER_QUARANTINED" in text
            and "BARRIER_DISPOSAL_FAILED" in text,
        "pass_after_cleanup": text.index("rc01_metadata_gate($pdo);", text.index(
            "function rc01_controller(): void")) <
            text.rfind("RC01_FIXTURE_TEARDOWN=PASS")
            and "rc01_assert($teardownIssues === [], 'full teardown completed');"
            in text,
        "no_on_switch": not re.search(
            r"\bB12_RC01_EXECUTION_ENABLED\s*=\s*true\b", text)
    }


class RC01OfflineSourceContract(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.text = RUNNER.read_text(encoding="utf-8")

    def test_source_guards(self):
        checks = invariants(self.text)
        for name, ok in checks.items():
            with self.subTest(name=name):
                self.assertTrue(ok, "static contract denied: " + name)

    def test_mutated_fuse_rejected(self):
        mutated = self.text.replace(
            "const B12_RC01_EXECUTION_ENABLED = false;",
            "const B12_RC01_EXECUTION_ENABLED = true;", 1)
        self.assertFalse(invariants(mutated)["hard_off"])

    def test_missing_observer_rejected(self):
        mutated = self.text.replace(
            "rc01_confirm_innodb_lock_wait($pdo, $aSession, $bSession);",
            "/* removed test lock-state observation */", 1)
        self.assertFalse(invariants(mutated)["server_observed_lock_wait"])

    def test_incomplete_fixture_check_rejected(self):
        mutated = self.text.replace(
            "all synthetic tables initially empty",
            "only selected tables checked", 1)
        self.assertFalse(invariants(mutated)["all_tables_preflight"])

    def test_dropped_first_failure_rejected(self):
        mutated = self.text.replace("throw $firstFailure;", "return;", 1)
        self.assertFalse(invariants(mutated)["preserve_first_error"])

    def test_quarantine_omission_rejected(self):
        mutated = self.text.replace("BARRIER_QUARANTINED", "SILENTLY_REMOVED", 1)
        self.assertFalse(invariants(mutated)["barrier_quarantine"])

    def test_no_php_execution_or_database_dependencies(self):
        # This Python verifier reads a local source file only. It does not
        # launch PHP, Docker, MariaDB, CI, or any external process.
        source = Path(__file__).read_text(encoding="utf-8")
        self.assertNotIn("subprocess." + "run(", source)
        self.assertNotIn("os." + "system(", source)
        self.assertNotIn("import " + "pymysql", source)


if __name__ == "__main__":
    unittest.main()
