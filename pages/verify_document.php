<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/application_types.php';

// Check if user is logged in and authorized
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['department_admin', 'super_admin'])) {
    header("Location: ../index.php");
    exit();
}

// Fetch stats for the queue counters
$statsQuery = "SELECT 
    COUNT(*) as total_queue,
    SUM(CASE WHEN priority_level = 'high' THEN 1 ELSE 0 END) as high_priority_count,
    SUM(CASE WHEN COALESCE(workflow_state, 'Received') = 'For Review' THEN 1 ELSE 0 END) as for_review,
    SUM(CASE WHEN COALESCE(workflow_state, 'Received') = 'Verified' THEN 1 ELSE 0 END) as verified
    FROM applications WHERE COALESCE(workflow_state, status) NOT IN ('Approved', 'Released')";
$statsStmt = $conn->prepare($statsQuery);
$statsStmt->execute();
$queueStats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$totalQCount   = $queueStats['total_queue'] ?? 0;
$highPCount    = $queueStats['high_priority_count'] ?? 0;
$forReviewC    = $queueStats['for_review'] ?? 0;
$verifiedC     = $queueStats['verified'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SENIORLINK — Document Verification Terminal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/department-sidebar.css?v=1.1">
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
        .page-header-left h1 { font-size: 1.7rem; font-weight: 800; color: var(--primary); margin: 0; }
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
                <div>
                    <div class="stat-label">Active Queue</div>
                    <div class="stat-value"><?php echo $totalQCount; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon amber"><i class="fas fa-star"></i></div>
                <div>
                    <div class="stat-label">High Priority (Bedridden)</div>
                    <div class="stat-value"><?php echo $highPCount; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-search"></i></div>
                <div>
                    <div class="stat-label">For evaluation</div>
                    <div class="stat-value"><?php echo $forReviewC; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-file-signature"></i></div>
                <div>
                    <div class="stat-label">Verified (Sign-off)</div>
                    <div class="stat-value"><?php echo $verifiedC; ?></div>
                </div>
            </div>
        </div>

        <!-- Review Queue Card -->
        <div class="queue-card">
            <div class="queue-card-header">
                <h2><i class="fas fa-list-ol"></i> Evaluation Review Queue</h2>
            </div>

            <!-- Table -->
            <div class="table-wrap">
                <table class="records-tbl">
                    <thead>
                        <tr>
                            <th>Priority</th>
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
                        $sql = "SELECT id_number as id, full_name, application_type, barangay, date_submitted, status, workflow_state, priority_level 
                                FROM applications 
                                ORDER BY CASE WHEN priority_level = 'high' THEN 0 ELSE 1 END, date_submitted DESC";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute();
                        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (count($result) === 0): ?>
                            <tr><td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-folder-open"></i>
                                    <p>No active applications in the review queue</p>
                                </div>
                            </td></tr>
                        <?php else:
                            foreach ($result as $row):
                                $isHigh = ($row['priority_level'] === 'high');
                                $rowClass = $isHigh ? 'priority-high-row' : '';
                                
                                $state = $row['workflow_state'] ?: 'Received';
                                $stateBadgeClass = 'badge-received';
                                if ($state === 'For Review') $stateBadgeClass = 'badge-review';
                                if ($state === 'Verified') $stateBadgeClass = 'badge-verified';
                                if ($state === 'Approved') $stateBadgeClass = 'badge-approved';
                                if ($state === 'Released') $stateBadgeClass = 'badge-released';

                                $typeLabel = applicationTypeLabel($row['application_type']);
                            ?>
                            <tr class="<?php echo $rowClass; ?>">
                                <td>
                                    <?php if ($isHigh): ?>
                                        <span class="priority-badge"><i class="fas fa-star"></i> HIGH</span>
                                    <?php else: ?>
                                        <span style="color:#94a3b8; font-size:0.8rem;">Normal</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="name-cell">
                                        <div class="full-name"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                        <div class="app-id"><?php echo htmlspecialchars($row['id']); ?></div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($typeLabel); ?></td>
                                <td><?php echo htmlspecialchars($row['barangay']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($row['date_submitted'])); ?></td>
                                <td><span class="badge <?php echo $stateBadgeClass; ?>"><?php echo htmlspecialchars($state); ?></span></td>
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
            <h2><i class="fas fa-file-shield"></i> <span id="modalAppTitle">Review Application Profile</span></h2>
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
                    </div>

                    <!-- Dynamic (type-specific) section -->
                    <div id="dynamicDetailsSection"></div>

                    <!-- Document previews -->
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

                <!-- RIGHT: Workflow actions + Audit timeline -->
                <div>
                    <!-- FSM Control Actions -->
                    <div class="workflow-control">
                        <h3><i class="fas fa-sliders-h" style="color:var(--accent); margin-right:5px;"></i> Workflow Control Actions</h3>
                        <div class="workflow-instructions" id="fsmInstructions">Loading instructions…</div>
                        <textarea id="fsmComment" class="workflow-comment" placeholder="Write transition details or reason for return/blurry scan here…"></textarea>
                        
                        <div class="workflow-buttons">
                            <button type="button" class="btn btn-primary" id="btnNextState" onclick="submitFsmTransition('next')">Advance State</button>
                            <button type="button" class="btn btn-secondary" id="btnReturnState" onclick="submitFsmTransition('return')">Return to Barangay</button>
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

<script src="../assets/js/sidebar-toggle.js"></script>
<script>
    /* ─── Greeting ──────────────────────────────────────────── */
    (function(){
        const h = new Date().getHours();
        const g = h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
        document.getElementById('greetingMsg').innerHTML = `${g}, <strong><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></strong>!`;
    })();

    /* ─── Modal open/close ──────────────────────────────────── */
    let currentAppId = null;
    let currentWorkflowState = 'Received';

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelector('.records-tbl').addEventListener('click', function(e) {
            const btn = e.target.closest('.view-details-btn');
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
        currentAppId = appId;

        // Reset placeholders
        document.getElementById('modalAppTitle').textContent  = 'Loading…';
        document.getElementById('fsmComment').value = "";
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

                currentWorkflowState = app.workflow_state || 'Received';

                /* ── Title ── */
                document.getElementById('modalAppTitle').textContent = `Reviewing: ${app.full_name} (${app.id_number})`;

                /* ── Stepper ── */
                const steps = ['Received','For Review','Verified','Approved','Released'];
                let idx = steps.indexOf(currentWorkflowState);
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
                document.getElementById('infoBirth').textContent    = `${app.birth_date} (${age} years old)`;
                document.getElementById('infoContact').textContent  = app.contact_number || '—';
                document.getElementById('infoAddress').textContent  = app.complete_address || '—';
                document.getElementById('infoBarangay').textContent = app.barangay || '—';

                /* ── Compliance Checks ── */
                let ch = '';
                let approvalBlocked = false;
                let approvalBlockReason = "";

                const aiScore  = parseFloat(app.ai_confidence_score || 95);
                const aiFlag   = (app.ai_status || '') === 'FLAGGED_ANOMALY';
                if (aiScore >= 90 && !aiFlag) {
                    ch += `<div class="compliance-item" style="background:rgba(16,185,129,0.06);border-left:3px solid var(--success);padding:6px 8px;border-radius:5px;">
                        <span style="font-weight:600;"><i class="fas fa-robot" style="color:var(--success)"></i> CNN AI Document Verification</span>
                        <span class="pass-tag"><i class="fas fa-shield-halved"></i> AI-VERIFIED — ${aiScore.toFixed(1)}% Authenticity Score</span>
                    </div>`;
                } else {
                    ch += `<div class="compliance-item" style="background:rgba(239,68,68,0.08);border-left:3px solid var(--danger);padding:6px 8px;border-radius:5px;">
                        <span style="font-weight:600;"><i class="fas fa-robot" style="color:var(--danger)"></i> CNN AI Document Verification</span>
                        <span class="fail-tag"><i class="fas fa-triangle-exclamation"></i> ANOMALY DETECTED IN BARANGAY SUBMISSION — ${aiScore.toFixed(1)}% Authenticity Score</span>
                    </div>`;
                }

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
                    const ed = calculateWorkingDays(app.date_of_death);
                    if (ed <= 30) {
                        ch += `<div class="compliance-item"><span>Filing Deadline Check (Pasig Ord. 3/2026)</span><span class="pass-tag"><i class="fas fa-circle-check"></i> PASS: Filed in ${ed} working days</span></div>`;
                    } else {
                        approvalBlocked = true;
                        approvalBlockReason = `POLICY VIOLATION: Submitted beyond the 30-working-day limit. Days elapsed: ${ed} (Pasig Ord. 3/2026).`;
                        ch += `<div class="compliance-item"><span>Filing Deadline Check (Pasig Ord. 3/2026)</span><span class="fail-tag"><i class="fas fa-circle-xmark"></i> FAIL: Outside 30-working-day limit (${ed} days elapsed)</span></div>`;
                    }
                }

                if (app.priority_level === 'high') {
                    ch += `<div class="compliance-item" style="background:rgba(245,158,11,0.08);padding:5px;border-radius:4px;">
                        <span>Priority Queue Placement</span>
                        <span style="color:#d97706;font-weight:700;"><i class="fas fa-star"></i> High-Priority (Bedridden Senior)</span>
                    </div>`;
                }
                document.getElementById('complianceList').innerHTML = ch;
                /* ── Dynamic Details (Complete details rendering) ── */
                document.getElementById('dynamicDetailsSection').innerHTML = getCompleteDetailsHtml(app);

                /* ── Document Previews ── */
                document.getElementById('previewProof').innerHTML = app.has_proof_of_address
                    ? `<img src="../api/get_document.php?id=${appId}&doc_type=proof_of_address" alt="Proof of Address">`
                    : '<span style="color:var(--gray);font-size:0.8rem;"><i class="fas fa-file-slash"></i> No document uploaded</span>';

                document.getElementById('previewIdImage').innerHTML = app.has_id_image
                    ? `<img src="../api/get_document.php?id=${appId}&doc_type=id_image" alt="ID Document Photo">`
                    : '<span style="color:var(--gray);font-size:0.8rem;"><i class="fas fa-file-slash"></i> No document uploaded</span>';

                /* ── FSM Operations ── */
                const btnNext = document.getElementById('btnNextState');
                const btnReturn = document.getElementById('btnReturnState');
                const instruction = document.getElementById('fsmInstructions');

                btnReturn.style.display = 'block';
                btnNext.style.display = 'block';

                const existingBlockBanner = document.getElementById('policyBlockBanner');
                if (existingBlockBanner) existingBlockBanner.remove();

                if (currentWorkflowState === 'Received') {
                    instruction.innerHTML = "<strong>State Action:</strong> Forward this application to the department desk for detailed evaluation.";
                    btnNext.textContent = "Forward to Review Desk";
                    btnReturn.style.display = 'none';
                } else if (currentWorkflowState === 'For Review') {
                    instruction.innerHTML = "<strong>State Action:</strong> Mark document audits as Verified and lock the compliance records.";
                    btnNext.textContent = "Verify Application";
                } else if (currentWorkflowState === 'Verified') {
                    instruction.innerHTML = "<strong>State Action:</strong> Authorize senior credentials and approve for payroll benefits distribution.";
                    btnNext.textContent = "Approve for Payroll";

                    if (approvalBlocked) {
                        btnNext.disabled = true;
                        btnNext.style.opacity = '0.4';
                        btnNext.style.cursor  = 'not-allowed';
                        
                        const blockBanner = document.createElement('div');
                        blockBanner.id = 'policyBlockBanner';
                        blockBanner.style.cssText = `
                            background: rgba(239,68,68,0.1);
                            border: 1.5px solid #ef4444;
                            border-radius: 8px;
                            padding: 10px 14px;
                            margin-bottom: 12px;
                            font-size: 0.82rem;
                            color: #b91c1c;
                            font-weight: 600;
                            line-height: 1.5;
                        `;
                        blockBanner.innerHTML = `<i class="fas fa-ban" style="margin-right:6px;"></i> ${approvalBlockReason}`;
                        btnNext.parentElement.insertBefore(blockBanner, btnNext);
                    } else {
                        btnNext.disabled = false;
                        btnNext.style.opacity = '';
                        btnNext.style.cursor  = '';
                    }
                } else if (currentWorkflowState === 'Approved') {
                    instruction.innerHTML = "<strong>State Action:</strong> Finalize benefits check and mark credentials as Released.";
                    btnNext.textContent = "Release Benefits";
                } else if (currentWorkflowState === 'Released') {
                    instruction.innerHTML = "<strong>State Action:</strong> The workflow has completed. Benefits are released to the citizen.";
                    btnNext.style.display = 'none';
                    btnReturn.style.display = 'none';
                }

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
                    th = '<p style="color:var(--gray);font-size:0.85rem;">No transition history logged.</p>';
                }
                document.getElementById('timelineList').innerHTML = th;
            })
            .catch(err => {
                console.error(err);
                alert("Failed to load application profile.");
            });
    }

    async function submitFsmTransition(action) {
        const comment = document.getElementById('fsmComment').value.trim();

        if (action === 'return' && !comment) {
            alert("Please input comment remarks explaining why this application is being returned to the Barangay (e.g. Blurry Documents / Missing IDs).");
            return;
        }

        if (!confirm(`Confirm triggering workflow state transition?`)) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append('applicationId', currentAppId);
            formData.append('action', action);
            formData.append('comments', comment);

            const response = await fetch('../api/update_fsm_state.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                alert(result.message);
                closeModal();
                location.reload();
            } else {
                alert("Workflow State Transition Error: " + result.message);
            }
        } catch (err) {
            console.error(err);
            alert("Connection error during state transition.");
        }
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
        personalHtml += getFieldHtml("Email Address", app.email_address);

        if (personalHtml) {
            dynamicHtml += `
                <div class="section-title" style="margin-top:20px;"><i class="fas fa-id-card-clip"></i> Personal Profile</div>
                <div class="info-grid">
                    ${personalHtml}
                </div>`;
        }

        // 2. Household & Housing
        let housingHtml = "";
        housingHtml += getFieldHtml("House No", app.house_no);
        housingHtml += getFieldHtml("Street", app.street);
        housingHtml += getFieldHtml("City", app.city);
        housingHtml += getFieldHtml("Province", app.province);
        housingHtml += getFieldHtml("Zip Code", app.zip_code);
        housingHtml += getFieldHtml("Landmark", app.landmark);
        housingHtml += getFieldHtml("Owns House", app.owns_house);
        housingHtml += getFieldHtml("Renter", app.is_renter);

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
            proxyHtml += getFieldHtml("Proxy Name", app.proxy_name);
            proxyHtml += getFieldHtml("Relationship", app.proxy_relationship);
            proxyHtml += getFieldHtml("Proxy Contact", app.proxy_contact_number);
            proxyHtml += getFieldHtml("Proxy Token", app.proxy_token);
        }
        if (proxyHtml) {
            dynamicHtml += `
                <div class="section-title" style="margin-top:20px;"><i class="fas fa-user-clock"></i> Proxy Representative Details</div>
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
</script>
</body>
</html>
