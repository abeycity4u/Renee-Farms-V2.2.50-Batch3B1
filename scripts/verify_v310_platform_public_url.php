<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$service =
    $root . '/includes/platform_public_url.php';

$failures = 0;

function check_v310_public_url(
    bool $condition,
    string $message
): void {
    global $failures;

    if ($condition) {
        echo 'PASS: ' . $message . PHP_EOL;
        return;
    }

    echo 'FAIL: ' . $message . PHP_EOL;
    $failures++;
}

check_v310_public_url(
    is_file($service),
    'platform public URL service exists'
);

if (!is_file($service)) {
    echo "V310_PLATFORM_PUBLIC_URL_CONTRACT=FAIL\n";
    exit(1);
}

$source =
    (string)file_get_contents($service);

check_v310_public_url(
    str_contains(
        $source,
        'function platform_public_url_env('
    ),
    'public URL environment reader is centralized'
);

check_v310_public_url(
    str_contains(
        $source,
        'function platform_public_base_url('
    ),
    'canonical public base URL function exists'
);

check_v310_public_url(
    str_contains(
        $source,
        'function platform_public_url('
    ),
    'canonical absolute public URL builder exists'
);

check_v310_public_url(
    str_contains(
        $source,
        "'PLATFORM_PUBLIC_BASE_URL'"
    ),
    'platform public base has a neutral deployment authority'
);

check_v310_public_url(
    str_contains(
        $source,
        "'BILLING_PUBLIC_BASE_URL'"
    ),
    'legacy billing public base remains a compatibility fallback'
);

check_v310_public_url(
    strpos(
        $source,
        "'PLATFORM_PUBLIC_BASE_URL'"
    )
    < strpos(
        $source,
        "'BILLING_PUBLIC_BASE_URL'"
    ),
    'neutral platform authority is preferred before billing fallback'
);

check_v310_public_url(
    str_contains(
        $source,
        "!== 'https'"
    ),
    'public URL authority requires HTTPS'
);

check_v310_public_url(
    str_contains(
        $source,
        "isset(\$parts['user'])"
    )
    && str_contains(
        $source,
        "isset(\$parts['pass'])"
    ),
    'configured public origin rejects embedded credentials'
);

check_v310_public_url(
    str_contains(
        $source,
        "isset(\$parts['query'])"
    )
    && str_contains(
        $source,
        "isset(\$parts['fragment'])"
    ),
    'configured public origin rejects query and fragment'
);

check_v310_public_url(
    !str_contains(
        $source,
        'HTTP_HOST'
    )
    && !str_contains(
        $source,
        'SERVER_NAME'
    )
    && !str_contains(
        $source,
        'REQUEST_SCHEME'
    ),
    'public origin is never derived from request host data'
);

check_v310_public_url(
    str_contains(
        $source,
        'PHP_QUERY_RFC3986'
    ),
    'public URL query strings use RFC3986 encoding'
);

check_v310_public_url(
    str_contains(
        $source,
        "str_starts_with(\$relativePath, '//')"
    )
    && str_contains(
        $source,
        "#^[a-z][a-z0-9+.-]*://#i"
    ),
    'URL builder rejects caller-supplied absolute origins'
);

check_v310_public_url(
    !preg_match(
        '/\bheader\s*\(|\bmail\s*\(|platform_mail_send|PDO\b/i',
        $source
    ),
    'URL service owns neither HTTP redirects, mail nor persistence'
);

/*
 * Behavior checks run with isolated environment values.
 */
require_once $service;

$oldPlatform =
    getenv('PLATFORM_PUBLIC_BASE_URL');

$oldBilling =
    getenv('BILLING_PUBLIC_BASE_URL');

$restore = static function (
    string $name,
    $value
): void {
    if ($value === false) {
        putenv($name);
        unset($_ENV[$name], $_SERVER[$name]);
        return;
    }

    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
};

