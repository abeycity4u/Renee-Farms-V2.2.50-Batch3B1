<?php
declare(strict_types=1);

/**
 * B12 source-only migration planner pure contract fixtures.
 * No application config, external database or MariaDB connection.
 * Does not certify a deployed schema or authorize running ALTER TABLE.
 */
require_once dirname(__DIR__, 2) . '/lib/daily_record_revision_schema_plan.php';

$passed = 0;
function b12_schema_plan_assert(bool $yes, string $label): void
{
    global $passed;
    if (!$yes) throw new RuntimeException('FAIL: ' . $label);
    ++$passed;
}
function b12_schema_plan_reject(callable $fn, string $label): void
{
    try { $fn(); }
    catch (DomainException|InvalidArgumentException $expected) {
        b12_schema_plan_assert(true, $label);
        return;
    }
    throw new RuntimeException('Expected fail-closed: ' . $label);
}
function b12_schema_plan_col(string $type, string $nullable, mixed $default = null): array
{
    return [
        'column_type' => $type,
        'is_nullable' => $nullable,
        'column_default' => $default,
    ];
}
function b12_schema_plan_fixture(): array
{
    $schema = [];
    foreach (daily_record_revision_schema_tables() as $table => $fkName) {
        $columns = [
            'id' => b12_schema_plan_col('int(11)', 'NO'),
            'farm_id' => b12_schema_plan_col('int(11)', 'NO'),
            'cycle_id' => b12_schema_plan_col('int(11)', 'YES'),
            'record_date' => b12_schema_plan_col('date', 'NO'),
        ];
        if ($table === 'ruminant_daily_records') {
            $columns['animal_type'] = b12_schema_plan_col('varchar(100)', 'NO');
        }
        $schema[$table] = [
            'engine' => 'InnoDB',
            'columns' => $columns,
            'cycle_fk' => [
                'constraint_name' => $fkName,
                'column' => 'cycle_id',
                'referenced_table' => 'production_cycles',
                'referenced_column' => 'id',
                'on_delete' => 'SET NULL',
            ],
        ];
    }
    return $schema;
}
function b12_schema_plan_revision(): array
{
    return b12_schema_plan_col('bigint(20) unsigned', 'NO', '1');
}

