#!/usr/bin/env python3
"""K2/P01 source-only readiness triage. Never run application PHP or SQL."""
import argparse
import ast
import copy
import hashlib
import json
import re
import sys
from pathlib import Path

sys.dont_write_bytecode=True
ROOT=Path(__file__).resolve().parent.parent
SRC=Path("/opt/renee-agrisuite-c137-gc")
DOC=ROOT/"docs/C137_GC24_K2_P01_READINESS_ENTRY.json"
PREV=ROOT/"docs/C137_GC24_K1_D12_IMPORT_AUTHORITY_REQUIREMENTS.json"
OLD="501bc6b6c2a86833aff151e0b003d18def20cfea"
CERT="322438870263825f9e739a12c4bd478983e67141"
D12_BLOB="8e131d1fb4d8996d5462cc46bd8045172bb39308"
CONT_BLOB="acbbae9a9796b8d87e4bb6cd1fc069ed3623aabc"
D12={"R1":"0f9c108376a850439e26540bc9c011da7df4f430bc1e691d050bdabc9ccf27f7",
     "R2":"3ff116b4d8f2c29c59f8f4c9d7d2ae19093574431c8aa4f3ea5bbce8cdd2b73c"}
D11={"R1":"5de70a178ca4660eba53346f7dc43fb47302322511369261cdb1773cd7c17600",
     "R2":"d424fc5bfc773619aaeadb6a245b1c5cea85c6a36b86d3cecb11bb8c7a413023"}
OLD_EVENTS={"R1":"e31421ee40ce25b16feb698313fcf337e74126a8e63d8c5dadeedf74e30165f2",
            "R2":"985341ed4315788ee8f3b9d62b327da66d38fc63ea16d406c5c52bc9949d3dfe"}
FILES=("lib/daily_population_continuity.php","poultry/layers_daily_record.php",
       "poultry/broiler_daily_record.php","lib/daily_population_boundary.php")
class Blocked(ValueError): pass
def check(v,k):
    if not v: raise Blocked(k)
def blob(data):
    return hashlib.sha1(b"blob "+str(len(data)).encode()+bytes([0])+data).hexdigest()
def norm(s):
    return re.sub(r"\s+","",s).lower()
def self_check():
    tree=ast.parse(Path(__file__).read_text(encoding="utf-8"))
    allowed={"argparse","ast","copy","hashlib","json","re","sys","pathlib"}
    unsafe={"connect","execute","executemany","commit","rollback","write",
            "write_text","write_bytes","unlink","rename","mkdir","rmdir",
            "remove","Popen","popen","system","urlopen","request","eval","exec"}
    for node in ast.walk(tree):
        if isinstance(node,ast.Import):
            check(all(x.name in allowed for x in node.names),"UNSAFE_IMPORT")
        elif isinstance(node,ast.ImportFrom):
            check(node.module in allowed,"UNSAFE_IMPORT")
        elif isinstance(node,ast.Call):
            f=node.func
            name=f.id if isinstance(f,ast.Name) else f.attr if isinstance(f,ast.Attribute) else ""
            check(name not in unsafe,"UNSAFE_CALL")
def prior():
    raw=PREV.read_bytes()
    check(blob(raw)==D12_BLOB,"D12_BLOB_DRIFT")
    d=json.loads(raw)
    check(d["checkpoint"]=="GC24CG_K1_D12" and
          d["gates"]["D12_import"]=="NOT_AUTHORIZED" and
          len(d["authorization_checklist"])==15 and
          all(x["status"]=="BLOCKED_PENDING_SEPARATE_EVIDENCE_AND_APPROVAL"
              for x in d["authorization_checklist"]),
          "D12_IMMUTABLE_AUTHORITY_DRIFT")
    check(len(d["unresolved_import_and_capacity_facts"])==86 and
          all(v is None for v in d["unresolved_import_and_capacity_facts"].values()),
          "D12_UNKNOWN_FACTS_CHANGED")
    for scenario in ("R1","R2"):
        payload={"scenario":scenario,"specification":d,
                 "prior_d11_spec_sha256":D11[scenario]}
        digest=hashlib.sha256(json.dumps(
            payload,sort_keys=True,separators=(",",":")
        ).encode()).hexdigest()
        check(digest==D12[scenario],"D12_SPEC_LINEAGE_DRIFT")
