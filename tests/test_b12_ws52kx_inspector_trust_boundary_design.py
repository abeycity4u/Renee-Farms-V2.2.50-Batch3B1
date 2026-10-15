"""WS52-KX no-network, fixture-only trust-boundary policy tests."""
import ast
from copy import deepcopy
from pathlib import Path
import sys
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "tools"))
import b12_ws52kv_physical_inspector_design_contract as kv
import b12_ws52kx_inspector_trust_boundary_design as kx


def fake_plan():
    return {
        "repository": kx.REPOSITORY,
        "source_parent": kx.SOURCE_PARENT,
        "design_scope": kx.REVIEW_SCOPE,
        "target_runner": "FUTURE_EPHEMERAL_GITHUB_HOSTED_ONLY",
        "expected_workflow_state": "NOT_CREATED",
        "runner_token_permissions": {},
        "runner_secrets": [],
        "runner_kind_claim": "UNVERIFIED_GITHUB_HOSTED_DESIGN",
        "runner_claim_attested": False,
        "docker_socket_access": False,
        "docker_socket_to_test_process": False,
        "broker_approval": None,
        "broker_boundary": "REVIEWED_ISOLATED_READONLY_BROKER_REQUIRED",
        "collector_runtime_enabled": False,
        "host_mutation_capability": False,
        "network_egress_policy": "PENDING_INDEPENDENT_NETWORK_MEASUREMENT",
        "loopback_binding_observation": "PENDING_INDEPENDENT_SOCKET_MEASUREMENT",
        "sensitive_response_handling": "BROKER_ALLOWLIST_REDACTS_BEFORE_EXPORT",
        "response_projection": list(kx.PROJECTION),
        "metadata_routes": list(kx.DOCKER_METADATA_ROUTES),
        "observation_intents": list(kx.READ_ONLY_INTENTS),
        "phase_labels": [a for a, _, _ in kv.STAGES],
        "provenance": dict(kx.EMPTY_PROVENANCE),
        "one_use_approval": "NOT_GRANTED",
    }


