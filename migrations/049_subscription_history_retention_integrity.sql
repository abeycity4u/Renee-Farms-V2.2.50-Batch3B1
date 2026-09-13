-- V2.3 Subscription History Retention Integrity
--
-- subscriptions is the append-only commercial subscription history table.
-- Its rows must not disappear merely because a tenant/farm is deleted.
--
-- This migration changes FK delete semantics only. It does not insert, update,
-- delete, backfill, or otherwise rewrite subscription-history rows.
--
-- Safe replacement strategy:
-- 1. Establish a new permanent RESTRICT foreign key first.
-- 2. Only after that protected relationship exists, remove the legacy CASCADE FK.
--
-- If the legacy DROP fails, the new RESTRICT FK remains active, so deletion stays
-- fail-closed rather than leaving subscription history without farm protection.

SET @subscription_farm_restrict_add_sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE subscriptions ADD CONSTRAINT fk_subscription_farm_restrict FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE RESTRICT',
        'SELECT 1'
    )
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND constraint_name = 'fk_subscription_farm_restrict'
      AND table_name = 'subscriptions'
      AND referenced_table_name = 'farms'
      AND UPPER(delete_rule) IN ('RESTRICT', 'NO ACTION')
);

PREPARE subscription_farm_restrict_add_stmt
    FROM @subscription_farm_restrict_add_sql;
EXECUTE subscription_farm_restrict_add_stmt;
DEALLOCATE PREPARE subscription_farm_restrict_add_stmt;

SET @subscription_farm_legacy_drop_sql = (
    SELECT IF(
        (
            SELECT COUNT(*)
            FROM information_schema.referential_constraints
            WHERE constraint_schema = DATABASE()
              AND constraint_name = 'fk_subscription_farm_restrict'
              AND table_name = 'subscriptions'
              AND referenced_table_name = 'farms'
              AND UPPER(delete_rule) IN ('RESTRICT', 'NO ACTION')
        ) > 0
        AND
        (
            SELECT COUNT(*)
            FROM information_schema.referential_constraints
            WHERE constraint_schema = DATABASE()
              AND constraint_name = 'fk_subscription_farm'
              AND table_name = 'subscriptions'
              AND referenced_table_name = 'farms'
        ) > 0,
        'ALTER TABLE subscriptions DROP FOREIGN KEY fk_subscription_farm',
        'SELECT 1'
    )
);

PREPARE subscription_farm_legacy_drop_stmt
    FROM @subscription_farm_legacy_drop_sql;
EXECUTE subscription_farm_legacy_drop_stmt;
DEALLOCATE PREPARE subscription_farm_legacy_drop_stmt;
