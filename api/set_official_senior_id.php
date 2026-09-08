<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/request_security.php';
require_once '../includes/audit_logger.php';
requireSameOriginMutation();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['department_admin', 'super_admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only an authorized department administrator can assign an official Senior Citizen ID.']);
    exit;
}
$applicationId = trim((string)($_POST['applicationId'] ?? ''));
$seniorId = strtoupper(trim((string)($_POST['seniorIdNo'] ?? '')));
if ($applicationId === '' || !preg_match('/^[A-Z0-9][A-Z0-9 -]{2,49}$/', $seniorId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Enter a valid official Senior Citizen ID number.']);
    exit;
}
if ($seniorId === strtoupper($applicationId) || preg_match('/^(PRX|PEN)-/i', $seniorId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'The Senior Citizen ID must be different from the PRX/PEN application token.']);
    exit;
}
try {
    $conn->beginTransaction();
    $recordStmt = $conn->prepare("SELECT full_name, senior_id_no FROM applications WHERE id_number = ? AND application_type = 'senior' AND workflow_state IN ('Verified','Approved','Released') AND COALESCE(is_archived,0)=0 FOR UPDATE");
    $recordStmt->execute([$applicationId]);
    $record = $recordStmt->fetch(PDO::FETCH_ASSOC);
    if (!$record) throw new RuntimeException('Approved Senior Citizen ID application not found.');
    // Benefit applications intentionally reuse their owner's Senior Citizen ID.
    // Only another base Senior ID record constitutes a duplicate assignment.
    $duplicate = $conn->prepare("SELECT 1 FROM applications WHERE application_type = 'senior' AND senior_id_no = ? AND id_number <> ? LIMIT 1");
    $duplicate->execute([$seniorId, $applicationId]);
    if ($duplicate->fetchColumn()) throw new DomainException('That Senior Citizen ID number is already assigned to another record.');
    $update = $conn->prepare('UPDATE applications SET senior_id_no = ? WHERE id_number = ?');
    $update->execute([$seniorId, $applicationId]);
    $history = $conn->prepare("INSERT INTO application_history (application_id, previous_state, new_state, changed_by, comments) VALUES (?, 'Verified', 'Verified', ?, ?)");
    $actor = $_SESSION['username'] ?? 'Department Admin';
    $history->execute([$applicationId, $actor, 'Official Senior Citizen ID assigned/corrected: ' . $seniorId]);
    logAudit($conn, 'ASSIGN_SENIOR_ID', "Assigned official Senior Citizen ID {$seniorId} to application {$applicationId}.");
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Official Senior Citizen ID saved.']);
} catch (DomainException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(409); echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log('Senior ID assignment failed: ' . $e->getMessage());
    http_response_code(400); echo json_encode(['success' => false, 'message' => $e instanceof RuntimeException ? $e->getMessage() : 'Unable to save the Senior Citizen ID.']);
}
