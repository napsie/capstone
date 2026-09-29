<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/applicant_backup.php';

$path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'seniorlink-backup-test-' . bin2hex(random_bytes(6)) . '.zip';
try {
    $manifest = createApplicantBackupZip($conn, $path, ['username' => 'Backup Test']);
    if (!is_file($path) || filesize($path) === 0) throw new RuntimeException('The ZIP was not created.');
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException('The generated ZIP cannot be opened.');
    foreach (['manifest.json','checksums.sha256','records/applications.json','records/application_history.json','records/application_documents.json'] as $required) {
        if ($zip->locateName($required) === false) throw new RuntimeException("Missing required backup entry: {$required}");
    }
    $storedManifest = json_decode((string)$zip->getFromName('manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    if (($storedManifest['counts']['applications'] ?? -1) !== (int)$conn->query('SELECT COUNT(*) FROM applications')->fetchColumn()) {
        throw new RuntimeException('The manifest application count is incorrect.');
    }
    $checksumLines = preg_split('/\R/', trim((string)$zip->getFromName('checksums.sha256'))) ?: [];
    foreach ($checksumLines as $line) {
        [$expected, $entry] = preg_split('/\s{2}/', $line, 2);
        $contents = $zip->getFromName($entry);
        if ($contents === false || !hash_equals($expected, hash('sha256', $contents))) {
            throw new RuntimeException("Checksum validation failed: {$entry}");
        }
    }
    $zip->close();
    echo 'PASS: full applicant ZIP backup (' . filesize($path) . ' bytes, '
        . $manifest['counts']['applications'] . ' applications, '
        . $manifest['counts']['document_files'] . " files)\n";
} catch (Throwable $error) {
    fwrite(STDERR, "FAIL: {$error->getMessage()}\n");
    exit(1);
} finally {
    @unlink($path);
}
