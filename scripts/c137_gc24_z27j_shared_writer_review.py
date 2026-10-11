#!/usr/bin/env python3
"""Z27J: read-only source contract review for proposed shared writers.

Reads only immutable certified PHP files and certified Z23 in-memory plans.
Never executes PHP, creates replacement events or writes files/databases.
"""
import argparse
import hashlib
import json
import re
import sys
from pathlib import Path

from c137_gc24_fixture_dryrun import DB, FARM, YEAR, SCENARIOS, build
from c137_gc24_fixture_import_preflight import CERTIFIED_DIGESTS, ImportPreflightError
from c137_gc24_v2_dependency_contract import inspect as z27i_inspect

EXECUTION_ENABLED = False
VERSION = "C137-GC24-Z27J-SHARED-WRITER-0"
CERTIFIED_SHA = "322438870263825f9e739a12c4bd478983e67141"
SOURCE_ROOT = "/opt/renee-agrisuite-c137-gc"
AS_OF = "2026-10-11"
CANDIDATE_YEAR = 2025
PREVIOUS_SPEC_DIGESTS = {
    "R1": "b5a42222cbe00e127987de1f747e39c06e51b71edee299f290611ef1f204a770",
    "R2": "c9463c26fcbaf434a66c6a5f85cf2309e1fad7962cd6aab41597cb1cea5c80e8",
}
PATHS = (
    "inventory.php",
    "includes/inventory_permission_hardening.php",
    "lib/inventory_financial.php",
    "lib/inventory_category_role.php",
    "lib/stock_service.php",
    "lib/record_reference_persistence.php",
    "management/sales_records.php",
    "lib/sales_units.php",
    "lib/attribution.php",
    "lib/sale_population_effects.php",
    "lib/sales_allocation.php",
    "poultry/layers_daily_record.php",
    "poultry/broiler_daily_record.php",
    "lib/daily_feed_sync.php",
    "lib/daily_population_continuity.php",
    "lib/daily_population_sync.php",
)
GATES = (
    "CATEGORY_CREATE_WRITER_STILL_PAGE_LOCAL",
    "ITEM_CREATE_WRITER_DUPLICATED_IN_TWO_ROUTES",
    "ITEM_TENANT_ROLE_AND_FINANCIAL_POLICY_MUST_BE_SHARED",
    "SALES_CREATE_ORCHESTRATION_STILL_PAGE_LOCAL",
    "SALE_FINANCIAL_AND_PHYSICAL_EFFECTS_MUST_STAY_ATOMIC",
    "SALES_ONLY_RUMINANT_SLAUGHTER_BRANCHES_MUST_NOT_REGRESS",
    "SALES_REFERENCE_PAYMENT_RECEIVABLE_POLICY_MUST_BE_PRESERVED",
    "LAYER_EGG_ALLOCATION_MUST_STAY_AUTOMATIC",
    "LAYER_DAILY_RECORD_WRITE_BOUNDARY_STILL_PAGE_LOCAL",
    "BROILER_DAILY_RECORD_WRITE_BOUNDARY_STILL_PAGE_LOCAL",
    "DAILY_FEED_AND_POPULATION_SIDE_EFFECTS_MUST_STAY_ATOMIC",
    "CANONICAL_DAILY_FEED_AND_MANUAL_STOCK_MUST_NOT_DUPLICATE",
    "REPORTING_YEAR_AND_BIOLOGICAL_ASSUMPTIONS_NOT_APPROVED",
    "CANONICAL_WRITER_EXTRACTION_NOT_IMPLEMENTED",
    "NO_DATABASE_IMPORT_AUTHORIZATION",
)
SCOPE = {
    "category": "EXTRACTION_REVIEW_ONLY",
    "item": "EXTRACTION_REVIEW_ONLY",
    "sales": "EXTRACTION_REVIEW_ONLY",
    "layer_daily": "EXTRACTION_REVIEW_ONLY",
    "broiler_daily": "EXTRACTION_REVIEW_ONLY",
    "certified_php_executed": False,
    "writers_extracted": False,
    "replacement_events": "NOT_GENERATED",
    "date_2025_candidate": "UNSELECTED",
    "date_2026_full_year": "NOT_YET_COMPLETE_AT_PINNED_AS_OF",
    "report_150_vu": "HOLD",
    "report_200_vu": "PROHIBITED",
}


