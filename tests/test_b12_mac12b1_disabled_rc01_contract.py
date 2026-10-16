"""MAC-12B1 fixture-free source contract; never runs a database or RC01."""
from pathlib import Path
import hashlib
import re
import unittest

ROOT = Path(__file__).resolve().parents[1]
ORIGINAL = ROOT / "tests/integration/k2p03_b12_repaired_rc01_real_mariadb.php"
CANDIDATE = ROOT / "tests/integration/k2p03_b12_mac_rc01_preliminary_disabled.php"
SERVICE = ROOT / "lib/daily_record_revision_authority.php"
CUTOVER = ROOT / "lib/daily_record_revision_cutover_gate.php"

EXPECTED_BLOBS = {
    ORIGINAL: "6f17d1a4ba7e98dbf286d1c8e1a147e6ac3e4aad",
    SERVICE: "5fbb90903b0cc801be45c684f6c3db85c660979a",
    CUTOVER: "6e88c913ad5e5b371a9d0c92ce08870214ac1268",
}

def blob(path: Path) -> str:
    content = path.read_bytes()
    return hashlib.sha1(b"blob " + str(len(content)).encode() + b"\0" + content).hexdigest()

class MacRc01DisabledContract(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.text = CANDIDATE.read_text(encoding="utf-8")

    def test_upstream_authority_and_gate_unchanged(self):
        for path, expected in EXPECTED_BLOBS.items():
            with self.subTest(path=path.name):
                self.assertEqual(blob(path), expected)

    def test_hard_disabled_fuse_before_dispatch(self):
        text = self.text
        self.assertRegex(text, r"const\s+B12_RC01_EXECUTION_ENABLED\s*=\s*false\s*;")
        self.assertRegex(
            text, r"if\s*\(\s*!B12_RC01_EXECUTION_ENABLED\s*\)\s*\{"
        )
        self.assertIn('B12_RC01=SOURCE_ONLY_HARD_DISABLED', text)
        self.assertIn("exit(90);", text)
        self.assertLess(text.index("exit(90);"), text.index("if (($argv[1] ?? '') === '--controller'"))

    def test_disposable_tcp_and_nonroot_worker(self):
        text = self.text
        self.assertIn("const B12_RC01_DATABASE = 'renee_b12_disposable';", text)
        self.assertIn("mysql:host=127.0.0.1;port=3306;dbname=renee_b12_disposable;charset=utf8mb4", text)
        self.assertIn("B12_RC01_TCP_ATTESTED", text)
        self.assertIn("B12_RC01_ISOLATED_MAC_ATTESTED", text)
        self.assertIn("'b12_test', $password", text)
        self.assertNotIn("B12_RC01_SOCKET", text)
        self.assertNotIn("'root', $password", text)
        self.assertNotIn("INNODB_LOCK_WAITS w", text)

    def test_host_observer_is_external_and_bound_to_connection_ids(self):
        text = self.text
        self.assertIn("function rc01_confirm_host_observed_lock_wait(", text)
        self.assertIn("rc01_wait($dir, 'OBSERVER_PASS', 4.0);", text)
        self.assertIn("$raw === $aId . ':' . $bId", text)
        self.assertIn("rc01_confirm_host_observed_lock_wait($dir, $aSession, $bSession)", text)
        self.assertIn("|OBSERVER_PASS)", text)
        self.assertIn("B12_RC01_BARRIER_PARENT", text)
        self.assertIn("$parent === '/b12-run'", text)

    def test_shared_cas_barriers_and_exact_fixture_teardown_remain(self):
        text = self.text
        for marker in (
            "daily_record_revision_write(",
            "proc_open([PHP_BINARY,__FILE__,'--worker'",
            "rc01_signal($dir,'GO')",
            "rc01_signal($dir,'RELEASE')",
            "rc01_wait($dir,'B_ATTEMPT')",
            "'STALE_CONFLICT'",
            "rc01_cleanup($pdo,$farm,$cycle,$id)",
            "rc01_metadata_gate($pdo)",
            "RC01_TWO_CONNECTIONS=PASS",
            "RC01_STALE_CONFLICT=PASS",
        ):
            with self.subTest(marker=marker):
                self.assertIn(marker, text)

    def test_no_runtime_or_deployment_operations_added(self):
        text = self.text
        self.assertNotRegex(text, r"\b(?:shell_exec|system|passthru|popen)\s*\(")
        self.assertNotIn("run_migrations.php", text)
        self.assertNotIn("CREATE USER", text)
        self.assertNotIn("GRANT PROCESS", text)
        self.assertNotIn("DROP DATABASE", text)
        self.assertNotIn("docker.sock", text)
        self.assertIn("'lib/daily_record_revision_cutover_gate.php' => '6e88c913ad5e5b371a9d0c92ce08870214ac1268'", text)

if __name__ == "__main__":
    unittest.main()
