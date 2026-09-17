-- Keep both sides of the same government ID on the application record.
ALTER TABLE `applications`
    ADD COLUMN IF NOT EXISTS `government_id_front` VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `government_id_back` VARCHAR(255) DEFAULT NULL;
