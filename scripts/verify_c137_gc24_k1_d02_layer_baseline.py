#!/usr/bin/env python3
"""K1/D02 Layer baseline evidence specification: offline static review only.

Reads certified PHP as text, K1/D01 snapshot, and design JSON. Never calls
PHP, generates V2 events, uses DB/network or changes source files.
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
DOCS = ROOT / "docs"
D01 = DOCS / "C137_GC24_K1_D01_2025_SELECTION.json"
SPEC = DOCS / "C137_GC24_K1_D02_LAYER_BASELINE_REQUIREMENTS.json"
SOURCE = Path("/opt/renee-agrisuite-c137-gc")
PREP = "cc186fa7799c048236a76c8dd58fcbd75c472e91"
CERT = "322438870263825f9e739a12c4bd478983e67141"
D01_BLOB = "db5ded90c63f35a59861e4e8a58ed07a0628b0db"
D01_SPEC = {
    "R1": "abf6ab785d80eef17af38cc36b036737b2ea08024b550a1202773964ca49dff8",
    "R2": "53b84e596c910473471b21926e634cb13d52e45bfe45c515b21e50dc0ff12091",
}
ORIGINAL = {
    "R1": "e31421ee40ce25b16feb698313fcf337e74126a8e63d8c5dadeedf74e30165f2",
    "R2": "985341ed4315788ee8f3b9d62b327da66d38fc63ea16d406c5c52bc9949d3dfe",
}
PATHS = (
    "lib/production_cycle_service.php",
    "lib/production_population.php",
    "lib/poultry_cycle_onboarding.php",
    "lib/poultry_cycle_acquisition.php",
    "lib/poultry_cycle_lifecycle.php",
    "lib/daily_population_continuity.php",
    "poultry/layers_daily_record.php",
    "lib/poultry_production_entry_snapshots.php",
)
# Semantic markers, whitespace-tolerant. Not a substitute for runtime PHP tests.
MARKERS = {
    PATHS[0]: (
        r"function\s+production_cycle_create_v3\s*\(",
        r"production_population_establish_baseline\s*\(",
        r"'cycle_opening'",
        r"'active'",
        r"opening_headcount",
    ),
    PATHS[1]: (
        r"function\s+production_population_establish_baseline\s*\(",
        r"baselineDate\s*!==\s*\(string\)\s*\$cycle\['start_date'\]",
        r"baselineQuantity\s*!==\s*\(int\)\s*\$cycle\['opening_headcount'\]",
        r"function\s+production_population_project_delta_locked\s*\(",
        r"projectedQuantity\s*<\s*0",
    ),
    PATHS[2]: (
        r"function\s+poultry_cycle_onboarding_record_initial\s*\(",
        r"poultry_acquisition_record\s*\(",
        r"poultry_lifecycle_record_initial_phase\s*\(",
        r"purchased_point_of_lay",
        r"initialPhase\s*!==\s*'production'",
        r"ageDays\s*<\s*1",
    ),
    PATHS[3]: (
        r"function\s+poultry_acquisition_allowed_types\s*\(",
        r"'purchased_point_of_lay'",
        r"'internal_transfer'",
        r"function\s+poultry_acquisition_record\s*\(",
        r"acquisitionType\s*!==\s*'internal_transfer'",
        r"totalCost\s*===\s*null",
        r"Acquisition date cannot be earlier",
        r"firstPhaseStart",
    ),
    PATHS[4]: (
        r"'rearing'\s*=>\s*'Rearing'",
        r"'production'\s*=>\s*'Production'",
        r"function\s+poultry_lifecycle_transition_phase\s*\(",
        r"Transition date must be later",
    ),
    PATHS[5]: (
        r"function\s+daily_population_continuity_expected_opening\s*\(",
        r"daily_population_boundary_snapshot\s*\(",
        r"'opening_stock'",
    ),
    PATHS[6]: (
        r"daily_population_continuity_expected_opening\s*\(",
        r"eggProduction\s*>\s*\$openingStock",
        r"mortality\s*>\s*\$openingStock",
        r"round\s*\(\s*\$eggProduction\s*/\s*30\s*,\s*2\s*\)",
        r"sales_rebuild_layer_egg_allocations\s*\(",
    ),
    PATHS[7]: (
        r"function\s+poultry_production_entry_candidate\s*\(",
        r"'provenance_fingerprint'",
        r"'production_entry_headcount'",
    ),
}


def demand(ok, reason):
    if not ok:
        raise ValueError(reason)


def sha_git_blob(raw):
    head = b"blob " + str(len(raw)).encode("ascii") + bytes([0])
    return hashlib.sha1(head + raw).hexdigest()


def static_no_writes():
    tree = ast.parse(Path(__file__).read_text(encoding="utf-8"))
    allowed = {"argparse","ast","copy","datetime","hashlib","json","re","sys","pathlib"}
    forbidden = {"connect","execute","commit","write","write_text","write_bytes",
                 "mkdir","unlink","remove","rename","replace","system","Popen",
                 "popen","run","urlopen","request","eval","exec"}
    for node in ast.walk(tree):
        if isinstance(node, ast.Import):
            demand(all(x.name in allowed for x in node.names), "EXTERNAL_IMPORT")
        elif isinstance(node, ast.ImportFrom):
            demand(node.module in allowed, "EXTERNAL_IMPORT")
        elif isinstance(node, ast.Call):
            f = node.func
            name = f.id if isinstance(f, ast.Name) else (
                f.attr if isinstance(f, ast.Attribute) else "")
            demand(name not in forbidden, "UNSAFE_IO_CALL")


def confirm_prior():
    raw = D01.read_bytes()
    demand(sha_git_blob(raw) == D01_BLOB, "D01_IMMUTABILITY_DRIFT")
    d = json.loads(raw)
    demand(d["selected_year"] == 2025 and
           d["window_start"] == "2025-01-01" and
           d["window_end"] == "2025-12-31", "D01_YEAR_DRIFT")
    demand(d["new_events"] == "NOT_GENERATED" and
           d["old_2026_to_2025_event_remap"] == "FORBIDDEN",
           "D01_EVENT_AUTHORITY_DRIFT")
    demand(d["permissions"]["write_database"] is False,
           "D01_IMPORT_AUTHORITY_DRIFT")


def validate_spec(spec):
    demand(spec["schema_version"] ==
           "C137-GC24-K1-D02-LAYER-BASELINE-REQUIREMENTS-0" and
           spec["checkpoint"] == "GC24CG_K1_D02", "SPEC_IDENTITY_DRIFT")
    demand(spec["status"] == "REQUIREMENTS_CAPTURED_BIOLOGICAL_INPUTS_UNAPPROVED",
           "UNAPPROVED_BIOLOGY_MARKED_READY")
    demand(spec["category"] == "DESIGN_ONLY_NO_FIXTURE_EVENTS",
           "DESIGN_ONLY_SCOPE_DRIFT")
    demand(spec["prior_preparation_head"] == PREP and
           spec["certified_reports_source_sha"] == CERT and
           spec["prior_k1_d01_selection_blob_sha1"] == D01_BLOB and
           spec["prior_k1_d01_spec_sha256"] == D01_SPEC and
           spec["original_2026_event_sha256"] == ORIGINAL,
           "ANCESTRY_DIGEST_DRIFT")
    win = spec["selected_reporting_window"]
    a = datetime.date.fromisoformat(win["start"])
    b = datetime.date.fromisoformat(win["end"])
    demand((str(a),str(b)) == ("2025-01-01","2025-12-31") and
           (b - a).days + 1 == win["inclusive_days"] == 365 and
           win["approved_scope"] == "YEAR_SELECTION_ONLY",
           "REPORTING_WINDOW_CHANGED")
    demand(spec["layer_cycle_count_for_v2"] is None and
           spec["legacy_scenario_layer_labels_only"] == ["layer-a","layer-b"],
           "UNAUTHORIZED_CYCLE_COUNT")
    unknowns = spec["per_cycle_unknowns"]
    demand(len(unknowns) >= 15 and all(v is None for v in unknowns.values()),
           "BIOLOGICAL_INPUTS_FABRICATED")
    demand(spec["production_date_before_2025_if_needed"] ==
           "NOT_DECIDED_REQUIRES_CROSS_PERIOD_DEPENDENCY_REVIEW",
           "CROSS_PERIOD_INPUTS_FALSELY_APPROVED")
    facts = spec["source_facts"]
    demand(len(facts) == len(PATHS) and
           [x["file"] for x in facts] == list(PATHS) and
           [x["id"] for x in facts] ==
           ["S" + str(n) for n in range(1,9)],
           "SOURCE_FACTS_CHANGED")
    checks = spec["required_design_checks"]
    essential = {
        "acquisition_entry_is_not_itself_a_population_baseline",
        "reconcile_acquired_quantity_with_opening_headcount_from_authoritative_sources",
        "no_duplicate_acquisition_population_credit",
        "point_of_lay_purchase_starts_in_production",
        "purchased_flock_has_actual_total_acquisition_cost",
        "2025_reporting_year_does_not_choose_2025_cycle_start",
        "no_assumed_laying_percent_or_flock_size_from_sale_targets",
        "future_production_entry_economics_provenance_requires_separate_review",
    }
    demand(essential.issubset(checks) and len(checks) >= 16,
           "REQUIRED_CONSERVATION_CHECK_MISSING")
    demand(len(spec["unresolved_owner_evidence"]) >= 10,
           "OWNER_EVIDENCE_LIST_MISSING")
    gate = spec["gate_status"]
    demand(gate["D01_YEAR_SELECTION"] == "SELECTED_DESIGN_ONLY" and
           gate["D01_EVENT_DATE_RULES"] == "PENDING" and
           gate["D02_LAYER_BASELINE"] == "UNAPPROVED" and
           gate["D03_LAYER_EGG_PRODUCTION"] == "UNAPPROVED" and
           gate["NEW_V2_EVENT_MODEL"] == "NOT_GENERATED" and
           gate["DATABASE_IMPORT"] == "PROHIBITED" and
           gate["SOURCE_MODIFICATION"] == "PROHIBITED" and
           gate["LOAD_150_VU"] == "HOLD" and
           gate["LOAD_200_VU"] == "PROHIBITED",
           "EXECUTION_OR_APPROVAL_POLICY_DRIFT")


def inspect_sources(root, override=None):
    demand(str(root) == str(SOURCE) and root.is_dir() and not root.is_symlink(),
           "CERTIFIED_ROOT_GUARD")
    results = {}
    for path in PATHS:
        sourcefile = root / path
        demand(sourcefile.is_file() and not sourcefile.is_symlink(),
               "MISSING_OR_SYMLINKED_SOURCE_FILE")
        content = sourcefile.read_text(encoding="utf-8") if override is None else (
            override.get(path, sourcefile.read_text(encoding="utf-8"))
        )
        for index, expr in enumerate(MARKERS[path]):
            demand(re.search(expr, content, re.IGNORECASE | re.DOTALL) is not None,
                   "CANONICAL_CONTRACT_DRIFT_" + "_".join(path.split("/")).upper() +
                   "_" + str(index))
        results[path] = len(MARKERS[path])
    return results


def negative_tests(spec, original_sources, root):
    t = copy.deepcopy(spec)
    t["per_cycle_unknowns"]["opening_headcount"] = 1000
    try:
        validate_spec(t)
    except ValueError:
        pass
    else:
        raise ValueError("FABRICATED_POPULATION_ACCEPTED")
    t = copy.deepcopy(spec)
    t["gate_status"]["DATABASE_IMPORT"] = "AUTHORIZED"
    try:
        validate_spec(t)
    except ValueError:
        pass
    else:
        raise ValueError("UNAUTHORIZED_IMPORT_ACCEPTED")
    t = copy.deepcopy(spec)
    t["gate_status"]["D02_LAYER_BASELINE"] = "APPROVED"
    try:
        validate_spec(t)
    except ValueError:
        pass
    else:
        raise ValueError("UNAUTHORIZED_D02_APPROVAL")
    tampered = dict(original_sources)
    tampered["lib/poultry_cycle_onboarding.php"] = re.sub(
        "purchased_point_of_lay", "not_a_real_pol_type",
        tampered["lib/poultry_cycle_onboarding.php"]
    )
    try:
        inspect_sources(root, tampered)
    except ValueError:
        pass
    else:
        raise ValueError("POL_SOURCE_TAMPER_ACCEPTED")


def main():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument("--dry-run", action="store_true", required=True)
    p.add_argument("--database", required=True)
    p.add_argument("--farm-slug", required=True)
    p.add_argument("--legacy-year", required=True, type=int)
    p.add_argument("--as-of", required=True)
    p.add_argument("--reporting-year", required=True, type=int)
    p.add_argument("--certified-source-root", required=True)
    args = p.parse_args()
    if (args.database,args.farm_slug,args.legacy_year) != (
        "renee_c137_gc24_http","c137-gc24-synthetic",2026
    ) or (args.as_of,args.reporting_year) != ("2026-10-11",2025) or (
        args.certified_source_root != str(SOURCE)
    ):
        p.error("K1_D02_SCOPE_GUARD_BLOCKED")
    static_no_writes()
    confirm_prior()
    spec = json.loads(SPEC.read_text(encoding="utf-8"))
    validate_spec(spec)
    source = inspect_sources(SOURCE)
    source_text = {path:(SOURCE / path).read_text(encoding="utf-8")
                   for path in PATHS}
    negative_tests(spec, source_text, SOURCE)
    print("K1_D02_STATIC_READONLY=PASS")
    print("K1_D01_SELECTED_2025_GUARD=PASS")
    print("K1_D02_SOURCE_PROBES=PASS")
    print("K1_D02_NEGATIVE_TESTS=PASS")
    print("K1_D02_SOURCE_FILES=" + str(len(source)))
    print("K1_D02_SOURCE_CONTRACT_PROBES=" + str(sum(source.values())))
    print("K1_D02_UNRESOLVED_INPUT_FIELDS=" + str(len(spec["per_cycle_unknowns"])))
    print("K1_D02_APPROVAL=NOT_GRANTED")
    print("K1_D02_REPORTING_YEAR=2025")
    print("K1_D02_DATE_POLICY=UNRESOLVED")
    for scenario in ("R1","R2"):
        data = {
            "scenario":scenario,
            "d02_specification":spec,
            "d01_decision_digest":D01_SPEC[scenario],
        }
        digest = hashlib.sha256(json.dumps(
            data, sort_keys=True, separators=(",",":")
        ).encode()).hexdigest()
        print(scenario + "_K1_D02_SPEC_SHA256=" + digest)
        print(scenario + "_ORIGINAL_2026_EVENT_DIGEST=UNCHANGED")
        print(scenario + "_NEW_V2_EVENT_DIGEST=NOT_GENERATED")
    print("DATABASE_CONNECTIONS=0")
    print("DATABASE_WRITES=0")
    print("SOURCE_WRITES=0")
    print("CANONICAL_PHP_EXECUTED=NO")
    print("V2_EVENTS_GENERATED=NO")
    print("NEW_LOAD_TESTS=0")
    print("IMPORT_EXECUTION_ENABLED=NO")
    print("GC24CG_K1_D02_OFFLINE_VERIFIER=PASS")


if __name__ == "__main__":
    main()