class TrustBoundaryFixtureTests(unittest.TestCase):
    def reject(self, plan, expected=None):
        result = kx.assess_inspector_boundary(plan)
        self.assertFalse(result.shape_valid, result.blockers)
        self.assertFalse(result.physical_attested)
        self.assertFalse(result.one_use_approved)
        self.assertFalse(result.network_isolated)
        self.assertFalse(result.cleanup_certified)
        self.assertFalse(result.rc01_certified)
        if expected:
            self.assertIn(expected, result.blockers)
        self.assertEqual(kx.safe_evidence_summary(plan)["RESULT"],
                         "BLOCKED_SOURCE_CONTRACT")

    def test_fixture_design_passes_shape_but_never_authorizes(self):
        result = kx.assess_inspector_boundary(fake_plan())
        self.assertTrue(result.shape_valid, result.blockers)
        self.assertEqual(result.blockers, ())
        self.assertFalse(result.physical_attested)
        self.assertFalse(result.one_use_approved)
        self.assertFalse(result.network_isolated)
        self.assertFalse(result.cleanup_certified)
        self.assertFalse(result.rc01_certified)

    def test_every_runtime_fuse_literal_disabled(self):
        self.assertIs(kx.INSPECTOR_EXECUTION_ENABLED, False)
        self.assertIs(kx.DOCKER_SOCKET_ACCESS_GRANTED, False)
        self.assertIs(kx.DOCKER_MUTATIONS_ALLOWED, False)
        self.assertIs(kx.SQL_ALLOWED, False)
        self.assertIs(kx.RC01_ALLOWED, False)
        self.assertIsNone(kx.APPROVED_ONE_USE_RUN)
        self.assertIsNone(kx.APPROVED_COLLECTOR_COMMIT)
        self.assertIsNone(kx.APPROVED_DOCKER_BROKER)

    def test_repository_drift(self):
        p = fake_plan()
        p["repository"] = "attacker/repo"
        self.reject(p, "INVALID_DESIGN_REPOSITORY")

    def test_parent_source_drift(self):
        p = fake_plan()
        p["source_parent"] = "0" * 40
        self.reject(p, "INVALID_DESIGN_SOURCE_PARENT")

    def test_scope_drift(self):
        p = fake_plan()
        p["design_scope"] = "RUNTIME_APPROVED"
        self.reject(p, "INVALID_DESIGN_DESIGN_SCOPE")

    def test_wrong_target_runner(self):
        p = fake_plan()
        p["target_runner"] = "SELF_HOSTED_MAC"
        self.reject(p, "INVALID_DESIGN_TARGET_RUNNER")

    def test_any_workflow_live_claim_rejected(self):
        for value in ("WORKFLOW_ENABLED", "RUNNING", True):
            with self.subTest(value=value):
                p = fake_plan()
                p["expected_workflow_state"] = value
                self.reject(p, "INVALID_DESIGN_EXPECTED_WORKFLOW_STATE")

    def test_runner_class_claim_not_trustworthy(self):
        p = fake_plan()
        p["runner_kind_claim"] = "VERIFIED_EPHEMERAL"
        self.reject(p, "INVALID_DESIGN_RUNNER_KIND_CLAIM")

    def test_runner_attestation_cannot_be_fabricated(self):
        for value in (True, 1, "YES"):
            p = fake_plan()
            p["runner_claim_attested"] = value
            self.reject(p, "REAL_RUNTIME_OR_PRIVILEGE_CLAIM_PRESENT")

    def test_runner_token_permission_expansion_rejected(self):
        for permissions in ({"contents": "read"}, {"id-token": "write"},
                            {"actions": "write"}):
            p = fake_plan()
            p["runner_token_permissions"] = permissions
            self.reject(p, "UNREVIEWED_RUNNER_TOKENS_OR_SECRETS")

    def test_runner_secret_access_rejected(self):
        for value in (["TOKEN"], ["DB_PASS"], "all"):
            p = fake_plan()
            p["runner_secrets"] = value
            self.reject(p, "UNREVIEWED_RUNNER_TOKENS_OR_SECRETS")

    def test_docker_socket_access_for_collector_rejected(self):
        p = fake_plan()
        p["docker_socket_access"] = True
        self.reject(p, "REAL_RUNTIME_OR_PRIVILEGE_CLAIM_PRESENT")

    def test_docker_socket_access_in_php_worker_rejected(self):
        p = fake_plan()
        p["docker_socket_to_test_process"] = True
        self.reject(p, "REAL_RUNTIME_OR_PRIVILEGE_CLAIM_PRESENT")

    def test_broker_approved_without_review_rejected(self):
        p = fake_plan()
        p["broker_approval"] = "APPROVED"
        self.reject(p, "REAL_RUNTIME_OR_PRIVILEGE_CLAIM_PRESENT")

    def test_broker_boundary_bypass_rejected(self):
        p = fake_plan()
        p["broker_boundary"] = "RAW_DOCKER_SOCKET"
        self.reject(p, "INVALID_DESIGN_BROKER_BOUNDARY")

    def test_enabled_inspector_rejected(self):
        p = fake_plan()
        p["collector_runtime_enabled"] = True
        self.reject(p, "REAL_RUNTIME_OR_PRIVILEGE_CLAIM_PRESENT")

    def test_host_mutation_capability_rejected(self):
        p = fake_plan()
        p["host_mutation_capability"] = "YES"
        self.reject(p, "REAL_RUNTIME_OR_PRIVILEGE_CLAIM_PRESENT")

    def test_egress_asserted_without_independent_measurement_rejected(self):
        p = fake_plan()
        p["network_egress_policy"] = "PASS"
        self.reject(p, "INVALID_DESIGN_NETWORK_EGRESS_POLICY")

    def test_loopback_listener_claim_fabricated_rejected(self):
        p = fake_plan()
        p["loopback_binding_observation"] = "LISTENER_PASS"
        self.reject(p, "INVALID_DESIGN_LOOPBACK_BINDING_OBSERVATION")

    def test_no_raw_docker_response_or_env_projection(self):
        for value in ("RAW_DOCKER_INFO", "EXPORT_ENV", True):
            with self.subTest(value=value):
                p = fake_plan()
                p["sensitive_response_handling"] = value
                self.reject(p, "INVALID_DESIGN_SENSITIVE_RESPONSE_HANDLING")

    def test_mutating_docker_routes_rejected(self):
        for operation in (
            ("POST", "/containers/create"),
            ("DELETE", "/volumes/{pinned_volume_name}"),
            ("POST", "/containers/{pinned_container_id}/start"),
        ):
            with self.subTest(op=operation):
                p = fake_plan()
                p["metadata_routes"][3] = operation
                self.reject(p, "DOCKER_METADATA_ROUTE_ALLOWLIST_DRIFT")

    def test_secret_bearing_or_overbroad_docker_endpoints_rejected(self):
        for endpoint in (
            ("GET", "/containers/{pinned_container_id}/logs"),
            ("GET", "/containers/{pinned_container_id}/archive"),
            ("GET", "/events"),
            ("GET", "/containers/json?all=1"),
        ):
            with self.subTest(path=endpoint[1]):
                p = fake_plan()
                p["metadata_routes"][3] = endpoint
                self.reject(p, "DOCKER_METADATA_ROUTE_ALLOWLIST_DRIFT")

    def test_route_reorder_duplication_or_extra_rejected(self):
        for mode in ("reverse", "extra", "duplicate"):
            p = fake_plan()
            if mode == "reverse":
                p["metadata_routes"].reverse()
            elif mode == "extra":
                p["metadata_routes"].append(("GET", "/containers"))
            else:
                p["metadata_routes"][0] = p["metadata_routes"][1]
            self.reject(p, "DOCKER_METADATA_ROUTE_ALLOWLIST_DRIFT")

    def test_observation_intent_reorder_and_mutation_rejected(self):
        p = fake_plan()
        p["observation_intents"].reverse()
        self.reject(p, "OBSERVATION_INTENT_DRIFT")
        p = fake_plan()
        p["observation_intents"][0] = "docker run"
        self.reject(p, "OBSERVATION_INTENT_DRIFT")

    def test_phase_order_drift_rejected(self):
        p = fake_plan()
        p["phase_labels"][2], p["phase_labels"][3] = (
            p["phase_labels"][3], p["phase_labels"][2]
        )
        self.reject(p, "OBSERVATION_PHASE_DRIFT")

    def test_redacted_evidence_projection_cannot_contain_env_or_host_paths(self):
        for bad in ("Env", "Mounts.Source", "Authorization",
                    "RAW_SIGNED_LOCATION", "HISTORICAL_BACKUP"):
            p = fake_plan()
            p["response_projection"].append(bad)
            self.reject(p, "UNREDACTED_OR_INCOMPLETE_RESPONSE_PROJECTION")

    def test_missing_projection_blocked(self):
        p = fake_plan()
        p["response_projection"].pop()
        self.reject(p, "UNREDACTED_OR_INCOMPLETE_RESPONSE_PROJECTION")

    def test_physical_provenance_claim_rejected(self):
        for field, value in (
            ("engine_id", "sha256:" + "a" * 64),
            ("workflow_commit", "f" * 40),
            ("one_use_run_id", "321"),
            ("collector_identity", "inspector"),
        ):
            p = fake_plan()
            p["provenance"][field] = value
            self.reject(p, "PHYSICAL_PROVENANCE_NOT_AUTHORIZED_TO_CLAIM")

    def test_missing_or_extra_provenance_rejected(self):
        p = fake_plan()
        p["provenance"].pop("volume_id")
        self.reject(p, "PROVENANCE_KEYS_INVALID")
        p = fake_plan()
        p["provenance"]["raw_token"] = "DONT_EXPORT"
        self.reject(p, "PROVENANCE_KEYS_INVALID")

    def test_unknown_or_missing_top_level_fields_rejected(self):
        p = fake_plan()
        p["PRIVATE_SECRET"] = "NO"
        self.reject(p, "TRUST_BOUNDARY_KEYS_INVALID")
        p = fake_plan()
        del p["response_projection"]
        self.reject(p, "TRUST_BOUNDARY_KEYS_INVALID")

    def test_invalid_input_types_rejected_without_exception(self):
        for bad in (None, "safe", [], 123, True):
            self.reject(bad, "TRUST_BOUNDARY_KEYS_INVALID")

    def test_evidence_summary_never_echoes_untrusted_secrets(self):
        p = fake_plan()
        p["EXTRA_TOKEN"] = "SENSITIVE_DO_NOT_EMIT"
        s = kx.safe_evidence_summary(p)
        self.assertEqual(s["RESULT"], "BLOCKED_SOURCE_CONTRACT")
        self.assertNotIn("SENSITIVE", repr(s))
        self.assertNotIn("EXTRA_TOKEN", repr(s))

    def test_valid_design_summary_never_claims_physical_pass(self):
        summary = kx.safe_evidence_summary(fake_plan())
        self.assertEqual(summary["RESULT"], "SOURCE_DESIGN_SHAPE_ONLY_NOT_ATTESTED")
        self.assertEqual(summary["RUNTIME_APPROVAL"], "NOT_GRANTED")
        self.assertEqual(summary["PHYSICAL_DOCKER_INSPECTION"], "NO")

    def test_source_ast_imports_have_no_runtime_io(self):
        text = (Path(__file__).resolve().parents[1] /
                "tools/b12_ws52kx_inspector_trust_boundary_design.py").read_text("utf-8")
        tree = ast.parse(text)
        imported = []
        for node in ast.walk(tree):
            if isinstance(node, ast.Import):
                imported.extend(name.name.split(".")[0] for name in node.names)
            elif isinstance(node, ast.ImportFrom):
                imported.append((node.module or "").split(".")[0])
        permitted = {"__future__", "dataclasses", "typing",
                     "b12_ws52kv_physical_inspector_design_contract"}
        self.assertTrue(set(imported) <= permitted, imported)
        self.assertNotIn("def main(", text)
        self.assertNotIn('if __name__ == "__main__":', text)
        self.assertEqual(text.count("INSPECTOR_EXECUTION_ENABLED = False"), 1)


if __name__ == "__main__":
    unittest.main()
