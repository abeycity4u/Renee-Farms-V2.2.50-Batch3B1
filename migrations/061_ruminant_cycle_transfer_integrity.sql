-- Renee Farms V3.0 — tagged ruminant cycle-transfer integrity.
--
-- A tagged animal remains Active while moving between two production cycles.
-- Physical population is owned by the canonical paired transfer service:
--   source cycle      -> transfer_out 1
--   destination cycle -> transfer_in  1
--
-- Membership provenance records which transfer opened/closed each membership.
-- Existing history is not backfilled or reinterpreted.
--
-- from_membership_id / to_membership_id in the audit table deliberately remain
-- historical numeric references rather than foreign keys. A successful
-- reversal may remove the destination membership created by the transfer while
-- the immutable transfer audit must still retain the original membership ID.

ALTER TABLE ruminant_animal_cycle_memberships
    ADD COLUMN opened_by_transfer_id BIGINT UNSIGNED NULL
        AFTER pre_exit_end_date,
    ADD COLUMN closed_by_transfer_id BIGINT UNSIGNED NULL
        AFTER opened_by_transfer_id,
    ADD COLUMN pre_transfer_end_date DATE NULL
        AFTER closed_by_transfer_id;

ALTER TABLE ruminant_animal_cycle_memberships
    ADD KEY idx_racm_opened_transfer
        (farm_id, opened_by_transfer_id, animal_id),
    ADD KEY idx_racm_closed_transfer
        (farm_id, closed_by_transfer_id, animal_id),
    ADD CONSTRAINT fk_racm_opened_transfer
        FOREIGN KEY (farm_id, opened_by_transfer_id)
        REFERENCES production_population_transfers(farm_id, id)
        ON DELETE RESTRICT,
    ADD CONSTRAINT fk_racm_closed_transfer
        FOREIGN KEY (farm_id, closed_by_transfer_id)
        REFERENCES production_population_transfers(farm_id, id)
        ON DELETE RESTRICT;

CREATE TABLE IF NOT EXISTS ruminant_animal_cycle_transfers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    farm_id INT NOT NULL,
    animal_id INT NOT NULL,

    population_transfer_id BIGINT UNSIGNED NOT NULL,

    from_membership_id INT NOT NULL,
    to_membership_id INT NOT NULL,

    source_previous_end_date DATE NULL,

    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    reversed_at DATETIME NULL,
    reversed_by INT NULL,
    reversal_reason VARCHAR(255) NULL,

    PRIMARY KEY (id),

    UNIQUE KEY uniq_ruminant_animal_transfer_parent (
        farm_id,
        population_transfer_id
    ),

    KEY idx_ruminant_animal_transfer_animal (
        farm_id,
        animal_id,
        created_at
    ),

    KEY idx_ruminant_animal_transfer_from_membership (
        farm_id,
        from_membership_id
    ),

    KEY idx_ruminant_animal_transfer_to_membership (
        farm_id,
        to_membership_id
    ),

    KEY idx_ruminant_animal_transfer_created_by (
        created_by
    ),

    KEY idx_ruminant_animal_transfer_reversed_by (
        reversed_by
    ),

    CONSTRAINT fk_ract_farm
        FOREIGN KEY (farm_id)
        REFERENCES farms(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_ract_animal
        FOREIGN KEY (animal_id)
        REFERENCES ruminant_animals(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_ract_population_transfer
        FOREIGN KEY (farm_id, population_transfer_id)
        REFERENCES production_population_transfers(farm_id, id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_ract_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_ract_reversed_by
        FOREIGN KEY (reversed_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


INSERT INTO schema_migrations (filename)
VALUES ('061_ruminant_cycle_transfer_integrity.sql')
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
