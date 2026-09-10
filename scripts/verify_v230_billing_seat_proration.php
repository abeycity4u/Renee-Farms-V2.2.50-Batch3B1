<?php
/**
 * Static/pure contract verifier for V2.3 seat proration.
 *
 * No config.php, database connection, payment provider or mutation.
 */

$root = dirname(__DIR__);
$servicePath = $root . '/includes/billing_seat_proration.php';

if (!is_file($servicePath)) {
    fwrite(STDERR, "FAIL: seat proration service is missing.\n");
    exit(1);
}

require_once $servicePath;

$source = (string)file_get_contents($servicePath);

$checks = 0;
$failures = 0;

$check = static function (
    bool $ok,
    string $label
) use (&$checks, &$failures): void {
    $checks++;

    if ($ok) {
        echo "PASS: {$label}\n";
        return;
    }

    $failures++;
    echo "FAIL: {$label}\n";
};

$check(
    strpos(
        $source,
        "b.status = 'paid'"
    ) !== false
    && strpos(
        $source,
        "b.purpose = 'subscription'"
    ) !== false,
    'timeline anchors only to verified paid subscription-purpose history'
);

$check(
    strpos(
        $source,
        "s.change_reason = 'billing_payment_applied'"
    ) !== false,
    'authoritative paid lineage requires billing-payment application history'
);

$check(
    strpos(
        $source,
        'Current commercial product does not match the latest paid subscription lineage.'
    ) !== false,
    'current product must match latest paid lineage'
);

$check(
    strpos(
        $source,
        'Current billing period does not match the authoritative paid subscription end.'
    ) !== false,
    'latest commercial period must match paid lineage end'
);

$check(
    strpos(
        $source,
        "['subscription_ends_at',"
    ) !== false
    || strpos(
        $source,
        "'subscription_ends_at',"
    ) !== false,
    'recorded subscription end boundaries participate in lineage'
);

$check(
    strpos(
        $source,
        '$futureFullPeriods++'
    ) !== false,
    'future already-paid intervals are counted from payment history'
);

$check(
    strpos(
        $source,
        'intdiv('
    ) !== false
    && strpos(
        $source,
        'intdiv($duration, 2)'
    ) !== false,
    'partial-period money uses integer half-up rounding'
);

$check(
    strpos(
        $source,
        'billing_pricing_price_book()'
    ) !== false,
    'seat unit amount comes from canonical server-side price book'
);

$check(
    strpos(
        $source,
        'subscription_seat_role_relevant'
    ) !== false,
    'module-specific seat relevance is enforced'
);

$check(
    strpos(
        $source,
        'subscription_seat_assert_capacity'
    ) !== false,
    'target seat capacity uses canonical seat policy'
);

$protectedWrite =
    '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM|REPLACE\s+INTO)\b/i';

$check(
    !preg_match($protectedWrite, $source),
    'proration service performs no database DML'
);

$networkPattern =
    '/\bcurl_(?:init|exec)|https?:\/\//i';

$check(
    !preg_match($networkPattern, $source),
    'proration service performs no provider or network call'
);

$tz = new DateTimeZone('Africa/Lagos');
$now = new DateTimeImmutable(
    '2026-09-10 20:35:59',
    $tz
);

$farm14 = billing_seat_proration_amount(
    $now,
    new DateTimeImmutable(
        '2026-09-07 23:59:59',
        $tz
    ),
    new DateTimeImmutable(
        '2026-10-07 23:59:59',
        $tz
    ),
    200000,
    0,
    1
);

$check(
    $farm14['partial_unit_minor'] === 180944
    && $farm14['total_minor'] === 180944,
    'Farm 14 lineage vector prorates one 2000 NGN seat to 1809.44'
);

$farm15 = billing_seat_proration_amount(
    $now,
    new DateTimeImmutable(
        '2026-09-06 00:00:00',
        $tz
    ),
    new DateTimeImmutable(
        '2026-10-05 23:59:59',
        $tz
    ),
    200000,
    4,
    1
);

$check(
    $farm15['partial_unit_minor'] === 167611
    && $farm15['future_full_periods'] === 4
    && $farm15['total_minor'] === 967611,
    'Farm 15 lineage vector charges partial current interval plus four prepaid future months'
);

$farm22 = billing_seat_proration_amount(
    $now,
    new DateTimeImmutable(
        '2026-09-09 00:58:47',
        $tz
    ),
    new DateTimeImmutable(
        '2026-10-09 00:58:47',
        $tz
    ),
    200000,
    0,
    1
);

$check(
    $farm22['partial_unit_minor'] === 187883
    && $farm22['total_minor'] === 187883,
    'Farm 22 vector preserves exact provider-paid timestamp boundary'
);

$twoSeats = billing_seat_proration_amount(
    $now,
    new DateTimeImmutable(
        '2026-09-07 23:59:59',
        $tz
    ),
    new DateTimeImmutable(
        '2026-10-07 23:59:59',
        $tz
    ),
    200000,
    0,
    2
);

$check(
    $twoSeats['total_minor']
        === (2 * $farm14['total_minor']),
    'multi-seat quote is an exact multiple of the per-seat amount'
);

$half = billing_seat_proration_amount(
    new DateTimeImmutable(
        '2026-01-01 00:00:01',
        $tz
    ),
    new DateTimeImmutable(
        '2026-01-01 00:00:00',
        $tz
    ),
    new DateTimeImmutable(
        '2026-01-01 00:00:02',
        $tz
    ),
    101,
    0,
    1
);

$check(
    $half['partial_unit_minor'] === 51,
    'half-cent-equivalent fractional result rounds half-up in minor units'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    exit(1);
}

echo "V2.3 BILLING SEAT PRORATION CONTRACT: PASSED\n";
