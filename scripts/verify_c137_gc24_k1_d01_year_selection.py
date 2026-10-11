#!/usr/bin/env python3
"""K1/D01 append-only reporting-period verification. No network, DB or PHP."""
import argparse
import ast
import copy
import datetime
import hashlib
import json
import sys
from pathlib import Path

sys.dont_write_bytecode = True
DOCS = Path(__file__).resolve().parent.parent / "docs"
REGISTER = DOCS / "C137_GC24_Z27K_DECISION_REGISTER.json"
DECISION = DOCS / "C137_GC24_K1_D01_2025_SELECTION.json"
EXPECTED_BLOB = "babb4fe2e575c08b543bc435cc8cd04080f3e0e9"
EXPECTED_SOURCE = "322438870263825f9e739a12c4bd478983e67141"
EXPECTED_PREP = "476d0d34b1d58f4984208ea34bdffd1bbc9210da"
PRIOR = {"R1":"3823c305c095a5eefa025ab4054580bec3dc4d04e3013a5cbf04b4df0a65ac63",
         "R2":"17d40dbd17a72a470e1c8d99aea85d544b7be6bf9250ce6aace59c9f909bbde6"}
OLD = {"R1":"e31421ee40ce25b16feb698313fcf337e74126a8e63d8c5dadeedf74e30165f2",
       "R2":"985341ed4315788ee8f3b9d62b327da66d38fc63ea16d406c5c52bc9949d3dfe"}
PENDING = ["D%02d" % x for x in range(2,13)]


def need(condition, msg):
    if not condition:
        raise ValueError(msg)


def check_static():
    tree = ast.parse(Path(__file__).read_text(encoding="utf-8"))
    allowed = {"argparse","ast","copy","datetime","hashlib","json","sys","pathlib"}
    banned = {"connect","execute","commit","write","write_text","write_bytes",
              "unlink","mkdir","replace","system","run","Popen","popen","urlopen"}
    for node in ast.walk(tree):
        if isinstance(node, ast.Import):
            need(all(x.name in allowed for x in node.names), "EXTERNAL_IMPORT")
        elif isinstance(node, ast.ImportFrom):
            need(node.module in allowed, "EXTERNAL_IMPORT")
        elif isinstance(node, ast.Call):
            f = node.func
            label = f.id if isinstance(f, ast.Name) else (
                f.attr if isinstance(f, ast.Attribute) else "")
            need(label not in banned, "UNSAFE_CALL")


def check_frozen(blob):
    header = b"blob " + str(len(blob)).encode("ascii") + bytes([0])
    need(hashlib.sha1(header + blob).hexdigest() == EXPECTED_BLOB,
         "Z27K_REGISTER_BLOB_DRIFT")
    original = json.loads(blob)
    need(original["chosen_historical_year"] is None, "Z27K_REGISTER_CHANGED")
    need(original["legacy_digest_sha256"] == OLD, "ORIGINAL_EVENTS_DRIFT")
    need(original["database_import_approved"] is False
         and original["new_v2_event_stream"] == "NOT_GENERATED",
         "PREVIOUS_AUTHORIZATION_DRIFT")
    rows = original["decision_register"]
    need(len(rows)==12 and all(x["selected"] is None and
         x["approval"]=="NOT_GRANTED" for x in rows), "PRESELECTION_DRIFT")
    need("independent_completed_2025_year_new_stream" in rows[0]["options"],
         "UNREGISTERED_YEAR_OPTION")


