<?php
/**
 * Sales unit-of-measure helpers.
 *
 * Keep the stored value human-readable because reports, debt notes and exports
 * need to remain understandable without depending on a lookup table.
 */
function sales_unit_presets(): array {
    return [
        'Head' => 'Head',
        'Crate' => 'Crate',
        'Piece' => 'Piece',
        'Kg' => 'Kg',
        'Gram' => 'Gram',
        'Litre' => 'Litre',
        'Ml' => 'Ml',
        'Bag' => 'Bag',
        'Bottle' => 'Bottle',
        'Tray' => 'Tray',
        'Dozen' => 'Dozen',
        'Unit' => 'Unit',
    ];
}

function sales_unit_from_post(array $post): string {
    $preset = trim((string)($post['unit_preset'] ?? ''));
    $custom = trim((string)($post['unit_custom'] ?? ''));
    if ($preset === '__custom__') {
        $unit = $custom;
    } else {
        $presets = sales_unit_presets();
        $unit = isset($presets[$preset]) ? $preset : '';
    }
    $unit = preg_replace('/\\s+/', ' ', $unit ?? '');
    $unit = trim((string)$unit);
    if ($unit === '') throw new RuntimeException('Unit of measure is required for a sale.');
    $length = function_exists('mb_strlen') ? mb_strlen($unit) : strlen($unit);
    if ($length > 30) throw new RuntimeException('Unit of measure must be 30 characters or fewer.');
    return $unit;
}

function sales_unit_label(?string $unit): string {
    $unit = trim((string)$unit);
    return $unit !== '' ? $unit : 'Not specified (legacy)';
}
