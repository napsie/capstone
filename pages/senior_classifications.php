<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/senior_classification.php';
require_once '../includes/request_security.php';
header('Cache-Control: private, no-store');
try { $actor = classificationActor($_SESSION); }
catch (RuntimeException $e) { http_response_code(403); exit('Authorized staff access and an assigned barangay are required.'); }
$error = ''; $notice = $_SESSION['classification_notice'] ?? ''; unset($_SESSION['classification_notice']);
$_SESSION['classification_csrf'] ??= bin2hex(random_bytes(32));
$selectedId = is_string($_GET['profile'] ?? '') ? trim($_GET['profile'] ?? '') : '';
$editValues = [];
try { $filters = classificationFilters($_GET, $actor); }
catch (InvalidArgumentException $e) { http_response_code(400); exit($e->getMessage()); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireSameOriginMutation();
    if (!$actor['can_edit']) { http_response_code(403); exit('Only Main OSCA administrators can update classifications.'); }
    if (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($_SESSION['classification_csrf'], $_POST['csrf_token'])) {
        http_response_code(403); exit('Your session could not be verified. Reload the page and try again.');
    }
    $selectedId = is_string($_POST['profile_id'] ?? '') ? trim($_POST['profile_id'] ?? '') : '';
    foreach (['activity_status','residence_status','effective_date','reason','version'] as $field) {
        if (isset($_POST[$field]) && is_scalar($_POST[$field])) $editValues[$field] = (string)$_POST[$field];
    }
    try {
        saveSeniorClassification($conn, $actor, $_POST);
        $_SESSION['classification_notice'] = 'Senior classification saved. The change has been recorded in history.';
        header('Location: senior_classifications.php?' . http_build_query($filters + ['profile'=>$selectedId])); exit;
    } catch (InvalidArgumentException $e) { http_response_code(422); $error=$e->getMessage(); }
    catch (RuntimeException $e) {
        $code=in_array($e->getCode(),[403,404,409],true)?$e->getCode():500;
        http_response_code($code); $error=$code===500?'Unable to save the classification. Please try again.':$e->getMessage();
        if ($code===500) error_log('Senior classification save: '.$e->getMessage());
    }
}
$summary = classificationSummary($conn, $filters);
$cardSummary = classificationSummary($conn, classificationFilters([], $actor));
$pageSize = 10;
$totalPages = max(1, (int)ceil($summary['total']/$pageSize));
$page = min($totalPages, max(1, (int)($_GET['page'] ?? 1)));
$rows = classificationRows($conn, $filters, $pageSize, ($page-1)*$pageSize);
$selected = $selectedId !== '' ? classificationProfile($conn, $selectedId, $actor) : null;
if ($selectedId !== '' && !$selected) { http_response_code(404); $error='Senior profile is unavailable or outside your permitted scope.'; }
$edit = array_merge($selected ?? [], $editValues);
$today = (new DateTimeImmutable('today', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
$history=[];
if ($selected) {
    $stmt=$conn->prepare('SELECT * FROM senior_classification_history WHERE profile_id=? ORDER BY id DESC LIMIT 20');
    $stmt->execute([$selected['profile_id']]); $history=$stmt->fetchAll();
}
$h=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');
$url=static fn(array $extra=[])=>'senior_classifications.php?'.http_build_query(array_merge($filters,$extra));
$isDepartment=$actor['can_edit']; $barangayName=$_SESSION['barangay']??'';
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Senior Classification Report - SENIORLINK</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/<?= $isDepartment?'department-sidebar':'barangay-sidebar' ?>.css?v=20261023">
<link rel="stylesheet" href="../assets/css/senior-classifications.css?v=9">
<link rel="stylesheet" href="../assets/css/system-header.css?v=1">
<link rel="stylesheet" href="../assets/css/metric-cards.css?v=3">
</head><body>
<?php include $isDepartment?'../partials/department_sidebar.php':'../partials/barangay_sidebar.php'; ?>
<main class="main-content classification-page">
<header class="page-header"><div class="page-header-left"><p class="greeting">Welcome, <strong><?= $h(trim(($_SESSION['first_name']??'').' '.($_SESSION['last_name']??''))?:$actor['name']) ?>!</strong></p><h1>Senior <span>Classification Report</span></h1></div>
<div class="header-user"><img src="<?= $h(systemLogoUrl($conn)) ?>" alt="SENIORLINK"><div class="header-user-info"><h3><?= $isDepartment?'Main OSCA':$h($actor['barangay']) ?></h3><p><?= $isDepartment?'Department Admin · Pasig City':'Barangay SHDO' ?></p></div></div></header>
<details class="classification-about"><summary><i class="fas fa-circle-info" aria-hidden="true"></i> About senior classifications</summary>
<p class="classification-explanation">One primary senior profile is counted once, regardless of linked benefit applications or ID change requests. Unapproved and archived primary profiles are excluded. Classification does not change benefit eligibility or application status.</p>
<p class="classification-explanation">Verified and approved senior profiles are automatically Active. Main OSCA can update classifications with a reason. Residence defaults to Within Pasig for this Pasig system. Main OSCA can change it to Outside Pasig when needed. Residence means currently living within or outside Pasig; it does not establish a required residency period. Deceased status comes from the existing deceased registry.</p>
</details>
<p class="classification-scope-note">Cards show overall totals and stay unchanged when you search or filter. Filters apply to the table and generated report. Select a card to switch categories.</p>
<?php if ($notice && !$selected): ?><p class="notice success" role="status"><?= $h($notice) ?></p><?php endif; ?>
<?php if ($error && !$selected): ?><p class="notice error" role="alert"><?= $h($error) ?></p><?php endif; ?>
<section class="classification-totals stats-strip" aria-label="Overall senior totals within your access">
<?php foreach (['total'=>'Registered Seniors','active'=>'Active','inactive'=>'Inactive','deceased'=>'Deceased'] as $key=>$label): ?>
<a class="stat-card classification-summary-link" href="<?= $h($url(['activity'=>$key==='total'?'all':$label,'residence'=>'all','page'=>1])) ?>" aria-label="<?= $h('Show '.($key==='total'?'all registered':strtolower($label)).' seniors') ?>"><div class="stat-icon"><i class="fas <?= ['total'=>'fa-users','active'=>'fa-circle-check','inactive'=>'fa-circle-pause','deceased'=>'fa-ribbon'][$key] ?>" aria-hidden="true"></i></div><div><div class="stat-label"><?= $label ?></div><strong class="stat-value"><?= number_format($cardSummary[$key]) ?></strong><small class="stat-meta">Overall total</small></div></a>
<?php endforeach; ?></section>
<section class="classification-records-card">
<div class="classification-records-header"><h2><i class="fas fa-folder-open" aria-hidden="true"></i> Senior Classifications – <?= $h($filters['barangay']==='all'?'Pasig City':$filters['barangay']) ?></h2><div class="classification-actions"><button class="button primary" type="button" id="classification-export-open"><i class="fas fa-file-export" aria-hidden="true"></i> Generate Report</button></div></div>
<form method="get" class="classification-filters" id="classification-filters">
<label>Search<input type="search" name="search" maxlength="100" placeholder="Name, Senior ID, or Tracking No." value="<?= $h($filters['search']) ?>"></label>
<?php if ($isDepartment): ?><label>Barangay<select name="barangay"><option value="all">All barangays</option><?php foreach ($barangays_list as $barangay): ?><option <?= $filters['barangay']===$barangay?'selected':'' ?>><?= $h($barangay) ?></option><?php endforeach; ?></select></label><?php endif; ?>
<label>Activity<select name="activity"><?php foreach (['all'=>'All classifications','Active'=>'Active','Inactive'=>'Inactive','Deceased'=>'Deceased'] as $value=>$label): ?><option value="<?= $value ?>" <?= $filters['activity']===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label>
<label>Residence<select name="residence"><?php foreach (['all'=>'All residence categories','Within Pasig'=>'Within Pasig','Outside Pasig'=>'Outside Pasig','Unclassified'=>'Unclassified'] as $value=>$label): ?><option value="<?= $value ?>" <?= $filters['residence']===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label>
<button class="button primary" type="submit">Search</button><a class="button" href="senior_classifications.php">Clear</a>
</form>
<?php if ($selected): ?>
<dialog class="classification-dialog" id="classification-profile" aria-labelledby="classification-dialog-title">
<div class="classification-dialog-header"><div><span>Senior classification</span><h2 id="classification-dialog-title"><?= $h($selected['full_name']) ?></h2></div><button class="classification-dialog-close" type="button" data-classification-close aria-label="Close classification" autofocus>&times;</button></div>
<div class="classification-dialog-body">
<?php if ($error): ?><p class="notice error" role="alert"><?= $h($error) ?></p><?php endif; ?>
<?php if ($notice): ?><p class="notice success" role="status"><?= $h($notice) ?></p><?php endif; ?>
<p>Senior ID: <?= $h($selected['senior_id_no']?:'Not assigned') ?> · Tracking No.: <?= $h($selected['tracking_no']) ?> · <?= $h($selected['barangay']) ?></p>
<?php if ($isDepartment && $selected['activity_status']!=='Deceased'): ?>
<form method="post" action="<?= $h($url(['profile'=>$selected['profile_id']])) ?>" class="classification-edit-form">
<input type="hidden" name="csrf_token" value="<?= $h($_SESSION['classification_csrf']) ?>"><input type="hidden" name="profile_id" value="<?= $h($selected['profile_id']) ?>"><input type="hidden" name="version" value="<?= (int)$edit['version'] ?>">
<label>Activity classification<select name="activity_status" required><?php foreach (['Active','Inactive'] as $value): ?><option <?= $edit['activity_status']===$value?'selected':'' ?>><?= $value ?></option><?php endforeach; ?></select></label>
<label>Residence classification<select name="residence_status" required><?php foreach (['Within Pasig','Outside Pasig'] as $value): ?><option <?= $edit['residence_status']===$value?'selected':'' ?>><?= $value ?></option><?php endforeach; ?></select></label>
<label>Effective date<input type="date" name="effective_date" max="<?= $today ?>" value="<?= $h($edit['effective_date']?:$today) ?>" required></label>
<label class="reason-field">Reason for this classification<textarea name="reason" minlength="5" maxlength="1000" rows="3" required placeholder="Record the review or office basis for this classification."><?= $h($edit['reason']) ?></textarea></label>
<button class="button primary" type="submit">Save classification</button>
</form>
<?php else: ?><p><?= $selected['activity_status']==='Deceased'?'This profile is marked Deceased in the existing registry.':'Classification updates are managed by Main OSCA.' ?></p><?php endif; ?>
<h3>Classification history</h3><p>Latest 20 changes, including the reason, effective date, and responsible staff.</p>
<?php if (!$history): ?><p>No manual classification changes recorded.</p><?php endif; ?>
<ol class="classification-history"><?php foreach ($history as $event): ?><li><strong><?= $h($event['previous_activity_status'].' → '.$event['activity_status']) ?></strong>; <?= $h($event['previous_residence_status'].' → '.$event['residence_status']) ?><br>Effective <?= $h($event['effective_date']) ?> · <?= $h($event['changed_by_name']) ?> · <?= $h($event['changed_at']) ?><p><?= nl2br($h($event['reason'])) ?></p></li><?php endforeach; ?></ol>
</div></dialog><?php endif; ?>
<section class="classification-table"><table><caption>Senior profiles matching current filters</caption><thead><tr><th>Senior</th><th>Barangay</th><th>Activity</th><th>Residence</th><th>Effective date</th><th>Reason and review</th><th>Action</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="7">No senior profiles match these filters.</td></tr><?php endif; ?>
<?php foreach ($rows as $row): ?><tr><td><strong><?= $h($row['full_name']) ?></strong><small>Senior ID: <?= $h($row['senior_id_no']?:'Not assigned') ?></small><small>Tracking No.: <?= $h($row['tracking_no']) ?></small></td><td><?= $h($row['barangay']) ?></td><td><span class="classification-badge" data-activity="<?= $h($row['activity_status']) ?>"><?= $h($row['activity_status']) ?></span></td><td><?= $h($row['residence_status']) ?></td><td><?= $h($row['effective_date']?:'Not reviewed') ?></td><td><p><?= $h($row['reason']?:($row['activity_status']==='Deceased'?'From deceased registry':((int)$row['version']===0?'Automatically Active upon verification':'Awaiting Main OSCA review'))) ?></p><small><?= $h($row['updated_by_name']) ?> <?= $h($row['updated_at']) ?></small></td><td><a class="button" data-classification-manage href="<?= $h($url(['page'=>$page,'profile'=>$row['profile_id']])) ?>#classification-profile"><?= $isDepartment && $row['activity_status']!=='Deceased'?'Manage':'View history' ?></a></td></tr><?php endforeach; ?>
</tbody></table></section>
<nav class="pagination-bar" aria-label="Report pages">
<div class="pagination-info">Page <?= $page ?> of <?= $totalPages ?> &bull; <?= number_format($summary['total']) ?> seniors &bull; <?= count($rows) ?> shown</div>
<div class="pagination-links">
<?php if ($page>1): ?><a class="pagination-direction" href="<?= $h($url(['page'=>$page-1])) ?>" aria-label="Previous page"><i class="fas fa-chevron-left" aria-hidden="true"></i> Previous</a><?php else: ?><span class="disabled pagination-direction" aria-disabled="true"><i class="fas fa-chevron-left" aria-hidden="true"></i> Previous</span><?php endif; ?>
<?php $start=max(1,$page-2);$end=min($totalPages,$page+2); ?>
<?php if ($start>1): ?><a href="<?= $h($url(['page'=>1])) ?>" aria-label="Page 1">1</a><?php if ($start>2): ?><span class="pagination-ellipsis" aria-hidden="true">&hellip;</span><?php endif; ?><?php endif; ?>
<?php for ($i=$start;$i<=$end;$i++): ?><?php if ($i===$page): ?><span class="current" aria-current="page" aria-label="Page <?= $i ?>"><?= $i ?></span><?php else: ?><a href="<?= $h($url(['page'=>$i])) ?>" aria-label="Page <?= $i ?>"><?= $i ?></a><?php endif; ?><?php endfor; ?>
<?php if ($end<$totalPages): ?><?php if ($end<$totalPages-1): ?><span class="pagination-ellipsis" aria-hidden="true">&hellip;</span><?php endif; ?><a href="<?= $h($url(['page'=>$totalPages])) ?>" aria-label="Page <?= $totalPages ?>"><?= $totalPages ?></a><?php endif; ?>
<?php if ($page<$totalPages): ?><a class="pagination-direction" href="<?= $h($url(['page'=>$page+1])) ?>" aria-label="Next page">Next <i class="fas fa-chevron-right" aria-hidden="true"></i></a><?php else: ?><span class="disabled pagination-direction" aria-disabled="true">Next <i class="fas fa-chevron-right" aria-hidden="true"></i></span><?php endif; ?>
</div></nav>
</section></main>
<dialog class="classification-dialog classification-export-dialog" id="classification-export" aria-labelledby="classification-export-title">
<div class="classification-dialog-header"><h2 id="classification-export-title"><i class="fas fa-file-export" aria-hidden="true"></i> Generate Report</h2><button class="classification-dialog-close" type="button" id="classification-export-close" aria-label="Close generate report" autofocus>&times;</button></div>
<div class="classification-dialog-body"><p>Choose a file format, classification filters, and barangay coverage. Only matching senior profiles will be included, across all report pages.</p>
<form id="classification-export-form" action="../api/export_senior_classifications.php" method="get">
<div class="classification-export-grid">
<label>File format<select name="format" required><option value="">Choose a format</option><option value="pdf">PDF</option><option value="xlsx">Excel (.xlsx)</option></select></label>
<label>Activity classification<select name="activity"><?php foreach (['all'=>'All classifications','Active'=>'Active','Inactive'=>'Inactive','Deceased'=>'Deceased'] as $value=>$label): ?><option value="<?= $value ?>" <?= $filters['activity']===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label>
<label>Residence classification<select name="residence"><?php foreach (['all'=>'All residence categories','Within Pasig'=>'Within Pasig','Outside Pasig'=>'Outside Pasig','Unclassified'=>'Unclassified'] as $value=>$label): ?><option value="<?= $value ?>" <?= $filters['residence']===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label>
<label>Search<input type="search" name="search" maxlength="100" placeholder="Name, Senior ID, or Tracking No." value="<?= $h($filters['search']) ?>"></label>
</div>
<fieldset class="classification-export-coverage"><legend><i class="fas fa-map-marker-alt" aria-hidden="true"></i> Barangay Coverage</legend>
<?php if ($isDepartment): ?>
<label class="classification-coverage-option"><input type="radio" name="coverage" value="all" <?= $filters['barangay']==='all'?'checked':'' ?>><span><strong>All Barangays</strong><small>Include matching senior profiles across Pasig City.</small></span></label>
<label class="classification-coverage-option"><input type="radio" name="coverage" value="one" <?= $filters['barangay']!=='all'?'checked':'' ?>><span><strong>One Barangay</strong><small>Export records from one selected barangay only.</small></span></label>
<label id="classification-export-barangay-label">Barangay<select name="barangay" id="classification-export-barangay"><?php foreach ($barangays_list as $barangay): ?><option <?= $filters['barangay']===$barangay?'selected':'' ?>><?= $h($barangay) ?></option><?php endforeach; ?></select></label>
<?php else: ?><input type="hidden" name="barangay" value="<?= $h($actor['barangay']) ?>"><p class="classification-coverage-assigned"><strong><?= $h($actor['barangay']) ?></strong><br>Only senior profiles within your assigned barangay are included.</p><?php endif; ?>
</fieldset>
<p id="classification-export-message" role="status" hidden></p>
<div class="classification-export-footer"><button class="button" type="button" id="classification-export-cancel">Cancel</button><button class="button primary" type="submit"><i class="fas fa-file-export" aria-hidden="true"></i> Generate Report</button></div>
</form></div></dialog>
<script src="../assets/js/classification-modal.js?v=1" defer></script><script src="../assets/js/classification-filters.js?v=2" defer></script><script src="../assets/js/classification-export-modal.js?v=1" defer></script></body></html>
