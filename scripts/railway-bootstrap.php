<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (trim((string)getenv('DATABASE_URL')) === '') {
    fwrite(STDERR, "DATABASE_URL is required for Railway.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/includes/db_connect.php';
require_once dirname(__DIR__) . '/includes/sql_script_runner.php';
if ($conn->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
    fwrite(STDERR, "Railway bootstrap requires MySQL.\n");
    exit(1);
}

$tables = $conn->query(
    "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
)->fetchAll(PDO::FETCH_COLUMN);
$baseTables = ['users', 'applications', 'application_documents', 'application_history',
    'audit_trail', 'login_history', 'notifications', 'sms_notifications',
    'home_visit_personnel', 'remember_tokens', 'settings', 'system_settings'];

if ($tables !== [] && count(array_diff($tables, ['users', 'app_schema_migrations'])) === 0) {
    // Recover an interrupted first import only when no user records exist.
    $usersCount = in_array('users', $tables, true)
        ? (int)$conn->query('SELECT COUNT(*) FROM users')->fetchColumn() : 0;
    if ($usersCount === 0) {
        $conn->exec('DROP TABLE IF EXISTS app_schema_migrations');
        $tables = in_array('users', $tables, true) ? ['users'] : [];
    } else {
        fwrite(STDERR, "Incomplete schema contains user records; refusing automatic recovery.\n");
        exit(1);
    }
}

if (!in_array('users', $tables, true) || count($tables) === 1 && $tables[0] === 'users') {
    if (count(array_diff($tables, ['users'])) !== 0) {
        fwrite(STDERR, "Database has tables but no users table; refusing to replace existing data.\n");
        exit(1);
    }
    if (in_array('users', $tables, true)
        && (int)$conn->query('SELECT COUNT(*) FROM users')->fetchColumn() !== 0) {
        fwrite(STDERR, "Existing users prevent automatic schema recovery.\n");
        exit(1);
    }
    $sql = file_get_contents(dirname(__DIR__) . '/capstone1_schema.sql');
    if ($sql === false) {
        throw new RuntimeException('The initial schema file is missing.');
    }
    // The checked-in schema names the local XAMPP database. Keep every statement
    // in Railway's database selected by DATABASE_URL instead.
    $sql = preg_replace('/CREATE DATABASE IF NOT EXISTS `capstone1`[^;]*;/i', '', $sql, 1);
    $sql = preg_replace('/^\s*USE `capstone1`;\s*$/mi', '', (string)$sql);
    if (stripos((string)$conn->query('SELECT VERSION()')->fetchColumn(), 'mariadb') === false) {
        $sql = preg_replace('/\bPERSISTENT\b/i', 'STORED', (string)$sql);
    }
    executeSqlScript($conn, (string)$sql);
    fwrite(STDOUT, "Initial schema created.\n");
} elseif (array_diff($baseTables, $tables) !== []) {
    fwrite(STDERR, "Initial schema is incomplete; refusing to change existing tables.\n");
    exit(1);
}

require __DIR__ . '/migrate.php';
