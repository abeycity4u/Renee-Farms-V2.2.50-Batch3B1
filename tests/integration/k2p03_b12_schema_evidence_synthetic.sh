#!/usr/bin/env bash
# B12 schema evidence only: disposable CI MariaDB + synthetic data.
# NOT a production duplicate audit, migration, B12 retry, or application E2E test.
set -euo pipefail

test "$GITHUB_ACTIONS" = 'true'
test "$RUNNER_ENVIRONMENT" = 'github-hosted'
test "$GITHUB_REPOSITORY" = 'abeycity4u/Renee-Farms-V2.2.50-Batch3B1'
test "$GITHUB_REF" = 'refs/heads/c137-gc24-k2p03-b12-schema-evidence-once'
test "$CI_DB_HOST" = '127.0.0.1'
test "$CI_DB_PORT" = '3306'
test "$CI_DB_NAME" = 'renee_b12_schema_synthetic_once'
test "$CI_DB_USER" = 'ci_schema'
test "$CI_DB_PASSWORD" = 'synthetic-only-b12-schema-once'

db() {
    MYSQL_PWD="$CI_DB_PASSWORD" mariadb --protocol=TCP \
      --host="$CI_DB_HOST" --port="$CI_DB_PORT" \
      --user="$CI_DB_USER" --database="$CI_DB_NAME" \
      --batch --skip-column-names "$@"
}
scalar() {
    db --execute="$1" | tr -d '\r'
}
assert_eq() {
    local name="$1" query="$2" expected="$3" actual
    actual="$(scalar "$query")"
    if [ "$actual" != "$expected" ]; then
        echo "FAIL=$name | expected=$expected | observed=$actual"
        exit 1
    fi
    echo "PASS=$name | value=$actual"
}

echo "B12_SCHEMA_EVIDENCE_MODE=DISPOSABLE_MARIADB_SYNTHETIC_ONLY"
assert_eq "DB_NAME" 'SELECT DATABASE()' "$CI_DB_NAME"
assert_eq "FRESH_SCHEMA_TABLES" "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE'" '0'
test "$(git hash-object database_schema.sql)" = 'e77f287d62c5812a3d55dc23b5b20f5d167db389'
echo "PINNED_SCHEMA_BLOB=PASS"

# database_schema.sql contains DROP TABLE; import ONLY after the exact
# GitHub-hosted local service, database identity, and zero-table guards.
db < database_schema.sql
echo "FRESH_SCHEMA_IMPORT=PASS"

assert_eq "THREE_DAILY_TABLES_EXIST" "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('layer_daily_records','broiler_daily_records','ruminant_daily_records')" '3'
assert_eq "RECORD_REVISION_COLUMNS_ABSENT" "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name IN ('layer_daily_records','broiler_daily_records','ruminant_daily_records') AND column_name='record_revision'" '0'
assert_eq "THREE_SOURCE_CYCLE_FOREIGN_KEYS_SET_NULL" "SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema=DATABASE() AND constraint_name IN ('fk_layer_cycle','fk_broiler_cycle','fk_ruminant_cycle') AND delete_rule='SET NULL'" '3'
assert_eq "THREE_EXPECTED_NULLABLE_CYCLE_KEYS" "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name IN ('layer_daily_records','broiler_daily_records','ruminant_daily_records') AND column_name='cycle_id' AND is_nullable='YES'" '3'

# The ONLY fixtures are synthetic farms/cycles created in the ephemeral DB.
db <<'SQL'
INSERT INTO farms (id, name, slug) VALUES
 (91001, 'B12 Synthetic Farm One', 'b12-synthetic-one'),
 (91002, 'B12 Synthetic Farm Two', 'b12-synthetic-two');
INSERT INTO production_cycles
 (id, farm_id, cycle_code, farm_type, production_type, start_date) VALUES
 (92001, 91001, 'synthetic-layer-a', 'poultry', 'layer', '2026-01-01'),
 (92002, 91001, 'synthetic-layer-b', 'poultry', 'layer', '2026-01-01'),
 (92003, 91001, 'synthetic-broiler-a', 'poultry', 'broiler', '2026-01-01'),
 (92004, 91001, 'synthetic-broiler-b', 'poultry', 'broiler', '2026-01-01'),
 (92005, 91001, 'synthetic-cattle-a', 'ruminant', 'cattle', '2026-01-01'),
 (92006, 91001, 'synthetic-cattle-b', 'ruminant', 'cattle', '2026-01-01');

