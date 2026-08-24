<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/audit_logger.php';
require_once '../includes/sms_service.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['barangay_staff', 'department_admin'], true)) {
    header('Location: ../index.php');
    exit;
}

$isDepartment = ($_SESSION['role'] ?? '') === 'department_admin';
$barangay = $_SESSION['barangay'] ?? '';
$csrfToken = $_SESSION['field_operations_csrf'] ?? bin2hex(random_bytes(24));
$_SESSION['field_operations_csrf'] = $csrfToken;
$operationsAnchor = '';

function operationsRedirect(string $message, bool $success = true): void {
    global $operationsAnchor;
    $_SESSION['field_operations_notice'] = ['message' => $message, 'success' => $success];
    $anchor = $operationsAnchor !== '' ? '#' . rawurlencode($operationsAnchor) : '';
    header('Location: field_operations.php' . $anchor);
    exit;
}

function requireOperationsCsrf(): void {
    $sent = (string)($_POST['csrf_token'] ?? '');
    $saved = (string)($_SESSION['field_operations_csrf'] ?? '');
    if ($saved === '' || !hash_equals($saved, $sent)) {
        operationsRedirect('Your session expired. Please try again.', false);
    }
}

function fetchScopedApplication(PDO $conn, string $id, bool $isDepartment, string $barangay): ?array {
    if ($isDepartment) {
        $stmt = $conn->prepare('SELECT * FROM applications WHERE id_number = ? AND COALESCE(is_archived, 0) = 0');
        $stmt->execute([$id]);
    } else {
        $stmt = $conn->prepare('SELECT * FROM applications WHERE id_number = ? AND barangay = ? AND COALESCE(is_archived, 0) = 0');
        $stmt->execute([$id, $barangay]);
    }
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireOperationsCsrf();
    $action = (string)($_POST['action'] ?? '');
    if (in_array($action, ['create_batch', 'release_batch', 'receive_batch', 'update_batch_item'], true)) {
        $operationsAnchor = 'batches';
    } elseif (in_array($action, ['add_personnel', 'toggle_personnel'], true)) {
        $operationsAnchor = 'personnel';
    } else {
        $operationsAnchor = 'visits';
    }

    try {
        if ($action === 'add_personnel') {
            if (!$isDepartment) operationsRedirect('Only department administrators can add personnel.', false);
            $name = trim(strip_tags((string)($_POST['full_name'] ?? '')));
            $position = trim(strip_tags((string)($_POST['position'] ?? '')));
            $contact = trim(strip_tags((string)($_POST['contact_number'] ?? '')));
            $assignedBarangay = trim(strip_tags((string)($_POST['barangay'] ?? '')));
            if ($name === '') operationsRedirect('Personnel name is required.', false);

            $stmt = $conn->prepare('INSERT INTO home_visit_personnel (full_name, position, contact_number, barangay, created_by) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$name, $position ?: null, $contact ?: null, $assignedBarangay ?: null, $_SESSION['user_id']]);
            logAudit($conn, 'ADD_HOME_VISIT_PERSONNEL', "Added home visit personnel: {$name}.");
            operationsRedirect('Home visit personnel added.');
        }

        if ($action === 'toggle_personnel') {
            if (!$isDepartment) operationsRedirect('Only department administrators can update personnel.', false);
            $personnelId = filter_var($_POST['personnel_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$personnelId) operationsRedirect('Invalid personnel record.', false);
            $stmt = $conn->prepare('UPDATE home_visit_personnel SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ?');
            $stmt->execute([$personnelId]);
            logAudit($conn, 'TOGGLE_HOME_VISIT_PERSONNEL', "Changed active status for personnel #{$personnelId}.");
            operationsRedirect('Personnel availability updated.');
        }

        if ($action === 'save_visit') {
            $applicationId = trim((string)($_POST['application_id'] ?? ''));
            $app = fetchScopedApplication($conn, $applicationId, $isDepartment, $barangay);
            if (!$app || ($app['application_type'] ?? '') !== 'pension') operationsRedirect('Local pension application not found.', false);

            $eligibility = (string)($_POST['eligibility'] ?? '');
            $reason = trim(strip_tags((string)($_POST['reason'] ?? '')));
            $notes = trim(strip_tags((string)($_POST['notes'] ?? '')));
            if (!in_array($eligibility, ['Eligible', 'Not Eligible'], true)) operationsRedirect('Select a valid eligibility decision.', false);

            $schedule = null;
            $personnelId = null;
            $status = 'Not Eligible';
            if ($eligibility === 'Eligible') {
                $personnelId = filter_var($_POST['personnel_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
                $rawSchedule = trim((string)($_POST['scheduled_at'] ?? ''));
                $tz = new DateTimeZone('Asia/Manila');
                $date = DateTime::createFromFormat('Y-m-d\TH:i', $rawSchedule, $tz);
                $errors = DateTime::getLastErrors();
                $validDate = $date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
                if ($reason === '') operationsRedirect('Enter the reason for home visit eligibility.', false);
                if (!$personnelId) operationsRedirect('Assign personnel to the visit.', false);
                if (!$validDate || $date <= new DateTime('now', $tz)) operationsRedirect('Choose a future visit date and time.', false);
                if (in_array($date->format('N'), ['6', '7'], true) || (int)$date->format('H') < 8 || (int)$date->format('H') >= 17 || !in_array($date->format('i'), ['00', '30'], true)) {
                    operationsRedirect('Visits must be Monday-Friday, 8:00 AM-4:30 PM, in 30-minute slots.', false);
                }
                if ($isDepartment) {
                    $personStmt = $conn->prepare('SELECT COUNT(*) FROM home_visit_personnel WHERE id = ? AND is_active = 1');
                    $personStmt->execute([$personnelId]);
                } else {
                    $personStmt = $conn->prepare("SELECT COUNT(*) FROM home_visit_personnel WHERE id = ? AND is_active = 1 AND (barangay IS NULL OR barangay = '' OR barangay = ?)");
                    $personStmt->execute([$personnelId, $barangay]);
                }
                if ((int)$personStmt->fetchColumn() === 0) operationsRedirect('Selected personnel is unavailable.', false);
                $schedule = $date->format('Y-m-d H:i:s');
                $conflictStmt = $conn->prepare("SELECT COUNT(*) FROM applications WHERE home_visit_personnel_id = ? AND home_visit_scheduled_at = ? AND id_number <> ? AND COALESCE(home_visit_status, '') NOT IN ('Cancelled', 'Completed')");
                $conflictStmt->execute([$personnelId, $schedule, $applicationId]);
                if ((int)$conflictStmt->fetchColumn() > 0) operationsRedirect('That personnel already has a visit at the selected time.', false);
                $status = 'Scheduled';
            }

            $stmt = $conn->prepare('UPDATE applications SET home_visit_eligibility = ?, home_visit_eligibility_reason = ?, home_visit_personnel_id = ?, home_visit_scheduled_at = ?, home_visit_status = ?, home_visit_notes = ?, home_visit_assessed_by = ?, home_visit_assessed_at = CURRENT_TIMESTAMP, home_visit_completed_at = NULL WHERE id_number = ?');
            $stmt->execute([$eligibility, $reason ?: null, $personnelId, $schedule, $status, $notes ?: null, $_SESSION['user_id'], $applicationId]);
            logAudit($conn, 'SAVE_HOME_VISIT_ASSESSMENT', "{$applicationId}: {$eligibility}; status {$status}.");

            if ($schedule && !empty($app['contact_number'])) {
                try {
                    $label = (new DateTime($schedule, new DateTimeZone('Asia/Manila')))->format('M j, Y g:i A');
                    $sms = "SENIORLINK: Home visit for {$applicationId} is scheduled on {$label}. Please keep your phone available.";
                    $result = sendOrQueueSms($conn, $applicationId, $app['contact_number'], $sms);
                    $smsStmt = $conn->prepare('UPDATE applications SET sms_notification_status = ? WHERE id_number = ?');
                    $smsStmt->execute([$result['status'], $applicationId]);
                } catch (Throwable $ignored) {
                    // The visit remains saved even if the optional SMS provider is unavailable.
                }
            }
            operationsRedirect('Home visit assessment saved.');
        }

        if ($action === 'update_visit_status') {
            $applicationId = trim((string)($_POST['application_id'] ?? ''));
            $app = fetchScopedApplication($conn, $applicationId, $isDepartment, $barangay);
            if (!$app) operationsRedirect('Application not found.', false);
            $status = (string)($_POST['visit_status'] ?? '');
            $allowed = ['Scheduled', 'In Progress', 'Completed', 'Cancelled'];
            if (!in_array($status, $allowed, true)) operationsRedirect('Invalid visit status.', false);
            $notes = trim(strip_tags((string)($_POST['notes'] ?? '')));
            if ($status === 'Completed' && $notes === '') operationsRedirect('Completion notes are required.', false);
            $stmt = $conn->prepare("UPDATE applications SET home_visit_status = ?, home_visit_notes = ?, home_visit_completed_at = CASE WHEN ? = 'Completed' THEN CURRENT_TIMESTAMP ELSE NULL END WHERE id_number = ?");
            $stmt->execute([$status, $notes ?: ($app['home_visit_notes'] ?? null), $status, $applicationId]);
            logAudit($conn, 'UPDATE_HOME_VISIT_STATUS', "{$applicationId}: home visit changed to {$status}.");
            operationsRedirect('Home visit status updated.');
        }

        if ($action === 'create_batch') {
            if ($isDepartment) operationsRedirect('Hardcopy batches must be created by barangay staff.', false);
            $handoverDate = trim((string)($_POST['handover_date'] ?? ''));
            $submittedBy = trim(strip_tags((string)($_POST['submitted_by_name'] ?? '')));
            $remarks = trim(strip_tags((string)($_POST['remarks'] ?? '')));
            $applicationIds = array_values(array_unique(array_filter(array_map('trim', (array)($_POST['application_ids'] ?? [])))));
            $date = DateTime::createFromFormat('Y-m-d', $handoverDate);
            if (!$date || $date->format('Y-m-d') !== $handoverDate) operationsRedirect('Select a valid handover date.', false);
            if ($submittedBy === '') operationsRedirect('Enter the name of the person carrying the hardcopies.', false);
            if (!$applicationIds) operationsRedirect('Select at least one application for the batch.', false);

            $placeholders = implode(',', array_fill(0, count($applicationIds), '?'));
            $check = $conn->prepare("SELECT id_number FROM applications WHERE barangay = ? AND workflow_state = 'For Review' AND COALESCE(is_archived, 0) = 0 AND id_number IN ({$placeholders})");
            $check->execute(array_merge([$barangay], $applicationIds));
            $validIds = $check->fetchAll(PDO::FETCH_COLUMN);
            if (count($validIds) !== count($applicationIds)) operationsRedirect('One or more selected applications are not ready for department handover.', false);

            $conn->beginTransaction();
            $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $barangay), 0, 3)) ?: 'BRG';
            $countStmt = $conn->prepare('SELECT COUNT(*) FROM hardcopy_batches WHERE barangay = ? AND handover_date = ?');
            $countStmt->execute([$barangay, $handoverDate]);
            $sequence = (int)$countStmt->fetchColumn() + 1;
            $batchCode = sprintf('HC-%s-%s-%03d', $date->format('Ymd'), $prefix, $sequence);
            $batchStmt = $conn->prepare('INSERT INTO hardcopy_batches (batch_code, barangay, handover_date, status, submitted_by_name, created_by_user_id, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $batchStmt->execute([$batchCode, $barangay, $handoverDate, 'Draft', $submittedBy, $_SESSION['user_id'], $remarks ?: null]);
            $batchId = (int)$conn->lastInsertId();
            $itemStmt = $conn->prepare('INSERT INTO hardcopy_batch_items (batch_id, application_id) VALUES (?, ?)');
            foreach ($validIds as $id) $itemStmt->execute([$batchId, $id]);
            $conn->commit();
            logAudit($conn, 'CREATE_HARDCOPY_BATCH', "Created {$batchCode} with " . count($validIds) . ' applications.');
            operationsRedirect("Batch {$batchCode} created as Draft.");
        }

        if ($action === 'release_batch') {
            if ($isDepartment) operationsRedirect('Only barangay staff can release a batch.', false);
            $batchId = filter_var($_POST['batch_id'] ?? null, FILTER_VALIDATE_INT);
            $stmt = $conn->prepare("UPDATE hardcopy_batches SET status = 'Released', released_at = CURRENT_TIMESTAMP WHERE id = ? AND barangay = ? AND status = 'Draft'");
            $stmt->execute([$batchId, $barangay]);
            if ($stmt->rowCount() === 0) operationsRedirect('Draft batch not found.', false);
            logAudit($conn, 'RELEASE_HARDCOPY_BATCH', "Released hardcopy batch #{$batchId}.");
            operationsRedirect('Batch marked as released to the main office.');
        }

        if ($action === 'receive_batch') {
            if (!$isDepartment) operationsRedirect('Only department administrators can receive a batch.', false);
            $batchId = filter_var($_POST['batch_id'] ?? null, FILTER_VALIDATE_INT);
            $stmt = $conn->prepare("UPDATE hardcopy_batches SET status = 'Received', received_by_user_id = ?, received_at = CURRENT_TIMESTAMP WHERE id = ? AND status IN ('Released', 'Incomplete')");
            $stmt->execute([$_SESSION['user_id'], $batchId]);
            if ($stmt->rowCount() === 0) operationsRedirect('Released batch not found.', false);
            logAudit($conn, 'RECEIVE_HARDCOPY_BATCH', "Received hardcopy batch #{$batchId}.");
            operationsRedirect('Batch receipt recorded. Review each application document set.');
        }

        if ($action === 'update_batch_item') {
            if (!$isDepartment) operationsRedirect('Only department administrators can review received documents.', false);
            $itemId = filter_var($_POST['item_id'] ?? null, FILTER_VALIDATE_INT);
            $documentStatus = (string)($_POST['document_status'] ?? '');
            $remarks = trim(strip_tags((string)($_POST['remarks'] ?? '')));
            if (!in_array($documentStatus, ['Complete', 'Incomplete'], true)) operationsRedirect('Invalid document result.', false);
            if ($documentStatus === 'Incomplete' && $remarks === '') operationsRedirect('Describe the missing or incomplete documents.', false);
            $find = $conn->prepare("SELECT i.batch_id FROM hardcopy_batch_items i JOIN hardcopy_batches b ON b.id = i.batch_id WHERE i.id = ? AND b.status IN ('Received', 'Incomplete')");
            $find->execute([$itemId]);
            $batchId = (int)$find->fetchColumn();
            if (!$batchId) operationsRedirect('Batch item not found.', false);
            $stmt = $conn->prepare('UPDATE hardcopy_batch_items SET document_status = ?, remarks = ? WHERE id = ?');
            $stmt->execute([$documentStatus, $remarks ?: null, $itemId]);
            $summary = $conn->prepare("SELECT SUM(CASE WHEN document_status = 'Pending' THEN 1 ELSE 0 END) pending_count, SUM(CASE WHEN document_status = 'Incomplete' THEN 1 ELSE 0 END) incomplete_count FROM hardcopy_batch_items WHERE batch_id = ?");
            $summary->execute([$batchId]);
            $counts = $summary->fetch(PDO::FETCH_ASSOC);
            $batchStatus = (int)$counts['incomplete_count'] > 0 ? 'Incomplete' : ((int)$counts['pending_count'] === 0 ? 'Completed' : 'Received');
            $batchStmt = $conn->prepare('UPDATE hardcopy_batches SET status = ? WHERE id = ?');
            $batchStmt->execute([$batchStatus, $batchId]);
            logAudit($conn, 'REVIEW_HARDCOPY_ITEM', "Batch #{$batchId}, item #{$itemId}: {$documentStatus}.");
            operationsRedirect('Document result saved.');
        }
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        error_log('Field operations error: ' . $e->getMessage());
        operationsRedirect('The operation could not be completed. Please check the information and try again.', false);
    }
}

$notice = $_SESSION['field_operations_notice'] ?? null;
unset($_SESSION['field_operations_notice']);

$personnel = $conn->query('SELECT * FROM home_visit_personnel ORDER BY is_active DESC, full_name')->fetchAll(PDO::FETCH_ASSOC);
$activePersonnel = array_values(array_filter($personnel, static function ($p) use ($isDepartment, $barangay) {
    return (int)$p['is_active'] === 1
        && ($isDepartment || empty($p['barangay']) || $p['barangay'] === $barangay);
}));

$visitSql = "SELECT a.id_number, a.full_name, a.barangay, a.contact_number, a.workflow_state,
                    a.home_visit_eligibility, a.home_visit_eligibility_reason, a.home_visit_scheduled_at,
                    a.home_visit_status, a.home_visit_notes, a.home_visit_personnel_id,
                    p.full_name personnel_name
             FROM applications a
             LEFT JOIN home_visit_personnel p ON p.id = a.home_visit_personnel_id
             WHERE a.application_type = 'pension' AND COALESCE(a.is_archived, 0) = 0";
$visitParams = [];
if (!$isDepartment) {
    $visitSql .= ' AND a.barangay = ?';
    $visitParams[] = $barangay;
}
$visitSql .= ' ORDER BY CASE WHEN a.home_visit_scheduled_at IS NULL THEN 1 ELSE 0 END, a.home_visit_scheduled_at, a.date_submitted DESC';
$visitStmt = $conn->prepare($visitSql);
$visitStmt->execute($visitParams);
$visits = $visitStmt->fetchAll(PDO::FETCH_ASSOC);

$eligibleApps = [];
if (!$isDepartment) {
    $eligibleStmt = $conn->prepare("SELECT a.id_number, a.full_name, a.application_type, a.workflow_state
        FROM applications a
        WHERE a.barangay = ? AND a.workflow_state = 'For Review' AND COALESCE(a.is_archived, 0) = 0
          AND NOT EXISTS (SELECT 1 FROM hardcopy_batch_items bi WHERE bi.application_id = a.id_number)
        ORDER BY a.full_name");
    $eligibleStmt->execute([$barangay]);
    $eligibleApps = $eligibleStmt->fetchAll(PDO::FETCH_ASSOC);
}

$batchSql = "SELECT b.*, CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) received_by_name,
                    COUNT(i.id) item_count,
                    SUM(CASE WHEN i.document_status = 'Complete' THEN 1 ELSE 0 END) complete_count,
                    SUM(CASE WHEN i.document_status = 'Incomplete' THEN 1 ELSE 0 END) incomplete_count
             FROM hardcopy_batches b
             LEFT JOIN users u ON u.id = b.received_by_user_id
             LEFT JOIN hardcopy_batch_items i ON i.batch_id = b.id";
$batchParams = [];
if (!$isDepartment) {
    $batchSql .= ' WHERE b.barangay = ?';
    $batchParams[] = $barangay;
}
$batchSql .= ' GROUP BY b.id, u.first_name, u.last_name ORDER BY b.created_at DESC';
$batchStmt = $conn->prepare($batchSql);
$batchStmt->execute($batchParams);
$batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);

$batchStatusCounts = ['Draft' => 0, 'Released' => 0, 'Received' => 0, 'Incomplete' => 0, 'Completed' => 0];
foreach ($batches as $batchRow) {
    $batchRowStatus = (string)($batchRow['status'] ?? '');
    if (array_key_exists($batchRowStatus, $batchStatusCounts)) $batchStatusCounts[$batchRowStatus]++;
}

$itemsByBatch = [];
if ($batches) {
    $batchIds = array_column($batches, 'id');
    $marks = implode(',', array_fill(0, count($batchIds), '?'));
    $itemsStmt = $conn->prepare("SELECT i.*, a.full_name, a.application_type, a.workflow_state FROM hardcopy_batch_items i JOIN applications a ON a.id_number = i.application_id WHERE i.batch_id IN ({$marks}) ORDER BY a.full_name");
    $itemsStmt->execute($batchIds);
    foreach ($itemsStmt->fetchAll(PDO::FETCH_ASSOC) as $item) $itemsByBatch[$item['batch_id']][] = $item;
}

$sidebarPartial = $isDepartment ? '../partials/department_sidebar.php' : '../partials/barangay_sidebar.php';
$sidebarCss = $isDepartment ? '../assets/css/department-sidebar.css' : '../assets/css/barangay-sidebar.css';
$profilePic = $_SESSION['profile_picture'] ?? 'default.jpg';
$profilePath = '../images/profile_pictures/' . $profilePic;
if (!file_exists($profilePath) || is_dir($profilePath)) $profilePath = '../images/profile_pictures/default.jpg';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SENIORLINK - Field Operations</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($sidebarCss) ?>?v=4">
    <link rel="stylesheet" href="../assets/css/seniorlink-ui.css?v=11">
    <link rel="stylesheet" href="../assets/css/table-pagination.css?v=1">
    <script src="../assets/js/table-pagination.js?v=1" defer></script>
    <style>
        :root { --primary:#0f172a; --accent:#2563eb; --border:#dbe4ef; --muted:#64748b; --card:#fff; --bg:#f1f5f9; }
        * { box-sizing:border-box; }
        body { margin:0; background-color:var(--bg); color:var(--primary); font-family:Inter,'Segoe UI',sans-serif; }
        .main-content { padding:24px; }
        .notice { padding:12px 15px; margin-bottom:18px; border-radius:10px; border:1px solid; font-weight:600; }
        .notice.ok { color:#166534; background:#f0fdf4; border-color:#86efac; }
        .notice.error { color:#991b1b; background:#fef2f2; border-color:#fca5a5; }
        .tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:18px; }
        .tab-btn { border:1px solid var(--border); background:#fff; border-radius:9px; padding:10px 14px; font-weight:700; color:#334155; cursor:pointer; }
        .tab-btn.active { color:#fff; background:#2563eb; border-color:#2563eb; }
        .tab-panel { display:none; }
        .tab-panel.active { display:block; }
        .panel { background:#fff; border:1px solid var(--border); border-radius:14px; box-shadow:0 5px 18px rgba(15,23,42,.05); margin-bottom:18px; overflow:hidden; }
        .panel-head { padding:15px 18px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; gap:12px; }
        .panel-head h2 { margin:0; font-size:1rem; }
        .panel-body { padding:18px; }
        .form-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; }
        .field label { display:block; font-size:.72rem; color:#475569; font-weight:800; text-transform:uppercase; margin-bottom:4px; }
        .field input,.field select,.field textarea { width:100%; min-height:40px; padding:8px 10px; border:1px solid #cbd5e1; border-radius:8px; background:#fff; font:inherit; }
        .field textarea { min-height:74px; resize:vertical; }
        .full { grid-column:1/-1; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:7px; border:0; border-radius:8px; padding:9px 13px; font-weight:700; cursor:pointer; text-decoration:none; }
        .btn-primary { background:#2563eb; color:#fff; }
        .btn-success { background:#059669; color:#fff; }
        .btn-warning { background:#d97706; color:#fff; }
        .btn-muted { background:#e2e8f0; color:#334155; }
        .table-wrap { overflow:auto; }
        table { width:100%; border-collapse:collapse; min-width:900px; }
        th,td { padding:11px 12px; text-align:left; border-bottom:1px solid #e2e8f0; vertical-align:top; font-size:.82rem; }
        th { background:#f8fafc; color:#475569; font-size:.7rem; text-transform:uppercase; letter-spacing:.04em; }
        .badge { display:inline-flex; padding:4px 8px; border-radius:999px; font-size:.68rem; font-weight:800; background:#e2e8f0; color:#334155; }
        .badge.eligible,.badge.completed,.badge.complete,.badge.received { background:#dcfce7; color:#166534; }
        .badge.scheduled,.badge.released { background:#dbeafe; color:#1d4ed8; }
        .badge.incomplete,.badge.cancelled,.badge.not-eligible { background:#fee2e2; color:#991b1b; }
        details { border:1px solid #e2e8f0; border-radius:10px; margin:10px 0; overflow:hidden; }
        summary { cursor:pointer; padding:12px 14px; background:#f8fafc; font-weight:800; }
        .details-body { padding:14px; }
        .selection-list { max-height:310px; overflow:auto; border:1px solid #dbe4ef; border-radius:9px; }
        .selection-row { display:flex; align-items:center; gap:10px; padding:10px 12px; border-bottom:1px solid #edf2f7; }
        .selection-row:last-child { border:0; }
        .muted { color:var(--muted); font-size:.78rem; }
        .sr-only { position:absolute!important; width:1px!important; height:1px!important; padding:0!important; margin:-1px!important; overflow:hidden!important; clip:rect(0,0,0,0)!important; white-space:nowrap!important; border:0!important; }
        .notice { display:flex; align-items:center; gap:10px; }
        .notice i { flex:0 0 auto; font-size:1rem; }
        .tab-btn { min-height:44px; display:inline-flex; align-items:center; gap:8px; transition:background .18s,border-color .18s,color .18s,box-shadow .18s; }
        .tab-btn:hover:not(.active) { background:#f8fafc; border-color:#94a3b8; }
        .tab-btn:focus-visible,.btn:focus-visible,.text-action:focus-visible,summary:focus-visible,.batch-filters input:focus-visible,.batch-filters select:focus-visible { outline:3px solid rgba(37,99,235,.28); outline-offset:2px; }
        .btn { min-height:42px; transition:filter .18s,transform .18s,box-shadow .18s; }
        .btn:hover:not(:disabled) { filter:brightness(.94); transform:translateY(-1px); box-shadow:0 5px 13px rgba(15,23,42,.14); }
        .btn:disabled { opacity:.52; cursor:not-allowed; }

        /* Hardcopy batches: task-oriented and status-forward workspace. */
        .batch-workspace { --batch-blue:#1769aa; --batch-green:#15804a; --batch-amber:#b35c08; --batch-red:#b42318; }
        .batch-intro { display:flex; align-items:flex-start; justify-content:space-between; gap:24px; padding:24px 26px; margin-bottom:16px; color:#fff; background:linear-gradient(135deg,#123c67 0%,#1769aa 58%,#0f766e 145%); border-radius:16px; box-shadow:0 10px 28px rgba(15,61,103,.16); overflow:hidden; position:relative; }
        .batch-intro::after { content:''; position:absolute; width:250px; height:250px; right:-80px; top:-120px; border-radius:50%; background:rgba(255,255,255,.08); pointer-events:none; }
        .section-eyebrow { display:inline-flex; align-items:center; gap:7px; margin-bottom:7px; color:#bfdbfe; font-size:.72rem; font-weight:800; letter-spacing:.07em; text-transform:uppercase; }
        .batch-intro h2 { margin:0 0 6px; font-size:1.55rem; line-height:1.2; }
        .batch-intro p { max-width:690px; margin:0; color:rgba(255,255,255,.82); font-size:.88rem; line-height:1.6; }
        .batch-role-note { z-index:1; max-width:270px; display:flex; align-items:flex-start; gap:9px; padding:11px 13px; color:#e0f2fe; background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.18); border-radius:11px; font-size:.75rem; line-height:1.45; backdrop-filter:blur(8px); }
        .batch-role-note i { margin-top:2px; color:#7dd3fc; }

        .batch-stats { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:10px; margin-bottom:16px; }
        .batch-stat { min-width:0; display:flex; align-items:center; gap:11px; padding:13px 14px; background:#fff; border:1px solid #dce5ee; border-radius:12px; box-shadow:0 3px 12px rgba(15,23,42,.035); }
        .stat-icon { width:38px; height:38px; flex:0 0 38px; display:grid; place-items:center; border-radius:10px; }
        .stat-icon.all { color:#1d4ed8; background:#dbeafe; }
        .stat-icon.draft { color:#475569; background:#e2e8f0; }
        .stat-icon.transit { color:#9a4b06; background:#ffedd5; }
        .stat-icon.review { color:#6d28d9; background:#ede9fe; }
        .stat-icon.done { color:#166534; background:#dcfce7; }
        .batch-stat strong,.batch-stat small { display:block; }
        .batch-stat strong { color:#0f172a; font-size:1.12rem; line-height:1.1; }
        .batch-stat small { margin-top:3px; color:#64748b; font-size:.68rem; font-weight:700; white-space:nowrap; }

        .batch-flow { display:grid; grid-template-columns:1fr auto 1fr auto 1fr auto 1fr; align-items:center; gap:12px; padding:14px 18px; margin-bottom:18px; background:#eef6fb; border:1px solid #cfe1ee; border-radius:13px; }
        .batch-flow>div { min-width:0; display:grid; grid-template-columns:30px minmax(0,1fr); column-gap:9px; align-items:center; }
        .batch-flow>div>span { grid-row:1/3; width:30px; height:30px; display:grid; place-items:center; color:#fff; background:#1769aa; border-radius:50%; font-size:.72rem; font-weight:800; }
        .batch-flow strong { color:#183b56; font-size:.77rem; line-height:1.25; }
        .batch-flow small { color:#5f7487; font-size:.63rem; line-height:1.35; }
        .batch-flow>i { color:#8eabc0; font-size:.62rem; }

        .batch-create-panel,.batch-monitor-panel { border-color:#d7e2ec; box-shadow:0 7px 22px rgba(15,23,42,.05); }
        .batch-panel-head { padding:17px 20px; background:#fbfdff; }
        .batch-panel-head>div { min-width:0; }
        .panel-step { display:block; margin-bottom:4px; color:#1769aa; font-size:.65rem; font-weight:800; letter-spacing:.07em; text-transform:uppercase; }
        .batch-panel-head h2 { font-size:1.05rem; color:#152d42; }
        .batch-panel-head p { margin:5px 0 0; color:#64748b; font-size:.76rem; }
        .availability-count { flex:0 0 auto; padding:7px 10px; color:#31536d; background:#eaf2f8; border-radius:999px; font-size:.7rem; font-weight:700; }
        .availability-count strong { color:#1769aa; }

        .batch-form { gap:17px; }
        .batch-form .field label,.batch-selection-field legend { color:#263f54; font-size:.75rem; font-weight:750; text-transform:none; }
        .batch-form .field>small { display:block; margin-top:5px; color:#6b7f91; font-size:.68rem; line-height:1.4; }
        .batch-form input { min-height:44px; }
        .batch-form input:focus,.document-review-form input:focus,.document-review-form select:focus { outline:none; border-color:#1769aa; box-shadow:0 0 0 3px rgba(23,105,170,.13); }
        .optional-label { margin-left:5px; color:#64748b; font-size:.62rem; font-weight:600; }
        .batch-selection-field { min-width:0; padding:0; margin:0; border:0; }
        .batch-selection-field legend { margin-bottom:7px; }
        .selection-toolbar { min-height:42px; display:flex; align-items:center; justify-content:space-between; gap:12px; padding:8px 12px; color:#405a70; background:#edf5fa; border:1px solid #cfe0eb; border-bottom:0; border-radius:10px 10px 0 0; font-size:.72rem; font-weight:700; }
        .selection-toolbar>span:last-child { display:flex; gap:4px; }
        .text-action { min-height:32px; padding:5px 9px; color:#1769aa; background:transparent; border:0; border-radius:6px; font:inherit; cursor:pointer; }
        .text-action:hover { background:#dcecf6; }
        .batch-selection-field .selection-list { max-height:330px; border-color:#cfe0eb; border-radius:0 0 10px 10px; }
        .batch-selection-field .selection-row { position:relative; min-height:58px; padding:9px 12px; background:#fff; cursor:pointer; transition:background .15s; }
        .batch-selection-field .selection-row:hover { background:#f5fafe; }
        .batch-selection-field .selection-row:has(input:checked) { background:#eef7ff; box-shadow:inset 3px 0 #1769aa; }
        .batch-selection-field input[type=checkbox] { position:absolute; opacity:0; pointer-events:none; }
        .selection-check { width:21px; height:21px; flex:0 0 21px; display:grid; place-items:center; color:transparent; background:#fff; border:2px solid #93a8ba; border-radius:6px; font-size:.65rem; }
        .selection-row input:checked+.selection-check { color:#fff; background:#1769aa; border-color:#1769aa; }
        .selection-row input:focus-visible+.selection-check { outline:3px solid rgba(37,99,235,.28); outline-offset:2px; }
        .selection-row>span:last-child { min-width:0; }
        .selection-row strong { color:#1f3547; font-size:.78rem; }
        .selection-row small { display:block; margin-top:2px; color:#6b7f91; font-size:.67rem; }
        .batch-submit-row { display:flex; align-items:center; justify-content:space-between; gap:18px; padding-top:2px; }
        .batch-submit-row>span { color:#64748b; font-size:.7rem; }
        .batch-submit-row>span i { margin-right:5px; color:#1769aa; }

        .monitoring-head { border-bottom:0; }
        .batch-filters { display:flex; align-items:center; gap:9px; padding:0 20px 15px; background:#fbfdff; border-bottom:1px solid var(--border); }
        .batch-filters label { min-width:0; }
        .batch-filters input,.batch-filters select { height:42px; padding:8px 11px; color:#243b4f; background:#fff; border:1px solid #cbd8e3; border-radius:9px; font:inherit; font-size:.78rem; }
        .batch-search { position:relative; flex:1 1 320px; max-width:430px; }
        .batch-search i { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#7790a4; }
        .batch-search input { width:100%; padding-left:35px; }
        .batch-filters select { min-width:165px; }
        .batch-list { padding:12px 18px 18px; }
        .batch-card { margin:9px 0; border-color:#d8e3ec; border-radius:12px; box-shadow:0 2px 8px rgba(15,23,42,.025); }
        .batch-card[hidden] { display:none; }
        .batch-card summary { list-style:none; min-height:76px; display:grid; grid-template-columns:42px minmax(190px,1.2fr) minmax(190px,.9fr) auto 24px; align-items:center; gap:13px; padding:11px 14px; background:#fff; font-weight:400; transition:background .15s; }
        .batch-card summary::-webkit-details-marker { display:none; }
        .batch-card summary:hover { background:#f7fbfe; }
        .batch-card[open] summary { background:#f2f8fc; border-bottom:1px solid #d8e3ec; }
        .batch-summary-icon { width:42px; height:42px; display:grid; place-items:center; color:#1769aa; background:#e3f0f9; border-radius:10px; }
        .batch-summary-main,.batch-summary-info { min-width:0; display:flex; flex-direction:column; }
        .batch-summary-main strong { overflow:hidden; color:#143652; font-size:.85rem; text-overflow:ellipsis; white-space:nowrap; }
        .batch-summary-main small,.batch-summary-info small { color:#64798a; font-size:.68rem; font-weight:600; }
        .batch-summary-main small { margin-top:4px; }
        .batch-summary-main i { width:13px; color:#829aac; }
        .batch-summary-info { gap:3px; }
        .batch-card .badge { gap:6px; align-items:center; justify-self:end; }
        .batch-card .badge i,.batch-table .badge i { font-size:.38rem; }
        .summary-chevron { color:#71879a; transition:transform .18s; }
        .batch-card[open] .summary-chevron { transform:rotate(180deg); }
        .batch-details-body { padding:18px; background:#fff; }

        .batch-progress { max-width:660px; display:grid; grid-template-columns:48px minmax(24px,1fr) 48px minmax(24px,1fr) 48px minmax(24px,1fr) 48px; align-items:start; margin:0 auto 20px; }
        .batch-progress>span { display:flex; flex-direction:column; align-items:center; gap:5px; color:#8b9baa; }
        .batch-progress>span>i { width:30px; height:30px; display:grid; place-items:center; background:#eef2f5; border:2px solid #d9e1e7; border-radius:50%; font-size:.65rem; }
        .batch-progress>span small { font-size:.62rem; font-weight:700; }
        .batch-progress>b { height:3px; margin-top:14px; background:#d9e1e7; }
        .batch-progress>.done { color:#177944; }
        .batch-progress>span.done>i,.batch-progress>b.done { color:#fff; background:#21a35c; border-color:#21a35c; }
        .batch-progress>span.attention { color:#a94710; }
        .batch-progress>span.attention>i { color:#fff; background:#d97706; border-color:#d97706; }

        .batch-metadata { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:9px; margin-bottom:14px; }
        .batch-metadata>div { min-width:0; display:flex; align-items:flex-start; gap:9px; padding:11px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:9px; }
        .batch-metadata>div>i { width:18px; margin-top:2px; color:#4c7899; text-align:center; }
        .batch-metadata span { min-width:0; }
        .batch-metadata small,.batch-metadata strong,.batch-metadata em { display:block; }
        .batch-metadata small { color:#718396; font-size:.61rem; font-weight:700; text-transform:uppercase; letter-spacing:.035em; }
        .batch-metadata strong { margin-top:2px; overflow-wrap:anywhere; color:#263f54; font-size:.7rem; line-height:1.4; }
        .batch-metadata em { color:#64748b; font-size:.62rem; font-style:normal; }
        .batch-remarks { display:flex; align-items:flex-start; gap:9px; padding:10px 12px; margin-bottom:14px; color:#5d4a16; background:#fffbeb; border:1px solid #fde68a; border-radius:9px; font-size:.72rem; }
        .batch-remarks i { margin-top:2px; color:#b7791f; }
        .batch-remarks strong { display:block; margin-bottom:2px; }
        .batch-actions { display:flex; align-items:center; min-height:44px; margin:5px 0 16px; }
        .action-status { display:inline-flex; align-items:center; gap:7px; color:#356149; font-size:.72rem; font-weight:650; }
        .action-status i { color:#22a15d; }
        .batch-table-heading { display:flex; align-items:end; justify-content:space-between; padding-top:14px; margin-bottom:9px; border-top:1px solid #e2e8f0; }
        .batch-table-heading h3 { margin:0; color:#19364d; font-size:.86rem; }
        .batch-table-heading p { margin:3px 0 0; color:#64748b; font-size:.67rem; }
        .batch-table-wrap { border:1px solid #dfe7ee; border-radius:9px; }
        .batch-table { min-width:920px; }
        .batch-table th { background:#edf4f8; color:#38566d; }
        .application-code { color:#1d4f78; font-family:ui-monospace,SFMono-Regular,Consolas,monospace; font-size:.73rem; font-weight:700; }
        .document-review-form { min-width:360px; display:grid; grid-template-columns:105px minmax(150px,1fr) auto; align-items:end; gap:7px; }
        .document-review-form label>span { display:block; margin-bottom:3px; color:#596f81; font-size:.61rem; font-weight:700; }
        .document-review-form select,.document-review-form input { width:100%; min-height:38px; padding:7px 8px; color:#263f54; background:#fff; border:1px solid #cbd5e1; border-radius:7px; font:inherit; font-size:.72rem; }
        .document-review-form .btn { min-height:38px; padding:7px 10px; font-size:.69rem; }
        .batch-empty { min-height:150px; display:flex; align-items:center; justify-content:center; gap:14px; padding:28px; color:#61788b; text-align:left; }
        .batch-empty>i { width:46px; height:46px; flex:0 0 46px; display:grid; place-items:center; color:#477895; background:#eaf3f8; border-radius:50%; font-size:1.1rem; }
        .batch-empty strong { display:block; color:#28465c; font-size:.86rem; }
        .batch-empty p { max-width:430px; margin:4px 0 0; font-size:.72rem; line-height:1.5; }
        .batch-empty.compact { min-height:100px; justify-content:flex-start; }

        @media(max-width:1100px){
            .batch-stats { grid-template-columns:repeat(3,minmax(0,1fr)); }
            .batch-flow { grid-template-columns:1fr 1fr; gap:10px; }
            .batch-flow>i { display:none; }
            .batch-metadata { grid-template-columns:1fr 1fr; }
        }
        @media(max-width:900px){
            .form-grid{grid-template-columns:1fr 1fr;}
            .batch-intro { flex-direction:column; }
            .batch-role-note { max-width:none; }
            .batch-card summary { grid-template-columns:42px minmax(160px,1fr) auto 24px; }
            .batch-summary-info { display:none; }
        }
        @media(max-width:600px){
            .main-content{padding:14px}.form-grid{grid-template-columns:1fr}.full{grid-column:auto}
            .batch-intro { padding:20px; border-radius:13px; }
            .batch-intro h2 { font-size:1.3rem; }
            .batch-stats { grid-template-columns:1fr 1fr; }
            .batch-stat { padding:11px; }
            .batch-flow { grid-template-columns:1fr; }
            .batch-panel-head { align-items:flex-start; }
            .availability-count { white-space:nowrap; }
            .batch-submit-row { align-items:stretch; flex-direction:column; }
            .batch-submit-row .btn { width:100%; }
            .batch-filters { align-items:stretch; flex-direction:column; }
            .batch-search,.batch-filters select { width:100%; max-width:none; }
            .batch-list { padding:9px; }
            .batch-card summary { grid-template-columns:38px minmax(0,1fr) auto 20px; gap:9px; padding:10px; }
            .batch-summary-icon { width:38px; height:38px; }
            .batch-summary-main strong { font-size:.75rem; }
            .batch-card .badge { padding:4px 6px; font-size:.6rem; }
            .batch-details-body { padding:13px 10px; }
            .batch-progress { grid-template-columns:38px minmax(12px,1fr) 38px minmax(12px,1fr) 38px minmax(12px,1fr) 38px; }
            .batch-progress>span small { font-size:.53rem; }
            .batch-metadata { grid-template-columns:1fr; }
            .batch-actions .btn { width:100%; }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/system-header.css?v=1">
    <link rel="stylesheet" href="../assets/css/system-sidebar.css?v=3">
</head>
<body>
<div class="container">
    <?php include $sidebarPartial; ?>
    <div class="main-content">
        <div class="header">
            <div class="header-content">
                <div class="welcome-message">Operational scheduling and physical-document accountability</div>
                <h1>Field <span>Operations</span></h1>
            </div>
            <div class="header-actions">
                <div class="user-info">
                    <div class="user-avatar"><img src="<?= htmlspecialchars($profilePath) ?>" alt="Profile Picture"></div>
                    <div class="user-details">
                        <h2><?= htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')) ?></h2>
                        <p><?= $isDepartment ? 'Department Admin - Pasig City' : 'Barangay Staff - ' . htmlspecialchars($barangay) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($notice): ?><div class="notice <?= $notice['success'] ? 'ok' : 'error' ?>" role="<?= $notice['success'] ? 'status' : 'alert' ?>"><i class="fas <?= $notice['success'] ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>" aria-hidden="true"></i><span><?= htmlspecialchars($notice['message']) ?></span></div><?php endif; ?>

        <div class="tabs" role="tablist" aria-label="Field operations sections">
            <button class="tab-btn active" id="visitsTab" type="button" role="tab" aria-selected="true" aria-controls="tab-visits" data-tab="visits"><i class="fas fa-house-medical"></i> Home Visits</button>
            <button class="tab-btn" id="batchesTab" type="button" role="tab" aria-selected="false" aria-controls="tab-batches" data-tab="batches"><i class="fas fa-box"></i> Hardcopy Batches</button>
            <?php if ($isDepartment): ?><button class="tab-btn" id="personnelTab" type="button" role="tab" aria-selected="false" aria-controls="tab-personnel" data-tab="personnel"><i class="fas fa-user-nurse"></i> Personnel</button><?php endif; ?>
        </div>

        <section id="tab-visits" class="tab-panel active" role="tabpanel" aria-labelledby="visitsTab">
            <div class="panel">
                <div class="panel-head"><h2>Home Visit Monitoring</h2><span class="muted"><?= count($visits) ?> local pension record(s)</span></div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Senior</th><th>Barangay</th><th>Eligibility</th><th>Schedule</th><th>Personnel</th><th>Status</th><th>Manage</th></tr></thead>
                        <tbody data-paginate="10" data-pagination-label="Home visit pages">
                        <?php if (!$visits): ?><tr><td colspan="7">No local pension applications found.</td></tr><?php endif; ?>
                        <?php foreach ($visits as $visit): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($visit['full_name']) ?></strong><br><span class="muted"><?= htmlspecialchars($visit['id_number']) ?></span></td>
                                <td><?= htmlspecialchars($visit['barangay']) ?></td>
                                <td><span class="badge <?= strtolower(str_replace(' ', '-', $visit['home_visit_eligibility'] ?? 'pending')) ?>"><?= htmlspecialchars($visit['home_visit_eligibility'] ?: 'Pending') ?></span><br><span class="muted"><?= htmlspecialchars($visit['home_visit_eligibility_reason'] ?? '') ?></span></td>
                                <td><?= $visit['home_visit_scheduled_at'] ? htmlspecialchars(date('M j, Y g:i A', strtotime($visit['home_visit_scheduled_at']))) : '-' ?></td>
                                <td><?= htmlspecialchars($visit['personnel_name'] ?? '-') ?></td>
                                <td><span class="badge <?= strtolower(str_replace(' ', '-', $visit['home_visit_status'] ?? 'pending')) ?>"><?= htmlspecialchars($visit['home_visit_status'] ?: 'Pending') ?></span></td>
                                <td><button class="btn btn-primary" type="button" onclick='openVisit(<?= json_encode($visit, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Assess / Schedule</button></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="panel" id="visitEditor">
                <div class="panel-head"><h2>Assessment and Schedule</h2><span class="muted" id="visitEditorName">Select a senior above</span></div>
                <div class="panel-body">
                    <form method="post" class="form-grid" id="visitForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="save_visit">
                        <input type="hidden" name="application_id" id="visitApplicationId">
                        <div class="field"><label>Eligibility</label><select name="eligibility" id="visitEligibility" required onchange="toggleVisitFields()"><option value="">Select decision</option><option>Eligible</option><option>Not Eligible</option></select></div>
                        <div class="field"><label>Reason</label><input name="reason" id="visitReason" maxlength="255" placeholder="Reason for the decision"></div>
                        <div class="field visit-assignment"><label>Assigned Personnel</label><select name="personnel_id" id="visitPersonnel"><option value="">Select personnel</option><?php foreach ($activePersonnel as $p): ?><option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['full_name'] . (!empty($p['position']) ? ' - ' . $p['position'] : '')) ?></option><?php endforeach; ?></select></div>
                        <div class="field visit-assignment"><label>Schedule</label><input type="datetime-local" name="scheduled_at" id="visitSchedule" step="1800" onclick="if (this.showPicker && !this.disabled) this.showPicker()"></div>
                        <div class="field full" id="visitScheduleHelp"><span class="muted"><i class="fas fa-info-circle"></i> Select <strong>Eligible</strong> to enable the calendar and personnel fields.</span></div>
                        <div class="field full"><label>Assessment / Visit Notes</label><textarea name="notes" id="visitNotes" placeholder="Assessment notes, address instructions, or visit result"></textarea></div>
                        <div class="full"><button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Save Assessment</button></div>
                    </form>

                    <form method="post" class="form-grid" id="visitStatusForm" style="margin-top:16px;padding-top:16px;border-top:1px solid #e2e8f0;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="update_visit_status">
                        <input type="hidden" name="application_id" id="statusApplicationId">
                        <div class="field"><label>Visit Status</label><select name="visit_status" required><option>Scheduled</option><option>In Progress</option><option>Completed</option><option>Cancelled</option></select></div>
                        <div class="field" style="grid-column:span 2;"><label>Status / Completion Notes</label><input name="notes" placeholder="Required when completing a visit"></div>
                        <div class="full"><button class="btn btn-success" type="submit"><i class="fas fa-check"></i> Update Status</button></div>
                    </form>
                </div>
            </div>
        </section>

        <section id="tab-batches" class="tab-panel batch-workspace" role="tabpanel" aria-labelledby="batchesTab">
            <div class="batch-intro" aria-labelledby="batchPageTitle">
                <div>
                    <span class="section-eyebrow"><i class="fas fa-boxes-stacked" aria-hidden="true"></i> Physical document tracking</span>
                    <h2 id="batchPageTitle">Hardcopy Batches</h2>
                    <p><?= $isDepartment ? 'Monitor submissions from every barangay, confirm physical receipt, and record document completeness.' : 'Group reviewed applications, record who will deliver them, and monitor receipt by the main office.' ?></p>
                </div>
                <div class="batch-role-note"><i class="fas fa-circle-info" aria-hidden="true"></i><span><strong>Your role:</strong> <?= $isDepartment ? 'Receive and review batches' : 'Prepare and release batches' ?></span></div>
            </div>

            <div class="batch-stats" aria-label="Hardcopy batch summary">
                <div class="batch-stat"><span class="stat-icon all"><i class="fas fa-layer-group"></i></span><span><strong><?= count($batches) ?></strong><small>Total batches</small></span></div>
                <div class="batch-stat"><span class="stat-icon draft"><i class="fas fa-file-pen"></i></span><span><strong><?= $batchStatusCounts['Draft'] ?></strong><small>Draft</small></span></div>
                <div class="batch-stat"><span class="stat-icon transit"><i class="fas fa-truck-fast"></i></span><span><strong><?= $batchStatusCounts['Released'] ?></strong><small>In transit</small></span></div>
                <div class="batch-stat"><span class="stat-icon review"><i class="fas fa-clipboard-check"></i></span><span><strong><?= $batchStatusCounts['Received'] + $batchStatusCounts['Incomplete'] ?></strong><small>Needs review</small></span></div>
                <div class="batch-stat"><span class="stat-icon done"><i class="fas fa-circle-check"></i></span><span><strong><?= $batchStatusCounts['Completed'] ?></strong><small>Completed</small></span></div>
            </div>

            <div class="batch-flow" aria-label="Hardcopy batch workflow">
                <div><span>1</span><strong>Prepare</strong><small>Barangay groups applications</small></div>
                <i class="fas fa-chevron-right" aria-hidden="true"></i>
                <div><span>2</span><strong>Release</strong><small>Hardcopies leave the barangay</small></div>
                <i class="fas fa-chevron-right" aria-hidden="true"></i>
                <div><span>3</span><strong>Receive</strong><small>Main office confirms delivery</small></div>
                <i class="fas fa-chevron-right" aria-hidden="true"></i>
                <div><span>4</span><strong>Review</strong><small>Documents are checked per senior</small></div>
            </div>

            <?php if (!$isDepartment): ?>
            <div class="panel batch-create-panel">
                <div class="panel-head batch-panel-head">
                    <div><span class="panel-step">Step 1</span><h2>Create a New Batch</h2><p>Complete the handover details, then select at least one application.</p></div>
                    <span class="availability-count"><strong><?= count($eligibleApps) ?></strong> ready to batch</span>
                </div>
                <div class="panel-body">
                    <form method="post" class="form-grid batch-form" id="batchCreateForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="create_batch">
                        <div class="field"><label for="handoverDate">Planned handover date <span aria-hidden="true">*</span></label><input id="handoverDate" type="date" name="handover_date" min="<?= date('Y-m-d') ?>" required><small>When the documents will leave the barangay.</small></div>
                        <div class="field"><label for="submittedBy">Person carrying the hardcopies <span aria-hidden="true">*</span></label><input id="submittedBy" name="submitted_by_name" maxlength="150" autocomplete="name" placeholder="Enter full name" required><small>This name appears in the handover record.</small></div>
                        <div class="field"><label for="batchRemarks">Remarks <span class="optional-label">Optional</span></label><input id="batchRemarks" name="remarks" maxlength="500" placeholder="Delivery instructions or notes"><small>Maximum of 500 characters.</small></div>
                        <fieldset class="field full batch-selection-field">
                            <legend>Applications in this batch <span aria-hidden="true">*</span></legend>
                            <div class="selection-toolbar">
                                <span id="batchSelectionCount" role="status" aria-live="polite">0 applications selected</span>
                                <?php if ($eligibleApps): ?><span><button type="button" class="text-action" id="selectAllBatch">Select all</button><button type="button" class="text-action" id="clearBatchSelection">Clear</button></span><?php endif; ?>
                            </div>
                            <div class="selection-list" id="batchApplicationList">
                                <?php if (!$eligibleApps): ?><div class="batch-empty compact"><i class="fas fa-circle-check" aria-hidden="true"></i><div><strong>Nothing is waiting to be batched</strong><p>Applications will appear here once they reach “For Review.”</p></div></div><?php endif; ?>
                                <?php foreach ($eligibleApps as $app): ?><label class="selection-row"><input type="checkbox" name="application_ids[]" value="<?= htmlspecialchars($app['id_number']) ?>"><span class="selection-check" aria-hidden="true"><i class="fas fa-check"></i></span><span><strong><?= htmlspecialchars($app['full_name']) ?></strong><small><?= htmlspecialchars($app['id_number']) ?> <span aria-hidden="true">&bull;</span> <?= htmlspecialchars(ucwords(str_replace('_', ' ', $app['application_type']))) ?></small></span></label><?php endforeach; ?>
                            </div>
                        </fieldset>
                        <div class="full batch-submit-row"><span><i class="fas fa-info-circle"></i> The batch starts as a draft. You can review it before release.</span><button class="btn btn-primary" id="createBatchButton" type="submit" <?= !$eligibleApps ? 'disabled' : '' ?>><i class="fas fa-plus"></i> Create Draft Batch</button></div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <div class="panel batch-monitor-panel">
                <div class="panel-head batch-panel-head monitoring-head">
                    <div><span class="panel-step"><?= $isDepartment ? 'Main office workspace' : 'Step 2' ?></span><h2>Batch Monitoring</h2><p>Open a batch to view its handover history and application documents.</p></div>
                    <span class="availability-count" id="visibleBatchCount"><?= count($batches) ?> shown</span>
                </div>
                <div class="batch-filters" aria-label="Filter hardcopy batches">
                    <label class="batch-search"><span class="sr-only">Search batches</span><i class="fas fa-search" aria-hidden="true"></i><input type="search" id="batchSearch" placeholder="Search batch code or barangay"></label>
                    <label><span class="sr-only">Filter by status</span><select id="batchStatusFilter"><option value="">All statuses</option><option>Draft</option><option>Released</option><option>Received</option><option>Incomplete</option><option>Completed</option></select></label>
                </div>
                <div class="panel-body batch-list" id="batchList">
                    <?php if (!$batches): ?><div class="batch-empty"><i class="fas fa-box-open" aria-hidden="true"></i><div><strong>No hardcopy batches yet</strong><p><?= $isDepartment ? 'Barangay submissions will appear here after they are created.' : 'Create a batch when applications are ready for department review.' ?></p></div></div><?php endif; ?>
                    <div class="batch-empty" id="batchFilterEmpty" hidden><i class="fas fa-filter-circle-xmark" aria-hidden="true"></i><div><strong>No batches match your filters</strong><p>Try a different search term or status.</p></div></div>
                    <?php foreach ($batches as $batch):
                        $batchStatus = (string)$batch['status'];
                        $isReleased = $batchStatus !== 'Draft';
                        $isReceived = in_array($batchStatus, ['Received', 'Incomplete', 'Completed'], true);
                        $isReviewed = $batchStatus === 'Completed';
                    ?>
                    <details class="batch-card" data-status="<?= htmlspecialchars(strtolower($batchStatus)) ?>" data-search="<?= htmlspecialchars(strtolower($batch['batch_code'] . ' ' . $batch['barangay'] . ' ' . $batch['submitted_by_name'])) ?>">
                        <summary>
                            <span class="batch-summary-icon"><i class="fas fa-box-archive" aria-hidden="true"></i></span>
                            <span class="batch-summary-main"><strong><?= htmlspecialchars($batch['batch_code']) ?></strong><small><i class="fas fa-location-dot" aria-hidden="true"></i> <?= htmlspecialchars($batch['barangay']) ?></small></span>
                            <span class="batch-summary-info"><small><?= (int)$batch['item_count'] ?> application<?= (int)$batch['item_count'] === 1 ? '' : 's' ?></small><small>Handover <?= htmlspecialchars(date('M j, Y', strtotime($batch['handover_date']))) ?></small></span>
                            <span class="badge <?= strtolower($batchStatus) ?>"><i class="fas fa-circle" aria-hidden="true"></i><?= htmlspecialchars($batchStatus) ?></span>
                            <span class="summary-chevron"><i class="fas fa-chevron-down" aria-hidden="true"></i></span>
                        </summary>
                        <div class="details-body batch-details-body">
                            <div class="batch-progress" aria-label="Batch progress">
                                <span class="done"><i class="fas fa-check"></i><small>Prepared</small></span>
                                <b class="<?= $isReleased ? 'done' : '' ?>"></b>
                                <span class="<?= $isReleased ? 'done' : '' ?>"><i class="fas <?= $isReleased ? 'fa-check' : 'fa-truck' ?>"></i><small>Released</small></span>
                                <b class="<?= $isReceived ? 'done' : '' ?>"></b>
                                <span class="<?= $isReceived ? 'done' : '' ?>"><i class="fas <?= $isReceived ? 'fa-check' : 'fa-inbox' ?>"></i><small>Received</small></span>
                                <b class="<?= $isReviewed ? 'done' : '' ?>"></b>
                                <span class="<?= $isReviewed ? 'done' : ($batchStatus === 'Incomplete' ? 'attention' : '') ?>"><i class="fas <?= $isReviewed ? 'fa-check' : ($batchStatus === 'Incomplete' ? 'fa-exclamation' : 'fa-clipboard-check') ?>"></i><small>Reviewed</small></span>
                            </div>

                            <div class="batch-metadata">
                                <div><i class="fas fa-calendar-day" aria-hidden="true"></i><span><small>Handover date</small><strong><?= htmlspecialchars(date('M j, Y', strtotime($batch['handover_date']))) ?></strong></span></div>
                                <div><i class="fas fa-person-walking-luggage" aria-hidden="true"></i><span><small>Carried by</small><strong><?= htmlspecialchars($batch['submitted_by_name']) ?></strong></span></div>
                                <div><i class="fas fa-clock" aria-hidden="true"></i><span><small>Released</small><strong><?= $batch['released_at'] ? htmlspecialchars(date('M j, Y g:i A', strtotime($batch['released_at']))) : 'Not yet released' ?></strong></span></div>
                                <div><i class="fas fa-building-circle-check" aria-hidden="true"></i><span><small>Received</small><strong><?= $batch['received_at'] ? htmlspecialchars(date('M j, Y g:i A', strtotime($batch['received_at']))) : 'Not yet received' ?></strong><?php if (trim($batch['received_by_name'] ?? '') !== ''): ?><em>by <?= htmlspecialchars(trim($batch['received_by_name'])) ?></em><?php endif; ?></span></div>
                            </div>

                            <?php if (!empty($batch['remarks'])): ?><div class="batch-remarks"><i class="fas fa-note-sticky" aria-hidden="true"></i><span><strong>Batch remarks</strong><?= htmlspecialchars($batch['remarks']) ?></span></div><?php endif; ?>

                            <div class="batch-actions">
                                <?php if (!$isDepartment && $batchStatus === 'Draft'): ?><form method="post" onsubmit="return confirm('Mark this batch as released? Confirm only when the hardcopies have left the barangay office.');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="action" value="release_batch"><input type="hidden" name="batch_id" value="<?= (int)$batch['id'] ?>"><button class="btn btn-warning" type="submit"><i class="fas fa-truck"></i> Confirm Release to Main Office</button></form><?php endif; ?>
                                <?php if ($isDepartment && in_array($batchStatus, ['Released', 'Incomplete'], true)): ?><form method="post" onsubmit="return confirm('<?= $batchStatus === 'Incomplete' ? 'Confirm that the corrected or missing documents were physically received?' : 'Confirm that the main office physically received this batch?' ?>');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="action" value="receive_batch"><input type="hidden" name="batch_id" value="<?= (int)$batch['id'] ?>"><button class="btn btn-success" type="submit"><i class="fas fa-inbox"></i> <?= $batchStatus === 'Incomplete' ? 'Confirm Corrected Documents' : 'Confirm Physical Receipt' ?></button></form><?php endif; ?>
                                <?php if (!$isDepartment && $batchStatus !== 'Draft'): ?><span class="action-status"><i class="fas fa-circle-check"></i> No action needed from the barangay at this stage.</span><?php endif; ?>
                            </div>

                            <div class="batch-table-heading"><div><h3>Applications and Documents</h3><p><?= (int)$batch['complete_count'] ?> complete, <?= (int)$batch['incomplete_count'] ?> incomplete, <?= max(0, (int)$batch['item_count'] - (int)$batch['complete_count'] - (int)$batch['incomplete_count']) ?> pending</p></div></div>
                            <div class="table-wrap batch-table-wrap"><table class="batch-table"><thead><tr><th>Senior</th><th>Application</th><th>Document status</th><th>Remarks</th><?php if ($isDepartment): ?><th>Review document set</th><?php endif; ?></tr></thead><tbody data-paginate="10" data-pagination-label="Batch application pages">
                            <?php foreach ($itemsByBatch[$batch['id']] ?? [] as $item): ?><tr><td><strong><?= htmlspecialchars($item['full_name']) ?></strong></td><td><span class="application-code"><?= htmlspecialchars($item['application_id']) ?></span><br><span class="muted"><?= htmlspecialchars($item['workflow_state']) ?></span></td><td><span class="badge <?= strtolower($item['document_status']) ?>"><i class="fas fa-circle" aria-hidden="true"></i><?= htmlspecialchars($item['document_status']) ?></span></td><td><?= htmlspecialchars($item['remarks'] ?: 'No remarks') ?></td><?php if ($isDepartment): ?><td><?php if (in_array($batchStatus, ['Received', 'Incomplete'], true)): ?><form method="post" class="document-review-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="action" value="update_batch_item"><input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>"><label><span>Result</span><select name="document_status" required><option value="Complete" <?= $item['document_status'] === 'Complete' ? 'selected' : '' ?>>Complete</option><option value="Incomplete" <?= $item['document_status'] === 'Incomplete' ? 'selected' : '' ?>>Incomplete</option></select></label><label><span>Remarks</span><input name="remarks" value="<?= htmlspecialchars($item['remarks'] ?? '') ?>" placeholder="Required if incomplete"></label><button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Save review</button></form><?php else: ?><span class="muted"><i class="fas fa-lock"></i> <?= $batchStatus === 'Completed' ? 'Review completed' : 'Confirm receipt before reviewing' ?></span><?php endif; ?></td><?php endif; ?></tr><?php endforeach; ?>
                            </tbody></table></div>
                        </div>
                    </details>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <?php if ($isDepartment): ?>
        <section id="tab-personnel" class="tab-panel" role="tabpanel" aria-labelledby="personnelTab">
            <div class="panel"><div class="panel-head"><h2>Add Home Visit Personnel</h2></div><div class="panel-body">
                <form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="action" value="add_personnel">
                    <div class="field"><label>Full Name</label><input name="full_name" maxlength="150" required></div>
                    <div class="field"><label>Position</label><input name="position" maxlength="100" placeholder="Social Worker"></div>
                    <div class="field"><label>Contact Number</label><input name="contact_number" maxlength="30"></div>
                    <div class="field"><label>Barangay Assignment (optional)</label><input name="barangay" maxlength="100" placeholder="Leave blank for city-wide"></div>
                    <div class="full"><button class="btn btn-primary" type="submit"><i class="fas fa-user-plus"></i> Add Personnel</button></div>
                </form>
            </div></div>
            <div class="panel"><div class="panel-head"><h2>Personnel Directory</h2></div><div class="table-wrap"><table><thead><tr><th>Name</th><th>Position</th><th>Assignment</th><th>Contact</th><th>Status</th><th>Action</th></tr></thead><tbody data-paginate="10" data-pagination-label="Personnel directory pages">
                <?php if (!$personnel): ?><tr><td colspan="6">No personnel added yet.</td></tr><?php endif; ?>
                <?php foreach ($personnel as $p): ?><tr><td><strong><?= htmlspecialchars($p['full_name']) ?></strong></td><td><?= htmlspecialchars($p['position'] ?? '-') ?></td><td><?= htmlspecialchars($p['barangay'] ?: 'City-wide') ?></td><td><?= htmlspecialchars($p['contact_number'] ?? '-') ?></td><td><span class="badge <?= (int)$p['is_active'] ? 'eligible' : 'cancelled' ?>"><?= (int)$p['is_active'] ? 'Active' : 'Inactive' ?></span></td><td><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="action" value="toggle_personnel"><input type="hidden" name="personnel_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-muted" type="submit"><?= (int)$p['is_active'] ? 'Deactivate' : 'Activate' ?></button></form></td></tr><?php endforeach; ?>
            </tbody></table></div></div>
        </section>
        <?php endif; ?>
    </div>
</div>
<script src="../assets/js/sidebar-toggle.js?v=3"></script>
<script>
    const tabButtons = Array.from(document.querySelectorAll('.tab-btn'));

    function activateOperationsTab(tabName, updateHash = false) {
        const selectedButton = tabButtons.find((button) => button.dataset.tab === tabName);
        const selectedPanel = document.getElementById('tab-' + tabName);
        if (!selectedButton || !selectedPanel) return;

        tabButtons.forEach((button) => {
            const active = button === selectedButton;
            button.classList.toggle('active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
            button.tabIndex = active ? 0 : -1;
        });
        document.querySelectorAll('.tab-panel').forEach((panel) => panel.classList.toggle('active', panel === selectedPanel));
        if (updateHash) history.replaceState(null, '', '#' + tabName);
    }

    tabButtons.forEach((button, index) => {
        button.addEventListener('click', () => activateOperationsTab(button.dataset.tab, true));
        button.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
            event.preventDefault();
            const direction = event.key === 'ArrowRight' ? 1 : -1;
            const nextButton = tabButtons[(index + direction + tabButtons.length) % tabButtons.length];
            activateOperationsTab(nextButton.dataset.tab, true);
            nextButton.focus();
        });
    });

    const requestedTab = window.location.hash.replace('#', '');
    activateOperationsTab(requestedTab || 'visits');

    const batchCheckboxes = Array.from(document.querySelectorAll('#batchApplicationList input[type="checkbox"]'));
    const batchSelectionCount = document.getElementById('batchSelectionCount');
    const createBatchButton = document.getElementById('createBatchButton');

    function updateBatchSelection() {
        if (!batchSelectionCount) return;
        const selectedCount = batchCheckboxes.filter((checkbox) => checkbox.checked).length;
        batchSelectionCount.textContent = selectedCount + ' application' + (selectedCount === 1 ? '' : 's') + ' selected';
        if (createBatchButton) createBatchButton.disabled = selectedCount === 0;
    }

    batchCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', updateBatchSelection));
    document.getElementById('selectAllBatch')?.addEventListener('click', () => {
        batchCheckboxes.forEach((checkbox) => checkbox.checked = true);
        updateBatchSelection();
    });
    document.getElementById('clearBatchSelection')?.addEventListener('click', () => {
        batchCheckboxes.forEach((checkbox) => checkbox.checked = false);
        updateBatchSelection();
    });
    updateBatchSelection();

    const batchSearch = document.getElementById('batchSearch');
    const batchStatusFilter = document.getElementById('batchStatusFilter');
    const batchCards = Array.from(document.querySelectorAll('.batch-card'));
    const visibleBatchCount = document.getElementById('visibleBatchCount');
    const batchFilterEmpty = document.getElementById('batchFilterEmpty');

    function filterBatches() {
        const query = (batchSearch?.value || '').trim().toLowerCase();
        const status = (batchStatusFilter?.value || '').toLowerCase();
        let visibleCount = 0;
        batchCards.forEach((card) => {
            const visible = (!query || card.dataset.search.includes(query)) && (!status || card.dataset.status === status);
            card.hidden = !visible;
            if (visible) visibleCount++;
        });
        if (visibleBatchCount) visibleBatchCount.textContent = visibleCount + ' shown';
        if (batchFilterEmpty) batchFilterEmpty.hidden = visibleCount !== 0 || batchCards.length === 0;
    }

    batchSearch?.addEventListener('input', filterBatches);
    batchStatusFilter?.addEventListener('change', filterBatches);

    document.querySelectorAll('.document-review-form').forEach((form) => {
        const result = form.querySelector('select[name="document_status"]');
        const remarks = form.querySelector('input[name="remarks"]');
        const syncRequirement = () => {
            remarks.required = result.value === 'Incomplete';
            remarks.setAttribute('aria-required', remarks.required ? 'true' : 'false');
        };
        result.addEventListener('change', syncRequirement);
        syncRequirement();
    });

    function toLocalInput(value) {
        if (!value) return '';
        return value.replace(' ', 'T').slice(0, 16);
    }

    function openVisit(visit) {
        document.getElementById('visitApplicationId').value = visit.id_number;
        document.getElementById('statusApplicationId').value = visit.id_number;
        document.getElementById('visitEditorName').textContent = visit.full_name + ' (' + visit.id_number + ')';
        document.getElementById('visitEligibility').value = visit.home_visit_eligibility || '';
        document.getElementById('visitReason').value = visit.home_visit_eligibility_reason || '';
        document.getElementById('visitPersonnel').value = visit.home_visit_personnel_id || '';
        document.getElementById('visitSchedule').value = toLocalInput(visit.home_visit_scheduled_at);
        document.getElementById('visitNotes').value = visit.home_visit_notes || '';
        toggleVisitFields();
        document.getElementById('visitEditor').scrollIntoView({behavior:'smooth', block:'start'});
    }

    function toggleVisitFields() {
        const eligible = document.getElementById('visitEligibility').value === 'Eligible';
        document.querySelectorAll('.visit-assignment').forEach((field) => field.style.opacity = eligible ? '1' : '0.55');
        document.getElementById('visitPersonnel').required = eligible;
        document.getElementById('visitPersonnel').disabled = !eligible;
        document.getElementById('visitSchedule').required = eligible;
        document.getElementById('visitSchedule').disabled = !eligible;
        document.getElementById('visitReason').required = eligible;
        document.getElementById('visitScheduleHelp').style.display = eligible ? 'none' : 'block';
    }
    toggleVisitFields();
</script>
</body>
</html>
