ALTER TABLE announcements
    ADD COLUMN IF NOT EXISTS custom_type VARCHAR(60) NULL AFTER category;
