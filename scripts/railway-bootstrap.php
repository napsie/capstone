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
if ($conn->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
    fwrite(STDERR, "Railway bootstrap requires MySQL.\n");
    exit(1);
}

$tables = $conn->query(
    "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
)->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('users', $tables, true)) {
    if ($tables !== []) {
        fwrite(STDERR, "Database has tables but no users table; refusing to replace existing data.\n");
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
    $conn->exec((string)$sql);
    fwrite(STDOUT, "Initial schema created.\n");
}

require __DIR__ . '/migrate.php';
