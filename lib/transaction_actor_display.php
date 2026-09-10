<?php
/**
 * Shared transaction actor display.
 *
 * Tenant activity should identify the farm, person and operational role
 * without hard-coding tenant names into individual pages.
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

    /*
     * Older/test accounts sometimes used synthetic names such as:
     *   Farm A poultry
     *   Farm A Ruminant
     *
     * Do not display these as though they were a person's name.
     */
    if ($fullName !== '') {
        $farmLower = strtolower($farmName);
        $nameLower = strtolower($fullName);

        if (
            $nameLower === $farmLower ||
            strpos($nameLower, $farmLower . ' ') === 0
        ) {
            $fullName = '';
        }
    }

    if ($fullName !== '' && $roleLabel !== '') {
        return $farmName . ' — ' . $fullName . ' (' . $roleLabel . ')';
    }

    if ($fullName !== '') {
        return $farmName . ' — ' . $fullName;
    }

    if ($roleLabel !== '') {
        return $farmName . ' — ' . $roleLabel;
    }

    return $farmName . ' — System';
}
