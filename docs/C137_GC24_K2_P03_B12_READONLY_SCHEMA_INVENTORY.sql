-- C137 GC24 K2-P03 B12. SOURCE-ONLY READ-ONLY INVENTORY. NOT A MIGRATION.
-- DO NOT place under migrations/ or run through scripts/run_migrations.php.
-- Run only on separately approved isolated disposable MariaDB clone with
-- a SELECT-only credential, AFTER reviewing its database identity and schema.
-- No production, staging, protected fixtures, AWS, cPanel, or B12 re-run.
-- Returns aggregate counts / metadata only; never exposes tenant row values.
-- Fail closed if any required table/column is absent; do not adjust live schema.

SELECT '01_DATABASE_IDENTITY' AS section, DATABASE() AS database_name,
       VERSION() AS database_version;

SELECT '02_REQUIRED_TABLES' AS section,
       expected.table_name,
       COALESCE(t.engine, 'MISSING') AS engine
FROM (
    SELECT 'layer_daily_records' AS table_name
    UNION ALL SELECT 'broiler_daily_records'
    UNION ALL SELECT 'ruminant_daily_records'
    UNION ALL SELECT 'production_cycles'
) AS expected
LEFT JOIN information_schema.tables t
       ON t.table_schema = DATABASE()
      AND t.table_name = expected.table_name
ORDER BY expected.table_name;

SELECT '03_IDENTITY_COLUMNS' AS section,
       table_name, column_name, column_type, is_nullable,
       column_default, extra
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name IN ('layer_daily_records',
                     'broiler_daily_records',
                     'ruminant_daily_records')
  AND column_name IN ('id', 'farm_id', 'cycle_id',
                      'record_date', 'animal_type', 'record_revision')
ORDER BY table_name, ordinal_position;

SELECT '04_UNIQUE_AND_RELEVANT_INDEXES' AS section,
       table_name, index_name, non_unique,
       GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ',') AS index_columns
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name IN ('layer_daily_records',
                     'broiler_daily_records',
                     'ruminant_daily_records')
GROUP BY table_name, index_name, non_unique
ORDER BY table_name, index_name;

SELECT '05_FOREIGN_KEY_DELETE_RULES' AS section,
       table_name, constraint_name, referenced_table_name,
       update_rule, delete_rule
FROM information_schema.referential_constraints
WHERE constraint_schema = DATABASE()
  AND table_name IN ('layer_daily_records',
                     'broiler_daily_records',
                     'ruminant_daily_records')
ORDER BY table_name, constraint_name;

SELECT '06_ROW_AND_CYCLE_COUNTS' AS section,
       'layer' AS record_type,
       COUNT(*) AS total_rows,
       COALESCE(SUM(cycle_id IS NULL), 0) AS null_cycle_rows,
       COALESCE(SUM(cycle_id IS NOT NULL), 0) AS linked_cycle_rows
FROM layer_daily_records
UNION ALL
SELECT '06_ROW_AND_CYCLE_COUNTS', 'broiler',
       COUNT(*),
       COALESCE(SUM(cycle_id IS NULL), 0),
       COALESCE(SUM(cycle_id IS NOT NULL), 0)
FROM broiler_daily_records
UNION ALL
SELECT '06_ROW_AND_CYCLE_COUNTS', 'ruminant',
       COUNT(*),
       COALESCE(SUM(cycle_id IS NULL), 0),
       COALESCE(SUM(cycle_id IS NOT NULL), 0)
FROM ruminant_daily_records;

-- Duplicate logical legacy keys. Zero required before any new constraint;
-- do not auto-merge rows even if this returns positive counts.
SELECT '07_NULL_CYCLE_DUPLICATES' AS section,
       'layer' AS record_type,
       COUNT(*) AS duplicate_groups,
       COALESCE(SUM(rows_in_group - 1), 0) AS excess_rows
FROM (
    SELECT farm_id, record_date, COUNT(*) AS rows_in_group
    FROM layer_daily_records
    WHERE cycle_id IS NULL
    GROUP BY farm_id, record_date
    HAVING COUNT(*) > 1
) AS legacy_layer
UNION ALL
SELECT '07_NULL_CYCLE_DUPLICATES', 'broiler',
       COUNT(*), COALESCE(SUM(rows_in_group - 1), 0)
FROM (
    SELECT farm_id, record_date, COUNT(*) AS rows_in_group
    FROM broiler_daily_records
    WHERE cycle_id IS NULL
    GROUP BY farm_id, record_date
    HAVING COUNT(*) > 1
) AS legacy_broiler
UNION ALL
SELECT '07_NULL_CYCLE_DUPLICATES', 'ruminant',
       COUNT(*), COALESCE(SUM(rows_in_group - 1), 0)
