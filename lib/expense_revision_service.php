<?php

require_once __DIR__ . '/expense_revision_provenance.php';

/**
 * V3.0.1 — canonical Expense Revision persistence service.
 *
 * Contract:
 * - farm_expenses remains the current/live projection;
 * - farm_expense_revisions is append-only historical evidence;
 * - this service never owns the transaction;
 * - callers must begin the transaction before invoking mutation helpers;
 * - legacy expenses are not globally backfilled;
 * - a legacy baseline is established only when that expense is first mutated;
 * - business-field mutation remains in the thin writer during the cutover;
 * - only revision metadata is updated here on farm_expenses.
 */

if (!function_exists('expense_revision_service_require_transaction')) {
function expense_revision_service_require_transaction(
    PDO $pdo
): void {
    if (!$pdo->inTransaction()) {
        throw new RuntimeException(
            'Expense revision persistence requires an active transaction.'
        );
    }
}
}


if (!function_exists('expense_revision_service_expense')) {
function expense_revision_service_expense(
    PDO $pdo,
    int $farmId,
    int $expenseId,
    bool $forUpdate = true
): array {
    expense_revision_service_require_transaction(
        $pdo
    );

    $sql =
        "SELECT *
         FROM farm_expenses
         WHERE farm_id=?
           AND id=?
         LIMIT 1";

    if ($forUpdate) {
        $sql .= " FOR UPDATE";
    }

    $stmt =
        $pdo->prepare(
            $sql
        );

    $stmt->execute([
        $farmId,
        $expenseId,
    ]);

    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$row) {
        throw new RuntimeException(
            'Expense record not found.'
        );
    }

    return $row;
}
}


if (!function_exists('expense_revision_service_financial_allocations')) {
function expense_revision_service_financial_allocations(
    PDO $pdo,
    int $farmId,
    int $expenseId,
    bool $forUpdate = true
): array {
    expense_revision_service_require_transaction(
        $pdo
    );

    $sql =
        "SELECT
             id,
             farm_id,
             expense_id,
             cycle_id,
             allocation_percent,
             allocated_amount,
             notes,
             created_by,
             created_at
         FROM financial_allocations
         WHERE farm_id=?
           AND expense_id=?
         ORDER BY id";

    if ($forUpdate) {
        $sql .= " FOR UPDATE";
    }

    $stmt =
        $pdo->prepare(
            $sql
        );

    $stmt->execute([
        $farmId,
        $expenseId,
    ]);

    return
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];
}
}


if (!function_exists('expense_revision_service_animal_allocations')) {
function expense_revision_service_animal_allocations(
    PDO $pdo,
    int $farmId,
    int $expenseId,
    bool $forUpdate = true
): array {
    expense_revision_service_require_transaction(
        $pdo
    );

    $sql =
        "SELECT
             id,
             farm_id,
             expense_id,
             animal_id,
             allocation_method,
             allocation_percent,
             allocated_amount,
             created_by,
             created_at
         FROM ruminant_expense_animal_allocations
         WHERE farm_id=?
           AND expense_id=?
         ORDER BY id";

    if ($forUpdate) {
        $sql .= " FOR UPDATE";
    }

    $stmt =
        $pdo->prepare(
            $sql
        );

    $stmt->execute([
        $farmId,
        $expenseId,
    ]);

    return
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];
}
}


if (!function_exists('expense_revision_service_state')) {
function expense_revision_service_state(
    PDO $pdo,
    int $farmId,
    int $expenseId,
    bool $forUpdate = true
): array {
    $expense =
        expense_revision_service_expense(
            $pdo,
            $farmId,
            $expenseId,
            $forUpdate
        );

    $financial =
        expense_revision_service_financial_allocations(
            $pdo,
            $farmId,
            $expenseId,
            $forUpdate
        );

    $animals =
        expense_revision_service_animal_allocations(
            $pdo,
            $farmId,
            $expenseId,
            $forUpdate
        );

    $built =
        expense_revision_build(
            $expense,
            $financial,
            $animals
        );

    return [
        'expense' =>
            $expense,

        'financial_allocations' =>
            $financial,

        'animal_allocations' =>
            $animals,

        'built' =>
            $built,
    ];
}
}


