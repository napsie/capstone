<?php

/** Create a portable SENIORLINK backup without relying on mysqldump. */
function createSystemBackup(PDO $conn, string $archivePassword): array
{
    if (!class_exists(ZipArchive::class)) throw new RuntimeException('ZIP support is unavailable on this server.');
    $workDirectory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'seniorlink-backup-' . bin2hex(random_bytes(8));
    if (!mkdir($workDirectory, 0700, true) && !is_dir($workDirectory)) throw new RuntimeException('The temporary backup directory could not be created.');

    $createdAt = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    $stamp = $createdAt->format('Ymd-His');
    $sqlPath = $workDirectory . DIRECTORY_SEPARATOR . 'database.sql';
    $zipPath = $workDirectory . DIRECTORY_SEPARATOR . "seniorlink-backup-{$stamp}.zip";

    try {
        $database = exportDatabaseToSql($conn, $sqlPath);
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) throw new RuntimeException('The backup archive could not be created.');
        $entries = [];
        addEncryptedBackupFile($zip, $sqlPath, 'database/database.sql', $archivePassword, $entries);

        $warnings = [];
        $projectRoot = dirname(__DIR__);
        $sources = [
            [$projectRoot . '/images/profile_pictures', 'files/profile_pictures'],
            [$projectRoot . '/images/system_logos', 'files/system_logos'],
            [$projectRoot . '/uploads', 'files/public_uploads'],
        ];
        $privateStorage = getenv('SENIORLINK_PRIVATE_STORAGE');
        $sources[] = $privateStorage !== false && trim($privateStorage) !== ''
            ? [rtrim(trim($privateStorage), '/\\') . '/uploads', 'files/private_uploads']
            : [dirname($projectRoot, 2) . '/seniorlink_private/uploads', 'files/private_uploads'];

        foreach ($sources as [$source, $destination]) {
            $resolved = realpath($source);
            if ($resolved === false || !is_dir($resolved)) {
                $warnings[] = "Not present: {$destination}";
                continue;
            }
            addEncryptedBackupDirectory($zip, $resolved, $destination, $archivePassword, $entries);
        }

        $manifest = [
            'format' => 'SENIORLINK portable backup',
            'format_version' => 1,
            'created_at' => $createdAt->format(DateTimeInterface::ATOM),
            'database' => $database,
            'files' => ['count' => count($entries), 'uncompressed_bytes' => array_sum(array_column($entries, 'size')), 'entries' => $entries],
            'warnings' => $warnings,
            'restore_notice' => 'Validate this archive in a separate environment before replacing live data.',
        ];
        $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (!$zip->addFromString('manifest.json', $manifestJson)) throw new RuntimeException('The backup manifest could not be written.');
        encryptBackupEntry($zip, 'manifest.json', $archivePassword);
        if (!$zip->close()) throw new RuntimeException('The backup archive could not be finalized.');
        if (!is_file($zipPath) || filesize($zipPath) === 0) throw new RuntimeException('The generated backup archive is empty.');

        @unlink($sqlPath);
        return [
            'path' => $zipPath,
            'directory' => $workDirectory,
            'filename' => basename($zipPath),
            'size' => (int)filesize($zipPath),
            'table_count' => $database['table_count'],
            'row_count' => $database['row_count'],
            'file_count' => count($entries) - 1,
            'warnings' => $warnings,
        ];
    } catch (Throwable $error) {
        removeBackupWorkingDirectory($workDirectory);
        throw $error;
    }
}

