<?php

require_once __DIR__
    . '/ruminant_animal_entry.php';

require_once __DIR__
    . '/ruminant_cycle_membership.php';

require_once __DIR__
    . '/ruminant_economic_participation.php';


if (!class_exists(
    'RuminantParticipationCorrectionException'
)) {
    class RuminantParticipationCorrectionException
        extends RuntimeException
    {
    }
}


/**
 * Historical participation correction policy.
 *
 * This service is intentionally narrow:
 *
 * - it may move a confirmed physical farm-entry date EARLIER;
 * - it may move one confirmed cycle-membership start EARLIER;
 * - it never rewrites purchase/tag/registry dates;
 * - it never rewrites membership end dates;
 * - it never rewrites transfer-owned membership starts;
 * - it records immutable before/after provenance;
 * - physical population must support the proposed cycle participation.
 *
 * This is a correction workflow, not a generic animal-edit API.
 */

if (!function_exists(
    'ruminant_participation_correction_reason_is_meaningful'
)) {
function ruminant_participation_correction_reason_is_meaningful(
    ?string $reason
): bool {
    $reason =
        strtolower(
            preg_replace(
                '/\s+/',
                ' ',
                trim(
                    (string)$reason
                )
            )
        );

    return !in_array(
        $reason,
        [
            '',
            '-',
            '--',
            'na',
            'n/a',
            'n.a.',
            'none',
            'nil',
            'no reason',
            'not applicable',
        ],
        true
    );
}
}


if (!function_exists(
    'ruminant_participation_correction_request_token'
)) {
function ruminant_participation_correction_request_token(
    string $token
): string {
    $token =
        trim($token);

    if (
        $token === ''
        ||
        strlen($token) > 64
        ||
        preg_match(
            '/^[A-Za-z0-9._:-]+$/',
            $token
        ) !== 1
    ) {
        throw new RuminantParticipationCorrectionException(
            'Participation correction request token is invalid.'
        );
    }

    return $token;
}
}


if (!function_exists(
    'ruminant_participation_correction_history'
)) {
function ruminant_participation_correction_history(
    PDO $pdo,
    int $farmId,
    int $animalId
): array {
    $stmt =
        $pdo->prepare(
            "SELECT
                 c.*,
                 pc.cycle_code
             FROM ruminant_participation_corrections c
             INNER JOIN production_cycles pc
               ON pc.id=c.cycle_id
              AND pc.farm_id=c.farm_id
             WHERE c.farm_id=?
               AND c.animal_id=?
             ORDER BY
                 c.created_at DESC,
                 c.id DESC"
        );

    $stmt->execute([
        $farmId,
        $animalId,
    ]);

    return
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
}
}


