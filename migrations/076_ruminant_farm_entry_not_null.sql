-- Renee Farms V3.0.1 — enforce mandatory Ruminant farm entry.
--
-- Phase 2 / enforcement migration.
--
-- Apply ONLY after:
-- - migration 075 has completed;
-- - compatible application runtime is live;
-- - every ruminant_animals row has farm_entry_date populated.
--
-- ALTER TABLE intentionally fails closed if any NULL remains.

ALTER TABLE ruminant_animals
    MODIFY farm_entry_date DATE NOT NULL;


INSERT INTO schema_migrations (filename)
VALUES (
    '076_ruminant_farm_entry_not_null.sql'
)
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
