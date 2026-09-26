-- V3.1 Account Credential Delivery Outbox
--
-- Purpose:
--   * remove credential mail transport from browser request latency;
--   * provide one durable delivery queue for activation and password reset;
--   * preserve enumeration-safe public credential recovery;
--   * never persist credential secrets or account lookup identifiers.
--
-- Privacy / security:
--   * no username;
--   * no workspace id;
--   * no email;
--   * no raw token or token hash;
--   * no credential URL;
--   * no message subject/body;
--   * no transport exception text.
--
-- user_id is intentionally nullable. Public no-match/ineligible requests may
-- enqueue a neutral row with no user identity, and deletion of a real account
-- must not block outbox cleanup or leave a restrictive user foreign key.
--
-- Migration 085_account_credential_lifecycle.sql must be installed before
-- this outbox is used by runtime credential workers.

CREATE TABLE account_credential_delivery_outbox (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    user_id INT NULL,

    purpose ENUM(
        'activation',
        'password_reset'
    ) NOT NULL,

    status ENUM(
        'pending',
        'processing',
        'sent',
        'discarded',
        'failed'
    ) NOT NULL DEFAULT 'pending',

    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,

    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    processed_at DATETIME NULL DEFAULT NULL,

    last_error_code VARCHAR(80) NULL DEFAULT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    KEY idx_account_credential_outbox_pending (
        status,
        available_at,
        id
    ),

    KEY idx_account_credential_outbox_user_purpose (
        user_id,
        purpose,
        status
    ),

    CONSTRAINT fk_account_credential_outbox_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO schema_migrations(filename)
VALUES ('086_account_credential_delivery_outbox.sql')
ON DUPLICATE KEY UPDATE filename = VALUES(filename);