if (!function_exists(
    'ruminant_participation_correction_preview_locked'
)) {
function ruminant_participation_correction_preview_locked(
    PDO $pdo,
    int $farmId,
    int $animalId,
    int $membershipId,
    string $newFarmEntryDate,
    string $newMembershipStartDate,
    string $reason
): array {
    if (
        $farmId < 1
        ||
        $animalId < 1
        ||
        $membershipId < 1
    ) {
        throw new RuminantParticipationCorrectionException(
            'Choose a valid animal participation record.'
        );
    }

    $newFarmEntryDate =
        trim(
            $newFarmEntryDate
        );

    $newMembershipStartDate =
        trim(
            $newMembershipStartDate
        );

    if (
        !ruminant_animal_entry_valid_date(
            $newFarmEntryDate
        )
        ||
        !ruminant_animal_entry_valid_date(
            $newMembershipStartDate
        )
    ) {
        throw new RuminantParticipationCorrectionException(
            'Enter valid farm-entry and physical-participation dates.'
        );
    }

    if (
        !ruminant_participation_correction_reason_is_meaningful(
            $reason
        )
    ) {
        throw new RuminantParticipationCorrectionException(
            'Enter a meaningful reason for the historical participation correction.'
        );
    }

    $animalStmt =
        $pdo->prepare(
            "SELECT
                 id,
                 tag_no,
                 species,
                 birth_date,
                 farm_entry_date,
                 purchase_date,
                 status,
                 created_at
             FROM ruminant_animals
             WHERE farm_id=?
               AND id=?
             LIMIT 1
             FOR UPDATE"
        );

    $animalStmt->execute([
        $farmId,
        $animalId,
    ]);

    $animal =
        $animalStmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$animal) {
        throw new RuminantParticipationCorrectionException(
            'Animal could not be found.'
        );
    }

    $oldFarmEntryDate =
        ruminant_animal_farm_entry_date_from_row(
            $animal
        );

    if ($oldFarmEntryDate === null) {
        throw new RuminantParticipationCorrectionException(
            'Current farm-entry provenance could not be resolved.'
        );
    }

    /*
     * This workflow extends known participation earlier.
     * It is deliberately not a generic date rewrite.
     */
    if (
        $newFarmEntryDate
        >
        $oldFarmEntryDate
    ) {
        throw new RuminantParticipationCorrectionException(
            'Historical participation correction cannot move farm entry later. Review the animal history instead.'
        );
    }

    ruminant_animal_assert_farm_entry_date(
        $newFarmEntryDate,
        $animal[
            'birth_date'
        ]
        ?? null,
        date('Y-m-d')
    );

    $membershipStmt =
        $pdo->prepare(
            "SELECT
                 m.id,
                 m.cycle_id,
                 m.start_date,
                 m.end_date,
                 m.opened_by_transfer_id,
                 m.closed_by_transfer_id,
                 m.closed_by_exit_event_id,
                 pc.cycle_code,
                 pc.production_type,
                 pc.start_date AS cycle_start_date,
                 pc.close_date AS cycle_close_date
             FROM ruminant_animal_cycle_memberships m
             INNER JOIN production_cycles pc
               ON pc.id=m.cycle_id
              AND pc.farm_id=m.farm_id
             WHERE m.id=?
               AND m.farm_id=?
               AND m.animal_id=?
             LIMIT 1
             FOR UPDATE"
        );

    $membershipStmt->execute([
        $membershipId,
        $farmId,
        $animalId,
    ]);

    $membership =
        $membershipStmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$membership) {
        throw new RuminantParticipationCorrectionException(
            'Production-cycle participation record could not be found.'
        );
    }

    $species =
        strtolower(
            trim(
                (string)$animal[
                    'species'
                ]
            )
        );

    if (
        strtolower(
            trim(
                (string)$membership[
                    'production_type'
                ]
            )
        )
        !==
        $species
    ) {
        throw new RuminantParticipationCorrectionException(
            'Animal species does not match the production cycle.'
        );
    }

    if (
        !empty(
            $membership[
                'opened_by_transfer_id'
            ]
        )
    ) {
        throw new RuminantParticipationCorrectionException(
            'This participation start is controlled by a recorded cycle transfer. Reverse or correct the transfer instead.'
        );
    }

    $oldMembershipStartDate =
        (string)$membership[
            'start_date'
        ];

    if (
        $newMembershipStartDate
        >
        $oldMembershipStartDate
    ) {
        throw new RuminantParticipationCorrectionException(
            'Historical participation correction cannot move cycle participation later.'
        );
    }

    if (
        $newFarmEntryDate
        >
        $newMembershipStartDate
    ) {
        throw new RuminantParticipationCorrectionException(
            'Physical cycle participation cannot begin before the animal entered the farm/operation.'
        );
    }

    $cycleStartDate =
        (string)$membership[
            'cycle_start_date'
        ];

    if (
        $newMembershipStartDate
        <
        $cycleStartDate
    ) {
        throw new RuminantParticipationCorrectionException(
            'Physical participation cannot begin before the production cycle start date.'
        );
    }

    $membershipEndDate =
        trim(
            (string)(
                $membership[
                    'end_date'
                ]
                ?? ''
            )
        );

    if (
        $membershipEndDate !== ''
        &&
        $newMembershipStartDate
            >
        $membershipEndDate
    ) {
        throw new RuminantParticipationCorrectionException(
            'Physical participation start cannot be after its recorded end date.'
        );
    }

    /*
     * The corrected start must not overlap another cycle membership.
     */
    $overlapStmt =
        $pdo->prepare(
            "SELECT
                 m.id,
                 pc.cycle_code
             FROM ruminant_animal_cycle_memberships m
             INNER JOIN production_cycles pc
               ON pc.id=m.cycle_id
              AND pc.farm_id=m.farm_id
             WHERE m.farm_id=?
               AND m.animal_id=?
               AND m.id<>?
               AND m.start_date<=COALESCE(?, '9999-12-31')
               AND COALESCE(m.end_date,'9999-12-31')>=?
             LIMIT 1
             FOR UPDATE"
        );

    $overlapStmt->execute([
        $farmId,
        $animalId,
        $membershipId,
        $membershipEndDate !== ''
            ? $membershipEndDate
            : null,
        $newMembershipStartDate,
    ]);

    $overlap =
        $overlapStmt->fetch(
            PDO::FETCH_ASSOC
        );

    if ($overlap) {
        throw new RuminantParticipationCorrectionException(
            'The corrected participation range would overlap '
            . (
                $overlap[
                    'cycle_code'
                ]
                ?? 'another production cycle'
            )
            . '.'
        );
    }

    /*
     * Physical population is authoritative.
     *
     * We require exact/canonical evidence that livestock existed in this
     * cycle on the proposed participation-start date.
     */
    $physical =
        ruminant_economic_participation_exact_cycle_population(
            $pdo,
            $farmId,
            (int)$membership[
                'cycle_id'
            ],
            $species,
            $newMembershipStartDate
        );

    if (
        $physical === null
        ||
        (int)(
            $physical[
                'physical_headcount'
            ]
            ?? 0
        ) <= 0
    ) {
        throw new RuminantParticipationCorrectionException(
            'Physical livestock population could not be proven for the proposed participation start date.'
        );
    }

    $currentRegistered =
        ruminant_cycle_eligible_animal_ids(
            $pdo,
            $farmId,
            $species,
            $newMembershipStartDate,
            (int)$membership[
                'cycle_id'
            ]
        );

    $alreadyIncluded =
        in_array(
            $animalId,
            $currentRegistered,
            true
        );

    $proposedRegisteredCount =
        count(
            $currentRegistered
        )
        +
        (
            $alreadyIncluded
                ? 0
                : 1
        );

    $physicalHeadcount =
        (int)$physical[
            'physical_headcount'
        ];

    if (
        $proposedRegisteredCount
        >
        $physicalHeadcount
    ) {
        throw new RuminantParticipationCorrectionException(
            'The corrected individual participation would exceed the proven physical livestock population on that date.'
        );
    }

    /*
     * Frozen slaughter snapshots stay immutable.
     * We disclose their existence rather than silently recalculating them.
     */
    $frozenStmt =
        $pdo->prepare(
            "SELECT COUNT(*)
             FROM ruminant_slaughter_batches
             WHERE farm_id=?
               AND animal_id=?
               AND cost_basis_snapshot_at IS NOT NULL"
        );

    $frozenStmt->execute([
        $farmId,
        $animalId,
    ]);

    $frozenSlaughterCount =
        (int)$frozenStmt->fetchColumn();

    return [
        'farm_id' =>
            $farmId,

        'animal_id' =>
            $animalId,

        'tag_no' =>
            (string)$animal[
                'tag_no'
            ],

        'species' =>
            $species,

        'membership_id' =>
            $membershipId,

        'cycle_id' =>
            (int)$membership[
                'cycle_id'
            ],

        'cycle_code' =>
            (string)$membership[
                'cycle_code'
            ],

        'old_farm_entry_date' =>
            $oldFarmEntryDate,

        'new_farm_entry_date' =>
            $newFarmEntryDate,

        'old_membership_start_date' =>
            $oldMembershipStartDate,

        'new_membership_start_date' =>
            $newMembershipStartDate,

        'physical_headcount' =>
            $physicalHeadcount,

        'registered_before_count' =>
            count(
                $currentRegistered
            ),

        'registered_after_count' =>
            $proposedRegisteredCount,

        'population_source' =>
            (string)(
                $physical[
                    'source'
                ]
                ?? ''
            ),

        'frozen_slaughter_batch_count' =>
            $frozenSlaughterCount,

        'reason' =>
            trim($reason),

        'changes_farm_entry' =>
            $newFarmEntryDate
            !==
            $oldFarmEntryDate,

        'changes_membership_start' =>
            $newMembershipStartDate
            !==
            $oldMembershipStartDate,
    ];
}
}


