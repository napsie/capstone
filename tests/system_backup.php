<?php

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/system_backup.php';
require_once __DIR__ . '/../includes/sql_script_runner.php';

$password = 'TestBackup!2026';
$backup = null;
$sourceDatabase = (string)$conn->query('SELECT DATABASE()')->fetchColumn();
$restoreDatabase = 'seniorlink_backup_test_' . bin2hex(random_bytes(4));
$sourceLargestDocumentHash = (string)($conn->query('SELECT SHA2(document_data, 256) FROM application_documents ORDER BY OCTET_LENGTH(document_data) DESC LIMIT 1')->fetchColumn() ?: '');

// Remove only abandoned databases created by an earlier interrupted test.
$abandoned = $conn->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME LIKE 'seniorlink\\_backup\\_test\\_%'")->fetchAll(PDO::FETCH_COLUMN);
foreach ($abandoned as $database) $conn->exec('DROP DATABASE ' . backupIdentifier((string)$database));

try {
    $backup = createSystemBackup($conn, $password);
    if (!is_file($backup['path']) || $backup['size'] <= 0) throw new RuntimeException('Backup file was not created.');

    $zip = new ZipArchive();
    if ($zip->open($backup['path']) !== true) throw new RuntimeException('Backup ZIP could not be opened.');
    $zip->setPassword('IncorrectPassword');
    if ($zip->getFromName('manifest.json') !== false) throw new RuntimeException('Encrypted manifest opened with an incorrect password.');
    $zip->setPassword($password);
    $manifestJson = $zip->getFromName('manifest.json');
    $sql = $zip->getFromName('database/database.sql');
    $zip->close();

    if (!is_string($manifestJson) || !is_string($sql)) throw new RuntimeException('Encrypted backup contents could not be read.');
    $manifest = json_decode($manifestJson, true, 512, JSON_THROW_ON_ERROR);
    if (($manifest['format'] ?? '') !== 'SENIORLINK portable backup') throw new RuntimeException('Backup manifest is invalid.');
    if (!str_contains($sql, 'SET FOREIGN_KEY_CHECKS=0;') || !str_contains($sql, 'CREATE TABLE')) {
        throw new RuntimeException('Database export is incomplete.');
    }
    if (($backup['table_count'] ?? 0) < 1) throw new RuntimeException('No database tables were exported.');

    $conn->exec('CREATE DATABASE ' . backupIdentifier($restoreDatabase) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
    $conn->exec('USE ' . backupIdentifier($restoreDatabase));
    executeSqlScript($conn, $sql);
    $restoredTables = (int)$conn->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'")->fetchColumn();
    if ($restoredTables !== (int)$backup['table_count']) throw new RuntimeException('Restored table count does not match the backup.');
    $restoredRows = 0;
    $restoredTableNames = $conn->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($restoredTableNames as $table) $restoredRows += (int)$conn->query('SELECT COUNT(*) FROM ' . backupIdentifier((string)$table))->fetchColumn();
    if ($restoredRows !== (int)$backup['row_count']) throw new RuntimeException('Restored row count does not match the backup.');
    $restoredLargestDocumentHash = (string)($conn->query('SELECT SHA2(document_data, 256) FROM application_documents ORDER BY OCTET_LENGTH(document_data) DESC LIMIT 1')->fetchColumn() ?: '');
    if (!hash_equals($sourceLargestDocumentHash, $restoredLargestDocumentHash)) throw new RuntimeException('Restored document data does not match the source.');

    echo "System backup and restore test passed: {$backup['table_count']} tables, {$backup['row_count']} rows, {$backup['file_count']} files.\n";
} finally {
    try {
        $conn->exec('USE ' . backupIdentifier($sourceDatabase));
        $conn->exec('DROP DATABASE IF EXISTS ' . backupIdentifier($restoreDatabase));
    } catch (Throwable $cleanupError) {
        fwrite(STDERR, 'Temporary restore cleanup failed: ' . $cleanupError->getMessage() . "\n");
    }
    if (is_array($backup) && isset($backup['directory'])) removeBackupWorkingDirectory($backup['directory']);
}
