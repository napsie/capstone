<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/request_security.php';
require_once '../includes/audit_logger.php';
require_once '../includes/data_normalizer.php';
requireSameOriginMutation();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['department_admin', 'super_admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only an authorized department administrator can assign an official Senior Citizen ID.']);
    exit;
}
$applicationId = trim((string)($_POST['applicationId'] ?? ''));
$generate = (string)($_POST['generate'] ?? '') === '1';
$seniorId = $generate ? '' : normalizeSeniorId($_POST['seniorIdNo'] ?? '');
if ($applicationId === '' || (!$generate && !preg_match('/^[A-Z0-9][A-Z0-9 -]{2,49}$/', $seniorId))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Enter a valid official Senior Citizen ID number.']);
    exit;
}
if (!$generate && ($seniorId === strtoupper($applicationId) || preg_match('/^(PRX|PEN)-/i', $seniorId))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'The Senior Citizen ID must be different from the permanent PRX application token.']);
    exit;
}
try {
    $lockStmt = $conn->query("SELECT GET_LOCK('seniorlink_generate_senior_id', 10)");
    if ((int)$lockStmt->fetchColumn() !== 1) throw new RuntimeException('The ID generator is busy. Please try again.');
    $conn->beginTransaction();
    $recordStmt = $conn->prepare("SELECT full_name, senior_id_no FROM applications WHERE id_number = ? AND application_type = 'senior' AND workflow_state IN ('Verified','Approved','Released') AND COALESCE(is_archived,0)=0 FOR UPDATE");
    $recordStmt->execute([$applicationId]);
    $record = $recordStmt->fetch(PDO::FETCH_ASSOC);
    if (!$record) throw new RuntimeException('Approved Senior Citizen ID application not found.');
    $existingId = trim((string)($record['senior_id_no'] ?? ''));
    $hasOfficialId = $existingId !== '' && !preg_match('/^OSCA-[0-9]{4}-[0-9A-F]{6}$/i', $existingId);
    if ($generate && $hasOfficialId) throw new DomainException('This senior already has an issued Senior Citizen ID.');
    if ($generate) {
        // Official IDs are numeric-only and issued sequentially. The named
        // database lock above prevents two administrators from receiving the
        // same next number when generating IDs at the same time.
        $sequenceStmt = $conn->query("SELECT COALESCE(MAX(CAST(senior_id_no AS UNSIGNED)), 0)
            FROM applications
            WHERE application_type = 'senior'
              AND senior_id_no REGEXP '^[0-9]+$'");
        $nextNumber = (int)$sequenceStmt->fetchColumn() + 1;
        $duplicate = $conn->prepare("SELECT 1 FROM applications WHERE application_type = 'senior' AND senior_id_no = ? LIMIT 1");
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = str_pad((string)($nextNumber + $attempt), 5, '0', STR_PAD_LEFT);
            $duplicate->execute([$candidate]);
            if (!$duplicate->fetchColumn()) { $seniorId = $candidate; break; }
        }
        if ($seniorId === '') throw new RuntimeException('Unable to create a unique Senior Citizen ID. Please try again.');
    }
    // Benefit applications intentionally reuse their owner's Senior Citizen ID.
    // Only another base Senior ID record constitutes a duplicate assignment.
    $duplicate = $conn->prepare("SELECT 1 FROM applications WHERE application_type = 'senior' AND senior_id_no = ? AND id_number <> ? LIMIT 1");
    $duplicate->execute([$seniorId, $applicationId]);
    if ($duplicate->fetchColumn()) throw new DomainException('That Senior Citizen ID number is already assigned to another record.');
    $update = $conn->prepare('UPDATE applications SET senior_id_no = ? WHERE id_number = ? OR parent_senior_id = ?');
    $update->execute([$seniorId, $applicationId, $applicationId]);
    $history = $conn->prepare("INSERT INTO application_history (application_id, previous_state, new_state, changed_by, comments) VALUES (?, 'Verified', 'Verified', ?, ?)");
    $actor = $_SESSION['username'] ?? 'Department Admin';
    $history->execute([$applicationId, $actor, 'Official Senior Citizen ID assigned/corrected: ' . $seniorId]);
    logAudit($conn, 'ASSIGN_SENIOR_ID', "Assigned official Senior Citizen ID {$seniorId} to application {$applicationId}.");
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Official Senior Citizen ID saved.', 'seniorIdNo' => $seniorId]);
} catch (DomainException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(409); echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log('Senior ID assignment failed: ' . $e->getMessage());
    http_response_code(400); echo json_encode(['success' => false, 'message' => $e instanceof RuntimeException ? $e->getMessage() : 'Unable to save the Senior Citizen ID.']);
} finally {
    try { $conn->query("SELECT RELEASE_LOCK('seniorlink_generate_senior_id')"); } catch (Throwable $ignored) {}
}
