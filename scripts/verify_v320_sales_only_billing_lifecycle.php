<?php
/**
 * V3.2 Sales-only renewal / seat / recovery lifecycle verifier.
 *
 * Focus:
 * - Sales-only commercial product identity;
 * - Sales-only seat-role relevance;
 * - recovery workspace commercial-product language;
 * - recovery delegates to shared current-product service;
 * - seat reduction remains shared-role-policy driven;
 * - seat top-up remains shared-proration driven;
 * - paid application remains centralized by purpose.
 *
 * No DB mutation and no provider/network work.
 */

$root = dirname(__DIR__);

require_once $root . '/includes/billing_commercial_product.php';
require_once $root . '/includes/subscription_seat_policy.php';

$recoverPath =
    $root . '/billing/recover.php';

$reductionPath =
    $root . '/includes/billing_seat_reduction_initiation.php';

$reactivationPath =
    $root . '/includes/billing_reactivation_quote.php';

$topupPath =
    $root . '/includes/billing_seat_topup_initiation.php';

$dispatcherPath =
    $root . '/includes/billing_paid_attempt_dispatcher.php';

$files = [
    $recoverPath,
    $reductionPath,
    $reactivationPath,
    $topupPath,
    $dispatcherPath,
];

foreach ($files as $file) {
    if (!is_file($file)) {
        fwrite(
            STDERR,
            'FAIL: missing lifecycle file: '
            . basename($file)
            . PHP_EOL
        );
        exit(1);
    }
}

$recover = file_get_contents($recoverPath);
$reduction = file_get_contents($reductionPath);
$reactivation = file_get_contents($reactivationPath);
$topup = file_get_contents($topupPath);
$dispatcher = file_get_contents($dispatcherPath);

if ($recover === false
    || $reduction === false
    || $reactivation === false
    || $topup === false
    || $dispatcher === false) {
    fwrite(
        STDERR,
        "FAIL: unable to read billing lifecycle sources.\n"
    );
    exit(1);
}

$checks = 0;
$failures = 0;

$check = static function (
    bool $ok,
    string $message
) use (&$checks, &$failures): void {
    $checks++;

    if ($ok) {
        echo "PASS: {$message}\n";
        return;
    }

    $failures++;
    echo "FAIL: {$message}\n";
};

$check(
    billing_commercial_product_label(['sales'])
        === 'Sales',
    'standalone Sales resolves to Sales commercial-product label'
);

$check(
    subscription_seat_role_relevant(
        'sales_rep',
        ['sales']
    ) === true,
    'Sales-only permits Sales Representative seats'
);

$check(
    subscription_seat_role_relevant(
        'viewer',
        ['sales']
    ) === true,
    'Sales-only permits Viewer seats'
);

$check(
    subscription_seat_role_relevant(
        'poultry_manager',
        ['sales']
    ) === false,
    'Sales-only rejects Poultry Manager seats'
);

$check(
    subscription_seat_role_relevant(
        'ruminant_manager',
        ['sales']
    ) === false,
    'Sales-only rejects Ruminant Manager seats'
);

$check(
    strpos(
        $recover,
        "billing_commercial_product.php"
    ) !== false
    && strpos(
        $recover,
        'billing_commercial_product_label('
    ) !== false,
    'recovery delegates product labeling to shared commercial-product policy'
);

$check(
    strpos(
        $recover,
        '>Commercial product<'
    ) !== false,
    'recovery presents Commercial product'
);

$check(
    stripos(
        $recover,
        'livestock bundle'
    ) === false
    && stripos(
        $recover,
        'livestock setup'
    ) === false,
    'recovery contains no livestock-only commercial wording'
);

$check(
    strpos(
        $reactivation,
        'billing_current_product('
    ) !== false
    && strpos(
        $reactivation,
        'billing_current_product_assert_selection('
    ) !== false,
    'recovery quote and selection assertion remain centralized'
);

$check(
    strpos(
        $reduction,
        'subscription_seat_role_relevant('
    ) !== false
    && strpos(
        $reduction,
        'commercial subscription'
    ) !== false
    && stripos(
        $reduction,
        'livestock subscription'
    ) === false,
    'seat reduction stays role-policy driven with commercial-neutral messaging'
);

$check(
    strpos(
        $topup,
        'billing_seat_proration_quote('
    ) !== false
    && strpos(
        $topup,
        "'seat_topup'"
    ) !== false,
    'seat top-up remains shared-proration driven with dedicated payment purpose'
);

$check(
    strpos(
        $dispatcher,
        "if (\$purpose === 'subscription')"
    ) !== false
    && strpos(
        $dispatcher,
        'billing_subscription_apply_paid_attempt('
    ) !== false
    && strpos(
        $dispatcher,
        "if (\$purpose === 'seat_topup')"
    ) !== false
    && strpos(
        $dispatcher,
        'billing_seat_topup_apply_paid_attempt('
    ) !== false,
    'paid dispatcher keeps subscription and seat-top-up application centralized'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V3.2 SALES-ONLY BILLING LIFECYCLE: FAILED\n";
    exit(1);
}

echo "V3.2 SALES-ONLY BILLING LIFECYCLE: PASSED\n";
?>
