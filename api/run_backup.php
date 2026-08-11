<?php
session_start();
header('Content-Type: application/json');

require_once '../includes/db_connect.php';

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['department_admin', 'super_admin'], true)) {
    $response['message'] = 'Unauthorized access.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

if ($conn->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
    $response['message'] = 'Manual backup is currently available only for the local MySQL deployment.';
    echo json_encode($response);
    exit;
}

$backupDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database_backups';
if (!is_dir($backupDir) && !mkdir($backupDir, 0750, true)) {
    $response['message'] = 'Unable to create the protected backup directory.';
    echo json_encode($response);
    exit;
}

$dumpExecutable = 'D:\\xampp1\\mysql\\bin\\mysqldump.exe';
if (!is_file($dumpExecutable)) {
    $response['message'] = 'The MySQL backup utility was not found.';
    echo json_encode($response);
    exit;
}

$timestamp = date('Ymd_His');
$backupFile = $backupDir . DIRECTORY_SEPARATOR . $dbname . '_' . $timestamp . '.sql';
$arguments = [
    escapeshellarg($dumpExecutable),
    '--host=' . escapeshellarg($servername),
    '--user=' . escapeshellarg($pdo_username),
    '--single-transaction',
    '--routines',
    '--events',
    '--result-file=' . escapeshellarg($backupFile),
];
if ($pdo_password !== '') {
    $arguments[] = '--password=' . escapeshellarg($pdo_password);
}
$arguments[] = escapeshellarg($dbname);

$output = [];
$returnCode = 0;
exec(implode(' ', $arguments), $output, $returnCode);

if ($returnCode === 0 && is_file($backupFile) && filesize($backupFile) > 0) {
    $response['success'] = true;
    $response['message'] = 'Database backup successful. File: ' . basename($backupFile);
} else {
    if (is_file($backupFile) && filesize($backupFile) === 0) {
        unlink($backupFile);
    }
    $response['message'] = 'Database backup failed. Please verify the local MySQL service and backup utility.';
    error_log('Database backup failed with exit code ' . $returnCode . ': ' . implode("\n", $output));
}

echo json_encode($response);
exit;
?>
