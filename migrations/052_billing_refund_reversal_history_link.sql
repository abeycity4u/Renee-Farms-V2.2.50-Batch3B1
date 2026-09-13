-- V2.3 Gap D Phase 3B:
-- durable exact linkage from a resolved entitlement reversal to the
-- immutable compensating subscriptions history row created by that reversal.
--
-- billing_refund_resolutions already identifies:
-- - the provider-refunded payment attempt;
-- - the original applied subscription history OR applied seat request;
-- - the final commercial resolution action, actor, reason and timestamp.
--
-- reverse_entitlement will also append a new immutable subscriptions row.
-- Exactly-once reversal must durably identify that compensating row so a
-- repeated resolver call can prove which history event completed the action.
--
-- Production was proven to contain zero billing_refund_resolutions rows before
-- this migration was authored, so no historical backfill is required or
-- permitted here.
--
-- This migration does NOT:
-- - resolve a refund;
-- - create subscription history;
-- - change tenant entitlement;
-- - alter seats or effective role limits;
-- - alter payment/provider fact;
-- - call a payment provider.

SET @v230_052_column_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'billing_refund_resolutions'
      AND column_name = 'reversal_subscription_record_id'
);

SET @v230_052_sql = IF(
    @v230_052_column_exists = 0,
    'ALTER TABLE billing_refund_resolutions ADD COLUMN reversal_subscription_record_id INT NULL AFTER seat_change_request_id',
    'SELECT 1'
);

PREPARE v230_052_stmt FROM @v230_052_sql;
EXECUTE v230_052_stmt;
DEALLOCATE PREPARE v230_052_stmt;

SET @v230_052_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'billing_refund_resolutions'
      AND index_name = 'idx_billing_refund_reversal_subscription'
);

SET @v230_052_sql = IF(
    @v230_052_index_exists = 0,
    'ALTER TABLE billing_refund_resolutions ADD INDEX idx_billing_refund_reversal_subscription (reversal_subscription_record_id)',
    'SELECT 1'
);

PREPARE v230_052_stmt FROM @v230_052_sql;
EXECUTE v230_052_stmt;
DEALLOCATE PREPARE v230_052_stmt;

SET @v230_052_fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'billing_refund_resolutions'
      AND constraint_name = 'fk_billing_refund_reversal_subscription'
);

SET @v230_052_sql = IF(
    @v230_052_fk_exists = 0,
    'ALTER TABLE billing_refund_resolutions ADD CONSTRAINT fk_billing_refund_reversal_subscription FOREIGN KEY (reversal_subscription_record_id) REFERENCES subscriptions(id) ON DELETE RESTRICT',
    'SELECT 1'
);

PREPARE v230_052_stmt FROM @v230_052_sql;
EXECUTE v230_052_stmt;
DEALLOCATE PREPARE v230_052_stmt;

INSERT INTO schema_migrations (filename)
VALUES ('052_billing_refund_reversal_history_link.sql')
ON DUPLICATE KEY UPDATE filename = VALUES(filename);
