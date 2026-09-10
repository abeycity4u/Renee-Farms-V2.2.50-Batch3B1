<?php
/**
 * Canonical actor identity display for tenant/audit records.
 *
 * Preferred user-facing format:
 *
 *     Farm Name — Person Name (Role)
 *
 * Synthetic legacy account names that merely repeat the farm name are
 * suppressed:
 *
 *     Farm Name — Role
 */

function transaction_actor_role_label(?string $userType): string
{
    $value = strtolower(trim((string)$userType));

    $labels = [
        'platform_owner' => 'Platform Owner',
        'farm_admin' => 'Farm Admin',
        'poultry_manager' => 'Poultry Manager',
        'ruminant_manager' => 'Ruminant Manager',
        'sales_rep' => 'Sales Representative',
        'viewer' => 'Viewer',
    ];

    if (isset($labels[$value])) {
        return $labels[$value];
    }

    return $value !== ''
        ? ucwords(str_replace('_', ' ', $value))
        : '';
}

/**
 * Normalize only for identity comparison.
 *
 * Formal business suffixes should not make a synthetic farm account look
 * like a real person's name:
 *
 *     Farm A LLC == Farm A
 *     Renee Farms Limited == Renee Farms
 */
function transaction_actor_identity_key(string $value): string
{
    $value = strtolower(trim($value));

    $value = preg_replace('/[.,]+/', ' ', $value) ?? $value;
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    $value = trim($value);

    $suffixPattern =
        '/\s+(?:limited|ltd|llc|plc|incorporated|inc|company|co)$/';

    while ($value !== '') {
        $before = $value;

        $value = preg_replace(
            $suffixPattern,
            '',
            $value
        ) ?? $value;

        $value = trim($value);

        if ($value === $before) {
            break;
        }
    }

    return $value;
}

/**
 * Cached farm-name lookup.
 *
 * One database lookup per farm per request, even when rendering many rows.
 */
function transaction_actor_farm_name(PDO $pdo, int $farmId): string
{
    static $cache = [];

    if ($farmId < 1) {
        return 'Farm';
    }

    if (isset($cache[$farmId])) {
        return $cache[$farmId];
    }

    $stmt = $pdo->prepare(
        'SELECT name FROM farms WHERE id = ? LIMIT 1'
    );

    $stmt->execute([$farmId]);

    $name = trim((string)$stmt->fetchColumn());

    if ($name === '') {
        $name = 'Farm';
    }

    $cache[$farmId] = $name;

    return $name;
}

function transaction_recorded_by_label(
    string $farmName,
    ?string $fullName,
    ?string $userType
): string {
    $farmName = trim($farmName);

    if ($farmName === '') {
        $farmName = 'Farm';
    }

    $fullName = trim((string)$fullName);
    $roleLabel = transaction_actor_role_label($userType);

    if ($fullName !== '') {
        $farmIdentity =
            transaction_actor_identity_key($farmName);

        $nameIdentity =
            transaction_actor_identity_key($fullName);

        /*
         * Suppress old synthetic user names such as:
         *
         *     Farm A
         *     Farm A poultry
         *     Farm A Ruminant
         *
         * This also works when the registered farm name contains LLC,
         * Limited, Ltd, PLC, etc.
         */
        if (
            $farmIdentity !== '' &&
            (
                $nameIdentity === $farmIdentity ||
                strpos(
                    $nameIdentity,
                    $farmIdentity . ' '
                ) === 0
            )
        ) {
            $fullName = '';
        }
    }

    if ($fullName !== '' && $roleLabel !== '') {
        return
            $farmName .
            ' — ' .
            $fullName .
            ' (' .
            $roleLabel .
            ')';
    }

    if ($fullName !== '') {
        return $farmName . ' — ' . $fullName;
    }

    if ($roleLabel !== '') {
        return $farmName . ' — ' . $roleLabel;
    }

    return $farmName . ' — System';
}

function transaction_recorded_by_label_for_farm(
    PDO $pdo,
    int $farmId,
    ?string $fullName,
    ?string $userType
): string {
    return transaction_recorded_by_label(
        transaction_actor_farm_name(
            $pdo,
            $farmId
        ),
        $fullName,
        $userType
    );
}

function transaction_recorded_by_label_from_row(
    PDO $pdo,
    int $farmId,
    array $row,
    string $nameKey = 'recorded_by_name',
    string $typeKey = 'recorded_by_user_type'
): string {
    return transaction_recorded_by_label_for_farm(
        $pdo,
        $farmId,
        $row[$nameKey] ?? null,
        $row[$typeKey] ?? null
    );
}
