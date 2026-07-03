<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db_connect.php';

// Authentication check (ensure only authorized roles can delete)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['barangay_staff', 'department_admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (isset($_POST['id'])) {
    $id = $_POST['id'];

    try {
        $sql = "DELETE FROM applications WHERE id_number = ?";
        $stmt = $conn->prepare($sql);
        
        if ($stmt->execute([$id])) {
            // Check if any row was actually deleted
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Application deleted successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Application not found with the given ID.']);
            }
        } else {
            // This part is for robustness, actual errors are caught below
            throw new Exception($stmt->errorInfo()[2]);
        }
    } catch (Exception $e) {
        error_log('Error in delete_application.php: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again later.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No application ID provided.']);
}
?>