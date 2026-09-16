<?php

/**
 * V3.0.1 — Production-Entry approval serialization verifier.
 *
 * Static/pure only:
 * - no database connection;
 * - no database writes;
 * - no approval execution.
 */

$root = dirname(__DIR__);

$service =
    file_get_contents(
        $root
        . '/lib/poultry_production_entry_snapshots.php'
    );

$migration =
    file_get_contents(
        $root
        . '/migrations/031_poultry_production_entry_snapshots.sql'
    );

$failures = [];

$assert = static function (
    bool $condition,
    string $message
) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$approvalStart =
    strpos(
        $service,
        'function poultry_production_entry_approve('
    );

$approval =
    $approvalStart === false
        ? ''
        : substr(
            $service,
            $approvalStart
        );

$begin =
    strpos(
        $approval,
        '$pdo->beginTransaction();'
    );

$cycleLock =
    strpos(
        $approval,
        '$cycleLock=$pdo->prepare('
    );

$cycleForUpdate =
    strpos(
        $approval,
        'FROM production_cycles'
    );

$candidate =
    strpos(
        $approval,
        'poultry_production_entry_candidate('
    );

$latestLock =
    strpos(
        $approval,
        'ORDER BY version_no DESC LIMIT 1 FOR UPDATE'
    );

$comparison =
    strpos(
        $approval,
        'poultry_production_entry_snapshot_comparison('
    );

$insert =
    strpos(
        $approval,
        'INSERT INTO poultry_production_entry_snapshots'
    );

$commit =
    strpos(
        $approval,
        '$pdo->commit();'
    );

$rollback =
    strpos(
        $approval,
        '$pdo->rollBack();'
    );

$assert(
    $approvalStart !== false,
    'Approval function is missing.'
);

$assert(
    $begin !== false,
    'Approval transaction is missing.'
);

$assert(
    $cycleLock !== false
    && $cycleForUpdate !== false
    && strpos(
        $approval,
        'WHERE id=?'
    ) !== false
    && strpos(
        $approval,
        'AND farm_id=?'
    ) !== false,
    'Stable farm/cycle serialization row lock is missing.'
);

$assert(
    $cycleForUpdate !== false
    && strpos(
        substr(
            $approval,
            $cycleForUpdate,
            180
        ),
        'FOR UPDATE'
    ) !== false,
    'Production-cycle serialization query is not locking.'
);

$assert(
    $begin !== false
    && $cycleLock !== false
    && $candidate !== false
    && $latestLock !== false
    && $comparison !== false
    && $insert !== false
    && $commit !== false
    && (
        $begin
        < $cycleLock
    )
    && (
        $cycleLock
        < $candidate
    )
    && (
        $candidate
        < $latestLock
    )
    && (
        $latestLock
        < $comparison
    )
    && (
        $comparison
        < $insert
    )
    && (
        $insert
        < $commit
    ),
    'Approval serialization order is invalid.'
);

$beforeTransaction =
    $begin === false
        ? $approval
        : substr(
            $approval,
            0,
            $begin
        );

$assert(
    strpos(
        $beforeTransaction,
        'poultry_production_entry_candidate('
    ) === false,
    'Candidate is still calculated before the approval transaction.'
);

$assert(
    strpos(
        $approval,
        '$lockedCycleId === false'
    ) !== false,
    'Missing-cycle lock failure is not guarded.'
);

$assert(
    $rollback !== false,
    'Approval transaction rollback path is missing.'
);

$assert(
    substr_count(
        $approval,
        '$pdo->beginTransaction();'
    ) === 1,
    'Approval opens more than one transaction.'
);

$assert(
    strpos(
        $approval,
        'UPDATE poultry_production_entry_snapshots'
    ) === false,
    'Approved snapshots became mutable.'
);

$assert(
    strpos(
        $migration,
        'UNIQUE KEY uniq_poultry_entry_snapshot_version'
    ) !== false,
    'Unique farm/cycle/version defense is missing.'
);

$assert(
    strpos(
        $approval,
        'ORDER BY version_no DESC LIMIT 1 FOR UPDATE'
    ) !== false,
    'Latest-snapshot defense-in-depth lock is missing.'
);

$assert(
    strpos(
        $approval,
        'poultry_production_entry_snapshot_comparison('
    ) !== false,
    'Central provenance comparison contract is missing.'
);

if ($failures) {
    echo "RESULT=FAIL\n";

    foreach ($failures as $failure) {
        echo "FAIL={$failure}\n";
    }

    exit(1);
}

echo "RESULT=PASS\n";
echo "TRANSACTION_BEFORE_CANDIDATE=PASS\n";
echo "STABLE_CYCLE_MUTEX=PASS\n";
echo "FIRST_APPROVAL_SERIALIZED=PASS\n";
echo "CANDIDATE_AFTER_MUTEX=PASS\n";
echo "LATEST_SNAPSHOT_LOCK_RETAINED=PASS\n";
echo "PROVENANCE_COMPARISON_RETAINED=PASS\n";
echo "UNIQUE_VERSION_DEFENSE_RETAINED=PASS\n";
echo "APPEND_ONLY_SNAPSHOT_CONTRACT=PASS\n";
echo "ROLLBACK_PATH_RETAINED=PASS\n";
echo "DATABASE_CONNECTION_USED=NO\n";
echo "DATABASE_WRITE_PERFORMED=NO\n";
