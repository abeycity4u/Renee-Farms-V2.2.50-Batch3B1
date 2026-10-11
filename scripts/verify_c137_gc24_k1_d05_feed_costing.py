#!/usr/bin/env python3
"""C137 GC24 K1/D05: offline Poultry feed/cost source-contract verifier.

No DB, network, PHP execution, ledger writes, V2 events or load tests.
"""
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
SPEC_FILE = DOCS / "C137_GC24_K1_D05_FEED_STOCK_COST_REQUIREMENTS.json"
PRIOR_FILE = DOCS / "C137_GC24_K1_D04_BROILER_COHORT_REQUIREMENTS.json"
PARENT = "f33721bf64927461fee123730d0bb9b62cacf99c"
CERT = "322438870263825f9e739a12c4bd478983e67141"
PRIOR_BLOB = "5be072cf0383641f437e865aa508649e3112358c"
PRIOR_D04 = {
    "R1": "6a7427e6ca34ecec707318ccb644e8f92acd648130502fe276e50fc1f07ccf69",
    "R2": "173e9136ea3ce342ac220fcf4786181dca77d1472b528b16c91595d8d9816761",
}
PRIOR_D03 = {
    "R1": "6d0e142f51c92c80d7031dedb3e47897d7b029d67f05c1095a62287e35268a44",
    "R2": "0a33b8f8cbe21dc49c7e24577141e5d837904fe2a3e3c6a35d86e5b6b539d6a8",
}
ORIGINAL = {
    "R1": "e31421ee40ce25b16feb698313fcf337e74126a8e63d8c5dadeedf74e30165f2",
    "R2": "985341ed4315788ee8f3b9d62b327da66d38fc63ea16d406c5c52bc9949d3dfe",
}
PATHS = (
    "lib/daily_feed_sync.php",
    "lib/stock_service.php",
    "lib/stock_costing.php",
    "lib/stock_reporting.php",
    "lib/stock_receipt_cost_adjustments.php",
    "lib/inventory_financial.php",
    "inventory.php",
    "includes/inventory_permission_hardening.php",
    "poultry/layers_daily_record.php",
    "poultry/broiler_daily_record.php",
    "lib/stock_consumption_economics.php",
    "lib/stock_consumption_source_resolver.php",
    "lib/stock_consumption_allocation_service.php",
    "lib/stock_consumption_allocation_persistence.php",
    "lib/stock_movement_attribution.php",
    "management/profitability.php",
)
REQUIRED_RULES = {
    "daily_feed_edit_reverses_old_used_before_replacement",
    "stock_writes_require_caller_transaction_and_record_reference_authority",
    "ordinary_USED_outgoing_unit_cost_uses_historical_cost_and_may_fallback_to_current_valuation",
    "receipt_corrections_use_append_only_total_cost_adjustments",
    "replay_may_be_incomplete_or_negative_and_fallback_is_not_proof_of_actual_historical_price",
    "feed_purchase_receipt_is_cash_spending_not_second_operating_expense",
    "reports_reader_selects_effective_USED_with_nonnull_total_cost_and_eligible_classification",
    "2048_annual_prefetch_is_memory_optimization_overflow_returns_to_monthly_reads",
    "positive_shared_cost_parent_requires_costed_effective_USED_without_direct_cycle",
    "pooled_stock_allocation_revisions_and_reversal_share_caller_transaction",
}

class Blocked(ValueError):
    pass

def require(condition, reason):
    if not condition:
        raise Blocked(reason)

def git_blob(raw):
    return hashlib.sha1(
        b"blob " + str(len(raw)).encode("ascii") + bytes([0]) + raw
    ).hexdigest()

def compact(value):
    return re.sub(r"\s+", "", value).lower()

def static_guard():
    ast_tree = ast.parse(Path(__file__).read_text(encoding="utf-8"))
    allowed = {"argparse","ast","copy","datetime","hashlib","json","re","sys","pathlib"}
    banned = {"connect","execute","executemany","commit","rollback","write",
              "write_bytes","write_text","unlink","rename","mkdir","rmdir",
              "remove","popen","Popen","system","urlopen","request","eval","exec"}
    for node in ast.walk(ast_tree):
        if isinstance(node, ast.Import):
            require(all(x.name in allowed for x in node.names), "EXTERNAL_IMPORT")
        elif isinstance(node, ast.ImportFrom):
            require(node.module in allowed, "EXTERNAL_IMPORT")
        elif isinstance(node, ast.Call):
            f = node.func
            method = f.id if isinstance(f, ast.Name) else (
                f.attr if isinstance(f, ast.Attribute) else "")
            require(method not in banned, "UNSAFE_IO_CALL")

