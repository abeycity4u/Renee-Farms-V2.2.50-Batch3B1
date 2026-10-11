#!/usr/bin/env python3
"""C137 GC24 K1/D08: read-only transactional Sales source requirements verifier.

Reads only pinned PHP source text and append-only documentation.
Does not run PHP, contact a DB, create customers/payments/sales, or test load.
"""
import argparse
import ast
import copy
import datetime
import hashlib
import json
import re
import sys
from pathlib import Path

sys.dont_write_bytecode = True
DOCS = Path(__file__).resolve().parent.parent / "docs"
SOURCE = Path("/opt/renee-agrisuite-c137-gc")
SPEC_FILE = DOCS / "C137_GC24_K1_D08_TRANSACTIONAL_SALES_REQUIREMENTS.json"
PRIOR_FILE = DOCS / "C137_GC24_K1_D07_INVENTORY_WRITER_REQUIREMENTS.json"
PARENT = "a5d3a0eb5955db34e482a902f4797a19c3c86efe"
CERT = "322438870263825f9e739a12c4bd478983e67141"
D07_BLOB = "5610a26f89b6e8da007831d8f1cf5885ec80cf46"
D07_DIGEST = {
    "R1":"48267cd7c330243200cebfd837bd541dbb3aa44f51a72d9107ab52695417a63d",
    "R2":"991d5c5f14ffa462613744d4fd1491dbf6da2e83050a0e81f1c9dcf7a54d39c7",
}
D06_DIGEST = {
    "R1":"fdebc8ef7e2ef694721a62ff4cd15f416ab855c777ccc33c59e320daa2c2e940",
    "R2":"ca29f1602575204c060f13904868fce14001760d6fc61f46e7420b08afd7958c",
}
ORIGINAL = {
    "R1":"e31421ee40ce25b16feb698313fcf337e74126a8e63d8c5dadeedf74e30165f2",
    "R2":"985341ed4315788ee8f3b9d62b327da66d38fc63ea16d406c5c52bc9949d3dfe",
}
FILES = (
    "management/sales_records.php",
    "lib/sales_receivables.php",
    "lib/sales_allocation.php",
    "lib/layer_egg_inventory.php",
    "lib/sale_population_effects.php",
    "lib/general_sale_inventory.php",
    "lib/slaughter_output_sale_dispatch.php",
    "lib/ruminant_sale_animal_allocation.php",
    "lib/ruminant_animal_exit.php",
    "lib/attribution.php",
    "lib/sales_units.php",
    "lib/record_reference_persistence.php",
    "lib/sale_revenue_allocation_service.php",
    "lib/sale_revenue_allocation_persistence.php",
    "api/delete_sale.php",
    "includes/sales_receivable_permissions.php",
    "api/update_sale_revenue_allocation.php",
)
REQUIRED = {
    "sale_financial_record_neq_population_exit",
    "sale_financial_record_neq_general_inventory_used",
    "sale_revenue_neq_upfront_cash_received",
    "credit_ledger_sale_row_neq_cash_payment_entry",
    "shared_layer_egg_sale_saved_neq_auto_allocated",
    "manual_allocation_neq_layer_egg_biology",
    "processed_slaughter_inventory_neq_live_bird_sale",
    "original_2026_sale_volume_neq_2025_validated_sales",
}
class Blocked(ValueError):
    pass

def require(ok, label):
    if not ok:
        raise Blocked(label)

def normalize(text):
    return re.sub(r"\s+", "", text).lower()

def blob_sha(raw):
    return hashlib.sha1(
        b"blob " + str(len(raw)).encode("ascii") + bytes([0]) + raw
    ).hexdigest()

def static_guard():
    root = ast.parse(Path(__file__).read_text(encoding="utf-8"))
    allowed = {"argparse","ast","copy","datetime","hashlib","json","re","sys","pathlib"}
    forbidden = {"connect","execute","executemany","commit","rollback","write",
                 "write_text","write_bytes","unlink","rename","mkdir","rmdir",
                 "remove","popen","Popen","system","urlopen","request","eval","exec"}
    for node in ast.walk(root):
        if isinstance(node, ast.Import):
            require(all(m.name in allowed for m in node.names), "UNSAFE_IMPORT")
        elif isinstance(node, ast.ImportFrom):
            require(node.module in allowed, "UNSAFE_IMPORT")
        elif isinstance(node, ast.Call):
            f = node.func
            name = f.id if isinstance(f, ast.Name) else (
                f.attr if isinstance(f, ast.Attribute) else "")
            require(name not in forbidden, "UNSAFE_CALL")

