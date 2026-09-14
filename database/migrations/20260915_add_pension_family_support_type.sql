ALTER TABLE `applications`
    ADD COLUMN IF NOT EXISTS `family_support_type` VARCHAR(255) DEFAULT NULL AFTER `family_support`;
