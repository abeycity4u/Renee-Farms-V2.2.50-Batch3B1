<?php

require_once __DIR__
    . '/stock_consumption_allocation_service.php';

require_once __DIR__
    . '/stock_consumption_source_resolver.php';

require_once __DIR__
    . '/stock_consumption_allocation_provenance.php';

/*
 * V3.0.1 canonical consumed-stock allocation persistence.
 *
 * Caller owns the PDO transaction.
 *
 * Lock order for linked Daily Record sources:
 *   1. pre-read immutable stock source identity
 *   2. authoritative Daily Record FOR UPDATE
 *   3. stock transaction FOR UPDATE
 *   4. target cycles in deterministic id order
 *   5. current allocation projection
 *   6. latest allocation revision
 *
 * The service never mutates stock_transactions.
 *
 * Stock reversal integration must invoke
 * stock_consumption_allocation_persistence_before_source_reversal()
 * while the original source movement is still effective and inside the
 * same transaction.
 */

if (!function_exists('stock_consumption_allocation_persistence_require_transaction')) {
function stock_consumption_allocation_persistence_require_transaction(
    PDO $pdo
): void {
    if (!$pdo->inTransaction()) {
        throw new RuntimeException(
            'Stock allocation persistence requires an active transaction.'
        );
    }
}
}

if (!function_exists('stock_consumption_allocation_persistence_reason')) {
function stock_consumption_allocation_persistence_reason(
    string $action,
    ?string $reason
): ?string {
    $reason =
        trim(
            (string)(
                $reason
                ?? ''
            )
        );

    /*
     * A first/create allocation may legitimately carry an optional reason.
     * This is required for audited pre-cycle preparation allocations.
     * Blank create reasons remain valid for ordinary first allocations.
     */
    if (
        in_array(
            $action,
            [
                'update',
                'clear',
                'source_reversal',
                'retain_shared',
            ],
            true
        )
        &&
        $reason === ''
    ) {
        throw new InvalidArgumentException(
            $action === 'retain_shared'
                ? 'Enter a reason for retaining this consumed stock cost as shared.'
                : 'Enter a reason for changing this stock allocation.'
        );
    }

    if ($reason === '') {
        return null;
    }

    $length =
        function_exists(
            'mb_strlen'
        )
            ? mb_strlen(
                $reason,
                'UTF-8'
            )
            : strlen(
                $reason
            );

    if ($length > 500) {
        throw new InvalidArgumentException(
            'Stock allocation revision reason cannot exceed 500 characters.'
        );
    }

    return $reason;
}
}

if (!function_exists('stock_consumption_allocation_persistence_notes')) {
function stock_consumption_allocation_persistence_notes(
    $value
): ?string {
    $value =
        trim(
            (string)(
                $value
                ?? ''
            )
        );

    if ($value === '') {
        return null;
    }

    $length =
        function_exists(
            'mb_strlen'
        )
            ? mb_strlen(
                $value,
                'UTF-8'
            )
            : strlen(
                $value
            );

    if ($length > 255) {
        throw new InvalidArgumentException(
            'Stock allocation notes cannot exceed 255 characters.'
        );
    }

    return $value;
}
}

if (!function_exists('stock_consumption_allocation_persistence_movement')) {
function stock_consumption_allocation_persistence_movement(
    PDO $pdo,
    int $farmId,
    int $stockTransactionId,
    bool $forUpdate = false
): array {
    if (
        $farmId < 1
        ||
        $stockTransactionId < 1
    ) {
        throw new InvalidArgumentException(
            'Stock allocation source identity is invalid.'
        );
    }

    if ($forUpdate) {
        stock_consumption_allocation_persistence_require_transaction(
            $pdo
        );
    }

    $sql =
        "SELECT *
         FROM stock_transactions
         WHERE farm_id=?
           AND id=?
         LIMIT 1";

    if ($forUpdate) {
        $sql .=
            " FOR UPDATE";
    }

    $stmt =
        $pdo->prepare(
            $sql
        );

    $stmt->execute([
        $farmId,
        $stockTransactionId,
    ]);

    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$row) {
        throw new RuntimeException(
            'Stock consumption transaction not found.'
        );
    }

    return $row;
}
}

