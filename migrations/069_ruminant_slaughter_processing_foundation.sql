-- Renee Farms V3.0.1 — Ruminant slaughter processing foundation.
--
-- A slaughter lifecycle event removes one tagged animal from live population.
-- This schema records the resulting processing batch and source-specific
-- Inventory outputs. Physical Inventory mutation remains owned exclusively by
-- stock_service.php / stock_transactions.
--
-- remaining_quantity is the source-specific lot balance. A later Sales
-- allocation migration/service will reduce it atomically with canonical
-- Inventory usage and mark the batch completed when every output is depleted.
--
-- No historical slaughter exits are backfilled automatically.

CREATE TABLE IF NOT EXISTS ruminant_slaughter_batches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    farm_id INT NOT NULL,
    animal_id INT NOT NULL,
    exit_event_id BIGINT UNSIGNED NOT NULL,
    cycle_id INT NOT NULL,

    batch_code VARCHAR(80) NOT NULL,
    slaughter_date DATE NOT NULL,

    status ENUM('open','completed') NOT NULL DEFAULT 'open',
    notes VARCHAR(255) NULL,

    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uniq_ruminant_slaughter_exit (
        farm_id,
        exit_event_id
    ),

    UNIQUE KEY uniq_ruminant_slaughter_batch_code (
        farm_id,
        batch_code
    ),

    KEY idx_ruminant_slaughter_animal (
        farm_id,
        animal_id,
        slaughter_date
    ),

    KEY idx_ruminant_slaughter_cycle (
        farm_id,
        cycle_id,
        slaughter_date
    ),

    KEY idx_ruminant_slaughter_created_by (
        created_by
    ),

    CONSTRAINT fk_rsb_farm
        FOREIGN KEY (farm_id)
        REFERENCES farms(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_rsb_animal
        FOREIGN KEY (animal_id)
        REFERENCES ruminant_animals(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_rsb_exit
        FOREIGN KEY (exit_event_id)
        REFERENCES ruminant_animal_exit_events(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_rsb_cycle
        FOREIGN KEY (cycle_id)
        REFERENCES production_cycles(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_rsb_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS ruminant_slaughter_outputs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    farm_id INT NOT NULL,
    batch_id BIGINT UNSIGNED NOT NULL,

    stock_item_id INT NOT NULL,
    stock_transaction_id INT NULL,

    initial_quantity DECIMAL(12,2) NOT NULL,
    remaining_quantity DECIMAL(12,2) NOT NULL,
    unit VARCHAR(50) NOT NULL,

    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uniq_ruminant_slaughter_output_item (
        farm_id,
        batch_id,
        stock_item_id
    ),

    UNIQUE KEY uniq_ruminant_slaughter_output_stock_tx (
        stock_transaction_id
    ),

    KEY idx_ruminant_slaughter_output_batch (
        farm_id,
        batch_id
    ),

    KEY idx_ruminant_slaughter_output_item (
        farm_id,
        stock_item_id
    ),

    KEY idx_ruminant_slaughter_output_created_by (
        created_by
    ),

    CONSTRAINT chk_ruminant_slaughter_output_initial
        CHECK (initial_quantity > 0),

    CONSTRAINT chk_ruminant_slaughter_output_remaining
        CHECK (
            remaining_quantity >= 0
            AND remaining_quantity <= initial_quantity
        ),

    CONSTRAINT fk_rso_farm
        FOREIGN KEY (farm_id)
        REFERENCES farms(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_rso_batch
        FOREIGN KEY (batch_id)
        REFERENCES ruminant_slaughter_batches(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_rso_stock_item
        FOREIGN KEY (stock_item_id)
        REFERENCES stock_items(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_rso_stock_transaction
        FOREIGN KEY (stock_transaction_id)
        REFERENCES stock_transactions(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_rso_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


INSERT INTO schema_migrations (filename)
VALUES ('069_ruminant_slaughter_processing_foundation.sql')
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
