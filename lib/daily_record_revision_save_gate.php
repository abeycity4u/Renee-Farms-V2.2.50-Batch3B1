<?php
declare(strict_types=1);

/**
 * B12 shared transactional CREATE/EDIT preflight — SOURCE ONLY, NOT WIRED.
 *
 * This is NOT a save route and performs NO mutation. It requires the future
 * three-table record_revision schema and a coordinated seven-writer/browser
 * cutover. Existing live routes must NOT call this independently.
 *
 * The initialized route owns login, CSRF, trusted current tenant, active
 * cycle/date business validation, and its PDO transaction. The supplied
 * authorization callback MUST enforce the module, view AND operation-level
 * capability for that session, including the existing privileged-role policy.
 * Never manufacture edit tokens from a POST-time database lookup.
 *
 * For EDIT, lock the exact tenant-owned source and compare the ORIGINAL
 * browser-loaded revision. After preflight, the caller must invoke the
 * canonical daily_record_revision_write() CAS before any downstream effects,
 * in this SAME transaction. A separate call to the CAS authority repeats its
 * comparison and is deliberate fail-closed defense.
 *
 * For CREATE, serialize the entire participating tenant CREATE scope before
 * INSERT. The returned 'locked_absent' is not a substitute for a reviewed DB
 * uniqueness/cycle lifecycle rule or permission to reclassify CREATE to EDIT.
 *
 * Failure must roll back the caller's transaction, leaving user input intact.
 */
require_once __DIR__ . '/daily_record_revision_capabilities.php';
require_once __DIR__ . '/daily_record_revision_reader.php';
require_once __DIR__ . '/daily_record_revision_create_guard.php';

if (!class_exists('DailyRecordRevisionPermissionDenied', false)) {
    final class DailyRecordRevisionPermissionDenied extends RuntimeException {}
}

if (!function_exists('daily_record_revision_save_contract')) {
    /**
     * No DB calls. Submitted fields are UNTRUSTED. The server's validated
     * scope is passed separately; farm_id must come from current tenant.
     *
     * @return array{type:string,mode:string,record_id:?int,expected_revision:?int,
     *               permission:string,scope:array}
     */
    function daily_record_revision_save_contract(
        string $type,
        int $farmId,
        ?int $cycleId,
        string $recordDate,
        ?string $animalType,
        array $tokens
    ): array {
        $type = strtolower(trim($type));
        if ($farmId < 1) {
            throw new InvalidArgumentException('Invalid current farm.');
        }
        $scope = daily_record_revision_reader_scope(
            $type, $farmId, $cycleId, $recordDate, $animalType
        );
        foreach ([
            'write_intent', 'record_id', 'expected_revision',
            'original_cycle_id', 'original_record_date', 'original_animal_type',
        ] as $field) {
            if (!array_key_exists($field, $tokens) || !is_string($tokens[$field])) {
                throw new DailyRecordRevisionConflict(
                    'The Daily Record form is incomplete. Reload before saving.'
                );
            }
        }
        $intent = daily_record_revision_parse_write_intent(
            $tokens['write_intent'],
            $tokens['record_id'],
            $tokens['expected_revision']
        );
        if ($intent['mode'] === 'create') {
            foreach (['original_cycle_id', 'original_record_date', 'original_animal_type'] as $field) {
                if ($tokens[$field] !== '') {
                    throw new DailyRecordRevisionConflict(
                        'CREATE cannot reuse a previous Daily Record identity.'
                    );
                }
            }
        } else {
            $originalCycle = $scope['cycle_id'] === null
                ? '' : (string)$scope['cycle_id'];
            if ($tokens['original_cycle_id'] !== $originalCycle ||
                $tokens['original_record_date'] !== $scope['record_date'] ||
                $tokens['original_animal_type'] !== ($scope['animal_type'] ?? '')) {
                throw new DailyRecordRevisionConflict(
                    'The Daily Record selection has changed. Reload before editing.'
                );
            }
        }
        return [
            'type' => $type,
            'mode' => $intent['mode'],
            'record_id' => $intent['record_id'],
            'expected_revision' => $intent['expected_revision'],
            'permission' => daily_record_revision_operation_permission($type, $intent['mode']),
            'scope' => $scope,
        ];
    }
}

if (!function_exists('daily_record_revision_prepare_save')) {
    /**
     * @param callable(string,string,string):bool $authorized
     *   Trusted server callback: (module, view_permission, operation_permission).
     *   Must check the authenticated actor's tenant/module/role/operation.
     *
     * @return array{contract:array,locked_row:?array,create_scope:?array}
     */
    function daily_record_revision_prepare_save(
        PDO $pdo,
        string $type,
        int $farmId,
        ?int $cycleId,
        string $recordDate,
        ?string $animalType,
        array $tokens,
        callable $authorized
    ): array {
        $contract = daily_record_revision_save_contract(
            $type, $farmId, $cycleId, $recordDate, $animalType, $tokens
        );
        $capabilities = daily_record_revision_capabilities($contract['type']);
        if ($authorized(
            $capabilities['module'],
            $capabilities['view'],
            $contract['permission']
        ) !== true) {
            throw new DailyRecordRevisionPermissionDenied(
                'You do not have permission to save this Daily Record.'
            );
        }
        daily_record_revision_require_transaction($pdo);

        if ($contract['mode'] === 'edit') {
            $row = daily_record_revision_lock_and_compare(
                $pdo, $contract['type'], $farmId,
                $contract['record_id'], $contract['scope']['cycle_id'],
                $contract['scope']['record_date'], $contract['scope']['animal_type'],
                $contract['expected_revision']
            );
            return ['contract' => $contract, 'locked_row' => $row, 'create_scope' => null];
        }
        $lockedScope = daily_record_revision_create_lock_scope(
            $pdo, $contract['type'], $farmId,
            $contract['scope']['cycle_id'], $contract['scope']['record_date'],
            $contract['scope']['animal_type']
        );
        return ['contract' => $contract, 'locked_row' => null, 'create_scope' => $lockedScope];
    }
}
