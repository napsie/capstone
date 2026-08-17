<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/barangays_list.php';
require_once '../includes/audit_logger.php';

// Access control check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'department_admin') {
    header('Location: ../index.php');
    exit;
}

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$search = trim($_GET['search'] ?? '');
$barangayFilter = trim($_GET['barangay'] ?? 'all');
$typeFilter = trim($_GET['type'] ?? 'all');
$activeTab = trim($_GET['tab'] ?? 'applications');

// Fetch Archived Applications
$appWhere = ["is_archived = 1"];
$appParams = [];

if (!empty($search)) {
    $appWhere[] = "(id_number LIKE ? OR full_name LIKE ? OR lastName LIKE ? OR firstName LIKE ?)";
    $searchParam = "%$search%";
    $appParams[] = $searchParam;
    $appParams[] = $searchParam;
    $appParams[] = $searchParam;
    $appParams[] = $searchParam;
}

if ($barangayFilter !== 'all' && !empty($barangayFilter)) {
    $appWhere[] = "barangay = ?";
    $appParams[] = $barangayFilter;
}

if ($typeFilter !== 'all' && !empty($typeFilter)) {
    $appWhere[] = "application_type = ?";
    $appParams[] = $typeFilter;
}

$appSql = "SELECT id_number, full_name, application_type, barangay, date_submitted, archived_at, archived_by 
           FROM applications 
           WHERE " . implode(' AND ', $appWhere) . " 
           ORDER BY archived_at DESC";

try {
    $stmtApp = $conn->prepare($appSql);
    $stmtApp->execute($appParams);
    $archivedApplications = $stmtApp->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $archivedApplications = [];
}

// Fetch Archived Users
$userWhere = ["is_archived = 1"];
$userParams = [];

if (!empty($search)) {
    $userWhere[] = "(username LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $searchParam = "%$search%";
    $userParams[] = $searchParam;
    $userParams[] = $searchParam;
    $userParams[] = $searchParam;
    $userParams[] = $searchParam;
}

if ($barangayFilter !== 'all' && !empty($barangayFilter)) {
    $userWhere[] = "barangay = ?";
    $userParams[] = $barangayFilter;
}

$userSql = "SELECT id, username, first_name, last_name, email, role, barangay, archived_at, archived_by, profile_picture 
            FROM users 
            WHERE " . implode(' AND ', $userWhere) . " 
            ORDER BY archived_at DESC";

