"""WS52-LM source-only diagnostics tests, with synthetic GITHUB_EVENT_PATH.

Loads only REPO/REF/APPROVAL/MESSAGE, audit, GuardReject, require, guard
definitions extracted from frozen YAML text; never runs workflow, Docker,
network, SQL, shell, or any GitHub Actions job. Builtins.open is mocked.
"""
import ast
import io
import json
import os
from pathlib import Path
import re
import sys
import textwrap
import unittest
from unittest import mock

WORKFLOW = ".github/workflows/c137-b12-ws52lk-gh-readonly-docker-one-use.yml"
SOURCE_PARENT = "1acd7c4ac5523ad7086e68e2dfb879ea53fc9cb4"
ALLOWED_REPO = "abeycity4u/Renee-Farms-V2.2.50-Batch3B1"
ALLOWED_REF = "refs/heads/c137-gc24-k2p03-b12-ws52lk-gh-readonly-docker-preflight"
ALLOWED_MESSAGE = "ci-b12(ws52lk-run): approve one readonly Docker preflight"
ALLOWED_DOCUMENT = "docs/C137_GC24_K2_P03_B12_WS52LK_READONLY_PREFLIGHT_ONE_USE_APPROVAL.txt"
SHA = "a" * 40
BEFORE_SHA = "b" * 40


def _get_workflow():
    return (Path(__file__).resolve().parents[1] / WORKFLOW).read_text("utf-8")


def _guard_only(workflow_source):
    a = "          REPO = "
    z = "          def readonly(*args):"
    if workflow_source.count(a) != 1 or workflow_source.count(z) != 1:
        raise AssertionError("GUARD_SOURCE_ANCHOR_CHANGED")
    extracted = textwrap.dedent(
        workflow_source.split(a, 1)[1].split(z, 1)[0]
    )
    extracted = "REPO = " + extracted
    tree = ast.parse(extracted)
    permitted = {
        "REPO", "REF", "APPROVAL", "MESSAGE", "audit",
        "emit", "GuardReject", "require", "guard",
    }
    names = set()
    for node in tree.body:
        if isinstance(node, ast.Assign):
            if len(node.targets) != 1 or not isinstance(node.targets[0], ast.Name):
                raise AssertionError("UNKNOWN_ASSIGNMENT")
            names.add(node.targets[0].id)
        elif isinstance(node, (ast.FunctionDef, ast.ClassDef)):
            names.add(node.name)
        else:
            raise AssertionError("UNSAFE_TOP_LEVEL")
    if names != permitted:
        raise AssertionError("DIAGNOSTIC_SOURCE_DEFINITIONS_CHANGED")
    env = {"os": os, "re": re, "json": json, "sys": sys}
    exec(compile(tree, "<guard-source-only>", "exec"), env)
    return env


def _default_env():
    return {
        "GITHUB_ACTIONS": "true",
        "GITHUB_EVENT_NAME": "push",
        "GITHUB_REPOSITORY": ALLOWED_REPO,
        "GITHUB_REF": ALLOWED_REF,
        "GITHUB_RUN_NUMBER": "1",
        "GITHUB_RUN_ATTEMPT": "1",
        "RUNNER_OS": "Linux",
        "RUNNER_ARCH": "X64",
        "RUNNER_ENVIRONMENT": "github-hosted",
        "GITHUB_SHA": SHA,
        "GITHUB_EVENT_PATH": "/synthetic/event.json",
    }


def _default_event():
    return {
        "head_commit": {
            "id": SHA, "message": ALLOWED_MESSAGE,
            "added": [ALLOWED_DOCUMENT], "modified": [], "removed": [],
        },
        "commits": [{"id": SHA}],
        "ref": ALLOWED_REF, "deleted": False,
        "repository": {"full_name": ALLOWED_REPO},
        "before": BEFORE_SHA,
    }


def _check(namespace, env=None, event=None, content=None, unreadable=False):
    settings = _default_env() if env is None else env
    payload = _default_event() if event is None else event
    raw = json.dumps(payload, separators=(",", ":")).encode() if content is None else content
    handle = OSError("DO_NOT_ECHO_PRIVATE_PATH") if unreadable else io.BytesIO(raw)
    with mock.patch.dict(os.environ, settings, clear=True):
        if unreadable:
            context = mock.patch("builtins.open", side_effect=handle)
        else:
            context = mock.patch("builtins.open", return_value=handle)
        with context:
            try:
                namespace["guard"]()
            except namespace["GuardReject"] as problem:
                return problem.code
    return "PASS"


class ReadonlyPreflightGuardDiagnosticsTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.workflow_source = _get_workflow()
        cls.namespace = _guard_only(cls.workflow_source)

    def test_successful_synthetic_exact_one_use_event(self):
        self.assertEqual(_check(self.namespace), "PASS")
        self.assertEqual(self.namespace["audit"]["ONE_USE_EVENT_GUARD"], "PASS")
        self.assertEqual(self.namespace["audit"]["GITHUB_HOSTED_RUNNER_CLAIM"],
                         "PASS_SELF_REPORTED")

    def test_workflow_dormant_even_after_approval_file_push(self):
        text = self.workflow_source
        self.assertIn("    if: \${{ false }}".replace("\\", ""), text)
        self.assertNotIn("workflow_dispatch:", text)
        self.assertNotIn("services:", text)
        self.assertNotIn("uses: ", text)
        self.assertIn("WS52-LK one-use attempt is CONSUMED", text)

    def test_no_app_production_or_test_execution_added(self):
        text = self.workflow_source
        for forbidden in (
            "docker run ", "docker pull ", "docker exec ", "docker rm ",
            "docker stop ", "mariadb --execute", "php tests/integration",
            "git checkout ", "aws ", "workflow_dispatch:",
        ):
            self.assertNotIn(forbidden, text)

    def test_event_labels_are_fixed_codes_not_user_supplied_values(self):
        text = self.workflow_source
        self.assertIn('emit("BLOCKED", issue.code)', text)
        self.assertIn('emit("BLOCKED", "UNEXPECTED_DOCKER_PREFLIGHT_FAILURE")', text)
        self.assertNotIn("print(event)", text)
        self.assertNotIn("print(os.environ)", text)
        self.assertNotIn("print(raw)", text)
        self.assertNotIn("traceback.print_exc", text)

    def test_repo_source_header_unchanged(self):
        n = self.namespace
        self.assertEqual(n["REPO"], ALLOWED_REPO)
        self.assertEqual(n["REF"], ALLOWED_REF)
        self.assertEqual(n["APPROVAL"], ALLOWED_DOCUMENT)
        self.assertEqual(n["MESSAGE"], ALLOWED_MESSAGE)

    def test_github_actions_environment_stage(self):
        for value in ("false", "", None, True):
            env = _default_env()
            if value is None:
                del env["GITHUB_ACTIONS"]
            else:
                env["GITHUB_ACTIONS"] = value
            with self.subTest(value=value):
                self.assertEqual(_check(self.namespace, env=env),
                                 "ENV_GITHUB_ACTIONS")

    def test_push_event_type_stage(self):
        env = _default_env()
        env["GITHUB_EVENT_NAME"] = "workflow_dispatch"
        self.assertEqual(_check(self.namespace, env=env), "ENV_EVENT_NOT_PUSH")

    def test_repository_ref_stages(self):
        for key, value, expected in (
            ("GITHUB_REPOSITORY", "other/repository", "ENV_REPOSITORY_MISMATCH"),
            ("GITHUB_REF", "refs/heads/other", "ENV_REF_MISMATCH"),
        ):
            env = _default_env()
            env[key] = value
            with self.subTest(key=key):
                self.assertEqual(_check(self.namespace, env=env), expected)

    def test_one_use_run_number_or_attempt_stages(self):
        for field, code in (
            ("GITHUB_RUN_NUMBER", "ENV_RUN_NUMBER_NOT_ONE"),
            ("GITHUB_RUN_ATTEMPT", "ENV_RUN_ATTEMPT_NOT_ONE"),
        ):
            for value in ("2", "0", "", None):
                env = _default_env()
                if value is None:
                    del env[field]
                else:
                    env[field] = value
                with self.subTest(field=field, value=value):
                    self.assertEqual(_check(self.namespace, env=env), code)

    def test_runner_os_arch_environment_each_identified(self):
        for key, bad, label in (
            ("RUNNER_OS", "Windows", "RUNNER_OS_UNEXPECTED"),
            ("RUNNER_ARCH", "ARM64", "RUNNER_ARCH_UNEXPECTED"),
            ("RUNNER_ENVIRONMENT", "self-hosted",
             "RUNNER_ENVIRONMENT_UNVERIFIED"),
        ):
            env = _default_env()
            env[key] = bad
            with self.subTest(key=key):
                self.assertEqual(_check(self.namespace, env=env), label)

    def test_missing_runner_environment_returns_diagnostic_code(self):
        env = _default_env()
        del env["RUNNER_ENVIRONMENT"]
        self.assertEqual(_check(self.namespace, env=env),
                         "RUNNER_ENVIRONMENT_UNVERIFIED")

    def test_invalid_git_head_sha_stages(self):
        for bad in ("", "0" * 39, "A" * 40, None):
            env = _default_env()
            if bad is None:
                del env["GITHUB_SHA"]
            else:
                env["GITHUB_SHA"] = bad
            self.assertEqual(_check(self.namespace, env=env),
                             "ENV_HEAD_SHA_INVALID")

    def test_missing_event_file_env_identified(self):
        env = _default_env()
        del env["GITHUB_EVENT_PATH"]
        self.assertEqual(_check(self.namespace, env=env), "EVENT_PATH_MISSING")

    def test_event_file_unreadable_returns_constant_label(self):
        self.assertEqual(_check(self.namespace, unreadable=True),
                         "EVENT_FILE_UNREADABLE")

    def test_event_file_empty_or_oversized(self):
        for blob in (b"", b" " * 262145):
            self.assertEqual(_check(self.namespace, content=blob),
                             "EVENT_BYTES_OUT_OF_BOUNDS")

    def test_event_file_invalid_json_or_utf8(self):
        for blob in (b"\xff", b"{", b"null bad", b""):
            expected = "EVENT_BYTES_OUT_OF_BOUNDS" if not blob else "EVENT_JSON_INVALID"
            self.assertEqual(_check(self.namespace, content=blob), expected)

    def test_event_root_must_be_object(self):
        self.assertEqual(_check(self.namespace, content=b'[]'),
                         "EVENT_ROOT_NOT_OBJECT")

    def test_event_head_commit_must_be_object(self):
        for value in (None, "wrong", [], {}):
            event = _default_event()
            event["head_commit"] = value
            expected = ("EVENT_COMMIT_SHA_MISMATCH" if value == {}
                        else "EVENT_HEAD_COMMIT_MISSING")
            self.assertEqual(_check(self.namespace, event=event), expected)

    def test_event_head_sha_and_message_each_identified(self):
        for key, value, label in (
            ("id", "b" * 40, "EVENT_COMMIT_SHA_MISMATCH"),
            ("message", "unexpected", "EVENT_COMMIT_MESSAGE_MISMATCH"),
        ):
            event = _default_event()
            event["head_commit"][key] = value
            self.assertEqual(_check(self.namespace, event=event), label)

    def test_approval_file_delta_each_stage_identified(self):
        for key, value, label in (
            ("added", [], "EVENT_ADDED_FILES_MISMATCH"),
            ("added", [ALLOWED_DOCUMENT, "extra"], "EVENT_ADDED_FILES_MISMATCH"),
            ("modified", ["settings"], "EVENT_MODIFIED_FILES_MISMATCH"),
            ("removed", ["config"], "EVENT_REMOVED_FILES_MISMATCH"),
        ):
            event = _default_event()
            event["head_commit"][key] = value
            with self.subTest(key=key, value=value):
                self.assertEqual(_check(self.namespace, event=event), label)

    def test_commit_count_must_be_one(self):
        for value in ([], [{}, {}], None):
            event = _default_event()
            event["commits"] = value
            self.assertEqual(_check(self.namespace, event=event),
                             "EVENT_COMMIT_COUNT_NOT_ONE")

    def test_event_ref_deleted_and_repo_specific_labels(self):
        for field, value, label in (
            ("ref", "refs/heads/wrong", "EVENT_REF_MISMATCH"),
            ("deleted", True, "EVENT_DELETED_OR_MISSING"),
            ("deleted", None, "EVENT_DELETED_OR_MISSING"),
            ("repository", {"full_name": "unreviewed"},
             "EVENT_REPOSITORY_MISMATCH"),
        ):
            event = _default_event()
            event[field] = value
            with self.subTest(field=field):
                self.assertEqual(_check(self.namespace, event=event), label)

    def test_before_commit_sha_format_nonzero(self):
        for value in (None, "0" * 40, "f" * 39, "Z" * 40):
            event = _default_event()
            event["before"] = value
            self.assertEqual(_check(self.namespace, event=event),
                             "EVENT_BEFORE_SHA_INVALID")

    def test_failure_code_cannot_leak_supplied_secret(self):
        event = _default_event()
        event["head_commit"]["message"] = "SECRET_TOKEN_ABC123"
        result = _check(self.namespace, event=event)
        self.assertEqual(result, "EVENT_COMMIT_MESSAGE_MISMATCH")
        self.assertNotIn("SECRET_TOKEN", result)
        self.assertNotIn("SECRET_TOKEN", str(self.namespace["audit"]))

    def test_source_guard_has_no_hidden_network_or_shell_calls(self):
        tree = ast.parse(textwrap.dedent(
            self.workflow_source.split("          REPO = ", 1)[1].split(
                "          def readonly(*args):", 1
            )[0].replace("", "", 1)  # no-op for source preservation
        ))
        self.assertTrue(isinstance(tree, ast.Module))
        self.assertNotIn("subprocess", self.workflow_source.split(
            "          def guard():", 1)[1].split(
            "          def readonly(*args):", 1
        )[0])
        self.assertNotIn("socket", self.workflow_source.split(
            "          def guard():", 1)[1].split(
            "          def readonly(*args):", 1
        )[0])


if __name__ == "__main__":
    unittest.main()
