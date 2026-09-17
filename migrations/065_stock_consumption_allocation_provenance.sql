-- Renee Farms V3.0.1
-- Consumed-stock allocation persistence and immutable provenance foundation.
--
-- Shared-cost policy is source-neutral in application code, while persistence
-- remains source-native:
--
--   farm expense       -> financial_allocations + farm_expense_revisions
--   consumed inventory -> stock_consumption_allocations
--                         + append-only allocation revisions
--
-- stock_transactions remains the immutable physical/economic stock ledger.
-- This migration does not rewrite or backfill historical stock movements.
--
-- allocated_amount is economic authority.
-- allocation_percent is derived/display metadata.
--
-- stock_consumption_allocations is the current/live projection only.
-- Historical allocation states are preserved independently below.
--
-- No foreign key is placed from historical allocation revisions back to the
-- stock transaction or production cycle. Historical evidence must remain
-- intelligible even after later lifecycle/correction events.

CREATE TABLE IF NOT EXISTS stock_consumption_allocations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    farm_id INT NOT NULL,
    stock_transaction_id INT NOT NULL,
    cycle_id INT NOT NULL,

    allocated_amount DECIMAL(14,2) NOT NULL,
    allocation_percent DECIMAL(7,4) NOT NULL,
    notes VARCHAR(255) NULL,

    allocation_revision_no INT UNSIGNED NOT NULL,

    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_by INT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,

    PRIMARY KEY (id),

    UNIQUE KEY uniq_stock_consumption_allocation (
        farm_id,
        stock_transaction_id,
        cycle_id
    ),

    KEY idx_stock_consumption_allocation_source (
        farm_id,
        stock_transaction_id
    ),

    KEY idx_stock_consumption_allocation_cycle (
        farm_id,
        cycle_id
    ),

    CONSTRAINT fk_stock_consumption_allocation_farm
        FOREIGN KEY (farm_id)
        REFERENCES farms(id)
        ON DELETE CASCADE
        ON UPDATE RESTRICT,

    CONSTRAINT fk_stock_consumption_allocation_cycle
        FOREIGN KEY (cycle_id)
        REFERENCES production_cycles(id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,

    CONSTRAINT fk_stock_consumption_allocation_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE RESTRICT,

    CONSTRAINT fk_stock_consumption_allocation_updated_by
        FOREIGN KEY (updated_by)
        REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE RESTRICT

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS stock_consumption_allocation_revisions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    farm_id INT NOT NULL,
    stock_transaction_id INT NOT NULL,

    revision_no INT UNSIGNED NOT NULL,
    revision_action VARCHAR(32) NOT NULL,
    revision_reason VARCHAR(500) NULL,

    previous_revision_id BIGINT UNSIGNED NULL,

    parent_amount DECIMAL(14,2) NOT NULL,
    allocated_amount DECIMAL(14,2) NOT NULL,
    unallocated_amount DECIMAL(14,2) NOT NULL,

    causal_fingerprint CHAR(64) NOT NULL,
    causal_manifest_json LONGTEXT NOT NULL,

    state_fingerprint CHAR(64) NOT NULL,
    state_manifest_json LONGTEXT NOT NULL,

    changed_by_user_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uniq_stock_consumption_allocation_revision (
        farm_id,
        stock_transaction_id,
        revision_no
    ),

    KEY idx_stock_consumption_allocation_revision_source (
        farm_id,
        stock_transaction_id,
        id
    ),

    KEY idx_stock_consumption_allocation_revision_previous (
        previous_revision_id
    ),

    CONSTRAINT fk_stock_consumption_allocation_revision_farm
        FOREIGN KEY (farm_id)
        REFERENCES farms(id)
        ON DELETE CASCADE
        ON UPDATE RESTRICT,

    CONSTRAINT fk_stock_consumption_allocation_revision_user
        FOREIGN KEY (changed_by_user_id)
        REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE RESTRICT

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS stock_consumption_allocation_revision_rows (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    revision_id BIGINT UNSIGNED NOT NULL,
    cycle_id INT NOT NULL,

    allocated_amount DECIMAL(14,2) NOT NULL,
    allocation_percent DECIMAL(7,4) NOT NULL,
    notes VARCHAR(255) NULL,

    PRIMARY KEY (id),

    UNIQUE KEY uniq_stock_consumption_allocation_revision_cycle (
        revision_id,
        cycle_id
    ),

    KEY idx_stock_consumption_allocation_revision_row_cycle (
        cycle_id
    ),

    CONSTRAINT fk_stock_consumption_allocation_revision_row
        FOREIGN KEY (revision_id)
        REFERENCES stock_consumption_allocation_revisions(id)
        ON DELETE CASCADE
        ON UPDATE RESTRICT

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


INSERT INTO schema_migrations (filename)
VALUES ('065_stock_consumption_allocation_provenance.sql')
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
