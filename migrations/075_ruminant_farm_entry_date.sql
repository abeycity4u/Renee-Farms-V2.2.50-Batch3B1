-- Renee Farms V3.0.1 — Ruminant physical farm-entry provenance.
--
-- Phase 1 / additive migration.
--
-- farm_entry_date is the date the physical animal became part of the
-- farm/operation. It is independent from tag/registry creation time and
-- purchase date.
--
-- This migration deliberately leaves the column NULLABLE during the
-- compatibility deployment window.
--
-- Historical backfill priority:
-- 1. earliest explicit production-cycle membership;
-- 2. recorded purchase date;
-- 3. recorded birth date;
-- 4. existing registry created_at date.
--
-- Migration 076 enforces NOT NULL only after the compatible application
-- runtime is live and a zero-NULL preflight has passed.
--
-- No cycle membership is inferred from species or financial records.

SET @farm_entry_column_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ruminant_animals'
      AND COLUMN_NAME = 'farm_entry_date'
);

SET @farm_entry_add_sql := IF(
    @farm_entry_column_exists = 0,
    'ALTER TABLE ruminant_animals ADD COLUMN farm_entry_date DATE NULL AFTER birth_date',
    'SELECT 1'
);

PREPARE farm_entry_add_stmt
FROM @farm_entry_add_sql;

EXECUTE farm_entry_add_stmt;

DEALLOCATE PREPARE farm_entry_add_stmt;


UPDATE ruminant_animals a

LEFT JOIN (
    SELECT
        farm_id,
        animal_id,
        MIN(start_date) AS first_membership_date
    FROM ruminant_animal_cycle_memberships
    GROUP BY
        farm_id,
        animal_id
) m
    ON m.farm_id = a.farm_id
   AND m.animal_id = a.id

SET
    a.farm_entry_date = COALESCE(
        m.first_membership_date,
        a.purchase_date,
        a.birth_date,
        DATE(a.created_at)
    )

WHERE
    a.farm_entry_date IS NULL;


INSERT INTO schema_migrations (filename)
VALUES (
    '075_ruminant_farm_entry_date.sql'
)
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
