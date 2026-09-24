<?php
session_start();
require_once '../includes/db_connect.php';

$role = $_SESSION['role'] ?? '';
if (!isset($_SESSION['user_id']) || !in_array($role, ['barangay_staff', 'department_admin', 'super_admin'], true)) {
    header('Location: ../index.php');
    exit;
}

$search = trim((string)($_GET['search'] ?? ''));
if (mb_strlen($search) > 100) $search = mb_substr($search, 0, 100);
$idStatus = (string)($_GET['id_status'] ?? 'all');
if (!in_array($idStatus, ['all', 'pending', 'generated'], true)) $idStatus = 'all';
$page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
// Keep this list compact so page controls remain useful even for a small
// barangay/department result set. The query still retrieves only one page.
$perPage = 5;
$where = ["application_type = 'senior'", "workflow_state IN ('Verified', 'Approved', 'Released')", 'COALESCE(is_archived, 0) = 0'];
$params = [];
if ($role === 'barangay_staff') {
    $where[] = 'barangay = ?';
    $params[] = $_SESSION['barangay'] ?? '';
}
if ($search !== '') {
    // LOWER() keeps name/ID searches case-insensitive on both MySQL and PostgreSQL.
    $where[] = '(LOWER(full_name) LIKE LOWER(?) OR LOWER(COALESCE(senior_id_no, \'\')) LIKE LOWER(?) OR LOWER(id_number) LIKE LOWER(?))';
    array_push($params, "%{$search}%", "%{$search}%", "%{$search}%");
}

// Railway may use PostgreSQL while the local XAMPP database uses MySQL.
// Use each database's regular-expression operator so ID-status filtering works
// identically in both environments.
$temporaryIdPattern = '^OSCA-[0-9]{4}-[0-9A-F]{6}$';
$isPostgres = ($driver ?? 'mysql') === 'pgsql';
$temporaryIdCondition = $isPostgres
    ? "senior_id_no ~* '{$temporaryIdPattern}'"
    : "senior_id_no REGEXP '{$temporaryIdPattern}'";
