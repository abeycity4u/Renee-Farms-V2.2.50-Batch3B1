#!/usr/bin/env python3
"""K1/D09 offline source review. No PHP, DB, writes, fixtures or load tests."""
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
BASE = Path(__file__).resolve().parent.parent
SOURCE = Path("/opt/renee-agrisuite-c137-gc")
SPEC = BASE / "docs" / "C137_GC24_K1_D09_DAILY_RECORD_REQUIREMENTS.json"
PREVIOUS = BASE / "docs" / "C137_GC24_K1_D08_TRANSACTIONAL_SALES_REQUIREMENTS.json"
PARENT = "3d5da697d26813a15e956fe9a727c33c0dd550af"
CERT = "322438870263825f9e739a12c4bd478983e67141"
PRIOR_BLOB = "b54a007e03ae2026c94fbef8dcf7e1a45fe0e9c1"
D08 = {"R1":"929fd1cd4fcdba1ab999e3bd26eb566e77b815d59d61644cd7b01c2f4362f768",
       "R2":"3294886470fc943a54bde83836d02fb9f336c2f221b581272cb9a5fbacfc822a"}
D07 = {"R1":"48267cd7c330243200cebfd837bd541dbb3aa44f51a72d9107ab52695417a63d",
       "R2":"991d5c5f14ffa462613744d4fd1491dbf6da2e83050a0e81f1c9dcf7a54d39c7"}
ORIGINAL = {"R1":"e31421ee40ce25b16feb698313fcf337e74126a8e63d8c5dadeedf74e30165f2",
            "R2":"985341ed4315788ee8f3b9d62b327da66d38fc63ea16d406c5c52bc9949d3dfe"}
FILES = (
    "poultry/layers_daily_record.php",
    "poultry/broiler_daily_record.php",
    "lib/daily_population_continuity.php",
    "lib/daily_population_boundary.php",
    "lib/daily_population_sync.php",
    "lib/production_population_projection.php",
    "lib/production_population.php",
    "lib/daily_feed_sync.php",
    "lib/stock_consumption_source_resolver.php",
    "api/delete_record.php",
    "includes/permission_runtime.php",
    "lib/daily_record_workspace_scope.php",
    "lib/layer_egg_inventory.php",
    "lib/sales_allocation.php",
    "lib/production_cycle_service.php",
)
REQUIRED_RULES = {
    "two_independent_page_local_daily_record_insert_update_writers",
    "layer_derives_crates_and_laying_rate_from_egg_count",
    "continuity_rebases_later_opening_without_editing_later_mortality_feed_water",
    "daily_feed_uses_linked_source_lock_and_canonical_stock_movements",
    "daily_delete_reverses_feed_and_mortality_before_row_removal",
    "continuity_preview_has_sourceId_signature_inconsistency",
}
REQUIRED_FUTURE = {
    "trace_and_resolve_daily_population_continuity_preview_sourceId_argument_mismatch",
    "verify_delete_of_historical_daily_record_does_not_leave_later_opening_inconsistent",
    "prove_source_lock_before_daily_feed_USE_and_costed_inventory",
    "validate_tenant_CSRF_and_separate_add_edit_delete_permissions",
}
FORBIDDEN = (
    "choose_record_dates","choose_bird_quantities","choose_bird_age",
    "choose_feed_quantities","choose_egg_production","create_daily_records",
    "update_daily_records","delete_daily_records","write_stock_used",
    "write_mortality","rebuild_egg_allocations","modify_PHP",
    "connect_database","write_database","generate_events",
    "import_fixture","deploy","run_load_tests"
)
class Blocked(ValueError):
    pass

def check(value, name):
    if not value:
        raise Blocked(name)

def norm(text):
    return re.sub(r"\s+", "", text).lower()

def git_blob(data):
    return hashlib.sha1(
        b"blob " + str(len(data)).encode() + bytes([0]) + data
    ).hexdigest()

def check_self():
    tree = ast.parse(Path(__file__).read_text(encoding="utf-8"))
    allowed = {"argparse","ast","copy","datetime","hashlib","json","re","sys","pathlib"}
    blocked = {"connect","execute","executemany","commit","rollback",
               "write","write_text","write_bytes","unlink","rename","mkdir",
               "remove","rmdir","Popen","popen","system","urlopen","request",
               "eval","exec"}
    for node in ast.walk(tree):
        if isinstance(node, ast.Import):
            check(all(x.name in allowed for x in node.names), "UNSAFE_IMPORT")
        elif isinstance(node, ast.ImportFrom):
            check(node.module in allowed, "UNSAFE_IMPORT")
        elif isinstance(node, ast.Call):
            f=node.func
            name = f.id if isinstance(f,ast.Name) else (
                f.attr if isinstance(f,ast.Attribute) else "")
            check(name not in blocked,"UNSAFE_CALL")

