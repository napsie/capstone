-- Older restore requests cleared is_archived but left the record Rejected.
-- The original rejection stays in application_history for the audit trail.
UPDATE applications
SET workflow_state = 'For Review',
    status = 'pending',
    return_reason = NULL
WHERE COALESCE(is_archived, 0) = 0
  AND (workflow_state = 'Rejected' OR LOWER(COALESCE(status, '')) = 'rejected');
