#!/usr/bin/env python3
"""C137 GC24 Z27I: no-I/O graph and lineage regression verification."""
import ast
import copy
import os
import subprocess
import sys
from pathlib import Path

sys.dont_write_bytecode = True

from c137_gc24_fixture_dryrun import DB, FARM, YEAR, SCENARIOS, build
from c137_gc24_fixture_import_preflight import CERTIFIED_DIGESTS, ImportPreflightError
from c137_gc24_operational_feasibility import analyze as z27h_analyze
from c137_gc24_v2_dependency_contract import (
    EXECUTION_ENABLED, NODES, OPEN_GATES, AUTHORITIES,
    NONNEGOTIABLE, Z27H_DIGESTS, topological_order, inspect,
)

SCRIPT = Path(__file__).with_name("c137_gc24_v2_dependency_contract.py")
ALLOWED = {
    "argparse", "hashlib", "json", "sys",
    "c137_gc24_fixture_dryrun",
    "c137_gc24_fixture_import_preflight",
    "c137_gc24_operational_feasibility",
}
FORBIDDEN = {
    "open", "eval", "exec", "__import__", "system", "popen", "Popen",
    "run", "connect", "execute", "commit", "write", "write_text",
    "write_bytes", "unlink", "remove", "mkdir", "urlopen", "urlretrieve",
    "getenv", "environ", "fetch", "request",
}
EXPECTED = {
    "R1": (5452, 16419, 5469, 1180, 1800),
    "R2": (13770, 49269, 16422, 2994, 3600),
}


def require(condition, code):
    if not condition:
        raise AssertionError(code)


def rejected(fn, code):
    try:
        fn()
    except (ImportPreflightError, ValueError):
        return
    raise AssertionError(code)


def cli_args(scenario="R1", *, database=DB, farm=FARM, year=YEAR,
             as_of="2026-10-11", candidate_year=2025):
    return [
        "--dry-run", "--scenario", scenario,
        "--database", database, "--farm-slug", farm,
        "--year", str(year), "--as-of", as_of,
        "--candidate-year", str(candidate_year),
    ]


def cli(args):
    env = dict(os.environ)
    env["PYTHONDONTWRITEBYTECODE"] = "1"
    return subprocess.run(
        [sys.executable, "-B", str(SCRIPT), *args],
        capture_output=True, text=True, timeout=120, env=env
    )


