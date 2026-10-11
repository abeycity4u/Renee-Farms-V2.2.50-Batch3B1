#!/usr/bin/env python3
"""GC24 Z27F: safe in-memory proposal and reject-path verifier."""
import ast
import copy
import os
import subprocess
import sys
from pathlib import Path

sys.dont_write_bytecode = True

from c137_gc24_fixture_dryrun import DB, FARM, YEAR, build, SCENARIOS
from c137_gc24_fixture_import_preflight import CERTIFIED_DIGESTS, ImportPreflightError
from c137_gc24_fixture_v2_design import EXECUTION_ENABLED, DESIGN_VERSION, RULES, GATES, proposed_design

SCRIPT = Path(__file__).with_name("c137_gc24_fixture_v2_design.py")
ALLOWED = {
    "argparse", "collections", "datetime", "hashlib", "json", "sys",
    "c137_gc24_fixture_dryrun", "c137_gc24_fixture_import_preflight",
}
BANNED = {
    "open", "eval", "exec", "__import__", "system", "popen", "Popen", "run",
    "connect", "execute", "commit", "write", "write_text", "write_bytes",
    "unlink", "remove", "mkdir", "urlopen", "urlretrieve", "getenv", "environ",
    "fetch", "request",
}


def guard(check, code):
    if not check:
        raise AssertionError(code)


def reject(fn, code):
    try:
        fn()
    except (ImportPreflightError, ValueError):
        return
    raise AssertionError(code)


def run_cli(args):
    env = dict(os.environ)
    env["PYTHONDONTWRITEBYTECODE"] = "1"
    return subprocess.run(
        [sys.executable, "-B", str(SCRIPT), *args],
        capture_output=True, text=True, timeout=90, env=env
    )


def args_for(scenario="R1", database=DB, farm=FARM, year=YEAR, as_of="2026-10-11"):
    return [
        "--dry-run", "--scenario", scenario,
        "--database", database, "--farm-slug", farm,
        "--year", str(year), "--as-of", as_of,
    ]


