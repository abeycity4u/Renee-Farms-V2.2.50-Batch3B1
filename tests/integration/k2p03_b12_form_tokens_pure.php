<?php
declare(strict_types=1);

/** Isolated pure B12 form presentation contract. No config, DB, CI, or routes. */
require_once dirname(__DIR__, 2) . '/lib/daily_record_revision_form_tokens.php';

$passed = 0;
function b12_form_pass(bool $condition, string $name): void
{
    global $passed;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $name);
    }
    ++$passed;
    echo 'PASS: ' . $name . PHP_EOL;
}
function b12_form_reject(callable $attempt, string $name): void
{
    try {
        $attempt();
    } catch (InvalidArgumentException|DailyRecordRevisionConflict $expected) {
        b12_form_pass(true, $name);
        return;
    }
    b12_form_pass(false, $name);
}
try {
    $keys = [
        'write_intent', 'record_id', 'expected_revision',
        'original_cycle_id', 'original_record_date', 'original_animal_type',
    ];
    foreach (['layer', 'broiler', 'ruminant'] as $type) {
        $create = daily_record_revision_form_fields($type, 21, null);
        b12_form_pass(array_keys($create) === $keys, "$type CREATE has uniform hidden field names");
        b12_form_pass($create['write_intent'] === 'create', "$type explicit CREATE intent");
        b12_form_pass(
            implode('', array_slice($create, 1)) === '',
            "$type CREATE has no forged original identity or revision"
        );
        b12_form_pass(
            daily_record_revision_parse_write_intent(
                $create['write_intent'], $create['record_id'], $create['expected_revision']
            )['mode'] === 'create',
            "$type CREATE round-trips shared pure intent parser"
        );

        $row = [
            'id' => '320', 'farm_id' => '21', 'cycle_id' => '504',
            'record_date' => '2026-10-12', 'record_revision' => '9',
            'animal_type' => ' Goat ', 'is_population_activity_only' => false,
        ];
        $edit = daily_record_revision_form_fields($type, 21, $row);
        b12_form_pass(array_keys($edit) === $keys, "$type EDIT shares field names");
        b12_form_pass($edit['write_intent'] === 'edit', "$type explicit EDIT intent");
        b12_form_pass($edit['record_id'] === '320' && $edit['expected_revision'] === '9',
            "$type preserves originally loaded row identity and revision");
        b12_form_pass($edit['original_cycle_id'] === '504' && $edit['original_record_date'] === '2026-10-12',
            "$type preserves immutable cycle/date scope");
        b12_form_pass($edit['original_animal_type'] === ($type === 'ruminant' ? 'goat' : ''),
            "$type species scope is correct");
        b12_form_pass(
            daily_record_revision_parse_write_intent(
                $edit['write_intent'], $edit['record_id'], $edit['expected_revision']
            ) === ['mode' => 'edit', 'record_id' => 320, 'expected_revision' => 9],
            "$type EDIT round-trips strict parser"
        );
        unset($row['cycle_id']);
        b12_form_reject(
            static fn() => daily_record_revision_form_fields($type, 21, $row),
            "$type incomplete cycle identity fails closed"
        );
    }

    $base = [
        'id' => 11, 'farm_id' => 21, 'cycle_id' => null,
        'record_date' => '2026-10-12', 'record_revision' => 1,
        'animal_type' => ' CATTLE ',
    ];
    $legacy = daily_record_revision_form_fields('ruminant', 21, $base);
    b12_form_pass($legacy['original_cycle_id'] === '', 'legacy NULL cycle presented unambiguously');
    b12_form_pass($legacy['original_animal_type'] === 'cattle', 'ruminant species normalized');

    foreach (['layer', 'broiler', 'ruminant'] as $type) {
        b12_form_reject(
            static fn() => daily_record_revision_form_fields($type, 0, null),
            "$type cannot render create form for invalid farm"
        );
        $cases = [
            ['is_population_activity_only' => true],
            ['is_population_activity_only' => 1],
            ['id' => 0],
            ['id' => '0011'],
            ['farm_id' => 22],
            ['farm_id' => '0'],
            ['cycle_id' => '0'],
            ['cycle_id' => '001'],
            ['record_revision' => null],
            ['record_revision' => '0'],
            ['record_revision' => '01'],
            ['record_date' => '2026-02-30'],
            ['record_date' => null],
        ];
        foreach ($cases as $changes) {
            $candidate = array_replace($base, $changes);
            b12_form_reject(
                static fn() => daily_record_revision_form_fields($type, 21, $candidate),
                "$type rejects unsafe durable row or missing revision"
            );
        }
        foreach (['id', 'farm_id', 'record_date', 'record_revision'] as $missing) {
            $candidate = $base;
            unset($candidate[$missing]);
            b12_form_reject(
                static fn() => daily_record_revision_form_fields($type, 21, $candidate),
                "$type rejects missing $missing"
            );
        }
    }
    $badSpecies = $base;
    $badSpecies['animal_type'] = '  ';
    b12_form_reject(
        static fn() => daily_record_revision_form_fields('ruminant', 21, $badSpecies),
        'ruminant rejects blank species'
    );
    unset($base['animal_type']);
    b12_form_reject(
        static fn() => daily_record_revision_form_fields('ruminant', 21, $base),
        'ruminant requires persisted species'
    );
    b12_form_reject(
        static fn() => daily_record_revision_form_fields('untrusted_table', 21, null),
        'unknown type cannot select arbitrary SQL table'
    );

    echo 'B12_FORM_TOKEN_PURE=PASS' . PHP_EOL;
    echo 'PASS_COUNT=' . $passed . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'B12_FORM_TOKEN_PURE=FAIL' . PHP_EOL);
    fwrite(STDERR, 'FAILURE_CLASS=' . get_class($error) . PHP_EOL);
    fwrite(STDERR, 'ERROR=' . $error->getMessage() . PHP_EOL);
    exit(1);
}
