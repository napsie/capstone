<?php

/**
 * Validates a given password against a set of security standards.
 *
 * Requirements:
 * - Minimum 8 characters in length.
 * - At least one uppercase letter.
 * - At least one lowercase letter.
 * - At least one digit.
 * - At least one special character from the set: !@#$%^&*()_+={}[\]:;<>,.?/~
 *
 * @param string $password The password to validate.
 * @return array An associative array with 'valid' (boolean) and 'message' (string) keys.
 */
function validatePassword(string $password): array
{
    $min_length = 8;
    $errors = [];

    // Check minimum length
    if (strlen($password) < $min_length) {
        $errors[] = "Password must be at least {$min_length} characters long.";
    }

    // Check for at least one uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter.";
    }

    // Check for at least one lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter.";
    }

    // Check for at least one digit
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one digit.";
    }

    // Check for at least one special character
    // Using a common set of special characters. Escaped characters for regex: \!\"\#\$\%\&\'\(\)\*\+\,\-\.\/\:\;\<\=\>\?\@\[\\\]\^\_\`\{\|\}\~
    // A simpler set that covers most common requirements: !@#$%^&*()_+={}[]:;<>,.?/~
    if (!preg_match('/[!@#$%^&*()_+=\[\]{};:<>,.?\/~-]/', $password)) {
        $errors[] = "Password must contain at least one special character (e.g., !@#$%^&*()_+={}[];:<>,.?/~).";
    }

    if (empty($errors)) {
        return ['valid' => true, 'message' => 'Password is valid.'];
    } else {
        return ['valid' => false, 'message' => implode("\n", $errors)];
    }
}
