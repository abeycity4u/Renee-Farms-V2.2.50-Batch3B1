#!/usr/bin/env python3
"""C137 GC24 Z27I: non-executable V2 canonical dependency architecture.

This produces an ordered DESIGN graph, never a fixture, event, SQL statement,
canonical writer invocation, database connection or import authorization.
"""
import argparse
import hashlib
import json
import sys

from c137_gc24_fixture_dryrun import DB, FARM, YEAR, SCENARIOS, build
from c137_gc24_fixture_import_preflight import CERTIFIED_DIGESTS, ImportPreflightError
from c137_gc24_operational_feasibility import analyze as z27h_analyze

EXECUTION_ENABLED = False
DESIGN_VERSION = "C137-GC24-Z27I-DEPENDENCY-0"
AS_OF = "2026-10-11"
HISTORICAL_CANDIDATE = 2025
CERTIFIED_REPORTS_SHA = "322438870263825f9e739a12c4bd478983e67141"
Z27H_DIGESTS = {
    "R1": "baad0d0fcfe63168abba7d44945142ced7dc0b3bee16b732add8b7f738b71b7d",
    "R2": "f156d00323a391233759bd74ad2e5a075916f362206faf82b9f98cd852980cb0",
}
# Each dependency refers to a PRECONDITION, not an executable task. Edge:
# node -> dependencies that must have a valid canonical authority first.
NODES = {
    "calendar_authority": (),
    "tenant_actor_authority": (),
    "cycle_opening_baseline": (
        "calendar_authority", "tenant_actor_authority",
    ),
    "poultry_onboarding_phases": ("cycle_opening_baseline",),
    "inventory_category_item_authority": ("tenant_actor_authority",),
    "priced_inventory_receipts": (
        "calendar_authority", "inventory_category_item_authority",
    ),
    "shared_sale_writer_boundary": ("tenant_actor_authority",),
    "layer_daily_production_and_feed": (
        "poultry_onboarding_phases", "priced_inventory_receipts",
    ),
    "broiler_daily_population_and_feed": (
        "poultry_onboarding_phases", "priced_inventory_receipts",
    ),
    "layer_unsold_egg_pool": ("layer_daily_production_and_feed",),
    "layer_sales": (
        "layer_unsold_egg_pool", "shared_sale_writer_boundary",
    ),
    "layer_automatic_revenue_allocations": ("layer_sales",),
    "broiler_sales": (
        "broiler_daily_population_and_feed", "shared_sale_writer_boundary",
    ),
    "broiler_sale_population_effects": (
        "broiler_sales", "cycle_opening_baseline",
    ),
    "manual_broiler_revenue_allocations": (
        "broiler_sale_population_effects",
    ),
    "nonstock_expense_entries": (
        "calendar_authority", "cycle_opening_baseline",
        "tenant_actor_authority",
    ),
    "expense_shared_allocations": ("nonstock_expense_entries",),
    "consumed_stock_ledger": (
        "layer_daily_production_and_feed",
        "broiler_daily_population_and_feed",
        "priced_inventory_receipts",
    ),
    "used_stock_shared_allocations": ("consumed_stock_ledger",),
    "historical_stock_cost_replay": (
        "consumed_stock_ledger", "priced_inventory_receipts",
    ),
    "reports_eligible_cost_rows": (
        "historical_stock_cost_replay",
        "used_stock_shared_allocations",
        "layer_automatic_revenue_allocations",
        "manual_broiler_revenue_allocations",
        "expense_shared_allocations",
    ),
    "authorized_import_gate": (
        "reports_eligible_cost_rows",
        "calendar_authority",
        "tenant_actor_authority",
    ),
}
# Every major root contract has its own unresolved gate. Downstream nodes
# also inherit blockers; no node is declared ready for execution/import.
OPEN_GATES = {
    "calendar_authority": "2025_UNSELECTED_2026_YEAR_NOT_COMPLETED",
    "tenant_actor_authority": "SYNTHETIC_TENANT_ACTOR_PERMISSION_NOT_RESOLVED",
    "cycle_opening_baseline": "REAL_COHORT_SCHEDULE_AND_OPENING_HEADCOUNT_MISSING",
    "poultry_onboarding_phases": "ACQUISITION_AGE_COST_AND_LIFECYCLE_NOT_DEFINED",
    "inventory_category_item_authority": "CANONICAL_CATEGORY_ITEM_WRITERS_NOT_EXTRACTED",
    "priced_inventory_receipts": "FEED_AND_CONSUMABLE_PRICE_HISTORY_UNVERIFIED",
    "shared_sale_writer_boundary": "CANONICAL_SALES_WRITER_NOT_EXTRACTED",
    "layer_daily_production_and_feed": "DATED_EGG_FLOCK_FEED_FACTS_NOT_MODELED",
    "broiler_daily_population_and_feed": "COHORT_FEED_AND_AGE_FACTS_NOT_MODELED",
    "layer_unsold_egg_pool": "NO_POSTED_DAILY_PRODUCTION_OR_ORDERING_PROOF",
    "layer_sales": "DATE_UNIT_PAYMENT_AND_SALE_PROVENANCE_UNVERIFIED",
    "layer_automatic_revenue_allocations": "CANONICAL_EGG_AUTO_ALLOCATION_NOT_EXECUTED",
    "broiler_sales": "STAGGERED_SALE_HARVEST_TIMELINE_MISSING",
    "broiler_sale_population_effects": "EXPLICIT_CYCLE_HEADCOUNTS_NOT_PROVEN",
    "manual_broiler_revenue_allocations": "MANUAL_BROILER_PARENT_REVISION_POLICY_UNVERIFIED",
    "nonstock_expense_entries": "CATEGORY_REVISION_PERMISSION_NOT_VALIDATED",
    "expense_shared_allocations": "CANONICAL_EXPENSE_ALLOCATIONS_NOT_VALIDATED",
    "consumed_stock_ledger": "SINGLE_FEED_PROVENANCE_AND_BROILER_FEED_MISSING",
    "used_stock_shared_allocations": "CANONICAL_STOCK_ALLOCATIONS_NOT_VALIDATED",
    "historical_stock_cost_replay": "RECEIPT_COST_TO_OUTGOING_REPLAY_UNVERIFIED",
    "reports_eligible_cost_rows": "POSTED_ELIGIBILITY_VS_2048_LIMIT_UNVERIFIED",
    "authorized_import_gate": "EXPLICIT_USER_APPROVAL_FOR_WRITES_ABSENT",
}
AUTHORITIES = {
    "cycle_opening_baseline": "lib/production_cycle_service.php::production_cycle_create_v3",
    "poultry_onboarding_phases": "lib/poultry_cycle_onboarding.php::poultry_cycle_onboarding_record_initial",
    "inventory_category_item_authority": "inventory.php::CATEGORY_ITEM_POST_EXTRACT_PENDING",
    "priced_inventory_receipts": "lib/stock_service.php::stock_apply_movement",
    "layer_daily_production_and_feed": "poultry/layers_daily_record.php::CANONICAL_PAGE_ORCHESTRATION",
    "broiler_daily_population_and_feed": "poultry/broiler_daily_record.php::CANONICAL_PAGE_ORCHESTRATION",
    "layer_unsold_egg_pool": "lib/layer_egg_inventory.php::layer_egg_pool_before_sale",
    "shared_sale_writer_boundary": "management/sales_records.php::SALE_POST_EXTRACT_PENDING",
    "layer_sales": "management/sales_records.php::SALE_POST_EXTRACT_PENDING",
    "layer_automatic_revenue_allocations": "lib/sales_allocation.php::sales_auto_allocate_layer_egg",
    "broiler_sales": "management/sales_records.php::SALE_POST_EXTRACT_PENDING",
    "broiler_sale_population_effects": "lib/sale_population_effects.php::sale_population_effect_sync",
    "manual_broiler_revenue_allocations": "lib/sale_revenue_allocation_persistence.php::sale_revenue_allocation_persistence_apply",
    "nonstock_expense_entries": "lib/poultry_expense_entry.php::poultry_expense_entry_create",
    "expense_shared_allocations": "lib/financial_allocation_persistence.php::financial_allocation_persistence_apply",
    "consumed_stock_ledger": "lib/daily_feed_sync.php::sync_daily_feed_usage_OR_lib/stock_service.php::stock_apply_movement",
    "used_stock_shared_allocations": "lib/stock_consumption_allocation_persistence.php::stock_consumption_allocation_persistence_apply",
    "historical_stock_cost_replay": "lib/stock_costing.php::stock_weighted_cost_replay",
    "reports_eligible_cost_rows": "lib/stock_consumption_economics.php::POSTED_ELIGIBLE_USED_ROWS",
}
NONNEGOTIABLE = {
    "reporting_year_option_2025": "UNSELECTED",
    "reporting_year_option_2026": "DEFER_UNTIL_COMPLETE_AND_APPROVED",
    "legacy_dates_modified": False,
    "egg_allocation_method": "CANONICAL_UNSOLD_EGG_POOL_NO_MANUAL_60_40",
    "broiler_sale_population": "EXPLICIT_HEADCOUNTS_NO_PRODUCT_INFERENCE",
    "stock_used_cost": "CANONICAL_WEIGHTED_RECEIPT_REPLAY",
    "stock_provenance": "EXACTLY_ONE_AUTHORITATIVE_SOURCE_PER_CONSUMPTION",
    "broiler_feed": "UNMODELED_IN_LEGACY",
    "category_item_sale_writer_extraction": "UNRESOLVED",
    "canonical_php_executed": False,
    "new_events_generated": False,
    "import_authorization": "ABSENT",
    "150_vu": "HOLD",
    "200_vu": "PROHIBITED",
}


