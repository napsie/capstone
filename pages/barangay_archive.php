<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/application_types.php';
require_once '../includes/audit_logger.php';

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['barangay_staff', 'department_admin', 'super_admin'])) {
    header('Location: ../index.php');
    exit;
}

$userRole = $_SESSION['role'];
$userBarangay = $_SESSION['barangay'] ?? '';
$barangayName = htmlspecialchars($userBarangay ?: 'Barangay');

// Handle search and filters
$search = trim($_GET['search'] ?? '');
$typeFilter = trim($_GET['type'] ?? 'all');
$activeTab = trim($_GET['tab'] ?? 'applications');

// Fetch archived applications
$appWhere = ["is_archived = 1"];
$appParams = [];

if ($userRole === 'barangay_staff' && !empty($userBarangay)) {
    $appWhere[] = "barangay = ?";
    $appParams[] = $userBarangay;
}

if (!empty($search)) {
    $appWhere[] = "(id_number LIKE ? OR full_name LIKE ? OR lastName LIKE ? OR firstName LIKE ?)";
    $searchParam = "%$search%";
    $appParams[] = $searchParam;
    $appParams[] = $searchParam;
    $appParams[] = $searchParam;
    $appParams[] = $searchParam;
}

if ($typeFilter !== 'all' && !empty($typeFilter)) {
    $appWhere[] = "application_type = ?";
    $appParams[] = $typeFilter;
}

$appSql = "SELECT id_number, full_name, application_type, barangay, date_submitted, archived_at, archived_by 
           FROM applications 
           WHERE " . implode(' AND ', $appWhere) . " 
           ORDER BY archived_at DESC, date_submitted DESC";

try {
    $stmtApp = $conn->prepare($appSql);
    $stmtApp->execute($appParams);
    $archivedApplications = $stmtApp->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $archivedApplications = [];
    $error = "Error fetching archived applications: " . $e->getMessage();
}

// Fetch audit trail for barangay
$auditWhere = [];
$auditParams = [];

if ($userRole === 'barangay_staff' && !empty($userBarangay)) {
    $auditWhere[] = "(barangay = ? OR role = 'barangay_staff')";
    $auditParams[] = $userBarangay;
}

$auditSql = "SELECT * FROM audit_trail " . 
            (!empty($auditWhere) ? "WHERE " . implode(' AND ', $auditWhere) : "") . 
            " ORDER BY created_at DESC LIMIT 100";

