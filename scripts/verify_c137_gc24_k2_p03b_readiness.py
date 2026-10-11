#!/usr/bin/env python3
"""C137 GC24 K2/P03B: offline-only readiness and authorization gate verifier."""
import argparse
import ast
import copy
import hashlib
import json
import re
import sys
from pathlib import Path

sys.dont_write_bytecode = True
ROOT = Path(__file__).resolve().parent.parent
CERT = Path("/opt/renee-agrisuite-c137-gc")
SPEC = ROOT / "docs/C137_GC24_K2_P03B_RUNTIME_READINESS.json"
D12 = ROOT / "docs/C137_GC24_K1_D12_IMPORT_AUTHORITY_REQUIREMENTS.json"
D12_BLOB = "8e131d1fb4d8996d5462cc46bd8045172bb39308"
CERT_CONT = "acbbae9a9796b8d87e4bb6cd1fc069ed3623aabc"
P02_CONT = "9503771b47c6c2fe26bb3237da69a17874e724a3"
P02_HEAD = "1f2a22f668788e075adce8ec0fee0d6b2648f924"
CERT_HEAD = "322438870263825f9e739a12c4bd478983e67141"
PREP_HEAD = "0d290c1f25b4bdb186f4cefbc61b7ade1bbcdf49"

class Blocked(ValueError):
    pass

def require(condition, code):
    if not condition:
        raise Blocked(code)

def blob(raw):
    return hashlib.sha1(
        b"blob " + str(len(raw)).encode() + b"\0" + raw
    ).hexdigest()

def norm(s):
    return re.sub(r"\s+", "", s).lower()

def self_guard():
    tree = ast.parse(Path(__file__).read_text(encoding="utf-8"))
    allowed = {"argparse", "ast", "copy", "hashlib", "json", "re", "sys", "pathlib"}
    banned = {
        "connect", "execute", "executemany", "commit", "rollback",
        "write", "write_text", "write_bytes", "unlink", "rename",
        "mkdir", "rmdir", "remove", "popen", "Popen", "system",
        "urlopen", "request", "eval", "exec"
    }
    for node in ast.walk(tree):
        if isinstance(node, ast.Import):
            require(all(i.name in allowed for i in node.names), "UNSAFE_IMPORT")
        elif isinstance(node, ast.ImportFrom):
            require(node.module in allowed, "UNSAFE_IMPORT")
        elif isinstance(node, ast.Call):
            fn = node.func
            name = fn.id if isinstance(fn, ast.Name) else (
                fn.attr if isinstance(fn, ast.Attribute) else "")
            require(name not in banned, "UNSAFE_OPERATION")

def check_prior():
    d = D12.read_bytes()
    require(blob(d) == D12_BLOB, "D12_IMMUTABILITY_DRIFT")
    j = json.loads(d)
    gates = j["authorization_checklist"]
    require(len(gates) == 15, "D12_GATE_COUNT_DRIFT")
    require(
        [x["id"] for x in gates] == ["G%02d" % n for n in range(1, 16)],
        "D12_GATE_ID_DRIFT"
    )
    require(
        all(x["status"] == "BLOCKED_PENDING_SEPARATE_EVIDENCE_AND_APPROVAL"
            for x in gates), "D12_APPROVAL_DRIFT"
    )
    facts = j["unresolved_import_and_capacity_facts"]
    require(len(facts) == 86 and all(v is None for v in facts.values()),
            "D12_UNKNOWN_INPUT_DRIFT")

