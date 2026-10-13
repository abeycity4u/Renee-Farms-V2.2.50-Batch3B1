<?php
declare(strict_types=1);

/**
 * B12 staged Daily Record save-route adapter, NOT ACTIVATED.
 *
 * Called only from the hard-disabled revision cutover branch inside an
 * existing caller-owned transaction. The route already authenticated the
 * user, verified CSRF, selected a tenant-owned cycle, and validated business
 * numbers. A second under-lock population/cycle check prevents the earlier
 * unlocked preview from becoming a stale authorization.
 *
 * Never permit any POST-supplied farm/permission value. No transaction,
 * redirected response, or draft destruction is performed here.
 */
require_once __DIR__ . '/daily_record_revision_save_pipeline.php';

if (!function_exists('daily_record_revision_route_text')) {
    function daily_record_revision_route_text(array $post, string $key, string $default = ''): string
    {
        $value = $post[$key] ?? $default;
        if (!is_string($value)) {
            throw new DailyRecordRevisionConflict('Invalid Daily Record text input.');
        }
        return $value;
    }
}

if (!function_exists('daily_record_revision_route_fields')) {
    /**
     * $facts are VALUES ALREADY VALIDATED by the current module route.
     * Keep the exact old SQL field set but centralize its future CAS mapping.
     */
    function daily_record_revision_route_fields(string $type, array $post, array $facts): array
    {
        $type = strtolower(trim($type));
        daily_record_revision_definition($type);
        foreach (['opening_stock', 'mortality', 'feed_consumption',
                  'feed_item_id', 'water_consumption'] as $key) {
            if (!array_key_exists($key, $facts)) {
                throw new InvalidArgumentException('Missing validated Daily Record fact.');
            }
        }
        $fields = [
            'opening_stock' => $facts['opening_stock'],
            'mortality' => $facts['mortality'],
            'feed_item_id' => $facts['feed_item_id'] > 0
                ? $facts['feed_item_id'] : null,
            'water_consumption_liters' => $facts['water_consumption'],
            'medications' => daily_record_revision_route_text($post, 'medications'),
            'remarks' => daily_record_revision_route_text($post, 'remarks'),
        ];
        if ($type === 'layer') {
            foreach (['birds_age','egg_production','crates_count','laying_rate'] as $key) {
                if (!array_key_exists($key, $facts)) {
                    throw new InvalidArgumentException('Missing validated Layer production fact.');
                }
            }
            $fields['feed_consumption_bags'] = $facts['feed_consumption'];
            $fields['birds_age'] = $facts['birds_age'];
            $fields['egg_production'] = $facts['egg_production'];
            $fields['crates_count'] = $facts['crates_count'];
            $fields['laying_rate'] = $facts['laying_rate'];
        } elseif ($type === 'broiler') {
            if (!array_key_exists('birds_age', $facts)) {
                throw new InvalidArgumentException('Missing validated Broiler bird age.');
            }
            $fields['feed_consumption_bags'] = $facts['feed_consumption'];
            $fields['birds_age'] = $facts['birds_age'];
        } else {
            if (!array_key_exists('tag_no', $facts) ||
                (!is_string($facts['tag_no']) && $facts['tag_no'] !== null)) {
                throw new InvalidArgumentException('Missing validated Ruminant tag.');
            }
            $unit = $facts['feed_item_id'] > 0
                ? daily_record_revision_route_text($post, 'feed_consumption_unit', 'kg')
                : 'kg';
            if ($unit === '' || strlen($unit) > 50) {
                throw new InvalidArgumentException('Invalid feed consumption unit.');
            }
            $fields['feed_consumption_kg'] = $facts['feed_consumption'];
            $fields['feed_consumption_unit'] = $unit;
            $fields['tag_no'] = $facts['tag_no'];
            $fields['other_details'] = daily_record_revision_route_text($post, 'other_details');
            $fields['reproduction_details'] =
                daily_record_revision_route_text($post, 'reproduction_details');
        }
        return $fields;
    }
}

