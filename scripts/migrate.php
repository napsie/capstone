<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/db_connect.php';
require_once dirname(__DIR__) . '/includes/sql_script_runner.php';

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
$isMariaDb = stripos((string)$conn->query('SELECT VERSION()')->fetchColumn(), 'mariadb') !== false;

/** MySQL lacks MariaDB's ADD ... IF NOT EXISTS syntax. */
function applyMigrationSql(PDO $conn, string $sql, bool $isMariaDb): void {
    if ($isMariaDb || stripos($sql, 'ADD COLUMN IF NOT EXISTS') === false
        && stripos($sql, 'ADD INDEX IF NOT EXISTS') === false
        && stripos($sql, 'ADD UNIQUE INDEX IF NOT EXISTS') === false) {
        executeSqlScript($conn, $sql);
        return;
    }
    preg_match_all('/^ALTER TABLE\s+(`?[A-Za-z0-9_]+`?)\s+([^;]+);/mi', $sql, $matches, PREG_OFFSET_CAPTURE);
    $cursor = 0;
    foreach ($matches[0] as $i => [$statement, $start]) {
        if (stripos($statement, 'IF NOT EXISTS') === false) continue;
        $prefix = substr($sql, $cursor, $start - $cursor);
        if (trim($prefix) !== '') executeSqlScript($conn, $prefix);
        $table = trim($matches[1][$i][0], '`');
        $clauses = splitAlterClauses($matches[2][$i][0]);
        foreach ($clauses as $clause) {
            $clause = trim($clause);
            if (!preg_match('/^ADD\s+(?:(COLUMN)|(UNIQUE\s+INDEX|INDEX))\s+IF NOT EXISTS\s+`?([A-Za-z0-9_]+)`?/i', $clause, $parts)) {
                throw new RuntimeException("Unsupported conditional ALTER TABLE clause in {$table}: {$clause}");
            }
            $column = $parts[1] !== '';
            $name = $parts[3];
            $catalog = $column ? 'information_schema.COLUMNS' : 'information_schema.STATISTICS';
            $nameField = $column ? 'COLUMN_NAME' : 'INDEX_NAME';
            $check = $conn->prepare("SELECT 1 FROM {$catalog} WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND {$nameField} = ? LIMIT 1");
            $check->execute([$table, $name]);
            if ($check->fetchColumn()) continue;
            $clause = preg_replace('/\bIF NOT EXISTS\s+/i', '', $clause, 1);
            $clause = preg_replace('/\bPERSISTENT\b/i', 'STORED', (string)$clause);
            $conn->exec("ALTER TABLE `{$table}` {$clause}");
        }
        $cursor = $start + strlen($statement);
    }
    $suffix = substr($sql, $cursor);
    if (trim($suffix) !== '') executeSqlScript($conn, $suffix);
}

function splitAlterClauses(string $source): array {
    $clauses = [];
    $start = 0;
    $depth = 0;
    $quote = '';
    $length = strlen($source);
    for ($i = 0; $i < $length; $i++) {
        $char = $source[$i];
        if ($quote !== '') {
            if ($char === $quote) {
                if ($i + 1 < $length && $source[$i + 1] === $quote) { $i++; continue; }
                $quote = '';
            } elseif ($char === '\\') { $i++; }
            continue;
        }
        if ($char === "'" || $char === '"' || $char === '`') { $quote = $char; continue; }
        if ($char === '(') { $depth++; continue; }
        if ($char === ')') { $depth--; continue; }
        if ($char === ',' && $depth === 0) {
            $clauses[] = substr($source, $start, $i - $start);
            $start = $i + 1;
        }
    }
    $clauses[] = substr($source, $start);
    return $clauses;
}

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
    // Older migrations name the local XAMPP database explicitly. Always apply
    // them to the database chosen by the connection string instead.
    $sql = preg_replace('/^\s*USE `capstone1`;\s*$/mi', '', $sql);
    fwrite(STDOUT, "APPLY {$name}\n");
    try {
        applyMigrationSql($conn, (string)$sql, $isMariaDb);
        $record = $conn->prepare('INSERT INTO app_schema_migrations (migration) VALUES (?)');
        $record->execute([$name]);
    } catch (Throwable $e) {
        fwrite(STDERR, "FAILED {$name}: {$e->getMessage()}\n");
        exit(1);
    }
}

fwrite(STDOUT, "Database is up to date.\n");
