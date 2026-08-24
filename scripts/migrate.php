<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/db_connect.php';

if ($conn->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
    fwrite(STDERR, "Migrations currently support MariaDB/MySQL only.\n");
    exit(1);
}

$trackingTableSql = 'CREATE TABLE IF NOT EXISTS app_schema_migrations (
    migration VARCHAR(255) PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
$conn->exec($trackingTableSql);
try {
    $migrationRows = $conn->query('SELECT migration FROM app_schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Recover only the disposable tracking table after an interrupted first creation.
    if ((string)$e->getCode() !== '42S02') throw $e;
    $conn->exec('DROP TABLE IF EXISTS app_schema_migrations');
    $conn->exec($trackingTableSql);
    $migrationRows = [];
}
$applied = array_fill_keys($migrationRows, true);
$files = glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: [];
sort($files, SORT_STRING);

foreach ($files as $file) {
    $name = basename($file);
    if (isset($applied[$name])) {
        fwrite(STDOUT, "SKIP  {$name}\n");
        continue;
    }
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException("Unable to read migration {$name}");
    }
    fwrite(STDOUT, "APPLY {$name}\n");
    try {
        $conn->exec($sql);
        $record = $conn->prepare('INSERT INTO app_schema_migrations (migration) VALUES (?)');
        $record->execute([$name]);
    } catch (Throwable $e) {
        fwrite(STDERR, "FAILED {$name}: {$e->getMessage()}\n");
        exit(1);
    }
}

fwrite(STDOUT, "Database is up to date.\n");
