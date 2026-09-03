-- V2.2.48 Foundation Batch 2
-- Separate farmer-defined inventory categories from system financial-spending classification.
-- Classification is snapshotted on each stock movement so later item edits do not rewrite history.

ALTER TABLE stock_items
    ADD COLUMN financial_classification VARCHAR(50) NOT NULL DEFAULT 'other_stock' AFTER feed_category;

ALTER TABLE stock_transactions
    ADD COLUMN financial_classification VARCHAR(50) NOT NULL DEFAULT 'other_stock' AFTER production_type;

UPDATE stock_items
SET financial_classification = CASE
    WHEN LOWER(COALESCE(feed_category,'')) IN ('layer','broiler','ruminant') THEN 'feed'
    ELSE 'other_stock'
END
WHERE financial_classification IS NULL
   OR financial_classification=''
   OR financial_classification='other_stock';

UPDATE stock_transactions t
INNER JOIN stock_items s ON s.id=t.stock_item_id AND s.farm_id=t.farm_id
SET t.financial_classification = COALESCE(NULLIF(s.financial_classification,''), 'other_stock')
WHERE t.financial_classification IS NULL
   OR t.financial_classification=''
   OR t.financial_classification='other_stock';

CREATE INDEX idx_stock_items_financial_classification
    ON stock_items (farm_id, financial_classification, is_active);

CREATE INDEX idx_stock_tx_financial_classification
    ON stock_transactions (farm_id, financial_classification, transaction_date);
