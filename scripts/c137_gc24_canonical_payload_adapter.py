#!/usr/bin/env python3
"""C137 GC24 Z27D: canonical writer INTENT adapter, OFFLINE ONLY.

The source model and its certified SHA digests are immutable. These are
symbolic, incomplete call previews, not executable payloads or SQL.
No credentials, database, network, filesystem changes or PHP invocation.
"""
import argparse
import collections
import datetime as dt
import json
import sys

from c137_gc24_fixture_dryrun import DB, FARM, YEAR, SCENARIOS, build
from c137_gc24_fixture_import_preflight import (
    CERTIFIED_DIGESTS, ImportPreflightError, validate_manifest
)

EXECUTION_ENABLED = False
CANONICAL_SOURCE_SHA = "322438870263825f9e739a12c4bd478983e67141"
OWNERS = {
    "cycle": "lib/production_cycle_service.php::production_cycle_create_v3",
    "category": "inventory.php::add_category_POST_NOT_CALLABLE",
    "item": "inventory.php::add_item_POST_NOT_CALLABLE",
    "sales": "management/sales_records.php::add_sale_POST_NOT_CALLABLE",
    "expenses": "lib/poultry_expense_entry.php::poultry_expense_entry_create",
    "stock_received": "lib/stock_service.php::stock_apply_movement",
    "used_stock": "lib/stock_service.php::stock_apply_movement",
    "sales_allocation": "lib/sale_revenue_allocation_persistence.php::sale_revenue_allocation_persistence_apply",
    "expense_allocation": "lib/financial_allocation_persistence.php::financial_allocation_persistence_apply",
    "stock_allocation": "lib/stock_consumption_allocation_persistence.php::stock_consumption_allocation_persistence_apply",
}
ALLOCATION_PARENTS = {
    "sales_allocation": "sales",
    "expense_allocation": "expenses",
    "stock_allocation": "used_stock",
}


