<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/audit_logger.php';

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
        operationsRedirect('Hardcopy batch processing has been removed from SENIORLINK.', false);
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
            if (!$isDepartment) operationsRedirect('Only the Department Admin can assign and schedule surprise home visits.', false);
            $applicationId = trim((string)($_POST['application_id'] ?? ''));
            $app = fetchScopedApplication($conn, $applicationId, $isDepartment, $barangay);
            if (!$app || ($app['application_type'] ?? '') !== 'pension') operationsRedirect('Local pension application not found.', false);

            $eligibility = 'Eligible';
            $reason = trim(strip_tags((string)($_POST['reason'] ?? '')));
            $notes = trim(strip_tags((string)($_POST['notes'] ?? '')));

            $schedule = null;
            $personnelId = null;
            $status = 'Scheduled';
            {
                $personnelId = filter_var($_POST['personnel_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
                $rawSchedule = trim((string)($_POST['scheduled_at'] ?? ''));
                $tz = new DateTimeZone('Asia/Manila');
                $date = DateTime::createFromFormat('Y-m-d\TH:i', $rawSchedule, $tz);
                $errors = DateTime::getLastErrors();
                $validDate = $date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
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

            operationsRedirect('Home visit assignment saved. The schedule remains private to authorized personnel.');
        }

        if ($action === 'update_visit_status') {
            if (!$isDepartment) operationsRedirect('Only the Department Admin can update surprise home visits.', false);
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
    <link rel="stylesheet" href="../assets/css/seniorlink-ui.css?v=16">
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

        @media(max-width:900px){
            .form-grid{grid-template-columns:1fr 1fr;}
        }
        @media(max-width:600px){
            .main-content{padding:14px}.form-grid{grid-template-columns:1fr}.full{grid-column:auto}
            .availability-count { white-space:nowrap; }
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
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Senior</th><th>Barangay</th><th>Eligibility</th><th>Schedule</th><th>Personnel</th><th>Status</th><?php if ($isDepartment): ?><th>Manage</th><?php endif; ?></tr></thead>
                        <tbody data-paginate="10" data-pagination-label="Home visit pages">
                        <?php if (!$visits): ?><tr><td colspan="<?= $isDepartment ? 7 : 6 ?>">No local pension applications found.</td></tr><?php endif; ?>
                        <?php foreach ($visits as $visit): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($visit['full_name']) ?></strong><br><span class="muted"><?= htmlspecialchars($visit['id_number']) ?></span></td>
                                <td><?= htmlspecialchars($visit['barangay']) ?></td>
                                <td><span class="badge <?= strtolower(str_replace(' ', '-', $visit['home_visit_eligibility'] ?? 'pending')) ?>"><?= htmlspecialchars($visit['home_visit_eligibility'] ?: 'Pending') ?></span><br><span class="muted"><?= htmlspecialchars($visit['home_visit_eligibility_reason'] ?? '') ?></span></td>
                                <td><?= $visit['home_visit_scheduled_at'] ? htmlspecialchars(date('M j, Y g:i A', strtotime($visit['home_visit_scheduled_at']))) : '-' ?></td>
                                <td><?= htmlspecialchars($visit['personnel_name'] ?? '-') ?></td>
                                <?php $visitStatus = $visit['home_visit_status'] ?: 'Waiting for Home Visit'; ?>
                                <td><span class="badge <?= strtolower(str_replace(' ', '-', $visitStatus)) ?>"><?= htmlspecialchars($visitStatus) ?></span></td>
                                <?php if ($isDepartment): ?><td><button class="btn btn-primary" type="button" onclick='openVisit(<?= json_encode($visit, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Assign / Schedule</button></td><?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($isDepartment): ?>
            <div class="panel" id="visitEditor">
                <div class="panel-head"><h2>Assessment and Schedule</h2><span class="muted" id="visitEditorName">Select a senior above</span></div>
                <div class="panel-body">
                    <form method="post" class="form-grid" id="visitForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="save_visit">
                        <input type="hidden" name="application_id" id="visitApplicationId">
                        <div class="field full"><span class="muted"><i class="fas fa-circle-info"></i> This required home visit is privately scheduled by the Department Admin. Do not disclose the date in advance.</span></div>
                        <div class="field visit-assignment"><label>Assigned Personnel</label><select name="personnel_id" id="visitPersonnel"><option value="">Select personnel</option><?php foreach ($activePersonnel as $p): ?><option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['full_name'] . (!empty($p['position']) ? ' - ' . $p['position'] : '')) ?></option><?php endforeach; ?></select></div>
                        <div class="field visit-assignment"><label>Schedule</label><input type="datetime-local" name="scheduled_at" id="visitSchedule" step="1800" onclick="if (this.showPicker && !this.disabled) this.showPicker()"></div>
                        <div class="field"><label>Internal scheduling note (optional)</label><input name="reason" id="visitReason" maxlength="255" placeholder="Internal reason or assignment note"></div>
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
            <?php else: ?>
            <div class="panel"><div class="panel-body"><p class="muted"><i class="fas fa-user-shield"></i> Local Pension applications are automatically queued for an unannounced home visit. The Department Admin privately assigns personnel and schedules each visit.</p></div></div>
            <?php endif; ?>
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

    function toLocalInput(value) {
        if (!value) return '';
        return value.replace(' ', 'T').slice(0, 16);
    }

    function openVisit(visit) {
        document.getElementById('visitApplicationId').value = visit.id_number;
        document.getElementById('statusApplicationId').value = visit.id_number;
        document.getElementById('visitEditorName').textContent = visit.full_name + ' (' + visit.id_number + ')';
        document.getElementById('visitReason').value = visit.home_visit_eligibility_reason || '';
        document.getElementById('visitPersonnel').value = visit.home_visit_personnel_id || '';
        document.getElementById('visitSchedule').value = toLocalInput(visit.home_visit_scheduled_at);
        document.getElementById('visitNotes').value = visit.home_visit_notes || '';
        document.getElementById('visitEditor').scrollIntoView({behavior:'smooth', block:'start'});
    }

    const visitPersonnel = document.getElementById('visitPersonnel');
    const visitSchedule = document.getElementById('visitSchedule');
    if (visitPersonnel) visitPersonnel.required = true;
    if (visitSchedule) visitSchedule.required = true;
</script>
</body>
</html>
