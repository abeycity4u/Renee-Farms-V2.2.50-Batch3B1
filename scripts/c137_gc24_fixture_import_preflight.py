#!/usr/bin/env python3
"""C137 GC24 Z26: isolated synthetic import manifest PRE-FLIGHT, no writes.

All records exist in memory. No import SQL is emitted, no credentials or
network are used, and --execute is deliberately unsupported. Canonical PHP
writers must be integrated and audited before a future separate authorization.
"""
import argparse
import collections
import datetime as dt
import json
import re
import sys

from c137_gc24_fixture_dryrun import (
    DB, FARM, YEAR, SCENARIOS, CYCLES, ITEMS, build
)

# Digests certified by the independent Z23A run. Changing the event model
# requires a separately reviewed new version, not an implicit rebaseline.
CERTIFIED_DIGESTS = {
    "R1": "e31421ee40ce25b16feb698313fcf337e74126a8e63d8c5dadeedf74e30165f2",
    "R2": "985341ed4315788ee8f3b9d62b327da66d38fc63ea16d406c5c52bc9949d3dfe",
}
KINDS = frozenset({
    "cycle", "category", "item", "sales", "expenses",
    "stock_received", "used_stock", "sales_allocation",
    "expense_allocation", "stock_allocation",
})
PARENTS = {
    "sales_allocation": "sales",
    "expense_allocation": "expenses",
    "stock_allocation": "used_stock",
}
CYCLE_TYPE = {
    "layer-a": "layer", "layer-b": "layer",
    "broiler-a": "broiler", "broiler-b": "broiler",
}
# Canonical integrations discovered from the source; do not substitute direct
# INSERTs for service-owned validation, provenance or population updates.
CANONICAL_CONTRACTS = {
    "cycle": "production_cycle_create_v3",
    "sales_allocation": "sale_revenue_allocation_persistence_apply",
    "expense_allocation": "financial_allocation_service_validate_desired_rows",
    "stock_allocation": "stock_consumption_allocation_persistence_apply",
}
EXECUTION_ENABLED = False


class ImportPreflightError(ValueError):
    pass


def require(ok, error):
    if not ok:
        raise ImportPreflightError(error)


