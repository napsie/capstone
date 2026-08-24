<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db_connect.php';
require_once '../includes/audit_logger.php';
require_once '../includes/request_security.php';
requireSameOriginMutation();

// Authentication check (ensure only authorized roles can archive)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['barangay_staff', 'department_admin', 'super_admin'])) {
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

if (isset($_POST['id'])) {
    $id = trim((string)$_POST['id']);
    if ($id === '') {
        echo json_encode(['success' => false, 'message' => 'Application ID is empty.']);
        exit;
    }

    try {
        $where = 'id_number = ?';
        $params = [$id];
        if (($_SESSION['role'] ?? '') === 'barangay_staff') {
            $where .= " AND barangay = ?";
            $params[] = $_SESSION['barangay'] ?? '';
        }

        // Fetch application details first for audit log
        $fetchStmt = $conn->prepare("SELECT full_name, barangay, application_type FROM applications WHERE $where");
        $fetchStmt->execute($params);
        $app = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        if (!$app) {
            echo json_encode(['success' => false, 'message' => 'Application was not found or is outside your scope.']);
            exit;
        }

        $username = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
        if ($username === '') $username = $_SESSION['username'] ?? 'Unknown User';
        $updateStmt = $conn->prepare("UPDATE applications SET is_archived = 1, archived_at = NOW(), archived_by = ? WHERE $where");
        $updateParams = array_merge([$username], $params);

        if ($updateStmt->execute($updateParams)) {
            if ($updateStmt->rowCount() > 0) {
                logAudit($conn, 'ARCHIVE_APPLICATION', "Archived application {$id} ({$app['full_name']} - " . ucfirst($app['application_type']) . " in Barangay {$app['barangay']})");
                echo json_encode(['success' => true, 'message' => 'Application archived successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Application is already archived or could not be updated.']);
            }
        } else {
            throw new Exception($updateStmt->errorInfo()[2]);
        }
    } catch (Exception $e) {
        error_log('Error in archive_application.php: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again later.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No application ID provided.']);
}
