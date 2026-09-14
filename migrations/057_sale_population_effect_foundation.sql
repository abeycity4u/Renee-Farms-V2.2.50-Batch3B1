-- Renee Farms V3.0 — explicit Sales population-effect foundation.
--
-- sales_records remains the financial source of truth.
-- sale_population_effects stores only explicit physical headcount removals
-- associated with a sale.
--
-- This migration deliberately:
--   * performs no historical backfill;
--   * does not infer population from product_type or unit_of_measure;
--   * does not alter existing sales_records or sales_allocations rows;
--   * does not own tagged-ruminant lifecycle exits;
--   * does not create production_population_movements by itself.
--
-- One row is one durable sale/cycle population fact.
-- Its id is used as source_id with source_type='sale' when projected through
-- the canonical population projection service.
--
-- Multiple rows for one sale allow an explicitly shared live-population sale
-- to remove known headcounts from multiple production cycles.
--
-- Farm-scoped parent identity keys let the database enforce that both the
-- financial sale and physical source cycle belong to the same tenant.

ALTER TABLE sales_records
    ADD UNIQUE KEY uniq_sales_record_farm_identity (
        farm_id,
        id
    );

ALTER TABLE production_cycles
    ADD UNIQUE KEY uniq_production_cycle_farm_identity (
        farm_id,
        id
    );


CREATE TABLE IF NOT EXISTS sale_population_effects (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    farm_id INT NOT NULL,
    sale_id INT NOT NULL,
    cycle_id INT NOT NULL,

    -- Explicit whole physical headcount removed from this cycle.
    -- This is independent from sales_records.quantity/unit_of_measure.
    population_quantity INT UNSIGNED NOT NULL,

    created_by INT NULL,
    updated_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    -- A sale has at most one physical population fact per source cycle.
    UNIQUE KEY uniq_sale_population_effect_cycle (
        farm_id,
        sale_id,
        cycle_id
    ),

    KEY idx_sale_population_effect_sale (
        farm_id,
        sale_id,
        id
    ),

    KEY idx_sale_population_effect_cycle (
        farm_id,
        cycle_id,
        id
    ),

    KEY idx_sale_population_effect_sale_fk (
        sale_id
    ),

    KEY idx_sale_population_effect_cycle_fk (
        cycle_id
    ),

    KEY idx_sale_population_effect_created_by (
        created_by
    ),

    KEY idx_sale_population_effect_updated_by (
        updated_by
    ),

    CONSTRAINT fk_sale_population_effect_farm
        FOREIGN KEY (farm_id)
        REFERENCES farms(id)
        ON DELETE RESTRICT,

    -- RESTRICT is intentional. A future sale-delete workflow must first
    -- reverse/remove its population projections and durable effect rows.
    CONSTRAINT fk_sale_population_effect_sale
        FOREIGN KEY (farm_id, sale_id)
        REFERENCES sales_records(farm_id, id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_sale_population_effect_cycle
        FOREIGN KEY (farm_id, cycle_id)
        REFERENCES production_cycles(farm_id, id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_sale_population_effect_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_sale_population_effect_updated_by
        FOREIGN KEY (updated_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT chk_sale_population_effect_quantity
        CHECK (population_quantity > 0)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


INSERT INTO schema_migrations (filename)
VALUES (
    '057_sale_population_effect_foundation.sql'
)
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
