-- Renee Farms V3.0.1 — Slaughter output costing foundation.
--
-- The batch snapshots the slaughtered animal's production cost basis as of the
-- slaughter date. Each physical output receives an explicit share of that
-- frozen batch basis. The resulting unit cost is passed to the canonical stock
-- writer; selling prices remain separate Sales facts and may change over time.
--
-- Existing output rows, if any, are left NULL and are not guessed/backfilled.

ALTER TABLE ruminant_slaughter_batches
    ADD COLUMN cost_basis_amount DECIMAL(14,2) NULL
        AFTER notes,
    ADD COLUMN cost_basis_purchase DECIMAL(14,2) NULL
        AFTER cost_basis_amount,
    ADD COLUMN cost_basis_direct_expense DECIMAL(14,2) NULL
        AFTER cost_basis_purchase,
    ADD COLUMN cost_basis_shared DECIMAL(14,2) NULL
        AFTER cost_basis_direct_expense,
    ADD COLUMN cost_basis_method VARCHAR(160) NULL
        AFTER cost_basis_shared,
    ADD COLUMN cost_basis_snapshot_at DATETIME NULL
        AFTER cost_basis_method;

ALTER TABLE ruminant_slaughter_outputs
    ADD COLUMN cost_share_percent DECIMAL(7,4) NULL
        AFTER unit,
    ADD COLUMN allocated_cost DECIMAL(14,2) NULL
        AFTER cost_share_percent,
    ADD COLUMN unit_cost_snapshot DECIMAL(14,4) NULL
        AFTER allocated_cost;

INSERT INTO schema_migrations (filename)
VALUES ('071_ruminant_slaughter_output_costing.sql')
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
