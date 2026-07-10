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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue – Barangay <?php echo $loggedInBarangay; ?></title>
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
                <a href="new_application.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Application</a>
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
                <div class="filter-group">
                    <label>FSM State</label>
                    <select id="statusFilter">
                        <option value="">All States</option>
                        <option value="Received">Received</option>
                        <option value="For Review">For Review</option>
                        <option value="Verified">Verified</option>
                        <option value="Approved">Approved</option>
                        <option value="Released">Released</option>
                    </select>
                </div>
                <button class="btn btn-primary" id="applyFilterBtn"><i class="fas fa-filter"></i> Apply</button>
                <button class="btn btn-accent" id="scanQrBtn" onclick="openProxyModal()"><i class="fas fa-qrcode"></i> Scan Token</button>
            </div>

            <!-- Table -->
            <div class="table-wrap">
                <table class="records-tbl">
                    <thead>
                        <tr>
                            <th>Priority</th>
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
        </div>

        <div class="page-footer">Centralized Profiling and Record Authentication System &bull; Barangay <?php echo $loggedInBarangay; ?> &copy; <?php echo date('Y'); ?></div>
    </div>
</div>

<!-- ──────────────────────────────────────────────────────────────────── -->
<!-- Application Detail Modal                                             -->
<!-- ──────────────────────────────────────────────────────────────────── -->
<div id="applicationModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <h2><i class="fas fa-file-invoice"></i> <span id="modalAppTitle">Application Profile Details</span></h2>
            <button class="modal-close" id="closeModalBtn">&times;</button>
        </div>
        <div class="modal-scroller">

            <!-- FSM Stepper -->
            <div class="stepper">
                <div class="step" id="step-Received"><div class="step-circle">1</div><div class="step-label">Received</div></div>
                <div class="step" id="step-For-Review"><div class="step-circle">2</div><div class="step-label">For Review</div></div>
                <div class="step" id="step-Verified"><div class="step-circle">3</div><div class="step-label">Verified</div></div>
                <div class="step" id="step-Approved"><div class="step-circle">4</div><div class="step-label">Approved</div></div>
                <div class="step" id="step-Released"><div class="step-circle">5</div><div class="step-label">Released</div></div>
            </div>

            <!-- Return Warning Box -->
            <div id="returnedWarningBox" style="display:none; background-color: rgba(239, 68, 68, 0.08); border: 1.5px solid var(--danger); padding: 14px; border-radius: 10px; margin-bottom: 22px; color: #b91c1c; font-size: 0.88rem;">
                <strong><i class="fas fa-exclamation-triangle"></i> SCANS REJECTED BY OFFICE REVIEWER:</strong>
                <p id="returnedReasonText" style="margin-top: 5px; font-style: italic;"></p>
                <p style="margin-top: 10px; font-weight: bold; text-decoration: underline;">Please upload clean, high-resolution scans below and save changes to update.</p>
            </div>

            <div class="modal-grid">
                <!-- LEFT: Details Form -->
                <div>
                    <form id="applicationDetailForm" method="POST" action="../api/update_application.php" enctype="multipart/form-data">
                        <input type="hidden" id="applicationId" name="applicationId">

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
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="houseNo">House / Lot / Block No.</label>
                                    <input type="text" id="houseNo" name="houseNo" oninput="syncCompleteAddressFromParts()">
                                </div>
                                <div class="form-group">
                                    <label for="street">Street / Purok / Village</label>
                                    <input type="text" id="street" name="street" oninput="syncCompleteAddressFromParts()">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="city">City</label>
                                    <input type="text" id="city" name="city" value="Pasig City" oninput="syncCompleteAddressFromParts()">
                                </div>
                                <div class="form-group">
                                    <label for="province">Province</label>
                                    <input type="text" id="province" name="province" value="Metro Manila" oninput="syncCompleteAddressFromParts()">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="zipCode">ZIP Code</label>
                                    <input type="text" id="zipCode" name="zipCode" oninput="syncCompleteAddressFromParts()">
                                </div>
                                <div class="form-group">
                                    <label for="landmark">Landmark</label>
                                    <input type="text" id="landmark" name="landmark">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="emergencyContactName">Emergency Contact Name</label>
                                    <input type="text" id="emergencyContactName" name="emergencyContactName">
                                </div>
                                <div class="form-group">
                                    <label for="emergencyContact">Emergency Contact Number</label>
                                    <input type="text" id="emergencyContact" name="emergencyContact">
                                </div>
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
                                    <label for="pensionAmount">Verified Monthly Pension (PHP)</label>
                                    <input type="number" step="0.01" id="pensionAmount" name="pensionAmount" readonly>
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
                            <h3><i class="fas fa-file-alt"></i> Required Documents upload</h3>
                            <div class="form-group" style="margin-bottom:14px;">
                                <label for="proofOfAddress">Proof of Address</label>
                                <input type="file" id="proofOfAddress" name="proofOfAddress">
                                <div class="image-placeholder" id="proofOfAddressPreview"></div>
                            </div>
                            <div class="form-group">
                                <label for="idImage">ID Image</label>
                                <input type="file" id="idImage" name="idImage">
                                <div class="image-placeholder" id="idImagePreview"></div>
                            </div>
                        </div>

                        <!-- Proxy Documents -->
                        <div class="form-section" id="proxyDocumentsSection" style="display:none;">
                            <h3><i class="fas fa-user-shield"></i> Submitted Proxy Documents</h3>
                            <div id="proxyDocumentsList" style="display:grid; grid-template-columns:1fr 1fr; gap:12px;"></div>
                        </div>

                        <div class="form-actions" id="modalFormActions" style="display:flex; gap:10px; margin-top:20px;"></div>
                    </form>
                </div>

                <!-- RIGHT: Audit Trail Timeline -->
                <div>
                    <div class="section-title"><i class="fas fa-clock-rotate-left"></i> Audit Trail History Log</div>
                    <div class="timeline" id="timelineList"></div>
                </div>
            </div>

        </div><!-- /.modal-scroller -->
    </div><!-- /.modal-box -->
