<?php
declare(strict_types=1);

/**
 * B12 staged source-only revision-aware Daily Record DELETE transaction.
 * NOT WIRED into an active path until the separately approved coordinated
 * three-table DDL + seven-writer + browser cutover. Do not deploy as a B12
 * certification or replace the existing trusted deletion implementations.
 *
 * Input tokens are untrusted CLIENT-LOADED immutable identity, never
 * manufactured from a POST-time SELECT. Caller must check login and CSRF.
 * farmId and actorId must come from trusted server session, not POST.
 *
 * Transaction lock / side-effect order intentionally matches the currently
 * deployed Poultry and delegated/privileged Ruminant delete paths:
 * source FOR UPDATE with original revision compare -> feed reversal ->
 * mortality reversal -> CAS DELETE -> Layer egg-sale allocation -> commit.
 * Any throwable rolls back all side effects and preserves the user draft.
 */
require_once __DIR__ . '/daily_record_revision_save_gate.php';
require_once __DIR__ . '/daily_record_revision_form_tokens.php';
require_once __DIR__ . '/daily_record_revision_delete_continuity.php';
require_once __DIR__ . '/daily_record_revision_parent_lock.php';

if (!function_exists('daily_record_revision_delete_contract')) {
    /**
     * @return array{record_id:int,expected_revision:int,cycle_id:?int,record_date:string,animal_type:?string}
     */
    function daily_record_revision_delete_contract(
        string $type, int $farmId, array $post
    ): array {
        $def = daily_record_revision_definition($type);
        if ($farmId <= 0) {
            throw new InvalidArgumentException('Invalid farm identity.');
        }
        $required = [
            'write_intent', 'record_id', 'expected_revision',
            'original_cycle_id', 'original_record_date', 'original_animal_type',
        ];
        foreach ($required as $key) {
            if (!array_key_exists($key, $post) || !is_string($post[$key])) {
                throw new DailyRecordRevisionConflict(
                    'Daily Record deletion identity is incomplete. Reload it.'
                );
            }
        }
        if ($post['write_intent'] !== 'delete') {
            throw new DailyRecordRevisionConflict(
                'Delete requires an originally loaded DELETE intent.'
            );
        }
        $id = daily_record_revision_parse_record_id($post['record_id']);
        // Poultry API still receives id; both supplied IDs must be identical.
        if (array_key_exists('id', $post)) {
            if (!is_string($post['id']) ||
                daily_record_revision_parse_record_id($post['id']) !== $id) {
                throw new DailyRecordRevisionConflict(
                    'The Daily Record delete identity changed. Reload it.'
                );
            }
        }
        $revision = daily_record_revision_parse_expected($post['expected_revision']);
        $cycleId = $post['original_cycle_id'] === ''
            ? null : daily_record_revision_parse_record_id($post['original_cycle_id']);
        try {
            $species = daily_record_revision_validate_scope(
                $farmId, $id, $cycleId, $post['original_record_date'],
                (bool)$def['animal_type'], $post['original_animal_type']
            );
        } catch (InvalidArgumentException $error) {
            throw new DailyRecordRevisionConflict(
                'The Daily Record delete scope is invalid. Reload it.', 0, $error
            );
        }
        if ($def['animal_type'] && !in_array(
            $species, ['cattle','goat','sheep','other'], true
        )) {
            throw new DailyRecordRevisionConflict(
                'Unsupported Ruminant Daily Record species.'
            );
        }
        return [
            'record_id' => $id,
            'expected_revision' => $revision,
            'cycle_id' => $cycleId,
            'record_date' => $post['original_record_date'],
            'animal_type' => $species,
        ];
    }
}

