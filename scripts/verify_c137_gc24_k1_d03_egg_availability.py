#!/usr/bin/env python3
"""Offline K1/D03 source-grounded egg-pool requirements; never builds fixtures."""
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
SPEC = ROOT / "docs" / "C137_GC24_K1_D03_LAYER_EGG_AVAILABILITY.json"
PRIOR_D02 = ROOT / "docs" / "C137_GC24_K1_D02_LAYER_BASELINE_REQUIREMENTS.json"
PRIOR_D01 = ROOT / "docs" / "C137_GC24_K1_D01_2025_SELECTION.json"
CERT = "322438870263825f9e739a12c4bd478983e67141"
PARENT = "5e1d1e403c279a84418e9ff29d11aa5039baef92"
D02_BLOB = "3ded482cf99d9e96cc8806b7e8ebbb02672a7078"
D01_BLOB = "db5ded90c63f35a59861e4e8a58ed07a0628b0db"
D02_DIGEST = {
    "R1": "c3c63b0c41d739f43ecdcb738b8e91089c1d354768ce4aacf927cc3c72dafb80",
    "R2": "28528c2853db293b3210c54ca32447731699120d34386ba210669ca304391fe9",
}
D01_DIGEST = {
    "R1": "abf6ab785d80eef17af38cc36b036737b2ea08024b550a1202773964ca49dff8",
    "R2": "53b84e596c910473471b21926e634cb13d52e45bfe45c515b21e50dc0ff12091",
}
ORIGINAL = {
    "R1": "e31421ee40ce25b16feb698313fcf337e74126a8e63d8c5dadeedf74e30165f2",
    "R2": "985341ed4315788ee8f3b9d62b327da66d38fc63ea16d406c5c52bc9949d3dfe",
}
PATHS = (
    "poultry/layers_daily_record.php",
    "lib/daily_population_continuity.php",
    "lib/layer_egg_inventory.php",
    "lib/sales_allocation.php",
    "management/sales_records.php",
    "lib/daily_feed_sync.php",
    "lib/sales_units.php",
)
MARKERS = {
    PATHS[0]: (
        r"daily_population_continuity_expected_opening\s*\(",
        r"\$mortality\s*>\s*\$openingStock",
        r"\$eggProduction\s*>\s*\$openingStock",
        r"\$calculatedLayingRate\s*=\s*round\s*\(",
        r"\$calculatedCrates\s*=\s*round\s*\(\s*\$eggProduction\s*/\s*30\s*,\s*2\s*\)",
        r"sync_daily_feed_usage\s*\(",
        r"daily_population_sync_mortality\s*\(",
        r"sales_rebuild_layer_egg_allocations\s*\(",
    ),
    PATHS[1]: (
        r"function\s+daily_population_continuity_expected_opening\s*\(",
        r"\$eggProduction\s*>\s*\$expectedOpening",
        r"\$newLayingRate\s*=\s*round\s*\(",
    ),
    PATHS[2]: (
        r"LAYER_EGGS_PER_CRATE'\s*,\s*30\.0",
        r"function\s+layer_egg_sale_quantity_crates\s*\(",
        r"function\s+layer_egg_pool_before_sale\s*\(",
        r"ldr\.record_date<=\?",
        r"sale_date\s*<\s*\?\s+OR\s*\(\s*sale_date=\?\s+AND\s+id\s*<\s*\?\s*\)",
        r"ORDER\s+BY\s+sale_date\s*,\s*id",
        r"sales_allocations\s+WHERE\s+farm_id",
        r"\$directCycle\s*>\s*0",
        r"allocated_quantity",
        r"round\s*\(\s*max\s*\(\s*0\.0",
    ),
    PATHS[3]: (
        r"function\s+sales_auto_allocate_layer_egg\s*\(",
        r"layer_egg_sale_quantity_crates\s*\(",
        r"layer_egg_pool_before_sale\s*\(",
        r"\$saleQty\s*>\s*\$available\s*\+\s*0\.01",
        r"'status'\s*=>\s*'unallocated'",
        r"'layer_unsold_egg_pool'",
        r"function\s+sales_refresh_automatic_allocation\s*\(",
        r"if\s*\(\s*\$isDirect\s*\)",
        r"function\s+sales_rebuild_layer_egg_allocations\s*\(",
        r"ORDER\s+BY\s+sale_date\s*,\s*id",
    ),
    PATHS[4]: (
        r"sales_refresh_automatic_allocation\s*\(",
        r"sales_rebuild_layer_egg_allocations\s*\(",
        r"Sale was saved, but its shared Layer revenue could not yet be assigned",
        r"Sale was updated, but its shared Layer revenue could not yet be assigned",
        r"min\s*\(\s*\$oldDate\s*,",
    ),
    PATHS[5]: (
        r"function\s+sync_daily_feed_usage\s*\(",
        r"daily_feed_sync_authoritative_cycle_guard\s*\(",
        r"stock_reverse_transaction\s*\(",
        r"stock_apply_movement\s*\(",
    ),
    PATHS[6]: (
        r"function\s+sales_unit_from_post\s*\(",
        r"function\s+sales_unit_presets\s*\(",
    ),
}

