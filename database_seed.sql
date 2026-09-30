-- Renee AgriSuite fresh-install system seed
--
-- System-reference data only.
-- No production farms, users, tenant assignments,
-- stock balances, billing state, credentials or
-- other production business records are included.
--
-- This seed is paired with database_schema.sql.

START TRANSACTION;

-- Final system role authority.
INSERT INTO `roles`
    (`code`, `name`, `is_platform_role`)
VALUES
    ('farm_admin', 'Admin / Farm Owner', 0),
    ('platform_owner', 'Owner / Developer', 1),
    ('poultry_manager', 'Poultry Manager', 0),
    ('ruminant_manager', 'Ruminant Manager', 0),
    ('sales_rep', 'Sales Representative', 0),
    ('viewer', 'Viewer', 0)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `is_platform_role` = VALUES(`is_platform_role`);

-- Final global default permission authority.
-- farm_id=0 represents global role defaults.
INSERT INTO `permissions`
    (`farm_id`, `role`, `module`, `allowed`)
VALUES
    (0, 'poultry_manager', 'expenses', 1),
    (0, 'poultry_manager', 'inventory', 1),
    (0, 'poultry_manager', 'inventory_add_new_item', 1),
    (0, 'poultry_manager', 'management', 1),
    (0, 'poultry_manager', 'permissions', 1),
    (0, 'poultry_manager', 'poultry_daily_broiler', 1),
    (0, 'poultry_manager', 'poultry_daily_layer', 1),
    (0, 'poultry_manager', 'poultry_expenses', 1),
    (0, 'poultry_manager', 'poultry_feeds', 1),
    (0, 'poultry_manager', 'poultry_health', 1),
    (0, 'poultry_manager', 'poultry_overview', 1),
    (0, 'poultry_manager', 'poultry_slaughter', 1),
    (0, 'poultry_manager', 'poultry_slaughter_batch_add', 1),
    (0, 'poultry_manager', 'poultry_slaughter_finalize', 1),
    (0, 'poultry_manager', 'poultry_slaughter_output_add', 1),
    (0, 'poultry_manager', 'poultry_slaughter_processing_expense_add', 1),
    (0, 'poultry_manager', 'production_cycles', 1),
    (0, 'poultry_manager', 'reports', 1),
    (0, 'poultry_manager', 'ruminant_daily', 0),
    (0, 'poultry_manager', 'ruminant_expenses', 0),
    (0, 'poultry_manager', 'ruminant_feeds', 0),
    (0, 'poultry_manager', 'ruminant_overview', 0),
    (0, 'poultry_manager', 'sales', 1),
    (0, 'poultry_manager', 'settings', 1),
    (0, 'poultry_manager', 'update_stock', 1),
    (0, 'poultry_manager', 'users', 1),
    (0, 'ruminant_manager', 'expenses', 1),
    (0, 'ruminant_manager', 'inventory', 1),
    (0, 'ruminant_manager', 'inventory_add_new_item', 1),
    (0, 'ruminant_manager', 'management', 1),
    (0, 'ruminant_manager', 'permissions', 1),
    (0, 'ruminant_manager', 'poultry_daily_broiler', 0),
    (0, 'ruminant_manager', 'poultry_daily_layer', 0),
    (0, 'ruminant_manager', 'poultry_expenses', 0),
    (0, 'ruminant_manager', 'poultry_feeds', 0),
    (0, 'ruminant_manager', 'poultry_health', 0),
    (0, 'ruminant_manager', 'poultry_overview', 0),
    (0, 'ruminant_manager', 'production_cycles', 1),
    (0, 'ruminant_manager', 'reports', 1),
    (0, 'ruminant_manager', 'ruminant_daily', 1),
    (0, 'ruminant_manager', 'ruminant_expenses', 1),
    (0, 'ruminant_manager', 'ruminant_feeds', 1),
    (0, 'ruminant_manager', 'ruminant_overview', 1),
    (0, 'ruminant_manager', 'ruminant_slaughter', 1),
    (0, 'ruminant_manager', 'ruminant_slaughter_batch_add', 1),
    (0, 'ruminant_manager', 'ruminant_slaughter_output_add', 1),
    (0, 'ruminant_manager', 'ruminant_slaughter_processing_expense_add', 1),
    (0, 'ruminant_manager', 'sales', 1),
    (0, 'ruminant_manager', 'settings', 1),
    (0, 'ruminant_manager', 'update_stock', 1),
    (0, 'ruminant_manager', 'users', 1),
    (0, 'sales_rep', 'expenses', 1),
    (0, 'sales_rep', 'inventory', 1),
    (0, 'sales_rep', 'inventory_add_new_item', 1),
    (0, 'sales_rep', 'management', 1),
    (0, 'sales_rep', 'permissions', 1),
    (0, 'sales_rep', 'poultry_daily_broiler', 0),
    (0, 'sales_rep', 'poultry_daily_layer', 0),
    (0, 'sales_rep', 'poultry_expenses', 1),
    (0, 'sales_rep', 'poultry_feeds', 0),
    (0, 'sales_rep', 'poultry_health', 0),
    (0, 'sales_rep', 'poultry_overview', 1),
    (0, 'sales_rep', 'reports', 1),
    (0, 'sales_rep', 'ruminant_daily', 0),
    (0, 'sales_rep', 'ruminant_expenses', 1),
    (0, 'sales_rep', 'ruminant_feeds', 0),
    (0, 'sales_rep', 'ruminant_overview', 1),
    (0, 'sales_rep', 'sales', 1),
    (0, 'sales_rep', 'settings', 1),
    (0, 'sales_rep', 'update_stock', 1),
    (0, 'sales_rep', 'users', 1)
ON DUPLICATE KEY UPDATE
    `allowed` = VALUES(`allowed`);

COMMIT;
