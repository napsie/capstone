<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/deadline_alerts.php';
require_once '../includes/audit_logger.php';
require_once '../includes/data_normalizer.php';
require_once '../includes/application_types.php';
require_once '../includes/barangays_list.php';

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
    if (($_POST['ajax'] ?? '') === '1') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => $success, 'message' => $message]);
        exit;
    }
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
        $stmt = $conn->prepare('SELECT id_number, application_type, workflow_state, home_visit_notes FROM applications WHERE id_number = ? AND COALESCE(is_archived, 0) = 0');
        $stmt->execute([$id]);
    } else {
        $stmt = $conn->prepare('SELECT id_number, application_type, workflow_state, home_visit_notes FROM applications WHERE id_number = ? AND barangay = ? AND COALESCE(is_archived, 0) = 0');
        $stmt->execute([$id, $barangay]);
    }
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireOperationsCsrf();
    $action = (string)($_POST['action'] ?? '');
    if (in_array($action, ['create_batch', 'release_batch', 'receive_batch', 'update_batch_item'], true)) {
        operationsRedirect('Hardcopy batch processing has been removed from SENIORLINK.', false);
    } elseif (in_array($action, ['add_personnel', 'toggle_personnel', 'archive_personnel', 'restore_personnel'], true)) {
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
            if (mb_strlen($name) < 2) operationsRedirect('Personnel name must contain at least 2 characters.', false);
            if (mb_strlen($name) > 80) operationsRedirect('Personnel name must not exceed 80 characters.', false);
            if (!preg_match("/^[\\p{L}][\\p{L}\\p{M} .'-]*$/u", $name)) operationsRedirect('Personnel name may contain letters, spaces, periods, apostrophes, and hyphens only.', false);
            if (!in_array($position, ['Social Worker', 'Nurse', 'Field Officer', 'Other'], true)) operationsRedirect('Select a valid personnel position.', false);
            if ($assignedBarangay !== '' && !in_array($assignedBarangay, $barangays_list, true)) operationsRedirect('Select a valid barangay assignment.', false);
            $contact = normalizePhoneNumber($contact);
            if (!isValidPhilippineMobileNumber($contact, true)) operationsRedirect('Contact number must contain exactly 11 digits and begin with 09.', false);

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

        if ($action === 'archive_personnel') {
            if (!$isDepartment) operationsRedirect('Only department administrators can archive personnel.', false);
            $personnelId = filter_var($_POST['personnel_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$personnelId) operationsRedirect('Invalid personnel record.', false);

            $personStmt = $conn->prepare('SELECT full_name FROM home_visit_personnel WHERE id = ? AND COALESCE(is_archived, 0) = 0');
            $personStmt->execute([$personnelId]);
            $person = $personStmt->fetch(PDO::FETCH_ASSOC);
            if (!$person) operationsRedirect('This personnel record is already archived or unavailable.', false);

            $assignedVisitStmt = $conn->prepare("SELECT COUNT(*) FROM applications WHERE home_visit_personnel_id = ? AND COALESCE(is_archived, 0) = 0 AND COALESCE(home_visit_status, '') IN ('Scheduled', 'In Progress')");
            $assignedVisitStmt->execute([$personnelId]);
            if ((int)$assignedVisitStmt->fetchColumn() > 0) {
                operationsRedirect('This personnel member still has an active home visit. Reassign or complete that visit before archiving.', false);
            }

            $archivedBy = trim((string)($_SESSION['username'] ?? $_SESSION['user_id'] ?? 'Department Admin'));
            $archiveStmt = $conn->prepare('UPDATE home_visit_personnel SET is_active = 0, is_archived = 1, archived_at = CURRENT_TIMESTAMP, archived_by = ? WHERE id = ? AND COALESCE(is_archived, 0) = 0');
            $archiveStmt->execute([$archivedBy, $personnelId]);
            if ($archiveStmt->rowCount() !== 1) operationsRedirect('The personnel record could not be archived. Please try again.', false);
            logAudit($conn, 'ARCHIVE_HOME_VISIT_PERSONNEL', "Archived home visit personnel: {$person['full_name']}.");
            operationsRedirect('Personnel archived. They can no longer be assigned to new home visits.');
        }

        if ($action === 'restore_personnel') {
            if (!$isDepartment) operationsRedirect('Only department administrators can restore personnel.', false);
            $personnelId = filter_var($_POST['personnel_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$personnelId) operationsRedirect('Invalid personnel record.', false);
            $restoreStmt = $conn->prepare('UPDATE home_visit_personnel SET is_archived = 0, archived_at = NULL, archived_by = NULL, is_active = 0 WHERE id = ? AND COALESCE(is_archived, 0) = 1');
            $restoreStmt->execute([$personnelId]);
            if ($restoreStmt->rowCount() !== 1) operationsRedirect('The personnel record could not be restored. Please try again.', false);
            logAudit($conn, 'RESTORE_HOME_VISIT_PERSONNEL', "Restored home visit personnel #{$personnelId} as inactive.");
            operationsRedirect('Personnel restored as inactive. Activate them before assigning a new home visit.');
        }

        if ($action === 'save_visit') {
            if (!$isDepartment) operationsRedirect('Only the Department Admin can assign and schedule surprise home visits.', false);
            $applicationId = trim((string)($_POST['application_id'] ?? ''));
            $app = fetchScopedApplication($conn, $applicationId, $isDepartment, $barangay);
            if (!$app || ($app['application_type'] ?? '') !== 'pension') operationsRedirect('Local pension application not found.', false);

            $eligibility = null;
            $reason = trim(strip_tags((string)($_POST['reason'] ?? '')));

            $schedule = null;
            $personnelId = null;
            $status = 'Scheduled';
            {
                $personnelId = filter_var($_POST['personnel_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
                $rawSchedule = trim((string)($_POST['scheduled_at'] ?? ''));
                $tz = new DateTimeZone('Asia/Manila');
                $date = DateTime::createFromFormat('!Y-m-d', $rawSchedule, $tz);
                $errors = DateTime::getLastErrors();
                $validDate = $date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
                if (!$personnelId) operationsRedirect('Assign personnel to the visit.', false);
                $today = new DateTime('today', $tz);
                if (!$validDate || $date < $today) operationsRedirect('Choose today or a future home visit date.', false);
                if (in_array($date->format('N'), ['6', '7'], true)) operationsRedirect('Home visits can only be scheduled Monday to Friday.', false);
                if ($isDepartment) {
                    $personStmt = $conn->prepare('SELECT COUNT(*) FROM home_visit_personnel WHERE id = ? AND is_active = 1');
                    $personStmt->execute([$personnelId]);
                } else {
                    $personStmt = $conn->prepare("SELECT COUNT(*) FROM home_visit_personnel WHERE id = ? AND is_active = 1 AND (barangay IS NULL OR barangay = '' OR barangay = ?)");
                    $personStmt->execute([$personnelId, $barangay]);
                }
                if ((int)$personStmt->fetchColumn() === 0) operationsRedirect('Selected personnel is unavailable.', false);
                $schedule = $date->format('Y-m-d 00:00:00');
                $conflictStmt = $conn->prepare("SELECT COUNT(*) FROM applications WHERE home_visit_personnel_id = ? AND home_visit_scheduled_at = ? AND id_number <> ? AND COALESCE(home_visit_status, '') NOT IN ('Rejected', 'Cancelled', 'Completed')");
                $conflictStmt->execute([$personnelId, $schedule, $applicationId]);
                if ((int)$conflictStmt->fetchColumn() > 0) operationsRedirect('That personnel already has a home visit on the selected date.', false);
                $status = 'Scheduled';
            }

            $stmt = $conn->prepare('UPDATE applications SET home_visit_eligibility_reason = ?, home_visit_personnel_id = ?, home_visit_scheduled_at = ?, home_visit_status = ?, home_visit_completed_at = NULL WHERE id_number = ?');
            $stmt->execute([$reason ?: null, $personnelId, $schedule, $status, $applicationId]);
            logAudit($conn, 'SAVE_HOME_VISIT_ASSIGNMENT', "{$applicationId}: personnel assigned; status {$status}.");

            operationsRedirect('Home visit assignment saved. The schedule remains private to authorized personnel.');
        }

        if ($action === 'update_visit_status') {
            if ($isDepartment) operationsRedirect('OSCA has read-only access to the SHDO home-visit evaluation.', false);
            $applicationId = trim((string)($_POST['application_id'] ?? ''));
            $app = fetchScopedApplication($conn, $applicationId, $isDepartment, $barangay);
            if (!$app) operationsRedirect('Application not found.', false);
            $status = (string)($_POST['visit_status'] ?? '');
            $allowed = ['Scheduled', 'In Progress', 'Completed', 'Rejected'];
            if (!in_array($status, $allowed, true)) operationsRedirect('Invalid visit status.', false);
            $notes = trim(strip_tags((string)($_POST['notes'] ?? '')));
            if ($status === 'Completed' && $notes === '') operationsRedirect('Completion notes are required.', false);
            $eligibility = trim((string)($_POST['eligibility'] ?? ''));
            $eligibilityReason = trim(strip_tags((string)($_POST['eligibility_reason'] ?? '')));
            if ($status === 'Completed' && !in_array($eligibility, ['Eligible', 'Not Eligible'], true)) operationsRedirect('Select the final evaluation.', false);
            if ($status === 'Completed' && $eligibility === 'Not Eligible' && $eligibilityReason === '') operationsRedirect('Enter the reason for a Not Eligible decision.', false);
            if ($status === 'Completed' && trim((string)($_POST['living_arrangement'] ?? '')) === '') operationsRedirect('Select the senior\'s living arrangement.', false);
            if ($status === 'Completed' && ($_POST['is_pensioner'] ?? '') === '') operationsRedirect('Indicate whether the senior receives a pension.', false);
            if ($status === 'Completed' && ($_POST['family_support'] ?? '') === '') operationsRedirect('Indicate whether the senior receives regular family support.', false);
            if ($status === 'Completed' && ($_POST['is_pensioner'] ?? '') === '1' && trim((string)($_POST['pension_source'] ?? '')) === '') operationsRedirect('Enter the pension source.', false);
            if ($status === 'Completed' && ($_POST['is_pensioner'] ?? '') === '1' && ($_POST['pension_amount'] ?? '') === '') operationsRedirect('Enter the pension amount.', false);
            if ($status === 'Completed' && ($_POST['family_support'] ?? '') === '1' && ($_POST['family_support_amount'] ?? '') === '') operationsRedirect('Enter the family support amount.', false);
            if ($status === 'Completed' && ($_POST['personal_income'] ?? '') === '1' && ($_POST['personal_income_amount'] ?? '') === '') operationsRedirect('Enter the personal income amount.', false);
            if ($status === 'Rejected') {
                $previousState = $app['workflow_state'] ?: 'Received';
                $rejectionReason = $notes ?: 'Required home visit was rejected.';
                $archiveActor = (string)($_SESSION['username'] ?? $_SESSION['user_id'] ?? 'SHDO');

                $conn->beginTransaction();
                $stmt = $conn->prepare("UPDATE applications
                    SET home_visit_status = 'Rejected', home_visit_notes = ?, home_visit_completed_at = NULL,
                        workflow_state = 'Rejected', status = 'rejected', return_reason = ?,
                        is_archived = 1, archived_at = CURRENT_TIMESTAMP, archived_by = ?
                    WHERE id_number = ? AND COALESCE(is_archived, 0) = 0");
                $stmt->execute([$rejectionReason, $rejectionReason, $archiveActor, $applicationId]);
                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('The application could not be rejected and archived.');
                }
                if ($previousState !== 'Rejected') {
                    $history = $conn->prepare('INSERT INTO application_history (application_id, previous_state, new_state, changed_by, comments) VALUES (?, ?, ?, ?, ?)');
                    $history->execute([$applicationId, $previousState, 'Rejected', $archiveActor, $rejectionReason]);
                }
                logAudit($conn, 'ARCHIVE_REJECTED_APPLICATION', "{$applicationId}: rejected after home visit and moved to archive.");
                $conn->commit();
            } else {
                $livingArrangement = trim(strip_tags((string)($_POST['living_arrangement'] ?? '')));
                $isPensioner = ($_POST['is_pensioner'] ?? '') === '' ? null : (int)$_POST['is_pensioner'];
                $pensionSource = trim(strip_tags((string)($_POST['pension_source'] ?? '')));
                $pensionAmount = ($_POST['pension_amount'] ?? '') === '' ? null : max(0, (float)$_POST['pension_amount']);
                $familySupport = ($_POST['family_support'] ?? '') === '' ? null : (int)$_POST['family_support'];
                $familySupportAmount = ($_POST['family_support_amount'] ?? '') === '' ? null : max(0, (float)$_POST['family_support_amount']);
                $personalIncome = ($_POST['personal_income'] ?? '') === '' ? null : (int)$_POST['personal_income'];
                $personalIncomeAmount = ($_POST['personal_income_amount'] ?? '') === '' ? null : max(0, (float)$_POST['personal_income_amount']);
                if ($isPensioner !== 1) {
                    $pensionSource = '';
                    $pensionAmount = null;
                }
                if ($familySupport !== 1) $familySupportAmount = null;
                if ($personalIncome !== 1) $personalIncomeAmount = null;
                $healthCondition = trim(strip_tags((string)($_POST['health_condition'] ?? '')));
                $withMaintenance = ($_POST['with_maintenance'] ?? '') === '' ? null : (int)$_POST['with_maintenance'];
                $maintenanceSpec = trim(strip_tags((string)($_POST['maintenance_spec'] ?? '')));
                $confirmationName = trim(strip_tags((string)($_POST['confirmation_name'] ?? '')));
                $confirmationContact = trim(strip_tags((string)($_POST['confirmation_contact'] ?? '')));
                $stmt = $conn->prepare("UPDATE applications SET home_visit_status = ?, home_visit_notes = ?, home_visit_completed_at = CASE WHEN ? = 'Completed' THEN CURRENT_TIMESTAMP ELSE NULL END,
                    home_visit_eligibility = CASE WHEN ? = 'Completed' THEN ? ELSE home_visit_eligibility END,
                    home_visit_eligibility_reason = CASE WHEN ? = 'Completed' THEN ? ELSE home_visit_eligibility_reason END,
                    visit_purpose = 'Local Pension', living_arrangement = ?, is_pensioner = ?, pension_source = ?, pension_amount = ?,
                    family_support = ?, family_support_amount = ?, personal_income = ?, personal_income_amount = ?, health_condition = ?,
                    with_maintenance = ?, maintenance_spec = ?, visit_summary = ?, claimant_name = ?, claimant_contact = ? WHERE id_number = ?");
                $stmt->execute([$status, $notes ?: ($app['home_visit_notes'] ?? null), $status,
                    $status, $eligibility ?: null, $status, $eligibilityReason ?: null,
                    $livingArrangement ?: null, $isPensioner, $pensionSource ?: null, $pensionAmount,
                    $familySupport, $familySupportAmount, $personalIncome, $personalIncomeAmount, $healthCondition ?: null,
                    $withMaintenance, $maintenanceSpec ?: null, $notes ?: null, $confirmationName ?: null, $confirmationContact ?: null, $applicationId]);
                logAudit($conn, 'UPDATE_HOME_VISIT_STATUS', "{$applicationId}: home visit changed to {$status}.");
            }
            operationsRedirect($status === 'Rejected'
                ? 'Application rejected and moved to the barangay and department archives.'
                : 'Home visit status updated.');
        }

    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        error_log('Field operations error: ' . $e->getMessage());
        operationsRedirect('The operation could not be completed. Please check the information and try again.', false);
    }
}

$notice = $_SESSION['field_operations_notice'] ?? null;
unset($_SESSION['field_operations_notice']);

$personnel = $conn->query('SELECT id, full_name, position, contact_number, barangay, is_active FROM home_visit_personnel WHERE COALESCE(is_archived, 0) = 0 ORDER BY is_active DESC, full_name')->fetchAll(PDO::FETCH_ASSOC);
$archivedPersonnel = $conn->query('SELECT id, full_name, position, barangay, archived_at FROM home_visit_personnel WHERE COALESCE(is_archived, 0) = 1 ORDER BY archived_at DESC, full_name')->fetchAll(PDO::FETCH_ASSOC);
$activePersonnel = array_values(array_filter($personnel, static function ($p) use ($isDepartment, $barangay) {
    return (int)$p['is_active'] === 1
        && ($isDepartment || empty($p['barangay']) || $p['barangay'] === $barangay);
}));

$visitSearch = trim((string)($_GET['visit_search'] ?? ''));
if (mb_strlen($visitSearch) > 100) $visitSearch = mb_substr($visitSearch, 0, 100);
$visitStatusFilter = (string)($_GET['visit_status'] ?? 'all');
$allowedVisitStatuses = ['all', 'Waiting for Home Visit', 'Scheduled', 'In Progress', 'Completed', 'Rejected'];
if (!in_array($visitStatusFilter, $allowedVisitStatuses, true)) $visitStatusFilter = 'all';
$visitDateFilter = trim((string)($_GET['visit_date'] ?? ''));
if ($visitDateFilter !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $visitDateFilter)) $visitDateFilter = '';
$visitBarangayFilter = $isDepartment ? trim((string)($_GET['visit_barangay'] ?? 'all')) : $barangay;
if ($isDepartment && $visitBarangayFilter !== 'all' && !in_array($visitBarangayFilter, $barangays_list, true)) $visitBarangayFilter = 'all';

$visitSql = "SELECT a.id_number, a.full_name, a.barangay, a.contact_number, a.workflow_state, a.application_type, a.date_submitted,
                    a.home_visit_eligibility, a.home_visit_eligibility_reason, a.home_visit_scheduled_at,
                    a.home_visit_status, a.home_visit_notes, a.home_visit_personnel_id,
                    a.living_arrangement, a.is_pensioner, a.pension_source, a.pension_amount,
                    a.family_support, a.family_support_amount, a.personal_income, a.personal_income_amount,
                    a.health_condition, a.with_maintenance, a.maintenance_spec, a.visit_summary,
                    a.claimant_name, a.claimant_contact,
                    p.full_name personnel_name
             FROM applications a
             LEFT JOIN home_visit_personnel p ON p.id = a.home_visit_personnel_id
             WHERE a.application_type = 'pension' AND COALESCE(a.is_archived, 0) = 0";
$visitParams = [];
if (!$isDepartment) {
    $visitSql .= ' AND a.barangay = ?';
    $visitParams[] = $barangay;
}
if ($isDepartment && $visitBarangayFilter !== 'all') {
    $visitSql .= ' AND a.barangay = ?';
    $visitParams[] = $visitBarangayFilter;
}
if ($visitSearch !== '') {
    $visitSql .= ' AND (a.full_name LIKE ? OR a.id_number LIKE ?)';
    $visitParams[] = "%{$visitSearch}%";
    $visitParams[] = "%{$visitSearch}%";
}
if ($visitStatusFilter === 'Waiting for Home Visit') {
    $visitSql .= " AND COALESCE(NULLIF(a.home_visit_status, ''), 'Waiting for Home Visit') = 'Waiting for Home Visit'";
} elseif ($visitStatusFilter !== 'all') {
    $visitSql .= ' AND a.home_visit_status = ?';
    $visitParams[] = $visitStatusFilter;
}
if ($visitDateFilter !== '') {
    $visitSql .= ' AND DATE(a.home_visit_scheduled_at) = ?';
    $visitParams[] = $visitDateFilter;
}
$visitSql .= " ORDER BY CASE COALESCE(NULLIF(a.home_visit_status, ''), 'Waiting for Home Visit') WHEN 'Waiting for Home Visit' THEN 0 WHEN 'Scheduled' THEN 1 WHEN 'In Progress' THEN 2 ELSE 3 END, a.home_visit_scheduled_at, a.date_submitted DESC";
$visitStmt = $conn->prepare($visitSql);
$visitStmt->execute($visitParams);
$visits = $visitStmt->fetchAll(PDO::FETCH_ASSOC);

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
    <link rel="stylesheet" href="../assets/css/seniorlink-ui.css?v=20">
    <link rel="stylesheet" href="../assets/css/table-pagination.css?v=1">
    <script src="../assets/js/table-pagination.js?v=2" defer></script>
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
        .field input:disabled { background:#f1f5f9; color:#94a3b8; cursor:not-allowed; }
        .full { grid-column:1/-1; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:7px; border:0; border-radius:8px; padding:9px 13px; font-weight:700; cursor:pointer; text-decoration:none; }
        .btn-primary { background:#2563eb; color:#fff; }
        .btn-success { background:#059669; color:#fff; }
        .btn-warning { background:#d97706; color:#fff; }
        .btn-muted { background:#e2e8f0; color:#334155; }
        .visit-filters { display:grid; grid-template-columns:minmax(220px,2fr) minmax(165px,1fr) minmax(165px,1fr) minmax(165px,1fr) auto; gap:12px; align-items:end; padding:16px 18px; border-bottom:1px solid #d7e6f7; background:linear-gradient(135deg,#eef7ff 0%,#f7fbff 58%,#eef6ff 100%); }
        .visit-filters .field { min-width:0; }
        .visit-filters label { display:block; margin-bottom:4px; color:#475569; font-size:.7rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; }
        .visit-filters input,.visit-filters select { width:100%; min-height:42px; padding:8px 11px; border:1px solid #bfcee1; border-radius:9px; background:#fff; color:#334155; font:inherit; font-size:.82rem; box-shadow:0 1px 2px rgba(15,23,42,.03); }
        .visit-filters input:focus,.visit-filters select:focus { outline:3px solid rgba(37,99,235,.16); border-color:#2563eb; }
        .visit-filters .btn { min-height:42px; white-space:nowrap; }
        .visit-filters .filter-note { grid-column:1/-1; margin:0; color:#64748b; font-size:.72rem; }
        .table-wrap { overflow:auto; }
        table { width:100%; border-collapse:collapse; min-width:900px; }
        th,td { padding:11px 12px; text-align:left; border-bottom:1px solid #e2e8f0; vertical-align:top; font-size:.82rem; }
        th { background:#f8fafc; color:#475569; font-size:.7rem; text-transform:uppercase; letter-spacing:.04em; }
        .badge { display:inline-flex; padding:4px 8px; border-radius:999px; font-size:.68rem; font-weight:800; background:#e2e8f0; color:#334155; }
        .badge.eligible,.badge.completed,.badge.complete,.badge.received { background:#dcfce7; color:#166534; }
        .badge.scheduled,.badge.released { background:#dbeafe; color:#1d4ed8; }
        .badge.incomplete,.badge.cancelled,.badge.rejected,.badge.not-eligible { background:#fee2e2; color:#991b1b; }
        .muted { color:var(--muted); font-size:.78rem; }
        .sr-only { position:absolute!important; width:1px!important; height:1px!important; padding:0!important; margin:-1px!important; overflow:hidden!important; clip:rect(0,0,0,0)!important; white-space:nowrap!important; border:0!important; }
        .notice { display:flex; align-items:center; gap:10px; }
        .notice i { flex:0 0 auto; font-size:1rem; }
        .tab-btn { min-height:44px; display:inline-flex; align-items:center; gap:8px; transition:background .18s,border-color .18s,color .18s,box-shadow .18s; }
        .tab-btn:hover:not(.active) { background:#f8fafc; border-color:#94a3b8; }
        .tab-btn:focus-visible,.btn:focus-visible,.text-action:focus-visible { outline:3px solid rgba(37,99,235,.28); outline-offset:2px; }
        .btn { min-height:42px; transition:filter .18s,transform .18s,box-shadow .18s; }
        .btn:hover:not(:disabled) { filter:brightness(.94); transform:translateY(-1px); box-shadow:0 5px 13px rgba(15,23,42,.14); }
        .btn:disabled { opacity:.52; cursor:not-allowed; }
        .evaluation-modal { position:fixed; inset:0; z-index:2200; display:none; align-items:center; justify-content:center; padding:20px; background:rgba(15,23,42,.68); }
        .evaluation-modal.open { display:flex; }
        .evaluation-dialog { width:min(920px,100%); max-height:calc(100dvh - 40px); display:flex; flex-direction:column; overflow:hidden; border:1px solid #dbe4ef; border-radius:18px; background:#fff; box-shadow:0 28px 80px rgba(15,23,42,.32); }
        .evaluation-modal-head { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:16px 20px; border-bottom:1px solid #e2e8f0; }
        .evaluation-modal-head h2 { margin:2px 0 0; font-size:1.15rem; }
        .modal-eyebrow { color:#16814b; font-size:.7rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
        .evaluation-close { width:42px; height:42px; flex:0 0 42px; border:1px solid #dbe4ef; border-radius:10px; background:#f8fafc; color:#475569; font-size:1.35rem; cursor:pointer; }
        .evaluation-modal-body { min-height:0; padding:18px 20px; overflow-y:auto; overscroll-behavior:contain; }
        .evaluation-senior-summary { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; margin:0 0 18px; padding:14px; border:1px solid #dbe4ef; border-radius:12px; background:#f8fafc; }
        .evaluation-senior-summary div { min-width:0; }
        .evaluation-senior-summary dt { color:#64748b; font-size:.67rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; }
        .evaluation-senior-summary dd { margin:3px 0 0; color:#172033; font-size:.88rem; font-weight:700; overflow-wrap:anywhere; }
        .evaluation-modal-actions { display:flex; align-items:center; justify-content:flex-end; gap:9px; padding:13px 20px; border-top:1px solid #e2e8f0; background:#f8fafc; }
        .evaluation-message { margin:0; padding:10px 12px; border-radius:9px; font-size:.82rem; font-weight:650; }
        .evaluation-message.error { display:block; color:#991b1b; background:#fef2f2; }
        .evaluation-message.ok { display:block; color:#166534; background:#f0fdf4; }
        body.evaluation-open { overflow:hidden; }

        @media(max-width:900px){
            .form-grid{grid-template-columns:1fr 1fr;}
            .visit-filters { grid-template-columns:repeat(2,minmax(0,1fr)); }
        }
        @media(max-width:600px){
            .main-content{padding:14px}.form-grid{grid-template-columns:1fr}.full{grid-column:auto}
            .visit-filters { grid-template-columns:1fr; }
            .visit-filters .btn { width:100%; }
            .availability-count { white-space:nowrap; }
            .evaluation-modal { align-items:flex-end; padding:0; }
            .evaluation-dialog { width:100%; max-height:94dvh; border-width:1px 0 0; border-radius:18px 18px 0 0; }
            .evaluation-modal-head,.evaluation-modal-body,.evaluation-modal-actions { padding-left:14px; padding-right:14px; }
            .evaluation-senior-summary { grid-template-columns:1fr; }
            .evaluation-modal-actions .btn { flex:1 1 0; }
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
                        <p><?= $isDepartment ? 'Department Admin - Pasig City' : 'SHDO - ' . htmlspecialchars($barangay) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($notice): ?><div class="notice <?= $notice['success'] ? 'ok' : 'error' ?>" role="<?= $notice['success'] ? 'status' : 'alert' ?>"><i class="fas <?= $notice['success'] ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>" aria-hidden="true"></i><span><?= htmlspecialchars($notice['message']) ?></span></div><?php endif; ?>

        <div class="tabs" role="tablist" aria-label="Field operations sections">
            <button class="tab-btn active" id="visitsTab" type="button" role="tab" aria-selected="true" aria-controls="tab-visits" data-tab="visits"><i class="fas fa-house-medical"></i> Home Visits</button>
            <?php if ($isDepartment): ?><button class="tab-btn" id="personnelTab" type="button" role="tab" aria-selected="false" aria-controls="tab-personnel" data-tab="personnel"><i class="fas fa-user-nurse"></i> Personnel</button><?php endif; ?>
        </div>

        <section id="tab-visits" class="tab-panel active" role="tabpanel" aria-labelledby="visitsTab">
            <div class="panel">
                <div class="panel-head"><h2>Home Visit Monitoring</h2><span class="muted"><?= count($visits) ?> local pension record(s)</span></div>
                <form class="visit-filters" method="get" aria-label="Filter home visits">
                    <div class="field"><label for="visitSearch">Search</label><input id="visitSearch" name="visit_search" type="search" value="<?= htmlspecialchars($visitSearch) ?>" maxlength="100" placeholder="Senior name or application token"></div>
                    <div class="field"><label for="visitStatus">Status</label><select id="visitStatus" name="visit_status"><option value="all">All statuses</option><?php foreach (array_slice($allowedVisitStatuses, 1) as $statusOption): ?><option value="<?= htmlspecialchars($statusOption) ?>" <?= $visitStatusFilter === $statusOption ? 'selected' : '' ?>><?= htmlspecialchars($statusOption) ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label for="visitDate">Home Visit Date</label><input id="visitDate" name="visit_date" type="date" value="<?= htmlspecialchars($visitDateFilter) ?>"></div>
                    <?php if ($isDepartment): ?><div class="field"><label for="visitBarangay">Barangay</label><select id="visitBarangay" name="visit_barangay"><option value="all">All barangays</option><?php foreach ($barangays_list as $barangayOption): ?><option value="<?= htmlspecialchars($barangayOption) ?>" <?= $visitBarangayFilter === $barangayOption ? 'selected' : '' ?>><?= htmlspecialchars($barangayOption) ?></option><?php endforeach; ?></select></div><?php endif; ?>
                    <a class="btn btn-muted" href="field_operations.php">Clear</a>
                    <p class="filter-note"><i class="fas fa-circle-info" aria-hidden="true"></i> Results update automatically when you change a filter.</p>
                </form>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Senior</th><th>Barangay</th><th>Eligibility</th><th>Home Visit Date</th><th>Personnel</th><th>Status</th><th><?= $isDepartment ? 'Manage' : 'Evaluate' ?></th></tr></thead>
                        <tbody data-paginate="10" data-pagination-label="Home visit pages">
                        <?php if (!$visits): ?><tr><td colspan="<?= $isDepartment ? 7 : 6 ?>">No local pension applications found.</td></tr><?php endif; ?>
                        <?php foreach ($visits as $visit): ?>
                            <?php $visitDeadlineAlerts = applicationDeadlineAlerts($visit); ?>
                            <tr id="visit-row-<?= htmlspecialchars($visit['id_number']) ?>">
                                <td><strong><?= htmlspecialchars($visit['full_name']) ?></strong><br><span class="muted"><?= htmlspecialchars($visit['id_number']) ?></span></td>
                                <td><?= htmlspecialchars($visit['barangay']) ?></td>
                                <td><span class="badge <?= strtolower(str_replace(' ', '-', $visit['home_visit_eligibility'] ?? 'pending')) ?>"><?= htmlspecialchars($visit['home_visit_eligibility'] ?: 'Pending') ?></span><br><span class="muted"><?= htmlspecialchars($visit['home_visit_eligibility_reason'] ?? '') ?></span></td>
                                <td><?= $visit['home_visit_scheduled_at'] ? htmlspecialchars(date('M j, Y', strtotime($visit['home_visit_scheduled_at']))) : '-' ?>
                                    <?php if ($visitDeadlineAlerts): ?><div class="deadline-alerts"><?php foreach ($visitDeadlineAlerts as $alert): if ($alert['type'] !== 'home_visit') continue; ?><span class="deadline-alert deadline-alert--<?= htmlspecialchars($alert['level']) ?>" title="<?= htmlspecialchars($alert['detail']) ?>"><i class="fas fa-clock"></i><?= htmlspecialchars($alert['label']) ?></span><?php endforeach; ?></div><?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($visit['personnel_name'] ?? '-') ?></td>
                                <?php $visitStatus = ($visit['home_visit_status'] ?? '') === 'Cancelled' ? 'Rejected' : ($visit['home_visit_status'] ?: 'Waiting for Home Visit'); ?>
                                <td><span class="badge visit-status-badge <?= strtolower(str_replace(' ', '-', $visitStatus)) ?>"><?= htmlspecialchars($visitStatus) ?></span></td>
                                <?php $hasEvaluation = in_array($visitStatus, ['In Progress', 'Completed'], true) || !empty($visit['visit_summary']) || !empty($visit['living_arrangement']); ?>
                                <td><button class="btn btn-primary visit-editor-button" type="button" onclick='openVisit(<?= json_encode($visit, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><?= $isDepartment ? ($hasEvaluation ? 'View Evaluation' : 'Assign / Schedule') : ($hasEvaluation ? 'View / Update Evaluation' : 'Update / Evaluate') ?></button> <a class="btn btn-muted" href="../api/export_application_pdf.php?id=<?= rawurlencode($visit['id_number']) ?>&amp;form=f8" target="_blank" rel="noopener"><i class="fas fa-download"></i> Download F8</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($isDepartment): ?>
            <div class="panel" id="visitEditor" hidden>
                <div class="panel-head"><h2>Assign and Schedule</h2><span class="muted" id="visitEditorName">Select a senior above</span></div>
                <div class="panel-body">
                    <form method="post" class="form-grid" id="visitForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="save_visit">
                        <input type="hidden" name="application_id" id="visitApplicationId">
                        <div class="field full"><span class="muted"><i class="fas fa-circle-info"></i> This required home visit is privately scheduled by the Department Admin. Do not disclose the date in advance.</span></div>
                        <div class="field visit-assignment"><label>Assigned Personnel</label><select name="personnel_id" id="visitPersonnel"><option value="">Select personnel</option><?php foreach ($activePersonnel as $p): ?><option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['full_name'] . (!empty($p['position']) ? ' - ' . $p['position'] : '')) ?></option><?php endforeach; ?></select></div>
                        <div class="field visit-assignment"><label for="visitSchedule">Home Visit Date</label><input type="date" name="scheduled_at" id="visitSchedule" title="Choose a Monday-to-Friday home visit date." onclick="if (this.showPicker && !this.disabled) this.showPicker()"></div>
                        <div class="field"><label>Internal scheduling note (optional)</label><input name="reason" id="visitReason" maxlength="255" placeholder="Internal reason or assignment note"></div>
                        <div class="full"><button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Save Assignment</button></div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </section>

        <div class="evaluation-modal" id="evaluationModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="evaluationModalTitle">
            <div class="evaluation-dialog">
                <div class="evaluation-modal-head"><div><span class="modal-eyebrow">Home Visit</span><h2 id="evaluationModalTitle">SHDO Evaluation Form</h2></div><button class="evaluation-close" type="button" data-close-evaluation aria-label="Close evaluation">&times;</button></div>
                <div class="evaluation-modal-body">
                    <dl class="evaluation-senior-summary"><div><dt>Senior</dt><dd id="evaluationSenior">—</dd></div><div><dt>Barangay</dt><dd id="evaluationBarangay">—</dd></div><div><dt>Home Visit Date</dt><dd id="evaluationDate">—</dd></div><div><dt>Assigned Personnel</dt><dd id="evaluationPersonnel">—</dd></div></dl>
                    <form method="post" class="form-grid<?= $isDepartment ? ' evaluation-readonly' : '' ?>" id="visitStatusForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="action" value="update_visit_status"><input type="hidden" name="ajax" value="1"><input type="hidden" name="application_id" id="statusApplicationId">
                        <div class="field"><label>Visit Status</label><select name="visit_status" required><option>Scheduled</option><option>In Progress</option><option>Completed</option><option>Rejected</option></select></div>
                        <div class="field"><label>Living Arrangement</label><select name="living_arrangement"><option value="">Select</option><option>Owned</option><option>Living Alone</option><option>Living with Relatives</option><option>Rent</option><option>Others</option></select></div>
                        <div class="field"><label>Pensioner?</label><select name="is_pensioner"><option value="">Select</option><option value="1">Yes</option><option value="0">No</option></select></div>
                        <div class="field"><label>Pension Source</label><input name="pension_source"></div><div class="field"><label>Pension Amount</label><input type="number" min="0" step="0.01" name="pension_amount"></div>
                        <div class="field"><label>Regular Family Support?</label><select name="family_support"><option value="">Select</option><option value="1">Yes</option><option value="0">No</option></select></div><div class="field"><label>Family Support Amount</label><input type="number" min="0" step="0.01" name="family_support_amount"></div>
                        <div class="field"><label>Personal Income?</label><select name="personal_income"><option value="">Select</option><option value="1">Yes</option><option value="0">No</option></select></div><div class="field"><label>Personal Income Amount</label><input type="number" min="0" step="0.01" name="personal_income_amount"></div>
                        <div class="field full"><label for="healthConditionVisit">Condition / Illness</label><select id="healthConditionVisit" name="health_condition"><option value="">Select condition</option><?php foreach (getHealthConditionOptions() as $condition): ?><option value="<?= htmlspecialchars($condition) ?>"><?= htmlspecialchars($condition) ?></option><?php endforeach; ?></select></div>
                        <div class="field"><label>With Maintenance?</label><select name="with_maintenance"><option value="">Select</option><option value="1">Yes</option><option value="0">No</option></select></div><div class="field"><label>Maintenance Details</label><input name="maintenance_spec"></div>
                        <div class="field"><label>Confirmation Made With</label><input name="confirmation_name"></div><div class="field"><label>Confirmation Contact No.</label><input type="tel" name="confirmation_contact" maxlength="11" pattern="09[0-9]{9}"></div>
                        <div class="field"><label>Final Evaluation</label><select name="eligibility"><option value="">Select</option><option>Eligible</option><option>Not Eligible</option></select></div><div class="field"><label>Reason for Decision</label><input name="eligibility_reason"></div>
                        <div class="field full"><label>Observations / Visit Summary</label><textarea name="notes" placeholder="Required when completing a visit"></textarea></div>
                        <p class="evaluation-message full" id="evaluationMessage" aria-live="polite" hidden></p>
                    </form>
                </div>
                <div class="evaluation-modal-actions"><button class="btn btn-muted" type="button" data-close-evaluation><?= $isDepartment ? 'Close' : 'Cancel' ?></button><?php if (!$isDepartment): ?><button class="btn btn-success" type="submit" form="visitStatusForm" id="saveEvaluation"><i class="fas fa-check"></i> Save Evaluation</button><?php endif; ?></div>
            </div>
        </div>

        <?php if ($isDepartment): ?>
        <section id="tab-personnel" class="tab-panel" role="tabpanel" aria-labelledby="personnelTab">
            <div class="panel"><div class="panel-head"><h2>Add Home Visit Personnel</h2></div><div class="panel-body">
                <form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="action" value="add_personnel">
                    <div class="field"><label for="personnelFullName">Full Name</label><input id="personnelFullName" name="full_name" maxlength="80" minlength="2" pattern="[A-Za-zÀ-ÖØ-öø-ÿ .'-]+" title="Use letters, spaces, periods, apostrophes, and hyphens only. Maximum 80 characters." autocomplete="name" required><small class="muted">Maximum 80 characters. Letters and name punctuation only.</small></div>
                      <div class="field"><label>Position</label><select name="position" required><option value="">Select position</option><option>Social Worker</option><option>Nurse</option><option>Field Officer</option><option>Other</option></select></div>
                      <div class="field"><label>Contact Number</label><input type="tel" name="contact_number" maxlength="11" pattern="09[0-9]{9}" inputmode="numeric" placeholder="09XXXXXXXXX" title="Enter an 11-digit Philippine mobile number beginning with 09." required></div>
                      <div class="field"><label>Barangay Assignment (optional)</label><select name="barangay"><option value="">City-wide</option><?php foreach ($barangays_list as $barangayOption): ?><option value="<?= htmlspecialchars($barangayOption) ?>"><?= htmlspecialchars($barangayOption) ?></option><?php endforeach; ?></select></div>
                    <div class="full"><button class="btn btn-primary" type="submit"><i class="fas fa-user-plus"></i> Add Personnel</button></div>
                </form>
            </div></div>
            <div class="panel"><div class="panel-head"><h2>Personnel Directory</h2></div><div class="table-wrap"><table><thead><tr><th>Name</th><th>Position</th><th>Assignment</th><th>Contact</th><th>Status</th><th>Action</th></tr></thead><tbody data-paginate="10" data-pagination-label="Personnel directory pages">
                <?php if (!$personnel): ?><tr><td colspan="6">No personnel added yet.</td></tr><?php endif; ?>
                <?php foreach ($personnel as $p): ?><tr><td><strong><?= htmlspecialchars($p['full_name']) ?></strong></td><td><?= htmlspecialchars($p['position'] ?? '-') ?></td><td><?= htmlspecialchars($p['barangay'] ?: 'City-wide') ?></td><td><?= htmlspecialchars($p['contact_number'] ?? '-') ?></td><td><span class="badge <?= (int)$p['is_active'] ? 'eligible' : 'cancelled' ?>"><?= (int)$p['is_active'] ? 'Active' : 'Inactive' ?></span></td><td><div class="row-actions"><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="action" value="toggle_personnel"><input type="hidden" name="personnel_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-muted" type="submit"><?= (int)$p['is_active'] ? 'Deactivate' : 'Activate' ?></button></form><form method="post" onsubmit="return confirm('Archive <?= htmlspecialchars($p['full_name'], ENT_QUOTES) ?>? They will no longer be available for new home visits.');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="action" value="archive_personnel"><input type="hidden" name="personnel_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-danger" type="submit"><i class="fas fa-box-archive"></i> Archive</button></form></div></td></tr><?php endforeach; ?>
            </tbody></table></div></div>
            <div class="panel"><div class="panel-head"><h2>Archived Personnel</h2><span class="muted">Restored personnel remain inactive until you activate them.</span></div><div class="table-wrap"><table><thead><tr><th>Name</th><th>Position</th><th>Assignment</th><th>Archived</th><th>Action</th></tr></thead><tbody data-paginate="10" data-pagination-label="Archived personnel pages">
                <?php if (!$archivedPersonnel): ?><tr><td colspan="5">No archived personnel.</td></tr><?php endif; ?>
                <?php foreach ($archivedPersonnel as $p): ?><tr><td><strong><?= htmlspecialchars($p['full_name']) ?></strong></td><td><?= htmlspecialchars($p['position'] ?? '-') ?></td><td><?= htmlspecialchars($p['barangay'] ?: 'City-wide') ?></td><td><?= $p['archived_at'] ? htmlspecialchars(date('M j, Y', strtotime($p['archived_at']))) : '-' ?></td><td><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="action" value="restore_personnel"><input type="hidden" name="personnel_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-muted" type="submit"><i class="fas fa-rotate-left"></i> Restore</button></form></td></tr><?php endforeach; ?>
            </tbody></table></div></div>
        </section>
        <?php endif; ?>
    </div>
</div>
<script src="../assets/js/sidebar-toggle.js?v=3"></script>
<script>
    const visitFilterForm = document.querySelector('.visit-filters');
    const visitSearchInput = document.getElementById('visitSearch');
    let visitSearchTimer;

    visitSearchInput?.addEventListener('input', () => {
        window.clearTimeout(visitSearchTimer);
        visitSearchTimer = window.setTimeout(() => visitFilterForm?.requestSubmit(), 350);
    });
    visitFilterForm?.querySelectorAll('select, input[type="date"]').forEach((control) => {
        control.addEventListener('change', () => visitFilterForm.requestSubmit());
    });

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

    function toDateInput(value) {
        if (!value) return '';
        return value.slice(0, 10);
    }

    function openVisit(visit) {
        const assignmentEditor = document.getElementById('visitEditor');
        if (assignmentEditor) {
            assignmentEditor.hidden = false;
            document.getElementById('visitApplicationId').value = visit.id_number;
            document.getElementById('visitEditorName').textContent = visit.full_name + ' (' + visit.id_number + ')';
            if (document.getElementById('visitReason')) document.getElementById('visitReason').value = visit.home_visit_eligibility_reason || '';
            if (document.getElementById('visitPersonnel')) document.getElementById('visitPersonnel').value = visit.home_visit_personnel_id || '';
            if (document.getElementById('visitSchedule')) document.getElementById('visitSchedule').value = toDateInput(visit.home_visit_scheduled_at);
            assignmentEditor.scrollIntoView({behavior:'smooth', block:'start'});
            return;
        }

        const modal = document.getElementById('evaluationModal');
        const form = document.getElementById('visitStatusForm');
        if (!modal || !form) return;
        form.reset();
        document.getElementById('statusApplicationId').value = visit.id_number;
        document.getElementById('evaluationSenior').textContent = visit.full_name || '—';
        document.getElementById('evaluationBarangay').textContent = visit.barangay || '—';
        document.getElementById('evaluationPersonnel').textContent = visit.personnel_name || 'Not assigned';
        document.getElementById('evaluationDate').textContent = visit.home_visit_scheduled_at
            ? new Intl.DateTimeFormat('en-PH', {year:'numeric', month:'long', day:'numeric'}).format(new Date(visit.home_visit_scheduled_at.replace(' ', 'T')))
            : 'Not scheduled';

        const values = {
            visit_status: visit.home_visit_status || 'Scheduled', living_arrangement: visit.living_arrangement,
            is_pensioner: visit.is_pensioner, pension_source: visit.pension_source, pension_amount: visit.pension_amount,
            family_support: visit.family_support, family_support_amount: visit.family_support_amount,
            personal_income: visit.personal_income, personal_income_amount: visit.personal_income_amount,
            health_condition: visit.health_condition, with_maintenance: visit.with_maintenance,
            maintenance_spec: visit.maintenance_spec, confirmation_name: visit.claimant_name,
            confirmation_contact: visit.claimant_contact, eligibility: visit.home_visit_eligibility,
            eligibility_reason: visit.home_visit_eligibility_reason, notes: visit.visit_summary || visit.home_visit_notes
        };
        Object.entries(values).forEach(([name, value]) => {
            const field = form.elements.namedItem(name);
            if (field) field.value = value === null || value === undefined ? '' : String(value);
        });
        updateEvaluationDependencies();
        const message = document.getElementById('evaluationMessage');
        message.hidden = true;
        message.className = 'evaluation-message full';
        evaluationModal.currentVisit = visit;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('evaluation-open');
        modal.querySelector('select[name="visit_status"]')?.focus();
    }

    const evaluationModal = document.getElementById('evaluationModal');
    const evaluationForm = document.getElementById('visitStatusForm');
    const dependentFields = {
        is_pensioner: ['pension_source', 'pension_amount'],
        family_support: ['family_support_amount'],
        personal_income: ['personal_income_amount']
    };
    function updateEvaluationDependencies() {
        if (!evaluationForm) return;
        const completed = evaluationForm.elements.namedItem('visit_status').value === 'Completed';
        for (const [answerName, fieldNames] of Object.entries(dependentFields)) {
            const answer = evaluationForm.elements.namedItem(answerName).value;
            for (const fieldName of fieldNames) {
                const field = evaluationForm.elements.namedItem(fieldName);
                if (answer === '0') field.value = '';
                field.disabled = answer !== '1';
                field.required = completed && answer === '1';
            }
        }
    }
    evaluationForm?.addEventListener('change', (event) => {
        if (event.target.name === 'visit_status' || Object.hasOwn(dependentFields, event.target.name)) updateEvaluationDependencies();
    });
    const closeEvaluation = () => {
        if (!evaluationModal) return;
        evaluationModal.classList.remove('open');
        evaluationModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('evaluation-open');
    };
    document.querySelectorAll('[data-close-evaluation]').forEach((button) => button.addEventListener('click', closeEvaluation));
    evaluationModal?.addEventListener('click', (event) => { if (event.target === evaluationModal) closeEvaluation(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && evaluationModal?.classList.contains('open')) closeEvaluation(); });

    evaluationForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const status = evaluationForm.elements.namedItem('visit_status').value;
        const requiredWhenComplete = ['living_arrangement', 'is_pensioner', 'family_support', 'eligibility', 'notes'];
        requiredWhenComplete.forEach((name) => evaluationForm.elements.namedItem(name)?.removeAttribute('required'));
        if (status === 'Completed') requiredWhenComplete.forEach((name) => evaluationForm.elements.namedItem(name)?.setAttribute('required', 'required'));
        updateEvaluationDependencies();
        if (!evaluationForm.reportValidity()) return;

        const saveButton = document.getElementById('saveEvaluation');
        const message = document.getElementById('evaluationMessage');
        saveButton.disabled = true;
        message.hidden = true;
        try {
            const response = await fetch('field_operations.php', {method:'POST', body:new FormData(evaluationForm), headers:{'X-Requested-With':'XMLHttpRequest'}});
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'The evaluation could not be saved.');

            const applicationId = evaluationForm.elements.namedItem('application_id').value;
            const row = document.getElementById('visit-row-' + CSS.escape(applicationId));
            if (status === 'Rejected') {
                row?.remove();
            } else if (row) {
                const badge = row.querySelector('.visit-status-badge');
                if (badge) {
                    badge.textContent = status;
                    badge.className = 'badge visit-status-badge ' + status.toLowerCase().replaceAll(' ', '-');
                }
                const button = row.querySelector('.visit-editor-button');
                if (button) {
                    button.textContent = 'View / Update Evaluation';
                    const updatedVisit = {...(evaluationModal.currentVisit || {})};
                    const fieldMap = {
                        visit_status:'home_visit_status', living_arrangement:'living_arrangement', is_pensioner:'is_pensioner',
                        pension_source:'pension_source', pension_amount:'pension_amount', family_support:'family_support',
                        family_support_amount:'family_support_amount', personal_income:'personal_income',
                        personal_income_amount:'personal_income_amount', health_condition:'health_condition',
                        with_maintenance:'with_maintenance', maintenance_spec:'maintenance_spec',
                        confirmation_name:'claimant_name', confirmation_contact:'claimant_contact',
                        eligibility:'home_visit_eligibility', eligibility_reason:'home_visit_eligibility_reason',
                        notes:'visit_summary'
                    };
                    Object.entries(fieldMap).forEach(([fieldName, property]) => {
                        updatedVisit[property] = evaluationForm.elements.namedItem(fieldName)?.value ?? '';
                    });
                    updatedVisit.home_visit_notes = evaluationForm.elements.namedItem('notes')?.value || '';
                    button.onclick = () => openVisit(updatedVisit);
                }
                const eligibilityBadge = row.cells[2]?.querySelector('.badge');
                const eligibility = evaluationForm.elements.namedItem('eligibility').value;
                if (eligibilityBadge && status === 'Completed' && eligibility) {
                    eligibilityBadge.textContent = eligibility;
                    eligibilityBadge.className = 'badge ' + eligibility.toLowerCase().replaceAll(' ', '-');
                }
            }
            closeEvaluation();
            const notice = document.createElement('div');
            notice.className = 'notice ok';
            notice.setAttribute('role', 'status');
            notice.innerHTML = '<i class="fas fa-circle-check" aria-hidden="true"></i><span>' + result.message.replace(/[<>&]/g, '') + '</span>';
            document.querySelector('.tabs')?.before(notice);
            setTimeout(() => notice.remove(), 5000);
        } catch (error) {
            message.textContent = error.message;
            message.className = 'evaluation-message full error';
            message.hidden = false;
        } finally {
            saveButton.disabled = false;
        }
    });

    const visitPersonnel = document.getElementById('visitPersonnel');
    const visitSchedule = document.getElementById('visitSchedule');
    if (visitPersonnel) visitPersonnel.required = true;
    if (visitSchedule) {
        visitSchedule.required = true;
        const today = new Date();
        const localToday = new Date(today.getTime() - today.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
        visitSchedule.min = localToday;
        const validateVisitSchedule = () => {
            visitSchedule.setCustomValidity('');
            if (!visitSchedule.value) return;
            const selected = new Date(visitSchedule.value + 'T12:00:00');
            const day = selected.getDay();
            if (day === 0 || day === 6) visitSchedule.setCustomValidity('Home visits can only be scheduled Monday to Friday.');
        };
        visitSchedule.addEventListener('input', validateVisitSchedule);
        visitSchedule.addEventListener('change', () => {
            validateVisitSchedule();
            if (visitSchedule.validity.customError) {
                visitSchedule.reportValidity();
                visitSchedule.value = '';
                visitSchedule.setCustomValidity('');
            }
        });
        document.getElementById('visitForm')?.addEventListener('submit', validateVisitSchedule);
    }
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const selector = 'input[type="tel"],input[name*="contact" i]:not([type="hidden"]),input[id*="contact" i]:not([type="hidden"]),input[name="phone" i],input[id="phone" i],input[oninput*="contactNumber"],input[oninput*="emergencyContact"]';
    const restrictContact = input => {
        const identity = `${input.name || ''} ${input.id || ''} ${input.getAttribute('oninput') || ''}`.toLowerCase();
        if (identity.includes('contactname') || identity.includes('contact-name') || input.readOnly) return;
        input.type = 'tel';
        input.inputMode = 'numeric';
        input.maxLength = 11;
        input.pattern = '09[0-9]{9}';
        input.title = 'Enter exactly 11 digits beginning with 09.';
        input.addEventListener('input', () => {
            input.value = input.value.replace(/\D/g, '').slice(0, 11);
            input.setCustomValidity(input.value && !/^09\d{9}$/.test(input.value) ? 'Enter exactly 11 digits beginning with 09.' : '');
        });
    };
    document.querySelectorAll(selector).forEach(restrictContact);
});
</script>
</body>
</html>
