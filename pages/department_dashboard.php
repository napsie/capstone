<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/audit_logger.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'department_admin') {
    header('Location: ../index.php');
    exit;
}

// Backfill one audit event for sessions established before login auditing was enabled.
if (empty($_SESSION['login_audit_recorded'])) {
    if (logAudit($conn, 'LOGIN', 'Active Department Administrator session confirmed.')) {
        $_SESSION['login_audit_recorded'] = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centralized Profiling and Record Authentication System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/department-sidebar.css?v=4">
    <style>
        :root {
            --primary: #0f172a;
            --secondary: #1e3a5f;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --light: #f8fafc;
            --dark: #020617;
            --gray: #94a3b8;
            --light-gray: #e2e8f0;
            --bg: #f1f5f9;
            --text: #0f172a;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--bg);
            color: var(--text);
            line-height: 1.6;
            height: 100vh;
            overflow: auto;
        }
        
        /* Sidebar styles are handled by department-sidebar.css */
        
        /* Main Content Styles */
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ddd;
        }
        
        .header-content {
            display: flex;
            flex-direction: column;
        }
        
        .welcome-message {
            font-size: 1.2rem;
            color: var(--gray);
            margin-bottom: 5px;
        }
        
        .header h1 {
            font-family: 'Inter', sans-serif;
            color: var(--primary);
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.05;
            margin: 0;
        }
        .header h1 span { color: var(--accent); }
        .welcome-message,
        .greeting {
            font-family: 'Inter', sans-serif;
            font-size: 0.98rem;
            font-weight: 500;
            color: #6b7280;
            margin-bottom: 6px;
            line-height: 1.3;
        }
        .welcome-message strong,
        .greeting strong {
            color: #2563eb;
            font-weight: 700;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .stat-info h3 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }
        
        .stat-info p {
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        .bg-primary { background: var(--primary); }
        .bg-success { background: var(--success); }
        .bg-warning { background: var(--warning); }
        .bg-danger { background: var(--danger); }
        
        /* Dashboard Panels Layout */
        .dashboard-panels {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .left-panel,
        .right-panel {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        @media (min-width: 992px) {
            .dashboard-panels {
                grid-template-columns: repeat(3, 1fr);
            }
            .left-panel {
                grid-column: span 2;
            }
            .right-panel {
                grid-column: span 1;
            }
        }

        .charts-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .chart-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .chart-card h3 {
            color: var(--primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-wrapper {
            position: relative;
            height: 260px;
            max-height: 260px;
            width: 100%;
        }

        .right-panel {
            display: flex;
            flex-direction: column;
            gap: 20px; /* Space between calendar and notifications */
        }

        .calendar-card,
        .notifications-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .calendar-card {
            width: 100%;
            padding: 16px;
            box-sizing: border-box;
        }

        .calendar-card h2 {
            text-align: center;
            margin-bottom: 8px;
            color: var(--primary);
            font-size: 1.1rem;
        }

        .calendar-card h3 {
            color: var(--primary);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1rem;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .calendar-header button {
            background: none;
            border: none;
            font-size: 1rem;
            cursor: pointer;
            color: var(--secondary);
            padding: 4px 6px;
        }

        .calendar-table {
            width: 100%;
            text-align: center;
        }

        .calendar-table th,
        .calendar-table td {
            padding: 5px 2px;
            border: none;
            font-size: 0.78rem;
        }

        .calendar-table th {
            color: var(--gray);
            font-weight: normal;
        }

        .calendar-table td {
            cursor: pointer;
            border-radius: 4px;
            transition: background-color 0.2s;
        }

        .calendar-table td:hover:not(.inactive) {
            background-color: var(--light-gray);
        }

        .calendar-table td.today {
            background-color: var(--secondary);
            color: white;
            font-weight: bold;
        }

        .calendar-table td.inactive {
            color: #ccc;
            cursor: default;
        }

        .notifications-card h3 {
            color: var(--primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .notification-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-icon {
            font-size: 1.2rem;
            color: var(--secondary);
        }

        .notification-title {
            font-weight: bold;
            color: var(--dark);
        }

        .notification-message {
            font-size: 0.9rem;
            color: var(--gray);
        }

        .notification-time {
            font-size: 0.8rem;
            color: #aaa;
        }
        
        .notifications-list {
            max-height: 300px; /* Adjust as needed */
            overflow-y: auto;
            padding-right: 10px; /* To prevent scrollbar from overlapping content */
        }
        .recent-apps-card {
            max-height: 320px;
            display: flex;
            flex-direction: column;
        }
        .recent-apps-card .notifications-list {
            overflow-y: auto;
            flex: 1;
            max-height: none;
        }
        
        /* Dashboard Sections */
        .dashboard-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .section-header h2 {
            color: white;
            font-size: 1.4rem;
            background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%);
            padding: 14px 18px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
        }
        
        .section-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: var(--secondary);
            color: white;
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-warning {
            background: var(--warning);
            color: white;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn:hover {
            background: #153860;
            opacity: 0.95;
            transform: translateY(-1px);
        }
        
        /* Table Styles */
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background: var(--light-gray);
            color: var(--dark);
            font-weight: 600;
        }
        
        tr:hover {
            background: #f9f9f9;
        }
        
        .status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-verified {
            background: #d1edff;
            color: #0c5460;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Records Section */
        .records-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .record-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }
        
        .record-card:hover {
            transform: translateY(-5px);
        }
        
        .record-card h3 {
            color: var(--primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .record-card ul {
            list-style: none;
            margin-left: 10px;
        }
        
        .record-card li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .record-card li:last-child {
            border-bottom: none;
        }
        
        .record-card i {
            color: var(--secondary);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .records-grid {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .user-info {
                align-self: flex-end;
            }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/seniorlink-ui.css?v=16">
    <link rel="stylesheet" href="../assets/css/system-header.css?v=1">
    <link rel="stylesheet" href="../assets/css/system-sidebar.css?v=3">
    <link rel="stylesheet" href="../assets/css/dashboard-hci.css?v=3">
</head>
<body class="dashboard-page department-dashboard">
    <div class="container">
        <?php include '../partials/department_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <div class="header-content">
                    <div class="welcome-message" data-first-name="<?php echo htmlspecialchars($_SESSION['first_name']); ?>" data-last-name="<?php echo htmlspecialchars($_SESSION['last_name']); ?>" data-role="<?php echo htmlspecialchars($_SESSION['role']); ?>"></div>
                    <h1>Centralized Profiling Dashboard</h1>
                </div>
                <div class="header-actions">        
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php
                                $profilePic = isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : 'default.jpg';
                                $profilePicPath = '../images/profile_pictures/' . $profilePic;
                                if (!file_exists($profilePicPath) || is_dir($profilePicPath)) {
                                    $profilePicPath = '../images/profile_pictures/default.jpg'; // Fallback to default if file doesn't exist
                                }
                            ?>
                            <img src="<?php echo $profilePicPath; ?>" alt="Profile Picture" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                        </div>
                        <div class="user-details">
                            <h2><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></h2>
                            <p><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $_SESSION['role']))) . ' · Pasig City'; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <section class="dashboard-command-bar" aria-labelledby="departmentOverviewTitle">
                <div class="command-copy">
                    <span class="command-eyebrow"><i class="fas fa-building-columns" aria-hidden="true"></i> Citywide administration</span>
                    <h2 id="departmentOverviewTitle">Operations at a glance</h2>
                    <p>Monitor all barangays, verify submitted documents, and resolve records that require department action.</p>
                </div>
                <nav class="dashboard-quick-actions" aria-label="Department quick actions">
                    <a class="quick-action primary" href="verify_document.php"><i class="fas fa-file-circle-check" aria-hidden="true"></i><span><strong>Verify documents</strong><small>Open review workspace</small></span></a>
                    <a class="quick-action" href="department_records.php"><i class="fas fa-database" aria-hidden="true"></i><span><strong>Citywide records</strong><small>Browse all barangays</small></span></a>
                </nav>
            </section>
            
            <!-- Stats Cards -->
            <div class="stats-container">
                <a class="stat-card stat-card-link stat-blue" href="department_records.php" aria-label="Open verified application records">
                    <div class="stat-icon bg-primary">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3 class="dashboard-loading" aria-live="polite" aria-label="Loading verified applications">0</h3>
                        <p>Verified applications</p><small>Review document activity</small>
                    </div>
                    <i class="fas fa-arrow-right stat-arrow" aria-hidden="true"></i>
                </a>
                
                <a class="stat-card stat-card-link stat-green" href="department_records.php" aria-label="Open senior citizen records from all barangays">
                    <div class="stat-icon bg-success">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3 class="dashboard-loading" aria-live="polite" aria-label="Loading senior citizen records">0</h3>
                        <p>Senior citizen records</p><small>Browse citywide profiles</small>
                    </div>
                    <i class="fas fa-arrow-right stat-arrow" aria-hidden="true"></i>
                </a>
                
                <a class="stat-card stat-card-link stat-violet" href="department_records.php" aria-label="Open all processed records">
                    <div class="stat-icon bg-danger">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3 class="dashboard-loading" aria-live="polite" aria-label="Loading total processed records">0</h3>
                        <p>Total processed</p><small>View complete workload</small>
                    </div>
                    <i class="fas fa-arrow-right stat-arrow" aria-hidden="true"></i>
                </a>
            </div>
            
            <div class="dashboard-section-heading"><div><span>Citywide performance</span><h2>Application insights</h2><p>Compare barangay activity and track long-term record trends.</p></div></div>
            <div class="dashboard-panels">
                        <div class="left-panel">
                            <div class="charts-container">
                                <div class="chart-card">
                                    <h3><span><i class="fas fa-chart-bar"></i> Records by barangay</span><small>Compare verified records across Pasig City</small></h3>
                                    <div class="chart-wrapper is-loading"><canvas id="barangayRecordsChart" aria-label="Chart comparing records across barangays" role="img">Barangay records chart</canvas></div>
                                </div>
                                <div class="chart-card">
                                    <h3><span><i class="fas fa-chart-line"></i> Yearly records</span><small>Record growth and processing trends over time</small></h3>
                                    <div class="chart-wrapper is-loading"><canvas id="yearlyRecordsChart" aria-label="Chart of yearly records and processing trends" role="img">Yearly records chart</canvas></div>
                                </div>
                            </div>
                        </div>
        
                        <div class="right-panel">
                            <div class="calendar-card">
                                <h2 id="current-time" aria-live="polite"></h2>
                                <h3><span><i class="fas fa-calendar-alt"></i> Calendar</span><small>Navigate dates and schedules</small></h3>
                                <div class="calendar-body">
                                    <div class="calendar-header">
                                        <button id="prev-month" type="button" aria-label="Show previous month"><i class="fas fa-chevron-left"></i></button>
                                        <span id="month-year"></span>
                                        <button id="next-month" type="button" aria-label="Show next month"><i class="fas fa-chevron-right"></i></button>
                                    </div>
                                    <table class="calendar-table">
                                        <thead><tr><th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th></tr></thead>
                                        <tbody id="calendar-days"></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="notifications-card recent-apps-card">
                                <h3><span class="notification-heading"><span><i class="fas fa-bell"></i> Recent applications</span><b class="important-label"><i class="fas fa-circle" aria-hidden="true"></i> Important updates</b></span><small>Latest activity from all barangays — review new items promptly</small></h3>
                                <div class="notifications-list" id="realtime-notifications-list" aria-live="polite">
                                    <p>Loading notifications...</p>
                                </div>
                            </div>

                            <!-- Quick RA Reference Card -->
                            <div class="notifications-card" id="raReferenceCard" hidden style="padding:0; overflow:hidden; border-radius:12px;">
                                <div style="background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%); padding: 16px 20px; display:flex; align-items:center; gap:10px;">
                                    <div style="width:36px;height:36px;background:rgba(255,255,255,0.15);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                        <i class="fas fa-balance-scale" style="color:#f0c060;font-size:1rem;"></i>
                                    </div>
                                    <div>
                                        <div style="color:#fff;font-weight:700;font-size:.95rem;">Quick RA Reference</div>
                                        <div style="color:rgba(255,255,255,0.6);font-size:.72rem;margin-top:1px;">Legal Compliance Guide for Staff</div>
                                    </div>
                                    <a href="legal_reference.php" style="margin-left:auto;background:rgba(255,255,255,0.15);color:#fff;text-decoration:none;padding:5px 12px;border-radius:20px;font-size:.72rem;font-weight:600;white-space:nowrap;transition:background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.25)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'">
                                        Full Guide <i class="fas fa-arrow-right" style="font-size:.65rem;"></i>
                                    </a>
                                </div>
                                <div style="padding:6px 0;">
                                    <div class="ra-item" onclick="var b=this.querySelector('.ra-body'),c=this.querySelector('.ra-chevron');b.style.display=b.style.display==='none'?'block':'none';c.style.transform=c.style.transform==='rotate(180deg)'?'rotate(0deg)':'rotate(180deg)';" style="cursor:pointer;border-bottom:1px solid #f1f5f9;">
                                        <div style="display:flex;align-items:center;gap:10px;padding:13px 18px;">
                                            <div style="width:32px;height:32px;background:#dbeafe;border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-id-card" style="color:#2563eb;font-size:.8rem;"></i></div>
                                            <div style="flex:1;"><div style="font-weight:700;font-size:.82rem;color:#1e293b;">RA 9994</div><div style="font-size:.71rem;color:#64748b;">Expanded Senior Citizens Act of 2010</div></div>
                                            <span style="background:#2563eb;color:#fff;padding:2px 7px;border-radius:10px;font-size:.68rem;font-weight:700;margin-right:6px;">Age 60+</span>
                                            <i class="fas fa-chevron-down ra-chevron" style="color:#94a3b8;font-size:.75rem;transition:transform 0.3s;"></i>
                                        </div>
                                        <div class="ra-body" style="display:none;padding:4px 18px 14px 60px;font-size:.78rem;color:#475569;line-height:1.7;">
                                            <p>Defines a <strong>Senior Citizen as 60 years or older</strong>. Key entitlements: <strong>20% discount</strong>, VAT exemption, free government medical services, and priority lanes.</p>
                                        </div>
                                    </div>
                                    <div class="ra-item" onclick="var b=this.querySelector('.ra-body'),c=this.querySelector('.ra-chevron');b.style.display=b.style.display==='none'?'block':'none';c.style.transform=c.style.transform==='rotate(180deg)'?'rotate(0deg)':'rotate(180deg)';" style="cursor:pointer;border-bottom:1px solid #f1f5f9;">
                                        <div style="display:flex;align-items:center;gap:10px;padding:13px 18px;">
                                            <div style="width:32px;height:32px;background:#dcfce7;border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-hand-holding-usd" style="color:#16a34a;font-size:.8rem;"></i></div>
                                            <div style="flex:1;"><div style="font-weight:700;font-size:.82rem;color:#1e293b;">RA 11916</div><div style="font-size:.71rem;color:#64748b;">Social Pension for Indigent Seniors</div></div>
                                            <span style="background:#16a34a;color:#fff;padding:2px 7px;border-radius:10px;font-size:.68rem;font-weight:700;margin-right:6px;">₱1,000/mo</span>
                                            <i class="fas fa-chevron-down ra-chevron" style="color:#94a3b8;font-size:.75rem;transition:transform 0.3s;"></i>
                                        </div>
                                        <div class="ra-body" style="display:none;padding:4px 18px 14px 60px;font-size:.78rem;color:#475569;line-height:1.7;">
                                            <p>Mandates <strong>₱1,000/month</strong> social pension for indigent seniors. Must not be a beneficiary of any other government pension. Administered by DSWD.</p>
                                        </div>
                                    </div>
                                    <div class="ra-item" onclick="var b=this.querySelector('.ra-body'),c=this.querySelector('.ra-chevron');b.style.display=b.style.display==='none'?'block':'none';c.style.transform=c.style.transform==='rotate(180deg)'?'rotate(0deg)':'rotate(180deg)';" style="cursor:pointer;border-bottom:1px solid #f1f5f9;">
                                         <div style="display:flex;align-items:center;gap:10px;padding:13px 18px;">
                                             <div style="width:32px;height:32px;background:#fef9c3;border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-birthday-cake" style="color:#ca8a04;font-size:.8rem;"></i></div>
                                             <div style="flex:1;"><div style="font-weight:700;font-size:.82rem;color:#1e293b;">RA 11982</div><div style="font-size:.71rem;color:#64748b;">Expanded Centenarian Act</div></div>
                                             <span style="background:#ca8a04;color:#fff;padding:2px 7px;border-radius:10px;font-size:.68rem;font-weight:700;margin-right:6px;">Age 80+</span>
                                             <i class="fas fa-chevron-down ra-chevron" style="color:#94a3b8;font-size:.75rem;transition:transform 0.3s;"></i>
                                         </div>
                                         <div class="ra-body" style="display:none;padding:4px 18px 14px 60px;font-size:.78rem;color:#475569;line-height:1.7;">
                                             <p>Cash gifts at milestone ages: <strong>₱10,000 at 80/85/90/95</strong> and <strong>₱100,000 at 100+</strong> (Centenarian Award via OSCA endorsement).</p>
                                         </div>
                                     </div>
                                     <!-- Verified OSCA Contacts -->
                                     <div class="ra-item" onclick="var b=this.querySelector('.ra-body'),c=this.querySelector('.ra-chevron');b.style.display=b.style.display==='none'?'block':'none';c.style.transform=c.style.transform==='rotate(180deg)'?'rotate(0deg)':'rotate(180deg)';" style="cursor:pointer;">
                                         <div style="display:flex;align-items:center;gap:10px;padding:13px 18px;">
                                             <div style="width:32px;height:32px;background:#fee2e2;border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-phone-alt" style="color:#ef4444;font-size:.8rem;"></i></div>
                                             <div style="flex:1;">
                                                 <div style="font-weight:700;font-size:.82rem;color:#1e293b;">Official Contacts</div>
                                                 <div style="font-size:.71rem;color:#64748b;">Verified Support Channels & Helpdesk</div>
                                             </div>
                                             <span style="background:#ef4444;color:#fff;padding:2px 7px;border-radius:10px;font-size:.68rem;font-weight:700;margin-right:6px;">OSCA</span>
                                             <i class="fas fa-chevron-down ra-chevron" style="color:#94a3b8;font-size:.75rem;transition:transform 0.3s;"></i>
                                         </div>
                                         <div class="ra-body" style="display:none;padding:4px 18px 14px 60px;font-size:.78rem;color:#475569;line-height:1.7;">
                                             <p>Official contacts for senior citizen inquiries and support:</p>
                                             <ul style="margin-top:6px;padding-left:14px;list-style-type:none;">
                                                 <li style="margin-bottom:6px;"><i class="fas fa-globe" style="color:#3b82f6;margin-right:6px;"></i><strong>Web:</strong> <a href="https://www.pasigcity.gov.ph" target="_blank" style="color:#2563eb;text-decoration:none;">Pasig City Official Website</a></li>
                                                 <li style="margin-bottom:6px;"><i class="fas fa-envelope" style="color:#10b981;margin-right:6px;"></i><strong>Emails:</strong> <a href="mailto:osca@pasigcity.gov.ph" style="color:#2563eb;text-decoration:none;">osca@pasigcity.gov.ph</a> / <a href="mailto:OSCApasig@gmail.com" style="color:#2563eb;text-decoration:none;">OSCApasig@gmail.com</a></li>
                                                 <li style="margin-bottom:6px;"><i class="fab fa-facebook" style="color:#1877f2;margin-right:6px;"></i><strong>Facebook:</strong> <a href="https://www.facebook.com/search/top/?q=Pasig%20City%20OSCA" target="_blank" style="color:#2563eb;text-decoration:none;">Pasig City OSCA</a></li>
                                                 <li style="margin-bottom:6px;"><i class="fas fa-phone-alt" style="color:#f59e0b;margin-right:6px;"></i><strong>Helpdesk:</strong> 8-643-1111 Local 1152</li>
                                                 <li style="margin-bottom:6px;"><i class="fas fa-landmark" style="color:#8b5cf6;margin-right:6px;"></i><strong>Oversight:</strong> <a href="https://ncsc.gov.ph" target="_blank" style="color:#2563eb;text-decoration:none;">NCSC Portal</a></li>
                                             </ul>
                                         </div>
                                     </div>
                                 </div>
                            </div>
                        </div>
                    </div>
        
                    <div class="footer">                <p>Centralized Profiling and Record Authentication System | Department Admin &copy; <?php echo date('Y'); ?></p>
            </div>
        </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/sidebar-toggle.js?v=3"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initializeWelcomeMessage();
            initializeCalendar();
            updateTime();
            setInterval(updateTime, 1000);

            loadDashboardData();
        });

        function loadDashboardData() {
            const list = document.getElementById('realtime-notifications-list');
            if (list) list.innerHTML = '<div class="dashboard-state" role="status">Updating dashboard…</div>';
            document.querySelectorAll('.chart-wrapper').forEach(el => el.classList.add('is-loading'));
            fetch('../api/get_realtime_data.php', { headers: { 'Accept': 'application/json' } })
                .then(response => {
                    if (!response.ok) throw new Error(`Request failed (${response.status})`);
                    return response.json();
                })
                .then(result => {
                    if (result.status === 'success') {
                        renderNotifications(result.data.notifications);
                        initializeDepartmentCharts(result.data);
                        updateStatCards(result.data); // Call new function to update stat cards
                    } else {
                        throw new Error(result.message || 'Dashboard data is unavailable');
                    }
                })
                .catch(error => {
                    console.error('Error fetching dashboard data:', error);
                    showDashboardError();
                });
        }

        function showDashboardError() {
            document.querySelectorAll('.dashboard-loading').forEach(el => { el.classList.remove('dashboard-loading'); el.textContent = '—'; el.removeAttribute('aria-label'); });
            document.querySelectorAll('.chart-wrapper').forEach(el => el.classList.remove('is-loading'));
            const list = document.getElementById('realtime-notifications-list');
            if (list) list.innerHTML = '<div class="dashboard-state" role="alert"><span>Dashboard updates could not be loaded.</span><button class="dashboard-retry" type="button" onclick="loadDashboardData()">Try again</button></div>';
        }

        function updateStatCards(data) {
            // Update stat card values from live API data
            document.querySelector('.stat-card:nth-child(1) h3').textContent = data.verified_applications ?? 0;
            document.querySelector('.stat-card:nth-child(2) h3').textContent = data.senior_citizen_records ?? 0;
            document.querySelector('.stat-card:nth-child(3) h3').textContent = data.total_processed ?? 0;
            document.querySelectorAll('.dashboard-loading').forEach(node => { node.classList.remove('dashboard-loading'); node.removeAttribute('aria-label'); });
        }

        function updateTime() {
            const timeEl = document.getElementById('current-time');
            if (timeEl) {
                timeEl.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            }
        }

        function initializeWelcomeMessage() {
            const welcomeMessage = document.querySelector('.welcome-message');
            const firstName = welcomeMessage.dataset.firstName;
            const lastName = welcomeMessage.dataset.lastName;
            const hour = new Date().getHours();
            let greeting = (hour < 12) ? "Good morning" : (hour < 18) ? "Good afternoon" : "Good evening";
            welcomeMessage.innerHTML = `${greeting}, <strong>${firstName} ${lastName}</strong>!`;
        }

        function initializeCalendar() {
            const monthYearEl = document.getElementById('month-year');
            const calendarDaysEl = document.getElementById('calendar-days');
            const prevMonthBtn = document.getElementById('prev-month');
            const nextMonthBtn = document.getElementById('next-month');
            if (!monthYearEl || !calendarDaysEl || !prevMonthBtn || !nextMonthBtn) return;
            let currentDate = new Date();
            function renderCalendar() {
                const year = currentDate.getFullYear(), month = currentDate.getMonth();
                const today = new Date();
                const firstDayOfMonth = new Date(year, month, 1), lastDayOfMonth = new Date(year, month + 1, 0);
                const firstDayOfWeek = firstDayOfMonth.getDay(), totalDays = lastDayOfMonth.getDate();
                const prevMonthDays = new Date(year, month, 0).getDate();
                monthYearEl.textContent = `${firstDayOfMonth.toLocaleString('default', { month: 'long' })} ${year}`;
                calendarDaysEl.innerHTML = '';
                let date = 1, nextMonthDate = 1;
                for (let i = 0; i < 6; i++) {
                    const row = document.createElement('tr');
                    let weekHasDays = false;
                    for (let j = 0; j < 7; j++) {
                        const cell = document.createElement('td');
                        if (i === 0 && j < firstDayOfWeek) {
                            cell.textContent = prevMonthDays - firstDayOfWeek + j + 1;
                            cell.classList.add('inactive');
                        } else if (date > totalDays) {
                            cell.textContent = nextMonthDate++;
                            cell.classList.add('inactive');
                        } else {
                            cell.textContent = date;
                            if (date === today.getDate() && month === today.getMonth() && year === today.getFullYear()) cell.classList.add('today');
                            date++;
                            weekHasDays = true;
                        }
                        row.appendChild(cell);
                    }
                    if (weekHasDays || i === 0) calendarDaysEl.appendChild(row);
                }
            }
            prevMonthBtn.addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() - 1); renderCalendar(); });
            nextMonthBtn.addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() + 1); renderCalendar(); });
            renderCalendar();
        }

        function renderNotifications(notifications) {
            const listEl = document.getElementById('realtime-notifications-list');
            if (!listEl) return;
            if (!notifications || notifications.length === 0) {
                listEl.innerHTML = '<p style="text-align:center;padding:20px;color:var(--gray);font-size:0.85rem;">No recent applications.</p>';
                return;
            }

            // Workflow status to icon and color mapping
            const stateConfig = {
                'received':   { icon: 'fa-inbox',       color: '#94a3b8', bg: 'rgba(148,163,184,0.15)', label: 'Received'   },
                'for review': { icon: 'fa-search',       color: '#3b82f6', bg: 'rgba(59,130,246,0.12)',  label: 'For Review' },
                'verified':   { icon: 'fa-check',        color: '#14b8a6', bg: 'rgba(20,184,166,0.12)',  label: 'Verified'   },
                'deceased':   { icon: 'fa-cross',        color: '#64748b', bg: 'rgba(100,116,139,0.12)', label: 'Deceased'   },
            };

            const appTypeLabels = {
                'senior': 'Senior ID', 'pension': 'Local Pension', 'burial': 'Burial Assistance',
                'national_pension': 'DSWD Pension', 'milestone_gift': 'Milestone Cash Gift',
                'landbank': 'Landbank Card', 'home_visit': 'Home Visit', 'pwd': 'PWD Support',
            };

            listEl.innerHTML = '';
            notifications.forEach(notif => {
                const stateKey = (notif.workflow_state || 'received').toLowerCase();
                const cfg = stateConfig[stateKey] || { icon: 'fa-info-circle', color: '#94a3b8', bg: 'rgba(148,163,184,0.15)', label: notif.workflow_state };
                const typeLabel = appTypeLabels[notif.application_type] || notif.application_type;
                const applicantName = String(notif.full_name || 'Unknown applicant');
                const safeApplicantName = applicantName.replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[character]);
                const destination = ['verified', 'approved', 'released'].includes(stateKey)
                    ? 'department_records.php'
                    : 'verify_document.php';
                const applicantUrl = `${destination}?search=${encodeURIComponent(applicantName)}`;
                const dateObj = new Date((notif.date_submitted || '').replace(' ', 'T'));
                const timeAgo = getTimeAgo(dateObj);

                const item = document.createElement('div');
                item.className = 'notification-item';
                item.style.cssText = 'display:flex;align-items:flex-start;padding:11px 5px;border-bottom:1px solid #f1f5f9;gap:12px;transition:background 0.15s;';
                item.onmouseenter = () => item.style.background = '#f8fafc';
                item.onmouseleave = () => item.style.background = '';
                item.innerHTML = `
                    <div style="width:38px;height:38px;border-radius:50%;background:${cfg.bg};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fas ${cfg.icon}" style="color:${cfg.color};font-size:0.9rem;"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:700;font-size:0.84rem;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            <a href="${applicantUrl}" title="Show ${safeApplicantName}" style="color:inherit;text-decoration:none;cursor:pointer;">${safeApplicantName}</a>
                        </div>
                        <div style="font-size:0.76rem;color:#475569;margin-top:2px;">${typeLabel} &mdash; <span style="color:#64748b;">${notif.barangay || ''}</span></div>
                        <div style="display:flex;align-items:center;gap:6px;margin-top:4px;">
                            <span style="font-size:0.7rem;font-weight:700;color:${cfg.color};background:${cfg.bg};padding:1px 7px;border-radius:10px;">${cfg.label}</span>
                            <span style="font-size:0.7rem;color:#94a3b8;">${timeAgo}</span>
                        </div>
                    </div>
                `;
                listEl.appendChild(item);
            });
        }

        function getTimeAgo(date) {
            const seconds = Math.floor((new Date() - date) / 1000);
            if (isNaN(seconds) || seconds < 0) return '';
            let interval = seconds / 31536000;
            if (interval > 1) return Math.floor(interval) + 'y ago';
            interval = seconds / 2592000;
            if (interval > 1) return Math.floor(interval) + 'mo ago';
            interval = seconds / 86400;
            if (interval > 1) return Math.floor(interval) + 'd ago';
            interval = seconds / 3600;
            if (interval > 1) return Math.floor(interval) + 'h ago';
            interval = seconds / 60;
            if (interval > 1) return Math.floor(interval) + 'm ago';
            return Math.floor(seconds) + 's ago';
        }

        function initializeDepartmentCharts(data) {
            const { barangay_records, yearly_records } = data;

            // Barangay Records Chart (Horizontal Bar)
            const barangayRecordsCtx = document.getElementById('barangayRecordsChart')?.getContext('2d');
            if (barangayRecordsCtx && barangay_records) {
                const labels = barangay_records.map(record => record.barangay);
                const counts = barangay_records.map(record => record.count);

                new Chart(barangayRecordsCtx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Applications',
                            data: counts,
                            backgroundColor: [
                                'rgba(255, 99, 132, 0.6)',
                                'rgba(54, 162, 235, 0.6)',
                                'rgba(255, 206, 86, 0.6)',
                                'rgba(75, 192, 192, 0.6)',
                                'rgba(153, 102, 255, 0.6)'
                            ],
                            borderColor: [
                                'rgba(255, 99, 132, 1)',
                                'rgba(54, 162, 235, 1)',
                                'rgba(255, 206, 86, 1)',
                                'rgba(75, 192, 192, 1)',
                                'rgba(153, 102, 255, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        indexAxis: 'y', // Makes it a horizontal bar chart
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            x: { beginAtZero: true }
                        }
                    }
                });
            }

            // Yearly Records Chart (Bar)
            const yearlyRecordsCtx = document.getElementById('yearlyRecordsChart')?.getContext('2d');
            if (yearlyRecordsCtx && yearly_records) {
                const labels = yearly_records.map(record => record.year);
                const counts = yearly_records.map(record => record.count);

                new Chart(yearlyRecordsCtx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Total Applications',
                            data: counts,
                            backgroundColor: 'rgba(75, 192, 192, 0.6)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
            }
            document.querySelectorAll('.chart-wrapper').forEach(el => el.classList.remove('is-loading'));
        }
    </script>
</body>
</html>
