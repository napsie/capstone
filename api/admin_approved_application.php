<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db_connect.php';

// Authentication check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['department_admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Application ID is missing.']);
    exit;
}

$appId = $_POST['id'];

try {
    $sql = "UPDATE applications SET status = 'approved' WHERE id_number = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt->execute([$appId])) {
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Application approved successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Application not found or status was already approved.']);
        }
    } else {
        throw new Exception($stmt->errorInfo()[2]);
    }
} catch (Exception $e) {
    // Log the error for debugging
    error_log('Error in admin_approved_application.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again later.']);
}

$stmt = null;
$conn = null;
?>