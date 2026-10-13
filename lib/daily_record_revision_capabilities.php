<?php
declare(strict_types=1);

/**
 * B12 canonical Daily Record capability names (source-only cutover contract).
 *
 * Permission names match today's includes/permission_runtime.php and
 * api/delete_record.php. This helper does not grant a permission or inspect
 * POST/session data. The initialized route must still establish authentication,
 * current farm, module access, operation permission and CSRF before mutations.
 */
require_once __DIR__ . '/daily_record_revision_authority.php';

if (!function_exists('daily_record_revision_capabilities')) {
    /** @return array{module:string,view:string,add:string,edit:string,delete:string} */
    function daily_record_revision_capabilities(string $type): array
    {
        $type = strtolower(trim($type));
        daily_record_revision_definition($type);
        return match ($type) {
            'layer' => [
                'module' => 'poultry',
                'view' => 'poultry_daily_layer',
                'add' => 'poultry_daily_layer_add',
                'edit' => 'poultry_daily_layer_edit',
                'delete' => 'poultry_daily_layer_delete',
            ],
            'broiler' => [
                'module' => 'poultry',
                'view' => 'poultry_daily_broiler',
                'add' => 'poultry_daily_broiler_add',
                'edit' => 'poultry_daily_broiler_edit',
                'delete' => 'poultry_daily_broiler_delete',
            ],
            'ruminant' => [
                'module' => 'ruminant',
                'view' => 'ruminant_daily',
                'add' => 'ruminant_daily_add',
                'edit' => 'ruminant_daily_edit',
                'delete' => 'ruminant_daily_delete',
            ],
            default => throw new InvalidArgumentException('Unsupported Daily Record type.'),
        };
    }
}

if (!function_exists('daily_record_revision_operation_permission')) {
    /** A client mode is NOT a permission grant; runtime must enforce result. */
    function daily_record_revision_operation_permission(string $type, string $mode): string
    {
        if (!in_array($mode, ['create', 'edit', 'delete'], true)) {
            throw new DailyRecordRevisionConflict('Invalid Daily Record operation.');
        }
        $capability = daily_record_revision_capabilities($type);
        return $capability[$mode === 'create' ? 'add' : $mode];
    }
}
