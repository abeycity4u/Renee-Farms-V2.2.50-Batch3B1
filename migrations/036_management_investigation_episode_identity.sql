-- V2.2.49 Batch 4J — Investigation Episode Isolation
-- Gives each evidence window its own immutable management follow-through identity.
ALTER TABLE management_investigation_followups
    ADD COLUMN episode_key VARCHAR(120) NULL AFTER as_of_date;

-- Preserve every existing review as historical context. We intentionally do not guess
-- which evidence window a legacy review represented after later source records may exist.
UPDATE management_investigation_followups
SET episode_key = CONCAT('legacy:', id)
WHERE episode_key IS NULL OR episode_key = '';

ALTER TABLE management_investigation_followups
    MODIFY episode_key VARCHAR(120) NOT NULL,
    DROP INDEX uniq_management_investigation,
    ADD UNIQUE KEY uniq_management_investigation_episode
      (farm_id, investigation_type, subject_id, issue_type, episode_key),
    ADD KEY idx_management_investigation_subject
      (farm_id, investigation_type, subject_id, issue_type, as_of_date);
