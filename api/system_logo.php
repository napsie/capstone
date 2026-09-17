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
// A newly uploaded logo must appear on every page as soon as it is saved.
header('Cache-Control: no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
readfile($path);
