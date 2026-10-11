#!/usr/bin/env python3
"""Z27K offline source-grounded decision register verifier; never executes PHP."""
import argparse
import ast
import copy
import hashlib
import json
import sys
from pathlib import Path

sys.dont_write_bytecode = True

from c137_gc24_fixture_dryrun import DB, FARM, YEAR, build
from c137_gc24_fixture_import_preflight import CERTIFIED_DIGESTS, ImportPreflightError
from c137_gc24_z27j_shared_writer_review import read_sources, review as j_review, SOURCE_ROOT

REGISTER = Path(__file__).resolve().parent.parent / "docs" / "C137_GC24_Z27K_DECISION_REGISTER.json"
EXPECTED_IDS = tuple("D%02d" % i for i in range(1, 13))
EXPECTED_PHASES = tuple("K%d" % i for i in range(8))
EXPECTED_PRIOR = {
    "R1": "20af186b096ba4e0404fe20442eb3091c5e0db25c46da45cb8213bfbd358e83d",
    "R2": "041a26508f5e6a2c3b3f7881740fd86bf35a05bec2a636fd76d658e205a35818",
}
CERT = "322438870263825f9e739a12c4bd478983e67141"
PREP_PARENT = "e050862fc75e8ead9193279c5b5f8a10983540ea"


def reject_bad_register(reg):
    if reg.get("certified_source_sha") != CERT:
        raise ImportPreflightError("CERTIFIED_SOURCE_IDENTITY_DRIFT")
    if reg.get("preparation_parent_sha") != PREP_PARENT:
        raise ImportPreflightError("PREPARATION_LINEAGE_DRIFT")
    if reg.get("as_of") != "2026-10-11":
        raise ImportPreflightError("AS_OF_CHANGED")
    if (reg.get("execution_enabled") is not False
            or reg.get("database_import_approved") is not False):
        raise ImportPreflightError("EXECUTION_OR_IMPORT_APPROVAL_INVENTED")
    if (reg.get("legacy_events_mutated") is not False
            or reg.get("chosen_historical_year") is not None):
        raise ImportPreflightError("UNAPPROVED_YEAR_OR_LEGACY_REBASE")
    if (reg.get("new_v2_event_stream") != "NOT_GENERATED"
            or reg.get("new_v2_event_digest") != "NOT_GENERATED"):
        raise ImportPreflightError("V2_EVENT_STREAM_FALSELY_CLAIMED")
    if reg.get("load_test_150_vu") != "HOLD" or reg.get("load_test_200_vu") != "PROHIBITED":
        raise ImportPreflightError("CAPACITY_SAFETY_DRIFT")
    rows = reg["decision_register"]
    if tuple(x["id"] for x in rows) != EXPECTED_IDS:
        raise ImportPreflightError("DECISION_IDS_CHANGED")
    for item in rows:
        if item["approval"] != "NOT_GRANTED" or item["selected"] is not None:
            raise ImportPreflightError("UNAUTHORIZED_DECISION_APPROVAL")
        if not item["owner"] or not item["options"] or not item["required_evidence"]:
            raise ImportPreflightError("DECISION_EVIDENCE_MISSING")
        if len(set(item["depends_on"])) != len(item["depends_on"]):
            raise ImportPreflightError("DUPLICATE_DECISION_DEPENDENCY")
        if not set(item["depends_on"]).issubset(set(EXPECTED_IDS) - {item["id"]}):
            raise ImportPreflightError("INVALID_DECISION_DEPENDENCY")
    if set(rows[-1]["depends_on"]) != set(EXPECTED_IDS[:-1]):
        raise ImportPreflightError("IMPORT_DEPENDENCY_BYPASS")
    if tuple(x["id"] for x in reg["implementation_phases"]) != EXPECTED_PHASES:
        raise ImportPreflightError("IMPLEMENTATION_PHASE_DRIFT")
    if reg["implementation_phases"][-1]["status"] != "WRITES_PROHIBITED":
        raise ImportPreflightError("UNAUTHORIZED_IMPORT_PHASE")
    if reg.get("prior_z27j_review_sha256") != EXPECTED_PRIOR:
        raise ImportPreflightError("PRIOR_REVIEW_HASH_DRIFT")
    if reg.get("legacy_digest_sha256") != {
        "R1": CERTIFIED_DIGESTS["R1"], "R2": CERTIFIED_DIGESTS["R2"]
    }:
        raise ImportPreflightError("CERTIFIED_LEGACY_DIGEST_DRIFT")
    return rows


def assert_rejected(data, label):
    try:
        reject_bad_register(data)
    except (ImportPreflightError, ValueError, KeyError, TypeError):
        return
    raise AssertionError(label)