def demand(ok, reason):
    if not ok:
        raise ValueError(reason)

def blob_sha(raw):
    return hashlib.sha1(b"blob " + str(len(raw)).encode("ascii") + bytes([0]) + raw).hexdigest()

def static_readonly():
    tree = ast.parse(Path(__file__).read_text(encoding="utf-8"))
    allow = {"argparse","ast","copy","datetime","hashlib","json","re","sys","pathlib"}
    unsafe = {"connect","execute","executemany","commit","rollback",
              "write","write_bytes","write_text","mkdir","unlink","rename",
              "rmdir","remove","popen","Popen","system","urlopen","request"}
    for node in ast.walk(tree):
        if isinstance(node, ast.Import):
            demand(all(n.name in allow for n in node.names), "UNSAFE_IMPORT")
        elif isinstance(node, ast.ImportFrom):
            demand(node.module in allow, "UNSAFE_IMPORT")
        elif isinstance(node, ast.Call):
            f = node.func
            name = f.id if isinstance(f, ast.Name) else (
                f.attr if isinstance(f, ast.Attribute) else "")
            demand(name not in unsafe, "UNSAFE_IO_CALL")

def verify_earlier():
    d01_bytes = PRIOR_D01.read_bytes()
    d02_bytes = PRIOR_D02.read_bytes()
    demand(blob_sha(d01_bytes) == D01_BLOB, "D01_BLOB_DRIFT")
    demand(blob_sha(d02_bytes) == D02_BLOB, "D02_BLOB_DRIFT")
    d01 = json.loads(d01_bytes)
    d02 = json.loads(d02_bytes)
    demand(d01["selected_year"] == 2025 and d01["new_events"] == "NOT_GENERATED",
           "D01_YEAR_OR_EVENT_DRIFT")
    demand(d02["status"] == "REQUIREMENTS_CAPTURED_BIOLOGICAL_INPUTS_UNAPPROVED" and
           all(value is None for value in d02["per_cycle_unknowns"].values()),
           "D02_BIOLOGY_APPROVED_WITHOUT_OWNER")
    for scenario in ("R1", "R2"):
        payload = {
            "scenario": scenario,
            "d02_specification": d02,
            "d01_decision_digest": D01_DIGEST[scenario],
        }
        computed = hashlib.sha256(json.dumps(
            payload, sort_keys=True, separators=(",", ":")
        ).encode()).hexdigest()
        demand(computed == D02_DIGEST[scenario], "D02_SPEC_DIGEST_DRIFT")

