-- Renee Farms V3.0 — farm-scoped custom livestock type foundation.
--
-- Compatibility contract:
-- - cattle/goat/sheep remain canonical built-in species keys;
-- - "other" remains the legacy compatibility bucket;
-- - a custom farm livestock type is represented only by livestock_type_id;
-- - custom links are valid only with ruminant/other records;
-- - legacy rows stay NULL until an explicit user classification;
-- - this migration performs no historical data backfill.
--
-- Tag uniqueness:
-- Existing tags are unique per built-in species. Custom "other" types need the
-- same behavior per actual custom type, so the generated scope key maps:
--   cattle -> -1, goat -> -2, sheep -> -3,
--   generic legacy other -> 0,
--   custom other -> livestock_type_id (> 0).
-- This preserves legacy uniqueness while allowing Rabbit #1 and Pig #1 to
-- coexist after both are explicitly classified.

CREATE TABLE IF NOT EXISTS livestock_types (
    id INT NOT NULL AUTO_INCREMENT,
    farm_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uniq_livestock_type_farm_name (
        farm_id,
        name
    ),

    UNIQUE KEY uniq_livestock_type_farm_identity (
        farm_id,
        id
    ),

    KEY idx_livestock_type_creator (created_by),

    CONSTRAINT fk_livestock_types_farm
        FOREIGN KEY (farm_id)
        REFERENCES farms(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_livestock_types_creator
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT chk_livestock_type_name
        CHECK (
            CHAR_LENGTH(name) = CHAR_LENGTH(TRIM(name))
            AND CHAR_LENGTH(name) > 0
            AND LOWER(name) NOT IN (
                'cattle',
                'goat',
                'sheep',
                'other',
                'shared'
            )
        ),

    CONSTRAINT chk_livestock_type_active
        CHECK (is_active IN (0, 1))
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

ALTER TABLE production_cycles
    ADD COLUMN livestock_type_id INT NULL
        AFTER production_type,

    ADD KEY idx_production_cycles_livestock_type (
        farm_id,
        livestock_type_id
    ),

    ADD CONSTRAINT fk_production_cycles_livestock_type
        FOREIGN KEY (farm_id, livestock_type_id)
        REFERENCES livestock_types(farm_id, id)
        ON DELETE RESTRICT,

    ADD CONSTRAINT chk_production_cycles_livestock_type_scope
        CHECK (
            livestock_type_id IS NULL
            OR (
                farm_type = 'ruminant'
                AND production_type = 'other'
            )
        );

ALTER TABLE ruminant_animals
    ADD COLUMN livestock_type_id INT NULL
        AFTER species,

    ADD COLUMN tag_type_scope_key INT
        AS (
            CASE
                WHEN species = 'cattle' THEN -1
                WHEN species = 'goat' THEN -2
                WHEN species = 'sheep' THEN -3
                ELSE COALESCE(livestock_type_id, 0)
            END
        ) PERSISTENT
        AFTER livestock_type_id,

    DROP INDEX uniq_ruminant_farm_species_tag,

    ADD UNIQUE KEY uniq_ruminant_farm_type_tag (
        farm_id,
        tag_type_scope_key,
        tag_no
    ),

    ADD KEY idx_ruminant_animals_livestock_type (
        farm_id,
        livestock_type_id
    ),

    ADD CONSTRAINT fk_ruminant_animals_livestock_type
        FOREIGN KEY (farm_id, livestock_type_id)
        REFERENCES livestock_types(farm_id, id)
        ON DELETE RESTRICT,

    ADD CONSTRAINT chk_ruminant_animals_livestock_type_scope
        CHECK (
            livestock_type_id IS NULL
            OR species = 'other'
        );

INSERT INTO schema_migrations (filename)
VALUES (
    '055_custom_livestock_type_foundation.sql'
)
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
