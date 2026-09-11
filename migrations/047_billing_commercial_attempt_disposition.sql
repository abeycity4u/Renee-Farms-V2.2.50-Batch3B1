-- V2.3 billing commercial-attempt disposition foundation.
--
-- Provider payment status remains an external/provider fact.
-- This migration adds only local commercial applicability metadata so a
-- provider-verified failed/cancelled subscription checkout may later be
-- superseded without rewriting its payment history.
--
-- A superseded attempt may still receive a later provider payment fact.
-- Paid application must therefore consult commercial_disposition before
-- changing tenant commercial state.
--
-- This migration does NOT supersede an attempt, change payment status,
-- charge/refund a customer, alter entitlement, or invoke migration 003.

SET @v230_commercial_disposition_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'billing_payment_attempts'
      AND column_name = 'commercial_disposition'
);

SET @v230_commercial_disposition_sql = IF(
    @v230_commercial_disposition_exists = 0,
    'ALTER TABLE billing_payment_attempts ADD COLUMN commercial_disposition VARCHAR(24) NOT NULL DEFAULT ''eligible'' AFTER purpose',
    'SELECT 1'
);

PREPARE v230_commercial_disposition_stmt
FROM @v230_commercial_disposition_sql;
EXECUTE v230_commercial_disposition_stmt;
DEALLOCATE PREPARE v230_commercial_disposition_stmt;

SET @v230_commercial_superseded_at_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'billing_payment_attempts'
      AND column_name = 'commercial_superseded_at'
);

SET @v230_commercial_superseded_at_sql = IF(
    @v230_commercial_superseded_at_exists = 0,
    'ALTER TABLE billing_payment_attempts ADD COLUMN commercial_superseded_at DATETIME NULL AFTER commercial_disposition',
    'SELECT 1'
);

PREPARE v230_commercial_superseded_at_stmt
FROM @v230_commercial_superseded_at_sql;
EXECUTE v230_commercial_superseded_at_stmt;
DEALLOCATE PREPARE v230_commercial_superseded_at_stmt;

SET @v230_commercial_supersession_verified_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'billing_payment_attempts'
      AND column_name = 'commercial_supersession_verified_at'
);

SET @v230_commercial_supersession_verified_sql = IF(
    @v230_commercial_supersession_verified_exists = 0,
    'ALTER TABLE billing_payment_attempts ADD COLUMN commercial_supersession_verified_at DATETIME NULL AFTER commercial_superseded_at',
    'SELECT 1'
);

PREPARE v230_commercial_supersession_verified_stmt
FROM @v230_commercial_supersession_verified_sql;
EXECUTE v230_commercial_supersession_verified_stmt;
DEALLOCATE PREPARE v230_commercial_supersession_verified_stmt;

SET @v230_commercial_superseded_by_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'billing_payment_attempts'
      AND column_name = 'commercial_superseded_by_user_id'
);

SET @v230_commercial_superseded_by_sql = IF(
    @v230_commercial_superseded_by_exists = 0,
    'ALTER TABLE billing_payment_attempts ADD COLUMN commercial_superseded_by_user_id INT NULL AFTER commercial_supersession_verified_at',
    'SELECT 1'
);

PREPARE v230_commercial_superseded_by_stmt
FROM @v230_commercial_superseded_by_sql;
EXECUTE v230_commercial_superseded_by_stmt;
DEALLOCATE PREPARE v230_commercial_superseded_by_stmt;

SET @v230_commercial_supersession_reason_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'billing_payment_attempts'
      AND column_name = 'commercial_supersession_reason'
);

SET @v230_commercial_supersession_reason_sql = IF(
    @v230_commercial_supersession_reason_exists = 0,
    'ALTER TABLE billing_payment_attempts ADD COLUMN commercial_supersession_reason VARCHAR(80) NULL AFTER commercial_superseded_by_user_id',
    'SELECT 1'
);

PREPARE v230_commercial_supersession_reason_stmt
FROM @v230_commercial_supersession_reason_sql;
EXECUTE v230_commercial_supersession_reason_stmt;
DEALLOCATE PREPARE v230_commercial_supersession_reason_stmt;

SET @v230_commercial_disposition_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'billing_payment_attempts'
      AND index_name = 'idx_billing_attempt_farm_purpose_disposition_status'
);

SET @v230_commercial_disposition_index_sql = IF(
    @v230_commercial_disposition_index_exists = 0,
    'ALTER TABLE billing_payment_attempts ADD INDEX idx_billing_attempt_farm_purpose_disposition_status (farm_id, purpose, commercial_disposition, status)',
    'SELECT 1'
);

PREPARE v230_commercial_disposition_index_stmt
FROM @v230_commercial_disposition_index_sql;
EXECUTE v230_commercial_disposition_index_stmt;
DEALLOCATE PREPARE v230_commercial_disposition_index_stmt;

INSERT INTO schema_migrations (filename)
VALUES ('047_billing_commercial_attempt_disposition.sql')
ON DUPLICATE KEY UPDATE filename = VALUES(filename);