def check_choice(c):
    need(c["version"]=="C137-GC24-K1-D01-DESIGN-YEAR-1"
         and c["decision"]=="D01", "DECISION_ID_DRIFT")
    need(c["origin"]=="explicit_user_instruction_in_current_chat"
         and c["as_of"]=="2026-10-11", "PROVENANCE_DRIFT")
    need(c["selected_option"]=="independent_completed_2025_year_new_stream"
         and c["selected_year"]==2025, "YEAR_CHOICE_CHANGED")
    a=datetime.date.fromisoformat(c["window_start"])
    b=datetime.date.fromisoformat(c["window_end"])
    need((str(a),str(b))==("2025-01-01","2025-12-31")
         and (b-a).days+1==c["inclusive_days"]==365
         and b<datetime.date(2026,10,11), "INCOMPLETE_OR_INVALID_PERIOD")
    need(c["status"]=="YEAR_SELECTED_DESIGN_ONLY_DATE_POLICY_PENDING",
         "FALSE_D01_COMPLETION")
    need(c["prior_preparation_head"]==EXPECTED_PREP
         and c["certified_source_sha"]==EXPECTED_SOURCE
         and c["frozen_z27k_register_blob_sha1"]==EXPECTED_BLOB,
         "IMMUTABLE_SOURCE_DRIFT")
    need(c["prior_z27k_dossier_spec_sha256"]==PRIOR and
         c["old_2026_event_digest_sha256"]==OLD and
         c["old_2026_event_counts"]=={"R1":5452,"R2":13770},
         "LEGACY_HASH_DRIFT")
    need(c["original_z27k_register"]=="FROZEN_PRESELECTION_UNCHANGED"
         and c["old_2026_to_2025_event_remap"]=="FORBIDDEN",
         "BACKDATED_LEGACY_REBASE")
    need(c["new_scenario_design_labels"]==
         ["R1-V2-2025-DESIGN","R2-V2-2025-DESIGN"]
         and c["new_events"]=="NOT_GENERATED"
         and c["new_event_digest"]=="NOT_GENERATED", "NEW_EVENT_CLAIM")
    need(c["date_rules_per_event_kind"]=="UNRESOLVED"
         and c["biological_assumptions"]=="UNRESOLVED"
         and c["downstream_unapproved"]==PENDING
         and c["next_design_checkpoint"]=="K1_D02",
         "OTHER_DECISIONS_FALSELY_RESOLVED")
    p=c["permissions"]
    need(p["design_documentation"] is True, "DESIGN_SCOPE_MISSING")
    for k in ("modify_original_z27k","modify_certified_source",
              "modify_original_event_streams","generate_new_v2_events",
              "change_php_writers","connect_database","write_database",
              "deploy","import_data","load_test"):
        need(p[k] is False, "UNAUTHORIZED_"+k.upper())
    need(p["150_vu"]=="HOLD" and p["200_vu"]=="PROHIBITED",
         "PERFORMANCE_HOLD_CHANGED")


def test_reject(c):
    for field,value in (("selected_year",2026),
                        ("old_2026_to_2025_event_remap","ALLOWED"),
                        ("new_events","GENERATED"),
                        ("downstream_unapproved",[])):
        wrong=copy.deepcopy(c)
        wrong[field]=value
        try:
            check_choice(wrong)
        except ValueError:
            continue
        raise ValueError("TAMPER_ACCEPTED_"+field.upper())
    wrong=copy.deepcopy(c)
    wrong["permissions"]["write_database"]=True
    try:
        check_choice(wrong)
    except ValueError:
        return
    raise ValueError("DB_AUTH_TAMPER_ACCEPTED")


def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--dry-run",action="store_true",required=True)
    parser.add_argument("--database",required=True)
    parser.add_argument("--farm-slug",required=True)
    parser.add_argument("--legacy-year",type=int,required=True)
    parser.add_argument("--as-of",required=True)
    parser.add_argument("--selected-year",type=int,required=True)
    args=parser.parse_args()
    if (args.database,args.farm_slug,args.legacy_year)!=(
        "renee_c137_gc24_http","c137-gc24-synthetic",2026
    ) or (args.as_of,args.selected_year)!=("2026-10-11",2025):
        parser.error("K1_D01_SCOPE_BLOCKED")
    check_static()
    check_frozen(REGISTER.read_bytes())
    chosen=json.loads(DECISION.read_text(encoding="utf-8"))
    check_choice(chosen)
    test_reject(chosen)
    print("K1_D01_STATIC_READONLY=PASS")
    print("Z27K_FROZEN_REGISTER=PASS")
    print("K1_D01_TAMPER_REJECTIONS=PASS")
    print("SELECTED_YEAR=2025")
    print("DESIGN_WINDOW=2025-01-01..2025-12-31")
    print("DATE_POLICY=PENDING")
    for key in ("R1","R2"):
        payload={"scenario":key,"selection":chosen,
                 "z27k_preselection_digest":PRIOR[key]}
        digest=hashlib.sha256(json.dumps(
            payload,sort_keys=True,separators=(",",":")
        ).encode()).hexdigest()
        print(key+"_K1_D01_SPEC_SHA256="+digest)
        print(key+"_ORIGINAL_EVENT_DIGEST=UNCHANGED")
        print(key+"_V2_EVENT_DIGEST=NOT_GENERATED")
    print("UNAPPROVED_DECISIONS_REMAINING=11")
    print("ORIGINAL_Z27K_REGISTER_UNCHANGED=YES")
    print("V2_EVENTS_GENERATED=NO")
    print("DATABASE_CONNECTIONS=0")
    print("DATABASE_WRITES=0")
    print("SOURCE_WRITES=0")
    print("PHP_EXECUTION=NO")
    print("NEW_LOAD_TESTS=0")
    print("IMPORT_EXECUTION_ENABLED=NO")
    print("GC24CG_K1_D01_OFFLINE_VERIFIER=PASS")


if __name__=="__main__":
    main()
