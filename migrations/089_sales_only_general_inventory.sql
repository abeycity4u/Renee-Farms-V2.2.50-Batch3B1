-- V3.2 Sales-only General Inventory foundation.
--
-- Contract:
--   * Sales-only tenants may own neutral General inventory without enabling
--     Poultry or Ruminant;
--   * existing Poultry, Ruminant and Shared inventory values remain valid;
--   * General stock movements use the existing canonical Inventory ledger;
--   * a General sale may optionally link to the exact stock item it consumes;
--   * no tenant business data is rewritten by this migration.

ALTER TABLE inventory_categories
    MODIFY COLUMN farm_type
        ENUM('poultry','ruminant','both','general')
        NOT NULL
        DEFAULT 'both';

ALTER TABLE stock_items
    MODIFY COLUMN farm_type
        ENUM('poultry','ruminant','both','general')
        NOT NULL
        DEFAULT 'both',

    ADD UNIQUE KEY uniq_stock_item_farm_identity (
        farm_id,
        id
    );

ALTER TABLE stock_transactions
    MODIFY COLUMN farm_type
        ENUM('poultry','ruminant','both','general')
        NOT NULL;

ALTER TABLE sales_records
    ADD COLUMN stock_item_id INT NULL
        AFTER cycle_id,

    ADD KEY idx_sales_stock_item (
        farm_id,
        stock_item_id
    ),

    ADD CONSTRAINT fk_sales_stock_item
        FOREIGN KEY (
            farm_id,
            stock_item_id
        )
        REFERENCES stock_items(
            farm_id,
            id
        )
        ON DELETE RESTRICT;

INSERT INTO schema_migrations (
    filename
) VALUES (
    '089_sales_only_general_inventory.sql'
)
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
