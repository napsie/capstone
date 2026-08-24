-- Schema fields previously created during normal web requests.
ALTER TABLE `applications`
    ADD COLUMN IF NOT EXISTS `is_archived` TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `archived_at` DATETIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `archived_by` VARCHAR(100) DEFAULT NULL;

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `is_archived` TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `archived_at` DATETIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `archived_by` VARCHAR(100) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `audit_trail` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT DEFAULT NULL,
    `username` VARCHAR(100) NOT NULL,
    `role` VARCHAR(50) NOT NULL,
    `barangay` VARCHAR(100) DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `description` TEXT NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_audit_user` (`user_id`),
    INDEX `idx_audit_action` (`action`),
    INDEX `idx_audit_created_id` (`created_at`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `applications`
    ADD INDEX IF NOT EXISTS `idx_application_senior_id` (`senior_id_no`),
    ADD INDEX IF NOT EXISTS `idx_application_proxy_token` (`proxy_token`),
    ADD INDEX IF NOT EXISTS `idx_home_visit_schedule` (`home_visit_personnel_id`, `home_visit_scheduled_at`, `home_visit_status`);

ALTER TABLE `application_history`
    ADD INDEX IF NOT EXISTS `idx_history_application_state_date` (`application_id`, `new_state`, `changed_at`);

ALTER TABLE `sms_notifications`
    ADD INDEX IF NOT EXISTS `idx_sms_status_created` (`status`, `created_at`);