if (!function_exists('daily_record_revision_delete_form_fields')) {
    /**
     * Produces DELETE tokens only from a tenant-owned, durable, originally
     * loaded row that has an authoritative revision. Display-only activity
     * placeholders and absent revision columns fail closed.
     */
    function daily_record_revision_delete_form_fields(
        string $type, int $farmId, array $loadedRow
    ): array {
        $fields = daily_record_revision_form_fields($type, $farmId, $loadedRow);
        if (($fields['write_intent'] ?? null) !== 'edit') {
            throw new DailyRecordRevisionConflict('Invalid DELETE source identity.');
        }
        $fields['write_intent'] = 'delete';
        return $fields;
    }
}

if (!function_exists('daily_record_revision_delete_transaction')) {
    /**
     * @param callable(string,string,string):bool $authorized
     *   Trusted callback must authorize the current actor's module + view +
     *   DELETE capability. Previous page-only role checks are insufficient.
     * @return array{record_id:int,record_date:string}
     */
    function daily_record_revision_delete_transaction(
        PDO $pdo,
        string $type,
        int $farmId,
        int $actorId,
        array $post,
        callable $authorized
    ): array {
        $type = strtolower(trim($type));
        $tokens = daily_record_revision_delete_contract($type, $farmId, $post);
        if ($actorId <= 0) {
            throw new InvalidArgumentException('Invalid authenticated actor.');
        }
        $permission = daily_record_revision_capabilities($type);
        if ($authorized(
            $permission['module'], $permission['view'], $permission['delete']
        ) !== true) {
            throw new DailyRecordRevisionPermissionDenied('DELETE capability is unavailable.');
        }
        if ($pdo->inTransaction()) {
            throw new LogicException('Daily Record DELETE owns its transaction.');
        }
        if (!function_exists('delete_daily_feed_usage') ||
            !function_exists('daily_population_remove_mortality') ||
            ($type === 'layer' && !function_exists('sales_rebuild_layer_egg_allocations'))) {
            throw new LogicException('Canonical Daily Record deletion services unavailable.');
        }
        if (!$pdo->beginTransaction()) {
            throw new RuntimeException('Daily Record DELETE transaction unavailable.');
        }
        try {
            // Match CREATE/EDIT: lock tenant and selected cycle before
            // taking the exact source-row CAS lock or any downstream locks.
            daily_record_revision_lock_parents(
                $pdo, $farmId, $tokens['cycle_id']
            );
            // First source-row read: exact farm-owned row, immutable scope,
            // and the browser-loaded expected revision under FOR UPDATE.
            daily_record_revision_lock_and_compare(
                $pdo, $type, $farmId,
                $tokens['record_id'], $tokens['cycle_id'],
                $tokens['record_date'], $tokens['animal_type'],
                $tokens['expected_revision']
            );
            $continuityPlan = daily_record_revision_delete_continuity_lock(
                $pdo, $type, $farmId, $tokens['record_id'],
                $tokens['cycle_id'], $tokens['record_date'], $tokens['animal_type']
            );
            $sourceType = match ($type) {
                'layer' => 'daily_layer_record',
                'broiler' => 'daily_broiler_record',
                'ruminant' => 'daily_ruminant_record',
            };
            delete_daily_feed_usage(
                $pdo, $farmId, $tokens['record_id'], $sourceType
            );
            daily_population_remove_mortality(
                $pdo, $farmId, $sourceType,
                $tokens['record_id'], $actorId
            );
            // Rebase later canonical Daily Records while source and later-row
            // locks remain held, after mortality reversal and before CAS DELETE.
            daily_record_revision_delete_continuity_apply($pdo, $continuityPlan);
            daily_record_revision_delete_locked(
                $pdo, $type, $farmId,
                $tokens['record_id'], $tokens['cycle_id'],
                $tokens['record_date'], $tokens['animal_type'],
                $tokens['expected_revision']
            );
            if ($type === 'layer') {
                sales_rebuild_layer_egg_allocations(
                    $pdo, $farmId, $tokens['record_date'], $actorId
                );
            }
            if (!$pdo->commit()) {
                throw new RuntimeException('Daily Record DELETE commit failed.');
            }
            return [
                'record_id' => $tokens['record_id'],
                'record_date' => $tokens['record_date'],
            ];
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }
}
