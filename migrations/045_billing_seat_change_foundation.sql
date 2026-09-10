-- V2.3 billing extra-seat change foundation.
--
-- Adds an explicit payment purpose so verified payments can later be dispatched
-- safely without sending seat-only top-ups through full subscription renewal.
--
-- Adds durable seat-change request storage for:
-- - paid mid-term seat increases;
-- - no-refund reductions scheduled for the current subscription period end.
--
-- This migration does NOT create a seat request, charge a customer, change a
-- tenant entitlement, change an active seat count, or invoke migration 003.

SET @v230_billing_purpose_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'billing_payment_attempts'
      AND column_name = 'purpose'
);

SET @v230_billing_purpose_sql = IF(
    @v230_billing_purpose_exists = 0,
    'ALTER TABLE billing_payment_attempts ADD COLUMN purpose VARCHAR(32) NOT NULL DEFAULT ''subscription'' AFTER farm_id',
    'SELECT 1'
);

PREPARE v230_billing_purpose_stmt FROM @v230_billing_purpose_sql;
EXECUTE v230_billing_purpose_stmt;
DEALLOCATE PREPARE v230_billing_purpose_stmt;

SET @v230_billing_purpose_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'billing_payment_attempts'
      AND index_name = 'idx_billing_attempt_farm_purpose_status'
);

SET @v230_billing_purpose_index_sql = IF(
    @v230_billing_purpose_index_exists = 0,
    'ALTER TABLE billing_payment_attempts ADD INDEX idx_billing_attempt_farm_purpose_status (farm_id, purpose, status)',
    'SELECT 1'
);

PREPARE v230_billing_purpose_index_stmt FROM @v230_billing_purpose_index_sql;
EXECUTE v230_billing_purpose_index_stmt;
DEALLOCATE PREPARE v230_billing_purpose_index_stmt;

CREATE TABLE IF NOT EXISTS billing_seat_change_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL,
    change_kind VARCHAR(24) NOT NULL,
    status VARCHAR(32) NOT NULL,
    role_code VARCHAR(50) NOT NULL,
    from_extra_seats INT UNSIGNED NOT NULL,
    to_extra_seats INT UNSIGNED NOT NULL,
    plan_code VARCHAR(50) NOT NULL,
    billing_interval ENUM('monthly','annual') NOT NULL,
    modules_snapshot TEXT NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    currency CHAR(3) NOT NULL,
    current_period_ends_at DATETIME NOT NULL,
    effective_at DATETIME NULL,
    payment_attempt_id BIGINT UNSIGNED NULL,
    initiated_by_user_id INT NULL,
    request_hash CHAR(64) NOT NULL,
    applied_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uniq_billing_seat_change_payment_attempt (payment_attempt_id),
    INDEX idx_billing_seat_change_farm_status (farm_id, status),
    INDEX idx_billing_seat_change_farm_role_status (farm_id, role_code, status),
    INDEX idx_billing_seat_change_effective (status, effective_at),

    CONSTRAINT fk_billing_seat_change_farm
        FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE,

    CONSTRAINT fk_billing_seat_change_payment_attempt
        FOREIGN KEY (payment_attempt_id)
        REFERENCES billing_payment_attempts(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO schema_migrations (filename)
VALUES ('045_billing_seat_change_foundation.sql')
ON DUPLICATE KEY UPDATE filename = VALUES(filename);
