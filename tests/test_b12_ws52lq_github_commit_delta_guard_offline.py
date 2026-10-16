"""Offline tests of inert WS52-LQ commit API approval proof. NO NETWORK."""
import ast
import io
import json
from pathlib import Path
import re
import textwrap
import unittest
from unittest import mock
import urllib.request

WF = ".github/workflows/c137-b12-ws52lp-gh-readonly-docker-one-use.yml"
APPROVAL = "docs/C137_GC24_K2_P03_B12_WS52LP_READONLY_PREFLIGHT_ONE_USE_APPROVAL.txt"
MESSAGE = "ci-b12(ws52lp-run): authorize fresh readonly Docker preflight"
PARENT = "1908c45f3526a511fd0037cae212133822fe7a5b"
SHA = "a" * 40


def load_from_disabled_workflow(text):
    start = "          class NoRedirect("
    end = "          def guard():"
    if text.count(start) != 1 or text.count(end) != 1:
        raise ValueError("SOURCE_ANCHOR_CHANGED")
    snippet = textwrap.dedent(text[text.index(start):text.index(end)])
    tree = ast.parse(snippet)
    if (len(tree.body) != 2 or
        not isinstance(tree.body[0], ast.ClassDef) or
        not isinstance(tree.body[1], ast.FunctionDef) or
        tree.body[0].name != "NoRedirect" or
        tree.body[1].name != "verify_commit_delta_via_api"):
        raise ValueError("UNEXPECTED_GUARD_SOURCE")

    class Reject(Exception):
        def __init__(self, code):
            self.code = code

    def require(cond, code):
        if not cond:
            raise Reject(code)

    scope = dict(urllib=urllib, json=json, re=re, GuardReject=Reject,
                 require=require, APPROVAL=APPROVAL, MESSAGE=MESSAGE,
                 audit={"APPROVAL_GITHUB_COMMIT_API": "NOT_VERIFIED"})
    exec(compile(tree, "<disabled-ws52lq-proof>", "exec"), scope)
    return scope


class FakeResponse:
    def __init__(self, body, *, status=200, url=None, encoding="identity"):
        self.body = body
        self.status = status
        self.url = url
        self.headers = {"Content-Encoding": encoding}
        self.limit = None
    def __enter__(self):
        return self
    def __exit__(self, *args):
        return False
    def geturl(self):
        return self.url
    def read(self, limit):
        self.limit = limit
        return self.body[:limit]


class FakeOpener:
    def __init__(self, response, fail=False):
        self.response, self.fail = response, fail
        self.url = None
    def open(self, request, timeout):
        if self.fail:
            raise OSError("DO_NOT_EXPOSE_PRIVATE_ERROR")
        self.url = request.full_url
        if self.response.url is None:
            self.response.url = self.url
        return self.response


def record():
    return {"sha": SHA, "parents": [{"sha": PARENT}],
            "files": [{"filename": APPROVAL, "status": "added"}],
            "commit": {"message": MESSAGE}}


class InertCommitDeltaProofTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.text = (Path(__file__).resolve().parents[1] / WF).read_text("utf-8")
        cls.scope = load_from_disabled_workflow(cls.text)

    def invoke(self, obj=None, *, raw=None, sha=SHA, status=200,
               url=None, encoding="identity", fail=False):
        body = json.dumps(record() if obj is None else obj).encode()
        res = FakeResponse(body if raw is None else raw, status=status,
                           url=url, encoding=encoding)
        opener = FakeOpener(res, fail=fail)
        with mock.patch.object(urllib.request, "build_opener",
                               return_value=opener):
            try:
                self.scope["verify_commit_delta_via_api"](sha)
                code = "PASS"
            except self.scope["GuardReject"] as error:
                code = error.code
        return code, res, opener

    def test_exact_pinned_single_added_approval_passes_offline(self):
        code, res, opener = self.invoke()
        self.assertEqual(code, "PASS")
        self.assertEqual(res.limit, 65537)
        self.assertEqual(opener.url,
                         "https://api.github.com/repos/"
                         "abeycity4u/Renee-Farms-V2.2.50-Batch3B1/commits/" + SHA)
        self.assertEqual(self.scope["audit"]["APPROVAL_GITHUB_COMMIT_API"],
                         "PASS_TLS_READONLY")

    def test_old_workflow_frozen_in_current_branch(self):
        self.assertIn("    if: $" + "{{ false }}", self.text)
        self.assertIn("WS52-LP physical preflight attempt was CONSUMED", self.text)
        self.assertNotIn("workflow_dispatch:", self.text)
        self.assertNotIn("services:", self.text)
        self.assertNotIn("uses: ", self.text)

    def test_no_dependency_on_push_added_file_list(self):
        self.assertNotIn('require(head.get("added")', self.text)
        self.assertIn("verify_commit_delta_via_api(sha)", self.text)

    def test_wrong_sha_blocked(self):
        for sha in ("", None, "0" * 39, "x" * 40):
            self.assertEqual(self.invoke(sha=sha)[0], "COMMIT_API_SHA_INVALID")

    def test_network_error_is_redacted(self):
        code, _, _ = self.invoke(fail=True)
        self.assertEqual(code, "COMMIT_API_FETCH_FAILED")
        self.assertNotIn("PRIVATE", code)

    def test_unexpected_http_status_or_redirect(self):
        self.assertEqual(self.invoke(status=429)[0],
                         "COMMIT_API_RESPONSE_UNEXPECTED")
        self.assertEqual(self.invoke(url="https://unexpected.invalid/")[0],
                         "COMMIT_API_RESPONSE_UNEXPECTED")

    def test_compressed_api_response_blocked(self):
        self.assertEqual(self.invoke(encoding="gzip")[0],
                         "COMMIT_API_ENCODING_UNEXPECTED")

    def test_body_length_cap(self):
        for body in (b"", b"X" * 65537):
            self.assertEqual(self.invoke(raw=body)[0],
                             "COMMIT_API_BODY_INVALID")

    def test_invalid_json_or_utf8(self):
        for body in (b"\xff", b"{"):
            self.assertEqual(self.invoke(raw=body)[0],
                             "COMMIT_API_JSON_INVALID")

    def test_non_object_response(self):
        self.assertEqual(self.invoke(raw=b"[]")[0],
                         "COMMIT_API_RECORD_INVALID")

    def test_wrong_commit_sha_or_parent(self):
        for kind in ("sha", "parent"):
            obj = record()
            if kind == "sha":
                obj["sha"] = "b" * 40
            else:
                obj["parents"] = [{"sha": "b" * 40}]
            label = ("COMMIT_API_SHA_MISMATCH" if kind == "sha"
                     else "COMMIT_API_PARENT_MISMATCH")
            self.assertEqual(self.invoke(obj)[0], label)

    def test_missing_or_merge_parents(self):
        for parents in ([], None, [{}, {}]):
            obj = record()
            obj["parents"] = parents
            self.assertEqual(self.invoke(obj)[0], "COMMIT_API_PARENT_COUNT")

    def test_approval_must_be_one_changed_file(self):
        for files in (None, [], [record()["files"][0]] * 2):
            obj = record()
            obj["files"] = files
            self.assertEqual(self.invoke(obj)[0],
                             "COMMIT_API_CHANGED_FILE_COUNT")

    def test_filename_and_added_status_must_match(self):
        for field, bad in (("filename", "docs/unrelated.txt"),
                           ("status", "modified"), ("status", "removed")):
            obj = record()
            obj["files"][0][field] = bad
            self.assertEqual(self.invoke(obj)[0],
                             "COMMIT_API_APPROVAL_DELTA_MISMATCH")

    def test_commit_message_must_match(self):
        obj = record()
        obj["commit"]["message"] = "UNAUTHORIZED"
        self.assertEqual(self.invoke(obj)[0], "COMMIT_API_MESSAGE_MISMATCH")

    def test_handler_denies_redirect(self):
        with self.assertRaises(self.scope["GuardReject"]) as found:
            self.scope["NoRedirect"]().redirect_request(
                None, None, 302, "Moved", {}, "https://elsewhere.invalid/")
        self.assertEqual(found.exception.code, "COMMIT_API_REDIRECT_BLOCKED")

    def test_no_raw_response_data_printed(self):
        for forbidden in ("print(record)", "print(raw)", "print(url)",
                          "traceback.print_exc"):
            self.assertNotIn(forbidden, self.text)


if __name__ == "__main__":
    unittest.main()