try {
    putenv(
        'PLATFORM_PUBLIC_BASE_URL=https://app.example.test/farms/'
    );

    $_ENV['PLATFORM_PUBLIC_BASE_URL'] =
        'https://app.example.test/farms/';

    $_SERVER['PLATFORM_PUBLIC_BASE_URL'] =
        'https://app.example.test/farms/';

    putenv(
        'BILLING_PUBLIC_BASE_URL=https://legacy.example.test/old'
    );

    $_ENV['BILLING_PUBLIC_BASE_URL'] =
        'https://legacy.example.test/old';

    $_SERVER['BILLING_PUBLIC_BASE_URL'] =
        'https://legacy.example.test/old';

    check_v310_public_url(
        platform_public_base_url()
            === 'https://app.example.test/farms',
        'platform authority wins over billing fallback'
    );

    check_v310_public_url(
        platform_public_url(
            '/account/activate.php',
            ['token' => 'a+b/c=']
        )
            ===
        'https://app.example.test/farms/account/activate.php'
        . '?token=a%2Bb%2Fc%3D',
        'absolute public links preserve application path and encode query'
    );

    putenv('PLATFORM_PUBLIC_BASE_URL');
    unset(
        $_ENV['PLATFORM_PUBLIC_BASE_URL'],
        $_SERVER['PLATFORM_PUBLIC_BASE_URL']
    );

    check_v310_public_url(
        platform_public_base_url()
            === 'https://legacy.example.test/old',
        'existing billing public base works during compatibility transition'
    );

    putenv(
        'PLATFORM_PUBLIC_BASE_URL=http://unsafe.example.test'
    );

    $_ENV['PLATFORM_PUBLIC_BASE_URL'] =
        'http://unsafe.example.test';

    $_SERVER['PLATFORM_PUBLIC_BASE_URL'] =
        'http://unsafe.example.test';

    $httpRejected = false;

    try {
        platform_public_base_url();
    } catch (RuntimeException $e) {
        $httpRejected = true;
    }

    check_v310_public_url(
        $httpRejected,
        'non-HTTPS platform origin is rejected'
    );

    putenv(
        'PLATFORM_PUBLIC_BASE_URL=https://user:secret@app.example.test'
    );

    $_ENV['PLATFORM_PUBLIC_BASE_URL'] =
        'https://user:secret@app.example.test';

    $_SERVER['PLATFORM_PUBLIC_BASE_URL'] =
        'https://user:secret@app.example.test';

    $userinfoRejected = false;

    try {
        platform_public_base_url();
    } catch (RuntimeException $e) {
        $userinfoRejected = true;
    }

    check_v310_public_url(
        $userinfoRejected,
        'public base credentials are rejected'
    );

    putenv(
        'PLATFORM_PUBLIC_BASE_URL=https://app.example.test?x=1'
    );

    $_ENV['PLATFORM_PUBLIC_BASE_URL'] =
        'https://app.example.test?x=1';

    $_SERVER['PLATFORM_PUBLIC_BASE_URL'] =
        'https://app.example.test?x=1';

    $queryRejected = false;

    try {
        platform_public_base_url();
    } catch (RuntimeException $e) {
        $queryRejected = true;
    }

    check_v310_public_url(
        $queryRejected,
        'configured public base query is rejected'
    );

    putenv(
        'PLATFORM_PUBLIC_BASE_URL=https://app.example.test'
    );

    $_ENV['PLATFORM_PUBLIC_BASE_URL'] =
        'https://app.example.test';

    $_SERVER['PLATFORM_PUBLIC_BASE_URL'] =
        'https://app.example.test';

    $absolutePathRejected = false;

    try {
        platform_public_url(
            'https://evil.example.test/reset'
        );
    } catch (InvalidArgumentException $e) {
        $absolutePathRejected = true;
    }

    check_v310_public_url(
        $absolutePathRejected,
        'caller cannot replace canonical origin'
    );

    $schemeRelativeRejected = false;

    try {
        platform_public_url(
            '//evil.example.test/reset'
        );
    } catch (InvalidArgumentException $e) {
        $schemeRelativeRejected = true;
    }

    check_v310_public_url(
        $schemeRelativeRejected,
        'scheme-relative external origin is rejected'
    );
} finally {
    $restore(
        'PLATFORM_PUBLIC_BASE_URL',
        $oldPlatform
    );

    $restore(
        'BILLING_PUBLIC_BASE_URL',
        $oldBilling
    );
}

if ($failures > 0) {
    echo "V310_PLATFORM_PUBLIC_URL_CONTRACT=FAIL\n";
    exit(1);
}

echo "V310_PLATFORM_PUBLIC_URL_CONTRACT=PASS\n";
