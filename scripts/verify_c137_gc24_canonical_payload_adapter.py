#!/usr/bin/env python3
"""C137 GC24 Z27D: offline mapping, negative and no-I/O contract verifier."""
import ast
import copy
import datetime as dt
import os
import subprocess
import sys
from pathlib import Path

sys.dont_write_bytecode = True
from c137_gc24_fixture_dryrun import DB, FARM, YEAR, SCENARIOS, build
from c137_gc24_fixture_import_preflight import (
    CERTIFIED_DIGESTS, ImportPreflightError
)
from c137_gc24_canonical_payload_adapter import (
    EXECUTION_ENABLED, OWNERS, adapt_plan, money
)

SCRIPT = Path(__file__).with_name("c137_gc24_canonical_payload_adapter.py")
AS_OF = dt.date(2026, 10, 11)


def require(ok, code):
    if not ok:
        raise AssertionError(code)


def reject(callback, code):
    try:
        callback()
    except (ImportPreflightError, ValueError):
        return
    raise AssertionError(code)


def static_no_io_guard():
    syntax = ast.parse(SCRIPT.read_text(encoding="utf-8"))
    allowed = {
        "argparse", "collections", "datetime", "json", "sys",
        "c137_gc24_fixture_dryrun", "c137_gc24_fixture_import_preflight",
    }
    banned_calls = {
        "open", "eval", "exec", "__import__", "system", "popen", "Popen",
        "run", "connect", "execute", "commit", "write", "write_text",
        "write_bytes", "unlink", "remove", "mkdir", "urlopen",
        "urlretrieve", "getenv", "environ", "fetch", "request",
    }
    for node in ast.walk(syntax):
        if isinstance(node, ast.Import):
            require(all(x.name in allowed for x in node.names), "IO_IMPORT")
        if isinstance(node, ast.ImportFrom):
            require(node.module in allowed, "IO_FROM_IMPORT")
        if isinstance(node, ast.Call):
            fn = node.func
            name = (fn.id if isinstance(fn, ast.Name)
                    else fn.attr if isinstance(fn, ast.Attribute) else "")
            require(name not in banned_calls, "IO_CALL")
    require(EXECUTION_ENABLED is False, "EXECUTION_ENABLED")
    print("STATIC_NO_DATABASE_NETWORK_FILE_WRITE=PASS")
    print("EXECUTION_HARD_DISABLED=PASS")


def cli_args(scenario="R1", *, database=DB, farm=FARM, year=YEAR,
             as_of="2026-10-11"):
    return [
        "--dry-run", "--scenario", scenario, "--database", database,
        "--farm-slug", farm, "--year", str(year), "--as-of", as_of
    ]


def run_cli(args):
    env = dict(os.environ)
    env["PYTHONDONTWRITEBYTECODE"] = "1"
    return subprocess.run(
        [sys.executable, "-B", str(SCRIPT), *args],
        capture_output=True, text=True, env=env, timeout=45
    )


