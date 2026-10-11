#!/usr/bin/env python3
"""K1/D06 offline tenant/actor source review, not runtime authorization testing."""
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
D05_FILE = DOCS / "C137_GC24_K1_D05_FEED_STOCK_COST_REQUIREMENTS.json"
SPEC_FILE = DOCS / "C137_GC24_K1_D06_TENANT_ACTOR_REQUIREMENTS.json"
PARENT = "e3bac113be5c0fd14a9fb318e16a921023c62204"
CERT = "322438870263825f9e739a12c4bd478983e67141"
D05_BLOB = "9efeada390c09c122413d9f02ac83ee0a128b72b"
D05_DIGEST = {
    "R1": "ccad5a76825380d083991c8c305cf17dcdd3f6d11e35e8a76f58f816684741bf",
    "R2": "58d4c14965a64b1fca1a8b0494ef7ae0763e9ec6a442e628d2044c239ac51930",
}
D04_DIGEST = {
    "R1": "6a7427e6ca34ecec707318ccb644e8f92acd648130502fe276e50fc1f07ccf69",
    "R2": "173e9136ea3ce342ac220fcf4786181dca77d1472b528b16c91595d8d9816761",
}
LEGACY = {
    "R1": "e31421ee40ce25b16feb698313fcf337e74126a8e63d8c5dadeedf74e30165f2",
    "R2": "985341ed4315788ee8f3b9d62b327da66d38fc63ea16d406c5c52bc9949d3dfe",
}
PATHS = (
    "config.php", "includes/farm_profile.php",
    "includes/tenant_provisioning.php", "includes/functions.php",
    "includes/permission_catalog.php", "includes/farm_entitlement_runtime.php",
    "includes/permission_runtime.php", "includes/user_management_tenant_guard.php",
    "management/users.php", "includes/platform_owner_tenant_view.php",
    "includes/csrf.php", "includes/legacy_api_view_permissions.php",
    "lib/production_cycle_service.php", "lib/stock_service.php",
    "lib/sale_population_effects.php", "poultry/layers_daily_record.php",
    "poultry/broiler_daily_record.php", "management/sales_records.php",
    "includes/subscription_lifecycle.php", "init.php",
)
class Blocked(ValueError):
    pass

def guard(ok, name):
    if not ok:
        raise Blocked(name)

def compact(value):
    return re.sub(r"\s+", "", value).lower()

def git_blob(value):
    return hashlib.sha1(
        b"blob " + str(len(value)).encode() + bytes([0]) + value
    ).hexdigest()

def static_no_side_effects():
    tree = ast.parse(Path(__file__).read_text(encoding="utf-8"))
    modules = {"argparse","ast","copy","datetime","hashlib","json","re","sys","pathlib"}
    forbidden = {"connect","execute","executemany","commit","rollback",
                 "write","write_text","write_bytes","mkdir","unlink","rename",
                 "remove","rmdir","Popen","popen","system","urlopen","request",
                 "eval","exec"}
    for node in ast.walk(tree):
        if isinstance(node, ast.Import):
            guard(all(n.name in modules for n in node.names), "UNSAFE_IMPORT")
        elif isinstance(node, ast.ImportFrom):
            guard(node.module in modules, "UNSAFE_IMPORT")
        elif isinstance(node, ast.Call):
            f = node.func
            name = f.id if isinstance(f, ast.Name) else (
                f.attr if isinstance(f, ast.Attribute) else "")
            guard(name not in forbidden, "UNSAFE_IO")

def earlier_d05():
    raw = D05_FILE.read_bytes()
    guard(git_blob(raw) == D05_BLOB, "D05_FROZEN_BLOB_DRIFT")
    doc = json.loads(raw)
    guard(doc["readiness"] == "FEED_RECEIPT_USED_COST_EVIDENCE_UNAPPROVED"
          and all(x is None for x in doc["per_item_and_event_unknowns"].values()),
          "D05_FACTS_FALSELY_APPROVED")
    for scenario in ("R1", "R2"):
        payload = {"scenario": scenario, "specification": doc,
                   "prior_d04_spec_sha256": D04_DIGEST[scenario]}
        sha = hashlib.sha256(json.dumps(
            payload, sort_keys=True, separators=(",", ":")
        ).encode()).hexdigest()
        guard(sha == D05_DIGEST[scenario], "D05_LINEAGE_DRIFT")

