<?php

declare(strict_types=1);

/**
 * V3.1 private CLI authority bridge.
 *
 * Purpose:
 * - import an explicit whitelist of existing Apache SetEnv deployment
 *   authorities into a short-lived CLI process;
 * - keep secrets in their existing deployment authority;
 * - provide a strict pre-SEND readiness gate.
 *
 * This service never prints, persists, hashes, returns, or logs secret values.
 * Imported values live only in the current process environment.
 */

if (!function_exists('v310_private_cli_bridge_required_names')) {
    function v310_private_cli_bridge_required_names(): array
    {
        return [
            'DB_HOST',
            'DB_USER',
            'DB_PASS',
            'DB_NAME',

            'BILLING_PUBLIC_BASE_URL',

            'PLATFORM_MAIL_TRANSPORT',
            'PLATFORM_MAIL_FROM',

            'PLATFORM_SMTP_HOST',
            'PLATFORM_SMTP_PORT',
            'PLATFORM_SMTP_ENCRYPTION',
            'PLATFORM_SMTP_USERNAME',
            'PLATFORM_SMTP_PASSWORD',
        ];
    }
}

if (!function_exists('v310_private_cli_bridge_optional_names')) {
    function v310_private_cli_bridge_optional_names(): array
    {
        return [
            'APP_TIMEZONE',
            'PLATFORM_PUBLIC_BASE_URL',
            'PLATFORM_MAIL_ENABLED',
            'PLATFORM_MAIL_FROM_NAME',
            'PLATFORM_MAIL_REPLY_TO',
        ];
    }
}

if (!function_exists('v310_private_cli_bridge_allowed_names')) {
    function v310_private_cli_bridge_allowed_names(): array
    {
        return array_merge(
            v310_private_cli_bridge_required_names(),
            v310_private_cli_bridge_optional_names()
        );
    }
}

if (!function_exists('v310_private_cli_bridge_parse_value')) {
    function v310_private_cli_bridge_parse_value(
        string $raw,
        string $name
    ): string {
        $raw = trim($raw);

        if ($raw === '') {
            throw new RuntimeException(
                'Private CLI authority is empty: '
                . $name
            );
        }

        $first = substr($raw, 0, 1);
        $last = substr($raw, -1);

        $firstQuoted =
            $first === '"'
            || $first === "'";

        $lastQuoted =
            $last === '"'
            || $last === "'";

        if ($firstQuoted || $lastQuoted) {
            if (
                strlen($raw) < 2
                || $first !== $last
                || !in_array(
                    $first,
                    ['"', "'"],
                    true
                )
            ) {
                throw new RuntimeException(
                    'Private CLI authority has invalid quoting: '
                    . $name
                );
            }

            $raw =
                substr(
                    $raw,
                    1,
                    -1
                );
        }

        if (
            $raw === ''
            || str_contains($raw, "\0")
            || str_contains($raw, "\r")
            || str_contains($raw, "\n")
        ) {
            throw new RuntimeException(
                'Private CLI authority has an unsafe value shape: '
                . $name
            );
        }

        return $raw;
    }
}

if (!function_exists('v310_private_cli_bridge_read')) {
    function v310_private_cli_bridge_read(
        string $authorityPath
    ): array {
        $realPath =
            realpath($authorityPath);

        if (
            $realPath === false
            || !is_file($realPath)
            || !is_readable($realPath)
        ) {
            throw new RuntimeException(
                'Private CLI authority source is unavailable.'
            );
        }

        $allowed =
            array_fill_keys(
                v310_private_cli_bridge_allowed_names(),
                true
            );

        $values = [];

        $lines =
            file(
                $realPath,
                FILE_IGNORE_NEW_LINES
            );

        if ($lines === false) {
            throw new RuntimeException(
                'Private CLI authority source could not be read.'
            );
        }

        foreach ($lines as $line) {
            if (
                !preg_match(
                    '/^[ \t]*SetEnv[ \t]+'
                    . '([A-Z][A-Z0-9_]*)'
                    . '[ \t]+(.+?)[ \t]*$/',
                    $line,
                    $match
                )
            ) {
                continue;
            }

            $name =
                (string)$match[1];

            if (!isset($allowed[$name])) {
                continue;
            }

            if (array_key_exists($name, $values)) {
                throw new RuntimeException(
                    'Private CLI authority appears more than once: '
                    . $name
                );
            }

            $values[$name] =
                v310_private_cli_bridge_parse_value(
                    (string)$match[2],
                    $name
                );
        }

        foreach (
            v310_private_cli_bridge_required_names()
            as $name
        ) {
            if (!array_key_exists($name, $values)) {
                throw new RuntimeException(
                    'Required private CLI authority is missing: '
                    . $name
                );
            }
        }

        return $values;
    }
}