if (!function_exists('stock_consumption_allocation_persistence_lock_parent')) {
function stock_consumption_allocation_persistence_lock_parent(
    PDO $pdo,
    int $farmId,
    int $stockTransactionId
): array {
    stock_consumption_allocation_persistence_require_transaction(
        $pdo
    );

    /*
     * Pre-read is intentionally unlocked.
     * Stock ledger source identity is append-only once posted.
     */
    $pre =
        stock_consumption_allocation_persistence_movement(
            $pdo,
            $farmId,
            $stockTransactionId,
            false
        );

    $definition =
        stock_consumption_source_resolver_assert_allocatable(
            $pre
        );

    if (
        $definition['mode']
        === 'linked_daily_record'
    ) {
        /*
         * Lock the authoritative operational source first.
         * Daily Record writers already own their Daily Record row before
         * synchronising stock, so this follows that same source -> stock order.
         */
        $resolution =
            stock_consumption_source_resolver_resolve(
                $pdo,
                $pre,
                true
            );

        $movement =
            stock_consumption_allocation_persistence_movement(
                $pdo,
                $farmId,
                $stockTransactionId,
                true
            );

    } else {
        $movement =
            stock_consumption_allocation_persistence_movement(
                $pdo,
                $farmId,
                $stockTransactionId,
                true
            );

        $resolution =
            stock_consumption_source_resolver_resolve(
                $pdo,
                $movement,
                false
            );
    }

    /*
     * Fail closed if immutable source identity somehow changed between
     * the pre-read and lock acquisition.
     */
    if (
        (string)(
            $pre['source_type']
            ?? ''
        )
            !==
        (string)(
            $movement['source_type']
            ?? ''
        )
        ||
        (int)(
            $pre['source_id']
            ?? 0
        )
            !==
        (int)(
            $movement['source_id']
            ?? 0
        )
    ) {
        throw new RuntimeException(
            'Stock movement source identity changed while acquiring allocation locks.'
        );
    }

    stock_consumption_source_resolver_assert_cycle_consistency(
        $movement,
        $resolution
    );

    $parent =
        stock_consumption_allocation_service_parent_contract(
            $movement,
            $resolution[
                'authoritative_source'
            ]
        );

    return [
        'movement' =>
            $movement,

        'resolution' =>
            $resolution,

        'parent' =>
            $parent,
    ];
}
}

if (!function_exists('stock_consumption_allocation_persistence_target_cycles')) {
function stock_consumption_allocation_persistence_target_cycles(
    PDO $pdo,
    int $farmId,
    array $cycleIds
): array {
    stock_consumption_allocation_persistence_require_transaction(
        $pdo
    );

    $ids = [];

    foreach ($cycleIds as $cycleId) {
        $cycleId =
            (int)$cycleId;

        if ($cycleId > 0) {
            $ids[$cycleId] =
                $cycleId;
        }
    }

    ksort(
        $ids,
        SORT_NUMERIC
    );

    $ids =
        array_values(
            $ids
        );

    if ($ids === []) {
        return [];
    }

    $placeholders =
        implode(
            ',',
            array_fill(
                0,
                count($ids),
                '?'
            )
        );

    $sql =
        "SELECT
             id,
             farm_id,
             farm_type,
             production_type,
             cycle_code,
             status,
             start_date
         FROM production_cycles
         WHERE farm_id=?
           AND id IN ({$placeholders})
         ORDER BY id
         FOR UPDATE";

    $stmt =
        $pdo->prepare(
            $sql
        );

    $stmt->execute(
        array_merge(
            [
                $farmId,
            ],
            $ids
        )
    );

    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];

    $map = [];

    foreach ($rows as $row) {
        $map[
            (int)$row['id']
        ] =
            $row;
    }

    if (
        count($map)
        !== count($ids)
    ) {
        throw new RuntimeException(
            'One or more stock allocation target cycles were not found.'
        );
    }

    return
        array_values(
            $map
        );
}
}

if (!function_exists('stock_consumption_allocation_persistence_current_rows')) {
function stock_consumption_allocation_persistence_current_rows(
    PDO $pdo,
    int $farmId,
    int $stockTransactionId
): array {
    stock_consumption_allocation_persistence_require_transaction(
        $pdo
    );

    $stmt =
        $pdo->prepare(
            "SELECT
                 id,
                 farm_id,
                 stock_transaction_id,
                 cycle_id,
                 allocated_amount,
                 allocation_percent,
                 notes,
                 allocation_revision_no,
                 created_by,
                 created_at,
                 updated_by,
                 updated_at
             FROM stock_consumption_allocations
             WHERE farm_id=?
               AND stock_transaction_id=?
             ORDER BY cycle_id,id
             FOR UPDATE"
        );

    $stmt->execute([
        $farmId,
        $stockTransactionId,
    ]);

    return
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];
}
}

