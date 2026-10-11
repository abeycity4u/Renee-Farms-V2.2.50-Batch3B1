#!/usr/bin/env python3
"""C137 GC24 Z27F: PROPOSED fixture V2 design, offline and non-executable.

Never changes Z23/Z26 events or digests. It produces a separate specification
digest, NOT a new event-stream digest, SQL, imported record, or payload.
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

EXECUTION_ENABLED = False
DESIGN_VERSION = "C137-GC24-V2-DESIGN-0"
AS_OF = dt.date(2026, 10, 11)
RULES = {
    "cycle": {
        "writer": "production_cycle_create_v3",
        "candidate_state": "active_from_canonical_writer",
        "opening_population": "UNRESOLVED",
        "daily_record_seeding": "UNRESOLVED",
        "cycle_lifecycle_realism": "UNRESOLVED",
    },
    "sales": {
        "layer_eggs": "PROPOSED_USE_DAILY_RECORD_UNSOLD_EGG_AUTO_ALLOCATION",
        "layer_eggs_manual_60_40": "FORBIDDEN",
        "broiler_manual_shared": "PROPOSED_RETAIN_ONLY_AFTER_TARGET_VALIDATION",
        "broiler_population": "UNRESOLVED",
        "unit_layer_eggs": "Crate",
        "unit_broiler": "Head",
        "payment_received": "PROPOSED_PAID_IN_FULL_NOT_VALIDATED",
        "writer_extraction": "UNRESOLVED",
    },
    "expenses": {
        "candidate_category": "labour",
        "candidate_category_manual": True,
        "shared_concrete_scope": "production_type",
        "parent_scope": "MUST_USE_CANONICAL_ATTRIBUTION",
        "writer": "poultry_expense_entry_create",
        "authorization_and_revision": "UNRESOLVED",
    },
    "inventory": {
        "feed": {
            "financial_classification": "feed",
            "inventory_role": "operational",
            "farm_type": "poultry",
            "feed_category": "layer",
            "default_production_type": "layer",
            "candidate_received_unit_cost": "250.00",
        },
        "operating": {
            "financial_classification": "consumables",
            "inventory_role": "operational",
            "farm_type": "poultry",
            "feed_category": "general",
            "default_production_type": "broiler",
            "candidate_received_unit_cost": "40.00",
        },
        "category_item_writer_extraction": "UNRESOLVED",
        "outgoing_price_override": "FORBIDDEN",
        "source_type_candidate": "inventory_manual",
        "ledger_cost_replay": "MUST_MATCH_POSTED_RECEIPT_COST",
        "source_and_revision_provenance": "UNRESOLVED",
    },
    "shared_allocations": {
        "percentage_is_metadata": True,
        "authoritative_input": "allocated_amount",
        "egg_revenue_percent_60_40": "NOT_ASSERTED",
        "manual_revenue": "sale_revenue_allocation_persistence_apply",
        "expenses": "financial_allocation_persistence_apply",
        "used_stock": "stock_consumption_allocation_persistence_apply",
        "canonical_transactions_required": True,
    },
    "date": {
        "legacy_reporting_year": 2026,
        "candidate_full_year_options": [
            "USE_PREVIOUS_COMPLETED_CALENDAR_YEAR_ONLY_AFTER_NEW_MODEL_REVIEW",
            "DEFER_2026_FULL_YEAR_UNTIL_AFTER_2026_12_31",
        ],
        "future_receipts_as_of_2026_10_11": "BLOCKED",
        "chosen_window": "UNRESOLVED",
    },
    "identity": {
        "farm_id": "RESOLVE_ONLY_IN_FUTURE_AUTHORIZED_ISOLATED_CONTEXT",
        "actor_user_id": "RESOLVE_AND_PERMISSION_CHECK_ONLY_IN_FUTURE_AUTHORIZED_CONTEXT",
        "symbolic_to_persisted_id_map": "UNRESOLVED",
    },
    "performance": {
        "r1_prefetch_used_stock_candidate": 1800,
        "r2_prefetch_used_stock_candidate": 3600,
        "cap": 2048,
        "eligible_posted_rows_must_be_verified": True,
        "150_vu": "HOLD",
        "200_vu": "PROHIBITED",
    },
}
GATES = (
    "AS_OF_REPORTING_WINDOW_NOT_RESOLVED",
    "CANONICAL_EGG_PRODUCTION_AND_AUTO_ALLOCATION_NOT_PROVEN",
    "BROILER_OPENING_POPULATION_AND_EXITS_NOT_PROVEN",
    "CYCLE_LIFECYCLE_AND_DAILY_RECORD_POLICY_NOT_PROVEN",
    "CATEGORY_ITEM_SALE_WRITER_EXTRACTION_NOT_DONE",
    "RECEIPT_COST_AND_STOCK_ATTRIBUTION_NOT_EXECUTED",
    "SHARED_ALLOCATION_REVISION_AND_PERMISSIONS_NOT_PROVEN",
    "SYMBOLIC_IDS_TRANSACTION_OWNERSHIP_UNRESOLVED",
    "NO_IMPORT_AUTHORIZATION",
)


def proposed_design(scenario, original, *, include_policies=False):
    if EXECUTION_ENABLED:
        raise ImportPreflightError("EXECUTION_ENABLED")
    summary = validate_manifest(scenario, original)
    rows = original["records"]
    buckets = collections.Counter()

    for event in rows:
        kind = event["kind"]
        if kind == "sales":
            product = event["product_type"]
            if product == "eggs":
                if event["production_type"] != "layer":
                    raise ImportPreflightError("EGG_PRODUCTION_TYPE_CONFLICT")
                buckets["egg_sales_total"] += 1
                if event["cycle_ref"] is None:
                    buckets["egg_sales_source_manual_parent_to_replace"] += 1
                else:
                    buckets["egg_sales_direct_cycle_source"] += 1
            elif product == "broiler":
                if event["production_type"] != "broiler":
                    raise ImportPreflightError("BROILER_PRODUCTION_TYPE_CONFLICT")
                buckets["broiler_sales_total"] += 1
                if event["cycle_ref"] is None:
                    buckets["broiler_sales_candidate_manual_shared"] += 1
                else:
                    buckets["broiler_sales_direct_cycle"] += 1
            else:
                raise ImportPreflightError("UNSUPPORTED_SALE_PRODUCT")
        elif kind == "sales_allocation":
            parent = event["parent_ref"]
            # The suffix in the original symbolic reference identifies the
            # source event but NOT a persisted sales row ID.
            # Build no new allocation: classify existing rows only.
            if not isinstance(parent, str):
                raise ImportPreflightError("INVALID_ALLOCATION_PARENT")
        elif kind == "expenses":
            buckets["expense_parents"] += 1
            if event["cycle_ref"] is None:
                buckets["expense_shared_parents"] += 1
        elif kind == "expense_allocation":
            buckets["expense_manual_rows_retained_candidate"] += 1
        elif kind == "stock_received":
            buckets["stock_receipts_requiring_unit_cost"] += 1
            if dt.date.fromisoformat(event["date"]) > AS_OF:
                buckets["future_receipts_at_pinned_as_of"] += 1
        elif kind == "used_stock":
            buckets["used_stock_parents"] += 1
            if event["financial_classification"] == "operating":
                buckets["used_stock_reclassify_operating_as_consumables"] += 1
            elif event["financial_classification"] != "feed":
                raise ImportPreflightError("UNSUPPORTED_STOCK_CLASSIFICATION")
        elif kind == "stock_allocation":
            buckets["used_stock_manual_rows_retained_candidate"] += 1

    # Identify manual layer-egg child rows by source parent rather than
    # assuming that every old revenue allocation is a valid V2 row.
    parents = {
        row["ref"]: row for row in rows if row["kind"] == "sales"
    }
    for row in rows:
        if row["kind"] != "sales_allocation":
            continue
        sale = parents.get(row["parent_ref"])
        if sale is None:
            raise ImportPreflightError("ORPHAN_REVENUE_ALLOCATION")
        if sale["product_type"] == "eggs":
            buckets["egg_manual_child_rows_must_not_replay"] += 1
        elif sale["product_type"] == "broiler":
            buckets["broiler_manual_child_rows_candidate"] += 1
        else:
            raise ImportPreflightError("UNSUPPORTED_REVENUE_CHILD")

    require = lambda condition, reason: (
        None if condition else (_ for _ in ()).throw(ImportPreflightError(reason))
    )
    require(buckets["egg_sales_total"] + buckets["broiler_sales_total"] ==
            original["record_counts"]["sales"], "SALES_CLASSIFICATION_INCOMPLETE")
    require(buckets["egg_sales_direct_cycle_source"] +
            buckets["egg_sales_source_manual_parent_to_replace"] ==
            buckets["egg_sales_total"], "EGG_BUCKET_MISMATCH")
    require(buckets["broiler_sales_direct_cycle"] +
            buckets["broiler_sales_candidate_manual_shared"] ==
            buckets["broiler_sales_total"], "BROILER_BUCKET_MISMATCH")
    require(buckets["egg_manual_child_rows_must_not_replay"] ==
            2 * buckets["egg_sales_source_manual_parent_to_replace"],
            "EGG_CHILD_GROUP_MISMATCH")
    require(buckets["broiler_manual_child_rows_candidate"] ==
            2 * buckets["broiler_sales_candidate_manual_shared"],
            "BROILER_CHILD_GROUP_MISMATCH")
    require(buckets["egg_manual_child_rows_must_not_replay"] +
            buckets["broiler_manual_child_rows_candidate"] ==
            original["record_counts"]["sales_allocation"],
            "REVENUE_ALLOCATIONS_NOT_CLASSIFIED")
    require(buckets["stock_receipts_requiring_unit_cost"] ==
            original["record_counts"]["stock_received"], "RECEIPTS_NOT_CLASSIFIED")
    require(buckets["used_stock_parents"] ==
            original["record_counts"]["used_stock"], "USED_STOCK_NOT_CLASSIFIED")
    require(buckets["expense_manual_rows_retained_candidate"] ==
            original["record_counts"]["expense_allocation"],
            "EXPENSE_ROWS_NOT_CLASSIFIED")
    require(buckets["used_stock_manual_rows_retained_candidate"] ==
            original["record_counts"]["stock_allocation"],
            "STOCK_ROWS_NOT_CLASSIFIED")
    require(summary["events_validated"] == len(rows), "ORIGINAL_STREAM_LOSS")

    proposal = {
        "version": DESIGN_VERSION,
        "proposal_id": scenario + "-V2-DESIGN",
        "legacy_scenario": scenario,
        "legacy_event_digest_sha256": CERTIFIED_DIGESTS[scenario],
        "legacy_event_count": len(rows),
        "derived_buckets": dict(sorted(buckets.items())),
        "proposed_policies": RULES,
        "unresolved_gates": list(GATES),
        "proposed_v2_events": "NOT_GENERATED",
        "proposed_v2_event_digest_sha256": "NOT_GENERATED",
        "canonical_php_validation": "NOT_EXECUTED",
        "readiness": "PROPOSED_BLOCKED",
        "execution": "HARD_DISABLED",
        "import_authorized": False,
    }
    # This digest identifies the SPECIFICATION ONLY, not fixture events.
    spec_digest = hashlib.sha256(
        json.dumps(proposal, sort_keys=True, separators=(",", ":")).encode()
    ).hexdigest()
    return {
        "proposal_id": proposal["proposal_id"],
        "specification_digest_sha256": spec_digest,
        "legacy_event_digest_sha256": CERTIFIED_DIGESTS[scenario],
        "legacy_events_preserved": True,
        "legacy_event_count": len(rows),
        "derived_buckets": proposal["derived_buckets"],
        "unresolved_gate_count": len(GATES),
        "proposed_v2_events": "NOT_GENERATED",
        "proposed_v2_event_digest_sha256": "NOT_GENERATED",
        "canonical_php_validation": "NOT_EXECUTED",
        "readiness": "PROPOSED_BLOCKED",
        "execution": "HARD_DISABLED",
        **({"policies": RULES, "unresolved_gates": list(GATES)}
           if include_policies else {}),
    }


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
        parser.error("Z27F_TARGET_GUARD_BLOCKED")
    if args.as_of != AS_OF.isoformat() or EXECUTION_ENABLED:
        parser.error("Z27F_AS_OF_OR_EXECUTION_GUARD_BLOCKED")

    result = proposed_design(
        args.scenario, build(args.scenario, include_records=True)
    )
    print("GC24CG_Z27F_V2_OFFLINE_DESIGN=PASS")
    for key, value in result.items():
        serialized = (
            json.dumps(value, sort_keys=True, separators=(",", ":"))
            if isinstance(value, (dict, list)) else str(value)
        )
        print(key.upper() + "=" + serialized)
    print("DATABASE_CONNECTIONS=0")
    print("DATABASE_WRITES=0")
    print("FILE_WRITES=0")
    print("NETWORK_REQUESTS=0")
    print("PHP_WRITER_CALLS=0")
    print("IMPORT_EXECUTION=PROHIBITED")


if __name__ == "__main__":
    main()
