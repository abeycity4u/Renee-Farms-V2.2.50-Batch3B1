<?php
/**
 * Current-subscription seat policy for V2.3 commercial hardening.
 *
 * Contract:
 * - subscription_plan_catalog() defines included seats;
 * - farm_subscription_seat_addons stores purchased extra seats durably;
 * - farm_role_limits remains the effective runtime enforcement limit;
 * - plan changes preserve purchased extras;
 * - plan/seat reductions are blocked when active assigned users would exceed the
 *   proposed effective limit;
 * - disabling a livestock module preserves users/history and purchased extras,
 *   but its specialist effective limit becomes 0 until the module is re-enabled.
 */

if (!function_exists('subscription_seat_roles')) {
    function subscription_seat_roles(): array
    {
        return [
            'poultry_manager' => 'Poultry Manager',
            'ruminant_manager' => 'Ruminant Manager',
            'sales_rep' => 'Sales Representative',
            'viewer' => 'Viewer',
        ];
    }
}

if (!function_exists('subscription_seat_normalize_addons')) {
    function subscription_seat_normalize_addons(array $input): array
    {
        $out = [];
        foreach (subscription_seat_roles() as $role => $_label) {
            $value = filter_var(
                $input[$role] ?? 0,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 0, 'max_range' => 500]]
            );
            $out[$role] = $value === false ? 0 : (int)$value;
        }
        return $out;
    }
}

if (!function_exists('subscription_seat_addon_table_exists')) {
    function subscription_seat_addon_table_exists(PDO $pdo): bool
    {
        static $exists = null;
        if ($exists !== null) return $exists;
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'farm_subscription_seat_addons'"
        );
        $stmt->execute();
        return $exists = ((int)$stmt->fetchColumn() > 0);
    }
}

if (!function_exists('subscription_seat_role_relevant')) {
    function subscription_seat_role_relevant(string $role, array $modules): bool
    {
        $modules = function_exists('farm_entitlement_normalize_modules')
            ? farm_entitlement_normalize_modules($modules)
            : array_values(array_unique(array_map('strtolower', $modules)));
        $salesAvailable = (bool)array_intersect(['poultry', 'ruminant', 'sales'], $modules);

        if ($role === 'poultry_manager') return in_array('poultry', $modules, true);
        if ($role === 'ruminant_manager') return in_array('ruminant', $modules, true);
        if ($role === 'sales_rep') return $salesAvailable;
        if ($role === 'viewer') return !empty($modules);
        return false;
    }
}

if (!function_exists('subscription_seat_load_effective_limits')) {
    function subscription_seat_load_effective_limits(PDO $pdo, int $farmId): array
    {
        $limits = array_fill_keys(array_keys(subscription_seat_roles()), 0);
        if ($farmId < 1) return $limits;

        $stmt = $pdo->prepare('SELECT role_code, max_users FROM farm_role_limits WHERE farm_id = ?');
        try {
            $stmt->execute([$farmId]);
        } catch (Throwable $e) {
            return $limits;
        }
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $role = (string)($row['role_code'] ?? '');
            if (array_key_exists($role, $limits)) $limits[$role] = max(0, (int)($row['max_users'] ?? 0));
        }
        return $limits;
    }
}

