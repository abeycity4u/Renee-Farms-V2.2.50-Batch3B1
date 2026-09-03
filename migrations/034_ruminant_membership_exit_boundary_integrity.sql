-- V2.2.49 Batch 4E — lifecycle/membership exit-boundary integrity.
-- Track membership closures performed by a dated animal exit so sale reversals
-- can restore the exact prior membership end date without guessing.

ALTER TABLE ruminant_animal_cycle_memberships
    ADD COLUMN closed_by_exit_event_id INT NULL AFTER end_date;

ALTER TABLE ruminant_animal_cycle_memberships
    ADD COLUMN pre_exit_end_date DATE NULL AFTER closed_by_exit_event_id;

ALTER TABLE ruminant_animal_cycle_memberships
    ADD KEY idx_ruminant_membership_exit_boundary (farm_id, animal_id, closed_by_exit_event_id);
