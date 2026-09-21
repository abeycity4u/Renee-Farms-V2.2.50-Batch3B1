-- Renee Farms V3.0.1
-- Human-facing record-reference NOT NULL hardening.
--
-- Preconditions are intentionally fail-closed.
-- This migration performs no historical business-row backfill.
--
-- Public references remain display/audit identity only.
-- PK/FK/tenant/source/provenance identity remains unchanged.

SET @rr_database = DATABASE();

SET @rr_066_present = (
    SELECT COUNT(*) = 1
    FROM schema_migrations
    WHERE filename = '066_human_facing_record_references.sql'
);

SET @rr_068_absent = (
    SELECT COUNT(*) = 0
    FROM schema_migrations
    WHERE filename = '068_human_facing_record_reference_not_null.sql'
);

SET @rr_column_contract = (
    SELECT COUNT(*) = 3
    FROM information_schema.columns
    WHERE table_schema = @rr_database
      AND table_name IN (
          'stock_transactions',
          'farm_expenses',
          'sales_records'
      )
      AND column_name = 'public_reference'
      AND is_nullable IN ('YES', 'NO')
      AND data_type = 'varchar'
      AND character_maximum_length = 32
      AND character_set_name = 'ascii'
      AND collation_name = 'ascii_bin'
);

SET @rr_stock_nullable = (
    SELECT is_nullable = 'YES'
    FROM information_schema.columns
    WHERE table_schema = @rr_database
      AND table_name = 'stock_transactions'
      AND column_name = 'public_reference'
);

SET @rr_expense_nullable = (
    SELECT is_nullable = 'YES'
    FROM information_schema.columns
    WHERE table_schema = @rr_database
      AND table_name = 'farm_expenses'
      AND column_name = 'public_reference'
);

SET @rr_sale_nullable = (
    SELECT is_nullable = 'YES'
    FROM information_schema.columns
    WHERE table_schema = @rr_database
      AND table_name = 'sales_records'
      AND column_name = 'public_reference'
);

SET @rr_unique_contract = (
    SELECT COUNT(*) = 3
    FROM information_schema.statistics
    WHERE table_schema = @rr_database
      AND non_unique = 0
      AND seq_in_index = 1
      AND column_name = 'public_reference'
      AND (
          (
              table_name = 'stock_transactions'
              AND index_name =
                  'uniq_stock_transaction_public_reference'
          )
          OR
          (
              table_name = 'farm_expenses'
              AND index_name =
                  'uniq_farm_expense_public_reference'
          )
          OR
          (
              table_name = 'sales_records'
              AND index_name =
                  'uniq_sale_public_reference'
          )
      )
);

SET @rr_stock_dirty = (
    SELECT COUNT(*)
    FROM stock_transactions
    WHERE public_reference IS NULL
       OR TRIM(public_reference) = ''
       OR public_reference NOT REGEXP
          '^RA-SM-[0-9]{8}-[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{10}$'
);

SET @rr_expense_dirty = (
    SELECT COUNT(*)
    FROM farm_expenses
    WHERE public_reference IS NULL
       OR TRIM(public_reference) = ''
       OR public_reference NOT REGEXP
          '^RA-EX-[0-9]{8}-[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{10}$'
);

SET @rr_sale_dirty = (
    SELECT COUNT(*)
    FROM sales_records
    WHERE public_reference IS NULL
       OR TRIM(public_reference) = ''
       OR public_reference NOT REGEXP
          '^RA-SA-[0-9]{8}-[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{10}$'
);

SET @rr_stock_duplicates = (
    SELECT COUNT(*)
    FROM (
        SELECT public_reference
        FROM stock_transactions
        WHERE public_reference IS NOT NULL
        GROUP BY public_reference
        HAVING COUNT(*) > 1
    ) AS rr_stock_duplicate_rows
);

SET @rr_expense_duplicates = (
    SELECT COUNT(*)
    FROM (
        SELECT public_reference
        FROM farm_expenses
        WHERE public_reference IS NOT NULL
        GROUP BY public_reference
        HAVING COUNT(*) > 1
    ) AS rr_expense_duplicate_rows
);

SET @rr_sale_duplicates = (
    SELECT COUNT(*)
    FROM (
        SELECT public_reference
        FROM sales_records
        WHERE public_reference IS NOT NULL
        GROUP BY public_reference
        HAVING COUNT(*) > 1
    ) AS rr_sale_duplicate_rows
);

SET @rr_ready = IF(
    @rr_066_present = 1
    AND @rr_068_absent = 1
    AND @rr_column_contract = 1
    AND @rr_unique_contract = 1
    AND @rr_stock_dirty = 0
    AND @rr_expense_dirty = 0
    AND @rr_sale_dirty = 0
    AND @rr_stock_duplicates = 0
    AND @rr_expense_duplicates = 0
    AND @rr_sale_duplicates = 0,
    1,
    0
);

SET @rr_sql = IF(
    @rr_ready = 1
    AND @rr_stock_nullable = 1,
    'ALTER TABLE stock_transactions
       MODIFY COLUMN public_reference VARCHAR(32)
       CHARACTER SET ascii COLLATE ascii_bin
       NOT NULL AFTER id',
    'SELECT 1'
);

PREPARE rr_statement FROM @rr_sql;
EXECUTE rr_statement;
DEALLOCATE PREPARE rr_statement;

SET @rr_sql = IF(
    @rr_ready = 1
    AND @rr_expense_nullable = 1,
    'ALTER TABLE farm_expenses
       MODIFY COLUMN public_reference VARCHAR(32)
       CHARACTER SET ascii COLLATE ascii_bin
       NOT NULL AFTER id',
    'SELECT 1'
);

PREPARE rr_statement FROM @rr_sql;
EXECUTE rr_statement;
DEALLOCATE PREPARE rr_statement;

SET @rr_sql = IF(
    @rr_ready = 1
    AND @rr_sale_nullable = 1,
    'ALTER TABLE sales_records
       MODIFY COLUMN public_reference VARCHAR(32)
       CHARACTER SET ascii COLLATE ascii_bin
       NOT NULL AFTER id',
    'SELECT 1'
);

PREPARE rr_statement FROM @rr_sql;
EXECUTE rr_statement;
DEALLOCATE PREPARE rr_statement;

SET @rr_post_contract = (
    SELECT COUNT(*) = 3
    FROM information_schema.columns
    WHERE table_schema = @rr_database
      AND table_name IN (
          'stock_transactions',
          'farm_expenses',
          'sales_records'
      )
      AND column_name = 'public_reference'
      AND is_nullable = 'NO'
      AND data_type = 'varchar'
      AND character_maximum_length = 32
      AND character_set_name = 'ascii'
      AND collation_name = 'ascii_bin'
);

SET @rr_record_sql = IF(
    @rr_ready = 1
    AND @rr_post_contract = 1,
    'INSERT INTO schema_migrations (filename)
     VALUES (''068_human_facing_record_reference_not_null.sql'')
     ON DUPLICATE KEY UPDATE filename = VALUES(filename)',
    'SELECT 1 FROM information_schema.__renee_record_reference_not_null_postcondition_failed__'
);

PREPARE rr_record_statement FROM @rr_record_sql;
EXECUTE rr_record_statement;
DEALLOCATE PREPARE rr_record_statement;
