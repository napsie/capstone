-- Scalable document, import, and reporting foundation. Safe to run repeatedly on MariaDB.
USE `capstone1`;

ALTER TABLE `application_documents`
    ADD COLUMN IF NOT EXISTS `version` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS `is_current` TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS `uploaded_by` VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `uploader_id` INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `original_filename` VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `file_size` BIGINT UNSIGNED DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `checksum_sha256` CHAR(64) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `source` VARCHAR(30) NOT NULL DEFAULT 'application',
    ADD COLUMN IF NOT EXISTS `supersedes_document_id` INT DEFAULT NULL;

SET @sql = (SELECT IF(EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='application_documents' AND index_name='uq_application_document'), 'ALTER TABLE application_documents DROP INDEX uq_application_document', 'SELECT 1'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
ALTER TABLE `application_documents`
    ADD UNIQUE INDEX IF NOT EXISTS `uq_application_document_version` (`application_id`,`document_key`,`version`),
    ADD INDEX IF NOT EXISTS `idx_document_current` (`application_id`,`is_current`,`document_key`),
    ADD INDEX IF NOT EXISTS `idx_document_checksum` (`checksum_sha256`),
    ADD INDEX IF NOT EXISTS `idx_document_uploaded_at` (`created_at`,`id`);

CREATE TABLE IF NOT EXISTS `import_jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `job_token` CHAR(36) NOT NULL,
    `original_filename` VARCHAR(255) NOT NULL,
    `file_checksum` CHAR(64) NOT NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'validating',
    `total_rows` INT UNSIGNED NOT NULL DEFAULT 0,
    `valid_rows` INT UNSIGNED NOT NULL DEFAULT 0,
    `error_rows` INT UNSIGNED NOT NULL DEFAULT 0,
    `duplicate_rows` INT UNSIGNED NOT NULL DEFAULT 0,
    `processed_rows` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_by` INT DEFAULT NULL,
    `created_by_username` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `completed_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`), UNIQUE KEY `uq_import_job_token` (`job_token`),
    KEY `idx_import_job_status_created` (`status`,`created_at`),
    KEY `idx_import_job_creator_created` (`created_by`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `import_job_rows` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `import_job_id` BIGINT UNSIGNED NOT NULL,
    `row_number` INT UNSIGNED NOT NULL,
    `normalized_payload` JSON NOT NULL,
    `validation_errors` JSON DEFAULT NULL,
    `duplicate_matches` JSON DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
    `application_id` VARCHAR(255) DEFAULT NULL,
    `processed_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`), UNIQUE KEY `uq_import_job_row` (`import_job_id`,`row_number`),
    KEY `idx_import_rows_status` (`import_job_id`,`status`,`row_number`),
    CONSTRAINT `fk_import_rows_job` FOREIGN KEY (`import_job_id`) REFERENCES `import_jobs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `applications`
    ADD INDEX IF NOT EXISTS `idx_reporting_type_state_date` (`application_type`,`workflow_state`,`date_submitted`),
    ADD INDEX IF NOT EXISTS `idx_reporting_barangay_state_date` (`barangay`,`workflow_state`,`date_submitted`),
    ADD INDEX IF NOT EXISTS `idx_reporting_home_visit` (`home_visit_status`,`home_visit_scheduled_at`);

CREATE OR REPLACE VIEW `management_application_metrics` AS
SELECT a.id_number, a.application_type, a.requested_benefit, a.barangay,
       COALESCE(NULLIF(a.workflow_state,''),'Received') workflow_state,
       a.date_submitted, a.home_visit_status, a.home_visit_scheduled_at,
       TIMESTAMPDIFF(HOUR, a.date_submitted, COALESCE(MAX(h.changed_at), NOW())) processing_hours,
       MAX(CASE WHEN h.new_state='Verified' THEN h.changed_at END) verified_at,
       MAX(CASE WHEN h.new_state='Rejected' THEN h.comments END) rejection_reason,
       SUM(CASE WHEN h.new_state='Needs Correction' THEN 1 ELSE 0 END) correction_count
FROM applications a LEFT JOIN application_history h ON h.application_id=a.id_number
WHERE COALESCE(a.is_archived,0)=0
GROUP BY a.id_number, a.application_type, a.requested_benefit, a.barangay, a.workflow_state,
         a.date_submitted, a.home_visit_status, a.home_visit_scheduled_at;

