<?php

declare(strict_types=1);

/**
 * Central tenant Farm Profile authority.
 *
 * Scope:
 * - farm display name;
 * - Farm Workspace ID (farms.slug);
 * - tenant primary colour;
 * - tenant logo validation/storage;
 * - tenant profile lookup;
 * - workspace-ID uniqueness.
 *
 * This service deliberately does NOT own:
 * - subscription plan/status/dates;
 * - Farm Admin credentials;
 * - team users/roles;
 * - billing/payment rules;
 * - tenant entitlements/modules.
 *
 * Those remain under their existing shared authorities.
 *
 * farms.id is the immutable tenant key. Changing farms.slug must never
 * re-key or recreate tenant-owned records.
 */

if (!function_exists('farm_profile_reserved_workspace_ids')) {
    function farm_profile_reserved_workspace_ids(): array
    {
        return [
            'owner',
        ];
    }
}

if (!function_exists('farm_profile_normalize_name')) {
    function farm_profile_normalize_name(
        string $name
    ): string {
        return trim($name);
    }
}

if (!function_exists('farm_profile_normalize_workspace_id')) {
    function farm_profile_normalize_workspace_id(
        string $workspaceId
    ): string {
        return strtolower(
            trim($workspaceId)
        );
    }
}

if (!function_exists('farm_profile_normalize_primary_color')) {
    function farm_profile_normalize_primary_color(
        string $color
    ): string {
        $color = trim($color);

        return $color === ''
            ? '#198754'
            : $color;
    }
}

if (!function_exists('farm_profile_validate_name')) {
    function farm_profile_validate_name(
        string $name
    ): void {
        if (
            farm_profile_normalize_name($name)
            === ''
        ) {
            throw new InvalidArgumentException(
                'Farm name is required.'
            );
        }
    }
}

if (!function_exists('farm_profile_validate_workspace_id')) {
    function farm_profile_validate_workspace_id(
        string $workspaceId
    ): void {
        $workspaceId =
            farm_profile_normalize_workspace_id(
                $workspaceId
            );

        if (
            !preg_match(
                '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $workspaceId
            )
        ) {
            throw new InvalidArgumentException(
                'Farm Workspace ID must use lowercase letters, numbers, and single hyphens only.'
            );
        }

        if (
            in_array(
                $workspaceId,
                farm_profile_reserved_workspace_ids(),
                true
            )
        ) {
            throw new InvalidArgumentException(
                'That Farm Workspace ID is reserved by the platform.'
            );
        }
    }
}

if (!function_exists('farm_profile_validate_primary_color')) {
    function farm_profile_validate_primary_color(
        string $color
    ): void {
        $color =
            farm_profile_normalize_primary_color(
                $color
            );

        if (
            !preg_match(
                '/^#[0-9a-fA-F]{6}$/',
                $color
            )
        ) {
            throw new InvalidArgumentException(
                'Primary colour must be a valid six-digit hex colour.'
            );
        }
    }
}

if (!function_exists('farm_profile_normalize_identity')) {
    function farm_profile_normalize_identity(
        array $input
    ): array {
        $profile = [
            'name' =>
                farm_profile_normalize_name(
                    (string)($input['name'] ?? '')
                ),

            'slug' =>
                farm_profile_normalize_workspace_id(
                    (string)($input['slug'] ?? '')
                ),

            'primary_color' =>
                farm_profile_normalize_primary_color(
                    (string)(
                        $input['primary_color']
                        ?? '#198754'
                    )
                ),
        ];

        farm_profile_validate_name(
            $profile['name']
        );

        farm_profile_validate_workspace_id(
            $profile['slug']
        );

        farm_profile_validate_primary_color(
            $profile['primary_color']
        );

        return $profile;
    }
}