if ($idStatus === 'pending') {
    $where[] = "(senior_id_no IS NULL OR senior_id_no = '' OR {$temporaryIdCondition})";
} elseif ($idStatus === 'generated') {
    $where[] = "senior_id_no IS NOT NULL AND senior_id_no <> '' AND NOT ({$temporaryIdCondition})";
}
$whereSql = implode(' AND ', $where);
$countStmt = $conn->prepare("SELECT COUNT(*) FROM applications WHERE {$whereSql}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;
$pendingIdOrder = "CASE WHEN senior_id_no IS NULL OR senior_id_no = '' OR {$temporaryIdCondition} THEN 0 ELSE 1 END";
$listStmt = $conn->prepare("SELECT id_number, senior_id_no, full_name, barangay, birth_date, date_submitted FROM applications WHERE {$whereSql} ORDER BY {$pendingIdOrder} ASC, full_name ASC LIMIT {$perPage} OFFSET {$offset}");
$listStmt->execute($params);
$records = $listStmt->fetchAll(PDO::FETCH_ASSOC);
$isDepartment = in_array($role, ['department_admin', 'super_admin'], true);
function hasOfficialSeniorId(array $record): bool {
    $id = trim((string)($record['senior_id_no'] ?? ''));
    return $id !== '' && !preg_match('/^OSCA-[0-9]{4}-[0-9A-F]{6}$/i', $id);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital IDs — SENIORLINK</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/<?= $isDepartment ? 'department' : 'barangay' ?>-sidebar.css?v=<?= $isDepartment ? '5' : '4' ?>">
    <link rel="stylesheet" href="../assets/css/system-sidebar.css?v=3">
    <link rel="stylesheet" href="../assets/css/system-header.css?v=1">
    <link rel="stylesheet" href="../assets/css/seniorlink-ui.css?v=20">
    <style>
        *{box-sizing:border-box}html,body{max-width:100%;overflow-x:hidden}body{margin:0;background:#f4f7fb;color:#172033;font-family:Inter,"Segoe UI",Arial,sans-serif}.main-content{width:calc(100% - 220px);margin-left:220px;min-height:100vh;padding:32px}.main-content.collapsed{width:calc(100% - 80px)}.page-shell{width:100%;max-width:1180px;margin:auto}.page-header{display:flex;justify-content:space-between;align-items:center;gap:18px;margin-bottom:24px}.page-header-left .greeting{margin-bottom:6px;color:#6b7280;font-size:.98rem;font-weight:500}.page-header-left .greeting strong{color:#2563eb}.page-header h1{margin:0;font-size:2rem;line-height:1.05;color:#0f2942}.page-header h1 span{color:#2563eb}.header-user{display:flex;align-items:center;gap:12px;padding:7px 13px;border:3px solid transparent;border-radius:30px;background:linear-gradient(#fff,#fff) padding-box,linear-gradient(135deg,#0f172a,#3498db) border-box;box-shadow:0 2px 8px rgba(0,0,0,.08)}.header-user img{width:40px;height:40px;object-fit:cover;border:2px solid #2563eb;border-radius:50%}.header-user h3,.header-user p{margin:0}.header-user h3{font-size:.9rem}.header-user p{color:#64748b;font-size:.75rem}.page-tools{display:flex;justify-content:flex-end;margin-bottom:16px}.search{display:flex;gap:8px;min-width:0}.search input{width:320px;max-width:48vw;padding:11px 12px;border:1px solid #cbd5e1;border-radius:9px;font:inherit}.search button{padding:0 15px;border:0;border-radius:9px;background:#2563eb;color:#fff;font-weight:800;cursor:pointer}.card{overflow:hidden;border:1px solid #d9e3ee;border-radius:14px;background:#fff;box-shadow:0 8px 24px rgba(15,23,42,.06)}table{width:100%;border-collapse:collapse}th,td{padding:14px 16px;border-bottom:1px solid #e5e7eb;text-align:left}th{background:#f8fafc;color:#64748b;font-size:.7rem;text-transform:uppercase;letter-spacing:.05em}td{font-size:.86rem}.id-number{color:#dc2626;font-weight:900}.view{display:inline-flex;align-items:center;gap:6px;padding:8px 11px;border:0;border-radius:8px;background:#2563eb;color:#fff;font:inherit;font-size:.75rem;font-weight:800;cursor:pointer}.assign-form{display:flex;align-items:center;gap:7px;min-width:270px}.assign-form input{width:170px;padding:8px 9px;border:1px solid #cbd5e1;border-radius:8px;font:inherit;font-size:.75rem;text-transform:uppercase}.assign-form input:focus{outline:3px solid rgba(37,99,235,.16);border-color:#2563eb}.assign-status{display:block;margin-top:5px;color:#b91c1c;font-size:.7rem}.empty{padding:48px;text-align:center;color:#64748b}.pagination{display:flex;justify-content:center;align-items:center;gap:10px;margin-top:18px}.pagination a{padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#334155;text-decoration:none}.pagination .disabled{opacity:.45;pointer-events:none}.id-modal{position:fixed;inset:0;z-index:2000;display:none;align-items:center;justify-content:center;padding:20px;background:rgba(15,23,42,.68);backdrop-filter:blur(3px)}.id-modal.open{display:flex}.id-modal-box{display:flex;flex-direction:column;width:min(900px,100%);max-height:94vh;overflow:hidden;border-radius:18px;background:#fff;box-shadow:0 28px 80px rgba(15,23,42,.35)}.id-modal-head{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #e2e8f0}.id-modal-head h2{margin:0;font-size:1rem}.id-modal-close{width:36px;height:36px;border:0;border-radius:8px;background:#f1f5f9;color:#475569;font-size:1.25rem;cursor:pointer}.id-frame{width:100%;height:min(710px,calc(94vh - 126px));border:0;background:#eef3f9}.id-modal-actions{display:flex;justify-content:flex-end;gap:9px;padding:12px 20px;border-top:1px solid #e2e8f0}.id-modal-actions button{padding:9px 13px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#334155;font-weight:800;cursor:pointer}.id-modal-actions .primary{border-color:#2563eb;background:#2563eb;color:#fff}@media(max-width:800px){.main-content,.main-content.collapsed{width:100%;margin-left:0;padding:20px 12px}.page-header{align-items:flex-start;flex-direction:column}.header-user{align-self:stretch}.page-tools,.search,.search input{width:100%;max-width:none}.card{overflow:auto}table{min-width:900px}.id-modal{padding:8px}.id-frame{height:78vh}} 
        /* Digital ID workspace */
        .page-shell{max-width:none}.search select{min-height:44px;padding:0 10px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;color:#334155;font:inherit;font-size:.8rem}.clear-search{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 12px;border:1px solid #cbd5e1;border-radius:10px;color:#334155;background:#fff;font-size:.8rem;font-weight:800;text-decoration:none}.pagination .pagination-summary{color:#64748b;font-size:.78rem;font-weight:700}.pagination .current{padding:8px 12px;border:1px solid #2563eb;border-radius:8px;background:#2563eb;color:#fff;font-weight:800}.pagination .ellipsis{color:#64748b}
        .page-tools{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:12px;padding:16px 18px;background:#fff;border:1px solid #d9e3ee;border-radius:14px;box-shadow:0 6px 18px rgba(15,23,42,.045)}
        .records-summary h2{margin:0 0 4px;color:#0f2942;font-size:1rem}.records-summary p{margin:0;color:#64748b;font-size:.76rem}.record-count{color:#2563eb;font-weight:800}
        .search input{min-height:44px;padding:11px 13px;border-radius:10px}.search input:focus{outline:3px solid rgba(37,99,235,.14);border-color:#2563eb}.search button{min-height:44px;padding-inline:17px;border-radius:10px;box-shadow:0 5px 12px rgba(37,99,235,.2)}
        .search button:hover,.view:hover{background:#1d4ed8}.card{box-shadow:0 8px 24px rgba(15,23,42,.06)}th,td{padding:16px 18px}th{letter-spacing:.07em}td{vertical-align:middle}tbody tr:nth-child(even){background:#f8fafc}tbody tr:hover{background:#eff6ff}.id-number{color:#0f2942;letter-spacing:.025em}tbody td:nth-child(3) strong{text-transform:capitalize}
        .view{justify-content:center;min-height:40px;padding:8px 13px;border-radius:9px;white-space:nowrap;transition:background-color .15s ease,transform .15s ease}.view:hover{transform:translateY(-1px)}.generate{background:#16814b}.generate:hover{background:#116b3e}.row-actions{display:flex;align-items:center;justify-content:flex-end;gap:8px;white-space:nowrap}.action-cell{text-align:right}.assign-status{max-width:250px;margin-left:auto;text-align:right}
        .action-modal{position:fixed;inset:0;z-index:2100;display:none;align-items:center;justify-content:center;padding:20px;background:rgba(15,23,42,.68);backdrop-filter:blur(4px)}.action-modal.open{display:flex}.action-dialog{width:min(500px,100%);overflow:hidden;border:1px solid #dbe4ef;border-radius:18px;background:#fff;box-shadow:0 28px 80px rgba(15,23,42,.32)}.action-dialog-head{display:flex;align-items:center;gap:12px;padding:20px 22px 14px}.action-dialog-icon{width:44px;height:44px;display:grid;place-items:center;flex:0 0 44px;border-radius:12px;background:#e7f6ed;color:#16814b;font-size:1.15rem}.action-dialog-head h2{flex:1;margin:0;color:#0f2942;font-size:1.15rem}.action-dialog-close{width:34px;height:34px;border:0;border-radius:8px;background:#f1f5f9;color:#64748b;font-size:1.2rem;cursor:pointer}.action-dialog-body{padding:2px 22px 20px;color:#475569;line-height:1.55}.action-dialog-body>p{margin:0 0 14px}.action-details{display:grid;gap:7px;padding:13px 15px;border:1px solid #dbe4ef;border-radius:11px;background:#f8fafc}.action-details div{display:grid;grid-template-columns:145px minmax(0,1fr);gap:8px}.action-details strong{color:#172033}.action-warning{margin-top:14px!important;padding:12px 14px;border:1px solid #f4cf70;border-radius:10px;background:#fff8e6;color:#7a4b0b;font-size:.82rem}.action-message{padding:12px 14px;border-radius:10px;background:#fef2f2;color:#991b1b}.action-dialog-actions{display:flex;justify-content:flex-end;gap:9px;padding:14px 22px;border-top:1px solid #e2e8f0;background:#f8fafc}.modal-button{min-height:42px;padding:9px 15px;border:1px solid #cbd5e1;border-radius:9px;background:#fff;color:#334155;font:inherit;font-size:.8rem;font-weight:800;cursor:pointer}.modal-button.generate-action{border-color:#16814b;background:#16814b;color:#fff}.modal-button.view-action{border-color:#2563eb;background:#2563eb;color:#fff}.modal-button:disabled{opacity:.6;cursor:wait}.action-modal [hidden]{display:none!important}
        @media(max-width:800px){.page-tools{align-items:stretch;flex-direction:column}.records-summary{width:100%}.search button{min-width:96px}}
    </style>
</head>
<body>
<?php include $isDepartment ? '../partials/department_sidebar.php' : '../partials/barangay_sidebar.php'; ?>
<main class="main-content">
    <header class="page-header"><div class="page-header-left"><div class="greeting" id="greetingMsg"></div><h1>Digital <span>IDs</span></h1></div><div class="header-user"><?php $profilePicPath='../images/profile_pictures/'.($_SESSION['profile_picture']??'default.jpg'); if(!file_exists($profilePicPath)||is_dir($profilePicPath))$profilePicPath='../images/profile_pictures/default.jpg'; ?><img src="<?= htmlspecialchars($profilePicPath) ?>" alt="Profile"><div class="header-user-info"><h3><?= htmlspecialchars(trim(($_SESSION['first_name']??'').' '.($_SESSION['last_name']??''))) ?></h3><p><?= htmlspecialchars(ucwords(str_replace('_',' ',$role))) ?> · <?= $isDepartment?'Pasig City':htmlspecialchars($_SESSION['barangay']??'') ?></p></div></div></header>
<div class="page-shell">
    <div class="page-tools"><div class="records-summary"><h2>Issued Senior Citizen IDs</h2><p><span class="record-count"><?= number_format($total) ?></span> approved <?= $total === 1 ? 'record' : 'records' ?> available</p></div><form class="search" method="get"><select name="id_status" aria-label="Filter digital IDs" onchange="this.form.submit()"><option value="all" <?= $idStatus === 'all' ? 'selected' : '' ?>>All IDs</option><option value="pending" <?= $idStatus === 'pending' ? 'selected' : '' ?>>Not yet generated</option><option value="generated" <?= $idStatus === 'generated' ? 'selected' : '' ?>>Generated IDs</option></select><input name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, ID, or application…" maxlength="100" aria-label="Search digital IDs"><button type="submit"><i class="fas fa-search"></i> Search</button><?php if ($search !== '' || $idStatus !== 'all'): ?><a class="clear-search" href="digital_ids.php">Clear</a><?php endif; ?></form></div>
    <section class="card">
        <?php if (!$records): ?><div class="empty"><i class="fas fa-id-card fa-2x"></i><p>No approved digital IDs found.</p></div>
        <?php else: ?><table><thead><tr><th>Senior Citizen ID No.</th><th>Application Token</th><th>Applicant</th><th>Barangay</th><th>Birth Date</th><th class="action-cell">Action</th></tr></thead><tbody>
        <?php foreach ($records as $record): $hasOfficialId = hasOfficialSeniorId($record); ?><tr data-record-id="<?= htmlspecialchars($record['id_number']) ?>"><td class="id-number"><?= $hasOfficialId ? htmlspecialchars($record['senior_id_no']) : '<span style="color:#b45309">Not yet generated</span>' ?></td><td><strong><?= htmlspecialchars($record['id_number']) ?></strong><br><small>Use for application tracking</small></td><td><strong><?= htmlspecialchars($record['full_name']) ?></strong></td><td><?= htmlspecialchars($record['barangay']) ?></td><td><?= htmlspecialchars(date('F j, Y', strtotime($record['birth_date']))) ?></td><td class="action-cell"><div class="row-actions"><?php if ($hasOfficialId): ?><button class="view" type="button" data-digital-id="<?= htmlspecialchars($record['id_number']) ?>" data-applicant="<?= htmlspecialchars($record['full_name']) ?>"><i class="fas fa-eye"></i> View Digital ID</button><?php elseif ($isDepartment): ?><button class="view generate" type="button" data-generate-id="<?= htmlspecialchars($record['id_number']) ?>" data-applicant="<?= htmlspecialchars($record['full_name']) ?>"><i class="fas fa-id-card"></i> Generate Senior ID</button><?php else: ?><small>Awaiting OSCA generation</small><?php endif; ?></div><small class="assign-status" aria-live="polite"></small></td></tr><?php endforeach; ?>
        </tbody></table><?php endif; ?>
    </section>
    <?php
        $query = ($search !== '' ? '&search=' . rawurlencode($search) : '') . ($idStatus !== 'all' ? '&id_status=' . rawurlencode($idStatus) : '');
        $startPage = max(1, $page - 2); $endPage = min($pages, $page + 2);
        $firstShown = $total === 0 ? 0 : $offset + 1;
        $lastShown = min($offset + $perPage, $total);
    ?><nav class="pagination" aria-label="Digital ID pages"><span class="pagination-summary">Showing <?= number_format($firstShown) ?>–<?= number_format($lastShown) ?> of <?= number_format($total) ?></span><a class="<?= $page <= 1 ? 'disabled' : '' ?>" href="?page=<?= max(1,$page-1) . $query ?>">Previous</a><?php if ($startPage > 1): ?><a href="?page=1<?= $query ?>">1</a><?php if ($startPage > 2): ?><span class="ellipsis">…</span><?php endif; ?><?php endif; ?><?php for ($number = $startPage; $number <= $endPage; $number++): ?><?php if ($number === $page): ?><span class="current" aria-current="page"><?= $number ?></span><?php else: ?><a href="?page=<?= $number . $query ?>"><?= $number ?></a><?php endif; ?><?php endfor; ?><?php if ($endPage < $pages): ?><?php if ($endPage < $pages - 1): ?><span class="ellipsis">…</span><?php endif; ?><a href="?page=<?= $pages . $query ?>"><?= $pages ?></a><?php endif; ?><a class="<?= $page >= $pages ? 'disabled' : '' ?>" href="?page=<?= min($pages,$page+1) . $query ?>">Next</a></nav>
</div></main>
<div class="id-modal" id="digitalIdModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="digitalIdModalTitle">
    <div class="id-modal-box"><div class="id-modal-head"><h2 id="digitalIdModalTitle"><i class="fas fa-id-card"></i> Temporary Digital Senior Citizen ID</h2><button class="id-modal-close" type="button" aria-label="Close digital ID">&times;</button></div><iframe class="id-frame" id="digitalIdFrame" title="Temporary Digital Senior Citizen ID"></iframe><div class="id-modal-actions"><button type="button" data-close-id>Close</button><button type="button" class="primary" id="printDigitalId"><i class="fas fa-print"></i> Print / Save PDF</button></div></div>
</div>
<div class="action-modal" id="seniorIdActionModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="seniorIdActionTitle">
    <div class="action-dialog">
        <div class="action-dialog-head"><span class="action-dialog-icon"><i class="fas fa-id-card" id="seniorIdActionIcon"></i></span><h2 id="seniorIdActionTitle">Generate Senior Citizen ID?</h2><button class="action-dialog-close" type="button" data-action-close aria-label="Close">&times;</button></div>
        <div class="action-dialog-body">
            <p id="seniorIdActionIntro">You are about to generate a permanent Senior Citizen ID for:</p>
            <div class="action-details" id="seniorIdActionDetails"><div><span>Applicant:</span><strong id="seniorIdApplicant"></strong></div><div><span>Permanent Token ID:</span><strong id="seniorIdToken"></strong></div><div id="generatedIdRow" hidden><span>Senior Citizen ID No.:</span><strong id="generatedSeniorId"></strong></div></div>
            <p class="action-warning" id="seniorIdWarning"><i class="fas fa-triangle-exclamation"></i> <strong>Important:</strong> Once generated, the Senior Citizen ID number will be permanent and cannot be generated again or duplicated.</p>
            <p class="action-message" id="seniorIdError" hidden></p>
        </div>
        <div class="action-dialog-actions"><button class="modal-button" type="button" id="cancelGenerateId">Cancel</button><button class="modal-button generate-action" type="button" id="confirmGenerateId"><i class="fas fa-id-card"></i> Generate Senior ID</button><button class="modal-button view-action" type="button" id="viewGeneratedId" hidden><i class="fas fa-eye"></i> View Digital ID</button><button class="modal-button" type="button" id="closeGeneratedId" hidden>Close</button></div>
    </div>
</div>
<script src="../assets/js/sidebar-toggle.js?v=3"></script>
<script>
(() => {
    const digitalIdSearchForm = document.querySelector('.search');
    const digitalIdSearchInput = digitalIdSearchForm?.querySelector('input[name="search"]');
    let digitalIdSearchTimer;
    digitalIdSearchInput?.addEventListener('input', () => {
        window.clearTimeout(digitalIdSearchTimer);
        digitalIdSearchTimer = window.setTimeout(() => {
            // A normal submit keeps the selected ID-status filter in the URL.
            digitalIdSearchForm.requestSubmit();
        }, 350);
    });
    const hour = new Date().getHours();
    const greeting = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
    document.getElementById('greetingMsg').innerHTML = greeting + ', <strong><?= htmlspecialchars(trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')), ENT_QUOTES) ?></strong>!';
    const modal = document.getElementById('digitalIdModal');
    const frame = document.getElementById('digitalIdFrame');
    const closeButton = modal.querySelector('.id-modal-close');
    let returnFocus = null;
    const close = () => { modal.classList.remove('open'); modal.setAttribute('aria-hidden','true'); frame.removeAttribute('src'); document.body.style.overflow=''; returnFocus?.focus(); };
    const openDigitalId = button => {
        returnFocus = button;
        document.getElementById('digitalIdModalTitle').innerHTML = '<i class="fas fa-id-card"></i> Digital ID — ' + button.dataset.applicant.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
        frame.src = 'digital_id.php?id=' + encodeURIComponent(button.dataset.digitalId) + '&embed=1';
        modal.classList.add('open'); modal.setAttribute('aria-hidden','false'); document.body.style.overflow='hidden'; closeButton.focus();
    };
    document.querySelectorAll('[data-digital-id]').forEach(button => button.addEventListener('click', () => openDigitalId(button)));

    const actionModal = document.getElementById('seniorIdActionModal');
    const actionTitle = document.getElementById('seniorIdActionTitle');
    const actionIcon = document.getElementById('seniorIdActionIcon');
    const actionIntro = document.getElementById('seniorIdActionIntro');
    const actionApplicant = document.getElementById('seniorIdApplicant');
    const actionToken = document.getElementById('seniorIdToken');
    const generatedIdRow = document.getElementById('generatedIdRow');
    const generatedSeniorId = document.getElementById('generatedSeniorId');
    const actionWarning = document.getElementById('seniorIdWarning');
    const actionError = document.getElementById('seniorIdError');
    const cancelGenerate = document.getElementById('cancelGenerateId');
    const confirmGenerate = document.getElementById('confirmGenerateId');
    const viewGenerated = document.getElementById('viewGeneratedId');
    const closeGenerated = document.getElementById('closeGeneratedId');
    let pendingGenerateButton = null;

    const closeActionModal = () => {
        actionModal.classList.remove('open');
        actionModal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        if (pendingGenerateButton?.isConnected) pendingGenerateButton.focus();
    };
    const showConfirmation = button => {
        pendingGenerateButton = button;
        actionTitle.textContent = 'Generate Senior Citizen ID?';
        actionIcon.className = 'fas fa-id-card';
        actionIntro.textContent = 'You are about to generate a permanent Senior Citizen ID for:';
        actionApplicant.textContent = button.dataset.applicant || 'Senior citizen';
        actionToken.textContent = button.dataset.generateId;
        generatedIdRow.hidden = true;
        actionWarning.hidden = false;
        actionError.hidden = true;
        cancelGenerate.textContent = 'Cancel';
        cancelGenerate.hidden = false;
        confirmGenerate.hidden = false;
        confirmGenerate.disabled = false;
        confirmGenerate.innerHTML = '<i class="fas fa-id-card"></i> Generate Senior ID';
        viewGenerated.hidden = true;
        closeGenerated.hidden = true;
        actionModal.classList.add('open');
        actionModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        confirmGenerate.focus();
    };
    document.querySelectorAll('[data-generate-id]').forEach(button => button.addEventListener('click', () => showConfirmation(button)));

    confirmGenerate.addEventListener('click', async () => {
        if (!pendingGenerateButton) return;
        const applicationId = pendingGenerateButton.dataset.generateId;
        const applicant = pendingGenerateButton.dataset.applicant || 'this senior';
        confirmGenerate.disabled = true;
        confirmGenerate.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating…';
        const body = new FormData(); body.append('applicationId', applicationId); body.append('generate', '1');
        try {
            const response = await fetch('../api/set_official_senior_id.php', { method:'POST', body });
            const result = await response.json().catch(() => ({success:false,message:'Invalid server response.'}));
            if (!response.ok || !result.success) throw new Error(result.message || 'Unable to generate the Senior ID.');

            const row = pendingGenerateButton.closest('tr');
            const idCell = row.querySelector('.id-number');
            const actions = row.querySelector('.row-actions');
            idCell.textContent = result.seniorIdNo;
            const viewButton = document.createElement('button');
            viewButton.type = 'button';
            viewButton.className = 'view';
            viewButton.dataset.digitalId = applicationId;
            viewButton.dataset.applicant = applicant;
            viewButton.innerHTML = '<i class="fas fa-eye"></i> View Digital ID';
            viewButton.addEventListener('click', () => openDigitalId(viewButton));
            actions.replaceChildren(viewButton);

            actionTitle.textContent = 'Senior ID Generated Successfully';
            actionIcon.className = 'fas fa-circle-check';
            actionIntro.textContent = `The permanent Senior Citizen ID has been successfully assigned to ${applicant}.`;
            generatedSeniorId.textContent = result.seniorIdNo;
            generatedIdRow.hidden = false;
            actionWarning.hidden = true;
            actionError.hidden = true;
            cancelGenerate.hidden = true;
            confirmGenerate.hidden = true;
            viewGenerated.hidden = false;
            closeGenerated.hidden = false;
            pendingGenerateButton = viewButton;
            viewGenerated.focus();
        } catch (error) {
            actionTitle.textContent = 'Senior ID Could Not Be Generated';
            actionIcon.className = 'fas fa-triangle-exclamation';
            actionIntro.textContent = 'The system did not create a Senior Citizen ID.';
            actionWarning.hidden = true;
            actionError.textContent = error.message || 'Unable to generate the Senior ID.';
            actionError.hidden = false;
            cancelGenerate.textContent = 'Close';
            cancelGenerate.hidden = false;
            confirmGenerate.hidden = true;
            cancelGenerate.focus();
        }
    });
    cancelGenerate.addEventListener('click', closeActionModal);
    closeGenerated.addEventListener('click', closeActionModal);
    actionModal.querySelector('[data-action-close]').addEventListener('click', closeActionModal);
    actionModal.addEventListener('click', event => { if (event.target === actionModal) closeActionModal(); });
    viewGenerated.addEventListener('click', () => { const button = pendingGenerateButton; closeActionModal(); if (button) openDigitalId(button); });
    closeButton.addEventListener('click', close); modal.querySelector('[data-close-id]').addEventListener('click', close);
    modal.addEventListener('click', event => { if (event.target === modal) close(); });
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        if (actionModal.classList.contains('open')) closeActionModal();
        else if (modal.classList.contains('open')) close();
    });
    document.getElementById('printDigitalId').addEventListener('click', () => frame.contentWindow?.print());
})();
</script>
</body></html>
