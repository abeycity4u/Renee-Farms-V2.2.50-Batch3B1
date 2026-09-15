<?php
/**
 * Renee Farms V3.0 — tagged ruminant production-cycle transfer service.
 *
 * A tagged animal remains Active when it moves between production cycles.
 *
 * Population ownership:
 * - this service never writes canonical population tables directly;
 * - one tagged animal always delegates quantity 1 to the paired production
 *   population transfer service;
 * - source and destination population movements therefore remain atomic.
 *
 * Membership ownership:
 * - transfer date is the destination membership start date;
 * - source membership ends on the previous calendar day;
 * - inclusive membership ranges therefore never overlap;
 * - source prior end date is preserved for a safe reversal;
 * - transfer-created/closed membership boundaries cannot be manually rewritten.
 */

require_once __DIR__
    . '/production_population_transfer.php';

require_once __DIR__
    . '/ruminant_cycle_membership.php';

if (!class_exists('RuminantCycleTransferException')) {
    class RuminantCycleTransferException extends RuntimeException {}
}

if (!function_exists('ruminant_cycle_transfer_previous_date')) {
    function ruminant_cycle_transfer_previous_date(
        string $transferDate
    ): string {
        if (!production_population_valid_date($transferDate)) {
            throw new InvalidArgumentException(
                'Enter a valid animal transfer date.'
            );
        }

        return (new DateTimeImmutable($transferDate))
            ->modify('-1 day')
            ->format('Y-m-d');
    }
}

if (!function_exists('ruminant_cycle_transfer_animal')) {
    function ruminant_cycle_transfer_animal(
        PDO $pdo,
        int $farmId,
        int $animalId,
        bool $forUpdate = false
    ): array {
        if ($farmId <= 0 || $animalId <= 0) {
            throw new InvalidArgumentException(
                'Select a valid animal.'
            );
        }

        $sql =
            'SELECT
                 id,
                 farm_id,
                 tag_no,
                 species,
                 status,
                 purchase_date,
                 birth_date
             FROM ruminant_animals
             WHERE id = ?
               AND farm_id = ?
             LIMIT 1';

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $animalId,
            $farmId,
        ]);

        $animal = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$animal) {
            throw new RuminantCycleTransferException(
                'Animal not found.'
            );
        }

        if (
            strtolower((string)$animal['status'])
            !== 'active'
        ) {
            throw new RuminantCycleTransferException(
                (string)$animal['tag_no']
                . ' is not Active and cannot be moved '
                . 'between production cycles.'
            );
        }

        return $animal;
    }
}

if (!function_exists(
    'ruminant_cycle_transfer_source_membership'
)) {
    function ruminant_cycle_transfer_source_membership(
        PDO $pdo,
        int $farmId,
        int $animalId,
        string $transferDate,
        bool $forUpdate = false
    ): array {
        if (
            $farmId <= 0
            || $animalId <= 0
            || !production_population_valid_date(
                $transferDate
            )
        ) {
            throw new InvalidArgumentException(
                'Select a valid animal and transfer date.'
            );
        }

        $sql =
            'SELECT
                 id,
                 farm_id,
                 animal_id,
                 cycle_id,
                 start_date,
                 end_date,
                 closed_by_exit_event_id,
                 pre_exit_end_date,
                 opened_by_transfer_id,
                 closed_by_transfer_id,
                 pre_transfer_end_date,
                 notes
             FROM ruminant_animal_cycle_memberships
             WHERE farm_id = ?
               AND animal_id = ?
               AND start_date <= ?
               AND (
                   end_date IS NULL
                   OR end_date >= ?
               )
             ORDER BY start_date DESC, id DESC
             LIMIT 2';

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $farmId,
            $animalId,
            $transferDate,
            $transferDate,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) > 1) {
            throw new RuminantCycleTransferException(
                'Animal transfer cannot continue because '
                . 'overlapping production-cycle memberships '
                . 'exist on the transfer date.'
            );
        }

        if (!$rows) {
            throw new RuminantCycleTransferException(
                'The animal has no production-cycle '
                . 'membership covering the selected '
                . 'transfer date.'
            );
        }

        $membership = $rows[0];

        /*
         * Inclusive ranges mean ending the source on the
         * previous day would become invalid when transfer
         * date equals membership start date.
         *
         * That situation is an assignment correction,
         * not a production-cycle transfer.
         */
        if (
            (string)$membership['start_date']
            >= $transferDate
        ) {
            throw new RuminantCycleTransferException(
                'The animal cannot be transferred on the '
                . 'first day of its current membership. '
                . 'Correct the membership assignment instead.'
            );
        }

        if (
            !empty(
                $membership['closed_by_exit_event_id']
            )
        ) {
            throw new RuminantCycleTransferException(
                'This membership is controlled by a '
                . 'lifecycle exit and cannot be transferred.'
            );
        }

        if (
            !empty(
                $membership['closed_by_transfer_id']
            )
        ) {
            throw new RuminantCycleTransferException(
                'This membership is already closed by '
                . 'another production-cycle transfer.'
            );
        }

        return $membership;
    }
}

