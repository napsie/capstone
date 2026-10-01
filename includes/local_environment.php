<?php
declare(strict_types=1);

/** Load local XAMPP secrets without overriding real server environment variables. */
function loadLocalEnvironment(string $path): void
{
    if (!is_file($path) || !is_readable($path)) return;

    $values = parse_ini_file($path, false, INI_SCANNER_RAW);
    if (!is_array($values)) {
        throw new RuntimeException('The local environment file could not be parsed.');
    }

    foreach ($values as $name => $value) {
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', (string)$name)) continue;
        if (getenv((string)$name) !== false) continue;
        putenv((string)$name . '=' . (string)$value);
        $_ENV[(string)$name] = (string)$value;
    }
}
