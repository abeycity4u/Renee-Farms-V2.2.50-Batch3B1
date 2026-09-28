-- V3.2 trial onboarding rejection actor attribution.
--
-- Contract:
--   * rejected trial requests retain the Platform Owner user responsible
--     for the rejection decision;
--   * approval attribution remains exclusively approved_by_user_id;
--   * rejection attribution remains exclusively rejected_by_user_id;
--   * deleting a user must not delete or invalidate onboarding history;
--   * this migration does not change request status or reject any request.

ALTER TABLE trial_onboarding_requests
    ADD COLUMN rejected_by_user_id INT NULL
        AFTER approved_by_user_id,

    ADD KEY idx_trial_onboarding_rejected_by (
        rejected_by_user_id
    ),

    ADD CONSTRAINT fk_trial_onboarding_rejected_by
        FOREIGN KEY (rejected_by_user_id)
        REFERENCES users(id)
        ON DELETE SET NULL;

INSERT INTO schema_migrations (
    filename
) VALUES (
    '088_trial_onboarding_rejection_actor.sql'
)
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
