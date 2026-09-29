<?php
require_once __DIR__ . '/private_storage.php';

function backupSafeSegment(string $value, string $fallback = 'item'): string
{
    $value = preg_replace('/[^A-Za-z0-9._-]+/', '-', trim($value)) ?? '';
    $value = trim($value, '.-_');
    return $value !== '' ? substr($value, 0, 100) : $fallback;
}

function backupExtension(string $filename, string $mimeType): string
{
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (preg_match('/^[a-z0-9]{1,8}$/', $extension)) return $extension;
    return match (strtolower($mimeType)) {
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif',
        'application/pdf' => 'pdf', default => 'bin',
    };
}

function writeBackupJsonArray(PDOStatement $statement, string $path, ?callable $map = null): int
{
    $stream = fopen($path, 'wb');
    if ($stream === false) throw new RuntimeException('Unable to create a temporary backup file.');
    fwrite($stream, "[\n");
    $count = 0;
    try {
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            if ($map !== null) $row = $map($row);
            $json = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
            fwrite($stream, ($count > 0 ? ",\n" : '') . $json);
            $count++;
        }
        fwrite($stream, "\n]\n");
    } finally {
        fclose($stream);
    }
    return $count;
}

function addBackupFile(ZipArchive $zip, string $diskPath, string $zipPath, array &$checksums): void
{
    if (!$zip->addFile($diskPath, $zipPath)) throw new RuntimeException("Unable to add {$zipPath} to the backup.");
    $checksum = hash_file('sha256', $diskPath);
    if ($checksum === false) throw new RuntimeException("Unable to verify {$zipPath}.");
    $checksums[$zipPath] = $checksum;
}

function addBackupBytes(ZipArchive $zip, string $bytes, string $zipPath, array &$checksums): void
{
    if (!$zip->addFromString($zipPath, $bytes)) throw new RuntimeException("Unable to add {$zipPath} to the backup.");
    $checksums[$zipPath] = hash('sha256', $bytes);
}

