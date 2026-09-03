-- Renee Farms V2.2.47 — Poultry Unit Economics
-- Adds an explicit per-bird cost basis to poultry production cycles.
-- NULL means the farm has not supplied a defensible bird cost yet.

ALTER TABLE production_cycles
    ADD COLUMN bird_unit_cost DECIMAL(14,2) NULL AFTER opening_headcount;
