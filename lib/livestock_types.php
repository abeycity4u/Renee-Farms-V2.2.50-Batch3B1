<?php
/**
 * Renee Farms V3.0 — canonical custom livestock type service.
 *
 * Built-in livestock remain the legacy cattle/goat/sheep keys.
 * "other" remains the compatibility bucket. A farm-defined livestock type is
 * represented only by a positive livestock_type_id attached to an "other"
 * ruminant cycle/animal.
 *
 * Pages must use this service rather than owning livestock type SQL or naming
 * policy themselves.
 */

if (!class_exists('LivestockTypeException')) {
    class LivestockTypeException extends RuntimeException {}
}

if (!function_exists('livestock_type_text_length')) {
    function livestock_type_text_length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value)
            : strlen($value);
    }
}

if (!function_exists('livestock_type_builtin_labels')) {
    function livestock_type_builtin_labels(): array
    {
        return [
            'cattle' => 'Cattle',
            'goat' => 'Goat',
            'sheep' => 'Sheep',
        ];
    }
}

if (!function_exists('livestock_type_reserved_names')) {
    function livestock_type_reserved_names(): array
    {
        return [
            'cattle',
            'goat',
            'sheep',
            'other',
            'shared',
        ];
    }
}

if (!function_exists('livestock_type_normalize_name')) {
    function livestock_type_normalize_name(string $name): string
    {
        $name = trim($name);
        $collapsed = preg_replace('/\s+/u', ' ', $name);

        if (is_string($collapsed)) {
            $name = $collapsed;
        }

        if ($name === '') {
            throw new InvalidArgumentException(
                'Enter a livestock type name.'
            );
        }

        if (livestock_type_text_length($name) > 100) {
            throw new InvalidArgumentException(
                'Livestock type name must be 100 characters or fewer.'
            );
        }

        $key = function_exists('mb_strtolower')
            ? mb_strtolower($name)
            : strtolower($name);

        if (in_array($key, livestock_type_reserved_names(), true)) {
            throw new InvalidArgumentException(
                'That livestock type name is reserved by the platform.'
            );
        }

        return $name;
    }
}

if (!function_exists('livestock_type_assert_farm_id')) {
    function livestock_type_assert_farm_id(int $farmId): void
    {
        if ($farmId <= 0) {
            throw new InvalidArgumentException(
                'Select a valid farm.'
            );
        }
    }
}

if (!function_exists('livestock_type_assert_user_id')) {
    function livestock_type_assert_user_id(?int $userId): void
    {
        if ($userId !== null && $userId <= 0) {
            throw new InvalidArgumentException(
                'Invalid livestock type user.'
            );
        }
    }
}

if (!function_exists('livestock_type_is_duplicate_exception')) {
    function livestock_type_is_duplicate_exception(Throwable $error): bool
    {
        if (!$error instanceof PDOException) {
            return false;
        }

        $sqlState = (string)($error->errorInfo[0] ?? $error->getCode());
        $driverCode = (int)($error->errorInfo[1] ?? 0);

        return $sqlState === '23000' && $driverCode === 1062;
    }
}

if (!function_exists('livestock_type_get')) {
    function livestock_type_get(
        PDO $pdo,
        int $farmId,
        int $livestockTypeId,
        bool $requireActive = false
    ): array {
        livestock_type_assert_farm_id($farmId);

        if ($livestockTypeId <= 0) {
            throw new InvalidArgumentException(
                'Select a valid livestock type.'
            );
        }

        $sql =
            'SELECT
                 id,
                 farm_id,
                 name,
                 is_active,
                 created_by,
                 created_at,
                 updated_at
             FROM livestock_types
             WHERE id = ?
               AND farm_id = ?';

        if ($requireActive) {
            $sql .= ' AND is_active = 1';
        }

        $sql .= ' LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$livestockTypeId, $farmId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new LivestockTypeException(
                $requireActive
                    ? 'The selected livestock type is not active for this farm.'
                    : 'The selected livestock type was not found in this farm.'
            );
        }

        $row['id'] = (int)$row['id'];
        $row['farm_id'] = (int)$row['farm_id'];
        $row['is_active'] = (int)$row['is_active'];

        return $row;
    }
}