def verify_d07_lineage():
    raw = PRIOR_FILE.read_bytes()
    require(blob_sha(raw) == D07_BLOB, "D07_BLOB_CHANGED")
    previous = json.loads(raw)
    require(previous["checkpoint"] == "GC24CG_K1_D07" and
            previous["readiness"] == "D07_WRITER_AUTHORITY_UNAPPROVED" and
            all(v is None for v in previous["per_category_item_unknowns"].values()),
            "D07_FACTS_FALSELY_APPROVED")
    for scenario in ("R1","R2"):
        payload = {"scenario":scenario,"specification":previous,
                   "prior_d06_spec_sha256":D06_DIGEST[scenario]}
        value = hashlib.sha256(json.dumps(
            payload, sort_keys=True, separators=(",",":")
        ).encode()).hexdigest()
        require(value == D07_DIGEST[scenario], "D07_DIGEST_DRIFT")

def validate(spec):
    require(spec["version"] == "C137-GC24-K1-D08-TRANSACTIONAL-SALES-REQUIREMENTS-0"
            and spec["checkpoint"] == "GC24CG_K1_D08"
            and spec["scope"] == "OFFLINE_READONLY_SOURCE_REVIEW"
            and spec["readiness"] == "D08_SALES_WRITER_AND_FINANCIAL_FACTS_UNAPPROVED",
            "D08_UNAUTHORIZED_SCOPE")
    w = spec["reporting"]
    a = datetime.date.fromisoformat(w["start"])
    b = datetime.date.fromisoformat(w["end"])
    require((w["selected_year"],str(a),str(b),(b-a).days+1,w["event_date_policy"]) ==
            (2025,"2025-01-01","2025-12-31",365,"UNAPPROVED"), "D08_DATE_POLICY")
    require(spec["design_as_of"] == "2026-10-11" and
            spec["preparation_parent_sha"] == PARENT and
            spec["certified_reports_sha"] == CERT and
            spec["prior_d07_requirements_git_blob"] == D07_BLOB and
            spec["prior_d07_spec_sha256"] == D07_DIGEST and
            spec["prior_d06_spec_sha256"] == D06_DIGEST and
            spec["original_2026_event_sha256"] == ORIGINAL,
            "D08_LINEAGE_DRIFT")
    require(spec["isolated_database_reference_only"] == "renee_c137_gc24_http"
            and spec["synthetic_tenant_slug_reference_only"] ==
            "c137-gc24-synthetic", "D08_TARGET_DRIFT")
    fields = spec["sale_and_payment_unknowns"]
    require(len(fields) >= 50 and all(x is None for x in fields.values()),
            "D08_SALE_CUSTOMER_OR_PAYMENT_FABRICATED")
    contracts = spec["source_contracts"]
    require(len(contracts) == len(FILES)
            and [c["file"] for c in contracts] == list(FILES)
            and [c["id"] for c in contracts] ==
            ["S%02d" % (i+1) for i in range(len(FILES))]
            and all(len(c["markers"])>=2 for c in contracts),
            "D08_SOURCE_SCOPE_DRIFT")
    rules = set(spec["source_grounded_rules"])
    must = {
        "sales_records_is_primary_financial_sale_fact_with_farm_and_actor_and_sale_business_date",
        "sale_with_credit_requires_customer_name_and_initial_credit_ledger_sale_row",
        "payment_entries_are_cash_facts_and_sale_entries_are_derived_receivable_amounts",
        "sale_edit_refuses_new_total_below_already_received_payments",
        "shared_layer_egg_sale_can_be_saved_with_unallocated_revenue_warning",
        "financial_livestock_sale_does_not_infer_live_population_change",
        "sales_only_general_inventory_forces_general_farm_and_no_livestock_exits",
        "sale_delete_refuses_deletion_when_debt_payment_history_attached",
    }
    require(len(rules)>=28 and must.issubset(rules), "D08_SALE_CONTRACT_MISSING")
    require(set(spec["critical_separations"]) == REQUIRED,
            "D08_FINANCIAL_AND_PHYSICAL_CONFLATED")
    plan = set(spec["future_fixture_proofs"])
    must_plan = {
        "prove_credit_sale_cash_snapshot_plus_receivable_opening_balance_consistency",
        "layer_egg_sale_unit_conversion_and_per_date_pool_are_verifiably_sufficient",
        "prevent_credit_sale_delete_after_real_payment_ledger_history",
        "no_direct_SQL_sales_writer_that_bypasses_shared_domain_workflows",
        "sale_edits_rebuild_automatic_allocations_from_earliest_affected_business_date",
    }
    require(len(plan)>=29 and must_plan.issubset(plan), "D08_FUTURE_PROOFS_MISSING")
    g = spec["gates"]
    require(g == {
        "D01_year":"SELECTED_2025_DESIGN_ONLY",
        "D01_date_policy":"UNAPPROVED",
        "D02_layer_baseline":"UNAPPROVED",
        "D03_layer_eggs":"UNPROVEN",
        "D04_broiler_populations":"UNAPPROVED",
        "D05_feed_inventory_costing":"UNAPPROVED",
        "D06_tenant_actor":"UNAPPROVED",
        "D07_inventory_writer":"UNAPPROVED",
        "D08_sales_writer":"UNAPPROVED",
        "D09_to_D12":"UNAPPROVED",
        "receipt_and_customer_input":"NOT_APPROVED",
        "actual_sale_rows":"NOT_CREATED",
        "actual_payment_rows":"NOT_CREATED",
        "population_exit_proof":"UNPROVEN",
        "egg_allocation_proof":"UNPROVEN",
        "new_v2_event_stream":"NOT_GENERATED",
        "annual_150_VU":"HOLD",
        "annual_200_VU":"PROHIBITED",
        "database_connections":0,
        "database_writes":0,
    }, "D08_GUARD_TAMPER")
    auth = spec["permissions"]
    require(auth["offline_spec_only"] is True, "D08_NOT_OFFLINE")
    for key in (
        "choose_sale_amount","choose_sale_date","choose_customer_or_payment",
        "create_sale","create_payment","create_population_exit",
        "create_stock_used","allocate_revenue","modify_app_source",
        "execute_PHP","access_database","write_database",
        "generate_new_events","import_or_deploy","run_load_test",
    ):
        require(auth[key] is False, "D08_UNAUTHORIZED_" + key.upper())

