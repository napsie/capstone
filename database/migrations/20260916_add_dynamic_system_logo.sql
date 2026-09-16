ALTER TABLE system_settings
    ADD COLUMN IF NOT EXISTS system_logo_filename VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS system_logo_mime VARCHAR(50) NULL;
