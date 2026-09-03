-- V2.2.48 Inventory default production attribution
-- General/non-feed stock needs a default production owner so purchase spending
-- can appear on the correct Layer/Broiler/Ruminant financial view without
-- changing the item's flexible Inventory Category.

ALTER TABLE stock_items
    ADD COLUMN default_production_type VARCHAR(50) NOT NULL DEFAULT 'shared' AFTER feed_category;

UPDATE stock_items
SET default_production_type = CASE
    WHEN feed_category='layer' THEN 'layer'
    WHEN feed_category='broiler' THEN 'broiler'
    ELSE 'shared'
END
WHERE default_production_type IS NULL OR default_production_type='' OR default_production_type='shared';

CREATE INDEX idx_stock_items_default_production
    ON stock_items (farm_id, farm_type, default_production_type, is_active);
