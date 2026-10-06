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

function deceasedRecordsUrl(array $changes = []): string {
    $query = array_merge($_GET, $changes);
    foreach ($query as $key => $value) if ($value === '' || $value === 'all' || $value === null) unset($query[$key]);
    return 'deceased_records.php' . ($query ? '?' . http_build_query($query) : '');
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Deceased Records — SENIORLINK</title><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"><link rel="stylesheet" href="../assets/css/<?= $isDepartment ? 'department-sidebar.css?v=5' : 'barangay-sidebar.css?v=4' ?>"><link rel="stylesheet" href="../assets/css/seniorlink-ui.css?v=23"><style>
*{box-sizing:border-box}body{margin:0;background:#f1f5f9;color:#172033;font-family:Inter,"Segoe UI",Arial,sans-serif}.main-content{width:calc(100% - 220px);margin-left:220px;min-height:100vh;padding:30px}.shell{max-width:1300px;margin:auto}.page-head{display:flex;justify-content:space-between;align-items:flex-end;gap:18px;margin-bottom:22px}.eyebrow{color:#64748b;font-size:.72rem;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.page-head h1{margin:5px 0 6px;color:#0f2942;font-size:2rem}.page-head p{margin:0;color:#64748b}.count-card{min-width:170px;padding:14px 18px;border:1px solid #d8e1ec;border-radius:13px;background:#fff;text-align:right;box-shadow:0 6px 18px rgba(15,23,42,.05)}.count-card strong{display:block;color:#475569;font-size:.72rem;text-transform:uppercase}.count-card span{color:#0f2942;font-size:1.65rem;font-weight:900}.info{display:flex;gap:12px;align-items:flex-start;margin-bottom:18px;padding:14px 16px;border:1px solid #cbd5e1;border-left:4px solid #64748b;border-radius:11px;background:#fff;color:#475569;font-size:.84rem;line-height:1.5}.info i{margin-top:3px;color:#64748b}.filters{display:grid;grid-template-columns:minmax(240px,1fr) 220px auto;gap:10px;margin-bottom:18px;padding:14px;border:1px solid #d9e3ee;border-radius:13px;background:#fff}.filters input,.filters select,.filters button,.clear{min-height:44px;padding:10px 12px;border:1px solid #cbd5e1;border-radius:9px;font:inherit}.filters button{border-color:#334155;background:#334155;color:#fff;font-weight:800;cursor:pointer}.clear{display:inline-flex;align-items:center;color:#475569;text-decoration:none;background:#f8fafc}.records{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.record{padding:18px;border:1px solid #d9e3ee;border-radius:14px;background:#fff;box-shadow:0 7px 20px rgba(15,23,42,.05)}.record-head{display:flex;justify-content:space-between;gap:14px;padding-bottom:12px;border-bottom:1px solid #e8eef4}.record h2{margin:0 0 3px;font-size:1rem}.record-id{color:#64748b;font-size:.75rem}.badge{height:max-content;padding:5px 8px;border-radius:999px;background:#f1f5f9;color:#475569;font-size:.66rem;font-weight:900;text-transform:uppercase}.facts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:14px}.fact span,.claim span{display:block;color:#64748b;font-size:.68rem;font-weight:800;text-transform:uppercase}.fact strong,.claim strong{display:block;margin-top:2px;color:#334155;font-size:.82rem;overflow-wrap:anywhere}.claim{margin-top:14px;padding:12px;border-radius:10px;background:#f8fafc}.claim-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.empty{grid-column:1/-1;padding:48px 20px;border:1px dashed #cbd5e1;border-radius:14px;background:#fff;text-align:center;color:#64748b}.empty i{display:block;margin-bottom:10px;font-size:2rem}.pagination{display:flex;justify-content:center;align-items:center;gap:8px;margin-top:20px}.pagination a,.pagination span{padding:8px 11px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#334155;text-decoration:none;font-size:.8rem;font-weight:700}.pagination .current{border-color:#475569;background:#475569;color:#fff}@media(max-width:900px){.main-content{width:100%;margin:0;padding:82px 12px 24px}.page-head{align-items:flex-start;flex-direction:column}.count-card{width:100%;text-align:left}.records{grid-template-columns:1fr}}@media(max-width:620px){.filters{grid-template-columns:1fr}.facts,.claim-grid{grid-template-columns:1fr}.record{padding:15px}}
</style></head><body><?php if($isDepartment) include '../partials/department_sidebar.php'; else include '../partials/barangay_sidebar.php'; ?><main class="main-content"><div class="shell"><header class="page-head"><div><span class="eyebrow">Segregated registry</span><h1>Deceased Records</h1><p>Senior profiles automatically moved here after Burial Assistance approval.</p></div><div class="count-card"><strong>Total records</strong><span><?= number_format($total) ?></span></div></header><div class="info"><i class="fas fa-circle-info"></i><div>These records are separated from active senior records. The originating Burial Assistance application and claimant information remain linked for audit and verification.</div></div><form class="filters" method="get"><input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, Senior ID, or token" aria-label="Search deceased records"><?php if($isDepartment): ?><select name="barangay" aria-label="Filter by barangay"><option value="all">All barangays</option><?php foreach($barangays as $item): ?><option value="<?= htmlspecialchars($item) ?>" <?= $barangay === $item ? 'selected' : '' ?>><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select><?php endif; ?><button type="submit"><i class="fas fa-search"></i> Search</button><?php if($search !== '' || ($isDepartment && $barangay !== 'all')): ?><a class="clear" href="deceased_records.php">Clear filters</a><?php endif; ?></form><section class="records" aria-label="Deceased senior records"><?php if(!$records): ?><div class="empty"><i class="fas fa-folder-open"></i><strong>No deceased records found</strong><br>Approved burial records will appear here automatically.</div><?php endif; ?><?php foreach($records as $record): ?><article class="record"><div class="record-head"><div><h2><?= htmlspecialchars($record['full_name']) ?></h2><div class="record-id">Senior ID: <?= htmlspecialchars($record['senior_id_no'] ?: 'Not assigned') ?></div></div><span class="badge"><i class="fas fa-ribbon"></i> Deceased</span></div><div class="facts"><div class="fact"><span>Date of passing</span><strong><?= $record['date_of_death'] ? htmlspecialchars(date('F j, Y', strtotime($record['date_of_death']))) : 'Not recorded' ?></strong></div><div class="fact"><span>Barangay</span><strong><?= htmlspecialchars($record['barangay'] ?: 'Not recorded') ?></strong></div><div class="fact"><span>Birth date</span><strong><?= $record['birth_date'] ? htmlspecialchars(date('F j, Y', strtotime($record['birth_date']))) : 'Not recorded' ?></strong></div><div class="fact"><span>Burial application</span><strong><?= htmlspecialchars($record['deceased_source_application_id'] ?: 'Legacy record') ?></strong></div></div><div class="claim"><div class="claim-grid"><div><span>Claimant</span><strong><?= htmlspecialchars($record['claimant_name'] ?: 'Not recorded') ?></strong></div><div><span>Relationship</span><strong><?= htmlspecialchars($record['relationship_to_deceased'] ?: 'Not recorded') ?></strong></div></div></div></article><?php endforeach; ?></section><?php if($totalPages > 1): ?><nav class="pagination" aria-label="Deceased records pages"><?php if($page > 1): ?><a href="<?= htmlspecialchars(deceasedRecordsUrl(['page'=>$page-1])) ?>">Previous</a><?php endif; ?><span class="current">Page <?= $page ?> of <?= $totalPages ?></span><?php if($page < $totalPages): ?><a href="<?= htmlspecialchars(deceasedRecordsUrl(['page'=>$page+1])) ?>">Next</a><?php endif; ?></nav><?php endif; ?></div></main></body></html>
