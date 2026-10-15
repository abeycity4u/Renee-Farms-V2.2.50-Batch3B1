"""WS52-LB: offline, fixture-only minimized Docker response redaction tests."""
import ast
from copy import deepcopy
import json
from pathlib import Path
import sys
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "tools"))
import b12_ws52kt_one_volume_identity_cleanup_contract as kt
import b12_ws52kv_physical_inspector_design_contract as kv
import b12_ws52kx_inspector_trust_boundary_design as kx
import b12_ws52kz_broker_projection_source_contract as kz
import b12_ws52lb_docker_redaction_adapter_source as lb

CID = "a" * 64
VOL = "b12-rc01-vol-r321-" + "b" * 16
RUN = "321"


def fixture(route=3):
    context = {
        "repository": kz.REPO,
        "source_parent": kz.SOURCE_PARENT,
        "runner_scope": "FUTURE_EPHEMERAL_GITHUB_HOSTED_ONLY",
        "run_id": RUN,
        "container_id": CID,
        "volume_name": VOL,
        "review_status": "SOURCE_ONLY_UNTRUSTED",
        "broker_state": "NOT_CREATED",
        "socket_access": False,
        "worker_socket_access": False,
        "one_use_approval": "NOT_GRANTED",
        "inherited_secrets": [],
        "runner_permissions": {},
        "engine_provenance": "NOT_COLLECTED",
        "egress_provenance": "NOT_COLLECTED",
    }
    method, path = kz._routes(CID, VOL)[route]
    request = {
        "method": method, "path": path, "query": "",
        "headers": {}, "body": None, "follow_redirects": False,
        "broker_token": None, "request_state": "NOT_EXECUTED_SOURCE_ONLY",
    }
    bodies = [
        {"Version": "26.1.0", "ApiVersion": "1.45"},
        {"ID": "test-engine-only", "ServerVersion": "26.1.0"},
        [{"Id": CID}],
        {"Id": CID, "Image": kt.CONFIG,
         "HostConfig": {
             "Privileged": False, "NetworkMode": "bridge",
             "PortBindings": {"3306/tcp": [{
                 "HostIp": "127.0.0.1", "HostPort": "3306",
             }]},
         },
         "Mounts": [{
             "Type": "volume", "Name": VOL,
             "Destination": "/var/lib/mysql",
             "RW": True, "Driver": "local",
         }],
         },
        {"Volumes": [{"Name": VOL}]},
        {"Name": VOL, "Driver": "local",
         "Labels": {"run_id": RUN, "purpose": "b12-rc01-disposable"}},
    ]
    return context, request, bodies[route]


def as_bytes(obj):
    return json.dumps(obj, separators=(",", ":"), ensure_ascii=True).encode()


