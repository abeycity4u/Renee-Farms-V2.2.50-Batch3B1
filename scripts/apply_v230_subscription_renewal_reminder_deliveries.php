<?php
/**
 * Creates the idempotency ledger for scheduled subscription renewal reminders.
 * Safe to run repeatedly.
 */
require_once dirname(__DIR__) . '/config.php';

$sql = "CREATE TABLE IF NOT EXISTS subscription_renewal_reminder_deliveries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    farm_id INT NOT NULL,
    subscription_ends_at DATETIME NOT NULL,
    days_left INT NOT NULL,
    recipient VARCHAR(254) NOT NULL,
    sent_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_subscription_reminder_delivery (farm_id, subscription_ends_at, days_left),
    KEY idx_subscription_reminder_farm (farm_id),
    CONSTRAINT fk_subscription_reminder_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

try {
    $pdo->exec($sql);
    echo "PASS: subscription renewal reminder delivery ledger is ready." . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