def inspect(spec, replacement=None):
    require(SOURCE.is_dir() and not SOURCE.is_symlink(),
            "CERTIFIED_SOURCE_ROOT_MISSING")
    probes=0
    for i, row in enumerate(spec["source_contracts"]):
        path = row["file"]
        require(path in FILES, "UNAPPROVED_SOURCE_PATH")
        f = SOURCE / path
        require(f.is_file() and not f.is_symlink(),
                "CANONICAL_FILE_UNAVAILABLE")
        body = f.read_text(encoding="utf-8")
        if replacement and path in replacement:
            body = replacement[path]
        normalized = normalize(body)
        for n, marker in enumerate(row["markers"]):
            require(normalize(marker) in normalized,
                    "CANONICAL_SALES_MARKER_DRIFT_" + str(i) + "_" + str(n))
            probes+=1
    return probes

def negative_tests(spec):
    for field,value in (
        ("sale_quantity", 1500),
        ("sale_unit_price", 2000),
        ("sale_upfront_payment", 8000),
        ("sale_customer_name", "fictional person"),
    ):
        x = copy.deepcopy(spec)
        x["sale_and_payment_unknowns"][field] = value
        try:
            validate(x)
        except Blocked:
            pass
        else:
            raise Blocked("D08_UNAPPROVED_SALE_INPUT_ACCEPTED")
    for section,field,value in (
        ("permissions","create_sale",True),
        ("permissions","access_database",True),
        ("gates","D08_sales_writer","APPROVED"),
        ("gates","annual_200_VU","ENABLED"),
    ):
        x=copy.deepcopy(spec)
        x[section][field]=value
        try:
            validate(x)
        except Blocked:
            pass
        else:
            raise Blocked("D08_EXECUTION_AUTHORIZATION_BYPASS")
    file="lib/sales_receivables.php"
    src=(SOURCE/file).read_text(encoding="utf-8")
    needle="function receivable_assert_sale_deletable("
    require(needle in src, "D08_TAMPER_NOT_CONFIGURED")
    wrong={file:"".join(src.split(needle))}
    try:
        inspect(spec,wrong)
    except Blocked:
        pass
    else:
        raise Blocked("D08_PAYMENT_DELETE_GUARD_REMOVAL_ACCEPTED")
    x=copy.deepcopy(spec)
    x["critical_separations"]=[
        value for value in x["critical_separations"]
        if value!="sale_revenue_neq_upfront_cash_received"
    ]
    try:
        validate(x)
    except Blocked:
        pass
    else:
        raise Blocked("D08_CASH_VS_REVENUE_CONFLATED")

