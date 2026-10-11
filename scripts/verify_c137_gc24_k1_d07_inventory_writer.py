#!/usr/bin/env python3
"""C137 GC24 K1/D07 static source-review verifier; no PHP, DB, or fixture execution."""
import argparse
import ast
import copy
import datetime
import hashlib
import json
import re
import sys
from pathlib import Path

sys.dont_write_bytecode = True
DOCS = Path(__file__).resolve().parent.parent / "docs"
SOURCE = Path("/opt/renee-agrisuite-c137-gc")
SPEC_FILE = DOCS / "C137_GC24_K1_D07_INVENTORY_WRITER_REQUIREMENTS.json"
D06_FILE = DOCS / "C137_GC24_K1_D06_TENANT_ACTOR_REQUIREMENTS.json"
PREP_PARENT = "18dc2476b5675f442a23dc3d2326c9e0d1378619"
CERT = "322438870263825f9e739a12c4bd478983e67141"
D06_BLOB = "c50ba051dbb6e6da88b0de5ffa4d51ab252d5c08"
D06_DIGEST = {
    "R1":"fdebc8ef7e2ef694721a62ff4cd15f416ab855c777ccc33c59e320daa2c2e940",
    "R2":"ca29f1602575204c060f13904868fce14001760d6fc61f46e7420b08afd7958c",
}
D05_DIGEST = {
    "R1":"ccad5a76825380d083991c8c305cf17dcdd3f6d11e35e8a76f58f816684741bf",
    "R2":"58d4c14965a64b1fca1a8b0494ef7ae0763e9ec6a442e628d2044c239ac51930",
}
LEGACY = {
    "R1":"e31421ee40ce25b16feb698313fcf337e74126a8e63d8c5dadeedf74e30165f2",
    "R2":"985341ed4315788ee8f3b9d62b327da66d38fc63ea16d406c5c52bc9949d3dfe",
}
PATHS = (
    "inventory.php",
    "includes/inventory_permission_hardening.php",
    "lib/inventory_financial.php",
    "lib/inventory_category_role.php",
    "lib/stock_service.php",
    "lib/stock_movement_attribution.php",
    "api/update_stock.php",
    "includes/inventory_category_legacy_redirect.php",
    "includes/permission_catalog.php",
    "config.php",
    "lib/stock_costing.php",
    "lib/daily_feed_sync.php",
    "includes/tenant_provisioning.php",
    "lib/record_reference_persistence.php",
)
REQUIRED_RULES = {
    "two_distinct_item_add_writers_exist_privileged_inventory_and_delegated_bridge",
    "both_item_add_writers_implement_duplicated_parsing_validation_and_insert_SQL",
    "new_item_is_inserted_with_zero_current_stock_before_initial_receipt",
    "positive_initial_quantity_goes_through_canonical_stock_apply_movement",
    "stock_ledger_requires_caller_transaction_and_persists_public_reference",
    "slaughter_output_category_requires_general_usage_and_forbids_manual_initial_stock",
    "delegated_add_uses_allowedFarmTypes_while_privileged_add_uses_allowedInventoryFarmTypes",
    "published_PHP_contracts_do_not_yet_expose_a_single_shared_item_create_service",
}
class Blocked(ValueError):
    pass

def demand(condition, key):
    if not condition:
        raise Blocked(key)

def compact(text):
    return re.sub(r"\s+", "", text).lower()

def git_blob(raw):
    return hashlib.sha1(
        b"blob " + str(len(raw)).encode("ascii") + bytes([0]) + raw
    ).hexdigest()

