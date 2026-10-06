CREATE TABLE IF NOT EXISTS announcements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(140) NOT NULL,
    message TEXT NOT NULL,
    category ENUM('announcement', 'benefit') NOT NULL DEFAULT 'announcement',
    audience ENUM('all', 'public', 'staff') NOT NULL DEFAULT 'all',
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_announcements_active_window (is_active, starts_at, ends_at),
    KEY idx_announcements_audience (audience),
    CONSTRAINT fk_announcements_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