try {
    $stmtAudit = $conn->prepare($auditSql);
    $stmtAudit->execute($auditParams);
    $auditLogs = $stmtAudit->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $auditLogs = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SENIORLINK — Barangay Archive</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/barangay-sidebar.css?v=1.1">
    <link rel="stylesheet" href="../assets/css/carelink-theme.css?v=3">
    <link rel="stylesheet" href="../assets/css/seniorlink-ui.css?v=3">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        body { background-color: #f8fafc; color: #0f172a; min-height: 100vh; }
        
        .container { display: flex; min-height: 100vh; }
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width, 220px);
            padding: 28px;
            background: #f8fafc;
            min-height: 100vh;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e2e8f0;
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
            color: #0f172a;
            margin: 0;
            line-height: 1.05;
        }
        .page-header-left h1 span { color: #2563eb; }
        .header-user {
            display: flex;
            align-items: center;
            gap: 12px;
            border-radius: 30px;
            padding: 7px 13px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 3px solid transparent;
            background: linear-gradient(#fff, #fff) padding-box, linear-gradient(135deg, #0f172a 0%, #3498db 100%) border-box;
        }
        .header-user img {
            width: 40px; height: 40px;
            border-radius: 50%; object-fit: cover;
            border: 2px solid #2563eb;
        }
        .header-user-info h3 { font-size: 0.9rem; font-weight: 700; color: #0f172a; margin: 0; }
        .header-user-info p  { font-size: 0.75rem; color: #94a3b8; margin: 0; }

        .tab-nav { display: flex; gap: 10px; margin-bottom: 24px; border-bottom: 2px solid #cbd5e1; }
        .tab-btn { padding: 12px 24px; font-weight: 600; border: none; background: none; cursor: pointer; color: #64748b; font-size: 0.95rem; border-bottom: 3px solid transparent; transition: all 0.2s; }
        .tab-btn.active { color: #2563eb; border-bottom-color: #2563eb; background: white; border-radius: 8px 8px 0 0; }
        .tab-btn i { margin-right: 8px; }

        .card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px rgba(0,0,0,0.05); padding: 24px; margin-bottom: 24px; }
        
        .filter-bar { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; align-items: center; }
        .search-input, .select-input { padding: 10px 14px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; outline: none; transition: border-color 0.2s; }
        .search-input { flex-grow: 1; min-width: 250px; }
        .search-input:focus, .select-input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.12); }
        
        .table-container { overflow-x: auto; border-radius: 8px; border: 1px solid #e2e8f0; }
        .table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.88rem; }
        .table th { background: #0f172a; color: white; padding: 14px 16px; font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.04em; }
        .table td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
        .table tbody tr:hover { background-color: #f8fafc; }

        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 6px; font-weight: 600; font-size: 0.82rem; border: none; cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .btn-restore { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .btn-restore:hover { background: #bbf7d0; transform: translateY(-1px); }
        .btn-view { background: #2563eb; color: white; }
        .btn-view:hover { background: #1d4ed8; }

        .badge-type { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
        .type-senior { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
        .type-pension { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .type-burial { background: #f3e8ff; color: #6b21a8; border: 1px solid #e9d5ff; }
        .type-default { background: #f1f5f9; color: #475569; }

        .empty-state { text-align: center; padding: 50px 20px; color: #64748b; }
        .empty-state i { font-size: 3rem; color: #cbd5e1; margin-bottom: 14px; display: block; }
        .empty-state p { font-size: 1rem; font-weight: 600; }

        /* Audit Log List */
        .audit-item { padding: 16px; border-bottom: 1px solid #f1f5f9; display: flex; gap: 14px; align-items: flex-start; }
        .audit-icon { width: 38px; height: 38px; border-radius: 50%; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; flex-shrink: 0; }
        .audit-details { flex-grow: 1; }
        .audit-action { font-weight: 700; color: #0f172a; margin-bottom: 3px; font-size: 0.92rem; }
        .audit-meta { font-size: 0.8rem; color: #64748b; }
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
                <h1>Barangay <span><?php echo $barangayName; ?></span> Archive</h1>
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
                    <p><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $_SESSION['role']))); ?> · Barangay <?php echo $barangayName; ?></p>
                </div>
            </div>
        </div>

        <div class="tab-nav">
            <button type="button" class="tab-btn <?php echo $activeTab !== 'audit' ? 'active' : ''; ?>" onclick="switchTab('applications')">
                <i class="fas fa-box-archive"></i> Archived Applications (<?php echo count($archivedApplications); ?>)
            </button>
            <button type="button" class="tab-btn <?php echo $activeTab === 'audit' ? 'active' : ''; ?>" onclick="switchTab('audit')">
                <i class="fas fa-history"></i> Barangay Audit Log (<?php echo count($auditLogs); ?>)
            </button>
        </div>

        <!-- TAB 1: ARCHIVED APPLICATIONS -->
        <div id="tab-applications" style="display: <?php echo $activeTab !== 'audit' ? 'block' : 'none'; ?>;">
            <div class="card">
                <form method="GET" class="filter-bar" id="barangayArchiveFilterForm">
                    <input type="hidden" name="tab" value="applications">
                    <input type="text" name="search" id="searchArchiveInput" class="search-input" placeholder="Search by Applicant Name or ID..." value="<?php echo htmlspecialchars($search); ?>">
                    <select name="type" class="select-input" onchange="this.form.submit()">
                        <option value="all" <?php echo $typeFilter === 'all' ? 'selected' : ''; ?>>All Types</option>
                        <?php foreach (getApplicationTypeOptions() as $val => $label): ?>
                            <option value="<?php echo htmlspecialchars($val); ?>" <?php echo $typeFilter === $val ? 'selected' : ''; ?>><?php echo htmlspecialchars(applicationTypeLabel($val)); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-view"><i class="fas fa-search"></i> Filter</button>
                    <?php if (!empty($search) || $typeFilter !== 'all'): ?>
                        <a href="barangay_archive.php" class="btn" style="background:#e2e8f0;color:#475569;"><i class="fas fa-undo"></i> Reset</a>
                    <?php endif; ?>
                </form>

                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID Number</th>
                                <th>Applicant Name</th>
                                <th>Type</th>
                                <th>Date Submitted</th>
                                <th>Archived Date</th>
                                <th>Archived By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($archivedApplications)): ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <i class="fas fa-box-open"></i>
                                            <p>No archived applications found</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($archivedApplications as $app): 
                                    $typeClass = 'type-default';
                                    if ($app['application_type'] === 'senior') $typeClass = 'type-senior';
                                    elseif ($app['application_type'] === 'pension') $typeClass = 'type-pension';
                                    elseif ($app['application_type'] === 'burial') $typeClass = 'type-burial';
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($app['id_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                                        <td><span class="badge-type <?php echo $typeClass; ?>"><?php echo htmlspecialchars(applicationTypeLabel($app['application_type'])); ?></span></td>
                                        <td><?php echo date('M d, Y', strtotime($app['date_submitted'])); ?></td>
                                        <td><?php echo !empty($app['archived_at']) ? date('M d, Y h:i A', strtotime($app['archived_at'])) : '—'; ?></td>
                                        <td><?php echo htmlspecialchars($app['archived_by'] ?: 'System'); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-restore restore-app-btn" data-id="<?php echo $app['id_number']; ?>" data-name="<?php echo htmlspecialchars($app['full_name']); ?>">
                                                <i class="fas fa-undo"></i> Restore
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 2: BARANGAY AUDIT LOG -->
        <div id="tab-audit" style="display: <?php echo $activeTab === 'audit' ? 'block' : 'none'; ?>;">
            <div class="card">
                <h3 style="font-size: 1.1rem; margin-bottom: 16px; color: #0f172a;"><i class="fas fa-list-ol"></i> Activity Stream</h3>
                <?php if (empty($auditLogs)): ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <p>No activity logs recorded yet</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($auditLogs as $log): ?>
                        <div class="audit-item">
                            <div class="audit-icon"><i class="fas fa-shield-alt"></i></div>
                            <div class="audit-details">
                                <div class="audit-action"><?php echo htmlspecialchars($log['action']); ?></div>
                                <div style="font-size: 0.9rem; color: #334155; margin-bottom: 4px;"><?php echo htmlspecialchars($log['description']); ?></div>
                                <div class="audit-meta">
                                    <span><i class="far fa-user"></i> <?php echo htmlspecialchars($log['username']); ?> (<?php echo htmlspecialchars($log['role']); ?>)</span> &bull; 
                                    <span><i class="far fa-clock"></i> <?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?></span> &bull; 
                                    <span>IP: <?php echo htmlspecialchars($log['ip_address']); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

    <script src="../assets/js/sidebar-toggle.js"></script>
    <script src="../assets/js/carelink-feedback.js?v=2"></script>
    <script>
        /* Greeting */
        (function(){
            const h = new Date().getHours();
            const g = h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
            const fn = <?php echo json_encode($_SESSION['first_name'] ?? ''); ?>;
            const ln = <?php echo json_encode($_SESSION['last_name'] ?? ''); ?>;
            const el = document.getElementById('greetingMsg');
            if (el) el.innerHTML = `${g}, <strong>${fn} ${ln}</strong>!`;
        })();

        function switchTab(tabName) {
            const appTab = document.getElementById('tab-applications');
            const auditTab = document.getElementById('tab-audit');
            const btns = document.querySelectorAll('.tab-btn');

            if (tabName === 'audit') {
                appTab.style.display = 'none';
                auditTab.style.display = 'block';
                btns[0].classList.remove('active');
                btns[1].classList.add('active');
            } else {
                appTab.style.display = 'block';
                auditTab.style.display = 'none';
                btns[0].classList.add('active');
                btns[1].classList.remove('active');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchArchiveInput');
            const filterForm = document.getElementById('barangayArchiveFilterForm');
            if (searchInput && filterForm) {
                let searchTimeout;
                searchInput.addEventListener('input', () => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        filterForm.submit();
                    }, 400); // 400ms debounce
                });

                // Keep cursor at the end on load
                if (searchInput.value.length > 0) {
                    const len = searchInput.value.length;
                    searchInput.focus();
                    searchInput.setSelectionRange(len, len);
                }
            }

            document.querySelectorAll('.restore-app-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const appId = this.dataset.id;
                    const appName = this.dataset.name;
                    
                    window.showCarelinkConfirm(`Are you sure you want to restore application ${appId} (${appName}) back to the active queue?`, function() {
                        const formData = new FormData();
                        formData.append('id', appId);

                        fetch('../api/restore_application.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                window.showCarelinkResult(data.message, true);
                                setTimeout(() => location.reload(), 1200);
                            } else {
                                window.showCarelinkResult(data.message || 'Failed to restore application.', false);
                            }
                        })
                        .catch(err => {
                            console.error('Error restoring application:', err);
                            window.showCarelinkResult('An error occurred while communicating with the server.', false);
                        });
                    });
                });
            });
        });
    </script>
</body>
</html>
