ALTER TABLE applications
    ADD COLUMN IF NOT EXISTS deceased_landbank_card VARCHAR(255) DEFAULT NULL AFTER comelec_cert;
