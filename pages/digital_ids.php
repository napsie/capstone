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
$page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$perPage = 15;
$where = ["application_type = 'senior'", "workflow_state IN ('Verified', 'Approved', 'Released')", 'COALESCE(is_archived, 0) = 0'];
$params = [];
if ($role === 'barangay_staff') {
    $where[] = 'barangay = ?';
    $params[] = $_SESSION['barangay'] ?? '';
}
if ($search !== '') {
    $where[] = '(full_name LIKE ? OR senior_id_no LIKE ? OR id_number LIKE ?)';
    array_push($params, "%{$search}%", "%{$search}%", "%{$search}%");
}
$whereSql = implode(' AND ', $where);
$countStmt = $conn->prepare("SELECT COUNT(*) FROM applications WHERE {$whereSql}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;
$listStmt = $conn->prepare("SELECT id_number, senior_id_no, full_name, barangay, birth_date, date_submitted FROM applications WHERE {$whereSql} ORDER BY full_name ASC LIMIT {$perPage} OFFSET {$offset}");
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
    <link rel="stylesheet" href="../assets/css/<?= $isDepartment ? 'department' : 'barangay' ?>-sidebar.css?v=4">
    <link rel="stylesheet" href="../assets/css/system-sidebar.css?v=3">
    <link rel="stylesheet" href="../assets/css/system-header.css?v=1">
    <style>
        *{box-sizing:border-box}html,body{max-width:100%;overflow-x:hidden}body{margin:0;background:#f4f7fb;color:#172033;font-family:Inter,"Segoe UI",Arial,sans-serif}.main-content{width:calc(100% - 220px);margin-left:220px;min-height:100vh;padding:32px}.main-content.collapsed{width:calc(100% - 80px)}.page-shell{width:100%;max-width:1180px;margin:auto}.page-header{display:flex;justify-content:space-between;align-items:center;gap:18px;margin-bottom:24px}.page-header-left .greeting{margin-bottom:6px;color:#6b7280;font-size:.98rem;font-weight:500}.page-header-left .greeting strong{color:#2563eb}.page-header h1{margin:0;font-size:2rem;line-height:1.05;color:#0f2942}.page-header h1 span{color:#2563eb}.header-user{display:flex;align-items:center;gap:12px;padding:7px 13px;border:3px solid transparent;border-radius:30px;background:linear-gradient(#fff,#fff) padding-box,linear-gradient(135deg,#0f172a,#3498db) border-box;box-shadow:0 2px 8px rgba(0,0,0,.08)}.header-user img{width:40px;height:40px;object-fit:cover;border:2px solid #2563eb;border-radius:50%}.header-user h3,.header-user p{margin:0}.header-user h3{font-size:.9rem}.header-user p{color:#64748b;font-size:.75rem}.page-tools{display:flex;justify-content:flex-end;margin-bottom:16px}.search{display:flex;gap:8px;min-width:0}.search input{width:320px;max-width:48vw;padding:11px 12px;border:1px solid #cbd5e1;border-radius:9px;font:inherit}.search button{padding:0 15px;border:0;border-radius:9px;background:#2563eb;color:#fff;font-weight:800;cursor:pointer}.card{overflow:hidden;border:1px solid #d9e3ee;border-radius:14px;background:#fff;box-shadow:0 8px 24px rgba(15,23,42,.06)}table{width:100%;border-collapse:collapse}th,td{padding:14px 16px;border-bottom:1px solid #e5e7eb;text-align:left}th{background:#f8fafc;color:#64748b;font-size:.7rem;text-transform:uppercase;letter-spacing:.05em}td{font-size:.86rem}.id-number{color:#dc2626;font-weight:900}.view{display:inline-flex;align-items:center;gap:6px;padding:8px 11px;border:0;border-radius:8px;background:#2563eb;color:#fff;font:inherit;font-size:.75rem;font-weight:800;cursor:pointer}.assign-form{display:flex;align-items:center;gap:7px;min-width:270px}.assign-form input{width:170px;padding:8px 9px;border:1px solid #cbd5e1;border-radius:8px;font:inherit;font-size:.75rem;text-transform:uppercase}.assign-form input:focus{outline:3px solid rgba(37,99,235,.16);border-color:#2563eb}.assign-status{display:block;margin-top:5px;color:#b91c1c;font-size:.7rem}.empty{padding:48px;text-align:center;color:#64748b}.pagination{display:flex;justify-content:center;align-items:center;gap:10px;margin-top:18px}.pagination a{padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#334155;text-decoration:none}.pagination .disabled{opacity:.45;pointer-events:none}.id-modal{position:fixed;inset:0;z-index:2000;display:none;align-items:center;justify-content:center;padding:20px;background:rgba(15,23,42,.68);backdrop-filter:blur(3px)}.id-modal.open{display:flex}.id-modal-box{display:flex;flex-direction:column;width:min(900px,100%);max-height:94vh;overflow:hidden;border-radius:18px;background:#fff;box-shadow:0 28px 80px rgba(15,23,42,.35)}.id-modal-head{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #e2e8f0}.id-modal-head h2{margin:0;font-size:1rem}.id-modal-close{width:36px;height:36px;border:0;border-radius:8px;background:#f1f5f9;color:#475569;font-size:1.25rem;cursor:pointer}.id-frame{width:100%;height:min(710px,calc(94vh - 126px));border:0;background:#eef3f9}.id-modal-actions{display:flex;justify-content:flex-end;gap:9px;padding:12px 20px;border-top:1px solid #e2e8f0}.id-modal-actions button{padding:9px 13px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#334155;font-weight:800;cursor:pointer}.id-modal-actions .primary{border-color:#2563eb;background:#2563eb;color:#fff}@media(max-width:800px){.main-content,.main-content.collapsed{width:100%;margin-left:0;padding:20px 12px}.page-header{align-items:flex-start;flex-direction:column}.header-user{align-self:stretch}.page-tools,.search,.search input{width:100%;max-width:none}.card{overflow:auto}table{min-width:900px}.id-modal{padding:8px}.id-frame{height:78vh}} 
        /* Digital ID workspace */
        .page-shell{max-width:none}
        .page-tools{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:12px;padding:16px 18px;background:#fff;border:1px solid #d9e3ee;border-radius:14px;box-shadow:0 6px 18px rgba(15,23,42,.045)}
        .records-summary h2{margin:0 0 4px;color:#0f2942;font-size:1rem}.records-summary p{margin:0;color:#64748b;font-size:.76rem}.record-count{color:#2563eb;font-weight:800}
        .search input{min-height:44px;padding:11px 13px;border-radius:10px}.search input:focus{outline:3px solid rgba(37,99,235,.14);border-color:#2563eb}.search button{min-height:44px;padding-inline:17px;border-radius:10px;box-shadow:0 5px 12px rgba(37,99,235,.2)}
        .search button:hover,.view:hover{background:#1d4ed8}.card{box-shadow:0 8px 24px rgba(15,23,42,.06)}th,td{padding:16px 18px}th{letter-spacing:.07em}td{vertical-align:middle}tbody tr:nth-child(even){background:#f8fafc}tbody tr:hover{background:#eff6ff}.id-number{color:#0f2942;letter-spacing:.025em}tbody td:nth-child(3) strong{text-transform:capitalize}
        .view{justify-content:center;min-height:40px;padding:8px 13px;border-radius:9px;white-space:nowrap;transition:background-color .15s ease,transform .15s ease}.view:hover{transform:translateY(-1px)}
        @media(max-width:800px){.page-tools{align-items:stretch;flex-direction:column}.records-summary{width:100%}.search button{min-width:96px}}
    </style>
</head>
<body>
<?php include $isDepartment ? '../partials/department_sidebar.php' : '../partials/barangay_sidebar.php'; ?>
<main class="main-content">
    <header class="page-header"><div class="page-header-left"><div class="greeting" id="greetingMsg"></div><h1>Digital <span>IDs</span></h1></div><div class="header-user"><?php $profilePicPath='../images/profile_pictures/'.($_SESSION['profile_picture']??'default.jpg'); if(!file_exists($profilePicPath)||is_dir($profilePicPath))$profilePicPath='../images/profile_pictures/default.jpg'; ?><img src="<?= htmlspecialchars($profilePicPath) ?>" alt="Profile"><div class="header-user-info"><h3><?= htmlspecialchars(trim(($_SESSION['first_name']??'').' '.($_SESSION['last_name']??''))) ?></h3><p><?= htmlspecialchars(ucwords(str_replace('_',' ',$role))) ?> · <?= $isDepartment?'Pasig City':htmlspecialchars($_SESSION['barangay']??'') ?></p></div></div></header>
<div class="page-shell">
    <div class="page-tools"><div class="records-summary"><h2>Issued Senior Citizen IDs</h2><p><span class="record-count"><?= number_format($total) ?></span> approved <?= $total === 1 ? 'record' : 'records' ?> available</p></div><form class="search" method="get"><input name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, ID, or application…" maxlength="100" aria-label="Search digital IDs"><button type="submit"><i class="fas fa-search"></i> Search</button></form></div>
    <section class="card">
        <?php if (!$records): ?><div class="empty"><i class="fas fa-id-card fa-2x"></i><p>No approved digital IDs found.</p></div>
        <?php else: ?><table><thead><tr><th>Senior Citizen ID No.</th><th>Application Token</th><th>Applicant</th><th>Barangay</th><th>Birth Date</th><th>Action</th></tr></thead><tbody>
        <?php foreach ($records as $record): $hasOfficialId = hasOfficialSeniorId($record); ?><tr><td class="id-number"><?= $hasOfficialId ? htmlspecialchars($record['senior_id_no']) : '<span style="color:#b45309">Needs official ID</span>' ?></td><td><strong><?= htmlspecialchars($record['id_number']) ?></strong><br><small>Use for application tracking</small></td><td><strong><?= htmlspecialchars($record['full_name']) ?></strong></td><td><?= htmlspecialchars($record['barangay']) ?></td><td><?= htmlspecialchars(date('F j, Y', strtotime($record['birth_date']))) ?></td><td><?php if ($hasOfficialId): ?><button class="view" type="button" data-digital-id="<?= htmlspecialchars($record['id_number']) ?>" data-applicant="<?= htmlspecialchars($record['full_name']) ?>"><i class="fas fa-eye"></i> View Digital ID</button><?php elseif ($isDepartment): ?><form class="assign-form" data-assign-form><input name="seniorIdNo" placeholder="Official Senior ID No." maxlength="50" aria-label="Official Senior ID number for <?= htmlspecialchars($record['full_name']) ?>" required><input type="hidden" name="applicationId" value="<?= htmlspecialchars($record['id_number']) ?>"><button class="view" type="submit"><i class="fas fa-check"></i> Assign</button></form><small class="assign-status" aria-live="polite"></small><?php else: ?><small>Awaiting OSCA assignment</small><?php endif; ?></td></tr><?php endforeach; ?>
        </tbody></table><?php endif; ?>
    </section>
    <?php if ($pages > 1): $query = $search !== '' ? '&search=' . rawurlencode($search) : ''; ?><nav class="pagination" aria-label="Digital ID pages"><a class="<?= $page <= 1 ? 'disabled' : '' ?>" href="?page=<?= max(1,$page-1) . $query ?>">Previous</a><span>Page <?= $page ?> of <?= $pages ?></span><a class="<?= $page >= $pages ? 'disabled' : '' ?>" href="?page=<?= min($pages,$page+1) . $query ?>">Next</a></nav><?php endif; ?>
</div></main>
<div class="id-modal" id="digitalIdModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="digitalIdModalTitle">
    <div class="id-modal-box"><div class="id-modal-head"><h2 id="digitalIdModalTitle"><i class="fas fa-id-card"></i> Temporary Digital Senior Citizen ID</h2><button class="id-modal-close" type="button" aria-label="Close digital ID">&times;</button></div><iframe class="id-frame" id="digitalIdFrame" title="Temporary Digital Senior Citizen ID"></iframe><div class="id-modal-actions"><button type="button" data-close-id>Close</button><button type="button" class="primary" id="printDigitalId"><i class="fas fa-print"></i> Print / Save PDF</button></div></div>
</div>
<script src="../assets/js/sidebar-toggle.js?v=3"></script>
<script>
(() => {
    const hour = new Date().getHours();
    const greeting = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
    document.getElementById('greetingMsg').innerHTML = greeting + ', <strong><?= htmlspecialchars(trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')), ENT_QUOTES) ?></strong>!';
    const modal = document.getElementById('digitalIdModal');
    const frame = document.getElementById('digitalIdFrame');
    const closeButton = modal.querySelector('.id-modal-close');
    let returnFocus = null;
    const close = () => { modal.classList.remove('open'); modal.setAttribute('aria-hidden','true'); frame.removeAttribute('src'); document.body.style.overflow=''; returnFocus?.focus(); };
    document.querySelectorAll('[data-digital-id]').forEach(button => button.addEventListener('click', () => {
        returnFocus = button;
        document.getElementById('digitalIdModalTitle').innerHTML = '<i class="fas fa-id-card"></i> Digital ID — ' + button.dataset.applicant.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
        frame.src = 'digital_id.php?id=' + encodeURIComponent(button.dataset.digitalId) + '&embed=1';
        modal.classList.add('open'); modal.setAttribute('aria-hidden','false'); document.body.style.overflow='hidden'; closeButton.focus();
    }));
    document.querySelectorAll('[data-assign-form]').forEach(form => form.addEventListener('submit', async event => {
        event.preventDefault();
        const input = form.elements.seniorIdNo, applicationId = form.elements.applicationId.value;
        const status = form.nextElementSibling, normalized = input.value.trim().toUpperCase();
        status.textContent = '';
        if (!/^[A-Z0-9][A-Z0-9 -]{2,49}$/.test(normalized) || normalized === applicationId.toUpperCase()) {
            status.textContent = 'Enter an official ID number, not the application token.'; input.focus(); return;
        }
        const body = new FormData(); body.append('applicationId', applicationId); body.append('seniorIdNo', normalized);
        const submit = form.querySelector('button'); submit.disabled = true;
        const response = await fetch('../api/set_official_senior_id.php', { method:'POST', body });
        const result = await response.json().catch(() => ({success:false,message:'Invalid server response.'}));
        if (!response.ok || !result.success) { status.textContent = result.message || 'Unable to save the Senior ID.'; submit.disabled = false; return; }
        window.location.reload();
    }));
    closeButton.addEventListener('click', close); modal.querySelector('[data-close-id]').addEventListener('click', close);
    modal.addEventListener('click', event => { if (event.target === modal) close(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && modal.classList.contains('open')) close(); });
    document.getElementById('printDigitalId').addEventListener('click', () => frame.contentWindow?.print());
})();
</script>
</body></html>
