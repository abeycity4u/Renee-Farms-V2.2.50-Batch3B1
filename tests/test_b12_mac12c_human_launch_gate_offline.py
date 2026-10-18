"""Offline MAC-12C human launch-decision tests.

No operational signing, Docker, SQL or worker execution.
"""
from datetime import datetime, timedelta, timezone
import importlib.util
import json
import os
from pathlib import Path
import tempfile
import unittest
from unittest import mock

ROOT = Path(__file__).resolve().parents[1]
SUPERVISOR = ROOT / "tools/b12_mac12b3_rc01_supervisor_disabled.py"
SPEC = importlib.util.spec_from_file_location("mac12c_human_gate", SUPERVISOR)
M = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(M)


def utc(value):
    return value.strftime("%Y-%m-%dT%H:%M:%SZ")


class HumanLaunchGateOffline(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.lab = Path(self.temp.name)
        patcher = mock.patch.object(M, "LAB", self.lab)
        patcher.start()
        self.addCleanup(patcher.stop)

        self.case = self.lab / ".b12-evidence/mac12c-r01"
        self.preflight = self.case / "preflight"
        (self.lab / ".b12-evidence").mkdir(mode=0o700)
        self.case.mkdir(mode=0o700)
        self.preflight.mkdir(mode=0o700)

        role = self.case / "role_assignment.txt"
        role.write_text(
            "RESPONSIBLE_PERSON=Abiodun Ayinde\n"
            "INDEPENDENT_HUMAN_OVERSIGHT=NO\n"
            "HUMAN_ROLE_ASSIGNMENT=CONFIRMED_IN_CHAT\n"
        )
        role.chmod(0o600)

        hold = self.case / "authorization_record.txt"
        hold.write_text(
            "RECORD_TYPE=PREAUTHORIZATION_HOLD_RECORD\n"
            "HUMAN_LAUNCH_APPROVAL=NOT_ISSUED\n"
        )
        hold.chmod(0o600)

        now = datetime.now(timezone.utc).replace(microsecond=0)
        self.grant = {
            "contract": M.APPROVAL_CONTRACT,
            "scope": M.APPROVAL_SCOPE,
            "run_id": "a" * 32,
            "issued_utc": utc(now - timedelta(minutes=1)),
            "expires_utc": utc(now + timedelta(minutes=9)),
            "repository": "abeycity4u/Renee-Farms-V2.2.50-Batch3B1",
            "branch": M.REPO_BRANCH,
            "source_commit": "b" * 40,
            "supervisor_blob": "c" * 40,
            "harness_blob": M.SOURCE_BLOBS[M.HARNESS],
            "database": M.DATABASE,
            "php_image_id": M.PHP_IMAGE,
            "maximum_runs": 1,
            "synthetic_only": True,
            "production_contact": False
        }
        self.decision = {
            "contract": M.HUMAN_LAUNCH_CONTRACT,
            "decision": "APPROVE_ONE_PRELIMINARY_RC01_LAUNCH",
            "decision_id": "MAC12C-R01-TEST01",
            "decision_utc": utc(now),
            "authorizer": "Abiodun Ayinde",
            "operator": "Abiodun Ayinde",
            "security_model": "R-A_LIMITED_SYNTHETIC_ONLY",
            "authorized_actions": list(M.HUMAN_LAUNCH_ACTIONS),
            "independent_human_oversight": False,
            "launch_approved": True,
            "production_contact": False
        }
        for field in (
            "repository", "branch", "run_id", "source_commit",
            "supervisor_blob", "harness_blob", "issued_utc",
            "expires_utc", "scope", "database", "php_image_id"
        ):
            self.decision[field] = self.grant[field]

        self.path = self.preflight / "human_oneuse_launch.json"

    def write_decision(self, data=None):
        if data is None:
            data = self.decision
        self.path.write_text(json.dumps(data))
        self.path.chmod(0o600)

    def test_both_execution_fuses_remain_off(self):
        self.assertIs(M.EXECUTION_ENABLED, False)
        php = (
            ROOT / M.HARNESS
        ).read_text(encoding="utf-8")
        self.assertIn(
            "const B12_RC01_EXECUTION_ENABLED = false;", php
        )

    def test_valid_exact_human_decision(self):
        self.write_decision()
        digest = M.verify_human_launch_decision(self.grant)
        self.assertEqual(len(digest), 64)

    def test_missing_decision_denied(self):
        with self.assertRaises(M.Blocked):
            M.verify_human_launch_decision(self.grant)

    def test_role_assignment_is_not_launch_approval(self):
        with mock.patch.object(
            M, "verify_source",
            side_effect=AssertionError("source reached")
        ), mock.patch.object(
            M, "load_signed_approval",
            return_value=self.grant
        ), mock.patch.object(
            M, "docker",
            side_effect=AssertionError("Docker reached")
        ):
            with self.assertRaises(M.Blocked):
                M.execute_one_use()

    def test_wrong_run_id_denied(self):
        self.decision["run_id"] = "d" * 32
        self.write_decision()
        with self.assertRaises(M.Blocked):
            M.verify_human_launch_decision(self.grant)

    def test_wrong_source_commit_denied(self):
        self.decision["source_commit"] = "e" * 40
        self.write_decision()
        with self.assertRaises(M.Blocked):
            M.verify_human_launch_decision(self.grant)

    def test_future_decision_denied(self):
        self.decision["decision_utc"] = utc(
            datetime.now(timezone.utc) + timedelta(minutes=1)
        )
        self.write_decision()
        with self.assertRaises(M.Blocked):
            M.verify_human_launch_decision(self.grant)

    def test_decision_before_signed_grant_denied(self):
        self.decision["decision_utc"] = utc(
            datetime.now(timezone.utc) - timedelta(minutes=5)
        )
        self.write_decision()
        with self.assertRaises(M.Blocked):
            M.verify_human_launch_decision(self.grant)

    def test_missing_worker_authorization_denied(self):
        self.decision["authorized_actions"].remove(
            "TWO_WORKER_LAYER_EDIT_EDIT_RACE"
        )
        self.write_decision()
        with self.assertRaises(M.Blocked):
            M.verify_human_launch_decision(self.grant)

    def test_false_approval_denied(self):
        self.decision["launch_approved"] = False
        self.write_decision()
        with self.assertRaises(M.Blocked):
            M.verify_human_launch_decision(self.grant)

    def test_extra_fields_denied(self):
        self.decision["unexpected"] = True
        self.write_decision()
        with self.assertRaises(M.Blocked):
            M.verify_human_launch_decision(self.grant)

    def test_insecure_file_permissions_denied(self):
        self.write_decision()
        self.path.chmod(0o644)
        with self.assertRaises(M.Blocked):
            M.verify_human_launch_decision(self.grant)

    def test_symlink_decision_denied(self):
        target = self.preflight / "untrusted.json"
        target.write_text(json.dumps(self.decision))
        target.chmod(0o600)
        self.path.symlink_to(target)
        with self.assertRaises(M.Blocked):
            M.verify_human_launch_decision(self.grant)

    def test_duplicate_json_keys_denied(self):
        self.write_decision()
        original = self.path.read_text()
        duplicate = original.replace(
            '"launch_approved": true',
            '"launch_approved": true, "launch_approved": true',
            1
        )
        self.assertNotEqual(original, duplicate)
        self.path.write_text(duplicate)
        self.path.chmod(0o600)
        with self.assertRaises(M.Blocked):
            M.verify_human_launch_decision(self.grant)

    def test_historical_hold_record_must_remain(self):
        self.write_decision()
        (self.case / "authorization_record.txt").write_text(
            "HUMAN_LAUNCH_APPROVAL=APPROVED\\n"
        )
        with self.assertRaises(M.Blocked):
            M.verify_human_launch_decision(self.grant)


if __name__ == "__main__":
    unittest.main()