def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument("--dry-run", required=True, action="store_true")
    p.add_argument("--database",required=True)
    p.add_argument("--farm-slug",required=True)
    p.add_argument("--legacy-year",type=int,required=True)
    p.add_argument("--as-of",required=True)
    p.add_argument("--reporting-year",type=int,required=True)
    p.add_argument("--certified-source-root",required=True)
    a=p.parse_args()
    if ((a.database,a.farm_slug,a.legacy_year) !=
        ("renee_c137_gc24_http","c137-gc24-synthetic",2026)
        or (a.as_of,a.reporting_year) != ("2026-10-11",2025)
        or a.certified_source_root != str(SOURCE)):
        p.error("K1_D08_SCOPE_GUARD_BLOCKED")
    static_guard()
    verify_d07_lineage()
    spec=json.loads(SPEC_FILE.read_text(encoding="utf-8"))
    validate(spec)
    count=inspect(spec)
    negative_tests(spec)
    print("K1_D08_STATIC_READONLY=PASS")
    print("K1_D07_IMMUTABLE_LINEAGE=PASS")
    print("K1_D08_SOURCE_MARKERS=PASS")
    print("K1_D08_NEGATIVE_TESTS=PASS")
    print("K1_D08_SOURCE_FILES="+str(len(FILES)))
    print("K1_D08_SOURCE_PROBES="+str(count))
    print("K1_D08_UNKNOWN_SALE_FIELDS="+str(len(spec["sale_and_payment_unknowns"])))
    print("K1_D08_YEAR=2025")
    print("K1_D08_FINANCIAL_PHYSICAL_SEPARATION=SOURCE_REVIEWED")
    print("K1_D08_SALES_APPROVAL=NOT_GRANTED")
    print("K1_D08_RUNTIME_TRANSACTIONAL_PROOF=UNPROVEN")
    for scenario in ("R1","R2"):
        payload={"scenario":scenario,"specification":spec,
                 "prior_d07_spec_sha256":D07_DIGEST[scenario]}
        digest=hashlib.sha256(json.dumps(
            payload,sort_keys=True,separators=(",",":")
        ).encode()).hexdigest()
        print(scenario+"_D08_SPEC_SHA256="+digest)
        print(scenario+"_ORIGINAL_2026_EVENT_DIGEST=UNCHANGED")
        print(scenario+"_V2_EVENT_DIGEST=NOT_GENERATED")
    print("CERTIFIED_REPORTS_UNCHANGED=YES")
    print("PHP_EXECUTED=NO")
    print("SALES_CREATED=0")
    print("PAYMENTS_CREATED=0")
    print("DATABASE_CONNECTIONS=0")
    print("DATABASE_WRITES=0")
    print("SOURCE_WRITES=0")
    print("V2_EVENTS_GENERATED=NO")
    print("NEW_LOAD_TESTS=0")
    print("IMPORT_EXECUTION_ENABLED=NO")
    print("GC24CG_K1_D08_OFFLINE_VERIFIER=PASS")

if __name__ == "__main__":
    main()
