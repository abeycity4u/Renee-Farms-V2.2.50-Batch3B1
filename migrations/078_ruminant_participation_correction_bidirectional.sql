-- Renee Farms V3.0.1 — bidirectional audited Ruminant participation correction.
--
-- Migration 077 introduced the immutable correction ledger with an
-- intentionally earlier-only contract.
--
-- Confirmed historical provenance can also require a LATER correction, for
-- example when a later-created identity was mistakenly backdated into an
-- earlier physical cohort.
--
-- The same immutable ledger remains authoritative. No historical correction
-- row is rewritten and no parallel correction table is introduced.
--
-- Application policy must still:
--   * protect transfer-owned membership starts;
--   * require physical population evidence;
--   * reject mixed-direction corrections;
--   * fail closed when a later contraction would cross a durable animal fact;
--   * preserve purchase/tag/registry facts;
--   * preserve frozen slaughter snapshots.

ALTER TABLE ruminant_participation_corrections
    DROP CONSTRAINT chk_rpc_farm_entry_extension,
    DROP CONSTRAINT chk_rpc_membership_extension,

    ADD CONSTRAINT chk_rpc_direction_coherent
        CHECK (
            (
                new_farm_entry_date
                <=
                old_farm_entry_date
                AND
                new_membership_start_date
                <=
                old_membership_start_date
            )
            OR
            (
                new_farm_entry_date
                >=
                old_farm_entry_date
                AND
                new_membership_start_date
                >=
                old_membership_start_date
            )
        ),

    ADD CONSTRAINT chk_rpc_has_change
        CHECK (
            new_farm_entry_date
            <>
            old_farm_entry_date
            OR
            new_membership_start_date
            <>
            old_membership_start_date
        );


INSERT INTO schema_migrations (filename)
VALUES (
    '078_ruminant_participation_correction_bidirectional.sql'
)
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
