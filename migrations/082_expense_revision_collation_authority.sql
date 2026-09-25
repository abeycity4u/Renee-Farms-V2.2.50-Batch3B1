-- Renee Farms V3.0.1 — farm expense revision collation authority.
--
-- Schema-only normalization. Historical revision values are not rewritten
-- by application logic; this aligns the immutable revision ledger with
-- farm_expenses and the newer revision-ledger families.

ALTER TABLE farm_expense_revisions
    CONVERT TO CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

INSERT INTO schema_migrations (filename)
VALUES ('082_expense_revision_collation_authority.sql')
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