if (!function_exists('daily_record_revision_route_revalidate_locked')) {
    /**
     * The cycle is already tenant-scoped by server selection, then locked
     * again here for lifecycle/date validation. Existing common population
     * policy is consulted without duplicating its calculations.
     *
     * A legacy previous-row chain is used only if canonical opening is
     * unavailable. Every query is fully tenant+cycle+species qualified.
     */
    function daily_record_revision_route_revalidate_locked(
        PDO $pdo, string $type, int $farmId, ?int $cycleId,
        string $recordDate, ?string $animalType, int $openingStock
    ): void {
        daily_record_revision_require_transaction($pdo);
        if ($cycleId === null) return;
        $type = strtolower(trim($type));
        $definition = daily_record_revision_definition($type);
        $farmType = $type === 'ruminant' ? 'ruminant' : 'poultry';
        $sql = 'SELECT id, start_date, expected_end_date FROM production_cycles'
            . ' WHERE id = ? AND farm_id = ? AND farm_type = ?'
            . " AND status = 'active'";
        $params = [$cycleId, $farmId, $farmType];
        if ($type !== 'ruminant') {
            $sql .= ' AND production_type = ?';
            $params[] = $type;
        }
        $sql .= ' LIMIT 1 FOR UPDATE';
        $statement = $pdo->prepare($sql);
        if ($statement === false || !$statement->execute($params)) {
            throw new DailyRecordRevisionConflict('Production cycle is unavailable.');
        }
        $cycle = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($cycle) ||
            $recordDate < (string)$cycle['start_date'] ||
            (!empty($cycle['expected_end_date']) &&
             $recordDate > (string)$cycle['expected_end_date'])) {
            throw new DailyRecordRevisionConflict('The selected cycle is unavailable for this date.');
        }

        $canonical = daily_population_continuity_expected_opening(
            $pdo, $farmId, $cycleId, $type, $recordDate, $animalType
        );
        if ($canonical !== null) {
            if (!is_array($canonical) || !array_key_exists('opening_stock', $canonical) ||
                $openingStock !== (int)$canonical['opening_stock']) {
                throw new DailyRecordRevisionConflict(
                    'Opening stock changed. Reload the Daily Record.'
                );
            }
            return;
        }
        $prior = 'SELECT opening_stock, mortality FROM ' . $definition['table']
            . ' WHERE farm_id = ? AND cycle_id = ? AND record_date < ?';
        $priorParams = [$farmId, $cycleId, $recordDate];
        if ($definition['animal_type']) {
            $prior .= ' AND LOWER(TRIM(animal_type)) = ?';
            $priorParams[] = $animalType;
        }
        $prior .= ' ORDER BY record_date DESC, id DESC LIMIT 1 FOR UPDATE';
        $stmt = $pdo->prepare($prior);
        if ($stmt === false || !$stmt->execute($priorParams)) {
            throw new DailyRecordRevisionConflict('Earlier Daily Record is unavailable.');
        }
        $previous = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($previous && $openingStock !== max(
            0, (int)$previous['opening_stock'] - (int)$previous['mortality']
        )) {
            throw new DailyRecordRevisionConflict(
                'Previous closing stock changed. Reload the Daily Record.'
            );
        }
    }
}

