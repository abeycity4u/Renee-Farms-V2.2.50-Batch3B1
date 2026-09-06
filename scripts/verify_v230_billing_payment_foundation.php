<?php
/**
 * Read-only V2.3 Billing / Payment Foundation static verifier.
 *
 * No config.php, database connection, provider SDK, network call or mutation.
 */

$root = dirname(__DIR__);
$paths = [
    'migration' => $root . '/migrations/042_billing_payment_foundation.sql',
    'service' => $root . '/includes/billing_payment_foundation.php',
    'runner' => $root . '/scripts/apply_v230_billing_payment_foundation.php',
    'catalog' => $root . '/includes/subscription_plan_catalog.php',
    'composer' => $root . '/composer.json',
    'init' => $root . '/init.php',
];

$source = [];
foreach ($paths as $key => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "FAIL: missing {$path}\n");
        exit(1);
    }
    $content = file_get_contents($path);
    if ($content === false) {
        fwrite(STDERR, "FAIL: unable to read {$path}\n");
        exit(1);
    }
    $source[$key] = $content;
}

$checks = 0;
$failures = 0;
$check = static function (bool $ok, string $message) use (&$checks, &$failures): void {
    $checks++;
    if ($ok) {
        echo "PASS: {$message}\n";
        return;
    }
    $failures++;
    echo "FAIL: {$message}\n";
};

$migration = $source['migration'];
$service = $source['service'];
$runner = $source['runner'];
$catalog = $source['catalog'];
$composer = $source['composer'];
$init = $source['init'];

$check(stripos($migration, 'CREATE TABLE IF NOT EXISTS billing_payment_attempts') !== false,
    'migration creates the provider-neutral payment-attempt audit table');
$check(stripos($migration, 'CREATE TABLE IF NOT EXISTS billing_provider_events') !== false,
    'migration creates the provider-event idempotency table');
$check(stripos($migration, 'UNIQUE KEY uniq_billing_provider_reference (provider, provider_reference)') !== false,
    'payment attempts are unique by provider and provider reference');
$check(stripos($migration, 'UNIQUE KEY uniq_billing_provider_event (provider, provider_event_id)') !== false,
    'provider events are unique by provider and provider event id');
$check(stripos($migration, 'quote_hash CHAR(64)') !== false && stripos($migration, 'payload_hash CHAR(64)') !== false,
    'quote and provider-event payload identities use SHA-256-sized hashes');
$check(!preg_match('/\b(raw_payload|payload_json|payload_body|webhook_payload)\b/i', $migration),
    'migration stores no raw provider/webhook payload column');
$check(stripos($migration, 'REFERENCES subscriptions(id) ON DELETE SET NULL') !== false,
    'future successful application can audit-link to the canonical subscription record');

$check(strpos($service, 'subscription_plan_is_valid') !== false,
    'quote builder validates plan codes against the canonical plan catalog');
$check(strpos($service, "['poultry', 'ruminant']") !== false,
    'quote modules are restricted to commercial Poultry/Ruminant modules');
$check(strpos($service, "['monthly', 'annual']") !== false,
    'billing interval is restricted to monthly or annual');
$check(strpos($service, "'/^[A-Z]{3}$/'") !== false,
    'billing currency is normalized to a three-letter code');
$check(strpos($service, "'/^(\\d{1,10})(?:\\.(\\d{1,2}))?$/'") !== false
    && strpos($service, "Billing amount must be greater than zero") !== false,
    'billing amount is exact DECIMAL(12,2) input and must be positive');
$check(strpos($service, 'subscription_seat_normalize_addons') !== false,
    'quote seat extras reuse the canonical durable seat normalization policy');
$check(substr_count($service, "hash('sha256'") >= 2,
    'quote and provider event payloads are canonically hashed with SHA-256');
$check(strpos($service, 'Provider reference already belongs to a different billing quote.') !== false,
    'provider-reference replay with a different quote is rejected');
$check(strpos($service, 'Provider event id was reused with a different payload.') !== false,
    'provider-event replay with a different payload is rejected');

$protectedWritePattern = '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+(?:farms|farm_modules|farm_role_limits|farm_subscription_seat_addons|subscriptions)\b/i';
$check(!preg_match($protectedWritePattern, $service),
    'Stage 1 service contains no entitlement or subscription-history DML');

$check(strpos($runner, "'042_billing_payment_foundation.sql'") !== false,
    'targeted runner names migration 042 explicitly');
$check(strpos($runner, 'run_migrations.php') === false,
    'targeted runner never invokes the historical all-migrations runner');
$check(strpos($runner, "table_name = 'subscriptions'") !== false && strpos($runner, "'snapshot_hash'") !== false,
    'targeted runner requires the migration 041 commercial foundation first');
$check(strpos($runner, '$beforeAttempts') !== false && strpos($runner, '$afterAttempts') !== false
    && strpos($runner, '$beforeEvents') !== false && strpos($runner, '$afterEvents') !== false,
    'targeted runner proves migration 042 does not insert billing audit rows');

$networkSurface = $service . "\n" . $runner;
$check(!preg_match('/\bcurl_(?:init|exec)|https?:\/\//i', $networkSurface),
    'Stage 1 service/installer makes no provider or network call');

$check(!preg_match('/[\'\"](?:price|monthly_price|annual_price|amount|currency)[\'\"]\s*=>/i', $catalog),
    'canonical plan catalog still contains no invented pricing/currency values');
$check(stripos($composer, 'paystack') === false
    && stripos($composer, 'flutterwave') === false
    && stripos($composer, 'stripe') === false,
    'no payment-gateway SDK has been introduced before provider selection');
$check(strpos($init, "includes/billing_payment_foundation.php") === false,
    'Stage 1 billing service is not globally loaded into application runtime yet');

$check(!preg_match($protectedWritePattern, $migration . "\n" . $runner),
    'migration/installer also contain no entitlement or subscription-history DML');

echo "\n{$checks} checks, {$failures} failure(s).\n";
if ($failures > 0) {
    fwrite(STDERR, "FAIL: V2.3 Billing / Payment Foundation contract is not statically closed.\n");
    exit(1);
}

echo "PASS: V2.3 Billing / Payment Foundation Stage 1 is provider-neutral, idempotent, audit-first, and entitlement-inert.\n";
echo "NOTE: no pricing, provider adapter, checkout route, webhook verification, charge, or entitlement application exists in Stage 1.\n";
