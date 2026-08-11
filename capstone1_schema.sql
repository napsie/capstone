-- ============================================================
-- CPRAS (Centralized Profiling and Record Authentication System)
-- Complete Database Schema — capstone1
-- Last Updated: 2026-08-06
-- ============================================================

CREATE DATABASE IF NOT EXISTS `capstone1`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE `capstone1`;

-- ============================================================
-- Drop all tables in correct order (respect foreign keys)
-- ============================================================
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `application_documents`;
DROP TABLE IF EXISTS `application_history`;
DROP TABLE IF EXISTS `login_history`;
DROP TABLE IF EXISTS `remember_tokens`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `applications`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `system_settings`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Table: users
-- ============================================================
CREATE TABLE `users` (
  `id`                 int(11)      NOT NULL AUTO_INCREMENT,
  `username`           varchar(50)  NOT NULL,
  `password`           varchar(255) NOT NULL,
  `role`               enum('barangay_staff','department_admin') NOT NULL,
  `first_name`         varchar(100) NOT NULL,
  `last_name`          varchar(100) NOT NULL,
  `email`              varchar(100) NOT NULL,
  `barangay`           varchar(100) DEFAULT NULL,
  `created_at`         timestamp    NOT NULL DEFAULT current_timestamp(),
  `profile_picture`    varchar(255) DEFAULT 'default.jpg',
  `reset_token`        varchar(255) DEFAULT NULL,
  `reset_token_expiry` datetime     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email`    (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table: applications
-- Complete schema including all extended columns
-- ============================================================
CREATE TABLE `applications` (
  -- Core identity
  `id_number`                  varchar(255) NOT NULL,
  `full_name`                  varchar(255) NOT NULL,
  `application_type`           varchar(50)  NOT NULL,

  -- Personal details
  `lastName`                   varchar(255) DEFAULT NULL,
  `firstName`                  varchar(255) DEFAULT NULL,
  `middleName`                 varchar(255) DEFAULT NULL,
  `suffix`                     varchar(255) DEFAULT NULL,
  `birth_date`                 date         NOT NULL,
  `contact_number`             varchar(20)  NOT NULL,
  `complete_address`           text         NOT NULL,
  `emergency_contact`          varchar(20)  NOT NULL,
  `emergency_contact_name`     varchar(255) DEFAULT NULL,
  `email_address`              varchar(255) DEFAULT NULL,

  -- Metadata
  `barangay`                   varchar(100) NOT NULL,
  `date_submitted`             timestamp    NOT NULL DEFAULT current_timestamp(),
  `status`                     enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',

  -- Rule-based workflow status
  `workflow_state`             varchar(50)  DEFAULT 'Received',
  `return_reason`              varchar(500) DEFAULT NULL,

  -- Priority & proxy
  `priority_level`             varchar(20)  DEFAULT 'normal',
  `is_proxy_application`       int(1)       DEFAULT 0,
  `proxy_name`                 varchar(255) DEFAULT NULL,
  `proxy_relationship`         varchar(100) DEFAULT NULL,
  `proxy_contact_number`       varchar(20)  DEFAULT NULL,
  `proxy_token`                varchar(255) DEFAULT NULL,

  -- PWD specific
  `disability_type`            varchar(255) DEFAULT NULL,

  -- Social Pension specific
  `sss_number`                 varchar(50)  DEFAULT NULL,
  `pension_amount`             decimal(10,2) DEFAULT NULL,

  -- Burial Assistance specific
  `date_of_death`              date         DEFAULT NULL,
  `relationship_to_deceased`   varchar(100) DEFAULT NULL,

  -- Uploaded document binaries (stored as BLOB in DB)
  `proof_of_address`           mediumblob   DEFAULT NULL,
  `proof_of_address_type`      varchar(100) DEFAULT NULL,
  `id_image`                   mediumblob   DEFAULT NULL,
  `id_image_type`              varchar(100) DEFAULT NULL,

  -- CSV-import supporting document type references (labels only)
  `birth_certificate_type`     varchar(100) DEFAULT NULL,
  `medical_certificate_type`   varchar(100) DEFAULT NULL,
  `client_identification_type` varchar(100) DEFAULT NULL,

  -- Additional info
  `medical_conditions`         text         DEFAULT NULL,
  `additional_notes`           text         DEFAULT NULL,

  -- OSCA Official Form Fields
  `place_of_birth`             varchar(255) DEFAULT NULL,
  `gender`                     varchar(20)  DEFAULT NULL,
  `civil_status`               varchar(50)  DEFAULT NULL,
  `mothers_maiden_name`        varchar(255) DEFAULT NULL,
  `house_no`                   varchar(50)  DEFAULT NULL,
  `street`                     varchar(255) DEFAULT NULL,
  `city`                       varchar(100) DEFAULT 'Pasig City',
  `province`                   varchar(100) DEFAULT 'Metro Manila',
  `zip_code`                   varchar(10)  DEFAULT NULL,
  `landmark`                   varchar(255) DEFAULT NULL,
  `health_status`              varchar(100) DEFAULT NULL,
  `senior_id_no`               varchar(50)  DEFAULT NULL,
  `id_purpose`                 varchar(20)  DEFAULT NULL,
  `milestone_age`              varchar(10)  DEFAULT NULL,
  `claimant_name`              varchar(255) DEFAULT NULL,
  `claimant_relationship`      varchar(100) DEFAULT NULL,
  `claimant_contact`           varchar(20)  DEFAULT NULL,
  `deceased_last_name`         varchar(255) DEFAULT NULL,
  `deceased_first_name`        varchar(255) DEFAULT NULL,
  `deceased_middle_name`       varchar(255) DEFAULT NULL,
  `deceased_suffix`            varchar(50)  DEFAULT NULL,
  `deceased_birth_date`        date         DEFAULT NULL,
  `landbank_card_no`           varchar(50)  DEFAULT NULL,
  `applicant_name`             varchar(255) DEFAULT NULL,
  `visit_purpose`              varchar(255) DEFAULT NULL,
  `living_arrangement`         varchar(100) DEFAULT NULL,
  `is_pensioner`               tinyint(1)   DEFAULT NULL,
  `pension_source`             varchar(255) DEFAULT NULL,
  `family_support`             tinyint(1)   DEFAULT NULL,
  `family_support_amount`      decimal(10,2) DEFAULT NULL,
  `personal_income`            tinyint(1)   DEFAULT NULL,
  `personal_income_amount`     decimal(10,2) DEFAULT NULL,
  `health_condition`           varchar(255) DEFAULT NULL,
  `with_maintenance`           tinyint(1)   DEFAULT NULL,
  `maintenance_spec`           varchar(255) DEFAULT NULL,
  `visit_summary`              text         DEFAULT NULL,
  `name_on_card`               varchar(23)  DEFAULT NULL,
  `tin`                        varchar(50)  DEFAULT NULL,
  `id_type_presented`          varchar(100) DEFAULT NULL,
  `nationality`                varchar(50)  DEFAULT 'Filipino',
  `source_of_funds`            varchar(255) DEFAULT NULL,
  `atm_card_no`                varchar(50)  DEFAULT NULL,
  `control_no`                 varchar(50)  DEFAULT NULL,
  `is_permanent_income`        tinyint(1)   DEFAULT NULL,
  `income_source`              varchar(255) DEFAULT NULL,
  `owns_house`                 tinyint(1)   DEFAULT NULL,
  `is_renter`                  tinyint(1)   DEFAULT NULL,

  -- Required attachments / file path references
  `psa_birth_cert`             varchar(255) DEFAULT NULL,
  `barangay_residency`         varchar(255) DEFAULT NULL,
  `comelec_cert`               varchar(255) DEFAULT NULL,
  `proof_of_life`              varchar(255) DEFAULT NULL,
  `auth_letter`                varchar(255) DEFAULT NULL,
  `proxy_id`                   varchar(255) DEFAULT NULL,
  `proxy_birth_cert`           varchar(255) DEFAULT NULL,
  `home_visitation_form`       varchar(255) DEFAULT NULL,
  `landbank_enrollment_form`   varchar(255) DEFAULT NULL,
  `parent_senior_id`           varchar(50)  DEFAULT NULL,

  PRIMARY KEY (`id_number`),
  KEY `idx_barangay`           (`barangay`),
  KEY `idx_workflow_state`     (`workflow_state`),
  KEY `idx_priority`           (`priority_level`),
  KEY `idx_date_submitted`     (`date_submitted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table: application_documents
-- Complete per-application document store
-- ============================================================
CREATE TABLE `application_documents` (
  `id`               int(11)      NOT NULL AUTO_INCREMENT,
  `application_id`   varchar(255) NOT NULL,
  `document_key`     varchar(100) NOT NULL,
  `document_label`   varchar(255) NOT NULL,
  `mime_type`        varchar(100) NOT NULL,
  `document_data`    mediumblob   NOT NULL,
  `created_at`       timestamp    NOT NULL DEFAULT current_timestamp(),
  `updated_at`       timestamp    NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_application_document` (`application_id`, `document_key`),
  KEY `idx_application_documents_application` (`application_id`),
  CONSTRAINT `fk_documents_application`
    FOREIGN KEY (`application_id`) REFERENCES `applications` (`id_number`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table: application_history
-- Workflow audit trail — records every routed status update
-- ============================================================
CREATE TABLE `application_history` (
  `id`               int(11)      NOT NULL AUTO_INCREMENT,
  `application_id`   varchar(255) NOT NULL,
  `previous_state`   varchar(50)  NOT NULL,
  `new_state`        varchar(50)  NOT NULL,
  `changed_by`       varchar(100) NOT NULL,
  `changed_at`       timestamp    NOT NULL DEFAULT current_timestamp(),
  `comments`         text         DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_application_id` (`application_id`),
  KEY `idx_changed_at`     (`changed_at`),
  CONSTRAINT `fk_history_application`
    FOREIGN KEY (`application_id`) REFERENCES `applications` (`id_number`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table: login_history
-- ============================================================
CREATE TABLE `login_history` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `user_id`    int(11)      DEFAULT NULL,
  `username`   varchar(255) NOT NULL,
  `login_time` timestamp    NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45)  NOT NULL,
  `user_agent` text         NOT NULL,
  `status`     enum('success','failure') NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_user` (`user_id`),
  CONSTRAINT `login_history_ibfk_1`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table: notifications
-- ============================================================
CREATE TABLE `notifications` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `message`    text         NOT NULL,
  `type`       varchar(50)  NOT NULL,
  `created_at` timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table: remember_tokens
-- ============================================================
CREATE TABLE `remember_tokens` (
  `id`             int(11)     NOT NULL AUTO_INCREMENT,
  `user_id`        int(11)     NOT NULL,
  `selector`       varchar(12) NOT NULL,
  `validator_hash` varchar(64) NOT NULL,
  `expires`        datetime    NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `selector` (`selector`),
  KEY `user_id`    (`user_id`),
  CONSTRAINT `remember_tokens_ibfk_1`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table: settings (per-user preferences)
-- ============================================================
CREATE TABLE `settings` (
  `id`            int(11)     NOT NULL AUTO_INCREMENT,
  `user_id`       int(11)     NOT NULL,
  `theme`         varchar(50) NOT NULL DEFAULT 'light',
  `language`      varchar(50) NOT NULL DEFAULT 'en',
  `notifications` varchar(50) NOT NULL DEFAULT 'all',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `settings_ibfk_1`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table: system_settings (single-row global config)
-- ============================================================
CREATE TABLE `system_settings` (
  `id`                 int(11)     NOT NULL DEFAULT 1,
  `session_timeout`    int(11)     NOT NULL DEFAULT 30,
  `max_login_attempts` int(11)     NOT NULL DEFAULT 5,
  `backup_frequency`   varchar(50) NOT NULL DEFAULT 'weekly',
  `auto_backup`        tinyint(1)  NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed default system settings
INSERT INTO `system_settings` (`id`, `session_timeout`, `max_login_attempts`, `backup_frequency`, `auto_backup`)
VALUES (1, 30, 5, 'weekly', 1)
ON DUPLICATE KEY UPDATE `id` = `id`;
