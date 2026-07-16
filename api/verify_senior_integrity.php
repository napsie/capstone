<?php
header('Content-Type: application/json');
require_once '../includes/db_connect.php';

$seniorId = isset($_GET['id']) ? trim($_GET['id']) : '';

if (empty($seniorId)) {
    echo json_encode(['success' => false, 'message' => 'Senior Citizen ID is required.']);
    exit();
}

try {
    // Check if senior citizen exists, has been approved or verified, and is a proxy/bedridden applicant
    $stmt = $conn->prepare("SELECT full_name, complete_address, barangay, workflow_state, is_proxy_application 
                            FROM applications 
                            WHERE id_number = ?");
    $stmt->execute([$seniorId]);
    $senior = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$senior) {
        echo json_encode(['success' => false, 'message' => 'Senior ID not found in database.']);
        exit();
    }

    if ($senior['is_proxy_application'] != 1) {
        echo json_encode(['success' => false, 'message' => 'Applicant profile is not flagged as Bedridden / Low-Mobility. Only Bedridden seniors are eligible for representative pension benefit claims.']);
        exit();
    }

    $isValidState = in_array($senior['workflow_state'], ['Verified', 'Approved', 'Released']);
    if (!$isValidState) {
        echo json_encode([
            'success' => false, 
            'message' => 'Profile is in state: [' . ($senior['workflow_state'] ?: 'Received') . ']. Applying for pension benefits requires a Verified or Approved Senior Citizen status.'
        ]);
        exit();
    }

    // Profile integrity check passed!
    echo json_encode([
        'success' => true,
        'senior' => [
            'full_name' => $senior['full_name'],
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