def validate(j):
    require(
        j["checkpoint"] == "GC24CG_K2_P03_STAGE_B"
        and j["version"] == "C137-GC24-K2-P03B-DB-READINESS-0"
        and j["scope"] == "OFFLINE_ISOLATED_RUNTIME_TEST_AUTHORIZATION_PREPARATION"
        and j["review_status"] == "PENDING_NODE_A_OFFLINE_VERIFICATION"
        and j["date"] == "2026-10-11", "SCOPE_OR_STATUS_DRIFT"
    )
    l = j["lineage"]
    require(
        l["parent_k2_p02_head"] == P02_HEAD
        and l["protected_reports_head"] == CERT_HEAD
        and l["unchanged_k1_k2p01_preparation_head"] == PREP_HEAD
        and l["k2_p02_php_blob"] == P02_CONT
        and l["frozen_k1_d12_blob"] == D12_BLOB,
        "LINEAGE_DRIFT"
    )
    require(
        l["k2_p03_stage_a_user_reported"] == "PASS_NO_DB"
        and l["stage_a_assertions"] ==
            {"fake_pdo_behavior": 38, "canonical_source_contract": 14,
             "runtime_integration_contract": 38, "prior_php_lint_files": 5}
        and l["stage_a_repeated_in_stage_b"] is False,
        "STAGE_A_EVIDENCE_MISREPRESENTED"
    )
    s = j["source_contracts"]
    require(len(s) == 10 and len({x["path"] for x in s}) == 10,
            "SOURCE_MANIFEST_SCOPE")
    require(
        s[0]["path"] == "lib/daily_population_continuity.php"
        and s[0]["blob"] == P02_CONT
        and all(len(x["markers"]) >= 2 for x in s),
        "SOURCE_MANIFEST_DRIFT"
    )
    p = j["future_approval_prerequisites"]
    require(len(p) == 16 and len({x["id"] for x in p}) == 16,
            "UNKNOWN_PREREQUISITES_SCOPE")
    require(
        all(x["value"] is None and x["status"] == "EVIDENCE_REQUIRED"
            for x in p), "PREAPPROVAL_INVENTED"
    )
    tests = j["future_runtime_test_cases"]
    require(len(tests) == 12 and
            [x["id"] for x in tests] ==
            ["B%02d" % n for n in range(1, 13)], "TEST_MATRIX_SCOPE")
    require(all(x["status"] == "NOT_RUN" and x["evidence"] is None
                for x in tests), "RUNTIME_RESULT_INVENTED")
    perm = j["permissions"]
    require(perm["read_frozen_docs_and_php_as_text"] is True
            and all(v is False for k, v in perm.items()
                    if k != "read_frozen_docs_and_php_as_text"),
            "UNAUTHORIZED_PERMISSION")
    g = j["gates"]
    require(
        g["prior_d12_authorization_gates"] == 15
        and g["prior_d12_unknown_inputs"] == 86
        and g["prior_d12_import_gates"] == "ALL_BLOCKED"
        and g["readiness_review"] == "PENDING_NODE_A"
        and g["future_db_authorization"] == "NOT_GRANTED"
        and g["future_test_data_authorization"] == "NOT_GRANTED"
        and g["runtime_test_suite"] == "NOT_EXECUTED"
        and g["runtime_outcome"] == "UNPROVEN"
        and g["capacity_150_vu"] == "HOLD"
        and g["capacity_200_vu"] == "PROHIBITED"
        and all(g[key] == 0 for key in
                ["database_connections", "database_reads", "database_writes",
                 "php_executed", "migrations", "restores",
                 "fixture_imports", "load_tests"]),
        "GATE_ESCALATION"
    )
    assets = j["protected_assets"]
    require(
        assets["unknown_active_test_database"] is None
        and assets["data_plane_identity_status"] == "NOT_INSPECTED_OR_AUTHORIZED"
        and assets["stage_b_source_only_checkout"] ==
            "/opt/renee-c137-k2-p03b-src",
        "DATA_PLANE_AUTHORITY_INVENTED"
    )

