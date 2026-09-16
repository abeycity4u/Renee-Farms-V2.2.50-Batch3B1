<?php

$root = dirname(__DIR__);

$_SERVER['DOCUMENT_ROOT'] =
    getenv('HOME') . '/public_html';

ob_start();
require getenv('HOME') . '/public_html/config.php';
ob_end_clean();

require_once
    $root . '/lib/production_population.php';

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
    $asOfDate = '2026-09-14';

    $history =
        production_population_history(
            $pdo,
            $farmId,
            $cycleId,
            $asOfDate
        );

    $assert(
        is_array($history),
        'Canonical population history was not returned.'
    );

    $baseline =
        is_array($history)
        && isset($history['baseline'])
        && is_array($history['baseline'])
            ? $history['baseline']
            : [];

    $movements =
        is_array($history)
        && isset($history['movements'])
        && is_array($history['movements'])
            ? $history['movements']
            : [];

    $assert(
        !empty($baseline['id']),
        'Canonical baseline identity is missing.'
    );

    $assert(
        (string)($baseline['baseline_date'] ?? '')
            <= $asOfDate,
        'Canonical baseline is later than the requested history boundary.'
    );

    $requiredMovementKeys = [
        'id',
        'movement_date',
        'movement_type',
        'quantity_delta',
        'source_type',
        'source_id',
        'source_version',
        'reversal_of_id',
    ];

    $previousOrder = null;
    $movementDelta = 0;
    $hasReversal = false;
    $maxDailyLayerVersion = 0;

    foreach ($movements as $movement) {
        foreach ($requiredMovementKeys as $key) {
            $assert(
                array_key_exists($key, $movement),
                'Population movement history is missing key: '
                . $key
            );
        }

        $movementDate =
            (string)($movement['movement_date'] ?? '');

        $movementId =
            (int)($movement['id'] ?? 0);

        $assert(
            $movementDate <= $asOfDate,
            'Population history returned a movement after the requested boundary.'
        );

        $orderKey =
            $movementDate
            . ':'
            . str_pad(
                (string)$movementId,
                20,
                '0',
                STR_PAD_LEFT
            );

        if ($previousOrder !== null) {
            $assert(
                strcmp($previousOrder, $orderKey) <= 0,
                'Population movement history is not deterministically ordered.'
            );
        }

        $previousOrder = $orderKey;

        $movementDelta +=
            (int)($movement['quantity_delta'] ?? 0);

        if (
            (string)($movement['movement_type'] ?? '')
            === 'reversal'
        ) {
            $hasReversal = true;
        }

        if (
            (string)($movement['source_type'] ?? '')
            === 'daily_layer_record'
        ) {
            $maxDailyLayerVersion =
                max(
                    $maxDailyLayerVersion,
                    (int)($movement['source_version'] ?? 0)
                );
        }
    }

    $state =
        production_population_state(
            $pdo,
            $farmId,
            $cycleId,
            $asOfDate
        );

    $historyQuantity =
        (int)($baseline['baseline_quantity'] ?? 0)
        + $movementDelta;

    $stateQuantity =
        is_array($state)
            ? (int)($state['quantity'] ?? -1)
            : -1;

    $assert(
        $historyQuantity === $stateQuantity,
        'Population history does not reconcile to canonical population state.'
    );

    $assert(
        $hasReversal,
        'Fixture does not prove compensating reversal visibility.'
    );

    $assert(
        $maxDailyLayerVersion >= 2,
        'Fixture does not prove later source-version visibility.'
    );

    $beforeBaselineRejected = false;

    try {
        $date =
            (new DateTimeImmutable(
                (string)$baseline['baseline_date']
            ))
            ->modify('-1 day')
            ->format('Y-m-d');

        production_population_history(
            $pdo,
            $farmId,
            $cycleId,
            $date
        );
    } catch (ProductionPopulationException $e) {
        $beforeBaselineRejected = true;
    }

    $assert(
        $beforeBaselineRejected,
        'History before the canonical baseline was not rejected.'
    );

    $source =
        file_get_contents(
            $root . '/lib/production_population.php'
        );

    $assert(
        strpos(
            $source,
            'function production_population_history('
        ) !== false,
        'Population history read model is missing.'
    );

    $assert(
        strpos(
            $source,
            "'movement_type' === 'reversal'"
        ) === false,
        'Read model appears to filter correction/reversal history.'
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

    $movementRefs = [];

    foreach ($movements as $movement) {
        $movementRefs[] =
            (string)$movement['id']
            . ':'
            . (string)$movement['source_type']
            . ':'
            . (
                $movement['source_id'] === null
                    ? 'NULL'
                    : (string)$movement['source_id']
            )
            . ':v'
            . (string)$movement['source_version']
            . ':d'
            . (string)$movement['quantity_delta'];
    }

    echo "RESULT=PASS\n";
    echo "READ_ONLY=PASS\n";
    echo "BASELINE_ID="
        . (string)$baseline['id']
        . "\n";
    echo "BASELINE_DATE="
        . (string)$baseline['baseline_date']
        . "\n";
    echo "MOVEMENT_COUNT="
        . count($movements)
        . "\n";
    echo "MOVEMENT_REFS="
        . (
            $movementRefs
                ? implode(',', $movementRefs)
                : 'NONE'
        )
        . "\n";
    echo "CORRECTION_CHAIN_VISIBLE=PASS\n";
    echo "SOURCE_VERSION_CHAIN_VISIBLE=PASS\n";
    echo "STATE_RECONCILIATION=PASS\n";
    echo "AS_OF_BOUNDARY=PASS\n";
    echo "BEFORE_BASELINE_REJECTION=PASS\n";
    echo "CANONICAL_QUANTITY="
        . $stateQuantity
        . "\n";

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
