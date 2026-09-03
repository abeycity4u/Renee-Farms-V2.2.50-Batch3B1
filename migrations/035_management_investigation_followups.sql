-- V2.2.49 Batch 4G — Management Follow-through & Investigation Outcomes.
-- Stores management review outcomes separately from operational source records.
CREATE TABLE IF NOT EXISTS management_investigation_followups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL,
    investigation_type ENUM('poultry','ruminant') NOT NULL,
    subject_id INT NOT NULL,
    issue_type VARCHAR(80) NOT NULL,
    as_of_date DATE NOT NULL,
    status ENUM('open','resolved') NOT NULL DEFAULT 'open',
    outcome VARCHAR(80) NULL,
    finding_notes TEXT NULL,
    action_taken TEXT NULL,
    recorded_by INT NULL,
    resolved_by INT NULL,
    resolved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_management_investigation (farm_id, investigation_type, subject_id, issue_type, as_of_date),
    KEY idx_management_investigation_status (farm_id, status, as_of_date),
    CONSTRAINT fk_mif_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE,
    CONSTRAINT fk_mif_recorded_by FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_mif_resolved_by FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
);
