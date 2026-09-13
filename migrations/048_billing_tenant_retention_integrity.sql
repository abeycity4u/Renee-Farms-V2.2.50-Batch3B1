-- V2.3 Billing Tenant Retention Integrity
--
-- Payment-attempt history is commercial audit evidence and must not disappear
-- merely because a tenant/farm is deleted.
--
-- This migration changes FK delete semantics only. It does not insert, update,
-- delete, backfill, or otherwise rewrite billing/payment rows.
--
-- Safe replacement strategy:
-- 1. Establish a new permanent RESTRICT foreign key first.
-- 2. Only after that protected relationship exists, remove the legacy CASCADE FK.
--
-- If the legacy DROP fails, the new RESTRICT FK remains active, so deletion stays
-- fail-closed rather than leaving billing attempts without farm protection.

SET @billing_attempt_farm_restrict_add_sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE billing_payment_attempts ADD CONSTRAINT fk_billing_attempt_farm_restrict FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE RESTRICT',
        'SELECT 1'
    )
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND constraint_name = 'fk_billing_attempt_farm_restrict'
      AND table_name = 'billing_payment_attempts'
      AND referenced_table_name = 'farms'
      AND UPPER(delete_rule) IN ('RESTRICT', 'NO ACTION')
);

PREPARE billing_attempt_farm_restrict_add_stmt
    FROM @billing_attempt_farm_restrict_add_sql;
EXECUTE billing_attempt_farm_restrict_add_stmt;
DEALLOCATE PREPARE billing_attempt_farm_restrict_add_stmt;

SET @billing_attempt_farm_legacy_drop_sql = (
    SELECT IF(
        (
            SELECT COUNT(*)
            FROM information_schema.referential_constraints
            WHERE constraint_schema = DATABASE()
              AND constraint_name = 'fk_billing_attempt_farm_restrict'
              AND table_name = 'billing_payment_attempts'
              AND referenced_table_name = 'farms'
              AND UPPER(delete_rule) IN ('RESTRICT', 'NO ACTION')
        ) > 0
        AND
        (
            SELECT COUNT(*)
            FROM information_schema.referential_constraints
            WHERE constraint_schema = DATABASE()
              AND constraint_name = 'fk_billing_attempt_farm'
              AND table_name = 'billing_payment_attempts'
              AND referenced_table_name = 'farms'
        ) > 0,
        'ALTER TABLE billing_payment_attempts DROP FOREIGN KEY fk_billing_attempt_farm',
        'SELECT 1'
    )
);

PREPARE billing_attempt_farm_legacy_drop_stmt
    FROM @billing_attempt_farm_legacy_drop_sql;
EXECUTE billing_attempt_farm_legacy_drop_stmt;
DEALLOCATE PREPARE billing_attempt_farm_legacy_drop_stmt;
