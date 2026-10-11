#!/usr/bin/env python3
"""K1/D10 offline allocation review: no PHP, SQL, network, writes or load."""
import argparse
import ast
import copy
import datetime
import hashlib
import json
import re
import sys
from pathlib import Path
sys.dont_write_bytecode=True
ROOT=Path(__file__).resolve().parent.parent
SRC=Path("/opt/renee-agrisuite-c137-gc")
SPEC=ROOT/"docs/C137_GC24_K1_D10_ALLOCATION_REQUIREMENTS.json"
PREV=ROOT/"docs/C137_GC24_K1_D09_DAILY_RECORD_REQUIREMENTS.json"
OLD="4095c2abfccee4899cb0e395fc683e4260993d71"
CERT="322438870263825f9e739a12c4bd478983e67141"
D09_BLOB="c391a3de0853468209afa9e2b8a40186b05374b4"
D09={"R1":"b07d009a87b0132fcd675e4aaf66e21a6594d086a871a6d84563c087c3128af6","R2":"013098e94b600544fb25651cb7154fb7e25e86096f70ebdb964b0e0a0bced94d"}
D08={"R1":"929fd1cd4fcdba1ab999e3bd26eb566e77b815d59d61644cd7b01c2f4362f768","R2":"3294886470fc943a54bde83836d02fb9f336c2f221b581272cb9a5fbacfc822a"}
LEGACY={"R1":"e31421ee40ce25b16feb698313fcf337e74126a8e63d8c5dadeedf74e30165f2","R2":"985341ed4315788ee8f3b9d62b327da66d38fc63ea16d406c5c52bc9949d3dfe"}
FILES=("lib/shared_cost_contract.php","lib/financial_allocation_service.php","lib/financial_allocation_integrity.php","lib/expense_revision_service.php","lib/financial_allocation_persistence.php","lib/sale_revenue_allocation_service.php","lib/sale_revenue_allocation_provenance.php","lib/sale_revenue_allocation_persistence.php","lib/stock_consumption_allocation_service.php","lib/stock_consumption_allocation_provenance.php","lib/stock_consumption_allocation_persistence.php","lib/profitability_unallocated_shared.php","lib/stock_consumption_economics.php","lib/financial_allocation_workspace.php","lib/sale_revenue_allocation_workspace.php","lib/stock_consumption_allocation_workspace.php","api/update_financial_allocation.php","api/update_sale_revenue_allocation.php","api/update_stock_consumption_allocation.php","lib/stock_service.php")
FORBIDDEN=("choose_allocation_amounts","choose_allocation_percentages","create_expense_allocations","create_revenue_allocations","create_stock_allocations","retain_shared_runtime","change_source_parent","alter_revision_history","modify_PHP","run_PHP","connect_to_DB","write_DB","create_V2_events","import_fixture","deploy","run_load_test")
DOMAINS={"expense":{"parent":"farm_expenses","projection":"financial_allocations","history":"farm_expense_revisions"},"revenue":{"parent":"sales_records","projection":"sales_allocations","history":"sales_allocation_revisions + sales_allocation_revision_rows","automatic_layer_egg":"EXCLUDED"},"stock":{"parent":"effective USED stock_transactions","projection":"stock_consumption_allocations","history":"stock_consumption_allocation_revisions","reversal":"CLOSE_IN_SOURCE_TRANSACTION"}}
REQUIRED={"manual_expense_parent_farm_expenses_not_new_child_expense","manual_revenue_parent_sales_records_not_payment_cash","stock_cost_parent_is_effective_costed_used_only","parent_equals_explicit_allocated_plus_visible_unallocated","Profitability_unallocated_shared_is_disclosure_only_not_PL","stock_cost_parent_reversal_is_lifecycle_closure","exact_semantic_noop_does_not_create_revision"}
REQUIRED_FUTURE={"prove_parent_amount_equals_allocated_plus_unallocated_in_cents","prove_source_reversal_closes_allocations_atomically","reconcile_D03_layer_egg_auto_allocation_without_manual_split","test_cross_tenant_actor_permission_CSRF_rate_limit_denials"}
class Blocked(ValueError): pass
def check(ok,reason):
    if not ok: raise Blocked(reason)
def norm(s): return re.sub(r"\s+","",s).lower()
def blob(raw): return hashlib.sha1(b"blob "+str(len(raw)).encode()+bytes([0])+raw).hexdigest()
def self_guard():
    t=ast.parse(Path(__file__).read_text(encoding="utf-8"))
    safe={"argparse","ast","copy","datetime","hashlib","json","re","sys","pathlib"}
    unsafe={"connect","execute","executemany","commit","rollback","write","write_bytes","write_text","unlink","rename","mkdir","rmdir","remove","popen","Popen","system","urlopen","request","eval","exec"}
    for n in ast.walk(t):
        if isinstance(n,ast.Import): check(all(m.name in safe for m in n.names),"UNSAFE_IMPORT")
        elif isinstance(n,ast.ImportFrom): check(n.module in safe,"UNSAFE_IMPORT")
        elif isinstance(n,ast.Call):
            f=n.func
            name=f.id if isinstance(f,ast.Name) else f.attr if isinstance(f,ast.Attribute) else ""
            check(name not in unsafe,"UNSAFE_CALL")
