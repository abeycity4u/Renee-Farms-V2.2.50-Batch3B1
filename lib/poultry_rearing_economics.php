<?php
require_once __DIR__ . '/stock_reporting.php';
require_once __DIR__ . '/stock_costing.php';
require_once __DIR__ . '/inventory_financial.php';
require_once __DIR__ . '/stock_consumption_economics.php';
require_once __DIR__ . '/poultry_cycle_lifecycle.php';
require_once __DIR__ . '/poultry_cycle_acquisition.php';
require_once __DIR__ . '/production_population.php';
require_once __DIR__ . '/poultry_production_entry_provenance.php';

if (!function_exists('poultry_production_entry_lifecycle_provenance_source')) {
function poultry_production_entry_lifecycle_provenance_source(
    array $row
): array {
    return poultry_production_entry_provenance_source([
        'role' => 'lifecycle_phase',
        'source_type' => 'production_cycle_phase',
        'source_id' => (int)$row['id'],
        'source_revision' =>
            poultry_production_entry_provenance_revision([
                'phase' =>
                    (string)$row['phase'],
                'start_date' =>
                    (string)$row['start_date'],
                'end_date' =>
                    empty($row['end_date'])
                        ? null
                        : (string)$row['end_date'],
            ]),
        'effective_date' =>
            (string)$row['start_date'],
    ]);
}
}

if (!function_exists('poultry_production_entry_acquisition_provenance_source')) {
function poultry_production_entry_acquisition_provenance_source(
    array $row
): array {
    $totalCost =
        $row['total_cost'] === null
        || $row['total_cost'] === ''
            ? null
            : (string)$row['total_cost'];

    return poultry_production_entry_provenance_source([
        'role' => 'acquisition',
        'source_type' => 'poultry_cycle_acquisition',
        'source_id' => (int)$row['id'],
        'source_revision' =>
            poultry_production_entry_provenance_revision([
                'acquisition_type' =>
                    (string)$row['acquisition_type'],
                'acquisition_date' =>
                    (string)$row['acquisition_date'],
                'quantity' =>
                    (int)$row['quantity'],
                'total_cost' =>
                    $totalCost,
            ]),
        'effective_date' =>
            (string)$row['acquisition_date'],
    ]);
}
}

if (!function_exists('poultry_production_entry_stock_use_provenance_source')) {
function poultry_production_entry_stock_use_provenance_source(
    array $row,
    string $role
): array {
    $role = strtolower(trim($role));

    if (
        !in_array(
            $role,
            [
                'feed_use',
                'operating_inventory_use',
            ],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Select a valid stock-use provenance role.'
        );
    }

    $nullableString =
        static function ($value): ?string {
            if (
                $value === null
                || $value === ''
            ) {
                return null;
            }

            return (string)$value;
        };

    $revisionFacts = [
        'stock_item_id' =>
            (int)$row['stock_item_id'],
        'transaction_date' =>
            (string)$row['transaction_date'],
        'transaction_type' =>
            (string)$row['transaction_type'],
        'quantity' =>
            (string)$row['quantity'],
        'unit_cost' =>
            $nullableString(
                $row['unit_cost'] ?? null
            ),
        'total_cost' =>
            $nullableString(
                $row['total_cost'] ?? null
            ),
        'financial_classification' =>
            $nullableString(
                $row[
                    'financial_classification'
                ] ?? null
            ),
        'source_type' =>
            $nullableString(
                $row['source_type'] ?? null
            ),
        'source_id' =>
            $nullableString(
                $row['source_id'] ?? null
            ),
    ];

    /*
     * Feed eligibility now comes from the immutable stock-movement Financial
     * Type snapshot. Keep the historical item/category metadata in this
     * provenance digest recipe for compatibility with previously approved
     * Production-Entry fingerprints. These fields no longer confer Feed
     * accounting status.
     */
    if ($role === 'feed_use') {
        $revisionFacts['feed_category'] =
            $nullableString(
                $row['feed_category'] ?? null
            );

        $revisionFacts[
            'feed_category_name_normalized'
        ] =
            strtolower(
                (string)(
                    $row['category_name']
                    ?? ''
                )
            );
    }

    return poultry_production_entry_provenance_source([
        'role' => $role,
        'source_type' => 'stock_transaction',
        'source_id' => (int)$row['id'],
        'source_revision' =>
            poultry_production_entry_provenance_revision(
                $revisionFacts
            ),
        'effective_date' =>
            (string)$row['transaction_date'],
    ]);
}
}


