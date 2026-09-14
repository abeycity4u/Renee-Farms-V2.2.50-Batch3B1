-- Renee Farms V3.0 — durable Sales population-effect lifecycle.
--
-- sale_population_effects rows are durable population source identities.
-- Removing a physical effect must reverse its canonical population projection
-- without deleting the source row referenced by immutable ledger history.
--
-- Existing rows were created under Migration 057 and are therefore active.
--
-- The Migration 057 sale foreign key remains ON DELETE RESTRICT intentionally.
-- A sale that has ever owned population history must remain auditable and must
-- not be hard-deleted by removing its durable population source row.

ALTER TABLE sale_population_effects
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1
        AFTER population_quantity,

    ADD KEY idx_sale_population_effect_active (
        farm_id,
        sale_id,
        is_active
    ),

    ADD CONSTRAINT chk_sale_population_effect_active
        CHECK (is_active IN (0, 1));


INSERT INTO schema_migrations (filename)
VALUES (
    '058_sale_population_effect_lifecycle.sql'
)
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
