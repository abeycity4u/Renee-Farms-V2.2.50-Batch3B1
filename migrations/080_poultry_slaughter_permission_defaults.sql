-- V3.0.1 Poultry Slaughter Processing permission defaults.
--
-- Global defaults only.
-- Tenant-specific permission rows continue to override farm_id=0 through the
-- existing hasPermission() precedence contract.
--
-- No Sales Representative, Ruminant Manager, Viewer or Farm Admin rows are
-- created here:
--   * Farm Admin / Platform Owner already bypass operational permission rows.
--   * Poultry slaughter remains a Poultry operational responsibility.

INSERT INTO permissions (
    farm_id,
    role,
    module,
    allowed
) VALUES
    (0, 'poultry_manager', 'poultry_slaughter', 1),
    (0, 'poultry_manager', 'poultry_slaughter_batch_add', 1),
    (0, 'poultry_manager', 'poultry_slaughter_processing_expense_add', 1),
    (0, 'poultry_manager', 'poultry_slaughter_finalize', 1),
    (0, 'poultry_manager', 'poultry_slaughter_output_add', 1)
ON DUPLICATE KEY UPDATE
    allowed = VALUES(allowed);

INSERT INTO schema_migrations (
    filename
) VALUES (
    '080_poultry_slaughter_permission_defaults.sql'
)
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
