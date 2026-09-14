<?php

/** Verify the account-creation authorization password without storing plaintext. */
function verifyMasterPassword(string $password): bool
{
    $configuredHash = trim((string)getenv('MASTER_PASSWORD_HASH'));
    $localFallbackHash = '$2y$10$fS1deU2toLKEDzruwbr2d.6j2OZS2/su7qidJjvDBAZojwYzeA5LS';
    $hash = $configuredHash !== '' ? $configuredHash : $localFallbackHash;

    return password_verify($password, $hash);
}
