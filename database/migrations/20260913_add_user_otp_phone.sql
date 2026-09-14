ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `phone` VARCHAR(20) DEFAULT NULL AFTER `email`;

ALTER TABLE `users`
    ADD UNIQUE INDEX IF NOT EXISTS `uq_users_phone` (`phone`);
