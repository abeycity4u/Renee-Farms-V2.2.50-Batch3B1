<?php

require_once __DIR__
    . '/financial_allocation_service.php';

require_once __DIR__
    . '/expense_revision_service.php';

/*
 * V3.0.1 canonical farm-expense financial-allocation persistence.
 *
 * Contract:
 * - caller owns the PDO transaction;
 * - this service never commits or rolls back;
 * - farm_expenses remains the parent/current expense projection;
 * - financial_allocations is the current allocation projection;
 * - farm_expense_revisions is the immutable provenance ledger;
 * - allocated_amount is economic authority;
 * - allocation_percent is derived by financial_allocation_service;
 * - no automatic/equal spreading;
 * - no parent + child double counting;
 * - current row identity/created metadata is preserved where possible;
 * - exact semantic no-op does not manufacture an expense revision.
 *
 * Lock order for one expense:
 *   1. farm_expenses parent
 *   2. current financial_allocations
 *   3. ruminant animal allocations
 *   4. target production_cycles in deterministic ID order
 *   5. expense revision state/history when a real mutation is required
 */

if (!function_exists(
    'financial_allocation_persistence_require_transaction'
)) {
function financial_allocation_persistence_require_transaction(
    PDO $pdo
): void {
    if (!$pdo->inTransaction()) {
        throw new RuntimeException(
            'Financial allocation persistence requires an active transaction.'
        );
    }
}
}

if (!function_exists(
    'financial_allocation_persistence_actor'
)) {
function financial_allocation_persistence_actor(
    int $actorUserId
): int {
    if ($actorUserId < 1) {
        throw new InvalidArgumentException(
            'Financial allocation actor is required.'
        );
    }

    return $actorUserId;
}
}

