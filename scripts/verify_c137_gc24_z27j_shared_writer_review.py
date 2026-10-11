#!/usr/bin/env python3
"""Z27J: static contract drift / unchanged lineage / deny-path verifier."""
import ast
import copy
import os
import subprocess
import sys
from pathlib import Path

sys.dont_write_bytecode = True

from c137_gc24_fixture_dryrun import DB, FARM, YEAR, SCENARIOS, build
from c137_gc24_fixture_import_preflight import CERTIFIED_DIGESTS, ImportPreflightError
from c137_gc24_z27j_shared_writer_review import (
    EXECUTION_ENABLED, GATES, SCOPE, PATHS, SOURCE_ROOT, read_sources,
    probe_sources, review,
)

SCRIPT = Path(__file__).with_name("c137_gc24_z27j_shared_writer_review.py")
ALLOWED_IMPORTS = {
    "argparse", "hashlib", "json", "re", "sys", "pathlib",
    "c137_gc24_fixture_dryrun",
    "c137_gc24_fixture_import_preflight",
    "c137_gc24_v2_dependency_contract",
}
BANNED = {
    "write", "write_text", "write_bytes", "mkdir", "unlink",
    "remove", "rmdir", "rename", "replace", "execute", "executemany",
    "commit", "connect", "popen", "system", "urlopen", "request",
    "eval", "exec", "__import__",
}


def demand(value, code):
    if not value:
        raise AssertionError(code)


def rejection(fn, code):
    try:
        fn()
    except (ValueError, ImportPreflightError):
        return
    raise AssertionError(code)


def arguments(scenario="R1", db=DB, farm=FARM, year=YEAR,
              as_of="2026-10-11", candidate=2025, src=SOURCE_ROOT):
    return [
        "--dry-run", "--scenario", scenario, "--database", db,
        "--farm-slug", farm, "--year", str(year),
        "--as-of", as_of, "--candidate-year", str(candidate),
        "--certified-source-root", src,
    ]


def run(args):
    env = dict(os.environ)
    env["PYTHONDONTWRITEBYTECODE"] = "1"
    return subprocess.run(
        [sys.executable, "-B", str(SCRIPT), *args],
        capture_output=True, text=True, timeout=120, env=env
    )


