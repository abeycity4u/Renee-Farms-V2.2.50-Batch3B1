<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$serviceFile =
    $root
    .
    '/lib/poultry_slaughter_service.php';

$migrationFile =
    $root
    .
    '/migrations/079_poultry_slaughter_processing_foundation.sql';

$fail =
    static function (
        string $message
    ): void {
        fwrite(
            STDERR,
            "FAIL: {$message}\n"
        );

        exit(1);
    };

$pass =
    static function (
        string $message
    ): void {
        echo
            "PASS: {$message}\n";
    };

foreach (
    [
        $serviceFile,
        $migrationFile,
    ]
    as $file
) {
    if (
        !is_file($file)
        ||
        !is_readable($file)
    ) {
        $fail(
            'Required source missing: '
            .
            basename($file)
        );
    }
}

$service =
    (string)file_get_contents(
        $serviceFile
    );

$migration =
    (string)file_get_contents(
        $migrationFile
    );

$requiredMigration = [
    'cost_basis_finalized_at DATETIME NULL',
    'cost_basis_finalized_by INT NULL',
    'request_token VARCHAR(64) NOT NULL',
    'request_fingerprint CHAR(64) NOT NULL',
    'UNIQUE KEY uniq_psbe_request',
    'expense_revision_id BIGINT UNSIGNED NOT NULL',
    'REFERENCES farm_expense_revisions(id)',
    'chk_poultry_slaughter_cost_finalization_pair',
];

foreach (
    $requiredMigration
    as $needle
) {
    if (
        strpos(
            $migration,
            $needle
        ) === false
    ) {
        $fail(
            'Migration 079 missing contract: '
            .
            $needle
        );
    }
}

$pass(
    'Migration 079 owns idempotent processing-expense provenance and finalization state'
);


$requiredService = [
    'function poultry_slaughter_processing_expense_add(',
    'function poultry_slaughter_cost_basis_finalize(',
    'function poultry_slaughter_processing_expense_fingerprint(',
    'poultry_expense_entry_create(',
    'expense_revision_service_expense(',
    'expense_revision_service_latest(',
    'request_fingerprint',
    'expense_revision_id',
    'amount_snapshot',
    'cost_basis_finalized_at',
    'canonical_cycle_cost_pool_plus_processing_v1',
    'processing_expenses',
];

foreach (
    $requiredService
    as $needle
) {
    if (
        strpos(
            $service,
            $needle
        ) === false
    ) {
        $fail(
            'Poultry slaughter service missing contract: '
            .
            $needle
        );
    }
}

$pass(
    'Poultry slaughter service uses canonical expense and immutable revision authorities'
);


if (
    preg_match(
        '/INSERT\s+INTO\s+farm_expenses/i',
        $service
    )
    ||
    preg_match(
        '/UPDATE\s+farm_expenses/i',
        $service
    )
    ||
    preg_match(
        '/DELETE\s+FROM\s+farm_expenses/i',
        $service
    )
) {
    $fail(
        'Poultry slaughter service must not own direct farm_expenses SQL.'
    );
}

$pass(
    'Processing-cost workflow does not duplicate canonical expense persistence'
);


if (
    strpos(
        $service,
        "cost_basis_finalized_at']"
    ) === false
    ||
    strpos(
        $service,
        'Finalize the processing cost basis of the earlier poultry slaughter batch'
    ) === false
) {
    $fail(
        'A later slaughter batch is not protected from an unfinished earlier cost basis.'
    );
}

$pass(
    'Later slaughter batches require prior processing-cost finalization'
);


if (
    strpos(
        $service,
        'SELECT COUNT(*)'
    ) === false
    ||
    strpos(
        $service,
        'FROM poultry_slaughter_outputs'
    ) === false
) {
    $fail(
        'Cost basis finalization must precede Poultry output Inventory.'
    );
}

$pass(
    'Output Inventory cannot precede frozen processing cost'
);


if (
    strpos(
        $service,
        'stock_apply_movement('
    ) !== false
) {
    $fail(
        'Processing-cost finalization must not mutate Inventory.'
    );
}

if (
    preg_match(
        '/INSERT\s+INTO\s+sales_records/i',
        $service
    )
) {
    $fail(
        'Processing-cost finalization must not create revenue.'
    );
}

$pass(
    'Processing-cost workflow remains separate from Inventory and Sales'
);


require_once $serviceFile;

$fingerprintA =
    poultry_slaughter_processing_expense_fingerprint(
        7,
        'fuel',
        '1000.00',
        '1.00',
        'Generator fuel'
    );

$fingerprintB =
    poultry_slaughter_processing_expense_fingerprint(
        7,
        'fuel',
        '1000.00',
        '1.00',
        'Generator fuel'
    );

$fingerprintC =
    poultry_slaughter_processing_expense_fingerprint(
        7,
        'fuel',
        '1200.00',
        '1.00',
        'Generator fuel'
    );

if (
    strlen($fingerprintA) !== 64
    ||
    !hash_equals(
        $fingerprintA,
        $fingerprintB
    )
    ||
    hash_equals(
        $fingerprintA,
        $fingerprintC
    )
) {
    $fail(
        'Processing-expense request fingerprint is not deterministic/sensitive.'
    );
}

$pass(
    'Processing-expense request fingerprint protects idempotent replay'
);


echo
    "PASS: Stage 14E-3C processing expense and cost finalization contract\n";
