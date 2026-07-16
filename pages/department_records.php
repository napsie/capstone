<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/barangays_list.php';
require_once '../includes/application_types.php';

// Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'department_admin') {
    header('Location: ../index.php');
    exit;
}

// Filter params
$search          = $_GET['search'] ?? '';
$barangayFilter  = $_GET['barangay'] ?? 'all';
$typeFilter      = $_GET['type'] ?? 'all';
$page            = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage  = 15;
$offset          = ($page - 1) * $recordsPerPage;

// Build dynamic query
$baseQuery    = "FROM applications";
$whereClauses = ["(workflow_state IN ('Approved', 'Released') OR status = 'Approved')"];
$params       = [];

if (!empty($search)) {
    $whereClauses[] = "(full_name LIKE :search OR id_number LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($barangayFilter !== 'all') {
    $whereClauses[] = "barangay = :barangay";
    $params[':barangay'] = $barangayFilter;
}
if ($typeFilter !== 'all') {
    $whereClauses[] = "application_type = :type";
    $params[':type'] = $typeFilter;
}

$whereSql = " WHERE " . implode(' AND ', $whereClauses);

// Total count
$totalStmt = $conn->prepare("SELECT COUNT(*) " . $baseQuery . $whereSql);
$totalStmt->execute($params);
$totalRecords = $totalStmt->fetchColumn();
$totalPages   = ceil($totalRecords / $recordsPerPage);

// Paginated records
$recordsStmt = $conn->prepare("SELECT id_number as id, full_name, application_type, barangay, date_submitted, COALESCE(workflow_state, status) as status " . $baseQuery . $whereSql . " ORDER BY date_submitted DESC LIMIT :limit OFFSET :offset");
foreach ($params as $k => &$v) $recordsStmt->bindParam($k, $v);
$recordsStmt->bindParam(':limit',  $recordsPerPage, PDO::PARAM_INT);
$recordsStmt->bindParam(':offset', $offset,         PDO::PARAM_INT);
$recordsStmt->execute();
$applications = $recordsStmt->fetchAll(PDO::FETCH_ASSOC);

