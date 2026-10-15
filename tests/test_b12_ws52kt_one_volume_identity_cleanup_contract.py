"""WS52-KT offline synthetic/fixture-only tests. NO Docker, network or SQL."""
import ast
from copy import deepcopy
from pathlib import Path
import sys
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "tools"))
import b12_ws52kt_one_volume_identity_cleanup_contract as contract

RUN_ID = "321"
CID = "a" * 64
OLD_CID = "b" * 64
VOL = "b12-rc01-vol-r321-" + "c" * 16
FPRINT = "sha256:" + "d" * 64
LABELS = {"run_id": RUN_ID, "purpose": contract.SYNTHETIC_SCOPE}


def synthetic_pre():
    """Entirely fictional metadata; structure-valid != physical provenance."""
    return {
        "repository": contract.REPO,
        "source_review_head": contract.SOURCE_REVIEW_HEAD,
        "runner_kind": "github-hosted-ephemeral",
        "run_id": RUN_ID,
        "run_attempt": 1,
        "engine_id": "sha256:" + "e" * 64,
        "image_child": contract.CHILD,
        "image_config": contract.CONFIG,
        "image_id": "sha256:" + "f" * 64,
        "container_id": CID,
        "container_name": "b12-rc01-db-r321-" + "e" * 16,
        "volume_name": VOL,
        "containers_before": [OLD_CID],
        "volumes_before": ["protected-existing-volume"],
        "containers_after_create": [OLD_CID, CID],
        "volumes_after_create": ["protected-existing-volume", VOL],
        "volume_inspect": {
            "name": VOL,
            "driver": "local",
            "mountpoint_fingerprint": FPRINT,
            "labels": dict(LABELS),
        },
        "effective_mounts": [{
            "type": "volume",
            "name": VOL,
            "driver": "local",
            "destination": "/var/lib/mysql",
            "rw": True,
            "source_fingerprint": FPRINT,
        }],
        "container_ownership_labels": dict(LABELS),
        "privileged": False,
        "network_mode": "bridge",
        "published_ports": [{
            "host_ip": "127.0.0.1",
            "host_port": 3306,
            "container_port": 3306,
            "protocol": "tcp",
        }],
        "restricted_egress_observer": "INDEPENDENT_POLICY_OBSERVATION_PENDING_REVIEW",
    }


def synthetic_post(pre=None):
    p = pre if pre is not None else synthetic_pre()
    return {
        "repository": p["repository"],
        "source_review_head": p["source_review_head"],
        "run_id": p["run_id"],
        "engine_id": p["engine_id"],
        "container_id": p["container_id"],
        "volume_name": p["volume_name"],
        "containers_after_cleanup": list(p["containers_before"]),
        "volumes_after_cleanup": list(p["volumes_before"]),
        "container_inspect_not_found": True,
        "volume_inspect_not_found": True,
        "workers_running": 0,
        "open_transactions": 0,
        "synthetic_rows_remaining": 0,
        "barrier_artifacts_remaining": 0,
        "independent_postcheck_reported": True,
    }


