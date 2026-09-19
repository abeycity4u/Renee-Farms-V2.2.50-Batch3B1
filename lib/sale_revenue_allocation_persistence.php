<?php

require_once __DIR__
    . '/sale_revenue_allocation_service.php';

require_once __DIR__
    . '/sale_revenue_allocation_provenance.php';

/*
 * V3.0.1 canonical manual shared-revenue allocation persistence.
 *
 * Contract:
 * - caller owns the PDO transaction;
 * - this service never commits or rolls back;
 * - sales_records is never mutated here;
 * - sales_allocations is the current/live projection;
 * - sales_allocation_revisions is append-only provenance;
 * - sales_allocation_revision_rows snapshots each revision;
 * - only allocation_basis=manual_shared_revenue is owned here;
 * - automatic Layer egg allocation remains separately owned;
 * - exact semantic no-op does not manufacture a revision.
 */

if (!function_exists(
    'sale_revenue_allocation_persistence_require_transaction'
)) {
function sale_revenue_allocation_persistence_require_transaction(
    PDO $pdo
): void {
    if (!$pdo->inTransaction()) {
        throw new RuntimeException(
            'Shared revenue allocation persistence requires an active transaction.'
        );
    }
}
}


if (!function_exists(
    'sale_revenue_allocation_persistence_actor'
)) {
function sale_revenue_allocation_persistence_actor(
    int $actorUserId
): int {
    if ($actorUserId < 1) {
        throw new InvalidArgumentException(
            'Shared revenue allocation actor is required.'
        );
    }

    return $actorUserId;
}
}


if (!function_exists(
    'sale_revenue_allocation_persistence_reason'
)) {
function sale_revenue_allocation_persistence_reason(
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

    $length =
        function_exists('mb_strlen')
            ? mb_strlen(
                $reason,
                'UTF-8'
            )
            : strlen(
                $reason
            );

    if ($length > 500) {
        throw new InvalidArgumentException(
            'Shared revenue allocation revision reason cannot exceed 500 characters.'
        );
    }

    if ($action === 'create') {
        return
            $reason === ''
                ? null
                : $reason;
    }

    if ($reason === '') {
        throw new InvalidArgumentException(
            'Enter a reason for changing this shared revenue allocation.'
        );
    }

    return $reason;
}
}


if (!function_exists(
    'sale_revenue_allocation_persistence_parent'
)) {
function sale_revenue_allocation_persistence_parent(
    PDO $pdo,
    int $farmId,
    int $saleId,
    bool $forUpdate = false
): array {
    if ($forUpdate) {
        sale_revenue_allocation_persistence_require_transaction(
            $pdo
        );
    }

    $sql =
        "SELECT *
         FROM sales_records
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
        $saleId,
    ]);

    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$row) {
        throw new RuntimeException(
            'Sale record not found.'
        );
    }

    return $row;
}
}


if (!function_exists(
    'sale_revenue_allocation_persistence_current_rows'
)) {
function sale_revenue_allocation_persistence_current_rows(
    PDO $pdo,
    int $farmId,
    int $saleId,
    bool $forUpdate = false
): array {
    if ($forUpdate) {
        sale_revenue_allocation_persistence_require_transaction(
            $pdo
        );
    }

    $sql =
        "SELECT
             id,
             farm_id,
             sale_id,
             cycle_id,
             allocation_percent,
             allocated_quantity,
             allocation_unit,
             allocated_amount,
             allocation_basis,
             notes,
             created_by,
             created_at
         FROM sales_allocations
         WHERE farm_id=?
           AND sale_id=?
         ORDER BY cycle_id,id";

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
        $saleId,
    ]);

    return
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];
}
}


if (!function_exists(
    'sale_revenue_allocation_persistence_assert_manual_projection'
)) {
function sale_revenue_allocation_persistence_assert_manual_projection(
    array $rows
): void {
    foreach ($rows as $row) {
        if (
            strtolower(
                trim(
                    (string)(
                        $row['allocation_basis']
                        ?? ''
                    )
                )
            ) !== 'manual_shared_revenue'
        ) {
            throw new RuntimeException(
                'This sale allocation is owned by another allocation contract.'
            );
        }

        if (
            (
                array_key_exists(
                    'allocated_quantity',
                    $row
                )
                &&
                $row['allocated_quantity'] !== null
            )
            ||
            (
                array_key_exists(
                    'allocation_unit',
                    $row
                )
                &&
                trim(
                    (string)$row['allocation_unit']
                ) !== ''
            )
        ) {
            throw new RuntimeException(
                'Manual shared revenue allocation must not invent physical quantity ownership.'
            );
        }
    }
}
}


