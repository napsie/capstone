<?php
header('Content-Type: application/json');
session_start();

require_once '../includes/db_connect.php';

// Auth check — department admins or super admins only
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['department_admin', 'super_admin'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$response = [
    'status'    => 'success',
    'timestamp' => date('Y-m-d H:i:s'),
    'data'      => []
];

try {
    // ── Recent Applications for Notifications (10 most recent, priority first) ──
    $stmt = $conn->prepare("
        SELECT 
            id_number,
            full_name,
            application_type,
            status,
            COALESCE(workflow_state, 'Received') as workflow_state,
            priority_level,
            barangay,
            date_submitted
        FROM applications
        ORDER BY 
            CASE WHEN priority_level = 'high' THEN 0 ELSE 1 END,
            date_submitted DESC
        LIMIT 10
    ");
    $stmt->execute();
    $response['data']['notifications'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Barangay Records Chart Data ──
    $stmt = $conn->prepare("SELECT barangay, COUNT(*) as count FROM applications GROUP BY barangay ORDER BY count DESC");
    $stmt->execute();
    $response['data']['barangay_records'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Yearly Records Chart Data ──
    $stmt = $conn->prepare("SELECT YEAR(date_submitted) as year, COUNT(*) as count FROM applications GROUP BY YEAR(date_submitted) ORDER BY year ASC");
    $stmt->execute();
    $response['data']['yearly_records'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Stat Cards ──
    // Verified Applications = workflow_state = 'Verified' OR 'Approved' OR 'Released'
    $stmt = $conn->prepare("SELECT COUNT(*) FROM applications WHERE workflow_state IN ('Verified','Approved','Released')");
    $stmt->execute();
    $response['data']['verified_applications'] = (int)$stmt->fetchColumn();

    // Senior Citizen Records
    $stmt = $conn->prepare("SELECT COUNT(*) FROM applications WHERE application_type = 'senior'");
    $stmt->execute();
    $response['data']['senior_citizen_records'] = (int)$stmt->fetchColumn();

    // Total Processed
    $stmt = $conn->prepare("SELECT COUNT(*) FROM applications");
    $stmt->execute();
    $response['data']['total_processed'] = (int)$stmt->fetchColumn();

    // FSM State Distribution (for stat cards extended)
    $stmt = $conn->prepare("
        SELECT COALESCE(workflow_state, 'Received') as workflow_state, COUNT(*) as count
        FROM applications
        GROUP BY workflow_state
    ");
    $stmt->execute();
    $fsmRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $fsmMap  = [];
    foreach ($fsmRows as $row) {
        $fsmMap[$row['workflow_state']] = (int)$row['count'];
    }
    $response['data']['fsm_stats'] = $fsmMap;

} catch (PDOException $e) {
    $response['status']  = 'error';
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log('Database error in get_realtime_data.php: ' . $e->getMessage());
}

echo json_encode($response);
?>