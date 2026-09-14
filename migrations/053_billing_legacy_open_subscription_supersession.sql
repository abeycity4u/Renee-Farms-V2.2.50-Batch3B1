-- V2.3 one-time normalization for pre-hardening open subscription attempts.
--
-- Historical installations may contain an initialized/pending subscription
-- attempt that remained commercially eligible even though a strictly newer
-- provider-verified paid subscription was already applied for the same tenant.
--
-- Those old provider facts are NOT rewritten or fabricated.
--
-- This migration changes commercial-disposition audit metadata only:
--   commercial_disposition
--   commercial_superseded_at
--   commercial_supersession_verified_at
--   commercial_superseded_by_user_id
--   commercial_supersession_reason
--
-- For this legacy-only path, commercial_supersession_verified_at records the
-- provider verification timestamp of the strictly newer paid-and-applied
-- subscription that proves the older commercial intent is obsolete.
--
-- If the old provider later reports the old attempt as paid, normal paid
-- dispatch still records that provider fact, while commercial_disposition =
-- 'superseded' prevents the older checkout from overwriting newer tenant state.
--
-- Current hardened checkout coordination prevents creation of this stale shape.
-- This migration therefore does not expand normal runtime supersession rules.

START TRANSACTION;

UPDATE billing_payment_attempts AS legacy
JOIN billing_payment_attempts AS newer
  ON newer.farm_id = legacy.farm_id
 AND newer.purpose = 'subscription'
 AND newer.id > legacy.id
 AND newer.status = 'paid'
 AND newer.verified_at IS NOT NULL
 AND newer.paid_at IS NOT NULL
 AND newer.applied_subscription_record_id IS NOT NULL
 AND newer.initiated_by_user_id IS NOT NULL
 AND newer.initiated_by_user_id > 0
LEFT JOIN billing_payment_attempts AS newer_later
  ON newer_later.farm_id = legacy.farm_id
 AND newer_later.purpose = 'subscription'
 AND newer_later.id > newer.id
 AND newer_later.status = 'paid'
 AND newer_later.verified_at IS NOT NULL
 AND newer_later.paid_at IS NOT NULL
 AND newer_later.applied_subscription_record_id IS NOT NULL
 AND newer_later.initiated_by_user_id IS NOT NULL
 AND newer_later.initiated_by_user_id > 0
SET
    legacy.commercial_disposition = 'superseded',
    legacy.commercial_superseded_at = CURRENT_TIMESTAMP,
    legacy.commercial_supersession_verified_at = newer.verified_at,
    legacy.commercial_superseded_by_user_id = newer.initiated_by_user_id,
    legacy.commercial_supersession_reason =
        CONCAT(
            'legacy_newer_applied_attempt_',
            newer.id
        )
WHERE legacy.purpose = 'subscription'
  AND legacy.commercial_disposition = 'eligible'
  AND legacy.status IN ('initialized', 'pending')
  AND legacy.verified_at IS NULL
  AND legacy.paid_at IS NULL
  AND legacy.failed_at IS NULL
  AND legacy.applied_subscription_record_id IS NULL
  AND legacy.commercial_superseded_at IS NULL
  AND legacy.commercial_supersession_verified_at IS NULL
  AND legacy.commercial_superseded_by_user_id IS NULL
  AND legacy.commercial_supersession_reason IS NULL
  AND newer_later.id IS NULL;

SELECT ROW_COUNT() AS legacy_open_subscription_attempts_superseded;

INSERT INTO schema_migrations (filename)
VALUES (
    '053_billing_legacy_open_subscription_supersession.sql'
)
ON DUPLICATE KEY UPDATE
    filename = VALUES(filename);

COMMIT;
