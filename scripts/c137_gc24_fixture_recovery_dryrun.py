#!/usr/bin/env python3
"""C137 GC24 Z23: OFFLINE recovery-readiness plan, never a restore executor.

Deliberately contains no database client, network client, backup, delete,
restore, or filesystem mutation routines. Prints prerequisites and STOP gates.
"""
import argparse
import re

from c137_gc24_fixture_dryrun import DB, FARM, YEAR, SCENARIOS, build

# Referentially related records exist outside these nine tables. Never rely
# on an ad-hoc deletion order to reverse a synthetic fixture operation.
SCOPED_TABLES = (
    "production_cycles", "inventory_categories", "stock_items",
    "stock_transactions", "sales_records", "farm_expenses",
    "sales_allocations", "financial_allocations",
    "stock_consumption_allocations"
)
PROTECTED_DATABASES = ("renee_c137_fixture",)
SYNTHETIC_ACTIVATION_ALLOWED = False


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--dry-run", action="store_true", required=True)
    parser.add_argument("--scenario", choices=sorted(SCENARIOS), required=True)
    parser.add_argument("--database", required=True)
    parser.add_argument("--farm-slug", required=True)
    parser.add_argument("--year", type=int, required=True)
    parser.add_argument("--expected-event-digest", required=True)
    args = parser.parse_args()
    if not args.dry_run or (args.database, args.farm_slug, args.year) != (DB, FARM, YEAR):
        parser.error("ISOLATED_TARGET_GUARD_BLOCKED")
    if args.database in PROTECTED_DATABASES:
        parser.error("PROTECTED_DATABASE_GUARD_BLOCKED")
    if not re.fullmatch(r"[0-9a-f]{64}", args.expected_event_digest):
        parser.error("PLAN_DIGEST_FORMAT_BLOCKED")
    plan = build(args.scenario)
    if args.expected_event_digest != plan["event_digest_sha256"]:
        parser.error("PLAN_DIGEST_MISMATCH")
    assert not SYNTHETIC_ACTIVATION_ALLOWED

    print("C137_GC24CG_Z23_RECOVERY_DRYRUN=PASS")
    print("ISOLATED_TARGET_GUARD=PASS")
    print("PLAN_DIGEST_GUARD=PASS")
    print("TARGET_DATABASE=" + DB)
    print("TARGET_FARM_SLUG=" + FARM)
    print("PROTECTED_FIXTURE_DATABASE=EXCLUDED")
    print("SCOPED_TABLE_COUNT=" + str(len(SCOPED_TABLES)))
    print("BACKUP_REQUIREMENT=COMPLETE_ISOLATED_DATABASE_SNAPSHOT")
    print("BACKUP_FILE_EXISTENCE=NOT_VERIFIED")
    print("BACKUP_HASH=NOT_VERIFIED")
    print("BACKUP_RESTORE_TEST=NOT_VERIFIED")
    print("DATABASE_IDENTITY_AT_EXECUTION=NOT_VERIFIED")
    print("BASELINE_FARM_AND_ADMIN_PRESERVATION=REQUIRED")
    print("EXTERNAL_FOREIGN_KEY_DEPENDENCIES=REQUIRE_FULL_RESTORE")
    print("INCOMING_CONSTRAINTS=32_PRIOR_READONLY_SCHEMA_INSPECTION")
    print("ROLLBACK_STRATEGY=EXCLUSIVE_ISOLATED_HTTP_DATABASE_RESTORE")
    print("ROLLBACK_EXECUTION_TOOL=NOT_IMPLEMENTED")
    print("TENANT_ONLY_DELETE=NOT_AUTHORIZED")
    print("FIXTURE_IMPORT=NOT_AUTHORIZED")
    print("WATCHDOG_FOR_FUTURE_ACTIVATION=REQUIRED")
    print("CANONICAL_BUSINESS_RULE_VALIDATION=PENDING")
    print("DB_CONNECTIONS=0")
    print("DB_WRITES=0")
    print("FILE_WRITES=0")
    print("NETWORK_REQUESTS=0")
    print("REAL_RECOVERY_READY=NO")
    print("EXPLICIT_POPULATION_AUTHORIZATION=REQUIRED")


if __name__ == "__main__":
    main()
