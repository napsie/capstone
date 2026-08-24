<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db_connect.php';
require_once '../includes/request_security.php';
requireSameOriginMutation();
require_once '../includes/audit_logger.php';

// Check if logged in and is department_admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'department_admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$id = filter_var($_POST['id'] ?? '', FILTER_SANITIZE_NUMBER_INT);

if ($id) {
    try {
        $fetchStmt = $conn->prepare("SELECT username, first_name, last_name, role, barangay FROM users WHERE id = :id");
        $fetchStmt->execute(['id' => $id]);
        $targetUser = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        if (!$targetUser) {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE users SET is_archived = 0, archived_at = NULL, archived_by = NULL WHERE id = :id");
        $stmt->execute(['id' => $id]);

        if ($stmt->rowCount() > 0) {
            logAudit($conn, 'RESTORE_USER', "Restored user account '{$targetUser['username']}' ({$targetUser['first_name']} {$targetUser['last_name']}, Role: {$targetUser['role']})", (int)$id);
            echo json_encode(['success' => true, 'message' => 'User account restored successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'User was not archived or could not be restored.']);
        }
    } catch (PDOException $e) {
        error_log("Error restoring user: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
}
