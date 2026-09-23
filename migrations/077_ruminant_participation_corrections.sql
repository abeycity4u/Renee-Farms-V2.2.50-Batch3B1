-- Renee Farms V3.0.1 — audited Ruminant physical-participation corrections.
--
-- ruminant_animals and ruminant_animal_cycle_memberships remain the current
-- canonical projection.
--
-- This immutable table records the before/after facts whenever a confirmed
-- late-tagging/history correction extends an animal's physical farm entry and
-- cycle participation earlier.
--
-- It does NOT create a parallel membership reader and does NOT infer history.
--
-- Transfer-owned membership starts remain controlled by the transfer service.
-- Purchase/tag/registry dates are not rewritten by this correction.

CREATE TABLE IF NOT EXISTS ruminant_participation_corrections (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    farm_id INT NOT NULL,
    animal_id INT NOT NULL,
    membership_id INT NOT NULL,
    cycle_id INT NOT NULL,

    old_farm_entry_date DATE NOT NULL,
    new_farm_entry_date DATE NOT NULL,

    old_membership_start_date DATE NOT NULL,
    new_membership_start_date DATE NOT NULL,

    reason VARCHAR(500) NOT NULL,
    request_token VARCHAR(64) NOT NULL,

    created_by INT NULL,
    created_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uniq_rpc_request (
        farm_id,
        request_token
    ),

    KEY idx_rpc_animal (
        farm_id,
        animal_id,
        created_at,
        id
    ),

    KEY idx_rpc_membership (
        farm_id,
        membership_id,
        created_at,
        id
    ),

    KEY idx_rpc_cycle (
        farm_id,
        cycle_id,
        created_at,
        id
    ),

    CONSTRAINT chk_rpc_farm_entry_extension
        CHECK (
            new_farm_entry_date
            <=
            old_farm_entry_date
        ),

    CONSTRAINT chk_rpc_membership_extension
        CHECK (
            new_membership_start_date
            <=
            old_membership_start_date
        ),

    CONSTRAINT chk_rpc_entry_before_participation
        CHECK (
            new_farm_entry_date
            <=
            new_membership_start_date
        ),

    CONSTRAINT fk_rpc_farm
        FOREIGN KEY (farm_id)
        REFERENCES farms(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_rpc_animal
        FOREIGN KEY (animal_id)
        REFERENCES ruminant_animals(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_rpc_membership
        FOREIGN KEY (membership_id)
        REFERENCES ruminant_animal_cycle_memberships(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_rpc_cycle
        FOREIGN KEY (cycle_id)
        REFERENCES production_cycles(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_rpc_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


INSERT INTO schema_migrations (filename)
VALUES (
    '077_ruminant_participation_corrections.sql'
)
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
