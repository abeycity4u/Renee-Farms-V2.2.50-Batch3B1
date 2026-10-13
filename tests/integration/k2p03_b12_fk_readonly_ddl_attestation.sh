#!/usr/bin/env bash
set -euo pipefail

# Disposable GitHub-hosted synthetic schema ONLY, no protected environment.
test "$GITHUB_ACTIONS" = "true"
test "$RUNNER_ENVIRONMENT" = "github-hosted"
test "$GITHUB_REPOSITORY" = "abeycity4u/Renee-Farms-V2.2.50-Batch3B1"
test "$GITHUB_REF" = "refs/heads/c137-gc24-k2p03-b12-fk-ddl-attestation-ci"
test "$GITHUB_RUN_ATTEMPT" = 1
test "$B12_DIAGNOSTIC_APPROVAL_GUARD" = PASS
test "$CI_DB_HOST" = 127.0.0.1
test "$CI_DB_NAME" = renee_b12_disposable_fkddl_20261013
test "$CI_DB_OWNER_USER" = b12_owner
test "$CI_DB_READER_USER" = b12_reader

db_query() {
    local role="$1" statement="$2" pw=''
    case "$role" in
      owner) pw="$CI_DB_OWNER_PASSWORD"; role="$CI_DB_OWNER_USER";;
      reader) pw="$CI_DB_READER_PASSWORD"; role="$CI_DB_READER_USER";;
      root) pw="$CI_DB_ROOT_PASSWORD"; role=root;;
      *) echo INVALID_ROLE >&2; return 1;;
    esac
    MYSQL_PWD="$pw" mariadb --protocol=TCP --host=127.0.0.1 --port=3306 \
      --user="$role" --database="$CI_DB_NAME" --batch --skip-column-names \
      --execute="$statement"
}

echo "#####COPY FROM HERE#####"
echo "AUDIT=B12_FK_READONLY_DDL_ATTESTATION"
echo "DATABASE_SCOPE=NEW_GITHUB_RUNNER_DISPOSABLE_ONLY"
test "$(db_query owner 'SELECT DATABASE()')" = "$CI_DB_NAME"
test "$(db_query root 'SELECT DATABASE()')" = "$CI_DB_NAME"
test "$(db_query owner "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE'")" = 0
echo "FRESH_SCHEMA_EMPTY=PASS"
# Dump contains DROP TABLE, so empty-schema guard above is mandatory.
MYSQL_PWD="$CI_DB_OWNER_PASSWORD" mariadb --protocol=TCP --host=127.0.0.1 \
  --port=3306 --user="$CI_DB_OWNER_USER" --database="$CI_DB_NAME" < database_schema.sql
echo "PINNED_SYNTHETIC_SCHEMA_IMPORTED=PASS"

db_query root "CREATE USER 'b12_reader'@'%' IDENTIFIED BY 'synthetic-fk-reader-20261013'"
db_query root "GRANT SELECT ON \`renee_b12_disposable_fkddl_20261013\`.* TO 'b12_reader'@'%'"
grants="$(db_query reader 'SHOW GRANTS')"
echo "$grants" | grep -Fq 'GRANT SELECT ON'
if echo "$grants" | grep -Eqi 'GRANT (ALL|INSERT|UPDATE|DELETE|ALTER|CREATE|DROP|SUPER)'; then
  echo "SELECT_ONLY_PRIVILEGE=FAIL"; exit 1
fi
test "$(db_query reader 'SELECT DATABASE()')" = "$CI_DB_NAME"
echo "SELECT_ONLY_PRIVILEGE=PASS"

q_rc="SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema=DATABASE() AND constraint_name IN ('fk_layer_cycle','fk_broiler_cycle','fk_ruminant_cycle') AND referenced_table_name='production_cycles' AND delete_rule='SET NULL'"
q_kcu="SELECT COUNT(DISTINCT CONCAT(table_name,':',constraint_name)) FROM information_schema.key_column_usage WHERE table_schema=DATABASE() AND constraint_name IN ('fk_layer_cycle','fk_broiler_cycle','fk_ruminant_cycle') AND referenced_table_name='production_cycles'"
q_tc="SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND constraint_name IN ('fk_layer_cycle','fk_broiler_cycle','fk_ruminant_cycle') AND constraint_type='FOREIGN KEY'"

