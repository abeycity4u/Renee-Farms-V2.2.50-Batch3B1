#!/usr/bin/env python3
"""C137 GC24 K1/D11 read-only Reports eligibility and prefetch source review.
No database, PHP, source mutation, fixture generation, load testing, or network.
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
ROOT = Path(__file__).resolve().parent.parent
SOURCE = Path("/opt/renee-agrisuite-c137-gc")
DOC = ROOT / "docs" / "C137_GC24_K1_D11_REPORTS_ELIGIBILITY_REQUIREMENTS.json"
PREV = ROOT / "docs" / "C137_GC24_K1_D10_ALLOCATION_REQUIREMENTS.json"
PREP_PARENT = "1beb16cceeefb97371c9a93714fed2007c3a6c7c"
CERT = "322438870263825f9e739a12c4bd478983e67141"
PREV_BLOB = "d9f06ccb357acca5790091665a60f33fc1d26b89"
D10 = {
    "R1":"9ac9355b16253ad2f110d499798cf9243c721e6dfeefd59e71cb210b54f14c12",
    "R2":"37781fbde25095e6c667557c3f835151ab6f3817573c9af2d73d33c8f019abea",
}
D09 = {
    "R1":"b07d009a87b0132fcd675e4aaf66e21a6594d086a871a6d84563c087c3128af6",
    "R2":"013098e94b600544fb25651cb7154fb7e25e86096f70ebdb964b0e0a0bced94d",
}
LEGACY = {
    "R1":"e31421ee40ce25b16feb698313fcf337e74126a8e63d8c5dadeedf74e30165f2",
    "R2":"985341ed4315788ee8f3b9d62b327da66d38fc63ea16d406c5c52bc9949d3dfe",
}
FILES = (
    "management/reports.php",
    "lib/farm_intelligence.php",
    "lib/stock_consumption_economics.php",
    "lib/stock_reporting.php",
    "lib/stock_costing.php",
    "lib/inventory_financial.php",
    "lib/stock_consumption_allocation_service.php",
    "lib/profitability_unallocated_shared.php",
    "includes/financial.php",
    "includes/readonly_session.php",
    "scripts/verify_c137_reports_prefetch_contract.php",
    "scripts/verify_c137_monthly_reporting_contract.php",
    "scripts/verify_c137_reports_readonly_session_lock.php",
    "lib/daily_feed_sync.php",
    "lib/stock_service.php",
    "lib/stock_consumption_source_resolver.php",
)
REQUIRED = {
    "annual_prefetch_default_maxRows_2048",
    "capped_query_reads_maxRows_plus_one",
    "overflow_above_2048_returns_empty_seed_list_not_partial_month_cache",
    "SQL_nonnull_cost_does_not_prove_positive_cost_or_historical_purchase",
    "canonical_manual_stock_allocation_requires_positive_cost_and_source_integrity",
    "annual_2048_is_per_farm_source_row_cache_cap_not_VU_capacity_goal",
    "C137_small_fixture_SQL_calls_82_source_queries_1_is_unit_fixture_not_GC24_result",
    "default_150_VU_HOLD_200_VU_PROHIBITED",
}
REQUIRED_FUTURE = {
    "independently_count_eligible_SQL_rows_for_year_2025_per_scenario",
    "require_positive_receipt_backed_cost_for_representative_used",
    "source_count_at_2048_exact_boundary_check",
    "source_count_at_2049_overflow_fallback_check",
    "record_real_GC24_SQL_source_query_counts_not_canned_82_1",
    "no_fabricated_used_rows_to_force_prefetch_threshold",
    "never_use_existing_PHP_verifier_DSN_read_C137_fixture_in_D11",
}
class Blocked(ValueError):
    pass

def check(ok, label):
    if not ok:
        raise Blocked(label)

def normalize(s):
    return re.sub(r"\s+", "", s).lower()

def git_blob(raw):
    return hashlib.sha1(
        b"blob " + str(len(raw)).encode() + bytes([0]) + raw
    ).hexdigest()

def static_safety():
    tree = ast.parse(Path(__file__).read_text(encoding="utf-8"))
    allowed = {"argparse","ast","copy","datetime","hashlib","json","re","sys","pathlib"}
    blocked = {"connect","execute","executemany","commit","rollback","write",
               "write_text","write_bytes","unlink","rename","mkdir","rmdir",
               "remove","popen","Popen","system","urlopen","request","eval","exec"}
    for node in ast.walk(tree):
        if isinstance(node, ast.Import):
            check(all(m.name in allowed for m in node.names), "D11_UNSAFE_IMPORT")
        elif isinstance(node, ast.ImportFrom):
            check(node.module in allowed, "D11_UNSAFE_IMPORT")
        elif isinstance(node, ast.Call):
            f = node.func
            name = f.id if isinstance(f, ast.Name) else (
                f.attr if isinstance(f, ast.Attribute) else "")
            check(name not in blocked, "D11_UNSAFE_CALL")

def prior_lineage():
    raw = PREV.read_bytes()
    check(git_blob(raw) == PREV_BLOB, "D10_GIT_BLOB_DRIFT")
    prior = json.loads(raw)
    check(prior["checkpoint"] == "GC24CG_K1_D10"
          and prior["approval"] == "NOT_GRANTED"
          and all(v is None for v in prior["unknown_inputs"].values()),
          "D10_FALSE_APPROVAL")
    for scenario in ("R1", "R2"):
        payload = {
            "scenario":scenario,"specification":prior,
            "prior_d09_spec_sha256":D09[scenario],
        }
        sha = hashlib.sha256(json.dumps(
            payload, sort_keys=True, separators=(",",":")
        ).encode()).hexdigest()
        check(sha == D10[scenario], "D10_SPEC_DIGEST_DRIFT")

def validate(doc):
    check(doc["version"] == "C137-GC24-K1-D11-REPORTS-ELIGIBILITY-PREFETCH-0"
          and doc["checkpoint"] == "GC24CG_K1_D11"
          and doc["scope"] == "OFFLINE_SOURCE_REQUIREMENTS_ONLY"
          and doc["review_state"] == "D11_SOURCE_REVIEW_PENDING_NODE_A",
          "D11_SCOPE_DRIFT")
    w = doc["selected_year"]
    a = datetime.date.fromisoformat(w["starts"])
    b = datetime.date.fromisoformat(w["ends"])
    check((w["year"],str(a),str(b),(b-a).days+1,w["event_policy"]) ==
          (2025,"2025-01-01","2025-12-31",365,"UNAPPROVED"),
          "D11_YEAR_DRIFT")
    check(doc["as_of"] == "2026-10-11"
          and doc["preparation_parent_head"] == PREP_PARENT
          and doc["certified_reports_head"] == CERT
          and doc["prior_D10_git_blob_sha1"] == PREV_BLOB
          and doc["prior_D10_spec_sha256"] == D10
          and doc["prior_D09_spec_sha256"] == D09
          and doc["original_2026_event_sha256"] == LEGACY
          and doc["isolated_DB_reference_only"] == "renee_c137_gc24_http"
          and doc["tenant_slug_reference_only"] == "c137-gc24-synthetic",
          "D11_LINEAGE_DRIFT")
    unk = doc["unknown_eligibility_and_capacity_inputs"]
    check(len(unk) >= 60 and all(v is None for v in unk.values()),
          "D11_REAL_DATA_FABRICATED")
    rows = doc["source_contracts"]
    check(len(rows) == len(FILES)
          and [r["file"] for r in rows] == list(FILES)
          and [r["id"] for r in rows] == [
              "S%02d" % (i+1) for i in range(len(FILES))
          ]
          and all(len(r["markers"]) >= 2 for r in rows),
          "D11_SOURCE_SCOPE_DRIFT")
    check(len(doc["source_grounded_rules"]) >= 35
          and REQUIRED.issubset(set(doc["source_grounded_rules"])),
          "D11_PREFETCH_RULE_MISSING")
    check(len(doc["future_proofs"]) >= 35
          and REQUIRED_FUTURE.issubset(set(doc["future_proofs"])),
          "D11_FUTURE_EVIDENCE_MISSING")
    check(doc["SQL_eligibility"] == {
        "transaction_type":"used",
        "effective":"is_reversed=0 AND reversal_of_id IS NULL",
        "within_requested_farm_and_dates":True,
        "total_cost":"IS NOT NULL",
        "eligible_cost_classes":"feed snapshot OR operating consumption snapshot",
        "require_positive_cost_in_SQL":False,
    }, "D11_SQL_ELIGIBILITY_CONFLATED")
    check(doc["representative_evidence"] == {
        "receipt_proven_real_positive_cost":"NOT_PROVEN",
        "authoritative_daily_feed_linkage":"NOT_PROVEN",
        "real_2025_count":"UNCOUNTED",
        "daily_population_biology":"UNAPPROVED",
        "per_month_reconciliation":"UNPROVEN",
    }, "D11_REPRESENTATIVE_FACTS_FALSELY_APPROVED")
    check(doc["annual_prefetch"] == {
        "default_maxRows":2048,
        "one_extra_probe":2049,
        "sql_limit_formula":"maxRows + 1",
        "overflow_condition":"count(parents) > maxRows",
        "overflow_behavior":"RETURN_EMPTY_SEEDS_MONTHLY_READ_FALLBACK",
        "exact_boundary_behavior":"2048_CAN_SEED",
        "cache_ownership":"WeakMap<PDO, farm_id, date-range>",
        "release_behavior":"REMOVE_ONLY_SEEDED_MONTHS_KEEP_ANNUAL_CACHE",
        "cache_scope":"REQUEST_AND_FARM",
        "not_technical_capacity_requirement":True,
    }, "D11_PREFETCH_BEHAVIOR_DRIFT")
    assert_small_fixture = {
        "verified_contract_source":"scripts/verify_c137_reports_prefetch_contract.php",
        "expected_SQL_calls":82,
        "expected_stock_source_queries":1,
        "source_fixture_size_stock_transactions":8,
        "applies_to_GC24_rep_capacity":False,
        "execution_this_step":"PROHIBITED",
    }
    check(doc["existing_small_C137_fixture_verifier"] == assert_small_fixture,
          "D11_EXISTING_SMALL_FIXTURE_MISREPRESENTED")
    check(doc["gates"] == {
        "D01_year":"2025_DESIGN_ONLY",
        "D01_event_date_policy":"UNAPPROVED",
        "D02_layer":"UNAPPROVED",
        "D03_egg_pool":"UNPROVEN",
        "D04_broiler":"UNAPPROVED",
        "D05_feed_cost":"UNAPPROVED",
        "D06_tenant_actor":"UNAPPROVED",
        "D07_inventory_writer":"UNAPPROVED",
        "D08_sales_writer":"UNAPPROVED",
        "D09_daily_writer":"UNAPPROVED",
        "D10_allocations":"UNAPPROVED",
        "D11_reports_eligibility":"UNPROVEN",
        "D11_capacity":"UNPROVEN",
        "D12_import":"UNAPPROVED",
        "GC24_2025_eligible_used_rows":"UNCOUNTED",
        "GC24_actual_report_SQL_calls":"UNMEASURED",
        "GC24_actual_peak_VU":"NOT_TESTED",
        "new_V2_events":"NOT_GENERATED",
        "database_connections":0,
        "database_writes":0,
        "capacity_150_VU":"HOLD",
        "capacity_200_VU":"PROHIBITED",
    }, "D11_GATES_TAMPER")
    perms = doc["permissions"]
    check(perms["offline_source_review_only"] is True
          and all(v is False for k,v in perms.items()
                  if k != "offline_source_review_only"),
          "D11_UNAUTHORIZED_OPERATION")

def sources(doc, override=None):
    check(SOURCE.is_dir() and not SOURCE.is_symlink(),
          "D11_CERTIFIED_SOURCE_ROOT_MISSING")
    total=0
    for i, rec in enumerate(doc["source_contracts"]):
        path = rec["file"]
        check(path in FILES, "D11_NONCANONICAL_SOURCE")
        f = SOURCE/path
        check(f.is_file() and not f.is_symlink(),
              "D11_SOURCE_FILE_UNAVAILABLE")
        txt = f.read_text(encoding="utf-8")
        if override and path in override:
            txt = override[path]
        for j, m in enumerate(rec["markers"]):
            check(normalize(m) in normalize(txt),
                  "D11_SOURCE_DRIFT_"+str(i)+"_"+str(j))
            total+=1
    return total

def negatives(doc):
    for key,val in (
        ("R1_2025_eligible_used_count",2048),
        ("R2_2025_feed_used_count",3600),
        ("actual_2025_total_cost_positive_count",1500),
        ("isolated_GC24_125_VU_latency",513),
    ):
        fake=copy.deepcopy(doc)
        fake["unknown_eligibility_and_capacity_inputs"][key]=val
        try: validate(fake)
        except Blocked: pass
        else: raise Blocked("D11_FAKE_METRIC_ACCEPTED")
    for section,key,val in (
        ("permissions","connect_database",True),
        ("permissions","run_existing_c137_fixture_script",True),
        ("gates","D11_capacity","APPROVED"),
        ("gates","capacity_200_VU","ALLOWED"),
    ):
        fake=copy.deepcopy(doc)
        fake[section][key]=val
        try: validate(fake)
        except Blocked: pass
        else: raise Blocked("D11_UNAUTHORIZED_TEST_ACCEPTED")
    fake=copy.deepcopy(doc)
    fake["SQL_eligibility"]["require_positive_cost_in_SQL"]=True
    try: validate(fake)
    except Blocked: pass
    else: raise Blocked("D11_SQL_POSITIVE_COST_ASSUMPTION_ACCEPTED")
    fake=copy.deepcopy(doc)
    fake["annual_prefetch"]["overflow_behavior"]="TRUNCATE_TO_2048"
    try: validate(fake)
    except Blocked: pass
    else: raise Blocked("D11_TRUNCATION_ACCEPTED")
    file="lib/stock_consumption_economics.php"
    raw=(SOURCE/file).read_text(encoding="utf-8")
    token="if (count($parents) > $maxRows)"
    check(token in raw, "D11_NEGATIVE_SOURCE_SETUP_FAILED")
    try: sources(doc,{file:"".join(raw.split(token))})
    except Blocked: pass
    else: raise Blocked("D11_OVERFLOW_GUARD_REMOVAL_ACCEPTED")

def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument("--dry-run",action="store_true",required=True)
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
        p.error("D11_SCOPE_GUARD_BLOCKED")
    static_safety()
    prior_lineage()
    doc=json.loads(DOC.read_text(encoding="utf-8"))
    validate(doc)
    n=sources(doc)
    negatives(doc)
    print("K1_D11_STATIC_READONLY=PASS")
    print("K1_D10_IMMUTABLE_LINEAGE=PASS")
    print("K1_D11_SOURCE_MARKERS=PASS")
    print("K1_D11_NEGATIVE_TESTS=PASS")
    print("K1_D11_SOURCE_FILES="+str(len(FILES)))
    print("K1_D11_SOURCE_PROBES="+str(n))
    print("K1_D11_UNRESOLVED_INPUTS="+str(len(doc["unknown_eligibility_and_capacity_inputs"])))
    print("K1_D11_ANNUAL_PREFETCH_THRESHOLD=2048")
    print("K1_D11_2049_OVERFLOW=MONTHLY_FALLBACK_SOURCE_REVIEWED")
    print("K1_D11_SQL_NON_NULL_VS_REPRESENTATIVE_COST=DISTINGUISHED")
    print("K1_D11_ACTUAL_ELIGIBLE_USED_ROWS=UNCOUNTED")
    print("K1_D11_CAPACITY_APPROVAL=NOT_GRANTED")
    for scenario in ("R1","R2"):
        payload={"scenario":scenario,"specification":doc,
                 "prior_d10_spec_sha256":D10[scenario]}
        digest=hashlib.sha256(json.dumps(
            payload,sort_keys=True,separators=(",",":")
        ).encode()).hexdigest()
        print(scenario+"_D11_SPEC_SHA256="+digest)
        print(scenario+"_ORIGINAL_2026_EVENT_DIGEST=UNCHANGED")
        print(scenario+"_V2_EVENT_DIGEST=NOT_GENERATED")
    print("SELECTED_REPORTING_YEAR=2025")
    print("CERTIFIED_REPORTS_UNCHANGED=YES")
    print("PHP_EXECUTED=NO")
    print("DATABASE_CONNECTIONS=0")
    print("DATABASE_WRITES=0")
    print("SOURCE_WRITES=0")
    print("V2_EVENTS_GENERATED=NO")
    print("NEW_LOAD_TESTS=0")
    print("IMPORT_EXECUTION_ENABLED=NO")
    print("GC24CG_K1_D11_OFFLINE_VERIFIER=PASS")

if __name__=="__main__":
    main()
