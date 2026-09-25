<?php
require_once __DIR__ . '/../includes/db_connect.php';

$workbook = 'D:/Downloads/Group_3_Pasig_Senior_Citizen_Data_With_Numeric_ID_and_Benefits.xlsx';
if (!is_file($workbook)) {
    fwrite(STDERR, "SKIP: provided workbook is unavailable\n");
    exit(0);
}

$admin = $conn->query("SELECT id,username FROM users WHERE role IN ('department_admin','super_admin') ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$admin) {
    fwrite(STDERR, "FAIL: no administrator is available for the upload test\n");
    exit(1);
}

$sessionId = 'seniorlink-import-' . bin2hex(random_bytes(6));
session_save_path(sys_get_temp_dir());
session_id($sessionId);
session_start();
$_SESSION = [
    'user_id' => (int)$admin['id'], 'username' => $admin['username'], 'role' => 'department_admin',
    'first_name' => 'Import', 'last_name' => 'Test',
];
session_write_close();

$originalDirectory = getcwd();
try {
    $conn->beginTransaction();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_POST = [];
    $_GET = [];
    $_FILES = ['records_file' => [
        'name' => basename($workbook), 'type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'tmp_name' => $workbook, 'error' => UPLOAD_ERR_OK, 'size' => filesize($workbook),
    ]];
    chdir(__DIR__ . '/../pages');
    $bufferLevel = ob_get_level();
    ob_start();
    include 'import_records.php';
    while (ob_get_level() > $bufferLevel + 1) ob_end_flush();
    $html = ob_get_clean();
    if (!str_contains($html, '<strong>50</strong> valid of <strong>50</strong> rows')) {
        throw new RuntimeException('The web upload did not produce a 50-of-50 validation preview.');
    }
    if (str_contains($html, 'The Excel worksheet is invalid') || str_contains($html, 'No readable worksheet was found')) {
        throw new RuntimeException('The web upload still reported a worksheet parsing error.');
    }
    if (!str_contains($html, 'type="hidden" name="commit_import" value="1"')) {
        throw new RuntimeException('The final import form is missing its commit marker.');
    }
    $conn->rollBack();
    echo "PASS: web upload validates all 50 workbook rows\n";
} catch (Throwable $error) {
    if ($conn->inTransaction()) $conn->rollBack();
    fwrite(STDERR, "FAIL: {$error->getMessage()}\n");
    exit(1);
} finally {
    chdir($originalDirectory);
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $_SESSION = [];
    session_destroy();
}