if (!function_exists('stock_consumption_allocation_persistence_latest_revision')) {
function stock_consumption_allocation_persistence_latest_revision(
    PDO $pdo,
    int $farmId,
    int $stockTransactionId
): ?array {
    stock_consumption_allocation_persistence_require_transaction(
        $pdo
    );

    $stmt =
        $pdo->prepare(
            "SELECT *
             FROM stock_consumption_allocation_revisions
             WHERE farm_id=?
               AND stock_transaction_id=?
             ORDER BY revision_no DESC
             LIMIT 1
             FOR UPDATE"
        );

    $stmt->execute([
        $farmId,
        $stockTransactionId,
    ]);

    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    return
        $row
            ?: null;
}
}

if (!function_exists('stock_consumption_allocation_persistence_normalize_rows')) {
function stock_consumption_allocation_persistence_normalize_rows(
    array $rows
): array {
    foreach ($rows as $index => $row) {
        if (!is_array($row)) {
            throw new RuntimeException(
                'Stock allocation row is invalid.'
            );
        }

        $rows[$index]['notes'] =
            stock_consumption_allocation_persistence_notes(
                $row['notes']
                ?? null
            );
    }

    return $rows;
}
}

if (!function_exists('stock_consumption_allocation_persistence_cycle_ids')) {
function stock_consumption_allocation_persistence_cycle_ids(
    array $rows
): array {
    $ids = [];

    foreach ($rows as $row) {
        $cycleId =
            (int)(
                $row['cycle_id']
                ?? 0
            );

        if ($cycleId > 0) {
            $ids[$cycleId] =
                $cycleId;
        }
    }

    ksort(
        $ids,
        SORT_NUMERIC
    );

    return
        array_values(
            $ids
        );
}
}

if (!function_exists('stock_consumption_allocation_persistence_validate_rows')) {
function stock_consumption_allocation_persistence_validate_rows(
    array $lockedParent,
    array $cycles,
    array $rows
): array {
    $validated =
        stock_consumption_allocation_service_validate_desired_rows(
            $lockedParent[
                'movement'
            ],
            $cycles,
            $rows,
            $lockedParent[
                'resolution'
            ][
                'authoritative_source'
            ]
        );

    return
        stock_consumption_allocation_persistence_normalize_rows(
            $validated['rows']
            ?? []
        );
}
}

