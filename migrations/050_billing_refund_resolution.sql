-- V2.3 billing post-application refund-resolution foundation.
--
-- Provider payment status remains an external/provider fact on
-- billing_payment_attempts.
--
-- This table records the separate commercial response when a provider-verified
-- refund belongs to commercial state that was already applied.
--
-- The initial workflow state is pending_review. No entitlement, subscription,
-- seat count or payment status is changed by this migration.
--
-- A later shared resolution service may explicitly choose:
-- - preserve_entitlement
-- - reverse_entitlement
--
-- The commercial decision must never be inferred merely from status=refunded.

CREATE TABLE IF NOT EXISTS billing_refund_resolutions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    farm_id INT NOT NULL,
    payment_attempt_id BIGINT UNSIGNED NOT NULL,
    purpose VARCHAR(32) NOT NULL,

    status VARCHAR(32) NOT NULL DEFAULT 'pending_review',
    resolution_action VARCHAR(32) NULL,

    refund_verified_at DATETIME NOT NULL,

    applied_subscription_record_id INT NULL,
    seat_change_request_id BIGINT UNSIGNED NULL,

    resolved_at DATETIME NULL,
    resolved_by_user_id INT NULL,
    resolution_reason VARCHAR(160) NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uniq_billing_refund_payment_attempt (
        payment_attempt_id
    ),

    INDEX idx_billing_refund_farm_status (
        farm_id,
        status
    ),

    INDEX idx_billing_refund_status_created (
        status,
        created_at
    ),

    INDEX idx_billing_refund_subscription (
        applied_subscription_record_id
    ),

    INDEX idx_billing_refund_seat_request (
        seat_change_request_id
    ),

    CONSTRAINT fk_billing_refund_farm
        FOREIGN KEY (farm_id)
        REFERENCES farms(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_billing_refund_payment_attempt
        FOREIGN KEY (payment_attempt_id)
        REFERENCES billing_payment_attempts(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_billing_refund_subscription
        FOREIGN KEY (applied_subscription_record_id)
        REFERENCES subscriptions(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_billing_refund_seat_request
        FOREIGN KEY (seat_change_request_id)
        REFERENCES billing_seat_change_requests(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT INTO schema_migrations (filename)
VALUES ('050_billing_refund_resolution.sql')
ON DUPLICATE KEY UPDATE filename = VALUES(filename);
