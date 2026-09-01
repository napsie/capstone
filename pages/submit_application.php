<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/application_types.php';

// Check if the user is logged in and has the correct role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'barangay_staff') {
    header('Location: ../index.php');
    exit;
}

$loggedInBarangay = htmlspecialchars($_SESSION['barangay'] ?? '');
$applicationSubmissionNotice = $_SESSION['application_submission_notice'] ?? '';
unset($_SESSION['application_submission_notice']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue – Barangay <?php echo $loggedInBarangay; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/barangay-sidebar.css?v=4">
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
        
        .header-actions { display: flex; align-items: center; gap: 14px; }
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

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 18px;
            border-radius: 8px;
            font-size: 0.84rem;
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            text-decoration: none;
            white-space: nowrap;
        }
        .btn-primary { background: var(--secondary); color: white; }
        .btn-primary:hover { background: #153860; transform: translateY(-1px); }
        .btn-accent { background: var(--success); color: white; }
        .btn-accent:hover { background: #059669; transform: translateY(-1px); }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-small { padding: 5px 12px; font-size: 0.76rem; border-radius: 6px; }

        /* ─── Queue Card ─────────────────────────────────────────────────── */
        .queue-card { background: var(--card); border-radius: 16px; border: 1px solid var(--border); box-shadow: 0 4px 16px rgba(0,0,0,0.05); overflow: hidden; }
        .queue-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 28px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);
        }
        .queue-card-header h2 { color: #fff; font-size: 1.05rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 10px; }
        .queue-card-header h2 i { color: #60a5fa; }
        .queue-export-btn { background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.38); color:#fff; }
        .queue-export-btn:hover { background:rgba(255,255,255,.22); color:#fff; }

        /* Filter Controls */
        .filter-bar {
            display: flex;
            align-items: flex-end;
            gap: 12px;
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
        }
        .filter-group select:focus, .search-wrap input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,0.12); }
        .search-wrap { position: relative; }
        .search-wrap i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--gray); font-size: 0.85rem; }
        .search-wrap input { padding-left: 32px; min-width: 200px; }

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
        .records-tbl tbody tr:hover { background: #f8fafc; }
        .records-tbl tbody tr:last-child td { border-bottom: none; }

        .name-cell .name-link { font-weight: 700; color: var(--accent); text-decoration: none; }
        .name-cell .name-link:hover { text-decoration: underline; }
        .name-cell .app-id    { font-size: 0.73rem; color: var(--gray); margin-top: 2px; font-family: monospace; }

        .alert-blurry {
            color: var(--danger);
            font-size: 0.75rem;
            font-weight: 700;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

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
            white-space: nowrap;
        }
        .badge-received { background-color: #e2e8f0; color: #475569; }
        .badge-review { background-color: #dbeafe; color: #1d4ed8; }
        .badge-verified { background-color: #ccfbf1; color: #0f766e; }
        .badge-approved { background-color: #dcfce7; color: #15803d; }
        .badge-released { background-color: #f3e8ff; color: #6b21a8; }

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
        .modal-head h2 { color: #fff; font-size: 1rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 10px; }
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
        .export-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:20px; }

        .app-result-dialog { width:min(380px,100%); padding:28px 26px 24px; border-radius:16px; background:#fff; text-align:center; box-shadow:0 20px 60px rgba(15,23,42,.28); animation:resultDialogIn .2s ease-out; }
        .app-result-icon { width:54px; height:54px; margin:0 auto 12px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.35rem; }
        .app-result-dialog h3 { margin:0 0 8px; color:#0f172a; font-size:1.05rem; }
        .app-result-dialog p { margin:0 auto 20px; color:#64748b; font-size:.86rem; line-height:1.5; max-width:310px; }
        .app-result-close { min-width:108px; justify-content:center; }
        @keyframes resultDialogIn { from { opacity:0; transform:translateY(8px) scale(.98); } to { opacity:1; transform:none; } }

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

        /* Form elements */
        .form-section { margin-bottom: 28px; }
        .form-section h3 { font-size: 0.95rem; color: var(--primary); margin-bottom: 15px; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid var(--border); padding-bottom: 6px; }
        .form-section h3 i { color: var(--accent); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 14px; }
        @media(max-width:600px) { .form-row { grid-template-columns: 1fr; } }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-group label { font-size: 0.78rem; font-weight: 700; color: var(--gray); text-transform: uppercase; letter-spacing: 0.05em; }
        .form-group input, .form-group select, .form-group textarea {
            border: 1.5px solid var(--border);
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 0.88rem;
            color: var(--primary);
            background: #fff;
            outline: none;
            transition: border-color 0.2s;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: var(--accent); }
        .form-group input[disabled], .form-group select[disabled], .form-group textarea[disabled] { background: #f1f5f9; cursor: not-allowed; }

        .image-placeholder {
            margin-top: 10px; width: 100%; height: 180px;
            border: 2px dashed var(--border); border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            overflow: hidden; background: #f8fafc;
        }
        .image-placeholder img { max-width: 100%; max-height: 100%; object-fit: contain; }

        /* Modal grid */
        .modal-grid { display:grid; grid-template-columns:1.2fr 1fr; gap:28px; }
        @media(max-width:760px) { .modal-grid { grid-template-columns:1fr; } }

        /* Timeline */
        .timeline { border-left:2px solid var(--border); padding-left:18px; margin-top:10px; }
        .timeline-event { position:relative; padding-bottom:18px; }
        .timeline-event::before { content:''; position:absolute; left:-25px; top:5px; width:12px; height:12px; border-radius:50%; background:var(--accent); border:2px solid #fff; box-shadow:0 0 0 2px var(--border); }
        .timeline-time  { font-size:0.72rem; color:var(--gray); margin-bottom:3px; }
        .timeline-title { font-size:0.85rem; font-weight:700; color:var(--primary); }
        .timeline-by    { font-size:0.75rem; color:#64748b; margin-top:2px; }
        .timeline-note  { font-size:0.82rem; color:#475569; margin-top:4px; font-style:italic; }

        /* Proxy document items */
        .proxy-doc-card {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px;
            background: #fff;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .proxy-doc-card label {
            font-size: 0.72rem; font-weight: 700; color: var(--gray);
            display: block; margin-bottom: 6px; height: 32px; overflow: hidden; line-height: 1.2;
        }

        /* Footer */
        .page-footer { text-align:center; padding:24px; font-size:0.78rem; color:var(--gray); }
    </style>
    <link rel="stylesheet" href="../assets/css/seniorlink-ui.css?v=16">
    <script src="../assets/js/modal-hci.js?v=2" defer></script>
    <link rel="stylesheet" href="../assets/css/table-pagination.css?v=1">
    <script src="../assets/js/table-pagination.js?v=1" defer></script>
    <link rel="stylesheet" href="../assets/css/system-header.css?v=1">
    <link rel="stylesheet" href="../assets/css/system-sidebar.css?v=3">
    <link rel="stylesheet" href="../assets/css/applicant-modal.css?v=1">
</head>
<body>
<div class="container">
    <?php include '../partials/barangay_sidebar.php'; ?>

    <div class="main-content">

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-left">
                <div class="greeting" id="greetingMsg">Welcome back!</div>
                <h1>Barangay <span>Queue</span></h1>
            </div>
            <div class="header-actions">
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
                        <p><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $_SESSION['role']))); ?> · <?php echo $loggedInBarangay; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Page subtitle / instructions -->
        <p style="color:var(--gray); margin-bottom: 24px; font-size: 0.9rem;">
            Manage and track active citizen profiles for your barangay. Click the applicant's name to open their review, transition state, or upload blurry scan replacements.
        </p>

        <!-- Queue Card -->
        <div class="queue-card">
            <div class="queue-card-header">
                <h2><i class="fas fa-clipboard-list"></i> Applications Queue</h2>
                <button type="button" class="btn btn-small queue-export-btn" onclick="openExportModal()">
                    <i class="fas fa-file-excel"></i> Generate Report
                </button>
            </div>

            <!-- Filter Controls -->
            <div class="filter-bar">
                <div class="filter-group">
                    <label>Search</label>
                    <div class="search-wrap">
                        <i class="fas fa-search"></i>
                        <input type="text" class="search-box" id="searchInput" placeholder="Search name or ID…">
                    </div>
                </div>
                <div class="filter-group">
                    <label>Application Type</label>
                    <select id="applicationTypeFilter">
                        <option value="">All Types</option>
                        <?php foreach (getApplicationTypeOptions() as $val => $label): ?>
                            <option value="<?php echo $val; ?>"><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-accent" id="scanQrBtn" onclick="openProxyModal()"><i class="fas fa-qrcode"></i> Scan Token</button>
            </div>

            <!-- Table -->
            <div class="table-wrap">
                <table class="records-tbl">
                    <thead>
                        <tr>
                            <th>Applicant</th>
                            <th>Application Type</th>
                            <th>Birth Date</th>
                            <th>Contact</th>
                            <th>Date Submitted</th>
                            <th>State</th>
                            <th>Address</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="applicationsTableBody">
                        <tr><td colspan="9" style="text-align:center; padding: 20px; color:var(--gray);">Loading applications…</td></tr>
                    </tbody>
                </table>
            </div>
            <nav id="serverPagination" class="server-pagination" aria-label="Application result pages" style="display:flex;align-items:center;justify-content:center;gap:12px;padding:16px;"></nav>
        </div>

        <div class="page-footer">Centralized Profiling and Record Authentication System &bull; Barangay <?php echo $loggedInBarangay; ?> &copy; <?php echo date('Y'); ?></div>
    </div>
</div>

<!-- ──────────────────────────────────────────────────────────────────── -->
<!-- Application Detail Modal                                             -->
<!-- ──────────────────────────────────────────────────────────────────── -->
<div id="applicationModal" class="modal-overlay applicant-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="modalAppTitle">
    <div class="modal-box applicant-modal-dialog" role="document">
        <div class="modal-head">
            <div class="applicant-modal-heading"><span class="applicant-modal-eyebrow">Applicant record</span><h2><i class="fas fa-file-invoice"></i> <span id="modalAppTitle">Application Profile Details</span></h2><p>Review applicant information, submitted requirements, and processing history.</p></div>
            <button type="button" class="modal-close" id="closeModalBtn" aria-label="Close application details"><i class="fas fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="modal-scroller applicant-modal-body">

            <!-- Workflow progress stepper -->
            <div class="stepper">
                <div class="step" id="step-Received"><div class="step-circle">1</div><div class="step-label">Received</div></div>
                <div class="step" id="step-For-Review"><div class="step-circle">2</div><div class="step-label">For Review</div></div>
                <div class="step" id="step-Verified"><div class="step-circle">3</div><div class="step-label">Verified</div></div>
            </div>

            <!-- Return Warning Box -->
            <div id="returnedWarningBox" style="display:none; background-color: rgba(239, 68, 68, 0.08); border: 1.5px solid var(--danger); padding: 14px; border-radius: 10px; margin-bottom: 22px; color: #b91c1c; font-size: 0.88rem;">
                <strong><i class="fas fa-exclamation-triangle"></i> SCANS REJECTED BY OFFICE REVIEWER:</strong>
                <p id="returnedReasonText" style="margin-top: 5px; font-style: italic;"></p>
                <p style="margin-top: 10px; font-weight: bold; text-decoration: underline;">Please upload clean, high-resolution scans below and save changes to update.</p>
            </div>

            <div class="modal-grid applicant-modal-layout">
                <!-- LEFT: Details Form -->
                <div>
                    <form id="applicationDetailForm" method="POST" action="../api/update_application.php" enctype="multipart/form-data">
                        <input type="hidden" id="applicationId" name="applicationId">
                        <!-- Mirror of disabled select so applicationType is always submitted -->
                        <input type="hidden" id="applicationTypeHidden" name="applicationType">

                        <div class="form-section">
                            <h3><i class="fas fa-user"></i> Basic Information</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="applicationType">Application Type</label>
                                    <select id="applicationType" name="applicationType" required disabled>
                                        <?php foreach (getApplicationTypeOptions() as $val => $label): ?>
                                            <option value="<?php echo $val; ?>"><?php echo htmlspecialchars($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="lastName">Last Name</label>
                                    <input type="text" id="lastName" name="lastName" required>
                                </div>
                                <div class="form-group">
                                    <label for="firstName">First Name</label>
                                    <input type="text" id="firstName" name="firstName" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="middleName">Middle Name</label>
                                    <input type="text" id="middleName" name="middleName">
                                </div>
                                <div class="form-group">
                                    <label for="suffix">Suffix</label>
                                    <input type="text" id="suffix" name="suffix">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="birthDate">Birth Date</label>
                                    <input type="date" id="birthDate" name="birthDate" required>
                                </div>
                                <div class="form-group">
                                    <label for="contactNumber">Contact Number</label>
                                    <input type="text" id="contactNumber" name="contactNumber" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="completeAddress">Complete Address</label>
                                <textarea id="completeAddress" name="completeAddress" rows="2" required></textarea>
                            </div>
                            <div class="form-row" id="zipLandmarkRow">
                                <div class="form-group">
                                    <label for="zipCode">ZIP Code</label>
                                    <input type="text" id="zipCode" name="zipCode">
                                </div>
                                <div class="form-group" id="landmarkGroup">
                                    <label for="landmark">Landmark</label>
                                    <input type="text" id="landmark" name="landmark">
                                </div>
                            </div>
                            <div class="form-row" id="emergencyContactRow">
                                <div class="form-group">
                                    <label for="emergencyContactName">Emergency Contact Name</label>
                                    <input type="text" id="emergencyContactName" name="emergencyContactName">
                                </div>
                                <div class="form-group">
                                    <label for="emergencyContact">Emergency Contact Number</label>
                                    <input type="text" id="emergencyContact" name="emergencyContact">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="emailAddress">Email Address</label>
                                <input type="email" id="emailAddress" name="emailAddress">
                            </div>
                            <div class="form-group">
                                <label for="additionalNotes">Additional Notes</label>
                                <textarea id="additionalNotes" name="additionalNotes" rows="3"></textarea>
                            </div>
                        </div>

                        <?php $formFieldPrefix = ''; include '../partials/osca_form_sections.php'; ?>

                        <div id="pension-fields-modal" class="form-section" style="display:none;">
                            <h3><i class="fas fa-wallet"></i> Social Pension Details</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="sssNumber">SSS Number</label>
                                    <input type="text" id="sssNumber" name="sssNumber">
                                </div>
                                <div class="form-group">
                                    <label for="pensionAmount">Monthly Pension Amount (PHP)</label>
                                    <input type="number" step="0.01" min="0" id="pensionAmount" name="pensionAmount">
                                </div>
                            </div>
                        </div>

                        <div id="burial-fields-modal" class="form-section" style="display:none;">
                            <h3><i class="fas fa-ribbon"></i> Burial Assistance Details</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="dateOfDeath">Date of Passing</label>
                                    <input type="date" id="dateOfDeath" name="dateOfDeath">
                                </div>
                                <div class="form-group">
                                    <label for="relationshipToDeceased">Relationship to Deceased</label>
                                    <input type="text" id="relationshipToDeceased" name="relationshipToDeceased">
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div id="allSubmittedDocumentsSection" style="display:none; margin:0 0 18px;">
                                <div style="font-size:.76rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--gray);margin-bottom:10px;">Submitted Documents</div>
                                <div id="allSubmittedDocumentsList" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;"></div>
                            </div>
                        </div>

                        <!-- Proxy Documents -->
                        <div class="form-section" id="proxyDocumentsSection" style="display:none;">
                            <h3><i class="fas fa-user-shield"></i> Submitted Representative Documents</h3>
                            <div id="proxyDocumentsList" style="display:grid; grid-template-columns:1fr 1fr; gap:12px;"></div>
                        </div>

                        <div class="form-actions" id="modalFormActions" style="display:flex; gap:10px; margin-top:20px;"></div>
                    </form>
                </div>

                <!-- RIGHT: Audit Trail Timeline -->
                <div>
                    <div class="section-title"><i class="fas fa-clock-rotate-left"></i> Audit Trail History Log</div>
                    <div class="timeline" id="timelineList"></div>
                    <div class="section-title" style="margin-top:24px;"><i class="fas fa-rectangle-list"></i> Application Context</div>
                    <div id="dynamicDetailsSection"></div>
                </div>
            </div>

        </div><!-- /.modal-scroller -->
    </div><!-- /.modal-box -->
</div><!-- /#applicationModal -->

<!-- Export Excel filter modal -->
<div id="exportModal" class="modal-overlay" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="exportModalTitle">
    <div class="modal-box export-modal-box">
        <div class="modal-head">
            <h2 id="exportModalTitle"><i class="fas fa-file-excel"></i> Export Queue to Excel</h2>
            <button type="button" class="modal-close" id="closeExportModalBtn" aria-label="Close export dialog">&times;</button>
        </div>
        <form id="exportReportForm" method="GET" action="../api/export_records_excel.php">
            <div class="export-modal-body">
                <p>Set the filters for your queue Excel report. Only applications matching these criteria will be included.</p>
                <input type="hidden" name="scope" value="barangay">
                <input type="hidden" name="report_mode" value="queue">
                <div class="export-filter-grid">
                    <div class="export-field">
                        <label for="exportType">Application Type</label>
                        <select name="type" id="exportType">
                            <option value="all">All Types</option>
                            <?php foreach (getApplicationTypeOptions() as $val => $label): ?>
                                <option value="<?php echo htmlspecialchars($val); ?>"><?php echo htmlspecialchars($label); ?></option>
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
                        <input type="date" name="date_from" id="exportDateFrom">
                    </div>
                    <div class="export-field">
                        <label for="exportDateTo">Date To</label>
                        <input type="date" name="date_to" id="exportDateTo">
                    </div>
                </div>
                <div class="export-actions">
                    <button type="button" class="btn btn-ghost" id="cancelExportBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-file-excel"></i> Generate Excel</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- QR scan Modal -->
<div id="proxyModal" class="modal-overlay" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="proxyModalTitle">
    <div class="modal-box" style="max-width:500px; margin: 10% auto;">
        <div class="modal-head">
            <h2 id="proxyModalTitle"><i class="fas fa-qrcode"></i> Scan Representative QR Token</h2>
            <button type="button" class="modal-close" onclick="closeProxyModal()" aria-label="Close QR scanner dialog">&times;</button>
        </div>
        <div class="modal-scroller" style="padding:20px;">
            <p style="font-size:0.82rem; color:var(--gray); margin-bottom:14px;">
                Paste the encrypted QR token link or type the representative transaction token (e.g. PRX-XXXXXX) to load the profile.
            </p>
            <div class="form-group" style="margin-bottom:14px;">
                <label style="font-weight:700; font-size:0.75rem;">Token Payload</label>
                <textarea id="modalToken" class="form-control" rows="3" placeholder="Paste scan payload here…" style="width:100%; border:1px solid var(--border); border-radius:8px; padding:10px; font-family:monospace; resize:none; outline:none;"></textarea>
            </div>
            <button type="button" class="btn btn-accent" style="width:100%; justify-content:center;" onclick="searchByQrToken()">
                <i class="fas fa-search"></i> Search and Open Profile
            </button>
            <div id="modalError" style="color:var(--danger); font-size:0.8rem; margin-top:10px; font-weight:600; text-align:center;"></div>
        </div>
    </div>
</div>

<script src="../assets/js/sidebar-toggle.js?v=3"></script>
<script src="../assets/js/osca-form-fields.js?v=3"></script>
<script src="../assets/js/application-documents.js?v=8"></script>
<script src="../assets/js/application-details.js?v=9"></script>
<script src="../assets/js/seniorlink-feedback.js?v=1"></script>
<script src="../assets/js/application-form-generator.js?v=1"></script>
<script src="../assets/js/report-validation.js?v=1"></script>
<script>
    const TYPE_LABELS = <?php echo json_encode(getApplicationTypeOptions()); ?>;
    
    /* ─── Greeting ──────────────────────────────────────────── */
    (function(){
        const h = new Date().getHours();
        const g = h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
        document.getElementById('greetingMsg').innerHTML = `${g}, <strong><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></strong>!`;
    })();

    /* ─── Modal Close ───────────────────────────────────────── */
    document.getElementById('closeModalBtn').addEventListener('click', () => {
        document.getElementById('applicationModal').style.display = 'none';
    });

    document.getElementById('applicationModal').addEventListener('click', function(e) {
        if (e.target === this) this.style.display = 'none';
    });

    /* ─── Search and Filters ────────────────────────────────── */
    const searchInput           = document.getElementById('searchInput');
    const applicationTypeFilter = document.getElementById('applicationTypeFilter');
    const tableBody             = document.getElementById('applicationsTableBody');
    const userBarangay          = "<?php echo $loggedInBarangay; ?>";
    const serverPagination      = document.getElementById('serverPagination');
    let applicationsPage        = 1;
    const requestedApplicantName = new URLSearchParams(window.location.search).get('search');
    if (requestedApplicantName) searchInput.value = requestedApplicantName;

    function buildAddressFromParts(app = {}) {
        return [
            app.house_no ?? app.houseNo ?? '',
            app.street ?? '',
            app.barangay ?? userBarangay ?? '',
            app.city ?? 'Pasig City',
            app.province ?? 'Metro Manila',
            app.zip_code ?? app.zipCode ?? ''
        ].map(part => String(part).trim()).filter(Boolean).join(', ');
    }

    function setValue(id, value) {
        const el = document.getElementById(id);
        if (el) el.value = value ?? '';
    }

    function setModalFormFieldVisibility(applicationType) {
        const zipRow = document.getElementById('zipLandmarkRow');
        const landmarkGroup = document.getElementById('landmarkGroup');
        const emergencyRow = document.getElementById('emergencyContactRow');
        const showZip = applicationType === 'senior' || applicationType === 'landbank';
        const showLandmark = applicationType === 'senior';
        const showEmergency = applicationType === 'senior';

        const setVisibility = (element, visible, display = 'flex') => {
            if (!element) return;
            element.style.display = visible ? display : 'none';
            element.querySelectorAll('input, textarea, select').forEach(field => field.disabled = !visible);
        };

        setVisibility(zipRow, showZip);
        setVisibility(landmarkGroup, showLandmark, 'block');
        setVisibility(emergencyRow, showEmergency);
    }

    function fetchApplications(page = applicationsPage) {
        applicationsPage = page;
        tableBody.innerHTML = '<tr><td colspan="9" style="text-align:center;">Loading applications…</td></tr>';
        
        let url = `../api/search_applications.php?query=${encodeURIComponent(searchInput.value)}&page=${applicationsPage}&per_page=25`;
        if (applicationTypeFilter.value) url += `&type=${encodeURIComponent(applicationTypeFilter.value)}`;
        // Verified is the final state and belongs in Barangay Records.
        url += `&status=${encodeURIComponent('Received,For Review')}`;
        if (userBarangay)                url += `&barangay=${encodeURIComponent(userBarangay)}`;

        fetch(url)
            .then(r => r.json())
            .then(result => {
                if (!result.success) throw new Error(result.message || 'Unable to load applications');
                const apps = result.data || [];
                tableBody.innerHTML = '';
                if (!apps.length) {
                    tableBody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--gray);">No applications found.</td></tr>';
                    renderServerPagination(result.pagination);
                    return;
                }

                apps.forEach(app => {
                    const state  = app.workflow_state || 'Received';
                    const displayState = app.display_status || state;
                    
                    let stateBadge = 'badge-received';
                    if (state === 'For Review') stateBadge = 'badge-review';
                    if (state === 'Verified')   stateBadge = 'badge-verified';
                    if (state === 'Approved')   stateBadge = 'badge-approved';
                    if (state === 'Released')   stateBadge = 'badge-released';

                    let blurryWarning = '';
                    if (state === 'Received' && app.return_comments) {
                        blurryWarning = `<div class="alert-blurry"><i class="fas fa-exclamation-triangle"></i> Resubmit scans: "${app.return_comments}"</div>`;
                    }

                    const typeLabel = TYPE_LABELS[app.application_type] || app.application_type;

                    tableBody.innerHTML += `
                        <tr class="applicant-row" data-id="${app.id}" tabindex="0" role="button" aria-label="View applicant details">
                            <td>
                                <div class="name-cell">
                                    <a href="#" class="name-link" data-id="${app.id}">${app.full_name}</a>
                                    ${blurryWarning}
                                </div>
                            </td>
                            <td>${typeLabel}</td>
                            <td>${app.birth_date}</td>
                            <td>${app.contact_number}</td>
                            <td>${new Date(app.date_submitted).toLocaleDateString()}</td>
                            <td><span class="badge ${stateBadge}">${displayState}</span></td>
                            <td style="max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${app.complete_address}</td>
                            <td>
                                ${state === 'Received' 
                                    ? `<button type="button" class="btn btn-warning btn-small archive-application-btn" data-id="${app.id}"><i class="fas fa-archive"></i> Archive</button>`
                                    : '<span style="color:var(--gray);font-size:0.75rem;font-style:italic;">Locked</span>'
                                }
                            </td>
                        </tr>`;
                });
                renderServerPagination(result.pagination);
            })
            .catch(err => {
                console.error(err);
                tableBody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--danger);">Error loading applications.</td></tr>';
            });
    }

    function renderServerPagination(pagination) {
        if (!serverPagination || !pagination || pagination.total_pages <= 1) {
            if (serverPagination) serverPagination.innerHTML = '';
            return;
        }
        serverPagination.innerHTML = `
            <button type="button" class="btn btn-small" ${pagination.has_previous ? '' : 'disabled'} data-page="${pagination.page - 1}">Previous</button>
            <span>Page ${pagination.page} of ${pagination.total_pages} · ${pagination.total} applications</span>
            <button type="button" class="btn btn-small" ${pagination.has_next ? '' : 'disabled'} data-page="${pagination.page + 1}">Next</button>`;
    }
    serverPagination.addEventListener('click', event => {
        const button = event.target.closest('button[data-page]');
        if (button && !button.disabled) fetchApplications(Number(button.dataset.page));
    });

    applicationTypeFilter.addEventListener('change', () => fetchApplications(1));
    let searchTimer;
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => fetchApplications(1), 250);
    });

    // Open the application from the name, View button, or anywhere on its row.
    tableBody.addEventListener('click', e => {
        const lnk = e.target.closest('.name-link');
        if (lnk) {
            e.preventDefault();
            openApplicationModal(lnk.dataset.id);
            return;
        }
        const archiveButton = e.target.closest('.archive-application-btn') || e.target.closest('.delete-application-btn');
        if (archiveButton) {
            e.preventDefault();
            archiveApplication(archiveButton.dataset.id);
            return;
        }
        const row = e.target.closest('.applicant-row[data-id]');
        if (row && !e.target.closest('a, button, input, select, textarea')) {
            openApplicationModal(row.dataset.id);
        }
    });
    tableBody.addEventListener('keydown', e => {
        const row = e.target.closest('.applicant-row[data-id]');
        if (row && e.target === row && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            openApplicationModal(row.dataset.id);
        }
    });

    fetchApplications();

    /* ─── Open Details Modal ────────────────────────────────── */
    let currentAppId = null;

    function openApplicationModal(appId) {
        currentAppId = appId;
        document.getElementById('applicationDetailForm').reset();
        document.getElementById('applicationModal').style.display = 'flex';
        document.querySelector('#applicationModal .modal-scroller').scrollTop = 0;

        // Clear previews / warning
        document.getElementById('returnedWarningBox').style.display = 'none';
        document.getElementById('proxyDocumentsSection').style.display = 'none';
        document.getElementById('proxyDocumentsList').innerHTML = '';

        // Reset stepper
        ['step-Received','step-For-Review','step-Verified'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.className = 'step';
        });

        fetch(`../api/get_application_details.php?id=${encodeURIComponent(appId)}`)
            .then(r => r.text())
            .then(text => {
                let app;
                try { app = JSON.parse(text); }
                catch(parseErr) {
                    window.showCarelinkResult("Server error. " + text.substring(0, 1000), false);
                    console.error("Non-JSON response:", text);
                    return;
                }
                if (app.error) { window.showCarelinkResult(app.error, false); return; }

                document.getElementById('modalAppTitle').textContent = `Edit application: ${app.full_name}`;
                document.getElementById('applicationId').value       = app.id_number;
                document.getElementById('applicationType').value     = app.application_type;
                document.getElementById('lastName').value            = app.lastName || '';
                document.getElementById('firstName').value           = app.firstName || '';
                document.getElementById('middleName').value          = app.middleName || '';
                document.getElementById('suffix').value              = app.suffix || '';
                document.getElementById('birthDate').value           = app.birth_date || '';
                document.getElementById('contactNumber').value       = app.contact_number || '';
                const displayAddress = app.complete_address || buildAddressFromParts(app);
                document.getElementById('completeAddress').value     = displayAddress;
                setValue('zipCode', app.zip_code || '');
                setValue('landmark', app.landmark || '');
                document.getElementById('emergencyContactName').value = app.emergency_contact_name || '';
                document.getElementById('emergencyContact').value     = app.emergency_contact || '';
                document.getElementById('emailAddress').value         = app.email_address || '';
                document.getElementById('additionalNotes').value      = app.additional_notes || '';

                const modalFormType = getOscaFormType(app.application_type, app.requested_benefit);
                populateOscaFields(app, '');
                setModalFormFieldVisibility(modalFormType);
                document.getElementById('dynamicDetailsSection').innerHTML = window.renderApplicationEditContext(app);

                // Toggle type-specific sections
                const pm = document.getElementById('pension-fields-modal');
                const bm = document.getElementById('burial-fields-modal');
                pm.style.display = 'none';
                bm.style.display = 'none';

                if (modalFormType === 'pension' || modalFormType === 'national_pension') {
                    pm.style.display = 'block';
                    document.getElementById('sssNumber').value = app.sss_number || '';
                    document.getElementById('pensionAmount').value = app.pension_amount || '';
                }
                if (modalFormType === 'burial') {
                    bm.style.display = 'block';
                    document.getElementById('dateOfDeath').value = app.date_of_death || '';
                    document.getElementById('relationshipToDeceased').value = app.relationship_to_deceased || '';
                }
                toggleOscaFormFields(modalFormType, '');

                // Stepper state highlighting
                const steps = ['Received','For Review','Verified'];
                const rawState = app.workflow_state || 'Received';
                const currentState = ['Approved','Released'].includes(rawState) ? 'Verified' : rawState;
                let idx = steps.indexOf(currentState);
                if (idx === -1) idx = 0;
                steps.forEach((s, i) => {
                    const el = document.getElementById('step-' + s.replace(' ','-'));
                    if (!el) return;
                    if (i < idx) el.classList.add('completed');
                    else if (i === idx) el.classList.add('active');
                });

                // Rejected Warning Banner
                document.getElementById('returnedWarningBox').style.display = 'none';
                if ((currentState === 'Received' || currentState === 'Submitted') && app.return_comments) {
                    document.getElementById('returnedWarningBox').style.display = 'block';
                    document.getElementById('returnedReasonText').textContent = `"${app.return_comments}"`;
                }

                // Render Proxy Docs
                // Fallback only if the shared document renderer is unavailable.
                if (app.is_proxy_application == 1 && typeof window.renderApplicationDocuments !== 'function') {
                    const proxySec  = document.getElementById('proxyDocumentsSection');
                    const proxyList = document.getElementById('proxyDocumentsList');
                    proxySec.style.display = 'block';
                    proxyList.innerHTML    = '';
                    const docs = [
                        { key: 'psa_birth_cert', label: 'PSA Birth Certificate' },
                        { key: 'barangay_residency', label: 'Barangay Residency' },
                        { key: 'comelec_cert', label: 'COMELEC Certificate' },
                        { key: 'proof_of_life', label: 'Current Senior Photo / Proof of Life' },
                        { key: 'auth_letter', label: 'Auth Letter' },
                        { key: 'proxy_id', label: 'Representative Government ID' },
                        { key: 'proxy_birth_cert', label: 'Representative Birth Certificate' },
                        { key: 'home_visitation_form', label: 'Home Visitation Form' },
                        { key: 'landbank_enrollment_form', label: 'Landbank Form' }
                    ];
                    docs.forEach(doc => {
                        if (app[doc.key]) {
                            const docUrl = `../api/get_document.php?id=${encodeURIComponent(appId)}&doc_type=${doc.key}`;
                            let ph = app[doc.key].toLowerCase().endsWith('.pdf')
                                ? `<div style="font-size:2.5rem; color:#ef4444;"><i class="fas fa-file-pdf"></i></div>`
                                : `<img src="${docUrl}" style="max-height:100%; max-width:100%; object-fit:contain;">`;
                            
                            proxyList.innerHTML += `
                                <div class="proxy-doc-card">
                                    <label>${doc.label}</label>
                                    <div class="image-placeholder" style="height:110px;">${ph}</div>
                                    <a href="${docUrl}" target="_blank" class="btn btn-primary btn-small" style="width:100%; justify-content:center; margin-top:8px;"><i class="fas fa-eye"></i> View</a>
                                </div>`;
                        }
                    });
                }

                // Edit state toggling
                const isEditable = (currentState === 'Received' || currentState === 'Submitted');
                const inputs = document.querySelectorAll('#applicationDetailForm input, #applicationDetailForm textarea, #applicationDetailForm select');
                inputs.forEach(inp => {
                    if (inp.id !== 'applicationType' && inp.id !== 'applicationId' && inp.id !== 'pensionAmount') {
                        if (isEditable) inp.removeAttribute('disabled');
                        else            inp.setAttribute('disabled', 'disabled');
                    }
                });

                // Barangay staff can correct a submitted requirement while the
                // application is still at the barangay stage.
                const canCorrectDocuments = currentState === 'Received' || currentState === 'Submitted';
                renderAllSubmittedDocuments(app, appId, { allowReplacement: canCorrectDocuments });

                // Action Buttons
                let btns = '';
                if (isEditable) {
                    btns += `<button type="submit" class="btn btn-accent"><i class="fas fa-save"></i> Save Changes</button>`;
                }
                btns += `<button type="button" class="btn btn-primary" onclick="openOfficialApplicationForm('${app.id_number}')"><i class="fas fa-file-pdf"></i> Generate Official Form</button>`;
                
                if (currentState === 'Received' || currentState === 'Submitted') {
                    btns += `<button type="button" class="btn" style="background:#3b82f6; color:#fff;" onclick="forwardToReviewDesk()"><i class="fas fa-paper-plane"></i> Submit to Review Desk</button>`;
                } else {
                    btns += `<span style="color:var(--gray);font-style:italic;font-size:0.84rem;margin-left:10px;"><i class="fas fa-lock"></i> Locked Status: [${currentState}]</span>`;
                }
                document.getElementById('modalFormActions').innerHTML = btns;

                // Paginated Audit History
                window.renderApplicationAuditHistory(app.history, 'timelineList', { pageSize: 5 });
            })
            .catch(err => { console.error(err); window.showCarelinkResult("Connection or network error: " + err.message, false); });
    }

    /* ─── Toast notification helper ─── */
    function showToast(message, isSuccess) {
        if (typeof window.showCarelinkResult === 'function') {
            window.showCarelinkResult(message, isSuccess);
            return;
        }
        const existing = document.getElementById('appResultModal');
        if (existing) existing.remove();
        const modal = document.createElement('div');
        modal.id = 'appResultModal';
        const color = isSuccess ? '#10b981' : '#ef4444';
        const title = isSuccess ? 'Success' : 'Unable to complete';
        modal.innerHTML = `
            <div class="app-result-dialog" role="alertdialog" aria-modal="true" aria-labelledby="appResultTitle">
                <div class="app-result-icon" style="background:${isSuccess ? 'rgba(16,185,129,.12)' : 'rgba(239,68,68,.12)'};color:${color};">
                    <i class="fas fa-${isSuccess ? 'check' : 'exclamation'}"></i>
                </div>
                <h3 id="appResultTitle">${title}</h3>
                <p>${String(message).replace(/[<>]/g, '')}</p>
                <button type="button" class="btn btn-primary app-result-close" style="background:${color};" onclick="document.getElementById('appResultModal')?.remove()">OK</button>
            </div>`;
        modal.style.cssText = 'position:fixed;inset:0;z-index:10000;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(15,23,42,.58);';
        document.body.appendChild(modal);
        modal.querySelector('.app-result-close')?.focus();
    }

    const applicationSubmissionNotice = <?php echo json_encode($applicationSubmissionNotice, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    if (applicationSubmissionNotice) {
        setTimeout(() => showToast(applicationSubmissionNotice, true), 100);
    }

    /* ─── Form submit handler ─── */
    document.getElementById('applicationDetailForm').addEventListener('submit', function(e) {
        e.preventDefault();
        // Sync the hidden applicationType input in case the disabled select wasn't captured
        const appTypeSelect = document.getElementById('applicationType');
        if (appTypeSelect) {
            document.getElementById('applicationTypeHidden').value = appTypeSelect.value;
        }

        const saveBtn = this.querySelector('button[type="submit"]');
        const origText = saveBtn ? saveBtn.innerHTML : '';
        if (saveBtn) { saveBtn.disabled = true; saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…'; }

        const fd = new FormData(this);
        // Always use the ID of the record currently open in the modal. This
        // prevents a stale/empty hidden input from saving to the wrong row.
        if (currentAppId) fd.set('applicationId', currentAppId);
        const hasReplacement = ['proofOfAddress', 'idImage'].some(id => document.getElementById(id)?.files?.length);
        const saveEndpoint = hasReplacement ? '../api/update_application_documents.php' : this.action;
        fetch(saveEndpoint, { method: 'POST', body: fd })
            .then(async response => {
                const text = await response.text();
                try { return JSON.parse(text); }
                catch (error) { throw new Error(`Server returned an invalid response (${response.status}).`); }
            })
            .then(data => {
                if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = origText; }
                if (data.success) {
                    showToast(data.message || 'Application updated successfully!', true);
                    ['proofOfAddress', 'idImage'].forEach(id => {
                        const input = document.getElementById(id);
                        if (input) input.value = '';
                    });
                    document.getElementById('applicationModal').style.display = 'none';
                    fetchApplications();
                } else {
                    showToast(data.message || 'Failed to save changes.', false);
                }
            })
            .catch(err => {
                if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = origText; }
                console.error(err);
                showToast('Could not save changes: ' + err.message, false);
            });
    });

    function renderAllSubmittedDocuments(app, appId, options = {}) {
        const section = document.getElementById('allSubmittedDocumentsSection');
        if (typeof window.renderApplicationDocuments === 'function') {
            section.style.display = 'block';
            section.innerHTML = window.renderApplicationDocuments(app, appId, options);
            return;
        }

        const list = document.getElementById('allSubmittedDocumentsList');
        const documents = [
            ['proof_of_address', 'Proof of Address', app.has_proof_of_address],
            ['id_image', 'ID / Identification Photo', app.has_id_image],
            ['psa_birth_cert', 'PSA Birth Certificate', app.psa_birth_cert],
            ['barangay_residency', 'Barangay Residency', app.barangay_residency],
            ['comelec_cert', 'COMELEC Certificate', app.comelec_cert],
            ['proof_of_life', 'Current Senior Photo / Proof of Life', app.proof_of_life],
            ['auth_letter', 'Authorization Letter', app.auth_letter],
            ['proxy_id', 'Representative Government ID', app.proxy_id],
            ['proxy_birth_cert', 'Representative Birth Certificate', app.proxy_birth_cert],
            ['home_visitation_form', 'Home Visitation Form', app.home_visitation_form],
            ['landbank_enrollment_form', 'Land Bank Enrollment Form', app.landbank_enrollment_form]
        ].filter(([, , exists]) => Boolean(exists));

        if (!documents.length) {
            section.style.display = 'none';
            list.innerHTML = '';
            return;
        }

        section.style.display = 'block';
        list.innerHTML = documents.map(([key, label, value]) => {
            const url = `../api/get_document.php?id=${encodeURIComponent(appId)}&doc_type=${encodeURIComponent(key)}`;
            const isPdf = typeof value === 'string' && value.toLowerCase().endsWith('.pdf');
            const preview = isPdf
                ? '<i class="fas fa-file-pdf" style="font-size:2.6rem;color:#ef4444;"></i>'
                : `<img src="${url}" alt="${label}" style="max-width:100%;max-height:100%;object-fit:contain;">`;
            return `<div style="padding:10px;background:#fff;border:1px solid #e2e8f0;border-radius:9px;">
                <div style="font-size:.72rem;font-weight:700;color:var(--gray);margin-bottom:8px;">${label}</div>
                <div class="image-placeholder" style="height:130px;display:flex;align-items:center;justify-content:center;">${preview}</div>
                <a href="${url}" target="_blank" class="btn btn-primary btn-small" style="width:100%;justify-content:center;margin-top:8px;"><i class="fas fa-eye"></i> View</a>
            </div>`;
        }).join('');
    }

    window.previewDocumentReplacement = function(input) {
        const file = input?.files?.[0];
        const card = input?.closest('.application-document');
        const preview = card?.querySelector('.application-document__preview');
        if (!file || !preview) return;

        if (preview.dataset.objectUrl) URL.revokeObjectURL(preview.dataset.objectUrl);
        if (file.type === 'application/pdf') {
            preview.innerHTML = '<i class="fas fa-file-pdf" aria-hidden="true"></i><span class="application-document__selected-file">PDF selected</span>';
            delete preview.dataset.objectUrl;
            return;
        }
        if (file.type.startsWith('image/')) {
            const objectUrl = URL.createObjectURL(file);
            preview.dataset.objectUrl = objectUrl;
            preview.innerHTML = `<img src="${objectUrl}" alt="Selected replacement document">`;
            return;
        }
        preview.innerHTML = '<i class="fas fa-file" aria-hidden="true"></i><span class="application-document__selected-file">File selected</span>';
    };

    window.replaceSubmittedDocument = async function(button) {
        const card = button.closest('.application-document');
        const fileInput = card?.querySelector('input[type="file"]');
        const appId = button.dataset.applicationId;
        const documentId = button.dataset.documentId;
        const documentKey = button.dataset.documentKey;
        if (!fileInput?.files?.length) {
            showToast('Choose the corrected document first.', false);
            return;
        }

        const file = fileInput.files[0];
        if (file.size > 8 * 1024 * 1024) {
            showToast('The replacement document must be 8 MB or smaller.', false);
            return;
        }

        const data = new FormData();
        data.append('applicationId', appId);
        if (documentId) {
            data.append('documentId', documentId);
            data.append('replacementDocument', file);
        } else {
            const legacyInputs = { proof_of_address: 'proofOfAddress', id_image: 'idImage' };
            if (!legacyInputs[documentKey]) {
                showToast('This older document cannot be replaced automatically. Please contact the administrator.', false);
                return;
            }
            data.append(legacyInputs[documentKey], file);
        }

        const original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
        try {
            const response = await fetch('../api/update_application_documents.php', { method: 'POST', body: data });
            const result = await response.json();
            if (!result.success) throw new Error(result.message || 'The document could not be replaced.');
            showToast(result.message, true);
            openApplicationModal(appId);
            fetchApplications();
        } catch (error) {
            console.error(error);
            showToast(error.message || 'The document could not be replaced.', false);
            button.disabled = false;
            button.innerHTML = original;
        }
    };

    /* ─── Action Functions ─── */
    function openExportModal() {
        const typeVal = document.getElementById('applicationTypeFilter')?.value || '';
        document.getElementById('exportType').value = typeVal || 'all';
        document.getElementById('exportDateFrom').value = '';
        document.getElementById('exportDateTo').value = '';
        document.getElementById('exportYear').value = 'all';
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

    document.addEventListener('DOMContentLoaded', function() {
        if (new URLSearchParams(window.location.search).get('openScanner') === '1') {
            const cleanUrl = new URL(window.location.href);
            cleanUrl.searchParams.delete('openScanner');
            window.history.replaceState({}, document.title, cleanUrl.pathname + cleanUrl.search + cleanUrl.hash);
            openProxyModal();
        }
        document.getElementById('closeExportModalBtn')?.addEventListener('click', closeExportModal);
        document.getElementById('cancelExportBtn')?.addEventListener('click', closeExportModal);
        document.getElementById('exportModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeExportModal();
        });
    });

    function exportQueuePdf() {
        openExportModal();
    }

    function forwardToReviewDesk() {
        window.showCarelinkConfirm("Forward this application to the department reviewer queue?", forwardApplicationRequest);
    }

    function forwardApplicationRequest() {
        const fd = new FormData();
        fd.append('applicationId', currentAppId);
        fd.append('action', 'next');
        fd.append('comments', 'Submitted by Barangay Staff for review evaluation.');

        fetch('../api/update_workflow_status.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showCarelinkResult(res.message, true);
                    document.getElementById('applicationModal').style.display = 'none';
                    fetchApplications();
                } else {
                    showCarelinkResult("Submission Error: " + res.message, false);
                }
            })
            .catch(err => { console.error(err); showCarelinkResult("Connection error during submission: " + err.message, false); });
    }

    function archiveApplication(appId) {
        window.showCarelinkConfirm('Are you sure you want to archive this application? You can restore it anytime from the Archive page.', () => archiveApplicationRequest(appId));
    }

    function archiveApplicationRequest(appId) {
        fetch('../api/archive_application.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${encodeURIComponent(appId)}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showCarelinkResult('Application archived successfully.', true);
                fetchApplications();
            } else {
                showCarelinkResult(data.message, false);
            }
        })
        .catch(err => { console.error(err); showCarelinkResult("Connection error during archiving: " + err.message, false); });
    }


    /* ─── Proxy QR Scanner Modals ─── */
    function openProxyModal() {
        const modal = document.getElementById('proxyModal');
        modal._returnFocus = document.activeElement;
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        document.getElementById('modalToken').value = '';
        document.getElementById('modalError').textContent = '';
        document.getElementById('modalToken').focus();
    }

    function closeProxyModal() {
        const modal = document.getElementById('proxyModal');
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        modal._returnFocus?.focus();
    }

    async function searchByQrToken() {
        const tokenInput = document.getElementById('modalToken').value.trim();
        const errDiv     = document.getElementById('modalError');
        if (!tokenInput) { errDiv.textContent = 'Token payload cannot be empty.'; return; }

        errDiv.innerHTML = '<span style="color:#2563eb;"><i class="fas fa-spinner fa-spin"></i> Parsing token…</span>';
        let token = tokenInput;

        if (tokenInput.includes('token=')) {
            try { token = new URL(tokenInput).searchParams.get('token') || tokenInput; } catch(e) {}
        }

        try {
            let txId = token;
            if (token.length > 50) {
                const r = await (await fetch(`../api/scan_proxy_qr.php?token=${encodeURIComponent(token)}`)).json();
                if (r.success && r.data && r.data.transactionId) {
                    txId = r.data.transactionId;
                } else {
                    errDiv.textContent = 'Failed to decrypt representative token.';
                    return;
                }
            }

            const check = await (await fetch(`../api/get_application_details.php?id=${encodeURIComponent(txId)}`)).json();
            if (check && !check.error) {
                closeProxyModal();
                openApplicationModal(txId);
            } else {
                errDiv.textContent = `Application ID: [${txId}] not found in records queue.`;
            }
        } catch(err) {
            console.error(err);
            errDiv.textContent = 'Connection error during QR token retrieval.';
        }
    }
</script>
<script src="../assets/js/vendor/html5-qrcode.min.js?v=2.3.8"></script>
<script src="../assets/js/simple-code-scanner.js?v=3"></script>
</body>
</html>
