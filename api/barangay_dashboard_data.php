<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db_connect.php';

// Authenticate and authorize
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'barangay_staff' || !isset($_SESSION['barangay'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$barangay = $_SESSION['barangay'];
$response = [
    'success' => true,
    'data'    => [
        'stats'          => [],
        'fsm_stats'      => [],
        'monthly'        => [],
        'notifications'  => [],
        'priority_count' => 0,
        'total_count'    => 0,
    ]
];

try {
    // 1. Get data for Status Distribution Chart (old status column)
    $statusStmt = $conn->prepare("
        SELECT status, COUNT(*) as count 
        FROM applications 
        WHERE barangay = :barangay 
        GROUP BY status
    ");
    $statusStmt->execute(['barangay' => $barangay]);
    $response['data']['stats'] = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Get FSM Workflow State Distribution Chart data
    $fsmStmt = $conn->prepare("
        SELECT 
            COALESCE(workflow_state, 'Received') as workflow_state, 
            COUNT(*) as count 
        FROM applications 
        WHERE barangay = :barangay 
        GROUP BY workflow_state
    ");
    $fsmStmt->execute(['barangay' => $barangay]);
    $response['data']['fsm_stats'] = $fsmStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Get priority queue count (high-priority applications awaiting action)
    $priorityStmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM applications 
        WHERE barangay = :barangay 
        AND priority_level = 'high'
        AND workflow_state = 'Received'
    ");
    $priorityStmt->execute(['barangay' => $barangay]);
    $priorityRow = $priorityStmt->fetch(PDO::FETCH_ASSOC);
    $response['data']['priority_count'] = (int)($priorityRow['count'] ?? 0);

    // 4. Total applications count
    $totalStmt = $conn->prepare("
        SELECT COUNT(*) as count FROM applications WHERE barangay = :barangay
    ");
    $totalStmt->execute(['barangay' => $barangay]);
    $totalRow = $totalStmt->fetch(PDO::FETCH_ASSOC);
    $response['data']['total_count'] = (int)($totalRow['count'] ?? 0);

    // 5. Get data for Monthly Applications Chart (last 12 months)
    $monthlyStmt = $conn->prepare("
        SELECT
            MONTHNAME(date_submitted) as month,
            MONTH(date_submitted) as month_num,
            YEAR(date_submitted) as year,
            application_type,
            COUNT(*) as count
        FROM applications
        WHERE barangay = :barangay AND date_submitted >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY YEAR(date_submitted), MONTH(date_submitted), MONTHNAME(date_submitted), application_type
        ORDER BY YEAR(date_submitted), MONTH(date_submitted)
    ");
    $monthlyStmt->execute(['barangay' => $barangay]);
    $response['data']['monthly'] = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Get recent applications for notifications (including FSM state)
    $notifStmt = $conn->prepare("
        SELECT 
            id_number as id,
            full_name,
            application_type,
            status,
            COALESCE(workflow_state, 'Received') as workflow_state,
            priority_level,
            date_submitted
        FROM applications
        WHERE barangay = :barangay
        ORDER BY CASE WHEN priority_level = 'high' THEN 0 ELSE 1 END, date_submitted DESC
        LIMIT 8
    ");
    $notifStmt->execute(['barangay' => $barangay]);
    $response['data']['notifications'] = $notifStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $response['success'] = false;
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log("API Error in barangay_dashboard_data.php: " . $e->getMessage());
}

echo json_encode($response);
?>