if (!function_exists('subscription_seat_load_addons')) {
    function subscription_seat_load_addons(PDO $pdo, int $farmId, string $planCode, array $modules): array
    {
        $addons = array_fill_keys(array_keys(subscription_seat_roles()), 0);
        if ($farmId < 1) return $addons;

        if (subscription_seat_addon_table_exists($pdo)) {
            $stmt = $pdo->prepare('SELECT role_code, extra_seats FROM farm_subscription_seat_addons WHERE farm_id = ?');
            $stmt->execute([$farmId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) {
                foreach ($rows as $row) {
                    $role = (string)($row['role_code'] ?? '');
                    if (array_key_exists($role, $addons)) $addons[$role] = max(0, min(500, (int)($row['extra_seats'] ?? 0)));
                }
                return $addons;
            }
        }

        // Compatibility bridge for existing farms before their first durable
        // extra-seat save: derive the old implied extras from the stored effective
        // total so no paid allowance is lost during migration to the new model.
        if (!function_exists('subscription_plan_included_role_limits') || !subscription_plan_is_valid($planCode)) return $addons;
        $included = subscription_plan_included_role_limits($planCode, $modules);
        $effective = subscription_seat_load_effective_limits($pdo, $farmId);
        foreach ($addons as $role => $_value) {
            if (!subscription_seat_role_relevant($role, $modules)) continue;
            $addons[$role] = max(0, (int)($effective[$role] ?? 0) - (int)($included[$role] ?? 0));
        }
        return $addons;
    }
}

if (!function_exists('subscription_seat_save_addons')) {
    function subscription_seat_save_addons(PDO $pdo, int $farmId, array $addons): void
    {
        if ($farmId < 1) throw new InvalidArgumentException('A valid farm is required for seat add-ons.');
        if (!subscription_seat_addon_table_exists($pdo)) {
            throw new RuntimeException('Commercial seat storage is not installed. Apply migration 040_subscription_seat_addons.sql before saving subscription changes.');
        }

        $addons = subscription_seat_normalize_addons($addons);
        $stmt = $pdo->prepare(
            'INSERT INTO farm_subscription_seat_addons (farm_id, role_code, extra_seats) VALUES (?, ?, ?) '
            . 'ON DUPLICATE KEY UPDATE extra_seats = VALUES(extra_seats), updated_at = CURRENT_TIMESTAMP'
        );
        foreach ($addons as $role => $extraSeats) {
            $stmt->execute([$farmId, $role, $extraSeats]);
        }
    }
}

if (!function_exists('subscription_seat_used_role_count')) {
    function subscription_seat_used_role_count(PDO $pdo, int $farmId, string $role): int
    {
        if ($farmId < 1 || !array_key_exists($role, subscription_seat_roles())) return 0;
        $sql = "SELECT COUNT(DISTINCT u.id)
                FROM users u
                WHERE u.farm_id = ?
                  AND u.user_type <> 'farm_admin'
                  AND (
                    EXISTS (
                        SELECT 1 FROM user_roles ur
                        INNER JOIN roles r ON r.id = ur.role_id
                        WHERE ur.user_id = u.id AND r.code = ?
                    )
                    OR (
                        u.user_type = ?
                        AND NOT EXISTS (SELECT 1 FROM user_roles ur2 WHERE ur2.user_id = u.id)
                    )
                  )";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$farmId, $role, $role]);
        return (int)$stmt->fetchColumn();
    }
}

if (!function_exists('subscription_seat_used_role_counts')) {
    function subscription_seat_used_role_counts(PDO $pdo, int $farmId): array
    {
        $counts = [];
        foreach (subscription_seat_roles() as $role => $_label) {
            $counts[$role] = subscription_seat_used_role_count($pdo, $farmId, $role);
        }
        return $counts;
    }
}

if (!function_exists('subscription_seat_assert_capacity')) {
    function subscription_seat_assert_capacity(PDO $pdo, int $farmId, string $planCode, array $modules, array $addons): void
    {
        if ($farmId < 1) return;
        if (!function_exists('subscription_plan_is_valid') || !subscription_plan_is_valid($planCode)) {
            throw new RuntimeException('Select a valid subscription plan before changing seat limits.');
        }

        $addons = subscription_seat_normalize_addons($addons);
        $limits = subscription_plan_effective_role_limits($planCode, $modules, $addons);
        $used = subscription_seat_used_role_counts($pdo, $farmId);
        $planLabel = subscription_plan_label($planCode);
        $violations = [];

        foreach (subscription_seat_roles() as $role => $label) {
            // Module disable is allowed without deleting people or purchased extras.
            // Capacity is enforced again if that commercial role becomes relevant.
            if (!subscription_seat_role_relevant($role, $modules)) continue;
            $max = max(0, (int)($limits[$role] ?? 0));
            $count = max(0, (int)($used[$role] ?? 0));
            if ($count <= $max) continue;

            $shortfall = $count - $max;
            $violations[] = $label . ' uses ' . $count . ' seat' . ($count === 1 ? '' : 's')
                . ' but ' . $planLabel . ' plus the selected extra seats allows ' . $max . '. '
                . 'Remove ' . $shortfall . ' assigned user' . ($shortfall === 1 ? '' : 's')
                . ' or add ' . $shortfall . ' extra seat' . ($shortfall === 1 ? '' : 's') . '.';
        }

        if ($violations) {
            throw new RuntimeException('Cannot save this subscription change. ' . implode(' ', $violations));
        }
    }
}
