-- Renee Farms V3.0.1
-- Human-facing immutable record-reference schema foundation.
--
-- Public references are display/audit identity only.
-- They do not replace database primary keys, foreign keys,
-- tenant ownership, authorization, source identity, reversal
-- identity, economic identity, or provenance identity.
--
-- Initial entities:
--   stock_transactions -> RA-SM-...
--   farm_expenses      -> RA-EX-...
--   sales_records      -> RA-SA-...
--
-- Columns remain nullable during staged cutover so schema deployment
-- never fabricates identity or blocks existing writers.
--
-- A later controlled application/backfill step will populate them
-- through the canonical record_reference service.
--
-- This migration performs no historical UPDATE/backfill.

ALTER TABLE stock_transactions
    ADD COLUMN public_reference VARCHAR(32)
        CHARACTER SET ascii
        COLLATE ascii_bin
        NULL
        AFTER id,
    ADD UNIQUE KEY uniq_stock_transaction_public_reference (
        public_reference
    );

ALTER TABLE farm_expenses
    ADD COLUMN public_reference VARCHAR(32)
        CHARACTER SET ascii
        COLLATE ascii_bin
        NULL
        AFTER id,
    ADD UNIQUE KEY uniq_farm_expense_public_reference (
        public_reference
    );

ALTER TABLE sales_records
    ADD COLUMN public_reference VARCHAR(32)
        CHARACTER SET ascii
        COLLATE ascii_bin
        NULL
        AFTER id,
    ADD UNIQUE KEY uniq_sale_public_reference (
        public_reference
    );

INSERT INTO schema_migrations (filename)
VALUES ('066_human_facing_record_references.sql')
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