if (!function_exists('stock_consumption_allocation_persistence_assert_current_consistency')) {
function stock_consumption_allocation_persistence_assert_current_consistency(
    array $lockedParent,
    array $currentRows,
    ?array $latest,
    array $currentCycles
): ?array {
    if (
        $latest === null
        &&
        $currentRows === []
    ) {
        return null;
    }

    if ($latest === null) {
        throw new RuntimeException(
            'Stock allocation current projection exists without revision history.'
        );
    }

    $validated =
        stock_consumption_allocation_persistence_validate_rows(
            $lockedParent,
            $currentCycles,
            $currentRows
        );

    if (
        count($validated)
        !== count($currentRows)
    ) {
        throw new RuntimeException(
            'Stock allocation current projection is incomplete.'
        );
    }

    $validatedMap = [];

    foreach ($validated as $row) {
        $validatedMap[
            (int)$row['cycle_id']
        ] =
            $row;
    }

    foreach ($currentRows as $row) {
        $cycleId =
            (int)$row['cycle_id'];

        if (
            !isset(
                $validatedMap[$cycleId]
            )
        ) {
            throw new RuntimeException(
                'Stock allocation current projection target is invalid.'
            );
        }

        $expected =
            $validatedMap[
                $cycleId
            ];

        if (
            shared_cost_contract_money(
                shared_cost_contract_money_cents(
                    $row[
                        'allocated_amount'
                    ],
                    'Allocated amount'
                )
            )
                !==
            (string)$expected[
                'allocated_amount'
            ]
            ||
            number_format(
                (float)$row[
                    'allocation_percent'
                ],
                4,
                '.',
                ''
            )
                !==
            (string)$expected[
                'allocation_percent'
            ]
            ||
            stock_consumption_allocation_persistence_notes(
                $row['notes']
                ?? null
            )
                !==
            (
                $expected['notes']
                ?? null
            )
        ) {
            throw new RuntimeException(
                'Stock allocation current projection changed outside the canonical contract.'
            );
        }

        if (
            (int)$row[
                'allocation_revision_no'
            ]
            !==
            (int)$latest[
                'revision_no'
            ]
        ) {
            throw new RuntimeException(
                'Stock allocation projection revision is out of sync.'
            );
        }
    }

    $built =
        stock_consumption_allocation_provenance_build(
            $lockedParent[
                'movement'
            ],
            $currentRows
        );

    if (
        !hash_equals(
            (string)$latest[
                'causal_fingerprint'
            ],
            $built[
                'causal_fingerprint'
            ]
        )
        ||
        !hash_equals(
            (string)$latest[
                'state_fingerprint'
            ],
            $built[
                'state_fingerprint'
            ]
        )
        ||
        shared_cost_contract_money(
            shared_cost_contract_money_cents(
                $latest[
                    'parent_amount'
                ],
                'Parent amount'
            )
        )
            !==
        $built['parent_amount']
        ||
        shared_cost_contract_money(
            shared_cost_contract_money_cents(
                $latest[
                    'allocated_amount'
                ],
                'Allocated amount'
            )
        )
            !==
        $built['allocated_amount']
        ||
        shared_cost_contract_money(
            shared_cost_contract_money_cents(
                $latest[
                    'unallocated_amount'
                ],
                'Unallocated amount'
            )
        )
            !==
        $built['unallocated_amount']
    ) {
        throw new RuntimeException(
            'Stock allocation state changed outside the canonical revision contract.'
        );
    }

    return $built;
}
}

if (!function_exists('stock_consumption_allocation_persistence_write_projection')) {
function stock_consumption_allocation_persistence_write_projection(
    PDO $pdo,
    int $farmId,
    int $stockTransactionId,
    array $currentRows,
    array $desiredRows,
    int $revisionNo,
    ?int $actorUserId
): void {
    stock_consumption_allocation_persistence_require_transaction(
        $pdo
    );

    $currentMap = [];

    foreach ($currentRows as $row) {
        $currentMap[
            (int)$row['cycle_id']
        ] =
            $row;
    }

    $desiredMap = [];

    foreach ($desiredRows as $row) {
        $desiredMap[
            (int)$row['cycle_id']
        ] =
            $row;
    }

    foreach ($currentMap as $cycleId => $row) {
        if (
            isset(
                $desiredMap[$cycleId]
            )
        ) {
            continue;
        }

        $stmt =
            $pdo->prepare(
                "DELETE FROM stock_consumption_allocations
                 WHERE farm_id=?
                   AND stock_transaction_id=?
                   AND cycle_id=?"
            );

        $stmt->execute([
            $farmId,
            $stockTransactionId,
            $cycleId,
        ]);
    }

    foreach ($desiredMap as $cycleId => $row) {
        if (
            isset(
                $currentMap[$cycleId]
            )
        ) {
            $stmt =
                $pdo->prepare(
                    "UPDATE stock_consumption_allocations
                     SET
                         allocated_amount=?,
                         allocation_percent=?,
                         notes=?,
                         allocation_revision_no=?,
                         updated_by=?,
                         updated_at=NOW()
                     WHERE farm_id=?
                       AND stock_transaction_id=?
                       AND cycle_id=?"
                );

            $stmt->execute([
                $row[
                    'allocated_amount'
                ],
                $row[
                    'allocation_percent'
                ],
                $row['notes']
                    ?? null,
                $revisionNo,
                $actorUserId,
                $farmId,
                $stockTransactionId,
                $cycleId,
            ]);

            continue;
        }

        $stmt =
            $pdo->prepare(
                "INSERT INTO stock_consumption_allocations
                    (
                        farm_id,
                        stock_transaction_id,
                        cycle_id,
                        allocated_amount,
                        allocation_percent,
                        notes,
                        allocation_revision_no,
                        created_by
                    )
                 VALUES
                    (
                        ?,?,?,?,?,?,?,?
                    )"
            );

        $stmt->execute([
            $farmId,
            $stockTransactionId,
            $cycleId,
            $row[
                'allocated_amount'
            ],
            $row[
                'allocation_percent'
            ],
            $row['notes']
                ?? null,
            $revisionNo,
            $actorUserId,
        ]);
    }
}
}