def main():
    syntax = ast.parse(SCRIPT.read_text(encoding="utf-8"))
    for node in ast.walk(syntax):
        if isinstance(node, ast.Import):
            guard(all(i.name in ALLOWED for i in node.names), "IO_IMPORT")
        if isinstance(node, ast.ImportFrom):
            guard(node.module in ALLOWED, "IO_FROM_IMPORT")
        if isinstance(node, ast.Call):
            fn = node.func
            name = fn.id if isinstance(fn, ast.Name) else (
                fn.attr if isinstance(fn, ast.Attribute) else ""
            )
            guard(name not in BANNED, "IO_CALL")
    guard(EXECUTION_ENABLED is False, "EXECUTION_ENABLED")
    guard(DESIGN_VERSION == "C137-GC24-V2-DESIGN-0", "DESIGN_VERSION_CHANGED")
    guard(RULES["sales"]["layer_eggs_manual_60_40"] == "FORBIDDEN",
          "MANUAL_EGG_POLICY_RELAXED")
    guard(RULES["inventory"]["operating"]["financial_classification"] ==
          "consumables", "OPERATING_CLASSIFICATION_DRIFT")
    guard(RULES["inventory"]["feed"]["financial_classification"] ==
          "feed", "FEED_CLASSIFICATION_DRIFT")
    guard(RULES["shared_allocations"]["authoritative_input"] ==
          "allocated_amount", "MANUAL_PERCENT_REPLACED_AUTHORITY")
    guard(RULES["date"]["chosen_window"] == "UNRESOLVED",
          "FUTURE_DATE_APPROVAL_IMPLIED")
    guard(len(GATES) >= 8, "UNRESOLVED_GATES_LOST")
    print("Z27F_STATIC_NO_IO_AND_POLICY_GUARD=PASS")
    print("EXECUTION_HARD_DISABLED=PASS")

    for scenario in sorted(SCENARIOS):
        legacy = build(scenario, include_records=True)
        before = copy.deepcopy(legacy)
        spec = proposed_design(scenario, legacy)
        guard(legacy == before, "LEGACY_EVENTS_MUTATED")
        guard(spec["legacy_event_digest_sha256"] == CERTIFIED_DIGESTS[scenario],
              "LEGACY_DIGEST_CHANGED")
        guard(spec["proposal_id"] == scenario + "-V2-DESIGN",
              "PROPOSAL_NAMESPACE_DRIFT")
        guard(len(spec["specification_digest_sha256"]) == 64, "SPEC_DIGEST_ABSENT")
        guard(spec["proposed_v2_events"] == "NOT_GENERATED" and
              spec["proposed_v2_event_digest_sha256"] == "NOT_GENERATED",
              "V2_EVENTS_FALSELY_CLAIMED")
        guard(spec["execution"] == "HARD_DISABLED" and
              spec["readiness"] == "PROPOSED_BLOCKED", "UNSAFE_READINESS")
        b = spec["derived_buckets"]
        guard(b["egg_manual_child_rows_must_not_replay"] ==
              2 * b["egg_sales_source_manual_parent_to_replace"],
              "MANUAL_EGG_ROWS_RETAINED")
        guard(b["broiler_manual_child_rows_candidate"] ==
              2 * b["broiler_sales_candidate_manual_shared"],
              "BROILER_REVENUE_ROW_LOSS")
        guard(b["future_receipts_at_pinned_as_of"] == 4,
              "FUTURE_DATE_GUARD_OMITTED")
        guard(b["used_stock_reclassify_operating_as_consumables"] > 0,
              "INVALID_OPERATING_STOCK_RETAINED")
        result = run_cli(args_for(scenario))
        guard(result.returncode == 0 and
              "GC24CG_Z27F_V2_OFFLINE_DESIGN=PASS" in result.stdout and
              "READINESS=PROPOSED_BLOCKED" in result.stdout,
              "DESIGN_CLI_REJECTED")
        guard("DATABASE_CONNECTIONS=0" in result.stdout and
              "DATABASE_WRITES=0" in result.stdout and
              "PROPOSED_V2_EVENTS=NOT_GENERATED" in result.stdout,
              "CLI_SAFETY_MISSING")
        print("SCENARIO_" + scenario + "_LEGACY_DIGEST=PASS")
        print("SCENARIO_" + scenario + "_CLASSIFICATION=PASS")
        print("SCENARIO_" + scenario + "_SPEC_DIGEST=" +
              spec["specification_digest_sha256"])

    wrong = build("R1", include_records=True)
    wrong["farm_slug"] = "different-farm"
    reject(lambda: proposed_design("R1", wrong), "TENANT_TAMPER_ACCEPTED")
    wrong = build("R1", include_records=True)
    wrong["records"][0]["status"] = "active"
    reject(lambda: proposed_design("R1", wrong), "EVENT_TAMPER_ACCEPTED")
    wrong = build("R1", include_records=True)
    wrong["event_digest_sha256"] = "0" * 64
    reject(lambda: proposed_design("R1", wrong), "DIGEST_TAMPER_ACCEPTED")

    for bad in (
        args_for(database="renee_c137_fixture"),
        args_for(database="reneefarms_prod"),
        args_for(farm="other-farm"),
        args_for(year=2025),
        args_for(as_of="2026-12-31"),
        args_for() + ["--execute"],
        [a for a in args_for() if a != "--dry-run"],
    ):
        guard(run_cli(bad).returncode != 0, "UNSAFE_CLI_ACCEPTED")

    print("TAMPER_AND_UNAUTHORIZED_CLI_REJECTION=PASS")
    print("ORIGINAL_EVENTS_WRITTEN=NO")
    print("PROPOSED_V2_EVENT_DIGEST=NOT_GENERATED")
    print("PHP_WRITER_CALLS=0")
    print("DATABASE_CONNECTIONS=0")
    print("DATABASE_WRITES=0")
    print("FILE_WRITES=0")
    print("NETWORK_REQUESTS=0")
    print("NEW_LOAD_TESTS=0")
    print("GC24CG_Z27F_OFFLINE_VERIFIER=PASS")


if __name__ == "__main__":
    main()
