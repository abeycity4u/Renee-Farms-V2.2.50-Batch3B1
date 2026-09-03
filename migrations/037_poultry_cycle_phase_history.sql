-- V2.2.50 Batch 1: explicit poultry biological lifecycle phase history.
-- No historical phase is inferred or backfilled. production_cycles.status remains operational workflow state.

CREATE TABLE IF NOT EXISTS production_cycle_phases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL,
    cycle_id INT NOT NULL,
    phase VARCHAR(40) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_poultry_phase_cycle_start (farm_id, cycle_id, start_date),
    INDEX idx_poultry_phase_cycle_date (farm_id, cycle_id, start_date, end_date),
    INDEX idx_poultry_phase_current (farm_id, cycle_id, end_date),
    CONSTRAINT fk_poultry_phase_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE,
    CONSTRAINT fk_poultry_phase_cycle FOREIGN KEY (cycle_id) REFERENCES production_cycles(id) ON DELETE RESTRICT,
    CONSTRAINT fk_poultry_phase_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);
