-- Renee Farms V3.0.1 - Ruminant slaughter permission defaults.
--
-- Establish global defaults for the dedicated Ruminant Slaughter Processing
-- permission family introduced with the Ruminant slaughter expense architecture.
--
-- Tenant-specific permission rows continue to override farm_id=0 through the
-- existing hasPermission() precedence contract.
--
-- No Poultry Manager, Sales Representative, Viewer or Farm Admin rows are
-- created here:
--   * Farm Admin / Platform Owner already bypass operational permission rows.
--   * Ruminant slaughter remains a Ruminant operational responsibility.

INSERT INTO permissions (
    farm_id,
    role,
    module,
    allowed
) VALUES
    (0, 'ruminant_manager', 'ruminant_slaughter', 1),
    (0, 'ruminant_manager', 'ruminant_slaughter_batch_add', 1),
    (0, 'ruminant_manager', 'ruminant_slaughter_processing_expense_add', 1),
    (0, 'ruminant_manager', 'ruminant_slaughter_output_add', 1)
ON DUPLICATE KEY UPDATE
    allowed = VALUES(allowed);

INSERT INTO schema_migrations (
    filename
) VALUES (
    '084_ruminant_slaughter_permission_defaults.sql'
)
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
