CREATE TABLE IF NOT EXISTS digital_id_notifications (
    application_id VARCHAR(255) NOT NULL PRIMARY KEY,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Existing eligible records also receive an availability notice on rollout.
INSERT INTO digital_id_notifications (application_id)
SELECT id_number FROM applications
WHERE application_type = 'senior'
  AND (parent_senior_id IS NULL OR parent_senior_id = '')
  AND workflow_state IN ('Verified','Approved','Released')
  AND COALESCE(is_archived,0) = 0
  AND senior_id_no IS NOT NULL AND TRIM(senior_id_no) <> ''
  AND TRIM(senior_id_no) NOT REGEXP '^OSCA-[0-9]{4}-[0-9A-F]{6}$'
ON DUPLICATE KEY UPDATE application_id = VALUES(application_id);