if (!function_exists('poultry_production_entry_stock_allocation_provenance_source')) {
function poultry_production_entry_stock_allocation_provenance_source(
    array $row,
    string $role
): array {
    $role =
        strtolower(
            trim($role)
        );

    if (
        !in_array(
            $role,
            [
                'feed_use',
                'operating_inventory_use',
            ],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Select a valid allocated stock-use provenance role.'
        );
    }

    $allocationId =
        (int)(
            $row['allocation_id']
            ?? 0
        );

    $stockTransactionId =
        (int)(
            $row['stock_transaction_id']
            ?? 0
        );

    $targetCycleId =
        (int)(
            $row['target_cycle_id']
            ?? $row['effective_cycle_id']
            ?? 0
        );

    if (
        $allocationId < 1
        ||
        $stockTransactionId < 1
        ||
        $targetCycleId < 1
    ) {
        throw new InvalidArgumentException(
            'Allocated stock provenance identity is invalid.'
        );
    }

    $amount =
        trim(
            (string)(
                $row['economic_amount']
                ?? ''
            )
        );

    if (
        $amount === ''
        ||
        !is_numeric($amount)
    ) {
        throw new InvalidArgumentException(
            'Allocated stock provenance amount is invalid.'
        );
    }

    $nullableString =
        static function ($value): ?string {
            if (
                $value === null
                ||
                $value === ''
            ) {
                return null;
            }

            return
                (string)$value;
        };

    /*
     * The provenance revision is economic/causal:
     * immutable parent stock facts + this explicit target amount.
     *
     * Allocation notes are presentation/audit metadata and therefore do not
     * alter the economic revision digest.
     */
    $revisionFacts = [
        'stock_transaction_id' =>
            $stockTransactionId,

        'stock_item_id' =>
            (int)(
                $row['stock_item_id']
                ?? 0
            ),

        'transaction_date' =>
            (string)(
                $row['transaction_date']
                ?? ''
            ),

        'transaction_type' =>
            (string)(
                $row['transaction_type']
                ?? ''
            ),

        'quantity' =>
            (string)(
                $row['quantity']
                ?? ''
            ),

        'unit_cost' =>
            $nullableString(
                $row['unit_cost']
                ?? null
            ),

        'parent_total_cost' =>
            $nullableString(
                $row['total_cost']
                ?? null
            ),

        'financial_classification' =>
            $nullableString(
                $row[
                    'financial_classification'
                ]
                ?? null
            ),

        'parent_source_type' =>
            $nullableString(
                $row['source_type']
                ?? null
            ),

        'parent_source_id' =>
            $nullableString(
                $row['source_id']
                ?? null
            ),

        'parent_farm_type' =>
            $nullableString(
                $row['farm_type']
                ?? null
            ),

        'parent_production_type' =>
            $nullableString(
                $row['production_type']
                ?? null
            ),

        'parent_attribution_scope' =>
            $nullableString(
                $row['attribution_scope']
                ?? null
            ),

        'target_cycle_id' =>
            $targetCycleId,

        'allocated_amount' =>
            $amount,
    ];

    /*
     * Feed accounting status comes from the immutable parent movement
     * Financial Type snapshot. Retain the historical metadata inputs below
     * solely so allocated Feed provenance remains digest-compatible with the
     * existing Production-Entry provenance recipe.
     */
    if ($role === 'feed_use') {
        $revisionFacts['feed_category'] =
            $nullableString(
                $row['feed_category']
                ?? null
            );

        $revisionFacts[
            'feed_category_name_normalized'
        ] =
            strtolower(
                (string)(
                    $row['category_name']
                    ?? ''
                )
            );
    }

    $revisionNo =
        (int)(
            $row[
                'allocation_revision_no'
            ]
            ?? 0
        );

    return
        poultry_production_entry_provenance_source([
            'role' =>
                $role,

            'source_type' =>
                'stock_consumption_allocation',

            'source_id' =>
                $allocationId,

            /*
             * Event/version identity is kept separately from the causal
             * digest. A later allocation revision is therefore auditable even
             * when its economic amount remains unchanged.
             */
            'source_version' =>
                $revisionNo > 0
                    ? (string)$revisionNo
                    : null,

            'source_revision' =>
                poultry_production_entry_provenance_revision(
                    $revisionFacts
                ),

            'effective_date' =>
                (string)(
                    $row[
                        'transaction_date'
                    ]
                    ?? ''
                ),
        ]);
}
}

if (!function_exists('poultry_production_entry_expense_causal_revision')) {
function poultry_production_entry_expense_causal_revision(
    array $row
): ?string {
    $revisionNo =
        $row['expense_revision_no']
        ?? null;

    $fingerprintRaw =
        $row['expense_causal_fingerprint']
        ?? null;

    $hasRevisionNo =
        $revisionNo !== null
        && trim((string)$revisionNo) !== '';

    $hasFingerprint =
        $fingerprintRaw !== null
        && trim((string)$fingerprintRaw) !== '';

    if ($hasRevisionNo !== $hasFingerprint) {
        throw new RuntimeException(
            'Expense revision provenance metadata is incomplete.'
        );
    }

    if (!$hasRevisionNo) {
        return null;
    }

    $revisionText =
        trim(
            (string)$revisionNo
        );

    $fingerprint =
        strtolower(
            trim(
                (string)$fingerprintRaw
            )
        );

    if (
        preg_match(
            '/^[1-9][0-9]*$/',
            $revisionText
        ) !== 1
        ||
        preg_match(
            '/^[a-f0-9]{64}$/',
            $fingerprint
        ) !== 1
    ) {
        throw new RuntimeException(
            'Expense revision provenance metadata is invalid.'
        );
    }

    return $fingerprint;
}
}

if (!function_exists('poultry_production_entry_expense_source_revision')) {
function poultry_production_entry_expense_source_revision(
    array $row,
    array $legacyFacts
): string {
    $canonical =
        poultry_production_entry_expense_causal_revision(
            $row
        );

    if ($canonical !== null) {
        return $canonical;
    }

    return poultry_production_entry_provenance_revision(
        $legacyFacts
    );
}
}

if (!function_exists('poultry_production_entry_direct_expense_provenance_source')) {
function poultry_production_entry_direct_expense_provenance_source(
    array $row
): array {
    $legacyFacts = [
        'expense_date' =>
            (string)$row['expense_date'],
        'cycle_id' =>
            $row['cycle_id'] === null
            || $row['cycle_id'] === ''
                ? null
                : (int)$row['cycle_id'],
        'category' =>
            (string)$row['category'],
        'amount' =>
            (string)$row['amount'],
        'unit' =>
            (string)$row['unit'],
    ];

    return poultry_production_entry_provenance_source([
        'role' => 'direct_expense',
        'source_type' => 'farm_expense',
        'source_id' => (int)$row['id'],
        'source_revision' =>
            poultry_production_entry_expense_source_revision(
                $row,
                $legacyFacts
            ),
        'effective_date' =>
            (string)$row['expense_date'],
    ]);
}
}

if (!function_exists('poultry_production_entry_explicit_allocation_provenance_source')) {
function poultry_production_entry_explicit_allocation_provenance_source(
    array $row
): array {
    $revisionFacts = [
        'expense_id' =>
            (int)$row['expense_id'],
        'cycle_id' =>
            $row['cycle_id'] === null
            || $row['cycle_id'] === ''
                ? null
                : (int)$row['cycle_id'],
        'allocated_amount' =>
            (string)$row['allocated_amount'],
        'expense_date' =>
            (string)$row['expense_date'],
        'expense_category' =>
            (string)$row['category'],
    ];

    $parentExpenseRevision =
        poultry_production_entry_expense_causal_revision(
            $row
        );

    if ($parentExpenseRevision !== null) {
        $revisionFacts[
            'expense_causal_fingerprint'
        ] =
            $parentExpenseRevision;
    }

    return poultry_production_entry_provenance_source([
        'role' => 'explicit_shared_allocation',
        'source_type' => 'financial_allocation',
        'source_id' => (int)$row['id'],
        'source_revision' =>
            poultry_production_entry_provenance_revision(
                $revisionFacts
            ),
        'effective_date' =>
            (string)$row['expense_date'],
    ]);
}
}

