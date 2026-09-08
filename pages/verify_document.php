<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/application_types.php';
require_once '../includes/barangays_list.php';

// Check if user is logged in and authorized
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['department_admin', 'super_admin'])) {
    header("Location: ../index.php");
    exit();
}

// Fetch stats for the queue counters
$statsQuery = "SELECT 
    COUNT(*) as total_queue,
    SUM(CASE WHEN COALESCE(NULLIF(workflow_state, ''), NULLIF(status, ''), 'Received') = 'For Review' THEN 1 ELSE 0 END) as for_review,
    SUM(CASE WHEN COALESCE(NULLIF(workflow_state, ''), NULLIF(status, ''), 'Received') = 'Verified' THEN 1 ELSE 0 END) as verified
    FROM applications
    WHERE COALESCE(NULLIF(workflow_state, ''), NULLIF(status, ''), 'Received') NOT IN ('Verified', 'Approved', 'Released')
      AND (is_archived = 0 OR is_archived IS NULL)";
$statsStmt = $conn->prepare($statsQuery);
$statsStmt->execute();
$queueStats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$totalQCount   = $queueStats['total_queue'] ?? 0;
$forReviewC    = $queueStats['for_review'] ?? 0;
$verifiedC     = $queueStats['verified'] ?? 0;

// Verification queue filters
$queueFilters = [
    'search'    => trim((string)($_GET['search'] ?? '')),
    'type'      => trim((string)($_GET['type'] ?? 'all')),
    'barangay'  => trim((string)($_GET['barangay'] ?? 'all')),
];

$applicationTypeOptions = getApplicationTypeOptions();

if ($queueFilters['type'] !== 'all' && !array_key_exists($queueFilters['type'], $applicationTypeOptions)) {
    $queueFilters['type'] = 'all';
}
if ($queueFilters['barangay'] !== 'all' && !in_array($queueFilters['barangay'], $barangays_list, true)) {
    $queueFilters['barangay'] = 'all';
}

$queueConditions = [
    "COALESCE(NULLIF(workflow_state, ''), NULLIF(status, ''), 'Received') NOT IN ('Verified', 'Approved', 'Released')",
    '(is_archived = 0 OR is_archived IS NULL)',
];
$queueParams = [];

if ($queueFilters['search'] !== '') {
    $queueConditions[] = '(full_name LIKE :queue_search_name OR id_number LIKE :queue_search_id)';
    $queueSearchValue = '%' . $queueFilters['search'] . '%';
    $queueParams['queue_search_name'] = $queueSearchValue;
    $queueParams['queue_search_id'] = $queueSearchValue;
}
if ($queueFilters['type'] !== 'all') {
    $queueConditions[] = 'application_type = :queue_type';
    $queueParams['queue_type'] = $queueFilters['type'];
}
if ($queueFilters['barangay'] !== 'all') {
    $queueConditions[] = 'barangay = :queue_barangay';
    $queueParams['queue_barangay'] = $queueFilters['barangay'];
}

$queuePage = max(1, (int)($_GET['page'] ?? 1));
$queuePerPage = 25;
$queueCountStmt = $conn->prepare('SELECT COUNT(*) FROM applications WHERE ' . implode(' AND ', $queueConditions));
$queueCountStmt->execute($queueParams);
$filteredQueueCount = (int)$queueCountStmt->fetchColumn();
$queueTotalPages = max(1, (int)ceil($filteredQueueCount / $queuePerPage));
$queuePage = min($queuePage, $queueTotalPages);
$queueOffset = ($queuePage - 1) * $queuePerPage;

$queueSql = "SELECT id_number as id, full_name, application_type, requested_benefit, barangay, date_submitted,
                    status, workflow_state, home_visit_status,
                    COALESCE(NULLIF(workflow_state, ''), NULLIF(status, ''), 'Received') AS effective_state
             FROM applications
             WHERE " . implode(' AND ', $queueConditions) . "
             ORDER BY date_submitted DESC, id_number DESC
             LIMIT :queue_limit OFFSET :queue_offset";