def main():
    ast_tree = ast.parse(SCRIPT.read_text(encoding="utf-8"))
    for node in ast.walk(ast_tree):
        if isinstance(node, ast.Import):
            demand(all(alias.name in ALLOWED_IMPORTS for alias in node.names),
                   "UNSAFE_IMPORT")
        elif isinstance(node, ast.ImportFrom):
            demand(node.module in ALLOWED_IMPORTS, "UNSAFE_FROM_IMPORT")
        elif isinstance(node, ast.Call):
            func = node.func
            label = func.id if isinstance(func, ast.Name) else (
                func.attr if isinstance(func, ast.Attribute) else ""
            )
            demand(label not in BANNED, "UNSAFE_EXECUTION_CALL")
    demand(EXECUTION_ENABLED is False, "IMPORT_EXECUTION_ENABLED")
    demand(SCOPE["writers_extracted"] is False, "EXTRACTION_FALSELY_CLAIMED")
    demand(SCOPE["replacement_events"] == "NOT_GENERATED",
           "REPLACEMENT_EVENTS_FALSELY_CLAIMED")
    demand(SCOPE["date_2025_candidate"] == "UNSELECTED",
           "REPORT_YEAR_FALSELY_CHOSEN")
    demand(len(GATES) >= 14, "CRITICAL_GATES_LOST")
    demand(len(PATHS) >= 15, "SOURCE_SCOPE_SHRUNK")
    print("Z27J_STATIC_READ_ONLY_SCOPE=PASS")

    source = read_sources(Path(SOURCE_ROOT))
    source_copy = copy.deepcopy(source)
    status = probe_sources(source)
    demand(source == source_copy, "PHP_SOURCE_CHANGED_BY_REVIEW")
    demand(status["stock_item_page_local_writer_count"] == 2,
           "DUPLICATE_ITEM_WRITERS_NOT_FOUND")
    demand(status["stock_item_writer_sql_signature_matches"] is True,
           "ITEM_WRITER_SQL_DIFFERENCE")
    demand(status["source_integrity_static_probes"] == "PASS",
           "SOURCE_CONTRACT_PROBES_FAILED")

    tampered = dict(source)
    tampered["inventory.php"] = tampered["inventory.php"].replace(
        "INSERT INTO stock_items", "INSERT INTO forbidden_stock_items", 1
    )
    rejection(lambda: probe_sources(tampered),
              "BROKEN_ITEM_INSERT_ACCEPTED")

    tampered = dict(source)
    tampered["management/sales_records.php"] = (
        tampered["management/sales_records.php"].replace(
            "sale_population_effect_sync(", "removed_sale_pop_effect(", 1
        )
    )
    rejection(lambda: probe_sources(tampered),
              "MISSING_POPULATION_EFFECT_ACCEPTED")

    tampered = dict(source)
    tampered["poultry/layers_daily_record.php"] = (
        tampered["poultry/layers_daily_record.php"].replace(
            "sales_rebuild_layer_egg_allocations(",
            "skipped_egg_allocation_rebuild(", 1
        )
    )
    rejection(lambda: probe_sources(tampered),
              "MISSING_EGG_REBUILD_ACCEPTED")
    print("Z27J_SOURCE_TAMPER_REJECTIONS=PASS")

    for scenario in sorted(SCENARIOS):
        original = build(scenario, include_records=True)
        before = copy.deepcopy(original)
        report = review(scenario, original, source)
        demand(original == before, "LEGACY_STREAM_MUTATED")
        demand(report["legacy_event_digest_sha256"] ==
               CERTIFIED_DIGESTS[scenario], "LEGACY_DIGEST_DRIFT")
        demand(report["new_event_count"] == 0 and
               report["new_event_digest_sha256"] == "NOT_GENERATED",
               "NEW_FIXTURE_EVENTS_GENERATED")
        demand(report["readiness"] ==
               "SHARED_WRITER_EXTRACTION_REVIEW_BLOCKED" and
               report["execution"] == "HARD_DISABLED",
               "FALSE_IMPORT_READINESS")
        demand(len(report["review_specification_digest_sha256"]) == 64,
               "REVIEW_SPEC_DIGEST_MISSING")
        p = run(arguments(scenario))
        demand(p.returncode == 0 and
               "GC24CG_Z27J_SOURCE_REVIEW=PASS" in p.stdout and
               "DATABASE_CONNECTIONS=0" in p.stdout and
               "DATABASE_WRITES=0" in p.stdout and
               "READINESS=SHARED_WRITER_EXTRACTION_REVIEW_BLOCKED" in p.stdout,
               "CLI_SOURCE_REVIEW_FAILED")
        print(scenario + "_WRITER_BOUNDARY_REVIEW=PASS")
        print(scenario + "_REVIEW_SPEC_DIGEST=" +
              report["review_specification_digest_sha256"])

    for bad in (
        arguments(db="renee_c137_fixture"),
        arguments(db="reneefarms_prod"),
        arguments(farm="wrong-farm"),
        arguments(year=2025),
        arguments(as_of="2026-12-31"),
        arguments(candidate=2024),
        arguments(src="/home/renee/public_html"),
        arguments() + ["--execute"],
        [v for v in arguments() if v != "--dry-run"],
    ):
        demand(run(bad).returncode != 0, "UNSAFE_CLI_ACCEPTED")
    print("Z27J_UNAUTHORIZED_SCOPE_REJECTIONS=PASS")
    print("CERTIFIED_PHP_SOURCE_UNCHANGED=YES")
    print("ORIGINAL_EVENT_DIGESTS_UNCHANGED=YES")
    print("NEW_FIXTURE_EVENTS=NO")
    print("DATABASE_CONNECTIONS=0")
    print("DATABASE_WRITES=0")
    print("SOURCE_WRITES=0")
    print("LOAD_TESTS=0")
    print("GC24CG_Z27J_OFFLINE_VERIFIER=PASS")


if __name__ == "__main__":
    main()
