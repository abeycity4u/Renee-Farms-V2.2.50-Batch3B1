-- Renee Farms V3.0.1 — canonical farm-expense category authority.
--
-- Preserve all historical farm_expenses.category values while adding the
-- non-stock categories required by normal Expenses and Slaughter Processing.
--
-- Feed and medication remain valid historical values so existing records stay
-- readable and correctable. New stock purchases continue to originate through
-- canonical Inventory.
--
-- No existing farm_expenses row is rewritten by this migration.

ALTER TABLE farm_expenses
    MODIFY COLUMN category
        ENUM(
            'feeds',
            'medication',
            'salary',
            'labour',
            'logistic',
            'fuel',
            'processing_materials',
            'misc'
        )
        NOT NULL;

INSERT INTO schema_migrations (
    filename
) VALUES (
    '081_expense_category_authority.sql'
)
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
