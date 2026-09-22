ALTER TABLE home_visit_personnel
    ADD COLUMN IF NOT EXISTS is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active,
    ADD COLUMN IF NOT EXISTS archived_at TIMESTAMP NULL DEFAULT NULL AFTER is_archived,
    ADD COLUMN IF NOT EXISTS archived_by VARCHAR(100) DEFAULT NULL AFTER archived_at,
    ADD INDEX IF NOT EXISTS idx_visit_personnel_archived (is_archived);
