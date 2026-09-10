-- V2.3 billing extra-seat authoritative quote snapshot.
--
-- Migration 045 created durable seat-change request storage. Migration 046
-- extends that storage so a paid mid-term seat increase can preserve the exact
-- server-authoritative proration facts that produced its amount.
--
-- These fields are nullable for scheduled no-refund removals and for safe
-- schema evolution. Application code must require the complete snapshot for
-- paid seat-add requests.
--
-- This migration does NOT create a seat request, start a payment, contact a
-- provider, change subscription dates, change tenant entitlements, or invoke
-- migration 003.

SET @v230_046_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'billing_seat_change_requests'
      AND column_name = 'quoted_at'
);

SET @v230_046_sql = IF(
    @v230_046_exists = 0,
    'ALTER TABLE billing_seat_change_requests ADD COLUMN quoted_at DATETIME NULL AFTER current_period_ends_at',
    'SELECT 1'
);

PREPARE v230_046_stmt FROM @v230_046_sql;
EXECUTE v230_046_stmt;
DEALLOCATE PREPARE v230_046_stmt;

SET @v230_046_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'billing_seat_change_requests'
      AND column_name = 'lineage_start_at'
);

SET @v230_046_sql = IF(
    @v230_046_exists = 0,
    'ALTER TABLE billing_seat_change_requests ADD COLUMN lineage_start_at DATETIME NULL AFTER quoted_at',
    'SELECT 1'
);

PREPARE v230_046_stmt FROM @v230_046_sql;
EXECUTE v230_046_stmt;
DEALLOCATE PREPARE v230_046_stmt;

SET @v230_046_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'billing_seat_change_requests'
      AND column_name = 'segment_start_at'
);

SET @v230_046_sql = IF(
    @v230_046_exists = 0,
    'ALTER TABLE billing_seat_change_requests ADD COLUMN segment_start_at DATETIME NULL AFTER lineage_start_at',
    'SELECT 1'
);

PREPARE v230_046_stmt FROM @v230_046_sql;
EXECUTE v230_046_stmt;
DEALLOCATE PREPARE v230_046_stmt;

SET @v230_046_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'billing_seat_change_requests'
      AND column_name = 'segment_end_at'
);

SET @v230_046_sql = IF(
    @v230_046_exists = 0,
    'ALTER TABLE billing_seat_change_requests ADD COLUMN segment_end_at DATETIME NULL AFTER segment_start_at',
    'SELECT 1'
);

PREPARE v230_046_stmt FROM @v230_046_sql;
EXECUTE v230_046_stmt;
DEALLOCATE PREPARE v230_046_stmt;

SET @v230_046_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'billing_seat_change_requests'
      AND column_name = 'pricing_version'
);

SET @v230_046_sql = IF(
    @v230_046_exists = 0,
    'ALTER TABLE billing_seat_change_requests ADD COLUMN pricing_version VARCHAR(80) NULL AFTER segment_end_at',
    'SELECT 1'
);

PREPARE v230_046_stmt FROM @v230_046_sql;
EXECUTE v230_046_stmt;
DEALLOCATE PREPARE v230_046_stmt;

SET @v230_046_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'billing_seat_change_requests'
      AND column_name = 'pricing_hash'
);

SET @v230_046_sql = IF(
    @v230_046_exists = 0,
    'ALTER TABLE billing_seat_change_requests ADD COLUMN pricing_hash CHAR(64) NULL AFTER pricing_version',
    'SELECT 1'
);

PREPARE v230_046_stmt FROM @v230_046_sql;
EXECUTE v230_046_stmt;
DEALLOCATE PREPARE v230_046_stmt;

SET @v230_046_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'billing_seat_change_requests'
      AND column_name = 'unit_amount'
);

SET @v230_046_sql = IF(
    @v230_046_exists = 0,
    'ALTER TABLE billing_seat_change_requests ADD COLUMN unit_amount DECIMAL(12,2) NULL AFTER pricing_hash',
    'SELECT 1'
);

PREPARE v230_046_stmt FROM @v230_046_sql;
EXECUTE v230_046_stmt;
DEALLOCATE PREPARE v230_046_stmt;

SET @v230_046_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'billing_seat_change_requests'
      AND column_name = 'partial_unit_amount'
);

SET @v230_046_sql = IF(
    @v230_046_exists = 0,
    'ALTER TABLE billing_seat_change_requests ADD COLUMN partial_unit_amount DECIMAL(12,2) NULL AFTER unit_amount',
    'SELECT 1'
);

PREPARE v230_046_stmt FROM @v230_046_sql;
EXECUTE v230_046_stmt;
DEALLOCATE PREPARE v230_046_stmt;

SET @v230_046_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'billing_seat_change_requests'
      AND column_name = 'future_full_periods'
);

SET @v230_046_sql = IF(
    @v230_046_exists = 0,
    'ALTER TABLE billing_seat_change_requests ADD COLUMN future_full_periods INT UNSIGNED NULL AFTER partial_unit_amount',
    'SELECT 1'
);

PREPARE v230_046_stmt FROM @v230_046_sql;
EXECUTE v230_046_stmt;
DEALLOCATE PREPARE v230_046_stmt;

SET @v230_046_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'billing_seat_change_requests'
      AND column_name = 'per_seat_amount'
);

SET @v230_046_sql = IF(
    @v230_046_exists = 0,
    'ALTER TABLE billing_seat_change_requests ADD COLUMN per_seat_amount DECIMAL(12,2) NULL AFTER future_full_periods',
    'SELECT 1'
);

PREPARE v230_046_stmt FROM @v230_046_sql;
EXECUTE v230_046_stmt;
DEALLOCATE PREPARE v230_046_stmt;