class RedactionSpecOfflineTests(unittest.TestCase):
    def blocked(self, c, req, data, reason=None):
        result = lb.assess_minimized_fixture(c, req, data)
        self.assertFalse(result.shape_valid, result.blockers)
        self.assertFalse(result.physical_attested)
        self.assertFalse(result.broker_executed)
        self.assertFalse(result.actual_raw_response_redacted)
        self.assertFalse(result.one_use_approved)
        self.assertFalse(result.cleanup_certified)
        if reason:
            self.assertIn(reason, result.blockers)
        self.assertEqual(lb.safe_report(result)["RESULT"], "BLOCKED_SOURCE_CONTRACT")

    def test_all_six_minimized_route_schemas_never_certify_physical(self):
        for i in range(6):
            with self.subTest(i=i):
                c, req, obj = fixture(i)
                result = lb.assess_minimized_fixture(c, req, as_bytes(obj))
                self.assertTrue(result.shape_valid, result.blockers)
                self.assertFalse(result.physical_attested)
                self.assertFalse(result.broker_executed)
                self.assertFalse(result.actual_raw_response_redacted)
                self.assertFalse(result.one_use_approved)
                self.assertFalse(result.cleanup_certified)
                self.assertEqual(lb.safe_report(result)["RESULT"],
                                 "SOURCE_ONLY_MINIMIZED_FIXTURE_SHAPE")

    def test_permanent_nonexecution_fuses(self):
        self.assertIs(lb.ADAPTER_EXECUTION_ENABLED, False)
        self.assertIs(lb.DOCKER_SOCKET_ACCESS_GRANTED, False)
        self.assertIs(lb.RAW_DOCKER_HTTP_ACCESS_GRANTED, False)
        self.assertIs(lb.NETWORK_ALLOWED, False)
        self.assertIs(lb.SQL_ALLOWED, False)
        self.assertIs(lb.RC01_ALLOWED, False)
        self.assertIsNone(lb.APPROVED_REAL_BROKER)
        self.assertIsNone(lb.APPROVED_ONE_USE_RUN)

    def test_older_kz_kx_kv_kt_guards_reused_unchanged(self):
        self.assertEqual(lb.SOURCE_PARENT, "8f6a4b5925074bcc8c2ee8e5966608c4714b554a")
        self.assertIs(kz.BROKER_EXECUTION_ENABLED, False)
        self.assertIs(kx.INSPECTOR_EXECUTION_ENABLED, False)
        self.assertIs(kv.INSPECTOR_EXECUTION_ENABLED, False)
        self.assertIs(kt.EXECUTION_ENABLED, False)
        self.assertEqual(lb.PINNED_IMAGE_CONFIG, kt.CONFIG)

    def test_content_size_cap_rejects_empty_oversized_and_wrong_types(self):
        c, req, obj = fixture()
        for bad in (b"", b"x" * (lb.MAX_RESPONSE_BYTES + 1),
                    "{}", None, 123, {}, bytearray(b"{}")):
            self.blocked(c, req, bad, "RESPONSE_BYTES_INVALID_OR_EXCESSIVE")

    def test_invalid_utf8_and_malformed_json(self):
        c, req, obj = fixture(0)
        for raw in (b"\xff", b"{", b"undefined", b"[] trailing",
                    b"{invalid-json}", b""):
            self.blocked(c, req, raw)

    def test_duplicate_top_level_fields_rejected(self):
        c, req, obj = fixture(0)
        raw = b'{"Version":"26.1.0","Version":"26.1.1","ApiVersion":"1.45"}'
        self.blocked(c, req, raw, "JSON_MALFORMED_DUPLICATED_OR_UNSAFE")

    def test_escaped_duplicate_field_rejected(self):
        c, req, obj = fixture(0)
        raw = b'{"Version":"26.1.0","\\u0056ersion":"26.1.1","ApiVersion":"1.45"}'
        self.blocked(c, req, raw, "JSON_MALFORMED_DUPLICATED_OR_UNSAFE")

    def test_duplicate_nested_host_fields_rejected(self):
        c, req, obj = fixture(3)
        raw = as_bytes(obj).replace(
            b'"Privileged":false',
            b'"Privileged":false,"Privileged":true')
        self.blocked(c, req, raw, "JSON_MALFORMED_DUPLICATED_OR_UNSAFE")

    def test_nan_infinity_invalid(self):
        c, req, obj = fixture(0)
        for raw in (b'{"Version":NaN}', b'{"Version":Infinity}',
                    b'{"Version":-Infinity}'):
            self.blocked(c, req, raw, "JSON_MALFORMED_DUPLICATED_OR_UNSAFE")

    def test_reject_deep_nested_json(self):
        c, req, obj = fixture(0)
        root = {"a": {"a": {"a": {"a": {"a": {"a": {"a": "x"}}}}}}}
        self.blocked(c, req, as_bytes(root), "JSON_DEPTH_OR_NODE_CAP")

    def test_reject_collection_over_limit(self):
        c, req, obj = fixture(2)
        raw = as_bytes([{"Id": format(i, "064x")} for i in range(10)])
        self.blocked(c, req, raw, "JSON_COLLECTION_LIMIT")

    def test_reject_oversize_json_field_or_scalar(self):
        c, req, obj = fixture(1)
        obj["ID"] = "x" * 300
        self.blocked(c, req, as_bytes(obj), "JSON_VALUE_LIMIT")
        obj = {"k" * 70: "v"}
        self.blocked(c, req, as_bytes(obj), "JSON_FIELD_NAME_LIMIT")

    def test_engine_version_rejects_unreviewed_fields_and_nontypes(self):
        c, req, obj = fixture(0)
        obj["Components"] = [{"Details": {"Secrets": "private"}}]
        self.blocked(c, req, as_bytes(obj), "UNEXPECTED_DOCKER_FIELDS_OR_STRUCTURE")
        for value in ("latest", "26.1.0\nSECRET", 26.1, True, None):
            c, req, obj = fixture(0)
            obj["Version"] = value
            self.blocked(c, req, as_bytes(obj), "ENGINE_VERSION_FORMAT")

    def test_engine_info_rejects_sensitive_fields(self):
        c, req, obj = fixture(1)
        for name in ("RegistryConfig", "DriverStatus", "DockerRootDir",
                     "SecurityOptions", "Env"):
            p = deepcopy(obj)
            p[name] = "secret"
            self.blocked(c, req, as_bytes(p), "UNEXPECTED_DOCKER_FIELDS_OR_STRUCTURE")

    def test_container_inventory_rejects_raw_fields_and_duplicates(self):
        c, req, obj = fixture(2)
        obj[0]["Names"] = ["/production"]
        self.blocked(c, req, as_bytes(obj), "CONTAINER_INVENTORY_NOT_MINIMIZED")
        c, req, obj = fixture(2)
        obj.append({"Id": CID})
        self.blocked(c, req, as_bytes(obj), "CONTAINER_INVENTORY_NOT_MINIMIZED")

    def test_volume_inventory_rejects_raw_mountpoints_and_duplicates(self):
        c, req, obj = fixture(4)
        obj["Volumes"][0]["Mountpoint"] = "/home/real/backup"
        self.blocked(c, req, as_bytes(obj), "VOLUME_INVENTORY_NOT_MINIMIZED")
        c, req, obj = fixture(4)
        obj["Volumes"].append({"Name": VOL})
        self.blocked(c, req, as_bytes(obj), "VOLUME_INVENTORY_NOT_MINIMIZED")

    def test_container_rejects_raw_docker_env_config_and_mount_sources(self):
        for key, val in (
            ("Config", {"Env": ["MYSQL_ROOT_PASSWORD=SECRET"]}),
            ("State", {"Pid": 100}),
            ("Path", "/bin/sh"),
            ("NetworkSettings", {"Networks": {"bridge": {}}}),
        ):
            c, req, obj = fixture(3)
            obj[key] = val
            self.blocked(c, req, as_bytes(obj), "UNEXPECTED_DOCKER_FIELDS_OR_STRUCTURE")
        c, req, obj = fixture(3)
        obj["Mounts"][0]["Source"] = "/home/user/private"
        self.blocked(c, req, as_bytes(obj), "EFFECTIVE_MOUNT_NOT_EXACTLY_ONE")

    def test_volume_rejects_raw_path_labels_or_unapproved_driver(self):
        for key, value in (
            ("Mountpoint", "/var/lib/docker/volumes/production"),
            ("Options", {"o": "bind"}),
        ):
            c, req, obj = fixture(5)
            obj[key] = value
            self.blocked(c, req, as_bytes(obj), "UNEXPECTED_DOCKER_FIELDS_OR_STRUCTURE")
        c, req, obj = fixture(5)
        obj["Driver"] = "nfs"
        self.blocked(c, req, as_bytes(obj), "VOLUME_IDENTITY_OR_LABELS_MISMATCH")

    def test_exact_container_id_and_image_digest_binding(self):
        for field, val in (
            ("Id", "b" * 64), ("Image", "sha256:" + "c" * 64),
            ("Image", "mariadb:10.11"),
        ):
            c, req, obj = fixture(3)
            obj[field] = val
            self.blocked(c, req, as_bytes(obj), "CONTAINER_IMAGE_OR_ID_MISMATCH")

    def test_container_privileged_public_network_or_port_changes(self):
        changes = (
            ("Privileged", True),
            ("NetworkMode", "host"),
            ("PortBindings", {"3306/tcp": [
                {"HostIp": "0.0.0.0", "HostPort": "3306"}]}),
        )
        for field, value in changes:
            c, req, obj = fixture(3)
            obj["HostConfig"][field] = value
            self.blocked(c, req, as_bytes(obj), "PRIVILEGE_OR_PORT_NOT_ALLOWED")

    def test_mount_writable_type_driver_name_and_target_binding(self):
        changes = (
            ("RW", False), ("RW", 1), ("Type", "bind"),
            ("Name", "production-volume"), ("Driver", "nfs"),
            ("Destination", "/etc/mysql"),
        )
        for key, value in changes:
            c, req, obj = fixture(3)
            obj["Mounts"][0][key] = value
            self.blocked(c, req, as_bytes(obj), "MOUNT_IDENTITY_OR_PERMISSIONS_MISMATCH")

    def test_unreviewed_extra_or_missing_mounts_fail_closed(self):
        c, req, obj = fixture(3)
        obj["Mounts"].append(deepcopy(obj["Mounts"][0]))
        self.blocked(c, req, as_bytes(obj), "EFFECTIVE_MOUNT_NOT_EXACTLY_ONE")
        c, req, obj = fixture(3)
        obj["Mounts"] = []
        self.blocked(c, req, as_bytes(obj), "EFFECTIVE_MOUNT_NOT_EXACTLY_ONE")

    def test_volume_labels_must_match_synthetic_run_and_purpose(self):
        c, req, obj = fixture(5)
        obj["Labels"]["run_id"] = "322"
        self.blocked(c, req, as_bytes(obj), "VOLUME_IDENTITY_OR_LABELS_MISMATCH")
        c, req, obj = fixture(5)
        obj["Labels"]["owner"] = "production"
        self.blocked(c, req, as_bytes(obj), "VOLUME_IDENTITY_OR_LABELS_MISMATCH")

    def test_kz_rejects_unsafe_method_query_and_wrong_identity(self):
        for name, val in (
            ("method", "POST"),
            ("query", "all=1"),
            ("path", "/containers/" + "b" * 64 + "/json"),
        ):
            c, req, obj = fixture(3)
            req[name] = val
            self.blocked(c, req, as_bytes(obj), "WS52KZ_BROKER_SHAPE_REJECTED")

    def test_unreviewed_context_identity_and_approval_claims(self):
        for key, value in (
            ("runner_scope", "USER_PRODUCTION"),
            ("broker_state", "ACTIVE"),
            ("socket_access", True),
            ("one_use_approval", "GRANTED"),
        ):
            c, req, obj = fixture(3)
            c[key] = value
            self.blocked(c, req, as_bytes(obj), "WS52KZ_BROKER_SHAPE_REJECTED")

    def test_no_secret_raw_id_or_path_in_sanitized_output(self):
        c, req, obj = fixture(3)
        obj["Config"] = {"Env": ["MYSQL_ROOT_PASSWORD=SUPER_SECRET"]}
        result = lb.assess_minimized_fixture(c, req, as_bytes(obj))
        out = lb.safe_report(result)
        self.assertEqual(out["RESULT"], "BLOCKED_SOURCE_CONTRACT")
        for secret in ("SUPER_SECRET", CID, VOL, "/home/", "Config", "Env"):
            self.assertNotIn(secret, str(out))

    def test_safe_report_refuses_non_result(self):
        for bad in (None, {}, "PASS", True):
            report = lb.safe_report(bad)
            self.assertEqual(report["RESULT"], "BLOCKED_SOURCE_CONTRACT")
            self.assertEqual(report["RUNTIME_APPROVAL"], "NOT_GRANTED")
            self.assertEqual(report["PHYSICAL_DOCKER_INSPECTION"], "NO")

    def test_source_only_ast_has_no_io_import_or_main(self):
        text = (Path(__file__).resolve().parents[1] /
                "tools/b12_ws52lb_docker_redaction_adapter_source.py").read_text("utf-8")
        tree = ast.parse(text)
        imported = []
        for node in ast.walk(tree):
            if isinstance(node, ast.Import):
                imported.extend(alias.name.split(".")[0] for alias in node.names)
            elif isinstance(node, ast.ImportFrom):
                imported.append((node.module or "").split(".")[0])
        allowed = {
            "__future__", "dataclasses", "json", "re", "typing",
            "b12_ws52kt_one_volume_identity_cleanup_contract",
            "b12_ws52kz_broker_projection_source_contract",
        }
        self.assertTrue(set(imported) <= allowed, imported)
        self.assertNotIn("def main(", text)
        self.assertNotIn('if __name__ == "__main__":', text)
        self.assertEqual(text.count("ADAPTER_EXECUTION_ENABLED = False"), 1)


if __name__ == "__main__":
    unittest.main()
