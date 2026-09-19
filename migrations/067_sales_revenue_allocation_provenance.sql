-- Renee Farms V3.0.1
-- Shared-revenue allocation immutable provenance foundation.
--
-- sales_records remains the immutable/current parent sale.
-- sales_allocations remains the current/live revenue-allocation projection.
--
-- Manual shared-revenue allocation history is preserved independently here.
-- Historical revision rows deliberately do NOT foreign-key back to the sale
-- or production cycle so later lifecycle changes cannot erase audit evidence.
--
-- Existing sales_allocations rows are intentionally NOT backfilled.
-- Automatic Layer-egg allocations predate this provenance contract and must
-- not be rewritten or represented as historical manual revisions.
--
-- Future manual shared-revenue mutations must:
--   * validate through the canonical revenue-allocation service;
--   * preserve the parent sale;
--   * write only the sales_allocations projection;
--   * append one immutable revision for every real change;
--   * treat allocated_amount as economic authority;
--   * derive allocation_percent;
--   * preserve any unallocated remainder visibly;
--   * never guess a distribution across cycles.

CREATE TABLE IF NOT EXISTS sales_allocation_revisions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    farm_id INT NOT NULL,
    sale_id INT NOT NULL,

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

    UNIQUE KEY uniq_sales_allocation_revision (
        farm_id,
        sale_id,
        revision_no
    ),

    KEY idx_sales_allocation_revision_source (
        farm_id,
        sale_id,
        id
    ),

    KEY idx_sales_allocation_revision_previous (
        previous_revision_id
    ),

    CONSTRAINT fk_sales_allocation_revision_farm
        FOREIGN KEY (farm_id)
        REFERENCES farms(id)
        ON DELETE CASCADE
        ON UPDATE RESTRICT,

    CONSTRAINT fk_sales_allocation_revision_user
        FOREIGN KEY (changed_by_user_id)
        REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE RESTRICT

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS sales_allocation_revision_rows (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    revision_id BIGINT UNSIGNED NOT NULL,
    cycle_id INT NOT NULL,

    allocated_amount DECIMAL(14,2) NOT NULL,
    allocation_percent DECIMAL(7,4) NOT NULL,
    allocation_basis VARCHAR(50) NOT NULL
        DEFAULT 'manual_shared_revenue',
    notes VARCHAR(255) NULL,

    PRIMARY KEY (id),

    UNIQUE KEY uniq_sales_allocation_revision_cycle (
        revision_id,
        cycle_id
    ),

    KEY idx_sales_allocation_revision_row_cycle (
        cycle_id
    ),

    CONSTRAINT fk_sales_allocation_revision_row
        FOREIGN KEY (revision_id)
        REFERENCES sales_allocation_revisions(id)
        ON DELETE CASCADE
        ON UPDATE RESTRICT

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


INSERT INTO schema_migrations (filename)
VALUES ('067_sales_revenue_allocation_provenance.sql')
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
