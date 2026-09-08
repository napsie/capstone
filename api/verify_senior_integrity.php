<?php
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store');
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['barangay_staff', 'department_admin', 'super_admin'], true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authorized staff access is required.']);
    exit;
}
if ($_SESSION['role'] === 'barangay_staff' && empty($_SESSION['barangay'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'A barangay assignment is required.']);
    exit;
}
require_once '../includes/db_connect.php';

$seniorId = isset($_GET['id']) ? trim($_GET['id']) : '';

if (empty($seniorId)) {
    echo json_encode(['success' => false, 'message' => 'Senior Citizen ID is required.']);
    exit();
}

try {
    // Benefit forms must use the official Senior Citizen ID. PRX/PEN values
    // are tracking tokens and are intentionally not accepted here.
    $scope = $_SESSION['role'] === 'barangay_staff' ? ' AND barangay = ?' : '';
    $params = [$seniorId];
    if ($scope !== '') $params[] = $_SESSION['barangay'];
    $stmt = $conn->prepare("SELECT id_number, senior_id_no, full_name, barangay,
                                   workflow_state, is_proxy_application
                            FROM applications
                            WHERE application_type = 'senior'
                              AND senior_id_no = ?
                              AND COALESCE(is_archived, 0) = 0 $scope
                            LIMIT 1");
    $stmt->execute($params);
    $senior = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$senior) {
        echo json_encode(['success' => false, 'message' => 'Senior ID not found in database.']);
        exit();
    }

    $isValidState = in_array($senior['workflow_state'], ['Verified', 'Approved', 'Released']);
    if (!$isValidState) {
        echo json_encode([
            'success' => false, 
            'message' => 'Profile status is [' . ($senior['workflow_state'] ?: 'Received') . ']. Applying for benefits requires a Verified Senior Citizen record.'
        ]);
        exit();
    }

    // Profile integrity check passed!
    echo json_encode([
        'success' => true,
        'senior' => [
            'full_name' => $senior['full_name'],
            'application_id' => $senior['id_number'],
            'senior_id_no' => $senior['senior_id_no'],
            'barangay' => $senior['barangay'],
            'workflow_state' => $senior['workflow_state']
        ]
    ]);

} catch (Exception $e) {
    error_log('Senior ID lookup failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to check the senior ID. Please try again.']);
}
exit();
?>