if (!function_exists('poultry_production_entry_shared_pool_expense_provenance_source')) {
function poultry_production_entry_shared_pool_expense_provenance_source(
    array $row
): array {
    $legacyFacts = [
        'expense_date' =>
            (string)$row['expense_date'],
        'farm_type' =>
            (string)$row['farm_type'],
        'production_type' =>
            strtolower(
                (string)$row['production_type']
            ),
        'cycle_id' =>
            $row['cycle_id'] === null
            || $row['cycle_id'] === ''
                ? null
                : (int)$row['cycle_id'],
        'category' =>
            (string)$row['category'],
        'amount' =>
            (string)$row['amount'],
        'unit' =>
            (string)$row['unit'],
    ];

    return poultry_production_entry_provenance_source([
        'role' => 'shared_pool_expense',
        'source_type' => 'farm_expense',
        'source_id' => (int)$row['id'],
        'source_revision' =>
            poultry_production_entry_expense_source_revision(
                $row,
                $legacyFacts
            ),
        'effective_date' =>
            (string)$row['expense_date'],
    ]);
}
}

if (!function_exists('poultry_production_entry_shared_pool_allocation_provenance_source')) {
function poultry_production_entry_shared_pool_allocation_provenance_source(
    array $row,
    string $expenseDate
): array {
    $revisionFacts = [
        'expense_id' =>
            (int)$row['expense_id'],
        'cycle_id' =>
            $row['cycle_id'] === null
            || $row['cycle_id'] === ''
                ? null
                : (int)$row['cycle_id'],
        'allocated_amount' =>
            (string)$row['allocated_amount'],
    ];

    $parentExpenseRevision =
        poultry_production_entry_expense_causal_revision(
            $row
        );

    if ($parentExpenseRevision !== null) {
        $revisionFacts[
            'expense_causal_fingerprint'
        ] =
            $parentExpenseRevision;
    }

    return poultry_production_entry_provenance_source([
        'role' => 'shared_pool_allocation',
        'source_type' => 'financial_allocation',
        'source_id' => (int)$row['id'],
        'source_revision' =>
            poultry_production_entry_provenance_revision(
                $revisionFacts
            ),
        'effective_date' => $expenseDate,
    ]);
}
}

if (!function_exists('poultry_production_entry_daily_reconciliation_provenance_source')) {
function poultry_production_entry_daily_reconciliation_provenance_source(
    array $row,
    string $role
): array {
    $role =
        strtolower(
            trim($role)
        );

    if (
        !in_array(
            $role,
            [
                'rearing_end_daily_reconciliation',
                'production_start_daily_reconciliation',
            ],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Select a valid Daily Record reconciliation provenance role.'
        );
    }

    $facts = [
        'record_date' =>
            (string)$row['record_date'],
        'opening_stock' =>
            (int)$row['opening_stock'],
    ];

    if (
        $role
        === 'rearing_end_daily_reconciliation'
    ) {
        $facts['mortality'] =
            (int)$row['mortality'];
    }

    return poultry_production_entry_provenance_source([
        'role' => $role,
        'source_type' => 'layer_daily_record',
        'source_id' => (int)$row['id'],
        'source_revision' =>
            poultry_production_entry_provenance_revision(
                $facts
            ),
        'effective_date' =>
            (string)$row['record_date'],
    ]);
}
}

