<?php
header('Content-Type: application/json');
session_start();

require_once '../includes/db_connect.php';
require_once '../includes/dashboard_response_cache.php';

// Auth check — department admins or super admins only
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['department_admin', 'super_admin'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$dashboardCacheKey = 'department-dashboard|' . ($_SESSION['role'] ?? '') . '|' . ($_SESSION['user_id'] ?? '');
if ($cachedResponse = readDashboardResponseCache($dashboardCacheKey)) {
    header('X-Seniorlink-Cache: HIT');
    echo json_encode($cachedResponse);
    exit;
}

$response = [
    'status'    => 'success',
    'timestamp' => date('Y-m-d H:i:s'),
    'data'      => []
];

try {
    // ── Recent Applications for Notifications (newest first) ──
    $stmt = $conn->prepare("
        SELECT 
            id_number,
            full_name,
            application_type,
            status,
            CASE
                WHEN COALESCE(home_visit_status, '') IN ('Rejected', 'Cancelled') THEN 'Rejected'
                WHEN workflow_state = 'Needs Correction' THEN 'Needs Correction'
                WHEN (application_type = 'pension' OR requested_benefit = 'Local Social Pension Assessment')
                     AND COALESCE(home_visit_status, '') NOT IN ('Completed', 'Rejected', 'Cancelled') THEN 'Pending'
                WHEN COALESCE(workflow_state, 'Received') IN ('Approved','Released') THEN 'Verified'
                ELSE COALESCE(workflow_state, 'Received')
            END as workflow_state,
            priority_level,
            barangay,
            date_submitted
        FROM applications
        WHERE (is_archived = 0 OR is_archived IS NULL)
        ORDER BY date_submitted DESC,
            CASE WHEN priority_level = 'high' THEN 0 ELSE 1 END
        LIMIT 10
    ");
    $stmt->execute();
    $response['data']['notifications'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Barangay Records Chart Data ──
    $stmt = $conn->prepare("SELECT barangay, COUNT(*) as count FROM applications WHERE (is_archived = 0 OR is_archived IS NULL) GROUP BY barangay ORDER BY count DESC");
    $stmt->execute();
    $response['data']['barangay_records'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Yearly Records Chart Data ──
    $stmt = $conn->prepare("SELECT YEAR(date_submitted) as year, COUNT(*) as count FROM applications WHERE (is_archived = 0 OR is_archived IS NULL) GROUP BY YEAR(date_submitted) ORDER BY year ASC");
    $stmt->execute();
    $response['data']['yearly_records'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Stat Cards: one aggregate scan for all summary values ──
    $stmt = $conn->prepare("
        SELECT
            SUM(CASE WHEN workflow_state IN ('Verified','Approved','Released') THEN 1 ELSE 0 END) AS verified_applications,
            SUM(CASE WHEN application_type = 'senior' THEN 1 ELSE 0 END) AS senior_citizen_records,
            SUM(CASE WHEN application_type = 'senior' AND COALESCE(workflow_state, status, 'Received') NOT IN ('Verified','Approved','Released','Rejected') THEN 1 ELSE 0 END) AS pending_senior_id,
            SUM(CASE WHEN application_type = 'landbank' AND COALESCE(workflow_state, status, 'Received') NOT IN ('Verified','Approved','Released','Rejected') THEN 1 ELSE 0 END) AS pending_landbank,
            SUM(CASE WHEN application_type = 'pension' AND COALESCE(workflow_state, status, 'Received') NOT IN ('Verified','Approved','Released','Rejected') THEN 1 ELSE 0 END) AS pending_local_pension,
            SUM(CASE WHEN application_type = 'milestone_gift' AND COALESCE(workflow_state, status, 'Received') NOT IN ('Verified','Approved','Released','Rejected') THEN 1 ELSE 0 END) AS pending_octogenarian,
            SUM(CASE WHEN application_type = 'burial' AND COALESCE(workflow_state, status, 'Received') NOT IN ('Verified','Approved','Released','Rejected') THEN 1 ELSE 0 END) AS pending_burial,
            COUNT(*) AS total_processed
        FROM applications
        WHERE (is_archived = 0 OR is_archived IS NULL)
    ");
    $stmt->execute();
    $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $response['data']['verified_applications'] = (int)($summary['verified_applications'] ?? 0);
    $response['data']['senior_citizen_records'] = (int)($summary['senior_citizen_records'] ?? 0);
    $response['data']['pending_senior_id'] = (int)($summary['pending_senior_id'] ?? 0);
    $response['data']['pending_landbank'] = (int)($summary['pending_landbank'] ?? 0);
    $response['data']['pending_local_pension'] = (int)($summary['pending_local_pension'] ?? 0);
    $response['data']['pending_octogenarian'] = (int)($summary['pending_octogenarian'] ?? 0);
    $response['data']['pending_burial'] = (int)($summary['pending_burial'] ?? 0);
    $response['data']['total_processed'] = (int)($summary['total_processed'] ?? 0);

    // Workflow status distribution (for extended stat cards)
    $stmt = $conn->prepare("
        SELECT CASE
                   WHEN COALESCE(home_visit_status, '') IN ('Rejected', 'Cancelled') THEN 'Rejected'
                   WHEN workflow_state = 'Needs Correction' THEN 'Needs Correction'
                WHEN (application_type = 'pension' OR requested_benefit = 'Local Social Pension Assessment')
                        AND COALESCE(home_visit_status, '') NOT IN ('Completed', 'Rejected', 'Cancelled') THEN 'Pending'
                   WHEN COALESCE(workflow_state, 'Received') IN ('Approved','Released') THEN 'Verified'
                   ELSE COALESCE(workflow_state, 'Received')
               END as workflow_state, COUNT(*) as count
        FROM applications
        WHERE (is_archived = 0 OR is_archived IS NULL)
        GROUP BY CASE
                     WHEN COALESCE(home_visit_status, '') IN ('Rejected', 'Cancelled') THEN 'Rejected'
                     WHEN workflow_state = 'Needs Correction' THEN 'Needs Correction'
                WHEN (application_type = 'pension' OR requested_benefit = 'Local Social Pension Assessment')
                          AND COALESCE(home_visit_status, '') NOT IN ('Completed', 'Rejected', 'Cancelled') THEN 'Pending'
                     WHEN COALESCE(workflow_state, 'Received') IN ('Approved','Released') THEN 'Verified'
                     ELSE COALESCE(workflow_state, 'Received')
                 END
    ");
    $stmt->execute();
    $workflowRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $workflowMap  = [];
    foreach ($workflowRows as $row) {
        $workflowMap[$row['workflow_state']] = (int)$row['count'];
    }
    $response['data']['workflow_stats'] = $workflowMap;

} catch (PDOException $e) {
    $response['status']  = 'error';
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log('Database error in get_realtime_data.php: ' . $e->getMessage());
}

if ($response['status'] === 'success') writeDashboardResponseCache($dashboardCacheKey, $response);
header('X-Seniorlink-Cache: MISS');
echo json_encode($response);
?>
