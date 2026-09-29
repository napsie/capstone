<?php
require_once __DIR__ . '/../includes/db_connect.php';

$admin = $conn->query("SELECT id,username FROM users WHERE role IN ('department_admin','super_admin') ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$admin) {
    fwrite(STDERR, "FAIL: no administrator is available for the commit test\n");
    exit(1);
}

$seniorId = 'TEST-' . strtoupper(bin2hex(random_bytes(6)));
$csv = tempnam(sys_get_temp_dir(), 'seniorlink-import-');
file_put_contents($csv, implode(',', [
    'application_type', 'senior_id_no', 'first_name', 'last_name', 'birth_date',
    'contact_number', 'complete_address', 'barangay', 'date_submitted',
]) . "\n" . implode(',', [
    'senior', $seniorId, 'Commit', 'Test', '1950-01-01', '09123456789',
    '1 Test Street Bagong Ilog Pasig City', 'Bagong Ilog', date('Y-m-d'),
]) . "\n");

session_save_path(sys_get_temp_dir());
session_id('seniorlink-commit-' . bin2hex(random_bytes(6)));
session_start();
$_SESSION = [
    'user_id' => (int)$admin['id'], 'username' => $admin['username'], 'role' => 'department_admin',
    'first_name' => 'Import', 'last_name' => 'Test',
];

$originalDirectory = getcwd();
$applicationId = null;
$jobToken = null;
try {
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_GET = [];
    $_POST = [];
    $_FILES = ['records_file' => [
        'name' => 'commit-test.csv', 'type' => 'text/csv', 'tmp_name' => $csv,
        'error' => UPLOAD_ERR_OK, 'size' => filesize($csv),
    ]];
    chdir(__DIR__ . '/../pages');
    $level = ob_get_level();
    ob_start();
    include 'import_records.php';
    while (ob_get_level() > $level + 1) ob_end_flush();
    $previewHtml = ob_get_clean();
    $jobToken = (string)($_SESSION['record_import_token'] ?? '');
    if ($jobToken === '' || !str_contains($previewHtml, '<strong>1</strong> valid of <strong>1</strong> rows')) {
        throw new RuntimeException('The commit fixture did not produce a valid preview.');
    }

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['commit_import' => '1', 'import_token' => $jobToken];
    $_FILES = [];
    $level = ob_get_level();
    ob_start();
    include 'import_records.php';
    while (ob_get_level() > $level + 1) ob_end_flush();
    $commitHtml = ob_get_clean();
    if (!str_contains($commitHtml, 'Import complete: 1 record(s) inserted; 0 skipped.')) {
        throw new RuntimeException('The final import transaction failed.');
    }
    $lookup = $conn->prepare('SELECT id_number FROM applications WHERE senior_id_no=? LIMIT 1');
    $lookup->execute([$seniorId]);
    $applicationId = $lookup->fetchColumn();
    if (!$applicationId) throw new RuntimeException('The imported application was not saved.');

    echo "PASS: web import commits a validated record\n";
} catch (Throwable $error) {
    fwrite(STDERR, "FAIL: {$error->getMessage()}\n");
    exitCode(1);
} finally {
    chdir($originalDirectory);
    if ($applicationId) {
        $conn->prepare('DELETE FROM application_history WHERE application_id=?')->execute([$applicationId]);
        $conn->prepare('DELETE FROM applications WHERE id_number=?')->execute([$applicationId]);
    }
    if ($jobToken) $conn->prepare('DELETE FROM import_jobs WHERE job_token=?')->execute([$jobToken]);
    @unlink($csv);
    $_SESSION = [];
    session_destroy();
}

function exitCode(int $code): void {
    $GLOBALS['importCommitExitCode'] = $code;
}

exit($GLOBALS['importCommitExitCode'] ?? 0);