def validate(d):
    check(d["version"]=="C137-GC24-K2-P01-READINESS-ENTRY-0"
          and d["checkpoint"]=="GC24CG_K2_P01"
          and d["status"]=="PREIMPLEMENTATION_DESIGN_ONLY"
          and d["scope"]=="READONLY_SOURCE_TRIAGE_AND_GATE_PRIORITIZATION",
          "K2_SCOPE_DRIFT")
    check(d["as_of"]=="2026-10-11" and
          d["prior_preparation_head"]==OLD and
          d["protected_certified_head"]==CERT and
          d["prior_D12_json_blob_sha1"]==D12_BLOB and
          d["prior_D12_specs_sha256"]==D12 and
          d["prior_D11_specs_sha256"]==D11 and
          d["original_2026_event_sha256"]==OLD_EVENTS,
          "K2_LINEAGE_DRIFT")
    k1=d["prior_node_A_offline_completion"]
    check(k1=={"D01_to_D12":"USER_PROVIDED_PASS_EVIDENCE",
               "D12":"GC24CG_K1_D12_NODE_A=PASS",
               "not_live_implementation_approval":True},
          "K2_PREVIOUS_PASS_MISREPRESENTED")
    p=d["prior_D12_authorization"]
    check(p=={"all_15_gates":"BLOCKED_PENDING_SEPARATE_EVIDENCE_AND_APPROVAL",
              "prior_facts_unknown_count":86,
              "import":"NOT_AUTHORIZED",
              "import_dry_run":"NOT_AUTHORIZED",
              "database_read":"NOT_AUTHORIZED",
              "capacity_150_VU":"HOLD","capacity_200_VU":"PROHIBITED"},
          "K2_D12_DENIAL_DRIFT")
    row=d["source_contracts"]
    check([x["path"] for x in row]==list(FILES)
          and all(len(x["markers"])>=3 for x in row),
          "K2_SOURCE_SCOPE_DRIFT")
    f=d["technical_finding"]
    check(f["id"]=="D09_CONTINUITY_PREVIEW_SOURCEID_SIGNATURE_MISMATCH"
          and f["source_git_blob_sha1"]==CONT_BLOB
          and f["source_level_preview_declares_sourceId"] is False
          and f["source_level_preview_reads_sourceId"] is True
          and f["source_level_apply_passes_tenth_argument_sourceId"] is True
          and f["source_level_canonical_rejects_absent_sourceId"] is True
          and f["runtime_effect"]=="NOT_PROVEN"
          and f["source_patch_allowed_now"] is False
          and f["PHP_execution_allowed_now"] is False
          and f["runtime_test_allowed_now"] is False,
          "K2_CONTINUITY_FALSE_CERTIFICATION")
    steps=d["next_steps"]
    check(len(steps)==6
          and [x["id"] for x in steps]==[
              "P01-A","P01-B","P01-C","P01-D","P01-E","P01-F"
          ] and steps[0]["status"]=="DOCUMENTED_PATCH_NOT_AUTHORIZED"
          and all(x["status"]=="BLOCKED" for x in steps[1:]),
          "K2_IMPORT_SEQUENCE_APPROVED")
    check(len(d["future_regression_matrix"])>=8
          and all(x["runtime"]=="NOT_RUN" for x in d["future_regression_matrix"]),
          "K2_RUNTIME_TEST_FABRICATED")
    g=d["gates"]
    check(g["K2_P01_source_review"]=="PENDING_NODE_A"
          and g["D09_runtime_continuity"]=="UNPROVEN"
          and g["D12_import"]=="NOT_AUTHORIZED"
          and g["D12_db_read"]=="NOT_AUTHORIZED"
          and g["new_V2_events"]=="NOT_GENERATED"
          and g["capacity_150_VU"]=="HOLD"
          and g["capacity_200_VU"]=="PROHIBITED"
          and all(g[x]==0 for x in (
              "PHP_executed","database_connections","database_writes",
              "source_PHP_changes","load_tests")),
          "K2_OPERATION_GATE_DRIFT")
    permits=d["permissions"]
    check(permits["offline_readonly_doc_verifier"] is True
          and all(v is False for k,v in permits.items()
                  if k!="offline_readonly_doc_verifier"),
          "K2_RUNTIME_PERMISSION_FORGED")
