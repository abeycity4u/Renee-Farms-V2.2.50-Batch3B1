#!/usr/bin/env python3
"""Z27H offline scope, lineage, physiology and denial-path verification."""
import ast
import copy
import os
import subprocess
import sys
from pathlib import Path

sys.dont_write_bytecode = True

from c137_gc24_fixture_dryrun import DB, FARM, YEAR, build, SCENARIOS
from c137_gc24_fixture_import_preflight import CERTIFIED_DIGESTS, ImportPreflightError
from c137_gc24_fixture_v2_design import proposed_design
from c137_gc24_operational_feasibility import (
    EXECUTION_ENABLED, VERSION, SOURCE_CONTRACTS, DECISION_GATES,
    OPERATING_RULES, Z27F_SPEC_DIGESTS, analyze,
)

SCRIPT = Path(__file__).with_name("c137_gc24_operational_feasibility.py")
ALLOWED = {
    "argparse", "collections", "datetime", "hashlib", "json", "sys",
    "c137_gc24_fixture_dryrun", "c137_gc24_fixture_import_preflight",
    "c137_gc24_fixture_v2_design",
}
FORBIDDEN = {
    "open", "eval", "exec", "__import__", "system", "popen", "Popen",
    "run", "connect", "execute", "commit", "write", "write_text",
    "write_bytes", "unlink", "remove", "mkdir", "urlopen", "urlretrieve",
    "getenv", "environ", "fetch", "request",
}
EXPECTED = {
    "R1": {
        "eggs": 16419,
        "broilers": 5469,
        "events": 5452,
        "future": 1180,
        "direct_a": 312,
    },
    "R2": {
        "eggs": 49269,
        "broilers": 16422,
        "events": 13770,
        "future": 2994,
        "direct_a": 938,
    },
}


def require(value, message):
    if not value:
        raise AssertionError(message)


def reject(fn, code):
    try:
        fn()
    except (ValueError, ImportPreflightError):
        return
    raise AssertionError(code)


def options(scenario="R1", *, db=DB, farm=FARM, year=YEAR,
            as_of="2026-10-11", candidate=2025):
    return [
        "--dry-run", "--scenario", scenario, "--database", db,
        "--farm-slug", farm, "--year", str(year), "--as-of", as_of,
        "--candidate-year", str(candidate),
    ]


def command(args):
    env = dict(os.environ)
    env["PYTHONDONTWRITEBYTECODE"] = "1"
    return subprocess.run(
        [sys.executable, "-B", str(SCRIPT), *args],
        text=True, capture_output=True, timeout=120, env=env
    )


