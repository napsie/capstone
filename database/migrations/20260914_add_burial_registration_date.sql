ALTER TABLE `applications`
    ADD COLUMN IF NOT EXISTS `death_registration_date` DATE DEFAULT NULL AFTER `deceased_birth_date`;