def sources(j):
    require(CERT.is_dir() and not CERT.is_symlink(),
            "CERTIFIED_SOURCE_ROOT_ABSENT")
    cert = CERT / "lib/daily_population_continuity.php"
    require(cert.is_file() and not cert.is_symlink()
            and blob(cert.read_bytes()) == CERT_CONT,
            "CERTIFIED_CONTINUITY_BLOB_DRIFT")
    probes = 0
    for entry in j["source_contracts"]:
        path = ROOT / entry["path"]
        require(path.is_file() and not path.is_symlink(),
                "SOURCE_FILE_ABSENT")
        raw = path.read_bytes()
        require(blob(raw) == entry["blob"], "SOURCE_BLOB_DRIFT")
        content = norm(raw.decode("utf-8"))
        for m in entry["markers"]:
            require(norm(m) in content, "SOURCE_MARKER_MISSING")
            probes += 1
    return probes

def negatives(j):
    mutations = []
    a = copy.deepcopy(j)
    a["permissions"]["database_connection"] = True
    mutations.append(a)
    a = copy.deepcopy(j)
    a["future_approval_prerequisites"][0]["value"] = "invented"
    mutations.append(a)
    a = copy.deepcopy(j)
    a["future_runtime_test_cases"][0]["status"] = "PASS"
    mutations.append(a)
    a = copy.deepcopy(j)
    a["gates"]["capacity_200_vu"] = "AUTHORIZED"
    mutations.append(a)
    a = copy.deepcopy(j)
    a["gates"]["prior_d12_import_gates"] = "PASS"
    mutations.append(a)
    a = copy.deepcopy(j)
    a["gates"]["database_reads"] = 1
    mutations.append(a)
    a = copy.deepcopy(j)
    a["lineage"]["k2_p02_php_blob"] = CERT_CONT
    mutations.append(a)
    for altered in mutations:
        try:
            validate(altered)
        except Blocked:
            pass
        else:
            raise Blocked("NEGATIVE_AUTHORIZATION_BYPASS")
    return len(mutations)

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--dry-run", action="store_true", required=True)
    parser.add_argument("--certified-source-root", required=True)
    parser.add_argument("--reporting-year", type=int, required=True)
    x = parser.parse_args()
    if (x.certified_source_root != str(CERT)
            or x.reporting_year != 2025):
        parser.error("STAGE_B_STATIC_ONLY_SCOPE")
    self_guard()
    check_prior()
    spec = json.loads(SPEC.read_text(encoding="utf-8"))
    validate(spec)
    probe_count = sources(spec)
    tests = negatives(spec)
    print("K2_P03B_STATIC_READONLY=PASS")
    print("K2_P03B_D12_IMMUTABLE_GATES=PASS")
    print("K2_P03B_STAGE_A_EVIDENCE=RETAINED_NOT_REPEATED")
    print("K2_P03B_SOURCE_FILES=10")
    print("K2_P03B_SOURCE_MARKERS=" + str(probe_count))
    print("K2_P03B_APPROVAL_PREREQUISITES=16_UNPROVEN")
    print("K2_P03B_TEST_SCENARIOS=12_NOT_RUN")
    print("K2_P03B_NEGATIVE_TAMPER_CASES=" + str(tests))
    print("K2_P03B_FUTURE_DB_AUTHORIZATION=NOT_GRANTED")
    print("K2_P03B_FUTURE_TEST_WRITES=NOT_GRANTED")
    print("K2_P03B_NO_REAL_DB_TEST=CONFIRMED")
    print("K2_P03B_REAL_DATABASE_CONNECTIONS=0")
    print("K2_P03B_REAL_DATABASE_WRITES=0")
    print("K2_P03B_PHP_EXECUTIONS=0")
    print("K2_P03B_APPLICATION_PHP_EDITS=0")
    print("K2_P03B_IMPORTS=0")
    print("K2_P03B_150_VU=HOLD")
    print("K2_P03B_200_VU=PROHIBITED")
    print("GC24CG_K2_P03_STAGE_B_OFFLINE_VERIFIER=PASS")

if __name__ == "__main__":
    main()