def require(text, pattern, code):
    if re.search(pattern, text, re.IGNORECASE | re.DOTALL) is None:
        raise ImportPreflightError(code)


def subroute(contents, start, end, code):
    a = contents.find(start)
    if a < 0:
        raise ImportPreflightError(code + "_START_MISSING")
    b = contents.find(end, a + len(start))
    if b < 0 or b <= a:
        raise ImportPreflightError(code + "_END_MISSING")
    return contents[a:b]


def ordered_calls(text, names, code):
    locations = []
    for name in names:
        match = re.search(r"\b" + re.escape(name) + r"\s*\(", text)
        if match is None:
            raise ImportPreflightError(code + "_MISSING_" + name.upper())
        locations.append(match.start())
    if locations != sorted(locations) or len(set(locations)) != len(locations):
        raise ImportPreflightError(code + "_ORDER_CHANGED")


def sql_insert_signature(contents, table):
    match = re.search(
        r"\bINSERT\s+INTO\s+" + re.escape(table)
        + r"\s*\(([^)]*)\)\s*VALUES\s*\(([^)]*)\)",
        contents, re.IGNORECASE | re.DOTALL
    )
    if match is None:
        raise ImportPreflightError("INSERT_SIGNATURE_MISSING_" + table.upper())
    return (
        re.sub(r"\s+", "", match.group(1)).lower(),
        re.sub(r"\s+", "", match.group(2)).lower(),
    )


def read_sources(root):
    if str(root) != SOURCE_ROOT or root.is_symlink() or not root.is_dir():
        raise ImportPreflightError("CERTIFIED_SOURCE_ROOT_GUARD")
    contents = {}
    for rel in PATHS:
        file = root / rel
        if not file.is_file() or file.is_symlink():
            raise ImportPreflightError("CERTIFIED_SOURCE_FILE_MISSING")
        contents[rel] = file.read_text(encoding="utf-8")
    return contents