class VolumeIdentityCleanupOfflineTests(unittest.TestCase):
    def assertBlocked(self, pre, key=None):
        v = contract.assess_postcreate_shape(pre)
        self.assertFalse(v.shape_valid, "modified synthetic preflight must block")
        self.assertFalse(v.physical_attested)
        self.assertFalse(v.execution_authorized)
        if key:
            self.assertIn(key, v.blockers)

    def test_fictional_nominal_preflight_remains_nonattesting(self):
        result = contract.assess_postcreate_shape(synthetic_pre())
        self.assertTrue(result.shape_valid)
        self.assertEqual(result.blockers, ())
        self.assertFalse(result.physical_attested)
        self.assertFalse(result.execution_authorized)

    def test_literal_runtime_fuses_are_hard_off(self):
        self.assertIs(contract.EXECUTION_ENABLED, False)
        self.assertIsNone(contract.APPROVED_ONE_USE_RUNTIME)
        self.assertIsNone(contract.APPROVED_PHYSICAL_INSPECTOR)
        self.assertIsNone(contract.APPROVED_IMAGE_FOR_EXECUTION)

    def test_wrong_repo_source_runner_or_image_is_rejected(self):
        for k, bad in (
            ("repository", "unknown/repo"),
            ("source_review_head", "0" * 40),
            ("runner_kind", "self-hosted"),
            ("image_child", "sha256:" + "0" * 64),
            ("image_config", "sha256:" + "1" * 64),
        ):
            with self.subTest(field=k):
                p = synthetic_pre()
                p[k] = bad
                self.assertBlocked(p, "UNPINNED_" + k.upper())

    def test_run_id_attempt_and_name_must_match(self):
        for k, bad in (
            ("run_id", "0"),
            ("run_id", "322"),
            ("run_attempt", 2),
            ("run_attempt", True),
            ("container_name", "b12-rc01-db-r322-" + "e" * 16),
            ("volume_name", "b12-rc01-vol-r322-" + "c" * 16),
        ):
            with self.subTest(key=k, bad=bad):
                p = synthetic_pre()
                p[k] = bad
                self.assertBlocked(p)

    def test_volume_that_preexisted_is_never_ours(self):
        p = synthetic_pre()
        p["volumes_before"].append(VOL)
        self.assertBlocked(p, "VOLUME_NOT_EXACT_NEW_ADDITION")

    def test_extra_anonymous_volume_or_missing_preexisting_volume_blocks(self):
        for after in (
            ["protected-existing-volume", VOL, "stray-anonymous-volume"],
            [VOL],
            ["protected-existing-volume"],
        ):
            with self.subTest(after=after):
                p = synthetic_pre()
                p["volumes_after_create"] = after
                self.assertBlocked(p, "VOLUME_NOT_EXACT_NEW_ADDITION")

    def test_container_additions_cannot_include_preexisting_or_stray(self):
        for before, after in (
            ([OLD_CID, CID], [OLD_CID, CID]),
            ([OLD_CID], [OLD_CID, CID, "c" * 64]),
            ([OLD_CID], [CID]),
        ):
            with self.subTest(before=before, after=after):
                p = synthetic_pre()
                p["containers_before"] = before
                p["containers_after_create"] = after
                self.assertBlocked(p, "CONTAINER_NOT_EXACT_NEW_ADDITION")

    def test_duplicate_inventory_and_invalid_resource_identity_rejected(self):
        for field, items in (
            ("volumes_before", ["protected-existing-volume"] * 2),
            ("containers_after_create", [OLD_CID, CID, CID]),
            ("volumes_after_create", ["protected-existing-volume", {"bad": 1}]),
        ):
            p = synthetic_pre()
            p[field] = items
            with self.subTest(field=field):
                self.assertBlocked(p)

    def test_zero_or_multiple_effective_mounts_must_block(self):
        for mounts in ([], [synthetic_pre()["effective_mounts"][0]] * 2):
            p = synthetic_pre()
            p["effective_mounts"] = mounts
            self.assertBlocked(p, "EFFECTIVE_MOUNTS_NOT_EXACTLY_ONE")

    def test_bind_mount_wrong_target_ro_or_source_mismatch_rejected(self):
        cases = (
            ("type", "bind"),
            ("name", "protected-existing-volume"),
            ("driver", "nfs"),
            ("destination", "/host/backup"),
            ("rw", False),
            ("rw", 1),
            ("source_fingerprint", "sha256:" + "0" * 64),
        )
        for k, val in cases:
            p = synthetic_pre()
            p["effective_mounts"][0][k] = val
            with self.subTest(key=k):
                self.assertBlocked(p, "EFFECTIVE_MOUNT_UNREVIEWED_OR_UNLINKED")

    def test_volume_inspect_driver_labels_and_fingerprint_mismatch(self):
        for field, value in (
            ("name", "other-volume"),
            ("driver", "nfs"),
            ("mountpoint_fingerprint", "sha256:" + "0" * 64),
            ("labels", {"run_id": "322", "purpose": contract.SYNTHETIC_SCOPE}),
        ):
            with self.subTest(field=field):
                p = synthetic_pre()
                p["volume_inspect"][field] = value
                expected = ("EFFECTIVE_MOUNT_UNREVIEWED_OR_UNLINKED"
                            if field == "mountpoint_fingerprint"
                            else "VOLUME_INSPECT_IDENTITY_DRIFT")
                self.assertBlocked(p, expected)

    def test_container_labels_port_privilege_network_egress_rejected(self):
        cases = (
            ("privileged", True),
            ("network_mode", "host"),
            ("published_ports", [{"host_ip": "0.0.0.0", "host_port": 3306,
                                   "container_port": 3306, "protocol": "tcp"}]),
            ("published_ports", []),
            ("restricted_egress_observer", True),
            ("container_ownership_labels", {"run_id": "321", "purpose": "production"}),
        )
        for k, value in cases:
            with self.subTest(key=k):
                p = synthetic_pre()
                p[k] = value
                self.assertBlocked(p)

    def test_fixture_only_postcleanup_shape_remains_nonattesting(self):
        pre = synthetic_pre()
        post = synthetic_post(pre)
        result = contract.assess_cleanup_shape(pre, post)
        self.assertTrue(result.shape_valid)
        self.assertFalse(result.physical_attested)
        self.assertFalse(result.execution_authorized)
        self.assertEqual(contract.safe_status(contract.assess_postcreate_shape(pre),
                                             result),
                         "SOURCE_SHAPE_ONLY_PHYSICAL_NOT_ATTESTED")

    def test_postcleanup_requires_exact_original_resource_inventories(self):
        for field, value in (
            ("volumes_after_cleanup", ["protected-existing-volume", VOL]),
            ("volumes_after_cleanup", []),
            ("containers_after_cleanup", [OLD_CID, CID]),
            ("containers_after_cleanup", []),
        ):
            with self.subTest(field=field):
                pre, post = synthetic_pre(), synthetic_post()
                post[field] = value
                v = contract.assess_cleanup_shape(pre, post)
                self.assertFalse(v.shape_valid)
                self.assertTrue(any("INVENTORY_NOT_RESTORED" in b for b in v.blockers))

    def test_postcleanup_wrong_engine_container_run_and_volume_rejected(self):
        for k, bad in (
            ("engine_id", "sha256:" + "0" * 64),
            ("container_id", "f" * 64),
            ("run_id", "322"),
            ("volume_name", "b12-rc01-vol-r322-" + "f" * 16),
            ("repository", "another/tenant"),
        ):
            with self.subTest(key=k):
                p, post = synthetic_pre(), synthetic_post()
                post[k] = bad
                result = contract.assess_cleanup_shape(p, post)
                self.assertFalse(result.shape_valid)
                self.assertIn("POST_IDENTITY_DRIFT_" + k.upper(), result.blockers)

    def test_postcleanup_any_residue_or_uncertainty_rejected(self):
        for field, val in (
            ("workers_running", 1),
            ("open_transactions", 1),
            ("synthetic_rows_remaining", 1),
            ("barrier_artifacts_remaining", 1),
            ("synthetic_rows_remaining", True),
            ("container_inspect_not_found", False),
            ("volume_inspect_not_found", False),
            ("independent_postcheck_reported", False),
        ):
            p, post = synthetic_pre(), synthetic_post()
            post[field] = val
            with self.subTest(field=field):
                self.assertFalse(contract.assess_cleanup_shape(p, post).shape_valid)

    def test_preflight_rejection_blocks_post_even_if_post_claims_success(self):
        p = synthetic_pre()
        p["effective_mounts"][0]["name"] = "protected-existing-volume"
        result = contract.assess_cleanup_shape(p, synthetic_post(p))
        self.assertFalse(result.shape_valid)
        self.assertIn("PREFLIGHT_CONTRACT_NOT_MET", result.blockers)

    def test_unknown_or_missing_metadata_keys_fail_closed(self):
        p = synthetic_pre()
        p["unapproved"] = "ignored"
        self.assertBlocked(p, "PRE_SHAPE_KEYS_INVALID")
        post = synthetic_post()
        post.pop("container_inspect_not_found")
        self.assertIn("CLEANUP_SHAPE_KEYS_INVALID",
                      contract.assess_cleanup_shape(synthetic_pre(), post).blockers)

    def test_status_fails_closed_when_evidence_not_verified(self):
        pre = contract.assess_postcreate_shape(synthetic_pre())
        broken = synthetic_post()
        broken["open_transactions"] = 1
        post = contract.assess_cleanup_shape(synthetic_pre(), broken)
        self.assertEqual(contract.safe_status(pre, post), "BLOCKED_MANUAL_REVIEW")
        self.assertEqual(contract.safe_status("PASS", "PASS"), "BLOCKED_MANUAL_REVIEW")

    def test_source_only_ast_has_no_io_runtime_network_or_privilege(self):
        source = (Path(__file__).resolve().parents[1] /
                  "tools/b12_ws52kt_one_volume_identity_cleanup_contract.py").read_text("utf-8")
        tree = ast.parse(source)
        forbidden = {"subprocess", "os", "socket", "urllib", "requests",
                     "docker", "pymysql", "mysql", "sqlite3", "pathlib"}
        imports = []
        for node in ast.walk(tree):
            if isinstance(node, ast.Import):
                imports.extend(alias.name.split(".")[0] for alias in node.names)
            elif isinstance(node, ast.ImportFrom):
                imports.append((node.module or "").split(".")[0])
        self.assertFalse(forbidden & set(imports))
        self.assertNotIn("def main(", source)
        self.assertNotIn("if __name__ ==", source)
        self.assertEqual(source.count("EXECUTION_ENABLED = False"), 1)


if __name__ == "__main__":
    unittest.main()
