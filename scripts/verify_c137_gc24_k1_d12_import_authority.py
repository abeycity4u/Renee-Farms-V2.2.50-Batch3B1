#!/usr/bin/env python3
"""K1/D12 read-only final import authority requirements.

Reads pinned Git documents and certified PHP / MD source as inert text.
Does not connect to any DB or execute PHP, import, backups, load or fixtures.
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
CERT_SOURCE = Path("/opt/renee-agrisuite-c137-gc")
SPEC_PATH = ROOT / "docs/C137_GC24_K1_D12_IMPORT_AUTHORITY_REQUIREMENTS.json"
D11_PATH = ROOT / "docs/C137_GC24_K1_D11_REPORTS_ELIGIBILITY_REQUIREMENTS.json"
PARENT = "d5e78b1b6f140b477d276627b2f6dd2fc0f079f0"
CERT = "322438870263825f9e739a12c4bd478983e67141"
D11_BLOB = "6e6ce4ed0f4af9a76bb637c1f5c1c2f3f56bf342"
D11 = {
 "R1":"5de70a178ca4660eba53346f7dc43fb47302322511369261cdb1773cd7c17600",
 "R2":"d424fc5bfc773619aaeadb6a245b1c5cea85c6a36b86d3cecb11bb8c7a413023",
}
D10 = {
 "R1":"9ac9355b16253ad2f110d499798cf9243c721e6dfeefd59e71cb210b54f14c12",
 "R2":"37781fbde25095e6c667557c3f835151ab6f3817573c9af2d73d33c8f019abea",
}
OLD = {
 "R1":"e31421ee40ce25b16feb698313fcf337e74126a8e63d8c5dadeedf74e30165f2",
 "R2":"985341ed4315788ee8f3b9d62b327da66d38fc63ea16d406c5c52bc9949d3dfe",
}
FILES = (
 "docs/ga-hardening/MAX_CAPACITY_TEST_PLAN.md",
 "docs/ga-hardening/MAX_CAPACITY_ENVIRONMENT_SPEC.md",
 "docs/ga-hardening/MAX_CAPACITY_EVIDENCE_TEMPLATE.md",
 "scripts/ga_dr_post_restore_verify.php",
 "scripts/run_migrations.php",
 "scripts/verify_c137_reports_prefetch_contract.php",
 "management/reports.php",
 "lib/stock_consumption_economics.php",
 "lib/stock_service.php",
 "lib/daily_population_continuity.php",
 "lib/sale_revenue_allocation_persistence.php",
 "lib/daily_feed_sync.php",
 "includes/readonly_session.php",
)
DENIED = (
 "run_application_PHP","run_migrations","run_DR_verifier",
 "run_C137_DB_verifier","query_any_database","connect_Node_B",
 "connect_Node_C","create_synthetic_tenant","create_synthetic_actor",
 "generate_V2_events","create_or_update_inventory","create_Daily_Records",
 "create_Sales","create_allocations","execute_import","execute_import_dry_run",
 "execute_rollback_or_restore","copy_production_data",
 "access_production_secrets","alter_certified_source",
 "alter_existing_preparation_files","load_test_25_to_125_VU",
 "load_test_150_VU","load_test_200_VU","deploy_runtime",
 "grant_approval_from_D12_PASS",
)
REQUIRED_RULES = {
 "source_K1_D01_to_D11_reviews_are_not_import_permissions",
 "offline_Python_dry_run_means_document_contract_check_not_live_import_preflight",
 "PHP_migration_runner_is_executable_schema_writer_not_safe_offline_check",
 "disaster_recovery_verifier_read_only_but_connects_to_DB_and_is_forbidden_now",
 "D09_sourceId_parameter_mismatch_source_confirmed_runtime_unproven",
 "annual_2048_row_cache_cap_not_required_fake_data_size",
 "explicit_consent_needed_before_any_future_fixture_generation_or_import",
 "capacity_150_VU_HOLD_200_VU_PROHIBITED_GC24",
 "no_automatic_permission_granted_by_completion_of_D12",
}
CHECK_IDS = ["G%02d" % i for i in range(1,16)]
class Blocked(ValueError):
    pass

def check(state, label):
    if not state:
        raise Blocked(label)

def norm(text):
    return re.sub(r"\s+", "", text).lower()

def git_blob(raw):
    return hashlib.sha1(
        b"blob " + str(len(raw)).encode() + bytes([0]) + raw
    ).hexdigest()

def safety_self_check():
    tree = ast.parse(Path(__file__).read_text(encoding="utf-8"))
    allowed = {"argparse","ast","copy","datetime","hashlib","json","re","sys","pathlib"}
    banned = {"connect","execute","executemany","commit","rollback","write",
              "write_text","write_bytes","unlink","rename","mkdir","rmdir",
              "remove","popen","Popen","system","urlopen","request","eval","exec"}
    for node in ast.walk(tree):
        if isinstance(node, ast.Import):
            check(all(x.name in allowed for x in node.names),"D12_UNSAFE_IMPORT")
        elif isinstance(node, ast.ImportFrom):
            check(node.module in allowed,"D12_UNSAFE_IMPORT")
        elif isinstance(node, ast.Call):
            t=node.func
            name=t.id if isinstance(t,ast.Name) else t.attr if isinstance(t,ast.Attribute) else ""
            check(name not in banned,"D12_UNSAFE_CALL")

def frozen_D11_lineage():
    raw=D11_PATH.read_bytes()
    check(git_blob(raw)==D11_BLOB,"D11_GIT_BLOB_CHANGED")
    d11=json.loads(raw)
    check(d11["checkpoint"]=="GC24CG_K1_D11" and
          d11["review_state"]=="D11_SOURCE_REVIEW_PENDING_NODE_A" and
          d11["gates"]["D12_import"]=="UNAPPROVED" and
          all(v is None for v in d11["unknown_eligibility_and_capacity_inputs"].values()),
          "D11_INVALID_LINEAGE_APPROVAL")
    for scenario in ("R1","R2"):
        payload={
            "scenario":scenario,"specification":d11,
            "prior_d10_spec_sha256":D10[scenario]
        }
        result=hashlib.sha256(json.dumps(
            payload,sort_keys=True,separators=(",",":")
        ).encode()).hexdigest()
        check(result==D11[scenario],"D11_DIGEST_CHANGED")

def validate(d):
    check(d["version"]=="C137-GC24-K1-D12-IMPORT-AUTHORITY-GATES-0" and
          d["checkpoint"]=="GC24CG_K1_D12" and
          d["scope"]=="OFFLINE_READONLY_PRE_IMPORT_AUTHORIZATION_REQUIREMENTS" and
          d["review_status"]=="PENDING_NODE_A_D12_REVIEW",
          "D12_SCOPE_DRIFT")
    y=d["reporting"]
    a=datetime.date.fromisoformat(y["start"])
    b=datetime.date.fromisoformat(y["end"])
    check((y["selected_year"],str(a),str(b),(b-a).days+1,y["date_policy"]) ==
          (2025,"2025-01-01","2025-12-31",365,"UNAPPROVED"),
          "D12_YEAR_POLICY_DRIFT")
    check(d["as_of"]=="2026-10-11" and
          d["preparation_parent_sha"]==PARENT and
          d["certified_reports_sha"]==CERT and
          d["prior_D11_git_blob_sha1"]==D11_BLOB and
          d["prior_D11_specs_sha256"]==D11 and
          d["prior_D10_specs_sha256"]==D10 and
          d["original_2026_event_sha256"]==OLD,
          "D12_IMMUTABLE_LINEAGE_DRIFT")
    protected=d["protection_register_as_previously_reported_not_reverified"]
    check(protected=={
      "certified_checkout":"/opt/renee-agrisuite-c137-gc",
      "preparation_checkout":"/opt/renee-c137-z23-dryrun-src",
      "isolated_database_reference_only":"renee_c137_gc24_http",
      "synthetic_slug_reference_only":"c137-gc24-synthetic",
      "original_fixture_database":"renee_c137_fixture",
      "protected_restore_database":"renee_c137_gc24_restore_z25",
      "offvolume_encrypted_backup_label":"C137_GC24_Z25A_20261011T072212Z_31149.sql.enc",
      "node_A_private_IP":"172.31.40.149",
      "node_B_private_IP":"172.31.35.250",
      "node_C_private_IP":"172.31.32.62",
      "verified_in_this_checkpoint":"NOT_ACCESSED_ONLY_PRIOR_CONTEXT_REFERENCE"
    },"D12_PROTECTED_ASSET_GUARD")
    fields=d["unresolved_import_and_capacity_facts"]
    check(len(fields)>=75 and all(v is None for v in fields.values()),
          "D12_FACTS_FABRICATED")
    rows=d["source_contracts"]
    check(len(rows)==len(FILES) and
          [x["file"] for x in rows]==list(FILES) and
          [x["id"] for x in rows]==[
             "S%02d"%(i+1) for i in range(len(FILES))] and
          all(len(x["markers"])>=2 for x in rows),
          "D12_SOURCE_SCOPE_DRIFT")
    gates=d["authorization_checklist"]
    check([r["id"] for r in gates]==CHECK_IDS and
          all(r["status"]=="BLOCKED_PENDING_SEPARATE_EVIDENCE_AND_APPROVAL"
              for r in gates),"D12_APPROVAL_CHECKLIST_BYPASS")
    check(len(d["source_grounded_rules"])>=35 and
          REQUIRED_RULES.issubset(set(d["source_grounded_rules"])),
          "D12_REQUIRED_RULES_MISSING")
    check(len(d["required_future_evidence"])>=30,
          "D12_FUTURE_EVIDENCE_MISSING")
    boundary=d["approval_boundary"]
    check(boundary=={
      "D12_offline_requirements_review_can_close":True,
      "fixture_generator_write_permission":False,
      "fixture_generator_run_permission":False,
      "any_SQL_inspection_permission":False,
      "any_import_dry_run_permission":False,
      "any_import_permission":False,
      "any_DB_restore_permission":False,
      "any_capacity_load_permission":False,
      "explicit_separate_user_decision_required":True,
      "successful_static_verifier_is_not_runtime_authorization":True
    },"D12_REVIEW_MISTAKEN_FOR_IMPORT_APPROVAL")
    expected={
      "D01_to_D11_requirements":"CLOSED_OFFLINE_ONLY",
      "D12_requirements":"PENDING_NODE_A",
      "D01_event_date_policy":"UNAPPROVED",
      "D02_layer_biology":"UNAPPROVED",
      "D03_egg_availability":"UNPROVEN",
      "D04_broiler_biology":"UNAPPROVED",
      "D05_feed_costing":"UNAPPROVED",
      "D06_isolated_tenant_actor":"UNAPPROVED",
      "D07_inventory_writer":"UNAPPROVED",
      "D08_sales_writer":"UNAPPROVED",
      "D09_daily_writer":"UNAPPROVED",
      "D09_continuity_runtime":"UNPROVEN",
      "D10_financial_revision":"UNAPPROVED",
      "D11_REPORTS_2025_eligibility":"UNCOUNTED",
      "D11_capacity_evidence":"UNPROVEN",
      "D12_fixture_generation":"NOT_AUTHORIZED",
      "D12_import":"NOT_AUTHORIZED",
      "D12_import_dry_run":"NOT_AUTHORIZED",
      "D12_backup_restore":"NOT_AUTHORIZED",
      "D12_DB_read":"NOT_AUTHORIZED",
      "R1_V2_events":"NOT_GENERATED",
      "R2_V2_events":"NOT_GENERATED",
      "existing_original_2026_events":"FROZEN_UNCHANGED",
      "D12_source_PHP_execution":0,
      "D12_database_connections":0,
      "D12_database_writes":0,
      "D12_imports":0,
      "D12_load_tests":0,
      "load_150_VU":"HOLD",
      "load_200_VU":"PROHIBITED"
    }
    check(d["gates"]==expected,"D12_OPERATION_GATES_TAMPERED")
    p=d["permissions"]
    check(set(p)==set(DENIED)|{"offline_doc_review_only"} and
          p["offline_doc_review_only"] is True and
          all(p[k] is False for k in DENIED),
          "D12_UNAUTHORIZED_RUNTIME_ACTION")

def source_probes(d, overrides=None):
    check(CERT_SOURCE.is_dir() and not CERT_SOURCE.is_symlink(),
          "CERTIFIED_SOURCE_MISSING")
    total=0
    for i,rec in enumerate(d["source_contracts"]):
        path=rec["file"]
        check(path in FILES,"D12_NONCANONICAL_SOURCE")
        f=CERT_SOURCE/path
        check(f.is_file() and not f.is_symlink(),
              "D12_SOURCE_FILE_MISSING")
        txt=f.read_text(encoding="utf-8")
        if overrides and path in overrides:
            txt=overrides[path]
        for j,marker in enumerate(rec["markers"]):
            check(norm(marker) in norm(txt),
                  "D12_SOURCE_MARKER_DRIFT_"+str(i)+"_"+str(j))
            total+=1
    return total

def negatives(d):
    for field,value in (
       ("D06_isolated_tenant_farm_id",12),
       ("R1_V2_new_event_stream",["not-real"]),
       ("capacity_150_VU_decision","APPROVED"),
       ("2025_approved_preperiod_inventory_baselines",100),
    ):
        wrong=copy.deepcopy(d)
        wrong["unresolved_import_and_capacity_facts"][field]=value
        try: validate(wrong)
        except Blocked: pass
        else: raise Blocked("D12_FAKE_AUTHORITY_ACCEPTED")
    for section,key,value in (
       ("permissions","run_migrations",True),
       ("permissions","query_any_database",True),
       ("gates","D12_import","APPROVED"),
       ("gates","load_150_VU","ALLOWED"),
       ("gates","load_200_VU","ALLOWED"),
       ("approval_boundary","any_import_dry_run_permission",True),
    ):
        wrong=copy.deepcopy(d)
        wrong[section][key]=value
        try: validate(wrong)
        except Blocked: pass
        else: raise Blocked("D12_RUNTIME_AUTHORITY_ACCEPTED")
    wrong=copy.deepcopy(d)
    wrong["authorization_checklist"][11]["status"]="APPROVED"
    try: validate(wrong)
    except Blocked: pass
    else: raise Blocked("D12_FINAL_SIGNOFF_FABRICATED")
    path="scripts/run_migrations.php"
    txt=(CERT_SOURCE/path).read_text(encoding="utf-8")
    marker="$pdo->beginTransaction();"
    check(marker in txt,"D12_TAMPER_SETUP_MISSING")
    try: source_probes(d,{path:"".join(txt.split(marker))})
    except Blocked: pass
    else: raise Blocked("D12_MIGRATION_WRITE_RISK_ERASURE_ACCEPTED")

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
    if ((a.database,a.farm_slug,a.legacy_year)!=
        ("renee_c137_gc24_http","c137-gc24-synthetic",2026)
        or (a.as_of,a.reporting_year)!=("2026-10-11",2025)
        or a.certified_source_root!=str(CERT_SOURCE)):
        p.error("D12_SCOPE_GUARD_BLOCKED")
    safety_self_check()
    frozen_D11_lineage()
    d=json.loads(SPEC_PATH.read_text(encoding="utf-8"))
    validate(d)
    n=source_probes(d)
    negatives(d)
    print("K1_D12_STATIC_READONLY=PASS")
    print("K1_D11_IMMUTABLE_LINEAGE=PASS")
    print("K1_D12_SOURCE_MARKERS=PASS")
    print("K1_D12_NEGATIVE_TESTS=PASS")
    print("K1_D12_SOURCE_FILES="+str(len(FILES)))
    print("K1_D12_SOURCE_PROBES="+str(n))
    print("K1_D12_UNRESOLVED_INPUTS="+str(len(d["unresolved_import_and_capacity_facts"])))
    print("K1_D12_AUTHORIZATION_GATES="+str(len(d["authorization_checklist"])))
    print("K1_D12_IMPLEMENTATION_APPROVAL=NOT_GRANTED")
    print("K1_D12_IMPORT_APPROVAL=NOT_GRANTED")
    print("K1_D12_IMPORT_DRY_RUN_APPROVAL=NOT_GRANTED")
    print("K1_D12_PROTECTED_RESOURCES=REFERENCE_ONLY_NOT_ACCESSED")
    for scenario in ("R1","R2"):
        payload={"scenario":scenario,"specification":d,
                 "prior_d11_spec_sha256":D11[scenario]}
        digest=hashlib.sha256(json.dumps(
            payload,sort_keys=True,separators=(",",":")
        ).encode()).hexdigest()
        print(scenario+"_D12_SPEC_SHA256="+digest)
        print(scenario+"_ORIGINAL_2026_EVENT_DIGEST=UNCHANGED")
        print(scenario+"_V2_EVENT_DIGEST=NOT_GENERATED")
    print("SELECTED_REPORTING_YEAR=2025")
    print("CERTIFIED_REPORTS_UNCHANGED=YES")
    print("PHP_EXECUTED=NO")
    print("DATABASE_CONNECTIONS=0")
    print("DATABASE_WRITES=0")
    print("SOURCE_WRITES=0")
    print("IMPORTS_EXECUTED=0")
    print("RESTORES_EXECUTED=0")
    print("V2_EVENTS_GENERATED=NO")
    print("NEW_LOAD_TESTS=0")
    print("IMPORT_EXECUTION_ENABLED=NO")
    print("GC24CG_K1_D12_OFFLINE_VERIFIER=PASS")

if __name__=="__main__":
    main()
