<?php
declare(strict_types=1);

/**
 * SOURCE-ONLY B12 shared revision contract — future offline pure verifier.
 * Does not load application configuration, connect to a database, mutate
 * anything or invoke GitHub Actions. NOT EXECUTED by source preparation.
 *
 * IMPORTANT: This does NOT certify transactional CAS, concurrent writers,
 * population/feed/sale projection order, roles, delete, CREATE or legacy NULL
 * cycle uniqueness; those require separate authorized synthetic MariaDB runs.
 */
require_once dirname(__DIR__, 2) . '/lib/daily_record_revision_authority.php';

function b12_revision_pure_check(bool $actual, string $label): void
{
    if (!$actual) {
        throw new RuntimeException('B12 pure contract failed: ' . $label);
    }
    echo 'PASS: ' . $label . PHP_EOL;
}

function b12_revision_pure_reject(callable $attempt, string $label): void
{
    try {
        $attempt();
    } catch (InvalidArgumentException|DailyRecordRevisionConflict $expected) {
        b12_revision_pure_check(true, $label);
        return;
    }
    b12_revision_pure_check(false, $label);
}

try {
    $expected = [
        'layer' => 'layer_daily_records',
        'broiler' => 'broiler_daily_records',
        'ruminant' => 'ruminant_daily_records',
    ];
    b12_revision_pure_check(
        array_keys(daily_record_revision_definitions()) === array_keys($expected),
        'closed source-type map contains precisely three supported types'
    );
    foreach ($expected as $type => $table) {
        $definition = daily_record_revision_definition($type);
        b12_revision_pure_check($definition['table'] === $table,
            $type . ' maps to its authoritative Daily Record table');
        b12_revision_pure_check(
            (bool)$definition['animal_type'] === ($type === 'ruminant'),
            $type . ' species identity requirement preserved'
        );
        b12_revision_pure_check(
            count($definition['fields']) === count(array_unique($definition['fields'])),
            $type . ' patch allowlist has no duplicate keys'
        );
        foreach ([
            'farm_id', 'cycle_id', 'record_date', 'animal_type', 'id',
            'user_id', 'record_revision', 'created_at',
        ] as $protected) {
            b12_revision_pure_check(
                !in_array($protected, $definition['fields'], true),
                $type . ' cannot patch protected identity ' . $protected
            );
        }
    }

    b12_revision_pure_check(daily_record_revision_parse_expected('1') === 1,
        'first persisted revision accepted');
    b12_revision_pure_check(daily_record_revision_parse_expected('42') === 42,
        'nonzero string revision accepted exactly');
    b12_revision_pure_check(daily_record_revision_parse_expected(2) === 2,
        'typed positive revision accepted');

    foreach ([
        null, false, 0, -1, 1.0, [], '', '0', '00', '01', ' 1',
        '+1', '-1', '1.0', '1e3', "1\n", (string)PHP_INT_MAX,
        str_repeat('9', 60),
    ] as $candidate) {
        b12_revision_pure_reject(
            static fn() => daily_record_revision_parse_expected($candidate),
            'missing/ambiguous/overflow revision is rejected'
        );
    }

    b12_revision_pure_check(
        daily_record_revision_parse_record_id('42') === 42,
        'immutable browser-loaded record ID parses exactly'
    );
    b12_revision_pure_check(
        daily_record_revision_parse_record_id(7) === 7,
        'typed positive record ID accepted'
    );
    foreach ([
        null, false, 0, -1, 1.2, [], '', '0', '00', '01',
        ' 1', '+1', '-1', '1.0', '1e3', "1\n", str_repeat('9', 60),
    ] as $candidate) {
        b12_revision_pure_reject(
            static fn() => daily_record_revision_parse_record_id($candidate),
            'ambiguous, missing or overflow record ID rejected'
        );
    }

    b12_revision_pure_check(
        daily_record_revision_parse_write_intent('create', null, null) === [
            'mode' => 'create', 'record_id' => null,
            'expected_revision' => null,
        ],
        'new record requires explicit CREATE without edit tokens'
    );
    b12_revision_pure_check(
        daily_record_revision_parse_write_intent('create', '', '')['mode']
            === 'create',
        'blank HTML create tokens accepted without inferring edit'
    );
    b12_revision_pure_check(
        daily_record_revision_parse_write_intent('edit', '23', '5') === [
            'mode' => 'edit', 'record_id' => 23,
            'expected_revision' => 5,
        ],
        'EDIT preserves immutable client row ID and client revision'
    );
    foreach ([
        [null, null, null],
        ['update', '23', '5'],
        ['EDIT', '23', '5'],
        ['create', '23', null],
        ['create', null, '1'],
        ['create', '0', '0'],
        ['edit', null, '1'],
        ['edit', '23', null],
        ['edit', '23', '0'],
        ['edit', '0', '1'],
        ['edit', '023', '1'],
        ['edit', '23', '01'],
    ] as $tokens) {
        b12_revision_pure_reject(
            static fn() => daily_record_revision_parse_write_intent(...$tokens),
            'missing/mismatched CREATE or EDIT client tokens rejected'
        );
    }

    b12_revision_pure_check(
        daily_record_revision_validate_scope(
            1, 2, 3, '2026-09-21', false, null
        ) === null,
        'scoped poultry source identity accepted'
    );
    b12_revision_pure_check(
        daily_record_revision_validate_scope(
            1, 2, null, '2026-09-21', true, ' Goat '
        ) === 'goat',
        'legacy nullable-cycle ruminant scope normalizes species'
    );

    b12_revision_pure_reject(
        static fn() => daily_record_revision_definition('table_from_post'),
        'untrusted source/table name is rejected'
    );
    b12_revision_pure_reject(
        static fn() => daily_record_revision_validate_scope(
            0, 2, 3, '2026-09-21', false, null
        ),
        'unscoped farm ID is rejected'
    );
    b12_revision_pure_reject(
        static fn() => daily_record_revision_validate_scope(
            1, 2, 3, '2026-02-30', false, null
        ),
        'invalid calendar date is rejected'
    );
    b12_revision_pure_reject(
        static fn() => daily_record_revision_validate_scope(
            1, 2, 3, '2026-09-21', false, 'goat'
        ),
        'species is prohibited in poultry source scope'
    );
    b12_revision_pure_reject(
        static fn() => daily_record_revision_validate_scope(
            1, 2, 3, '2026-09-21', true, null
        ),
        'ruminant source requires species scope'
    );
    b12_revision_pure_reject(
        static fn() => daily_record_revision_validate_scope(
            1, 2, 0, '2026-09-21', true, 'goat'
        ),
        'cycle id zero cannot stand in for nullable legacy cycle'
    );

    echo 'K2_P03_B12_SHARED_REVISION_PURE=PASS' . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'K2_P03_B12_SHARED_REVISION_PURE=FAIL' . PHP_EOL);
    fwrite(STDERR, 'FAILURE_CLASS=' . get_class($error) . PHP_EOL);
    exit(1);
}
