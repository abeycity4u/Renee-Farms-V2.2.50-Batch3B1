"""Focused, fixture-free negative-policy tests for WS52-KH; zero external I/O."""
import sys
from copy import deepcopy
from pathlib import Path
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "tools"))
import b12_ws52kh_rc01_isolation_cleanup_contract as policy


# THESE ARE INVENTED STRINGS, NEVER A CLAIM ABOUT ANY REAL DOCKER IMAGE/SERVER.
_FAKE_DIGEST = "mariadb@sha256:" + "a" * 64
_FAKE_CONTAINER_ID = "b" * 64


def example_fixture():
    return {
        "image_reference": _FAKE_DIGEST,
        "image_id": "sha256:" + "c" * 64,
        "container_id": _FAKE_CONTAINER_ID,
        "docker_engine_sha256": "sha256:" + "d" * 64,
        "container_privileged": False,
        "mounts": [],
        "port_bindings": [{
            "host_ip": "127.0.0.1", "host_port": 3306,
            "container_port": 3306, "protocol": "tcp",
        }],
        "network_mode": "bridge",
        "restricted_egress_attested": True,
        "source_commit": policy.CANDIDATE_COMMIT,
        "candidate_blob": policy.CANDIDATE_BLOB,
        "original_mac_blob": policy.ORIGINAL_MAC_BLOB,
        "gate_blob": policy.GATE_BLOB,
        "authority_blob": policy.AUTHORITY_BLOB,
        "schema_blob": policy.SCHEMA_BLOB,
        "runner_environment": "github-hosted",
        "repository": policy.REPO,
        "branch": policy.CANDIDATE_BRANCH,
        "run_number": 1,
        "run_attempt": 1,
        "db_name": policy.DB_NAME,
        "server_id": 7,
        "server_version": "10.11.14-MariaDB-0+deb12u2",
        "schema_metadata": {
            "base_tables": 69,
            "total_columns": 886,
            "revision_columns": 3,
            "cycle_set_null_fks": 3,
            "nonempty_tables": 0,
            "views": 0,
            "triggers": 0,
            "routines": 0,
            "events": 0,
        },
        "approval_scope": "PRELIMINARY_NONCERTIFYING",
    }


def example_cleanup():
    return {
        "db_name": policy.DB_NAME,
        "container_id": _FAKE_CONTAINER_ID,
        "farm_id": 4,
        "cycle_id": 5,
        "layer_record_id": 6,
        "farm_slug": "b12-rc01-" + "e" * 16,
        "farm_name": "B12 RC01 disposable synthetic farm",
        "deleted_layer_rows": 1,
        "deleted_cycle_rows": 1,
        "deleted_farm_rows": 1,
        "remaining_business_rows": 0,
        "workers_running": 0,
        "open_transactions": 0,
        "container_removed": True,
        "ephemeral_volume_removed": True,
        "barrier_artifacts_disposed": True,
        "cleanup_status": "INDEPENDENT_POSTCHECK_PASS",
    }


def assess_cleanup(data):
    return policy.assess_cleanup_evidence(
        data,
        expected_container_id=_FAKE_CONTAINER_ID,
        expected_farm_id=4,
        expected_cycle_id=5,
        expected_layer_record_id=6,
        expected_slug="b12-rc01-" + "e" * 16,
    )


