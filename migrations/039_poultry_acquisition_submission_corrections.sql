-- V2.2.50 Batch 2A: acquisition idempotency and auditable correction support.
-- Existing acquisition rows remain history. Voided rows are retained and excluded from active acquisition summaries.

ALTER TABLE poultry_cycle_acquisitions
    ADD COLUMN request_token VARCHAR(64) NULL AFTER notes;

ALTER TABLE poultry_cycle_acquisitions
    ADD COLUMN voided_at DATETIME NULL AFTER created_at;

ALTER TABLE poultry_cycle_acquisitions
    ADD COLUMN voided_by INT NULL AFTER voided_at;

ALTER TABLE poultry_cycle_acquisitions
    ADD COLUMN void_reason VARCHAR(255) NULL AFTER voided_by;

ALTER TABLE poultry_cycle_acquisitions
    ADD UNIQUE KEY uniq_poultry_acquisition_request (farm_id, request_token);

CREATE INDEX idx_poultry_acquisition_active
    ON poultry_cycle_acquisitions (farm_id, cycle_id, voided_at, acquisition_date, id);