</div><!-- /#applicationModal -->

<!-- QR scan Modal -->
<div id="proxyModal" class="modal-overlay">
    <div class="modal-box" style="max-width:500px; margin: 10% auto;">
        <div class="modal-head">
            <h2><i class="fas fa-qrcode"></i> Scan Proxy QR Token</h2>
            <button class="modal-close" onclick="closeProxyModal()">&times;</button>
        </div>
        <div class="modal-scroller" style="padding:20px;">
            <p style="font-size:0.82rem; color:var(--gray); margin-bottom:14px;">
                Paste the encrypted QR token link or type the unique transaction priority token (e.g. PRX-XXXXXX) to load profiles.
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

<script src="../assets/js/sidebar-toggle.js"></script>
<script src="../assets/js/dark-mode.js"></script>
<script src="../assets/js/osca-form-fields.js"></script>
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
    const statusFilter          = document.getElementById('statusFilter');
    const applyFilterBtn        = document.getElementById('applyFilterBtn');
    const tableBody             = document.getElementById('applicationsTableBody');
    const userBarangay          = "<?php echo $loggedInBarangay; ?>";

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

    function syncCompleteAddressFromParts() {
        const address = buildAddressFromParts({
            houseNo: document.getElementById('houseNo')?.value,
            street: document.getElementById('street')?.value,
            barangay: userBarangay,
            city: document.getElementById('city')?.value || 'Pasig City',
            province: document.getElementById('province')?.value || 'Metro Manila',
            zipCode: document.getElementById('zipCode')?.value
        });

        if (address) {
            setValue('completeAddress', address);
        }
    }

    function fetchApplications() {
        tableBody.innerHTML = '<tr><td colspan="9" style="text-align:center;">Loading applications…</td></tr>';
        
        let url = `../api/search_applications.php?query=${encodeURIComponent(searchInput.value)}`;
        if (applicationTypeFilter.value) url += `&type=${encodeURIComponent(applicationTypeFilter.value)}`;
        if (statusFilter.value)          url += `&status=${encodeURIComponent(statusFilter.value)}`;
        if (userBarangay)                url += `&barangay=${encodeURIComponent(userBarangay)}`;

        fetch(url)
            .then(r => r.json())
            .then(apps => {
                tableBody.innerHTML = '';
                if (!apps.length) {
                    tableBody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--gray);">No applications found.</td></tr>';
                    return;
                }

                apps.forEach(app => {
                    const isHigh = app.priority_level === 'high';
                    const state  = app.workflow_state || 'Received';
                    
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
                        <tr class="${isHigh ? 'priority-high-row' : ''}">
                            <td>${isHigh ? '<span class="priority-badge"><i class="fas fa-star"></i> HIGH</span>' : '<span style="color:var(--gray);font-size:0.75rem;">Normal</span>'}</td>
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
                            <td><span class="badge ${stateBadge}">${state}</span></td>
                            <td style="max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${app.complete_address}</td>
                            <td>
                                ${state === 'Received' 
                                    ? `<button class="btn btn-danger btn-small" onclick="deleteApplication(${app.id})"><i class="fas fa-trash"></i> Delete</button>`
                                    : '<span style="color:var(--gray);font-size:0.75rem;font-style:italic;">Locked</span>'
                                }
                            </td>
                        </tr>`;
                });
            })
            .catch(err => {
                console.error(err);
                tableBody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--danger);">Error loading applications.</td></tr>';
            });
    }

    applyFilterBtn.addEventListener('click', fetchApplications);
    statusFilter.addEventListener('change', fetchApplications);
    applicationTypeFilter.addEventListener('change', fetchApplications);
    searchInput.addEventListener('keyup', e => { if (e.key === 'Enter') fetchApplications(); });

    // Table Event delegation for clicking Name links
    tableBody.addEventListener('click', e => {
        const lnk = e.target.closest('.name-link');
        if (lnk) { e.preventDefault(); openApplicationModal(lnk.dataset.id); }
    });

    fetchApplications();

    /* ─── Open Details Modal ────────────────────────────────── */
    let currentAppId = null;

    function openApplicationModal(appId) {
        currentAppId = appId;
        document.getElementById('applicationModal').style.display = 'block';

        // Clear previews / warning
        document.getElementById('proofOfAddressPreview').innerHTML = '';
        document.getElementById('idImagePreview').innerHTML = '';
        document.getElementById('returnedWarningBox').style.display = 'none';
        document.getElementById('proxyDocumentsSection').style.display = 'none';
        document.getElementById('proxyDocumentsList').innerHTML = '';

        // Reset stepper
        ['step-Received','step-For-Review','step-Verified','step-Approved','step-Released'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.className = 'step';
        });

        fetch(`../api/get_application_details.php?id=${encodeURIComponent(appId)}`)
            .then(r => r.text())
            .then(text => {
                let app;
                try { app = JSON.parse(text); }
                catch(parseErr) {
                    alert("Server error. Check below for raw output:\n\n" + text.substring(0, 1000));
                    console.error("Non-JSON response:", text);
                    return;
                }
                if (app.error) { alert(app.error); return; }

                document.getElementById('modalAppTitle').textContent = `Reviewing: ${app.full_name} (${app.id_number})`;
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
                setValue('houseNo', app.house_no || '');
                setValue('street', app.street || '');
                setValue('city', app.city || 'Pasig City');
                setValue('province', app.province || 'Metro Manila');
                setValue('zipCode', app.zip_code || '');
                setValue('landmark', app.landmark || '');
                document.getElementById('emergencyContactName').value = app.emergency_contact_name || '';
                document.getElementById('emergencyContact').value     = app.emergency_contact || '';

                populateOscaFields(app, '');

                // Toggle type-specific sections
                const pm = document.getElementById('pension-fields-modal');
                const bm = document.getElementById('burial-fields-modal');
                pm.style.display = 'none';
                bm.style.display = 'none';

                if (app.application_type === 'pension' || app.application_type === 'national_pension') {
                    pm.style.display = 'block';
                    document.getElementById('sssNumber').value = app.sss_number || '';
                    document.getElementById('pensionAmount').value = app.pension_amount || '';
                }
                if (app.application_type === 'burial') {
                    bm.style.display = 'block';
                    document.getElementById('dateOfDeath').value = app.date_of_death || '';
                    document.getElementById('relationshipToDeceased').value = app.relationship_to_deceased || '';
                }
                toggleOscaFormFields(app.application_type, '');

                // Stepper state highlighting
                const steps = ['Received','For Review','Verified','Approved','Released'];
                const currentState = app.workflow_state || 'Received';
                let idx = steps.indexOf(currentState);
                if (idx === -1) idx = 0;
                steps.forEach((s, i) => {
                    const el = document.getElementById('step-' + s.replace(' ','-'));
                    if (!el) return;
                    if (i < idx) el.classList.add('completed');
                    else if (i === idx) el.classList.add('active');
                });

                // File previews
                document.getElementById('proofOfAddressPreview').innerHTML = app.has_proof_of_address
                    ? `<img src="../api/get_document.php?id=${appId}&doc_type=proof_of_address" alt="Proof of Address">`
                    : '<span style="color:var(--gray);font-size:0.8rem;">No file uploaded</span>';
                
                document.getElementById('idImagePreview').innerHTML = app.has_id_image
                    ? `<img src="../api/get_document.php?id=${appId}&doc_type=id_image" alt="ID Image">`
                    : '<span style="color:var(--gray);font-size:0.8rem;">No file uploaded</span>';

                // Rejected Warning Banner
                if ((currentState === 'Received' || currentState === 'Submitted') && app.return_comments) {
                    document.getElementById('returnedWarningBox').style.display = 'block';
                    document.getElementById('returnedReasonText').textContent = `"${app.return_comments}"`;
                }

                // Render Proxy Docs
                if (app.is_proxy_application == 1) {
                    const proxySec  = document.getElementById('proxyDocumentsSection');
                    const proxyList = document.getElementById('proxyDocumentsList');
                    proxySec.style.display = 'block';
                    proxyList.innerHTML    = '';
                    const docs = [
                        { key: 'psa_birth_cert', label: 'PSA Birth Certificate' },
                        { key: 'barangay_residency', label: 'Barangay Residency' },
                        { key: 'comelec_cert', label: 'COMELEC Certificate' },
                        { key: 'proof_of_life', label: 'Proof of Life (In Bed)' },
                        { key: 'auth_letter', label: 'Auth Letter' },
                        { key: 'proxy_id', label: 'Proxy Government ID' },
                        { key: 'proxy_birth_cert', label: 'Proxy Birth Cert' },
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

                // Action Buttons
                let btns = '';
                if (isEditable) {
                    btns += `<button type="submit" class="btn btn-accent"><i class="fas fa-save"></i> Save Changes</button>`;
                }
                btns += `<button type="button" class="btn btn-primary" onclick="exportApplicationDetails('${app.id_number}')"><i class="fas fa-print"></i> Print Official Form</button>`;
                
                if (currentState === 'Received' || currentState === 'Submitted') {
                    btns += `<button type="button" class="btn" style="background:#3b82f6; color:#fff;" onclick="forwardToReviewDesk()"><i class="fas fa-paper-plane"></i> Submit to Review Desk</button>`;
                } else {
                    btns += `<span style="color:var(--gray);font-style:italic;font-size:0.84rem;margin-left:10px;"><i class="fas fa-lock"></i> Locked State: [${currentState}]</span>`;
                }
                document.getElementById('modalFormActions').innerHTML = btns;

                // Audit History
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
                    th = '<p style="color:var(--gray);font-size:0.85rem;font-style:italic;">No transition records logged.</p>';
                }
                document.getElementById('timelineList').innerHTML = th;
            })
            .catch(err => { console.error(err); alert("Connection or network error: " + err.message); });
    }

    /* ─── Form submit handler ─── */
    document.getElementById('applicationDetailForm').addEventListener('submit', function(e) {
        e.preventDefault();
        syncCompleteAddressFromParts();
        const fd = new FormData(this);
        fetch(this.action, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    document.getElementById('applicationModal').style.display = 'none';
                    fetchApplications();
                } else {
                    alert(data.message);
                }
            })
            .catch(err => { console.error(err); alert("An error occurred during updating: " + err.message); });
    });

    /* ─── Action Functions ─── */
    function forwardToReviewDesk() {
        if (!confirm("Forward this application to the department reviewer queue?")) return;
        
        const fd = new FormData();
        fd.append('applicationId', currentAppId);
        fd.append('action', 'next');
        fd.append('comments', 'Submitted by Barangay Staff for review evaluation.');

        fetch('../api/update_fsm_state.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    alert(res.message);
                    document.getElementById('applicationModal').style.display = 'none';
                    fetchApplications();
                } else {
                    alert("Submission Error: " + res.message);
                }
            })
            .catch(err => { console.error(err); alert("Connection error during submission: " + err.message); });
    }

    function deleteApplication(appId) {
        if (!confirm('Are you sure you want to delete this application? This action is permanent.')) return;
        
        fetch('../api/delete_application.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${encodeURIComponent(appId)}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Application deleted successfully.');
                fetchApplications();
            } else {
                alert(data.message);
            }
        })
        .catch(err => { console.error(err); alert("Connection error during deletion: " + err.message); });
    }

    function exportApplicationDetails(idNo) {
        window.open(`../api/export_application_pdf.php?id=${encodeURIComponent(idNo)}`, '_blank');
    }

    /* ─── Proxy QR Scanner Modals ─── */
    function openProxyModal() {
        document.getElementById('proxyModal').style.display = 'block';
        document.getElementById('modalToken').value = '';
        document.getElementById('modalError').textContent = '';
    }

    function closeProxyModal() {
        document.getElementById('proxyModal').style.display = 'none';
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
                    errDiv.textContent = 'Failed to decrypt proxy token.';
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
</body>
</html>