class GHRC01FixtureContractTests(unittest.TestCase):
    def test_literal_runtime_fuse_and_review_refs_are_unset(self):
        self.assertIs(policy.RC01_EXECUTION_ENABLED, False)
        self.assertIsNone(policy.APPROVED_MARIADB_IMAGE)
        self.assertIsNone(policy.APPROVED_RUNTIME_COMMIT)
        self.assertIsNone(policy.APPROVED_ONE_USE_RUN)

    def test_even_fictional_perfect_evidence_cannot_authorize_a_run(self):
        # A test-injected digest tests the contract's SHAPE ONLY.
        result = policy.assess_fixture_evidence(
            example_fixture(), simulated_reviewed_image=_FAKE_DIGEST
        )
        self.assertTrue(result.structure_valid, result.blockers)
        self.assertIs(result.execution_authorized, False)

    def test_unset_reviewed_digest_blocks_default_even_if_metadata_is_forged(self):
        result = policy.assess_fixture_evidence(example_fixture())
        self.assertFalse(result.structure_valid)
        self.assertIn("IMMUTABLE_MARIADB_IMAGE_UNREVIEWED", result.blockers)
        self.assertFalse(result.execution_authorized)

    def test_floating_tag_wrong_image_and_image_id_are_denied(self):
        for key, value in (
            ("image_reference", "mariadb:10.11"),
            ("image_reference", "mariadb@sha256:" + "f" * 64),
            ("image_id", "latest"),
        ):
            with self.subTest(key=key, value=value[:22]):
                fixture = example_fixture()
                fixture[key] = value
                result = policy.assess_fixture_evidence(
                    fixture, simulated_reviewed_image=_FAKE_DIGEST
                )
                self.assertFalse(result.structure_valid)
                self.assertFalse(result.execution_authorized)

    def test_network_mount_and_privilege_regressions_are_denied(self):
        changes = (
            ("mounts", ["/home/user:/data"]),
            ("container_privileged", True),
            ("network_mode", "host"),
            ("restricted_egress_attested", False),
            ("port_bindings", [{
                "host_ip": "0.0.0.0", "host_port": 3306,
                "container_port": 3306, "protocol": "tcp",
            }]),
        )
        for key, value in changes:
            with self.subTest(key=key):
                fixture = example_fixture()
                fixture[key] = value
                result = policy.assess_fixture_evidence(
                    fixture, simulated_reviewed_image=_FAKE_DIGEST
                )
                self.assertFalse(result.structure_valid)

    def test_source_identity_tenancy_scope_and_old_run_are_denied(self):
        for key, value in (
            ("source_commit", "f" * 40),
            ("authority_blob", "f" * 40),
            ("db_name", "reneefarms_prod"),
            ("repository", "other/repo"),
            ("run_attempt", 2),
            ("run_number", 2),
            ("approval_scope", "PRODUCTION_CERTIFYING"),
        ):
            with self.subTest(key=key):
                fixture = example_fixture()
                fixture[key] = value
                self.assertFalse(policy.assess_fixture_evidence(
                    fixture, simulated_reviewed_image=_FAKE_DIGEST
                ).structure_valid)

    def test_unexpected_field_or_secret_is_rejected(self):
        for field in ("password", "sql", "environment_secrets"):
            with self.subTest(field=field):
                fixture = example_fixture()
                fixture[field] = "do-not-store-secrets"
                self.assertIn("MISSING_OR_UNEXPECTED_FIXTURE_METADATA_KEYS",
                              policy.assess_fixture_evidence(fixture).blockers)

    def test_missing_schema_visibility_or_existing_rows_are_denied(self):
        for key, value in (
            ("nonempty_tables", 1),
            ("cycle_set_null_fks", 0),
            ("total_columns", 883),
            ("triggers", 1),
        ):
            with self.subTest(key=key):
                fixture = example_fixture()
                fixture["schema_metadata"][key] = value
                result = policy.assess_fixture_evidence(
                    fixture, simulated_reviewed_image=_FAKE_DIGEST
                )
                self.assertIn("SYNTHETIC_SCHEMA_NOT_EXACT_EMPTY_BASELINE",
                              result.blockers)

    def test_cleanup_fictional_shape_pass_is_not_authorization(self):
        result = assess_cleanup(example_cleanup())
        self.assertTrue(result.structure_valid, result.blockers)
        self.assertFalse(result.execution_authorized)
        self.assertEqual(
            policy.status_for_reporting(
                policy.assess_fixture_evidence(
                    example_fixture(), simulated_reviewed_image=_FAKE_DIGEST
                ), result),
            "SOURCE_CONTRACT_SHAPE_ONLY_NOT_ATTESTED",
        )

    def test_cleanup_must_match_exact_fixture_and_container(self):
        for key, value in (
            ("container_id", "c" * 64),
            ("farm_id", 3),
            ("cycle_id", 9),
            ("layer_record_id", 10),
            ("farm_slug", "somebody-else"),
            ("farm_name", "Actual customer farm"),
        ):
            with self.subTest(key=key):
                cleanup = example_cleanup()
                cleanup[key] = value
                self.assertFalse(assess_cleanup(cleanup).structure_valid)

    def test_cleanup_incomplete_workers_rows_or_files_requires_review(self):
        changes = (
            ("deleted_layer_rows", 0),
            ("deleted_cycle_rows", 2),
            ("remaining_business_rows", 1),
            ("workers_running", 1),
            ("open_transactions", 1),
            ("container_removed", False),
            ("ephemeral_volume_removed", False),
            ("barrier_artifacts_disposed", False),
            ("cleanup_status", "INCOMPLETE_MANUAL_REVIEW"),
        )
        for key, value in changes:
            with self.subTest(key=key):
                cleanup = example_cleanup()
                cleanup[key] = value
                outcome = assess_cleanup(cleanup)
                self.assertFalse(outcome.structure_valid)
                self.assertFalse(outcome.execution_authorized)
                self.assertEqual(
                    policy.status_for_reporting(
                        policy.assess_fixture_evidence(example_fixture()), outcome
                    ), "BLOCKED_MANUAL_REVIEW"
                )

    def test_missing_data_wrong_types_and_boolean_count_fail_closed(self):
        fixture = example_fixture()
        del fixture["schema_metadata"]
        self.assertFalse(policy.assess_fixture_evidence(fixture).structure_valid)
        for key, value in (("server_id", True), ("run_attempt", True)):
            with self.subTest(key=key):
                changed = example_fixture()
                changed[key] = value
                self.assertFalse(policy.assess_fixture_evidence(
                    changed, simulated_reviewed_image=_FAKE_DIGEST
                ).structure_valid)
        cleanup = example_cleanup()
        cleanup["remaining_business_rows"] = False
        self.assertFalse(assess_cleanup(cleanup).structure_valid)
        cleanup = example_cleanup()
        cleanup["db_password"] = "should-never-be-in-evidence"
        self.assertFalse(assess_cleanup(cleanup).structure_valid)


if __name__ == "__main__":
    unittest.main()
