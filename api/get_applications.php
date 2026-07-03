<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db_connect.php';

// Authenticate and authorize
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'department_admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$response = [
    'success' => true,
    'applications' => [],
    'pagination' => [
        'currentPage' => 1,
        'totalPages' => 1,
        'totalRecords' => 0
    ]
];

try {
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    $recordsPerPage = 15;
    $offset = ($page - 1) * $recordsPerPage;

    // Get total number of records
    $totalQuery = "SELECT COUNT(*) FROM applications";
    $totalStmt = $conn->prepare($totalQuery);
    $totalStmt->execute();
    $totalRecords = $totalStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $recordsPerPage);

    // Get applications for the current page
    $recordsQuery = "SELECT id_number as id, full_name, application_type, barangay, date_submitted, status FROM applications ORDER BY date_submitted DESC LIMIT :limit OFFSET :offset";
    $recordsStmt = $conn->prepare($recordsQuery);
    $recordsStmt->bindParam(':limit', $recordsPerPage, PDO::PARAM_INT);
    $recordsStmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $recordsStmt->execute();
    $applications = $recordsStmt->fetchAll(PDO::FETCH_ASSOC);

    $response['applications'] = $applications;
    $response['pagination']['currentPage'] = $page;
    $response['pagination']['totalPages'] = $totalPages;
    $response['pagination']['totalRecords'] = $totalRecords;

} catch (PDOException $e) {
    $response['success'] = false;
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log("API Error in get_applications.php: " . $e->getMessage());
}

echo json_encode($response);
?>