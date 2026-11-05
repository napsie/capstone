CREATE TABLE IF NOT EXISTS senior_classifications (
    profile_id VARCHAR(255) NOT NULL PRIMARY KEY,
    activity_status VARCHAR(20) NOT NULL DEFAULT 'Unclassified',
    residence_status VARCHAR(20) NOT NULL DEFAULT 'Unclassified',
    effective_date DATE NOT NULL,
    reason VARCHAR(1000) NOT NULL,
    updated_by INT NOT NULL,
    updated_by_name VARCHAR(100) NOT NULL,
    version INT NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_senior_classification_status (activity_status, residence_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS senior_classification_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    profile_id VARCHAR(255) NOT NULL,
    previous_activity_status VARCHAR(20) NOT NULL,
    activity_status VARCHAR(20) NOT NULL,
    previous_residence_status VARCHAR(20) NOT NULL,
    residence_status VARCHAR(20) NOT NULL,
    effective_date DATE NOT NULL,
    reason VARCHAR(1000) NOT NULL,
    changed_by INT NOT NULL,
    changed_by_name VARCHAR(100) NOT NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_senior_classification_history (profile_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