def validate_spec(spec):
    demand(spec["version"] == "C137-GC24-K1-D03-LAYER-EGG-AVAILABILITY-0" and
           spec["checkpoint"] == "GC24CG_K1_D03" and
           spec["readiness"] == "REQUIREMENTS_REVIEW_ONLY_EGG_PRODUCTION_UNAPPROVED" and
           spec["approved_scope"] == "OFFLINE_DESIGN_REVIEW_ONLY",
           "D03_SCOPE_DRIFT")
    win = spec["reporting_window"]
    a, b = datetime.date.fromisoformat(win["start"]), datetime.date.fromisoformat(win["end"])
    demand((win["year"], str(a), str(b), win["days"]) ==
           (2025, "2025-01-01", "2025-12-31", 365) and
           (b-a).days + 1 == 365 and
           win["technical_event_date_rules"] == "UNRESOLVED",
           "REPORTING_DATE_DRIFT")
    demand(spec["preparation_parent_head"] == PARENT and
           spec["certified_reports_head"] == CERT and
           spec["d02_requirements_git_blob"] == D02_BLOB and
           spec["d02_spec_digests"] == D02_DIGEST and
           spec["d01_year_selection_git_blob"] == D01_BLOB and
           spec["original_2026_event_digests"] == ORIGINAL,
           "SPEC_PROVENANCE_DRIFT")
    actual = spec["production_model_unknowns"]
    demand(len(actual) >= 20 and all(v is None for v in actual.values()),
           "FABRICATED_LAYER_PRODUCTION_VALUES")
    contracts = spec["source_contracts"]
    demand(len(contracts) == len(PATHS) and
           [c["file"] for c in contracts] == list(PATHS),
           "CANONICAL_SOURCE_CONTRACT_SCOPE_DRIFT")
    u = spec["explicit_unit_policy"]
    demand(u["eggs_per_crate"] == 30 and
           u["daily_crates_stored_precision_decimals"] == 2 and
           u["egg_pool_summary_precision_decimals"] == 4 and
           u["shared_auto_sale_tolerance_crates"] == 0.01 and
           "" in u["convertible_sale_units"] and
           "dozens" in u["convertible_sale_units"] and
           u["unconvertible_units"] == "UNALLOCATED_NOT_GUESSED" and
           u["precision_reconciliation"] == "PENDING_NO_NEW_PRECISION_POLICY_INVENTED",
           "CANONICAL_EGG_UNIT_POLICY_DRIFT")
    critical = set(spec["critical_distinctions"])
    required = {
        "same_date_production_is_included_in_pool_even_if_sale_was_posted_first",
        "sale_order_uses_business_date_then_sale_id_not_entry_timestamp",
        "insufficient_shared_pool_returns_unallocated_it_does_not_automatically_reject_financial_sale",
        "direct_sale_allocation_status_does_not_prove_date_matched_physical_egg_stock",
        "clamped_negative_available_crates_can_mask_historical_oversale_in_returned_pool",
        "stored_two_decimal_crates_cannot_be_treated_as_exact_raw_egg_counts_without_rounding_policy",
        "egg_counts_flock_and_lay_rates_cannot_be_reverse_engineered_from_sale_targets",
    }
    demand(required.issubset(critical) and len(critical) >= 14,
           "CRITICAL_EGG_AVAILABILITY_GUARD_MISSING")
    demand(len(spec["future_fixture_conditions"]) >= 12,
           "EGG_AVAILABILITY_VALIDATION_GAPS")
    demand(spec["unapproved_decisions"] ==
           ["D%02d" % i for i in range(2,13)],
           "DOWNSTREAM_DECISION_APPROVAL_INVENTED")
    demand(spec["egg_pool_source"] ==
           "DERIVED_FROM_DATED_LAYER_DAILY_RECORDS_AND_PRIOR_SALES_NOT_INDEPENDENT_LEDGER",
           "INDEPENDENT_EGG_LEDGER_INVENTED")
    demand(spec["legacy_sales_only_not_production"] ==
           {"R1":{"egg_crates_sold":16419,"max_daily_crates_sold":54},
            "R2":{"egg_crates_sold":49269,"max_daily_crates_sold":144}},
           "LEGACY_SALES_BECAME_PRODUCTION")
    p=spec["permissions"]
    demand(p["allow_documentation_only"] is True and
           p["load_150_vu"] == "HOLD" and p["load_200_vu"] == "PROHIBITED",
           "UNAUTHORIZED_CAPACITY_OR_DOC_SCOPE")
    for field in ("choose_lay_rate","choose_bird_headcount",
                  "choose_daily_egg_production","generate_v2_events",
                  "convert_original_2026_events","run_canonical_php",
                  "modify_certified_source","modify_previous_decision_specs",
                  "access_database","write_database","deploy_or_import",
                  "start_load_tests"):
        demand(p[field] is False, "UNAUTHORIZED_" + field.upper())

def inspect_source(root, tampered=None):
    demand(root == SOURCE and root.is_dir() and not root.is_symlink(),
           "CERTIFIED_SOURCE_ROOT_BLOCKED")
    marker_count=0
    for path in PATHS:
        f = root / path
        demand(f.is_file() and not f.is_symlink(), "CERTIFIED_SOURCE_FILE_GUARD")
        src = (tampered or {}).get(path)
        if src is None:
            src = f.read_text(encoding="utf-8")
        for i, pattern in enumerate(MARKERS[path]):
            demand(re.search(pattern, src, re.IGNORECASE | re.DOTALL) is not None,
                   "SOURCE_CONTRACT_DRIFT_" + str(PATHS.index(path)) +
                   "_" + str(i))
            marker_count += 1
    return marker_count