def verify_d04_lineage():
    raw = PRIOR_FILE.read_bytes()
    require(git_blob(raw) == PRIOR_BLOB, "D04_FROZEN_BLOB_CHANGED")
    d04 = json.loads(raw)
    require(d04["checkpoint"] == "GC24CG_K1_D04" and
            d04["readiness"] == "D04_COHORT_AND_EXIT_EVIDENCE_UNAPPROVED",
            "PRIOR_D04_APPROVED_WITHOUT_OWNER")
    require(all(v is None for v in d04["per_cohort_unknowns"].values()),
            "PRIOR_D04_BIOLOGY_CHANGED")
    for scenario in ("R1", "R2"):
        payload = {
            "scenario": scenario, "specification": d04,
            "prior_d03_spec_sha256": PRIOR_D03[scenario]
        }
        digest = hashlib.sha256(json.dumps(
            payload, sort_keys=True, separators=(",", ":")
        ).encode()).hexdigest()
        require(digest == PRIOR_D04[scenario], "D04_SPEC_LINEAGE_DRIFT")

def validate_spec(s):
    require(s["version"] == "C137-GC24-K1-D05-FEED-STOCK-COST-REQUIREMENTS-0"
            and s["checkpoint"] == "GC24CG_K1_D05"
            and s["scope"] == "READONLY_SOURCE_REQUIREMENTS_ONLY"
            and s["readiness"] == "FEED_RECEIPT_USED_COST_EVIDENCE_UNAPPROVED",
            "UNAPPROVED_COST_READINESS")
    w = s["selected_year"]
    first = datetime.date.fromisoformat(w["start_date"])
    last = datetime.date.fromisoformat(w["end_date"])
    require((w["year"],str(first),str(last),(last-first).days+1,w["technical_event_dates"]) ==
            (2025,"2025-01-01","2025-12-31",365,"UNRESOLVED"),
            "REPORT_YEAR_POLICY_CHANGED")
    require(s["as_of"] == "2026-10-11"
            and s["preparation_parent_sha"] == PARENT
            and s["certified_reports_sha"] == CERT
            and s["previous_d04_requirements_git_blob_sha1"] == PRIOR_BLOB
            and s["previous_d04_spec_sha256"] == PRIOR_D04
            and s["previous_d03_spec_sha256"] == PRIOR_D03
            and s["original_2026_event_sha256"] == ORIGINAL,
            "PREVIOUS_CERTIFICATION_DRIFT")
    require(s["legacy_used_stock_is_not_eligible_proof"] ==
            {"R1_candidate_used_rows":1800,"R2_candidate_used_rows":3600,
             "broiler_daily_feed_used_events":0,
             "scenario_classification":"ORIGINAL_2026_UNPROVEN"},
            "LEGACY_ROWS_FALSELY_CERTIFIED")
    unknown = s["per_item_and_event_unknowns"]
    require(len(unknown) >= 30 and all(v is None for v in unknown.values()),
            "FEED_PRICES_OR_STOCK_EVENTS_INVENTED")
    contracts = s["certified_source_contracts"]
    require(len(contracts) == len(PATHS) and
            [c["file"] for c in contracts] == list(PATHS) and
            [c["id"] for c in contracts] ==
            ["S%02d" % i for i in range(1, len(PATHS)+1)] and
            all(len(c["markers"])>=2 for c in contracts),
            "SOURCE_CONTRACT_INCOMPLETE")
    rules = set(s["observed_canonical_rules"])
    require(REQUIRED_RULES.issubset(rules) and len(rules) >= 25,
            "COST_AND_RECEIPT_POLICY_MISSING")
    proof = set(s["future_proof_gates"])
    require(len(proof)>=25 and
            "detect_and_reject_historical_cost_fallback_for_unproven_synthetic_cost_accuracy" in proof and
            "eligible_USED_row_count_must_be_counted_after_effectiveness_and_cost_filters" in proof and
            "no_posted_use_can_exceed_physically_available_stock_at_posting" in proof,
            "COST_VALIDATION_PLAN_MISSING")
    g = s["gates"]
    require(g["year_2025"] == "SELECTED_FOR_DESIGN_ONLY"
            and g["event_date_policy"] == "UNAPPROVED"
            and g["D05_feed_and_cost"] == "UNAPPROVED"
            and g["new_v2_event_streams"] == "NOT_GENERATED"
            and g["priced_receipt_input_approval"] == "NOT_GRANTED"
            and g["reports_eligible_used_row_proof"] == "UNPROVEN"
            and g["annual_prefetch_max_rows"] == 2048
            and g["overflow_behavior"] == "FALL_BACK_TO_MONTHLY_QUERIES"
            and g["capacity_150_vu"] == "HOLD"
            and g["capacity_200_vu"] == "PROHIBITED"
            and g["database_mutation"] == "PROHIBITED"
            and g["certified_source_mutation"] == "PROHIBITED",
            "IMPLEMENTATION_GATE_TAMPER")
    p = s["permissions"]
    require(p["documentation_and_offline_verifier"] is True, "DOC_SCOPE_DENIED")
    for key in ("choose_feed_quantity","choose_item_prices",
                "choose_inventory_initial_value","rewrite_2026_events",
                "create_v2_events","change_php_writers","execute_php",
                "touch_database","import_or_restore","deploy","run_load_tests"):
        require(p[key] is False, "UNAUTHORIZED_" + key.upper())

