-- Local Pension applications always enter the required surprise Home Visit queue.
UPDATE applications
SET home_visit_status = 'Waiting for Home Visit',
    home_visit_scheduled_at = NULL,
    home_visit_personnel_id = NULL,
    sms_notification_status = NULL
WHERE application_type = 'pension'
  AND COALESCE(home_visit_status, '') = '';
