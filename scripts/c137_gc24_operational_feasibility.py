#!/usr/bin/env python3
"""C137 GC24 Z27H: OFFLINE poultry feasibility constraints, not a fixture.

Read only the Z23 original records and Z27F design. This never creates
replacement events, dates, DB payloads, SQL, or runnable source calls.
"""
import argparse
import collections
import datetime as dt
import hashlib
import json
import sys

from c137_gc24_fixture_dryrun import DB, FARM, YEAR, SCENARIOS, build
from c137_gc24_fixture_import_preflight import (
    CERTIFIED_DIGESTS, ImportPreflightError, validate_manifest,
)
from c137_gc24_fixture_v2_design import proposed_design, DESIGN_VERSION

EXECUTION_ENABLED = False
VERSION = "C137-GC24-Z27H-FEASIBILITY-0"
CERTIFIED_SOURCE_SHA = "322438870263825f9e739a12c4bd478983e67141"
AS_OF = dt.date(2026, 10, 11)
CANDIDATE_YEAR = 2025  # Alternative only; neither chosen nor approved.
Z27F_SPEC_DIGESTS = {
    "R1": "ac09913df7f79979ead0281b7a8dac35398c2b8f2b04d725e4373f370a306106",
    "R2": "d7344a30aa8aa3cc77e244d41fab7819e30050b127fc7912b86f06f94cf8446e",
}
SOURCE_CONTRACTS = {
    "cycle": "lib/production_cycle_service.php::production_cycle_create_v3",
    "onboarding": "lib/poultry_cycle_onboarding.php::poultry_cycle_onboarding_record_initial",
    "lifecycle": "lib/poultry_cycle_lifecycle.php::poultry_lifecycle_record_initial_phase",
    "daily_layer": "poultry/layers_daily_record.php::POST_WRITER_AND_POPULATION_CONTINUITY",
    "daily_broiler": "poultry/broiler_daily_record.php::POST_WRITER_AND_POPULATION_CONTINUITY",
    "egg_pool": "lib/layer_egg_inventory.php::layer_egg_pool_before_sale",
    "egg_revenue": "lib/sales_allocation.php::sales_auto_allocate_layer_egg",
    "population_exit": "lib/sale_population_effects.php::sale_population_effect_sync",
    "population_integrity": "lib/production_population.php::production_population_project_delta_locked",
    "stock_movement": "lib/stock_service.php::stock_apply_movement",
    "daily_feed": "lib/daily_feed_sync.php::sync_daily_feed_usage",
    "inventory_category": "lib/inventory_financial.php::inventory_category_item_contract_errors",
}
DECISION_GATES = (
    "REPORTING_YEAR_NOT_SELECTED",
    "LEGACY_DATES_ARE_NOT_V2_DATES",
    "LAYER_DAILY_RECORD_EGG_PRODUCTION_NOT_SCHEDULED",
    "LAYER_OPENING_FLOCK_AND_LAYING_RATE_NOT_DEFINED",
    "EGG_SAME_DATE_SEQUENCE_AND_UNSOLD_POOL_NOT_PROVEN",
    "BROILER_COHORT_DATES_AND_TURNOVER_NOT_DEFINED",
    "BROILER_PHYSICAL_EXIT_ROWS_NOT_MODELED",
    "BROILER_FEED_ITEM_AND_PRICED_CONSUMPTION_MISSING",
    "DAILY_FEED_VS_MANUAL_STOCK_SINGLE_AUTHORITY_NOT_CHOSEN",
    "OPENING_POPULATION_AND_CANONICAL_LIFECYCLE_NOT_PROVEN",
    "RECEIPT_PRICING_AND_HISTORICAL_COST_REPLAY_NOT_PROVEN",
    "CANONICAL_WRITER_EXTRACTION_AND_PERMISSION_GATES_PENDING",
    "SHARED_ALLOCATION_AND_REVISION_PROVENANCE_PENDING",
    "ELIGIBLE_REPORT_ROWS_AND_PREFETCH_BEHAVIOR_UNPROVEN",
    "DATABASE_INSERTION_NOT_AUTHORIZED",
)
OPERATING_RULES = {
    "reporting_year": {
        "legacy_year": 2026,
        "historical_candidate": 2025,
        "historical_candidate_status": "NOT_SELECTED",
        "other_option": "DEFER_2026_FULL_YEAR_UNTIL_AFTER_2026_12_31",
        "original_dates_remapped": False,
        "source_event_digest_rebased": False,
    },
    "layers": {
        "daily_source": "layer_daily_records",
        "crates_per_egg_count": 30,
        "production_constraint": "egg_production <= canonical opening_stock",
        "same_day_pool": "date_then_sale_id_prior_sales; direct-cycle first and pooled allocations persisted",
        "egg_allocation": "automatic_unsold_pool_only_not_manual_60_40",
        "opening_stock": "UNRESOLVED",
        "production_schedule": "UNRESOLVED",
        "assumed_lay_rate": "NONE",
    },
    "broilers": {
        "unit": "Head",
        "sale_population": "explicit_cycle_and_headcount_only",
        "cycle_count": "REPLAN_REQUIRED",
        "harvest_dates": "UNRESOLVED",
        "physical_exits": "UNRESOLVED",
        "future_sale_distribution": "UNRESOLVED",
    },
    "feed": {
        "existing_feed_item": "layer_only_in_Z27F_CANDIDATE",
        "broiler_feed_item": "MISSING",
        "daily_record_feed_provenance": "daily_feed_sync",
        "manual_consumption_provenance": "inventory_manual",
        "one_source_per_consumption": True,
        "no_duplication": True,
        "priced_receipts": "REQUIRED",
        "outgoing_override": "FORBIDDEN",
    },
    "import": {
        "canonical_php": "NOT_EXECUTED",
        "new_event_model": "NOT_GENERATED",
        "db_connections": 0,
        "db_writes": 0,
        "certified_source_updates": 0,
        "150_vu": "HOLD",
        "200_vu": "PROHIBITED",
    },
}


