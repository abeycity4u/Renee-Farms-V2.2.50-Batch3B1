<?php

$root = dirname(__DIR__);

$_SERVER['DOCUMENT_ROOT'] =
    getenv('HOME') . '/public_html';

ob_start();
require getenv('HOME') . '/public_html/config.php';
ob_end_clean();

require_once
    $root . '/lib/poultry_rearing_economics.php';

$failures = [];

$assert = static function (
    bool $condition,
    string $message
) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

try {
    $pdo->exec('SET TRANSACTION READ ONLY');
    $pdo->beginTransaction();

    $farmId = 4;
    $cycleId = 55;

    $economics =
        poultry_rearing_economics(
            $pdo,
            $farmId,
            $cycleId
        );

    $assert(
        !empty($economics['available']),
        'Layer economics are unavailable.'
    );

    $assert(
        (string)($economics['mode'] ?? '')
            === 'reared',
        'Expected farm-reared economics mode.'
    );

    $assert(
        abs(
            (float)($economics['rearing_investment'] ?? 0)
            - 927833.36
        ) < 0.005,
        'Attributed investment changed.'
    );

    $assert(
        (int)($economics['production_entry_headcount'] ?? 0)
            === 496,
        'Production-Entry headcount changed.'
    );

    $assert(
        (string)(
            $economics[
                'production_entry_headcount_source'
            ] ?? ''
        )
            ===
            'Canonical population ledger at Rearing close, reconciled to exact Daily Record boundary',
        'Production-Entry population source wording changed.'
    );

    $sources =
        isset(
            $economics['population_provenance_sources']
        )
        && is_array(
            $economics['population_provenance_sources']
        )
            ? $economics[
                'population_provenance_sources'
            ]
            : [];

    $assert(
        count($sources) === 8,
        'Expected one baseline plus seven canonical movement sources.'
    );

    $baselineCount = 0;
    $movementCount = 0;
    $refs = [];
    $movementIds = [];
    $hasReversalMovement = false;
    $hasLaterDailyVersion = false;

    foreach ($sources as $source) {
        $normalized =
            poultry_production_entry_provenance_source(
                $source
            );

        $assert(
            preg_match(
                '/^[a-f0-9]{64}$/',
                (string)(
                    $normalized['source_revision']
                    ?? ''
                )
            ) === 1,
            'Population source revision is not a SHA-256 digest.'
        );

        $role =
            (string)$normalized['role'];

        if ($role === 'population_baseline') {
            $baselineCount++;

            $assert(
                (string)$normalized['source_id']
                    === '7',
                'Unexpected canonical baseline identity.'
            );

            $assert(
                (string)$normalized['effective_date']
                    === '2026-09-10',
                'Unexpected canonical baseline date.'
            );
        }

        if ($role === 'population_movement') {
            $movementCount++;

            $movementId =
                (int)$normalized['source_id'];

            $movementIds[] = $movementId;

            if ($movementId === 15 || $movementId === 20) {
                $hasReversalMovement = true;
            }

            if (
                $movementId === 16
                && (string)$normalized['source_version']
                    === '2'
            ) {
                $hasLaterDailyVersion = true;
            }

            if (
                $movementId === 21
                && (string)$normalized['source_version']
                    === '3'
            ) {
                $hasLaterDailyVersion = true;
            }
        }

        $refs[] =
            $role
            . ':'
            . (string)$normalized['source_type']
            . ':'
            . (string)$normalized['source_id']
            . (
                $normalized['source_version'] === null
                    ? ''
                    : ':v'
                        . (string)$normalized[
                            'source_version'
                        ]
            );
    }

    sort($movementIds, SORT_NUMERIC);

    $assert(
        $baselineCount === 1,
        'Population baseline provenance count is not one.'
    );

    $assert(
        $movementCount === 7,
        'Canonical movement provenance count is not seven.'
    );

    $assert(
        $movementIds === [
            1,
            2,
            3,
            15,
            16,
            20,
            21,
        ],
        'Canonical movement provenance identities changed.'
    );

    $assert(
        $hasReversalMovement,
        'Correction/reversal provenance is not visible.'
    );

    $assert(
        $hasLaterDailyVersion,
        'Later population source versions are not visible.'
    );

    $manifest =
        poultry_production_entry_provenance_build(
            [
                'farm_id' => $farmId,
                'cycle_id' => $cycleId,
                'mode' => 'reared',
                'production_entry_date' =>
                    '2026-09-15',
                'rearing_start_date' =>
                    '2026-09-10',
                'rearing_end_date' =>
                    '2026-09-14',
            ],
            $sources
        );

    $assert(
        preg_match(
            '/^[a-f0-9]{64}$/',
            (string)$manifest['fingerprint']
        ) === 1,
        'Population provenance sources do not build a valid manifest fingerprint.'
    );

    $economicsSource =
        file_get_contents(
            $root
            . '/lib/poultry_rearing_economics.php'
        );

    $assert(
        strpos(
            $economicsSource,
            'production_population_history('
        ) !== false,
        'Economics boundary is not using the canonical population history service.'
    );

    $assert(
        strpos(
            $economicsSource,
            "'population_provenance_sources'"
        ) !== false,
        'Economics result does not expose population provenance.'
    );

    if ($failures) {
        echo "RESULT=FAIL\n";

        foreach ($failures as $failure) {
            echo "FAIL={$failure}\n";
        }

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        exit(1);
    }

    echo "RESULT=PASS\n";
    echo "READ_ONLY=PASS\n";
    echo "ECONOMICS_UNCHANGED=PASS\n";
    echo "ATTRIBUTED_INVESTMENT="
        . (string)$economics['rearing_investment']
        . "\n";
    echo "PRODUCTION_ENTRY_HEADCOUNT="
        . (string)$economics[
            'production_entry_headcount'
        ]
        . "\n";
    echo "POPULATION_SOURCE_COUNT="
        . count($sources)
        . "\n";
    echo "POPULATION_BASELINE_COUNT="
        . $baselineCount
        . "\n";
    echo "POPULATION_MOVEMENT_COUNT="
        . $movementCount
        . "\n";
    echo "POPULATION_REFS="
        . implode(',', $refs)
        . "\n";
    echo "CORRECTION_CHAIN_VISIBLE=PASS\n";
    echo "SOURCE_VERSION_CHAIN_VISIBLE=PASS\n";
    echo "MANIFEST_COMPATIBILITY=PASS\n";

    $pdo->rollBack();

} catch (Throwable $e) {
    if (
        isset($pdo)
        && $pdo instanceof PDO
        && $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }

    echo "RESULT=FAIL\n";
    echo "FAIL="
        . str_replace(
            ["\r", "\n"],
            ' ',
            $e->getMessage()
        )
        . "\n";

    exit(1);
}
