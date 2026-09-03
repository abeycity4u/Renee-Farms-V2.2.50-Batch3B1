-- V2.2.50 Batch 2: canonical poultry flock entry/acquisition facts.
-- No historical acquisition is inferred or backfilled from opening headcount, bird cost basis, age, lifecycle, expenses, inventory, or sales.

CREATE TABLE IF NOT EXISTS poultry_cycle_acquisitions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL,
    cycle_id INT NOT NULL,
    acquisition_type VARCHAR(40) NOT NULL,
    acquisition_date DATE NOT NULL,
    quantity INT NOT NULL,
    age_days INT NOT NULL,
    total_cost DECIMAL(14,2) NULL,
    source_name VARCHAR(190) NULL,
    reference_no VARCHAR(120) NULL,
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_poultry_acquisition_cycle_date (farm_id, cycle_id, acquisition_date, id),
    INDEX idx_poultry_acquisition_type (farm_id, acquisition_type, acquisition_date),
    CONSTRAINT fk_poultry_acquisition_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE,
    CONSTRAINT fk_poultry_acquisition_cycle FOREIGN KEY (cycle_id) REFERENCES production_cycles(id) ON DELETE RESTRICT,
    CONSTRAINT fk_poultry_acquisition_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);
