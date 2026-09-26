<?php

declare(strict_types=1);

/**
 * Canonical absolute public URL authority for Renee Farms.
 *
 * Contract:
 * - PLATFORM_PUBLIC_BASE_URL is the preferred deployment authority;
 * - BILLING_PUBLIC_BASE_URL remains a compatibility fallback while
 *   existing V2.3 deployments transition;
 * - public origins are never derived from request Host headers;
 * - only absolute HTTPS application origins are accepted;
 * - configured base URLs may include an application subdirectory;
 * - configured base URLs may not contain credentials, query, or fragment.
 *
 * This service owns URL construction only. It does not own HTTP redirects,
 * billing policy, account credentials, or mail transport.
 */

if (!function_exists('platform_public_url_env')) {
    function platform_public_url_env(string $name): string
    {
        if (defined($name)) {
            return trim((string)constant($name));
        }

        $value = getenv($name);

        if (
            $value === false
            && array_key_exists($name, $_ENV)
        ) {
            $value = $_ENV[$name];
        }

        if (
            $value === false
            && array_key_exists($name, $_SERVER)
        ) {
            $value = $_SERVER[$name];
        }

        return trim(
            (string)($value === false ? '' : $value)
        );
    }
}

if (!function_exists('platform_public_base_url')) {
    function platform_public_base_url(): string
    {
        $raw =
            platform_public_url_env(
                'PLATFORM_PUBLIC_BASE_URL'
            );

        if ($raw === '') {
            /*
             * Backward-compatible bridge for existing deployments.
             * This can be retired only after production configuration
             * has moved to PLATFORM_PUBLIC_BASE_URL.
             */
            $raw =
                platform_public_url_env(
                    'BILLING_PUBLIC_BASE_URL'
                );
        }

        if ($raw === '') {
            throw new RuntimeException(
                'Platform public URL is not configured. '
                . 'Set PLATFORM_PUBLIC_BASE_URL.'
            );
        }

        $parts = parse_url($raw);

        if (
            $parts === false
            || strtolower(
                (string)($parts['scheme'] ?? '')
            ) !== 'https'
            || trim(
                (string)($parts['host'] ?? '')
            ) === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw new RuntimeException(
                'PLATFORM_PUBLIC_BASE_URL must be an absolute HTTPS '
                . 'application URL without credentials, query or fragment.'
            );
        }

        $host = strtolower(
            (string)$parts['host']
        );

        /*
         * parse_url() strips IPv6 brackets. Restore them when needed.
         */
        if (
            str_contains($host, ':')
            && !str_starts_with($host, '[')
        ) {
            $host = '[' . $host . ']';
        }

        $port = isset($parts['port'])
            ? ':' . (int)$parts['port']
            : '';

        $path = rtrim(
            (string)($parts['path'] ?? ''),
            '/'
        );

        return 'https://'
            . $host
            . $port
            . $path;
    }
}

if (!function_exists('platform_public_url')) {
    function platform_public_url(
        string $relativePath,
        array $query = []
    ): string {
        $relativePath = trim($relativePath);

        if (
            $relativePath === ''
            || str_contains($relativePath, "\0")
        ) {
            throw new InvalidArgumentException(
                'A valid public application path is required.'
            );
        }

        /*
         * Callers supply only an application-relative path.
         * This prevents a caller from replacing the canonical origin.
         */
        if (
            str_starts_with($relativePath, '//')
            || preg_match(
                '#^[a-z][a-z0-9+.-]*://#i',
                $relativePath
            )
        ) {
            throw new InvalidArgumentException(
                'Public application paths must be relative.'
            );
        }

        $relativePath =
            '/' . ltrim($relativePath, '/');

        $url =
            platform_public_base_url()
            . $relativePath;

        if ($query) {
            $url .= '?'
                . http_build_query(
                    $query,
                    '',
                    '&',
                    PHP_QUERY_RFC3986
                );
        }

        return $url;
    }
}
