-- Renee Farms V3.0 — paired production population transfer foundation.
--
-- One physical transfer is one parent business fact with exactly two durable
-- population-source legs:
--   OUT leg -> transfer_out from the source production cycle
--   IN leg  -> transfer_in  to the destination production cycle
--
-- The legs deliberately have separate durable IDs because canonical population
-- source identity is global within a farm. One source ID must never own two
-- simultaneously-active population movements.
--
-- This migration:
--   * does not backfill historical transfers;
--   * does not reinterpret Registry "Transferred" statuses;
--   * does not create population movements;
--   * does not alter existing Daily Records, Sales, Registry, or cycle rows.
--
-- Both cycles must already have a V3 population baseline before a transfer
-- parent/leg can exist.

CREATE TABLE IF NOT EXISTS production_population_transfers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    farm_id INT NOT NULL,

    from_cycle_id INT NOT NULL,
    to_cycle_id INT NOT NULL,

    transfer_date DATE NOT NULL,
    quantity INT UNSIGNED NOT NULL,

    notes VARCHAR(255) NULL,
    request_token VARCHAR(64) NOT NULL,

    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    reversed_at DATETIME NULL,
    reversed_by INT NULL,
    reversal_reason VARCHAR(255) NULL,

    PRIMARY KEY (id),

    UNIQUE KEY uniq_population_transfer_identity (
        farm_id,
        id
    ),

    UNIQUE KEY uniq_population_transfer_request (
        farm_id,
        request_token
    ),

    KEY idx_population_transfer_from_cycle (
        farm_id,
        from_cycle_id,
        transfer_date
    ),

    KEY idx_population_transfer_to_cycle (
        farm_id,
        to_cycle_id,
        transfer_date
    ),

    KEY idx_population_transfer_created_by (
        created_by
    ),

    KEY idx_population_transfer_reversed_by (
        reversed_by
    ),

    CONSTRAINT fk_population_transfer_farm
        FOREIGN KEY (farm_id)
        REFERENCES farms(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_population_transfer_from_baseline
        FOREIGN KEY (farm_id, from_cycle_id)
        REFERENCES production_population_baselines(farm_id, cycle_id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_population_transfer_to_baseline
        FOREIGN KEY (farm_id, to_cycle_id)
        REFERENCES production_population_baselines(farm_id, cycle_id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_population_transfer_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_population_transfer_reversed_by
        FOREIGN KEY (reversed_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS production_population_transfer_legs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    farm_id INT NOT NULL,
    transfer_id BIGINT UNSIGNED NOT NULL,
    cycle_id INT NOT NULL,

    direction VARCHAR(8) NOT NULL,

    population_movement_id BIGINT UNSIGNED NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uniq_population_transfer_leg_identity (
        farm_id,
        id
    ),

    UNIQUE KEY uniq_population_transfer_leg_direction (
        farm_id,
        transfer_id,
        direction
    ),

    UNIQUE KEY uniq_population_transfer_leg_movement (
        farm_id,
        cycle_id,
        population_movement_id
    ),

    KEY idx_population_transfer_leg_transfer (
        farm_id,
        transfer_id
    ),

    KEY idx_population_transfer_leg_cycle (
        farm_id,
        cycle_id
    ),

    CONSTRAINT fk_population_transfer_leg_parent
        FOREIGN KEY (farm_id, transfer_id)
        REFERENCES production_population_transfers(farm_id, id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_population_transfer_leg_baseline
        FOREIGN KEY (farm_id, cycle_id)
        REFERENCES production_population_baselines(farm_id, cycle_id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_population_transfer_leg_movement
        FOREIGN KEY (
            farm_id,
            cycle_id,
            population_movement_id
        )
        REFERENCES production_population_movements(
            farm_id,
            cycle_id,
            id
        )
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


INSERT INTO schema_migrations (filename)
VALUES ('060_production_population_transfers.sql')
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