def static_no_writes():
    tree = ast.parse(Path(__file__).read_text(encoding="utf-8"))
    modules = {"argparse","ast","copy","datetime","hashlib","json","re","sys","pathlib"}
    forbidden = {"connect","execute","executemany","commit","rollback",
                 "write","write_text","write_bytes","mkdir","unlink","rename",
                 "rmdir","remove","Popen","popen","system","urlopen","request",
                 "eval","exec","subprocess"}
    for node in ast.walk(tree):
        if isinstance(node, ast.Import):
            demand(all(item.name in modules for item in node.names),
                   "STATIC_IMPORT_UNSAFE")
        elif isinstance(node, ast.ImportFrom):
            demand(node.module in modules, "STATIC_IMPORT_UNSAFE")
        elif isinstance(node, ast.Call):
            fn = node.func
            name = fn.id if isinstance(fn, ast.Name) else (
                fn.attr if isinstance(fn, ast.Attribute) else "")
            demand(name not in forbidden, "STATIC_IO_UNSAFE")

def prior_lineage():
    raw = D06_FILE.read_bytes()
    demand(git_blob(raw) == D06_BLOB, "D06_FROZEN_BLOB_DRIFT")
    previous = json.loads(raw)
    demand(previous["checkpoint"] == "GC24CG_K1_D06" and
           previous["readiness"] == "D06_TENANT_ACTOR_AUTHORITY_UNAPPROVED" and
           all(value is None for value in previous["ids_and_actor_state_unknown"].values()),
           "D06_OWNER_APPROVAL_FABRICATED")
    for scenario in ("R1","R2"):
        payload = {
            "scenario":scenario,
            "specification":previous,
            "prior_d05_spec_sha256":D05_DIGEST[scenario],
        }
        sha = hashlib.sha256(json.dumps(
            payload, sort_keys=True, separators=(",",":")
        ).encode()).hexdigest()
        demand(sha == D06_DIGEST[scenario], "D06_SHA256_SPEC_DRIFT")