def validate_manifest(scenario, manifest):
    require(scenario in SCENARIOS, "UNKNOWN_SCENARIO")
    require(manifest.get("database") == DB, "DATABASE_SCOPE")
    require(manifest.get("farm_slug") == FARM, "TENANT_SCOPE")
    require(manifest.get("year") == YEAR, "REPORTING_YEAR")
    require(
        manifest.get("event_digest_sha256") == CERTIFIED_DIGESTS[scenario],
        "CERTIFIED_EVENT_DIGEST"
    )
    require(
        manifest.get("financial_reconciliation") == "PASS_OFFLINE_MODEL",
        "OFFLINE_FINANCIAL_RECONCILIATION"
    )
    require(
        manifest.get("data_insertion") == "PROHIBITED",
        "INSERTION_GUARD"
    )
    require(
        manifest.get("canonical_business_validation") == "PENDING",
        "CANONICAL_VALIDATION_NOT_MISREPRESENTED"
    )
    records = manifest.get("records")
    require(isinstance(records, list), "NO_EVENT_STREAM")
    require(len(records) < 30000, "EVENT_CAP")

    unique = set()
    parents = {}
    cycles = {}
    categories = set()
    items = {}
    allocated = collections.defaultdict(list)
    stocks = collections.Counter()
    money_month = collections.defaultdict(collections.Counter)
    money_year = collections.Counter()
    counted = collections.Counter()
    received = collections.Counter()
    consumed = collections.Counter()

    for event in records:
        require(isinstance(event, dict), "BAD_EVENT")
        kind = event.get("kind")
        ref = event.get("ref")
        date = event.get("date")
        require(kind in KINDS, "INVALID_EVENT_KIND")
        require(
            isinstance(ref, str) and bool(re.fullmatch(r"[A-Za-z0-9_./-]{1,120}", ref)),
            "INVALID_EVENT_REF"
        )
        require(
            isinstance(date, str) and len(date) == 10,
            "INVALID_EVENT_DATE"
        )
        try:
            parsed = dt.date.fromisoformat(date)
        except ValueError as exc:
            raise ImportPreflightError("INVALID_EVENT_DATE") from exc
        require(parsed.year == YEAR, "OUTSIDE_REPORTING_YEAR")
        key = (kind, ref)
        require(key not in unique, "DUPLICATE_EVENT_REFERENCE")
        unique.add(key)
        counted[kind] += 1

        if kind == "cycle":
            require(ref in CYCLES and event.get("farm_type") == "poultry", "CYCLE_SCOPE")
            require(event.get("production_type") == CYCLE_TYPE[ref], "CYCLE_TYPE")
            require(event.get("livestock_type_id") is None, "UNKNOWN_LIVESTOCK_TYPE")
            require(event.get("valid_by_canonical_service") == "NOT_VERIFIED",
                    "CYCLE_CANONICAL_VALIDATION_NOT_MISREPRESENTED")
            cycles[ref] = event
        elif kind == "category":
            require(event.get("farm_type") == "poultry", "CATEGORY_FARM_TYPE")
            categories.add(ref)
        elif kind == "item":
            require(event.get("category_ref") in categories, "ITEM_CATEGORY_FK")
            require(event.get("farm_type") == "poultry", "ITEM_FARM_SCOPE")
            items[ref] = event
        elif kind in PARENTS:
            require(
                isinstance(event.get("parent_ref"), str)
                and isinstance(event.get("cycle_ref"), str),
                "ALLOCATION_MISSING_KEYS"
            )
            allocated[(PARENTS[kind], event["parent_ref"])].append(event)
        else:
            if kind in ("sales", "expenses", "used_stock"):
                value = event.get("amount_cents")
                require(type(value) is int and value >= 0, "INVALID_AMOUNT")
                money_month[date[:7]][kind] += value
                money_year[kind] += value
                parents[(kind, ref)] = event
                require(event.get("farm_type") == "poultry", "PARENT_FARM_TYPE")
                require(event.get("attribution_scope") in ("cycle", "farm"),
                        "PARENT_ATTRIBUTION_SCOPE")
                if event["attribution_scope"] == "farm":
                    require(event.get("cycle_ref") is None, "FARM_PARENT_DIRECT_CYCLE")
                else:
                    cycle = event.get("cycle_ref")
                    require(cycle in cycles, "PARENT_CYCLE_MISSING")
                    if kind != "used_stock":
                        require(CYCLE_TYPE[cycle] == event.get("production_type"),
                                "PARENT_CYCLE_TYPE_MISMATCH")
            if kind == "sales":
                quantity = event.get("quantity")
                price = event.get("unit_price_cents")
                require(type(quantity) is int and quantity > 0 and
                        type(price) is int and price > 0, "SALE_QUANTITY_PRICE")
                require(quantity * price == event["amount_cents"], "GENERATED_TOTAL_MISMATCH")
                require(event.get("product_type") in ("eggs", "broiler"),
                        "SALE_PRODUCT_TYPE")
            elif kind == "expenses":
                require(event.get("unit") == 1, "EXPENSE_UNIT")
            elif kind == "stock_received":
                item = event.get("item_ref")
                quantity = event.get("quantity")
                require(item in items and type(quantity) is int and quantity > 0,
                        "RECEIPT_STOCK_ITEM_FK")
                require(event.get("type") == "received", "RECEIPT_DIRECTION")
                stocks[item] += quantity
                received[item] += quantity
            elif kind == "used_stock":
                item = event.get("item_ref")
                quantity = event.get("quantity")
                require(item in items and type(quantity) is int and quantity > 0,
                        "USED_STOCK_ITEM_FK")
                require(event.get("transaction_type") == "used" and
                        event.get("is_reversed") == 0,
                        "USED_STOCK_EFFECTIVE")
                stocks[item] -= quantity
                consumed[item] += quantity
                require(stocks[item] >= 0, "NEGATIVE_STOCK")

    require(set(cycles) == set(CYCLES), "MISSING_CYCLE")
    require(categories == {"cat-" + x for x in ITEMS}, "MISSING_CATEGORY")
    require(set(items) == {"item-" + x for x in ITEMS}, "MISSING_ITEM")
    require(len(money_month) == 12, "MISSING_REPORTING_MONTH")
    require(dict(money_year) == manifest["annual_totals_cents"],
            "ANNUAL_FINANCIAL_TOTAL_MISMATCH")
    require(
        {name: stocks["item-" + name] for name in ITEMS}
        == manifest["remaining_stock_units"], "STOCK_BALANCE_MISMATCH"
    )
    for name in ITEMS:
        require(
            received["item-" + name] - consumed["item-" + name]
            == stocks["item-" + name],
            "STOCK_CONSERVATION"
        )

    allocated_totals = collections.Counter()
    for (parent_kind, ref), children in allocated.items():
        parent = parents.get((parent_kind, ref))
        require(parent is not None, "ALLOCATION_ORPHAN_PARENT")
        require(parent["attribution_scope"] == "farm", "ALLOCATING_CYCLE_PARENT")
        require(len(children) == 2, "ALLOCATION_TARGET_COUNT")
        require({x["percent"] for x in children} == {60, 40},
                "ALLOCATION_PERCENTAGES")
        require(sum(x["percent"] for x in children) == 100,
                "ALLOCATION_PERCENT_CONSERVATION")
        require(all(
            type(x.get("amount_cents")) is int and x["amount_cents"] >= 0
            and x["date"] == parent["date"]
            for x in children
        ), "ALLOCATION_VALUE_OR_DATE")
        require(
            sum(x["amount_cents"] for x in children) == parent["amount_cents"],
            "ALLOCATION_AMOUNT_CONSERVATION"
        )
        require(len({x["cycle_ref"] for x in children}) == 2,
                "DUPLICATE_ALLOCATION_CYCLE")
        for child in children:
            cycle = child["cycle_ref"]
            require(cycle in cycles, "ALLOCATION_UNKNOWN_CYCLE")
            if parent_kind != "used_stock":
                require(CYCLE_TYPE[cycle] == parent["production_type"],
                        "ALLOCATION_CYCLE_TYPE_MISMATCH")
            allocated_totals[child["kind"]] += child["amount_cents"]

    for (kind, ref), parent in parents.items():
        if parent.get("attribution_scope") == "farm":
            require((kind, ref) in allocated, "UNALLOCATED_SHARED_PARENT")
        else:
            require((kind, ref) not in allocated, "DIRECT_PARENT_ALLOCATED")

    require(dict(counted) == manifest["record_counts"],
            "RECORD_COUNT_MISMATCH")
    require(dict(allocated_totals) == manifest["planned_allocated_child_cents"],
            "ALLOCATED_TOTAL_MISMATCH")
    require(len(records) == sum(counted.values()), "RECORD_LOSS")

    return {
        "scenario": scenario,
        "event_digest_sha256": manifest["event_digest_sha256"],
        "events_validated": len(records),
        "parents_validated": len(parents),
        "allocation_parent_groups_validated": len(allocated),
        "allocation_rows_validated": sum(
            counted[k] for k in PARENTS
        ),
        "farm_scoped": True,
        "financial_reconciliation": "PASS_OFFLINE",
        "referential_contract": "PASS_OFFLINE",
        "stock_conservation": "PASS_OFFLINE",
        "schema_required_writer_field_mapping": "PENDING",
        "canonical_service_execution": "NOT_EXECUTED",
        "canonical_writer_integration": "PENDING",
        "backup_restore_z25b_evidence": "USER_CONFIRMED_NOT_REVERIFIED",
        "execution": "DISABLED",
    }


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--dry-run", action="store_true", required=True)
    parser.add_argument("--scenario", choices=sorted(SCENARIOS), required=True)
    parser.add_argument("--database", required=True)
    parser.add_argument("--farm-slug", required=True)
    parser.add_argument("--year", type=int, required=True)
    args = parser.parse_args(argv)
    if (args.database, args.farm_slug, args.year) != (DB, FARM, YEAR):
        parser.error("C137_IMPORT_TARGET_GUARD_BLOCKED")
    if EXECUTION_ENABLED:
        parser.error("EXECUTION_GUARD_MUST_REMAIN_FALSE")
    result = validate_manifest(args.scenario, build(args.scenario, include_records=True))
    print("C137_GC24CG_Z26_IMPORT_PREFLIGHT=PASS")
    for key, value in result.items():
        print(key.upper() + "=" + str(value))
    print("DATABASE_CONNECTIONS=0")
    print("DATABASE_WRITES=0")
    print("FILES_WRITTEN=0")
    print("NETWORK_REQUESTS=0")
    print("K6_EXECUTED=NO")


if __name__ == "__main__":
    try:
        main()
    except ImportPreflightError as exc:
        print("C137_GC24CG_Z26_IMPORT_PREFLIGHT=BLOCKED")
        print("BLOCKER=" + str(exc))
        sys.exit(1)
