"""WS52-KV fixture-only inspector-design negatives; no host or Docker access."""
import ast
from copy import deepcopy
from pathlib import Path
import sys
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "tools"))
import b12_ws52kt_one_volume_identity_cleanup_contract as kt
import b12_ws52kv_physical_inspector_design_contract as kv

RUN_ID = "321"
CID = "a" * 64
OLD_CID = "b" * 64
VOL = "b12-rc01-vol-r321-" + "c" * 16
FPRINT = "sha256:" + "d" * 64
LABELS = {"run_id": RUN_ID, "purpose": kt.SYNTHETIC_SCOPE}


def fake_pre():
    return {
        "repository": kt.REPO,
        "source_review_head": kt.SOURCE_REVIEW_HEAD,
        "runner_kind": "github-hosted-ephemeral",
        "run_id": RUN_ID,
        "run_attempt": 1,
        "engine_id": "sha256:" + "e" * 64,
        "image_child": kt.CHILD,
        "image_config": kt.CONFIG,
        "image_id": "sha256:" + "f" * 64,
        "container_id": CID,
        "container_name": "b12-rc01-db-r321-" + "e" * 16,
        "volume_name": VOL,
        "containers_before": [OLD_CID],
        "volumes_before": ["protected-existing-volume"],
        "containers_after_create": [OLD_CID, CID],
        "volumes_after_create": ["protected-existing-volume", VOL],
        "volume_inspect": {
            "name": VOL, "driver": "local",
            "mountpoint_fingerprint": FPRINT, "labels": dict(LABELS),
        },
        "effective_mounts": [{
            "type": "volume", "name": VOL, "driver": "local",
            "destination": "/var/lib/mysql", "rw": True,
            "source_fingerprint": FPRINT,
        }],
        "container_ownership_labels": dict(LABELS),
        "privileged": False,
        "network_mode": "bridge",
        "published_ports": [{
            "host_ip": "127.0.0.1", "host_port": 3306,
            "container_port": 3306, "protocol": "tcp",
        }],
        "restricted_egress_observer": "INDEPENDENT_POLICY_OBSERVATION_PENDING_REVIEW",
    }


def fake_post(pre):
    return {
        "repository": pre["repository"],
        "source_review_head": pre["source_review_head"],
        "run_id": pre["run_id"],
        "engine_id": pre["engine_id"],
        "container_id": pre["container_id"],
        "volume_name": pre["volume_name"],
        "containers_after_cleanup": list(pre["containers_before"]),
        "volumes_after_cleanup": list(pre["volumes_before"]),
        "container_inspect_not_found": True,
        "volume_inspect_not_found": True,
        "workers_running": 0,
        "open_transactions": 0,
        "synthetic_rows_remaining": 0,
        "barrier_artifacts_remaining": 0,
        "independent_postcheck_reported": True,
    }


def fake_plan():
    pre = fake_pre()
    return {
        "repository": kv.REPO,
        "reviewed_source_head": kv.WS52KU_PARENT,
        "design_scope": "SOURCE_ONLY_NOT_PHYSICAL",
        "read_only_operations": list(kv.READ_ONLY_INTENTS),
        "postcreate_fixture": pre,
        "postcleanup_fixture": fake_post(pre),
        "observation_stages": [{
            "order": i,
            "stage": stage,
            "collector": collector,
            "observation_intent": intent,
            "repository": kv.REPO,
            "reviewed_source_head": kv.WS52KU_PARENT,
            "run_id": RUN_ID,
            "engine_id": pre["engine_id"],
            "container_id": "" if i < 2 else CID,
            "volume_name": "" if i < 2 else VOL,
            "redacted_fingerprint": "sha256:" + format(i + 1, "064x"),
            "execution_status": "NOT_EXECUTED_SOURCE_ONLY",
        } for i, (stage, collector, intent) in enumerate(kv.STAGES)],
    }


