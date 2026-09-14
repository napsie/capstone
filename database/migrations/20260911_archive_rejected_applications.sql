-- Reconcile applications rejected by the legacy home-visit workflow.
-- Older versions changed the visit status but did not set the archive fields.
UPDATE applications
SET home_visit_status = CASE
        WHEN home_visit_status = 'Cancelled' THEN 'Rejected'
        ELSE home_visit_status
    END,
    workflow_state = 'Rejected',
    status = 'rejected',
    is_archived = 1,
    archived_at = COALESCE(archived_at, CURRENT_TIMESTAMP),
    archived_by = COALESCE(NULLIF(archived_by, ''), 'System')
WHERE COALESCE(is_archived, 0) = 0
  AND (
      workflow_state = 'Rejected'
      OR LOWER(COALESCE(status, '')) = 'rejected'
      OR home_visit_status IN ('Rejected', 'Cancelled')
  );
