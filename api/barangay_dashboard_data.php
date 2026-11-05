<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db_connect.php';
require_once '../includes/dashboard_response_cache.php';
require_once '../includes/dashboard_data.php';
require_once '../includes/dashboard_report_snapshot.php';

// Authenticate and authorize
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'barangay_staff' || !isset($_SESSION['barangay'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$barangay = $_SESSION['barangay'];
$dashboardCacheKey = 'barangay-dashboard-v2|' . $dbname . '|' . $barangay;
if ($cachedResponse = readDashboardResponseCache($dashboardCacheKey)) {
    header('X-Seniorlink-Cache: HIT');
    rememberDashboardReportSnapshot($cachedResponse,$_SESSION);
    echo json_encode($cachedResponse);
    exit;
}
$response = [
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'data'    => [
        'stats'          => [],
        'workflow_stats' => [],
        'gender_stats'   => [],
        'monthly'        => [],
        'notifications'  => [],
        'priority_count' => 0,
        'queue_count'    => 0,
        'total_count'    => 0,
    ]
];

try {
    $response['data']=barangayDashboardData($conn,$barangay);
} catch (PDOException $e) {
    $response['success'] = false;
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log("API Error in barangay_dashboard_data.php: " . $e->getMessage());
}

if ($response['success']) writeDashboardResponseCache($dashboardCacheKey, $response);
header('X-Seniorlink-Cache: MISS');
if (($response['status']??'')==='success' || ($response['success']??false)) rememberDashboardReportSnapshot($response,$_SESSION);
echo json_encode($response);
?>
