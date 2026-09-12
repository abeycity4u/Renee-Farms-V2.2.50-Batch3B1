-- V2.3 Billing Tenant Retention Integrity
--
-- Payment-attempt history is commercial audit evidence and must not disappear
-- merely because a tenant/farm is deleted.
--
-- This migration changes FK delete semantics only. It does not insert, update,
-- delete, backfill, or otherwise rewrite billing/payment rows.
--
-- When replacing an existing CASCADE rule, DROP + ADD occur in one ALTER TABLE
-- so there is no intermediate state where billing attempts lack farm protection.

SET @billing_attempt_farm_fk_sql = (
    SELECT CASE
        WHEN COUNT(*) = 0 THEN
            'ALTER TABLE billing_payment_attempts ADD CONSTRAINT fk_billing_attempt_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE RESTRICT'
        WHEN UPPER(MAX(delete_rule)) IN ('RESTRICT', 'NO ACTION') THEN
            'SELECT 1'
        ELSE
            'ALTER TABLE billing_payment_attempts DROP FOREIGN KEY fk_billing_attempt_farm, ADD CONSTRAINT fk_billing_attempt_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE RESTRICT'
    END
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND constraint_name = 'fk_billing_attempt_farm'
      AND table_name = 'billing_payment_attempts'
      AND referenced_table_name = 'farms'
);

PREPARE billing_attempt_farm_fk_stmt
    FROM @billing_attempt_farm_fk_sql;
EXECUTE billing_attempt_farm_fk_stmt;
DEALLOCATE PREPARE billing_attempt_farm_fk_stmt;
