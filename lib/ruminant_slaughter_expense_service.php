<?php

declare(strict_types=1);

require_once __DIR__
    . '/ruminant_expense_entry.php';

require_once __DIR__
    . '/expense_revision_service.php';


/*
 * V3.0.1 Ruminant Slaughter Processing Expense Service.
 *
 * Authority chain:
 *
 * ruminant_slaughter_batches
 *   -> canonical farm_expenses
 *   -> ruminant_expense_animal_allocations
 *   -> immutable farm_expense_revisions
 *   -> ruminant_slaughter_batch_expenses provenance
 *
 * This service never creates a second financial expense ledger.
 *
 * Processing expenses are recognized on the physical slaughter date and are
 * allocated 100% to the slaughtered tagged animal.
 *
 * Existing frozen slaughter cost bases are immutable:
 * a batch that already has a frozen cost basis or any Inventory output cannot
 * receive a processing expense through this service.
 */


if (!function_exists(
    'ruminant_slaughter_expense_request_token'
)) {
function ruminant_slaughter_expense_request_token(
    string $value
): string {
    $token =
        strtolower(
            trim(
                $value
            )
        );

    if (
        preg_match(
            '/^[a-f0-9]{32,64}$/',
            $token
        ) !== 1
    ) {
        throw new InvalidArgumentException(
            'Invalid Ruminant slaughter processing-expense submission token. Refresh the page and try again.'
        );
    }

    return $token;
}
}


if (!function_exists(
    'ruminant_slaughter_expense_fingerprint'
)) {
function ruminant_slaughter_expense_fingerprint(
    int $batchId,
    string $category,
    string $amount,
    string $unit,
    string $description
): string {
    if ($batchId < 1) {
        throw new InvalidArgumentException(
            'A valid Ruminant slaughter batch is required.'
        );
    }

    $payload = [
        'contract' =>
            'ruminant_slaughter_processing_expense_request_v1',

        'batch_id' =>
            $batchId,

        'category' =>
            $category,

        'amount' =>
            $amount,

        'unit' =>
            $unit,

        'description' =>
            trim(
                $description
            ),
    ];

    $json =
        json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES
            |
            JSON_PRESERVE_ZERO_FRACTION
        );

    if (!is_string($json)) {
        throw new RuntimeException(
            'Ruminant processing-expense request identity could not be serialized.'
        );
    }

    return
        hash(
            'sha256',
            $json
        );
}
}


if (!function_exists(
    'ruminant_slaughter_expense_batch_locked'
)) {
function ruminant_slaughter_expense_batch_locked(
    PDO $pdo,
    int $farmId,
    int $batchId
): array {
    if (
        $farmId < 1
        ||
        $batchId < 1
    ) {
        throw new InvalidArgumentException(
            'Choose a valid Ruminant slaughter batch.'
        );
    }

    $stmt =
        $pdo->prepare(
            "SELECT
                 b.*,
                 a.tag_no,
                 a.species,
                 a.status AS animal_status,
                 e.exit_outcome,
                 e.resulting_status,
                 pc.farm_type AS cycle_farm_type,
                 pc.production_type AS cycle_production_type
             FROM ruminant_slaughter_batches b
             INNER JOIN ruminant_animals a
               ON a.id=b.animal_id
              AND a.farm_id=b.farm_id
             INNER JOIN ruminant_animal_exit_events e
               ON e.id=b.exit_event_id
              AND e.farm_id=b.farm_id
             INNER JOIN production_cycles pc
               ON pc.id=b.cycle_id
              AND pc.farm_id=b.farm_id
             WHERE b.id=?
               AND b.farm_id=?
             LIMIT 1
             FOR UPDATE"
        );

    $stmt->execute([
        $batchId,
        $farmId,
    ]);

    $batch =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$batch) {
        throw new RuntimeException(
            'The selected Ruminant slaughter batch could not be found.'
        );
    }

    if (
        strtolower(
            (string)$batch[
                'cycle_farm_type'
            ]
        ) !== 'ruminant'
    ) {
        throw new RuntimeException(
            'The selected slaughter batch is not attached to a Ruminant production cycle.'
        );
    }

    $species =
        strtolower(
            trim(
                (string)$batch[
                    'species'
                ]
            )
        );

    $cycleProductionType =
        strtolower(
            trim(
                (string)$batch[
                    'cycle_production_type'
                ]
            )
        );

    if (
        $species === ''
        ||
        $cycleProductionType !== $species
    ) {
        throw new RuntimeException(
            'The Ruminant slaughter batch species and production cycle are inconsistent.'
        );
    }

    return $batch;
}
}


