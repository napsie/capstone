<?php

function systemLogoFilename(PDO $conn): string {
    try {
        $value = $conn->query("SELECT system_logo_filename FROM system_settings WHERE id = 1 LIMIT 1")->fetchColumn();
        $filename = basename(trim((string)$value));
        if ($filename !== '' && is_file(dirname(__DIR__) . '/images/system_logos/' . $filename)) return $filename;
    } catch (Throwable $ignored) {
        // The migration may not have run yet; retain the established logo.
    }
    return '';
}

function systemLogoPath(PDO $conn): string {
    $filename = systemLogoFilename($conn);
    return $filename !== ''
        ? dirname(__DIR__) . '/images/system_logos/' . $filename
        : dirname(__DIR__) . '/images/logo.jpg';
}

function systemLogoUrl(PDO $conn, string $prefix = '..'): string {
    return rtrim($prefix, '/') . '/api/system_logo.php';
}
