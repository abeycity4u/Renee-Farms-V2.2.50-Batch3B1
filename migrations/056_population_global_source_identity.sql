-- Renee Farms V3.0 — global durable population source identity.
--
-- A durable source row is one logical fact within a farm even when a correction
-- moves that fact from one production cycle to another. Source version identity
-- must therefore be farm/source scoped rather than farm/cycle/source scoped.
--
-- NULL source_id remains reserved for request-token-owned adjustments; MariaDB
-- permits repeated NULL values in this unique key, while request_token continues
-- to enforce idempotency for those manual movements.

ALTER TABLE production_population_movements
    DROP INDEX uniq_population_movement_source,
    ADD UNIQUE KEY uniq_population_movement_source (
        farm_id,
        source_type,
        source_id,
        source_version
    );

INSERT INTO schema_migrations (filename)
VALUES (
    '056_population_global_source_identity.sql'
)
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
