-- Renee Farms V3.0.1 — Inventory role boundary for slaughter outputs.
--
-- Financial Type continues to describe purchase/spending classification.
-- inventory_role describes the physical purpose of the category.
--
-- Existing categories remain operational inventory by default.
-- No existing category is guessed or converted into slaughter output.

ALTER TABLE inventory_categories
    ADD COLUMN inventory_role VARCHAR(40)
        NOT NULL
        DEFAULT 'operational'
        AFTER financial_type,
    ADD INDEX idx_inventory_category_role (
        farm_id,
        inventory_role,
        farm_type
    );

INSERT INTO schema_migrations (filename)
VALUES ('070_inventory_slaughter_output_role.sql')
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