class PhysicalInspectorDesignOfflineTests(unittest.TestCase):
    def assertBlocked(self, plan, blocker=None):
        result = kv.assess_design(plan)
        self.assertFalse(result.shape_valid)
        self.assertFalse(result.physical_attested)
        self.assertFalse(result.execution_authorized)
        self.assertFalse(result.cleanup_certified)
        self.assertEqual(kv.safe_decision(result), "BLOCKED_MANUAL_REVIEW")
        if blocker is not None:
            self.assertIn(blocker, result.blockers)

    def test_hypothetical_six_stages_pass_shape_but_no_physical_attestation(self):
        result = kv.assess_design(fake_plan())
        self.assertTrue(result.shape_valid, result.blockers)
        self.assertEqual(result.blockers, ())
        self.assertFalse(result.physical_attested)
        self.assertFalse(result.execution_authorized)
        self.assertFalse(result.cleanup_certified)
        self.assertEqual(kv.safe_decision(result),
                         "SOURCE_INSPECTOR_DESIGN_ONLY_NO_PHYSICAL_PROOF")

    def test_runtime_and_mutation_fuses_permanently_disabled(self):
        self.assertIs(kv.INSPECTOR_EXECUTION_ENABLED, False)
        self.assertIs(kv.DOCKER_MUTATIONS_ALLOWED, False)
        self.assertIs(kv.SQL_ALLOWED, False)
        self.assertIs(kv.RC01_ALLOWED, False)
        self.assertIsNone(kv.PHYSICAL_INSPECTOR_APPROVED)
        self.assertIsNone(kv.ONE_USE_RUN_APPROVED)

    def test_wrong_repo_parent_or_design_scope_fails(self):
        for field, value in (
            ("repository", "unknown/repo"),
            ("reviewed_source_head", "0" * 40),
            ("design_scope", "LIVE_RUNTIME_APPROVED"),
        ):
            p = fake_plan()
            p[field] = value
            with self.subTest(field=field):
                self.assertBlocked(p, "UNPINNED_DESIGN_SCOPE")

    def test_unknown_and_missing_top_level_metadata_fails(self):
        for operation in ("drop", "add"):
            p = fake_plan()
            if operation == "drop":
                p.pop("observation_stages")
            else:
                p["extra_auth"] = True
            self.assertBlocked(p, "PLAN_KEYS_INVALID")

    def test_mutating_or_unplanned_intent_must_fail(self):
        for bad in ("docker container rm", "docker volume prune",
                    "docker run", "SQL_WRITE", "unlock_cutover"):
            p = fake_plan()
            p["read_only_operations"][2] = bad
            self.assertBlocked(p, "READ_ONLY_INTENT_ORDER_INVALID")

    def test_intent_order_is_fixed_and_not_a_command_set(self):
        p = fake_plan()
        p["read_only_operations"][0], p["read_only_operations"][1] = (
            p["read_only_operations"][1], p["read_only_operations"][0]
        )
        self.assertBlocked(p, "READ_ONLY_INTENT_ORDER_INVALID")

    def test_missing_or_extra_observation_stage_blocks(self):
        for operation in ("missing", "extra"):
            p = fake_plan()
            if operation == "missing":
                p["observation_stages"].pop()
            else:
                p["observation_stages"].append(deepcopy(p["observation_stages"][-1]))
            self.assertBlocked(p, "OBSERVATION_STAGES_NOT_EXACT")

    def test_swapped_phases_or_repeated_observer_stage_fails(self):
        p = fake_plan()
        p["observation_stages"][2], p["observation_stages"][3] = (
            p["observation_stages"][3], p["observation_stages"][2]
        )
        self.assertBlocked(p, "STAGE_ORDER_OR_OBSERVER_INVALID")

    def test_ordinal_bool_and_incorrect_order_rejected(self):
        for bad in (True, 4, -1, "2"):
            p = fake_plan()
            p["observation_stages"][2]["order"] = bad
            self.assertBlocked(p, "STAGE_ORDER_OR_OBSERVER_INVALID")

    def test_actor_cannot_claim_independent_observer_identity(self):
        p = fake_plan()
        p["observation_stages"][3]["collector"] = "mutating-actor"
        self.assertBlocked(p, "STAGE_ORDER_OR_OBSERVER_INVALID")

    def test_reported_runtime_execution_cannot_appear_in_design(self):
        for bad in ("EXECUTED", "PASS", True):
            p = fake_plan()
            p["observation_stages"][2]["execution_status"] = bad
            self.assertBlocked(p, "UNAUTHORIZED_PHYSICAL_EXECUTION_CLAIM")

    def test_observation_missing_or_extra_key_fails(self):
        for action in ("missing", "extra"):
            p = fake_plan()
            if action == "missing":
                p["observation_stages"][0].pop("redacted_fingerprint")
            else:
                p["observation_stages"][0]["raw_signed_url"] = "DO_NOT_CAPTURE"
            self.assertBlocked(p, "OBSERVATION_RECORD_KEYS_INVALID")

    def test_observation_run_and_engine_drift_fails(self):
        for field, value in (
            ("run_id", "322"),
            ("engine_id", "sha256:" + "0" * 64),
        ):
            p = fake_plan()
            p["observation_stages"][4][field] = value
            self.assertBlocked(p, "OBSERVATION_RUN_ENGINE_DRIFT")

    def test_observation_repository_and_source_drift_fails(self):
        p = fake_plan()
        p["observation_stages"][4]["reviewed_source_head"] = "f" * 40
        self.assertBlocked(p, "OBSERVATION_SOURCE_DRIFT")

    def test_resource_ids_must_be_blank_before_container_exists(self):
        p = fake_plan()
        p["observation_stages"][1]["container_id"] = CID
        self.assertBlocked(p, "OBSERVATION_RESOURCE_ID_DRIFT")

    def test_postcreation_resource_ids_cannot_drift(self):
        for field, value in (
            ("container_id", "f" * 64),
            ("volume_name", "protected-existing-volume"),
        ):
            p = fake_plan()
            p["observation_stages"][5][field] = value
            self.assertBlocked(p, "OBSERVATION_RESOURCE_ID_DRIFT")

    def test_unredacted_or_invalid_fingerprint_rejected(self):
        for bad in ("raw-mount-/root/path", "", "sha256:SECRET", None):
            p = fake_plan()
            p["observation_stages"][3]["redacted_fingerprint"] = bad
            self.assertBlocked(p, "OBSERVATION_FINGERPRINT_INVALID")

    def test_ws52kt_rejects_unreviewed_second_volume(self):
        p = fake_plan()
        p["postcreate_fixture"]["volumes_after_create"].append("stray-anonymous-volume")
        self.assertBlocked(p, "WS52KT_POSTCREATE_SHAPE_REJECTED")

    def test_ws52kt_rejects_incorrect_mount_or_public_port(self):
        for field, value in (
            ("destination", "/mnt/production"),
            ("rw", False),
        ):
            p = fake_plan()
            p["postcreate_fixture"]["effective_mounts"][0][field] = value
            self.assertBlocked(p, "WS52KT_POSTCREATE_SHAPE_REJECTED")
        p = fake_plan()
        p["postcreate_fixture"]["published_ports"][0]["host_ip"] = "0.0.0.0"
        self.assertBlocked(p, "WS52KT_POSTCREATE_SHAPE_REJECTED")

    def test_ws52kt_rejects_postcleanup_leftover_and_missing_volume(self):
        for field, value in (
            ("workers_running", 1),
            ("open_transactions", 1),
            ("volume_inspect_not_found", False),
        ):
            p = fake_plan()
            p["postcleanup_fixture"][field] = value
            self.assertBlocked(p, "WS52KT_POSTCLEANUP_SHAPE_REJECTED")

    def test_safe_decision_never_accepts_strings_or_unknown_results(self):
        for bad in ("PASS", {"shape_valid": True}, None):
            self.assertEqual(kv.safe_decision(bad), "BLOCKED_MANUAL_REVIEW")

    def test_source_imports_are_pure_and_do_not_run_docker(self):
        text = (Path(__file__).resolve().parents[1] /
                "tools/b12_ws52kv_physical_inspector_design_contract.py").read_text("utf-8")
        tree = ast.parse(text)
        imports = []
        for node in ast.walk(tree):
            if isinstance(node, ast.Import):
                imports.extend(alias.name.split(".")[0] for alias in node.names)
            if isinstance(node, ast.ImportFrom):
                imports.append((node.module or "").split(".")[0])
        allowed = {"__future__", "dataclasses", "typing", "re",
                   "b12_ws52kt_one_volume_identity_cleanup_contract"}
        self.assertTrue(set(imports) <= allowed)
        self.assertNotIn("if __name__ ==", text)
        self.assertNotIn("def main(", text)
        self.assertEqual(text.count("INSPECTOR_EXECUTION_ENABLED = False"), 1)


if __name__ == "__main__":
    unittest.main()