if (!function_exists('farm_profile_load')) {
    function farm_profile_load(
        PDO $pdo,
        int $farmId,
        bool $allowPlatformWorkspace = false
    ): ?array {
        if ($farmId < 1) {
            return null;
        }

        if ($allowPlatformWorkspace) {
            $stmt = $pdo->prepare(
                'SELECT
                    id,
                    name,
                    slug,
                    logo_path,
                    primary_color,
                    contact_name,
                    contact_email,
                    subscription_plan,
                    subscription_status,
                    trial_ends_at,
                    subscription_starts_at,
                    subscription_ends_at,
                    created_at
                 FROM farms
                 WHERE id = ?
                 LIMIT 1'
            );

            $stmt->execute([
                $farmId,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'SELECT
                    id,
                    name,
                    slug,
                    logo_path,
                    primary_color,
                    contact_name,
                    contact_email,
                    subscription_plan,
                    subscription_status,
                    trial_ends_at,
                    subscription_starts_at,
                    subscription_ends_at,
                    created_at
                 FROM farms
                 WHERE id = ?
                   AND slug <> ?
                 LIMIT 1'
            );

            $reserved =
                farm_profile_reserved_workspace_ids();

            $stmt->execute([
                $farmId,
                $reserved[0],
            ]);
        }

        $row =
            $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

if (!function_exists('farm_profile_workspace_id_available')) {
    function farm_profile_workspace_id_available(
        PDO $pdo,
        string $workspaceId,
        int $excludeFarmId = 0
    ): bool {
        $workspaceId =
            farm_profile_normalize_workspace_id(
                $workspaceId
            );

        farm_profile_validate_workspace_id(
            $workspaceId
        );

        if ($excludeFarmId > 0) {
            $stmt = $pdo->prepare(
                'SELECT id
                 FROM farms
                 WHERE slug = ?
                   AND id <> ?
                 LIMIT 1'
            );

            $stmt->execute([
                $workspaceId,
                $excludeFarmId,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'SELECT id
                 FROM farms
                 WHERE slug = ?
                 LIMIT 1'
            );

            $stmt->execute([
                $workspaceId,
            ]);
        }

        return
            $stmt->fetchColumn()
            === false;
    }
}

if (!function_exists('farm_profile_assert_workspace_id_available')) {
    function farm_profile_assert_workspace_id_available(
        PDO $pdo,
        string $workspaceId,
        int $excludeFarmId = 0
    ): void {
        if (
            !farm_profile_workspace_id_available(
                $pdo,
                $workspaceId,
                $excludeFarmId
            )
        ) {
            throw new InvalidArgumentException(
                'That Farm Workspace ID is already in use.'
            );
        }
    }
}

if (!function_exists('farm_profile_detect_logo_extension')) {
    function farm_profile_detect_logo_extension(
        ?array $file
    ): ?string {
        if (
            empty($file['tmp_name'])
        ) {
            return null;
        }

        if (
            ($file['error'] ?? UPLOAD_ERR_NO_FILE)
                !== UPLOAD_ERR_OK
            || ($file['size'] ?? 0)
                > 2 * 1024 * 1024
        ) {
            throw new RuntimeException(
                'Logo upload must be an image smaller than 2 MB.'
            );
        }

        $mime = null;

        if (
            class_exists('finfo')
        ) {
            $finfo =
                new finfo(
                    FILEINFO_MIME_TYPE
                );

            $mime =
                $finfo->file(
                    $file['tmp_name']
                );
        }

        $mimeExtensions = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        $imageInfo =
            @getimagesize(
                $file['tmp_name']
            );

        $imageType =
            $imageInfo[2]
            ?? null;

        $extensions = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
        ];

        $extension =
            $extensions[$imageType]
            ?? $mimeExtensions[$mime]
            ?? null;

        if ($extension === null) {
            throw new RuntimeException(
                'Logo must contain valid JPG, PNG, or WebP image data. Try re-exporting the image as JPG before uploading.'
            );
        }

        return $extension;
    }
}

if (!function_exists('farm_profile_save_logo_upload')) {
    function farm_profile_save_logo_upload(
        ?array $file,
        int $farmId,
        ?string $existing = null,
        ?string $validatedExtension = null
    ): ?string {
        if ($farmId < 1) {
            throw new InvalidArgumentException(
                'A valid farm is required before saving a logo.'
            );
        }

        if (
            empty($file['tmp_name'])
        ) {
            return $existing;
        }

        $extension =
            $validatedExtension
            ?? farm_profile_detect_logo_extension(
                $file
            );

        if ($extension === null) {
            return $existing;
        }

        $directory =
            dirname(__DIR__)
            . '/uploads/farms';

        if (
            !is_dir($directory)
            && !mkdir(
                $directory,
                0755,
                true
            )
            && !is_dir($directory)
        ) {
            throw new RuntimeException(
                'Unable to create the logo directory.'
            );
        }

        $filename =
            $farmId
            . '-'
            . bin2hex(
                random_bytes(12)
            )
            . '.'
            . $extension;

        $target =
            $directory
            . '/'
            . $filename;

        if (
            !move_uploaded_file(
                $file['tmp_name'],
                $target
            )
        ) {
            throw new RuntimeException(
                'Unable to save the logo.'
            );
        }

        return
            '/uploads/farms/'
            . $filename;
    }
}

if (!function_exists('farm_profile_update_identity')) {
    function farm_profile_update_identity(
        PDO $pdo,
        int $farmId,
        array $input,
        ?string $logoPath
    ): array {
        if ($farmId < 1) {
            throw new InvalidArgumentException(
                'A valid farm is required.'
            );
        }

        $profile =
            farm_profile_normalize_identity(
                $input
            );

        farm_profile_assert_workspace_id_available(
            $pdo,
            $profile['slug'],
            $farmId
        );

        $stmt = $pdo->prepare(
            'UPDATE farms
             SET
                name = ?,
                slug = ?,
                primary_color = ?,
                logo_path = ?
             WHERE id = ?
               AND slug <> ?'
        );

        $reserved =
            farm_profile_reserved_workspace_ids();

        $stmt->execute([
            $profile['name'],
            $profile['slug'],
            $profile['primary_color'],
            $logoPath,
            $farmId,
            $reserved[0],
        ]);

        if ($stmt->rowCount() < 1) {
            $existing =
                farm_profile_load(
                    $pdo,
                    $farmId
                );

            if ($existing === null) {
                throw new RuntimeException(
                    'That farm cannot be edited.'
                );
            }
        }

        $updated =
            farm_profile_load(
                $pdo,
                $farmId
            );

        if ($updated === null) {
            throw new RuntimeException(
                'Farm profile could not be reloaded after update.'
            );
        }

        return $updated;
    }
}
