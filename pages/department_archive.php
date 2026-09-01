<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/barangays_list.php';
require_once '../includes/application_types.php';
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
$auditEventFilter = trim($_GET['event'] ?? 'all');
$activeTab = trim($_GET['tab'] ?? 'applications');
$auditPage = max(1, (int)($_GET['audit_page'] ?? 1));
$auditPerPage = 10;
$allowedAuditEventFilters = ['all', 'login', 'logout', 'failed', 'archive', 'restore'];
if (!in_array($auditEventFilter, $allowedAuditEventFilters, true)) $auditEventFilter = 'all';

// Resolve legacy archived_by usernames to staff full names.
$archiveActorNames = [];
try {
    $actorRows = $conn->query("SELECT username, TRIM(CONCAT(first_name, ' ', last_name)) AS full_name FROM users")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($actorRows as $actor) $archiveActorNames[$actor['username']] = $actor['full_name'];
} catch (PDOException $e) {
    $archiveActorNames = [];
}

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
$representedBarangays = array_values(array_unique(array_filter(array_column($archivedApplications, 'barangay'))));

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

// Department administrators receive the citywide audit stream. Barangay is
// optional because department-level actions are not tied to one barangay.
$auditWhere = [];
$auditParams = [];
if ($barangayFilter !== 'all' && $barangayFilter !== '') {
    $auditWhere[] = 'barangay = ?';
    $auditParams[] = $barangayFilter;
}
if ($search !== '') {
    $auditWhere[] = '(action LIKE ? OR description LIKE ? OR username LIKE ?)';
    $auditSearch = "%{$search}%";
    array_push($auditParams, $auditSearch, $auditSearch, $auditSearch);
}
$auditEventConditions = [
    'login' => "action = 'LOGIN'",
    'logout' => "action = 'LOGOUT'",
    'failed' => "action = 'FAILED_LOGIN'",
    'archive' => "action LIKE 'ARCHIVE_%'",
    'restore' => "action LIKE 'RESTORE_%'",
];
if (isset($auditEventConditions[$auditEventFilter])) {
    $auditWhere[] = $auditEventConditions[$auditEventFilter];
}
$auditWhereSql = $auditWhere ? ' WHERE ' . implode(' AND ', $auditWhere) : '';
try {
    $auditCountStmt = $conn->prepare('SELECT COUNT(*) FROM audit_trail' . $auditWhereSql);
    $auditCountStmt->execute($auditParams);
    $auditTotal = (int)$auditCountStmt->fetchColumn();
    $auditTotalPages = max(1, (int)ceil($auditTotal / $auditPerPage));
    $auditPage = min($auditPage, $auditTotalPages);
    $auditOffset = ($auditPage - 1) * $auditPerPage;
    $auditSql = 'SELECT * FROM audit_trail' . $auditWhereSql
        . " ORDER BY created_at DESC LIMIT {$auditPerPage} OFFSET {$auditOffset}";
    $auditStmt = $conn->prepare($auditSql);
    $auditStmt->execute($auditParams);
    $auditLogs = $auditStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $auditLogs = [];
    $auditTotal = 0;
    $auditTotalPages = 1;
    $auditPage = 1;
    $auditOffset = 0;
}
$auditFrom = $auditTotal > 0 ? $auditOffset + 1 : 0;
$auditTo = min($auditOffset + count($auditLogs), $auditTotal);
$auditStartPage = max(1, min($auditPage - 2, $auditTotalPages - 4));
$auditEndPage = min($auditTotalPages, $auditStartPage + 4);
$auditPageUrl = static function (int $page): string {
    $params = $_GET;
    $params['tab'] = 'audit';
    $params['audit_page'] = max(1, $page);
    return 'department_archive.php?' . htmlspecialchars(http_build_query($params), ENT_QUOTES, 'UTF-8');
};
$auditHasFilters = $search !== '' || $barangayFilter !== 'all' || $auditEventFilter !== 'all';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SENIORLINK — Department Master Archive</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/department-sidebar.css?v=4">
    <link rel="stylesheet" href="../assets/css/seniorlink-ui.css?v=16">
    <script src="../assets/js/modal-hci.js?v=2" defer></script>
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

        .badge-type { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
        .type-senior { background: #dbeafe; color: #1e40af; }
        .type-pension { background: #fef3c7; color: #b45309; }
        .type-burial { background: #f3e8ff; color: #6b21a8; }
        .type-default { background: #f1f5f9; color: #475569; }

        .empty-state { text-align: center; padding: 40px 20px; color: #64748b; }
        .empty-state i { font-size: 3rem; color: #cbd5e1; margin-bottom: 12px; }
        .audit-item { padding: 16px; border-bottom: 1px solid #f1f5f9; display: flex; gap: 14px; align-items: flex-start; }
        .audit-item:last-child { border-bottom: 0; }
        .audit-icon { width: 38px; height: 38px; border-radius: 50%; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; flex-shrink: 0; }
        .audit-details { flex-grow: 1; min-width: 0; }
        .audit-action { font-weight: 700; color: #0f172a; margin-bottom: 3px; font-size: 0.92rem; }
        .audit-description { font-size: 0.9rem; color: #334155; margin-bottom: 5px; overflow-wrap: anywhere; }
        .audit-meta { display: flex; gap: 7px 14px; flex-wrap: wrap; font-size: 0.8rem; color: #64748b; }
        .audit-barangay { display: inline-flex; align-items: center; gap: 5px; color: #1d4ed8; font-weight: 650; }
    </style>
    <link rel="stylesheet" href="../assets/css/archive-details.css?v=2">
    <link rel="stylesheet" href="../assets/css/system-header.css?v=1">
    <link rel="stylesheet" href="../assets/css/system-sidebar.css?v=3">
    <link rel="stylesheet" href="../assets/css/audit-log.css?v=2">
    <link rel="stylesheet" href="../assets/css/table-pagination.css?v=1">
    <script src="../assets/js/table-pagination.js?v=1" defer></script>
</head>
<body>
    <div class="container">
        <?php include '../partials/department_sidebar.php'; ?>

        <div class="main-content">
            <!-- Page Header -->
            <div class="header">
                <div class="header-content">
                    <div class="greeting" id="greetingMsg"></div>
                    <h1>Department Master <span>Archive</span></h1>
                </div>
                <div class="header-actions">
                    <div class="user-info">
                    <div class="user-avatar">
                    <?php
                        $profilePic = isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : 'default.jpg';
                        $profilePicPath = '../images/profile_pictures/' . $profilePic;
                        if (!file_exists($profilePicPath) || is_dir($profilePicPath)) {
                            $profilePicPath = '../images/profile_pictures/default.jpg';
                        }
                    ?>
                    <img src="<?php echo $profilePicPath; ?>" alt="Profile Picture">
                    </div>
                    <div class="user-details">
                        <h2><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></h2>
                        <p><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $_SESSION['role']))); ?> · Pasig City</p>
                    </div>
                    </div>
                </div>
            </div>

            <p style="color:#64748b;font-size:0.9rem;margin:-12px 0 20px;">Central repository for archived applications and deactivated user accounts across Pasig City.</p>

            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:-8px 0 20px;padding:10px 13px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;color:#1e40af;font-size:0.82rem;font-weight:650;">
                <i class="fas fa-city"></i>
                <?php if ($barangayFilter === 'all'): ?>
                    Citywide view: all barangays are included. <?php echo count($representedBarangays); ?> barangay<?php echo count($representedBarangays) === 1 ? '' : 's'; ?> currently represented in archived applications.
                <?php else: ?>
                    Filtered view: Barangay <?php echo htmlspecialchars($barangayFilter); ?>.
                <?php endif; ?>
            </div>

            <div class="tab-nav" role="tablist" aria-label="Archive sections">
                <button type="button" id="tabButtonApplications" role="tab" aria-controls="tab-applications" aria-selected="<?php echo $activeTab === 'applications' ? 'true' : 'false'; ?>" class="tab-btn <?php echo $activeTab === 'applications' ? 'active' : ''; ?>" onclick="switchTab('applications')">
                    <i class="fas fa-box-archive"></i> Archived Applications (<?php echo count($archivedApplications); ?>)
                </button>
                <button type="button" id="tabButtonUsers" role="tab" aria-controls="tab-users" aria-selected="<?php echo $activeTab === 'users' ? 'true' : 'false'; ?>" class="tab-btn <?php echo $activeTab === 'users' ? 'active' : ''; ?>" onclick="switchTab('users')">
                    <i class="fas fa-users-slash"></i> Archived User Accounts (<?php echo count($archivedUsers); ?>)
                </button>
                <button type="button" id="tabButtonAudit" role="tab" aria-controls="tab-audit" aria-selected="<?php echo $activeTab === 'audit' ? 'true' : 'false'; ?>" class="tab-btn <?php echo $activeTab === 'audit' ? 'active' : ''; ?>" onclick="switchTab('audit')">
                    <i class="fas fa-clock-rotate-left"></i> Citywide Audit Log (<?php echo number_format($auditTotal); ?>)
                </button>
            </div>

            <!-- TAB 1: ARCHIVED APPLICATIONS -->
            <div id="tab-applications" role="tabpanel" aria-labelledby="tabButtonApplications" aria-hidden="<?php echo $activeTab === 'applications' ? 'false' : 'true'; ?>" style="display: <?php echo $activeTab === 'applications' ? 'block' : 'none'; ?>;">
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
                            <?php foreach (getApplicationTypeOptions() as $value => $label): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $typeFilter === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
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
                            <tbody data-paginate="10" data-pagination-label="Archived application pages">
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
                                        <tr class="archive-application-row" data-id="<?php echo htmlspecialchars($app['id_number']); ?>" data-name="<?php echo htmlspecialchars($app['full_name']); ?>" tabindex="0" role="button" aria-label="Open archived application for <?php echo htmlspecialchars($app['full_name']); ?>">
                                            <td><strong><?php echo htmlspecialchars($app['id_number']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                                            <td><span class="badge-type <?php echo $typeClass; ?>"><?php echo htmlspecialchars(applicationTypeLabel($app['application_type'])); ?></span></td>
                                            <td><?php echo htmlspecialchars($app['barangay']); ?></td>
                                            <td><?php echo !empty($app['archived_at']) ? date('M d, Y h:i A', strtotime($app['archived_at'])) : '—'; ?></td>
                                            <td><?php echo htmlspecialchars($archiveActorNames[$app['archived_by']] ?? ($app['archived_by'] ?: 'System')); ?></td>
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

            <!-- TAB 2: ARCHIVED USER ACCOUNTS -->
            <div id="tab-users" role="tabpanel" aria-labelledby="tabButtonUsers" aria-hidden="<?php echo $activeTab === 'users' ? 'false' : 'true'; ?>" style="display: <?php echo $activeTab === 'users' ? 'block' : 'none'; ?>;">
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
                            <tbody data-paginate="10" data-pagination-label="Archived user pages">
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
                                            <td><?php echo htmlspecialchars($archiveActorNames[$user['archived_by']] ?? ($user['archived_by'] ?: 'System')); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-restore restore-user-btn" data-id="<?php echo $user['id']; ?>" data-name="<?php echo htmlspecialchars($user['username']); ?>">
                                                    <i class="fas fa-undo"></i> Restore User
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

            <!-- TAB 3: CITYWIDE AUDIT LOG -->
            <div id="tab-audit" role="tabpanel" aria-labelledby="tabButtonAudit" aria-hidden="<?php echo $activeTab === 'audit' ? 'false' : 'true'; ?>" style="display: <?php echo $activeTab === 'audit' ? 'block' : 'none'; ?>;">
                <section class="card audit-panel" aria-labelledby="citywideAuditHeading">
                    <header class="audit-panel-header">
                        <span class="audit-panel-header__icon"><i class="fas fa-shield-halved" aria-hidden="true"></i></span>
                        <div class="audit-panel-header__copy">
                            <h2 id="citywideAuditHeading">Citywide Activity Stream</h2>
                            <p>Review security and system activity across the department and all barangays.</p>
                        </div>
                        <span class="audit-count"><?php echo number_format($auditTotal); ?> event<?php echo $auditTotal === 1 ? '' : 's'; ?></span>
                    </header>

                    <form method="GET" class="audit-filters" id="auditFilterForm">
                        <input type="hidden" name="tab" value="audit">
                        <div class="audit-filter-field">
                            <label for="auditSearch">Search activity</label>
                            <div class="audit-search"><i class="fas fa-search" aria-hidden="true"></i><input type="search" id="auditSearch" name="search" placeholder="Action, description, or staff name" value="<?php echo htmlspecialchars($search); ?>"></div>
                        </div>
                        <div class="audit-filter-field">
                            <label for="auditBarangay">Location</label>
                            <select name="barangay" id="auditBarangay">
                                <option value="all">All locations</option>
                                <?php foreach ($barangays_list as $b): ?>
                                    <option value="<?php echo htmlspecialchars($b); ?>" <?php echo $barangayFilter === $b ? 'selected' : ''; ?>>Barangay <?php echo htmlspecialchars($b); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="audit-filter-field">
                            <label for="auditEvent">Event type</label>
                            <select name="event" id="auditEvent">
                                <option value="all" <?php echo $auditEventFilter === 'all' ? 'selected' : ''; ?>>All events</option>
                                <option value="login" <?php echo $auditEventFilter === 'login' ? 'selected' : ''; ?>>Sign-ins</option>
                                <option value="logout" <?php echo $auditEventFilter === 'logout' ? 'selected' : ''; ?>>Sign-outs</option>
                                <option value="failed" <?php echo $auditEventFilter === 'failed' ? 'selected' : ''; ?>>Failed sign-ins</option>
                                <option value="archive" <?php echo $auditEventFilter === 'archive' ? 'selected' : ''; ?>>Archived items</option>
                                <option value="restore" <?php echo $auditEventFilter === 'restore' ? 'selected' : ''; ?>>Restored items</option>
                            </select>
                        </div>
                        <a href="department_archive.php?tab=audit" class="audit-filter-clear" <?php echo $auditHasFilters ? '' : 'hidden'; ?>><i class="fas fa-rotate-left" aria-hidden="true"></i> Clear</a>
                        <div class="audit-filter-help" aria-live="polite"><i class="fas fa-circle-info" aria-hidden="true"></i> Filters update automatically. Showing <?php echo number_format($auditFrom); ?>&ndash;<?php echo number_format($auditTo); ?> of <?php echo number_format($auditTotal); ?> matching events.</div>
                    </form>

                    <?php if (empty($auditLogs)): ?>
                        <div class="empty-state audit-empty"><i class="fas fa-filter-circle-xmark"></i><p>No activity matches these filters.</p><?php if ($auditHasFilters): ?><a href="department_archive.php?tab=audit" class="btn btn-restore">Clear filters</a><?php endif; ?></div>
                    <?php else: ?>
                        <div class="audit-list">
                        <?php foreach ($auditLogs as $log): ?>
                            <?php $auditMeta = auditActionPresentation($log['action']); ?>
                            <article class="audit-entry audit-entry--<?php echo htmlspecialchars($auditMeta['tone']); ?>">
                                <div class="audit-entry__icon"><i class="fas <?php echo htmlspecialchars($auditMeta['icon']); ?>" aria-hidden="true"></i></div>
                                <div class="audit-entry__body">
                                    <div class="audit-entry__topline">
                                        <span class="audit-event-badge"><?php echo htmlspecialchars($auditMeta['label']); ?></span>
                                        <time datetime="<?php echo date('c', strtotime($log['created_at'])); ?>"><?php echo date('M d, Y · h:i A', strtotime($log['created_at'])); ?></time>
                                    </div>
                                    <p class="audit-entry__description"><?php echo htmlspecialchars(auditDescriptionPresentation($log['description'])); ?></p>
                                    <div class="audit-entry__meta">
                                        <span class="audit-meta-chip"><i class="far fa-user" aria-hidden="true"></i> <?php echo htmlspecialchars($archiveActorNames[$log['username']] ?? $log['username']); ?></span>
                                        <span class="audit-meta-chip"><i class="far fa-id-badge" aria-hidden="true"></i> <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $log['role']))); ?></span>
                                        <span class="audit-meta-chip audit-meta-chip--location"><i class="fas fa-location-dot" aria-hidden="true"></i> <?php echo htmlspecialchars($log['barangay'] ? 'Barangay ' . $log['barangay'] : 'City Department'); ?></span>
                                    </div>
                                    <details class="audit-technical"><summary>Technical details</summary><div>Network: <?php echo htmlspecialchars(auditIpLabel($log['ip_address'])); ?></div></details>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        </div>
                        <?php if ($auditTotalPages > 1): ?>
                            <nav class="archive-audit-pagination" aria-label="Citywide audit log pages">
                                <div class="archive-audit-pagination__summary">Page <?php echo $auditPage; ?> of <?php echo $auditTotalPages; ?></div>
                                <div class="archive-audit-pagination__controls">
                                    <?php if ($auditPage > 1): ?>
                                        <a class="archive-audit-page archive-audit-page--direction" href="<?php echo $auditPageUrl($auditPage - 1); ?>" rel="prev"><i class="fas fa-chevron-left" aria-hidden="true"></i> Previous</a>
                                    <?php else: ?>
                                        <span class="archive-audit-page archive-audit-page--direction is-disabled" aria-disabled="true"><i class="fas fa-chevron-left" aria-hidden="true"></i> Previous</span>
                                    <?php endif; ?>
                                    <?php for ($pageNumber = $auditStartPage; $pageNumber <= $auditEndPage; $pageNumber++): ?>
                                        <a class="archive-audit-page<?php echo $pageNumber === $auditPage ? ' is-current' : ''; ?>" href="<?php echo $auditPageUrl($pageNumber); ?>" <?php echo $pageNumber === $auditPage ? 'aria-current="page"' : ''; ?>><?php echo $pageNumber; ?></a>
                                    <?php endfor; ?>
                                    <?php if ($auditPage < $auditTotalPages): ?>
                                        <a class="archive-audit-page archive-audit-page--direction" href="<?php echo $auditPageUrl($auditPage + 1); ?>" rel="next">Next <i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                                    <?php else: ?>
                                        <span class="archive-audit-page archive-audit-page--direction is-disabled" aria-disabled="true">Next <i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                                    <?php endif; ?>
                                </div>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </div>

    <div id="archiveApplicationModal" class="archive-detail-overlay" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="archiveApplicationModalTitle">
        <div class="archive-detail-dialog">
            <div class="archive-detail-head">
                <div class="archive-detail-heading"><span>Archived applicant record</span><h2 id="archiveApplicationModalTitle"><i class="fas fa-box-archive"></i> Archived Application Details</h2><p>Review the preserved application information before restoring the record.</p></div>
                <button type="button" class="archive-detail-close" id="closeArchiveApplicationModal" aria-label="Close application details"><i class="fas fa-xmark" aria-hidden="true"></i></button>
            </div>
            <div class="archive-detail-body" id="archiveApplicationModalBody"></div>
        </div>
    </div>

    <script src="../assets/js/sidebar-toggle.js?v=3"></script>
    <script src="../assets/js/application-details.js?v=9"></script>
    <script src="../assets/js/archive-application-modal.js?v=2"></script>
    <script src="../assets/js/seniorlink-feedback.js?v=1"></script>
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
            const auditTab = document.getElementById('tab-audit');
            const btns = document.querySelectorAll('.tab-btn');

            appTab.style.display = tabName === 'applications' ? 'block' : 'none';
            userTab.style.display = tabName === 'users' ? 'block' : 'none';
            auditTab.style.display = tabName === 'audit' ? 'block' : 'none';
            btns.forEach((button, index) => button.classList.toggle('active',
                (index === 0 && tabName === 'applications') ||
                (index === 1 && tabName === 'users') ||
                (index === 2 && tabName === 'audit')
            ));
            btns.forEach((button, index) => button.setAttribute('aria-selected', String(
                (index === 0 && tabName === 'applications') ||
                (index === 1 && tabName === 'users') ||
                (index === 2 && tabName === 'audit')
            )));
            appTab.setAttribute('aria-hidden', String(tabName !== 'applications'));
            userTab.setAttribute('aria-hidden', String(tabName !== 'users'));
            auditTab.setAttribute('aria-hidden', String(tabName !== 'audit'));
        }

        document.addEventListener('DOMContentLoaded', function() {
            const auditForm = document.getElementById('auditFilterForm');
            if (auditForm) {
                let auditSearchTimer;
                document.getElementById('auditSearch')?.addEventListener('input', function() {
                    window.clearTimeout(auditSearchTimer);
                    auditSearchTimer = window.setTimeout(() => auditForm.requestSubmit(), 350);
                });
                ['auditBarangay', 'auditEvent'].forEach(id => document.getElementById(id)?.addEventListener('change', () => auditForm.requestSubmit()));
            }

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