function getStatusBadge($status) {
    switch (strtolower($status)) {
        case 'released': return ['badge-released', 'fa-box-archive'];
        case 'approved': return ['badge-approved', 'fa-circle-check'];
        default:         return ['badge-default',  'fa-circle'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Records – SENIORLINK</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/department-sidebar.css?v=1.1">
    <link rel="stylesheet" href="../assets/css/application-documents.css?v=6">
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
        .page-header-left .greeting {
            font-family: 'Inter', sans-serif;
            font-size: 0.98rem;
            font-weight: 500;
            color: #6b7280;
            margin-bottom: 6px;
            line-height: 1.3;
        }
        .page-header-left .greeting strong {
            color: #2563eb;
            font-weight: 700;
        }
        .page-header-left h1 {
            font-family: 'Inter', sans-serif;
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--primary);
            margin: 0;
            line-height: 1.05;
        }
        .page-header-left h1 span { color: var(--accent); }
        .header-user {
            display: flex;
            align-items: center;
            gap: 12px;
            border-radius: 30px;
            padding: 7px 13px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 3px solid transparent;
            background: linear-gradient(var(--card), var(--card)) padding-box, linear-gradient(135deg, #0f172a 0%, #3498db 100%) border-box;
        }
        .header-user img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent); }
        .header-user-info h3 { font-size: 0.9rem; font-weight: 700; color: var(--primary); margin: 0; }
        .header-user-info p  { font-size: 0.75rem; color: var(--gray); margin: 0; }

        /* ─── Stats ──────────────────────────────────────────────────────── */
        .stats-strip { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
        .stat-card {
            background: var(--card);
            border-radius: 14px;
            padding: 18px 22px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.07); }
        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
        .stat-icon.green  { background: rgba(16,185,129,0.12); color: var(--success); }
        .stat-icon.blue   { background: rgba(37,99,235,0.12);  color: var(--accent); }
        .stat-icon.purple { background: rgba(139,92,246,0.12); color: var(--purple); }
        .stat-icon.amber  { background: rgba(245,158,11,0.12); color: var(--warning); }
        .stat-label { font-size: 0.75rem; color: var(--gray); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
        .stat-value { font-size: 1.7rem; font-weight: 800; color: var(--primary); line-height: 1; }

        /* ─── Records Card ───────────────────────────────────────────────── */
        .records-card { background: var(--card); border-radius: 16px; border: 1px solid var(--border); box-shadow: 0 4px 16px rgba(0,0,0,0.05); overflow: hidden; }
        .records-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 28px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);
        }
        .records-card-header h2 { color: #fff; font-size: 1.05rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 10px; }
        .records-card-header h2 i { color: #60a5fa; }
        .header-actions { display: flex; gap: 10px; }

        /* Buttons */
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
        .btn-ghost { background: rgba(255,255,255,0.15); color: #fff; border: 1px solid rgba(255,255,255,0.25); }
        .btn-ghost:hover { background: rgba(255,255,255,0.28); }

        /* ─── Filter Bar ─────────────────────────────────────────────────── */
        .filter-bar {
            display: flex;
            align-items: flex-end;
            gap: 16px;
            padding: 18px 28px;
            border-bottom: 1px solid var(--border);
            background: #f8fafc;
            flex-wrap: wrap;
        }
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filter-group label { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--gray); }
        .filter-group select,
        .search-wrap input {
            border: 1.5px solid var(--border);
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 0.86rem;
            color: var(--primary);
            background: #fff;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            min-width: 150px;
        }
        .filter-group select:focus, .search-wrap input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,0.12); }
        .search-wrap { position: relative; }
        .search-wrap i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--gray); font-size: 0.85rem; }
        .search-wrap input { padding-left: 32px; min-width: 230px; }
        .btn-apply {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 9px 18px;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
            align-self: flex-end;
        }
        .btn-apply:hover { background: #1d4ed8; transform: translateY(-1px); }

        /* ─── Table ──────────────────────────────────────────────────────── */
        .table-wrap { overflow-x: auto; }
        .records-tbl { width: 100%; border-collapse: collapse; }
        .records-tbl thead tr { background: #f8fafc; border-bottom: 2px solid var(--border); }
        .records-tbl th { padding: 12px 18px; text-align: left; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--gray); white-space: nowrap; }
        .records-tbl td { padding: 13px 18px; font-size: 0.86rem; color: var(--primary); border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .records-tbl tbody tr { transition: background 0.15s; }
        .records-tbl tbody tr:hover { background: #f8fafc; }
        .records-tbl tbody tr:last-child td { border-bottom: none; }

        .name-cell .full-name { font-weight: 700; }
        .name-cell .app-id    { font-size: 0.73rem; color: var(--gray); margin-top: 2px; font-family: monospace; }

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
        .badge-approved { background: rgba(16,185,129,0.12); color: #15803d; border: 1px solid rgba(16,185,129,0.25); }
        .badge-released { background: rgba(139,92,246,0.12); color: #6d28d9; border: 1px solid rgba(139,92,246,0.25); }
        .badge-default  { background: rgba(148,163,184,0.15); color: #475569; }

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

        .empty-state { text-align: center; padding: 60px 20px; color: var(--gray); }
        .empty-state i { font-size: 3rem; margin-bottom: 16px; opacity: 0.4; display: block; }

        /* ─── Pagination ─────────────────────────────────────────────────── */
        .pagination-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 24px;
            border-top: 1px solid var(--border);
            background: #f8fafc;
            flex-wrap: wrap;
            gap: 10px;
        }
        .pagination-info { font-size: 0.82rem; color: var(--gray); font-weight: 600; }
        .pagination-links { display: flex; gap: 6px; flex-wrap: wrap; }
        .pagination-links a,
        .pagination-links span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            padding: 0 10px;
            border: 1.5px solid var(--border);
            border-radius: 7px;
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            transition: all 0.2s;
        }
        .pagination-links a:hover { background: var(--primary); color: #fff; border-color: var(--primary); }
        .pagination-links .current { background: var(--accent); color: #fff; border-color: var(--accent); }
        .pagination-links .disabled { color: var(--gray); cursor: not-allowed; opacity: 0.5; }

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
        .export-modal-box { max-width: 520px; }
        .export-modal-body { padding: 26px 28px 28px; }
        .export-modal-body p { margin: 0 0 18px; color: var(--gray); font-size: .88rem; line-height: 1.55; }
        .export-option { display:flex; align-items:center; gap:10px; padding:12px 14px; border:1px solid var(--border); border-radius:9px; margin-bottom:10px; cursor:pointer; }
        .export-option:has(input:checked) { border-color:var(--accent); background:#eff6ff; }
        .export-option input { accent-color:var(--accent); }
        .export-option strong { display:block; font-size:.88rem; color:var(--primary); }
        .export-option small { color:var(--gray); font-size:.76rem; }
        .export-select-wrap { margin: 14px 0 20px; }
        .export-select-wrap label { display:block; font-size:.76rem; font-weight:700; color:var(--gray); text-transform:uppercase; margin-bottom:6px; }
        .export-select-wrap select { width:100%; padding:10px 11px; border:1px solid var(--border); border-radius:8px; color:var(--primary); background:#fff; }
        .export-actions { display:flex; justify-content:flex-end; gap:10px; }

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

        /* Footer */
        .page-footer { text-align:center; padding:24px; font-size:0.78rem; color:var(--gray); }
    </style>
</head>
<body>
<div class="container">
    <?php include '../partials/department_sidebar.php'; ?>

    <div class="main-content">

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-left">
                <div class="greeting" id="greetingMsg"></div>
                <h1>Department <span>Records</span></h1>
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
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="stat-label">Total on Page</div>
                    <div class="stat-value"><?php echo $totalRecords; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-city"></i></div>
                <div>
                    <div class="stat-label">Coverage</div>
                    <div class="stat-value" style="font-size:1rem;padding-top:4px;">Pasig City</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-filter"></i></div>
                <div>
                    <div class="stat-label">Current Page</div>
                    <div class="stat-value"><?php echo $page; ?> / <?php echo max($totalPages, 1); ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon amber"><i class="fas fa-database"></i></div>
                <div>
                    <div class="stat-label">All Records</div>
                    <div class="stat-value"><?php echo number_format($totalRecords); ?></div>
                </div>
            </div>
        </div>

        <!-- Records Card -->
        <div class="records-card">
            <div class="records-card-header">
                <h2><i class="fas fa-folder-open"></i> All Approved Records – Pasig City</h2>
                <div class="header-actions">
                    <button type="button" class="btn btn-ghost" onclick="exportDepartmentRecords()">
                        <i class="fas fa-file-pdf"></i> Export PDF
                    </button>
                </div>
            </div>

            <!-- Filter Bar -->
            <form method="GET" action="department_records.php">
                <div class="filter-bar">
                    <div class="filter-group">
                        <label for="searchInput">Search</label>
                        <div class="search-wrap">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchInput" name="search" placeholder="Name or ID…" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="filter-group">
                        <label for="barangayFilter">Barangay</label>
                        <select id="barangayFilter" name="barangay">
                            <option value="all">All Barangays</option>
                            <?php foreach ($barangays_list as $b): ?>
                                <option value="<?php echo htmlspecialchars($b); ?>" <?php echo ($barangayFilter === $b) ? 'selected' : ''; ?>><?php echo htmlspecialchars($b); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="typeFilter">Application Type</label>
                        <select id="typeFilter" name="type">
                            <option value="all">All Types</option>
                            <?php foreach (getApplicationTypeOptions() as $val => $label): ?>
                                <option value="<?php echo htmlspecialchars($val); ?>" <?php echo ($typeFilter === $val) ? 'selected' : ''; ?>><?php echo htmlspecialchars(applicationTypeLabel($val)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-apply"><i class="fas fa-search"></i> Apply Filters</button>
                    <?php if (!empty($search) || $barangayFilter !== 'all' || $typeFilter !== 'all'): ?>
                        <a href="department_records.php" class="btn-apply" style="background:#64748b;text-decoration:none;"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Table -->
            <div class="table-wrap">
                <table class="records-tbl">
                    <thead>
                        <tr>
                            <th>Applicant</th>
                            <th>Application Type</th>
                            <th>Barangay</th>
                            <th>Date Submitted</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($applications)): ?>
                        <tr><td colspan="6">
                            <div class="empty-state">
                                <i class="fas fa-folder-open"></i>
                                <p>No approved records found</p>
                                <small>Try adjusting your filters or search query.</small>
                            </div>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($applications as $app):
                            [$badgeClass, $badgeIcon] = getStatusBadge($app['status']);
                        ?>
                        <tr>
                            <td>
                                <div class="name-cell">
                                    <div class="full-name"><?php echo htmlspecialchars($app['full_name']); ?></div>
                                    <div class="app-id"><?php echo htmlspecialchars($app['id']); ?></div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars(applicationTypeLabel($app['application_type'])); ?></td>
                            <td><?php echo htmlspecialchars($app['barangay']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($app['date_submitted'])); ?></td>
                            <td>
                                <span class="badge <?php echo $badgeClass; ?>">
                                    <i class="fas <?php echo $badgeIcon; ?>"></i>
                                    <?php echo htmlspecialchars(ucfirst($app['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn-view view-details-btn" data-id="<?php echo htmlspecialchars($app['id']); ?>">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <?php
                $qs = http_build_query(array_filter(['search' => $search, 'barangay' => ($barangayFilter !== 'all' ? $barangayFilter : ''), 'type' => ($typeFilter !== 'all' ? $typeFilter : '')]));
            ?>
            <div class="pagination-bar">
                <div class="pagination-info">
                    Page <?php echo $page; ?> of <?php echo $totalPages; ?> &bull; <?php echo number_format($totalRecords); ?> total records
                </div>
                <div class="pagination-links">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&<?php echo $qs; ?>"><i class="fas fa-chevron-left"></i></a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 2);
                    $end   = min($totalPages, $page + 2);
                    if ($start > 1) echo '<span>…</span>';
                    for ($i = $start; $i <= $end; $i++) {
                        if ($i == $page) echo "<span class=\"current\">$i</span>";
                        else echo "<a href=\"?page=$i&$qs\">$i</a>";
                    }
                    if ($end < $totalPages) echo '<span>…</span>';
                    ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page+1; ?>&<?php echo $qs; ?>"><i class="fas fa-chevron-right"></i></a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="page-footer">Centralized Profiling and Record Authentication System &bull; Pasig City Department &copy; <?php echo date('Y'); ?></div>
    </div>
</div>

<!-- ──────────────────────────────────────────────────────────────────── -->
<!-- Application Detail Modal                                             -->
<!-- ──────────────────────────────────────────────────────────────────── -->
<div id="applicationModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <h2><i class="fas fa-file-shield"></i> <span id="modalAppTitle">Application Details</span></h2>
            <button class="modal-close" id="closeModalBtn">&times;</button>
        </div>
        <div class="modal-scroller">

            <!-- FSM Stepper -->
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
                <div class="step" id="step-Approved">
                    <div class="step-circle">4</div>
                    <div class="step-label">Approved</div>
                </div>
                <div class="step" id="step-Released">
                    <div class="step-circle">5</div>
                    <div class="step-label">Released</div>
                </div>
            </div>

            <!-- Compliance Engine -->
            <div class="compliance-card">
                <div class="compliance-title"><i class="fas fa-shield-halved"></i> Compliance & Verification Engine</div>
                <div id="complianceList">
                    <p style="color:var(--gray);font-size:0.85rem;">Loading compliance checks…</p>
                </div>
            </div>

            <!-- Two-column grid -->
            <div class="modal-grid">
                <!-- LEFT: Details -->
                <div>
                    <div class="section-title"><i class="fas fa-user"></i> Applicant Information</div>
                    <div class="info-grid">
                        <div class="info-item wide">
                            <label>Full Name</label>
                            <span id="infoName">—</span>
                        </div>
                        <div class="info-item">
                            <label>Date of Birth</label>
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

                <!-- RIGHT: Audit timeline -->
                <div>
                    <div style="margin-bottom:16px;">
                        <button type="button" class="btn btn-primary" id="btnOfficialForm" disabled><i class="fas fa-file-pdf"></i> Generate Official Form</button>
                        <button type="button" class="btn" id="btnReleaseApplication" style="display:none;background:#10b981;color:#fff;"><i class="fas fa-box-open"></i> Release Application</button>
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

<!-- Export scope modal -->
<div id="exportModal" class="modal-overlay">
    <div class="modal-box export-modal-box">
        <div class="modal-head">
            <h2><i class="fas fa-file-pdf"></i> Export PDF Report</h2>
            <button type="button" class="modal-close" id="closeExportModalBtn">&times;</button>
        </div>
        <form id="exportReportForm" method="GET" action="../api/export_records_pdf.php">
            <div class="export-modal-body">
                <p>Choose the barangay coverage for this report. Your current search and application-type filters will also be applied.</p>
                <input type="hidden" name="scope" value="department">
                <input type="hidden" name="search" id="exportSearch">
                <input type="hidden" name="type" id="exportType">
                <input type="hidden" name="barangay" id="exportBarangayValue" value="all">
                <label class="export-option">
                    <input type="radio" name="barangayChoice" value="all" checked>
                    <span><strong>All Barangays</strong><small>Include approved and released records across Pasig City.</small></span>
                </label>
                <label class="export-option">
                    <input type="radio" name="barangayChoice" value="selected" id="selectedBarangayRadio">
                    <span><strong>One Barangay</strong><small>Export records from one selected barangay only.</small></span>
                </label>
                <div class="export-select-wrap" id="exportBarangayWrap" style="display:none;">
                    <label for="exportBarangay">Select Barangay</label>
                    <select id="exportBarangay">
                        <?php foreach ($barangays_list as $b): ?>
                            <option value="<?php echo htmlspecialchars($b); ?>"><?php echo htmlspecialchars($b); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="export-actions">
                    <button type="button" class="btn btn-ghost" id="cancelExportBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-file-pdf"></i> Generate PDF</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/sidebar-toggle.js"></script>
<script src="../assets/js/application-documents.js?v=6"></script>
<script src="../assets/js/carelink-feedback.js?v=1"></script>
<script src="../assets/js/application-form-generator.js?v=2"></script>
<script>
    /* ─── Greeting ──────────────────────────────────────────── */
    (function(){
        const h = new Date().getHours();
        const g = h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
        const fn = <?php echo json_encode($_SESSION['first_name']); ?>;
        const ln = <?php echo json_encode($_SESSION['last_name']); ?>;
        document.getElementById('greetingMsg').innerHTML = `${g}, <strong>${fn} ${ln}</strong>!`;
    })();

    /* ─── Modal open/close ──────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelector('.table-wrap').addEventListener('click', function(e) {
            const btn = e.target.closest('.view-details-btn');
            if (btn) openApplicationModal(btn.dataset.id);
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

    function exportDepartmentRecords() {
        const currentBarangay = document.getElementById('barangayFilter').value;
        document.getElementById('exportSearch').value = document.getElementById('searchInput').value.trim();
        document.getElementById('exportType').value = document.getElementById('typeFilter').value;
        if (currentBarangay !== 'all') {
            document.getElementById('selectedBarangayRadio').checked = true;
            document.getElementById('exportBarangay').value = currentBarangay;
        } else {
            document.querySelector('input[name="barangayChoice"][value="all"]').checked = true;
        }
        toggleExportBarangay();
        document.getElementById('exportModal').style.display = 'block';
    }

    function toggleExportBarangay() {
        const oneBarangay = document.getElementById('selectedBarangayRadio').checked;
        document.getElementById('exportBarangayWrap').style.display = oneBarangay ? 'block' : 'none';
    }

    function closeExportModal() {
        document.getElementById('exportModal').style.display = 'none';
    }

    /* ─── Helpers ───────────────────────────────────────────── */
    function calculateWorkingDays(startDateVal) {
        if (!startDateVal) return 0;
        const start = new Date(startDateVal);
        const end   = new Date();
        if (start > end) return 0;
        let days = 0, cur = new Date(start);
        while (cur < end) {
            const d = cur.getDay();
            if (d !== 0 && d !== 6) days++;
            cur.setDate(cur.getDate() + 1);
        }
        return days;
    }

    /* ─── Open modal and populate ───────────────────────────── */
    function openApplicationModal(appId) {
        document.getElementById('btnOfficialForm').disabled = true;
        document.getElementById('btnReleaseApplication').style.display = 'none';
        // Reset placeholders
        document.getElementById('modalAppTitle').textContent  = 'Loading…';
        document.getElementById('complianceList').innerHTML   = '<p style="color:var(--gray);font-size:0.85rem;">Loading compliance checks…</p>';
        document.getElementById('dynamicDetailsSection').innerHTML = '';
        document.getElementById('allDocumentsSection').innerHTML   = '<span style="color:var(--gray);font-size:0.8rem;">Loading documents…</span>';
        document.getElementById('timelineList').innerHTML     = '<p style="color:var(--gray);font-size:0.85rem;">Loading history…</p>';

        // Reset stepper
        ['step-Received','step-For-Review','step-Verified','step-Approved','step-Released'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.className = 'step';
        });

        document.getElementById('applicationModal').style.display = 'block';
        document.querySelector('#applicationModal .modal-scroller').scrollTop = 0;

        fetch(`../api/get_application_details.php?id=${encodeURIComponent(appId)}`)
            .then(r => r.text())
            .then(text => {
                let app;
                try { app = JSON.parse(text); }
                catch(parseErr) {
                    document.getElementById('complianceList').innerHTML =
                        `<p style="color:red;"><strong>Server Error:</strong><br><pre style="font-size:0.75rem;overflow:auto;">${text.substring(0,600)}</pre></p>`;
                    return;
                }
                if (app.error) {
                    document.getElementById('complianceList').innerHTML = `<p style="color:red;">${app.error}</p>`;
                    return;
                }

                /* ── Title ── */
                const seniorCitizenId = app.senior_id_no || 'Not yet issued';
                document.getElementById('modalAppTitle').textContent = `${app.full_name} - Senior Citizen ID: ${seniorCitizenId} - ${getOfficialApplicationFormLabel(app.application_type)}`;
                const officialFormButton = document.getElementById('btnOfficialForm');
                officialFormButton.disabled = false;
                officialFormButton.onclick = () => openOfficialApplicationForm(app.id_number);

                const currentState = app.workflow_state || app.status || 'Received';
                const releaseButton = document.getElementById('btnReleaseApplication');
                if (currentState === 'Approved') {
                    releaseButton.style.display = 'inline-flex';
                    releaseButton.onclick = () => releaseApplication(app.id_number);
                }

                /* ── Stepper ── */
                const steps = ['Received','For Review','Verified','Approved','Released'];
                const state = app.workflow_state || 'Received';
                let idx = steps.indexOf(state);
                if (idx === -1) idx = 0;
                steps.forEach((s, i) => {
                    const el = document.getElementById('step-' + s.replace(' ','-'));
                    if (!el) return;
                    if (i < idx) el.classList.add('completed');
                    else if (i === idx) el.classList.add('active');
                });

                /* ── Basic Info ── */
                document.getElementById('infoName').innerHTML    = `<strong>${app.lastName}, ${app.firstName} ${app.middleName||''} ${app.suffix||''}</strong>`;
                const birth = new Date(app.birth_date);
                const today = new Date();
                let age = today.getFullYear() - birth.getFullYear();
                if (today.getMonth() < birth.getMonth() || (today.getMonth()===birth.getMonth() && today.getDate()<birth.getDate())) age--;
                document.getElementById('infoBirth').textContent    = `${app.birth_date} (${age} yrs)`;
                document.getElementById('infoContact').textContent  = app.contact_number || '—';
                document.getElementById('infoAddress').textContent  = app.complete_address || '—';
                document.getElementById('infoBarangay').textContent = app.barangay || '—';
                document.getElementById('infoNotes').textContent    = app.additional_notes || '—';

                /* ── Compliance ── */
                let ch = '';
                if (app.application_type !== 'pwd') {
                    ch += age >= 60
                        ? `<div class="compliance-item"><span>Age Compliance (60+ Check)</span><span class="pass-tag"><i class="fas fa-circle-check"></i> PASS — Age ${age}</span></div>`
                        : `<div class="compliance-item"><span>Age Compliance (60+ Check)</span><span class="fail-tag"><i class="fas fa-circle-xmark"></i> FAIL — Age ${age} (under 60)</span></div>`;
                } else {
                    ch += `<div class="compliance-item"><span>Age Compliance (PWD)</span><span class="pass-tag" style="color:#3498db;"><i class="fas fa-info-circle"></i> No senior restriction</span></div>`;
                }
                if (app.application_type === 'pension') {
                    const pa = parseFloat(app.pension_amount || 0);
                    ch += pa <= 4000
                        ? `<div class="compliance-item"><span>SSS Pension Limit (Pasig Ord. 17/2025)</span><span class="pass-tag"><i class="fas fa-circle-check"></i> PASS — ₱${pa.toFixed(2)} ≤ ₱4,000</span></div>`
                        : `<div class="compliance-item"><span>SSS Pension Limit (Pasig Ord. 17/2025)</span><span class="fail-tag"><i class="fas fa-circle-xmark"></i> FAIL — ₱${pa.toFixed(2)} exceeds ₱4,000</span></div>`;
                }
                if (app.application_type === 'burial') {
                    const ed = calculateWorkingDays(app.date_of_death);
                    ch += ed <= 30
                        ? `<div class="compliance-item"><span>Filing Deadline (Pasig Ord. 3/2026)</span><span class="pass-tag"><i class="fas fa-circle-check"></i> PASS — ${ed} working days</span></div>`
                        : `<div class="compliance-item"><span>Filing Deadline (Pasig Ord. 3/2026)</span><span class="fail-tag"><i class="fas fa-circle-xmark"></i> FAIL — ${ed} days elapsed</span></div>`;
                }
                if (app.priority_level === 'high') {
                    ch += `<div class="compliance-item" style="background:rgba(245,158,11,0.08);padding:5px;border-radius:4px;">
                        <span>Priority Queue</span>
                        <span style="color:#d97706;font-weight:700;"><i class="fas fa-star"></i> High-Priority (Bedridden)</span>
                    </div>`;
                }
                document.getElementById('complianceList').innerHTML = ch;
                /* ── Dynamic Details (Complete details rendering) ── */
                document.getElementById('dynamicDetailsSection').innerHTML = getCompleteDetailsHtml(app);

                /* ── All Documents ── */
                document.getElementById('allDocumentsSection').innerHTML = buildAllDocumentsHtml(app, appId);

                /* ── Timeline ── */
                let th = '';
                if (app.history && app.history.length > 0) {
                    app.history.forEach(log => {
                        const t = new Date((log.changed_at || '').replace(' ','T')).toLocaleString();
                        th += `<div class="timeline-event">
                            <div class="timeline-time">${t}</div>
                            <div class="timeline-title">${log.previous_state} &rarr; ${log.new_state}</div>
                            <div class="timeline-by">By: ${log.changed_by}</div>
                            ${log.comments ? `<div class="timeline-note">&ldquo;${log.comments}&rdquo;</div>` : ''}
                        </div>`;
                    });
                } else {
                    th = '<p style="color:var(--gray);font-size:0.85rem;">No transition history found.</p>';
                }
                document.getElementById('timelineList').innerHTML = th;
            })
            .catch(err => {
                console.error(err);
                document.getElementById('complianceList').innerHTML = '<p style="color:red;">Failed to load details. Please try again.</p>';
            });
    }

    function releaseApplication(appId) {
        if (!confirm('Release this approved application? This will mark it as released.')) return;

        const formData = new FormData();
        formData.append('applicationId', appId);
        formData.append('action', 'next');
        formData.append('comments', 'Application released by the department.');

        fetch('../api/update_fsm_state.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(result => {
                if (!result.success) {
                    showCarelinkResult('Release failed: ' + (result.message || 'Please try again.'), false);
                    return;
                }
                showCarelinkResult('Application released successfully.', true);
                document.getElementById('applicationModal').style.display = 'none';
                setTimeout(() => window.location.reload(), 900);
            })
            .catch(error => {
                console.error(error);
                showCarelinkResult('Unable to release the application. Please try again.', false);
            });
    }

    function getCompleteDetailsHtml(app) {
        function getFieldHtml(label, val) {
            if (val === null || val === undefined || val === '' || val === '0' || val === 0) return '';
            if (val === 1 || val === '1') val = 'Yes';
            return `
                <div class="info-item">
                    <label>${label}</label>
                    <span>${val}</span>
                </div>`;
        }

        let dynamicHtml = "";

        // 1. Personal & Demographics Extra Info
        let personalHtml = "";
        personalHtml += getFieldHtml("Place of Birth", app.place_of_birth);
        personalHtml += getFieldHtml("Gender", app.gender);
        personalHtml += getFieldHtml("Civil Status", app.civil_status);
        personalHtml += getFieldHtml("Mother's Maiden Name", app.mothers_maiden_name);
        personalHtml += getFieldHtml("Nationality", app.nationality);

        if (personalHtml) {
            dynamicHtml += `
                <div class="section-title" style="margin-top:20px;"><i class="fas fa-id-card-clip"></i> Personal Profile</div>
                <div class="info-grid">
                    ${personalHtml}
                </div>`;
        }

        // 2. Household & Housing
        let housingHtml = "";
        housingHtml += getFieldHtml("Complete Address", app.complete_address);
        if (app.application_type === 'senior' || app.application_type === 'landbank') {
            housingHtml += getFieldHtml("ZIP Code", app.zip_code);
        }
        if (app.application_type === 'senior') {
            housingHtml += getFieldHtml("Landmark", app.landmark);
        }
        if (app.application_type === 'pension' || app.application_type === 'national_pension') {
            housingHtml += getFieldHtml("Owns House", app.owns_house);
            housingHtml += getFieldHtml("Renter", app.is_renter);
        }

        if (housingHtml) {
            dynamicHtml += `
                <div class="section-title" style="margin-top:20px;"><i class="fas fa-house-user"></i> Address Details</div>
                <div class="info-grid">
                    ${housingHtml}
                </div>`;
        }

        // 3. Financial & Pension Info
        let financeHtml = "";
        financeHtml += getFieldHtml("SSS Number", app.sss_number);
        financeHtml += getFieldHtml("Pension Amount", app.pension_amount ? `₱${parseFloat(app.pension_amount).toFixed(2)}` : '');
        financeHtml += getFieldHtml("Is Pensioner", app.is_pensioner);
        financeHtml += getFieldHtml("Pension Source", app.pension_source);
        financeHtml += getFieldHtml("Permanent Income", app.is_permanent_income);
        financeHtml += getFieldHtml("Income Source", app.income_source);
        financeHtml += getFieldHtml("Personal Income Amount", app.personal_income_amount ? `₱${parseFloat(app.personal_income_amount).toFixed(2)}` : '');
        financeHtml += getFieldHtml("Source of Funds", app.source_of_funds);
        financeHtml += getFieldHtml("Family Support Amount", app.family_support_amount ? `₱${parseFloat(app.family_support_amount).toFixed(2)}` : '');

        if (financeHtml) {
            dynamicHtml += `
                <div class="section-title" style="margin-top:20px;"><i class="fas fa-wallet"></i> Financial Profile</div>
                <div class="info-grid">
                    ${financeHtml}
                </div>`;
        }

        // 4. OSCA Registration & Banking info
        let oscaRegHtml = "";
        oscaRegHtml += getFieldHtml("Senior ID No", app.senior_id_no);
        oscaRegHtml += getFieldHtml("ID Purpose", app.id_purpose);
        oscaRegHtml += getFieldHtml("Control No", app.control_no);
        oscaRegHtml += getFieldHtml("Landbank Card No", app.landbank_card_no);
        oscaRegHtml += getFieldHtml("ATM Card No", app.atm_card_no);
        oscaRegHtml += getFieldHtml("Name on Card", app.name_on_card);
        oscaRegHtml += getFieldHtml("TIN", app.tin);
        oscaRegHtml += getFieldHtml("ID Type Presented", app.id_type_presented);
        oscaRegHtml += getFieldHtml("Parent Senior ID", app.parent_senior_id);

        if (oscaRegHtml) {
            dynamicHtml += `
                <div class="section-title" style="margin-top:20px;"><i class="fas fa-piggy-bank"></i> Registration & Banking</div>
                <div class="info-grid">
                    ${oscaRegHtml}
                </div>`;
        }

        // 5. Health & Living Arrangement
        let healthHtml = "";
        healthHtml += getFieldHtml("Health Status", app.health_status);
        healthHtml += getFieldHtml("Health Condition", app.health_condition);
        healthHtml += getFieldHtml("Living Arrangement", app.living_arrangement);
        healthHtml += getFieldHtml("With Maintenance Meds", app.with_maintenance);
        healthHtml += getFieldHtml("Maintenance Specification", app.maintenance_spec);
        healthHtml += getFieldHtml("Priority Level", app.priority_level);

        if (healthHtml) {
            dynamicHtml += `
                <div class="section-title" style="margin-top:20px;"><i class="fas fa-heart-pulse"></i> Health & Wellness</div>
                <div class="info-grid">
                    ${healthHtml}
                </div>`;
        }

        // 6. Burial Claims
        let burialHtml = "";
        if (app.application_type === 'burial' || app.deceased_last_name) {
            let decName = [app.deceased_last_name, app.deceased_first_name, app.deceased_middle_name, app.deceased_suffix].filter(Boolean).join(' ');
            burialHtml += getFieldHtml("Deceased Senior Name", decName);
            burialHtml += getFieldHtml("Date of Passing", app.date_of_death);
            burialHtml += getFieldHtml("Relationship to Deceased", app.relationship_to_deceased);
            burialHtml += getFieldHtml("Deceased Birth Date", app.deceased_birth_date);
            burialHtml += getFieldHtml("Claimant Name", app.claimant_name);
            burialHtml += getFieldHtml("Claimant Relationship", app.claimant_relationship);
            burialHtml += getFieldHtml("Claimant Contact", app.claimant_contact);
        }

        if (burialHtml) {
            dynamicHtml += `
                <div class="section-title" style="margin-top:20px;"><i class="fas fa-ribbon"></i> Burial Claim Details</div>
                <div class="info-grid">
                    ${burialHtml}
                </div>`;
        }

        // 7. PWD details
        let pwdHtml = "";
        pwdHtml += getFieldHtml("Disability Type", app.disability_type);
        if (pwdHtml) {
            dynamicHtml += `
                <div class="section-title" style="margin-top:20px;"><i class="fas fa-wheelchair"></i> Disability Support Info</div>
                <div class="info-grid">
                    ${pwdHtml}
                </div>`;
        }

        // 8. Milestone Gifts
        let milestoneHtml = "";
        milestoneHtml += getFieldHtml("Milestone Age", app.milestone_age);
        milestoneHtml += getFieldHtml("Milestone Applicant Name", app.applicant_name);
        if (milestoneHtml) {
            dynamicHtml += `
                <div class="section-title" style="margin-top:20px;"><i class="fas fa-cake-candles"></i> Milestone Celebration Details</div>
                <div class="info-grid">
                    ${milestoneHtml}
                </div>`;
        }

        // 9. Home Visit summaries
        let visitHtml = "";
        visitHtml += getFieldHtml("Visit Purpose", app.visit_purpose);
        visitHtml += getFieldHtml("Visit Summary", app.visit_summary);
        if (visitHtml) {
            dynamicHtml += `
                <div class="section-title" style="margin-top:20px;"><i class="fas fa-person-walking-luggage"></i> Field Visit Summary</div>
                <div class="info-grid">
                    ${visitHtml}
                </div>`;
        }

        // 10. Proxy Details
        let proxyHtml = "";
        if (app.is_proxy_application == 1) {
            proxyHtml += getFieldHtml("Representative Name", app.proxy_name);
            proxyHtml += getFieldHtml("Relationship", app.proxy_relationship);
            proxyHtml += getFieldHtml("Representative Contact", app.proxy_contact_number);
            proxyHtml += getFieldHtml("Representative Token", app.proxy_token);
        }
        if (proxyHtml) {
            dynamicHtml += `
                <div class="section-title" style="margin-top:20px;"><i class="fas fa-user-clock"></i> Representative Details</div>
                <div class="info-grid">
                    ${proxyHtml}
                </div>`;
        }

        // 11. Additional Notes
        let notesHtml = getFieldHtml("Additional Notes / Remarks", app.additional_notes);
        if (notesHtml) {
            dynamicHtml += `
                <div class="section-title" style="margin-top:20px;"><i class="fas fa-comment-dots"></i> Additional Notes</div>
                <div class="info-grid">
                    ${notesHtml}
                </div>`;
        }

        return dynamicHtml;
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

        html += blobDocBox('Proof of Address', app.has_proof_of_address, 'proof_of_address');
        html += blobDocBox('ID / Identification Photo', app.has_id_image, 'id_image');

        const additionalDocs = [
            ['psa_birth_cert', 'PSA Birth Certificate'], ['barangay_residency', 'Barangay Residency'],
            ['comelec_cert', 'COMELEC Certificate'], ['proof_of_life', 'Proof of Life (In Bed)'],
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