if (!function_exists('stock_consumption_allocation_persistence_insert_revision')) {
function stock_consumption_allocation_persistence_insert_revision(
    PDO $pdo,
    int $farmId,
    int $stockTransactionId,
    int $revisionNo,
    string $action,
    ?string $reason,
    ?int $previousRevisionId,
    array $built,
    array $rows,
    ?int $actorUserId
): array {
    stock_consumption_allocation_persistence_require_transaction(
        $pdo
    );

    $stmt =
        $pdo->prepare(
            "INSERT INTO stock_consumption_allocation_revisions
                (
                    farm_id,
                    stock_transaction_id,
                    revision_no,
                    revision_action,
                    revision_reason,
                    previous_revision_id,
                    parent_amount,
                    allocated_amount,
                    unallocated_amount,
                    causal_fingerprint,
                    causal_manifest_json,
                    state_fingerprint,
                    state_manifest_json,
                    changed_by_user_id
                )
             VALUES
                (
                    ?,?,?,?,?,?,?,?,?,?,?,?,?,?
                )"
        );

    $stmt->execute([
        $farmId,
        $stockTransactionId,
        $revisionNo,
        $action,
        $reason,
        $previousRevisionId,
        $built[
            'parent_amount'
        ],
        $built[
            'allocated_amount'
        ],
        $built[
            'unallocated_amount'
        ],
        $built[
            'causal_fingerprint'
        ],
        $built[
            'causal_manifest_json'
        ],
        $built[
            'state_fingerprint'
        ],
        $built[
            'state_manifest_json'
        ],
        $actorUserId,
    ]);

    $revisionId =
        (int)$pdo->lastInsertId();

    foreach ($rows as $row) {
        $rowStmt =
            $pdo->prepare(
                "INSERT INTO stock_consumption_allocation_revision_rows
                    (
                        revision_id,
                        cycle_id,
                        allocated_amount,
                        allocation_percent,
                        notes
                    )
                 VALUES
                    (
                        ?,?,?,?,?
                    )"
            );

        $rowStmt->execute([
            $revisionId,
            (int)$row[
                'cycle_id'
            ],
            $row[
                'allocated_amount'
            ],
            $row[
                'allocation_percent'
            ],
            $row['notes']
                ?? null,
        ]);
    }

    return [
        'id' =>
            $revisionId,

        'revision_no' =>
            $revisionNo,

        'revision_action' =>
            $action,

        'causal_fingerprint' =>
            $built[
                'causal_fingerprint'
            ],

        'state_fingerprint' =>
            $built[
                'state_fingerprint'
            ],
    ];
}
}


