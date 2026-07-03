<?php
header('Content-Type: application/json');
session_start();

require_once '../includes/db_connect.php'; // Ensure this path is correct

$response = [
    'status' => 'success',
    'timestamp' => date('Y-m-d H:i:s'),
    'data' => []
];

try {
    // Fetch Notifications
    $stmt = $conn->prepare("SELECT full_name, application_type, status, date_submitted, barangay FROM applications ORDER BY date_submitted DESC");
    $stmt->execute();
    $response['data']['notifications'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Barangay Records Chart Data
    $stmt = $conn->prepare("SELECT barangay, COUNT(*) as count FROM applications GROUP BY barangay ORDER BY count DESC");
    $stmt->execute();
    $response['data']['barangay_records'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Yearly Records Chart Data
    $stmt = $conn->prepare("SELECT YEAR(date_submitted) as year, COUNT(*) as count FROM applications GROUP BY YEAR(date_submitted) ORDER BY year ASC");
    $stmt->execute();
    $response['data']['yearly_records'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Stats Card Data
    $stmt = $conn->prepare("SELECT COUNT(*) FROM applications WHERE LOWER(status) = 'approved'");
    $stmt->execute();
    $response['data']['verified_applications'] = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COUNT(*) FROM applications WHERE LOWER(application_type) = 'senior citizen'");
    $stmt->execute();
    $response['data']['senior_citizen_records'] = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COUNT(*) FROM applications WHERE LOWER(application_type) = 'pwd'");
    $stmt->execute();
    $response['data']['pwd_records'] = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COUNT(*) FROM applications");
    $stmt->execute();
    $response['data']['total_processed'] = $stmt->fetchColumn();

} catch (PDOException $e) {
    $response['status'] = 'error';
    $response['message'] = 'Database error: ' . $e->getMessage();
    // Log the error for debugging
    error_log('Database error in get_realtime_data.php: ' . $e->getMessage());
}

echo json_encode($response);
?>