if (!function_exists('poultry_production_entry_population_boundary')) {
function poultry_production_entry_population_boundary(
    PDO $pdo,
    int $farmId,
    int $cycleId,
    string $rearingEnd,
    string $productionStart
): array {
    $result = [
        'headcount' => null,
        'source' => null,
        'canonical_state' => null,
        'rearing_closing' => null,
        'production_opening' => null,
        'provenance_sources' => [],
        'reconciliation_provenance_sources' => [],
        'warnings' => [],
    ];

    // Exact Daily Records remain valuable reconciliation evidence, but they
    // are not the population authority once the cycle has entered the V3
    // canonical population contract.
    $entryStmt = $pdo->prepare(
        'SELECT
             id,
             record_date,
             opening_stock
         FROM layer_daily_records
         WHERE farm_id = ?
           AND cycle_id = ?
           AND record_date = ?
         LIMIT 1'
    );

    $entryStmt->execute([
        $farmId,
        $cycleId,
        $productionStart,
    ]);

    $productionStartRow =
        $entryStmt->fetch(
            PDO::FETCH_ASSOC
        );

    if ($productionStartRow) {
        $result['production_opening'] =
            (int)$productionStartRow[
                'opening_stock'
            ];

        $result[
            'reconciliation_provenance_sources'
        ][] =
            poultry_production_entry_daily_reconciliation_provenance_source(
                $productionStartRow,
                'production_start_daily_reconciliation'
            );
    }

    $endStmt = $pdo->prepare(
        'SELECT
             id,
             record_date,
             opening_stock,
             mortality
         FROM layer_daily_records
         WHERE farm_id = ?
           AND cycle_id = ?
           AND record_date = ?
         LIMIT 1'
    );

    $endStmt->execute([
        $farmId,
        $cycleId,
        $rearingEnd,
    ]);

    $rearingEndRow =
        $endStmt->fetch(
            PDO::FETCH_ASSOC
        );

    if ($rearingEndRow) {
        $result['rearing_closing'] =
            max(
                0,
                (int)$rearingEndRow[
                    'opening_stock'
                ]
                - (int)$rearingEndRow[
                    'mortality'
                ]
            );

        $result[
            'reconciliation_provenance_sources'
        ][] =
            poultry_production_entry_daily_reconciliation_provenance_source(
                $rearingEndRow,
                'rearing_end_daily_reconciliation'
            );
    }

    // First establish whether this historical boundary is covered by the
    // canonical V3 baseline. A later legacy-cutover baseline cannot be used
    // to reconstruct an earlier Production-Entry boundary.
    $currentPopulationState =
        production_population_state(
            $pdo,
            $farmId,
            $cycleId
        );

    if (
        $currentPopulationState !== null
        && $rearingEnd >=
            (string)$currentPopulationState['baseline_date']
    ) {
        $canonicalState =
            production_population_state(
                $pdo,
                $farmId,
                $cycleId,
                $rearingEnd
            );

        if ($canonicalState !== null) {
            $result['canonical_state'] =
                $canonicalState;

            $populationHistory =
                production_population_history(
                    $pdo,
                    $farmId,
                    $cycleId,
                    $rearingEnd
                );

            if (is_array($populationHistory)) {
                $baseline =
                    $populationHistory['baseline']
                    ?? null;

                if (is_array($baseline)) {
                    $result['provenance_sources'][] =
                        poultry_production_entry_provenance_source([
                            'role' =>
                                'population_baseline',
                            'source_type' =>
                                'production_population_baseline',
                            'source_id' =>
                                (int)$baseline['id'],
                            'source_revision' =>
                                poultry_production_entry_provenance_revision([
                                    'baseline_date' =>
                                        (string)$baseline['baseline_date'],
                                    'baseline_quantity' =>
                                        (int)$baseline['baseline_quantity'],
                                    'baseline_source' =>
                                        (string)$baseline['baseline_source'],
                                ]),
                            'effective_date' =>
                                (string)$baseline['baseline_date'],
                        ]);
                }

                foreach (
                    $populationHistory['movements'] ?? []
                    as $movement
                ) {
                    $result['provenance_sources'][] =
                        poultry_production_entry_provenance_source([
                            'role' =>
                                'population_movement',
                            'source_type' =>
                                'production_population_movement',
                            'source_id' =>
                                (int)$movement['id'],
                            'source_version' =>
                                (string)$movement['source_version'],
                            'source_revision' =>
                                poultry_production_entry_provenance_revision([
                                    'movement_date' =>
                                        (string)$movement['movement_date'],
                                    'movement_type' =>
                                        (string)$movement['movement_type'],
                                    'quantity_delta' =>
                                        (int)$movement['quantity_delta'],
                                    'source_type' =>
                                        (string)$movement['source_type'],
                                    'source_id' =>
                                        $movement['source_id'] === null
                                            ? null
                                            : (string)$movement['source_id'],
                                    'source_version' =>
                                        (int)$movement['source_version'],
                                    'reversal_of_id' =>
                                        $movement['reversal_of_id'] === null
                                            ? null
                                            : (int)$movement['reversal_of_id'],
                                ]),
                            'effective_date' =>
                                (string)$movement['movement_date'],
                        ]);
                }
            }

            $canonicalHeadcount =
                (int)$canonicalState['quantity'];

            $result['headcount'] =
                $canonicalHeadcount;

            $reconciledChecks = 0;

            if ($result['rearing_closing'] !== null) {
                if (
                    $result['rearing_closing']
                    === $canonicalHeadcount
                ) {
                    $reconciledChecks++;
                } else {
                    $result['warnings'][] =
                        'Canonical Production-Entry population does not reconcile to the rearing-end Daily Record closing flock.';
                }
            }

            if ($result['production_opening'] !== null) {
                if (
                    $result['production_opening']
                    === $canonicalHeadcount
                ) {
                    $reconciledChecks++;
                } else {
                    $result['warnings'][] =
                        'Canonical Production-Entry population does not reconcile to the production-start Daily Record opening flock.';
                }
            }

            if ($reconciledChecks === 2) {
                $result['source'] =
                    'Canonical population ledger at Rearing close, reconciled to exact Daily Record boundary';
            } elseif ($reconciledChecks === 1) {
                $result['source'] =
                    'Canonical population ledger at Rearing close, cross-checked to available Daily Record boundary';
            } else {
                $result['source'] =
                    'Canonical population ledger at Rearing close';
            }

            return $result;
        }
    }

    // Legacy fallback only: where no V3 baseline covers the historical
    // boundary, preserve the established exact Daily Record reconciliation.
    if (
        $result['production_opening'] !== null
        && $result['rearing_closing'] !== null
    ) {
        if (
            $result['production_opening']
            === $result['rearing_closing']
        ) {
            $result['headcount'] =
                $result['production_opening'];

            $result['source'] =
                'Production-start opening flock, reconciled to prior rearing-day closing flock';
        } else {
            $result['warnings'][] =
                'Production-entry flock boundary does not reconcile: production opening flock differs from the preceding rearing-day closing flock.';
        }
    } elseif ($result['production_opening'] !== null) {
        $result['headcount'] =
            $result['production_opening'];

        $result['source'] =
            'Production-start opening flock';
    } elseif ($result['rearing_closing'] !== null) {
        $result['headcount'] =
            $result['rearing_closing'];

        $result['source'] =
            'Rearing-end closing flock';
    } else {
        $result['warnings'][] =
            'No canonical population boundary or exact Daily Record boundary is available, so surviving Production-Entry flock is not inferred.';
    }

    return $result;
}
}

/**
 * V2.2.50 Batch 3A — read-only Layer rearing / production-entry economics.
 *
 * This helper does not post financial transactions and does not modify the
 * canonical period-profitability engine. It interprets already-attributed,
 * historical source records inside the explicit Layer Rearing phase.
 */
