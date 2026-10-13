<?php
declare(strict_types=1);

/**
 * B12 staged single-transaction Daily Record save pipeline. NOT WIRED.
 *
 * This is the intended common future replacement for three date-based
 * CREATE/EDIT SQL blocks, not a live alternate route. Requires all seven
 * writer paths, API/browser tokens, revision schema, create lock policy and
 * permission_runtime cutover to activate TOGETHER. No migration or rollout
 * permission is implied by including this file in source.
 *
 * Original Layer/Broiler/Ruminant business validation, CSRF, authenticated
 * actor identity, tenant selection, and cycle/date authorization remain the
 * owning routes' responsibility. The caller MUST own the PDO transaction,
 * perform validation before invoking, and roll back on ANY throwable.
 *
 * The same transaction orders:
 *   client intent and operation permission -> exact source lock / CREATE scope
 *   -> locked business invariant callback -> CAS UPDATE or guarded INSERT
 *   -> locked population continuity WITH revision bumps -> feed inventory
 *   -> mortality projection -> (Layer only) pooled egg sale allocation.
 *
 * No beginTransaction(), commit(), rollback(), or duplicated business SQL.
 */
require_once __DIR__ . '/daily_record_revision_save_gate.php';
require_once __DIR__ . '/daily_record_revision_persistence.php';
require_once __DIR__ . '/daily_record_revision_continuity_apply.php';

if (!function_exists('daily_record_revision_save_pipeline')) {
    /**
     * @param callable(string,string,string):bool $authorized Trusted role check.
     * @param callable(PDO,array):void $revalidateLocked Owns business invariants
     *        that must be checked AFTER authoritative row/scope locking.
     *
     * @return array{record_id:int,record_revision:int,mode:string,continuity_affected:int}
     */
    function daily_record_revision_save_pipeline(
        PDO $pdo,
        string $type,
        int $farmId,
        int $actorId,
        ?int $cycleId,
        string $recordDate,
        ?string $animalType,
        array $tokens,
        array $fields,
        callable $authorized,
        callable $revalidateLocked
    ): array {
        daily_record_revision_require_transaction($pdo);
        $type = strtolower(trim($type));
        daily_record_revision_definition($type);
        if ($actorId <= 0 || $farmId <= 0) {
            throw new InvalidArgumentException('Invalid authenticated Daily Record context.');
        }

        $sourceType = match ($type) {
            'layer' => 'daily_layer_record',
            'broiler' => 'daily_broiler_record',
            'ruminant' => 'daily_ruminant_record',
        };
        $feedColumn = $type === 'ruminant'
            ? 'feed_consumption_kg' : 'feed_consumption_bags';
        foreach (['opening_stock', 'mortality', 'feed_item_id', $feedColumn] as $key) {
            if (!array_key_exists($key, $fields)) {
                throw new InvalidArgumentException('Incomplete validated Daily Record feed/population facts.');
            }
        }
        // The business route has already validated values; this boundary
        // refuses incoherent side-effect inputs rather than inventing defaults.
        $openingStock = $fields['opening_stock'];
        $mortality = $fields['mortality'];
        $rawItem = $fields['feed_item_id'];
        $quantity = $fields[$feedColumn];
        if (!is_int($openingStock) || $openingStock < 1 ||
            !is_int($mortality) || $mortality < 0 ||
            $mortality > $openingStock ||
            !is_numeric($quantity) || (float)$quantity < 0 ||
            !is_finite((float)$quantity) ||
            ($rawItem !== null && (!is_int($rawItem) || $rawItem < 1)) ||
            ((float)$quantity > 0 && $rawItem === null)) {
            throw new InvalidArgumentException('Invalid validated Daily Record side-effect facts.');
        }

        $prepared = daily_record_revision_prepare_save(
            $pdo, $type, $farmId, $cycleId, $recordDate,
            $animalType, $tokens, $authorized
        );
        $revalidateLocked($pdo, $prepared);
        // This must happen BEFORE feed/mortality reversals or downstream
        // allocation mutation so a stale edit fails without side effects.
        $saved = daily_record_revision_persist_prepared(
            $pdo, $prepared, $farmId, $actorId, $fields
        );
        $scope = $prepared['contract']['scope'];
        $savedCycle = $scope['cycle_id'];
        $savedSpecies = $scope['animal_type'];
        $savedDate = $scope['record_date'];

        $affected = 0;
        if ($savedCycle !== null) {
            $continuity = daily_record_revision_continuity_apply(
                $pdo, $farmId, $savedCycle, $type, $savedDate,
                $openingStock, $mortality, $savedSpecies, $saved['record_id']
            );
            $affected = (int)$continuity['applied_count'];
        }

        if (!function_exists('sync_daily_feed_usage') ||
            !function_exists('daily_population_sync_mortality') ||
            ($type === 'layer' && !function_exists('sales_rebuild_layer_egg_allocations'))) {
            throw new LogicException('Canonical Daily Record downstream services are unavailable.');
        }
        sync_daily_feed_usage(
            $pdo, $farmId, $saved['record_id'], $rawItem,
            (float)$quantity, $savedCycle, $savedDate,
            $type === 'ruminant' ? 'ruminant' : 'poultry',
            $type === 'ruminant' ? 'ruminant' : $type,
            $sourceType
        );
        daily_population_sync_mortality(
            $pdo, $farmId, $sourceType, $saved['record_id'],
            $savedCycle, $savedDate, $mortality, $actorId
        );
        if ($type === 'layer') {
            sales_rebuild_layer_egg_allocations(
                $pdo, $farmId, $savedDate, $actorId
            );
        }
        return [
            'record_id' => $saved['record_id'],
            'record_revision' => $saved['record_revision'],
            'mode' => $saved['mode'],
            'continuity_affected' => $affected,
        ];
    }
}
