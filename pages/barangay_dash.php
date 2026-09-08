<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/audit_logger.php';

// Redirect if not logged in or not barangay staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'barangay_staff') {
    header('Location: ../index.php');
    exit;
}

// Backfill one audit event for sessions established before login auditing was enabled.
if (empty($_SESSION['login_audit_recorded'])) {
    $sessionBarangay = (string)($_SESSION['barangay'] ?? 'Unknown Barangay');
    if (logAudit($conn, 'LOGIN', "Active SHDO session confirmed for Barangay {$sessionBarangay}.")) {
        $_SESSION['login_audit_recorded'] = true;
    }
}

$barangayName = htmlspecialchars($_SESSION['barangay'] ?? 'Unknown Barangay');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CPRAS Dashboard - Barangay <?php echo $barangayName; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/barangay-sidebar.css?v=4">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Page-specific styles for dashboard */
        .welcome-message {
            font-size: 0.98rem;
            font-weight: 500;
            color: #6b7280;
            margin-bottom: 6px;
            font-family: 'Inter', sans-serif;
            line-height: 1.3;
        }
        .welcome-message strong {
            color: #2563eb;
            font-weight: 700;
        }
        .dashboard-panels {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        .left-panel, .right-panel {
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
        .chart-card, .calendar-card, .notifications-card { background: white; border-radius: 10px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); padding: 20px; }
        .calendar-card { width: 100%; padding: 16px; box-sizing: border-box; }
        .chart-card h3, .calendar-card h3, .notifications-card h3 { font-size: 18px; margin-bottom: 15px; color: white; background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%); display: flex; align-items: center; gap: 10px; padding: 14px 18px; border-radius: 10px; }
        
        #current-time { font-size: 1.1rem; font-weight: 600; color: var(--primary); text-align: center; margin-bottom: 8px; }
        .calendar-card h3 { margin-bottom: 10px; padding: 10px 14px; font-size: 1rem; }
        .calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .calendar-header button { background: none; border: none; font-size: 1rem; color: var(--primary); cursor: pointer; padding: 4px 6px; transition: color 0.3s; }
        .calendar-header button:hover { color: var(--secondary); }
        .calendar-header .month-year { font-size: 0.95rem; font-weight: 600; color: var(--dark); }
        .calendar-table { width: 100%; border-collapse: collapse; text-align: center; }
        .calendar-table th, .calendar-table td { padding: 5px 2px; border: 1px solid #eee; font-size: 0.78rem; }
        .calendar-table th { background: var(--light); color: var(--dark); font-weight: 500; }
        .calendar-table td { color: var(--primary); }
        .calendar-table td.inactive { color: var(--gray); background-color: #f9f9f9; }
        .calendar-table td.today { background: var(--secondary); color: white; border-radius: 50%; font-weight: bold; }
        
        .charts-container { display: flex; flex-direction: column; gap: 20px; }
        .chart-wrapper { position: relative; height: 260px; max-height: 260px; width: 100%; }
        .notifications-list { max-height: 450px; overflow-y: auto; }
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
        .notification-item { display: flex; align-items: center; padding: 15px 5px; border-bottom: 1px solid #eee; }
        .notification-item:last-child { border-bottom: none; }
        .notification-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px; flex-shrink: 0; }
        .notification-info { flex-grow: 1; }
        .notification-title { font-weight: 600; margin-bottom: 2px; color: var(--primary); }
        .notification-message { font-size: 15px; color: var(--dark); font-weight: bold; margin-bottom: 5px; }
        .notification-time { font-size: 13px; color: var(--dark); font-weight: bold; }
        .status-pending .notification-icon { background: rgba(243, 156, 18, 0.2); color: var(--warning); }
        .status-verified .notification-icon { background: rgba(52, 152, 219, 0.2); color: var(--secondary); }
        .status-rejected .notification-icon { background: rgba(231, 76, 60, 0.2); color: var(--accent); }
        .status-approved .notification-icon { background: rgba(46, 204, 113, 0.2); color: var(--success); }

        /* Summary stats strip */
        .stats-strip { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-card { background: white; border-radius: 10px; padding: 18px 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.08); display: flex; align-items: center; gap: 15px; }
        .stat-card-link { color: inherit; text-decoration: none; cursor: pointer; transition: transform .18s ease, box-shadow .18s ease; }
        .stat-card-link:hover, .stat-card-link:focus-visible { transform: translateY(-2px); box-shadow: 0 8px 16px rgba(15,23,42,.14); outline: none; }
        .stat-icon { font-size: 1.8rem; width: 45px; text-align: center; }
        .stat-label { font-size: 0.8rem; color: var(--gray); font-weight: 600; text-transform: uppercase; }
        .stat-value { font-size: 1.5rem; font-weight: 700; color: var(--primary); }

        /* Priority queue alert banner */
        .priority-alert { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; border-radius: 10px; padding: 15px 20px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(245,158,11,0.4); }
        .priority-alert.hidden { display: none; }
        .priority-alert-text { font-weight: 600; font-size: 1rem; }
        .priority-alert-text span { font-size: 1.5rem; font-weight: 800; }
        .priority-alert a { background: white; color: #d97706; padding: 8px 18px; border-radius: 6px; font-weight: 700; text-decoration: none; font-size: 0.9rem; }
        .priority-alert a:hover { background: #fef3c7; }


    </style>
    <link rel="stylesheet" href="../assets/css/seniorlink-ui.css?v=16">
    <link rel="stylesheet" href="../assets/css/system-header.css?v=1">
    <link rel="stylesheet" href="../assets/css/system-sidebar.css?v=3">
    <link rel="stylesheet" href="../assets/css/dashboard-hci.css?v=3">
    <link rel="stylesheet" href="../assets/css/metric-cards.css?v=1">
<script src="../assets/js/dashboard-chart-fallback.js?v=1"></script>
</head>
<body class="dashboard-page barangay-dashboard">
    <div class="container">
        <?php include '../partials/barangay_sidebar.php'; ?>

        <div class="main-content">
            <div class="header">
                <div class="header-content">
                    <div class="welcome-message" style="font-size:0.98rem;font-weight:500;color:#6b7280;margin-bottom:6px;font-family:'Inter',sans-serif;line-height:1.3;" data-first-name="<?php echo htmlspecialchars($_SESSION['first_name']); ?>" data-last-name="<?php echo htmlspecialchars($_SESSION['last_name']); ?>"></div>
                    <h1>Barangay <span><?php echo $barangayName; ?></span> Dashboard</h1>
                </div>
                <div class="header-actions">
                    
                    <div class="user-info">
                        <div class="user-avatar">
                            <img src="../images/profile_pictures/<?php echo htmlspecialchars($_SESSION['profile_picture'] ?? 'default.jpg'); ?>" alt="Profile Picture">
                        </div>
                        <div class="user-details">
                            <h2><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></h2>
                            <p><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $_SESSION['role']))) . ' • ' . htmlspecialchars($_SESSION['barangay']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <section class="dashboard-command-bar" aria-labelledby="barangayOverviewTitle">
                <div class="command-copy">
                    <span class="command-eyebrow"><i class="fas fa-location-dot" aria-hidden="true"></i> Barangay operations</span>
                    <h2 id="barangayOverviewTitle">Today’s service overview</h2>
                    <p>Review the local queue, register applicants, and monitor recent activity.</p>
                </div>
                <nav class="dashboard-quick-actions" aria-label="Barangay quick actions">
                    <a class="quick-action primary" href="new_application.php"><i class="fas fa-user-plus" aria-hidden="true"></i><span><strong>New application</strong><small>Register an applicant</small></span></a>
                    <a class="quick-action" href="submit_application.php"><i class="fas fa-clipboard-list" aria-hidden="true"></i><span><strong>Open queue</strong><small>Continue processing</small></span></a>
                </nav>
            </section>

            <!-- Summary Stats Strip -->
            <div class="stats-strip" id="statsStrip">
                <a class="stat-card stat-card-link stat-blue" href="submit_application.php" aria-label="Open all active applications"><div class="stat-icon"><i class="fas fa-file-lines" aria-hidden="true"></i></div><div class="stat-info"><div class="stat-label">Active applications</div><div class="stat-value dashboard-loading" id="statTotal" aria-live="polite" aria-label="Loading active applications">0</div><small>View local workload</small></div><i class="fas fa-arrow-right stat-arrow" aria-hidden="true"></i></a>
                <a class="stat-card stat-card-link stat-amber" href="submit_application.php" aria-label="Open received applications"><div class="stat-icon"><i class="fas fa-inbox" aria-hidden="true"></i></div><div class="stat-info"><div class="stat-label">Awaiting action</div><div class="stat-value dashboard-loading" id="statReceived" aria-live="polite" aria-label="Loading applications awaiting action">0</div><small>Received in queue</small></div><i class="fas fa-arrow-right stat-arrow" aria-hidden="true"></i></a>
                <a class="stat-card stat-card-link stat-green" href="barangay_records.php" aria-label="Open verified application records"><div class="stat-icon"><i class="fas fa-circle-check" aria-hidden="true"></i></div><div class="stat-info"><div class="stat-label">Verified records</div><div class="stat-value dashboard-loading" id="statApproved" aria-live="polite" aria-label="Loading verified records">0</div><small>Browse completed records</small></div><i class="fas fa-arrow-right stat-arrow" aria-hidden="true"></i></a>
            </div>

            <div class="dashboard-section-heading"><div><span>Performance and activity</span><h2>Application insights</h2><p>Use these summaries to identify workload patterns and recent changes.</p></div></div>
            <div class="dashboard-panels">
                <div class="left-panel">
                    <div class="charts-container">
                        <div class="chart-card">
                            <h3><span><i class="fas fa-chart-pie"></i> Status distribution</span><small>Current applications by workflow stage</small></h3>
                            <div class="chart-wrapper is-loading"><canvas id="statusChart" aria-label="Chart of barangay applications by workflow status" role="img">Application status chart</canvas></div>
                        </div>
                        <div class="chart-card">
                            <h3><span><i class="fas fa-chart-column"></i> Monthly applications</span><small>Submission volume during the last 12 months</small></h3>
                            <div class="chart-wrapper is-loading"><canvas id="monthlyChart" aria-label="Chart of monthly barangay application volume" role="img">Monthly application chart</canvas></div>
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
                        <h3><span class="notification-heading"><span><i class="fas fa-bell"></i> Recent applications</span><b class="important-label"><i class="fas fa-circle" aria-hidden="true"></i> Important updates</b></span><small>Latest local workflow activity — review new items promptly</small></h3>
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
                            <!-- RA 9994 -->
                            <div class="ra-item" onclick="this.querySelector('.ra-body').style.display = this.querySelector('.ra-body').style.display==='none'?'block':'none'; this.querySelector('.ra-chevron').style.transform = this.querySelector('.ra-chevron').style.transform==='rotate(180deg)'?'rotate(0deg)':'rotate(180deg)';" style="cursor:pointer; border-bottom:1px solid #f1f5f9;">
                                <div style="display:flex;align-items:center;gap:10px;padding:13px 18px;">
                                    <div style="width:32px;height:32px;background:#dbeafe;border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-id-card" style="color:#2563eb;font-size:.8rem;"></i></div>
                                    <div style="flex:1;">
                                        <div style="font-weight:700;font-size:.82rem;color:#1e293b;">RA 9994</div>
                                        <div style="font-size:.71rem;color:#64748b;">Expanded Senior Citizens Act of 2010</div>
                                    </div>
                                    <span style="background:#2563eb;color:#fff;padding:2px 7px;border-radius:10px;font-size:.68rem;font-weight:700;margin-right:6px;">Age 60+</span>
                                    <i class="fas fa-chevron-down ra-chevron" style="color:#94a3b8;font-size:.75rem;transition:transform 0.3s;"></i>
                                </div>
                                <div class="ra-body" style="display:none;padding:4px 18px 14px 60px;font-size:.78rem;color:#475569;line-height:1.7;">
                                    <p>Defines a <strong>Senior Citizen as 60 years or older</strong>. Key entitlements include:</p>
                                    <ul style="margin-top:6px;padding-left:14px;">
                                        <li><strong>20% discount</strong> on goods and services (medicine, restaurants, transport, etc.)</li>
                                        <li><strong>VAT exemption</strong> on the 20% senior discount purchases</li>
                                        <li>Free medical and dental services in government hospitals</li>
                                        <li>Priority lanes in all government and private establishments</li>
                                        <li>SSS/GSIS minimum monthly pension benefits</li>
                                    </ul>
                                </div>
                            </div>
                            <!-- RA 11916 -->
                            <div class="ra-item" onclick="this.querySelector('.ra-body').style.display = this.querySelector('.ra-body').style.display==='none'?'block':'none'; this.querySelector('.ra-chevron').style.transform = this.querySelector('.ra-chevron').style.transform==='rotate(180deg)'?'rotate(0deg)':'rotate(180deg)';" style="cursor:pointer; border-bottom:1px solid #f1f5f9;">
                                <div style="display:flex;align-items:center;gap:10px;padding:13px 18px;">
                                    <div style="width:32px;height:32px;background:#dcfce7;border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-hand-holding-usd" style="color:#16a34a;font-size:.8rem;"></i></div>
                                    <div style="flex:1;">
                                        <div style="font-weight:700;font-size:.82rem;color:#1e293b;">RA 11916</div>
                                        <div style="font-size:.71rem;color:#64748b;">Social Pension for Indigent Seniors</div>
                                    </div>
                                    <span style="background:#16a34a;color:#fff;padding:2px 7px;border-radius:10px;font-size:.68rem;font-weight:700;margin-right:6px;">₱1,000/mo</span>
                                    <i class="fas fa-chevron-down ra-chevron" style="color:#94a3b8;font-size:.75rem;transition:transform 0.3s;"></i>
                                </div>
                                <div class="ra-body" style="display:none;padding:4px 18px 14px 60px;font-size:.78rem;color:#475569;line-height:1.7;">
                                    <p>Mandates a <strong>100% increase</strong> in the monthly social pension for indigent senior citizens:</p>
                                    <ul style="margin-top:6px;padding-left:14px;">
                                        <li>From ₱500 → <strong>₱1,000 per month</strong></li>
                                        <li>Beneficiaries must be <strong>60+</strong>, indigent, frail, sick, or with disability</li>
                                        <li>Not a beneficiary of any other pension/retirement benefit from government</li>
                                        <li>Must pass a <strong>means test</strong> to qualify as "indigent"</li>
                                        <li>Administered by DSWD (Dept. of Social Welfare and Development)</li>
                                    </ul>
                                </div>
                            </div>
                            <!-- RA 11982 -->
                            <div class="ra-item" onclick="this.querySelector('.ra-body').style.display = this.querySelector('.ra-body').style.display==='none'?'block':'none'; this.querySelector('.ra-chevron').style.transform = this.querySelector('.ra-chevron').style.transform==='rotate(180deg)'?'rotate(0deg)':'rotate(180deg)';" style="cursor:pointer; border-bottom:1px solid #f1f5f9;">
                                <div style="display:flex;align-items:center;gap:10px;padding:13px 18px;">
                                    <div style="width:32px;height:32px;background:#fef9c3;border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-birthday-cake" style="color:#ca8a04;font-size:.8rem;"></i></div>
                                    <div style="flex:1;">
                                        <div style="font-weight:700;font-size:.82rem;color:#1e293b;">RA 11982</div>
                                        <div style="font-size:.71rem;color:#64748b;">Expanded Centenarian Act</div>
                                    </div>
                                    <span style="background:#ca8a04;color:#fff;padding:2px 7px;border-radius:10px;font-size:.68rem;font-weight:700;margin-right:6px;">Age 80+</span>
                                    <i class="fas fa-chevron-down ra-chevron" style="color:#94a3b8;font-size:.75rem;transition:transform 0.3s;"></i>
                                </div>
                                <div class="ra-body" style="display:none;padding:4px 18px 14px 60px;font-size:.78rem;color:#475569;line-height:1.7;">
                                    <p>Expands milestone <strong>cash gifts</strong> to elderly Filipinos at key ages:</p>
                                    <ul style="margin-top:6px;padding-left:14px;">
                                        <li>Age 80: <strong>₱10,000</strong></li>
                                        <li>Age 85: <strong>₱10,000</strong></li>
                                        <li>Age 90: <strong>₱10,000</strong></li>
                                        <li>Age 95: <strong>₱10,000</strong></li>
                                        <li>Age 100+: <strong>₱100,000</strong> (Centenarian Award)</li>
                                    </ul>
                                    <p style="margin-top:6px;color:#92400e;background:#fef9c3;padding:5px 8px;border-radius:5px;font-size:.74rem;"><i class="fas fa-info-circle"></i> Gifts granted by the Office of the President via OSCA endorsement.</p>
                                </div>
                            </div>
                            <!-- Verified OSCA Contacts -->
                            <div class="ra-item" onclick="this.querySelector('.ra-body').style.display = this.querySelector('.ra-body').style.display==='none'?'block':'none'; this.querySelector('.ra-chevron').style.transform = this.querySelector('.ra-chevron').style.transform==='rotate(180deg)'?'rotate(0deg)':'rotate(180deg)';" style="cursor:pointer;">
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

            <div class="footer">
                <p>Centralized Profiling and Record Authentication System | Barangay <?php echo $barangayName; ?> &copy; <?php echo date('Y'); ?></p>
            </div>
        </div>
    </div>

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

        fetch('../api/barangay_dashboard_data.php', { headers: { 'Accept': 'application/json' } })
            .then(response => {
                if (!response.ok) throw new Error(`Request failed (${response.status})`);
                return response.json();
            })
            .then(result => {
                if (result.success) {
                    if (typeof Chart !== 'undefined') initializeCharts(result.data);
                    else showUnavailableCharts();
                    renderNotifications(result.data.notifications);
                    renderStatsStrip(result.data);
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
            'received':   { icon: 'fa-inbox',        color: '#94a3b8', bg: 'rgba(148,163,184,0.15)', label: 'Received'   },
            'pending':    { icon: 'fa-clock',        color: '#d97706', bg: 'rgba(245,158,11,0.14)', label: 'Pending'    },
            'for review': { icon: 'fa-search',        color: '#3b82f6', bg: 'rgba(59,130,246,0.12)',  label: 'For Review' },
            'verified':   { icon: 'fa-check',         color: '#14b8a6', bg: 'rgba(20,184,166,0.12)',  label: 'Verified'   },
            'deceased':   { icon: 'fa-cross',         color: '#64748b', bg: 'rgba(100,116,139,0.12)', label: 'Deceased'   },
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
                ? 'barangay_records.php'
                : 'submit_application.php';
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
                    <div style="font-size:0.76rem;color:#475569;margin-top:2px;">${typeLabel}</div>
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
        let interval = seconds / 31536000;
        if (interval > 1) return Math.floor(interval) + " years ago";
        interval = seconds / 2592000;
        if (interval > 1) return Math.floor(interval) + " months ago";
        interval = seconds / 86400;
        if (interval > 1) return Math.floor(interval) + " days ago";
        interval = seconds / 3600;
        if (interval > 1) return Math.floor(interval) + " hours ago";
        interval = seconds / 60;
        if (interval > 1) return Math.floor(interval) + " minutes ago";
        return Math.floor(seconds) + " seconds ago";
    }

    function renderStatsStrip(data) {
        // Update total
        const el = id => document.getElementById(id);
        if (el('statTotal')) el('statTotal').textContent = data.total_count ?? '0';

        // Map workflow status counts
        const workflowMap = {};
        (data.workflow_stats || []).forEach(row => {
            workflowMap[row.workflow_state] = parseInt(row.count);
        });
        // The Submit Application page contains all active states, not only
        // applications that are still at the first Received step.
        if (el('statReceived'))  el('statReceived').textContent  = data.queue_count ?? 0;
        if (el('statApproved'))  el('statApproved').textContent  = workflowMap['Verified'] ?? 0;
        document.querySelectorAll('.dashboard-loading').forEach(node => { node.classList.remove('dashboard-loading'); node.removeAttribute('aria-label'); });

    }

    function initializeCharts(data) {
        const textColor = 'rgba(0, 0, 0, 0.8)';
        const gridColor = 'rgba(0, 0, 0, 0.1)';

        // --- Status Chart ---
        const statusCtx = document.getElementById('statusChart')?.getContext('2d');
        if (statusCtx && data.workflow_stats) {
            const statusOrder = ['Received', 'Pending', 'For Review', 'Verified', 'Rejected', 'Deceased'];
            const workflowStats = [...data.workflow_stats].sort((a, b) => {
                const aIndex = statusOrder.indexOf(a.workflow_state);
                const bIndex = statusOrder.indexOf(b.workflow_state);
                return (aIndex === -1 ? statusOrder.length : aIndex) - (bIndex === -1 ? statusOrder.length : bIndex);
            });
            const labels = workflowStats.map(s => s.workflow_state);
            const counts = workflowStats.map(s => parseInt(s.count, 10));
            const statusColors = {
                'Received':   '#94a3b8',
                'Pending':    '#f59e0b',
                'For Review': '#3b82f6',
                'Verified':   '#14b8a6',
                'Rejected':   '#ef4444',
                'Deceased':   '#64748b'
            };
            const backgroundColors = labels.map(label => statusColors[label] || '#94a3b8');
            new Chart(statusCtx, {
                type: 'doughnut',
                data: { labels, datasets: [{ data: counts, backgroundColor: backgroundColors, borderWidth: 2, borderColor: '#fff' }] },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    animation: false,
                    plugins: { 
                        legend: { 
                            position: 'right',
                            labels: {
                                color: textColor
                            }
                        } 
                    } 
                }
            });
        }

            // --- Monthly Chart ---
            const monthlyCtx = document.getElementById('monthlyChart')?.getContext('2d');
            if (monthlyCtx && data.monthly) {
                // Generate an array of the last 12 months, including month number and year
                const twelveMonths = [];
                for (let i = 11; i >= 0; i--) { // Iterate backwards from 11 to 0 for correct chronological order
                    const d = new Date();
                    d.setMonth(d.getMonth() - i);
                    twelveMonths.push({
                        name: d.toLocaleString('default', { month: 'short' }),
                        month_num: d.getMonth() + 1, // getMonth() is 0-indexed
                        year: d.getFullYear()
                    });
                }

                const chartLabels = twelveMonths.map(m => `${m.name} ${m.year.toString().slice(2)}`); // e.g., "Nov 23"

                const pwdData = Array(12).fill(0);
                const seniorData = Array(12).fill(0);

                data.monthly.forEach(item => {
                    // Find the index in our twelveMonths array based on month number and year
                    const index = twelveMonths.findIndex(
                        m => m.month_num == item.month_num && m.year == item.year
                    );

                    if (index !== -1) { // If a matching month is found
                        if (item.application_type === 'pwd') { // Corrected to match database casing
                            pwdData[index] = item.count;
                        } else if (item.application_type === 'senior') { // Corrected to match database casing
                            seniorData[index] = item.count;
                        }
                    }
                });

                new Chart(monthlyCtx, {
                    type: 'bar',
                    data: {
                        labels: chartLabels, // Use the new chartLabels
                        datasets: [
                           
                            { label: 'Senior Citizen Applications', data: seniorData, backgroundColor: '#2ecc71' }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        plugins: {
                            legend: {
                                labels: {
                                    color: textColor
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    color: textColor
                                },
                                grid: {
                                    color: gridColor
                                }
                            },
                            x: {
                                ticks: {
                                    color: textColor
                                },
                                grid: {
                                    color: gridColor
                                }
                            }
                        }
                    }
                });
            }
            document.querySelectorAll('.chart-wrapper').forEach(el => el.classList.remove('is-loading'));
    }
    </script>
</body>
</html>
