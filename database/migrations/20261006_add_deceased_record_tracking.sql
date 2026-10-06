ALTER TABLE applications
    MODIFY COLUMN status ENUM('pending','approved','rejected','deceased') NOT NULL DEFAULT 'pending';

ALTER TABLE applications
    ADD COLUMN IF NOT EXISTS deceased_at DATETIME NULL AFTER death_registration_date,
    ADD COLUMN IF NOT EXISTS deceased_source_application_id VARCHAR(50) NULL AFTER deceased_at,
    ADD INDEX IF NOT EXISTS idx_deceased_records (workflow_state, deceased_at, barangay);

UPDATE applications senior
JOIN applications burial
  ON burial.application_type = 'burial'
 AND burial.workflow_state IN ('Verified', 'Approved', 'Released')
 AND (
      burial.parent_senior_id = senior.id_number
      OR (
          burial.parent_senior_id IS NULL
          AND burial.deceased_first_name = senior.firstName
          AND burial.deceased_last_name = senior.lastName
          AND (burial.deceased_birth_date = senior.birth_date OR burial.deceased_birth_date IS NULL)
      )
 )
SET senior.deceased_at = COALESCE(senior.deceased_at, burial.date_of_death, burial.date_submitted),
    senior.deceased_source_application_id = COALESCE(senior.deceased_source_application_id, burial.id_number)
WHERE senior.application_type = 'senior'
  AND senior.workflow_state = 'Deceased';