$queueStmt = $conn->prepare($queueSql);
$queueStmt->bindValue(':queue_limit', $queuePerPage, PDO::PARAM_INT);
$queueStmt->bindValue(':queue_offset', $queueOffset, PDO::PARAM_INT);
foreach ($queueParams as $key => $value) $queueStmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
$queueStmt->execute();
$result = $queueStmt->fetchAll(PDO::FETCH_ASSOC);
$hasQueueFilters = $queueFilters['search'] !== ''
    || $queueFilters['type'] !== 'all'
    || $queueFilters['barangay'] !== 'all';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SENIORLINK — Document Verification Terminal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/department-sidebar.css?v=4">
    <link rel="stylesheet" href="../assets/css/application-documents.css?v=7">
    <style>
        /* ─── Variables ─────────────────────────────────────────────────── */
        :root {
            --primary:   #0f172a;
            --secondary: #1e3a5f;
            --accent:    #2563eb;
            --success:   #10b981;
            --warning:   #f59e0b;
            --danger:    #ef4444;
            --purple:    #8b5cf6;
            --gray:      #94a3b8;
            --border:    #e2e8f0;
            --bg:        #f1f5f9;
            --card:      #ffffff;
            --text:      #0f172a;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); color: var(--text); line-height: 1.6; }

        /* ─── Layout ─────────────────────────────────────────────────────── */
        .container { display: flex; min-height: 100vh; }
        .main-content { flex: 1; padding: 28px; overflow-y: auto; }

        /* ─── Page Header ────────────────────────────────────────────────── */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
        }
        .page-header-left h1 {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #0f172a;
            margin: 0;
            font-family: 'Inter', sans-serif;
            line-height: 1.05;
        }
        .page-header-left h1 span {
            color: #2563eb;
        }
        .greeting {
            font-size: 0.98rem;
            font-weight: 500;
            color: #6b7280;
            margin-bottom: 4px;
            font-family: 'Inter', sans-serif;
        }
        .greeting strong {
            font-weight: 700;
            color: #111827;
        }
        .header-user {
            display: flex;
            align-items: center;
            gap: 12px;
            border-radius: 30px;
            padding: 8px 14px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.12);
            border: 1px solid rgba(37, 99, 235, 0.22);
            background: linear-gradient(var(--card), var(--card)) padding-box, linear-gradient(135deg, #0f172a 0%, #3498db 100%) border-box;
        }
        .header-user img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent); }
        .header-user-info h3 { font-size: 0.9rem; font-weight: 700; color: var(--primary); margin: 0; }
        .header-user-info p  { font-size: 0.75rem; color: var(--gray); margin: 0; }

        /* ─── Stats ──────────────────────────────────────────────────────── */
        .stats-strip { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin-bottom: 28px; }
        .stat-card {
            position: relative;
            overflow: hidden;
            background: var(--card);
            border-radius: 16px;
            min-height: 126px;
            padding: 22px 24px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: 0 6px 18px rgba(15,23,42,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card::before { content: ''; position: absolute; inset: 0 0 auto; height: 4px; }
        .stat-card:nth-child(1) { background: linear-gradient(145deg,#fff 45%,#eff6ff); border-color:#cfe0ff; }
        .stat-card:nth-child(2) { background: linear-gradient(145deg,#fff 45%,#f5f0ff); border-color:#e1d5ff; }
        .stat-card:nth-child(3) { background: linear-gradient(145deg,#fff 45%,#ecfdf5); border-color:#c8eedf; }
        .stat-card:nth-child(1)::before { background:linear-gradient(90deg,#2563eb,#60a5fa); }
        .stat-card:nth-child(2)::before { background:linear-gradient(90deg,#7c3aed,#a78bfa); }
        .stat-card:nth-child(3)::before { background:linear-gradient(90deg,#059669,#34d399); }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.07); }
        .stat-icon { width: 54px; height: 54px; border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0; box-shadow:inset 0 0 0 1px rgba(255,255,255,.65); }
        .stat-icon.green  { background: rgba(16,185,129,0.12); color: var(--success); }
        .stat-icon.blue   { background: rgba(37,99,235,0.12);  color: var(--accent); }
        .stat-icon.purple { background: rgba(139,92,246,0.12); color: var(--purple); }
        .stat-icon.amber  { background: rgba(245,158,11,0.12); color: var(--warning); }
        .stat-copy { min-width:0; }
        .stat-label { margin-bottom:5px; font-size: 0.73rem; color: #64748b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.07em; }
        .stat-value { font-size: 2rem; font-weight: 850; color: #0f172a; line-height: .95; }
        .stat-meta { margin-top:7px; color:#64748b; font-size:.72rem; line-height:1.25; }

        /* ─── Queue Card ─────────────────────────────────────────────────── */
        .queue-card { background: var(--card); border-radius: 16px; border: 1px solid var(--border); box-shadow: 0 10px 30px rgba(15,23,42,0.06); overflow: hidden; }
        .queue-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 24px;
            border-bottom: 1px solid var(--border);
            background: #fff;
        }
        .queue-card-header h2 { color: #0f2942; font-size: 1.08rem; font-weight: 800; margin: 0; display: flex; align-items: center; gap: 10px; }
        .queue-card-header h2 i { width: 36px; height: 36px; display: grid; place-items: center; color: #2563eb; background: #eff6ff; border-radius: 10px; }
        .queue-export-btn { min-height: 42px; background:#2563eb; border:1px solid #2563eb; color:#fff; box-shadow:0 5px 12px rgba(37,99,235,.2); }
        .queue-export-btn:hover { background:#1d4ed8; border-color:#1d4ed8; color:#fff; }

        /* Queue filters */
        .queue-filters { padding: 20px 24px; background: #f8fafc; border-bottom: 1px solid var(--border); }
        .queue-filter-grid { display: grid; grid-template-columns: minmax(240px, 1.5fr) repeat(2, minmax(160px, 1fr)); gap: 14px; align-items: end; }
        .queue-filter-field { min-width: 0; }
        .queue-filter-field label { display: block; margin-bottom: 6px; color: #64748b; font-size: .72rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
        .queue-filter-field input, .queue-filter-field select { width: 100%; min-height: 46px; padding: 10px 12px; color: #0f172a; background: #fff; border: 1px solid #cbd5e1; border-radius: 10px; font: inherit; font-size: .84rem; }
        .queue-filter-field input:focus, .queue-filter-field select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37, 99, 235, .1); outline: none; }
        .queue-search-wrap { position: relative; }
        .queue-search-wrap i { position: absolute; top: 50%; left: 12px; color: #94a3b8; transform: translateY(-50%); pointer-events: none; }
        .queue-search-wrap input { padding-left: 36px; }
        .queue-filter-actions { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-top: 14px; }
        .queue-result-count { color: #64748b; font-size: .82rem; }
        .queue-result-count strong { color: #0f172a; }
        .queue-filter-buttons { display: flex; min-height: 40px; gap: 9px; }
        .queue-filter-buttons .btn { min-height: 40px; text-decoration: none; }
        .btn-filter-clear { color: #475569; background: #fff; border: 1px solid #cbd5e1; }
        .btn-filter-clear:hover { color: #0f172a; background: #f1f5f9; }
        .table-wrap.is-filtering { opacity: .55; pointer-events: none; transition: opacity .15s ease; }

        @media (max-width: 1180px) {
            .queue-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 720px) {
            .queue-filters { padding: 16px; }
            .queue-filter-grid { grid-template-columns: minmax(0, 1fr); }
            .queue-filter-actions { align-items: stretch; flex-direction: column; }
            .queue-filter-buttons { width: 100%; }
            .queue-filter-buttons .btn { flex: 1 1 0; justify-content: center; }
        }

        /* Priority badge */
        .priority-badge {
            background-color: rgba(245,158,11,0.12);
            color: #d97706;
            border: 1px solid rgba(245,158,11,0.25);
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.72rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .priority-high-row { background-color: rgba(245,158,11,0.03); border-left: 4px solid var(--warning) !important; }

        /* Table */
        .table-wrap { overflow-x: auto; }
        .records-tbl { width: 100%; border-collapse: collapse; }
        .records-tbl thead tr { background: #f8fafc; border-bottom: 2px solid var(--border); }
        .records-tbl th { padding: 12px 18px; text-align: left; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--gray); white-space: nowrap; }
        .records-tbl td { padding: 13px 18px; font-size: 0.86rem; color: var(--primary); border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .records-tbl tbody tr { transition: background 0.15s; }
        .records-tbl tbody tr:nth-child(even) { background: #fafcff; }
        .records-tbl tbody tr:hover { background: #eff6ff; }
        .records-tbl tbody tr:last-child td { border-bottom: none; }

        .name-cell .full-name { font-weight: 700; }
        .name-cell .app-id    { font-size: 0.73rem; color: var(--gray); margin-top: 2px; font-family: monospace; }

        /* Stepper states */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.71rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 4px 10px;
            border-radius: 20px;
        }
        .badge-received { background-color: #e2e8f0; color: #475569; }
        .badge-review { background-color: #dbeafe; color: #1d4ed8; }
        .badge-verified { background-color: #ccfbf1; color: #0f766e; }
        .badge-approved { background-color: #dcfce7; color: #15803d; }
        .badge-released { background-color: #f3e8ff; color: #6b21a8; }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        .btn-primary { background: var(--accent); color: white; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-secondary { background: #64748b; color: white; }
        .btn-secondary:hover { background: #475569; }

        .btn-view {
            background: #fff;
            border: 1.5px solid var(--border);
            border-radius: 7px;
            padding: 6px 14px;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--primary);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .btn-view:hover { background: var(--primary); color: #fff; border-color: var(--primary); transform: translateY(-1px); }

        /* ─── Modal ──────────────────────────────────────────────────────── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15,23,42,0.55);
            z-index: 1000;
            backdrop-filter: blur(4px);
            overflow-y: auto;
            padding: 30px 16px;
        }
        .modal-box {
            background: #fff;
            border-radius: 16px;
            width: 100%;
            max-width: 980px;
            margin: 0 auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .modal-head {
            background: linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);
            padding: 20px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-head h2 { color: #fff; font-size: 1rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 10px; min-width: 0; }
        #modalAppTitle { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .modal-head h2 i { color: #60a5fa; }
        .modal-close {
            background: rgba(255,255,255,0.15);
            border: none;
            border-radius: 8px;
            color: #fff;
            width: 34px; height: 34px;
            font-size: 1.2rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        .modal-close:hover { background: rgba(255,255,255,0.3); }
        .modal-scroller { max-height: 80vh; overflow-y: auto; padding: 28px; }

        /* Export modal */
        .export-modal-box { max-width: 520px; }
        .export-modal-body { padding: 26px 28px 28px; }
        .export-modal-body p { margin: 0 0 18px; color: var(--gray); font-size: .88rem; line-height: 1.55; }
        .export-filter-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:6px; }
        .export-filter-grid .full { grid-column:1 / -1; }
        .export-field label { display:block; font-size:.76rem; font-weight:700; color:var(--gray); text-transform:uppercase; margin-bottom:6px; }
        .export-field input, .export-field select { width:100%; padding:10px 11px; border:1px solid var(--border); border-radius:8px; color:var(--primary); background:#fff; font-size:.88rem; box-sizing:border-box; }
        .export-option { display:flex; align-items:center; gap:10px; padding:12px 14px; border:1px solid var(--border); border-radius:9px; margin-bottom:10px; cursor:pointer; }
        .export-option:has(input:checked) { border-color:var(--accent); background:#eff6ff; }
        .export-option input { accent-color:var(--accent); }
        .export-option strong { display:block; font-size:.88rem; color:var(--primary); }
        .export-option small { color:var(--gray); font-size:.76rem; }
        .export-select-wrap { margin: 14px 0 20px; }
        .export-select-wrap label { display:block; font-size:.76rem; font-weight:700; color:var(--gray); text-transform:uppercase; margin-bottom:6px; }
        .export-select-wrap select { width:100%; padding:10px 11px; border:1px solid var(--border); border-radius:8px; color:var(--primary); background:#fff; }
        .export-divider { border-top:1px solid var(--border); margin:18px 0 16px; padding-top:18px; }
        .export-divider-title { font-size:.78rem; font-weight:700; color:var(--primary); margin-bottom:12px; display:flex; align-items:center; gap:8px; }
        .export-divider-title i { color:var(--accent); }
        .export-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:20px; }

        /* Stepper */
        .stepper { display: flex; justify-content: space-between; margin-bottom: 28px; padding-bottom: 20px; border-bottom: 1px solid var(--border); }
        .step { flex: 1; text-align: center; position: relative; }
        .step::after { content:''; position:absolute; top:15px; left:50%; width:100%; height:3px; background:var(--border); z-index:1; }
        .step:last-child::after { display: none; }
        .step-circle {
            width: 32px; height: 32px; border-radius: 50%;
            background: var(--border); color: #64748b;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 7px; font-weight: 800; font-size: 0.8rem;
            position: relative; z-index: 2; transition: background 0.3s;
        }
        .step-label { font-size: 0.72rem; font-weight: 600; color: var(--gray); }
        .step.active .step-circle  { background: var(--accent); color:#fff; box-shadow:0 0 0 4px rgba(37,99,235,0.2); }
        .step.active .step-label   { color: var(--accent); font-weight: 700; }
        .step.completed .step-circle { background: var(--success); color:#fff; }
        .step.completed .step-label  { color: var(--success); font-weight: 700; }
        .step.completed::after       { background: var(--success); }

        /* Compliance */
        .compliance-card { background:#f8fafc; border:1px solid var(--border); border-radius:10px; padding:16px; margin-bottom:22px; }
        .compliance-title { font-weight:700; font-size:0.88rem; color:var(--primary); margin-bottom:12px; display:flex; align-items:center; gap:8px; }
        .compliance-title i { color:var(--accent); }
        .compliance-item { display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px dashed var(--border); font-size:0.85rem; gap:10px; }
        .compliance-item:last-child { border-bottom: none; }
        .pass-tag { color:var(--success); font-weight:700; display:flex; align-items:center; gap:5px; white-space:nowrap; }
        .fail-tag { color:var(--danger);  font-weight:700; display:flex; align-items:center; gap:5px; white-space:nowrap; }

        /* Modal grid */
        .modal-grid { display:grid; grid-template-columns:1.2fr 1fr; gap:28px; }
        @media(max-width:760px) { .modal-grid { grid-template-columns:1fr; } }

        .section-title { font-size:0.78rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:var(--gray); margin-bottom:14px; display:flex; align-items:center; gap:7px; }
        .section-title i { color:var(--accent); }

        .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px 16px; margin-bottom:20px; }
        .info-item label { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:var(--gray); display:block; margin-bottom:2px; }
        .info-item span  { font-size:0.88rem; font-weight:600; color:var(--primary); }
        .info-item.wide  { grid-column: 1 / -1; }

        .doc-preview-title { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:var(--gray); margin-bottom:8px; }
        .doc-preview-box {
            width:100%; height:180px;
            border:2px dashed var(--border); border-radius:10px;
            display:flex; align-items:center; justify-content:center;
            overflow:hidden; background:#f8fafc; margin-bottom:14px;
        }
        .doc-preview-box img { max-width:100%; max-height:100%; object-fit:contain; }

        .timeline { border-left:2px solid var(--border); padding-left:18px; margin-top:10px; }
        .timeline-event { position:relative; padding-bottom:18px; }
        .timeline-event::before { content:''; position:absolute; left:-25px; top:5px; width:12px; height:12px; border-radius:50%; background:var(--accent); border:2px solid #fff; box-shadow:0 0 0 2px var(--border); }
        .timeline-time  { font-size:0.72rem; color:var(--gray); margin-bottom:3px; }
        .timeline-title { font-size:0.85rem; font-weight:700; color:var(--primary); }
        .timeline-by    { font-size:0.75rem; color:#64748b; margin-top:2px; }
        .timeline-note  { font-size:0.82rem; color:#475569; margin-top:4px; font-style:italic; }

        /* Workflow control */
        .workflow-control {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .workflow-control h3 { font-size: 0.88rem; font-weight: 700; color: var(--primary); margin-bottom: 12px; }
        .workflow-instructions { font-size: 0.82rem; color: #475569; margin-bottom: 14px; line-height: 1.5; }
        .workflow-comment {
            width: 100%; height: 80px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            padding: 10px;
            font-size: 0.85rem;
            color: var(--primary);
            resize: none;
            outline: none;
            margin-bottom: 14px;
            transition: border-color 0.2s;
        }
        .workflow-comment:focus { border-color: var(--accent); }
        .workflow-buttons { display: flex; gap: 10px; }
        .workflow-buttons button { flex: 1; justify-content: center; }

        /* Footer */
        .page-footer { text-align:center; padding:24px; font-size:0.78rem; color:var(--gray); }
    </style>
    <link rel="stylesheet" href="../assets/css/seniorlink-ui.css?v=17">
    <link rel="stylesheet" href="../assets/css/metric-cards.css?v=1">
    <script src="../assets/js/modal-hci.js?v=2" defer></script>
    <link rel="stylesheet" href="../assets/css/table-pagination.css?v=1">
    <script src="../assets/js/table-pagination.js?v=1" defer></script>
    <link rel="stylesheet" href="../assets/css/system-header.css?v=1">
    <link rel="stylesheet" href="../assets/css/system-sidebar.css?v=3">
    <link rel="stylesheet" href="../assets/css/applicant-modal.css?v=1">
</head>
<body>
<div class="container">
    <?php include '../partials/department_sidebar.php'; ?>

    <div class="main-content">

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-left">
                <div class="greeting" id="greetingMsg">Welcome back!</div>
                <h1>Document <span>Verification Terminal</span></h1>
            </div>
            <div class="header-user">
                <?php
                    $profilePic = isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : 'default.jpg';
                    $profilePicPath = '../images/profile_pictures/' . $profilePic;
                    if (!file_exists($profilePicPath) || is_dir($profilePicPath)) {
                        $profilePicPath = '../images/profile_pictures/default.jpg';
                    }
                ?>
                <img src="<?php echo $profilePicPath; ?>" alt="Profile">
                <div class="header-user-info">
                    <h3><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></h3>
                    <p><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $_SESSION['role']))); ?> · Pasig City</p>
                </div>
            </div>
        </div>

        <!-- Stats Strip -->
        <div class="stats-strip">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-list-check"></i></div>
                <div class="stat-copy">
                    <div class="stat-label">Active Queue</div>
                    <div class="stat-value"><?php echo $totalQCount; ?></div>
                    <div class="stat-meta">Applications requiring action</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-search"></i></div>
                <div class="stat-copy">
                    <div class="stat-label">For evaluation</div>
                    <div class="stat-value"><?php echo $forReviewC; ?></div>
                    <div class="stat-meta">Ready for department review</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-file-signature"></i></div>
                <div class="stat-copy">
                    <div class="stat-label">Verified (Sign-off)</div>
                    <div class="stat-value"><?php echo $verifiedC; ?></div>
                    <div class="stat-meta">Completed verification checks</div>
                </div>
            </div>
        </div>

        <!-- Review Queue Card -->
        <div class="queue-card">
            <div class="queue-card-header">
                <h2><i class="fas fa-list-ol"></i> Evaluation Review Queue</h2>
                <button type="button" class="btn btn-small queue-export-btn" onclick="openExportModal()">
                    <i class="fas fa-file-excel"></i> Generate Report
                </button>
            </div>

            <form class="queue-filters" id="queueFilterForm" method="GET" action="verify_document.php" aria-label="Filter verification queue">
                <div class="queue-filter-grid">
                    <div class="queue-filter-field">
                        <label for="queueSearch">Search applicant or ID</label>
                        <div class="queue-search-wrap">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <input type="search" id="queueSearch" name="search" value="<?php echo htmlspecialchars($queueFilters['search']); ?>" placeholder="Name or application ID">
                        </div>
                    </div>
                    <div class="queue-filter-field">
                        <label for="queueType">Application Type</label>
                        <select id="queueType" name="type">
                            <option value="all">All Types</option>
                            <?php foreach ($applicationTypeOptions as $value => $label): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $queueFilters['type'] === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars(applicationTypeLabel($value)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="queue-filter-field">
                        <label for="queueBarangay">Barangay</label>
                        <select id="queueBarangay" name="barangay">
                            <option value="all">All Barangays</option>
                            <?php foreach ($barangays_list as $barangay): ?>
                                <option value="<?php echo htmlspecialchars($barangay); ?>" <?php echo $queueFilters['barangay'] === $barangay ? 'selected' : ''; ?>><?php echo htmlspecialchars($barangay); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="queue-filter-actions">
                    <div class="queue-result-count" id="queueResultCount" aria-live="polite">
                        Showing <strong><?php echo number_format($filteredQueueCount); ?></strong> of <strong><?php echo number_format((int)$totalQCount); ?></strong> active applications
                    </div>
                    <div class="queue-filter-buttons">
                        <a class="btn btn-filter-clear" id="clearQueueFilters" href="verify_document.php" <?php echo $hasQueueFilters ? '' : 'hidden'; ?>><i class="fas fa-rotate-left"></i> Clear Filters</a>
                    </div>
                </div>
            </form>

            <!-- Table -->
            <div class="table-wrap">
                <table class="records-tbl">
                    <thead>
                        <tr>
                            <th>Applicant Name</th>
                            <th>Application Type</th>
                            <th>Barangay</th>
                            <th>Date Submitted</th>
                            <th>Workflow State</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (count($result) === 0): ?>
                            <tr><td colspan="6">
                                <div class="empty-state">
                                    <i class="fas fa-<?php echo $hasQueueFilters ? 'filter-circle-xmark' : 'folder-open'; ?>"></i>
                                    <p><?php echo $hasQueueFilters ? 'No applications match the selected filters' : 'No active applications in the review queue'; ?></p>
                                    <?php if ($hasQueueFilters): ?><a class="btn btn-filter-clear" href="verify_document.php">Clear all filters</a><?php endif; ?>
                                </div>
                            </td></tr>
                        <?php else:
                            foreach ($result as $row):
                                $requiresHomeVisit = ($row['application_type'] === 'pension'
                                    || ($row['requested_benefit'] ?? '') === 'Local Social Pension Assessment')
                                    && ($row['home_visit_status'] ?? '') !== 'Completed';
                                $state = $row['effective_state'];
                                $displayState = $requiresHomeVisit && $state !== 'Needs Correction' ? 'Pending' : $state;
                                $stateBadgeClass = 'badge-received';
                                if ($state === 'For Review') $stateBadgeClass = 'badge-review';
                                if ($state === 'Verified') $stateBadgeClass = 'badge-verified';
                                if ($state === 'Approved') $stateBadgeClass = 'badge-approved';
                                if ($state === 'Released') $stateBadgeClass = 'badge-released';

                                $typeLabel = applicationTypeLabel($row['application_type']);
                            ?>
                            <tr class="applicant-row" data-id="<?php echo htmlspecialchars($row['id']); ?>" tabindex="0" role="button" aria-label="Open applicant review">
                                <td>
                                    <div class="name-cell">
                                        <div class="full-name"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                        <div class="app-id"><?php echo htmlspecialchars($row['id']); ?></div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($typeLabel); ?></td>
                                <td><?php echo htmlspecialchars($row['barangay']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($row['date_submitted'])); ?></td>
                                <td><span class="badge <?php echo $stateBadgeClass; ?>"><?php echo htmlspecialchars($displayState); ?></span></td>
                                <td>
                                    <button class="btn-view view-details-btn" data-id="<?php echo htmlspecialchars($row['id']); ?>">
                                        <i class="fas fa-folder-open"></i> Open Review
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach;
                        endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($queueTotalPages > 1):
                $queueQuery = $_GET;
            ?>
            <nav class="server-pagination" aria-label="Verification queue pages" style="display:flex;align-items:center;justify-content:center;gap:12px;padding:16px;">
                <?php $queueQuery['page'] = max(1, $queuePage - 1); ?>
                <a class="btn btn-filter-clear" <?php echo $queuePage > 1 ? 'href="?' . htmlspecialchars(http_build_query($queueQuery)) . '"' : 'aria-disabled="true"'; ?>>Previous</a>
                <span>Page <?php echo $queuePage; ?> of <?php echo $queueTotalPages; ?></span>
                <?php $queueQuery['page'] = min($queueTotalPages, $queuePage + 1); ?>
                <a class="btn btn-filter-clear" <?php echo $queuePage < $queueTotalPages ? 'href="?' . htmlspecialchars(http_build_query($queueQuery)) . '"' : 'aria-disabled="true"'; ?>>Next</a>
            </nav>
            <?php endif; ?>
        </div>

        <div class="page-footer">Centralized Profiling and Record Authentication System &bull; Pasig City Department &copy; <?php echo date('Y'); ?></div>
    </div>
</div>

<!-- ──────────────────────────────────────────────────────────────────── -->
<!-- Application Detail Modal                                             -->
<!-- ──────────────────────────────────────────────────────────────────── -->
<div id="applicationModal" class="modal-overlay applicant-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="modalAppTitle">
    <div class="modal-box applicant-modal-dialog" role="document">
        <div class="modal-head">
            <div class="applicant-modal-heading"><span class="applicant-modal-eyebrow">Verification workspace</span><h2><i class="fas fa-file-shield"></i> <span id="modalAppTitle">Review Application Profile</span></h2><p>Validate identity, requirements, compliance checks, and the next workflow action.</p></div>
            <button type="button" class="modal-close" id="closeModalBtn" aria-label="Close application details"><i class="fas fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="modal-scroller applicant-modal-body">

            <!-- Workflow progress stepper -->
            <div class="stepper">
                <div class="step" id="step-Received">
                    <div class="step-circle">1</div>
                    <div class="step-label">Received</div>
                </div>
                <div class="step" id="step-For-Review">
                    <div class="step-circle">2</div>
                    <div class="step-label">For Review</div>
                </div>
                <div class="step" id="step-Verified">
                    <div class="step-circle">3</div>
                    <div class="step-label">Verified</div>
                </div>
            </div>

            <!-- Manual review checklist -->
            <div class="compliance-card">
                <div class="compliance-title"><i class="fas fa-clipboard-check"></i> Compliance & Verification Checklist</div>
                <div id="complianceList">
                    <p style="color:var(--gray);font-size:0.85rem;">Loading compliance checks…</p>
                </div>
            </div>

            <!-- Two-column grid -->
            <div class="modal-grid applicant-modal-layout">
                <!-- LEFT: Details -->
                <div>
                    <div class="section-title"><i class="fas fa-user"></i> Applicant Information</div>
                    <div class="info-grid">
                        <div class="info-item wide">
                            <label>Full Name</label>
                            <span id="infoName">—</span>
                        </div>
                        <div class="info-item">
                            <label>Date of Birth / Age</label>
                            <span id="infoBirth">—</span>
                        </div>
                        <div class="info-item">
                            <label>Contact Number</label>
                            <span id="infoContact">—</span>
                        </div>
                        <div class="info-item">
                            <label>Barangay</label>
                            <span id="infoBarangay">—</span>
                        </div>
                        <div class="info-item wide">
                            <label>Complete Address</label>
                            <span id="infoAddress">—</span>
                        </div>
                        <div class="info-item wide">
                            <label>Additional Notes</label>
                            <span id="infoNotes">—</span>
                        </div>
                    </div>

                    <!-- Dynamic (type-specific) section -->
                    <div id="dynamicDetailsSection"></div>

                    <!-- Document previews (all docs, dynamic) -->
                    <div id="allDocumentsSection">
                        <span style="color:var(--gray);font-size:0.8rem;">Loading documents…</span>
                    </div>
                </div>

                <!-- RIGHT: Workflow actions + Audit timeline -->
                <div>
                    <!-- Rule-based workflow actions -->
                    <div class="workflow-control">
                        <h3><i class="fas fa-sliders-h" style="color:var(--accent); margin-right:5px;"></i> Workflow Control Actions</h3>
                        <div class="workflow-instructions" id="statusInstructions">Loading instructions…</div>
                        <label for="correctionDocuments">Documents or fields needing correction (required when returning)</label>
                        <textarea id="correctionDocuments" class="workflow-comment" maxlength="180" placeholder="Example: PSA birth certificate; contact number"></textarea>
                        <label for="statusComment">Reason / instructions</label>
                        <textarea id="statusComment" maxlength="280" class="workflow-comment" placeholder="Write status update details or reason for return/blurry scan here…"></textarea>
                        <div class="workflow-buttons">
                            <button type="button" class="btn btn-primary" id="btnOfficialForm" disabled><i class="fas fa-file-pdf"></i> Generate Official Form</button>
                            <button type="button" class="btn btn-primary" id="btnAdvanceStatus" onclick="submitStatusAction('next')">Advance Status</button>
                            <button type="button" class="btn btn-secondary" id="btnReturnStatus" onclick="submitStatusAction('return')">Request Correction</button>
                            <button type="button" class="btn btn-danger" id="btnRejectStatus" onclick="submitStatusAction('reject')"><i class="fas fa-ban"></i> Reject &amp; Archive</button>
                        </div>
                    </div>

                    <div class="section-title"><i class="fas fa-clock-rotate-left"></i> Audit History</div>
                    <div class="timeline" id="timelineList">
                        <p style="color:var(--gray);font-size:0.85rem;">Loading history…</p>
                    </div>
                </div>
            </div>

        </div><!-- /.modal-scroller -->
    </div><!-- /.modal-box -->
</div><!-- /#applicationModal -->

<!-- Export Excel filter modal -->
<div id="exportModal" class="modal-overlay" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="exportModalTitle">
    <div class="modal-box export-modal-box">
        <div class="modal-head">
            <h2 id="exportModalTitle"><i class="fas fa-file-export"></i> Generate Verification Report</h2>
            <button type="button" class="modal-close" id="closeExportModalBtn" aria-label="Close export dialog">&times;</button>
        </div>
        <form id="exportReportForm" method="GET" action="../api/export_records_excel.php">
            <div class="export-modal-body">
                <p>Choose a file format and set the verification filters. Only matching applications will be included.</p>
                <input type="hidden" name="scope" value="department">
                <input type="hidden" name="report_mode" value="verification">
                <input type="hidden" name="barangay" id="exportBarangayValue" value="all">
                <div class="export-filter-grid">
                    <div class="export-field">
                        <label for="exportFormat">File Format</label>
                        <select name="format" id="exportFormat" required>
                            <option value="">Choose a format</option>
                            <option value="pdf">PDF</option>
                            <option value="excel">Excel (.xlsx)</option>
                        </select>
                    </div>
                    <div class="export-field">
                        <label for="exportType">Application Type</label>
                        <select name="type" id="exportType">
                            <option value="all">All Types</option>
                            <?php foreach (getApplicationTypeOptions() as $val => $label): ?>
                                <option value="<?php echo htmlspecialchars($val); ?>"><?php echo htmlspecialchars(applicationTypeLabel($val)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="export-field">
                        <label for="exportYear">Year (if no date range)</label>
                        <select name="year" id="exportYear">
                            <option value="all">All Years</option>
                            <?php
                                $currentYear = (int)date('Y');
                                for ($y = $currentYear; $y >= 2020; $y--) {
                                    echo '<option value="' . $y . '">' . $y . '</option>';
                                }
                            ?>
                        </select>
                    </div>
                    <div class="export-field">
                        <label for="exportDateFrom">Date From</label>
                        <input type="date" name="date_from" id="exportDateFrom" required>
                    </div>
                    <div class="export-field">
                        <label for="exportDateTo">Date To</label>
                        <input type="date" name="date_to" id="exportDateTo" required>
                    </div>
                </div>
                <div class="export-divider">
                    <div class="export-divider-title"><i class="fas fa-map-marker-alt"></i> Barangay Coverage</div>
                    <label class="export-option">
                        <input type="radio" name="barangayChoice" value="all" checked>
                        <span><strong>All Barangays</strong><small>Include all pending verification applications across Pasig City.</small></span>
                    </label>
                    <label class="export-option">
                        <input type="radio" name="barangayChoice" value="selected" id="selectedBarangayRadio">
                        <span><strong>One Barangay</strong><small>Export queue records from one barangay only.</small></span>
                    </label>
                    <div class="export-select-wrap" id="exportBarangayWrap" style="display:none;">
                        <label for="exportBarangay">Select Barangay</label>
                        <select id="exportBarangay">
                            <?php foreach ($barangays_list as $b): ?>
                                <option value="<?php echo htmlspecialchars($b); ?>"><?php echo htmlspecialchars($b); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="export-actions">
                    <button type="button" class="btn btn-ghost" id="cancelExportBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-download"></i> Generate Report</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/sidebar-toggle.js?v=3"></script>
<script src="../assets/js/application-documents.js?v=9"></script>
<script src="../assets/js/application-details.js?v=13"></script>
<script src="../assets/js/application-modal-data.js?v=1"></script>
<script src="../assets/js/seniorlink-feedback.js?v=1"></script>
<script src="../assets/js/application-form-generator.js?v=2"></script>
<script src="../assets/js/report-validation.js?v=2"></script>
<script>
    /* ─── Greeting ──────────────────────────────────────────── */
    (function(){
        const h = new Date().getHours();
        const g = h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
        document.getElementById('greetingMsg').innerHTML = `${g}, <strong><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></strong>!`;
    })();

    /* ─── Modal open/close ──────────────────────────────────── */
    let currentAppId = null;
    let currentWorkflowStatus = 'Received';

    document.addEventListener('DOMContentLoaded', function() {
        const requestedApplication = new URLSearchParams(window.location.search).get('application');
        if (requestedApplication) openApplicationModal(requestedApplication);
        const queueFilterForm = document.getElementById('queueFilterForm');
        const queueSearch = document.getElementById('queueSearch');
        const clearQueueFilters = document.getElementById('clearQueueFilters');
        let filterTimer = null;
        let filterRequest = null;

        function hasActiveQueueFilters() {
            return queueSearch.value.trim() !== ''
                || document.getElementById('queueType').value !== 'all'
                || document.getElementById('queueBarangay').value !== 'all';
        }

        async function refreshVerificationQueue() {
            if (filterRequest) filterRequest.abort();
            const requestController = new AbortController();
            filterRequest = requestController;

            const params = new URLSearchParams(new FormData(queueFilterForm));
            params.set('search', queueSearch.value.trim());
            for (const [key, value] of [...params.entries()]) {
                if (value === '' || value === 'all') params.delete(key);
            }

            const url = new URL(queueFilterForm.action, window.location.href);
            url.search = params.toString();
            const tableWrap = document.querySelector('.queue-card > .table-wrap');
            tableWrap.classList.add('is-filtering');
            tableWrap.setAttribute('aria-busy', 'true');

            try {
                const response = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: requestController.signal
                });
                if (!response.ok) throw new Error('Unable to load filtered records.');

                const html = await response.text();
                const nextDocument = new DOMParser().parseFromString(html, 'text/html');
                const nextTableBody = nextDocument.querySelector('.records-tbl tbody');
                const nextResultCount = nextDocument.getElementById('queueResultCount');
                if (!nextTableBody || !nextResultCount) throw new Error('Invalid filter response.');

                document.querySelector('.records-tbl tbody').innerHTML = nextTableBody.innerHTML;
                document.getElementById('queueResultCount').innerHTML = nextResultCount.innerHTML;
                clearQueueFilters.hidden = !hasActiveQueueFilters();
                window.history.replaceState({}, '', url);
            } catch (error) {
                if (error.name !== 'AbortError') window.location.assign(url);
            } finally {
                if (!requestController.signal.aborted) {
                    tableWrap.classList.remove('is-filtering');
                    tableWrap.removeAttribute('aria-busy');
                }
            }
        }

        queueFilterForm.addEventListener('submit', function(event) {
            event.preventDefault();
            refreshVerificationQueue();
        });
        queueSearch.addEventListener('input', function() {
            window.clearTimeout(filterTimer);
            filterTimer = window.setTimeout(refreshVerificationQueue, 350);
        });
        ['queueType', 'queueBarangay'].forEach(id => {
            document.getElementById(id).addEventListener('change', refreshVerificationQueue);
        });
        clearQueueFilters.addEventListener('click', function(event) {
            event.preventDefault();
            queueFilterForm.reset();
            refreshVerificationQueue();
        });

        const recordsTable = document.querySelector('.records-tbl');
        recordsTable.addEventListener('click', function(e) {
            const btn = e.target.closest('.view-details-btn');
            if (btn) {
                openApplicationModal(btn.dataset.id);
                return;
            }
            const row = e.target.closest('.applicant-row[data-id]');
            if (row && !e.target.closest('a, button, input, select, textarea')) {
                openApplicationModal(row.dataset.id);
            }
        });
        recordsTable.addEventListener('keydown', function(e) {
            const row = e.target.closest('.applicant-row[data-id]');
            if (row && e.target === row && (e.key === 'Enter' || e.key === ' ')) {
                e.preventDefault();
                openApplicationModal(row.dataset.id);
            }
        });

        document.getElementById('closeModalBtn').addEventListener('click', closeModal);
        document.getElementById('applicationModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
        document.getElementById('closeExportModalBtn').addEventListener('click', closeExportModal);
        document.getElementById('cancelExportBtn').addEventListener('click', closeExportModal);
        document.getElementById('exportModal').addEventListener('click', function(e) {
            if (e.target === this) closeExportModal();
        });
        document.querySelectorAll('input[name="barangayChoice"]').forEach(input => input.addEventListener('change', toggleExportBarangay));
        document.getElementById('exportReportForm').addEventListener('submit', function() {
            const selected = document.querySelector('input[name="barangayChoice"]:checked').value;
            document.getElementById('exportBarangayValue').value = selected === 'selected'
                ? document.getElementById('exportBarangay').value
                : 'all';
        });
    });

    function closeModal() {
        document.getElementById('applicationModal').style.display = 'none';
    }

    function openExportModal() {
        document.getElementById('exportType').value = 'all';
        document.getElementById('exportDateFrom').value = '';
        document.getElementById('exportDateTo').value = '';
        document.getElementById('exportYear').value = 'all';
        document.querySelector('input[name="barangayChoice"][value="all"]').checked = true;
        toggleExportBarangay();
        const modal = document.getElementById('exportModal');
        modal._returnFocus = document.activeElement;
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        modal.querySelector('.modal-close')?.focus();
    }

    function closeExportModal() {
        const modal = document.getElementById('exportModal');
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        modal._returnFocus?.focus();
    }

    function toggleExportBarangay() {
        const oneBarangay = document.getElementById('selectedBarangayRadio').checked;
        document.getElementById('exportBarangayWrap').style.display = oneBarangay ? 'block' : 'none';
    }

    /* ─── Helpers ───────────────────────────────────────────── */

    /* ─── Open modal and populate ───────────────────────────── */
    function openApplicationModal(appId) {
        document.getElementById('btnOfficialForm').disabled = true;
        currentAppId = appId;

        // Reset placeholders
        document.getElementById('modalAppTitle').textContent  = 'Loading…';
        document.getElementById('statusComment').value = "";
        document.getElementById('correctionDocuments').value = "";
        document.getElementById('complianceList').innerHTML   = '<p style="color:var(--gray);font-size:0.85rem;">Loading compliance checks…</p>';
        document.getElementById('dynamicDetailsSection').innerHTML = '';
        document.getElementById('allDocumentsSection').innerHTML   = '<span style="color:var(--gray);font-size:0.8rem;">Loading documents…</span>';
        document.getElementById('timelineList').innerHTML     = '<p style="color:var(--gray);font-size:0.85rem;">Loading history…</p>';

        // Reset stepper
        ['step-Received','step-For-Review','step-Verified'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.className = 'step';
        });

        document.getElementById('applicationModal').style.display = 'flex';
        document.querySelector('#applicationModal .modal-scroller').scrollTop = 0;

        window.loadApplicationModalData(appId)
            .then(app => {
                if (!app) return;
                currentWorkflowStatus = app.workflow_state || 'Received';
                window.currentVerificationApplicationType = app.application_type || '';
                document.getElementById('modalAppTitle').textContent = `Verifying: ${app.full_name} - ${getOfficialApplicationFormLabel(app.application_type)}`;
                const officialFormButton = document.getElementById('btnOfficialForm');
                officialFormButton.disabled = false;
                officialFormButton.onclick = () => openOfficialApplicationForm(app.id_number);

                /* ── Stepper ── */
                const steps = ['Received','For Review','Verified'];
                let idx = steps.indexOf(['Approved','Released'].includes(currentWorkflowStatus) ? 'Verified' : currentWorkflowStatus);
                if (idx === -1) idx = 0;
                steps.forEach((s, i) => {
                    const el = document.getElementById('step-' + s.replace(' ','-'));
                    if (!el) return;
                    if (i < idx) el.classList.add('completed');
                    else if (i === idx) el.classList.add('active');
                });

                /* ── Basic Info ── */
                document.getElementById('infoName').textContent = app.full_name || [app.firstName, app.middleName, app.lastName, app.suffix].filter(Boolean).join(' ');
                const birth = new Date(app.birth_date);
                const today = new Date();
                let age = today.getFullYear() - birth.getFullYear();
                if (today.getMonth() < birth.getMonth() || (today.getMonth()===birth.getMonth() && today.getDate()<birth.getDate())) age--;
                document.getElementById('infoBirth').textContent    = `${app.birth_date} (${age} years old)`;
                document.getElementById('infoContact').textContent  = app.contact_number || '—';
                document.getElementById('infoAddress').textContent  = app.complete_address || '—';
                document.getElementById('infoBarangay').textContent = app.barangay || '—';
                document.getElementById('infoNotes').textContent    = app.additional_notes || '—';

                /* ── Compliance Checks ── */
                let ch = '';
                let approvalBlocked = false;
                let approvalBlockReason = "";

                if (app.application_type !== 'pwd') {
                    if (age >= 60) {
                        ch += `<div class="compliance-item"><span>Age Compliance (Senior citizen check)</span><span class="pass-tag"><i class="fas fa-circle-check"></i> PASS: Age ${age} >= 60</span></div>`;
                    } else {
                        ch += `<div class="compliance-item"><span>Age Compliance (Senior citizen check)</span><span class="fail-tag"><i class="fas fa-circle-xmark"></i> FAIL: Age ${age} is under 60</span></div>`;
                    }
                } else {
                    ch += `<div class="compliance-item"><span>Age Compliance (Disability support check)</span><span class="pass-tag" style="color:#3498db;"><i class="fas fa-info-circle"></i> No senior restriction</span></div>`;
                }

                if (app.application_type === 'pension') {
                    const pa = parseFloat(app.pension_amount || 0);
                    if (pa <= 4000) {
                        ch += `<div class="compliance-item"><span>SSS Pension Limit Check (Pasig Ord. 17/2025)</span><span class="pass-tag"><i class="fas fa-circle-check"></i> PASS: Pension ₱${pa.toFixed(2)} ≤ ₱4,000</span></div>`;
                    } else {
                        approvalBlocked = true;
                        approvalBlockReason = `APPLICATION BLOCKED: SSS pension of ₱${pa.toFixed(2)} exceeds the ₱4,000 limit (Pasig Ord. 17/2025).`;
                        ch += `<div class="compliance-item"><span>SSS Pension Limit Check (Pasig Ord. 17/2025)</span><span class="fail-tag"><i class="fas fa-circle-xmark"></i> FAIL: Pension ₱${pa.toFixed(2)} exceeds ₱4,000</span></div>`;
                    }
                }

                if (app.application_type === 'burial') {
                    const ed = app.burial_filing_days;
                    if (ed === null || ed === undefined) {
                        approvalBlocked = true;
                        approvalBlockReason = 'Correct the death date: it must be valid and on or before the original submission date.';
                        ch += '<div class="compliance-item"><span>Filing deadline</span><span class="fail-tag">Missing or invalid filing dates</span></div>';
                    } else if (ed <= 30) {
                        ch += `<div class="compliance-item"><span>Filing Deadline Check (Pasig Ord. 3/2026)</span><span class="pass-tag"><i class="fas fa-circle-check"></i> PASS: Filed in ${ed} working days</span></div>`;
                    } else {
                        approvalBlocked = true;
                        approvalBlockReason = `POLICY VIOLATION: Submitted beyond the 30-working-day limit. Days elapsed: ${ed} (Pasig Ord. 3/2026).`;
                        ch += `<div class="compliance-item"><span>Filing Deadline Check (Pasig Ord. 3/2026)</span><span class="fail-tag"><i class="fas fa-circle-xmark"></i> FAIL: Outside 30-working-day limit (${ed} days elapsed)</span></div>`;
                    }
                }

                document.getElementById('complianceList').innerHTML = ch;
                /* ── Dynamic Details (Complete details rendering) ── */
                document.getElementById('dynamicDetailsSection').innerHTML = window.renderApplicationVerificationDetails(app);

                /* ── All Documents ── */
                document.getElementById('allDocumentsSection').innerHTML = buildAllDocumentsHtml(app, appId);

                /* Rule-based workflow operations */
                const btnNext = document.getElementById('btnAdvanceStatus');
                const btnReturn = document.getElementById('btnReturnStatus');
                const btnReject = document.getElementById('btnRejectStatus');
                const instruction = document.getElementById('statusInstructions');

                btnNext.disabled = false;
                btnNext.style.opacity = '';
                btnReturn.style.display = 'block';
                btnNext.style.display = 'block';
                btnReject.style.display = 'block';

                const existingBlockBanner = document.getElementById('policyBlockBanner');
                if (existingBlockBanner) existingBlockBanner.remove();

                if (currentWorkflowStatus === 'Needs Correction') {
                    instruction.textContent = 'Waiting for barangay staff to correct the listed items and resubmit.';
                    btnNext.style.display = 'none';
                    btnReturn.style.display = 'none';
                } else if (['Received', 'Submitted'].includes(currentWorkflowStatus)) {
                    instruction.innerHTML = "<strong>Status Action:</strong> Forward this application to the department desk for detailed evaluation.";
                    btnNext.textContent = "Forward to Review Desk";
                } else if (currentWorkflowStatus === 'For Review') {
                    instruction.innerHTML = "<strong>Status Action:</strong> Mark document audits as Verified and lock the compliance records.";
                    btnNext.textContent = "Verify Application";
                    if (approvalBlocked) {
                        btnNext.disabled = true;
                        btnNext.style.opacity = '0.45';
                        const blockBanner = document.createElement('div');
                        blockBanner.id = 'policyBlockBanner';
                        blockBanner.className = 'alert alert-danger';
                        blockBanner.textContent = approvalBlockReason;
                        btnNext.parentElement.insertBefore(blockBanner, btnNext);
                    } else {
                        btnNext.disabled = false;
                        btnNext.style.opacity = '';
                    }
                } else if (currentWorkflowStatus === 'Verified') {
                    instruction.innerHTML = "<strong>Status:</strong> Verification is complete and the compliance record is locked.";
                    btnNext.style.display = 'none';
                    btnReturn.style.display = 'none';
                    btnReject.style.display = 'none';
                } else if (['Approved', 'Released'].includes(currentWorkflowStatus)) {
                    instruction.innerHTML = "<strong>Status:</strong> This legacy application is complete and is now represented as Verified.";
                    btnNext.style.display = 'none';
                    btnReturn.style.display = 'none';
                    btnReject.style.display = 'none';
                }

                /* ── Paginated Timeline ── */
                window.renderApplicationAuditHistory(app.history, 'timelineList', { pageSize: 5 });
            })
            .catch(error => window.showApplicationModalError('applicationModal', error));
    }

    async function submitStatusAction(action, confirmed = false) {
        const comment = document.getElementById('statusComment').value.trim();

        if (['return', 'reject'].includes(action) && !comment) {
            showCarelinkResult(action === 'reject'
                ? "Please enter the reason for rejecting this application."
                : "Please input comment remarks explaining why this application is being returned to the Barangay (e.g. Blurry Documents / Missing IDs).", false);
            return;
        }

        const correctionDocuments = document.getElementById('correctionDocuments').value.trim();
        if (action === 'return' && !correctionDocuments) {
            showCarelinkResult('List the documents or fields that need correction.', false);
            document.getElementById('correctionDocuments').focus();
            return;
        }
        if (!confirmed) {
            const confirmation = action === 'reject'
                ? 'Reject this application and move it to the Archive?'
                : 'Confirm this status action?';
            window.showCarelinkConfirm(confirmation, () => submitStatusAction(action, true));
            return;
        }

        try {
            const formData = new FormData();
            formData.append('applicationId', currentAppId);
            formData.append('action', action);
            formData.append('comments', comment);
            formData.append('correctionDocuments', correctionDocuments);
            const response = await fetch('../api/update_workflow_status.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                showCarelinkResult(result.message, true);
                closeModal();
                setTimeout(() => location.reload(), 900);
            } else {
                showCarelinkResult("Status update error: " + result.message, false);
            }
        } catch (err) {
            console.error(err);
            showCarelinkResult("Connection error while updating the application status.", false);
        }
    }


    /* ─── Build Complete Documents Section ─────────────────── */
    function buildAllDocumentsHtml(app, appId) {
        if (typeof window.renderApplicationDocuments === 'function') {
            return window.renderApplicationDocuments(app, appId);
        }

        const noDoc = `<span style="color:var(--gray);font-size:0.8rem;"><i class="fas fa-file-slash"></i> No file uploaded</span>`;

        function blobDocBox(label, hasBool, docType) {
            const inner = hasBool
                ? `<img src="../api/get_document.php?id=${appId}&doc_type=${docType}" alt="${label}" style="max-width:100%;max-height:100%;object-fit:contain;">`
                : noDoc;
            return `
                <div style="margin-bottom:16px;">
                    <div class="doc-preview-title">${label}</div>
                    <div class="doc-preview-box">${inner}</div>
                </div>`;
        }

        function fileDocCard(label, filename, docType) {
            if (!filename) return '';
            const ext    = (filename.split('.').pop() || '').toLowerCase();
            const isPdf  = ext === 'pdf';
            const url    = `../api/get_document.php?id=${encodeURIComponent(appId)}&doc_type=${encodeURIComponent(docType)}`;
            const preview = isPdf
                ? `<i class="fas fa-file-pdf" style="font-size:2.6rem;color:var(--danger);"></i>`
                : `<img src="${url}" alt="${label}" style="max-width:100%;max-height:100%;object-fit:contain;">`;
            return `
                <div style="border:1px solid var(--border);border-radius:10px;padding:10px;background:#fff;">
                    <div class="doc-preview-title">${label}</div>
                    <div class="doc-preview-box" style="height:150px;display:flex;align-items:center;justify-content:center;">${preview}</div>
                    <a href="${url}" target="_blank" class="btn btn-primary btn-small" style="width:100%;justify-content:center;margin-top:8px;"><i class="fas fa-eye"></i> View</a>
                </div>`;
        }

        let html = `<div class="section-title" style="margin-top:20px;"><i class="fas fa-file-image"></i> Submitted Documents</div>`;

        // Always shown: Proof of Address + ID Image (BLOB)
        html += blobDocBox('Proof of Address', app.has_proof_of_address, 'proof_of_address');
        html += blobDocBox('ID / Identification Photo', app.has_id_image, 'id_image');

        const additionalDocs = [
            ['psa_birth_cert', 'PSA Birth Certificate'], ['barangay_residency', 'Barangay Residency'],
            ['comelec_cert', 'COMELEC Certificate'], ['proof_of_life', 'Current Senior Photo / Proof of Life'],
            ['auth_letter', 'Authorization Letter'], ['proxy_id', 'Representative Government ID'],
            ['proxy_birth_cert', 'Representative Birth Certificate'], ['home_visitation_form', 'Home Visitation Form'],
            ['landbank_enrollment_form', 'Land Bank Enrollment Form']
        ].filter(([key]) => app[key]);
        if (additionalDocs.length) {
            html += `<div class="section-title" style="margin-top:24px;"><i class="fas fa-user-shield"></i> Additional Submitted Documents</div><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;">`;
            html += additionalDocs.map(([key, label]) => fileDocCard(label, app[key], key)).join('');
            html += '</div>';
        }

        return html;
    }
</script>
</body>
</html>