if (!function_exists('poultry_rearing_economics')) {
function poultry_rearing_economics(PDO $pdo, int $farmId, int $cycleId): array
{
    $base = [
        'available' => false,
        'mode' => 'not_available',
        'message' => 'Rearing economics are not available for this cycle.',
        'cycle' => null,
        'rearing_phase' => null,
        'production_phase' => null,
        'acquisition_cost' => 0.0,
        'acquisition_quantity' => 0,
        'feed_consumed_cost' => 0.0,
        'direct_feed_consumed_cost' => 0.0,
        'allocated_feed_consumed_cost' => 0.0,
        'inventory_operating_cost' => 0.0,
        'direct_inventory_operating_cost' => 0.0,
        'allocated_inventory_operating_cost' => 0.0,
        'inventory_operating_breakdown' => [],
        'direct_expenses' => 0.0,
        'allocated_shared_expenses' => 0.0,
        'expense_breakdown' => [],
        'known_attributable_rearing_cost' => 0.0,
        'rearing_investment' => null,
        'production_entry_headcount' => null,
        'production_entry_headcount_source' => null,
        'provenance_sources' => [],
        'population_provenance_sources' => [],
        'investment_per_surviving_bird' => null,
        'uncosted_feed_uses' => 0,
        'uncosted_operating_uses' => 0,
        'uncosted_acquisition_entries' => 0,
        'unallocated_shared_expense_pool' => 0.0,
        'warnings' => [],
    ];

    $stmt = $pdo->prepare(
        "SELECT id, farm_id, cycle_code, farm_type, production_type, status, start_date, close_date,
                opening_headcount, closing_headcount, bird_unit_cost
         FROM production_cycles
         WHERE id=? AND farm_id=? AND farm_type='poultry'
         LIMIT 1"
    );
    $stmt->execute([$cycleId, $farmId]);
    $cycle = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cycle || strtolower((string)$cycle['production_type']) !== 'layer') {
        $base['message'] = 'Layer rearing economics are available only for a Layer production cycle in this farm.';
        return $base;
    }
    $base['cycle'] = $cycle;

    $phases = poultry_lifecycle_history($pdo, $farmId, $cycleId);
    $rearing = null;
    $production = null;
    foreach ($phases as $phase) {
        if ((string)$phase['phase'] === 'rearing' && $rearing === null) $rearing = $phase;
        if ((string)$phase['phase'] === 'production' && $production === null) $production = $phase;
    }
    $base['rearing_phase'] = $rearing;
    $base['production_phase'] = $production;

    foreach ([$rearing, $production] as $phaseRow) {
        if (
            is_array($phaseRow)
            && !empty($phaseRow['id'])
        ) {
            $base['provenance_sources'][] =
                poultry_production_entry_lifecycle_provenance_source(
                    $phaseRow
                );
        }
    }

    $acqRows = poultry_acquisition_history($pdo, $farmId, $cycleId);
    $activeAcq = array_values(array_filter($acqRows, static fn(array $r): bool => empty($r['voided_at'])));
    $polRows = array_values(array_filter($activeAcq, static fn(array $r): bool => (string)$r['acquisition_type'] === 'purchased_point_of_lay'));

    // POL is a distinct entry model. Do not fabricate an on-farm rearing phase.
    if ($rearing === null && !empty($polRows)) {
        $qty = 0; $cost = 0.0; $allCosted = true;
        foreach ($polRows as $row) {
            $qty += (int)$row['quantity'];

            if (
                $row['total_cost'] === null
                || $row['total_cost'] === ''
            ) {
                $allCosted = false;
            } else {
                $cost += (float)$row['total_cost'];
            }

            $base['provenance_sources'][] =
                poultry_production_entry_acquisition_provenance_source(
                    $row
                );
        }
        $base['available'] = true;
        $base['mode'] = 'pol';
        $base['message'] = 'Purchased Point-of-Lay entry. On-farm rearing investment is not applicable.';
        $base['acquisition_quantity'] = $qty;
        $base['acquisition_cost'] = $allCosted ? round($cost, 2) : 0.0;
        $base['rearing_investment'] = $allCosted ? round($cost, 2) : null;
        $base['production_entry_headcount'] = $qty > 0 ? $qty : null;
        $base['production_entry_headcount_source'] = $qty > 0 ? 'Recorded Point-of-Lay acquisition quantity' : null;
        $base['investment_per_surviving_bird'] = ($allCosted && $qty > 0) ? round($cost / $qty, 2) : null;
        if (!$allCosted) $base['warnings'][] = 'One or more active Point-of-Lay acquisition entries do not have a defensible cost basis.';
        if ($production === null) $base['warnings'][] = 'Production lifecycle phase has not yet been recorded; acquisition basis is shown without inventing a production-entry date.';
        return $base;
    }

    if ($rearing === null) {
        $base['message'] = 'No explicit Layer Rearing phase is recorded. Rearing is not inferred from bird age, egg output, feed, or cycle status.';
        return $base;
    }
    if (empty($rearing['end_date']) || $production === null) {
        $base['message'] = 'Rearing is still open or no Production transition is recorded. Accumulated rearing cost can be reviewed only after a known production-entry boundary exists.';
        return $base;
    }

    $start = (string)$rearing['start_date'];
    $end = (string)$rearing['end_date'];
    $productionStart = (string)$production['start_date'];

    // Acquisition basis: active non-POL entries received no later than the end of rearing.
    $acqCost = 0.0; $acqQty = 0; $uncostedAcq = 0; $eligibleAcqRows = 0;
    foreach ($activeAcq as $row) {
        if (
            (string)$row['acquisition_type']
            === 'purchased_point_of_lay'
        ) {
            continue;
        }

        if ((string)$row['acquisition_date'] > $end) {
            continue;
        }

        $eligibleAcqRows++;
        $acqQty += (int)$row['quantity'];

        if (
            $row['total_cost'] === null
            || $row['total_cost'] === ''
        ) {
            $uncostedAcq++;
        } else {
            $acqCost += (float)$row['total_cost'];
        }

        $base['provenance_sources'][] =
            poultry_production_entry_acquisition_provenance_source(
                $row
            );
    }
    $base['acquisition_cost'] = round($acqCost, 2);
    $base['acquisition_quantity'] = $acqQty;
    $base['uncosted_acquisition_entries'] = $uncostedAcq;
    if ($eligibleAcqRows === 0) {
        $uncostedAcq++;
        $base['uncosted_acquisition_entries'] = $uncostedAcq;
    }

    $effective =
        stock_effective_sql_predicate('t');

    $feedPredicate =
        stock_feed_transaction_sql_predicate(
            't'
        );

    /*
     * Read the exact effective Feed movements rather than only their SUM.
     * The same canonical predicates remain authoritative; row retention is
     * needed only so Production-Entry provenance can identify its sources.
     */
    $feedSql =
        "SELECT
             t.id,
             t.stock_item_id,
             t.transaction_date,
             t.transaction_type,
             t.quantity,
             t.unit_cost,
             t.total_cost,
             t.financial_classification,
             t.source_type,
             t.source_id,
             s.feed_category AS feed_category,
             c.category_name AS category_name
         FROM stock_transactions t
         JOIN stock_items s
           ON s.id = t.stock_item_id
          AND s.farm_id = t.farm_id
         LEFT JOIN inventory_categories c
           ON c.id = s.category_id
          AND c.farm_id = s.farm_id
         WHERE t.farm_id = ?
           AND t.cycle_id = ?
           AND t.transaction_type = 'used'
           AND {$effective}
           AND {$feedPredicate}
           AND t.transaction_date BETWEEN ? AND ?
         ORDER BY t.transaction_date ASC, t.id ASC";

    $stmt =
        $pdo->prepare($feedSql);

    $stmt->execute([
        $farmId,
        $cycleId,
        $start,
        $end,
    ]);

    $base['feed_consumed_cost'] = 0.0;
    $base['uncosted_feed_uses'] = 0;

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        as $row
    ) {
        if (
            $row['total_cost'] === null
            || $row['total_cost'] === ''
        ) {
            $base['uncosted_feed_uses']++;
        } else {
            $value =
                (float)$row['total_cost'];

            $base[
                'direct_feed_consumed_cost'
            ] +=
                $value;

            $base['feed_consumed_cost'] +=
                $value;
        }

        $base['provenance_sources'][] =
            poultry_production_entry_stock_use_provenance_source(
                $row,
                'feed_use'
            );
    }

    $base['feed_consumed_cost'] =
        round(
            $base['feed_consumed_cost'],
            2
        );

    /*
     * Operating stock uses follow the same effective-ledger policy.
     * Retain each contributing row, then reconstruct the same grouped
     * economics in PHP so provenance and economics share one source set.
     */
    $classes =
        array_keys(
            inventory_operating_consumption_classifications()
        );

    if ($classes) {
        $ph =
            implode(
                ',',
                array_fill(
                    0,
                    count($classes),
                    '?'
                )
            );

        $sql =
            "SELECT
                 t.id,
                 t.stock_item_id,
                 t.transaction_date,
                 t.transaction_type,
                 t.quantity,
                 t.unit_cost,
                 t.total_cost,
                 t.financial_classification,
                 t.source_type,
                 t.source_id
             FROM stock_transactions t
             WHERE t.farm_id = ?
               AND t.cycle_id = ?
               AND t.transaction_type = 'used'
               AND {$effective}
               AND t.transaction_date BETWEEN ? AND ?
               AND t.financial_classification IN ({$ph})
             ORDER BY t.transaction_date ASC, t.id ASC";

        $params =
            array_merge(
                [
                    $farmId,
                    $cycleId,
                    $start,
                    $end,
                ],
                $classes
            );

        $stmt =
            $pdo->prepare($sql);

        $stmt->execute($params);

        foreach (
            $stmt->fetchAll(PDO::FETCH_ASSOC)
            as $row
        ) {
            $key =
                (string)$row[
                    'financial_classification'
                ];

            if (
                !array_key_exists(
                    $key,
                    $base[
                        'inventory_operating_breakdown'
                    ]
                )
            ) {
                $base[
                    'inventory_operating_breakdown'
                ][$key] = 0.0;
            }

            if (
                $row['total_cost'] === null
                || $row['total_cost'] === ''
            ) {
                $base[
                    'uncosted_operating_uses'
                ]++;
            } else {
                $value =
                    (float)$row['total_cost'];

                $base[
                    'inventory_operating_breakdown'
                ][$key] += $value;

                $base[
                    'direct_inventory_operating_cost'
                ] +=
                    $value;

                $base[
                    'inventory_operating_cost'
                ] += $value;
            }

            $base['provenance_sources'][] =
                poultry_production_entry_stock_use_provenance_source(
                    $row,
                    'operating_inventory_use'
                );
        }

        foreach (
            $base['inventory_operating_breakdown']
            as $key => $value
        ) {
            $base[
                'inventory_operating_breakdown'
            ][$key] =
                round(
                    (float)$value,
                    2
                );
        }

        $base['inventory_operating_cost'] =
            round(
                $base['inventory_operating_cost'],
                2
            );
    }

    /*
     * Explicit consumed-stock allocations are defensible cycle attribution.
     *
     * Existing direct-cycle stock logic above remains authoritative for
     * native rows, including uncosted-use disclosure and its established
     * provenance. The central consumed-stock economics reader contributes
     * only broader-parent allocations that explicitly target this cycle.
     *
     * Filtering to explicit_allocation is essential: native_parent rows are
     * already counted above and must never be counted twice.
     */
    $allocatedStockRows =
        stock_consumption_economics_rows(
            $pdo,
            $farmId,
            $start,
            $end,
            'poultry',
            'layer',
            $cycleId,
            false
        );

    foreach (
        $allocatedStockRows
        as $row
    ) {
        if (
            (string)(
                $row[
                    'attribution_mode'
                ]
                ?? ''
            )
            !== 'explicit_allocation'
        ) {
            continue;
        }

        if (
            (int)(
                $row[
                    'target_cycle_id'
                ]
                ?? 0
            )
            !== $cycleId
        ) {
            throw new RuntimeException(
                'Consumed-stock allocation escaped the requested Rearing cycle.'
            );
        }

        $value =
            round(
                (float)(
                    $row[
                        'economic_amount'
                    ]
                    ?? 0
                ),
                2
            );

        if ($value <= 0) {
            continue;
        }

        if (
            (string)(
                $row[
                    'cost_kind'
                ]
                ?? ''
            )
            === 'feed'
        ) {
            $base[
                'allocated_feed_consumed_cost'
            ] +=
                $value;

            $base[
                'feed_consumed_cost'
            ] +=
                $value;

            $base[
                'provenance_sources'
            ][] =
                poultry_production_entry_stock_allocation_provenance_source(
                    $row,
                    'feed_use'
                );

            continue;
        }

        $classification =
            strtolower(
                trim(
                    (string)(
                        $row[
                            'cost_classification'
                        ]
                        ?? ''
                    )
                )
            );

        if (
            !inventory_financial_classification_is_operating_consumption(
                $classification
            )
        ) {
            throw new RuntimeException(
                'Consumed-stock allocation has a non-operating Rearing classification.'
            );
        }

        if (
            !array_key_exists(
                $classification,
                $base[
                    'inventory_operating_breakdown'
                ]
            )
        ) {
            $base[
                'inventory_operating_breakdown'
            ][
                $classification
            ] = 0.0;
        }

        $base[
            'inventory_operating_breakdown'
        ][
            $classification
        ] +=
            $value;

        $base[
            'allocated_inventory_operating_cost'
        ] +=
            $value;

        $base[
            'inventory_operating_cost'
        ] +=
            $value;

        $base[
            'provenance_sources'
        ][] =
            poultry_production_entry_stock_allocation_provenance_source(
                $row,
                'operating_inventory_use'
            );
    }

    $base['feed_consumed_cost'] =
        round(
            (float)$base[
                'feed_consumed_cost'
            ],
            2
        );

    $base['inventory_operating_cost'] =
        round(
            (float)$base[
                'inventory_operating_cost'
            ],
            2
        );

    foreach (
        [
            'direct_feed_consumed_cost',
            'allocated_feed_consumed_cost',
            'direct_inventory_operating_cost',
            'allocated_inventory_operating_cost',
        ]
        as $compositionKey
    ) {
        $base[$compositionKey] =
            round(
                (float)$base[$compositionKey],
                2
            );
    }

    if (
        abs(
            (
                $base['direct_feed_consumed_cost']
                +
                $base['allocated_feed_consumed_cost']
            )
            -
            $base['feed_consumed_cost']
        ) > 0.009
    ) {
        throw new RuntimeException(
            'Rearing Feed attribution composition does not conserve the Feed total.'
        );
    }

    if (
        abs(
            (
                $base['direct_inventory_operating_cost']
                +
                $base['allocated_inventory_operating_cost']
            )
            -
            $base['inventory_operating_cost']
        ) > 0.009
    ) {
        throw new RuntimeException(
            'Rearing operating-stock attribution composition does not conserve the operating-stock total.'
        );
    }

    foreach (
        $base[
            'inventory_operating_breakdown'
        ]
        as $classification => $value
    ) {
        $base[
            'inventory_operating_breakdown'
        ][
            $classification
        ] =
            round(
                (float)$value,
                2
            );
    }

    /*
     * Direct non-feed expenses recorded specifically against this cycle.
     * Retain each row so provenance identifies the same source rows that
     * produce the existing category totals.
     */
    $sql =
        "SELECT
             id,
             expense_date,
             cycle_id,
             category,
             amount,
             unit,
             expense_revision_no,
             expense_causal_fingerprint
         FROM farm_expenses
         WHERE farm_id = ?
           AND cycle_id = ?
           AND expense_date BETWEEN ? AND ?
           AND category <> 'feeds'
         ORDER BY expense_date ASC, id ASC";

    $stmt =
        $pdo->prepare($sql);

    $stmt->execute([
        $farmId,
        $cycleId,
        $start,
        $end,
    ]);

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        as $row
    ) {
        $cat =
            (string)$row['category'];

        $value =
            (float)$row['amount']
            * (float)$row['unit'];

        $base['expense_breakdown'][$cat] =
            (
                $base['expense_breakdown'][$cat]
                ?? 0.0
            ) + $value;

        $base['direct_expenses'] +=
            $value;

        $base['provenance_sources'][] =
            poultry_production_entry_direct_expense_provenance_source(
                $row
            );
    }

    $base['direct_expenses'] =
        round(
            $base['direct_expenses'],
            2
        );

    /*
     * Explicit shared-expense allocations are defensible cycle
     * attribution. Keep the allocation row plus the joined expense
     * boundary facts that control eligibility and category.
     */
    $sql =
        "SELECT
             fa.id,
             fa.expense_id,
             fa.cycle_id,
             fa.allocated_amount,
             e.expense_date,
             e.category,
             e.expense_revision_no,
             e.expense_causal_fingerprint
         FROM financial_allocations fa
         JOIN farm_expenses e
           ON e.id = fa.expense_id
          AND e.farm_id = fa.farm_id
         WHERE fa.farm_id = ?
           AND fa.cycle_id = ?
           AND e.expense_date BETWEEN ? AND ?
           AND e.category <> 'feeds'
         ORDER BY e.expense_date ASC, fa.id ASC";

    $stmt =
        $pdo->prepare($sql);

    $stmt->execute([
        $farmId,
        $cycleId,
        $start,
        $end,
    ]);

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        as $row
    ) {
        $cat =
            (string)$row['category'];

        $value =
            (float)$row['allocated_amount'];

        $base['expense_breakdown'][$cat] =
            (
                $base['expense_breakdown'][$cat]
                ?? 0.0
            ) + $value;

        $base['allocated_shared_expenses'] +=
            $value;

        $base['provenance_sources'][] =
            poultry_production_entry_explicit_allocation_provenance_source(
                $row
            );
    }

    $base['allocated_shared_expenses'] =
        round(
            $base['allocated_shared_expenses'],
            2
        );

    /*
     * Disclosure-only shared Layer pool.
     *
     * Its residual depends on both each qualifying shared expense and
     * every allocation against that expense, including allocations to
     * other cycles. Preserve all of those dependencies in provenance.
     */
    $sql =
        "SELECT
             e.id AS expense_id,
             e.expense_date,
             e.farm_type,
             e.production_type,
             e.cycle_id AS expense_cycle_id,
             e.category,
             e.amount,
             e.unit,
             e.expense_revision_no,
             e.expense_causal_fingerprint,
             fa.id AS allocation_id,
             fa.cycle_id AS allocation_cycle_id,
             fa.allocated_amount
         FROM farm_expenses e
         LEFT JOIN financial_allocations fa
           ON fa.farm_id = e.farm_id
          AND fa.expense_id = e.id
         WHERE e.farm_id = ?
           AND e.cycle_id IS NULL
           AND e.expense_date BETWEEN ? AND ?
           AND e.farm_type IN ('poultry','both')
           AND LOWER(
               COALESCE(
                   e.production_type,
                   ''
               )
           ) = 'layer'
           AND e.category <> 'feeds'
         ORDER BY
             e.expense_date ASC,
             e.id ASC,
             fa.id ASC";

    $stmt =
        $pdo->prepare($sql);

    $stmt->execute([
        $farmId,
        $start,
        $end,
    ]);

    $sharedPoolExpenses = [];

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        as $row
    ) {
        $expenseId =
            (int)$row['expense_id'];

        if (
            !isset(
                $sharedPoolExpenses[
                    $expenseId
                ]
            )
        ) {
            $expenseSourceRow = [
                'id' => $expenseId,
                'expense_date' =>
                    (string)$row['expense_date'],
                'farm_type' =>
                    (string)$row['farm_type'],
                'production_type' =>
                    (string)$row['production_type'],
                'cycle_id' =>
                    $row['expense_cycle_id'],
                'category' =>
                    (string)$row['category'],
                'amount' =>
                    (string)$row['amount'],
                'unit' =>
                    (string)$row['unit'],
                'expense_revision_no' =>
                    $row['expense_revision_no'],
                'expense_causal_fingerprint' =>
                    $row['expense_causal_fingerprint'],
            ];

            $sharedPoolExpenses[$expenseId] = [
                'gross' =>
                    (float)$row['amount']
                    * (float)$row['unit'],
                'allocated' => 0.0,
            ];

            $base['provenance_sources'][] =
                poultry_production_entry_shared_pool_expense_provenance_source(
                    $expenseSourceRow
                );
        }

        if (
            $row['allocation_id'] !== null
            && $row['allocation_id'] !== ''
        ) {
            $allocationSourceRow = [
                'id' =>
                    (int)$row['allocation_id'],
                'expense_id' =>
                    $expenseId,
                'cycle_id' =>
                    $row['allocation_cycle_id'],
                'allocated_amount' =>
                    (string)$row[
                        'allocated_amount'
                    ],
                'expense_revision_no' =>
                    $row['expense_revision_no'],
                'expense_causal_fingerprint' =>
                    $row['expense_causal_fingerprint'],
            ];

            $sharedPoolExpenses[
                $expenseId
            ]['allocated'] +=
                (float)$row[
                    'allocated_amount'
                ];

            $base['provenance_sources'][] =
                poultry_production_entry_shared_pool_allocation_provenance_source(
                    $allocationSourceRow,
                    (string)$row['expense_date']
                );
        }
    }

    $base['unallocated_shared_expense_pool'] =
        0.0;

    foreach (
        $sharedPoolExpenses
        as $sharedPoolExpense
    ) {
        $base[
            'unallocated_shared_expense_pool'
        ] += max(
            (float)$sharedPoolExpense['gross']
            - (float)$sharedPoolExpense[
                'allocated'
            ],
            0.0
        );
    }

    $base['unallocated_shared_expense_pool'] =
        round(
            $base[
                'unallocated_shared_expense_pool'
            ],
            2
        );

    $complete = $uncostedAcq===0 && $base['uncosted_feed_uses']===0 && $base['uncosted_operating_uses']===0;
    $investment = $acqCost + $base['feed_consumed_cost'] + $base['inventory_operating_cost'] + $base['direct_expenses'] + $base['allocated_shared_expenses'];
    $base['known_attributable_rearing_cost'] = round($investment, 2);
    $base['rearing_investment'] = $complete ? round($investment,2) : null;

    // Production-entry flock authority:
    // - V3-tracked cycles use the canonical population ledger at Rearing close;
    // - exact Daily Records are reconciliation evidence only;
    // - legacy cycles without a covering V3 baseline retain exact-boundary
    //   fallback behavior.
    $populationBoundary =
        poultry_production_entry_population_boundary(
            $pdo,
            $farmId,
            $cycleId,
            $end,
            $productionStart
        );

    $base['production_entry_headcount'] =
        $populationBoundary['headcount'];

    $base['production_entry_headcount_source'] =
        $populationBoundary['source'];

    $base['population_provenance_sources'] =
        $populationBoundary['provenance_sources']
        ?? [];

    foreach (
        $base['population_provenance_sources']
        as $populationSource
    ) {
        $base['provenance_sources'][] =
            $populationSource;
    }

    /*
     * Daily Records are reconciliation/boundary evidence only.
     * Keep them in the generic provenance manifest without adding
     * them to canonical population provenance.
     */
    foreach (
        $populationBoundary[
            'reconciliation_provenance_sources'
        ] ?? []
        as $reconciliationSource
    ) {
        $base['provenance_sources'][] =
            $reconciliationSource;
    }

    foreach (
        $populationBoundary['warnings'] as $boundaryWarning
    ) {
        $base['warnings'][] =
            $boundaryWarning;
    }

    if ($base['rearing_investment'] !== null && !empty($base['production_entry_headcount'])) {
        $base['investment_per_surviving_bird']=round($base['rearing_investment']/(int)$base['production_entry_headcount'],2);
    }
    if ($base['uncosted_feed_uses']>0) $base['warnings'][]='One or more feed-use transactions in the Rearing phase have no historical cost snapshot.';
    if ($base['uncosted_operating_uses']>0) $base['warnings'][]='One or more eligible non-feed inventory-use transactions in the Rearing phase have no historical cost snapshot.';
    if ($eligibleAcqRows===0) $base['warnings'][]='No active flock-entry/acquisition record is available for the Rearing phase, so rearing investment cannot be presented as complete.';
    elseif ($uncostedAcq>0) $base['warnings'][]='One or more active flock-entry records in the Rearing phase do not have a defensible acquisition cost.';
    if ($base['unallocated_shared_expense_pool']>0) $base['warnings'][]='Unallocated shared Layer expenses exist in the Rearing window. They are disclosed separately and are not silently assigned to this cycle.';

    $base['available']=true;
    $base['mode']='reared';
    $base['message']='Actual recorded costs attributed to the explicit Layer Rearing phase. Mortality value is not deducted again.';
    return $base;
}
}
