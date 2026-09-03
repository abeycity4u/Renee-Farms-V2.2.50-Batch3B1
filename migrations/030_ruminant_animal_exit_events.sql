-- V2.2.48 — explicit ruminant animal exit events linked to sales.
-- Revenue allocation remains financial attribution; an animal exits only when
-- the farmer explicitly chooses a lifecycle outcome for that animal.

CREATE TABLE IF NOT EXISTS ruminant_animal_exit_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    farm_id INT NOT NULL,
    animal_id INT NOT NULL,
    sale_id INT NULL,
    exit_date DATE NOT NULL,
    exit_outcome VARCHAR(40) NOT NULL,
    previous_status VARCHAR(30) NOT NULL,
    resulting_status VARCHAR(30) NOT NULL,
    notes VARCHAR(255) NULL,
    recorded_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_ruminant_exit_sale_animal (farm_id, sale_id, animal_id),
    KEY idx_ruminant_exit_animal_date (farm_id, animal_id, exit_date),
    KEY idx_ruminant_exit_sale (farm_id, sale_id),
    CONSTRAINT fk_rae_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE RESTRICT,
    CONSTRAINT fk_rae_animal FOREIGN KEY (animal_id) REFERENCES ruminant_animals(id) ON DELETE CASCADE,
    CONSTRAINT fk_rae_sale FOREIGN KEY (sale_id) REFERENCES sales_records(id) ON DELETE CASCADE,
    CONSTRAINT fk_rae_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (filename)
VALUES ('030_ruminant_animal_exit_events.sql')
ON DUPLICATE KEY UPDATE filename=VALUES(filename);
