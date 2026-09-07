-- V2.3 Billing Stage 2G commercial application transactional-integrity repair.
--
-- Converts only the two legacy commercial-state tables that can still inherit a
-- MyISAM default, then restores their tenant foreign keys. The targeted runner
-- performs orphan/row-count preflight and applies this migration alone.
--
-- DO NOT invoke migration 003 from this migration.

ALTER TABLE farm_role_limits ENGINE=InnoDB;
ALTER TABLE farm_subscription_seat_addons ENGINE=InnoDB;

SET @v230_fk_role_limits_exists = (
    SELECT COUNT(*)
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND constraint_name = 'fk_farm_role_limits_farm'
);
SET @v230_fk_role_limits_sql = IF(
    @v230_fk_role_limits_exists = 0,
    'ALTER TABLE farm_role_limits ADD CONSTRAINT fk_farm_role_limits_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE v230_fk_role_limits_stmt FROM @v230_fk_role_limits_sql;
EXECUTE v230_fk_role_limits_stmt;
DEALLOCATE PREPARE v230_fk_role_limits_stmt;

SET @v230_fk_seat_addons_exists = (
    SELECT COUNT(*)
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND constraint_name = 'fk_farm_subscription_seat_addons_farm'
);
SET @v230_fk_seat_addons_sql = IF(
    @v230_fk_seat_addons_exists = 0,
    'ALTER TABLE farm_subscription_seat_addons ADD CONSTRAINT fk_farm_subscription_seat_addons_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE v230_fk_seat_addons_stmt FROM @v230_fk_seat_addons_sql;
EXECUTE v230_fk_seat_addons_stmt;
DEALLOCATE PREPARE v230_fk_seat_addons_stmt;

INSERT INTO schema_migrations (filename)
VALUES ('044_commercial_application_transactional_integrity.sql')
ON DUPLICATE KEY UPDATE filename = VALUES(filename);
