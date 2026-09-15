-- V3.0 Poultry Transactional Integrity Repair
--
-- Repairs legacy poultry lifecycle/acquisition tables that inherited the
-- server's non-transactional default storage engine (MyISAM).
--
-- The original migrations declared foreign keys, but MyISAM did not enforce
-- them. Existing data has been preflighted for cycle, farm, and user orphans.
--
-- Scope is intentionally limited:
--   * convert the two poultry fact/history tables to InnoDB;
--   * restore the six foreign keys originally intended by migrations 037/038;
--   * preserve existing rows, indexes, columns, values, and business meaning;
--   * do not alter the server-wide default storage engine.

ALTER TABLE poultry_cycle_acquisitions ENGINE=InnoDB;
ALTER TABLE production_cycle_phases ENGINE=InnoDB;

SET @poultry_fk_sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE poultry_cycle_acquisitions ADD CONSTRAINT fk_poultry_acquisition_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE',
        'SELECT 1'
    )
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'poultry_cycle_acquisitions'
      AND constraint_name = 'fk_poultry_acquisition_farm'
);
PREPARE poultry_fk_stmt FROM @poultry_fk_sql;
EXECUTE poultry_fk_stmt;
DEALLOCATE PREPARE poultry_fk_stmt;

SET @poultry_fk_sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE poultry_cycle_acquisitions ADD CONSTRAINT fk_poultry_acquisition_cycle FOREIGN KEY (cycle_id) REFERENCES production_cycles(id) ON DELETE RESTRICT',
        'SELECT 1'
    )
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'poultry_cycle_acquisitions'
      AND constraint_name = 'fk_poultry_acquisition_cycle'
);
PREPARE poultry_fk_stmt FROM @poultry_fk_sql;
EXECUTE poultry_fk_stmt;
DEALLOCATE PREPARE poultry_fk_stmt;

SET @poultry_fk_sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE poultry_cycle_acquisitions ADD CONSTRAINT fk_poultry_acquisition_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL',
        'SELECT 1'
    )
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'poultry_cycle_acquisitions'
      AND constraint_name = 'fk_poultry_acquisition_user'
);
PREPARE poultry_fk_stmt FROM @poultry_fk_sql;
EXECUTE poultry_fk_stmt;
DEALLOCATE PREPARE poultry_fk_stmt;

SET @poultry_fk_sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE production_cycle_phases ADD CONSTRAINT fk_poultry_phase_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE',
        'SELECT 1'
    )
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'production_cycle_phases'
      AND constraint_name = 'fk_poultry_phase_farm'
);
PREPARE poultry_fk_stmt FROM @poultry_fk_sql;
EXECUTE poultry_fk_stmt;
DEALLOCATE PREPARE poultry_fk_stmt;

SET @poultry_fk_sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE production_cycle_phases ADD CONSTRAINT fk_poultry_phase_cycle FOREIGN KEY (cycle_id) REFERENCES production_cycles(id) ON DELETE RESTRICT',
        'SELECT 1'
    )
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'production_cycle_phases'
      AND constraint_name = 'fk_poultry_phase_cycle'
);
PREPARE poultry_fk_stmt FROM @poultry_fk_sql;
EXECUTE poultry_fk_stmt;
DEALLOCATE PREPARE poultry_fk_stmt;

SET @poultry_fk_sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE production_cycle_phases ADD CONSTRAINT fk_poultry_phase_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL',
        'SELECT 1'
    )
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'production_cycle_phases'
      AND constraint_name = 'fk_poultry_phase_user'
);
PREPARE poultry_fk_stmt FROM @poultry_fk_sql;
EXECUTE poultry_fk_stmt;
DEALLOCATE PREPARE poultry_fk_stmt;
