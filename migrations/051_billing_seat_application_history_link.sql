-- V2.3 Gap D Phase 3B:
-- durable exact subscription-history linkage for applied paid seat top-ups.
--
-- billing_seat_topup_apply_paid_attempt() already creates an immutable
-- subscriptions history row when paid capacity is applied. Before this
-- migration that history id is returned to the caller but is not stored on
-- the durable billing_seat_change_requests row.
--
-- Refund reversal must be able to prove the exact commercial snapshot created
-- by the applied seat top-up. Store that immutable history identity directly
-- on the request.
--
-- Production was proven to contain zero already-applied paid seat-top-up
-- requests before this migration was authored, so no historical backfill is
-- required or permitted here.
--
-- This migration does NOT:
-- - apply or reverse a seat top-up;
-- - change purchased seats or effective role limits;
-- - alter provider/payment status;
-- - create subscription history;
-- - resolve a refund review.

SET @v230_051_column_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'billing_seat_change_requests'
      AND column_name = 'applied_subscription_record_id'
);

SET @v230_051_sql = IF(
    @v230_051_column_exists = 0,
    'ALTER TABLE billing_seat_change_requests ADD COLUMN applied_subscription_record_id INT NULL AFTER payment_attempt_id',
    'SELECT 1'
);

PREPARE v230_051_stmt FROM @v230_051_sql;
EXECUTE v230_051_stmt;
DEALLOCATE PREPARE v230_051_stmt;

SET @v230_051_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'billing_seat_change_requests'
      AND index_name = 'idx_billing_seat_change_applied_subscription'
);

SET @v230_051_sql = IF(
    @v230_051_index_exists = 0,
    'ALTER TABLE billing_seat_change_requests ADD INDEX idx_billing_seat_change_applied_subscription (applied_subscription_record_id)',
    'SELECT 1'
);

PREPARE v230_051_stmt FROM @v230_051_sql;
EXECUTE v230_051_stmt;
DEALLOCATE PREPARE v230_051_stmt;

SET @v230_051_fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'billing_seat_change_requests'
      AND constraint_name = 'fk_billing_seat_change_applied_subscription'
);

SET @v230_051_sql = IF(
    @v230_051_fk_exists = 0,
    'ALTER TABLE billing_seat_change_requests ADD CONSTRAINT fk_billing_seat_change_applied_subscription FOREIGN KEY (applied_subscription_record_id) REFERENCES subscriptions(id) ON DELETE RESTRICT',
    'SELECT 1'
);

PREPARE v230_051_stmt FROM @v230_051_sql;
EXECUTE v230_051_stmt;
DEALLOCATE PREPARE v230_051_stmt;

INSERT INTO schema_migrations (filename)
VALUES ('051_billing_seat_application_history_link.sql')
ON DUPLICATE KEY UPDATE filename = VALUES(filename);
