ALTER TABLE `applications`
    ADD COLUMN IF NOT EXISTS `expected_release_date` DATE DEFAULT NULL;
