-- V2.3 Billing Transactional Integrity Repair
--
-- Repairs installations where migration 042 inherited a non-transactional
-- default storage engine (for example MyISAM), which also caused MySQL to ignore
-- the declared foreign keys. Scope is intentionally limited to the two billing
-- audit tables; no operational farm table is converted.

ALTER TABLE billing_payment_attempts ENGINE=InnoDB;
ALTER TABLE billing_provider_events ENGINE=InnoDB;

SET @billing_fk_sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE billing_payment_attempts ADD CONSTRAINT fk_billing_attempt_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE',
        'SELECT 1'
    )
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND constraint_name = 'fk_billing_attempt_farm'
);
PREPARE billing_fk_stmt FROM @billing_fk_sql;
EXECUTE billing_fk_stmt;
DEALLOCATE PREPARE billing_fk_stmt;

SET @billing_fk_sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE billing_payment_attempts ADD CONSTRAINT fk_billing_attempt_subscription_record FOREIGN KEY (applied_subscription_record_id) REFERENCES subscriptions(id) ON DELETE SET NULL',
        'SELECT 1'
    )
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND constraint_name = 'fk_billing_attempt_subscription_record'
);
PREPARE billing_fk_stmt FROM @billing_fk_sql;
EXECUTE billing_fk_stmt;
DEALLOCATE PREPARE billing_fk_stmt;

SET @billing_fk_sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE billing_provider_events ADD CONSTRAINT fk_billing_event_attempt FOREIGN KEY (payment_attempt_id) REFERENCES billing_payment_attempts(id) ON DELETE SET NULL',
        'SELECT 1'
    )
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND constraint_name = 'fk_billing_event_attempt'
);
PREPARE billing_fk_stmt FROM @billing_fk_sql;
EXECUTE billing_fk_stmt;
DEALLOCATE PREPARE billing_fk_stmt;
