-- Renee Farms V3.0.1 — immutable farm-expense revision provenance foundation.
--
-- farm_expenses remains the current/live projection.
--
-- Historical expense revisions are preserved independently in
-- farm_expense_revisions. The revision table intentionally has no foreign key
-- back to farm_expenses: deleting a current projection must never erase its
-- historical revision evidence.
--
-- Existing farm_expenses rows are intentionally NOT backfilled. Their complete
-- historical edit provenance was not recorded before this cutover, so creating
-- synthetic revision history would misrepresent legacy records.
--
-- Future application cutover will:
--   * create revision 1 for new expenses;
--   * lazily establish an explicitly labelled legacy baseline when an
--     existing pre-cutover expense is first mutated;
--   * append immutable revisions for corrections and deletion;
--   * keep causal/economic fingerprints separate from full audit-state hashes.
--
-- No current expense values are rewritten by this migration.

CREATE TABLE IF NOT EXISTS farm_expense_revisions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    farm_id INT NOT NULL,
    expense_id INT NOT NULL,
    revision_no INT UNSIGNED NOT NULL,
    revision_action VARCHAR(32) NOT NULL,
    previous_revision_id BIGINT UNSIGNED NULL,

    causal_fingerprint CHAR(64) NOT NULL,
    causal_manifest_json LONGTEXT NOT NULL,

    state_fingerprint CHAR(64) NOT NULL,
    state_manifest_json LONGTEXT NOT NULL,

    changed_by_user_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uniq_farm_expense_revision (
        farm_id,
        expense_id,
        revision_no
    ),

    KEY idx_farm_expense_revision_lookup (
        farm_id,
        expense_id,
        id
    ),

    KEY idx_farm_expense_revision_previous (
        previous_revision_id
    ),

    CONSTRAINT fk_farm_expense_revisions_farm
        FOREIGN KEY (farm_id)
        REFERENCES farms(id)
        ON DELETE CASCADE
        ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE farm_expenses
    ADD COLUMN expense_revision_no INT UNSIGNED NULL
        AFTER created_at,
    ADD COLUMN expense_causal_fingerprint CHAR(64) NULL
        AFTER expense_revision_no;

INSERT INTO schema_migrations (filename)
VALUES ('063_expense_revision_provenance.sql')
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