if (!function_exists(
    'stock_consumption_allocation_persistence_retain_shared'
)) {
function stock_consumption_allocation_persistence_retain_shared(
    PDO $pdo,
    int $farmId,
    int $stockTransactionId,
    int $actorUserId,
    ?string $revisionReason = null
): array {
    stock_consumption_allocation_persistence_require_transaction(
        $pdo
    );

    if (
        $farmId < 1
        || $stockTransactionId < 1
    ) {
        throw new InvalidArgumentException(
            'Consumed-stock shared-cost identity is invalid.'
        );
    }

    if ($actorUserId < 1) {
        throw new RuntimeException(
            'Stock retained-shared decision actor is required.'
        );
    }

    /*
     * This is a reviewed business decision, not an allocation.
     *
     * stock_transactions remains untouched.
     * stock_consumption_allocations remains empty.
     * The append-only allocation revision ledger records the deliberate
     * retained-shared decision.
     */
    $locked =
        stock_consumption_allocation_persistence_lock_parent(
            $pdo,
            $farmId,
            $stockTransactionId
        );

    $current =
        stock_consumption_allocation_persistence_current_rows(
            $pdo,
            $farmId,
            $stockTransactionId
        );

    $latest =
        stock_consumption_allocation_persistence_latest_revision(
            $pdo,
            $farmId,
            $stockTransactionId
        );

    $currentCycleIds =
        stock_consumption_allocation_persistence_cycle_ids(
            $current
        );

    $currentCycles =
        stock_consumption_allocation_persistence_target_cycles(
            $pdo,
            $farmId,
            $currentCycleIds
        );

    $currentBuilt =
        stock_consumption_allocation_persistence_assert_current_consistency(
            $locked,
            $current,
            $latest,
            $currentCycles
        );

    if ($current !== []) {
        throw new RuntimeException(
            'Only fully unallocated consumed-stock costs can be retained entirely as shared.'
        );
    }

    /*
     * Empty desired allocation is validated by the same canonical service
     * used by normal allocation writes.
     */
    $desired =
        stock_consumption_allocation_persistence_validate_rows(
            $locked,
            [],
            []
        );

    if ($desired !== []) {
        throw new RuntimeException(
            'Retained-shared consumed stock unexpectedly produced allocation rows.'
        );
    }

    $desiredBuilt =
        stock_consumption_allocation_provenance_build(
            $locked[
                'movement'
            ],
            $desired
        );

    /*
     * When revision history already exists, empty current projection must
     * describe the exact same economic state before another decision event
     * can be appended.
     */
    if (
        $currentBuilt !== null
        &&
        !hash_equals(
            (string)$currentBuilt[
                'state_fingerprint'
            ],
            (string)$desiredBuilt[
                'state_fingerprint'
            ]
        )
    ) {
        throw new RuntimeException(
            'Consumed-stock shared-cost state changed before the retained-shared decision.'
        );
    }

    /*
     * Repeating the same reviewed decision is a semantic no-op.
     * Do not manufacture duplicate retain_shared revisions.
     */
    if (
        $latest !== null
        &&
        strtolower(
            trim(
                (string)(
                    $latest[
                        'revision_action'
                    ]
                    ?? ''
                )
            )
        ) === 'retain_shared'
    ) {
        return [
            'changed' =>
                false,

            'revision_action' =>
                'retain_shared',

            'stock_transaction_id' =>
                $stockTransactionId,

            'allocated_amount' =>
                $desiredBuilt[
                    'allocated_amount'
                ],

            'unallocated_amount' =>
                $desiredBuilt[
                    'unallocated_amount'
                ],

            'revision_no' =>
                (int)$latest[
                    'revision_no'
                ],
        ];
    }

    $reason =
        stock_consumption_allocation_persistence_reason(
            'retain_shared',
            $revisionReason
        );

    $revisionNo =
        $latest === null
            ? 1
            : (
                (int)$latest[
                    'revision_no'
                ]
                + 1
            );

    /*
     * No stock_consumption_allocations write occurs here.
     * Zero projection is the intended financial state.
     */
    $revision =
        stock_consumption_allocation_persistence_insert_revision(
            $pdo,
            $farmId,
            $stockTransactionId,
            $revisionNo,
            'retain_shared',
            $reason,
            $latest === null
                ? null
                : (int)$latest['id'],
            $desiredBuilt,
            [],
            $actorUserId
        );

    $latestWritten =
        stock_consumption_allocation_persistence_latest_revision(
            $pdo,
            $farmId,
            $stockTransactionId
        );

    if (
        $latestWritten === null
        ||
        strtolower(
            trim(
                (string)(
                    $latestWritten[
                        'revision_action'
                    ]
                    ?? ''
                )
            )
        ) !== 'retain_shared'
    ) {
        throw new RuntimeException(
            'Consumed-stock retained-shared decision was not persisted safely.'
        );
    }

    /*
     * Reuse canonical consistency authority against the newly appended
     * zero-row revision before allowing the transaction to commit.
     */
    $writtenBuilt =
        stock_consumption_allocation_persistence_assert_current_consistency(
            $locked,
            [],
            $latestWritten,
            []
        );

    if (
        $writtenBuilt === null
        ||
        !hash_equals(
            (string)$writtenBuilt[
                'state_fingerprint'
            ],
            (string)$desiredBuilt[
                'state_fingerprint'
            ]
        )
    ) {
        throw new RuntimeException(
            'Consumed-stock retained-shared revision does not match current state.'
        );
    }

    return array_merge(
        $revision,
        [
            'changed' =>
                true,

            'revision_action' =>
                'retain_shared',

            'stock_transaction_id' =>
                $stockTransactionId,

            'allocated_amount' =>
                $desiredBuilt[
                    'allocated_amount'
                ],

            'unallocated_amount' =>
                $desiredBuilt[
                    'unallocated_amount'
                ],
        ]
    );
}
}


