-- Renee AgriSuite V3.2.0
-- Sales-only General operating-expense category storage.
--
-- expense_category_catalog.php is the canonical category authority.
-- Do not duplicate the evolving application category catalog inside a DB ENUM.
--
-- Preserve every existing category value exactly, including any legacy/error
-- value that must later be corrected through the canonical revision workflow.

ALTER TABLE farm_expenses
    MODIFY COLUMN category
        VARCHAR(64)
        NOT NULL;

INSERT INTO schema_migrations (
    filename
) VALUES (
    '090_general_expense_category_storage.sql'
)
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
