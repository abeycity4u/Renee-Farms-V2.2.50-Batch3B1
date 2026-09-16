-- Renee Farms V3.0.1 — Production-Entry true provenance persistence.
--
-- Additive cutover only.
--
-- Historical approved snapshots remain immutable. Existing rows intentionally
-- remain NULL for the new provenance fields because their causal source
-- manifests were not recorded when those approvals were created.
--
-- source_fingerprint is retained unchanged as the legacy aggregate-value
-- fingerprint for compatibility with pre-cutover approvals.
--
-- Future approved versions additionally persist:
--   provenance_fingerprint   canonical causal-source manifest SHA-256
--   provenance_manifest_json exact canonical manifest used for that digest
--   provenance_source_count normalized source count
--
-- No historical row is backfilled or reinterpreted by this migration.

ALTER TABLE poultry_production_entry_snapshots
    ADD COLUMN provenance_fingerprint CHAR(64) NULL
        AFTER source_fingerprint,
    ADD COLUMN provenance_manifest_json LONGTEXT NULL
        AFTER provenance_fingerprint,
    ADD COLUMN provenance_source_count INT UNSIGNED NULL
        AFTER provenance_manifest_json;

INSERT INTO schema_migrations (filename)
VALUES ('062_poultry_production_entry_true_provenance.sql')
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
