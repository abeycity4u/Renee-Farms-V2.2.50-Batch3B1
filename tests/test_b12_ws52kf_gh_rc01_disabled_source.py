"""WS52-KF fixture-free GitHub RC01 candidate safeguards.

These tests read source text only; they do not invoke PHP, SQL, Docker, Actions,
network, a production backup or any application code.
"""
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[1]
ORIGINAL = ROOT / "tests/integration/k2p03_b12_repaired_rc01_real_mariadb.php"
CANDIDATE = ROOT / "tests/integration/k2p03_b12_gh_rc01_preliminary_synthetic.php"
WORKFLOW = ROOT / ".github/workflows/c137-b12-ws52kf-rc01-gh-candidate-disabled.yml"
APPROVAL = ROOT / "docs/C137_GC24_K2_P03_B12_WS52KF_ONE_USE_RC01_APPROVAL.txt"
ANCHOR = "function rc01_metadata_gate(PDO $pdo): void"


class B12GHPreliminarySourceContracts(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.original = ORIGINAL.read_text("utf-8")
        cls.candidate = CANDIDATE.read_text("utf-8")
        cls.workflow = WORKFLOW.read_text("utf-8")

    def test_both_harnesses_hard_disabled(self):
        for source in (self.original, self.candidate):
            with self.subTest(source_length=len(source)):
                self.assertEqual(
                    source.count("const B12_RC01_EXECUTION_ENABLED = false;"), 1)
                self.assertNotIn("B12_RC01_EXECUTION_ENABLED = true", source)
                fuse = source.index("if (!B12_RC01_EXECUTION_ENABLED)")
                controller = source.index("rc01_controller();", fuse)
                self.assertIn("exit(90);", source[fuse:controller])

    def test_exact_same_shared_cas_race_and_cleanup_body(self):
        self.assertEqual(self.original.count(ANCHOR), 1)
        self.assertEqual(self.candidate.count(ANCHOR), 1)
        self.assertEqual(
            self.original.split(ANCHOR, 1)[1],
            self.candidate.split(ANCHOR, 1)[1])

    def test_github_runner_identity_replaces_mac_only(self):
        candidate = self.candidate
        self.assertNotIn("B12_RC01_ISOLATED_MAC_ATTESTED", candidate)
        self.assertNotIn("B12_RC01_SOCKET", candidate)
        for marker in (
            "GITHUB_ACTIONS", "RUNNER_ENVIRONMENT", "GITHUB_REPOSITORY",
            "GITHUB_REF", "GITHUB_RUN_ATTEMPT",
            "B12_RC01_GH_ISOLATION_ATTESTED",
            "B12_RC01_APPROVED_HARNESS_BLOB",
            "B12_RC01_EXPECTED_SERVER_ID",
            "127.0.0.1", "10.11.", "SELECT @@server_id"
        ):
            with self.subTest(marker=marker):
                self.assertIn(marker, candidate)

    def test_unchanged_product_source_guards(self):
        for marker in (
            "5fbb90903b0cc801be45c684f6c3db85c660979a",
            "6e88c913ad5e5b371a9d0c92ce08870214ac1268",
            "a0ddbec542e891d484b48d1adbdac17442eaadf8",
            "e77f287d62c5812a3d55dc23b5b20f5d167db389",
            "daily_record_revision_write("
        ):
            with self.subTest(marker=marker):
                self.assertIn(marker, self.candidate)

    def test_approval_trigger_is_absent(self):
        self.assertFalse(APPROVAL.exists())
        self.assertIn(
            "docs/C137_GC24_K2_P03_B12_WS52KF_ONE_USE_RC01_APPROVAL.txt",
            self.workflow)

    def test_job_is_both_disabled_and_fail_closed(self):
        workflow = self.workflow
        self.assertRegex(workflow, r"(?m)^    if: \$\{\{ false \}\}\s*$")
        self.assertIn("exit 86", workflow)
        self.assertIn("B12_RC01_GH_ISOLATION_ATTESTED: \"NO\"", workflow)
        self.assertIn("B12_RC01_APPROVED_HARNESS_BLOB: \"\"", workflow)
        self.assertNotIn("services:", workflow)
        self.assertNotIn("workflow_dispatch:", workflow)
        self.assertNotIn("pull_request:", workflow)
        self.assertNotIn("mariadb:", workflow)

    def test_workflow_no_privileged_production_permissions(self):
        workflow = self.workflow
        self.assertIn("permissions:\n  contents: read", workflow)
        self.assertIn("runs-on: ubuntu-24.04", workflow)
        self.assertIn("cancel-in-progress: false", workflow)
        self.assertNotIn("secrets.", workflow)
        self.assertNotIn("environment: production", workflow)


if __name__ == "__main__":
    unittest.main()