if (!function_exists('stock_consumption_allocation_persistence_apply')) {
function stock_consumption_allocation_persistence_apply(
    PDO $pdo,
    int $farmId,
    int $stockTransactionId,
    array $desiredRows,
    int $actorUserId,
    ?string $revisionReason = null
): array {
    stock_consumption_allocation_persistence_require_transaction(
        $pdo
    );

    if ($actorUserId < 1) {
        throw new RuntimeException(
            'Stock allocation revision actor is required.'
        );
    }

    $locked =
        stock_consumption_allocation_persistence_lock_parent(
            $pdo,
            $farmId,
            $stockTransactionId
        );

    $desiredCycleIds =
        stock_consumption_allocation_persistence_cycle_ids(
            $desiredRows
        );

    $desiredCycles =
        stock_consumption_allocation_persistence_target_cycles(
            $pdo,
            $farmId,
            $desiredCycleIds
        );

    $current =
        stock_consumption_allocation_persistence_current_rows(
            $pdo,
            $farmId,
            $stockTransactionId
        );

    $latest =
        stock_consumption_allocation_persistence_latest_revision(
            $pdo,
            $farmId,
            $stockTransactionId
        );

    $currentCycleIds =
        stock_consumption_allocation_persistence_cycle_ids(
            $current
        );

    $currentCycles =
        stock_consumption_allocation_persistence_target_cycles(
            $pdo,
            $farmId,
            $currentCycleIds
        );

    $currentBuilt =
        stock_consumption_allocation_persistence_assert_current_consistency(
            $locked,
            $current,
            $latest,
            $currentCycles
        );

    $desired =
        stock_consumption_allocation_persistence_validate_rows(
            $locked,
            $desiredCycles,
            $desiredRows
        );

    $desiredBuilt =
        stock_consumption_allocation_provenance_build(
            $locked[
                'movement'
            ],
            $desired
        );

    if (
        $latest === null
        &&
        $current === []
        &&
        $desired === []
    ) {
        return [
            'changed' =>
                false,

            'revision_no' =>
                null,

            'allocated_amount' =>
                '0.00',

            'unallocated_amount' =>
                $desiredBuilt[
                    'unallocated_amount'
                ],
        ];
    }

    if (
        $currentBuilt !== null
        &&
        hash_equals(
            $currentBuilt[
                'state_fingerprint'
            ],
            $desiredBuilt[
                'state_fingerprint'
            ]
        )
    ) {
        return [
            'changed' =>
                false,

            'revision_no' =>
                (int)$latest[
                    'revision_no'
                ],

            'allocated_amount' =>
                $currentBuilt[
                    'allocated_amount'
                ],

            'unallocated_amount' =>
                $currentBuilt[
                    'unallocated_amount'
                ],
        ];
    }

    shared_cost_contract_assert_pre_cycle_reason(
        $locked['movement']['transaction_date']
            ?? null,
        $desiredCycles,
        $desired,
        $revisionReason
    );

    if ($latest === null) {
        $action =
            'create';

    } elseif ($desired === []) {
        $action =
            'clear';

    } else {
        $action =
            'update';
    }

    $reason =
        stock_consumption_allocation_persistence_reason(
            $action,
            $revisionReason
        );

    $revisionNo =
        $latest === null
            ? 1
            : (
                (int)$latest[
                    'revision_no'
                ]
                + 1
            );

    stock_consumption_allocation_persistence_write_projection(
        $pdo,
        $farmId,
        $stockTransactionId,
        $current,
        $desired,
        $revisionNo,
        $actorUserId
    );

    $revision =
        stock_consumption_allocation_persistence_insert_revision(
            $pdo,
            $farmId,
            $stockTransactionId,
            $revisionNo,
            $action,
            $reason,
            $latest === null
                ? null
                : (int)$latest['id'],
            $desiredBuilt,
            $desired,
            $actorUserId
        );

    return array_merge(
        $revision,
        [
            'changed' =>
                true,

            'allocated_amount' =>
                $desiredBuilt[
                    'allocated_amount'
                ],

            'unallocated_amount' =>
                $desiredBuilt[
                    'unallocated_amount'
                ],
        ]
    );
}
}

