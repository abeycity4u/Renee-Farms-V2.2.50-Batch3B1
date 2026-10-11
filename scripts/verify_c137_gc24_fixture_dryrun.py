#!/usr/bin/env python3
"""C137 GC24 Z23: self-test for offline, fail-closed fixture/recovery plans."""
import ast
import os
import subprocess
import sys
from pathlib import Path

sys.dont_write_bytecode = True

from c137_gc24_fixture_dryrun import DB, FARM, YEAR, SCENARIOS, build

HERE = Path(__file__).resolve().parent
PLANNER = HERE / "c137_gc24_fixture_dryrun.py"
RECOVERY = HERE / "c137_gc24_fixture_recovery_dryrun.py"
ALLOWED_IMPORTS = {
    "argparse", "calendar", "datetime", "hashlib", "json",
    "collections", "re", "c137_gc24_fixture_dryrun"
}
FORBIDDEN_CALLS = {
    "open", "eval", "exec", "compile", "__import__", "system",
    "popen", "Popen", "run", "check_output", "connect",
    "urlopen", "urlretrieve", "remove", "unlink",
    "write_text", "write_bytes", "write", "mkdir",
    "execute", "executescript", "commit"
}


def ensure(condition, name):
    if not condition:
        raise AssertionError(name)


def inspect_no_io(path):
    tree = ast.parse(path.read_text(encoding="utf-8"), filename=str(path))
    for node in ast.walk(tree):
        if isinstance(node, ast.Import):
            for item in node.names:
                ensure(item.name.split(".")[0] in ALLOWED_IMPORTS, "IMPORT_GUARD")
        elif isinstance(node, ast.ImportFrom):
            ensure(node.module in ALLOWED_IMPORTS, "FROM_IMPORT_GUARD")
        elif isinstance(node, ast.Call):
            func = node.func
            name = func.id if isinstance(func, ast.Name) else (
                func.attr if isinstance(func, ast.Attribute) else ""
            )
            ensure(name not in FORBIDDEN_CALLS, "IO_CALL_GUARD")


def cli(script, extra):
    env = dict(os.environ)
    env["PYTHONDONTWRITEBYTECODE"] = "1"
    return subprocess.run(
        [sys.executable, "-B", str(script), *extra],
        capture_output=True, text=True, env=env, timeout=25
    )


def common(scenario):
    return [
        "--dry-run", "--scenario", scenario, "--database", DB,
        "--farm-slug", FARM, "--year", str(YEAR)
    ]