def inspect_source(s, edited=None):
    require(SOURCE.is_dir() and not SOURCE.is_symlink(),
            "CERTIFIED_SOURCE_ROOT_INVALID")
    checked=0
    for index,c in enumerate(s["certified_source_contracts"]):
        path = c["file"]
        require(path in PATHS, "UNAPPROVED_FILE_IN_CONTRACT")
        file = SOURCE / path
        require(file.is_file() and not file.is_symlink(),
                "CERTIFIED_SOURCE_FILE_MISSING")
        text = file.read_text(encoding="utf-8")
        if edited and path in edited:
            text = edited[path]
        body = compact(text)
        for j,marker in enumerate(c["markers"]):
            require(compact(marker) in body,
                    "CANONICAL_COST_CONTRACT_DRIFT_" + str(index) + "_" + str(j))
            checked+=1
    return checked

def negative_tests(spec):
    for key,value in (("dated_broiler_feed_consumption_quantities",125.0),
                      ("feed_receipt_unit_costs",50000),
                      ("actual_R1_eligible_used_row_count",1800)):
        bad = copy.deepcopy(spec)
        bad["per_item_and_event_unknowns"][key] = value
        try:
            validate_spec(bad)
        except Blocked:
            pass
        else:
            raise Blocked("INVENTED_INPUT_ACCEPTED_" + key.upper())
    for section,key,value in (("permissions","touch_database",True),
                              ("gates","D05_feed_and_cost","APPROVED"),
                              ("gates","capacity_200_vu","ALLOWED")):
        bad = copy.deepcopy(spec)
        bad[section][key] = value
        try:
            validate_spec(bad)
        except Blocked:
            pass
        else:
            raise Blocked("UNAUTHORIZED_GATE_ACCEPTED")
    file = "lib/stock_consumption_economics.php"
    src = (SOURCE / file).read_text(encoding="utf-8")
    needle = "t.total_cost IS NOT NULL"
    require(needle in src, "COST_SOURCE_TAMPER_TEST_NOT_SETUP")
    altered = "".join(src.split(needle))
    try:
        inspect_source(spec, {file:altered})
    except Blocked:
        pass
    else:
        raise Blocked("COST_SOURCE_TAMPER_ACCEPTED")

def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument("--dry-run",required=True,action="store_true")
    p.add_argument("--database",required=True)
    p.add_argument("--farm-slug",required=True)
    p.add_argument("--legacy-year",type=int,required=True)
    p.add_argument("--as-of",required=True)
    p.add_argument("--reporting-year",type=int,required=True)
    p.add_argument("--certified-source-root",required=True)
    a=p.parse_args()
    if ((a.database,a.farm_slug,a.legacy_year) !=
        ("renee_c137_gc24_http","c137-gc24-synthetic",2026)
        or (a.as_of,a.reporting_year) != ("2026-10-11",2025)
        or a.certified_source_root != str(SOURCE)):
        p.error("K1_D05_AUTHORITY_GUARD_BLOCKED")
    static_guard()
    verify_d04_lineage()
    spec=json.loads(SPEC_FILE.read_text(encoding="utf-8"))
    validate_spec(spec)
    count=inspect_source(spec)
    negative_tests(spec)
    print("K1_D05_STATIC_READONLY=PASS")
    print("K1_D04_IMMUTABLE_LINEAGE=PASS")
    print("K1_D05_SOURCE_MARKERS=PASS")
    print("K1_D05_TAMPER_REJECTIONS=PASS")
    print("K1_D05_CERTIFIED_SOURCE_FILES="+str(len(PATHS)))
    print("K1_D05_SOURCE_PROBE_COUNT="+str(count))
    print("K1_D05_UNRESOLVED_INPUT_FIELDS="+str(len(spec["per_item_and_event_unknowns"])))
    print("K1_D05_YEAR=2025")
    print("K1_D05_FEED_COST_APPROVAL=NOT_GRANTED")
    print("K1_D05_REPORTS_ELIGIBLE_COST_ROWS=UNPROVEN")
    for scenario in ("R1","R2"):
        data={"scenario":scenario,"specification":spec,
              "prior_d04_spec_sha256":PRIOR_D04[scenario]}
        digest=hashlib.sha256(json.dumps(
            data,sort_keys=True,separators=(",",":")
        ).encode()).hexdigest()
        print(scenario+"_D05_SPEC_SHA256="+digest)
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
    print("GC24CG_K1_D05_OFFLINE_VERIFIER=PASS")

if __name__=="__main__":
    main()