def money(cents):
    if type(cents) is not int or cents < 0:
        raise ImportPreflightError("INVALID_CENTS")
    return str(cents // 100) + "." + str(cents % 100).zfill(2)


def expected_scope(event):
    if event.get("cycle_ref") is not None:
        return "cycle"
    if event.get("production_type") == "shared":
        return "farm"
    return "production_type"


def adapt_event(event, parents, as_of):
    """Map only known facts; never supply a fabricated farm ID or actor ID."""
    kind = event["kind"]
    ref = event["ref"]
    issues = set()
    args = {}
    if kind == "cycle":
        args = {
            "cycle_code": ref, "farm_type": event["farm_type"],
            "production_choice": event["production_type"],
            "start_date": event["date"],
        }
        if event.get("status") != "active":
            issues.add("CYCLE_STATUS_MODEL_CONFLICT")
        issues.add("CYCLE_OPENING_HEADCOUNT_MISSING")
        issues.add("CYCLE_CODE_NAMESPACE_NOT_APPROVED")
    elif kind == "category":
        args = {"category_name": ref, "category_farm_type": event["farm_type"]}
        issues.update((
            "CATEGORY_FINANCIAL_TYPE_MISSING", "CATEGORY_ROLE_MISSING",
            "CATEGORY_DOMAIN_WRITER_NOT_EXTRACTED",
        ))
    elif kind == "item":
        args = {
            "item_name": ref, "category_ref": event["category_ref"],
            "farm_type": event["farm_type"],
        }
        issues.update((
            "ITEM_UNIT_MISSING", "ITEM_FEED_USAGE_MISSING",
            "ITEM_DEFAULT_PRODUCTION_MISSING",
            "ITEM_DOMAIN_WRITER_NOT_EXTRACTED",
        ))
    elif kind == "sales":
        args = {
            "sale_date": event["date"], "farm_type": event["farm_type"],
            "production_type": event["production_type"],
            "cycle_ref": event["cycle_ref"], "product_type": event["product_type"],
            "quantity": event["quantity"],
            "unit_price": money(event["unit_price_cents"]),
        }
        issues.update((
            "SALE_DOMAIN_WRITER_NOT_EXTRACTED", "SALE_UNIT_MISSING",
            "SALE_PAYMENT_POLICY_UNRESOLVED",
        ))
        if event["attribution_scope"] != expected_scope(event):
            issues.add("SOURCE_ATTRIBUTION_SCOPE_CONFLICT")
        if event["product_type"] == "eggs":
            issues.add("LAYER_EGG_DAILY_PRODUCTION_ABSENT")
        elif event["product_type"] == "broiler":
            issues.add("BROILER_POPULATION_EFFECT_UNRESOLVED")
    elif kind == "expenses":
        args = {
            "expense_date": event["date"],
            "production_type": event["production_type"],
            "cycle_ref": event["cycle_ref"],
            "amount": money(event["amount_cents"]),
            "unit": event["unit"],
        }
        issues.add("EXPENSE_NON_STOCK_CATEGORY_MISSING")
        if event["attribution_scope"] != expected_scope(event):
            issues.add("SOURCE_ATTRIBUTION_SCOPE_CONFLICT")
    elif kind == "stock_received":
        args = {
            "transaction_date": event["date"],
            "item_ref": event["item_ref"],
            "type": "received", "quantity": event["quantity"],
        }
        issues.update((
            "RECEIPT_UNIT_COST_MISSING", "STOCK_SOURCE_POLICY_UNRESOLVED",
        ))
        if dt.date.fromisoformat(event["date"]) > as_of:
            issues.add("FUTURE_RECEIPT_REJECTED_BY_UI")
    elif kind == "used_stock":
        args = {
            "transaction_date": event["date"],
            "item_ref": event["item_ref"],
            "type": "used", "quantity": event["quantity"],
            "cycle_ref": event["cycle_ref"],
        }
        issues.update((
            "STOCK_SOURCE_POLICY_UNRESOLVED",
            "STOCK_PRODUCTION_ATTRIBUTION_UNRESOLVED",
            "STOCK_HISTORICAL_COST_UNVERIFIED",
        ))
        if event["financial_classification"] == "operating":
            issues.add("OPERATING_IS_NOT_A_FINANCIAL_CLASSIFICATION")
        # Modeled amount is intentionally NOT a stock_apply_movement argument.
    elif kind in ALLOCATION_PARENTS:
        args = {
            "parent_ref": event["parent_ref"],
            "desired_row": {
                "cycle_ref": event["cycle_ref"],
                "allocated_amount": money(event["amount_cents"]),
            },
        }
        issues.add("SYMBOLIC_PARENT_AND_CYCLE_IDS_NOT_RESOLVED")
        parent = parents[(ALLOCATION_PARENTS[kind], event["parent_ref"])]
        if parent.get("attribution_scope") != expected_scope(parent):
            issues.add("ALLOCATION_PARENT_SCOPE_CONFLICT")
        if kind == "sales_allocation" and parent["product_type"] == "eggs":
            issues.add("MANUAL_LAYER_EGG_ALLOCATION_FORBIDDEN")
        if kind == "stock_allocation":
            issues.add("STOCK_SOURCE_AND_REVISION_VALIDATION_PENDING")
        if kind == "expense_allocation":
            issues.add("EXPENSE_REVISION_VALIDATION_PENDING")
    else:
        raise ImportPreflightError("UNKNOWN_CANONICAL_EVENT_KIND")
    return {
        "kind": kind, "symbolic_ref": ref, "owner": OWNERS[kind],
        "call_preview": args, "blockers": sorted(issues),
        "executable": False,
    }


def adapt_plan(scenario, manifest, as_of, *, include_intents=False):
    """Requires the old Z26 certified digest, target and conservation guards."""
    if EXECUTION_ENABLED:
        raise ImportPreflightError("EXECUTION_MUST_REMAIN_DISABLED")
    if not isinstance(as_of, dt.date) or as_of.year != YEAR:
        raise ImportPreflightError("AS_OF_YEAR_OR_TYPE_INVALID")
    preflight = validate_manifest(scenario, manifest)
    records = manifest["records"]
    parents = {
        (r["kind"], r["ref"]): r for r in records
        if r["kind"] in ("sales", "expenses", "used_stock")
    }
    intents = []
    grouped = collections.defaultdict(list)
    issue_counts = collections.Counter()
    by_kind = collections.Counter()
    blocked_events = 0
    for event in records:
        intent = adapt_event(event, parents, as_of)
        intents.append(intent)
        by_kind[intent["kind"]] += 1
        issue_counts.update(intent["blockers"])
        if intent["blockers"]:
            blocked_events += 1
        if event["kind"] in ALLOCATION_PARENTS:
            grouped[(event["kind"], event["parent_ref"])].append(
                intent["call_preview"]["desired_row"]
            )
    allocation_calls = []
    for (kind, parent_ref), rows in sorted(grouped.items()):
        if len(rows) != 2 or len({r["cycle_ref"] for r in rows}) != 2:
            raise ImportPreflightError("ALLOCATION_GROUP_NOT_CANONICAL")
        allocation_calls.append({
            "owner": OWNERS[kind], "parent_ref": parent_ref,
            "desired_rows": sorted(rows, key=lambda row: row["cycle_ref"]),
            "executable": False,
        })
    if len(intents) != preflight["events_validated"]:
        raise ImportPreflightError("ADAPTER_EVENT_LOSS")
    if len(OWNERS) != len(by_kind) or set(OWNERS) != set(by_kind):
        raise ImportPreflightError("ADAPTER_OWNER_COVERAGE")
    result = {
        "scenario": scenario,
        "source_sha": CANONICAL_SOURCE_SHA,
        "certified_event_digest_sha256": CERTIFIED_DIGESTS[scenario],
        "original_events_unchanged": True,
        "intents_mapped": len(intents),
        "blocked_events": blocked_events,
        "allocation_parent_calls_grouped": len(allocation_calls),
        "allocation_child_rows": sum(len(c["desired_rows"]) for c in allocation_calls),
        "kind_counts": dict(sorted(by_kind.items())),
        "blocker_counts": dict(sorted(issue_counts.items())),
        "readiness": "BLOCKED_OFFLINE",
        "canonical_php_validation": "NOT_EXECUTED",
        "actor_identity": "UNRESOLVED",
        "persisted_ids": "UNRESOLVED",
        "reference_generation": "CANONICAL_SERVICE_ONLY",
        "execution": "HARD_DISABLED",
        "real_import": "NOT_AUTHORIZED",
        "as_of": as_of.isoformat(),
    }
    if include_intents:
        result["intents"] = intents
        result["allocation_call_previews"] = allocation_calls
    return result


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--dry-run", action="store_true", required=True)
    parser.add_argument("--scenario", required=True, choices=sorted(SCENARIOS))
    parser.add_argument("--database", required=True)
    parser.add_argument("--farm-slug", required=True)
    parser.add_argument("--year", required=True, type=int)
    parser.add_argument("--as-of", required=True)
    args = parser.parse_args(argv)
    if (args.database, args.farm_slug, args.year) != (DB, FARM, YEAR):
        parser.error("C137_Z27D_TARGET_GUARD_BLOCKED")
    if EXECUTION_ENABLED:
        parser.error("C137_Z27D_EXECUTION_GUARD_BLOCKED")
    try:
        as_of = dt.date.fromisoformat(args.as_of)
        result = adapt_plan(
            args.scenario, build(args.scenario, include_records=True), as_of
        )
    except (ValueError, ImportPreflightError) as error:
        parser.error("C137_Z27D_PREFLIGHT_BLOCKED:" + str(error))
    print("C137_GC24CG_Z27D_OFFLINE_ADAPTER=PASS")
    for key, value in result.items():
        encoded = (json.dumps(value, sort_keys=True, separators=(",", ":"))
                   if isinstance(value, dict) else str(value))
        print(key.upper() + "=" + encoded)
    print("DATABASE_CONNECTIONS=0")
    print("DATABASE_WRITES=0")
    print("FILE_WRITES=0")
    print("NETWORK_REQUESTS=0")
    print("CANONICAL_PHP_WRITER_CALLS=0")
    print("IMPORT_EXECUTION_ENABLED=NO")


if __name__ == "__main__":
    main()