if (!function_exists('ruminant_cycle_transfer_membership')) {
    function ruminant_cycle_transfer_membership(
        PDO $pdo,
        int $farmId,
        int $animalId,
        int $membershipId,
        bool $forUpdate = false
    ): array {
        if (
            $farmId <= 0
            || $animalId <= 0
            || $membershipId <= 0
        ) {
            throw new InvalidArgumentException(
                'Select a valid animal cycle membership.'
            );
        }

        $sql =
            'SELECT *
             FROM ruminant_animal_cycle_memberships
             WHERE id = ?
               AND farm_id = ?
               AND animal_id = ?
             LIMIT 1';

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $membershipId,
            $farmId,
            $animalId,
        ]);

        $membership =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$membership) {
            throw new RuminantCycleTransferException(
                'Animal transfer membership history '
                . 'is incomplete.'
            );
        }

        return $membership;
    }
}

if (!function_exists(
    'ruminant_cycle_transfer_load_by_token'
)) {
    function ruminant_cycle_transfer_load_by_token(
        PDO $pdo,
        int $farmId,
        string $requestToken,
        bool $forUpdate = false
    ): ?array {
        $sql =
            'SELECT
                 rat.id,
                 rat.farm_id,
                 rat.animal_id,
                 rat.population_transfer_id,
                 rat.from_membership_id,
                 rat.to_membership_id,
                 rat.source_previous_end_date,
                 rat.created_by,
                 rat.created_at,
                 rat.reversed_at,
                 rat.reversed_by,
                 rat.reversal_reason,

                 pt.from_cycle_id,
                 pt.to_cycle_id,
                 pt.transfer_date,
                 pt.quantity,
                 pt.notes AS transfer_notes,
                 pt.request_token,
                 pt.reversed_at
                     AS population_reversed_at

             FROM ruminant_animal_cycle_transfers rat

             INNER JOIN production_population_transfers pt
               ON pt.id = rat.population_transfer_id
              AND pt.farm_id = rat.farm_id

             WHERE rat.farm_id = ?
               AND pt.request_token = ?
             LIMIT 1';

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $farmId,
            $requestToken,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

if (!function_exists('ruminant_cycle_transfer_load')) {
    function ruminant_cycle_transfer_load(
        PDO $pdo,
        int $farmId,
        int $ruminantTransferId,
        bool $forUpdate = false
    ): array {
        if (
            $farmId <= 0
            || $ruminantTransferId <= 0
        ) {
            throw new InvalidArgumentException(
                'Select a valid tagged-animal transfer.'
            );
        }

        $sql =
            'SELECT
                 rat.id,
                 rat.farm_id,
                 rat.animal_id,
                 rat.population_transfer_id,
                 rat.from_membership_id,
                 rat.to_membership_id,
                 rat.source_previous_end_date,
                 rat.created_by,
                 rat.created_at,
                 rat.reversed_at,
                 rat.reversed_by,
                 rat.reversal_reason,

                 pt.from_cycle_id,
                 pt.to_cycle_id,
                 pt.transfer_date,
                 pt.quantity,
                 pt.notes AS transfer_notes,
                 pt.request_token,
                 pt.reversed_at
                     AS population_reversed_at

             FROM ruminant_animal_cycle_transfers rat

             INNER JOIN production_population_transfers pt
               ON pt.id = rat.population_transfer_id
              AND pt.farm_id = rat.farm_id

             WHERE rat.id = ?
               AND rat.farm_id = ?
             LIMIT 1';

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $ruminantTransferId,
            $farmId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new RuminantCycleTransferException(
                'The selected tagged-animal transfer '
                . 'was not found in this farm.'
            );
        }

        return $row;
    }
}

if (!function_exists('ruminant_cycle_transfer_snapshot')) {
    function ruminant_cycle_transfer_snapshot(
        array $row
    ): array {
        $domainReversed =
            !empty($row['reversed_at']);

        $populationReversed =
            !empty($row['population_reversed_at']);

        if ($domainReversed !== $populationReversed) {
            throw new RuminantCycleTransferException(
                'Tagged-animal transfer integrity failed: '
                . 'membership and population reversal '
                . 'states disagree.'
            );
        }

        if ((int)$row['quantity'] !== 1) {
            throw new RuminantCycleTransferException(
                'Tagged-animal transfer integrity failed: '
                . 'one tagged animal must equal one head.'
            );
        }

        return [
            'ruminant_transfer_id' =>
                (int)$row['id'],

            'population_transfer_id' =>
                (int)$row['population_transfer_id'],

            'farm_id' =>
                (int)$row['farm_id'],

            'animal_id' =>
                (int)$row['animal_id'],

            'from_cycle_id' =>
                (int)$row['from_cycle_id'],

            'to_cycle_id' =>
                (int)$row['to_cycle_id'],

            'from_membership_id' =>
                (int)$row['from_membership_id'],

            'to_membership_id' =>
                (int)$row['to_membership_id'],

            'transfer_date' =>
                (string)$row['transfer_date'],

            'quantity' =>
                (int)$row['quantity'],

            'status' =>
                $domainReversed
                    ? 'reversed'
                    : 'active',

            'reversed_at' =>
                $row['reversed_at'],

            'reversal_reason' =>
                $row['reversal_reason'],
        ];
    }
}

if (!function_exists(
    'ruminant_cycle_transfer_same_submission'
)) {
    function ruminant_cycle_transfer_same_submission(
        array $existing,
        int $animalId,
        int $toCycleId,
        string $transferDate,
        ?string $notes
    ): bool {
        $existingNotes =
            production_population_normalize_note(
                isset($existing['transfer_notes'])
                    ? (string)$existing['transfer_notes']
                    : null
            );

        return
            (int)$existing['animal_id']
                === $animalId
            && (int)$existing['to_cycle_id']
                === $toCycleId
            && (string)$existing['transfer_date']
                === $transferDate
            && (int)$existing['quantity'] === 1
            && $existingNotes === $notes;
    }
}

if (!function_exists('ruminant_cycle_transfer_record')) {
    /**
     * Move one tagged Active animal between two V3-tracked
     * same-farm production cycles.
     */
    function ruminant_cycle_transfer_record(
        PDO $pdo,
        int $farmId,
        int $animalId,
        int $toCycleId,
        string $transferDate,
        ?string $notes,
        ?int $userId,
        string $requestToken
    ): array {
        production_population_assert_user_id($userId);

        if (
            $farmId <= 0
            || $animalId <= 0
            || $toCycleId <= 0
        ) {
            throw new InvalidArgumentException(
                'Select a valid animal and destination '
                . 'production cycle.'
            );
        }

        if (
            !production_population_valid_date(
                $transferDate
            )
        ) {
            throw new InvalidArgumentException(
                'Enter a valid animal transfer date.'
            );
        }

        $notes =
            production_population_normalize_note(
                $notes
            );

        $requestToken =
            production_population_transfer_request_token(
                $requestToken
            );

        $sourceEndDate =
            ruminant_cycle_transfer_previous_date(
                $transferDate
            );

        $startedTransaction =
            !$pdo->inTransaction();

        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        try {
            /*
             * Idempotency must be checked before re-deriving
             * the source membership. After a successful transfer
             * the animal legitimately belongs to the destination.
             */
            $existing =
                ruminant_cycle_transfer_load_by_token(
                    $pdo,
                    $farmId,
                    $requestToken,
                    true
                );

            if ($existing !== null) {
                if (
                    !ruminant_cycle_transfer_same_submission(
                        $existing,
                        $animalId,
                        $toCycleId,
                        $transferDate,
                        $notes
                    )
                ) {
                    throw new RuminantCycleTransferException(
                        'This animal-transfer submission '
                        . 'token already belongs to a '
                        . 'different transfer.'
                    );
                }

                $snapshot =
                    ruminant_cycle_transfer_snapshot(
                        $existing
                    );

                if ($startedTransaction) {
                    $pdo->commit();
                }

                return $snapshot;
            }

            /*
             * Never adopt an unrelated generic population
             * transfer merely because a caller reused its token.
             */
            $genericParent =
                production_population_transfer_load_by_token(
                    $pdo,
                    $farmId,
                    $requestToken,
                    true
                );

            if ($genericParent !== null) {
                throw new RuminantCycleTransferException(
                    'This submission token already belongs '
                    . 'to another production transfer.'
                );
            }

            /*
             * Preflight identity without taking animal locks.
             * The canonical population service owns the cycle /
             * baseline lock order. We re-lock and re-prove
             * animal membership after canonical population is
             * recorded, inside this same transaction.
             */
            $animalPreflight =
                ruminant_cycle_transfer_animal(
                    $pdo,
                    $farmId,
                    $animalId,
                    false
                );

            $sourcePreflight =
                ruminant_cycle_transfer_source_membership(
                    $pdo,
                    $farmId,
                    $animalId,
                    $transferDate,
                    false
                );

            $fromCycleId =
                (int)$sourcePreflight['cycle_id'];

            if ($fromCycleId === $toCycleId) {
                throw new RuminantCycleTransferException(
                    'Choose a different destination '
                    . 'production cycle.'
                );
            }

            /*
             * Canonical paired service validates:
             * - both cycles active;
             * - both cycles V3-baselined;
             * - same farm;
             * - same production identity;
             * - valid transfer date;
             * - safe source population balance.
             *
             * One tagged animal = quantity 1.
             */
            $populationTransfer =
                production_population_transfer_record(
                    $pdo,
                    $farmId,
                    $fromCycleId,
                    $toCycleId,
                    $transferDate,
                    1,
                    $notes,
                    $userId,
                    $requestToken
                );

            $populationTransferId =
                (int)$populationTransfer[
                    'transfer_id'
                ];

            /*
             * Canonical cycle / baseline locks now exist.
             * Re-lock tagged identity and membership and prove
             * they still match the preflight read.
             */
            $animal =
                ruminant_cycle_transfer_animal(
                    $pdo,
                    $farmId,
                    $animalId,
                    true
                );

            $sourceMembership =
                ruminant_cycle_transfer_source_membership(
                    $pdo,
                    $farmId,
                    $animalId,
                    $transferDate,
                    true
                );

            if (
                (int)$sourceMembership['id']
                    !== (int)$sourcePreflight['id']
                || (int)$sourceMembership['cycle_id']
                    !== $fromCycleId
            ) {
                throw new RuminantCycleTransferException(
                    'The animal cycle membership changed '
                    . 'while the transfer was being recorded. '
                    . 'Refresh and try again.'
                );
            }

            if (
                strtolower((string)$animal['species'])
                !== strtolower(
                    (string)$animalPreflight['species']
                )
            ) {
                throw new RuminantCycleTransferException(
                    'The animal species changed while the '
                    . 'transfer was being recorded. '
                    . 'Refresh and try again.'
                );
            }

            if (
                $sourceEndDate
                < (string)$sourceMembership['start_date']
            ) {
                throw new RuminantCycleTransferException(
                    'The transfer date would end the source '
                    . 'membership before it began.'
                );
            }

            $sourcePreviousEndDate =
                $sourceMembership['end_date'] !== null
                    ? (string)$sourceMembership[
                        'end_date'
                    ]
                    : null;

            $closeSource = $pdo->prepare(
                'UPDATE ruminant_animal_cycle_memberships
                 SET
                     pre_transfer_end_date = end_date,
                     end_date = ?,
                     closed_by_transfer_id = ?,
                     updated_at = NOW()
                 WHERE id = ?
                   AND farm_id = ?
                   AND animal_id = ?
                   AND closed_by_exit_event_id IS NULL
                   AND closed_by_transfer_id IS NULL
                   AND start_date < ?
                   AND (
                       end_date IS NULL
                       OR end_date >= ?
                   )'
            );

            $closeSource->execute([
                $sourceEndDate,
                $populationTransferId,
                (int)$sourceMembership['id'],
                $farmId,
                $animalId,
                $transferDate,
                $transferDate,
            ]);

            if ($closeSource->rowCount() !== 1) {
                throw new RuminantCycleTransferException(
                    'The source membership could not be '
                    . 'closed for this transfer.'
                );
            }

            /*
             * Reuse the central membership service for:
             * - animal/cycle tenant scope;
             * - species compatibility;
             * - animal economic-entry date;
             * - cycle date boundaries;
             * - overlap protection.
             */
            $destinationMembershipId =
                ruminant_cycle_membership_add(
                    $pdo,
                    $farmId,
                    $animalId,
                    $toCycleId,
                    $transferDate,
                    null,
                    'Opened by tagged-animal '
                        . 'production transfer #'
                        . $populationTransferId
                        . '.',
                    $userId
                );

            $markDestination = $pdo->prepare(
                'UPDATE ruminant_animal_cycle_memberships
                 SET
                     opened_by_transfer_id = ?,
                     updated_at = NOW()
                 WHERE id = ?
                   AND farm_id = ?
                   AND animal_id = ?
                   AND opened_by_transfer_id IS NULL
                   AND closed_by_transfer_id IS NULL
                   AND closed_by_exit_event_id IS NULL'
            );

            $markDestination->execute([
                $populationTransferId,
                $destinationMembershipId,
                $farmId,
                $animalId,
            ]);

            if (
                $markDestination->rowCount() !== 1
            ) {
                throw new RuminantCycleTransferException(
                    'The destination membership could not '
                    . 'be linked to its production transfer.'
                );
            }

            $domainInsert = $pdo->prepare(
                'INSERT INTO ruminant_animal_cycle_transfers
                 (
                     farm_id,
                     animal_id,
                     population_transfer_id,
                     from_membership_id,
                     to_membership_id,
                     source_previous_end_date,
                     created_by
                 )
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );

            $domainInsert->execute([
                $farmId,
                $animalId,
                $populationTransferId,
                (int)$sourceMembership['id'],
                $destinationMembershipId,
                $sourcePreviousEndDate,
                $userId,
            ]);

            $ruminantTransferId =
                (int)$pdo->lastInsertId();

            if (
                function_exists(
                    'audit_log_event'
                )
            ) {
                audit_log_event(
                    'ruminant_cycle_transfer_recorded',
                    'ruminant_animal_cycle_transfer',
                    $ruminantTransferId,
                    [
                        'animal_id' =>
                            $animalId,

                        'tag_no' =>
                            (string)$animal['tag_no'],

                        'population_transfer_id' =>
                            $populationTransferId,

                        'from_cycle_id' =>
                            $fromCycleId,

                        'to_cycle_id' =>
                            $toCycleId,

                        'transfer_date' =>
                            $transferDate,

                        'from_membership_id' =>
                            (int)$sourceMembership['id'],

                        'to_membership_id' =>
                            $destinationMembershipId,
                    ]
                );
            }

            $final =
                ruminant_cycle_transfer_load(
                    $pdo,
                    $farmId,
                    $ruminantTransferId,
                    true
                );

            $snapshot =
                ruminant_cycle_transfer_snapshot(
                    $final
                );

            if ($startedTransaction) {
                $pdo->commit();
            }

            return $snapshot;

        } catch (Throwable $error) {
            if (
                $startedTransaction
                && $pdo->inTransaction()
            ) {
                $pdo->rollBack();
            }

            throw $error;
        }
    }
}

if (!function_exists('ruminant_cycle_transfer_reverse')) {
    /**
     * Reverse one tagged-animal cycle transfer.
     *
     * Canonical population reversal runs first inside the
     * caller-owned transaction. If membership restoration
     * later fails, that population reversal is rolled back.
     *
     * Reversal is intentionally conservative:
     * - animal must still be Active;
     * - destination membership must still be open;
     * - no later exit / transfer boundary may exist;
     * - destination membership must have no financial
     *   activity under the existing membership contract.
     */
    function ruminant_cycle_transfer_reverse(
        PDO $pdo,
        int $farmId,
        int $ruminantTransferId,
        string $reason,
        ?int $userId
    ): array {
        production_population_assert_user_id($userId);

        $reason = trim($reason);

        if (
            $reason === ''
            || production_population_text_length(
                $reason
            ) < 4
        ) {
            throw new InvalidArgumentException(
                'Enter a short reason for reversing '
                . 'this animal transfer.'
            );
        }

        if (
            production_population_text_length(
                $reason
            ) > 255
        ) {
            throw new InvalidArgumentException(
                'Animal transfer reversal reason must '
                . 'be 255 characters or fewer.'
            );
        }

        $startedTransaction =
            !$pdo->inTransaction();

        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $preflight =
                ruminant_cycle_transfer_load(
                    $pdo,
                    $farmId,
                    $ruminantTransferId,
                    false
                );

            /*
             * Also proves tagged-domain and canonical
             * population reversal states agree.
             */
            $preflightSnapshot =
                ruminant_cycle_transfer_snapshot(
                    $preflight
                );

            if (
                $preflightSnapshot['status']
                === 'reversed'
            ) {
                $preflightSnapshot[
                    'already_reversed'
                ] = true;

                if ($startedTransaction) {
                    $pdo->commit();
                }

                return $preflightSnapshot;
            }

            /*
             * Shared paired reversal:
             * destination IN reversal first,
             * then source OUT restoration.
             *
             * Because this transaction already exists,
             * the canonical service will not commit
             * independently.
             */
            $populationReversal =
                production_population_transfer_reverse(
                    $pdo,
                    $farmId,
                    (int)$preflight[
                        'population_transfer_id'
                    ],
                    $reason,
                    $userId
                );

            $transfer =
                ruminant_cycle_transfer_load(
                    $pdo,
                    $farmId,
                    $ruminantTransferId,
                    true
                );

            $animal =
                ruminant_cycle_transfer_animal(
                    $pdo,
                    $farmId,
                    (int)$transfer['animal_id'],
                    true
                );

            $source =
                ruminant_cycle_transfer_membership(
                    $pdo,
                    $farmId,
                    (int)$transfer['animal_id'],
                    (int)$transfer[
                        'from_membership_id'
                    ],
                    true
                );

            $destination =
                ruminant_cycle_transfer_membership(
                    $pdo,
                    $farmId,
                    (int)$transfer['animal_id'],
                    (int)$transfer[
                        'to_membership_id'
                    ],
                    true
                );

            $populationTransferId =
                (int)$transfer[
                    'population_transfer_id'
                ];

            $expectedSourceEnd =
                ruminant_cycle_transfer_previous_date(
                    (string)$transfer[
                        'transfer_date'
                    ]
                );

            if (
                (int)(
                    $source[
                        'closed_by_transfer_id'
                    ] ?? 0
                ) !== $populationTransferId
                || (string)$source['end_date']
                    !== $expectedSourceEnd
                || (
                    $source[
                        'pre_transfer_end_date'
                    ] ?? null
                ) !== (
                    $transfer[
                        'source_previous_end_date'
                    ] ?? null
                )
            ) {
                throw new RuminantCycleTransferException(
                    'The source membership changed after '
                    . 'this transfer and cannot be '
                    . 'restored automatically.'
                );
            }

            if (
                (int)(
                    $destination[
                        'opened_by_transfer_id'
                    ] ?? 0
                ) !== $populationTransferId
                || (string)$destination['start_date']
                    !== (string)$transfer[
                        'transfer_date'
                    ]
                || $destination['end_date'] !== null
                || !empty(
                    $destination[
                        'closed_by_exit_event_id'
                    ]
                )
                || !empty(
                    $destination[
                        'closed_by_transfer_id'
                    ]
                )
            ) {
                throw new RuminantCycleTransferException(
                    'The destination membership has later '
                    . 'history and this transfer must not '
                    . 'be reversed automatically.'
                );
            }

            if (
                ruminant_cycle_membership_has_financial_activity(
                    $pdo,
                    $farmId,
                    (int)$transfer['animal_id'],
                    (int)$destination['id']
                )
            ) {
                throw new RuminantCycleTransferException(
                    'This transfer cannot be reversed '
                    . 'because the destination membership '
                    . 'has financial activity in its '
                    . 'date range.'
                );
            }

            $deleteDestination = $pdo->prepare(
                'DELETE FROM
                     ruminant_animal_cycle_memberships
                 WHERE id = ?
                   AND farm_id = ?
                   AND animal_id = ?
                   AND opened_by_transfer_id = ?
                   AND end_date IS NULL
                   AND closed_by_exit_event_id IS NULL
                   AND closed_by_transfer_id IS NULL'
            );

            $deleteDestination->execute([
                (int)$destination['id'],
                $farmId,
                (int)$transfer['animal_id'],
                $populationTransferId,
            ]);

            if (
                $deleteDestination->rowCount() !== 1
            ) {
                throw new RuminantCycleTransferException(
                    'The destination membership could not '
                    . 'be removed during transfer reversal.'
                );
            }

            $restoreSource = $pdo->prepare(
                'UPDATE ruminant_animal_cycle_memberships
                 SET
                     end_date = pre_transfer_end_date,
                     closed_by_transfer_id = NULL,
                     pre_transfer_end_date = NULL,
                     updated_at = NOW()
                 WHERE id = ?
                   AND farm_id = ?
                   AND animal_id = ?
                   AND closed_by_transfer_id = ?'
            );

            $restoreSource->execute([
                (int)$source['id'],
                $farmId,
                (int)$transfer['animal_id'],
                $populationTransferId,
            ]);

            if (
                $restoreSource->rowCount() !== 1
            ) {
                throw new RuminantCycleTransferException(
                    'The source membership could not be '
                    . 'restored during transfer reversal.'
                );
            }

            $updateTransfer = $pdo->prepare(
                'UPDATE ruminant_animal_cycle_transfers
                 SET
                     reversed_at = NOW(),
                     reversed_by = ?,
                     reversal_reason = ?
                 WHERE id = ?
                   AND farm_id = ?
                   AND reversed_at IS NULL'
            );

            $updateTransfer->execute([
                $userId,
                $reason,
                $ruminantTransferId,
                $farmId,
            ]);

            if (
                $updateTransfer->rowCount() !== 1
            ) {
                throw new RuminantCycleTransferException(
                    'The tagged-animal transfer reversal '
                    . 'could not be finalized.'
                );
            }

            if (
                function_exists(
                    'audit_log_event'
                )
            ) {
                audit_log_event(
                    'ruminant_cycle_transfer_reversed',
                    'ruminant_animal_cycle_transfer',
                    $ruminantTransferId,
                    [
                        'animal_id' =>
                            (int)$transfer['animal_id'],

                        'tag_no' =>
                            (string)$animal['tag_no'],

                        'population_transfer_id' =>
                            $populationTransferId,

                        'reason' =>
                            $reason,

                        'population_already_reversed' =>
                            !empty(
                                $populationReversal[
                                    'already_reversed'
                                ]
                            ),
                    ]
                );
            }

            $final =
                ruminant_cycle_transfer_load(
                    $pdo,
                    $farmId,
                    $ruminantTransferId,
                    true
                );

            $snapshot =
                ruminant_cycle_transfer_snapshot(
                    $final
                );

            $snapshot['already_reversed'] = false;

            if ($startedTransaction) {
                $pdo->commit();
            }

            return $snapshot;

        } catch (Throwable $error) {
            if (
                $startedTransaction
                && $pdo->inTransaction()
            ) {
                $pdo->rollBack();
            }

            throw $error;
        }
    }
}
