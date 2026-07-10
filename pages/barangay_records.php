<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/application_types.php';

// Redirect if not logged in or not barangay staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'barangay_staff') {
    header('Location: ../index.php');
    exit;
}

$barangayName = htmlspecialchars($_SESSION['barangay'] ?? 'Unknown Barangay');

// Get filter parameters
$search     = $_GET['search'] ?? '';
$typeFilter = $_GET['type'] ?? 'all';
$yearFilter = $_GET['year'] ?? 'all';

// Build query – only approved/released for this barangay
$baseQuery    = "FROM applications WHERE barangay = :barangay AND (workflow_state IN ('Approved', 'Released') OR status = 'approved')";
$params       = [':barangay' => $_SESSION['barangay']];

// Fetch all (client-side filtering handles search/year/type)
$recordsQuery = "SELECT id_number as id, full_name, application_type, date_submitted, COALESCE(workflow_state, status) as status " . $baseQuery . " ORDER BY date_submitted DESC";
$recordsStmt  = $conn->prepare($recordsQuery);
$recordsStmt->execute($params);
$applications = $recordsStmt->fetchAll(PDO::FETCH_ASSOC);

$totalRecords = count($applications);

function getStatusClass($status) {
    switch (strtolower($status)) {
        case 'approved':  return 'status-approved';
        case 'released':  return 'status-released';
        case 'verified':  return 'status-verified';
        case 'rejected':  return 'status-rejected';
        default:          return 'status-default';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Records – Barangay <?php echo $barangayName; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/barangay-sidebar.css?v=1.1">
    <link rel="stylesheet" href="../assets/css/main-dark-mode.css?v=1.1">
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

        /* ─── Layout ─────────────────────────────────────────────────────── */
        .main-content { flex: 1; padding: 28px; background: var(--bg); min-height: 100vh; }

        /* ─── Page Header ────────────────────────────────────────────────── */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
        }
        .page-header-left .greeting {
            font-size: 0.88rem;
            color: var(--gray);
            margin-bottom: 4px;
        }
        .page-header-left h1 {
            font-size: 1.7rem;
            font-weight: 800;
            color: var(--primary);
            margin: 0;
        }
        .page-header-left h1 span { color: var(--accent); }
        .header-user {
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--card);
            border-radius: 12px;
            padding: 10px 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border: 1px solid var(--border);
        }
        .header-user img {
            width: 40px; height: 40px;
            border-radius: 50%; object-fit: cover;
            border: 2px solid var(--accent);
        }
        .header-user-info h3 { font-size: 0.9rem; font-weight: 700; color: var(--primary); margin: 0; }
        .header-user-info p  { font-size: 0.75rem; color: var(--gray); margin: 0; }

        /* ─── Stats Strip ────────────────────────────────────────────────── */
        .stats-strip {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: var(--card);
            border-radius: 14px;
            padding: 20px 24px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.07); }
        .stat-icon {
            width: 50px; height: 50px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .stat-icon.green  { background: rgba(16,185,129,0.12); color: var(--success); }
        .stat-icon.blue   { background: rgba(37,99,235,0.12);  color: var(--accent); }
        .stat-icon.purple { background: rgba(139,92,246,0.12); color: var(--purple); }
        .stat-label { font-size: 0.78rem; color: var(--gray); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
        .stat-value { font-size: 1.8rem; font-weight: 800; color: var(--primary); line-height: 1; }

        /* ─── Records Card ───────────────────────────────────────────────── */
        .records-card {
            background: var(--card);
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 16px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .records-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 22px 28px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);
        }
        .records-card-header h2 {
            color: #fff;
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .records-card-header h2 i { color: #60a5fa; }
        .btn-export {
            background: rgba(255,255,255,0.15);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.25);
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 0.82rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-export:hover { background: rgba(255,255,255,0.25); }

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
        .filter-group label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--gray);
        }
        .filter-group select,
        .search-input-wrap input {
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
        .filter-group select:focus,
        .search-input-wrap input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
        }
        .search-input-wrap {
            display: flex;
            align-items: center;
            gap: 0;
            position: relative;
        }
        .search-input-wrap i {
            position: absolute;
            left: 10px;
            color: var(--gray);
            font-size: 0.85rem;
        }
        .search-input-wrap input { padding-left: 32px; min-width: 220px; }

        /* ─── Table ──────────────────────────────────────────────────────── */
        .table-wrap { overflow-x: auto; }
        .records-tbl { width: 100%; border-collapse: collapse; }
        .records-tbl thead tr {
            background: #f8fafc;
            border-bottom: 2px solid var(--border);
        }
        .records-tbl th {
            padding: 12px 20px;
            text-align: left;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--gray);
            white-space: nowrap;
        }
        .records-tbl td {
            padding: 14px 20px;
            font-size: 0.875rem;
            color: var(--primary);
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .records-tbl tbody tr { transition: background 0.15s; }
        .records-tbl tbody tr:hover { background: #f8fafc; }
        .records-tbl tbody tr:last-child td { border-bottom: none; }

        /* Name cell */
        .name-cell .full-name { font-weight: 700; color: var(--primary); }
        .name-cell .app-id    { font-size: 0.75rem; color: var(--gray); margin-top: 2px; }

        /* Status badge */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 4px 10px;
            border-radius: 20px;
        }
        .badge-approved { background: rgba(16,185,129,0.12); color: #15803d; border: 1px solid rgba(16,185,129,0.25); }
        .badge-released { background: rgba(139,92,246,0.12); color: #6d28d9; border: 1px solid rgba(139,92,246,0.25); }
        .badge-default  { background: rgba(148,163,184,0.15); color: #475569; }

        /* Action button */
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

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray);
        }
        .empty-state i  { font-size: 3rem; margin-bottom: 16px; opacity: 0.4; }
        .empty-state p  { font-size: 1rem; font-weight: 600; }
        .empty-state small { font-size: 0.85rem; }

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
        .modal-head h2 {
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
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

        /* Stepper */
        .stepper {
            display: flex;
            justify-content: space-between;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }
        .step { flex: 1; text-align: center; position: relative; }
        .step::after {
            content: '';
            position: absolute;
            top: 15px; left: 50%;
            width: 100%; height: 3px;
            background: var(--border); z-index: 1;
        }
        .step:last-child::after { display: none; }
        .step-circle {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: var(--border);
            color: #64748b;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 7px;
            font-weight: 800;
            font-size: 0.8rem;
            position: relative; z-index: 2;
            transition: background 0.3s, color 0.3s;
        }
        .step-label { font-size: 0.72rem; font-weight: 600; color: var(--gray); }
        .step.active .step-circle  { background: var(--accent); color: #fff; box-shadow: 0 0 0 4px rgba(37,99,235,0.2); }
        .step.active .step-label   { color: var(--accent); font-weight: 700; }
        .step.completed .step-circle { background: var(--success); color: #fff; }
        .step.completed .step-label  { color: var(--success); font-weight: 700; }
        .step.completed::after       { background: var(--success); }

        /* Compliance card */
        .compliance-card {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 22px;
        }
        .compliance-title {
            font-weight: 700;
            font-size: 0.88rem;
            color: var(--primary);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .compliance-title i { color: var(--accent); }
        .compliance-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px dashed var(--border);
            font-size: 0.85rem;
            gap: 10px;
        }
        .compliance-item:last-child { border-bottom: none; }
        .pass-tag { color: var(--success); font-weight: 700; display: flex; align-items: center; gap: 5px; white-space: nowrap; }
        .fail-tag { color: var(--danger);  font-weight: 700; display: flex; align-items: center; gap: 5px; white-space: nowrap; }

        /* Two-column modal grid */
        .modal-grid { display: grid; grid-template-columns: 1.2fr 1fr; gap: 28px; }
        @media(max-width: 760px) { .modal-grid { grid-template-columns: 1fr; } }

        .section-title {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--gray);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .section-title i { color: var(--accent); }

        /* Info grid */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 16px; margin-bottom: 20px; }
        .info-item label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--gray);
            display: block;
            margin-bottom: 2px;
        }
        .info-item span {
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--primary);
        }
        .info-item.wide { grid-column: 1 / -1; }

        /* Image preview */
        .doc-preview-title {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--gray);
            margin-bottom: 8px;
        }
        .doc-preview-box {
            width: 100%;
            height: 180px;
            border: 2px dashed var(--border);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #f8fafc;
            margin-bottom: 14px;
        }
        .doc-preview-box img { max-width: 100%; max-height: 100%; object-fit: contain; }

        /* Timeline */
        .timeline { border-left: 2px solid var(--border); padding-left: 18px; margin-top: 10px; }
        .timeline-event { position: relative; padding-bottom: 18px; }
        .timeline-event::before {
            content: '';
            position: absolute;
            left: -25px; top: 5px;
            width: 12px; height: 12px;
            border-radius: 50%;
            background: var(--accent);
            border: 2px solid #fff;
            box-shadow: 0 0 0 2px var(--border);
        }
        .timeline-time  { font-size: 0.72rem; color: var(--gray); margin-bottom: 3px; }
        .timeline-title { font-size: 0.85rem; font-weight: 700; color: var(--primary); }
        .timeline-by    { font-size: 0.75rem; color: #64748b; margin-top: 2px; }
        .timeline-note  { font-size: 0.82rem; color: #475569; margin-top: 4px; font-style: italic; }

        /* ─── Footer ─────────────────────────────────────────────────────── */
        .page-footer {
            text-align: center;
            padding: 24px;
            font-size: 0.78rem;
            color: var(--gray);
        }
    </style>
</head>
<body>
<div class="container">
    <?php include '../partials/barangay_sidebar.php'; ?>

    <div class="main-content">

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-left">
                <div class="greeting" id="greetingMsg"></div>
                <h1>Barangay <span><?php echo $barangayName; ?></span> Records</h1>
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
                    <p><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $_SESSION['role']))); ?> · <?php echo $barangayName; ?></p>
                </div>
            </div>
        </div>

        <!-- Stats Strip -->
        <div class="stats-strip">
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="stat-label">Total Approved</div>
                    <div class="stat-value" id="totalApproved"><?php echo $totalRecords; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-id-card"></i></div>
                <div>
                    <div class="stat-label">Barangay</div>
                    <div class="stat-value" style="font-size:1.1rem;padding-top:4px;"><?php echo $barangayName; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-box-archive"></i></div>
                <div>
                    <div class="stat-label">Filtered Records</div>
                    <div class="stat-value" id="filteredCount"><?php echo $totalRecords; ?></div>
                </div>
            </div>
        </div>

        <!-- Records Card -->
        <div class="records-card">
            <div class="records-card-header">
                <h2><i class="fas fa-folder-open"></i> Application Records</h2>
                <button class="btn-export" onclick="exportDisplayedRecords()">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <div class="filter-group">
                    <label for="search-input">Search</label>
                    <div class="search-input-wrap">
                        <i class="fas fa-search"></i>
                        <input id="search-input" type="text" placeholder="Name or ID…" oninput="applyFilters()">
                    </div>
                </div>
                <div class="filter-group">
                    <label for="year-filter">Year</label>
                    <select id="year-filter" onchange="applyFilters()">
                        <option value="all">All Years</option>
                        <?php
                            $currentYear = date("Y");
                            for ($y = $currentYear; $y >= 2020; $y--) {
                                $selected = ($yearFilter == $y) ? 'selected' : '';
                                echo "<option value=\"$y\" $selected>$y</option>";
                            }
                        ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="type-filter">Application Type</label>
                    <select id="type-filter" onchange="applyFilters()">
                        <option value="all">All Types</option>
                        <?php foreach (getApplicationTypeOptions() as $val => $label): ?>
                            <option value="<?php echo htmlspecialchars($val); ?>"><?php echo htmlspecialchars(applicationTypeLabel($val)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="table-wrap">
                <table class="records-tbl">
                    <thead>
                        <tr>
                            <th>Applicant</th>
                            <th>Application Type</th>
                            <th>Date Submitted</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                    <?php if (empty($applications)): ?>
                        <tr><td colspan="5">
                            <div class="empty-state">
                                <i class="fas fa-folder-open"></i>
                                <p>No approved applications found</p>
                                <small>Barangay <?php echo $barangayName; ?> has no approved records yet.</small>
                            </div>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($applications as $app):
                            $statusLower = strtolower($app['status']);
                            $badgeClass  = ($statusLower === 'released') ? 'badge-released' : 'badge-approved';
                            $badgeIcon   = ($statusLower === 'released') ? 'fa-box-archive' : 'fa-circle-check';
                        ?>
                        <tr class="record-row"
                            data-id="<?php echo htmlspecialchars($app['id']); ?>"
                            data-name="<?php echo htmlspecialchars($app['full_name']); ?>"
                            data-date="<?php echo htmlspecialchars($app['date_submitted']); ?>"
                            data-type="<?php echo htmlspecialchars($app['application_type']); ?>">
                            <td>
                                <div class="name-cell">
                                    <div class="full-name"><?php echo htmlspecialchars($app['full_name']); ?></div>
                                    <div class="app-id"><?php echo htmlspecialchars($app['id']); ?></div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars(applicationTypeLabel($app['application_type'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($app['date_submitted'])); ?></td>
                            <td><span class="badge <?php echo $badgeClass; ?>"><i class="fas <?php echo $badgeIcon; ?>"></i><?php echo htmlspecialchars(ucfirst($app['status'])); ?></span></td>
                            <td>
                                <button class="btn-view view-application-btn" data-id="<?php echo $app['id']; ?>">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- No results row (injected by JS) -->
            <div id="noResultsMsg" style="display:none; text-align:center; padding:40px 20px; color:var(--gray);">
                <i class="fas fa-search" style="font-size:2rem;opacity:0.4;display:block;margin-bottom:10px;"></i>
                No records match your filters.
            </div>
        </div>

        <div class="page-footer">Centralized Profiling and Record Authentication System &bull; Barangay <?php echo $barangayName; ?> &copy; <?php echo date('Y'); ?></div>
    </div><!-- /.main-content -->
</div><!-- /.container -->

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
                <div class="compliance-title"><i class="fas fa-shield-halved"></i> Compliance & AI Verification Engine</div>
                <div id="complianceList">
                    <p style="color:var(--gray);font-size:0.85rem;">Loading compliance checks…</p>
                </div>
            </div>

            <!-- Two-column grid -->
            <div class="modal-grid">
                <!-- LEFT: Applicant details -->
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
                        <div class="info-item wide">
                            <label>Complete Address</label>
                            <span id="infoAddress">—</span>
                        </div>
                        <div class="info-item">
                            <label>Barangay</label>
                            <span id="infoBarangay">—</span>
                        </div>
                    </div>

                    <!-- Dynamic section (PWD / Pension / Burial) -->
                    <div id="dynamicDetailsSection"></div>

                    <!-- Documents -->
                    <div class="section-title" style="margin-top:20px;"><i class="fas fa-file-image"></i> Submitted Documents</div>
                    <div class="doc-preview-title">Proof of Address</div>
                    <div class="doc-preview-box" id="previewProof">
                        <span style="color:var(--gray);font-size:0.8rem;">Loading…</span>
                    </div>
                    <div class="doc-preview-title">ID / Identification Photo</div>
                    <div class="doc-preview-box" id="previewIdImage">
                        <span style="color:var(--gray);font-size:0.8rem;">Loading…</span>
                    </div>
                </div>

                <!-- RIGHT: Audit timeline -->
                <div>
                    <div class="section-title"><i class="fas fa-clock-rotate-left"></i> Audit History</div>
                    <div class="timeline" id="timelineList">
                        <p style="color:var(--gray);font-size:0.85rem;">Loading history…</p>
                    </div>
                </div>
            </div>

        </div><!-- /.modal-scroller -->
    </div><!-- /.modal-box -->
</div><!-- /#applicationModal -->

<script src="../assets/js/sidebar-toggle.js"></script>
<script src="../assets/js/dark-mode.js"></script>
<script>
    /* ─── Greeting ──────────────────────────────────────────── */
    (function(){
        const h = new Date().getHours();
        const g = h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
        document.getElementById('greetingMsg').innerHTML = `${g}, <strong><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></strong>!`;
    })();

    /* ─── Modal open/close ──────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelector('.table-wrap').addEventListener('click', function(e) {
            const btn = e.target.closest('.view-application-btn');
            if (btn) openApplicationModal(btn.dataset.id);
        });

        document.getElementById('closeModalBtn').addEventListener('click', closeModal);
        document.getElementById('applicationModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    });

    function closeModal() {
        document.getElementById('applicationModal').style.display = 'none';
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
        // Reset
        document.getElementById('modalAppTitle').textContent  = 'Loading…';
        document.getElementById('complianceList').innerHTML   = '<p style="color:var(--gray);font-size:0.85rem;">Loading compliance checks…</p>';
        document.getElementById('dynamicDetailsSection').innerHTML = '';
        document.getElementById('timelineList').innerHTML     = '<p style="color:var(--gray);font-size:0.85rem;">Loading history…</p>';
        document.getElementById('previewProof').innerHTML     = '<span style="color:var(--gray);font-size:0.8rem;">Loading…</span>';
        document.getElementById('previewIdImage').innerHTML   = '<span style="color:var(--gray);font-size:0.8rem;">Loading…</span>';

        // Reset stepper
        ['step-Received','step-For-Review','step-Verified','step-Approved','step-Released'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.className = 'step';
        });

        document.getElementById('applicationModal').style.display = 'block';

        fetch(`../api/get_application_details.php?id=${encodeURIComponent(appId)}`)
            .then(r => r.json())
            .then(app => {
                if (app.error) {
                    document.getElementById('complianceList').innerHTML = `<p style="color:red;">${app.error}</p>`;
                    return;
                }

                /* ── Title ── */
                document.getElementById('modalAppTitle').textContent = `${app.full_name} — ${app.id_number}`;

                /* ── Stepper ── */
                const steps = ['Received','For Review','Verified','Approved','Released'];
                const state = app.workflow_state || 'Received';
                let idx = steps.indexOf(state);
                if (idx === -1) idx = 0;
                steps.forEach((s, i) => {
                    const el = document.getElementById('step-' + s.replace(' ','-'));
                    if (!el) return;
                    el.classList.add(i < idx ? 'completed' : i === idx ? 'active' : '');
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

                /* ── Compliance ── */
                let ch = '';
                const aiScore  = parseFloat(app.ai_confidence_score || 95);
                const aiFlag   = (app.ai_status || '') === 'FLAGGED_ANOMALY';
                if (aiScore >= 90 && !aiFlag) {
                    ch += `<div class="compliance-item" style="background:rgba(16,185,129,0.06);border-left:3px solid var(--success);padding:6px 8px;border-radius:5px;">
                        <span style="font-weight:600;"><i class="fas fa-robot" style="color:var(--success)"></i> CNN AI Document Verification</span>
                        <span class="pass-tag"><i class="fas fa-shield-halved"></i> AI-VERIFIED — ${aiScore.toFixed(1)}%</span>
                    </div>`;
                } else {
                    ch += `<div class="compliance-item" style="background:rgba(239,68,68,0.08);border-left:3px solid var(--danger);padding:6px 8px;border-radius:5px;">
                        <span style="font-weight:600;"><i class="fas fa-robot" style="color:var(--danger)"></i> CNN AI Document Verification</span>
                        <span class="fail-tag"><i class="fas fa-triangle-exclamation"></i> ANOMALY DETECTED — ${aiScore.toFixed(1)}%</span>
                    </div>`;
                }

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
                        : `<div class="compliance-item"><span>Filing Deadline (Pasig Ord. 3/2026)</span><span class="fail-tag"><i class="fas fa-circle-xmark"></i> FAIL — ${ed} days elapsed (limit: 30)</span></div>`;
                }
                if (app.priority_level === 'high') {
                    ch += `<div class="compliance-item" style="background:rgba(245,158,11,0.08);padding:5px;border-radius:4px;">
                        <span>Priority Queue</span>
                        <span style="color:#d97706;font-weight:700;"><i class="fas fa-star"></i> High-Priority (Bedridden)</span>
                    </div>`;
                }
                document.getElementById('complianceList').innerHTML = ch;

                /* ── Dynamic Details ── */
                let dh = '';
                if (app.application_type === 'pwd') {
                    dh = `<div class="section-title" style="margin-top:14px;"><i class="fas fa-wheelchair"></i> Disability Details</div>
                          <div class="info-grid"><div class="info-item wide"><label>Disability Type</label><span>${app.disability_type||'—'}</span></div></div>`;
                } else if (app.application_type === 'pension') {
                    dh = `<div class="section-title" style="margin-top:14px;"><i class="fas fa-coins"></i> Social Pension</div>
                          <div class="info-grid">
                            <div class="info-item"><label>SSS Number</label><span>${app.sss_number||'—'}</span></div>
                            <div class="info-item"><label>Monthly Pension</label><span>₱${parseFloat(app.pension_amount||0).toFixed(2)}</span></div>
                          </div>`;
                } else if (app.application_type === 'burial') {
                    dh = `<div class="section-title" style="margin-top:14px;"><i class="fas fa-cross"></i> Burial Assistance</div>
                          <div class="info-grid">
                            <div class="info-item"><label>Date of Passing</label><span>${app.date_of_death||'—'}</span></div>
                            <div class="info-item"><label>Relationship</label><span>${app.relationship_to_deceased||'—'}</span></div>
                          </div>`;
                }
                if (app.is_proxy_application == 1) {
                    dh += `<div class="section-title" style="margin-top:14px;"><i class="fas fa-user-clock"></i> Proxy Representative</div>
                           <div class="info-grid">
                             <div class="info-item"><label>Proxy Name</label><span>${app.proxy_name||'—'}</span></div>
                             <div class="info-item"><label>Relationship</label><span>${app.proxy_relationship||'—'}</span></div>
                             <div class="info-item"><label>Contact</label><span>${app.proxy_contact_number||'—'}</span></div>
                             <div class="info-item"><label>Token</label><span>${app.proxy_token||'—'}</span></div>
                           </div>`;
                }
                document.getElementById('dynamicDetailsSection').innerHTML = dh;

                /* ── Document Previews ── */
                document.getElementById('previewProof').innerHTML = app.has_proof_of_address
                    ? `<img src="../api/get_document.php?id=${appId}&doc_type=proof_of_address" alt="Proof of Address">`
                    : '<span style="color:var(--gray);font-size:0.8rem;"><i class="fas fa-file-slash"></i> No file uploaded</span>';

                document.getElementById('previewIdImage').innerHTML = app.has_id_image
                    ? `<img src="../api/get_document.php?id=${appId}&doc_type=id_image" alt="ID Photo">`
                    : '<span style="color:var(--gray);font-size:0.8rem;"><i class="fas fa-file-slash"></i> No file uploaded</span>';

                /* ── Timeline ── */
                let th = '';
                if (app.history && app.history.length > 0) {
                    app.history.forEach(log => {
                        const t = new Date(log.changed_at.replace(' ','T')).toLocaleString();
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
                document.getElementById('complianceList').innerHTML = '<p style="color:red;">Failed to load details.</p>';
            });
    }

    /* ─── Client-side filtering ─────────────────────────────── */
    function applyFilters() {
        const search = document.getElementById('search-input').value.toLowerCase();
        const year   = document.getElementById('year-filter').value;
        const type   = document.getElementById('type-filter').value.toLowerCase();
        const rows   = document.querySelectorAll('#tableBody .record-row');
        let visible  = 0;

        rows.forEach(row => {
            const nameMatch   = row.dataset.name.toLowerCase().includes(search) || row.dataset.id.toLowerCase().includes(search);
            const yearMatch   = year === 'all' || (row.dataset.date && new Date(row.dataset.date).getFullYear().toString() === year);
            const typeMatch   = type === 'all' || row.dataset.type.toLowerCase() === type;
            const show        = nameMatch && yearMatch && typeMatch;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        document.getElementById('filteredCount').textContent = visible;
        document.getElementById('noResultsMsg').style.display = visible === 0 ? 'block' : 'none';
    }

    /* ─── Export CSV ─────────────────────────────────────────── */
    function exportDisplayedRecords() {
        const rows = Array.from(document.querySelectorAll('#tableBody .record-row')).filter(r => r.style.display !== 'none');
        if (!rows.length) { alert('No visible records to export.'); return; }

        const csvRows = [['Applicant Name','ID Number','Application Type','Date Submitted','Status']];
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            csvRows.push([
                cells[0]?.querySelector('.full-name')?.textContent.trim() || '',
                cells[0]?.querySelector('.app-id')?.textContent.trim() || '',
                cells[1]?.textContent.trim() || '',
                cells[2]?.textContent.trim() || '',
                cells[3]?.textContent.trim() || '',
            ]);
        });

        const csv  = csvRows.map(r => r.map(c => `"${c.replace(/"/g,'""')}"`).join(',')).join('\n');
        const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
        const url  = URL.createObjectURL(blob);
        const a    = Object.assign(document.createElement('a'), {href: url, download: `barangay_${<?php echo json_encode($_SESSION['barangay']); ?>}_records.csv`});
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }
</script>
</body>
</html>