def probe_sources(src):
    if set(src) != set(PATHS):
        raise ImportPreflightError("SOURCE_MANIFEST_DRIFT")

    category = subroute(
        src["inventory.php"],
        "if (isset($_POST['add_category']))",
        "if (isset($_POST['update_category_financial_type']))",
        "CATEGORY_ROUTE"
    )
    require(category, r"\bcanManageInventory\b", "CATEGORY_PERMISSION_ABSENT")
    require(category, r"inventory_financial_classification_is_valid\s*\(",
            "CATEGORY_FINANCIAL_POLICY_ABSENT")
    require(category, r"inventory_category_role_contract_errors\s*\(",
            "CATEGORY_ROLE_POLICY_ABSENT")
    require(category, r"\bINSERT\s+INTO\s+inventory_categories\b",
            "CATEGORY_INSERT_ABSENT")
    require(category, r"\bfarm_id\b", "CATEGORY_TENANCY_ABSENT")
    if len(re.findall(r"\bINSERT\s+INTO\s+inventory_categories\b",
                      category, re.IGNORECASE)) != 1:
        raise ImportPreflightError("CATEGORY_INSERT_MULTIPLIED")

    owner = subroute(
        src["inventory.php"],
        "if (isset($_POST['add_item']))",
        "if (isset($_POST['update_stock']))",
        "OWNER_ADD_ITEM_ROUTE"
    )
    delegated = subroute(
        src["includes/inventory_permission_hardening.php"],
        "function inventory_permission_handle_delegated_add_item(",
        "if (isset($_POST['add_item']))",
        "DELEGATED_ADD_ITEM_ROUTE"
    )
    require(delegated, r"inventory_permission_user_can_access_farm_type\s*\(",
            "DELEGATED_FARM_SCOPE_MISSING")
    require(delegated, r"\bcheckAccess\s*\(\s*'poultry'\s*\)",
            "DELEGATED_SHARED_FARM_ACCESS_MISSING")
    for name, item in (("OWNER", owner), ("DELEGATED", delegated)):
        for expression, detail in (
            (r"inventory_category_item_contract_errors\s*\(", "CATEGORY_CONTRACT"),
            (r"inventory_category_role_item_contract_errors\s*\(", "ROLE_CONTRACT"),
            (r"inventory_category_role_initial_stock_errors\s*\(", "INITIAL_STOCK_ROLE"),
            (r"inventory_category_role_min_stock_errors\s*\(", "MIN_STOCK_ROLE"),
            (r"inventory_normalize_default_production_type\s*\(", "DEFAULT_PRODUCTION"),
            (r"\bINSERT\s+INTO\s+stock_items\b", "ITEM_INSERT"),
            (r"\bstock_apply_movement\s*\(", "LEDGER_RECEIPT"),
            (r"\bbeginTransaction\s*\(", "BEGIN_TXN"),
            (r"\bcommit\s*\(", "COMMIT_TXN"),
            (r"\brollBack\s*\(", "ROLLBACK_TXN"),
            (r"\bcurrent_stock\b", "CURRENT_STOCK_FIELD"),
            (r"\bfinancial_classification\b", "FINANCIAL_SNAPSHOT"),
        ):
            require(item, expression, name + "_" + detail + "_MISSING")
        ordered_calls(item, ("beginTransaction", "stock_apply_movement", "commit"),
                      name + "_LEDGER_ORDER")
    owner_signature = sql_insert_signature(owner, "stock_items")
    delegated_signature = sql_insert_signature(delegated, "stock_items")
    if owner_signature != delegated_signature:
        raise ImportPreflightError("DUPLICATED_ITEM_SQL_SEMANTICS_DIVERGED")
    require(src["includes/inventory_permission_hardening.php"],
            r"inventory_permission_has\s*\(\s*'inventory_add_new_item'",
            "DELEGATED_PERMISSION_GATE_MISSING")

    sale = subroute(
        src["management/sales_records.php"],
        "if (isset($_POST['add_sale']))",
        "} elseif (isset($_POST['record_payment'])",
        "ADD_SALE_ROUTE"
    )
    for expression, detail in (
        (r"\bcanAddSales\b", "SALES_PERMISSION"),
        (r"\bsalesOnlyWorkspace\b", "SALES_ONLY_SCOPE"),
        (r"general_sale_inventory_selection_from_post\s*\(", "GENERAL_STOCK_SOURCE"),
        (r"sales_unit_from_post\s*\(", "UNIT_POLICY"),
        (r"attribution_validate_cycle\s*\(", "CYCLE_TENANCY"),
        (r"attribution_scope\s*\(", "ATTRIBUTION_SCOPE"),
        (r"sale_population_effect_rows_from_post\s*\(", "EXPLICIT_POPULATION_ROWS"),
        (r"\bINSERT\s+INTO\s+sales_records\b", "SALE_INSERT"),
        (r"\bINSERT\s+INTO\s+customer_ledger_entries\b", "RECEIVABLE"),
        (r"ruminant_sale_apply_exit_outcomes\s*\(", "RUMINANT_EXIT"),
        (r"\brollBack\s*\(", "ROLLBACK"),
    ):
        require(sale, expression, detail + "_MISSING")
    ordered_calls(
        sale,
        ("beginTransaction", "record_reference_persistence_insert_new",
         "general_sale_inventory_sync", "slaughter_output_sale_sync",
         "sale_population_effect_sync", "sales_refresh_automatic_allocation",
         "sales_rebuild_layer_egg_allocations", "commit"),
        "SALE_ATOMIC_ORCHESTRATION"
    )
    # Only a complete sale service should ever replace the page route;
    # the call order is important but not a substitute for transaction tests.
    require(src["lib/record_reference_persistence.php"],
            r"function\s+record_reference_persistence_require_transaction\s*\(",
            "REFERENCE_TRANSACTION_GUARD_MISSING")
    require(src["lib/stock_service.php"],
            r"record_reference_persistence_require_transaction\s*\(",
            "LEDGER_TRANSACTION_GUARD_MISSING")
    require(src["lib/sale_population_effects.php"],
            r"\bfunction\s+sale_population_effect_sync\s*\(",
            "PHYSICAL_SALE_SERVICE_MISSING")
    require(src["lib/sales_allocation.php"],
            r"\bfunction\s+sales_auto_allocate_layer_egg\s*\(",
            "EGG_AUTO_ALLOCATION_MISSING")
    require(src["lib/sales_units.php"],
            r"\bfunction\s+sales_unit_from_post\s*\(",
            "SALE_UNIT_AUTHORITY_MISSING")

    for production, rel in (
        ("LAYER", "poultry/layers_daily_record.php"),
        ("BROILER", "poultry/broiler_daily_record.php"),
    ):
        daily = src[rel]
        for expression, detail in (
            (r"\bopeningStock\b", "OPENING_STOCK"),
            (r"daily_population_continuity_expected_opening\s*\(", "EXPECTED_OPENING"),
            (r"daily_population_continuity_apply\s*\(", "CONTINUITY"),
            (r"\bsync_daily_feed_usage\s*\(", "FEED"),
            (r"daily_population_sync_mortality\s*\(", "MORTALITY"),
            (r"\bbeginTransaction\s*\(", "BEGIN_TXN"),
            (r"\bcommit\s*\(", "COMMIT_TXN"),
            (r"\brollBack\s*\(", "ROLLBACK"),
        ):
            require(daily, expression, production + "_" + detail + "_MISSING")
        ordered_calls(
            daily,
            ("beginTransaction", "daily_population_continuity_apply",
             "sync_daily_feed_usage", "daily_population_sync_mortality"),
            production + "_DAILY_ORDER"
        )
    require(src["poultry/layers_daily_record.php"],
            r"\beggProduction\s*>\s*\$openingStock",
            "LAYER_EGG_LIMIT_MISSING")
    require(src["poultry/layers_daily_record.php"],
            r"\bsales_rebuild_layer_egg_allocations\s*\(",
            "LAYER_AUTO_REBUILD_MISSING")
    if re.search(r"\bsales_rebuild_layer_egg_allocations\s*\(",
                 src["poultry/broiler_daily_record.php"]):
        raise ImportPreflightError("BROILER_INCORRECT_EGG_REBUILD")
    require(src["lib/daily_feed_sync.php"],
            r"\bfunction\s+sync_daily_feed_usage\s*\(",
            "CANONICAL_FEED_SYNC_MISSING")
    require(src["lib/daily_feed_sync.php"],
            r"\bstock_apply_movement\s*\(",
            "CANONICAL_FEED_STOCK_DELEGATION_MISSING")
    require(src["lib/attribution.php"],
            r"\bfunction\s+attribution_scope\s*\(",
            "ATTRIBUTION_AUTHORITY_MISSING")
    require(src["lib/inventory_financial.php"],
            r"\bfunction\s+inventory_category_item_contract_errors\s*\(",
            "ITEM_FINANCIAL_AUTHORITY_MISSING")
    require(src["lib/inventory_category_role.php"],
            r"\bfunction\s+inventory_category_role_item_contract_errors\s*\(",
            "ITEM_ROLE_AUTHORITY_MISSING")
    require(src["lib/daily_population_continuity.php"],
            r"\bfunction\s+daily_population_continuity_apply\s*\(",
            "DAILY_CONTINUITY_AUTHORITY_MISSING")
    require(src["lib/daily_population_sync.php"],
            r"\bfunction\s+daily_population_sync_mortality\s*\(",
            "MORTALITY_AUTHORITY_MISSING")
    return {
        "certified_source_files_read": len(src),
        "category_page_local_writer_count": 1,
        "stock_item_page_local_writer_count": 2,
        "stock_item_writer_sql_signature_matches": True,
        "sales_page_local_create_writer_count": 1,
        "sales_side_effects_in_source_order": True,
        "layer_daily_route_orchestration": "PRESENT_NOT_EXTRACTED",
        "broiler_daily_route_orchestration": "PRESENT_NOT_EXTRACTED",
        "source_integrity_static_probes": "PASS",
    }


