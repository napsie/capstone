<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db_connect.php';
require_once '../includes/request_security.php';
requireSameOriginMutation();
require_once '../includes/audit_logger.php';

// Authentication check
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
        $conn->beginTransaction();
        $where = 'id_number = ?';
        $params = [$id];
        if (($_SESSION['role'] ?? '') === 'barangay_staff') {
            $where .= " AND barangay = ?";
            $params[] = $_SESSION['barangay'] ?? '';
        }

        $fetchStmt = $conn->prepare("SELECT full_name, barangay, application_type, workflow_state, status, is_archived FROM applications WHERE $where FOR UPDATE");
        $fetchStmt->execute($params);
        $app = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        if (!$app) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Application was not found or is outside your scope.']);
            exit;
        }
        $wasRejected = ($app['workflow_state'] ?? '') === 'Rejected' || strtolower((string)($app['status'] ?? '')) === 'rejected';
        if (empty($app['is_archived']) && $wasRejected && !in_array($_SESSION['role'] ?? '', ['department_admin', 'super_admin'], true)) {
            $conn->rollBack();
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only a Department Administrator can reopen this rejected application.']);
            exit;
        }
        if (empty($app['is_archived']) && !$wasRejected) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Application is already active.']);
            exit;
        }
        $newState = $wasRejected ? 'For Review' : ($app['workflow_state'] ?: 'Received');
        $updateStmt = $conn->prepare("UPDATE applications SET is_archived = 0, archived_at = NULL, archived_by = NULL,
            workflow_state = ?, status = CASE WHEN ? = 1 THEN 'pending' ELSE status END,
            return_reason = CASE WHEN ? = 1 THEN NULL ELSE return_reason END
            WHERE $where AND (is_archived = 1 OR workflow_state = 'Rejected' OR LOWER(COALESCE(status, '')) = 'rejected')");
        $updateStmt->execute([$newState, (int)$wasRejected, (int)$wasRejected, ...$params]);
        if ($updateStmt->rowCount() !== 1) {
            throw new RuntimeException('Application was not restored.');
        }
        if ($wasRejected) {
            $history = $conn->prepare("INSERT INTO application_history (application_id, previous_state, new_state, changed_by, comments) VALUES (?, 'Rejected', 'For Review', ?, ?)");
            $history->execute([$id, $_SESSION['username'] ?? 'System', 'Restored from archive and reopened for department review.']);
        }
        logAudit($conn, 'RESTORE_APPLICATION', "Restored application {$id} ({$app['full_name']} - " . ucfirst($app['application_type']) . " in Barangay {$app['barangay']})" . ($wasRejected ? ' and reopened for review' : ''));
        $conn->commit();
        echo json_encode(['success' => true, 'message' => $wasRejected
            ? 'Application restored to For Review. You can review, request correction, verify, or reject it again.'
            : 'Application restored successfully.']);
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        error_log('Error in restore_application.php: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again later.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No application ID provided.']);
}