if (!function_exists('v310_private_cli_bridge_import')) {
    function v310_private_cli_bridge_import(
        string $authorityPath
    ): void {
        $values =
            v310_private_cli_bridge_read(
                $authorityPath
            );

        foreach ($values as $name => $value) {
            if (!putenv($name . '=' . $value)) {
                throw new RuntimeException(
                    'Private CLI authority could not be imported: '
                    . $name
                );
            }

            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }

        /*
         * Do not return values.
         * Do not print values.
         * Do not persist values.
         */
    }
}

if (!function_exists('v310_private_cli_env_value')) {
    function v310_private_cli_env_value(
        string $name
    ): string {
        $value =
            getenv($name);

        return is_string($value)
            ? trim($value)
            : '';
    }
}

if (!function_exists('v310_private_cli_database_authority_ready')) {
    function v310_private_cli_database_authority_ready(): bool
    {
        foreach (
            [
                'DB_HOST',
                'DB_USER',
                'DB_PASS',
                'DB_NAME',
            ]
            as $name
        ) {
            if (
                v310_private_cli_env_value($name)
                === ''
            ) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('v310_private_cli_public_url_ready')) {
    function v310_private_cli_public_url_ready(): bool
    {
        foreach (
            [
                'PLATFORM_PUBLIC_BASE_URL',
                'BILLING_PUBLIC_BASE_URL',
            ]
            as $name
        ) {
            $url =
                v310_private_cli_env_value(
                    $name
                );

            if ($url === '') {
                continue;
            }

            $parts =
                parse_url($url);

            if (
                is_array($parts)
                && strtolower(
                    (string)($parts['scheme'] ?? '')
                ) === 'https'
                && (string)($parts['host'] ?? '') !== ''
                && !isset($parts['user'])
                && !isset($parts['pass'])
                && !isset($parts['fragment'])
            ) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('v310_private_cli_mail_authority_ready')) {
    function v310_private_cli_mail_authority_ready(): bool
    {
        if (
            strtolower(
                v310_private_cli_env_value(
                    'PLATFORM_MAIL_TRANSPORT'
                )
            ) !== 'smtp'
        ) {
            return false;
        }

        $from =
            strtolower(
                v310_private_cli_env_value(
                    'PLATFORM_MAIL_FROM'
                )
            );

        if (
            filter_var(
                $from,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            return false;
        }

        if (
            v310_private_cli_env_value(
                'PLATFORM_SMTP_HOST'
            ) === ''
            || v310_private_cli_env_value(
                'PLATFORM_SMTP_USERNAME'
            ) === ''
            || v310_private_cli_env_value(
                'PLATFORM_SMTP_PASSWORD'
            ) === ''
        ) {
            return false;
        }

        $portText =
            v310_private_cli_env_value(
                'PLATFORM_SMTP_PORT'
            );

        if (
            !ctype_digit($portText)
            || (int)$portText < 1
            || (int)$portText > 65535
        ) {
            return false;
        }

        $encryption =
            strtolower(
                v310_private_cli_env_value(
                    'PLATFORM_SMTP_ENCRYPTION'
                )
            );

        return in_array(
            $encryption,
            ['ssl', 'tls'],
            true
        );
    }
}

if (!function_exists('v310_private_cli_send_authority_ready')) {
    function v310_private_cli_send_authority_ready(): bool
    {
        return
            v310_private_cli_database_authority_ready()
            && v310_private_cli_public_url_ready()
            && v310_private_cli_mail_authority_ready();
    }
}
