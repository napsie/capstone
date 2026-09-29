<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/request_security.php';
require_once '../includes/audit_logger.php';
require_once '../includes/applicant_backup.php';

requireSameOriginMutation();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['department_admin', 'super_admin'], true)) {
    http_response_code(403);
    exit('Only a Department Administrator can download applicant backups.');
}

$csrfToken = (string)($_POST['csrf_token'] ?? '');
if ($csrfToken === '' || empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    exit('The backup request expired. Refresh System Settings and try again.');
}

$backupActor = ['id' => $_SESSION['user_id'], 'username' => $_SESSION['username'] ?? 'Department Admin'];
// The ZIP can take a few seconds on larger installations. Release the PHP
// session lock so the administrator can continue using other browser tabs.
session_write_close();

try {
    $temporaryZip = tempnam(sys_get_temp_dir(), 'seniorlink-backup-');
    if ($temporaryZip === false) throw new RuntimeException('Unable to prepare the backup download.');
    $manifest = createApplicantBackupZip($conn, $temporaryZip, $backupActor);
    $counts = $manifest['counts'];
    logAudit($conn, 'BACKUP_APPLICANT_RECORDS', "Downloaded full applicant ZIP backup ({$counts['applications']} applications, {$counts['application_history']} history entries, {$counts['document_files']} document files).");

    $filename = 'seniorlink-full-applicant-backup-' . date('Y-m-d-His') . '.zip';
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($temporaryZip));
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('X-Content-Type-Options: nosniff');
    readfile($temporaryZip);
    @unlink($temporaryZip);
} catch (Throwable $e) {
    if (isset($temporaryZip)) @unlink($temporaryZip);
    error_log('Applicant backup failed: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    exit('The applicant backup could not be created. Please try again.');
}
