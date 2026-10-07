<?php
session_start();
require_once '../includes/db_connect.php';

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
    $where[] = '(senior.full_name LIKE :search OR senior.senior_id_no LIKE :search OR senior.id_number LIKE :search)';
    $params[':search'] = '%' . $search . '%';
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
*{box-sizing:border-box}body{margin:0;background:#f1f5f9;color:#172033;font-family:Inter,"Segoe UI",Arial,sans-serif}.main-content{width:calc(100% - 220px);margin-left:220px;min-height:100vh;padding:30px}.shell{max-width:1300px;margin:auto}.page-header{margin-bottom:22px}.header-user img{width:40px;height:40px;object-fit:cover;border-radius:50%}.info{display:flex;gap:12px;align-items:flex-start;margin-bottom:18px;padding:14px 16px;border:1px solid #cbd5e1;border-left:4px solid #64748b;border-radius:11px;background:#fff;color:#475569;font-size:.84rem;line-height:1.5}.info i{margin-top:3px;color:#64748b}.filters{display:grid;grid-template-columns:minmax(240px,1fr) 220px auto;gap:10px;margin-bottom:18px;padding:14px;border:1px solid #d9e3ee;border-radius:13px;background:#fff}.filters input,.filters select,.filters button,.clear{min-height:44px;padding:10px 12px;border:1px solid #cbd5e1;border-radius:9px;font:inherit}.filters button{border-color:#334155;background:#334155;color:#fff;font-weight:800;cursor:pointer}.clear{display:inline-flex;align-items:center;color:#475569;text-decoration:none;background:#f8fafc}.records{display:grid;grid-template-columns:1fr;gap:14px}.record{position:relative;padding:18px;border:1px solid #d9e3ee;border-radius:14px;background:#fff;box-shadow:0 7px 20px rgba(15,23,42,.05);cursor:pointer;transition:transform .16s,border-color .16s,box-shadow .16s}.record:hover,.record:focus-visible{transform:translateY(-2px);border-color:#60a5fa;box-shadow:0 12px 28px rgba(15,23,42,.10);outline:none}.record-head{display:flex;justify-content:space-between;gap:14px;padding-bottom:12px;border-bottom:1px solid #e8eef4}.record h2{margin:0 0 3px;font-size:1rem}.record-id{color:#64748b;font-size:.75rem}.record-action{display:block;margin-top:5px;color:#2563eb;font-size:.7rem;font-weight:800}.badge{height:max-content;padding:5px 8px;border-radius:999px;background:#f1f5f9;color:#475569;font-size:.66rem;font-weight:900;text-transform:uppercase}.facts{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:14px}.fact span,.claim span,.detail-item span{display:block;color:#64748b;font-size:.68rem;font-weight:800;text-transform:uppercase}.fact strong,.claim strong,.detail-item strong{display:block;margin-top:2px;color:#334155;font-size:.82rem;overflow-wrap:anywhere}.claim{margin-top:14px;padding:12px;border-radius:10px;background:#f8fafc}.claim-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.empty{grid-column:1/-1;padding:48px 20px;border:1px dashed #cbd5e1;border-radius:14px;background:#fff;text-align:center;color:#64748b}.empty i{display:block;margin-bottom:10px;font-size:2rem}.pagination{display:flex;justify-content:center;align-items:center;gap:8px;margin-top:20px}.pagination a,.pagination span{padding:8px 11px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#334155;text-decoration:none;font-size:.8rem;font-weight:700}.pagination .current{border-color:#475569;background:#475569;color:#fff}.record-dialog{width:min(720px,calc(100% - 24px));padding:0;border:0;border-radius:18px;box-shadow:0 24px 70px rgba(15,23,42,.3)}.record-dialog::backdrop{background:rgba(15,23,42,.62)}.dialog-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:20px 22px;border-bottom:1px solid #e2e8f0}.dialog-head h2{margin:0;color:#0f172a;font-size:1.25rem}.dialog-head p{margin:4px 0 0;color:#64748b;font-size:.8rem}.dialog-close{width:38px;height:38px;border:0;border-radius:10px;background:#f1f5f9;color:#334155;cursor:pointer}.dialog-body{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;padding:22px}.detail-item{padding:13px;border-radius:10px;background:#f8fafc}.detail-item.wide{grid-column:1/-1}.dialog-footer{display:flex;justify-content:flex-end;padding:0 22px 22px}.dialog-footer button{padding:10px 18px;border:0;border-radius:9px;background:#334155;color:#fff;font-weight:800;cursor:pointer}@media(max-width:900px){.main-content{width:100%;margin:0;padding:82px 12px 24px}.facts{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:620px){.filters{grid-template-columns:1fr}.facts,.claim-grid,.dialog-body{grid-template-columns:1fr}.detail-item.wide{grid-column:auto}.record{padding:15px}}
</style></head><body>
<?php if($isDepartment) include '../partials/department_sidebar.php'; else include '../partials/barangay_sidebar.php'; ?>
<main class="main-content">
<header class="page-header">
    <div class="page-header-left"><div class="greeting">Segregated registry</div><h1>Deceased <span>Records</span></h1></div>
    <div class="header-user"><img src="<?= htmlspecialchars($profilePath) ?>" alt="Profile"><div class="header-user-info"><h3><?= htmlspecialchars($staffName) ?></h3><p><?= htmlspecialchars($staffRole . ($staffLocation !== '' ? ' · ' . $staffLocation : '')) ?></p></div></div>
</header>
<div class="shell">
<div class="info"><i class="fas fa-circle-info"></i><div>Senior profiles are moved here after Burial Assistance approval. Select any record to see its complete linked information.</div></div>
<form class="filters" method="get"><input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, Senior ID, or token" aria-label="Search deceased records"><?php if($isDepartment): ?><select name="barangay" aria-label="Filter by barangay"><option value="all">All barangays</option><?php foreach($barangays as $item): ?><option value="<?= htmlspecialchars($item) ?>" <?= $barangay === $item ? 'selected' : '' ?>><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select><?php endif; ?><button type="submit"><i class="fas fa-search"></i> Search / Maghanap</button><?php if($search !== '' || ($isDepartment && $barangay !== 'all')): ?><a class="clear" href="deceased_records.php">Clear filters</a><?php endif; ?></form>
<section class="records" aria-label="Deceased senior records">
<?php if(!$records): ?><div class="empty"><i class="fas fa-folder-open"></i><strong>No deceased records found</strong><br>Approved burial records will appear here automatically.</div><?php endif; ?>
<?php foreach($records as $index => $record): $detailsId = 'deceased-details-' . $index; ?>
<article class="record" tabindex="0" role="button" aria-haspopup="dialog" aria-controls="<?= $detailsId ?>">
    <div class="record-head"><div><h2><?= htmlspecialchars($record['full_name']) ?></h2><div class="record-id">Senior ID: <?= htmlspecialchars($record['senior_id_no'] ?: 'Not assigned') ?></div><span class="record-action"><i class="fas fa-eye"></i> View complete information</span></div><span class="badge"><i class="fas fa-ribbon"></i> Deceased</span></div>
    <div class="facts"><div class="fact"><span>Date of passing</span><strong><?= $record['date_of_death'] ? htmlspecialchars(date('F j, Y', strtotime($record['date_of_death']))) : 'Not recorded' ?></strong></div><div class="fact"><span>Barangay</span><strong><?= htmlspecialchars($record['barangay'] ?: 'Not recorded') ?></strong></div><div class="fact"><span>Birth date</span><strong><?= $record['birth_date'] ? htmlspecialchars(date('F j, Y', strtotime($record['birth_date']))) : 'Not recorded' ?></strong></div><div class="fact"><span>Burial application</span><strong><?= htmlspecialchars($record['deceased_source_application_id'] ?: 'Legacy record') ?></strong></div></div>
    <div class="claim"><div class="claim-grid"><div><span>Claimant</span><strong><?= htmlspecialchars($record['claimant_name'] ?: 'Not recorded') ?></strong></div><div><span>Relationship</span><strong><?= htmlspecialchars($record['relationship_to_deceased'] ?: 'Not recorded') ?></strong></div></div></div>
</article>
<dialog class="record-dialog" id="<?= $detailsId ?>"><div class="dialog-head"><div><h2><?= htmlspecialchars($record['full_name']) ?></h2><p>Deceased senior and linked burial assistance information</p></div><button type="button" class="dialog-close" aria-label="Close details"><i class="fas fa-xmark"></i></button></div><div class="dialog-body">
    <div class="detail-item"><span>Senior ID</span><strong><?= htmlspecialchars($record['senior_id_no'] ?: 'Not assigned') ?></strong></div><div class="detail-item"><span>Record token</span><strong><?= htmlspecialchars($record['id_number'] ?: 'Not recorded') ?></strong></div>
    <div class="detail-item"><span>Birth date</span><strong><?= $record['birth_date'] ? htmlspecialchars(date('F j, Y', strtotime($record['birth_date']))) : 'Not recorded' ?></strong></div><div class="detail-item"><span>Date of passing</span><strong><?= $record['date_of_death'] ? htmlspecialchars(date('F j, Y', strtotime($record['date_of_death']))) : 'Not recorded' ?></strong></div>
    <div class="detail-item"><span>Barangay</span><strong><?= htmlspecialchars($record['barangay'] ?: 'Not recorded') ?></strong></div><div class="detail-item"><span>Senior contact</span><strong><?= htmlspecialchars($record['contact_number'] ?: 'Not recorded') ?></strong></div>
    <div class="detail-item wide"><span>Complete address</span><strong><?= htmlspecialchars($record['complete_address'] ?: 'Not recorded') ?></strong></div>
    <div class="detail-item"><span>Burial application</span><strong><?= htmlspecialchars($record['deceased_source_application_id'] ?: 'Legacy record') ?></strong></div><div class="detail-item"><span>Burial status</span><strong><?= htmlspecialchars($record['burial_status'] ?: 'Not recorded') ?></strong></div>
    <div class="detail-item"><span>Claimant</span><strong><?= htmlspecialchars($record['claimant_name'] ?: 'Not recorded') ?></strong></div><div class="detail-item"><span>Relationship</span><strong><?= htmlspecialchars($record['relationship_to_deceased'] ?: 'Not recorded') ?></strong></div>
    <div class="detail-item"><span>Claimant contact</span><strong><?= htmlspecialchars($record['claimant_contact'] ?: 'Not recorded') ?></strong></div><div class="detail-item"><span>Burial submitted</span><strong><?= $record['burial_submitted_at'] ? htmlspecialchars(date('F j, Y g:i A', strtotime($record['burial_submitted_at']))) : 'Not recorded' ?></strong></div>
</div><div class="dialog-footer"><button type="button" class="dialog-close">Close</button></div></dialog>
<?php endforeach; ?>
</section>
<?php if($totalPages > 1): ?><nav class="pagination" aria-label="Deceased records pages"><?php if($page > 1): ?><a href="<?= htmlspecialchars(deceasedRecordsUrl(['page'=>$page-1])) ?>">Previous</a><?php endif; ?><span class="current">Page <?= $page ?> of <?= $totalPages ?></span><?php if($page < $totalPages): ?><a href="<?= htmlspecialchars(deceasedRecordsUrl(['page'=>$page+1])) ?>">Next</a><?php endif; ?></nav><?php endif; ?>
</div></main>
<script src="../assets/js/sidebar-toggle.js?v=3"></script><script>
document.querySelectorAll('.record').forEach(function(card){
    var dialog=document.getElementById(card.getAttribute('aria-controls'));
    var open=function(){if(dialog&&typeof dialog.showModal==='function')dialog.showModal();};
    card.addEventListener('click',open);
    card.addEventListener('keydown',function(event){if(event.key==='Enter'||event.key===' '){event.preventDefault();open();}});
});
document.querySelectorAll('.record-dialog').forEach(function(dialog){
    dialog.querySelectorAll('.dialog-close').forEach(function(button){button.addEventListener('click',function(){dialog.close();});});
    dialog.addEventListener('click',function(event){if(event.target===dialog)dialog.close();});
});
</script></body></html>
