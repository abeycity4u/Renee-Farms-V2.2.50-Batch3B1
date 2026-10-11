#!/usr/bin/env python3
"""K1/D04: read-only Broiler cohort and explicit sale-exit contract verifier.

No DB connection, PHP execution, file writes, network, or fixture generation.
Source checks are structural probes, NOT a proof of biological readiness.
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
SPEC_FILE = ROOT / "docs" / "C137_GC24_K1_D04_BROILER_COHORT_REQUIREMENTS.json"
D03_FILE = ROOT / "docs" / "C137_GC24_K1_D03_LAYER_EGG_AVAILABILITY.json"
CERT = "322438870263825f9e739a12c4bd478983e67141"
PARENT = "6da0d69d7b94bdb1dddb47d71b1f2cc8adfcfedb"
D03_BLOB = "8c02f1a6fd0eb30944a38a28adce8c15968ea0be"
D03_DIGEST = {
    "R1": "6d0e142f51c92c80d7031dedb3e47897d7b029d67f05c1095a62287e35268a44",
    "R2": "0a33b8f8cbe21dc49c7e24577141e5d837904fe2a3e3c6a35d86e5b6b539d6a8",
}
D02_DIGEST = {
    "R1": "c3c63b0c41d739f43ecdcb738b8e91089c1d354768ce4aacf927cc3c72dafb80",
    "R2": "28528c2853db293b3210c54ca32447731699120d34386ba210669ca304391fe9",
}
ORIGINAL_DIGEST = {
    "R1": "e31421ee40ce25b16feb698313fcf337e74126a8e63d8c5dadeedf74e30165f2",
    "R2": "985341ed4315788ee8f3b9d62b327da66d38fc63ea16d406c5c52bc9949d3dfe",
}
FILES = (
    "lib/production_cycle_service.php",
    "lib/poultry_cycle_onboarding.php",
    "lib/poultry_cycle_acquisition.php",
    "lib/poultry_cycle_lifecycle.php",
    "poultry/broiler_daily_record.php",
    "lib/daily_population_continuity.php",
    "lib/daily_population_sync.php",
    "lib/sale_population_effects.php",
    "lib/production_population.php",
    "lib/daily_feed_sync.php",
    "management/sales_records.php",
)
REQUIRED = {
    "financial_sale_does_not_automatically_remove_live_broiler_population",
    "physical_sale_exit_requires_explicit_remove_live_population_mode_and_cycle_whole_headcount",
    "multi_cycle_physical_exit_requires_financial_shared_cycle_attribution",
    "canonical_population_never_below_zero_at_sale_date_or_later_projected_dates",
    "slaughter_is_separate_canonical_physical_movement_not_auto_inferred_from_head_sales",
    "broiler_daily_record_requires_positive_opening_and_age_and_mortality_lte_opening",
}
class Blocked(ValueError):
    pass

def demand(ok, label):
    if not ok:
        raise Blocked(label)

def compact(value):
    return re.sub(r"\s+", "", value).lower()

def git_blob_sha(raw):
    return hashlib.sha1(
        b"blob " + str(len(raw)).encode("ascii") + bytes([0]) + raw
    ).hexdigest()

def ensure_no_writer_calls():
    parsed = ast.parse(Path(__file__).read_text(encoding="utf-8"))
    safe = {"argparse", "ast", "copy", "datetime", "hashlib", "json",
            "re", "sys", "pathlib"}
    denied = {"connect", "execute", "executemany", "commit", "rollback",
              "write", "write_text", "write_bytes", "unlink", "rename",
              "mkdir", "rmdir", "remove", "system", "popen", "Popen",
              "urlopen", "request", "eval", "exec"}
    for item in ast.walk(parsed):
        if isinstance(item, ast.Import):
            demand(all(i.name in safe for i in item.names), "UNSAFE_MODULE_IMPORT")
        elif isinstance(item, ast.ImportFrom):
            demand(item.module in safe, "UNSAFE_MODULE_IMPORT")
        elif isinstance(item, ast.Call):
            f = item.func
            method = f.id if isinstance(f, ast.Name) else (
                f.attr if isinstance(f, ast.Attribute) else ""
            )
            demand(method not in denied, "UNSAFE_PYTHON_IO")

def verify_prior_d03():
    raw = D03_FILE.read_bytes()
    demand(git_blob_sha(raw) == D03_BLOB, "PRIOR_D03_BLOB_DRIFT")
    p = json.loads(raw)
    demand(p["version"] == "C137-GC24-K1-D03-LAYER-EGG-AVAILABILITY-0",
           "D03_SCOPE_DRIFT")
    demand(p["readiness"] == "REQUIREMENTS_REVIEW_ONLY_EGG_PRODUCTION_UNAPPROVED",
           "D03_BIOLOGY_FALSELY_APPROVED")
    for scenario in ("R1", "R2"):
        payload = {"scenario": scenario, "specification": p,
                   "prior_d02_spec_sha256": D02_DIGEST[scenario]}
        digest = hashlib.sha256(json.dumps(
            payload, sort_keys=True, separators=(",", ":")
        ).encode()).hexdigest()
        demand(digest == D03_DIGEST[scenario], "D03_DIGEST_DRIFT")

def validate_spec(doc):
    demand(doc["version"] == "C137-GC24-K1-D04-BROILER-COHORT-CONTRACT-0" and
           doc["checkpoint"] == "GC24CG_K1_D04" and
           doc["scope"] == "READONLY_REQUIREMENTS_ONLY" and
           doc["readiness"] == "D04_COHORT_AND_EXIT_EVIDENCE_UNAPPROVED",
           "D04_SCOPE_FALSELY_READY")
    yr = doc["reporting_year_selection"]
    a = datetime.date.fromisoformat(yr["start_date"])
    b = datetime.date.fromisoformat(yr["end_date"])
    demand((yr["year"], str(a), str(b), (b - a).days + 1,
            yr["scope"]) ==
           (2025, "2025-01-01", "2025-12-31", 365,
            "DESIGN_ONLY_DATE_RULES_PENDING"), "REPORTING_PERIOD_DRIFT")
    demand(doc["planning_as_of"] == "2026-10-11" and
           doc["previous_preparation_head"] == PARENT and
           doc["certified_reports_source_head"] == CERT and
           doc["prior_d03_spec_file_git_blob"] == D03_BLOB and
           doc["prior_d03_spec_digests"] == D03_DIGEST and
           doc["prior_d02_spec_digests"] == D02_DIGEST and
           doc["original_2026_event_digest_sha256"] == ORIGINAL_DIGEST,
           "PROVENANCE_DRIFT")
    demand(doc["scenario_broiler_cycle_count"] is None,
           "BROILER_CYCLE_COUNT_FABRICATED")
    inputs = doc["per_cohort_unknowns"]
    demand(len(inputs) >= 35 and all(v is None for v in inputs.values()),
           "BROILER_BIOLOGY_OR_PRICE_FABRICATED")
    legacy = doc["legacy_sales_not_population_proof"]
    demand(legacy["R1"] == {"broiler_head_sales": 5469, "broiler_feed_used_events": 0}
           and legacy["R2"] == {"broiler_head_sales": 16422, "broiler_feed_used_events": 0}
           and legacy["original_direct_broiler_sales"] ==
           "broiler-a_only_broiler-b_unassigned" and
           legacy["implication"] == "NO_EXIT_OR_COHORT_PROVEN",
           "LEGACY_VOLUME_FALSELY_PROVEN_AS_POPULATION")
    contracts = doc["certified_source_contracts"]
    demand(len(contracts) == len(FILES) and
           [c["file"] for c in contracts] == list(FILES) and
           [c["id"] for c in contracts] ==
           ["S%02d" % i for i in range(1, len(FILES) + 1)] and
           all(len(c["markers"]) >= 2 for c in contracts),
           "SOURCE_SCOPE_DRIFT")
    rules = set(doc["observed_canonical_rules"])
    demand(REQUIRED.issubset(rules) and len(rules) >= 18,
           "PHYSICAL_EXIT_CONTRACT_MISSING")
    checks = set(doc["required_future_V2_fixture_proofs"])
    demand(len(checks) >= 18 and
           "feed_prices_and_stock_receipts_deferred_to_D05_avoid_fabricating_broiler_feed" in checks and
           "no_assumption_financial_broiler_head_sale_equals_physical_exit" in checks and
           "dated_daily_mortality_and_population_continuity_before_after_exits" in checks and
           "regression_for_shared_sale_multi_cycle_exit_and_direct_sale_same_cycle_guard" in checks,
           "COHORT_TEST_PLAN_MISSING")
    gates = doc["operational_gates"]
    expected = {
        "D01_2025_YEAR": "SELECTED_DESIGN_ONLY",
        "D01_2025_EVENT_DATE_POLICY": "PENDING",
        "D02_LAYER_BIOLOGY": "UNAPPROVED",
        "D03_LAYER_EGG_POOL": "UNPROVEN",
        "D04_BROILER_COHORTS": "UNAPPROVED",
        "D05_BROILER_FEED_COSTS": "UNAPPROVED",
        "D06_TO_D12": "UNAPPROVED",
        "NEW_V2_EVENT_STREAMS": "NOT_GENERATED",
        "APPLICATION_PHP_RUNTIME": "NOT_EXECUTED",
        "CANONICAL_SOURCE_MUTATION": "FORBIDDEN",
        "DATABASE_MUTATION": "PROHIBITED",
        "REPORTS_150_VU": "HOLD",
        "REPORTS_200_VU": "PROHIBITED",
    }
    demand(gates == expected, "GATE_MODIFICATION_UNAUTHORIZED")
    auth = doc["authorization"]
    demand(auth["docs_and_offline_verifier_only"] is True, "DOC_SCOPE_INVALID")
    demand(all(auth[k] is False for k in (
        "set_bird_numbers", "set_age_or_harvest_schedule",
        "map_legacy_sales_to_exits", "invent_feed", "generate_v2_events",
        "modify_original_events", "modify_certified_source",
        "change_php_writers", "execute_php", "connect_to_database",
        "write_to_database", "deploy", "import", "load_test"
    )), "UNAUTHORIZED_IMPLEMENTATION")

def inspect_sources(doc, changed=None):
    demand(SOURCE.is_dir() and not SOURCE.is_symlink(),
           "CERTIFIED_SOURCE_ROOT_NOT_READY")
    total = 0
    for rec in doc["certified_source_contracts"]:
        path = rec["file"]
        demand(path in FILES, "UNAPPROVED_SOURCE_PATH")
        file = SOURCE / path
        demand(file.is_file() and not file.is_symlink(),
               "CERTIFIED_SOURCE_FILE_NOT_READY")
        source = file.read_text(encoding="utf-8")
        if changed and path in changed:
            source = changed[path]
        condensed = compact(source)
        for n, marker in enumerate(rec["markers"]):
            demand(compact(marker) in condensed,
                   "SOURCE_MARKER_DRIFT_" + str(FILES.index(path)) +
                   "_" + str(n))
            total += 1
    return total

def negative_tests(doc):
    for field, value in (
        ("opening_bird_headcount", 3000),
        ("harvest_phase_transition_date", "2025-06-01"),
        ("feed_quantity_per_day", 125.0),
    ):
        bad = copy.deepcopy(doc)
        bad["per_cohort_unknowns"][field] = value
        try:
            validate_spec(bad)
        except Blocked:
            pass
        else:
            raise Blocked("UNAPPROVED_INPUT_ACCEPTED")
    bad = copy.deepcopy(doc)
    bad["authorization"]["write_to_database"] = True
    try:
        validate_spec(bad)
    except Blocked:
        pass
    else:
        raise Blocked("DATABASE_APPROVAL_ACCEPTED")
    bad = copy.deepcopy(doc)
    bad["observed_canonical_rules"] = [
        r for r in bad["observed_canonical_rules"]
        if r != "financial_sale_does_not_automatically_remove_live_broiler_population"
    ]
    try:
        validate_spec(bad)
    except Blocked:
        pass
    else:
        raise Blocked("FINANCIAL_VS_PHYSICAL_GUARD_LOST")
    path = "lib/sale_population_effects.php"
    src = (SOURCE / path).read_text(encoding="utf-8")
    needle = "remove_live_population"
    demand(needle in src, "CANONICAL_SOURCE_SAMPLE_MISSING")
    tampered = "".join(src.split(needle))
    try:
        inspect_sources(doc, {path: tampered})
    except Blocked:
        pass
    else:
        raise Blocked("POPULATION_EFFECT_TAMPER_ACCEPTED")

def main():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument("--dry-run", action="store_true", required=True)
    p.add_argument("--database", required=True)
    p.add_argument("--farm-slug", required=True)
    p.add_argument("--legacy-year", type=int, required=True)
    p.add_argument("--as-of", required=True)
    p.add_argument("--reporting-year", type=int, required=True)
    p.add_argument("--certified-source-root", required=True)
    args = p.parse_args()
    if (args.database, args.farm_slug, args.legacy_year) != (
        "renee_c137_gc24_http", "c137-gc24-synthetic", 2026
    ) or (args.as_of, args.reporting_year) != (
        "2026-10-11", 2025
    ) or args.certified_source_root != str(SOURCE):
        p.error("K1_D04_SCOPE_GUARD_BLOCKED")
    ensure_no_writer_calls()
    verify_prior_d03()
    doc = json.loads(SPEC_FILE.read_text(encoding="utf-8"))
    validate_spec(doc)
    probes = inspect_sources(doc)
    negative_tests(doc)
    print("K1_D04_STATIC_READ_ONLY=PASS")
    print("K1_D03_IMMUTABLE_LINEAGE=PASS")
    print("K1_D04_SOURCE_MARKERS=PASS")
    print("K1_D04_NEGATIVE_TESTS=PASS")
    print("K1_D04_CERTIFIED_SOURCE_FILES="+str(len(FILES)))
    print("K1_D04_SOURCE_PROBE_COUNT="+str(probes))
    print("K1_D04_UNRESOLVED_COHORT_INPUTS="+str(len(doc["per_cohort_unknowns"])))
    print("K1_D04_YEAR=2025")
    print("K1_D04_COHORT_APPROVAL=NOT_GRANTED")
    print("K1_D04_PHYSICAL_EXIT_PROOF=UNPROVEN")
    for scenario in ("R1", "R2"):
        v = {"scenario": scenario, "specification": doc,
             "prior_d03_spec_sha256": D03_DIGEST[scenario]}
        digest = hashlib.sha256(json.dumps(
            v, sort_keys=True, separators=(",", ":")
        ).encode()).hexdigest()
        print(scenario+"_D04_SPEC_SHA256="+digest)
        print(scenario+"_ORIGINAL_2026_EVENT_DIGEST=UNCHANGED")
        print(scenario+"_V2_EVENT_DIGEST=NOT_GENERATED")
    print("CERTIFIED_SOURCE_UNCHANGED=YES")
    print("PHP_EXECUTED=NO")
    print("DATABASE_CONNECTIONS=0")
    print("DATABASE_WRITES=0")
    print("SOURCE_WRITES=0")
    print("NEW_V2_EVENTS_GENERATED=NO")
    print("NEW_LOAD_TESTS=0")
    print("IMPORT_EXECUTION_ENABLED=NO")
    print("GC24CG_K1_D04_OFFLINE_VERIFIER=PASS")

if __name__ == "__main__":
    main()
