-- V2.3 Commercial Subscription Record Foundation
--
-- farms remains the current runtime subscription snapshot.
-- subscriptions becomes the append-only commercial history table.
-- This migration only creates/extends subscriptions; it does not alter tenant
-- operational data, farm entitlements, role limits, or billing-provider state.

CREATE TABLE IF NOT EXISTS subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL,
    plan_code VARCHAR(50) NOT NULL,
    status ENUM('trial','active','past_due','suspended','cancelled') NOT NULL,
    billing_interval ENUM('monthly','annual') NOT NULL DEFAULT 'monthly',
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    provider VARCHAR(50) NULL,
    provider_subscription_id VARCHAR(150) NULL,
    current_period_ends_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_subscription_farm_status (farm_id, status),
    CONSTRAINT fk_subscription_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE
);

ALTER TABLE subscriptions ADD COLUMN subscription_starts_at DATETIME NULL AFTER current_period_ends_at;
ALTER TABLE subscriptions ADD COLUMN subscription_ends_at DATETIME NULL AFTER subscription_starts_at;
ALTER TABLE subscriptions ADD COLUMN modules_snapshot TEXT NULL AFTER subscription_ends_at;
ALTER TABLE subscriptions ADD COLUMN seat_addons_snapshot TEXT NULL AFTER modules_snapshot;
ALTER TABLE subscriptions ADD COLUMN change_reason VARCHAR(80) NULL AFTER seat_addons_snapshot;
ALTER TABLE subscriptions ADD COLUMN recorded_by_user_id INT NULL AFTER change_reason;
ALTER TABLE subscriptions ADD COLUMN snapshot_hash CHAR(64) NULL AFTER recorded_by_user_id;
ALTER TABLE subscriptions ADD INDEX idx_subscription_farm_history (farm_id, id);