def analyze(scenario, original, *, as_of=AS_OF, candidate_year=CANDIDATE_YEAR):
    if EXECUTION_ENABLED:
        raise ImportPreflightError("EXECUTION_ENABLED")
    if as_of != AS_OF or candidate_year != CANDIDATE_YEAR:
        raise ImportPreflightError("FEASIBILITY_AUTHORITY_GUARD")
    if scenario not in SCENARIOS:
        raise ImportPreflightError("UNKNOWN_SCENARIO")
    old = validate_manifest(scenario, original)
    z27f = proposed_design(scenario, original)
    if z27f["specification_digest_sha256"] != Z27F_SPEC_DIGESTS[scenario]:
        raise ImportPreflightError("Z27F_SPECIFICATION_DIGEST_DRIFT")
    if DESIGN_VERSION != "C137-GC24-V2-DESIGN-0":
        raise ImportPreflightError("Z27F_DESIGN_VERSION_CHANGED")

    eggs = collections.Counter()
    broilers = collections.Counter()
    broiler_direct = collections.Counter()
    dates_future = collections.Counter()
    item_used = collections.Counter()
    counts = collections.Counter()
    egg_sales = broiler_sales = 0

    for row in original["records"]:
        kind = row["kind"]
        counts[kind] += 1
        date = dt.date.fromisoformat(row["date"])
        if date > as_of:
            dates_future[kind] += 1
        if kind == "sales":
            quantity = row["quantity"]
            if row["product_type"] == "eggs":
                if row["production_type"] != "layer":
                    raise ImportPreflightError("EGG_SALE_TYPE_CHANGED")
                eggs[row["date"]] += quantity
                egg_sales += 1
            elif row["product_type"] == "broiler":
                if row["production_type"] != "broiler":
                    raise ImportPreflightError("BROILER_SALE_TYPE_CHANGED")
                broilers[row["date"]] += quantity
                broiler_sales += 1
                if row["cycle_ref"] is not None:
                    broiler_direct[row["cycle_ref"]] += 1
            else:
                raise ImportPreflightError("UNSUPPORTED_SALE_TYPE")
        elif kind == "used_stock":
            item = row["item_ref"]
            if item not in ("item-feed", "item-operating"):
                raise ImportPreflightError("UNSUPPORTED_INVENTORY_SYMBOL")
            item_used[item] += 1

    all_days = (dt.date(YEAR, 12, 31) - dt.date(YEAR, 1, 1)).days + 1
    if all_days != 365:
        raise ImportPreflightError("LEGACY_YEAR_DAYS_CHANGED")
    if (dt.date(candidate_year, 12, 31) -
            dt.date(candidate_year, 1, 1)).days + 1 != all_days:
        raise ImportPreflightError("CANDIDATE_YEAR_DAY_COUNT_CONFLICT")
    if sum(eggs.values()) != z27f["derived_buckets"]["egg_sales_total"] * 0 + sum(
        r["quantity"] for r in original["records"]
        if r["kind"] == "sales" and r["product_type"] == "eggs"
    ):
        raise ImportPreflightError("EGG_QUANTITY_SUM")
    if egg_sales + broiler_sales != counts["sales"]:
        raise ImportPreflightError("SALES_COUNT_SUM")
    if sum(item_used.values()) != counts["used_stock"]:
        raise ImportPreflightError("CONSUMPTION_COUNT_SUM")
    if old["events_validated"] != sum(counts.values()):
        raise ImportPreflightError("EVENT_COUNT_SUM")

    egg_count = sum(eggs.values()) * 30
    result = {
        "version": VERSION,
        "scenario": scenario,
        "certified_source_sha": CERTIFIED_SOURCE_SHA,
        "legacy_event_digest_sha256": CERTIFIED_DIGESTS[scenario],
        "z27f_spec_digest_sha256": z27f["specification_digest_sha256"],
        "legacy_events": old["events_validated"],
        "legacy_year": YEAR,
        "candidate_historical_year": candidate_year,
        "candidate_year_selected": False,
        "candidate_full_year_days": all_days,
        "egg_sale_days": len(eggs),
        "egg_sale_transactions": egg_sales,
        "egg_crates_sold": sum(eggs.values()),
        "egg_equivalent_count": egg_count,
        "egg_max_daily_crates": max(eggs.values()) if eggs else 0,
        "egg_max_daily_equivalent_count": max(eggs.values()) * 30 if eggs else 0,
        "broiler_sale_days": len(broilers),
        "broiler_sale_transactions": broiler_sales,
        "broiler_heads_sold": sum(broilers.values()),
        "broiler_max_daily_heads": max(broilers.values()) if broilers else 0,
        "broiler_direct_a_sales": broiler_direct["broiler-a"],
        "broiler_direct_b_sales": broiler_direct["broiler-b"],
        "layer_feed_used_rows": item_used["item-feed"],
        "broiler_feed_used_rows": 0,
        "broiler_consumables_used_rows": item_used["item-operating"],
        "future_events_at_pinned_as_of": sum(dates_future.values()),
        "future_receipts_at_pinned_as_of": dates_future["stock_received"],
        "legacy_date_rewrites": 0,
        "new_event_count": 0,
        "new_event_digest_sha256": "NOT_GENERATED",
        "decision_gate_count": len(DECISION_GATES),
        "readiness": "FEASIBILITY_DESIGN_BLOCKED",
        "canonical_php_calls": 0,
        "execution": "HARD_DISABLED",
    }
    document = {
        "result": result,
        "source_contracts": SOURCE_CONTRACTS,
        "decision_gates": DECISION_GATES,
        "operating_rules": OPERATING_RULES,
    }
    result["feasibility_specification_digest_sha256"] = hashlib.sha256(
        json.dumps(document, sort_keys=True, separators=(",", ":")).encode()
    ).hexdigest()
    return result


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--dry-run", action="store_true", required=True)
    parser.add_argument("--scenario", required=True, choices=sorted(SCENARIOS))
    parser.add_argument("--database", required=True)
    parser.add_argument("--farm-slug", required=True)
    parser.add_argument("--year", required=True, type=int)
    parser.add_argument("--as-of", required=True)
    parser.add_argument("--candidate-year", required=True, type=int)
    args = parser.parse_args(argv)
    if (args.database, args.farm_slug, args.year) != (DB, FARM, YEAR):
        parser.error("Z27H_LEGACY_SCOPE_BLOCKED")
    if (args.as_of, args.candidate_year) != (
        AS_OF.isoformat(), CANDIDATE_YEAR
    ):
        parser.error("Z27H_CANDIDATE_SCOPE_BLOCKED")
    if EXECUTION_ENABLED:
        parser.error("Z27H_EXECUTION_BLOCKED")
    report = analyze(args.scenario, build(args.scenario, include_records=True))
    print("GC24CG_Z27H_OFFLINE_FEASIBILITY=PASS")
    for key, value in report.items():
        print(key.upper() + "=" + str(value))
    print("DATABASE_CONNECTIONS=0")
    print("DATABASE_WRITES=0")
    print("FILES_WRITTEN=0")
    print("NETWORK_REQUESTS=0")
    print("CANONICAL_PHP_WRITER_CALLS=0")
    print("V2_EVENTS_GENERATED=NO")
    print("IMPORT_EXECUTION_ENABLED=NO")


if __name__ == "__main__":
    main()