def review(scenario, original, src):
    if EXECUTION_ENABLED:
        raise ImportPreflightError("EXECUTION_ENABLED")
    if scenario not in SCENARIOS:
        raise ImportPreflightError("UNKNOWN_SCENARIO")
    previous = z27i_inspect(scenario, original)
    if previous["legacy_event_digest_sha256"] != CERTIFIED_DIGESTS[scenario]:
        raise ImportPreflightError("LEGACY_EVENT_DIGEST_DRIFT")
    if previous["readiness"] != "DEPENDENCY_ARCHITECTURE_BLOCKED":
        raise ImportPreflightError("PREVIOUS_READINESS_DRIFT")
    survey = probe_sources(src)
    answer = {
        "review_version": VERSION,
        "scenario": scenario,
        "certified_reports_source_sha": CERTIFIED_SHA,
        "legacy_event_count": previous["legacy_event_count"],
        "legacy_event_digest_sha256": previous["legacy_event_digest_sha256"],
        "z27i_specification_digest_sha256":
            previous["dependency_specification_digest_sha256"],
        "source_checks": survey,
        "unresolved_gate_count": len(GATES),
        "gate_ids": list(GATES),
        "scope": SCOPE,
        "new_event_count": 0,
        "new_event_digest_sha256": "NOT_GENERATED",
        "execution": "HARD_DISABLED",
        "readiness": "SHARED_WRITER_EXTRACTION_REVIEW_BLOCKED",
    }
    answer["review_specification_digest_sha256"] = hashlib.sha256(
        json.dumps(answer, sort_keys=True, separators=(",", ":")).encode()
    ).hexdigest()
    return answer


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--dry-run", action="store_true", required=True)
    parser.add_argument("--scenario", choices=sorted(SCENARIOS), required=True)
    parser.add_argument("--database", required=True)
    parser.add_argument("--farm-slug", required=True)
    parser.add_argument("--year", type=int, required=True)
    parser.add_argument("--as-of", required=True)
    parser.add_argument("--candidate-year", type=int, required=True)
    parser.add_argument("--certified-source-root", required=True)
    args = parser.parse_args(argv)
    if (args.database, args.farm_slug, args.year) != (DB, FARM, YEAR):
        parser.error("Z27J_ORIGINAL_SCOPE_GUARD")
    if (args.as_of, args.candidate_year) != (AS_OF, CANDIDATE_YEAR):
        parser.error("Z27J_DATE_SCOPE_GUARD")
    if args.certified_source_root != SOURCE_ROOT or EXECUTION_ENABLED:
        parser.error("Z27J_SOURCE_OR_EXECUTION_GUARD")
    report = review(args.scenario, build(args.scenario, include_records=True),
                    read_sources(Path(args.certified_source_root)))
    print("GC24CG_Z27J_SOURCE_REVIEW=PASS")
    fields = (
        "scenario", "legacy_event_count", "legacy_event_digest_sha256",
        "z27i_specification_digest_sha256", "source_checks",
        "unresolved_gate_count", "new_event_count", "new_event_digest_sha256",
        "review_specification_digest_sha256", "readiness", "execution",
    )
    for name in fields:
        value = report[name]
        if isinstance(value, dict):
            value = json.dumps(value, sort_keys=True, separators=(",", ":"))
        print(name.upper() + "=" + str(value))
    print("CERTIFIED_SOURCE_READ_ONLY=YES")
    print("CANONICAL_PHP_EXECUTED=NO")
    print("DATABASE_CONNECTIONS=0")
    print("DATABASE_WRITES=0")
    print("SOURCE_WRITES=0")
    print("V2_EVENTS_GENERATED=NO")
    print("IMPORT_EXECUTION_ENABLED=NO")


if __name__ == "__main__":
    main()