def main():
    syntax = ast.parse(SCRIPT.read_text(encoding="utf-8"))
    for node in ast.walk(syntax):
        if isinstance(node, ast.Import):
            require(all(s.name in ALLOWED for s in node.names), "UNSAFE_IMPORT")
        elif isinstance(node, ast.ImportFrom):
            require(node.module in ALLOWED, "UNSAFE_FROM_IMPORT")
        elif isinstance(node, ast.Call):
            f = node.func
            name = f.id if isinstance(f, ast.Name) else (
                f.attr if isinstance(f, ast.Attribute) else ""
            )
            require(name not in FORBIDDEN, "UNSAFE_CALL")
    require(EXECUTION_ENABLED is False, "EXECUTION_ENABLED")
    require(OPERATING_RULES["layers"]["assumed_lay_rate"] == "NONE",
            "INVENTED_LAY_RATE")
    require(OPERATING_RULES["layers"]["egg_allocation"] ==
            "automatic_unsold_pool_only_not_manual_60_40",
            "MANUAL_EGG_ALLOCATION")
    require(OPERATING_RULES["feed"]["no_duplication"] is True,
            "DUPLICATE_FEED_POLICY")
    require(OPERATING_RULES["broilers"]["physical_exits"] == "UNRESOLVED",
            "PRETEND_BROILER_EXITS")
    require(len(DECISION_GATES) >= 12, "LOST_CRITICAL_GATES")
    require(len(SOURCE_CONTRACTS) >= 10, "SOURCE_OWNERS_MISSING")
    print("STATIC_NO_IO=PASS")
    print("EXECUTION_HARD_DISABLED=PASS")

    for scenario in sorted(SCENARIOS):
        original = build(scenario, include_records=True)
        before = copy.deepcopy(original)
        x = analyze(scenario, original)
        v2 = proposed_design(scenario, original)
        require(original == before, "ORIGINAL_STREAM_MUTATED")
        require(x["legacy_event_digest_sha256"] == CERTIFIED_DIGESTS[scenario],
                "LEGACY_DIGEST_CHANGED")
        require(x["z27f_spec_digest_sha256"] == Z27F_SPEC_DIGESTS[scenario] ==
                v2["specification_digest_sha256"], "Z27F_DIGEST_CHANGED")
        require(x["legacy_events"] == EXPECTED[scenario]["events"],
                "EVENT_COUNT_CHANGED")
        require(x["egg_crates_sold"] == EXPECTED[scenario]["eggs"],
                "EGG_TOTAL_CHANGED")
        require(x["broiler_heads_sold"] == EXPECTED[scenario]["broilers"],
                "BROILER_TOTAL_CHANGED")
        require(x["egg_equivalent_count"] == x["egg_crates_sold"] * 30,
                "EGG_CRATE_UNIT_CHANGED")
        require(x["egg_sale_days"] == 365 and x["broiler_sale_days"] == 365,
                "DAILY_SALE_COVERAGE_CHANGED")
        require(x["future_events_at_pinned_as_of"] ==
                EXPECTED[scenario]["future"], "FUTURE_EXPOSURE_CHANGED")
        require(x["future_receipts_at_pinned_as_of"] == 4,
                "FUTURE_RECEIPTS_CHANGED")
        require(x["broiler_direct_a_sales"] ==
                EXPECTED[scenario]["direct_a"] and
                x["broiler_direct_b_sales"] == 0,
                "BROILER_DISTRIBUTION_CHANGED")
        require(x["broiler_feed_used_rows"] == 0,
                "BROILER_FEED_ALREADY_MODELED")
        require(x["layer_feed_used_rows"] > 0,
                "LAYER_FEED_LOST")
        require(x["candidate_historical_year"] == 2025 and
                x["candidate_year_selected"] is False,
                "HISTORICAL_YEAR_CHOSEN")
        require(x["new_event_count"] == 0 and
                x["new_event_digest_sha256"] == "NOT_GENERATED",
                "IMPLIED_NEW_EVENTS")
        require(x["legacy_date_rewrites"] == 0 and
                x["readiness"] == "FEASIBILITY_DESIGN_BLOCKED",
                "REWRITTEN_INPUT")
        require(len(x["feasibility_specification_digest_sha256"]) == 64,
                "SPEC_DIGEST_MISSING")
        cli = command(options(scenario))
        require(cli.returncode == 0 and
                "GC24CG_Z27H_OFFLINE_FEASIBILITY=PASS" in cli.stdout,
                "CLI_FAIL")
        require("DATABASE_CONNECTIONS=0" in cli.stdout and
                "DATABASE_WRITES=0" in cli.stdout and
                "V2_EVENTS_GENERATED=NO" in cli.stdout and
                "READINESS=FEASIBILITY_DESIGN_BLOCKED" in cli.stdout,
                "CLI_UNSAFE")
        print("SCENARIO_" + scenario + "_LINEAGE=PASS")
        print("SCENARIO_" + scenario + "_DAILY_FEASIBILITY=PASS")
        print("SCENARIO_" + scenario + "_DIGEST=" +
              x["feasibility_specification_digest_sha256"])

    bad = build("R1", include_records=True)
    bad["farm_slug"] = "another-tenant"
    reject(lambda: analyze("R1", bad), "TENANT_SCOPE_BROKEN")
    bad = build("R1", include_records=True)
    bad["records"][0]["status"] = "active"
    reject(lambda: analyze("R1", bad), "EVENT_TAMPER_ACCEPTED")
    reject(lambda: analyze("R1", build("R1", include_records=True),
                           candidate_year=2024), "BAD_CANDIDATE_ACCEPTED")
    for opts in (
        options(db="renee_c137_fixture"),
        options(db="reneefarms_prod"),
        options(farm="wrong-farm"),
        options(year=2025),
        options(as_of="2026-12-31"),
        options(candidate=2024),
        options() + ["--execute"],
        [x for x in options() if x != "--dry-run"],
    ):
        require(command(opts).returncode != 0, "UNSAFE_CLI_ACCEPTED")
    print("REJECTION_GUARDS=PASS")
    print("LEGACY_STREAM_CHANGES=NO")
    print("V2_EVENTS_GENERATED=NO")
    print("DATABASE_CONNECTIONS=0")
    print("DATABASE_WRITES=0")
    print("FILES_WRITTEN=0")
    print("NETWORK_REQUESTS=0")
    print("NEW_LOAD_TESTS=0")
    print("GC24CG_Z27H_OFFLINE_VERIFIER=PASS")


if __name__ == "__main__":
    main()