def lineage():
    raw=PREV.read_bytes()
    check(blob(raw)==D09_BLOB,"D09_BLOB_DRIFT")
    p=json.loads(raw)
    check(p["checkpoint"]=="GC24CG_K1_D09" and p["gates"]["D09_daily"]=="UNAPPROVED" and all(v is None for v in p["unknown_inputs"].values()),"D09_FALSE_APPROVAL")
    for scenario in ("R1","R2"):
        payload={"scenario":scenario,"specification":p,"prior_d08_spec_sha256":D08[scenario]}
        digest=hashlib.sha256(json.dumps(payload,sort_keys=True,separators=(",",":")).encode()).hexdigest()
        check(digest==D09[scenario],"D09_SPEC_DRIFT")
def validate(d):
    check(d["version"]=="C137-GC24-K1-D10-ALLOCATION-REVISIONS-0" and d["checkpoint"]=="GC24CG_K1_D10" and d["scope"]=="READ_ONLY_OFFLINE_REQUIREMENTS" and d["approval"]=="NOT_GRANTED","D10_SCOPE_DRIFT")
    w=d["reporting"];a=datetime.date.fromisoformat(w["start"]);b=datetime.date.fromisoformat(w["end"])
    check((w["year"],str(a),str(b),(b-a).days+1,w["event_date_policy"])==(2025,"2025-01-01","2025-12-31",365,"UNAPPROVED"),"D10_DATE_DRIFT")
    check(d["as_of"]=="2026-10-11" and d["parent_prep_HEAD"]==OLD and d["protected_reports_HEAD"]==CERT and d["prior_D09_git_blob"]==D09_BLOB and d["prior_D09_spec_digest"]==D09 and d["prior_D08_spec_digest"]==D08 and d["original_2026_event_digest"]==LEGACY and d["db_label_reference_only"]=="renee_c137_gc24_http" and d["tenant_slug_reference_only"]=="c137-gc24-synthetic","D10_LINEAGE_DRIFT")
    check(len(d["unknown_inputs"])>=60 and all(v is None for v in d["unknown_inputs"].values()),"D10_INVENTED_AMOUNTS")
    rows=d["source_contracts"]
    check(len(rows)==len(FILES) and [x["file"] for x in rows]==list(FILES) and [x["id"] for x in rows]==["S%02d"%(i+1) for i in range(len(FILES))] and all(len(x["markers"])>=4 for x in rows),"D10_SOURCE_SCOPE_DRIFT")
    check(len(d["source_rules"])>=30 and REQUIRED.issubset(set(d["source_rules"])),"D10_SOURCE_RULES_MISSING")
    check(len(d["future_evidence"])>=30 and REQUIRED_FUTURE.issubset(set(d["future_evidence"])),"D10_FUTURE_PROOFS_MISSING")
    check(len(d["conservation_invariants"])==10 and d["domain_separation"]==DOMAINS,"D10_DOMAIN_CONFLATION")
    g=d["gates"]
    check(g=={"D01_reporting_year":"2025_DESIGN_ONLY","D01_date_policy":"UNAPPROVED","D02_layer":"UNAPPROVED","D03_egg":"UNPROVEN","D04_broiler":"UNAPPROVED","D05_feed":"UNAPPROVED","D06_actor":"UNAPPROVED","D07_inventory":"UNAPPROVED","D08_sales":"UNAPPROVED","D09_daily":"UNAPPROVED","D10_allocation":"UNAPPROVED","D11_D12":"UNAPPROVED","actual_allocation_rows":"NOT_CREATED","actual_revision_rows":"NOT_CREATED","runtime_reconciliation":"UNPROVEN","V2_events":"NOT_GENERATED","DB_connections":0,"DB_writes":0,"capacity_150_VU":"HOLD","capacity_200_VU":"PROHIBITED"},"D10_GATES_DRIFT")
    a=d["permissions"]
    check(set(a)==set(FORBIDDEN)|{"read_only_review"} and a["read_only_review"] is True and all(a[k] is False for k in FORBIDDEN),"D10_UNAUTHORIZED_OPERATION")
