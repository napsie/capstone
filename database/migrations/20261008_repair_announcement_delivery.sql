ALTER TABLE announcements
    MODIFY COLUMN category ENUM('announcement', 'benefit', 'other') NOT NULL DEFAULT 'announcement',
    MODIFY COLUMN audience ENUM('all', 'public', 'staff', 'barangay') NOT NULL DEFAULT 'all';

ALTER TABLE announcements
    ADD COLUMN IF NOT EXISTS target_barangay VARCHAR(100) NULL AFTER audience;

ALTER TABLE announcements
    ADD INDEX IF NOT EXISTS idx_announcements_target_barangay (target_barangay);