def static_no_io_check():
    parsed = ast.parse(Path(__file__).read_text(encoding="utf-8"))
    allowed = {
        "argparse", "ast", "copy", "hashlib", "json", "sys", "pathlib",
        "c137_gc24_fixture_dryrun",
        "c137_gc24_fixture_import_preflight",
        "c137_gc24_z27j_shared_writer_review",
    }
    denied = {
        "connect", "execute", "commit", "write", "write_text", "write_bytes",
        "unlink", "mkdir", "system", "Popen", "popen", "urlopen",
        "request", "exec", "eval", "__import__",
    }
    for node in ast.walk(parsed):
        if isinstance(node, ast.Import):
            if any(alias.name not in allowed for alias in node.names):
                raise ImportPreflightError("UNSAFE_MODULE_IMPORT")
        if isinstance(node, ast.ImportFrom):
            if node.module not in allowed:
                raise ImportPreflightError("UNSAFE_MODULE_IMPORT")
        if isinstance(node, ast.Call):
            func = node.func
            name = func.id if isinstance(func, ast.Name) else (
                func.attr if isinstance(func, ast.Attribute) else ""
            )
            if name in denied:
                raise ImportPreflightError("UNSAFE_IO_CALL")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--dry-run", required=True, action="store_true")
    parser.add_argument("--database", required=True)
    parser.add_argument("--farm-slug", required=True)
    parser.add_argument("--year", required=True, type=int)
    parser.add_argument("--as-of", required=True)
    parser.add_argument("--candidate-year", required=True, type=int)
    parser.add_argument("--certified-source-root", required=True)
    args = parser.parse_args()
    if (args.database, args.farm_slug, args.year) != (DB, FARM, YEAR):
        parser.error("Z27K_TARGET_GUARD_BLOCKED")
    if (args.as_of, args.candidate_year) != ("2026-10-11", 2025):
        parser.error("Z27K_DATE_AUTHORITY_BLOCKED")
    if args.certified_source_root != SOURCE_ROOT:
        parser.error("Z27K_SOURCE_SCOPE_BLOCKED")
    static_no_io_check()
    reg = json.loads(REGISTER.read_text(encoding="utf-8"))
    rows = reject_bad_register(reg)
    for key, value in (
        ("chosen_historical_year", 2025),
        ("database_import_approved", True),
        ("new_v2_event_stream", "REPLACED"),
        ("load_test_200_vu", "ALLOWED"),
    ):
        wrong = copy.deepcopy(reg)
        wrong[key] = value
        assert_rejected(wrong, key)
    wrong = copy.deepcopy(reg)
    wrong["decision_register"][0]["approval"] = "GRANTED"
    assert_rejected(wrong, "FAKE_APPROVAL")
    wrong = copy.deepcopy(reg)
    wrong["decision_register"][-1]["depends_on"] = ["D01"]
    assert_rejected(wrong, "IMPORT_PRECONDITION_REMOVED")
    print("Z27K_STATIC_NO_IO=PASS")
    print("Z27K_APPROVAL_AND_SCOPE_REJECTIONS=PASS")

    source = read_sources(Path(args.certified_source_root))
    for scenario in ("R1", "R2"):
        original = build(scenario, include_records=True)
        before = copy.deepcopy(original)
        result = j_review(scenario, original, source)
        if original != before:
            raise ImportPreflightError("ORIGINAL_EVENTS_MUTATED")
        if result["review_specification_digest_sha256"] != EXPECTED_PRIOR[scenario]:
            raise ImportPreflightError("Z27J_SPEC_DRIFT")
        if result["legacy_event_digest_sha256"] != CERTIFIED_DIGESTS[scenario]:
            raise ImportPreflightError("Z23_DIGEST_DRIFT")
        document = {"scenario": scenario, "register": reg,
                    "previous_source_review_digest":
                    result["review_specification_digest_sha256"]}
        spec_sha = hashlib.sha256(json.dumps(
            document, sort_keys=True, separators=(",", ":")
        ).encode()).hexdigest()
        print(scenario + "_SOURCE_REVIEW_LINEAGE=PASS")
        print(scenario + "_LEGACY_EVENTS=" + str(result["legacy_event_count"]))
        print(scenario + "_DECISIONS_UNRESOLVED=" + str(len(rows)))
        print(scenario + "_DECISION_DOSSIER_SPEC_SHA256=" + spec_sha)
        print(scenario + "_READINESS=DECISION_DOSSIER_UNAPPROVED")
    print("DECISIONS_APPROVED=0")
    print("DECISIONS_SELECTED=0")
    print("NEW_V2_EVENTS_GENERATED=NO")
    print("CANONICAL_PHP_EXECUTED=NO")
    print("DATABASE_CONNECTIONS=0")
    print("DATABASE_WRITES=0")
    print("SOURCE_WRITES=0")
    print("NEW_LOAD_TESTS=0")
    print("IMPORT_EXECUTION_ENABLED=NO")
    print("GC24CG_Z27K_NODE_A_OFFLINE_VERIFIER=PASS")


if __name__ == "__main__":
    main()