if (!function_exists('livestock_type_list')) {
    function livestock_type_list(
        PDO $pdo,
        int $farmId,
        bool $includeInactive = false
    ): array {
        livestock_type_assert_farm_id($farmId);

        $sql =
            'SELECT
                 id,
                 farm_id,
                 name,
                 is_active,
                 created_by,
                 created_at,
                 updated_at
             FROM livestock_types
             WHERE farm_id = ?';

        if (!$includeInactive) {
            $sql .= ' AND is_active = 1';
        }

        $sql .= ' ORDER BY name ASC, id ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$farmId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
            $row['farm_id'] = (int)$row['farm_id'];
            $row['is_active'] = (int)$row['is_active'];
        }
        unset($row);

        return $rows;
    }
}

if (!function_exists('livestock_type_create')) {
    function livestock_type_create(
        PDO $pdo,
        int $farmId,
        string $name,
        ?int $createdBy
    ): int {
        livestock_type_assert_farm_id($farmId);
        livestock_type_assert_user_id($createdBy);
        $name = livestock_type_normalize_name($name);

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO livestock_types
                 (
                     farm_id,
                     name,
                     is_active,
                     created_by
                 )
                 VALUES (?, ?, 1, ?)'
            );

            $stmt->execute([
                $farmId,
                $name,
                $createdBy,
            ]);

            $id = (int)$pdo->lastInsertId();

            if (function_exists('audit_log_event')) {
                audit_log_event(
                    'livestock_type_created',
                    'livestock_type',
                    $id,
                    [
                        'name' => $name,
                        'is_active' => 1,
                    ]
                );
            }

            return $id;
        } catch (Throwable $error) {
            if (livestock_type_is_duplicate_exception($error)) {
                throw new LivestockTypeException(
                    'A livestock type with this name already exists for this farm.'
                );
            }

            throw $error;
        }
    }
}

if (!function_exists('livestock_type_rename')) {
    function livestock_type_rename(
        PDO $pdo,
        int $farmId,
        int $livestockTypeId,
        string $name
    ): void {
        $existing = livestock_type_get(
            $pdo,
            $farmId,
            $livestockTypeId,
            false
        );

        $name = livestock_type_normalize_name($name);

        if ((string)$existing['name'] === $name) {
            return;
        }

        try {
            $stmt = $pdo->prepare(
                'UPDATE livestock_types
                 SET name = ?
                 WHERE id = ?
                   AND farm_id = ?'
            );

            $stmt->execute([
                $name,
                $livestockTypeId,
                $farmId,
            ]);
        } catch (Throwable $error) {
            if (livestock_type_is_duplicate_exception($error)) {
                throw new LivestockTypeException(
                    'A livestock type with this name already exists for this farm.'
                );
            }

            throw $error;
        }

        if (function_exists('audit_log_event')) {
            audit_log_event(
                'livestock_type_renamed',
                'livestock_type',
                $livestockTypeId,
                [
                    'previous_name' => (string)$existing['name'],
                    'name' => $name,
                ]
            );
        }
    }
}

if (!function_exists('livestock_type_set_active')) {
    function livestock_type_set_active(
        PDO $pdo,
        int $farmId,
        int $livestockTypeId,
        bool $isActive
    ): void {
        $existing = livestock_type_get(
            $pdo,
            $farmId,
            $livestockTypeId,
            false
        );

        $target = $isActive ? 1 : 0;

        if ((int)$existing['is_active'] === $target) {
            return;
        }

        $stmt = $pdo->prepare(
            'UPDATE livestock_types
             SET is_active = ?
             WHERE id = ?
               AND farm_id = ?'
        );

        $stmt->execute([
            $target,
            $livestockTypeId,
            $farmId,
        ]);

        if (function_exists('audit_log_event')) {
            audit_log_event(
                $isActive
                    ? 'livestock_type_activated'
                    : 'livestock_type_deactivated',
                'livestock_type',
                $livestockTypeId,
                [
                    'name' => (string)$existing['name'],
                    'is_active' => $target,
                ]
            );
        }
    }
}

