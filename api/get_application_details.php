<?php
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

try {
    $appId = isset($_GET['id']) ? $_GET['id'] : '';

    if (empty($appId)) {
        echo json_encode(['error' => 'Application ID is required.']);
        exit();
    }

    $sql = "SELECT a.id_number, a.full_name, a.application_type, a.birth_date, a.contact_number, a.complete_address, 
                   a.emergency_contact, a.emergency_contact_name, a.date_submitted, a.status, a.barangay, a.disability_type, 
                   (a.proof_of_address IS NOT NULL) as has_proof_of_address, (a.id_image IS NOT NULL) as has_id_image, 
                   a.lastName, a.firstName, a.middleName, a.suffix,
                   a.sss_number, a.pension_amount, a.date_of_death, a.relationship_to_deceased,
                   a.is_proxy_application, a.proxy_name, a.proxy_relationship, a.proxy_token,
                   a.priority_level, a.workflow_state,
                   (SELECT h.comments FROM application_history h 
                    WHERE h.application_id = a.id_number 
                      AND h.new_state = 'Received' 
                      AND h.previous_state != 'None' 
                    ORDER BY h.changed_at DESC LIMIT 1) as return_comments
            FROM applications a 
            WHERE a.id_number = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute([$appId]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($application) {
        // Fetch FSM transition history for this application
        $sqlHistory = "SELECT previous_state, new_state, changed_by, changed_at, comments 
                       FROM application_history 
                       WHERE application_id = ? 
                       ORDER BY changed_at ASC";
        $stmtHistory = $conn->prepare($sqlHistory);
        $stmtHistory->execute([$appId]);
        $history = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);
        
        $application['history'] = $history;
    }

    echo json_encode($application);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

$stmt = null;
$conn = null;
?>