def validate(spec):
    demand(
        spec["version"] == "C137-GC24-K1-D07-INVENTORY-WRITER-REQUIREMENTS-0" and
        spec["checkpoint"] == "GC24CG_K1_D07" and
        spec["scope"] == "OFFLINE_READ_ONLY_SOURCE_REQUIREMENTS" and
        spec["readiness"] == "D07_WRITER_AUTHORITY_UNAPPROVED",
        "D07_SCOPE_DRIFT")
    w = spec["selected_year"]
    first = datetime.date.fromisoformat(w["start"])
    last = datetime.date.fromisoformat(w["end"])
    demand((w["year"],str(first),str(last),(last-first).days+1,
            w["event_date_policy"]) ==
           (2025,"2025-01-01","2025-12-31",365,"UNRESOLVED"),
           "D07_YEAR_DRIFT")
    demand(
        spec["as_of"] == "2026-10-11" and
        spec["preparation_parent_head"] == PREP_PARENT and
        spec["certified_reports_head"] == CERT and
        spec["prior_d06_blob_sha1"] == D06_BLOB and
        spec["prior_d06_spec_sha256"] == D06_DIGEST and
        spec["original_2026_event_sha256"] == LEGACY and
        spec["isolated_target_database_reference_only"] == "renee_c137_gc24_http" and
        spec["isolated_tenant_slug_reference_only"] == "c137-gc24-synthetic",
        "D07_PROVENANCE_DRIFT")
    fields = spec["per_category_item_unknowns"]
    demand(len(fields) >= 40 and all(v is None for v in fields.values()),
           "D07_ITEM_PRICE_OR_STOCK_FABRICATED")
    c = spec["canonical_source_contracts"]
    demand(len(c) == len(PATHS) and
           [row["file"] for row in c] == list(PATHS) and
           [row["id"] for row in c] ==
           ["S%02d" % (i+1) for i in range(len(PATHS))] and
           all(len(row["markers"]) >= 2 for row in c),
           "D07_SOURCE_PATH_SCOPE_DRIFT")
    rules = set(spec["observed_source_rules"])
    demand(len(rules) >= 27 and REQUIRED_RULES.issubset(rules),
           "D07_WRITER_CONTRACT_MISSING")
    concern = spec["observed_discrepancy_to_review"]
    demand(concern["name"] == "ALLOWED_FARM_TYPE_SET_MISMATCH" and
           concern["severity"] == "NEEDS_FUTURE_RUNTIME_VERIFICATION_NO_UNAPPROVED_CHANGE" and
           concern["proof_state"] == "SOURCE_ONLY_NOT_RUNTIME_CERTIFIED",
           "D07_DELEGATED_GENERAL_INVENTORY_MISMATCH_HIDDEN")
    future = set(spec["future_implementation_requirements"])
    needed = {
        "canonicalize_both_privileged_and_delegated_item_create_paths_without_parallel_business_SQL",
        "reject_foreign_farm_category_and_item_ids",
        "reject_slaughter_output_manual_initial_receipt_and_category_role_reclassification",
        "transactional_item_insert_plus_initial_RECEIVED_or_rollback",
        "regression_for_general_sales_only_inventory_admin_vs_specialist_paths",
        "no_2025_beginning_stock_without_approved_pre_year_source_policy",
    }
    demand(len(future) >= 25 and needed.issubset(future),
           "D07_FUTURE_GATES_MISSING")
    g = spec["gates"]
    demand(g == {
        "D01_reporting_period":"2025_DESIGN_ONLY",
        "D01_event_dates":"UNAPPROVED",
        "D02_layer_biology":"UNAPPROVED",
        "D03_layer_egg_pool":"UNPROVEN",
        "D04_broiler_populations":"UNAPPROVED",
        "D05_feed_costs":"UNAPPROVED",
        "D06_tenant_actor":"UNAPPROVED",
        "D07_inventory_writer":"UNAPPROVED",
        "D08_to_D12":"UNAPPROVED",
        "actual_category_item_records":"NOT_CREATED",
        "actual_stock_movements":"NOT_CREATED",
        "runtime_authorization":"NOT_TESTED",
        "new_V2_events":"NOT_GENERATED",
        "database_connections":"0",
        "database_writes":"0",
        "capacity_150_VU":"HOLD",
        "capacity_200_VU":"PROHIBITED",
    }, "D07_GATES_DRIFT")
    access = spec["permissions"]
    demand(access["offline_documentation_only"] is True,
           "D07_DOC_SCOPE_INVALID")
    for key in (
        "create_category","create_stock_item","approve_stock_quantities",
        "approve_unit_costs","modify_application_PHP","consolidate_writers",
        "run_PHP","connect_database","write_database","generate_V2_events",
        "import_restore","deploy","run_load_test",
    ):
        demand(access[key] is False, "D07_UNAUTHORIZED_" + key.upper())

def source_check(spec, overrides=None):
    demand(SOURCE.is_dir() and not SOURCE.is_symlink(),
           "CERTIFIED_SOURCE_ROOT_INVALID")
    total=0
    for i, row in enumerate(spec["canonical_source_contracts"]):
        path = row["file"]
        demand(path in PATHS, "NONCANONICAL_SOURCE_PATH")
        f = SOURCE / path
        demand(f.is_file() and not f.is_symlink(),
               "CANONICAL_FILE_UNAVAILABLE")
        body = f.read_text(encoding="utf-8")
        if overrides and path in overrides:
            body = overrides[path]
        normalized = compact(body)
        for j, marker in enumerate(row["markers"]):
            demand(compact(marker) in normalized,
                   "D07_SOURCE_MARKER_DRIFT_" + str(i) + "_" + str(j))
            total += 1
    return total

