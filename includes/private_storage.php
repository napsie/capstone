<?php
/** Private applicant storage. The configured directory must be outside the web root. */
function privateUploadDirectory(): string
{
    $configured = getenv('SENIORLINK_PRIVATE_STORAGE');
    $base = $configured !== false && trim($configured) !== ''
        ? trim($configured)
        : dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'seniorlink_private';
    $root = realpath($base);
    $webRoot = realpath(dirname(__DIR__));
    $documentRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($root === false || !is_dir($root)) {
        throw new RuntimeException('Private storage is not configured.');
    }
    foreach ([$webRoot, $documentRoot] as $publicRoot) {
        if ($publicRoot === false) continue;
        $rootKey = strtolower(rtrim(str_replace('\\', '/', $root), '/'));
        $publicKey = strtolower(rtrim(str_replace('\\', '/', $publicRoot), '/'));
        if ($rootKey === $publicKey || str_starts_with($rootKey, $publicKey . '/')) {
            throw new RuntimeException('Private storage must be outside the public web directory.');
        }
    }
    $uploads = $root . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($uploads)) {
        throw new RuntimeException('Private upload directory is missing.');
    }
    return $uploads;
}

function privateUploadPath(string $storedName): ?string
{
    if ($storedName === '' || basename($storedName) !== $storedName || preg_match('/[\\\\\/\x00]/', $storedName)) {
        return null;
    }
    return privateUploadDirectory() . DIRECTORY_SEPARATOR . $storedName;
}

function privateExistingUploadPath(string $storedName): ?string
{
    $candidate = privateUploadPath($storedName);
    if ($candidate === null) return null;
    $resolved = realpath($candidate);
    $directory = realpath(privateUploadDirectory());
    if ($resolved === false || $directory === false) return null;
    $resolvedKey = strtolower(str_replace('\\', '/', $resolved));
    $directoryKey = strtolower(rtrim(str_replace('\\', '/', $directory), '/')) . '/';
    return str_starts_with($resolvedKey, $directoryKey) && is_file($resolved) ? $resolved : null;
}
