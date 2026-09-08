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
        'workflow_stats' => [],
        'monthly'        => [],
        'notifications'  => [],
        'priority_count' => 0,
        'queue_count'    => 0,
        'total_count'    => 0,
    ]
];

try {
    // 1. Get data for Status Distribution Chart (old status column)
    $statusStmt = $conn->prepare("
        SELECT status, COUNT(*) as count 
        FROM applications 
        WHERE barangay = :barangay 
          AND (is_archived = 0 OR is_archived IS NULL)
        GROUP BY status
    ");
    $statusStmt->execute(['barangay' => $barangay]);
    $response['data']['stats'] = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Get workflow status distribution chart data
    $workflowStmt = $conn->prepare("
        SELECT 
            CASE
                WHEN workflow_state = 'Needs Correction' THEN 'Needs Correction'
                WHEN (application_type = 'pension' OR requested_benefit = 'Local Social Pension Assessment')
                     AND COALESCE(home_visit_status, '') <> 'Completed' THEN 'Pending'
                WHEN COALESCE(NULLIF(workflow_state, ''), NULLIF(status, ''), 'Received') IN ('Approved','Released') THEN 'Verified'
                ELSE COALESCE(NULLIF(workflow_state, ''), NULLIF(status, ''), 'Received')
            END as workflow_state,
            COUNT(*) as count 
        FROM applications 
        WHERE barangay = :barangay 
          AND (is_archived = 0 OR is_archived IS NULL)
        GROUP BY CASE
                     WHEN workflow_state = 'Needs Correction' THEN 'Needs Correction'
                WHEN (application_type = 'pension' OR requested_benefit = 'Local Social Pension Assessment')
                          AND COALESCE(home_visit_status, '') <> 'Completed' THEN 'Pending'
                     WHEN COALESCE(NULLIF(workflow_state, ''), NULLIF(status, ''), 'Received') IN ('Approved','Released') THEN 'Verified'
                     ELSE COALESCE(NULLIF(workflow_state, ''), NULLIF(status, ''), 'Received')
                 END
    ");
    $workflowStmt->execute(['barangay' => $barangay]);
    $response['data']['workflow_stats'] = $workflowStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Compute summary counts in one scan instead of three near-identical queries.
    $summaryStmt = $conn->prepare("
        SELECT
            SUM(CASE WHEN priority_level = 'high'
                AND COALESCE(NULLIF(workflow_state, ''), NULLIF(status, ''), 'Received') = 'Received'
                THEN 1 ELSE 0 END) AS priority_count,
            SUM(CASE WHEN COALESCE(NULLIF(workflow_state, ''), NULLIF(status, ''), 'Received')
                IN ('Received', 'Submitted', 'For Review', 'Needs Correction') THEN 1 ELSE 0 END) AS queue_count
        FROM applications 
        WHERE barangay = :barangay 
          AND (is_archived = 0 OR is_archived IS NULL)
    ");
    $summaryStmt->execute(['barangay' => $barangay]);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $response['data']['priority_count'] = (int)($summary['priority_count'] ?? 0);
    $response['data']['queue_count'] = (int)($summary['queue_count'] ?? 0);
    $response['data']['total_count'] = $response['data']['queue_count'];

    // 5. Get data for Monthly Applications Chart (last 12 months)
    $monthlyStmt = $conn->prepare("
        SELECT
            MONTHNAME(date_submitted) as month,
            MONTH(date_submitted) as month_num,
            YEAR(date_submitted) as year,
            application_type,
            COUNT(*) as count
        FROM applications
        WHERE barangay = :barangay 
          AND (is_archived = 0 OR is_archived IS NULL)
          AND date_submitted >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY YEAR(date_submitted), MONTH(date_submitted), MONTHNAME(date_submitted), application_type
        ORDER BY YEAR(date_submitted), MONTH(date_submitted)
    ");
    $monthlyStmt->execute(['barangay' => $barangay]);
    $response['data']['monthly'] = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Get recent applications for notifications (including workflow status)
    $notifStmt = $conn->prepare("
        SELECT 
            id_number as id,
            full_name,
            application_type,
            status,
            CASE
                WHEN workflow_state = 'Needs Correction' THEN 'Needs Correction'
                WHEN (application_type = 'pension' OR requested_benefit = 'Local Social Pension Assessment')
                     AND COALESCE(home_visit_status, '') <> 'Completed' THEN 'Pending'
                WHEN COALESCE(NULLIF(workflow_state, ''), NULLIF(status, ''), 'Received') IN ('Approved','Released') THEN 'Verified'
                ELSE COALESCE(NULLIF(workflow_state, ''), NULLIF(status, ''), 'Received')
            END as workflow_state,
            priority_level,
            date_submitted
        FROM applications
        WHERE barangay = :barangay
          AND (is_archived = 0 OR is_archived IS NULL)
        ORDER BY date_submitted DESC, CASE WHEN priority_level = 'high' THEN 0 ELSE 1 END
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
