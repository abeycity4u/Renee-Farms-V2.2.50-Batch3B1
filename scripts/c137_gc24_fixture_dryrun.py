#!/usr/bin/env python3
"""C137 GC24 Z23: OFFLINE representative Reports fixture design only.

No SQL connector, HTTP client, shell calls, SQL statements, credential reads,
data insertion, or persistent file output. This is not a fixture writer.
"""
import argparse
import calendar
import datetime as dt
import hashlib
import json
from collections import Counter, defaultdict

DB = "renee_c137_gc24_http"
FARM = "c137-gc24-synthetic"
YEAR = 2026
PREFETCH_CAP = 2048
SCENARIOS = {
    "R1": {"sales": 1460, "expenses": 730, "used_stock": 1800},
    "R2": {"sales": 4380, "expenses": 2190, "used_stock": 3600},
}
CYCLES = ("layer-a", "layer-b", "broiler-a", "broiler-b")
ITEMS = ("feed", "operating")
KIND_KEYS = ("sales", "expenses", "used_stock")


class Plan:
    def __init__(self):
        self.sha = hashlib.sha256()
        self.count = Counter()
        self.monthly = defaultdict(Counter)
        self.totals = Counter()
        self.parent_allocated = Counter()
        self.child_allocated = Counter()

    def emit(self, kind, date, identifier, **properties):
        record = {"kind": kind, "date": date, "ref": identifier, **properties}
        self.sha.update((json.dumps(record, sort_keys=True, separators=(",", ":")) + "\n").encode())
        self.count[kind] += 1

    def financial(self, kind, date, identifier, amount_cents, **properties):
        assert isinstance(amount_cents, int) and amount_cents >= 0
        self.emit(kind, date, identifier, amount_cents=amount_cents, **properties)
        self.totals[kind] += amount_cents
        self.monthly[date[:7]][kind] += amount_cents

    def allocation(self, kind, date, parent, cents, cycles):
        assert len(cycles) == 2 and cents % 5 == 0
        portions = (cents * 3 // 5, cents * 2 // 5)
        assert sum(portions) == cents
        self.parent_allocated[kind] += cents
        for n, (cycle, portion) in enumerate(zip(cycles, portions), 1):
            self.emit(kind, date, f"{parent}/alloc-{n}",
                      parent_ref=parent, cycle_ref=cycle,
                      percent=(60 if n == 1 else 40),
                      amount_cents=portion)
            self.child_allocated[kind] += portion


def build(scenario):
    if scenario not in SCENARIOS:
        raise ValueError("UNAPPROVED_SCENARIO")
    volume = SCENARIOS[scenario]
    p = Plan()
    base = dt.date(YEAR, 1, 1)
    days = 365

    for name in CYCLES:
        production = "layer" if name.startswith("layer") else "broiler"
        p.emit("cycle", f"{YEAR}-01-01", name, production_type=production,
               farm_type="poultry", livestock_type_id=None,
               status="planned", valid_by_canonical_service="NOT_VERIFIED")
    for name in ITEMS:
        p.emit("category", f"{YEAR}-01-01", f"cat-{name}", farm_type="poultry")
        p.emit("item", f"{YEAR}-01-01", f"item-{name}",
               category_ref=f"cat-{name}", farm_type="poultry")

    for i in range(volume["sales"]):
        date = (base + dt.timedelta(days=i % days)).isoformat()
        is_broiler = (i % 4 == 0)
        production = "broiler" if is_broiler else "layer"
        cycles = ("broiler-a", "broiler-b") if is_broiler else ("layer-a", "layer-b")
        allocated = (i % 7 == 0)
        quantity = 10 + i % 11
        unit_price_cents = 60000 if is_broiler else 170000
        amount = quantity * unit_price_cents
        ref = f"{scenario}-sale-{i:06d}"
        p.financial("sales", date, ref, amount,
                    quantity=quantity, unit_price_cents=unit_price_cents,
                    product_type=("broiler" if is_broiler else "eggs"),
                    farm_type="poultry", production_type=production,
                    attribution_scope=("farm" if allocated else "cycle"),
                    cycle_ref=(None if allocated else cycles[i % 2]))
        if allocated:
            p.allocation("sales_allocation", date, ref, amount, cycles)

    for i in range(volume["expenses"]):
        date = (base + dt.timedelta(days=i % days)).isoformat()
        production = "broiler" if i % 4 == 0 else "layer"
        cycles = ("broiler-a", "broiler-b") if production == "broiler" else ("layer-a", "layer-b")
        allocated = i % 5 == 0
        amount = 25000 + (i % 11) * 500
        ref = f"{scenario}-expense-{i:06d}"
        p.financial("expenses", date, ref, amount,
                    farm_type="poultry", production_type=production,
                    attribution_scope=("farm" if allocated else "cycle"),
                    cycle_ref=(None if allocated else cycles[i % 2]),
                    unit=1)
        if allocated:
            p.allocation("expense_allocation", date, ref, amount, cycles)

    used_by_month = defaultdict(list)
    for i in range(volume["used_stock"]):
        date = (base + dt.timedelta(days=i % days)).isoformat()
        item = "operating" if i % 5 == 0 else "feed"
        used_by_month[(date[:7], item)].append((i, date))

    balance = Counter()
    for month in range(1, 13):
        month_key = f"{YEAR}-{month:02d}"
        receipt_date = month_key + "-01"
        for item in ITEMS:
            uses = used_by_month[(month_key, item)]
            received_qty = len(uses) + 10
            balance[item] += received_qty
            p.emit("stock_received", receipt_date, f"{scenario}-{month_key}-{item}-receipt",
                   item_ref=f"item-{item}", quantity=received_qty, type="received")
            for index, date in sorted(uses, key=lambda value: (value[1], value[0])):
                balance[item] -= 1
                assert balance[item] >= 0
                cost = 25000 if item == "feed" else 4000
                cycles = ("layer-a", "layer-b") if item == "feed" else ("broiler-a", "broiler-b")
                allocated = index % 5 == 0
                ref = f"{scenario}-stock-used-{index:06d}"
                p.financial("used_stock", date, ref, cost, item_ref=f"item-{item}",
                            quantity=1, transaction_type="used", is_reversed=0,
                            farm_type="poultry", financial_classification=item,
                            attribution_scope=("farm" if allocated else "cycle"),
                            cycle_ref=(None if allocated else cycles[index % 2]))
                if allocated:
                    p.allocation("stock_allocation", date, ref, cost, cycles)

    assert p.count["sales"] == volume["sales"]
    assert p.count["expenses"] == volume["expenses"]
    assert p.count["used_stock"] == volume["used_stock"]
    assert p.count["stock_received"] == 24
    assert p.count["cycle"] == 4
    assert p.count["category"] == 2 and p.count["item"] == 2
    assert all(balance[name] == 120 for name in ITEMS)
    assert len(p.monthly) == 12
    assert all(p.parent_allocated[k] == p.child_allocated[k]
               for k in ("sales_allocation", "expense_allocation", "stock_allocation"))
    assert all(sum(month[k] for month in p.monthly.values()) == p.totals[k]
               for k in KIND_KEYS)
    assert (volume["used_stock"] <= PREFETCH_CAP) == (scenario == "R1")

    return {
        "scenario": scenario,
        "database": DB,
        "farm_slug": FARM,
        "year": YEAR,
        "record_counts": dict(sorted(p.count.items())),
        "annual_totals_cents": dict(sorted(p.totals.items())),
        "planned_allocated_parent_cents": dict(sorted(p.parent_allocated.items())),
        "planned_allocated_child_cents": dict(sorted(p.child_allocated.items())),
        "monthly_periods": len(p.monthly),
        "remaining_stock_units": dict(sorted(balance.items())),
        "planned_used_stock": volume["used_stock"],
        "prefetch_limit": PREFETCH_CAP,
        "prefetch_case": "IN_LIMIT" if scenario == "R1" else "OVERFLOW_INTENDED",
        "event_digest_sha256": p.sha.hexdigest(),
        "financial_reconciliation": "PASS_OFFLINE_MODEL",
        "canonical_business_validation": "PENDING",
        "data_insertion": "PROHIBITED",
        "backup_verification": "PENDING",
    }


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--dry-run", action="store_true", required=True)
    parser.add_argument("--scenario", required=True, choices=sorted(SCENARIOS))
    parser.add_argument("--database", required=True)
    parser.add_argument("--farm-slug", required=True)
    parser.add_argument("--year", required=True, type=int)
    args = parser.parse_args()
    if not args.dry_run or (args.database, args.farm_slug, args.year) != (DB, FARM, YEAR):
        parser.error("C137_FIXTURE_TARGET_GUARD_BLOCKED")
    data = build(args.scenario)
    print("C137_GC24CG_Z23_DRY_RUN=PASS")
    for key, value in data.items():
        output = json.dumps(value, sort_keys=True, separators=(",", ":")) if isinstance(value, dict) else str(value)
        print(key.upper() + "=" + output)
    print("DATABASE_CONNECTIONS=0")
    print("DATABASE_WRITES=0")
    print("SOURCE_WRITES=0")
    print("NETWORK_REQUESTS=0")


if __name__ == "__main__":
    main()
