-- Renee Farms V3.0.1 — slaughter-output Sales lot consumption foundation.
--
-- One row records one physical decrement from one slaughter output lot into one
-- financial sale. Rows are durable audit history: edits/corrections deactivate
-- the old allocation through an append-only stock reversal and append a new
-- active allocation instead of rewriting physical history.
--
-- No historical sales are inferred or backfilled.

CREATE TABLE IF NOT EXISTS ruminant_slaughter_sale_allocations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    farm_id INT NOT NULL,
    sale_id INT NOT NULL,
    output_id BIGINT UNSIGNED NOT NULL,

    stock_transaction_id INT NULL,
    reversal_stock_transaction_id INT NULL,

    quantity DECIMAL(12,2) NOT NULL,
    unit VARCHAR(50) NOT NULL,

    unit_cost_snapshot DECIMAL(14,4) NOT NULL,
    total_cost_snapshot DECIMAL(14,2) NOT NULL,

    is_active TINYINT(1) NOT NULL DEFAULT 1,

    created_by INT NULL,
    reversed_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reversed_at DATETIME NULL,

    PRIMARY KEY (id),

    UNIQUE KEY uniq_rssa_stock_tx (
        stock_transaction_id
    ),

    UNIQUE KEY uniq_rssa_reversal_stock_tx (
        reversal_stock_transaction_id
    ),

    KEY idx_rssa_sale (
        farm_id,
        sale_id,
        is_active,
        id
    ),

    KEY idx_rssa_output (
        farm_id,
        output_id,
        is_active,
        id
    ),

    KEY idx_rssa_created_by (
        created_by
    ),

    KEY idx_rssa_reversed_by (
        reversed_by
    ),

    CONSTRAINT chk_rssa_quantity
        CHECK (quantity > 0),

    CONSTRAINT chk_rssa_unit_cost
        CHECK (unit_cost_snapshot >= 0),

    CONSTRAINT chk_rssa_total_cost
        CHECK (total_cost_snapshot >= 0),

    CONSTRAINT chk_rssa_active
        CHECK (is_active IN (0,1)),

    CONSTRAINT fk_rssa_farm
        FOREIGN KEY (farm_id)
        REFERENCES farms(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_rssa_sale
        FOREIGN KEY (sale_id)
        REFERENCES sales_records(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_rssa_output
        FOREIGN KEY (output_id)
        REFERENCES ruminant_slaughter_outputs(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_rssa_stock_tx
        FOREIGN KEY (stock_transaction_id)
        REFERENCES stock_transactions(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_rssa_reversal_stock_tx
        FOREIGN KEY (reversal_stock_transaction_id)
        REFERENCES stock_transactions(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_rssa_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_rssa_reversed_by
        FOREIGN KEY (reversed_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


INSERT INTO schema_migrations (filename)
VALUES ('074_ruminant_slaughter_sale_lot_consumption.sql')
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
