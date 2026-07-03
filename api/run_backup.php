<?php
session_start();
header('Content-Type: application/json');

require_once '../includes/db_connect.php'; // For DB credentials

$response = ['success' => false, 'message' => ''];

// Ensure only authorized users can trigger backup
// Adjust role check as per your application's authorization logic
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'department_admin' && $_SESSION['role'] !== 'super_admin')) {
    $response['message'] = 'Unauthorized access.';
    echo json_encode($response);
    exit;
}

$backupDir = dirname(__DIR__) . '/_backups/';
if (!is_dir($backupDir)) {
    if (!mkdir($backupDir, 0777, true)) {
        $response['message'] = 'Failed to create backup directory: ' . $backupDir;
        echo json_encode($response);
        exit;
    }
}

// Database credentials from db_connect.php
$dbHost = "localhost"; // Assuming $servername is localhost from db_connect.php
$dbUser = "root";      // Assuming $username is root from db_connect.php
$dbPass = "";          // Assuming $password is empty from db_connect.php
$dbName = "carelink_db"; // Assuming $dbname is carelink_db from db_connect.php

$timestamp = date('Ymd_His');
$backupFile = $backupDir . $dbName . '_' . $timestamp . '.sql';

// Construct the mysqldump command
// NOTE: For Windows, ensure mysqldump is in your system's PATH or provide the full path to mysqldump.exe
// Example for XAMPP: "C:\\xampp\\mysql\\bin\\mysqldump.exe"
$mysqldumpPath = '"D:\\xampp1\\mysql\\bin\\mysqldump.exe"'; // Explicitly set path for XAMPP on D drive


$command = "{$mysqldumpPath} -h {$dbHost} -u {$dbUser} " . (!empty($dbPass) ? "-p{$dbPass} " : "") . "{$dbName} > {$backupFile} 2>&1";

$output = [];
$returnVar = 0;

exec($command, $output, $returnVar);

if ($returnVar === 0) {
    $response['success'] = true;
    $response['message'] = 'Database backup successful. File: ' . basename($backupFile);
} else {
    $response['message'] = 'Database backup failed. Error: ' . implode("\n", $output);
    // Log the full command for debugging, but be careful not to expose passwords in logs in production
    error_log("Backup command failed: " . $command);
    error_log("Backup output: " . implode("\n", $output));
}

echo json_encode($response);
exit;
?>