def validate(doc):
    guard(doc["version"] == "C137-GC24-K1-D06-TENANT-ACTOR-BOUNDARY-0"
          and doc["checkpoint"] == "GC24CG_K1_D06"
          and doc["scope"] == "OFFLINE_DOCUMENTATION_AND_STATIC_SOURCE_PROBES_ONLY"
          and doc["readiness"] == "D06_TENANT_ACTOR_AUTHORITY_UNAPPROVED",
          "D06_SCOPE_OR_APPROVAL_DRIFT")
    w = doc["selected_reporting_window"]
    start = datetime.date.fromisoformat(w["start"])
    end = datetime.date.fromisoformat(w["end"])
    guard((w["year"],str(start),str(end),(end-start).days+1,w["date_policy"]) ==
          (2025,"2025-01-01","2025-12-31",365,"PENDING"),
          "SELECTED_YEAR_DRIFT")
    guard(doc["as_of"] == "2026-10-11"
          and doc["preparation_parent_head"] == PARENT
          and doc["certified_reports_source_head"] == CERT
          and doc["prior_d05_spec_git_blob_sha1"] == D05_BLOB
          and doc["prior_d05_spec_sha256"] == D05_DIGEST
          and doc["original_2026_event_sha256"] == LEGACY,
          "SOURCE_LINEAGE_DRIFT")
    guard(doc["isolated_database_name_reference_only"] == "renee_c137_gc24_http"
          and doc["synthetic_slug_reference_only"] == "c137-gc24-synthetic",
          "IDENTITY_SCOPE_DRIFT")
    unknown = doc["ids_and_actor_state_unknown"]
    guard(len(unknown) >= 35 and all(x is None for x in unknown.values()),
          "TENANT_USER_CREDENTIAL_OR_ID_INVENTED")
    contracts = doc["source_contracts"]
    guard(len(contracts) == len(PATHS)
          and [c["file"] for c in contracts] == list(PATHS)
          and [c["id"] for c in contracts] ==
          ["S%02d" % i for i in range(1,len(PATHS)+1)]
          and all(len(c["markers"]) >= 3 for c in contracts),
          "CANONICAL_TENANT_SCOPE_DRIFT")
    rules = set(doc["observed_rules"])
    required = {
        "farms_id_is_immutable_tenant_key_slug_is_not_tenant_database_primary_key",
        "current_user_roles_query_joins_user_to_session_farm_id",
        "subscription_lifecycle_entitlement_runs_before_legacy_role_permission_bypass",
        "platform_owner_read_only_selected_tenant_view_does_not_impersonate_session_identity",
        "tenant_permission_row_overrides_global_default_permission",
        "sale_population_effect_service_validates_sale_farm_and_cycle_before_projection",
        "php_bootstrap_has_DB_connection_so_no_PHP_execution_in_this_offline_step"
    }
    guard(len(rules) >= 21 and required.issubset(rules),
          "TENANT_AUTHORITY_POLICY_MISSING")
    tests = set(doc["future_verification_requirements"])
    guard(len(tests) >= 25
          and "deny_cross_farm_cycle_ID_even_if_valid_in_other_tenant" in tests
          and "deny_cross_farm_stock_item_and_stock_movement_ID" in tests
          and "deny_cross_farm_sale_and_population_effect_ID" in tests
          and "deny_cross_farm_daily_record_ID" in tests
          and "no_platform_owner_impersonation_or_session_farm_switch_as_a_shortcut" in tests
          and "no_user_password_token_personal_data_or_IDs_in_this_design_document" in tests,
          "CROSS_FARM_NEGATIVE_PLAN_MISSING")
    gates = doc["gates"]
    guard(gates == {
        "D01_year_selection":"2025_DESIGN_ONLY",
        "D01_event_date_policy":"UNAPPROVED",
        "D02_layer_biology":"UNAPPROVED",
        "D03_egg_pool":"UNPROVEN",
        "D04_broiler_biology":"UNAPPROVED",
        "D05_feed_cost":"UNAPPROVED",
        "D06_tenant_actor":"UNAPPROVED",
        "D07_to_D12":"UNAPPROVED",
        "actual_tenant_record":"NOT_CREATED",
        "actor_credentials":"NOT_CREATED",
        "runtime_permission_proof":"NOT_EXECUTED",
        "fresh_fixture_events":"NOT_GENERATED",
        "database":"NO_CONNECTIONS_OR_WRITES",
        "capacity_150":"HOLD",
        "capacity_200":"PROHIBITED",
    }, "EXECUTION_GATE_DRIFT")
    access = doc["permissions"]
    guard(access["document_offline_review_only"] is True,
          "DESIGN_SCOPE_REJECTED")
    for field in ("choose_farm_ID","create_tenant","create_user",
                  "generate_credentials","modify_role_policy","modify_entitlement",
                  "run_php","connect_to_database","write_database",
                  "modify_certified_source","generate_fixture_events",
                  "import_or_deploy","execute_load_test"):
        guard(access[field] is False, "UNAUTHORIZED_" + field.upper())

