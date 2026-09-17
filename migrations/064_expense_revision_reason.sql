ALTER TABLE farm_expense_revisions
    ADD COLUMN revision_reason VARCHAR(500) NULL
        AFTER revision_action;

INSERT INTO schema_migrations (filename)
VALUES ('064_expense_revision_reason.sql')
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