function exportDatabaseToSql(PDO $conn, string $destination): array
{
    if ($conn->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') throw new RuntimeException('Portable backups currently require MySQL.');
    $handle = fopen($destination, 'wb');
    if ($handle === false) throw new RuntimeException('The database export file could not be opened.');
    $databaseName = (string)$conn->query('SELECT DATABASE()')->fetchColumn();
    $tableCount = 0;
    $rowCount = 0;

    try {
        fwrite($handle, "-- SENIORLINK portable database backup\n-- Created: " . date(DATE_ATOM) . "\n");
        fwrite($handle, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET UNIQUE_CHECKS=0;\n\n");
        $tables = $conn->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
        $conn->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $conn->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');

        foreach ($tables as $table) {
            $table = (string)$table;
            $quotedTable = backupIdentifier($table);
            $createRow = $conn->query("SHOW CREATE TABLE {$quotedTable}")->fetch(PDO::FETCH_NUM);
            if (!$createRow || !isset($createRow[1])) throw new RuntimeException("Unable to read the structure of table {$table}.");
            fwrite($handle, "-- Table: {$table}\nDROP TABLE IF EXISTS {$quotedTable};\n{$createRow[1]};\n\n");

            $columns = backupTableColumns($conn, $databaseName, $table);
            $insertable = array_values(array_filter($columns, static fn(array $column): bool => !$column['generated']));
            if ($insertable === []) { $tableCount++; continue; }
            $columnSql = implode(', ', array_map(static fn(array $column): string => backupIdentifier($column['name']), $insertable));
            $statement = $conn->query("SELECT {$columnSql} FROM {$quotedTable}");
            while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                $values = [];
                $largeBinaryColumns = [];
                foreach ($insertable as $column) {
                    $value = $row[$column['name']] ?? null;
                    if ($column['binary'] && is_string($value) && strlen($value) > 131072) {
                        $values[] = "X''";
                        $largeBinaryColumns[] = [$column, $value];
                    } else {
                        $values[] = backupSqlValue($conn, $value, $column['binary']);
                    }
                }
                fwrite($handle, "INSERT INTO {$quotedTable} ({$columnSql}) VALUES (" . implode(', ', $values) . ");\n");
                if ($largeBinaryColumns !== []) {
                    $primaryColumns = array_values(array_filter($insertable, static fn(array $column): bool => $column['primary']));
                    if ($primaryColumns === []) throw new RuntimeException("Table {$table} contains a large binary value but has no primary key for safe chunking.");
                    $where = implode(' AND ', array_map(
                        static fn(array $column): string => backupIdentifier($column['name']) . ' = ' . backupSqlValue($conn, $row[$column['name']] ?? null, $column['binary']),
                        $primaryColumns
                    ));
                    foreach ($largeBinaryColumns as [$column, $binaryValue]) {
                        foreach (str_split($binaryValue, 131072) as $chunk) {
                            fwrite($handle, 'UPDATE ' . $quotedTable . ' SET ' . backupIdentifier($column['name'])
                                . ' = CONCAT(' . backupIdentifier($column['name']) . ', 0x' . bin2hex($chunk) . ") WHERE {$where};\n");
                        }
                    }
                }
                $rowCount++;
            }
            $statement->closeCursor();
            fwrite($handle, "\n");
            $tableCount++;
        }
        $conn->commit();
        fwrite($handle, "SET UNIQUE_CHECKS=1;\nSET FOREIGN_KEY_CHECKS=1;\n");
    } catch (Throwable $error) {
        if ($conn->inTransaction()) $conn->rollBack();
        throw $error;
    } finally {
        fclose($handle);
    }

    return ['name' => $databaseName, 'engine' => 'mysql', 'table_count' => $tableCount, 'row_count' => $rowCount,
        'sql_bytes' => (int)filesize($destination), 'sql_sha256' => hash_file('sha256', $destination)];
}

function backupTableColumns(PDO $conn, string $database, string $table): array
{
    $statement = $conn->prepare('SELECT COLUMN_NAME, DATA_TYPE, EXTRA, COLUMN_KEY FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION');
    $statement->execute([$database, $table]);
    $binaryTypes = ['binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob', 'bit'];
    $columns = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $columns[] = ['name' => (string)$column['COLUMN_NAME'],
            'binary' => in_array(strtolower((string)$column['DATA_TYPE']), $binaryTypes, true),
            'generated' => stripos((string)$column['EXTRA'], 'GENERATED') !== false,
            'primary' => strtoupper((string)$column['COLUMN_KEY']) === 'PRI'];
    }
    return $columns;
}

function backupSqlValue(PDO $conn, mixed $value, bool $binary): string
{
    if ($value === null) return 'NULL';
    if ($binary) return $value === '' ? "X''" : '0x' . bin2hex((string)$value);
    $quoted = $conn->quote((string)$value);
    if ($quoted === false) throw new RuntimeException('A database value could not be safely encoded.');
    return $quoted;
}

function backupIdentifier(string $identifier): string { return '`' . str_replace('`', '``', $identifier) . '`'; }

function addEncryptedBackupDirectory(ZipArchive $zip, string $source, string $destination, string $password, array &$entries): void
{
    $source = rtrim($source, '/\\');
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->isLink()) continue;
        $relative = substr($file->getPathname(), strlen($source) + 1);
        addEncryptedBackupFile($zip, $file->getPathname(), trim($destination, '/') . '/' . str_replace('\\', '/', $relative), $password, $entries);
    }
}

function addEncryptedBackupFile(ZipArchive $zip, string $source, string $entryName, string $password, array &$entries): void
{
    if (!$zip->addFile($source, $entryName)) throw new RuntimeException("Unable to add {$entryName} to the backup.");
    encryptBackupEntry($zip, $entryName, $password);
    $entries[] = ['path' => $entryName, 'size' => (int)filesize($source), 'sha256' => hash_file('sha256', $source)];
}

function encryptBackupEntry(ZipArchive $zip, string $entryName, string $password): void
{
    if (!method_exists($zip, 'setEncryptionName') || !$zip->setEncryptionName($entryName, ZipArchive::EM_AES_256, $password)) {
        throw new RuntimeException('AES-256 ZIP encryption is unavailable on this server.');
    }
}

function removeBackupWorkingDirectory(string $directory): void
{
    if ($directory === '' || !is_dir($directory)) return;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        if ($item->isDir() && !$item->isLink()) @rmdir($item->getPathname()); else @unlink($item->getPathname());
    }
    @rmdir($directory);
}
