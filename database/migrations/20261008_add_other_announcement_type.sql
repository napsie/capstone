ALTER TABLE announcements
    MODIFY COLUMN category ENUM('announcement', 'benefit', 'other') NOT NULL DEFAULT 'announcement';
