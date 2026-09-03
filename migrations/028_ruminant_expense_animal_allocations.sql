-- V2.2.48 Ruminant Animal Economics — expense-to-animal attribution.
-- farm_expenses remains the canonical financial transaction. These rows only
-- allocate that one expense across selected registered animals.

CREATE TABLE IF NOT EXISTS ruminant_expense_animal_allocations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    farm_id INT NOT NULL,
    expense_id INT NOT NULL,
    animal_id INT NOT NULL,
    allocation_method ENUM('equal','custom') NOT NULL DEFAULT 'equal',
    allocation_percent DECIMAL(7,4) NOT NULL,
    allocated_amount DECIMAL(14,2) NOT NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_ruminant_expense_animal (expense_id, animal_id),
    KEY idx_reaa_farm_animal (farm_id, animal_id),
    KEY idx_reaa_farm_expense (farm_id, expense_id),
    CONSTRAINT fk_reaa_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE RESTRICT,
    CONSTRAINT fk_reaa_expense FOREIGN KEY (expense_id) REFERENCES farm_expenses(id) ON DELETE CASCADE,
    CONSTRAINT fk_reaa_animal FOREIGN KEY (animal_id) REFERENCES ruminant_animals(id) ON DELETE CASCADE,
    CONSTRAINT fk_reaa_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (filename)
VALUES ('028_ruminant_expense_animal_allocations.sql')
ON DUPLICATE KEY UPDATE filename=VALUES(filename);