def source_probe(d,overrides=None):
    check(SRC.is_dir() and not SRC.is_symlink(),"SOURCE_ROOT_INVALID")
    count=0
    for i,row in enumerate(d["source_contracts"]):
        f=SRC/row["file"]
        check(f.is_file() and not f.is_symlink(),"SOURCE_FILE_MISSING")
        text=f.read_text(encoding="utf-8")
        if overrides and row["file"] in overrides: text=overrides[row["file"]]
        for j,m in enumerate(row["markers"]):
            check(norm(m) in norm(text),"D10_SOURCE_DRIFT_"+str(i)+"_"+str(j));count+=1
    return count
def negatives(d):
    for k,v in (("expense_allocated_amounts",[60,40]),("sale_unallocated_cents",0),("used_movement_total_cost",1000),("target_tenant_ID",9)):
        x=copy.deepcopy(d);x["unknown_inputs"][k]=v
        try: validate(x)
        except Blocked: pass
        else: raise Blocked("INVENTED_FINANCIAL_FACT_ACCEPTED")
    for section,k,v in (("permissions","connect_to_DB",True),("permissions","create_revenue_allocations",True),("gates","D10_allocation","APPROVED"),("gates","capacity_200_VU","ENABLED")):
        x=copy.deepcopy(d);x[section][k]=v
        try: validate(x)
        except Blocked: pass
        else: raise Blocked("D10_GATE_TAMPER_ACCEPTED")
    f="lib/shared_cost_contract.php";src=(SRC/f).read_text(encoding="utf-8")
    needle="function shared_cost_contract_conservation("
    check(needle in src,"SOURCE_TAMPER_SETUP")
    try: source_probe(d,{f:"".join(src.split(needle))})
    except Blocked: pass
    else: raise Blocked("CONSERVATION_TAMPER_ACCEPTED")
    x=copy.deepcopy(d);x["domain_separation"]["revenue"]["parent"]="customer_payments"
    try: validate(x)
    except Blocked: pass
    else: raise Blocked("CASH_REVENUE_CONFLATION_ACCEPTED")
def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument("--dry-run",action="store_true",required=True)
    p.add_argument("--database",required=True);p.add_argument("--farm-slug",required=True)
    p.add_argument("--legacy-year",type=int,required=True);p.add_argument("--as-of",required=True)
    p.add_argument("--reporting-year",type=int,required=True);p.add_argument("--certified-source-root",required=True)
    a=p.parse_args()
    if ((a.database,a.farm_slug,a.legacy_year)!=("renee_c137_gc24_http","c137-gc24-synthetic",2026) or (a.as_of,a.reporting_year)!=("2026-10-11",2025) or a.certified_source_root!=str(SRC)):p.error("D10_SCOPE_GUARD_BLOCKED")
    self_guard();lineage();d=json.loads(SPEC.read_text(encoding="utf-8"))
    validate(d);count=source_probe(d);negatives(d)
    print("K1_D10_STATIC_READONLY=PASS")
    print("K1_D09_IMMUTABLE_LINEAGE=PASS")
    print("K1_D10_SOURCE_MARKERS=PASS")
    print("K1_D10_NEGATIVE_TESTS=PASS")
    print("K1_D10_SOURCE_FILES="+str(len(FILES)))
    print("K1_D10_SOURCE_PROBES="+str(count))
    print("K1_D10_UNRESOLVED_INPUTS="+str(len(d["unknown_inputs"])))
    print("K1_D10_PARENT_DOMAINS=EXPENSE_REVENUE_STOCK_SEPARATE")
    print("K1_D10_ALLOCATION_APPROVAL=NOT_GRANTED")
    print("K1_D10_RUNTIME_RECONCILIATION=UNPROVEN")
    for scenario in ("R1","R2"):
        payload={"scenario":scenario,"specification":d,"prior_d09_spec_sha256":D09[scenario]}
        digest=hashlib.sha256(json.dumps(payload,sort_keys=True,separators=(",",":")).encode()).hexdigest()
        print(scenario+"_D10_SPEC_SHA256="+digest)
        print(scenario+"_ORIGINAL_2026_EVENT_DIGEST=UNCHANGED")
        print(scenario+"_V2_EVENT_DIGEST=NOT_GENERATED")
    print("SELECTED_REPORTING_YEAR=2025")
    print("CERTIFIED_REPORTS_UNCHANGED=YES")
    print("PHP_EXECUTED=NO")
    print("DATABASE_CONNECTIONS=0")
    print("DATABASE_WRITES=0")
    print("SOURCE_WRITES=0")
    print("ALLOCATION_ROWS_CREATED=0")
    print("REVISION_ROWS_CREATED=0")
    print("V2_EVENTS_GENERATED=NO")
    print("NEW_LOAD_TESTS=0")
    print("IMPORT_EXECUTION_ENABLED=NO")
    print("GC24CG_K1_D10_OFFLINE_VERIFIER=PASS")
if __name__=="__main__":main()