FROM (
    SELECT farm_id, record_date, LOWER(TRIM(animal_type)) AS species,
           COUNT(*) AS rows_in_group
    FROM ruminant_daily_records
    WHERE cycle_id IS NULL
    GROUP BY farm_id, record_date, LOWER(TRIM(animal_type))
    HAVING COUNT(*) > 1
) AS legacy_ruminant;

-- All-cycle date collisions are NOT inherently duplicate business records.
-- They ARE risky for existing UI routes that UPDATE by farm/date without id.
SELECT '08_UNSCOPED_UPDATE_AMBIGUITY' AS section,
       'layer' AS record_type,
       COUNT(*) AS ambiguous_groups,
       COALESCE(SUM(rows_in_group - 1), 0) AS additional_matched_rows
FROM (
    SELECT farm_id, record_date, COUNT(*) AS rows_in_group
    FROM layer_daily_records
    GROUP BY farm_id, record_date
    HAVING COUNT(*) > 1
) AS ambiguous_layer
UNION ALL
SELECT '08_UNSCOPED_UPDATE_AMBIGUITY', 'broiler',
       COUNT(*), COALESCE(SUM(rows_in_group - 1), 0)
FROM (
    SELECT farm_id, record_date, COUNT(*) AS rows_in_group
    FROM broiler_daily_records
    GROUP BY farm_id, record_date
    HAVING COUNT(*) > 1
) AS ambiguous_broiler
UNION ALL
SELECT '08_UNSCOPED_UPDATE_AMBIGUITY', 'ruminant',
       COUNT(*), COALESCE(SUM(rows_in_group - 1), 0)
FROM (
    SELECT farm_id, record_date, LOWER(TRIM(animal_type)) AS species,
           COUNT(*) AS rows_in_group
    FROM ruminant_daily_records
    GROUP BY farm_id, record_date, LOWER(TRIM(animal_type))
    HAVING COUNT(*) > 1
) AS ambiguous_ruminant;

-- Cycle foreign-key identity must not cross tenants.
SELECT '09_CYCLE_TENANT_INTEGRITY' AS section,
       'layer' AS record_type,
       COALESCE(SUM(d.cycle_id IS NOT NULL AND c.id IS NULL), 0) AS orphan_cycle_links,
       COALESCE(SUM(d.cycle_id IS NOT NULL AND c.id IS NOT NULL
                    AND d.farm_id <> c.farm_id), 0) AS cross_tenant_cycle_links
FROM layer_daily_records d
LEFT JOIN production_cycles c ON c.id = d.cycle_id
UNION ALL
SELECT '09_CYCLE_TENANT_INTEGRITY', 'broiler',
       COALESCE(SUM(d.cycle_id IS NOT NULL AND c.id IS NULL), 0),
       COALESCE(SUM(d.cycle_id IS NOT NULL AND c.id IS NOT NULL
                    AND d.farm_id <> c.farm_id), 0)
FROM broiler_daily_records d
LEFT JOIN production_cycles c ON c.id = d.cycle_id
UNION ALL
SELECT '09_CYCLE_TENANT_INTEGRITY', 'ruminant',
       COALESCE(SUM(d.cycle_id IS NOT NULL AND c.id IS NULL), 0),
       COALESCE(SUM(d.cycle_id IS NOT NULL AND c.id IS NOT NULL
                    AND d.farm_id <> c.farm_id), 0)
FROM ruminant_daily_records d
LEFT JOIN production_cycles c ON c.id = d.cycle_id;

-- Triggers are only counted here; trigger bodies require a separate review.
SELECT '10_TRIGGER_COUNTS' AS section,
       COALESCE(SUM(event_object_table = 'layer_daily_records'), 0) AS layer_triggers,
       COALESCE(SUM(event_object_table = 'broiler_daily_records'), 0) AS broiler_triggers,
       COALESCE(SUM(event_object_table = 'ruminant_daily_records'), 0) AS ruminant_triggers,
       COALESCE(SUM(event_object_table = 'production_cycles'), 0) AS cycle_triggers
FROM information_schema.triggers
WHERE trigger_schema = DATABASE()
  AND event_object_table IN ('layer_daily_records', 'broiler_daily_records',
                             'ruminant_daily_records', 'production_cycles');

SELECT '11_RUMINANT_SPECIES_DATA_SHAPE' AS section,
       COUNT(*) AS ruminant_rows,
       COALESCE(SUM(TRIM(animal_type) = ''), 0) AS blank_species_rows,
       COUNT(DISTINCT LOWER(TRIM(animal_type))) AS normalized_species_count
FROM ruminant_daily_records;
