<?php
require_once '../includes/db_connect.php';
require_once '../includes/system_branding.php';

$path = systemLogoPath($conn);
$mime = 'image/jpeg';
if (is_file($path)) {
    $detected = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    if (in_array($detected, ['image/jpeg', 'image/png', 'image/gif'], true)) $mime = $detected;
}
header('Content-Type: ' . $mime);
$requestedVersion = trim((string)($_GET['v'] ?? ''));
if ($requestedVersion !== '') {
    // The URL changes with the logo file timestamp, so this exact image can be
    // reused across page navigation without blinking or becoming stale.
    header('Cache-Control: public, max-age=31536000, immutable');
} else {
    header('Cache-Control: no-cache, must-revalidate');
}
header('X-Content-Type-Options: nosniff');
readfile($path);