if (!function_exists('livestock_type_validate_link')) {
    /**
     * Validate the canonical pair used by cycles and registry rows.
     *
     * Built-ins never carry livestock_type_id.
     * Generic legacy "other" is represented by NULL.
     * Custom farm-defined types are represented by ("other", positive id).
     */
    function livestock_type_validate_link(
        PDO $pdo,
        int $farmId,
        string $legacyType,
        ?int $livestockTypeId,
        bool $requireActiveCustom = true
    ): ?array {
        livestock_type_assert_farm_id($farmId);
        $legacyType = strtolower(trim($legacyType));
        $builtins = livestock_type_builtin_labels();

        if (isset($builtins[$legacyType])) {
            if ($livestockTypeId !== null) {
                throw new LivestockTypeException(
                    'Built-in livestock types cannot carry a custom livestock type ID.'
                );
            }

            return null;
        }

        if ($legacyType !== 'other') {
            throw new InvalidArgumentException(
                'Select a valid livestock type.'
            );
        }

        if ($livestockTypeId === null) {
            return null;
        }

        return livestock_type_get(
            $pdo,
            $farmId,
            $livestockTypeId,
            $requireActiveCustom
        );
    }
}

if (!function_exists('livestock_type_display_name')) {
    function livestock_type_display_name(
        PDO $pdo,
        int $farmId,
        string $legacyType,
        ?int $livestockTypeId
    ): string {
        $legacyType = strtolower(trim($legacyType));
        $builtins = livestock_type_builtin_labels();

        if (isset($builtins[$legacyType])) {
            livestock_type_validate_link(
                $pdo,
                $farmId,
                $legacyType,
                $livestockTypeId,
                false
            );

            return $builtins[$legacyType];
        }

        if ($legacyType !== 'other') {
            throw new InvalidArgumentException(
                'Select a valid livestock type.'
            );
        }

        if ($livestockTypeId === null) {
            return 'Other';
        }

        $type = livestock_type_validate_link(
            $pdo,
            $farmId,
            'other',
            $livestockTypeId,
            false
        );

        return (string)$type['name'];
    }
}

if (!function_exists('livestock_type_choices')) {
    /**
     * Canonical UI choice source.
     *
     * value is stable for forms:
     * - cattle/goat/sheep for built-ins
     * - other for generic compatibility rows
     * - custom:<id> for farm-defined types
     */
    function livestock_type_choices(
        PDO $pdo,
        int $farmId,
        bool $includeGenericOther = true,
        bool $includeInactiveCustom = false
    ): array {
        livestock_type_assert_farm_id($farmId);

        $choices = [];

        foreach (livestock_type_builtin_labels() as $key => $label) {
            $choices[] = [
                'value' => $key,
                'legacy_type' => $key,
                'livestock_type_id' => null,
                'label' => $label,
                'is_custom' => false,
                'is_active' => true,
            ];
        }

        if ($includeGenericOther) {
            $choices[] = [
                'value' => 'other',
                'legacy_type' => 'other',
                'livestock_type_id' => null,
                'label' => 'Other',
                'is_custom' => false,
                'is_active' => true,
            ];
        }

        foreach (
            livestock_type_list(
                $pdo,
                $farmId,
                $includeInactiveCustom
            ) as $type
        ) {
            $choices[] = [
                'value' => 'custom:' . (int)$type['id'],
                'legacy_type' => 'other',
                'livestock_type_id' => (int)$type['id'],
                'label' => (string)$type['name'],
                'is_custom' => true,
                'is_active' => (int)$type['is_active'] === 1,
            ];
        }

        return $choices;
    }
}

if (!function_exists('livestock_type_parse_choice')) {
    /**
     * Convert canonical form choice into the stored pair.
     * Database ownership/active validation remains livestock_type_validate_link().
     */
    function livestock_type_parse_choice(string $value): array
    {
        $value = strtolower(trim($value));

        if (isset(livestock_type_builtin_labels()[$value])) {
            return [
                'legacy_type' => $value,
                'livestock_type_id' => null,
            ];
        }

        if ($value === 'other') {
            return [
                'legacy_type' => 'other',
                'livestock_type_id' => null,
            ];
        }

        if (
            preg_match('/^custom:([1-9][0-9]*)$/', $value, $match) === 1
        ) {
            return [
                'legacy_type' => 'other',
                'livestock_type_id' => (int)$match[1],
            ];
        }

        throw new InvalidArgumentException(
            'Select a valid livestock type.'
        );
    }
}