def inspect(doc, replacement=None):
    guard(SOURCE.is_dir() and not SOURCE.is_symlink(),
          "CERTIFIED_SOURCE_ROOT_UNAVAILABLE")
    probes=0
    for index, rec in enumerate(doc["source_contracts"]):
        path = rec["file"]
        guard(path in PATHS, "UNAPPROVED_SOURCE_PATH")
        f = SOURCE / path
        guard(f.is_file() and not f.is_symlink(),
              "CERTIFIED_SOURCE_FILE_UNAVAILABLE")
        txt = f.read_text(encoding="utf-8")
        if replacement and path in replacement:
            txt = replacement[path]
        canon = compact(txt)
        for i, marker in enumerate(rec["markers"]):
            guard(compact(marker) in canon,
                  "CANONICAL_SOURCE_DRIFT_" + str(index) + "_" + str(i))
            probes += 1
    return probes

def negative(doc):
    for key,val in (("V2_target_farm_id",123),
                    ("fixture_operator_ID",900),
                    ("fixture_operator_role_list",["farm_admin"]),
                    ("tenant_creation_credentials","synthetic-password")):
        tampered = copy.deepcopy(doc)
        tampered["ids_and_actor_state_unknown"][key] = val
        try:
            validate(tampered)
        except Blocked:
            pass
        else:
            raise Blocked("ACTOR_IDENTITY_FABRICATION_ACCEPTED")
    for container,field,value in (("permissions","create_user",True),
                                  ("permissions","connect_to_database",True),
                                  ("gates","runtime_permission_proof","PASS"),
                                  ("gates","capacity_200","ENABLED")):
        tampered = copy.deepcopy(doc)
        tampered[container][field] = value
        try:
            validate(tampered)
        except Blocked:
            pass
        else:
            raise Blocked("UNAUTHORIZED_TENANT_CHANGE_ACCEPTED")
    file="includes/user_management_tenant_guard.php"
    src=(SOURCE/file).read_text(encoding="utf-8")
    needle="SELECT id FROM users WHERE id = ? AND farm_id = ?"
    guard(needle in src, "TAMPER_SETUP_FAILED")
    bad="".join(src.split(needle))
    try:
        inspect(doc,{file:bad})
    except Blocked:
        pass
    else:
        raise Blocked("CROSS_FARM_GUARD_REMOVAL_ACCEPTED")

def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument("--dry-run", required=True, action="store_true")
    p.add_argument("--database", required=True)
    p.add_argument("--farm-slug", required=True)
    p.add_argument("--legacy-year", type=int, required=True)
    p.add_argument("--as-of", required=True)
    p.add_argument("--reporting-year", type=int, required=True)
    p.add_argument("--certified-source-root", required=True)
    a=p.parse_args()
    if ((a.database,a.farm_slug,a.legacy_year) !=
        ("renee_c137_gc24_http","c137-gc24-synthetic",2026)
        or (a.as_of,a.reporting_year) != ("2026-10-11",2025)
        or a.certified_source_root != str(SOURCE)):
        p.error("K1_D06_SCOPE_GUARD_FAILED")
    static_no_side_effects()
    earlier_d05()
    doc=json.loads(SPEC_FILE.read_text(encoding="utf-8"))
    validate(doc)
    checked=inspect(doc)
    negative(doc)
    print("K1_D06_STATIC_READONLY=PASS")
    print("K1_D05_IMMUTABLE_LINEAGE=PASS")
    print("K1_D06_SOURCE_MARKERS=PASS")
    print("K1_D06_TAMPER_REJECTIONS=PASS")
    print("K1_D06_SOURCE_FILES="+str(len(PATHS)))
    print("K1_D06_SOURCE_PROBES="+str(checked))
    print("K1_D06_UNKNOWN_ACTOR_FIELDS="+str(len(doc["ids_and_actor_state_unknown"])))
    print("K1_D06_YEAR=2025")
    print("K1_D06_TENANT_ACTOR_APPROVAL=NOT_GRANTED")
    print("K1_D06_RUNTIME_TENANT_ISOLATION=UNPROVEN")
    for scenario in ("R1","R2"):
        payload={"scenario":scenario,"specification":doc,
                 "prior_d05_spec_sha256":D05_DIGEST[scenario]}
        digest=hashlib.sha256(json.dumps(
            payload, sort_keys=True, separators=(",",":")
        ).encode()).hexdigest()
        print(scenario+"_D06_SPEC_SHA256="+digest)
        print(scenario+"_ORIGINAL_2026_EVENT_DIGEST=UNCHANGED")
        print(scenario+"_V2_EVENT_DIGEST=NOT_GENERATED")
    print("CERTIFIED_REPORTS_UNCHANGED=YES")
    print("PHP_EXECUTED=NO")
    print("TENANT_ACCOUNTS_CREATED=0")
    print("USER_ACCOUNTS_CREATED=0")
    print("DATABASE_CONNECTIONS=0")
    print("DATABASE_WRITES=0")
    print("SOURCE_WRITES=0")
    print("V2_EVENTS_GENERATED=NO")
    print("NEW_LOAD_TESTS=0")
    print("IMPORT_EXECUTION_ENABLED=NO")
    print("GC24CG_K1_D06_OFFLINE_VERIFIER=PASS")

if __name__=="__main__":
    main()