try {
    $fixture = b12_schema_plan_fixture();
    $tables = array_keys(daily_record_revision_schema_tables());
    b12_schema_plan_assert(
        $tables === ['layer_daily_records', 'broiler_daily_records', 'ruminant_daily_records'],
        'three closed table identities'
    );
    $fresh = daily_record_revision_schema_candidate_plan($fixture);
    b12_schema_plan_assert($fresh['status'] === 'candidate_only_unapproved', 'all absent yields DRAFT status');
    b12_schema_plan_assert($fresh['executable'] === false, 'planner never authorizes execution');
    b12_schema_plan_assert(count($fresh['statements']) === 3, 'exactly three candidate ALTERs');
    foreach ($tables as $index => $table) {
        b12_schema_plan_assert(
            $fresh['statements'][$index] ===
                'ALTER TABLE `' . $table . '` ADD COLUMN `record_revision` BIGINT UNSIGNED NOT NULL DEFAULT 1',
            'source-only immutable candidate SQL for ' . $table
        );
        b12_schema_plan_assert($fresh['columns'][$table] === 'absent', 'revision initially missing');
    }

    $already = b12_schema_plan_fixture();
    foreach ($tables as $table) $already[$table]['columns']['record_revision'] = b12_schema_plan_revision();
    $valid = daily_record_revision_schema_candidate_plan($already);
    b12_schema_plan_assert(
        $valid['status'] === 'columns_already_valid_marker_not_verified' &&
        $valid['statements'] === [] && $valid['executable'] === false,
        'complete structural state is not a migration marker/approval'
    );
    $partial = b12_schema_plan_fixture();
    $partial[$tables[0]]['columns']['record_revision'] = b12_schema_plan_revision();
    $status = daily_record_revision_schema_candidate_plan($partial);
    b12_schema_plan_assert(
        $status['status'] === 'partial_ddl_stop_manual_recovery' &&
        $status['statements'] === [] && $status['executable'] === false,
        'partial implicit-commit DDL requires manual stop'
    );
    $partial[$tables[1]]['columns']['record_revision'] = b12_schema_plan_revision();
    b12_schema_plan_assert(
        daily_record_revision_schema_candidate_plan($partial)['status'] === 'partial_ddl_stop_manual_recovery',
        'second possible crash point also stops'
    );
    $permuted = array_reverse($already, true);
    b12_schema_plan_assert(
        daily_record_revision_schema_candidate_plan($permuted)['status'] === 'columns_already_valid_marker_not_verified',
        'map order does not cause false drift'
    );

    $bad = b12_schema_plan_fixture();
    unset($bad['layer_daily_records']);
    b12_schema_plan_reject(
        fn() => daily_record_revision_schema_candidate_plan($bad),
        'missing table fails closed'
    );
    $bad = b12_schema_plan_fixture();
    $bad['custom_daily_records'] = $bad['layer_daily_records'];
    b12_schema_plan_reject(
        fn() => daily_record_revision_schema_candidate_plan($bad),
        'unexpected table fails closed'
    );
    foreach ([
        ['engine', 'MyISAM'],
        ['cycle_fk', ['constraint_name'=>'wrong','column'=>'cycle_id','referenced_table'=>'production_cycles',
                      'referenced_column'=>'id','on_delete'=>'SET NULL']],
    ] as [$field, $wrong]) {
        $bad=b12_schema_plan_fixture();
        $bad['layer_daily_records'][$field]=$wrong;
        b12_schema_plan_reject(
            fn() => daily_record_revision_schema_candidate_plan($bad),
            'incompatible engine/cycle FK fails closed'
        );
    }
    $bad=b12_schema_plan_fixture();
    $bad['layer_daily_records']['cycle_fk']['on_delete']='CASCADE';
    b12_schema_plan_reject(
        fn()=>daily_record_revision_schema_candidate_plan($bad),
        'cycle deletion rule mismatch blocks migration'
    );
    foreach ([
        ['cycle_id', b12_schema_plan_col('int(11)', 'NO')],
        ['farm_id', b12_schema_plan_col('int(11)', 'YES')],
        ['record_date', b12_schema_plan_col('varchar(10)', 'NO')],
    ] as [$column,$wrong]) {
        $bad=b12_schema_plan_fixture();
        $bad['layer_daily_records']['columns'][$column]=$wrong;
        b12_schema_plan_reject(
            fn()=>daily_record_revision_schema_candidate_plan($bad),
            'reviewed legacy identity drift rejected'
        );
    }
    foreach ([
        b12_schema_plan_col('bigint(20)', 'NO','1'),
        b12_schema_plan_col('bigint(20) unsigned', 'YES','1'),
        b12_schema_plan_col('bigint(20) unsigned', 'NO','0'),
        b12_schema_plan_col('int(11) unsigned', 'NO','1'),
        b12_schema_plan_col('bigint(20) unsigned', 'NO',null),
    ] as $wrongRevision) {
        $bad=b12_schema_plan_fixture();
        $bad['layer_daily_records']['columns']['record_revision']=$wrongRevision;
        b12_schema_plan_reject(
            fn()=>daily_record_revision_schema_candidate_plan($bad),
            'mismatched existing revision column always blocks'
        );
    }
    b12_schema_plan_assert(
        daily_record_revision_schema_revision_valid(
            b12_schema_plan_col('bigint unsigned', 'NO', "'1'")
        ),
        'MariaDB unsigned BIGINT declaration/default can normalize'
    );
    b12_schema_plan_reject(
        fn()=>daily_record_revision_schema_required_columns('outside_table'),
        'no untrusted SQL table selection'
    );

    echo "B12_REVISION_SCHEMA_PLAN_PURE=PASS\nPASS_COUNT={$passed}\n";
} catch (Throwable $error) {
    fwrite(STDERR,'B12_REVISION_SCHEMA_PLAN_PURE=FAIL ' . get_class($error) . ': ' . $error->getMessage() . "\n");
    exit(1);
}