-- Two identical farm/date legacy-key identities with NULL cycle_id are
-- admitted under each actual fresh-install table's unique indexes.
-- Two different cycle records on the same farm/date are legitimately admitted.
INSERT INTO layer_daily_records
 (farm_id,cycle_id,record_date,opening_stock,birds_age) VALUES
 (91001,NULL,'2026-01-02',11,40),
 (91001,NULL,'2026-01-02',12,40),
 (91001,92001,'2026-01-02',13,40),
 (91001,92002,'2026-01-02',14,40),
 (91002,92001,'2026-01-03',15,40);
INSERT INTO broiler_daily_records
 (farm_id,cycle_id,record_date,opening_stock,birds_age) VALUES
 (91001,NULL,'2026-01-02',11,40),
 (91001,NULL,'2026-01-02',12,40),
 (91001,92003,'2026-01-02',13,40),
 (91001,92004,'2026-01-02',14,40),
 (91002,92003,'2026-01-03',15,40);
INSERT INTO ruminant_daily_records
 (farm_id,cycle_id,record_date,animal_type,opening_stock) VALUES
 (91001,NULL,'2026-01-02','cattle',11),
 (91001,NULL,'2026-01-02','cattle',12),
 (91001,92005,'2026-01-02','cattle',13),
 (91001,92006,'2026-01-02','cattle',14),
 (91002,92005,'2026-01-03','cattle',15);
SQL
echo "SYNTHETIC_FIXTURES_INSERTED=YES"
for table in layer_daily_records broiler_daily_records ruminant_daily_records; do
    assert_eq "FIVE_SYNTHETIC_ROWS_$table" "SELECT COUNT(*) FROM $table" '5'
    assert_eq "NULL_CYCLE_REPEATED_$table" "SELECT COUNT(*) FROM $table WHERE farm_id=91001 AND record_date='2026-01-02' AND cycle_id IS NULL" '2'
done

output="$(mktemp)"
trap 'rm -f "$output"' EXIT
# Execute the actual previously-reviewed 11 SELECT inventory statements.
db < docs/C137_GC24_K2_P03_B12_READONLY_SCHEMA_INVENTORY.sql > "$output"
echo "SOURCE_READONLY_INVENTORY_EXECUTED=PASS"

check_section() {
    local section="$1" kind="$2" first="$3" second="$4"
    if ! grep -Fxq "$(printf '%s\t%s\t%s\t%s' "$section" "$kind" "$first" "$second")" "$output"; then
        echo "FAIL=INVENTORY_${section}_${kind}"
        exit 1
    fi
    echo "PASS=INVENTORY_$section_$kind | value=$first,$second"
}
for kind in layer broiler ruminant; do
    # Section 07: one repeated NULL-cycle logical key, one excess row.
    check_section '07_NULL_CYCLE_DUPLICATES' "$kind" 1 1
    # Section 08: one multi-cycle same-day group, three additional matches.
    check_section '08_UNSCOPED_UPDATE_AMBIGUITY' "$kind" 1 3
    # Section 09: FK permits source farm 91002 to reference farm 91001 cycle.
    check_section '09_CYCLE_TENANT_INTEGRITY' "$kind" 0 1
done
echo "SCHEMA_RISK_EXPECTATIONS=PASS"

# After cycle deletion, its Daily source's FK sets cycle_id to NULL. The
# pre-existing duplicate legacy identities persist and gain another NULL row.
db --execute="DELETE FROM production_cycles WHERE id=92002 AND farm_id=91001"
assert_eq "CYCLE_SET_NULL_CREATES_EXTRA_LAYER_LEGACY_COLLISION" "SELECT COUNT(*) FROM layer_daily_records WHERE farm_id=91001 AND record_date='2026-01-02' AND cycle_id IS NULL" '3'
echo "SYNTHETIC_SET_NULL_COLLISION=PASS"
echo "SOURCE_SCHEMA_RISKS=CONFIRMED_ON_SYNTHETIC_FIXTURES"
echo "LIVE_LEGACY_DUPLICATE_COUNTS=UNKNOWN"
echo "MIGRATIONS_APPLIED_TO_PROTECTED_DATABASES=NONE"
echo "B12_CONCURRENCY_RERUN=NONE"