def probe(d, overrides=None):
    check(SRC.is_dir() and not SRC.is_symlink(),"SOURCE_ROOT_MISSING")
    count=0
    for i,r in enumerate(d["source_contracts"]):
        f=SRC/r["path"]
        check(f.is_file() and not f.is_symlink(),"SOURCE_FILE_MISSING")
        s=f.read_text(encoding="utf-8")
        if overrides and r["path"] in overrides: s=overrides[r["path"]]
        for j,m in enumerate(r["markers"]):
            check(norm(m) in norm(s),
                  "SOURCE_MARKER_DRIFT_"+str(i)+"_"+str(j))
            count+=1
    source=(SRC/FILES[0]).read_bytes()
    check(blob(source)==CONT_BLOB,"CONTINUITY_SOURCE_BLOB_DRIFT")
    return count
def signature():
    s=(SRC/FILES[0]).read_text(encoding="utf-8")
    start=s.index("function daily_population_continuity_preview(")
    end=s.index("    ): array {",start)
    sig=s[start:end]
    check("bool $forUpdate = false" in sig and "$sourceId" not in sig,
          "PREVIEW_SIGNATURE_CHANGED_REVIEW_NEEDED")
    body=s[end:s.index("if (!function_exists('daily_population_continuity_apply'))",end)]
    check("$sourceId" in body,"PREVIEW_SOURCE_ID_USAGE_CHANGED")
    canonical=s[s.index("function daily_population_continuity_canonical_preview("):]
    check("if ($sourceId === null || $sourceId <= 0)" in canonical,
          "CANONICAL_SOURCE_ID_GUARD_CHANGED")
    apply=s[s.index("function daily_population_continuity_apply("):]
    check("true,\n            $sourceId" in apply,
          "APPLY_SOURCE_ID_CALL_CHANGED")
def negatives(d):
    for section,key,value in (
       ("permissions","modify_preparation_PHP",True),
       ("permissions","read_database",True),
       ("gates","D09_runtime_continuity","PASS"),
       ("gates","capacity_200_VU","ALLOWED"),
       ("technical_finding","runtime_effect","CERTIFIED"),
       ("technical_finding","source_patch_allowed_now",True)
    ):
        x=copy.deepcopy(d)
        x[section][key]=value
        try: validate(x)
        except Blocked: pass
        else: raise Blocked("K2_UNAUTHORIZED_STATE_ACCEPTED")
    x=copy.deepcopy(d)
    x["next_steps"][5]["status"]="APPROVED"
    try: validate(x)
    except Blocked: pass
    else: raise Blocked("K2_IMPORT_AUTHORITY_BYPASS")
def main():
    ap=argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--dry-run",action="store_true",required=True)
    ap.add_argument("--certified-source-root",required=True)
    ap.add_argument("--year",type=int,required=True)
    a=ap.parse_args()
    if a.certified_source_root!=str(SRC) or a.year!=2025:
        ap.error("K2_P01_SCOPE_DENIED")
    self_check()
    prior()
    d=json.loads(DOC.read_text(encoding="utf-8"))
    validate(d)
    count=probe(d)
    signature()
    negatives(d)
    print("K2_P01_STATIC_READONLY=PASS")
    print("K1_D12_IMMUTABLE_LINEAGE=PASS")
    print("K2_P01_SOURCE_FILES="+str(len(FILES)))
    print("K2_P01_SOURCE_MARKERS="+str(count))
    print("K2_P01_NEGATIVE_TESTS=PASS")
    print("K2_P01_SOURCEID_MISMATCH=SOURCE_CONFIRMED_RUNTIME_UNPROVEN")
    print("K2_P01_NEXT_PATCH_AUTHORITY=NOT_GRANTED")
    print("K2_P01_IMPORT_AUTHORITY=NOT_GRANTED")
    print("K2_P01_15_AUTHORIZATION_GATES=BLOCKED")
    print("K2_P01_2025_REPORTING_YEAR=DESIGN_ONLY")
    print("K2_P01_V2_EVENTS=NOT_GENERATED")
    print("CERTIFIED_SOURCE_UNMODIFIED=YES")
    print("PHP_EXECUTED=NO")
    print("DATABASE_CONNECTIONS=0")
    print("DATABASE_WRITES=0")
    print("SOURCE_PHP_WRITES=0")
    print("IMPORTS_EXECUTED=0")
    print("LOAD_150_VU=HOLD")
    print("LOAD_200_VU=PROHIBITED")
    print("GC24CG_K2_P01_OFFLINE_VERIFIER=PASS")
if __name__=="__main__":
    main()