if (!function_exists(
    'ruminant_participation_correction_apply'
)) {
function ruminant_participation_correction_apply(
    PDO $pdo,
    int $farmId,
    int $animalId,
    int $membershipId,
    string $newFarmEntryDate,
    string $newMembershipStartDate,
    string $reason,
    int $createdBy,
    string $requestToken
): array {
    if ($createdBy < 1) {
        throw new RuminantParticipationCorrectionException(
            'A valid user is required for participation correction.'
        );
    }

    $requestToken =
        ruminant_participation_correction_request_token(
            $requestToken
        );

    if ($pdo->inTransaction()) {
        throw new RuminantParticipationCorrectionException(
            'Participation correction must own its database transaction.'
        );
    }

    $pdo->beginTransaction();

    try {
        /*
         * Idempotency: return the existing correction only when the same
         * token refers to the same requested target facts.
         */
        $existingStmt =
            $pdo->prepare(
                "SELECT *
                 FROM ruminant_participation_corrections
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
                    'animal_id'
                ] !== $animalId
                ||
                (int)$existing[
                    'membership_id'
                ] !== $membershipId
                ||
                (string)$existing[
                    'new_farm_entry_date'
                ] !== $newFarmEntryDate
                ||
                (string)$existing[
                    'new_membership_start_date'
                ] !== $newMembershipStartDate
            ) {
                throw new RuminantParticipationCorrectionException(
                    'This participation-correction request token has already been used for different facts.'
                );
            }

            $pdo->commit();

            return [
                'correction_id' =>
                    (int)$existing[
                        'id'
                    ],

                'idempotent_replay' =>
                    true,

                'old_farm_entry_date' =>
                    (string)$existing[
                        'old_farm_entry_date'
                    ],

                'new_farm_entry_date' =>
                    (string)$existing[
                        'new_farm_entry_date'
                    ],

                'old_membership_start_date' =>
                    (string)$existing[
                        'old_membership_start_date'
                    ],

                'new_membership_start_date' =>
                    (string)$existing[
                        'new_membership_start_date'
                    ],
            ];
        }

        $preview =
            ruminant_participation_correction_preview_locked(
                $pdo,
                $farmId,
                $animalId,
                $membershipId,
                $newFarmEntryDate,
                $newMembershipStartDate,
                $reason
            );

        if (
            empty(
                $preview[
                    'changes_farm_entry'
                ]
            )
            &&
            empty(
                $preview[
                    'changes_membership_start'
                ]
            )
        ) {
            throw new RuminantParticipationCorrectionException(
                'The proposed participation correction does not change any historical fact.'
            );
        }

        /*
         * Immutable provenance FIRST inside the same transaction.
         */
        $insert =
            $pdo->prepare(
                "INSERT INTO
                     ruminant_participation_corrections
                 (
                     farm_id,
                     animal_id,
                     membership_id,
                     cycle_id,
                     old_farm_entry_date,
                     new_farm_entry_date,
                     old_membership_start_date,
                     new_membership_start_date,
                     reason,
                     request_token,
                     created_by
                 )
                 VALUES
                 (
                     ?,?,?,?,?,?,?,?,?,?,?
                 )"
            );

        $insert->execute([
            $farmId,
            $animalId,
            $membershipId,
            $preview[
                'cycle_id'
            ],
            $preview[
                'old_farm_entry_date'
            ],
            $preview[
                'new_farm_entry_date'
            ],
            $preview[
                'old_membership_start_date'
            ],
            $preview[
                'new_membership_start_date'
            ],
            $preview[
                'reason'
            ],
            $requestToken,
            $createdBy,
        ]);

        $correctionId =
            (int)$pdo->lastInsertId();

        /*
         * Current canonical projection.
         *
         * purchase_date, birth_date and registry timestamps are deliberately
         * untouched.
         */
        if (
            !empty(
                $preview[
                    'changes_farm_entry'
                ]
            )
        ) {
            $animalUpdate =
                $pdo->prepare(
                    "UPDATE ruminant_animals
                     SET
                         farm_entry_date=?,
                         updated_at=NOW()
                     WHERE farm_id=?
                       AND id=?
                       AND farm_entry_date=?"
                );

            $animalUpdate->execute([
                $preview[
                    'new_farm_entry_date'
                ],
                $farmId,
                $animalId,
                $preview[
                    'old_farm_entry_date'
                ],
            ]);

            if (
                $animalUpdate->rowCount()
                !==
                1
            ) {
                throw new RuminantParticipationCorrectionException(
                    'Animal farm-entry history changed during correction. No correction was applied.'
                );
            }
        }

        if (
            !empty(
                $preview[
                    'changes_membership_start'
                ]
            )
        ) {
            $membershipUpdate =
                $pdo->prepare(
                    "UPDATE ruminant_animal_cycle_memberships
                     SET
                         start_date=?,
                         updated_at=NOW()
                     WHERE id=?
                       AND farm_id=?
                       AND animal_id=?
                       AND start_date=?
                       AND opened_by_transfer_id IS NULL"
                );

            $membershipUpdate->execute([
                $preview[
                    'new_membership_start_date'
                ],
                $membershipId,
                $farmId,
                $animalId,
                $preview[
                    'old_membership_start_date'
                ],
            ]);

            if (
                $membershipUpdate->rowCount()
                !==
                1
            ) {
                throw new RuminantParticipationCorrectionException(
                    'Cycle participation history changed during correction. No correction was applied.'
                );
            }
        }

        $pdo->commit();

        $preview[
            'correction_id'
        ] =
            $correctionId;

        $preview[
            'idempotent_replay'
        ] =
            false;

        return $preview;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}
}