if (!function_exists('daily_record_revision_route_save')) {
    function daily_record_revision_route_save(
        PDO $pdo, string $type, int $farmId, int $actorId,
        ?int $cycleId, string $recordDate, ?string $animalType,
        array $post, array $facts
    ): array {
        daily_record_revision_require_transaction($pdo);
        $fields = daily_record_revision_route_fields($type, $post, $facts);
        $tokens = [];
        foreach ([
            'write_intent','record_id','expected_revision',
            'original_cycle_id','original_record_date','original_animal_type',
        ] as $field) {
            $tokens[$field] = daily_record_revision_route_text($post, $field);
        }
        if (!function_exists('checkAccess') || !function_exists('permission_runtime_has')) {
            throw new LogicException('Daily Record runtime authorization is not initialized.');
        }
        $authorized = static function(
            string $module, string $view, string $operation
        ): bool {
            return checkAccess($module) &&
                permission_runtime_has($view) &&
                permission_runtime_has($operation);
        };
        $revalidate = static function(PDO $lockedPdo, array $prepared) use (
            $type, $farmId, $cycleId, $recordDate, $animalType, $facts
        ): void {
            daily_record_revision_route_revalidate_locked(
                $lockedPdo, $type, $farmId, $cycleId,
                $recordDate, $animalType, $facts['opening_stock']
            );
        };
        return daily_record_revision_save_pipeline(
            $pdo, $type, $farmId, $actorId, $cycleId, $recordDate, $animalType,
            $tokens, $fields, $authorized, $revalidate
        );
    }
}

/**
 * Source-only staged transaction owner used by all three form routes.
 * A future AJAX submit must preserve unsaved fields on 409 rather than
 * redirecting to a blank page. This function does NOT emit a response.
 */
if (!function_exists('daily_record_revision_route_transaction')) {
    function daily_record_revision_route_transaction(
        PDO $pdo, string $type, int $farmId, int $actorId,
        ?int $cycleId, string $recordDate, ?string $animalType,
        array $post, array $facts
    ): array {
        if ($pdo->inTransaction()) {
            throw new LogicException('Daily Record route already owns a transaction.');
        }
        if (!$pdo->beginTransaction()) {
            throw new RuntimeException('Cannot begin Daily Record transaction.');
        }
        try {
            $result = daily_record_revision_route_save(
                $pdo, $type, $farmId, $actorId,
                $cycleId, $recordDate, $animalType, $post, $facts
            );
            if (!$pdo->commit()) {
                throw new RuntimeException('Daily Record transaction could not be committed.');
            }
            return $result;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }
}

/**
 * Future B12 XHR route endpoint envelope. An ordinary form POST must NOT
 * reach this after activation: the synchronized JS bridge must intercept
 * submit so 409 leaves the user's original inputs in place. No redirect.
 * Called only behind the hard-disabled source cutover gate.
 */
if (!function_exists('daily_record_revision_route_dispatch_json')) {
    function daily_record_revision_route_dispatch_json(
        PDO $pdo, string $type, int $farmId, int $actorId,
        ?int $cycleId, string $recordDate, ?string $animalType,
        array $post, array $facts
    ): never {
        $status = 200;
        try {
            $saved = daily_record_revision_route_transaction(
                $pdo, $type, $farmId, $actorId,
                $cycleId, $recordDate, $animalType, $post, $facts
            );
            $body = [
                'success' => true,
                'record_id' => $saved['record_id'],
                'record_revision' => $saved['record_revision'],
                'continuity_affected' => $saved['continuity_affected'],
            ];
        } catch (DailyRecordRevisionPermissionDenied $error) {
            $status = 403;
            $body = ['success'=>false,'code'=>'forbidden',
                     'error'=>'You do not have permission to save this Daily Record.'];
        } catch (DailyRecordRevisionConflict $error) {
            $status = 409;
            $body = ['success'=>false,'code'=>'record_conflict',
                     'error'=>'The Daily Record changed. Keep your draft and reload the record before retrying.'];
        } catch (InvalidArgumentException $error) {
            $status = 422;
            $body = ['success'=>false,'code'=>'invalid_record',
                     'error'=>'Invalid Daily Record data. Review the form before retrying.'];
        } catch (PDOException $error) {
            $status = 503;
            $body = ['success'=>false,'code'=>'storage_unavailable',
                     'error'=>'Daily Record saving is temporarily unavailable. Keep your draft.'];
        } catch (Throwable $error) {
            $status = 500;
            $body = ['success'=>false,'code'=>'save_failed',
                     'error'=>'The Daily Record could not be saved. Keep your draft.'];
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($body, JSON_THROW_ON_ERROR);
        exit;
    }
}
