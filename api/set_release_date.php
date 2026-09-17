<?php
session_start();
header('Content-Type: application/json');
header('Cache-Control: private, no-store');
require_once '../includes/db_connect.php';
require_once '../includes/request_security.php';
require_once '../includes/audit_logger.php';
requireSameOriginMutation();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['department_admin', 'super_admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only a Department Administrator can set the expected release date.']);
    exit;
}

$appId = trim((string)($_POST['applicationId'] ?? ''));
$dateText = trim((string)($_POST['expectedReleaseDate'] ?? ''));
$clear = ($_POST['action'] ?? '') === 'clear';
if ($appId === '' || strlen($appId) > 255) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Select a valid application.']);
    exit;
}
if (!$clear) {
    $timezone = new DateTimeZone('Asia/Manila');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateText, $timezone);
    $today = new DateTimeImmutable('today', $timezone);
    if (!$date || $date->format('Y-m-d') !== $dateText || $date < $today) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Choose today or a future date for the expected release.']);
        exit;
    }
}

try {
    $conn->beginTransaction();
    $stmt = $conn->prepare('SELECT full_name, workflow_state, is_archived, expected_release_date FROM applications WHERE id_number = ? FOR UPDATE');
    $stmt->execute([$appId]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$app) {
        $conn->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Application not found.']);
        exit;
    }
    if (!empty($app['is_archived']) || !in_array($app['workflow_state'], ['Verified', 'Approved'], true)) {
        $conn->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Only active, verified applications can be scheduled. Refresh the record and try again.']);
        exit;
    }
    $newDate = $clear ? null : $dateText;
    $oldDate = $app['expected_release_date'] ?: null;
    if ($newDate === $oldDate) {
        $conn->rollBack();
        echo json_encode(['success' => true, 'expected_release_date' => $newDate, 'message' => 'The release schedule is already up to date.']);
        exit;
    }
    $update = $conn->prepare('UPDATE applications SET expected_release_date = ? WHERE id_number = ?');
    $update->execute([$newDate, $appId]);
    $comment = $newDate === null
        ? 'Expected release date removed.'
        : ($oldDate === null ? "Expected release date set to {$newDate}." : "Expected release date changed from {$oldDate} to {$newDate}.");
    $history = $conn->prepare('INSERT INTO application_history (application_id, previous_state, new_state, changed_by, comments) VALUES (?, ?, ?, ?, ?)');
    $history->execute([$appId, $app['workflow_state'], $app['workflow_state'], $_SESSION['username'] ?? 'System', $comment]);
    logAudit($conn, 'UPDATE_RELEASE_DATE', "{$comment} Application {$appId} ({$app['full_name']}).");
    $conn->commit();
    echo json_encode(['success' => true, 'expected_release_date' => $newDate, 'message' => $comment]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log('Release schedule update failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'The release date could not be saved. Please try again.']);
}