if (!function_exists(
    'ruminant_slaughter_processing_expense_add'
)) {
function ruminant_slaughter_processing_expense_add(
    PDO $pdo,
    int $farmId,
    int $batchId,
    string $category,
    $amount,
    $unit,
    ?string $description,
    int $actorUserId,
    string $requestToken
): array {
    if (
        $farmId < 1
        ||
        $batchId < 1
        ||
        $actorUserId < 1
    ) {
        throw new InvalidArgumentException(
            'Processing expense requires a valid farm, Ruminant slaughter batch and user.'
        );
    }

    $requestToken =
        ruminant_slaughter_expense_request_token(
            $requestToken
        );

    $category =
        ruminant_expense_entry_category(
            $category,
            'slaughter_processing'
        );

    $amount =
        ruminant_expense_entry_positive_decimal(
            $amount,
            'Expense amount'
        );

    $unit =
        ruminant_expense_entry_positive_decimal(
            $unit,
            'Expense quantity'
        );

    $description =
        trim(
            (string)$description
        );

    $requestFingerprint =
        ruminant_slaughter_expense_fingerprint(
            $batchId,
            $category,
            $amount,
            $unit,
            $description
        );

    $startedTransaction =
        !$pdo->inTransaction();

    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $batch =
            ruminant_slaughter_expense_batch_locked(
                $pdo,
                $farmId,
                $batchId
            );

        /*
         * Global-in-farm request-token replay protection.
         *
         * This is checked before mutable-state rejection so a successful
         * request retry remains idempotent if the batch later freezes.
         */
        $existingStmt =
            $pdo->prepare(
                "SELECT *
                 FROM ruminant_slaughter_batch_expenses
                 WHERE farm_id=?
                   AND request_token=?
                 LIMIT 1
                 FOR UPDATE"
            );

        $existingStmt->execute([
            $farmId,
            $requestToken,
        ]);

        $existing =
            $existingStmt->fetch(
                PDO::FETCH_ASSOC
            );

        if ($existing) {
            if (
                (int)$existing[
                    'batch_id'
                ] !== $batchId
                ||
                !hash_equals(
                    (string)$existing[
                        'request_fingerprint'
                    ],
                    $requestFingerprint
                )
            ) {
                throw new RuntimeException(
                    'This Ruminant processing-expense submission token has already been used for different details. Refresh and submit again.'
                );
            }

            if ($startedTransaction) {
                $pdo->commit();
            }

            return [
                'link_id' =>
                    (int)$existing['id'],

                'expense_id' =>
                    (int)$existing[
                        'expense_id'
                    ],

                'expense_revision_id' =>
                    (int)$existing[
                        'expense_revision_id'
                    ],

                'amount_snapshot' =>
                    round(
                        (float)$existing[
                            'amount_snapshot'
                        ],
                        2
                    ),

                'idempotent' =>
                    true,
            ];
        }

        if (
            strtolower(
                (string)$batch['status']
            ) !== 'open'
        ) {
            throw new RuntimeException(
                'Only an open Ruminant slaughter batch can receive processing expenses.'
            );
        }

        if (
            $batch[
                'cost_basis_amount'
            ] !== null
            ||
            !empty(
                $batch[
                    'cost_basis_snapshot_at'
                ]
            )
        ) {
            throw new RuntimeException(
                'Processing expenses cannot be added after the Ruminant slaughter cost basis has been frozen.'
            );
        }

        if (
            (string)$batch[
                'animal_status'
            ] !== 'slaughtered'
            ||
            (string)$batch[
                'exit_outcome'
            ] !== 'manual_slaughtered'
            ||
            (string)$batch[
                'resulting_status'
            ] !== 'slaughtered'
        ) {
            throw new RuntimeException(
                'This Ruminant slaughter batch is no longer linked to a valid Slaughtered lifecycle event.'
            );
        }

        $outputStmt =
            $pdo->prepare(
                "SELECT COUNT(*)
                 FROM ruminant_slaughter_outputs
                 WHERE farm_id=?
                   AND batch_id=?"
            );

        $outputStmt->execute([
            $farmId,
            $batchId,
        ]);

        if (
            (int)$outputStmt->fetchColumn()
            > 0
        ) {
            throw new RuntimeException(
                'Processing expenses cannot change after Ruminant slaughter output Inventory has been created.'
            );
        }

        $productionType =
            strtolower(
                trim(
                    (string)$batch[
                        'species'
                    ]
                )
            );

        $expenseDescription =
            'Slaughter processing '
            .
            (string)$batch[
                'batch_code'
            ];

        if ($description !== '') {
            $expenseDescription .=
                ' · '
                .
                $description;
        }

        /*
         * One slaughter-processing expense belongs directly to the slaughtered
         * tagged animal. A one-animal equal allocation therefore assigns
         * exactly 100% of the expense without inventing a new allocation rule.
         */
        $created =
            ruminant_expense_entry_create(
                $pdo,
                $farmId,
                $actorUserId,
                [
                    'expense_date' =>
                        (string)$batch[
                            'slaughter_date'
                        ],

                    'production_type' =>
                        $productionType,

                    'cycle_id' =>
                        (int)$batch[
                            'cycle_id'
                        ],

                    'category' =>
                        $category,

                    'amount' =>
                        $amount,

                    'unit' =>
                        $unit,

                    'description' =>
                        $expenseDescription,

                    'animal_allocation_mode' =>
                        'equal',

                    'animal_ids' => [
                        (int)$batch[
                            'animal_id'
                        ],
                    ],
                ],
                'slaughter_processing'
            );

        $expenseId =
            (int)$created[
                'expense_id'
            ];

        $allocationRows =
            $created[
                'animal_allocation'
            ][
                'rows'
            ]
            ?? [];

        if (
            count(
                $allocationRows
            ) !== 1
            ||
            (int)$allocationRows[0][
                'animal_id'
            ]
                !==
                (int)$batch[
                    'animal_id'
                ]
        ) {
            throw new RuntimeException(
                'Canonical Ruminant processing-expense animal attribution could not be established.'
            );
        }

        $expense =
            expense_revision_service_expense(
                $pdo,
                $farmId,
                $expenseId,
                true
            );

        $revision =
            expense_revision_service_latest(
                $pdo,
                $farmId,
                $expenseId,
                true
            );

        if (
            $revision === null
            ||
            (int)$revision[
                'revision_no'
            ] < 1
            ||
            (int)$expense[
                'expense_revision_no'
            ]
                !==
                (int)$revision[
                    'revision_no'
                ]
            ||
            !hash_equals(
                (string)$expense[
                    'expense_causal_fingerprint'
                ],
                (string)$revision[
                    'causal_fingerprint'
                ]
            )
        ) {
            throw new RuntimeException(
                'Canonical Ruminant processing-expense revision provenance could not be established.'
            );
        }

        $amountSnapshot =
            round(
                (float)$expense[
                    'amount'
                ]
                *
                (float)$expense[
                    'unit'
                ],
                2
            );

        if ($amountSnapshot <= 0) {
            throw new RuntimeException(
                'Canonical Ruminant processing-expense snapshot must be greater than zero.'
            );
        }

        $allocatedAmount =
            round(
                (float)$allocationRows[0][
                    'allocated_amount'
                ],
                2
            );

        if (
            abs(
                $allocatedAmount
                -
                $amountSnapshot
            ) > 0.005
        ) {
            throw new RuntimeException(
                'Ruminant processing-expense animal allocation does not conserve the expense total.'
            );
        }

        $insert =
            $pdo->prepare(
                "INSERT INTO ruminant_slaughter_batch_expenses
                 (
                     farm_id,
                     batch_id,
                     request_token,
                     request_fingerprint,
                     expense_id,
                     expense_revision_id,
                     expense_revision_no,
                     expense_causal_fingerprint,
                     amount_snapshot
                 )
                 VALUES (?,?,?,?,?,?,?,?,?)"
            );

        $insert->execute([
            $farmId,
            $batchId,
            $requestToken,
            $requestFingerprint,
            $expenseId,
            (int)$revision['id'],
            (int)$revision[
                'revision_no'
            ],
            (string)$revision[
                'causal_fingerprint'
            ],
            $amountSnapshot,
        ]);

        $linkId =
            (int)$pdo->lastInsertId();

        if (
            function_exists(
                'audit_log_event'
            )
        ) {
            audit_log_event(
                'ruminant_slaughter_processing_expense_linked',
                'ruminant_slaughter_batch',
                $batchId,
                [
                    'animal_id' =>
                        (int)$batch[
                            'animal_id'
                        ],

                    'tag_no' =>
                        (string)$batch[
                            'tag_no'
                        ],

                    'expense_id' =>
                        $expenseId,

                    'expense_revision_id' =>
                        (int)$revision['id'],

                    'category' =>
                        $category,

                    'amount_snapshot' =>
                        $amountSnapshot,

                    'request_fingerprint' =>
                        $requestFingerprint,
                ]
            );
        }

        if ($startedTransaction) {
            $pdo->commit();
        }

        return [
            'link_id' =>
                $linkId,

            'expense_id' =>
                $expenseId,

            'expense_revision_id' =>
                (int)$revision['id'],

            'amount_snapshot' =>
                $amountSnapshot,

            'animal_id' =>
                (int)$batch[
                    'animal_id'
                ],

            'idempotent' =>
                false,
        ];

    } catch (Throwable $e) {
        if (
            $startedTransaction
            &&
            $pdo->inTransaction()
        ) {
            $pdo->rollBack();
        }

        throw $e;
    }
}
}