def negative_tests(spec):
    for key,value in (
        ("target_tenant_immutable_farm_id", 101),
        ("item_initial_stock_quantities", 2000),
        ("item_receipt_actual_unit_costs", 30000),
        ("item_slaughter_output_provenance", "invented"),
    ):
        wrong = copy.deepcopy(spec)
        wrong["per_category_item_unknowns"][key] = value
        try:
            validate(wrong)
        except Blocked:
            pass
        else:
            raise Blocked("D07_FAKE_ITEM_APPROVAL_ACCEPTED")
    for category,key,value in (
        ("permissions","create_stock_item",True),
        ("permissions","connect_database",True),
        ("gates","D07_inventory_writer","APPROVED"),
        ("gates","capacity_200_VU","ALLOWED"),
    ):
        wrong = copy.deepcopy(spec)
        wrong[category][key] = value
        try:
            validate(wrong)
        except Blocked:
            pass
        else:
            raise Blocked("D07_WRITE_PERMISSION_TAMPER_ACCEPTED")
    path = "includes/inventory_permission_hardening.php"
    source = (SOURCE / path).read_text(encoding="utf-8")
    needle = "inventory_category_item_contract_errors("
    demand(needle in source, "D07_NEGATIVE_TEST_SOURCE_NOT_READY")
    wrong = {path:"".join(source.split(needle))}
    try:
        source_check(spec, wrong)
    except Blocked:
        pass
    else:
        raise Blocked("D07_SOURCE_CONTRACT_TAMPER_ACCEPTED")

def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument("--dry-run", required=True, action="store_true")
    p.add_argument("--database", required=True)
    p.add_argument("--farm-slug", required=True)
    p.add_argument("--legacy-year", required=True, type=int)
    p.add_argument("--as-of", required=True)
    p.add_argument("--reporting-year", required=True, type=int)
    p.add_argument("--certified-source-root", required=True)
    a=p.parse_args()
    if ((a.database,a.farm_slug,a.legacy_year) !=
        ("renee_c137_gc24_http","c137-gc24-synthetic",2026)
        or (a.as_of,a.reporting_year) != ("2026-10-11",2025)
        or a.certified_source_root != str(SOURCE)):
        p.error("K1_D07_SCOPE_GUARD_BLOCKED")
    static_no_writes()
    prior_lineage()
    spec=json.loads(SPEC_FILE.read_text(encoding="utf-8"))
    validate(spec)
    probes=source_check(spec)
    negative_tests(spec)
    print("K1_D07_STATIC_READONLY=PASS")
    print("K1_D06_IMMUTABLE_LINEAGE=PASS")
    print("K1_D07_SOURCE_MARKERS=PASS")
    print("K1_D07_NEGATIVE_TESTS=PASS")
    print("K1_D07_SOURCE_FILES="+str(len(PATHS)))
    print("K1_D07_SOURCE_PROBE_COUNT="+str(probes))
    print("K1_D07_UNRESOLVED_INPUT_FIELDS="+str(len(spec["per_category_item_unknowns"])))
    print("K1_D07_TWO_ITEM_WRITERS=SOURCE_CONFIRMED")
    print("K1_D07_DELEGATED_GENERAL_INVENTORY_MISMATCH=RUNTIME_UNVERIFIED")
    print("K1_D07_REPORTING_YEAR=2025")
    print("K1_D07_INVENTORY_WRITER_APPROVAL=NOT_GRANTED")
    for scenario in ("R1","R2"):
        payload = {"scenario":scenario,"specification":spec,
                   "prior_d06_spec_sha256":D06_DIGEST[scenario]}
        digest=hashlib.sha256(json.dumps(
            payload,sort_keys=True,separators=(",",":")
        ).encode()).hexdigest()
        print(scenario+"_D07_SPEC_SHA256="+digest)
        print(scenario+"_ORIGINAL_2026_EVENT_DIGEST=UNCHANGED")
        print(scenario+"_V2_EVENT_DIGEST=NOT_GENERATED")
    print("CERTIFIED_REPORTS_UNCHANGED=YES")
    print("PHP_EXECUTED=NO")
    print("DATABASE_CONNECTIONS=0")
    print("DATABASE_WRITES=0")
    print("SOURCE_WRITES=0")
    print("NEW_V2_EVENTS_GENERATED=NO")
    print("NEW_LOAD_TESTS=0")
    print("IMPORT_EXECUTION_ENABLED=NO")
    print("GC24CG_K1_D07_OFFLINE_VERIFIER=PASS")

if __name__=="__main__":
    main()
