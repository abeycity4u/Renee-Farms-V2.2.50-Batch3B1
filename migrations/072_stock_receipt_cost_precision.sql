-- Renee Farms V3.0.1 — stock receipt cost precision.
--
-- Current Inventory valuation already computes and replays weighted unit cost
-- to four decimals. Widen the persisted current unit-cost cache to the same
-- precision so source-derived costs are not rounded down to currency cents.
--
-- No historical stock transaction is updated by this migration.

ALTER TABLE stock_items
    MODIFY COLUMN unit_cost
        DECIMAL(14,4)
        NOT NULL
        DEFAULT 0;

INSERT INTO schema_migrations (filename)
VALUES ('072_stock_receipt_cost_precision.sql')
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