try {
    $stmtUsers = $conn->prepare($userSql);
    $stmtUsers->execute($userParams);
    $archivedUsers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $archivedUsers = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SENIORLINK — Department Master Archive</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/department-sidebar.css?v=1.1">
    <link rel="stylesheet" href="../assets/css/seniorlink-ui.css?v=3">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', 'Segoe UI', sans-serif; }
        :root { --primary:#0f172a; --secondary:#1e3a5f; --accent:#2563eb; --success:#10b981; --gray:#94a3b8; --bg:#f1f5f9; --card:#ffffff; }
        body { background-color: var(--bg); color: var(--primary); min-height: 100vh; }
        .container { display: flex; }
        .main-content { flex-grow: 1; padding: 24px; }

        /* ─── Page Header ─────────────────────────────────────────────────── */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .page-header-left .greeting { font-family: 'Inter', sans-serif; font-size: 0.98rem; font-weight: 500; color: #6b7280; margin-bottom: 6px; line-height: 1.3; }
        .page-header-left .greeting strong { color: #2563eb; font-weight: 700; }
        .page-header-left h1 { font-family: 'Inter', sans-serif; font-size: 2rem; font-weight: 800; letter-spacing: -0.02em; color: var(--primary); margin: 0; line-height: 1.05; }
        .page-header-left h1 span { color: var(--accent); }
        .page-header-left h1 i { color: #f59e0b; font-size: 1.6rem; }
        .header-user { display: flex; align-items: center; gap: 12px; border-radius: 30px; padding: 7px 13px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border: 3px solid transparent; background: linear-gradient(var(--card), var(--card)) padding-box, linear-gradient(135deg, #0f172a 0%, #3498db 100%) border-box; }
        .header-user img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent); }
        .header-user-info h3 { font-size: 0.9rem; font-weight: 700; color: var(--primary); margin: 0; }
        .header-user-info p  { font-size: 0.75rem; color: var(--gray); margin: 0; }

        .tab-nav { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #cbd5e1; }
        .tab-btn { padding: 12px 24px; font-weight: 600; border: none; background: none; cursor: pointer; color: #64748b; font-size: 0.95rem; border-bottom: 3px solid transparent; transition: all 0.2s; }
        .tab-btn.active { color: #2563eb; border-bottom-color: #2563eb; background: white; border-radius: 8px 8px 0 0; }
        .tab-btn i { margin-right: 8px; }

        .card { background: white; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); padding: 20px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
        .filter-bar { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
        .search-input, .select-input { padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem; outline: none; }
        .search-input { flex-grow: 1; min-width: 250px; }
        .search-input:focus, .select-input:focus { border-color: #2563eb; }

        .table-container { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem; }
        .table th { background: #0f172a; color: white; padding: 12px 16px; font-weight: 600; }
        .table td { padding: 14px 16px; border-bottom: 1px solid #e2e8f0; color: #334155; vertical-align: middle; }
        .table tbody tr:hover { background-color: #f8fafc; }

        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 6px; font-weight: 500; font-size: 0.85rem; border: none; cursor: pointer; text-decoration: none; transition: background 0.2s; }
        .btn-restore { background: #dcfce7; color: #15803d; }
        .btn-restore:hover { background: #bbf7d0; }
        .btn-danger { background: #fee2e2; color: #dc2626; }
        .btn-danger:hover { background: #fca5a5; }

        .badge-type { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
        .type-senior { background: #dbeafe; color: #1e40af; }
        .type-pension { background: #fef3c7; color: #b45309; }
        .type-burial { background: #f3e8ff; color: #6b21a8; }
        .type-default { background: #f1f5f9; color: #475569; }

        .empty-state { text-align: center; padding: 40px 20px; color: #64748b; }
        .empty-state i { font-size: 3rem; color: #cbd5e1; margin-bottom: 12px; }
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
                    <h1><i class="fas fa-archive"></i> Department Master <span>Archive</span></h1>
                    <p style="color:#64748b;font-size:0.9rem;margin-top:4px;">Central repository for archived applications and deactivated user accounts across Pasig City.</p>
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

            <div class="tab-nav">
                <button type="button" class="tab-btn <?php echo $activeTab !== 'users' ? 'active' : ''; ?>" onclick="switchTab('applications')">
                    <i class="fas fa-box-archive"></i> Archived Applications (<?php echo count($archivedApplications); ?>)
                </button>
                <button type="button" class="tab-btn <?php echo $activeTab === 'users' ? 'active' : ''; ?>" onclick="switchTab('users')">
                    <i class="fas fa-users-slash"></i> Archived User Accounts (<?php echo count($archivedUsers); ?>)
                </button>
            </div>

            <!-- TAB 1: ARCHIVED APPLICATIONS -->
            <div id="tab-applications" style="display: <?php echo $activeTab !== 'users' ? 'block' : 'none'; ?>;">
                <div class="card">
                    <form method="GET" class="filter-bar" id="appFilterForm">
                        <input type="hidden" name="tab" value="applications">
                        <input type="text" name="search" id="appSearchInput" class="search-input" placeholder="Search by Applicant Name or ID..." value="<?php echo htmlspecialchars($search); ?>">
                        <select name="barangay" class="select-input" onchange="this.form.submit()">
                            <option value="all">All Barangays</option>
                            <?php foreach ($barangays_list as $b): ?>
                                <option value="<?php echo htmlspecialchars($b); ?>" <?php echo $barangayFilter === $b ? 'selected' : ''; ?>><?php echo htmlspecialchars($b); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="type" class="select-input" onchange="this.form.submit()">
                            <option value="all" <?php echo $typeFilter === 'all' ? 'selected' : ''; ?>>All Types</option>
                            <option value="senior" <?php echo $typeFilter === 'senior' ? 'selected' : ''; ?>>Senior ID</option>
                            <option value="pension" <?php echo $typeFilter === 'pension' ? 'selected' : ''; ?>>Social Pension</option>
                            <option value="burial" <?php echo $typeFilter === 'burial' ? 'selected' : ''; ?>>Burial Assistance</option>
                        </select>
                        <button type="submit" class="btn btn-restore"><i class="fas fa-search"></i> Filter</button>
                        <?php if (!empty($search) || $barangayFilter !== 'all' || $typeFilter !== 'all'): ?>
                            <a href="department_archive.php" class="btn" style="background:#e2e8f0;color:#475569;"><i class="fas fa-undo"></i> Reset</a>
                        <?php endif; ?>
                    </form>

                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID Number</th>
                                    <th>Applicant Name</th>
                                    <th>Type</th>
                                    <th>Barangay</th>
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
                                                <p>No archived applications found.</p>
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
                                            <td><span class="badge-type <?php echo $typeClass; ?>"><?php echo htmlspecialchars($app['application_type']); ?></span></td>
                                            <td><?php echo htmlspecialchars($app['barangay']); ?></td>
                                            <td><?php echo !empty($app['archived_at']) ? date('M d, Y h:i A', strtotime($app['archived_at'])) : '—'; ?></td>
                                            <td><?php echo htmlspecialchars($app['archived_by'] ?: 'System'); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-restore restore-app-btn" data-id="<?php echo $app['id_number']; ?>" data-name="<?php echo htmlspecialchars($app['full_name']); ?>">
                                                    <i class="fas fa-undo"></i> Restore
                                                </button>
                                                <button type="button" class="btn btn-danger hard-delete-app-btn" data-id="<?php echo $app['id_number']; ?>" data-name="<?php echo htmlspecialchars($app['full_name']); ?>">
                                                    <i class="fas fa-trash-alt"></i> Delete Permanently
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

            <!-- TAB 2: ARCHIVED USER ACCOUNTS -->
            <div id="tab-users" style="display: <?php echo $activeTab === 'users' ? 'block' : 'none'; ?>;">
                <div class="card">
                    <form method="GET" class="filter-bar" id="userFilterForm">
                        <input type="hidden" name="tab" value="users">
                        <input type="text" name="search" id="userSearchInput" class="search-input" placeholder="Search by Username, Name or Email..." value="<?php echo htmlspecialchars($search); ?>">
                        <select name="barangay" class="select-input" onchange="this.form.submit()">
                            <option value="all">All Barangays</option>
                            <?php foreach ($barangays_list as $b): ?>
                                <option value="<?php echo htmlspecialchars($b); ?>" <?php echo $barangayFilter === $b ? 'selected' : ''; ?>><?php echo htmlspecialchars($b); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-restore"><i class="fas fa-search"></i> Filter</button>
                        <?php if (!empty($search) || $barangayFilter !== 'all'): ?>
                            <a href="department_archive.php?tab=users" class="btn" style="background:#e2e8f0;color:#475569;"><i class="fas fa-undo"></i> Reset</a>
                        <?php endif; ?>
                    </form>

                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Barangay</th>
                                    <th>Archived Date</th>
                                    <th>Archived By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($archivedUsers)): ?>
                                    <tr>
                                        <td colspan="8">
                                            <div class="empty-state">
                                                <i class="fas fa-user-slash"></i>
                                                <p>No archived user accounts found.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($archivedUsers as $user): ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <img src="../images/LOGO.jpg" alt="User" style="width:32px;height:32px;border-radius:50%;">
                                                    <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $user['role']))); ?></td>
                                            <td><?php echo htmlspecialchars($user['barangay'] ?: 'City Department'); ?></td>
                                            <td><?php echo !empty($user['archived_at']) ? date('M d, Y h:i A', strtotime($user['archived_at'])) : '—'; ?></td>
                                            <td><?php echo htmlspecialchars($user['archived_by'] ?: 'System'); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-restore restore-user-btn" data-id="<?php echo $user['id']; ?>" data-name="<?php echo htmlspecialchars($user['username']); ?>">
                                                    <i class="fas fa-undo"></i> Restore User
                                                </button>
                                                <form action="delete_user.php" method="POST" style="display:inline;">
                                                    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="permanent" value="1">
                                                    <input type="hidden" name="redirect" value="archive">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <button type="submit" name="deleteUser" class="btn btn-danger" onclick="return confirm('PERMANENT DELETION WARNING: Are you sure you want to permanently delete this user account? This cannot be undone.')">
                                                        <i class="fas fa-trash-alt"></i> Delete Permanently
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/sidebar-toggle.js"></script>
    <script src="../assets/js/carelink-feedback.js?v=2"></script>
    <script>
        (function(){
            const h = new Date().getHours();
            const g = h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
            document.getElementById('greetingMsg').innerHTML = `${g}, <strong><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></strong>!`;
        })();
    </script>
    <script>
        function switchTab(tabName) {
            const appTab = document.getElementById('tab-applications');
            const userTab = document.getElementById('tab-users');
            const btns = document.querySelectorAll('.tab-btn');

            if (tabName === 'users') {
                appTab.style.display = 'none';
                userTab.style.display = 'block';
                btns[0].classList.remove('active');
                btns[1].classList.add('active');
            } else {
                appTab.style.display = 'block';
                userTab.style.display = 'none';
                btns[0].classList.add('active');
                btns[1].classList.remove('active');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Restore Application
            document.querySelectorAll('.restore-app-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const appId = this.dataset.id;
                    const appName = this.dataset.name;
                    
                    window.showCarelinkConfirm(`Restore application ${appId} (${appName}) back to active status?`, function() {
                        const formData = new FormData();
                        formData.append('id', appId);

                        fetch('../api/restore_application.php', { method: 'POST', body: formData })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                window.showCarelinkResult(data.message, true);
                                setTimeout(() => location.reload(), 1200);
                            } else {
                                window.showCarelinkResult(data.message || 'Failed to restore.', false);
                            }
                        });
                    });
                });
            });

            // Hard Delete Application
            document.querySelectorAll('.hard-delete-app-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const appId = this.dataset.id;
                    const appName = this.dataset.name;
                    
                    window.showCarelinkConfirm(`PERMANENT DELETE WARNING: Are you sure you want to permanently delete application ${appId} (${appName})? All attached documents will be permanently removed.`, function() {
                        const formData = new FormData();
                        formData.append('id', appId);
                        formData.append('permanent', '1');

                        fetch('../api/delete_application.php', { method: 'POST', body: formData })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                window.showCarelinkResult(data.message, true);
                                setTimeout(() => location.reload(), 1200);
                            } else {
                                window.showCarelinkResult(data.message || 'Failed to delete.', false);
                            }
                        });
                    });
                });
            });

            // Restore User
            document.querySelectorAll('.restore-user-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const userId = this.dataset.id;
                    const userName = this.dataset.name;
                    
                    window.showCarelinkConfirm(`Reactivate and restore user account '${userName}'?`, function() {
                        const formData = new FormData();
                        formData.append('id', userId);

                        fetch('../api/restore_user.php', { method: 'POST', body: formData })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                window.showCarelinkResult(data.message, true);
                                setTimeout(() => location.reload(), 1200);
                            } else {
                                window.showCarelinkResult(data.message || 'Failed to restore user.', false);
                            }
                        });
                    });
                });
            });
            // Debounced filtering for Applications and Users
            const appSearch = document.getElementById('appSearchInput');
            const appForm = document.getElementById('appFilterForm');
            if (appSearch && appForm) {
                let timeout;
                appSearch.addEventListener('input', () => {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => appForm.submit(), 400);
                });
                if (appSearch.value.length > 0 && "<?php echo $activeTab; ?>" === 'applications') {
                    const len = appSearch.value.length;
                    appSearch.focus();
                    appSearch.setSelectionRange(len, len);
                }
            }

            const userSearch = document.getElementById('userSearchInput');
            const userForm = document.getElementById('userFilterForm');
            if (userSearch && userForm) {
                let timeout;
                userSearch.addEventListener('input', () => {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => userForm.submit(), 400);
                });
                if (userSearch.value.length > 0 && "<?php echo $activeTab; ?>" === 'users') {
                    const len = userSearch.value.length;
                    userSearch.focus();
                    userSearch.setSelectionRange(len, len);
                }
            }
        });
    </script>
</body>
</html>