if (!function_exists(
    'sale_revenue_allocation_persistence_animal_count'
)) {
function sale_revenue_allocation_persistence_animal_count(
    PDO $pdo,
    int $farmId,
    int $saleId,
    bool $forUpdate = false
): int {
    if ($forUpdate) {
        sale_revenue_allocation_persistence_require_transaction(
            $pdo
        );
    }

    $sql =
        "SELECT id
         FROM ruminant_sale_animal_allocations
         WHERE farm_id=?
           AND sale_id=?
         ORDER BY id";

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
        $saleId,
    ]);

    return count(
        $stmt->fetchAll(
            PDO::FETCH_COLUMN
        ) ?: []
    );
}
}


if (!function_exists(
    'sale_revenue_allocation_persistence_cycle_ids'
)) {
function sale_revenue_allocation_persistence_cycle_ids(
    array $rows
): array {
    $ids = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

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


if (!function_exists(
    'sale_revenue_allocation_persistence_target_cycles'
)) {
function sale_revenue_allocation_persistence_target_cycles(
    PDO $pdo,
    int $farmId,
    array $cycleIds,
    bool $forUpdate = false
): array {
    if ($forUpdate) {
        sale_revenue_allocation_persistence_require_transaction(
            $pdo
        );
    }

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

    if (!$ids) {
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
             start_date,
             end_date
         FROM production_cycles
         WHERE farm_id=?
           AND id IN ({$placeholders})
         ORDER BY id";

    if ($forUpdate) {
        $sql .=
            " FOR UPDATE";
    }

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

    if (count($rows) !== count($ids)) {
        throw new RuntimeException(
            'One or more revenue allocation target cycles were not found.'
        );
    }

    return $rows;
}
}


if (!function_exists(
    'sale_revenue_allocation_persistence_semantic_rows'
)) {
function sale_revenue_allocation_persistence_semantic_rows(
    array $rows
): array {
    $normalized = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            throw new RuntimeException(
                'Shared revenue allocation row is invalid.'
            );
        }

        $cycleId =
            (int)(
                $row['cycle_id']
                ?? 0
            );

        if ($cycleId < 1) {
            throw new RuntimeException(
                'Shared revenue allocation target cycle is invalid.'
            );
        }

        $normalized[] = [
            'cycle_id' =>
                $cycleId,

            'allocated_amount' =>
                sale_revenue_allocation_service_money_string(
                    sale_revenue_allocation_service_money_cents(
                        $row['allocated_amount']
                        ?? null,
                        'Allocated revenue'
                    )
                ),

            'allocation_percent' =>
                number_format(
                    (float)(
                        $row['allocation_percent']
                        ?? 0
                    ),
                    4,
                    '.',
                    ''
                ),

            'allocation_basis' =>
                strtolower(
                    trim(
                        (string)(
                            $row['allocation_basis']
                            ?? ''
                        )
                    )
                ),

            'allocated_quantity' =>
                $row['allocated_quantity']
                ?? null,

            'allocation_unit' =>
                (
                    trim(
                        (string)(
                            $row['allocation_unit']
                            ?? ''
                        )
                    ) === ''
                )
                    ? null
                    : strtolower(
                        trim(
                            (string)$row['allocation_unit']
                        )
                    ),

            'notes' =>
                sale_revenue_allocation_service_notes(
                    $row['notes']
                    ?? null
                ),
        ];
    }

    usort(
        $normalized,
        static function (
            array $left,
            array $right
        ): int {
            return
                (int)$left['cycle_id']
                <=>
                (int)$right['cycle_id'];
        }
    );

    return $normalized;
}
}


if (!function_exists(
    'sale_revenue_allocation_persistence_latest_revision'
)) {
function sale_revenue_allocation_persistence_latest_revision(
    PDO $pdo,
    int $farmId,
    int $saleId,
    bool $forUpdate = false
): ?array {
    if ($forUpdate) {
        sale_revenue_allocation_persistence_require_transaction(
            $pdo
        );
    }

    $sql =
        "SELECT *
         FROM sales_allocation_revisions
         WHERE farm_id=?
           AND sale_id=?
         ORDER BY revision_no DESC,id DESC
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
        $saleId,
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


if (!function_exists(
    'sale_revenue_allocation_persistence_revision_rows'
)) {
function sale_revenue_allocation_persistence_revision_rows(
    PDO $pdo,
    int $revisionId,
    bool $forUpdate = false
): array {
    if ($forUpdate) {
        sale_revenue_allocation_persistence_require_transaction(
            $pdo
        );
    }

    $sql =
        "SELECT
             id,
             revision_id,
             cycle_id,
             allocated_amount,
             allocation_percent,
             allocation_basis,
             notes
         FROM sales_allocation_revision_rows
         WHERE revision_id=?
         ORDER BY cycle_id,id";

    if ($forUpdate) {
        $sql .=
            " FOR UPDATE";
    }

    $stmt =
        $pdo->prepare(
            $sql
        );

    $stmt->execute([
        $revisionId,
    ]);

    return
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];
}
}


if (!function_exists(
    'sale_revenue_allocation_persistence_assert_projection'
)) {
function sale_revenue_allocation_persistence_assert_projection(
    array $currentRows,
    array $validatedRows
): void {
    $current =
        sale_revenue_allocation_persistence_semantic_rows(
            $currentRows
        );

    $validated =
        sale_revenue_allocation_persistence_semantic_rows(
            $validatedRows
        );

    if ($current !== $validated) {
        throw new RuntimeException(
            'Shared revenue allocation current projection changed outside the canonical contract.'
        );
    }
}
}


if (!function_exists(
    'sale_revenue_allocation_persistence_revision_semantic_rows'
)) {
function sale_revenue_allocation_persistence_revision_semantic_rows(
    array $rows
): array {
    $normalized = [];

    foreach ($rows as $row) {
        $normalized[] = [
            'cycle_id' =>
                (int)$row['cycle_id'],

            'allocated_amount' =>
                sale_revenue_allocation_service_money_string(
                    sale_revenue_allocation_service_money_cents(
                        $row['allocated_amount'],
                        'Allocated revenue'
                    )
                ),

            'allocation_percent' =>
                number_format(
                    (float)$row['allocation_percent'],
                    4,
                    '.',
                    ''
                ),

            'allocation_basis' =>
                strtolower(
                    trim(
                        (string)$row['allocation_basis']
                    )
                ),

            'notes' =>
                sale_revenue_allocation_service_notes(
                    $row['notes']
                    ?? null
                ),
        ];
    }

    usort(
        $normalized,
        static function (
            array $left,
            array $right
        ): int {
            return
                (int)$left['cycle_id']
                <=>
                (int)$right['cycle_id'];
        }
    );

    return $normalized;
}
}


if (!function_exists(
    'sale_revenue_allocation_persistence_current_revision_semantic_rows'
)) {
function sale_revenue_allocation_persistence_current_revision_semantic_rows(
    array $rows
): array {
    $normalized = [];

    foreach (
        sale_revenue_allocation_persistence_semantic_rows(
            $rows
        )
        as $row
    ) {
        $normalized[] = [
            'cycle_id' =>
                $row['cycle_id'],

            'allocated_amount' =>
                $row['allocated_amount'],

            'allocation_percent' =>
                $row['allocation_percent'],

            'allocation_basis' =>
                $row['allocation_basis'],

            'notes' =>
                $row['notes'],
        ];
    }

    return $normalized;
}
}


if (!function_exists(
    'sale_revenue_allocation_persistence_assert_revision_consistency'
)) {
function sale_revenue_allocation_persistence_assert_revision_consistency(
    PDO $pdo,
    array $sale,
    array $currentRows,
    ?array $latest,
    bool $forUpdate = false
): void {
    if ($latest === null) {
        if ($currentRows !== []) {
            throw new RuntimeException(
                'Manual shared revenue allocation exists without revision provenance.'
            );
        }

        return;
    }

    $provenance =
        sale_revenue_allocation_provenance_build(
            $sale,
            $currentRows
        );

    if (
        !hash_equals(
            (string)$latest['causal_fingerprint'],
            (string)$provenance['causal_fingerprint']
        )
        ||
        !hash_equals(
            (string)$latest['state_fingerprint'],
            (string)$provenance['state_fingerprint']
        )
        ||
        number_format(
            (float)$latest['parent_amount'],
            2,
            '.',
            ''
        ) !== $provenance['parent_amount']
        ||
        number_format(
            (float)$latest['allocated_amount'],
            2,
            '.',
            ''
        ) !== $provenance['allocated_amount']
        ||
        number_format(
            (float)$latest['unallocated_amount'],
            2,
            '.',
            ''
        ) !== $provenance['unallocated_amount']
    ) {
        throw new RuntimeException(
            'Shared revenue allocation revision history does not match the current projection.'
        );
    }

    $revisionRows =
        sale_revenue_allocation_persistence_revision_rows(
            $pdo,
            (int)$latest['id'],
            $forUpdate
        );

    if (
        sale_revenue_allocation_persistence_revision_semantic_rows(
            $revisionRows
        )
        !==
        sale_revenue_allocation_persistence_current_revision_semantic_rows(
            $currentRows
        )
    ) {
        throw new RuntimeException(
            'Shared revenue allocation revision detail does not match the current projection.'
        );
    }
}
}


if (!function_exists(
    'sale_revenue_allocation_persistence_write_projection'
)) {
function sale_revenue_allocation_persistence_write_projection(
    PDO $pdo,
    int $farmId,
    int $saleId,
    array $currentRows,
    array $desiredRows,
    int $actorUserId
): void {
    sale_revenue_allocation_persistence_require_transaction(
        $pdo
    );

    $actorUserId =
        sale_revenue_allocation_persistence_actor(
            $actorUserId
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

    foreach ($currentMap as $cycleId => $current) {
        if (isset($desiredMap[$cycleId])) {
            continue;
        }

        $stmt =
            $pdo->prepare(
                "DELETE FROM sales_allocations
                 WHERE farm_id=?
                   AND sale_id=?
                   AND cycle_id=?"
            );

        $stmt->execute([
            $farmId,
            $saleId,
            $cycleId,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                'Shared revenue allocation projection delete was not deterministic.'
            );
        }
    }

    foreach ($desiredMap as $cycleId => $desired) {
        if (isset($currentMap[$cycleId])) {
            $currentSemantic =
                sale_revenue_allocation_persistence_semantic_rows([
                    $currentMap[$cycleId],
                ]);

            $desiredSemantic =
                sale_revenue_allocation_persistence_semantic_rows([
                    $desired,
                ]);

            if ($currentSemantic === $desiredSemantic) {
                continue;
            }

            $stmt =
                $pdo->prepare(
                    "UPDATE sales_allocations
                     SET
                         allocation_percent=?,
                         allocated_quantity=NULL,
                         allocation_unit=NULL,
                         allocated_amount=?,
                         allocation_basis='manual_shared_revenue',
                         notes=?
                     WHERE farm_id=?
                       AND sale_id=?
                       AND cycle_id=?"
                );

            $stmt->execute([
                $desired['allocation_percent'],
                $desired['allocated_amount'],
                $desired['notes']
                    ?? null,
                $farmId,
                $saleId,
                $cycleId,
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException(
                    'Shared revenue allocation projection update was not deterministic.'
                );
            }

            continue;
        }

        $stmt =
            $pdo->prepare(
                "INSERT INTO sales_allocations
                    (
                        farm_id,
                        sale_id,
                        cycle_id,
                        allocation_percent,
                        allocated_quantity,
                        allocation_unit,
                        allocated_amount,
                        allocation_basis,
                        notes,
                        created_by,
                        created_at
                    )
                 VALUES
                    (
                        ?,?,?,?,
                        NULL,
                        NULL,
                        ?,
                        'manual_shared_revenue',
                        ?,?,
                        NOW()
                    )"
            );

        $stmt->execute([
            $farmId,
            $saleId,
            $cycleId,
            $desired['allocation_percent'],
            $desired['allocated_amount'],
            $desired['notes']
                ?? null,
            $actorUserId,
        ]);
    }
}
}


if (!function_exists(
    'sale_revenue_allocation_persistence_append_revision'
)) {
function sale_revenue_allocation_persistence_append_revision(
    PDO $pdo,
    int $farmId,
    int $saleId,
    array $sale,
    array $rows,
    ?array $latest,
    string $action,
    ?string $reason,
    int $actorUserId
): array {
    sale_revenue_allocation_persistence_require_transaction(
        $pdo
    );

    $actorUserId =
        sale_revenue_allocation_persistence_actor(
            $actorUserId
        );

    $provenance =
        sale_revenue_allocation_provenance_build(
            $sale,
            $rows
        );

    $revisionNo =
        $latest
            ? (int)$latest['revision_no'] + 1
            : 1;

    $previousId =
        $latest
            ? (int)$latest['id']
            : null;

    $stmt =
        $pdo->prepare(
            "INSERT INTO sales_allocation_revisions
                (
                    farm_id,
                    sale_id,
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
                    changed_by_user_id,
                    created_at
                )
             VALUES
                (
                    ?,?,?,?,?,?,
                    ?,?,?,?,?,?,?,?,
                    NOW()
                )"
        );

    $stmt->execute([
        $farmId,
        $saleId,
        $revisionNo,
        $action,
        $reason,
        $previousId,
        $provenance['parent_amount'],
        $provenance['allocated_amount'],
        $provenance['unallocated_amount'],
        $provenance['causal_fingerprint'],
        $provenance['causal_manifest_json'],
        $provenance['state_fingerprint'],
        $provenance['state_manifest_json'],
        $actorUserId,
    ]);

    $revisionId =
        (int)$pdo->lastInsertId();

    if ($revisionId < 1) {
        throw new RuntimeException(
            'Shared revenue allocation revision could not be created.'
        );
    }

    if ($rows) {
        $insert =
            $pdo->prepare(
                "INSERT INTO sales_allocation_revision_rows
                    (
                        revision_id,
                        cycle_id,
                        allocated_amount,
                        allocation_percent,
                        allocation_basis,
                        notes
                    )
                 VALUES
                    (?,?,?,?,?,?)"
            );

        foreach ($rows as $row) {
            $insert->execute([
                $revisionId,
                (int)$row['cycle_id'],
                $row['allocated_amount'],
                $row['allocation_percent'],
                'manual_shared_revenue',
                $row['notes']
                    ?? null,
            ]);
        }
    }

    return [
        'id' =>
            $revisionId,

        'revision_no' =>
            $revisionNo,

        'action' =>
            $action,

        'provenance' =>
            $provenance,
    ];
}
}


if (!function_exists(
    'sale_revenue_allocation_persistence_apply'
)) {
function sale_revenue_allocation_persistence_apply(
    PDO $pdo,
    int $farmId,
    int $saleId,
    array $desiredRows,
    int $actorUserId,
    ?string $revisionReason = null
): array {
    sale_revenue_allocation_persistence_require_transaction(
        $pdo
    );

    if (
        $farmId < 1
        ||
        $saleId < 1
    ) {
        throw new InvalidArgumentException(
            'Shared revenue allocation parent identity is invalid.'
        );
    }

    $actorUserId =
        sale_revenue_allocation_persistence_actor(
            $actorUserId
        );

    /*
     * Lock order:
     *   1. sale parent
     *   2. current sale-allocation projection
     *   3. individual-animal revenue allocation state
     *   4. all old/new target cycles in deterministic order
     *   5. latest revision and its detail rows
     */

    $sale =
        sale_revenue_allocation_persistence_parent(
            $pdo,
            $farmId,
            $saleId,
            true
        );

    sale_revenue_allocation_service_parent_contract(
        $sale
    );

    $currentRows =
        sale_revenue_allocation_persistence_current_rows(
            $pdo,
            $farmId,
            $saleId,
            true
        );

    sale_revenue_allocation_persistence_assert_manual_projection(
        $currentRows
    );

    $animalCount =
        sale_revenue_allocation_persistence_animal_count(
            $pdo,
            $farmId,
            $saleId,
            true
        );

    $cycleIds =
        sale_revenue_allocation_persistence_cycle_ids(
            array_merge(
                $currentRows,
                $desiredRows
            )
        );

    $cycles =
        sale_revenue_allocation_persistence_target_cycles(
            $pdo,
            $farmId,
            $cycleIds,
            true
        );

    $currentValidated =
        sale_revenue_allocation_service_validate_desired_rows(
            $sale,
            $cycles,
            $currentRows,
            $animalCount
        );

    sale_revenue_allocation_persistence_assert_projection(
        $currentRows,
        $currentValidated['rows']
            ?? []
    );

    $desiredValidated =
        sale_revenue_allocation_service_validate_desired_rows(
            $sale,
            $cycles,
            $desiredRows,
            $animalCount
        );

    $currentSemantic =
        sale_revenue_allocation_persistence_semantic_rows(
            $currentValidated['rows']
            ?? []
        );

    $desiredSemantic =
        sale_revenue_allocation_persistence_semantic_rows(
            $desiredValidated['rows']
            ?? []
        );

    $latest =
        sale_revenue_allocation_persistence_latest_revision(
            $pdo,
            $farmId,
            $saleId,
            true
        );

    sale_revenue_allocation_persistence_assert_revision_consistency(
        $pdo,
        $sale,
        $currentRows,
        $latest,
        true
    );

    if ($currentSemantic === $desiredSemantic) {
        return [
            'changed' =>
                false,

            'action' =>
                'noop',

            'sale_id' =>
                $saleId,

            'rows' =>
                $desiredSemantic,

            'allocated_amount' =>
                $desiredValidated['allocated_amount'],

            'remaining_amount' =>
                $desiredValidated['remaining_amount'],

            'revision_no' =>
                $latest
                    ? (int)$latest['revision_no']
                    : null,
        ];
    }

    $action =
        $latest === null
        &&
        $currentSemantic === []
        &&
        $desiredSemantic !== []
            ? 'create'
            : (
                $desiredSemantic === []
                    ? 'clear'
                    : 'update'
            );

    $reason =
        sale_revenue_allocation_persistence_reason(
            $action,
            $revisionReason
        );

    sale_revenue_allocation_persistence_write_projection(
        $pdo,
        $farmId,
        $saleId,
        $currentRows,
        $desiredValidated['rows']
            ?? [],
        $actorUserId
    );

    $writtenRows =
        sale_revenue_allocation_persistence_current_rows(
            $pdo,
            $farmId,
            $saleId,
            true
        );

    sale_revenue_allocation_persistence_assert_manual_projection(
        $writtenRows
    );

    $writtenValidated =
        sale_revenue_allocation_service_validate_desired_rows(
            $sale,
            $cycles,
            $writtenRows,
            $animalCount
        );

    sale_revenue_allocation_persistence_assert_projection(
        $writtenRows,
        $writtenValidated['rows']
            ?? []
    );

    $writtenSemantic =
        sale_revenue_allocation_persistence_semantic_rows(
            $writtenValidated['rows']
            ?? []
        );

    if ($writtenSemantic !== $desiredSemantic) {
        throw new RuntimeException(
            'Shared revenue allocation projection did not reach the requested state.'
        );
    }

    $revision =
        sale_revenue_allocation_persistence_append_revision(
            $pdo,
            $farmId,
            $saleId,
            $sale,
            $writtenValidated['rows']
                ?? [],
            $latest,
            $action,
            $reason,
            $actorUserId
        );

    sale_revenue_allocation_persistence_assert_revision_consistency(
        $pdo,
        $sale,
        $writtenRows,
        sale_revenue_allocation_persistence_latest_revision(
            $pdo,
            $farmId,
            $saleId,
            true
        ),
        true
    );

    return [
        'changed' =>
            true,

        'action' =>
            $action,

        'sale_id' =>
            $saleId,

        'rows' =>
            $writtenSemantic,

        'allocated_amount' =>
            $writtenValidated['allocated_amount'],

        'remaining_amount' =>
            $writtenValidated['remaining_amount'],

        'revision_no' =>
            (int)$revision['revision_no'],
    ];
}
}
