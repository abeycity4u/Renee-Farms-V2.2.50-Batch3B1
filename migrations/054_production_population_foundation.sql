-- Renee Farms V3.0 — canonical production population foundation.
--
-- This migration is deliberately additive.
--
-- It does NOT:
--   * backfill historical V2.x population events;
--   * reinterpret mortality, sales, registry status, or legacy Daily Records;
--   * alter existing Layer, Broiler, Ruminant, Sales, Registry, or cycle rows;
--   * activate any existing cycle automatically.
--
-- production_population_baselines establishes the explicitly accepted starting
-- population for a cycle under the V3 population contract.
--
-- production_population_movements is an immutable quantity ledger after that
-- baseline. Corrections are represented by compensating/reversal movements and
-- a later source version. Historical movement rows are never silently rewritten.
--
-- Application services remain responsible for:
--   * validating that a baseline farm owns its production cycle;
--   * permitted movement/source types and sign;
--   * movement date validation;
--   * preventing any population balance from becoming negative;
--   * source/request idempotency;
--   * exact reversal quantity/type semantics.
--
-- Once a baseline exists, the database itself requires every population
-- movement to belong to that exact farm + cycle baseline.
--
-- Generic source_type/source_id linkage intentionally avoids coupling this
-- foundation to Sales, Daily Record, Animal Registry, or Transfer schemas.

CREATE TABLE IF NOT EXISTS production_population_baselines (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    farm_id INT NOT NULL,
    cycle_id INT NOT NULL,
    baseline_date DATE NOT NULL,
    baseline_quantity INT UNSIGNED NOT NULL,
    baseline_source VARCHAR(40) NOT NULL,
    notes VARCHAR(255) NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uniq_population_baseline_cycle (
        farm_id,
        cycle_id
    ),

    KEY idx_population_baseline_date (
        farm_id,
        baseline_date
    ),

    KEY idx_population_baseline_cycle (
        cycle_id
    ),

    KEY idx_population_baseline_user (
        created_by
    ),

    CONSTRAINT fk_population_baseline_farm
        FOREIGN KEY (farm_id)
        REFERENCES farms(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_population_baseline_cycle
        FOREIGN KEY (cycle_id)
        REFERENCES production_cycles(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_population_baseline_user
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS production_population_movements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    farm_id INT NOT NULL,
    cycle_id INT NOT NULL,

    movement_date DATE NOT NULL,
    movement_type VARCHAR(40) NOT NULL,

    -- Signed physical headcount effect.
    -- Positive = population enters the cycle.
    -- Negative = population leaves the cycle.
    quantity_delta INT NOT NULL,

    -- Generic durable ownership reference.
    -- Examples later may include:
    -- daily_record, sale, ruminant_exit, transfer,
    -- poultry_acquisition, adjustment.
    source_type VARCHAR(40) NOT NULL,
    source_id BIGINT UNSIGNED NULL,

    -- Allows an immutable source-linked movement to be superseded by a later
    -- corrected version without rewriting the original historical movement.
    source_version INT UNSIGNED NOT NULL DEFAULT 1,

    -- Server-generated idempotency token for a single request-owned movement.
    -- Multi-row operations such as transfers use their durable source identity.
    request_token VARCHAR(64) NULL,

    -- Corrections never rewrite the original movement. A compensating row
    -- references the exact movement it reverses.
    reversal_of_id BIGINT UNSIGNED NULL,

    notes VARCHAR(255) NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    -- Supports a tenant/cycle-safe self-reference for reversals.
    UNIQUE KEY uniq_population_movement_identity (
        farm_id,
        cycle_id,
        id
    ),

    KEY idx_population_movement_cycle_date (
        farm_id,
        cycle_id,
        movement_date,
        id
    ),

    KEY idx_population_movement_source (
        farm_id,
        source_type,
        source_id
    ),

    KEY idx_population_movement_type_date (
        farm_id,
        movement_type,
        movement_date
    ),

    KEY idx_population_movement_cycle_fk (
        cycle_id
    ),

    KEY idx_population_movement_user (
        created_by
    ),

    -- A durable source fact/version may create at most one canonical movement
    -- for the same cycle. NULL source_id is reserved for request/manual writers,
    -- whose submission idempotency is enforced by request_token.
    UNIQUE KEY uniq_population_movement_source (
        farm_id,
        cycle_id,
        source_type,
        source_id,
        source_version
    ),

    UNIQUE KEY uniq_population_movement_request (
        farm_id,
        request_token
    ),

    -- Exactly one canonical compensating reversal per original movement.
    -- NULL remains repeatable for ordinary non-reversal rows.
    UNIQUE KEY uniq_population_movement_reversal (
        farm_id,
        cycle_id,
        reversal_of_id
    ),

    -- A V3 movement can only exist for a cycle that has an established
    -- population baseline in the same tenant.
    CONSTRAINT fk_population_movement_baseline
        FOREIGN KEY (farm_id, cycle_id)
        REFERENCES production_population_baselines(farm_id, cycle_id)
        ON DELETE RESTRICT,

    -- A reversal cannot point across tenant or cycle boundaries.
    CONSTRAINT fk_population_movement_reversal
        FOREIGN KEY (farm_id, cycle_id, reversal_of_id)
        REFERENCES production_population_movements(farm_id, cycle_id, id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_population_movement_user
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


INSERT INTO schema_migrations (filename)
VALUES ('054_production_population_foundation.sql')
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