SET @v230_046_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'billing_seat_change_requests'
      AND column_name = 'latest_paid_subscription_id'
);

SET @v230_046_sql = IF(
    @v230_046_exists = 0,
    'ALTER TABLE billing_seat_change_requests ADD COLUMN latest_paid_subscription_id INT NULL AFTER per_seat_amount',
    'SELECT 1'
);

PREPARE v230_046_stmt FROM @v230_046_sql;
EXECUTE v230_046_stmt;
DEALLOCATE PREPARE v230_046_stmt;

SET @v230_046_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'billing_seat_change_requests'
      AND column_name = 'latest_paid_attempt_id'
);

SET @v230_046_sql = IF(
    @v230_046_exists = 0,
    'ALTER TABLE billing_seat_change_requests ADD COLUMN latest_paid_attempt_id BIGINT UNSIGNED NULL AFTER latest_paid_subscription_id',
    'SELECT 1'
);

PREPARE v230_046_stmt FROM @v230_046_sql;
EXECUTE v230_046_stmt;
DEALLOCATE PREPARE v230_046_stmt;


-- A durable seat-change request must retain the payment identity that its
-- immutable request hash binds. Migration 045 used SET NULL; upgrade that
-- relationship to RESTRICT before paid seat changes are exposed.

SET @v230_046_payment_fk_rule = (
    SELECT delete_rule
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'billing_seat_change_requests'
      AND constraint_name = 'fk_billing_seat_change_payment_attempt'
    LIMIT 1
);

SET @v230_046_sql = IF(
    @v230_046_payment_fk_rule IS NOT NULL
    AND UPPER(@v230_046_payment_fk_rule) <> 'RESTRICT',
    'ALTER TABLE billing_seat_change_requests DROP FOREIGN KEY fk_billing_seat_change_payment_attempt',
    'SELECT 1'
);

PREPARE v230_046_stmt FROM @v230_046_sql;
EXECUTE v230_046_stmt;
DEALLOCATE PREPARE v230_046_stmt;

SET @v230_046_payment_fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'billing_seat_change_requests'
      AND constraint_name = 'fk_billing_seat_change_payment_attempt'
);

SET @v230_046_sql = IF(
    @v230_046_payment_fk_exists = 0,
    'ALTER TABLE billing_seat_change_requests ADD CONSTRAINT fk_billing_seat_change_payment_attempt FOREIGN KEY (payment_attempt_id) REFERENCES billing_payment_attempts(id) ON DELETE RESTRICT',
    'SELECT 1'
);

PREPARE v230_046_stmt FROM @v230_046_sql;
EXECUTE v230_046_stmt;
DEALLOCATE PREPARE v230_046_stmt;

-- Preserve explicit indexes for the authoritative paid-lineage references.

SET @v230_046_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'billing_seat_change_requests'
      AND index_name = 'idx_billing_seat_change_latest_paid_subscription'
);

SET @v230_046_sql = IF(
    @v230_046_index_exists = 0,
    'ALTER TABLE billing_seat_change_requests ADD INDEX idx_billing_seat_change_latest_paid_subscription (latest_paid_subscription_id)',
    'SELECT 1'
);

PREPARE v230_046_stmt FROM @v230_046_sql;
EXECUTE v230_046_stmt;
DEALLOCATE PREPARE v230_046_stmt;

SET @v230_046_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'billing_seat_change_requests'
      AND index_name = 'idx_billing_seat_change_latest_paid_attempt'
);

SET @v230_046_sql = IF(
    @v230_046_index_exists = 0,
    'ALTER TABLE billing_seat_change_requests ADD INDEX idx_billing_seat_change_latest_paid_attempt (latest_paid_attempt_id)',
    'SELECT 1'
);

PREPARE v230_046_stmt FROM @v230_046_sql;
EXECUTE v230_046_stmt;
DEALLOCATE PREPARE v230_046_stmt;

-- subscriptions.id is signed INT in the production schema, while billing
-- payment-attempt ids are BIGINT UNSIGNED. The snapshot columns deliberately
-- match those parent types exactly.

SET @v230_046_fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'billing_seat_change_requests'
      AND constraint_name = 'fk_billing_seat_change_latest_subscription'
);

SET @v230_046_sql = IF(
    @v230_046_fk_exists = 0,
    'ALTER TABLE billing_seat_change_requests ADD CONSTRAINT fk_billing_seat_change_latest_subscription FOREIGN KEY (latest_paid_subscription_id) REFERENCES subscriptions(id) ON DELETE RESTRICT',
    'SELECT 1'
);

PREPARE v230_046_stmt FROM @v230_046_sql;
EXECUTE v230_046_stmt;
DEALLOCATE PREPARE v230_046_stmt;

SET @v230_046_fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'billing_seat_change_requests'
      AND constraint_name = 'fk_billing_seat_change_latest_attempt'
);

SET @v230_046_sql = IF(
    @v230_046_fk_exists = 0,
    'ALTER TABLE billing_seat_change_requests ADD CONSTRAINT fk_billing_seat_change_latest_attempt FOREIGN KEY (latest_paid_attempt_id) REFERENCES billing_payment_attempts(id) ON DELETE RESTRICT',
    'SELECT 1'
);

PREPARE v230_046_stmt FROM @v230_046_sql;
EXECUTE v230_046_stmt;
DEALLOCATE PREPARE v230_046_stmt;

INSERT INTO schema_migrations (filename)
VALUES ('046_billing_seat_quote_snapshot.sql')
ON DUPLICATE KEY UPDATE filename = VALUES(filename);
