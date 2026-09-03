-- V2.2.48 — Sales Unit of Measure
-- Historical rows remain NULL because their quantity meaning cannot be safely inferred.
ALTER TABLE sales_records
    ADD COLUMN unit_of_measure VARCHAR(30) NULL AFTER quantity;

CREATE INDEX idx_sales_uom ON sales_records (farm_id, unit_of_measure);