if (!function_exists('expense_revision_service_latest')) {
function expense_revision_service_latest(
    PDO $pdo,
    int $farmId,
    int $expenseId,
    bool $forUpdate = true
): ?array {
    expense_revision_service_require_transaction(
        $pdo
    );

    $sql =
        "SELECT *
         FROM farm_expense_revisions
         WHERE farm_id=?
           AND expense_id=?
         ORDER BY revision_no DESC
         LIMIT 1";

    if ($forUpdate) {
        $sql .= " FOR UPDATE";
    }

    $stmt =
        $pdo->prepare(
            $sql
        );

    $stmt->execute([
        $farmId,
        $expenseId,
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


if (!function_exists('expense_revision_service_insert_revision')) {
function expense_revision_service_insert_revision(
    PDO $pdo,
    array $state,
    string $action,
    int $revisionNo,
    ?int $previousRevisionId,
    ?int $changedByUserId
): array {
    expense_revision_service_require_transaction(
        $pdo
    );

    $allowedActions = [
        'create',
        'legacy_baseline',
        'update',
        'delete',
    ];

    if (
        !in_array(
            $action,
            $allowedActions,
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Select a valid expense revision action.'
        );
    }

    if ($revisionNo < 1) {
        throw new InvalidArgumentException(
            'Expense revision number must be positive.'
        );
    }

    if (
        $revisionNo === 1
        && $previousRevisionId !== null
    ) {
        throw new RuntimeException(
            'The first expense revision cannot have a previous revision.'
        );
    }

    if (
        $revisionNo > 1
        && (
            $previousRevisionId === null
            || $previousRevisionId < 1
        )
    ) {
        throw new RuntimeException(
            'A later expense revision must reference its previous revision.'
        );
    }

    if (
        empty($state['expense'])
        || empty($state['built'])
    ) {
        throw new InvalidArgumentException(
            'Expense revision state is incomplete.'
        );
    }

    $expense =
        $state['expense'];

    $built =
        $state['built'];

    $farmId =
        (int)(
            $expense['farm_id']
            ?? 0
        );

    $expenseId =
        (int)(
            $expense['id']
            ?? 0
        );

    if (
        $farmId < 1
        || $expenseId < 1
    ) {
        throw new RuntimeException(
            'Expense revision identity is invalid.'
        );
    }

    $stmt =
        $pdo->prepare(
            "INSERT INTO farm_expense_revisions
                (
                    farm_id,
                    expense_id,
                    revision_no,
                    revision_action,
                    previous_revision_id,
                    causal_fingerprint,
                    causal_manifest_json,
                    state_fingerprint,
                    state_manifest_json,
                    changed_by_user_id
                )
             VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )"
        );

    $stmt->execute([
        $farmId,
        $expenseId,
        $revisionNo,
        $action,
        $previousRevisionId,
        (string)$built[
            'causal_fingerprint'
        ],
        (string)$built[
            'causal_manifest_json'
        ],
        (string)$built[
            'state_fingerprint'
        ],
        (string)$built[
            'state_manifest_json'
        ],
        $changedByUserId
            && $changedByUserId > 0
                ? $changedByUserId
                : null,
    ]);

    return [
        'id' =>
            (int)$pdo->lastInsertId(),

        'revision_no' =>
            $revisionNo,

        'revision_action' =>
            $action,

        'causal_fingerprint' =>
            (string)$built[
                'causal_fingerprint'
            ],

        'state_fingerprint' =>
            (string)$built[
                'state_fingerprint'
            ],
    ];
}
}


if (!function_exists('expense_revision_service_sync_projection')) {
function expense_revision_service_sync_projection(
    PDO $pdo,
    int $farmId,
    int $expenseId,
    int $revisionNo,
    string $causalFingerprint
): void {
    expense_revision_service_require_transaction(
        $pdo
    );

    if (
        $revisionNo < 1
        || preg_match(
            '/^[a-f0-9]{64}$/',
            $causalFingerprint
        ) !== 1
    ) {
        throw new RuntimeException(
            'Expense revision projection metadata is invalid.'
        );
    }

    $stmt =
        $pdo->prepare(
            "UPDATE farm_expenses
             SET
                 expense_revision_no=?,
                 expense_causal_fingerprint=?
             WHERE farm_id=?
               AND id=?"
        );

    $stmt->execute([
        $revisionNo,
        $causalFingerprint,
        $farmId,
        $expenseId,
    ]);
}
}


if (!function_exists('expense_revision_service_record_created')) {
function expense_revision_service_record_created(
    PDO $pdo,
    int $farmId,
    int $expenseId,
    ?int $changedByUserId
): array {
    expense_revision_service_require_transaction(
        $pdo
    );

    $state =
        expense_revision_service_state(
            $pdo,
            $farmId,
            $expenseId,
            true
        );

    $expense =
        $state['expense'];

    if (
        $expense['expense_revision_no'] !== null
        || $expense[
            'expense_causal_fingerprint'
        ] !== null
    ) {
        throw new RuntimeException(
            'A newly created expense already has revision metadata.'
        );
    }

    $latest =
        expense_revision_service_latest(
            $pdo,
            $farmId,
            $expenseId,
            true
        );

    if ($latest !== null) {
        throw new RuntimeException(
            'A newly created expense already has revision history.'
        );
    }

    $revision =
        expense_revision_service_insert_revision(
            $pdo,
            $state,
            'create',
            1,
            null,
            $changedByUserId
        );

    expense_revision_service_sync_projection(
        $pdo,
        $farmId,
        $expenseId,
        1,
        $revision[
            'causal_fingerprint'
        ]
    );

    $revision['changed'] =
        true;

    return $revision;
}
}


if (!function_exists('expense_revision_service_assert_current_consistency')) {
function expense_revision_service_assert_current_consistency(
    array $state,
    array $latest
): void {
    $expense =
        $state['expense'];

    $built =
        $state['built'];

    $projectionRevision =
        (int)(
            $expense[
                'expense_revision_no'
            ]
            ?? 0
        );

    $projectionFingerprint =
        (string)(
            $expense[
                'expense_causal_fingerprint'
            ]
            ?? ''
        );

    if (
        $projectionRevision < 1
        || preg_match(
            '/^[a-f0-9]{64}$/',
            $projectionFingerprint
        ) !== 1
    ) {
        throw new RuntimeException(
            'Expense revision projection metadata is incomplete.'
        );
    }

    if (
        (int)$latest[
            'revision_no'
        ] !== $projectionRevision
        || !hash_equals(
            (string)$latest[
                'causal_fingerprint'
            ],
            $projectionFingerprint
        )
    ) {
        throw new RuntimeException(
            'Expense revision projection is out of sync with its latest revision.'
        );
    }

    if (
        !hash_equals(
            (string)$latest[
                'causal_fingerprint'
            ],
            (string)$built[
                'causal_fingerprint'
            ]
        )
        ||
        !hash_equals(
            (string)$latest[
                'state_fingerprint'
            ],
            (string)$built[
                'state_fingerprint'
            ]
        )
    ) {
        throw new RuntimeException(
            'Expense state changed outside the canonical revision contract.'
        );
    }
}
}


if (!function_exists('expense_revision_service_prepare_existing_mutation')) {
function expense_revision_service_prepare_existing_mutation(
    PDO $pdo,
    int $farmId,
    int $expenseId,
    ?int $changedByUserId
): array {
    expense_revision_service_require_transaction(
        $pdo
    );

    $state =
        expense_revision_service_state(
            $pdo,
            $farmId,
            $expenseId,
            true
        );

    $expense =
        $state['expense'];

    $latest =
        expense_revision_service_latest(
            $pdo,
            $farmId,
            $expenseId,
            true
        );

    $revisionNo =
        $expense[
            'expense_revision_no'
        ];

    $projectionFingerprint =
        $expense[
            'expense_causal_fingerprint'
        ];

    /*
     * Legacy rows are recognizable only when BOTH projection metadata fields
     * are NULL and no revision ledger exists.
     */
    if (
        $revisionNo === null
        && $projectionFingerprint === null
    ) {
        if ($latest !== null) {
            throw new RuntimeException(
                'Legacy expense metadata is inconsistent with revision history.'
            );
        }

        $baseline =
            expense_revision_service_insert_revision(
                $pdo,
                $state,
                'legacy_baseline',
                1,
                null,
                $changedByUserId
            );

        expense_revision_service_sync_projection(
            $pdo,
            $farmId,
            $expenseId,
            1,
            $baseline[
                'causal_fingerprint'
            ]
        );

        return [
            'legacy_baseline_created' =>
                true,

            'revision_id' =>
                $baseline['id'],

            'revision_no' =>
                1,

            'causal_fingerprint' =>
                $baseline[
                    'causal_fingerprint'
                ],

            'state_fingerprint' =>
                $baseline[
                    'state_fingerprint'
                ],
        ];
    }

    /*
     * Half-populated metadata is never silently repaired.
     */
    if (
        $revisionNo === null
        || $projectionFingerprint === null
    ) {
        throw new RuntimeException(
            'Expense revision metadata is partially populated.'
        );
    }

    if ($latest === null) {
        throw new RuntimeException(
            'Expense revision history is missing.'
        );
    }

    expense_revision_service_assert_current_consistency(
        $state,
        $latest
    );

    return [
        'legacy_baseline_created' =>
            false,

        'revision_id' =>
            (int)$latest['id'],

        'revision_no' =>
            (int)$latest[
                'revision_no'
            ],

        'causal_fingerprint' =>
            (string)$latest[
                'causal_fingerprint'
            ],

        'state_fingerprint' =>
            (string)$latest[
                'state_fingerprint'
            ],
    ];
}
}


if (!function_exists('expense_revision_service_record_updated')) {
function expense_revision_service_record_updated(
    PDO $pdo,
    int $farmId,
    int $expenseId,
    ?int $changedByUserId
): array {
    expense_revision_service_require_transaction(
        $pdo
    );

    $state =
        expense_revision_service_state(
            $pdo,
            $farmId,
            $expenseId,
            true
        );

    $expense =
        $state['expense'];

    $latest =
        expense_revision_service_latest(
            $pdo,
            $farmId,
            $expenseId,
            true
        );

    if ($latest === null) {
        throw new RuntimeException(
            'Expense update requires an established prior revision.'
        );
    }

    /*
     * During an update, business fields may already contain the new state,
     * while projection metadata must still identify the prior revision.
     */
    if (
        (int)(
            $expense[
                'expense_revision_no'
            ]
            ?? 0
        ) !== (int)$latest[
            'revision_no'
        ]
        ||
        !hash_equals(
            (string)$latest[
                'causal_fingerprint'
            ],
            (string)(
                $expense[
                    'expense_causal_fingerprint'
                ]
                ?? ''
            )
        )
    ) {
        throw new RuntimeException(
            'Expense update lost its prior revision linkage.'
        );
    }

    /*
     * A submitted edit that produces exactly the same complete state does not
     * manufacture a revision.
     */
    if (
        hash_equals(
            (string)$latest[
                'state_fingerprint'
            ],
            (string)$state['built'][
                'state_fingerprint'
            ]
        )
    ) {
        return [
            'id' =>
                (int)$latest['id'],

            'revision_no' =>
                (int)$latest[
                    'revision_no'
                ],

            'revision_action' =>
                (string)$latest[
                    'revision_action'
                ],

            'causal_fingerprint' =>
                (string)$latest[
                    'causal_fingerprint'
                ],

            'state_fingerprint' =>
                (string)$latest[
                    'state_fingerprint'
                ],

            'changed' =>
                false,
        ];
    }

    $nextRevisionNo =
        (int)$latest[
            'revision_no'
        ] + 1;

    $revision =
        expense_revision_service_insert_revision(
            $pdo,
            $state,
            'update',
            $nextRevisionNo,
            (int)$latest['id'],
            $changedByUserId
        );

    expense_revision_service_sync_projection(
        $pdo,
        $farmId,
        $expenseId,
        $nextRevisionNo,
        $revision[
            'causal_fingerprint'
        ]
    );

    $revision['changed'] =
        true;

    return $revision;
}
}


if (!function_exists('expense_revision_service_record_deleted')) {
function expense_revision_service_record_deleted(
    PDO $pdo,
    int $farmId,
    int $expenseId,
    ?int $changedByUserId
): array {
    expense_revision_service_require_transaction(
        $pdo
    );

    $state =
        expense_revision_service_state(
            $pdo,
            $farmId,
            $expenseId,
            true
        );

    $expense =
        $state['expense'];

    $latest =
        expense_revision_service_latest(
            $pdo,
            $farmId,
            $expenseId,
            true
        );

    if ($latest === null) {
        throw new RuntimeException(
            'Expense deletion requires an established prior revision.'
        );
    }

    if (
        (int)(
            $expense[
                'expense_revision_no'
            ]
            ?? 0
        ) !== (int)$latest[
            'revision_no'
        ]
        ||
        !hash_equals(
            (string)$latest[
                'causal_fingerprint'
            ],
            (string)(
                $expense[
                    'expense_causal_fingerprint'
                ]
                ?? ''
            )
        )
        ||
        !hash_equals(
            (string)$latest[
                'state_fingerprint'
            ],
            (string)$state['built'][
                'state_fingerprint'
            ]
        )
    ) {
        throw new RuntimeException(
            'Expense deletion state is not synchronized with its latest revision.'
        );
    }

    $revision =
        expense_revision_service_insert_revision(
            $pdo,
            $state,
            'delete',
            (int)$latest[
                'revision_no'
            ] + 1,
            (int)$latest['id'],
            $changedByUserId
        );

    /*
     * No projection metadata synchronization here.
     * The caller must physically delete farm_expenses within this same
     * transaction. If that delete fails, this appended revision rolls back.
     */
    $revision['changed'] =
        true;

    return $revision;
}
}
