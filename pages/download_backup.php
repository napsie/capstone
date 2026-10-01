<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/request_security.php';
require_once '../includes/audit_logger.php';
require_once '../includes/system_backup.php';

if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit; }
if (!in_array($_SESSION['role'] ?? '', ['department_admin', 'super_admin'], true)) {
    http_response_code(403); exit('Only a Department Administrator can create a system backup.');
}

function backupFailure(string $message): never
{
    $_SESSION['backup_notice'] = ['success' => false, 'message' => $message];
    header('Location: system_settings.php#backup');
    exit;
}

requireSameOriginMutation();
$savedToken = (string)($_SESSION['backup_csrf_token'] ?? '');
$sentToken = (string)($_POST['csrf_token'] ?? '');
if ($savedToken === '' || $sentToken === '' || !hash_equals($savedToken, $sentToken)) backupFailure('Your backup request expired. Please try again.');

$accountPassword = (string)($_POST['account_password'] ?? '');
$archivePassword = (string)($_POST['archive_password'] ?? '');
if (strlen($archivePassword) < 12) backupFailure('Use at least 12 characters for the backup file password.');
if (!hash_equals($archivePassword, (string)($_POST['archive_password_confirmation'] ?? ''))) backupFailure('The backup file passwords do not match.');

$passwordStatement = $conn->prepare('SELECT password FROM users WHERE id = ? AND COALESCE(is_archived, 0) = 0 LIMIT 1');
$passwordStatement->execute([(int)$_SESSION['user_id']]);
$storedPassword = $passwordStatement->fetchColumn();
if (!is_string($storedPassword) || !password_verify($accountPassword, $storedPassword)) {
    logAudit($conn, 'FAILED_BACKUP_AUTHORIZATION', 'A system backup request was rejected because the account password was incorrect.');
    backupFailure('Your current account password is incorrect.');
}

@set_time_limit(300);
$backup = null;
try {
    $backup = createSystemBackup($conn, $archivePassword);
    $warningText = $backup['warnings'] ? ' Some optional folders were not present.' : '';
    logAudit($conn, 'CREATE_SYSTEM_BACKUP', "Created encrypted portable backup {$backup['filename']} containing {$backup['table_count']} database tables, {$backup['row_count']} rows, and {$backup['file_count']} uploaded files.{$warningText}");
    $_SESSION['backup_csrf_token'] = bin2hex(random_bytes(32));
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $backup['filename'] . '"');
    header('Content-Length: ' . $backup['size']);
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    $stream = fopen($backup['path'], 'rb');
    if ($stream === false) throw new RuntimeException('The completed backup could not be opened.');
    fpassthru($stream);
    fclose($stream);
} catch (Throwable $error) {
    error_log('System backup failed: ' . $error->getMessage());
    if (!headers_sent()) backupFailure('The backup could not be created. No system data was changed.');
} finally {
    if (is_array($backup) && isset($backup['directory'])) removeBackupWorkingDirectory((string)$backup['directory']);
}
exit;
