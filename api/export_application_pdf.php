<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/application_types.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['barangay_staff', 'department_admin', 'super_admin'])) {
    http_response_code(401);
    echo '<h2>Unauthorized. Please <a href="../index.php">log in</a>.</h2>';
    exit();
}

$userRole     = $_SESSION['role'];
$userBarangay = $_SESSION['barangay'] ?? null;
$appId        = trim($_GET['id'] ?? '');

if (empty($appId)) {
    echo '<h2>Error: No application ID provided.</h2>';
    exit();
}

try {
    if ($userRole === 'barangay_staff' && $userBarangay) {
        $stmt = $conn->prepare("SELECT * FROM applications WHERE id_number = ? AND barangay = ?");
        $stmt->execute([$appId, $userBarangay]);
    } else {
        $stmt = $conn->prepare("SELECT * FROM applications WHERE id_number = ?");
        $stmt->execute([$appId]);
    }
    $app = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$app) {
        echo '<h2>Error: Application not found or access denied.</h2>';
        exit();
    }

    // Approval history is the issuance audit trail displayed on form F1.
    if (!empty($app['senior_id_no'])) {
        $issuedStmt = $conn->prepare(
            "SELECT changed_by, changed_at
             FROM application_history
             WHERE application_id = ? AND new_state IN ('Verified', 'Approved')
             ORDER BY changed_at ASC LIMIT 1"
        );
        $issuedStmt->execute([$appId]);
        $issuance = $issuedStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $app['senior_id_issued_by'] = $issuance['changed_by'] ?? '';
        $app['senior_id_issued_at'] = $issuance['changed_at'] ?? null;
    }

    unset($app['proof_of_address'], $app['id_image']);

    $formTemplates = [
        'senior'           => 'f1_senior_id.php',
        'landbank'         => 'f2_landbank.php',
        'pension'          => 'f_pension.php',
        'national_pension' => 'f_pension.php',
        'milestone_gift'   => 'f5_octogenarian.php',
        'burial'           => 'f7_burial.php',
        'home_visit'       => 'f8_home_visit.php',
        'pwd'              => 'f1_senior_id.php',
    ];

    $type = $app['application_type'] ?? 'senior';
    $templateFile = $formTemplates[$type] ?? 'f1_senior_id.php';
    $templatePath = __DIR__ . '/../templates/forms/' . $templateFile;

    if (!file_exists($templatePath)) {
        echo '<h2>Error: Form template not found.</h2>';
        exit();
    }

    include $templatePath;

} catch (PDOException $e) {
    echo '<h2>Database error: ' . htmlspecialchars($e->getMessage()) . '</h2>';
    exit();
}