def check_lineage():
    data=PREVIOUS.read_bytes()
    check(git_blob(data)==PRIOR_BLOB,"D08_GIT_BLOB_DRIFT")
    d08=json.loads(data)
    check(d08["readiness"]=="D08_SALES_WRITER_AND_FINANCIAL_FACTS_UNAPPROVED"
          and all(v is None for v in d08["sale_and_payment_unknowns"].values()),
          "D08_SOURCE_DRIFT")
    for scenario in ("R1","R2"):
        payload={"scenario":scenario,"specification":d08,
                 "prior_d07_spec_sha256":D07[scenario]}
        digest=hashlib.sha256(json.dumps(
            payload,sort_keys=True,separators=(",",":")
        ).encode()).hexdigest()
        check(digest==D08[scenario],"D08_LINEAGE_DRIFT")

def check_design(d):
    check(d["version"]=="C137-GC24-K1-D09-DAILY-RECORD-WRITER-0"
          and d["checkpoint"]=="GC24CG_K1_D09"
          and d["scope"]=="OFFLINE_READONLY_REVIEW_ONLY"
          and d["readiness"]=="DAILY_RECORD_RUNTIME_AND_BIOLOGY_UNAPPROVED",
          "D09_SCOPE_DRIFT")
    yr=d["reporting"]
    start=datetime.date.fromisoformat(yr["start"])
    end=datetime.date.fromisoformat(yr["end"])
    check((yr["year"],str(start),str(end),(end-start).days+1,
           yr["dates_approved"])==
          (2025,"2025-01-01","2025-12-31",365,False),"D09_YEAR_DRIFT")
    check(d["planning_as_of"]=="2026-10-11"
          and d["preparation_parent"]==PARENT
          and d["certified_source_head"]==CERT
          and d["prior_d08_blob"]==PRIOR_BLOB
          and d["prior_d08_spec_sha256"]==D08
          and d["prior_d07_spec_sha256"]==D07
          and d["original_2026_digests"]==ORIGINAL
          and d["reference_only"]=={
              "database":"renee_c137_gc24_http",
              "tenant_slug":"c137-gc24-synthetic"},
          "D09_LINEAGE_DRIFT")
    check(len(d["unknown_inputs"])>=50
          and all(v is None for v in d["unknown_inputs"].values()),
          "BIOLOGY_FABRICATION")
    c=d["contracts"]
    check(len(c)==len(FILES)
          and [x["file"] for x in c]==list(FILES)
          and [x["id"] for x in c]==[
              "S%02d"%(i+1) for i in range(len(FILES))]
          and all(len(x["markers"])>=3 for x in c),"D09_SOURCE_SCOPE_DRIFT")
    check(len(d["observed_source_rules"])>=20
          and REQUIRED_RULES.issubset(set(d["observed_source_rules"])),
          "D09_SHARED_CONTRACT_DRIFT")
    check(len(d["future_proofs"])>=25
          and REQUIRED_FUTURE.issubset(set(d["future_proofs"])),
          "D09_FUTURE_CHECKS_DRIFT")
    i=d["unverified_source_issue"]
    check(i=={
       "id":"CONTINUITY_PREVIEW_SOURCEID_MISMATCH",
       "file":"lib/daily_population_continuity.php",
       "preview_declares_sourceId":False,
       "preview_body_uses_sourceId":True,
       "apply_passes_extra_sourceId":True,
       "runtime_status":"NOT_TESTED",
       "fix_authority":"NOT_GRANTED"
    },"D09_SIGNATURE_PROOF_FALSE_APPROVAL")
    g=d["gates"]
    check(g["D09_daily"]=="UNAPPROVED"
          and g["runtime_continuity"]=="UNPROVEN"
          and g["new_V2_events"]=="NOT_GENERATED"
          and g["database_connections"]==0
          and g["database_writes"]==0
          and g["load_150_VU"]=="HOLD"
          and g["load_200_VU"]=="PROHIBITED","D09_OPERATION_GATES_DRIFT")
    a=d["permissions"]
    check(a["offline_review_only"] is True
          and all(a[k] is False for k in FORBIDDEN),
          "D09_UNAUTHORIZED_OPERATION")

def source_checks(d,replaced=None):
    check(SOURCE.is_dir() and not SOURCE.is_symlink(),
          "CERTIFIED_SOURCE_ROOT_MISSING")
    total=0
    for i,c in enumerate(d["contracts"]):
        f=SOURCE/c["file"]
        check(f.is_file() and not f.is_symlink(),"SOURCE_FILE_MISSING")
        text=f.read_text(encoding="utf-8")
        if replaced and c["file"] in replaced:
            text=replaced[c["file"]]
        for j,m in enumerate(c["markers"]):
            check(norm(m) in norm(text),
                  "SOURCE_PROBE_DRIFT_"+str(i)+"_"+str(j))
            total+=1
    return total

