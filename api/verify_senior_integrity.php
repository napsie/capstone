<?php
header('Content-Type: application/json');
require_once '../includes/db_connect.php';

$seniorId = isset($_GET['id']) ? trim($_GET['id']) : '';

if (empty($seniorId)) {
    echo json_encode(['success' => false, 'message' => 'Senior Citizen ID is required.']);
    exit();
}

try {
    // Accept the official OSCA number used on benefit forms. The transaction
    // number remains supported for older representative QR slips.
    $stmt = $conn->prepare("SELECT id_number, senior_id_no, full_name, complete_address, barangay,
                                   workflow_state, is_proxy_application
                            FROM applications
                            WHERE application_type = 'senior'
                              AND (senior_id_no = ? OR id_number = ?)
                            ORDER BY CASE WHEN senior_id_no = ? THEN 0 ELSE 1 END
                            LIMIT 1");
    $stmt->execute([$seniorId, $seniorId, $seniorId]);
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
            'complete_address' => $senior['complete_address'],
            'barangay' => $senior['barangay'],
            'workflow_state' => $senior['workflow_state']
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server or database error: ' . $e->getMessage()]);
}
exit();
?>