for role in root owner reader; do
  rc="$(db_query "$role" "$q_rc")"
  kcu="$(db_query "$role" "$q_kcu")"
  tc="$(db_query "$role" "$q_tc")"
  test "$rc" -ge 0 && test "$rc" -le 3
  test "$kcu" -ge 0 && test "$kcu" -le 3
  test "$tc" -ge 0 && test "$tc" -le 3
  echo "ROLE_"$role"_RC=$rc"
  echo "ROLE_"$role"_KCU=$kcu"
  echo "ROLE_"$role"_TC=$tc"
  eval "b12_$role""_rc=$rc"
  eval "b12_$role""_kcu=$kcu"
  eval "b12_$role""_tc=$tc"
done

test "$b12_root_rc" = 3
test "$b12_owner_rc" = 3
test "$b12_root_kcu" = 3
test "$b12_owner_kcu" = 3
test "$b12_root_tc" = 3
test "$b12_owner_tc" = 3
echo "OWNER_ROOT_CYCLE_FK_METADATA=PASS"

seen=0
for tbl in layer_daily_records broiler_daily_records ruminant_daily_records; do
  ddl="$(db_query owner "SHOW CREATE TABLE $tbl")"
  if ! printf '%s\n' "$ddl" | grep -Eq 'CONSTRAINT.*REFERENCES.*production_cycles.*ON DELETE SET NULL'; then
    echo "OWNER_DDL_FK_"$tbl"=FAIL"; exit 1
  fi
  seen=$((seen+1))
done
test "$seen" = 3
echo "OWNER_SHOW_CREATE_FK=PASS"

if [ "$b12_reader_rc" = 0 ] && [ "$b12_reader_kcu" = 0 ] && [ "$b12_reader_tc" = 0 ]; then
  echo "DIAGNOSIS=SELECT_ONLY_METADATA_INVISIBLE"
elif [ "$b12_reader_rc" = 3 ]; then
  echo "DIAGNOSIS=READER_METADATA_VISIBLE"
else
  echo "DIAGNOSIS=PARTIAL_OR_INCONSISTENT_VISIBILITY"
fi

# A table SELECT grant should also permit SHOW CREATE TABLE in this
# disposable instance. Check without displaying the schema contents.
for tbl in layer_daily_records broiler_daily_records ruminant_daily_records; do
  reader_ddl="$(db_query reader "SHOW CREATE TABLE $tbl")"
  printf '%s\n' "$reader_ddl" | grep -Eq 'CONSTRAINT.*REFERENCES.*production_cycles.*ON DELETE SET NULL'
done
echo "READER_SHOW_CREATE_FK=PASS"

umask 077
secret_file="$RUNNER_TEMP/b12-fk-reader"
trap 'rm -f "$secret_file"' EXIT
printf '%s\n' "$CI_DB_READER_PASSWORD" > "$secret_file"
export B12_SCHEMA_APPROVED_RUN=YES_ONE_DISPOSABLE_READONLY_CLONE
export B12_SCHEMA_PROVENANCE_VERIFIED=YES
export B12_SCHEMA_READONLY_GRANTS_VERIFIED=YES
export B12_SCHEMA_HOST=127.0.0.1
export B12_SCHEMA_DATABASE="$CI_DB_NAME"
export B12_SCHEMA_USER="$CI_DB_READER_USER"
export B12_SCHEMA_PASSWORD_FILE="$secret_file"

set +e
result="$(php scripts/k2p03_b12_schema_readonly_gate.php --run 2>&1)"
status=$?
set -e
test "$status" = 0
if [ "$b12_reader_rc" = 3 ]; then
  echo "$result" | grep -Fq 'CYCLE_FK_ATTESTATION=REFERENTIAL_CONSTRAINTS_PASS'
  echo "READER_RC_FK_ATTESTATION=PASS"
else
  echo "$result" | grep -Fq 'CYCLE_FK_ATTESTATION=READONLY_SHOW_CREATE_TABLE_PASS'
  echo "READER_SHOW_CREATE_FK_ATTESTATION=PASS"
fi
echo "CUSTOMER_DATA_INSPECTED=NO"
echo "REVISION_MIGRATION_EXECUTED=NO"
echo "B12_CONCURRENCY_CERTIFIED=NO"
echo "AUDIT=COMPLETE"
echo "#####COPY TO HERE#####"
