<?php
/**
 * Read-only V2.3 Billing / Payment Foundation static verifier.
 *
 * No config.php, database connection, provider SDK, network call or mutation.
 */

$root = dirname(__DIR__);
$paths = [
    'migration42' => $root . '/migrations/042_billing_payment_foundation.sql',
    'migration43' => $root . '/migrations/043_billing_transactional_integrity.sql',
    'service' => $root . '/includes/billing_payment_foundation.php',
    'runner42' => $root . '/scripts/apply_v230_billing_payment_foundation.php',
    'runner43' => $root . '/scripts/apply_v230_billing_transactional_integrity.php',
    'cleanup' => $root . '/scripts/cleanup_v230_billing_stage1_qa.php',
    'runtime' => $root . '/scripts/verify_v230_billing_transactional_runtime.php',
    'rollback' => $root . '/scripts/verify_v230_billing_idempotency_rollback.php',
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

$migration42 = $source['migration42'];
$migration43 = $source['migration43'];
$service = $source['service'];
$runner42 = $source['runner42'];
$runner43 = $source['runner43'];
$cleanup = $source['cleanup'];
$runtime = $source['runtime'];
$rollback = $source['rollback'];
$catalog = $source['catalog'];
$composer = $source['composer'];
$init = $source['init'];

$check(stripos($migration42, 'CREATE TABLE IF NOT EXISTS billing_payment_attempts') !== false,
    'migration 042 creates the provider-neutral payment-attempt audit table');
$check(stripos($migration42, 'CREATE TABLE IF NOT EXISTS billing_provider_events') !== false,
    'migration 042 creates the provider-event idempotency table');
$check(substr_count(strtolower($migration42), 'engine=innodb') >= 2,
    'migration 042 explicitly creates both billing tables as InnoDB');
$check(stripos($migration42, 'UNIQUE KEY uniq_billing_provider_reference (provider, provider_reference)') !== false,
    'payment attempts are unique by provider and provider reference');
$check(stripos($migration42, 'UNIQUE KEY uniq_billing_provider_event (provider, provider_event_id)') !== false,
    'provider events are unique by provider and provider event id');
$check(stripos($migration42, 'quote_hash CHAR(64)') !== false && stripos($migration42, 'payload_hash CHAR(64)') !== false,
    'quote and provider-event payload identities use SHA-256-sized hashes');
$check(!preg_match('/\b(raw_payload|payload_json|payload_body|webhook_payload)\b/i', $migration42),
    'migration 042 stores no raw provider/webhook payload column');
$check(stripos($migration42, 'REFERENCES subscriptions(id) ON DELETE SET NULL') !== false,
    'future successful application can audit-link to the canonical subscription record');

$check(stripos($migration43, 'ALTER TABLE billing_payment_attempts ENGINE=InnoDB') !== false,
    'migration 043 converts payment attempts to InnoDB');
$check(stripos($migration43, 'ALTER TABLE billing_provider_events ENGINE=InnoDB') !== false,
    'migration 043 converts provider events to InnoDB');
$check(strpos($migration43, 'fk_billing_attempt_farm') !== false
    && strpos($migration43, 'fk_billing_attempt_subscription_record') !== false
    && strpos($migration43, 'fk_billing_event_attempt') !== false,
    'migration 043 restores all three intended billing foreign keys');
preg_match_all('/\bALTER\s+TABLE\s+([a-z0-9_]+)/i', $migration43, $alterMatches);
$alteredTables = array_values(array_unique(array_map('strtolower', $alterMatches[1] ?? [])));
sort($alteredTables, SORT_STRING);
$check($alteredTables === ['billing_payment_attempts', 'billing_provider_events'],
    'migration 043 alters only the two billing audit tables');
$check(!preg_match('/\bALTER\s+TABLE\s+(?:farms|subscriptions|farm_modules|farm_role_limits|farm_subscription_seat_addons)\b/i', $migration43),
    'migration 043 does not convert or alter operational/commercial parent tables');

$check(strpos($service, 'subscription_plan_is_valid') !== false,
    'quote builder validates plan codes against the canonical plan catalog');
$check(strpos($service, "['poultry', 'ruminant']") !== false,
    'quote modules are restricted to commercial Poultry/Ruminant modules');
$check(strpos($service, "['monthly', 'annual']") !== false,
    'billing interval is restricted to monthly or annual');
$check(strpos($service, "'/^[A-Z]{3}$/'") !== false,
    'billing currency is normalized to a three-letter code');
$check(strpos($service, "'/^(\\d{1,10})(?:\\.(\\d{1,2}))?$/'") !== false
    && strpos($service, 'Billing amount must be greater than zero') !== false,
    'billing amount is exact DECIMAL(12,2) input and must be positive');
$check(strpos($service, 'subscription_seat_normalize_addons') !== false,
    'quote seat extras reuse the canonical durable seat normalization policy');
$check(substr_count($service, "hash('sha256'") >= 2,
    'quote and provider event payloads are canonically hashed with SHA-256');
$check(strpos($service, 'Provider reference already belongs to a different billing quote.') !== false,
    'provider-reference replay with a different quote is rejected');
$check(strpos($service, 'Provider event id was reused with a different payload.') !== false,
    'provider-event replay with a different payload is rejected');
$check(strpos($service, 'billing_payment_foundation_transactional') !== false
    && strpos($service, "strcasecmp($attemptEngine, 'InnoDB')") !== false
    && strpos($service, "strcasecmp($eventEngine, 'InnoDB')") !== false,
    'billing service explicitly requires transactional InnoDB storage');
$check(strpos($service, 'billing_payment_foreign_keys_ready') !== false
    && strpos($service, 'fk_billing_attempt_farm') !== false
    && strpos($service, 'fk_billing_attempt_subscription_record') !== false
    && strpos($service, 'fk_billing_event_attempt') !== false,
    'billing service explicitly requires all three billing foreign keys');
$check(strpos($service, '&& billing_payment_foundation_transactional($pdo)') !== false
    && strpos($service, '&& billing_payment_foreign_keys_ready($pdo)') !== false,
    'billing foundation readiness fails closed on engine or FK integrity');

$protectedWritePattern = '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+(?:farms|farm_modules|farm_role_limits|farm_subscription_seat_addons|subscriptions)\b/i';
$check(!preg_match($protectedWritePattern, $service),
    'Stage 1 service contains no entitlement or subscription-history DML');

$check(strpos($runner42, "'042_billing_payment_foundation.sql'") !== false,
    'targeted 042 runner names migration 042 explicitly');
$check(strpos($runner42, 'run_migrations.php') === false,
    'targeted 042 runner never invokes the historical all-migrations runner');
$check(strpos($runner43, "'043_billing_transactional_integrity.sql'") !== false,
    'targeted 043 runner names migration 043 explicitly');
$check(strpos($runner43, 'run_migrations.php') === false,
    'targeted 043 runner never invokes the historical all-migrations runner');
$check(strpos($runner43, '$beforeAttempts') !== false && strpos($runner43, '$afterAttempts') !== false
    && strpos($runner43, '$beforeEvents') !== false && strpos($runner43, '$afterEvents') !== false,
    'targeted 043 runner proves billing row counts are preserved');
$check(strpos($runner43, "['farms', 'subscriptions']") !== false
    && strpos($runner43, "strcasecmp($engines[$parentTable], 'InnoDB')") !== false,
    'targeted 043 runner requires existing parent tables to remain InnoDB');

$check(strpos($cleanup, "$provider = 'qa-stage1';") !== false,
    'cleanup utility is hard-scoped to the reserved qa-stage1 provider');
$eventDeletePos = strpos($cleanup, 'DELETE FROM billing_provider_events WHERE provider = ?');
$attemptDeletePos = strpos($cleanup, 'DELETE FROM billing_payment_attempts WHERE provider = ?');
$check($eventDeletePos !== false && $attemptDeletePos !== false && $eventDeletePos < $attemptDeletePos,
    'cleanup deletes qa-stage1 provider events before qa-stage1 attempts');
$check(!preg_match('/DELETE\s+FROM\s+(?:farms|subscriptions|farm_modules|farm_role_limits|farm_subscription_seat_addons)/i', $cleanup),
    'cleanup cannot delete subscription, entitlement, seat, or operational farm rows');

$check(strpos($runtime, 'billing_payment_foundation_transactional($pdo)') !== false
    && strpos($runtime, 'billing_payment_foreign_keys_ready($pdo)') !== false
    && strpos($runtime, "'043_billing_transactional_integrity.sql'") !== false,
    'runtime verifier checks engine integrity, FK integrity, and migration 043 marker');
$check(strpos($runtime, "['qa-stage1']") !== false
    && strpos($runtime, 'temporary qa-stage1 payment attempts are absent') !== false
    && strpos($runtime, 'temporary qa-stage1 provider events are absent') !== false,
    'runtime verifier requires no qa-stage1 residue');

$check(strpos($rollback, '$pdo->beginTransaction()') !== false
    && strpos($rollback, '$pdo->rollBack()') !== false,
    'rollback verifier uses an explicit database transaction and rollback');
$check(strpos($rollback, '$beforeAttempts') !== false && strpos($rollback, '$afterAttempts') !== false
    && strpos($rollback, '$beforeEvents') !== false && strpos($rollback, '$afterEvents') !== false
    && strpos($rollback, 'rollback did not restore billing audit row counts') !== false,
    'rollback verifier fails unless before/after billing row counts match exactly');
$check(strpos($rollback, 'duplicate provider reference returned the existing attempt') !== false
    && strpos($rollback, 'duplicate provider event returned the existing event') !== false,
    'rollback verifier preserves payment-attempt and provider-event idempotency tests');

$networkSurface = $service . "\n" . $runner42 . "\n" . $runner43 . "\n" . $cleanup . "\n" . $runtime . "\n" . $rollback;
$check(!preg_match('/\bcurl_(?:init|exec)|https?:\/\//i', $networkSurface),
    'Stage 1 repair surface makes no provider or network call');
$check(!preg_match('/[\'\"](?:price|monthly_price|annual_price|amount|currency)[\'\"]\s*=>/i', $catalog),
    'canonical plan catalog still contains no invented pricing/currency values');
$check(stripos($composer, 'paystack') === false
    && stripos($composer, 'flutterwave') === false
    && stripos($composer, 'stripe') === false,
    'no payment-gateway SDK has been introduced before provider selection');
$check(strpos($init, 'includes/billing_payment_foundation.php') === false,
    'Stage 1 billing service is still not globally loaded into application runtime');

$stripSqlComments = static fn(string $sql): string => preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
$stripPhpComments = static function (string $php): string {
    $out = '';
    foreach (token_get_all($php) as $token) {
        if (is_array($token)) {
            if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
            $out .= $token[1];
        } else {
            $out .= $token;
        }
    }
    return $out;
};
$protectedSurface = $stripSqlComments($migration42)
    . "\n" . $stripSqlComments($migration43)
    . "\n" . $stripPhpComments($runner42)
    . "\n" . $stripPhpComments($runner43);
$check(!preg_match($protectedWritePattern, $protectedSurface),
    'migrations/runners contain no entitlement or subscription-history DML');

echo "\n{$checks} checks, {$failures} failure(s).\n";
if ($failures > 0) {
    fwrite(STDERR, "FAIL: V2.3 Billing / Payment Foundation contract is not statically closed.\n");
    exit(1);
}

echo "PASS: V2.3 Billing / Payment Foundation Stage 1 is provider-neutral, transactional, FK-enforced, idempotent, audit-first, and entitlement-inert.\n";
echo "NOTE: no pricing, provider adapter, checkout route, webhook verification, charge, or entitlement application exists in Stage 1.\n";
