<?php
header('Content-Type: application/json');
session_start();

require_once '../includes/db_connect.php';
require_once '../includes/dashboard_response_cache.php';
require_once '../includes/dashboard_data.php';
require_once '../includes/dashboard_report_snapshot.php';

// Auth check — department admins or super admins only
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['department_admin', 'super_admin'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$dashboardCacheKey = 'department-dashboard-v3|' . $dbname . '|' . ($_SESSION['role'] ?? '') . '|' . ($_SESSION['user_id'] ?? '');
if ($cachedResponse = readDashboardResponseCache($dashboardCacheKey)) {
    header('X-Seniorlink-Cache: HIT');
    rememberDashboardReportSnapshot($cachedResponse,$_SESSION);
    echo json_encode($cachedResponse);
    exit;
}

$response = [
    'status'    => 'success',
    'timestamp' => date('Y-m-d H:i:s'),
    'data'      => []
];

try {
    $response['data']=departmentDashboardData($conn,$driver??'mysql');
} catch (PDOException $e) {
    $response['status']  = 'error';
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log('Database error in get_realtime_data.php: ' . $e->getMessage());
}

if ($response['status'] === 'success') writeDashboardResponseCache($dashboardCacheKey, $response);
header('X-Seniorlink-Cache: MISS');
if (($response['status']??'')==='success' || ($response['success']??false)) rememberDashboardReportSnapshot($response,$_SESSION);
echo json_encode($response);
?>