def main():
    tree = ast.parse(SCRIPT.read_text(encoding="utf-8"))
    for node in ast.walk(tree):
        if isinstance(node, ast.Import):
            require(all(alias.name in ALLOWED for alias in node.names),
                    "UNSAFE_IMPORT")
        elif isinstance(node, ast.ImportFrom):
            require(node.module in ALLOWED, "UNSAFE_FROM_IMPORT")
        elif isinstance(node, ast.Call):
            target = node.func
            name = target.id if isinstance(target, ast.Name) else (
                target.attr if isinstance(target, ast.Attribute) else ""
            )
            require(name not in FORBIDDEN, "UNSAFE_EXECUTION_CALL")
    require(EXECUTION_ENABLED is False, "EXECUTION_ENABLED")
    require(NONNEGOTIABLE["legacy_dates_modified"] is False,
            "LEGACY_DATE_MUTATION")
    require(NONNEGOTIABLE["reporting_year_option_2025"] == "UNSELECTED",
            "YEAR_SELECTED_WITHOUT_AUTHORITY")
    require(NONNEGOTIABLE["egg_allocation_method"] ==
            "CANONICAL_UNSOLD_EGG_POOL_NO_MANUAL_60_40", "EGG_POLICY_BYPASSED")
    require(NONNEGOTIABLE["broiler_sale_population"] ==
            "EXPLICIT_HEADCOUNTS_NO_PRODUCT_INFERENCE", "POPULATION_BYPASSED")
    require(NONNEGOTIABLE["stock_provenance"] ==
            "EXACTLY_ONE_AUTHORITATIVE_SOURCE_PER_CONSUMPTION", "DOUBLE_FEED")
    require(NONNEGOTIABLE["import_authorization"] == "ABSENT",
            "IMPORT_AUTHORIZATION_INVENTED")
    require(set(AUTHORITIES).issubset(set(NODES)), "UNKNOWN_AUTHORITY_NODE")
    order = topological_order(NODES)
    require(len(NODES) >= 20 and len(OPEN_GATES) == len(NODES),
            "INCOMPLETE_GRAPH")
    require(order[-1] == "authorized_import_gate", "EARLY_IMPORT_GATE")
    position = {node: i for i, node in enumerate(order)}
    for node, predecessors in NODES.items():
        for predecessor in predecessors:
            require(position[predecessor] < position[node],
                    "DEPENDENCY_ORDER_BROKEN")
    for node, predecessor in (
        ("layer_sales", "layer_unsold_egg_pool"),
        ("layer_unsold_egg_pool", "layer_daily_production_and_feed"),
        ("broiler_sale_population_effects", "broiler_sales"),
        ("consumed_stock_ledger", "priced_inventory_receipts"),
        ("reports_eligible_cost_rows", "historical_stock_cost_replay"),
        ("authorized_import_gate", "reports_eligible_cost_rows"),
    ):
        require(predecessor in NODES[node], "CRITICAL_EDGE_MISSING")
    cyclic = dict(NODES)
    cyclic["calendar_authority"] = ("authorized_import_gate",)
    rejected(lambda: topological_order(cyclic), "DEPENDENCY_CYCLE_UNDETECTED")
    missing = dict(NODES)
    missing["layer_sales"] = ("not_a_real_node",)
    rejected(lambda: topological_order(missing), "BAD_DEPENDENCY_UNDETECTED")
    print("STATIC_NO_IO_AND_GRAPH_CONTRACT=PASS")
    print("EXECUTION_HARD_DISABLED=PASS")

    for scenario in sorted(SCENARIOS):
        original = build(scenario, include_records=True)
        before = copy.deepcopy(original)
        prior = z27h_analyze(scenario, original)
        report = inspect(scenario, original)
        require(original == before, "ORIGINAL_STREAM_MUTATED")
        require(prior["feasibility_specification_digest_sha256"] ==
                Z27H_DIGESTS[scenario], "PRIOR_DIGEST_DRIFT")
        require(report["legacy_event_digest_sha256"] ==
                CERTIFIED_DIGESTS[scenario], "EVENT_DIGEST_DRIFT")
        count, eggs, broilers, future, used = EXPECTED[scenario]
        require(report["legacy_event_count"] == count, "LEGACY_COUNT_DRIFT")
        require(report["egg_crate_sale_equivalent"] == eggs, "EGG_COUNT_DRIFT")
        require(report["broiler_head_sales_not_proven_exits"] ==
                broilers, "BROILER_COUNT_DRIFT")
        require(report["future_dated_legacy_events"] == future,
                "FUTURE_COUNT_DRIFT")
        require(report["candidate_eligible_stock_rows"] == used and
                report["candidate_eligible_stock_rows_verified"] is False,
                "PREFETCH_EVIDENCE_INVENTED")
        require(report["broiler_direct_b_legacy"] == 0 and
                report["broiler_feed_used_legacy"] == 0,
                "ORIGINAL_MISSING_DEPENDENCIES_CLAIMED_PRESENT")
        require(report["historical_year_selected"] is False,
                "REPORTING_YEAR_UNAUTHORIZED")
        require(report["new_event_count"] == 0 and
                report["new_event_digest_sha256"] == "NOT_GENERATED",
                "NEW_EVENTS_UNAUTHORIZED")
        require(report["readiness"] == "DEPENDENCY_ARCHITECTURE_BLOCKED" and
                report["execution"] == "HARD_DISABLED",
                "FALSE_IMPORT_READINESS")
        require(report["dependency_nodes"] == len(NODES) and
                report["unresolved_node_gates"] == len(OPEN_GATES) and
                len(report["dependency_specification_digest_sha256"]) == 64,
                "SPECIFICATION_INCOMPLETE")
        result = cli(cli_args(scenario))
        require(result.returncode == 0 and
                "GC24CG_Z27I_DEPENDENCY_PLAN=PASS" in result.stdout and
                "READINESS=DEPENDENCY_ARCHITECTURE_BLOCKED" in result.stdout
                and "DATABASE_CONNECTIONS=0" in result.stdout and
                "DATABASE_WRITES=0" in result.stdout and
                "V2_EVENTS_GENERATED=NO" in result.stdout,
                "CLI_GUARD_FAILED")
        print(scenario + "_LEGACY_LINEAGE=PASS")
        print(scenario + "_DEPENDENCY_ARCHITECTURE=PASS")
        print(scenario + "_DEPENDENCY_SPEC_DIGEST=" +
              report["dependency_specification_digest_sha256"])

    tampered = build("R1", include_records=True)
    tampered["farm_slug"] = "different-farm"
    rejected(lambda: inspect("R1", tampered), "TENANT_TAMPER_ACCEPTED")
    tampered = build("R1", include_records=True)
    tampered["records"][0]["status"] = "active"
    rejected(lambda: inspect("R1", tampered), "EVENT_TAMPER_ACCEPTED")
    for invalid in (
        cli_args(database="renee_c137_fixture"),
        cli_args(database="reneefarms_prod"),
        cli_args(farm="foreign-farm"),
        cli_args(year=2025),
        cli_args(as_of="2026-12-31"),
        cli_args(candidate_year=2024),
        cli_args() + ["--execute"],
        [x for x in cli_args() if x != "--dry-run"],
    ):
        require(cli(invalid).returncode != 0, "UNAUTHORIZED_CLI_ACCEPTED")
    print("TENANT_DATE_IMPORT_AND_TAMPER_REJECTIONS=PASS")
    print("ORIGINAL_STREAM_CHANGES=NO")
    print("V2_EVENTS_GENERATED=NO")
    print("DATABASE_CONNECTIONS=0")
    print("DATABASE_WRITES=0")
    print("SOURCE_WRITES=0")
    print("NEW_LOAD_TESTS=0")
    print("GC24CG_Z27I_OFFLINE_VERIFIER=PASS")


if __name__ == "__main__":
    main()
