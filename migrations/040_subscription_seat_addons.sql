-- V2.3 durable commercial extra-seat storage.
--
-- farm_role_limits remains the effective runtime enforcement limit.
-- This table stores purchased extra seats separately so plan changes do not
-- reconstruct commercial state from totals forever.

CREATE TABLE IF NOT EXISTS farm_subscription_seat_addons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL,
    role_code VARCHAR(50) NOT NULL,
    extra_seats INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_farm_subscription_seat_role (farm_id, role_code),
    INDEX idx_farm_subscription_seat_addons_farm (farm_id),
    CONSTRAINT fk_farm_subscription_seat_addons_farm
        FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE
);

-- Preserve any pre-existing implied extras from the old effective-limit model.
-- Disabled specialist roles normally have max_users=0, so they safely backfill 0.
INSERT INTO farm_subscription_seat_addons (farm_id, role_code, extra_seats)
SELECT
    frl.farm_id,
    frl.role_code,
    GREATEST(
        frl.max_users -
        CASE LOWER(COALESCE(f.subscription_plan, 'starter'))
            WHEN 'growth' THEN
                CASE frl.role_code
                    WHEN 'poultry_manager' THEN 3
                    WHEN 'ruminant_manager' THEN 3
                    WHEN 'sales_rep' THEN 2
                    WHEN 'viewer' THEN 2
                    ELSE 0
                END
            WHEN 'pro' THEN
                CASE frl.role_code
                    WHEN 'poultry_manager' THEN 5
                    WHEN 'ruminant_manager' THEN 5
                    WHEN 'sales_rep' THEN 3
                    WHEN 'viewer' THEN 3
                    ELSE 0
                END
            ELSE
                CASE frl.role_code
                    WHEN 'poultry_manager' THEN 1
                    WHEN 'ruminant_manager' THEN 1
                    WHEN 'sales_rep' THEN 1
                    WHEN 'viewer' THEN 1
                    ELSE 0
                END
        END,
        0
    ) AS extra_seats
FROM farm_role_limits frl
INNER JOIN farms f ON f.id = frl.farm_id
WHERE frl.role_code IN ('poultry_manager', 'ruminant_manager', 'sales_rep', 'viewer')
ON DUPLICATE KEY UPDATE extra_seats = VALUES(extra_seats);

-- This migration may be applied directly during the pre-billing closure so the
-- full historical migration runner never needs to be invoked just to install it.
INSERT INTO schema_migrations (filename)
VALUES ('040_subscription_seat_addons.sql')
ON DUPLICATE KEY UPDATE filename = VALUES(filename);
