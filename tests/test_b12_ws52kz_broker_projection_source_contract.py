"""WS52-KZ only synthetic offline negative controls. NO Docker or network."""
import ast
from copy import deepcopy
from pathlib import Path
import sys
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "tools"))
import b12_ws52kt_one_volume_identity_cleanup_contract as kt
import b12_ws52kv_physical_inspector_design_contract as kv
import b12_ws52kx_inspector_trust_boundary_design as kx
import b12_ws52kz_broker_projection_source_contract as kz

RUN = "321"
CID = "a" * 64
VOL = "b12-rc01-vol-r321-" + "b" * 16


def fixtures(route=3):
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
        "method": method,
        "path": path,
        "query": "",
        "headers": {},
        "body": None,
        "follow_redirects": False,
        "broker_token": None,
        "request_state": "NOT_EXECUTED_SOURCE_ONLY",
    }
    response = {
        "route_class": kz.ROUTE_NAMES[route],
        "http_status": 200,
        "content_type": "application/json",
        "content_encoding": "identity",
        "fixture_class": "SYNTHETIC_SCHEMA_ONLY",
        "projected_keys": [],
        "projection_state": "NOT_COLLECTED",
    }
    return context, request, response


class BrokerProjectionOfflineTests(unittest.TestCase):
    def assertBlocked(self, context, request, response, blocker=None):
        result = kz.assess_broker_fixture(context, request, response)
        self.assertFalse(result.shape_valid, result.blockers)
        self.assertFalse(result.physical_attested)
        self.assertFalse(result.broker_executed)
        self.assertFalse(result.one_use_approved)
        self.assertFalse(result.raw_response_exported)
        self.assertFalse(result.cleanup_certified)
        if blocker:
            self.assertIn(blocker, result.blockers)
        self.assertEqual(
            kz.sanitized_static_output(context, request, response)["RESULT"],
            "BLOCKED_SOURCE_CONTRACT",
        )

    def test_each_six_existing_get_metadata_templates_is_source_only(self):
        self.assertEqual(len(kx.DOCKER_METADATA_ROUTES), 6)
        self.assertEqual(len(kz.ROUTE_NAMES), 6)
        for i in range(6):
            with self.subTest(route=i):
                c, req, resp = fixtures(i)
                result = kz.assess_broker_fixture(c, req, resp)
                self.assertTrue(result.shape_valid, result.blockers)
                self.assertFalse(result.physical_attested)
                self.assertFalse(result.broker_executed)
                self.assertFalse(result.one_use_approved)
                self.assertFalse(result.cleanup_certified)
                self.assertFalse(result.raw_response_exported)
                self.assertEqual(
                    kz.sanitized_static_output(c, req, resp)["RESULT"],
                    "SOURCE_ONLY_SHAPE_NOT_ATTESTED",
                )

    def test_hard_disabled_broker_socket_network_sql_worker(self):
        self.assertIs(kz.BROKER_EXECUTION_ENABLED, False)
        self.assertIs(kz.DOCKER_SOCKET_ACCESS_GRANTED, False)
        self.assertIs(kz.DOCKER_MUTATIONS_ALLOWED, False)
        self.assertIs(kz.NETWORK_ALLOWED, False)
        self.assertIs(kz.SQL_ALLOWED, False)
        self.assertIs(kz.RC01_ALLOWED, False)
        self.assertIsNone(kz.PHYSICAL_BROKER_APPROVED)
        self.assertIsNone(kz.APPROVED_ONE_USE_RUN)

    def test_prior_kt_kv_kx_contracts_are_reused(self):
        self.assertEqual(kz.SOURCE_PARENT, "9da2dff0c3db1420524ac184a886354e217582b1")
        self.assertEqual(kz.REPO, kx.REPOSITORY)
        self.assertEqual(kz._routes(CID, VOL)[3][1],
                         "/containers/" + CID + "/json")
        self.assertEqual(kz._routes(CID, VOL)[5][1], "/volumes/" + VOL)
        self.assertIs(kt.EXECUTION_ENABLED, False)
        self.assertIs(kv.INSPECTOR_EXECUTION_ENABLED, False)
        self.assertIs(kx.INSPECTOR_EXECUTION_ENABLED, False)

    def test_context_missing_or_unexpected_keys_block(self):
        c, q, r = fixtures()
        del c["source_parent"]
        self.assertBlocked(c, q, r, "CONTEXT_KEYS_INVALID")
        c, q, r = fixtures()
        c["raw_credentials"] = "SECRET"
        self.assertBlocked(c, q, r, "CONTEXT_KEYS_INVALID")

    def test_request_missing_or_unexpected_keys_block(self):
        c, q, r = fixtures()
        del q["query"]
        self.assertBlocked(c, q, r, "REQUEST_KEYS_INVALID")
        c, q, r = fixtures()
        q["X-Auth"] = "SECRET"
        self.assertBlocked(c, q, r, "REQUEST_KEYS_INVALID")

    def test_response_missing_or_unexpected_keys_block(self):
        for field, value in (
            ("Env", ["PASSWORD=SECRET"]),
            ("Mounts", [{"Source": "/private/protected"}]),
            ("AuthConfig", {"password": "SECRET"}),
            ("raw_response", {"Id": CID}),
            ("Id", CID),
        ):
            with self.subTest(field=field):
                c, q, r = fixtures()
                r[field] = value
                self.assertBlocked(c, q, r, "PROJECTED_RESPONSE_KEYS_INVALID")
        c, q, r = fixtures()
        del r["fixture_class"]
        self.assertBlocked(c, q, r, "PROJECTED_RESPONSE_KEYS_INVALID")

    def test_wrong_repository_source_and_runner_scope(self):
        for field, bad in (
            ("repository", "attacker/unknown"),
            ("source_parent", "0" * 40),
            ("runner_scope", "USER_MAC"),
            ("review_status", "LIVE_APPROVED"),
        ):
            with self.subTest(field=field):
                c, q, r = fixtures()
                c[field] = bad
                self.assertBlocked(c, q, r, "UNPINNED_OR_PREMATURE_CONTEXT")

    def test_no_early_engine_or_egress_provenance_claim(self):
        for field, bad in (
            ("engine_provenance", "sha256:" + "a" * 64),
            ("egress_provenance", "FIREWALL_PASSED"),
            ("broker_state", "RUNNING"),
            ("one_use_approval", "YES"),
        ):
            with self.subTest(field=field):
                c, q, r = fixtures()
                c[field] = bad
                self.assertBlocked(c, q, r, "UNPINNED_OR_PREMATURE_CONTEXT")

    def test_socket_access_claim_rejected_even_for_get(self):
        for field, bad in (("socket_access", True), ("worker_socket_access", True)):
            c, q, r = fixtures()
            c[field] = bad
            self.assertBlocked(c, q, r, "FORBIDDEN_RUNTIME_PRIVILEGE_OR_SECRET")

    def test_runner_permissions_and_secrets_fail(self):
        for field, bad in (
            ("runner_permissions", {"contents": "read"}),
            ("runner_permissions", {"actions": "write"}),
            ("inherited_secrets", ["GITHUB_TOKEN"]),
            ("inherited_secrets", ["DB_PASSWORD"]),
        ):
            with self.subTest(field=field):
                c, q, r = fixtures()
                c[field] = bad
                self.assertBlocked(c, q, r, "FORBIDDEN_RUNTIME_PRIVILEGE_OR_SECRET")

    def test_run_and_resource_identity_must_be_tied(self):
        for field, bad in (
            ("run_id", "0"),
            ("run_id", "322"),
            ("run_id", True),
            ("container_id", "f" * 63),
            ("container_id", "not-known"),
            ("volume_name", "production-mysql"),
            ("volume_name", "b12-rc01-vol-r322-" + "b" * 16),
        ):
            with self.subTest(field=field):
                c, q, r = fixtures()
                c[field] = bad
                self.assertBlocked(c, q, r, "RESOURCE_IDENTITY_UNBOUND")

    def test_mutating_verbs_never_allowed(self):
        for method in ("POST", "PUT", "PATCH", "DELETE", "HEAD", "OPTIONS"):
            with self.subTest(method=method):
                c, q, r = fixtures()
                q["method"] = method
                self.assertBlocked(c, q, r, "UNALLOWLISTED_METHOD_PATH_OR_ID")

    def test_unreviewed_docker_paths_rejected(self):
        paths = (
            "/containers/create", "/containers/prune",
            "/volumes/prune", "/volumes/" + VOL + "/json",
            "/containers/" + CID + "/logs",
            "/containers/" + CID + "/archive",
            "/exec/" + CID + "/start", "/events", "/images/json",
            "/../containers/" + CID + "/json",
        )
        for path in paths:
            with self.subTest(path=path):
                c, q, r = fixtures()
                q["path"] = path
                self.assertBlocked(c, q, r, "UNALLOWLISTED_METHOD_PATH_OR_ID")

    def test_cross_resource_container_or_volume_id_block(self):
        for route, path in (
            (3, "/containers/" + "f" * 64 + "/json"),
            (5, "/volumes/" + "b12-rc01-vol-r322-" + "c" * 16),
        ):
            with self.subTest(route=route):
                c, q, r = fixtures(route)
                q["path"] = path
                self.assertBlocked(c, q, r, "UNALLOWLISTED_METHOD_PATH_OR_ID")

    def test_encoded_path_params_and_query_bypass_blocked(self):
        for path in (
            "/containers/%61" + "a" * 63 + "/json",
            "/containers/" + CID + "%2Fjson",
            "/containers/" + CID + "/json?size=1",
            "//containers/" + CID + "/json",
            "http://127.0.0.1/containers/" + CID + "/json",
        ):
            c, q, r = fixtures()
            q["path"] = path
            self.assertBlocked(c, q, r, "UNALLOWLISTED_METHOD_PATH_OR_ID")

    def test_any_query_string_rejected(self):
        for value in ("all=1", "filters=%7B%7D", "?all=1", "x=secret", None):
            c, q, r = fixtures()
            q["query"] = value
            self.assertBlocked(c, q, r, "REQUEST_HAS_UNREVIEWED_PARAMETERS_OR_EXECUTION")

    def test_any_header_or_authorization_rejected(self):
        for value in (
            {"Authorization": "Bearer secret"},
            {"X-Api-Version": "1.50"},
            {"Host": "other-host"},
            {"Cookie": "token=x"},
            {"Accept": "application/json"},
        ):
            c, q, r = fixtures()
            q["headers"] = value
            self.assertBlocked(c, q, r, "REQUEST_HAS_UNREVIEWED_PARAMETERS_OR_EXECUTION")

    def test_request_bodies_or_redirection_fail_closed(self):
        for field, bad in (
            ("body", "{}"), ("body", b"SECRET"),
            ("body", {"images": "all"}),
            ("follow_redirects", True),
            ("follow_redirects", "NO"),
            ("broker_token", "SECRET"),
            ("request_state", "EXECUTED"),
        ):
            with self.subTest(field=field):
                c, q, r = fixtures()
                q[field] = bad
                self.assertBlocked(c, q, r, "REQUEST_HAS_UNREVIEWED_PARAMETERS_OR_EXECUTION")

    def test_observation_claim_wrong_route_class_rejected(self):
        for route, claimed in ((0, "single_container"), (3, "engine_info"),
                               (5, "volume_inventory")):
            c, q, r = fixtures(route)
            r["route_class"] = claimed
            self.assertBlocked(c, q, r, "UNREVIEWED_RESPONSE_OR_RAW_DATA")

    def test_docker_raw_json_in_place_of_projection_blocked(self):
        for raw in (
            {"Id": CID, "Config": {"Env": ["MYSQL_ROOT_PASSWORD=SECRET"]}},
            {"Mounts": [{"Source": "/home/user/backups"}]},
            [{"Id": CID}],
            "SECRET",
            None,
        ):
            c, q, _ = fixtures()
            self.assertBlocked(c, q, raw, "PROJECTED_RESPONSE_KEYS_INVALID")

    def test_response_invalid_http_content_type_or_encoding(self):
        for field, bad in (
            ("http_status", 201),
            ("http_status", 307),
            ("http_status", 200.0),
            ("content_type", "text/plain"),
            ("content_type", "application/json; charset=utf-8"),
            ("content_encoding", "gzip"),
        ):
            with self.subTest(field=field):
                c, q, r = fixtures()
                r[field] = bad
                self.assertBlocked(c, q, r, "UNREVIEWED_RESPONSE_OR_RAW_DATA")

    def test_response_forged_physical_status_and_projected_data_block(self):
        for field, bad in (
            ("fixture_class", "ACTUAL_DOCKER"),
            ("projection_state", "PHYSICAL_ATTESTED"),
            ("projected_keys", ["Env"]),
            ("projected_keys", ["Mounts.Source"]),
            ("projected_keys", ["Id"]),
            ("projected_keys", ["secrets"]),
        ):
            c, q, r = fixtures()
            r[field] = bad
            self.assertBlocked(c, q, r, "UNREVIEWED_RESPONSE_OR_RAW_DATA")

    def test_summary_does_not_reflect_secrets_or_host_paths(self):
        for injection in (
            ("context", "private", "token=secret"),
            ("request", "Authorization", "Bearer secret"),
            ("response", "raw", {"Source": "/home/real/backup"}),
        ):
            c, q, r = fixtures()
            which, key, val = injection
            {"context": c, "request": q, "response": r}[which][key] = val
            summary = kz.sanitized_static_output(c, q, r)
            self.assertEqual(summary["RESULT"], "BLOCKED_SOURCE_CONTRACT")
            self.assertNotIn("secret", str(summary).lower())
            self.assertNotIn("/home/", str(summary))

    def test_passing_summary_remains_non_authorizing(self):
        c, q, r = fixtures()
        summary = kz.sanitized_static_output(c, q, r)
        self.assertEqual(summary["RESULT"], "SOURCE_ONLY_SHAPE_NOT_ATTESTED")
        self.assertEqual(summary["RUNTIME_APPROVAL"], "NOT_GRANTED")
        self.assertEqual(summary["PHYSICAL_DOCKER_INSPECTION"], "NO")
        self.assertEqual(summary["RAW_DOCKER_RESPONSE_EXPORTED"], "NO")
        self.assertEqual(summary["B12_CUTOVER"], "HARD_OFF")

    def test_malformed_inputs_fail_without_leaking_strings(self):
        for item in (None, [], "RAW_SECRET", 7, True):
            c, q, r = fixtures()
            self.assertBlocked(item, q, r, "CONTEXT_KEYS_INVALID")
            self.assertBlocked(c, item, r, "REQUEST_KEYS_INVALID")
            self.assertBlocked(c, q, item, "PROJECTED_RESPONSE_KEYS_INVALID")


    def test_nominal_policy_result_all_capability_flags_false(self):
        c, q, r = fixtures()
        check = kz.assess_broker_fixture(c, q, r)
        self.assertTrue(check.shape_valid, check.blockers)
        self.assertIs(check.physical_attested, False)
        self.assertIs(check.broker_executed, False)
        self.assertIs(check.one_use_approved, False)
        self.assertIs(check.raw_response_exported, False)
        self.assertIs(check.cleanup_certified, False)

    def test_response_projection_shape_cannot_coerce_to_empty_list(self):
        for bad in (None, (), {}, {"Env": []}, "[]", False):
            with self.subTest(value_type=type(bad).__name__):
                c, q, r = fixtures()
                r["projected_keys"] = bad
                self.assertBlocked(c, q, r, "UNREVIEWED_RESPONSE_OR_RAW_DATA")

    def test_no_response_status_or_header_type_confusion(self):
        for field, bad in (
            ("http_status", "200"),
            ("http_status", True),
            ("fixture_class", True),
            ("projection_state", None),
            ("content_encoding", "Identity"),
        ):
            with self.subTest(field=field):
                c, q, r = fixtures()
                r[field] = bad
                self.assertBlocked(c, q, r, "UNREVIEWED_RESPONSE_OR_RAW_DATA")

    def test_source_only_ast_import_guard_and_no_client(self):
        text = (Path(__file__).resolve().parents[1] /
                "tools/b12_ws52kz_broker_projection_source_contract.py").read_text("utf-8")
        tree = ast.parse(text)
        imports = []
        for node in ast.walk(tree):
            if isinstance(node, ast.Import):
                imports.extend(alias.name.split(".")[0] for alias in node.names)
            elif isinstance(node, ast.ImportFrom):
                imports.append((node.module or "").split(".")[0])
        allowed = {"__future__", "dataclasses", "typing", "re",
                   "b12_ws52kx_inspector_trust_boundary_design"}
        self.assertTrue(set(imports) <= allowed, imports)
        self.assertNotIn("def main(", text)
        self.assertNotIn('if __name__ == "__main__":', text)
        self.assertEqual(text.count("BROKER_EXECUTION_ENABLED = False"), 1)


if __name__ == "__main__":
    unittest.main()
