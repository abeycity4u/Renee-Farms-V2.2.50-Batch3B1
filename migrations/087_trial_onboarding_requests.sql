-- V3.2 Renee AgriSuite public trial onboarding request foundation.
--
-- Contract:
--   * public submission creates an onboarding request, never a farm directly;
--   * automatic and manual approval converge on status=approved;
--   * approval_mode records whether approval was automatic or manual;
--   * provisioning may bind a request to at most one farm and one Farm Admin;
--   * request lifecycle is separate from tenant subscription lifecycle;
--   * no password, credential token, credential URL, payment secret, or raw IP
--     is stored in this table;
--   * the 14-day trial clock is NOT started by this request table.

CREATE TABLE trial_onboarding_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    request_reference CHAR(32) NOT NULL,

    status ENUM(
        'pending_review',
        'approved',
        'provisioning',
        'provisioned',
        'activated',
        'rejected',
        'cancelled'
    ) NOT NULL DEFAULT 'pending_review',

    approval_mode ENUM(
        'auto',
        'manual'
    ) NULL DEFAULT NULL,

    farm_name VARCHAR(150) NOT NULL,
    requested_workspace_id VARCHAR(100) NULL,

    admin_full_name VARCHAR(150) NOT NULL,
    admin_username VARCHAR(100) NULL,
    admin_email VARCHAR(255) NOT NULL,

    contact_name VARCHAR(150) NULL,
    contact_email VARCHAR(255) NULL,

    requested_modules_snapshot TEXT NULL,

    approved_plan_code VARCHAR(50) NULL,
    approved_modules_snapshot TEXT NULL,
    approved_role_limits_snapshot TEXT NULL,
    approved_trial_days SMALLINT UNSIGNED NULL,

    review_reason_code VARCHAR(80) NULL,
    rejection_reason_code VARCHAR(80) NULL,

    source_fingerprint CHAR(64) NULL,

    farm_id INT NULL,
    farm_admin_user_id INT NULL,
    approved_by_user_id INT NULL,

    approved_at DATETIME NULL,
    provisioning_started_at DATETIME NULL,
    provisioned_at DATETIME NULL,
    activated_at DATETIME NULL,
    rejected_at DATETIME NULL,
    cancelled_at DATETIME NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uniq_trial_onboarding_request_reference (
        request_reference
    ),

    UNIQUE KEY uniq_trial_onboarding_request_farm (
        farm_id
    ),

    UNIQUE KEY uniq_trial_onboarding_request_admin (
        farm_admin_user_id
    ),

    KEY idx_trial_onboarding_status_created (
        status,
        created_at,
        id
    ),

    KEY idx_trial_onboarding_admin_email_status (
        admin_email,
        status
    ),

    KEY idx_trial_onboarding_workspace_status (
        requested_workspace_id,
        status
    ),

    KEY idx_trial_onboarding_fingerprint_status (
        source_fingerprint,
        status
    ),

    CONSTRAINT fk_trial_onboarding_farm
        FOREIGN KEY (farm_id)
        REFERENCES farms(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_trial_onboarding_farm_admin
        FOREIGN KEY (farm_admin_user_id)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_trial_onboarding_approved_by
        FOREIGN KEY (approved_by_user_id)
        REFERENCES users(id)
        ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO schema_migrations(filename)
VALUES ('087_trial_onboarding_requests.sql')
ON DUPLICATE KEY UPDATE filename = VALUES(filename);
