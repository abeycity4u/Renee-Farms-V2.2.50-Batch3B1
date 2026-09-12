-- V2.3 Billing Tenant Retention Integrity
--
-- Payment-attempt history is commercial audit evidence and must not disappear
-- merely because a tenant/farm is deleted.
--
-- This migration changes FK delete semantics only. It does not insert, update,
-- delete, backfill, or otherwise rewrite billing/payment rows.

SET @billing_attempt_farm_fk_drop_sql = (
    SELECT IF(
        COUNT(*) = 0
        OR UPPER(MAX(delete_rule)) IN ('RESTRICT', 'NO ACTION'),
        'SELECT 1',
        'ALTER TABLE billing_payment_attempts DROP FOREIGN KEY fk_billing_attempt_farm'
    )
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND constraint_name = 'fk_billing_attempt_farm'
      AND table_name = 'billing_payment_attempts'
      AND referenced_table_name = 'farms'
);

PREPARE billing_attempt_farm_fk_drop_stmt
    FROM @billing_attempt_farm_fk_drop_sql;
EXECUTE billing_attempt_farm_fk_drop_stmt;
DEALLOCATE PREPARE billing_attempt_farm_fk_drop_stmt;

SET @billing_attempt_farm_fk_add_sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE billing_payment_attempts ADD CONSTRAINT fk_billing_attempt_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE RESTRICT',
        'SELECT 1'
    )
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND constraint_name = 'fk_billing_attempt_farm'
      AND table_name = 'billing_payment_attempts'
      AND referenced_table_name = 'farms'
);

PREPARE billing_attempt_farm_fk_add_stmt
    FROM @billing_attempt_farm_fk_add_sql;
EXECUTE billing_attempt_farm_fk_add_stmt;
DEALLOCATE PREPARE billing_attempt_farm_fk_add_stmt;
