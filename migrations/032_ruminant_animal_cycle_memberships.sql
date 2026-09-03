-- V2.2.48 — Ruminant animal cycle membership history.
-- This is a history table rather than a single cycle_id on the animal so an
-- animal can move between production cycles without rewriting prior economics.

CREATE TABLE IF NOT EXISTS ruminant_animal_cycle_memberships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL,
    animal_id INT NOT NULL,
    cycle_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    notes VARCHAR(255) NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_ruminant_animal_cycle_start (farm_id, animal_id, cycle_id, start_date),
    KEY idx_ruminant_membership_animal_dates (farm_id, animal_id, start_date, end_date),
    KEY idx_ruminant_membership_cycle_dates (farm_id, cycle_id, start_date, end_date),
    CONSTRAINT fk_racm_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE RESTRICT,
    CONSTRAINT fk_racm_animal FOREIGN KEY (animal_id) REFERENCES ruminant_animals(id) ON DELETE CASCADE,
    CONSTRAINT fk_racm_cycle FOREIGN KEY (cycle_id) REFERENCES production_cycles(id) ON DELETE CASCADE,
    CONSTRAINT fk_racm_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