def topological_order(graph):
    nodes = set(graph)
    if not nodes:
        raise ImportPreflightError("EMPTY_DEPENDENCY_GRAPH")
    if len(OPEN_GATES) != len(nodes) or set(OPEN_GATES) != nodes:
        raise ImportPreflightError("MISSING_BLOCKER_POLICY")
    indegree = {}
    dependents = {node: set() for node in nodes}
    for node, dependencies in graph.items():
        if len(set(dependencies)) != len(dependencies):
            raise ImportPreflightError("DUPLICATED_DEPENDENCY")
        if node in dependencies or not set(dependencies).issubset(nodes):
            raise ImportPreflightError("INVALID_DEPENDENCY_EDGE")
        indegree[node] = len(dependencies)
        for dependency in dependencies:
            dependents[dependency].add(node)
    ready = sorted(node for node in nodes if indegree[node] == 0)
    result = []
    while ready:
        node = ready.pop(0)
        result.append(node)
        for dependent in sorted(dependents[node]):
            indegree[dependent] -= 1
            if indegree[dependent] == 0:
                ready.append(dependent)
        ready.sort()
    if len(result) != len(nodes):
        raise ImportPreflightError("DEPENDENCY_CYCLE")
    return result


def inspect(scenario, original):
    if EXECUTION_ENABLED:
        raise ImportPreflightError("EXECUTION_ENABLED")
    if scenario not in SCENARIOS:
        raise ImportPreflightError("UNKNOWN_SCENARIO")
    prior = z27h_analyze(scenario, original)
    if prior["feasibility_specification_digest_sha256"] != Z27H_DIGESTS[scenario]:
        raise ImportPreflightError("Z27H_DIGEST_DRIFT")
    if prior["legacy_event_digest_sha256"] != CERTIFIED_DIGESTS[scenario]:
        raise ImportPreflightError("LEGACY_STREAM_DIGEST_DRIFT")
    if prior["legacy_year"] != YEAR or prior["candidate_year_selected"]:
        raise ImportPreflightError("DATE_AUTHORITY_DRIFT")
    order = topological_order(NODES)
    if order[-1] != "authorized_import_gate":
        raise ImportPreflightError("UNSAFE_TERMINAL_GATE")
    if (prior["broiler_direct_b_sales"] != 0 or
            prior["broiler_feed_used_rows"] != 0):
        raise ImportPreflightError("LEGACY_CYCLE_OR_FEED_DISTRIBUTION_DRIFT")
    result = {
        "version": DESIGN_VERSION,
        "scenario": scenario,
        "certified_source_sha": CERTIFIED_REPORTS_SHA,
        "legacy_event_digest_sha256": CERTIFIED_DIGESTS[scenario],
        "z27h_specification_digest_sha256": Z27H_DIGESTS[scenario],
        "legacy_event_count": prior["legacy_events"],
        "legacy_year": YEAR,
        "as_of": AS_OF,
        "historical_candidate": HISTORICAL_CANDIDATE,
        "historical_year_selected": False,
        "reporting_year_gate": OPEN_GATES["calendar_authority"],
        "egg_crate_sale_equivalent": prior["egg_crates_sold"],
        "egg_count_sale_equivalent": prior["egg_equivalent_count"],
        "maximum_daily_egg_crate_sales": prior["egg_max_daily_crates"],
        "broiler_head_sales_not_proven_exits": prior["broiler_heads_sold"],
        "maximum_daily_broiler_head_sales": prior["broiler_max_daily_heads"],
        "broiler_direct_a_legacy": prior["broiler_direct_a_sales"],
        "broiler_direct_b_legacy": prior["broiler_direct_b_sales"],
        "broiler_feed_used_legacy": prior["broiler_feed_used_rows"],
        "future_dated_legacy_events": prior["future_events_at_pinned_as_of"],
        "candidate_eligible_stock_rows": SCENARIOS[scenario]["used_stock"],
        "candidate_eligible_stock_rows_verified": False,
        "report_prefetch_cap": 2048,
        "dependency_nodes": len(order),
        "dependency_edges": sum(len(x) for x in NODES.values()),
        "unresolved_node_gates": len(OPEN_GATES),
        "terminal_node": order[-1],
        "execution": "HARD_DISABLED",
        "readiness": "DEPENDENCY_ARCHITECTURE_BLOCKED",
        "new_event_count": 0,
        "new_event_digest_sha256": "NOT_GENERATED",
    }
    model = {
        "summary": result,
        "dependencies": {key: list(NODES[key]) for key in sorted(NODES)},
        "blockers": OPEN_GATES,
        "authorities": AUTHORITIES,
        "nonnegotiable": NONNEGOTIABLE,
        "order": order,
    }
    result["dependency_specification_digest_sha256"] = hashlib.sha256(
        json.dumps(model, sort_keys=True, separators=(",", ":")).encode()
    ).hexdigest()
    return result


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--dry-run", action="store_true", required=True)
    parser.add_argument("--scenario", choices=sorted(SCENARIOS), required=True)
    parser.add_argument("--database", required=True)
    parser.add_argument("--farm-slug", required=True)
    parser.add_argument("--year", type=int, required=True)
    parser.add_argument("--as-of", required=True)
    parser.add_argument("--candidate-year", type=int, required=True)
    args = parser.parse_args(argv)
    if (args.database, args.farm_slug, args.year) != (DB, FARM, YEAR):
        parser.error("Z27I_TARGET_GUARD_BLOCKED")
    if (args.as_of, args.candidate_year) != (AS_OF, HISTORICAL_CANDIDATE):
        parser.error("Z27I_DATE_SCOPE_BLOCKED")
    if EXECUTION_ENABLED:
        parser.error("Z27I_EXECUTION_BLOCKED")
    summary = inspect(args.scenario, build(args.scenario, include_records=True))
    print("GC24CG_Z27I_DEPENDENCY_PLAN=PASS")
    for key, value in summary.items():
        print(key.upper() + "=" + str(value))
    print("CANONICAL_PHP_WRITER_CALLS=0")
    print("DATABASE_CONNECTIONS=0")
    print("DATABASE_WRITES=0")
    print("FILES_WRITTEN=0")
    print("NETWORK_REQUESTS=0")
    print("V2_EVENTS_GENERATED=NO")
    print("IMPORT_EXECUTION_ENABLED=NO")


if __name__ == "__main__":
    main()
