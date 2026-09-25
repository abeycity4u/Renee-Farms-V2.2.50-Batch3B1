-- Renee Farms V3.0.1 — Ruminant slaughter processing expense provenance.
--
-- farm_expenses remains the canonical financial / Profitability authority.
-- ruminant_expense_animal_allocations remains the direct-animal allocation authority.
-- This table only anchors immutable expense-revision provenance to a slaughter batch.
--
-- Existing Ruminant slaughter batches are intentionally NOT backfilled.
-- Existing frozen cost-basis snapshots must never be rewritten by this migration.

CREATE TABLE IF NOT EXISTS ruminant_slaughter_batch_expenses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    farm_id INT NOT NULL,
    batch_id BIGINT UNSIGNED NOT NULL,

    request_token VARCHAR(64) NOT NULL,
    request_fingerprint CHAR(64) NOT NULL,

    expense_id INT NOT NULL,
    expense_revision_id BIGINT UNSIGNED NOT NULL,
    expense_revision_no INT UNSIGNED NOT NULL,
    expense_causal_fingerprint CHAR(64) NOT NULL,

    amount_snapshot DECIMAL(14,2) NOT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uniq_rsbe_request (
        farm_id,
        request_token
    ),

    UNIQUE KEY uniq_rsbe_expense_revision (
        farm_id,
        batch_id,
        expense_revision_id
    ),

    KEY idx_rsbe_batch (
        farm_id,
        batch_id,
        id
    ),

    KEY idx_rsbe_expense (
        farm_id,
        expense_id,
        expense_revision_no
    ),

    KEY idx_rsbe_expense_revision (
        expense_revision_id
    ),

    CONSTRAINT chk_rsbe_revision
        CHECK (expense_revision_no > 0),

    CONSTRAINT chk_rsbe_amount
        CHECK (amount_snapshot > 0),

    CONSTRAINT fk_rsbe_farm
        FOREIGN KEY (farm_id)
        REFERENCES farms(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_rsbe_batch
        FOREIGN KEY (batch_id)
        REFERENCES ruminant_slaughter_batches(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_rsbe_expense_revision
        FOREIGN KEY (expense_revision_id)
        REFERENCES farm_expense_revisions(id)
        ON DELETE RESTRICT

    -- Intentionally no FK to farm_expenses.
    -- farm_expenses is the mutable current projection.
    -- The immutable farm_expense_revisions row above is historical authority.
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


INSERT INTO schema_migrations (filename)
VALUES ('083_ruminant_slaughter_processing_expense_provenance.sql')
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
