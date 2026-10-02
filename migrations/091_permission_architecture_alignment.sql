-- V3.2.0 Permission Architecture Alignment
--
-- Consolidate the former Layer/Broiler Poultry expense permission families
-- into the canonical Poultry Expenses View/Add/Edit/Delete family.
--
-- Security-preserving migration rule:
-- a legacy tenant permission becomes a broad consolidated permission only
-- when BOTH former Layer and Broiler permissions for that action were granted.
-- Existing canonical rows are normalized from the same legacy authority where
-- legacy rows exist. Historical legacy permission rows are retained for audit
-- and rollback compatibility but are no longer exposed by the catalog.

INSERT INTO permissions (
    farm_id,
    role,
    module,
    allowed
)
SELECT
    farm_id,
    role,
    'poultry_expenses',
    CASE
        WHEN COUNT(DISTINCT module) = 2
         AND MIN(allowed) = 1
        THEN 1
        ELSE 0
    END
FROM permissions
WHERE module IN (
    'poultry_layer_expenses',
    'poultry_broiler_expenses'
)
GROUP BY farm_id, role
ON DUPLICATE KEY UPDATE
    allowed = VALUES(allowed);

INSERT INTO permissions (
    farm_id,
    role,
    module,
    allowed
)
SELECT
    farm_id,
    role,
    'poultry_expenses_add',
    CASE
        WHEN COUNT(DISTINCT module) = 2
         AND MIN(allowed) = 1
        THEN 1
        ELSE 0
    END
FROM permissions
WHERE module IN (
    'poultry_layer_expenses_add',
    'poultry_broiler_expenses_add'
)
GROUP BY farm_id, role
ON DUPLICATE KEY UPDATE
    allowed = VALUES(allowed);

INSERT INTO permissions (
    farm_id,
    role,
    module,
    allowed
)
SELECT
    farm_id,
    role,
    'poultry_expenses_edit',
    CASE
        WHEN COUNT(DISTINCT module) = 2
         AND MIN(allowed) = 1
        THEN 1
        ELSE 0
    END
FROM permissions
WHERE module IN (
    'poultry_layer_expenses_edit',
    'poultry_broiler_expenses_edit'
)
GROUP BY farm_id, role
ON DUPLICATE KEY UPDATE
    allowed = VALUES(allowed);

INSERT INTO permissions (
    farm_id,
    role,
    module,
    allowed
)
SELECT
    farm_id,
    role,
    'poultry_expenses_delete',
    CASE
        WHEN COUNT(DISTINCT module) = 2
         AND MIN(allowed) = 1
        THEN 1
        ELSE 0
    END
FROM permissions
WHERE module IN (
    'poultry_layer_expenses_delete',
    'poultry_broiler_expenses_delete'
)
GROUP BY farm_id, role
ON DUPLICATE KEY UPDATE
    allowed = VALUES(allowed);

-- Preserve the established global View defaults where no legacy granular
-- pair exists. Migration 021/022 already established poultry_expenses.
INSERT INTO permissions (
    farm_id,
    role,
    module,
    allowed
) VALUES
    (0, 'poultry_manager', 'poultry_expenses', 1),
    (0, 'sales_rep', 'poultry_expenses', 1)
ON DUPLICATE KEY UPDATE
    allowed = allowed;

INSERT INTO schema_migrations (
    filename
) VALUES (
    '091_permission_architecture_alignment.sql'
)
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