def signature_check():
    source=(SOURCE/"lib/daily_population_continuity.php").read_text(
        encoding="utf-8")
    start=source.index("function daily_population_continuity_preview(")
    sig=source[start:source.index("    ): array {",start)]
    check("bool $forUpdate = false" in sig and
          "$sourceId" not in sig,"CONTINUITY_SIGNATURE_DRIFT")
    body=source[source.index("    ): array {",start):
                source.index("if (!function_exists('daily_population_continuity_apply'))",start)]
    check("$sourceId" in body,"CONTINUITY_PREVIEW_USAGE_DRIFT")
    apply=source[source.index("function daily_population_continuity_apply("):]
    check("true,\n            $sourceId" in apply,
          "CONTINUITY_APPLY_CALL_DRIFT")

def negatives(d):
    for k,value in (
        ("layer_daily_dates","2025-01-01"),
        ("layer_egg_produced",2000),
        ("broiler_daily_feed_quantity",125.0),
        ("layer_opening_flock",3000),
    ):
        x=copy.deepcopy(d)
        x["unknown_inputs"][k]=value
        try: check_design(x)
        except Blocked: pass
        else: raise Blocked("INVENTED_DAILY_FACT_ACCEPTED")
    for section,k,v in (
        ("permissions","write_database",True),
        ("permissions","create_daily_records",True),
        ("gates","runtime_continuity","PASS"),
        ("gates","load_200_VU","ALLOWED"),
    ):
        x=copy.deepcopy(d)
        x[section][k]=v
        try: check_design(x)
        except Blocked: pass
        else: raise Blocked("UNAUTHORIZED_ACTION_ACCEPTED")
    path="lib/daily_feed_sync.php"
    source=(SOURCE/path).read_text(encoding="utf-8")
    needle="daily_feed_sync_authoritative_cycle_guard("
    check(needle in source,"TAMPER_TEST_SETUP")
    try: source_checks(d,{path:"".join(source.split(needle))})
    except Blocked: pass
    else: raise Blocked("CANONICAL_FEED_GUARD_TAMPER_ACCEPTED")

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
        or a.certified_source_root!=str(SOURCE)):
        p.error("D09_SCOPE_GUARD_BLOCKED")
    check_self()
    check_lineage()
    d=json.loads(SPEC.read_text(encoding="utf-8"))
    check_design(d)
    n=source_checks(d)
    signature_check()
    negatives(d)
    print("K1_D09_STATIC_READONLY=PASS")
    print("K1_D08_IMMUTABLE_LINEAGE=PASS")
    print("K1_D09_SOURCE_MARKERS=PASS")
    print("K1_D09_NEGATIVE_TESTS=PASS")
    print("K1_D09_SOURCE_FILES="+str(len(FILES)))
    print("K1_D09_SOURCE_PROBES="+str(n))
    print("K1_D09_UNRESOLVED_INPUTS="+str(len(d["unknown_inputs"])))
    print("K1_D09_SIGNATURE_MISMATCH=SOURCE_CONFIRMED_RUNTIME_UNPROVEN")
    print("K1_D09_DAILY_WRITER_APPROVAL=NOT_GRANTED")
    for scenario in ("R1","R2"):
        payload={"scenario":scenario,"specification":d,
                 "prior_d08_spec_sha256":D08[scenario]}
        digest=hashlib.sha256(json.dumps(
            payload,sort_keys=True,separators=(",",":")
        ).encode()).hexdigest()
        print(scenario+"_D09_SPEC_SHA256="+digest)
        print(scenario+"_ORIGINAL_2026_EVENT_DIGEST=UNCHANGED")
        print(scenario+"_V2_EVENT_DIGEST=NOT_GENERATED")
    print("SELECTED_REPORTING_YEAR=2025")
    print("CERTIFIED_REPORTS_UNCHANGED=YES")
    print("PHP_EXECUTED=NO")
    print("DATABASE_CONNECTIONS=0")
    print("DATABASE_WRITES=0")
    print("SOURCE_WRITES=0")
    print("DAILY_RECORDS_CREATED=0")
    print("V2_EVENTS_GENERATED=NO")
    print("NEW_LOAD_TESTS=0")
    print("IMPORT_EXECUTION_ENABLED=NO")
    print("GC24CG_K1_D09_OFFLINE_VERIFIER=PASS")

if __name__=="__main__":
    main()