if (!function_exists('stock_consumption_allocation_persistence_before_source_reversal')) {
function stock_consumption_allocation_persistence_before_source_reversal(
    PDO $pdo,
    int $farmId,
    int $stockTransactionId,
    string $reason,
    ?int $actorUserId = null
): array {
    stock_consumption_allocation_persistence_require_transaction(
        $pdo
    );

    /*
     * Source reversal is lifecycle closure, not allocation creation.
     *
     * Lock only the immutable stock parent and its allocation state.
     * Do not re-run source eligibility or Daily Record cycle resolution:
     * a direct-cycle or attribution-drifted movement must remain correctable.
     */
    $movement =
        stock_consumption_allocation_persistence_movement(
            $pdo,
            $farmId,
            $stockTransactionId,
            true
        );

    $current =
        stock_consumption_allocation_persistence_current_rows(
            $pdo,
            $farmId,
            $stockTransactionId
        );

    $latest =
        stock_consumption_allocation_persistence_latest_revision(
            $pdo,
            $farmId,
            $stockTransactionId
        );

    /*
     * Normal stock movements have no allocation history.
     * Reversal remains behaviorally unchanged for them.
     */
    if (
        $latest === null
        &&
        $current === []
    ) {
        return [
            'changed' =>
                false,

            'revision_no' =>
                null,
        ];
    }

    if ($latest === null) {
        throw new RuntimeException(
            'Stock allocation current projection exists without revision history.'
        );
    }

    if (
        $actorUserId === null
        ||
        $actorUserId < 1
    ) {
        throw new RuntimeException(
            'Stock allocation revision actor is required.'
        );
    }

    if (
        (string)(
            $latest['revision_action']
            ?? ''
        ) === 'source_reversal'
        &&
        $current === []
    ) {
        return [
            'changed' =>
                false,

            'revision_no' =>
                (int)$latest[
                    'revision_no'
                ],
        ];
    }

    /*
     * Validate current allocation state from immutable economic provenance,
     * not from present-day allocation eligibility.
     */
    $currentBuilt =
        stock_consumption_allocation_provenance_build(
            $movement,
            $current
        );

    foreach ($current as $row) {
        if (
            (int)(
                $row[
                    'allocation_revision_no'
                ]
                ?? 0
            )
            !==
            (int)$latest[
                'revision_no'
            ]
        ) {
            throw new RuntimeException(
                'Stock allocation projection revision is out of sync.'
            );
        }
    }

    if (
        !hash_equals(
            (string)$latest[
                'causal_fingerprint'
            ],
            $currentBuilt[
                'causal_fingerprint'
            ]
        )
        ||
        !hash_equals(
            (string)$latest[
                'state_fingerprint'
            ],
            $currentBuilt[
                'state_fingerprint'
            ]
        )
        ||
        shared_cost_contract_money(
            shared_cost_contract_money_cents(
                $latest[
                    'parent_amount'
                ],
                'Parent amount'
            )
        )
            !==
        $currentBuilt[
            'parent_amount'
        ]
        ||
        shared_cost_contract_money(
            shared_cost_contract_money_cents(
                $latest[
                    'allocated_amount'
                ],
                'Allocated amount'
            )
        )
            !==
        $currentBuilt[
            'allocated_amount'
        ]
        ||
        shared_cost_contract_money(
            shared_cost_contract_money_cents(
                $latest[
                    'unallocated_amount'
                ],
                'Unallocated amount'
            )
        )
            !==
        $currentBuilt[
            'unallocated_amount'
        ]
    ) {
        throw new RuntimeException(
            'Stock allocation state changed outside the canonical revision contract.'
        );
    }

    $revisionReason =
        stock_consumption_allocation_persistence_reason(
            'source_reversal',
            $reason
        );

    $revisionNo =
        (int)$latest[
            'revision_no'
        ] + 1;

    $clearedBuilt =
        stock_consumption_allocation_provenance_build(
            $movement,
            []
        );

    stock_consumption_allocation_persistence_write_projection(
        $pdo,
        $farmId,
        $stockTransactionId,
        $current,
        [],
        $revisionNo,
        $actorUserId
    );

    $revision =
        stock_consumption_allocation_persistence_insert_revision(
            $pdo,
            $farmId,
            $stockTransactionId,
            $revisionNo,
            'source_reversal',
            $revisionReason,
            (int)$latest['id'],
            $clearedBuilt,
            [],
            $actorUserId
        );

    return array_merge(
        $revision,
        [
            'changed' =>
                true,

            'allocated_amount' =>
                '0.00',

            'unallocated_amount' =>
                $clearedBuilt[
                    'unallocated_amount'
                ],
        ]
    );
}


}
