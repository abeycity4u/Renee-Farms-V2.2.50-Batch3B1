#!/usr/bin/env python3
"""Z26: offline structural and negative tests for import PRE-FLIGHT.

No MariaDB client, credentials, network or filesystem mutation.
"""
import ast
import copy
import os
import subprocess
import sys
from pathlib import Path

sys.dont_write_bytecode = True

from c137_gc24_fixture_dryrun import DB, FARM, YEAR, SCENARIOS, build
from c137_gc24_fixture_import_preflight import (
    CERTIFIED_DIGESTS, EXECUTION_ENABLED, ImportPreflightError,
    validate_manifest
)

SCRIPT = Path(__file__).with_name("c137_gc24_fixture_import_preflight.py")
PROTECTED = "renee_c137_fixture"


def require(check, code):
    if not check:
        raise AssertionError(code)


def reject(callback, code):
    try:
        callback()
    except ImportPreflightError:
        return
    raise AssertionError(code)


def run_cli(arguments):
    env = dict(os.environ)
    env["PYTHONDONTWRITEBYTECODE"] = "1"
    return subprocess.run(
        [sys.executable, "-B", str(SCRIPT), *arguments],
        capture_output=True, text=True, env=env, timeout=40
    )


def options(scenario="R1", *, database=DB, farm=FARM, year=YEAR):
    return [
        "--dry-run", "--scenario", scenario, "--database", database,
        "--farm-slug", farm, "--year", str(year)
    ]


def static_io_guard():
    tree = ast.parse(SCRIPT.read_text(encoding="utf-8"))
    allowed_imports = {
        "argparse", "collections", "datetime", "hashlib",
        "json", "re", "sys", "c137_gc24_fixture_dryrun"
    }
    blocked_calls = {
        "open", "eval", "exec", "__import__", "system", "popen",
        "Popen", "run", "connect", "execute", "commit", "write",
        "write_text", "write_bytes", "unlink", "remove", "mkdir",
        "urlopen", "urlretrieve", "getenv", "environ"
    }
    for node in ast.walk(tree):
        if isinstance(node, ast.Import):
            require(all(x.name.split(".")[0] in allowed_imports
                        for x in node.names), "UNEXPECTED_IMPORT")
        if isinstance(node, ast.ImportFrom):
            require(node.module in allowed_imports, "UNEXPECTED_FROM_IMPORT")
        if isinstance(node, ast.Call):
            f = node.func
            name = (
                f.id if isinstance(f, ast.Name)
                else f.attr if isinstance(f, ast.Attribute) else ""
            )
            require(name not in blocked_calls, "UNEXPECTED_IO_CALL")
    require(EXECUTION_ENABLED is False, "EXECUTION_ENABLED")
    print("STATIC_NO_DATABASE_NETWORK_FILE_WRITE=PASS")
    print("EXECUTION_HARD_DISABLED=PASS")


def main():
    static_io_guard()
    for scenario in sorted(SCENARIOS):
        m = build(scenario, include_records=True)
        require(m["event_digest_sha256"] == CERTIFIED_DIGESTS[scenario],
                "CERTIFIED_DIGEST_CHANGED")
        summary = validate_manifest(scenario, m)
        require(summary["referential_contract"] == "PASS_OFFLINE",
                "RELATIONSHIP_VERIFIER")
        require(summary["financial_reconciliation"] == "PASS_OFFLINE",
                "FINANCIAL_VERIFIER")
        require(summary["stock_conservation"] == "PASS_OFFLINE",
                "STOCK_VERIFIER")
        require(summary["execution"] == "DISABLED", "WRITE_MODE_ENABLED")
        require(summary["events_validated"] == sum(
            m["record_counts"].values()), "RECORD_VALIDATION_COVERAGE")
        command = run_cli(options(scenario))
        require(command.returncode == 0
                and "C137_GC24CG_Z26_IMPORT_PREFLIGHT=PASS"
                in command.stdout, "CLI_VERIFIER")
        require("DATABASE_CONNECTIONS=0" in command.stdout
                and "DATABASE_WRITES=0" in command.stdout,
                "CLI_ZERO_DATABASE_IO")
        print("SCENARIO_" + scenario + "_RELATIONSHIPS=PASS")
        print("SCENARIO_" + scenario + "_FINANCIAL_STOCK=PASS")
        print("SCENARIO_" + scenario + "_DIGEST=" + CERTIFIED_DIGESTS[scenario])

    print("BOTH_SCENARIOS=PASS")

    base = build("R1", include_records=True)
    modified = copy.deepcopy(base)
    modified["records"][0]["production_type"] = "tampered"
    reject(lambda: validate_manifest("R1", modified), "EVENT_MUTATION_ACCEPTED")

    modified = copy.deepcopy(base)
    modified["records"][-1]["parent_ref"] = "unknown-parent"
    reject(lambda: validate_manifest("R1", modified), "ORPHAN_ACCEPTED")

    modified = copy.deepcopy(base)
    modified["event_digest_sha256"] = "0" * 64
    reject(lambda: validate_manifest("R1", modified), "BAD_DIGEST_ACCEPTED")

    modified = copy.deepcopy(base)
    modified["database"] = PROTECTED
    reject(lambda: validate_manifest("R1", modified), "PROTECTED_DB_ACCEPTED")

    modified = copy.deepcopy(base)
    modified["farm_slug"] = "not-our-farm"
    reject(lambda: validate_manifest("R1", modified), "OTHER_FARM_ACCEPTED")

    print("TAMPER_ORPHAN_DIGEST_TENANT_REJECTION=PASS")

    for bad in (
        options(database=PROTECTED),
        options(database="reneefarms_prod"),
        options(farm="other-farm"),
        options(year=2025),
        options() + ["--execute"],
        [flag for flag in options() if flag != "--dry-run"]
    ):
        result = run_cli(bad)
        require(result.returncode != 0, "UNSAFE_CLI_ACCEPTED")

    print("UNSAFE_TARGET_AND_EXECUTION_REJECTION=PASS")
    print("CANONICAL_DOMAIN_WRITER_MAPPING=PENDING")
    print("IMPORT_WRITES_IMPLEMENTED=NO")
    print("DATABASE_CONNECTIONS=0")
    print("DATABASE_WRITES=0")
    print("FILE_WRITES=0")
    print("NETWORK_REQUESTS=0")
    print("NEW_LOAD_TESTS=0")
    print("GC24CG_Z26_OFFLINE_VERIFIER=PASS")


if __name__ == "__main__":
    main()
