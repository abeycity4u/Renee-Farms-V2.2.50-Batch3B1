-- Renee Farms V3.0.1 — explicit tagged-ruminant slaughter lifecycle.
--
-- Slaughter is a terminal physical lifecycle event distinct from cull and sale.
-- Existing animals and exit history are not rewritten.
-- Existing legacy culled_slaughtered sale outcomes remain historical cull facts.
--
-- This migration only extends the registry status vocabulary so a new
-- Slaughtered lifecycle event can be represented explicitly.

ALTER TABLE ruminant_animals
    MODIFY COLUMN status ENUM(
        'active',
        'sold',
        'dead',
        'culled',
        'slaughtered',
        'transferred'
    ) NOT NULL DEFAULT 'active';


INSERT INTO schema_migrations (filename)
VALUES ('062_ruminant_slaughter_lifecycle.sql')
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
