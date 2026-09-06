-- V2.3 Billing / Payment Foundation
--
-- Provider-neutral audit storage only.
-- This migration does NOT charge a customer, alter subscription entitlements,
-- update farms/farm_modules/role limits, or write subscription history rows.
-- Migration 041_commercial_subscription_records.sql must already be installed.

CREATE TABLE IF NOT EXISTS billing_payment_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL,
    status ENUM('initialized','pending','paid','failed','cancelled','refunded') NOT NULL DEFAULT 'initialized',
    provider VARCHAR(40) NOT NULL,
    provider_reference VARCHAR(150) NOT NULL,
    provider_transaction_id VARCHAR(150) NULL,
    provider_subscription_id VARCHAR(150) NULL,
    plan_code VARCHAR(50) NOT NULL,
    billing_interval ENUM('monthly','annual') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL,
    modules_snapshot TEXT NOT NULL,
    seat_addons_snapshot TEXT NOT NULL,
    quote_hash CHAR(64) NOT NULL,
    initiated_by_user_id INT NULL,
    verified_at DATETIME NULL,
    paid_at DATETIME NULL,
    failed_at DATETIME NULL,
    failure_code VARCHAR(80) NULL,
    applied_subscription_record_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_billing_provider_reference (provider, provider_reference),
    INDEX idx_billing_attempt_farm_status (farm_id, status),
    INDEX idx_billing_attempt_provider_transaction (provider, provider_transaction_id),
    INDEX idx_billing_attempt_quote_hash (quote_hash),
    CONSTRAINT fk_billing_attempt_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE,
    CONSTRAINT fk_billing_attempt_subscription_record FOREIGN KEY (applied_subscription_record_id) REFERENCES subscriptions(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS billing_provider_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(40) NOT NULL,
    provider_event_id VARCHAR(150) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    payload_hash CHAR(64) NOT NULL,
    payment_attempt_id BIGINT UNSIGNED NULL,
    processing_status ENUM('received','processed','ignored','failed') NOT NULL DEFAULT 'received',
    error_message VARCHAR(255) NULL,
    received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    UNIQUE KEY uniq_billing_provider_event (provider, provider_event_id),
    INDEX idx_billing_event_attempt (payment_attempt_id),
    CONSTRAINT fk_billing_event_attempt FOREIGN KEY (payment_attempt_id) REFERENCES billing_payment_attempts(id) ON DELETE SET NULL
);