def main():
    static_no_io_guard()
    require(money(170000) == "1700.00", "NAIRA_CONVERSION")
    reject(lambda: money(-1), "NEGATIVE_MONEY")
    for scenario in sorted(SCENARIOS):
        original = build(scenario, include_records=True)
        saved = copy.deepcopy(original)
        require(original["event_digest_sha256"] == CERTIFIED_DIGESTS[scenario],
                "SOURCE_EVENT_DIGEST_CHANGED")
        output = adapt_plan(scenario, original, AS_OF, include_intents=True)
        require(original == saved, "ORIGINAL_STREAM_MUTATED")
        require(output["readiness"] == "BLOCKED_OFFLINE"
                and output["execution"] == "HARD_DISABLED"
                and output["canonical_php_validation"] == "NOT_EXECUTED",
                "READINESS_MISREPRESENTED")
        require(output["intents_mapped"] == len(original["records"])
                and output["blocked_events"] == len(original["records"]),
                "INTENT_COVERAGE")
        require(set(output["kind_counts"]) == set(OWNERS),
                "OWNER_COVERAGE")
        require(output["allocation_child_rows"] ==
                sum(original["record_counts"][k] for k in (
                    "sales_allocation", "expense_allocation", "stock_allocation"
                )), "ALLOCATION_ROW_LOSS")
        require(all(not r["executable"] and r["blockers"]
                    for r in output["intents"]), "EXECUTABLE_OR_READY_EVENT")
        require(all(not g["executable"] and len(g["desired_rows"]) == 2
                    and all(set(row) == {"cycle_ref", "allocated_amount"}
                            for row in g["desired_rows"])
                    for g in output["allocation_call_previews"]),
                "PERCENT_OR_ORPHAN_ALLOCATIONS")
        issues = output["blocker_counts"]
        require(issues["CYCLE_STATUS_MODEL_CONFLICT"] == 4,
                "CYCLE_STATUS_DRIFT")
        require(issues["CATEGORY_DOMAIN_WRITER_NOT_EXTRACTED"] == 2,
                "CATEGORY_WRITER_DRIFT")
        require(issues["ITEM_DOMAIN_WRITER_NOT_EXTRACTED"] == 2,
                "ITEM_WRITER_DRIFT")
        require(issues["SALE_DOMAIN_WRITER_NOT_EXTRACTED"] ==
                original["record_counts"]["sales"], "SALE_WRITER_DRIFT")
        require(issues["LAYER_EGG_DAILY_PRODUCTION_ABSENT"] > 0
                and issues["MANUAL_LAYER_EGG_ALLOCATION_FORBIDDEN"] > 0,
                "EGG_AUTHORITY_MISSING")
        require(issues["OPERATING_IS_NOT_A_FINANCIAL_CLASSIFICATION"] > 0,
                "STOCK_CLASSIFICATION_MISSING")
        require(issues["FUTURE_RECEIPT_REJECTED_BY_UI"] == 4,
                "RECEIPT_DATE_GUARD")
        result = run_cli(cli_args(scenario))
        require(result.returncode == 0
                and "C137_GC24CG_Z27D_OFFLINE_ADAPTER=PASS"
                in result.stdout
                and "READINESS=BLOCKED_OFFLINE" in result.stdout,
                "Z27D_CLI_FAILURE")
        require("DATABASE_CONNECTIONS=0" in result.stdout
                and "CANONICAL_PHP_WRITER_CALLS=0" in result.stdout,
                "Z27D_NO_IO_CLI")
        print("SCENARIO_" + scenario + "_DIGEST=PASS")
        print("SCENARIO_" + scenario + "_BLOCKING_DIAGNOSTICS=PASS")
        print("SCENARIO_" + scenario + "_GROUPED_ALLOCATION_PREVIEWS=PASS")
    tampered = build("R1", include_records=True)
    tampered["records"][0]["status"] = "active"
    reject(lambda: adapt_plan("R1", tampered, AS_OF), "MUTATED_EVENT_ACCEPTED")
    bad_farm = build("R1", include_records=True)
    bad_farm["farm_slug"] = "unauthorized"
    reject(lambda: adapt_plan("R1", bad_farm, AS_OF), "TENANT_GUARD")
    reject(lambda: adapt_plan("R1", build("R1", include_records=True),
                              dt.date(2025, 10, 11)), "AS_OF_GUARD")
    for options in (
        cli_args(database="renee_c137_fixture"),
        cli_args(database="reneefarms_prod"),
        cli_args(farm="other-tenant"),
        cli_args(year=2025),
        cli_args(as_of="invalid"),
        cli_args() + ["--execute"],
        [x for x in cli_args() if x != "--dry-run"],
    ):
        require(run_cli(options).returncode != 0, "UNSAFE_CLI_ACCEPTED")
    print("TAMPER_TARGET_EXECUTION_REJECTION=PASS")
    print("SOURCE_EVENTS_CHANGED=NO")
    print("CANONICAL_PHP_WRITER_CALLS=0")
    print("DATABASE_CONNECTIONS=0")
    print("DATABASE_WRITES=0")
    print("FILE_WRITES=0")
    print("NETWORK_REQUESTS=0")
    print("NEW_LOAD_TESTS=0")
    print("GC24CG_Z27D_OFFLINE_VERIFIER=PASS")


if __name__ == "__main__":
    main()