if (!function_exists(
    'ruminant_slaughter_processing_expenses_for_batches'
)) {
function ruminant_slaughter_processing_expenses_for_batches(
    PDO $pdo,
    int $farmId,
    array $batchIds
): array {
    if ($farmId < 1) {
        return [];
    }

    $batchIds =
        array_values(
            array_unique(
                array_filter(
                    array_map(
                        'intval',
                        $batchIds
                    ),
                    static fn(int $id): bool =>
                        $id > 0
                )
            )
        );

    if (!$batchIds) {
        return [];
    }

    $placeholders =
        implode(
            ',',
            array_fill(
                0,
                count($batchIds),
                '?'
            )
        );

    $sql =
        "SELECT
             l.id AS link_id,
             l.batch_id,
             l.expense_id,
             l.expense_revision_id,
             l.expense_revision_no,
             l.expense_causal_fingerprint,
             l.amount_snapshot,
             l.created_at AS linked_at,

             e.public_reference,
             e.expense_date,
             e.production_type,
             e.cycle_id,
             e.category,
             e.amount,
             e.unit,
             e.description,
             e.expense_revision_no AS current_revision_no,
             e.expense_causal_fingerprint AS current_causal_fingerprint,

             er.revision_action,
             er.causal_fingerprint AS revision_causal_fingerprint,

             aa.animal_id,
             aa.allocation_method,
             aa.allocation_percent,
             aa.allocated_amount AS animal_allocated_amount

         FROM ruminant_slaughter_batch_expenses l

         INNER JOIN ruminant_slaughter_batches b
           ON b.id=l.batch_id
          AND b.farm_id=l.farm_id

         INNER JOIN farm_expenses e
           ON e.id=l.expense_id
          AND e.farm_id=l.farm_id

         INNER JOIN farm_expense_revisions er
           ON er.id=l.expense_revision_id
          AND er.farm_id=l.farm_id
          AND er.expense_id=l.expense_id
          AND er.revision_no=l.expense_revision_no
          AND er.causal_fingerprint=l.expense_causal_fingerprint

         INNER JOIN ruminant_expense_animal_allocations aa
           ON aa.farm_id=l.farm_id
          AND aa.expense_id=l.expense_id
          AND aa.animal_id=b.animal_id

         WHERE l.farm_id=?
           AND l.batch_id IN ({$placeholders})

         ORDER BY
             l.batch_id,
             l.id";

    $params = [
        $farmId,
    ];

    foreach ($batchIds as $batchId) {
        $params[] =
            $batchId;
    }

    $stmt =
        $pdo->prepare(
            $sql
        );

    $stmt->execute(
        $params
    );

    $map = [];

    foreach (
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: []
        as $row
    ) {
        $batchId =
            (int)$row[
                'batch_id'
            ];

        $row[
            'category_label'
        ] =
            expense_category_label(
                (string)$row[
                    'category'
                ]
            );

        $row[
            'amount_snapshot'
        ] =
            round(
                (float)$row[
                    'amount_snapshot'
                ],
                2
            );

        $row[
            'animal_allocated_amount'
        ] =
            round(
                (float)$row[
                    'animal_allocated_amount'
                ],
                2
            );

        $row[
            'projection_matches_link'
        ] =
            (int)$row[
                'current_revision_no'
            ]
                ===
                (int)$row[
                    'expense_revision_no'
                ]
            &&
            hash_equals(
                (string)$row[
                    'current_causal_fingerprint'
                ],
                (string)$row[
                    'expense_causal_fingerprint'
                ]
            )
            &&
            hash_equals(
                (string)$row[
                    'revision_causal_fingerprint'
                ],
                (string)$row[
                    'expense_causal_fingerprint'
                ]
            );

        if (!isset($map[$batchId])) {
            $map[$batchId] = [
                'rows' => [],
                'snapshot_total' => 0.0,
            ];
        }

        $map[$batchId]['rows'][] =
            $row;

        $map[$batchId]['snapshot_total'] +=
            (float)$row[
                'amount_snapshot'
            ];
    }

    foreach ($map as &$entry) {
        $entry['snapshot_total'] =
            round(
                (float)$entry[
                    'snapshot_total'
                ],
                2
            );
    }
    unset($entry);

    return $map;
}
}