def negative_tests(spec, root):
    for field, value in (
        ("daily_egg_production_count", 1200),
        ("daily_laying_rate_percent", 80.0),
    ):
        t = copy.deepcopy(spec)
        t["production_model_unknowns"][field] = value
        try:
            validate_spec(t)
        except ValueError:
            pass
        else:
            raise ValueError("FABRICATED_EGG_PRODUCTION_ACCEPTED")
    t = copy.deepcopy(spec)
    t["permissions"]["write_database"] = True
    try:
        validate_spec(t)
    except ValueError:
        pass
    else:
        raise ValueError("DATABASE_APPROVAL_TAMPER_ACCEPTED")
    t = copy.deepcopy(spec)
    t["critical_distinctions"] = [
        x for x in t["critical_distinctions"]
        if x != "insufficient_shared_pool_returns_unallocated_it_does_not_automatically_reject_financial_sale"
    ]
    try:
        validate_spec(t)
    except ValueError:
        pass
    else:
        raise ValueError("FINANCIAL_SALE_UNALLOCATED_WARNING_LOST")
    text = (root / "lib/sales_allocation.php").read_text(encoding="utf-8")
    bad = {"lib/sales_allocation.php": text.replace(
        "$saleQty > $available + 0.01", "$saleQty > $available + 50.0"
    )}
    demand(bad["lib/sales_allocation.php"] != text, "TAMPER_NOT_APPLIED")
    try:
        inspect_source(root, bad)
    except ValueError:
        pass
    else:
        raise ValueError("CANONICAL_TOLERANCE_TAMPER_ACCEPTED")

def main():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument("--dry-run", action="store_true", required=True)
    p.add_argument("--database", required=True)
    p.add_argument("--farm-slug", required=True)
    p.add_argument("--legacy-year", type=int, required=True)
    p.add_argument("--as-of", required=True)
    p.add_argument("--reporting-year", type=int, required=True)
    p.add_argument("--certified-source-root", required=True)
    args=p.parse_args()
    if (args.database, args.farm_slug, args.legacy_year) != (
        "renee_c137_gc24_http","c137-gc24-synthetic",2026
    ) or (args.as_of,args.reporting_year) != ("2026-10-11",2025) or (
        args.certified_source_root != str(SOURCE)
    ):
        p.error("K1_D03_SCOPE_GUARD_BLOCKED")
    static_readonly()
    verify_earlier()
    spec = json.loads(SPEC.read_text(encoding="utf-8"))
    validate_spec(spec)
    source_probes = inspect_source(SOURCE)
    negative_tests(spec, SOURCE)
    print("K1_D03_STATIC_READONLY=PASS")
    print("K1_D02_IMMUTABLE_LINEAGE=PASS")
    print("K1_D03_CANONICAL_SOURCE_PROBES=PASS")
    print("K1_D03_NEGATIVE_TESTS=PASS")
    print("K1_D03_SOURCE_FILES="+str(len(PATHS)))
    print("K1_D03_SOURCE_PROBE_COUNT="+str(source_probes))
    print("K1_D03_UNRESOLVED_INPUT_FIELDS="+str(len(spec["production_model_unknowns"])))
    print("K1_D03_YEAR=2025")
    print("K1_D03_EGG_PRODUCTION_APPROVAL=NOT_GRANTED")
    print("K1_D03_EGG_POOL_VALIDITY=UNPROVEN")
    for scenario in ("R1","R2"):
        payload = {"scenario":scenario, "specification":spec,
                   "prior_d02_spec_sha256":D02_DIGEST[scenario]}
        value = hashlib.sha256(json.dumps(
            payload,sort_keys=True,separators=(",",":")
        ).encode()).hexdigest()
        print(scenario+"_D03_SPEC_SHA256="+value)
        print(scenario+"_ORIGINAL_2026_EVENT_DIGEST=UNCHANGED")
        print(scenario+"_V2_EVENT_DIGEST=NOT_GENERATED")
    print("CERTIFIED_SOURCE_READ_ONLY=YES")
    print("CANONICAL_PHP_EXECUTED=NO")
    print("DATABASE_CONNECTIONS=0")
    print("DATABASE_WRITES=0")
    print("SOURCE_WRITES=0")
    print("NEW_V2_EVENTS_GENERATED=NO")
    print("NEW_LOAD_TESTS=0")
    print("IMPORT_EXECUTION_ENABLED=NO")
    print("GC24CG_K1_D03_OFFLINE_VERIFIER=PASS")

if __name__ == "__main__":
    main()
