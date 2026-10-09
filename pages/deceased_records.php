<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/application_types.php';

$role = $_SESSION['role'] ?? '';
if (!isset($_SESSION['user_id']) || !in_array($role, ['barangay_staff', 'department_admin', 'super_admin'], true)) {
    header('Location: ../index.php');
    exit;
}
$isDepartment = in_array($role, ['department_admin', 'super_admin'], true);
$search = trim((string)($_GET['search'] ?? ''));
$barangay = $isDepartment ? trim((string)($_GET['barangay'] ?? 'all')) : (string)($_SESSION['barangay'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;

$where = ["senior.application_type = 'senior'", "senior.workflow_state = 'Deceased'", 'COALESCE(senior.is_archived, 0) = 0'];
$params = [];
if ($search !== '') {
    $where[] = '(senior.full_name LIKE :search_name OR senior.senior_id_no LIKE :search_senior_id OR senior.id_number LIKE :search_token
        OR EXISTS (SELECT 1 FROM applications linked_search
            WHERE linked_search.id_number LIKE :search_linked_token
              AND (linked_search.parent_senior_id = senior.id_number
                OR linked_search.id_number = senior.deceased_source_application_id)))';
    $searchValue = '%' . $search . '%';
    $params[':search_name'] = $searchValue;
    $params[':search_senior_id'] = $searchValue;
    $params[':search_token'] = $searchValue;
    $params[':search_linked_token'] = $searchValue;
}
if ($barangay !== '' && $barangay !== 'all') {
    $where[] = 'senior.barangay = :barangay';
    $params[':barangay'] = $barangay;
}
$whereSql = implode(' AND ', $where);
$count = $conn->prepare("SELECT COUNT(*) FROM applications senior WHERE {$whereSql}");
$count->execute($params);
$total = (int)$count->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT senior.id_number, senior.senior_id_no, senior.full_name, senior.birth_date,
               senior.barangay, senior.complete_address, senior.contact_number, senior.deceased_at,
               senior.deceased_source_application_id,
               burial.date_of_death, burial.claimant_name, burial.claimant_contact,
               burial.relationship_to_deceased, burial.workflow_state burial_status,
               burial.date_submitted burial_submitted_at
        FROM applications senior
        LEFT JOIN applications burial ON burial.id_number = senior.deceased_source_application_id
        WHERE {$whereSql}
        ORDER BY COALESCE(burial.date_of_death, senior.deceased_at) DESC, senior.full_name ASC
        LIMIT {$perPage} OFFSET {$offset}";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();
$transactionsBySenior = [];
if ($records) {
    $seniorIds = array_values(array_filter(array_column($records, 'id_number')));
    $placeholders = implode(',', array_fill(0, count($seniorIds), '?'));
    $transactionSql = "SELECT DISTINCT senior.id_number deceased_profile_id,
                              tx.id_number, tx.application_type, tx.requested_benefit,
                              tx.id_purpose, tx.date_submitted, tx.workflow_state, tx.status,
                              tx.barangay
                       FROM applications senior
                       JOIN applications tx ON (
                           tx.id_number = senior.id_number
                           OR tx.parent_senior_id = senior.id_number
                           OR tx.id_number = senior.deceased_source_application_id
                           OR (NULLIF(TRIM(senior.senior_id_no), '') IS NOT NULL
                               AND tx.senior_id_no = senior.senior_id_no)
                       )
                       WHERE senior.id_number IN ({$placeholders})
                       ORDER BY senior.id_number, tx.date_submitted DESC, tx.id_number DESC";
    $transactionStmt = $conn->prepare($transactionSql);
    $transactionStmt->execute($seniorIds);
    foreach ($transactionStmt->fetchAll(PDO::FETCH_ASSOC) as $transaction) {
        $transactionsBySenior[(string)$transaction['deceased_profile_id']][] = $transaction;
    }
}
$barangays = $isDepartment ? $conn->query("SELECT DISTINCT barangay FROM applications WHERE workflow_state = 'Deceased' AND barangay IS NOT NULL AND barangay <> '' ORDER BY barangay")->fetchAll(PDO::FETCH_COLUMN) : [];

$profilePicture = basename((string)($_SESSION['profile_picture'] ?? 'default.jpg'));
$profilePath = '../images/profile_pictures/' . $profilePicture;
if (!file_exists($profilePath) || is_dir($profilePath)) $profilePath = '../images/profile_pictures/default.jpg';
$staffName = trim((string)(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')));
if ($staffName === '') $staffName = (string)($_SESSION['username'] ?? 'Staff member');
$staffRole = ucwords(str_replace('_', ' ', $role));
$staffLocation = $isDepartment ? 'Pasig City' : trim((string)($_SESSION['barangay'] ?? ''));

function deceasedRecordsUrl(array $changes = []): string {
    $query = array_merge($_GET, $changes);
    foreach ($query as $key => $value) if ($value === '' || $value === 'all' || $value === null) unset($query[$key]);
    return 'deceased_records.php' . ($query ? '?' . http_build_query($query) : '');
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Deceased Records — SENIORLINK</title><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"><link rel="stylesheet" href="../assets/css/<?= $isDepartment ? 'department-sidebar.css?v=5' : 'barangay-sidebar.css?v=4' ?>"><link rel="stylesheet" href="../assets/css/seniorlink-ui.css?v=23"><link rel="stylesheet" href="../assets/css/system-header.css?v=1"><style>
*{box-sizing:border-box}body{margin:0;background:#f1f5f9;color:#172033;font-family:Inter,"Segoe UI",Arial,sans-serif}.main-content{width:calc(100% - 220px);margin-left:220px;min-height:100vh;padding:30px}.shell{max-width:1300px;margin:auto}.page-header{margin-bottom:22px}.header-user img{width:40px;height:40px;object-fit:cover;border-radius:50%}.info{display:flex;gap:12px;align-items:flex-start;margin-bottom:18px;padding:14px 16px;border:1px solid #cbd5e1;border-left:4px solid #64748b;border-radius:11px;background:#fff;color:#475569;font-size:.84rem;line-height:1.5}.info i{margin-top:3px;color:#64748b}.records-card{overflow:hidden;border:1px solid #d9e3ee;border-radius:16px;background:#fff;box-shadow:0 4px 16px rgba(15,23,42,.06)}.records-card-header{display:flex;align-items:center;justify-content:space-between;padding:20px 28px;background:linear-gradient(135deg,#0f172a,#1e3a5f)}.records-card-header h2{display:flex;align-items:center;gap:10px;margin:0;color:#fff;font-size:1.05rem}.records-card-header h2 i{color:#60a5fa}.records-count{color:#cbd5e1;font-size:.78rem;font-weight:700}.filters{display:flex;align-items:flex-end;gap:16px;margin:0;padding:18px 28px;border-bottom:1px solid #d9e3ee;background:#f8fafc;flex-wrap:wrap}.filter-group{display:flex;flex:1 1 240px;flex-direction:column;gap:5px}.filter-group--barangay{flex:0 1 220px}.filter-group label{color:#64748b;font-size:.72rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase}.filter-control{min-height:42px;padding:9px 12px;border:1.5px solid #d9e3ee;border-radius:8px;background:#fff;color:#172033;font:inherit}.filters button,.clear{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:42px;padding:9px 16px;border-radius:8px;font:inherit;font-size:.82rem;font-weight:800;text-decoration:none}.filters button{border:0;background:#2563eb;color:#fff;cursor:pointer}.clear{border:1px solid #cbd5e1;background:#fff;color:#475569}.table-wrap{overflow-x:auto}.records-tbl{width:100%;border-collapse:collapse}.records-tbl thead tr{border-bottom:2px solid #d9e3ee;background:#f8fafc}.records-tbl th{padding:12px 18px;color:#64748b;font-size:.72rem;font-weight:800;letter-spacing:.06em;text-align:left;text-transform:uppercase;white-space:nowrap}.records-tbl td{padding:14px 18px;border-bottom:1px solid #f1f5f9;color:#172033;font-size:.86rem;vertical-align:middle}.records-tbl tbody tr:hover{background:#f8fafc}.name-cell strong,.date-cell strong{display:block}.name-cell span,.date-cell span{display:block;margin-top:3px;color:#64748b;font-size:.73rem}.status-badge{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border:1px solid #cbd5e1;border-radius:999px;background:#f1f5f9;color:#475569;font-size:.7rem;font-weight:900;text-transform:uppercase}.view-record{display:inline-flex;align-items:center;gap:7px;padding:8px 12px;border:1.5px solid #d9e3ee;border-radius:8px;background:#fff;color:#172033;font-weight:800;white-space:nowrap;cursor:pointer}.view-record:hover,.view-record:focus-visible{border-color:#60a5fa;background:#eff6ff;color:#2563eb;outline:none}.empty{padding:48px 20px;text-align:center;color:#64748b}.empty i{display:block;margin-bottom:10px;font-size:2rem}.pagination{display:flex;justify-content:center;align-items:center;gap:8px;padding:16px;border-top:1px solid #e2e8f0}.pagination a,.pagination span{padding:8px 11px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#334155;text-decoration:none;font-size:.8rem;font-weight:700}.pagination .current{border-color:#475569;background:#475569;color:#fff}.record-dialog{width:min(720px,calc(100% - 24px));padding:0;border:0;border-radius:18px;box-shadow:0 24px 70px rgba(15,23,42,.3)}.record-dialog::backdrop{background:rgba(15,23,42,.62)}.dialog-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:20px 22px;border-bottom:1px solid #e2e8f0}.dialog-head h2{margin:0;color:#0f172a;font-size:1.25rem}.dialog-head p{margin:4px 0 0;color:#64748b;font-size:.8rem}.dialog-close{width:38px;height:38px;border:0;border-radius:10px;background:#f1f5f9;color:#334155;cursor:pointer}.dialog-body{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;padding:22px}.detail-item{padding:13px;border-radius:10px;background:#f8fafc}.detail-item span{display:block;color:#64748b;font-size:.68rem;font-weight:800;text-transform:uppercase}.detail-item strong{display:block;margin-top:2px;color:#334155;font-size:.82rem;overflow-wrap:anywhere}.detail-item.wide{grid-column:1/-1}.dialog-footer{display:flex;justify-content:flex-end;padding:0 22px 22px}.dialog-footer button{padding:10px 18px;border:0;border-radius:9px;background:#334155;color:#fff;font-weight:800;cursor:pointer}@media(max-width:900px){.main-content{width:100%;margin:0;padding:82px 12px 24px}}@media(max-width:620px){.records-card-header,.filters{padding-left:16px;padding-right:16px}.records-count{display:none}.filter-group,.filter-group--barangay{flex-basis:100%}.filters button,.clear{flex:1}.dialog-body{grid-template-columns:1fr}.detail-item.wide{grid-column:auto}}
</style><style>
.record-dialog{width:min(820px,calc(100vw - 48px));height:min(720px,calc(100dvh - 48px));max-height:calc(100dvh - 48px);margin:auto;padding:0;overflow:hidden;color:#172d40;background:#f4f8fb;border:1px solid rgba(255,255,255,.8);border-radius:20px;box-shadow:0 30px 80px rgba(2,15,27,.38)}.record-dialog[open]{display:flex;flex-direction:column}.record-dialog::backdrop{background:rgba(9,25,40,.72)}.dialog-head{min-height:84px;display:flex;flex:0 0 auto;align-items:center;justify-content:space-between;gap:20px;padding:14px 18px 14px 24px;color:#fff;background:linear-gradient(120deg,#123c67,#1769aa 70%,#197769 140%);border:0;box-shadow:inset 0 -1px rgba(255,255,255,.12)}.dialog-title{min-width:0}.dialog-eyebrow{display:block;margin-bottom:3px;color:#b9def8;font-size:.61rem;font-weight:850;letter-spacing:.09em;text-transform:uppercase}.dialog-head h2{display:flex;align-items:center;gap:9px;margin:0;color:#fff;font-size:clamp(1.05rem,1.8vw,1.28rem);font-weight:800;line-height:1.25}.dialog-head h2 i{color:#8dd7ff}.dialog-head p{margin:3px 0 0;overflow:hidden;color:rgba(255,255,255,.76);font-size:.7rem;font-weight:550;text-overflow:ellipsis;white-space:nowrap}.dialog-close{width:44px;min-width:44px;height:44px;display:grid;place-items:center;color:#fff;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.24);border-radius:11px;font-size:1rem;cursor:pointer}.dialog-close:hover{background:rgba(255,255,255,.2)}.dialog-close:focus-visible{outline:3px solid rgba(141,215,255,.6);outline-offset:2px}.dialog-body{min-height:0;flex:1 1 auto;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));align-content:start;gap:12px;padding:20px;overflow-x:hidden;overflow-y:auto;scrollbar-gutter:stable;overscroll-behavior:contain;background:#f4f8fb}.detail-item{padding:13px 14px;background:#fff;border:1px solid #dce6ee;border-radius:11px;box-shadow:0 3px 10px rgba(18,50,75,.035)}.detail-item span{margin-bottom:4px;color:#687b8c;font-size:.65rem;font-weight:850;letter-spacing:.04em}.detail-item strong{color:#172d40;font-size:.84rem;font-weight:700;line-height:1.45}.dialog-footer{display:flex;flex:0 0 auto;justify-content:flex-end;padding:14px 20px 18px;background:#f4f8fb;border-top:1px solid #dce6ee}.dialog-footer .dialog-close{width:auto;height:auto;min-width:100px;padding:10px 18px;color:#fff;background:#1769aa;border:0;border-radius:9px}.dialog-footer .dialog-close:hover{background:#125b94}@media(max-width:600px){.record-dialog{width:100vw;height:100dvh;max-height:100dvh;border:0;border-radius:0}.dialog-head{min-height:76px;padding:12px 12px 12px 16px}.dialog-head p{max-width:calc(100vw - 92px)}.dialog-body{grid-template-columns:1fr;padding:14px}.detail-item.wide{grid-column:auto}.dialog-footer{padding:12px 14px 14px}.dialog-footer .dialog-close{width:100%}}
</style><style>
.record-dialog,.record-dialog *{box-sizing:border-box}.record-dialog{overscroll-behavior:contain}.dialog-body:focus-visible{outline:none}.dialog-footer{width:100%;min-width:0;align-items:center}.dialog-done{min-width:120px;min-height:44px;display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:10px 20px;border:0;border-radius:9px;background:#1769aa;color:#fff;font:800 .82rem/1 Inter,"Segoe UI",sans-serif;cursor:pointer;white-space:nowrap}.dialog-done:hover{background:#125b94}.dialog-done:focus-visible{outline:3px solid rgba(23,105,170,.28);outline-offset:2px}@media(max-width:600px){.dialog-footer{padding:12px 14px 14px}.dialog-done{width:100%;min-width:0}}
.transaction-history{grid-column:1/-1;padding:15px;background:#fff;border:1px solid #dce6ee;border-radius:11px}.transaction-history h3{display:flex;align-items:center;gap:8px;margin:0 0 11px;color:#172d40;font-size:.86rem}.transaction-history h3 i{color:#1769aa}.transaction-list{display:grid;gap:8px}.transaction-row{display:grid;grid-template-columns:minmax(190px,1.4fr) minmax(130px,.8fr) minmax(105px,.65fr);gap:12px;align-items:center;padding:10px 12px;background:#f4f8fb;border:1px solid #e2e8f0;border-radius:9px}.transaction-row strong,.transaction-row span,.transaction-row small{display:block}.transaction-row strong{font-size:.78rem;color:#172d40}.transaction-row span{margin-top:2px;color:#1769aa;font:750 .68rem/1.4 ui-monospace,SFMono-Regular,Consolas,monospace;overflow-wrap:anywhere}.transaction-row small{color:#64748b;font-size:.68rem}.transaction-status{justify-self:start;padding:5px 8px;border-radius:999px;background:#e2e8f0;color:#475569!important;font:800 .63rem/1 sans-serif!important;text-transform:uppercase}@media(max-width:600px){.transaction-history{grid-column:auto}.transaction-row{grid-template-columns:1fr}.transaction-status{margin-top:2px}}
</style></head><body>
<?php if($isDepartment) include '../partials/department_sidebar.php'; else include '../partials/barangay_sidebar.php'; ?>
<main class="main-content">
<header class="page-header">
    <div class="page-header-left"><div class="greeting">Segregated registry</div><h1>Deceased <span>Records</span></h1></div>
    <div class="header-user"><img src="<?= htmlspecialchars($profilePath) ?>" alt="Profile"><div class="header-user-info"><h3><?= htmlspecialchars($staffName) ?></h3><p><?= htmlspecialchars($staffRole . ($staffLocation !== '' ? ' · ' . $staffLocation : '')) ?></p></div></div>
</header>
<div class="shell">
<div class="info"><i class="fas fa-circle-info"></i><div>Senior profiles are moved here after Burial Assistance approval. Select any record to see its complete linked information.</div></div>
<section class="records-card" aria-label="Deceased senior records">
<div class="records-card-header"><h2><i class="fas fa-folder-open"></i> Deceased Records<?= $isDepartment ? ' – Pasig City' : '' ?></h2><span class="records-count"><?= number_format($total) ?> total record<?= $total === 1 ? '' : 's' ?></span></div>
<form class="filters" method="get"><div class="filter-group"><label for="deceasedSearch">Search / Maghanap</label><input class="filter-control" id="deceasedSearch" type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Name, Senior ID, or token" aria-label="Search deceased records"></div><?php if($isDepartment): ?><div class="filter-group filter-group--barangay"><label for="deceasedBarangay">Barangay</label><select class="filter-control" id="deceasedBarangay" name="barangay"><option value="all">All barangays</option><?php foreach($barangays as $item): ?><option value="<?= htmlspecialchars($item) ?>" <?= $barangay === $item ? 'selected' : '' ?>><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></div><?php endif; ?><button type="submit"><i class="fas fa-search"></i> Search</button><?php if($search !== '' || ($isDepartment && $barangay !== 'all')): ?><a class="clear" href="deceased_records.php">Clear</a><?php endif; ?></form>
<?php if(!$records): ?><div class="empty"><i class="fas fa-folder-open"></i><strong>No deceased records found</strong><br>Approved burial records will appear here automatically.</div><?php else: ?>
<div class="table-wrap"><table class="records-tbl"><thead><tr><th>Applicant</th><th>Date of Passing</th><th>Barangay</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php foreach($records as $index => $record): $detailsId = 'deceased-details-' . $index; ?>
<tr><td><div class="name-cell"><strong><?= htmlspecialchars($record['full_name']) ?></strong><span>ID Number: <?= htmlspecialchars($record['senior_id_no'] ?: 'Not assigned') ?> · Born <?= $record['birth_date'] ? htmlspecialchars(date('M j, Y', strtotime($record['birth_date']))) : 'Not recorded' ?></span></div></td><td><div class="date-cell"><strong><?= $record['date_of_death'] ? htmlspecialchars(date('M j, Y', strtotime($record['date_of_death']))) : 'Not recorded' ?></strong><span>Burial record</span></div></td><td><?= htmlspecialchars($record['barangay'] ?: 'Not recorded') ?></td><td><span class="status-badge"><i class="fas fa-ribbon"></i> Deceased</span></td><td><button type="button" class="view-record" aria-haspopup="dialog" aria-controls="<?= $detailsId ?>"><i class="fas fa-eye"></i> View</button></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<?php if($totalPages > 1): ?><nav class="pagination" aria-label="Deceased records pages"><?php if($page > 1): ?><a href="<?= htmlspecialchars(deceasedRecordsUrl(['page'=>$page-1])) ?>">Previous</a><?php endif; ?><span class="current">Page <?= $page ?> of <?= $totalPages ?></span><?php if($page < $totalPages): ?><a href="<?= htmlspecialchars(deceasedRecordsUrl(['page'=>$page+1])) ?>">Next</a><?php endif; ?></nav><?php endif; ?>
</section>
<?php foreach($records as $index => $record): $detailsId = 'deceased-details-' . $index; ?>
<dialog class="record-dialog" id="<?= $detailsId ?>"><div class="dialog-head"><div class="dialog-title"><span class="dialog-eyebrow">Deceased record</span><h2><i class="fas fa-ribbon" aria-hidden="true"></i> <?= htmlspecialchars($record['full_name']) ?></h2><p>Deceased senior and linked burial assistance information</p></div><button type="button" class="dialog-close" aria-label="Close details"><i class="fas fa-xmark"></i></button></div><div class="dialog-body">
    <div class="detail-item"><span>Senior ID</span><strong><?= htmlspecialchars($record['senior_id_no'] ?: 'Not assigned') ?></strong></div><div class="detail-item"><span>Record token</span><strong><?= htmlspecialchars($record['id_number'] ?: 'Not recorded') ?></strong></div>
    <div class="detail-item"><span>Birth date</span><strong><?= $record['birth_date'] ? htmlspecialchars(date('F j, Y', strtotime($record['birth_date']))) : 'Not recorded' ?></strong></div><div class="detail-item"><span>Date of passing</span><strong><?= $record['date_of_death'] ? htmlspecialchars(date('F j, Y', strtotime($record['date_of_death']))) : 'Not recorded' ?></strong></div>
    <div class="detail-item"><span>Barangay</span><strong><?= htmlspecialchars($record['barangay'] ?: 'Not recorded') ?></strong></div><div class="detail-item"><span>Senior contact</span><strong><?= htmlspecialchars($record['contact_number'] ?: 'Not recorded') ?></strong></div>
    <div class="detail-item wide"><span>Complete address</span><strong><?= htmlspecialchars($record['complete_address'] ?: 'Not recorded') ?></strong></div>
    <div class="detail-item"><span>Burial application</span><strong><?= htmlspecialchars($record['deceased_source_application_id'] ?: 'Legacy record') ?></strong></div><div class="detail-item"><span>Burial status</span><strong><?= htmlspecialchars($record['burial_status'] ?: 'Not recorded') ?></strong></div>
    <div class="detail-item"><span>Claimant</span><strong><?= htmlspecialchars($record['claimant_name'] ?: 'Not recorded') ?></strong></div><div class="detail-item"><span>Relationship</span><strong><?= htmlspecialchars($record['relationship_to_deceased'] ?: 'Not recorded') ?></strong></div>
    <div class="detail-item"><span>Claimant contact</span><strong><?= htmlspecialchars($record['claimant_contact'] ?: 'Not recorded') ?></strong></div><div class="detail-item"><span>Burial submitted</span><strong><?= $record['burial_submitted_at'] ? htmlspecialchars(date('F j, Y g:i A', strtotime($record['burial_submitted_at']))) : 'Not recorded' ?></strong></div>
    <section class="transaction-history" aria-labelledby="transactionHistory<?= $index ?>"><h3 id="transactionHistory<?= $index ?>"><i class="fas fa-layer-group" aria-hidden="true"></i> Complete transaction history</h3><div class="transaction-list">
        <?php foreach (($transactionsBySenior[(string)$record['id_number']] ?? []) as $transaction): ?>
        <?php $transactionState = (string)($transaction['workflow_state'] ?: $transaction['status'] ?: 'Recorded'); ?>
        <div class="transaction-row"><div><strong><?= htmlspecialchars(applicationRecordTypeLabel((string)$transaction['application_type'], $transaction['id_purpose'] ?? null)) ?></strong><span>Transaction ID: <?= htmlspecialchars($transaction['id_number']) ?></span></div><small><?= $transaction['date_submitted'] ? htmlspecialchars(date('F j, Y g:i A', strtotime($transaction['date_submitted']))) : 'Date not recorded' ?></small><span class="transaction-status"><?= htmlspecialchars($transactionState) ?></span></div>
        <?php endforeach; ?>
    </div></section>
</div><div class="dialog-footer"><button type="button" class="dialog-done"><i class="fas fa-check" aria-hidden="true"></i> Close</button></div></dialog>
<?php endforeach; ?>
</div></main>
<script src="../assets/js/sidebar-toggle.js?v=3"></script><script>
document.querySelectorAll('.view-record').forEach(function(button){
    var dialog=document.getElementById(button.getAttribute('aria-controls'));
    var open=function(){if(dialog&&typeof dialog.showModal==='function'){dialog._returnFocus=button;dialog.showModal();window.setTimeout(function(){dialog.querySelector('.dialog-close')?.focus();},0);}};
    button.addEventListener('click',open);
});
document.querySelectorAll('.record-dialog').forEach(function(dialog){
    dialog.querySelectorAll('.dialog-close,.dialog-done').forEach(function(button){button.addEventListener('click',function(){dialog.close();});});
    dialog.addEventListener('click',function(event){if(event.target===dialog)dialog.close();});
    dialog.addEventListener('close',function(){dialog._returnFocus?.focus();});
});
</script></body></html>
