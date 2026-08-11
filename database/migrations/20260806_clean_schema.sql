-- SENIORLINK database cleanup and integrity migration
-- Safe to run more than once on MariaDB/MySQL.

USE `capstone1`;

-- Expired login sessions are no longer useful and contain no application data.
DELETE FROM `remember_tokens` WHERE `expires` < NOW();

-- id_number is already the applications primary key, so the second unique
-- index stores the same values without providing additional protection.
SET @sql = (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'applications'
              AND index_name = 'uk_id_number'
        ),
        'ALTER TABLE `applications` DROP INDEX `uk_id_number`',
        'SELECT 1'
    )
);
PREPARE cleanup_stmt FROM @sql;
EXECUTE cleanup_stmt;
DEALLOCATE PREPARE cleanup_stmt;

-- Each user has one settings record. The application already treats this as
-- one-to-one, so enforce that assumption at database level.
SET @sql = (
    SELECT IF(
        NOT EXISTS(
            SELECT 1 FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'settings'
              AND index_name = 'uq_settings_user'
        ),
        'ALTER TABLE `settings` ADD UNIQUE KEY `uq_settings_user` (`user_id`)',
        'SELECT 1'
    )
);
PREPARE cleanup_stmt FROM @sql;
EXECUTE cleanup_stmt;
DEALLOCATE PREPARE cleanup_stmt;

-- Documents cannot exist without their parent application. Existing orphan
-- checks must be clean before this relationship is installed.
SET @sql = (
    SELECT IF(
        NOT EXISTS(
            SELECT 1 FROM information_schema.referential_constraints
            WHERE constraint_schema = DATABASE()
              AND table_name = 'application_documents'
              AND constraint_name = 'fk_documents_application'
        ),
        'ALTER TABLE `application_documents` ADD CONSTRAINT `fk_documents_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id_number`) ON DELETE CASCADE',
        'SELECT 1'
    )
);
PREPARE cleanup_stmt FROM @sql;
EXECUTE cleanup_stmt;
DEALLOCATE PREPARE cleanup_stmt;
