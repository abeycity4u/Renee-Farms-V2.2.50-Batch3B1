-- Renee Farms V3.0.1 — Poultry slaughter / processed-output foundation.
--
-- Poultry slaughter is a physical transformation, not a financial sale.
--
-- poultry_slaughter_batches owns the durable physical processing event.
-- Its ID is the durable source used by the canonical population projection
-- with source_type=poultry_slaughter and movement_type=slaughter.
--
-- poultry_slaughter_outputs owns source-specific processed-product balances.
-- Physical stock mutation remains owned exclusively by stock_service.php /
-- stock_transactions.
--
-- poultry_slaughter_sale_allocations links processed output lots to ordinary
-- Sales records while preserving append-only Inventory correction history.
--
-- poultry_slaughter_batch_expenses snapshots canonical farm-expense revision
-- provenance used in frozen slaughter valuation. farm_expenses remains the
-- Profitability authority for those operating costs.
--
-- No historical poultry slaughter is inferred or backfilled.

CREATE TABLE IF NOT EXISTS poultry_slaughter_batches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    farm_id INT NOT NULL,
    cycle_id INT NOT NULL,

    batch_code VARCHAR(80) NOT NULL,
    request_token VARCHAR(64) NOT NULL,

    slaughter_date DATE NOT NULL,
    bird_count INT UNSIGNED NOT NULL,

    live_weight_total_kg DECIMAL(14,4) NULL,

    population_before INT UNSIGNED NOT NULL,

    capital_pool_available_before DECIMAL(14,2) NOT NULL,
    operating_pool_available_before DECIMAL(14,2) NOT NULL,

    capital_basis_transferred DECIMAL(14,2) NOT NULL,
    embedded_operating_basis_transferred DECIMAL(14,2) NOT NULL,
    processing_operating_cost DECIMAL(14,2) NOT NULL DEFAULT 0,

    full_cost_basis_amount DECIMAL(14,2) NOT NULL,

    cost_basis_method VARCHAR(160) NOT NULL,
    cost_basis_snapshot_at DATETIME NOT NULL,

    cost_basis_finalized_at DATETIME NULL,
    cost_basis_finalized_by INT NULL,

    cost_basis_provenance_fingerprint CHAR(64) NOT NULL,
    cost_basis_provenance_json LONGTEXT NOT NULL,

    status ENUM('open','completed','reversed')
        NOT NULL DEFAULT 'open',

    notes VARCHAR(255) NULL,

    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    reversed_by INT NULL,
    reversed_at DATETIME NULL,
    reversal_reason VARCHAR(255) NULL,

    PRIMARY KEY (id),

    UNIQUE KEY uniq_poultry_slaughter_batch_code (
        farm_id,
        batch_code
    ),

    UNIQUE KEY uniq_poultry_slaughter_request (
        farm_id,
        request_token
    ),

    KEY idx_poultry_slaughter_cycle (
        farm_id,
        cycle_id,
        slaughter_date,
        id
    ),

    KEY idx_poultry_slaughter_status (
        farm_id,
        status,
        slaughter_date
    ),

    KEY idx_poultry_slaughter_cost_finalized_by (
        cost_basis_finalized_by
    ),

    KEY idx_poultry_slaughter_created_by (
        created_by
    ),

    KEY idx_poultry_slaughter_reversed_by (
        reversed_by
    ),

    CONSTRAINT chk_poultry_slaughter_bird_count
        CHECK (bird_count > 0),

    CONSTRAINT chk_poultry_slaughter_population_before
        CHECK (population_before >= bird_count),

    CONSTRAINT chk_poultry_slaughter_live_weight
        CHECK (
            live_weight_total_kg IS NULL
            OR live_weight_total_kg > 0
        ),

    CONSTRAINT chk_poultry_slaughter_capital_pool
        CHECK (capital_pool_available_before >= 0),

    CONSTRAINT chk_poultry_slaughter_operating_pool
        CHECK (operating_pool_available_before >= 0),

    CONSTRAINT chk_poultry_slaughter_capital_transfer
        CHECK (capital_basis_transferred >= 0),

    CONSTRAINT chk_poultry_slaughter_operating_transfer
        CHECK (embedded_operating_basis_transferred >= 0),

    CONSTRAINT chk_poultry_slaughter_processing_cost
        CHECK (processing_operating_cost >= 0),

    CONSTRAINT chk_poultry_slaughter_full_cost
        CHECK (full_cost_basis_amount >= 0),

    CONSTRAINT chk_poultry_slaughter_capital_pool_conservation
        CHECK (
            capital_basis_transferred
            <= capital_pool_available_before
        ),

    CONSTRAINT chk_poultry_slaughter_operating_pool_conservation
        CHECK (
            embedded_operating_basis_transferred
            <= operating_pool_available_before
        ),

    CONSTRAINT chk_poultry_slaughter_full_cost_conservation
        CHECK (
            full_cost_basis_amount
            = capital_basis_transferred
              + embedded_operating_basis_transferred
              + processing_operating_cost
        ),

    -- cost_basis_finalized_at is the durable finalized-state authority.
    --
    -- cost_basis_finalized_by intentionally remains nullable attribution with
    -- ON DELETE SET NULL below, matching the platform-wide user-deletion
    -- contract. MariaDB does not permit a CHECK constraint to reference a
    -- column governed by an ON DELETE SET NULL foreign key.
    --
    -- The central Poultry slaughter service writes finalized_at and
    -- finalized_by together in one guarded UPDATE. If the user is later
    -- deleted, the timestamp remains frozen while actor attribution may
    -- become NULL without changing the finalized state.

    CONSTRAINT fk_psb_farm
        FOREIGN KEY (farm_id)
        REFERENCES farms(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_psb_cycle
        FOREIGN KEY (cycle_id)
        REFERENCES production_cycles(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_psb_cost_finalized_by
        FOREIGN KEY (cost_basis_finalized_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_psb_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_psb_reversed_by
        FOREIGN KEY (reversed_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS poultry_slaughter_batch_expenses (
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

    UNIQUE KEY uniq_psbe_request (
        farm_id,
        request_token
    ),

    UNIQUE KEY uniq_psbe_expense_revision (
        farm_id,
        batch_id,
        expense_revision_id
    ),

    KEY idx_psbe_batch (
        farm_id,
        batch_id,
        id
    ),

    KEY idx_psbe_expense (
        farm_id,
        expense_id,
        expense_revision_no
    ),

    KEY idx_psbe_expense_revision (
        expense_revision_id
    ),

    CONSTRAINT chk_psbe_revision
        CHECK (expense_revision_no > 0),

    CONSTRAINT chk_psbe_amount
        CHECK (amount_snapshot > 0),

    CONSTRAINT fk_psbe_farm
        FOREIGN KEY (farm_id)
        REFERENCES farms(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_psbe_batch
        FOREIGN KEY (batch_id)
        REFERENCES poultry_slaughter_batches(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_psbe_expense_revision
        FOREIGN KEY (expense_revision_id)
        REFERENCES farm_expense_revisions(id)
        ON DELETE RESTRICT

    -- Intentionally no FK to farm_expenses.
    -- farm_expenses is only the current projection. The immutable
    -- farm_expense_revisions row above anchors historical provenance.
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS poultry_slaughter_outputs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    farm_id INT NOT NULL,
    batch_id BIGINT UNSIGNED NOT NULL,

    stock_item_id INT NOT NULL,
    stock_transaction_id INT NULL,

    initial_quantity DECIMAL(12,2) NOT NULL,
    remaining_quantity DECIMAL(12,2) NOT NULL,
    unit VARCHAR(50) NOT NULL,

    cost_share_percent DECIMAL(7,4) NOT NULL,
    allocated_cost DECIMAL(14,2) NOT NULL,
    unit_cost_snapshot DECIMAL(14,4) NOT NULL,

    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uniq_poultry_slaughter_output_item (
        farm_id,
        batch_id,
        stock_item_id
    ),

    UNIQUE KEY uniq_poultry_slaughter_output_stock_tx (
        stock_transaction_id
    ),

    KEY idx_poultry_slaughter_output_batch (
        farm_id,
        batch_id
    ),

    KEY idx_poultry_slaughter_output_item (
        farm_id,
        stock_item_id
    ),

    KEY idx_poultry_slaughter_output_created_by (
        created_by
    ),

    CONSTRAINT chk_poultry_slaughter_output_initial
        CHECK (initial_quantity > 0),

    CONSTRAINT chk_poultry_slaughter_output_remaining
        CHECK (
            remaining_quantity >= 0
            AND remaining_quantity <= initial_quantity
        ),

    CONSTRAINT chk_poultry_slaughter_output_cost_share
        CHECK (
            cost_share_percent > 0
            AND cost_share_percent <= 100
        ),

    CONSTRAINT chk_poultry_slaughter_output_allocated_cost
        CHECK (allocated_cost >= 0),

    CONSTRAINT chk_poultry_slaughter_output_unit_cost
        CHECK (unit_cost_snapshot >= 0),

    CONSTRAINT fk_pso_farm
        FOREIGN KEY (farm_id)
        REFERENCES farms(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_pso_batch
        FOREIGN KEY (batch_id)
        REFERENCES poultry_slaughter_batches(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_pso_stock_item
        FOREIGN KEY (stock_item_id)
        REFERENCES stock_items(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_pso_stock_transaction
        FOREIGN KEY (stock_transaction_id)
        REFERENCES stock_transactions(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_pso_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS poultry_slaughter_sale_allocations (
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

    UNIQUE KEY uniq_pssa_stock_tx (
        stock_transaction_id
    ),

    UNIQUE KEY uniq_pssa_reversal_stock_tx (
        reversal_stock_transaction_id
    ),

    KEY idx_pssa_sale (
        farm_id,
        sale_id,
        is_active,
        id
    ),

    KEY idx_pssa_output (
        farm_id,
        output_id,
        is_active,
        id
    ),

    KEY idx_pssa_created_by (
        created_by
    ),

    KEY idx_pssa_reversed_by (
        reversed_by
    ),

    CONSTRAINT chk_pssa_quantity
        CHECK (quantity > 0),

    CONSTRAINT chk_pssa_unit_cost
        CHECK (unit_cost_snapshot >= 0),

    CONSTRAINT chk_pssa_total_cost
        CHECK (total_cost_snapshot >= 0),

    CONSTRAINT chk_pssa_active
        CHECK (is_active IN (0,1)),

    CONSTRAINT fk_pssa_farm
        FOREIGN KEY (farm_id)
        REFERENCES farms(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_pssa_sale
        FOREIGN KEY (sale_id)
        REFERENCES sales_records(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_pssa_output
        FOREIGN KEY (output_id)
        REFERENCES poultry_slaughter_outputs(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_pssa_stock_tx
        FOREIGN KEY (stock_transaction_id)
        REFERENCES stock_transactions(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_pssa_reversal_stock_tx
        FOREIGN KEY (reversal_stock_transaction_id)
        REFERENCES stock_transactions(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_pssa_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_pssa_reversed_by
        FOREIGN KEY (reversed_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


INSERT INTO schema_migrations (filename)
VALUES ('079_poultry_slaughter_processing_foundation.sql')
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
