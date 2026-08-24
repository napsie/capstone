-- Dashboard query indexes for existing installations.
-- MariaDB supports IF NOT EXISTS for index additions, so this migration is safe
-- to run when an index has already been created from the full schema.
ALTER TABLE `applications`
    ADD COLUMN IF NOT EXISTS `is_archived` TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE `applications`
    ADD INDEX IF NOT EXISTS `idx_dashboard_barangay_workflow` (`barangay`, `is_archived`, `workflow_state`),
    ADD INDEX IF NOT EXISTS `idx_dashboard_barangay_date` (`barangay`, `is_archived`, `date_submitted`),
    ADD INDEX IF NOT EXISTS `idx_dashboard_priority_date` (`is_archived`, `priority_level`, `date_submitted`);
