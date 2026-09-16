-- Login generates a 16-character selector; the original 12-character column
-- rejected remembered logins on MySQL in strict mode.
ALTER TABLE `remember_tokens`
    MODIFY COLUMN `selector` VARCHAR(32) NOT NULL;
