-- Renee Farms V3.0.1 — append-only stock receipt cost adjustments.
--
-- Physical stock transactions remain immutable. Proven monetary corrections
-- to posted RECEIVED movements are stored separately and projected into
-- effective receipt cost by the canonical costing layer.
--
-- This migration creates infrastructure only. It does not rewrite historical
-- stock transactions, stock quantities, slaughter outputs or population data.

CREATE TABLE IF NOT EXISTS stock_receipt_cost_adjustments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    farm_id INT NOT NULL,
    stock_transaction_id INT NOT NULL,
    amount_delta DECIMAL(14,2) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    source_type VARCHAR(80) NOT NULL,
    source_id BIGINT UNSIGNED NOT NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uniq_stock_receipt_cost_adjustment_source (
        farm_id,
        source_type,
        source_id
    ),

    INDEX idx_stock_receipt_cost_adjustment_tx (
        farm_id,
        stock_transaction_id,
        id
    ),

    CONSTRAINT fk_stock_receipt_cost_adjustment_farm
        FOREIGN KEY (farm_id)
        REFERENCES farms(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_stock_receipt_cost_adjustment_tx
        FOREIGN KEY (stock_transaction_id)
        REFERENCES stock_transactions(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_stock_receipt_cost_adjustment_user
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (filename)
VALUES ('073_stock_receipt_cost_adjustments.sql')
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