def main():
    for script in (PLANNER, RECOVERY):
        inspect_no_io(script)
    print("STATIC_NO_DATABASE_NETWORK_OR_FILE_IO=PASS")

    hashes = {}
    for scenario, volumes in SCENARIOS.items():
        first = build(scenario)
        second = build(scenario)
        ensure(first == second, "NONDETERMINISTIC_PLAN")
        ensure(first["event_digest_sha256"] not in hashes.values(), "DIGEST_COLLISION")
        hashes[scenario] = first["event_digest_sha256"]
        ensure(first["financial_reconciliation"] == "PASS_OFFLINE_MODEL", "RECONCILIATION")
        ensure(first["canonical_business_validation"] == "PENDING", "CANONICAL_GATE")
        ensure(first["data_insertion"] == "PROHIBITED", "INSERT_GATE")
        ensure(first["backup_verification"] == "PENDING", "BACKUP_GATE")
        ensure(first["monthly_periods"] == 12, "MONTH_COVERAGE")
        ensure(first["remaining_stock_units"] == {"feed": 120, "operating": 120}, "STOCK_CONSERVATION")
        counts = first["record_counts"]
        for source, field in (("sales", "sales"), ("expenses", "expenses"), ("used_stock", "used_stock")):
            ensure(counts[source] == volumes[field], "VOLUME_CONTRACT")
        ensure(counts["cycle"] == 4 and counts["stock_received"] == 24, "PARENT_COUNTS")
        ensure(counts["sales_allocation"] == 2 * ((volumes["sales"] + 6) // 7), "SALES_ALLOCATIONS")
        ensure(counts["expense_allocation"] == 2 * ((volumes["expenses"] + 4) // 5), "EXPENSE_ALLOCATIONS")
        ensure(counts["stock_allocation"] == 2 * ((volumes["used_stock"] + 4) // 5), "STOCK_ALLOCATIONS")
        ensure(first["planned_allocated_parent_cents"] == first["planned_allocated_child_cents"], "ALLOCATED_SUMS")
        ensure(first["prefetch_case"] == ("IN_LIMIT" if scenario == "R1" else "OVERFLOW_INTENDED"), "PREFETCH_SCENARIO")

        runner = cli(PLANNER, common(scenario))
        ensure(runner.returncode == 0 and "C137_GC24CG_Z23_DRY_RUN=PASS" in runner.stdout, "PLANNER_CLI")
        ensure("DATABASE_WRITES=0" in runner.stdout and "NETWORK_REQUESTS=0" in runner.stdout, "PLANNER_ZERO_IO")

        recovery = cli(RECOVERY, common(scenario) + [
            "--expected-event-digest", hashes[scenario]
        ])
        ensure(recovery.returncode == 0 and "C137_GC24CG_Z23_RECOVERY_DRYRUN=PASS" in recovery.stdout, "RECOVERY_CLI")
        ensure("REAL_RECOVERY_READY=NO" in recovery.stdout, "RECOVERY_NOT_ARMED")
        ensure("ROLLBACK_EXECUTION_TOOL=NOT_IMPLEMENTED" in recovery.stdout, "RECOVERY_EXECUTION_GATE")

    print("R1_R2_DETERMINISM_AND_RECONCILIATION=PASS")
    print("R1_R2_OFFLINE_COMMANDS=PASS")

    invalid_plans = [
        common("R1")[:-1] + ["2027"],
        common("R1")[:-3] + ["renee_c137_fixture", "--year", str(YEAR)],
        ["--scenario", "R1", "--database", DB, "--farm-slug", FARM, "--year", str(YEAR)],
    ]
    # Explicit, unambiguous negative tests.
    invalid_plans = [
        ["--dry-run", "--scenario", "R1", "--database", DB,
         "--farm-slug", FARM, "--year", "2027"],
        ["--dry-run", "--scenario", "R1", "--database", "renee_c137_fixture",
         "--farm-slug", FARM, "--year", str(YEAR)],
        ["--scenario", "R1", "--database", DB, "--farm-slug", FARM, "--year", str(YEAR)],
    ]
    for flags in invalid_plans:
        ensure(cli(PLANNER, flags).returncode != 0, "PLANNER_FAIL_CLOSED")

    ensure(
        cli(RECOVERY, common("R1") +
            ["--expected-event-digest", hashes["R2"]]).returncode != 0,
        "RECOVERY_DIGEST_FAIL_CLOSED"
    )
    ensure(
        cli(RECOVERY, ["--dry-run", "--scenario", "R1",
                       "--database", "renee_c137_fixture",
                       "--farm-slug", FARM, "--year", str(YEAR),
                       "--expected-event-digest", hashes["R1"]]).returncode != 0,
        "RECOVERY_TARGET_FAIL_CLOSED"
    )
    print("PROTECTED_FIXTURE_AND_INVALID_TARGET_REJECTION=PASS")
    print("MISMATCHED_RECOVERY_PLAN_REJECTION=PASS")
    print("DATABASE_CONNECTIONS=0")
    print("DATABASE_WRITES=0")
    print("BACKUP_OPERATIONS=0")
    print("ROLLBACK_OPERATIONS=0")
    print("K6_EXECUTED=NO")
    print("GC24CG_Z23_OFFLINE_VERIFIER=PASS")


if __name__ == "__main__":
    main()