if (!function_exists(
    'financial_allocation_persistence_cycle_ids'
)) {
function financial_allocation_persistence_cycle_ids(
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
                ?? $row['target_cycle_id']
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
    'financial_allocation_persistence_semantic_rows'
)) {
function financial_allocation_persistence_semantic_rows(
    array $rows
): array {
    $normalized = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            throw new RuntimeException(
                'Financial allocation row is invalid.'
            );
        }

        $cycleId =
            (int)(
                $row['cycle_id']
                ?? $row['target_cycle_id']
                ?? 0
            );

        if ($cycleId < 1) {
            throw new RuntimeException(
                'Financial allocation target cycle is invalid.'
            );
        }

        $amountCents =
            financial_allocation_service_money_cents(
                $row['allocated_amount']
                ?? null
            );

        $normalized[] = [
            'cycle_id' =>
                $cycleId,

            'allocated_amount' =>
                financial_allocation_service_money_string(
                    $amountCents
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

            'notes' =>
                financial_allocation_service_notes(
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
    'financial_allocation_persistence_assert_projection'
)) {
function financial_allocation_persistence_assert_projection(
    array $currentRows,
    array $validatedRows
): void {
    $currentSemantic =
        financial_allocation_persistence_semantic_rows(
            $currentRows
        );

    $validatedSemantic =
        financial_allocation_persistence_semantic_rows(
            $validatedRows
        );

    if ($currentSemantic !== $validatedSemantic) {
        throw new RuntimeException(
            'Financial allocation current projection changed outside the canonical contract.'
        );
    }
}
}

if (!function_exists(
    'financial_allocation_persistence_write_projection'
)) {
function financial_allocation_persistence_write_projection(
    PDO $pdo,
    int $farmId,
    int $expenseId,
    array $currentRows,
    array $desiredRows,
    int $actorUserId
): void {
    financial_allocation_persistence_require_transaction(
        $pdo
    );

    $actorUserId =
        financial_allocation_persistence_actor(
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

    /*
     * Remove only targets explicitly omitted from the desired state.
     * Never blanket-delete the expense allocation projection.
     */
    foreach ($currentMap as $cycleId => $row) {
        if (isset($desiredMap[$cycleId])) {
            continue;
        }

        $stmt =
            $pdo->prepare(
                "DELETE FROM financial_allocations
                 WHERE farm_id=?
                   AND expense_id=?
                   AND cycle_id=?"
            );

        $stmt->execute([
            $farmId,
            $expenseId,
            $cycleId,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                'Financial allocation projection delete was not deterministic.'
            );
        }
    }

    foreach ($desiredMap as $cycleId => $desired) {
        if (isset($currentMap[$cycleId])) {
            $current =
                financial_allocation_persistence_semantic_rows([
                    $currentMap[$cycleId],
                ]);

            $next =
                financial_allocation_persistence_semantic_rows([
                    $desired,
                ]);

            /*
             * Preserve row identity and created metadata when the target
             * already exists. Only business-state changes are updated.
             */
            if ($current === $next) {
                continue;
            }

            $stmt =
                $pdo->prepare(
                    "UPDATE financial_allocations
                     SET
                         allocated_amount=?,
                         allocation_percent=?,
                         notes=?
                     WHERE farm_id=?
                       AND expense_id=?
                       AND cycle_id=?"
                );

            $stmt->execute([
                $desired['allocated_amount'],
                $desired['allocation_percent'],
                $desired['notes']
                    ?? null,
                $farmId,
                $expenseId,
                $cycleId,
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException(
                    'Financial allocation projection update was not deterministic.'
                );
            }

            continue;
        }

        $stmt =
            $pdo->prepare(
                "INSERT INTO financial_allocations
                    (
                        farm_id,
                        expense_id,
                        cycle_id,
                        allocation_percent,
                        allocated_amount,
                        notes,
                        created_by,
                        created_at
                    )
                 VALUES
                    (
                        ?,?,?,?,?,?,?,NOW()
                    )"
            );

        $stmt->execute([
            $farmId,
            $expenseId,
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
    'financial_allocation_persistence_retain_shared'
)) {
function financial_allocation_persistence_retain_shared(
    PDO $pdo,
    int $farmId,
    int $expenseId,
    int $actorUserId,
    ?string $revisionReason
): array {
    financial_allocation_persistence_require_transaction(
        $pdo
    );

    if (
        $farmId < 1
        || $expenseId < 1
    ) {
        throw new InvalidArgumentException(
            'Shared cost parent identity is invalid.'
        );
    }

    $actorUserId =
        financial_allocation_persistence_actor(
            $actorUserId
        );

    /*
     * This is a reviewed business decision, not an allocation.
     *
     * The parent expense remains unchanged.
     * financial_allocations remains empty.
     * The immutable expense revision ledger records that the cost was
     * deliberately retained at shared-operation level.
     */
    $parent =
        financial_allocation_service_parent(
            $pdo,
            $farmId,
            $expenseId,
            true
        );

    $currentRows =
        financial_allocation_service_current_rows(
            $pdo,
            $farmId,
            $expenseId,
            true
        );

    $animalCount =
        financial_allocation_service_animal_count(
            $pdo,
            $farmId,
            $expenseId,
            true
        );

    if ($currentRows !== []) {
        throw new RuntimeException(
            'Only fully unallocated shared costs can be retained entirely as shared.'
        );
    }

    if ($animalCount > 0) {
        throw new RuntimeException(
            'A cost allocated to individual animals cannot also be retained as shared.'
        );
    }

    $validated =
        financial_allocation_service_validate_desired_rows(
            $parent,
            [],
            [],
            0
        );

    /*
     * Establish legacy provenance when necessary, or verify canonical
     * projection/history consistency before recording the decision.
     */
    expense_revision_service_prepare_existing_mutation(
        $pdo,
        $farmId,
        $expenseId,
        $actorUserId
    );

    /*
     * Re-read while locks remain held and fail closed if allocation state
     * changed while revision provenance was being established.
     */
    $lockedParent =
        financial_allocation_service_parent(
            $pdo,
            $farmId,
            $expenseId,
            true
        );

    $lockedRows =
        financial_allocation_service_current_rows(
            $pdo,
            $farmId,
            $expenseId,
            true
        );

    $lockedAnimalCount =
        financial_allocation_service_animal_count(
            $pdo,
            $farmId,
            $expenseId,
            true
        );

    if ($lockedRows !== []) {
        throw new RuntimeException(
            'Shared cost allocation changed while recording the retained-shared decision.'
        );
    }

    if ($lockedAnimalCount > 0) {
        throw new RuntimeException(
            'Shared cost animal allocation changed while recording the retained-shared decision.'
        );
    }

    $lockedValidated =
        financial_allocation_service_validate_desired_rows(
            $lockedParent,
            [],
            [],
            0
        );

    if (
        $lockedValidated[
            'allocated_amount'
        ] !== $validated[
            'allocated_amount'
        ]
        ||
        $lockedValidated[
            'remaining_amount'
        ] !== $validated[
            'remaining_amount'
        ]
    ) {
        throw new RuntimeException(
            'Shared cost parent value changed while recording the retained-shared decision.'
        );
    }

    $latest =
        expense_revision_service_latest(
            $pdo,
            $farmId,
            $expenseId,
            true
        );

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

            'action' =>
                'retain_shared',

            'expense_id' =>
                $expenseId,

            'rows' =>
                [],

            'allocated_amount' =>
                $lockedValidated[
                    'allocated_amount'
                ],

            'remaining_amount' =>
                $lockedValidated[
                    'remaining_amount'
                ],

            'revision_no' =>
                (int)$latest[
                    'revision_no'
                ],
        ];
    }

    $revision =
        expense_revision_service_record_retained_shared(
            $pdo,
            $farmId,
            $expenseId,
            $actorUserId,
            $revisionReason
        );

    $latestWritten =
        expense_revision_service_latest(
            $pdo,
            $farmId,
            $expenseId,
            true
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
            'Shared cost retention decision was not persisted safely.'
        );
    }

    return [
        'changed' =>
            !empty(
                $revision[
                    'changed'
                ]
            ),

        'action' =>
            'retain_shared',

        'expense_id' =>
            $expenseId,

        'rows' =>
            [],

        'allocated_amount' =>
            $lockedValidated[
                'allocated_amount'
            ],

        'remaining_amount' =>
            $lockedValidated[
                'remaining_amount'
            ],

        'revision_no' =>
            (int)$latestWritten[
                'revision_no'
            ],
    ];
}
}


if (!function_exists(
    'financial_allocation_persistence_apply'
)) {
function financial_allocation_persistence_apply(
    PDO $pdo,
    int $farmId,
    int $expenseId,
    array $desiredRows,
    int $actorUserId,
    ?string $revisionReason
): array {
    financial_allocation_persistence_require_transaction(
        $pdo
    );

    if (
        $farmId < 1
        ||
        $expenseId < 1
    ) {
        throw new InvalidArgumentException(
            'Financial allocation parent identity is invalid.'
        );
    }

    $actorUserId =
        financial_allocation_persistence_actor(
            $actorUserId
        );

    /*
     * Lock parent/current allocation/animal state before target cycles.
     * This establishes one deterministic mutation boundary.
     */
    $parent =
        financial_allocation_service_parent(
            $pdo,
            $farmId,
            $expenseId,
            true
        );

    $currentRows =
        financial_allocation_service_current_rows(
            $pdo,
            $farmId,
            $expenseId,
            true
        );

    $animalCount =
        financial_allocation_service_animal_count(
            $pdo,
            $farmId,
            $expenseId,
            true
        );

    $cycleIds =
        financial_allocation_persistence_cycle_ids(
            array_merge(
                $currentRows,
                $desiredRows
            )
        );

    $cycles =
        financial_allocation_service_target_cycles(
            $pdo,
            $farmId,
            $cycleIds,
            true
        );

    /*
     * Validate both current and desired states through the single
     * financial/shared-cost policy authority.
     */
    $currentValidated =
        financial_allocation_service_validate_desired_rows(
            $parent,
            $cycles,
            $currentRows,
            $animalCount
        );

    financial_allocation_persistence_assert_projection(
        $currentRows,
        $currentValidated['rows']
            ?? []
    );

    $desiredValidated =
        financial_allocation_service_validate_desired_rows(
            $parent,
            $cycles,
            $desiredRows,
            $animalCount
        );

    $currentSemantic =
        financial_allocation_persistence_semantic_rows(
            $currentValidated['rows']
            ?? []
        );

    $desiredSemantic =
        financial_allocation_persistence_semantic_rows(
            $desiredValidated['rows']
            ?? []
        );

    /*
     * Exact economic no-op must not manufacture a revision.
     *
     * Legacy rows remain genuinely untouched: clicking Save without a
     * business-state change must not establish their first baseline.
     *
     * Canonical rows are different. Their complete current state must still
     * agree with the immutable expense revision chain before a no-op can be
     * accepted. This prevents state-only/out-of-band projection drift from
     * being hidden merely because cycle/amount semantics are unchanged.
     */
    if ($currentSemantic === $desiredSemantic) {
        $projectionRevision =
            $parent[
                'expense_revision_no'
            ]
            ?? null;

        $projectionFingerprint =
            $parent[
                'expense_causal_fingerprint'
            ]
            ?? null;

        $noopRevisionNo =
            null;

        if (
            $projectionRevision === null
            &&
            $projectionFingerprint === null
        ) {
            /*
             * A genuine legacy no-op remains legacy, but fail closed if
             * revision history somehow exists without projection metadata.
             */
            $legacyLatest =
                expense_revision_service_latest(
                    $pdo,
                    $farmId,
                    $expenseId,
                    true
                );

            if ($legacyLatest !== null) {
                throw new RuntimeException(
                    'Legacy expense metadata is inconsistent with revision history.'
                );
            }

        } else {
            /*
             * For canonical or partially-populated metadata, reuse the
             * established expense provenance authority. Canonical state is
             * fully checked; partial/missing linkage fails closed.
             *
             * This call cannot create a legacy baseline because the
             * both-NULL legacy case returned through the branch above.
             */
            $preparedNoop =
                expense_revision_service_prepare_existing_mutation(
                    $pdo,
                    $farmId,
                    $expenseId,
                    $actorUserId
                );

            $noopRevisionNo =
                (int)$preparedNoop[
                    'revision_no'
                ];
        }

        return [
            'changed' =>
                false,

            'action' =>
                'noop',

            'expense_id' =>
                $expenseId,

            'rows' =>
                $desiredSemantic,

            'allocated_amount' =>
                $desiredValidated[
                    'allocated_amount'
                ],

            'remaining_amount' =>
                $desiredValidated[
                    'remaining_amount'
                ],

            'revision_no' =>
                $noopRevisionNo,
        ];
    }

    shared_cost_contract_assert_pre_cycle_reason(
        $parent['expense_date']
            ?? null,
        $cycles,
        $desiredValidated['rows']
            ?? [],
        $revisionReason
    );

    /*
     * A real allocation mutation becomes part of the SAME expense
     * revision transaction. For a legacy expense this establishes its
     * controlled baseline first; for a canonical expense it verifies
     * projection/history consistency.
     */
    expense_revision_service_prepare_existing_mutation(
        $pdo,
        $farmId,
        $expenseId,
        $actorUserId
    );

    /*
     * Re-read/revalidate while retaining the already-held parent lock.
     * Fail closed if anything differs from the pre-mutation state.
     */
    $lockedParent =
        financial_allocation_service_parent(
            $pdo,
            $farmId,
            $expenseId,
            true
        );

    $lockedCurrentRows =
        financial_allocation_service_current_rows(
            $pdo,
            $farmId,
            $expenseId,
            true
        );

    $lockedAnimalCount =
        financial_allocation_service_animal_count(
            $pdo,
            $farmId,
            $expenseId,
            true
        );

    $lockedCycleIds =
        financial_allocation_persistence_cycle_ids(
            array_merge(
                $lockedCurrentRows,
                $desiredRows
            )
        );

    $lockedCycles =
        financial_allocation_service_target_cycles(
            $pdo,
            $farmId,
            $lockedCycleIds,
            true
        );

    $lockedCurrentValidated =
        financial_allocation_service_validate_desired_rows(
            $lockedParent,
            $lockedCycles,
            $lockedCurrentRows,
            $lockedAnimalCount
        );

    financial_allocation_persistence_assert_projection(
        $lockedCurrentRows,
        $lockedCurrentValidated['rows']
            ?? []
    );

    $lockedCurrentSemantic =
        financial_allocation_persistence_semantic_rows(
            $lockedCurrentValidated['rows']
            ?? []
        );

    if ($lockedCurrentSemantic !== $currentSemantic) {
        throw new RuntimeException(
            'Financial allocation state changed while establishing revision provenance.'
        );
    }

    $lockedDesiredValidated =
        financial_allocation_service_validate_desired_rows(
            $lockedParent,
            $lockedCycles,
            $desiredRows,
            $lockedAnimalCount
        );

    $lockedDesiredSemantic =
        financial_allocation_persistence_semantic_rows(
            $lockedDesiredValidated['rows']
            ?? []
        );

    if ($lockedDesiredSemantic !== $desiredSemantic) {
        throw new RuntimeException(
            'Financial allocation desired state changed during validation.'
        );
    }

    $action =
        $lockedCurrentSemantic === []
            ? 'create'
            : (
                $lockedDesiredSemantic === []
                    ? 'clear'
                    : 'update'
            );

    financial_allocation_persistence_write_projection(
        $pdo,
        $farmId,
        $expenseId,
        $lockedCurrentRows,
        $lockedDesiredValidated['rows']
            ?? [],
        $actorUserId
    );

    /*
     * Reload the projection before revision capture. The revision service
     * must fingerprint the exact state that will commit.
     */
    $writtenRows =
        financial_allocation_service_current_rows(
            $pdo,
            $farmId,
            $expenseId,
            true
        );

    $writtenSemantic =
        financial_allocation_persistence_semantic_rows(
            $writtenRows
        );

    if ($writtenSemantic !== $lockedDesiredSemantic) {
        throw new RuntimeException(
            'Financial allocation projection does not match the validated desired state.'
        );
    }

    $revision =
        expense_revision_service_record_updated(
            $pdo,
            $farmId,
            $expenseId,
            $actorUserId,
            $revisionReason
        );

    if (empty($revision['changed'])) {
        throw new RuntimeException(
            'Financial allocation mutation did not produce the required expense revision.'
        );
    }

    return [
        'changed' =>
            true,

        'action' =>
            $action,

        'expense_id' =>
            $expenseId,

        'rows' =>
            $lockedDesiredSemantic,

        'allocated_amount' =>
            $lockedDesiredValidated[
                'allocated_amount'
            ],

        'remaining_amount' =>
            $lockedDesiredValidated[
                'remaining_amount'
            ],

        'revision_id' =>
            (int)$revision['id'],

        'revision_no' =>
            (int)$revision['revision_no'],
    ];
}
}
