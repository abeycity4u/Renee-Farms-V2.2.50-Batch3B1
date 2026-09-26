-- Renee Farms V3.1 - Account credential lifecycle foundation.
--
-- Compatibility contract:
--   * every existing user remains active after migration;
--   * legacy users may continue to have NULL/blank email;
--   * email is NOT globally unique;
--   * existing password hashes are not changed;
--   * activation/reset secrets are never stored in plaintext.
--
-- New-account writers will explicitly create accounts as pending_activation.
-- Existing accounts inherit the active default/backfill and continue signing in
-- exactly as before until the V3.1 account-lifecycle runtime is enabled.

ALTER TABLE users
    ADD COLUMN credential_state ENUM(
        'active',
        'pending_activation'
    ) NOT NULL DEFAULT 'active'
    AFTER email;

CREATE TABLE account_credential_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    purpose ENUM(
        'activation',
        'password_reset'
    ) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uniq_account_credential_token_hash (
        token_hash
    ),

    KEY idx_account_credential_tokens_user_purpose (
        user_id,
        purpose,
        consumed_at
    ),

    KEY idx_account_credential_tokens_expiry (
        expires_at
    ),

    CONSTRAINT fk_account_credential_tokens_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO schema_migrations (
    filename
) VALUES (
    '085_account_credential_lifecycle.sql'
)
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);