/** Build a portable applicant backup and return its manifest. */
function createApplicantBackupZip(PDO $conn, string $zipPath, array $actor): array
{
    if (!class_exists(ZipArchive::class)) throw new RuntimeException('ZIP support is unavailable on this server.');
    $workDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'seniorlink-backup-' . bin2hex(random_bytes(8));
    if (!mkdir($workDir, 0700, true) && !is_dir($workDir)) throw new RuntimeException('Unable to prepare the backup workspace.');

    $zip = new ZipArchive();
    $opened = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($opened !== true) throw new RuntimeException('Unable to create the ZIP backup.');

    $checksums = [];
    $missingFiles = [];
    $counts = ['applications' => 0, 'application_history' => 0, 'document_metadata' => 0, 'document_files' => 0];
    $temporaryFiles = [];
    try {
        $columnRows = $conn->query('SHOW COLUMNS FROM applications')->fetchAll(PDO::FETCH_ASSOC);
        $blobColumns = [];
        $applicationColumns = [];
        foreach ($columnRows as $column) {
            $type = strtolower((string)($column['Type'] ?? ''));
            $field = (string)($column['Field'] ?? '');
            $escaped = '`' . str_replace('`', '``', $field) . '`';
            if (preg_match('/^(tinyblob|blob|mediumblob|longblob|binary|varbinary)/', $type)) $blobColumns[] = $field;
            else $applicationColumns[] = $escaped;
        }
        if (!$applicationColumns) throw new RuntimeException('No applicant fields are available for backup.');

        $applicationsPath = $workDir . DIRECTORY_SEPARATOR . 'applications.json';
        $historyPath = $workDir . DIRECTORY_SEPARATOR . 'application_history.json';
        $documentsPath = $workDir . DIRECTORY_SEPARATOR . 'application_documents.json';
        $temporaryFiles = [$applicationsPath, $historyPath, $documentsPath];

        $counts['applications'] = writeBackupJsonArray(
            $conn->query('SELECT ' . implode(',', $applicationColumns) . ' FROM applications ORDER BY id_number'),
            $applicationsPath
        );
        $counts['application_history'] = writeBackupJsonArray(
            $conn->query('SELECT id,application_id,previous_state,new_state,changed_by,comments,changed_at FROM application_history ORDER BY application_id,changed_at,id'),
            $historyPath
        );

        $documentMetadata = [];
        $documents = $conn->query('SELECT * FROM application_documents ORDER BY application_id,document_key,version,id');
        while ($document = $documents->fetch(PDO::FETCH_ASSOC)) {
            $bytes = (string)($document['document_data'] ?? '');
            unset($document['document_data']);
            $extension = backupExtension((string)($document['original_filename'] ?? ''), (string)($document['mime_type'] ?? ''));
            $appSegment = backupSafeSegment((string)$document['application_id'], 'application');
            $keySegment = backupSafeSegment((string)$document['document_key'], 'document');
            $version = max(1, (int)($document['version'] ?? 1));
            $zipDocumentPath = "documents/{$appSegment}/{$document['id']}-{$keySegment}-v{$version}.{$extension}";
            addBackupBytes($zip, $bytes, $zipDocumentPath, $checksums);
            $document['backup_path'] = $zipDocumentPath;
            $document['backup_sha256'] = $checksums[$zipDocumentPath];
            $documentMetadata[] = $document;
            $counts['document_files']++;
        }
        $metadataStream = fopen($documentsPath, 'wb');
        if ($metadataStream === false) throw new RuntimeException('Unable to create document metadata.');
        fwrite($metadataStream, json_encode($documentMetadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR));
        fclose($metadataStream);
        $counts['document_metadata'] = count($documentMetadata);

        // Preserve legacy document columns that predate application_documents.
        if ($blobColumns) {
            $select = ['id_number'];
            foreach ($blobColumns as $column) {
                $select[] = '`' . str_replace('`', '``', $column) . '`';
                $mimeColumn = $column . '_type';
                if (in_array($mimeColumn, array_column($columnRows, 'Field'), true)) {
                    $select[] = '`' . str_replace('`', '``', $mimeColumn) . '`';
                }
            }
            $legacyRows = $conn->query('SELECT ' . implode(',', $select) . ' FROM applications ORDER BY id_number');
            while ($row = $legacyRows->fetch(PDO::FETCH_ASSOC)) {
                $applicationId = (string)$row['id_number'];
                foreach ($blobColumns as $column) {
                    $value = $row[$column] ?? null;
                    if ($value === null || $value === '') continue;
                    $mimeColumn = $column . '_type';
                    $mime = (string)($row[$mimeColumn] ?? '');
                    $diskPath = null;
                    if (is_string($value) && strlen($value) < 255) {
                        try { $diskPath = privateExistingUploadPath($value); }
                        catch (Throwable) { $diskPath = null; }
                    }
                    $extension = backupExtension($diskPath !== null ? (string)$value : '', $mime);
                    $legacyPath = 'legacy-documents/' . backupSafeSegment($applicationId) . '/' . backupSafeSegment($column) . '.' . $extension;
                    if ($diskPath !== null) addBackupFile($zip, $diskPath, $legacyPath, $checksums);
                    else addBackupBytes($zip, (string)$value, $legacyPath, $checksums);
                    $counts['document_files']++;
                }
            }
        }

        // Copy filename-based legacy documents from private storage when present.
        $legacyReferenceColumns = ['psa_birth_cert','barangay_residency','comelec_cert','deceased_landbank_card','proof_of_life','auth_letter','proxy_id','proxy_birth_cert','home_visitation_form','landbank_enrollment_form','government_id_front','government_id_back'];
        $availableColumns = array_column($columnRows, 'Field');
        $legacyReferenceColumns = array_values(array_intersect($legacyReferenceColumns, $availableColumns));
        if ($legacyReferenceColumns) {
            $legacyRows = $conn->query('SELECT id_number,`' . implode('`,`', $legacyReferenceColumns) . '` FROM applications ORDER BY id_number');
            while ($row = $legacyRows->fetch(PDO::FETCH_ASSOC)) {
                $applicationId = (string)$row['id_number'];
                foreach ($legacyReferenceColumns as $column) {
                    $storedName = (string)($row[$column] ?? '');
                    if ($storedName === '') continue;
                    try { $diskPath = privateExistingUploadPath($storedName); }
                    catch (Throwable) { $diskPath = null; }
                    if ($diskPath === null) {
                        $missingFiles[] = ['application_id' => $applicationId, 'field' => $column, 'stored_name' => basename($storedName)];
                        continue;
                    }
                    $extension = backupExtension($storedName, '');
                    $legacyPath = 'legacy-documents/' . backupSafeSegment($applicationId) . '/' . backupSafeSegment($column) . '.' . $extension;
                    addBackupFile($zip, $diskPath, $legacyPath, $checksums);
                    $counts['document_files']++;
                }
            }
        }

        foreach ([
            'records/applications.json' => $applicationsPath,
            'records/application_history.json' => $historyPath,
            'records/application_documents.json' => $documentsPath,
        ] as $inside => $disk) addBackupFile($zip, $disk, $inside, $checksums);

        ksort($checksums);
        $manifest = [
            'backup_version' => 2,
            'scope' => 'applicant_records_with_documents',
            'generated_at' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format(DateTimeInterface::ATOM),
            'generated_by' => (string)($actor['username'] ?? $actor['id'] ?? 'unknown'),
            'counts' => $counts,
            'missing_legacy_files' => $missingFiles,
            'complete' => count($missingFiles) === 0,
        ];
        addBackupBytes($zip, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 'manifest.json', $checksums);
        $checksumText = '';
        foreach ($checksums as $path => $hash) $checksumText .= "{$hash}  {$path}\n";
        if (!$zip->addFromString('checksums.sha256', $checksumText)) throw new RuntimeException('Unable to add backup checksums.');
        if (!$zip->close()) throw new RuntimeException('Unable to finalize the ZIP backup.');
        return $manifest;
    } catch (Throwable $error) {
        $zip->close();
        @unlink($zipPath);
        throw $error;
    } finally {
        foreach ($temporaryFiles as $file) @unlink($file);
        @rmdir($workDir);
    }
}
