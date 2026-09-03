-- V2.2.48 Inventory Category Financial Type
-- Inventory Category is the single source of truth for stocked-item financial grouping.
-- Existing stock transaction classifications remain historical snapshots, except legacy
-- classification names are normalized to the current controlled list.

ALTER TABLE inventory_categories
    ADD COLUMN financial_type VARCHAR(50) NOT NULL DEFAULT 'other_stock' AFTER unit;

-- Normalize Batch 2 item/transaction labels into the consolidated controlled list.
UPDATE stock_items SET financial_classification='medication_vaccine'
WHERE financial_classification IN ('medication','vaccine');
UPDATE stock_transactions SET financial_classification='medication_vaccine'
WHERE financial_classification IN ('medication','vaccine');

UPDATE stock_items SET financial_classification='equipment_tools'
WHERE financial_classification IN ('equipment');
UPDATE stock_transactions SET financial_classification='equipment_tools'
WHERE financial_classification IN ('equipment');

UPDATE stock_items SET financial_classification='spare_parts'
WHERE financial_classification IN ('repairs');
UPDATE stock_transactions SET financial_classification='spare_parts'
WHERE financial_classification IN ('repairs');

-- Fuel/Energy remains a non-stock Expense in the agreed farm workflow.
-- Any experimental Batch 2 stock classification is retained only as General/Other Stock.
UPDATE stock_items SET financial_classification='other_stock'
WHERE financial_classification='fuel';
UPDATE stock_transactions SET financial_classification='other_stock'
WHERE financial_classification='fuel';

-- Infer each existing category once from its current items. Feed has priority because
-- production feed usage is already explicit in the item model. Owners can adjust the
-- category Financial Type from Manage Categories after migration if needed.
UPDATE inventory_categories c
SET c.financial_type = CASE
    WHEN EXISTS (SELECT 1 FROM stock_items s WHERE s.farm_id=c.farm_id AND s.category_id=c.id AND (s.feed_category IN ('layer','broiler','ruminant') OR s.financial_classification='feed')) THEN 'feed'
    WHEN EXISTS (SELECT 1 FROM stock_items s WHERE s.farm_id=c.farm_id AND s.category_id=c.id AND s.financial_classification='medication_vaccine') THEN 'medication_vaccine'
    WHEN EXISTS (SELECT 1 FROM stock_items s WHERE s.farm_id=c.farm_id AND s.category_id=c.id AND s.financial_classification='supplement') THEN 'supplement'
    WHEN EXISTS (SELECT 1 FROM stock_items s WHERE s.farm_id=c.farm_id AND s.category_id=c.id AND s.financial_classification='consumables') THEN 'consumables'
    WHEN EXISTS (SELECT 1 FROM stock_items s WHERE s.farm_id=c.farm_id AND s.category_id=c.id AND s.financial_classification='equipment_tools') THEN 'equipment_tools'
    WHEN EXISTS (SELECT 1 FROM stock_items s WHERE s.farm_id=c.farm_id AND s.category_id=c.id AND s.financial_classification='spare_parts') THEN 'spare_parts'
    ELSE 'other_stock'
END;

-- Category becomes source of truth for each item's FUTURE receipts.
-- Historical stock_transactions are not rewritten to a category's inferred type.
UPDATE stock_items s
INNER JOIN inventory_categories c ON c.id=s.category_id AND c.farm_id=s.farm_id
SET s.financial_classification=c.financial_type;

CREATE INDEX idx_inventory_categories_financial_type
    ON inventory_categories (farm_id, financial_type);
