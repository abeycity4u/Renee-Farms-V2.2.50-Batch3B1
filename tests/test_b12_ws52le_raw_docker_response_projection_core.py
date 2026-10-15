"""WS52-LE adversarial unit tests for ACTUAL raw-byte projection logic.

Pure fixture-generated raw Docker-shaped payloads. No Docker API/SQL/HTTP.
"""
import ast
from copy import deepcopy
import json
from pathlib import Path
import sys
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "tools"))
import b12_ws52kt_one_volume_identity_cleanup_contract as kt
import b12_ws52kz_broker_projection_source_contract as kz
import b12_ws52lb_docker_redaction_adapter_source as lb
import b12_ws52le_raw_docker_response_projection_core as le

CID = "a" * 64
RUN = "321"
VOL = "b12-rc01-vol-r321-" + "b" * 16


def fixture(route=3):
    ctx = {
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
    req = {
        "method": method, "path": path, "query": "", "headers": {},
        "body": None, "follow_redirects": False, "broker_token": None,
        "request_state": "NOT_EXECUTED_SOURCE_ONLY",
    }
    bodies = [
        {"Version": "26.1.0", "ApiVersion": "1.45",
         "Components": [{"Name": "Engine", "Details": {"Root": "/secret"}}]},
        {"ID": "test-engine", "ServerVersion": "26.1.0",
         "DockerRootDir": "/home/secret",
         "RegistryConfig": {"InsecureRegistryCIDRs": []}},
        [{"Id": CID, "Names": ["/private-db"], "Command": "secret-command",
          "Labels": {"password": "secret"}}],
        {
            "Id": CID, "Image": kt.CONFIG,
            "Config": {"Env": ["MYSQL_ROOT_PASSWORD=VERY_SECRET"]},
            "HostConfig": {
                "Privileged": False, "NetworkMode": "bridge",
                "PortBindings": {"3306/tcp": [{
                    "HostIp": "127.0.0.1", "HostPort": "3306",
                }]},
                "Binds": ["/home/private:/restricted"],
            },
            "Mounts": [{
                "Type": "volume", "Name": VOL, "Destination": "/var/lib/mysql",
                "RW": True, "Driver": "local",
                "Source": "/var/lib/docker/volumes/private/_data",
                "Mode": "rw", "Propagation": "",
            }],
            "State": {"Pid": 112},
            "NetworkSettings": {"Networks": {"bridge": {"IPAddress": "172.0.0.2"}}},
        },
        {"Volumes": [{"Name": VOL, "Mountpoint": "/private/vol",
                      "Labels": {"password": "VERY_SECRET"}}],
         "Warnings": None},
        {"Name": VOL, "Driver": "local",
         "Labels": {"run_id": RUN, "purpose": "b12-rc01-disposable"},
         "Mountpoint": "/var/lib/docker/volumes/private/_data",
         "Options": {}, "Scope": "local"},
    ]
    return ctx, req, bodies[route]


def encoded(raw):
    return json.dumps(raw, ensure_ascii=True, separators=(",", ":")).encode()


class RawProjectionCoreTests(unittest.TestCase):
    def require_blocked(self, c, req, raw, reason=None, **kwargs):
        result = le.project_raw_response(c, req, raw, **kwargs)
        self.assertFalse(result.shape_valid, result.reason)
        self.assertIsNone(result.minimized)
        self.assertFalse(result.physical_attested)
        self.assertFalse(result.one_use_approved)
        self.assertFalse(result.real_broker_executed)
        self.assertFalse(result.actual_raw_response_redaction_certified)
        self.assertFalse(result.cleanup_certified)
        self.assertEqual(le.safe_status(result)["RESULT"], "BLOCKED_SOURCE_CONTRACT")
        if reason:
            self.assertEqual(result.reason, reason)

    def test_all_six_real_shaped_raw_payloads_are_minimized(self):
        for route in range(6):
            with self.subTest(route=route):
                c, req, obj = fixture(route)
                result = le.project_raw_response(c, req, encoded(obj))
                self.assertTrue(result.shape_valid, result.reason)
                self.assertFalse(result.physical_attested)
                self.assertFalse(result.real_broker_executed)
                self.assertFalse(result.actual_raw_response_redaction_certified)
                self.assertFalse(result.one_use_approved)
                self.assertFalse(result.cleanup_certified)
                self.assertEqual(le.safe_status(result)["RESULT"],
                                 "SOURCE_ONLY_RAW_PROJECTION_SHAPE")

    def test_raw_sensitive_data_removed_before_legacy_schema_validation(self):
        for route in range(6):
            c, req, obj = fixture(route)
            result = le.project_raw_response(c, req, encoded(obj))
            self.assertTrue(result.shape_valid, result.reason)
            projected = encoded(result.minimized).decode()
            for token in ("VERY_SECRET", "/home/secret", "secret-command",
                          "password", "DockerRootDir", "Mountpoint", "Source",
                          "NetworkSettings", "Binds", "RegistryConfig"):
                self.assertNotIn(token, projected)
                self.assertNotIn(token, str(le.safe_status(result)))

    def test_source_broker_runtime_and_transport_are_disabled(self):
        self.assertIs(le.BROKER_RUNTIME_ENABLED, False)
        self.assertIs(le.DOCKER_SOCKET_ACCESS_ALLOWED, False)
        self.assertIs(le.TRANSPORT_IMPLEMENTED, False)
        self.assertIs(le.IMAGE_PULL_ALLOWED, False)
        self.assertIs(le.SQL_ALLOWED, False)
        self.assertIs(le.RC01_ALLOWED, False)
        self.assertIsNone(le.APPROVED_ONE_USE_RUNTIME)
        self.assertEqual(le.SOURCE_PARENT, "9dcf25a4907d676a8c27070eab60603744c4ad2d")

    def test_existing_kt_kz_lb_shared_guards_untouched(self):
        self.assertIs(kt.EXECUTION_ENABLED, False)
        self.assertIs(kz.BROKER_EXECUTION_ENABLED, False)
        self.assertIs(lb.ADAPTER_EXECUTION_ENABLED, False)
        self.assertEqual(kt.CONFIG, "sha256:41096ae9913468679bb5e05bbc19f44edcaf0eae45d67fcde224ffdeda9d3a1f")

    def test_raw_body_empty_oversized_and_incorrect_types_blocked(self):
        c, req, obj = fixture(0)
        for raw in (None, "", {}, [], b"", b"x" * (le.MAX_RAW_BYTES + 1)):
            self.require_blocked(c, req, raw, "RAW_SIZE_OR_TYPE_INVALID")

    def test_invalid_utf8_malformed_json_and_duplicate_keys_blocked(self):
        c, req, obj = fixture(0)
        for raw in (b"\xff", b"{", b"undefined", b"[] extra", b'{"x":NaN}',
                    b'{"Version":"26.1.0","Version":"26.2.0","ApiVersion":"1.45"}',
                    b'{"\\u0056ersion":"26.1.0","Version":"26.2.0"}'):
            self.require_blocked(c, req, raw, "RAW_JSON_INVALID")

    def test_raw_nested_duplicate_secrets_blocked(self):
        c, req, obj = fixture(3)
        raw = encoded(obj).replace(b'"Privileged":false',
                                   b'"Privileged":false,"Privileged":true')
        self.require_blocked(c, req, raw, "RAW_JSON_INVALID")

    def test_depth_limit_blocks_parser_bombs(self):
        c, req, obj = fixture(0)
        nested = {"a": "x"}
        for _ in range(le.MAX_RAW_DEPTH + 2):
            nested = {"a": nested}
        self.require_blocked(c, req, encoded(nested), "RAW_NODES_OR_DEPTH_INVALID")

    def test_field_count_limit_blocks_unbounded_info(self):
        c, req, obj = fixture(1)
        obj.update({"Extra" + str(i): "x" for i in range(le.MAX_RAW_COLLECTION)})
        self.require_blocked(c, req, encoded(obj), "RAW_COLLECTION_LIMIT")

    def test_raw_long_env_value_blocks_without_reflection(self):
        c, req, obj = fixture(3)
        obj["Config"]["Env"][0] = "A" * (le.MAX_RAW_TEXT + 1)
        self.require_blocked(c, req, encoded(obj), "RAW_STRING_INVALID")

    def test_unknown_raw_scalar_float_blocks_before_projection(self):
        c, req, obj = fixture(0)
        obj["Extra"] = 1.5
        self.require_blocked(c, req, encoded(obj), "RAW_FLOAT_INVALID")

    def test_invalid_unicode_surrogate_blocks_before_projection(self):
        c, req, obj = fixture(0)
        obj["Extra"] = "\ud800"
        self.require_blocked(c, req, encoded(obj), "RAW_STRING_INVALID")

    def test_missing_necessary_docker_fields_blocks(self):
        for route, path in (
            (0, "Version"), (1, "ID"), (3, "HostConfig"),
            (4, "Volumes"), (5, "Labels"),
        ):
            c, req, obj = fixture(route)
            del obj[path]
            self.require_blocked(c, req, encoded(obj), "PROJECTION_SHAPE_INVALID")

    def test_missing_container_nested_fields_blocks(self):
        c, req, obj = fixture(3)
        del obj["HostConfig"]["PortBindings"]
        self.require_blocked(c, req, encoded(obj), "PROJECTION_SHAPE_INVALID")
        c, req, obj = fixture(3)
        del obj["Mounts"][0]["Driver"]
        self.require_blocked(c, req, encoded(obj), "PROJECTION_SHAPE_INVALID")

    def test_missing_container_inventory_id_blocks(self):
        c, req, obj = fixture(2)
        self.require_blocked(c, req, encoded([{"Names": ["x"]}]),
                             "PROJECTION_SHAPE_INVALID")

    def test_wrong_container_image_and_ownership_fails(self):
        for field, bad in (("Id", "b" * 64),
                           ("Image", "sha256:" + "c" * 64)):
            c, req, obj = fixture(3)
            obj[field] = bad
            self.require_blocked(c, req, encoded(obj),
                                 "MINIMIZED_CONTRACT_REJECTED")

    def test_raw_volume_extra_labels_rejected_not_hidden(self):
        c, req, obj = fixture(5)
        obj["Labels"]["hidden_owner"] = "production"
        self.require_blocked(c, req, encoded(obj), "PROJECTION_SHAPE_INVALID")

    def test_wrong_volume_name_labels_driver_fails(self):
        for field, bad in (("Name", "protected"), ("Driver", "nfs")):
            c, req, obj = fixture(5)
            obj[field] = bad
            self.require_blocked(c, req, encoded(obj),
                                 "MINIMIZED_CONTRACT_REJECTED")
        c, req, obj = fixture(5)
        obj["Labels"]["run_id"] = "322"
        self.require_blocked(c, req, encoded(obj), "MINIMIZED_CONTRACT_REJECTED")

    def test_unexpected_extra_mount_detected(self):
        c, req, obj = fixture(3)
        obj["Mounts"].append(deepcopy(obj["Mounts"][0]))
        self.require_blocked(c, req, encoded(obj), "MINIMIZED_CONTRACT_REJECTED")

    def test_effective_mount_not_matching_named_volume_fails(self):
        for key, bad in (
            ("Name", "production"),
            ("Type", "bind"),
            ("Destination", "/etc/mysql"),
            ("RW", False),
            ("RW", 1),
        ):
            c, req, obj = fixture(3)
            obj["Mounts"][0][key] = bad
            self.require_blocked(c, req, encoded(obj),
                                 "MINIMIZED_CONTRACT_REJECTED")

    def test_public_listener_hostnetwork_or_privilege_blocks(self):
        for field, bad in (("Privileged", True), ("NetworkMode", "host")):
            c, req, obj = fixture(3)
            obj["HostConfig"][field] = bad
            self.require_blocked(c, req, encoded(obj),
                                 "MINIMIZED_CONTRACT_REJECTED")
        c, req, obj = fixture(3)
        obj["HostConfig"]["PortBindings"]["3306/tcp"][0]["HostIp"] = "0.0.0.0"
        self.require_blocked(c, req, encoded(obj),
                             "MINIMIZED_CONTRACT_REJECTED")

    def test_raw_unexpected_portbinding_rejected_before_projection(self):
        c, req, obj = fixture(3)
        obj["HostConfig"]["PortBindings"]["22/tcp"] = [
            {"HostIp": "0.0.0.0", "HostPort": "22"}
        ]
        self.require_blocked(c, req, encoded(obj),
                             "PROJECTION_SHAPE_INVALID")

    def test_wrong_method_path_query_context_and_approval(self):
        for field, bad in (
            ("method", "DELETE"),
            ("path", "/containers/prune"),
            ("query", "all=1"),
            ("follow_redirects", True),
        ):
            c, req, obj = fixture(3)
            req[field] = bad
            self.require_blocked(c, req, encoded(obj))
        for field, bad in (("container_id", "b" * 64),
                           ("one_use_approval", "GRANTED"),
                           ("socket_access", True)):
            c, req, obj = fixture(3)
            c[field] = bad
            self.require_blocked(c, req, encoded(obj))

    def test_wrong_http_status_content_encoding_or_media_blocks(self):
        c, req, obj = fixture(0)
        for kwargs in (
            {"http_status": 201}, {"http_status": True},
            {"content_type": "application/json;charset=utf-8"},
            {"content_encoding": "gzip"},
        ):
            self.require_blocked(c, req, encoded(obj),
                                 "HTTP_RESPONSE_ENVELOPE_REJECTED", **kwargs)

    def test_inventory_duplicate_and_too_many_entries_fail(self):
        c, req, obj = fixture(2)
        obj.append(deepcopy(obj[0]))
        self.require_blocked(c, req, encoded(obj),
                             "MINIMIZED_CONTRACT_REJECTED")
        c, req, obj = fixture(2)
        obj = [{"Id": format(i, "064x")} for i in range(9)]
        self.require_blocked(c, req, encoded(obj),
                             "MINIMIZED_CONTRACT_REJECTED")

    def test_data_projection_does_not_claim_runtime_certification(self):
        c, req, obj = fixture(3)
        v = le.project_raw_response(c, req, encoded(obj))
        self.assertTrue(v.shape_valid)
        for attr in ("physical_attested", "real_broker_executed",
                     "one_use_approved", "actual_raw_response_redaction_certified",
                     "cleanup_certified"):
            self.assertIs(getattr(v, attr), False)
        report = le.safe_status(v)
        self.assertEqual(report["B12_CUTOVER"], "HARD_OFF")
        self.assertEqual(report["RUNTIME_APPROVAL"], "NOT_GRANTED")
        self.assertEqual(report["MIGRATION_092"], "NOT_AUTHORIZED")

    def test_safe_report_never_emits_untrusted_material(self):
        c, req, obj = fixture(3)
        obj["Config"]["Env"].append("VERY_PRIVATE_DO_NOT_EXPORT")
        obj["Mounts"][0]["Source"] = "/home/user/private"
        v = le.project_raw_response(c, req, encoded(obj))
        self.assertTrue(v.shape_valid)
        report = le.safe_status(v)
        for token in ("VERY_PRIVATE", "/home", CID, VOL, "Config", "Env"):
            self.assertNotIn(token, str(report))
        self.assertEqual(le.safe_status(None)["RESULT"],
                         "BLOCKED_SOURCE_CONTRACT")

    def test_source_has_no_network_socket_sql_or_command_exec_imports(self):
        s = (Path(__file__).resolve().parents[1] /
             "tools/b12_ws52le_raw_docker_response_projection_core.py"
             ).read_text("utf-8")
        ast_tree = ast.parse(s)
        imports = set()
        for node in ast.walk(ast_tree):
            if isinstance(node, ast.Import):
                imports.update(alias.name.split(".")[0] for alias in node.names)
            elif isinstance(node, ast.ImportFrom):
                imports.add((node.module or "").split(".")[0])
        allowed = {"__future__", "dataclasses", "json", "typing",
                   "b12_ws52kz_broker_projection_source_contract",
                   "b12_ws52lb_docker_redaction_adapter_source"}
        self.assertTrue(imports <= allowed, imports)
        self.assertNotIn('if __name__ == "__main__":', s)
        self.assertNotIn("def main(", s)


if __name__ == "__main__":
    unittest.main()
