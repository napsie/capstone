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
    $auditWhere[] = "barangay = ?";
    $auditParams[] = $userBarangay;
}

if ($activeTab === 'audit' && $search !== '') {
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

$auditWhereSql = !empty($auditWhere) ? " WHERE " . implode(' AND ', $auditWhere) : "";

try {
    $auditCountStmt = $conn->prepare("SELECT COUNT(*) FROM audit_trail" . $auditWhereSql);
    $auditCountStmt->execute($auditParams);
    $auditTotal = (int)$auditCountStmt->fetchColumn();
    $auditTotalPages = max(1, (int)ceil($auditTotal / $auditPerPage));
    $auditPage = min($auditPage, $auditTotalPages);
    $auditOffset = ($auditPage - 1) * $auditPerPage;
    $auditSql = "SELECT * FROM audit_trail" . $auditWhereSql
        . " ORDER BY created_at DESC LIMIT {$auditPerPage} OFFSET {$auditOffset}";
    $stmtAudit = $conn->prepare($auditSql);
    $stmtAudit->execute($auditParams);
    $auditLogs = $stmtAudit->fetchAll(PDO::FETCH_ASSOC);
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
    return 'barangay_archive.php?' . htmlspecialchars(http_build_query($params), ENT_QUOTES, 'UTF-8');
};
$auditHasFilters = ($activeTab === 'audit' && $search !== '') || $auditEventFilter !== 'all';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SENIORLINK — Barangay Archive</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/barangay-sidebar.css?v=4">
    <link rel="stylesheet" href="../assets/css/seniorlink-public.css?v=1">
    <link rel="stylesheet" href="../assets/css/seniorlink-ui.css?v=16">
    <script src="../assets/js/modal-hci.js?v=2" defer></script>
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
    <link rel="stylesheet" href="../assets/css/archive-details.css?v=2">
    <link rel="stylesheet" href="../assets/css/system-header.css?v=1">
    <link rel="stylesheet" href="../assets/css/system-sidebar.css?v=3">
    <link rel="stylesheet" href="../assets/css/audit-log.css?v=2">
    <link rel="stylesheet" href="../assets/css/table-pagination.css?v=1">
    <script src="../assets/js/table-pagination.js?v=1" defer></script>
</head>
<body>
<div class="container">
    <?php include '../partials/barangay_sidebar.php'; ?>

    <div class="main-content">
        <!-- Page Header -->
        <div class="header">
            <div class="header-content">
                <div class="greeting" id="greetingMsg"></div>
                <h1>Barangay <span><?php echo $barangayName; ?></span> Archive</h1>
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
                    <p><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $_SESSION['role']))); ?> · Barangay <?php echo $barangayName; ?></p>
                </div>
                </div>
            </div>
        </div>

        <div class="tab-nav" role="tablist" aria-label="Archive sections">
            <button type="button" id="tabButtonApplications" role="tab" aria-controls="tab-applications" aria-selected="<?php echo $activeTab !== 'audit' ? 'true' : 'false'; ?>" class="tab-btn <?php echo $activeTab !== 'audit' ? 'active' : ''; ?>" onclick="switchTab('applications')">
                <i class="fas fa-box-archive"></i> Archived Applications (<?php echo count($archivedApplications); ?>)
            </button>
            <button type="button" id="tabButtonAudit" role="tab" aria-controls="tab-audit" aria-selected="<?php echo $activeTab === 'audit' ? 'true' : 'false'; ?>" class="tab-btn <?php echo $activeTab === 'audit' ? 'active' : ''; ?>" onclick="switchTab('audit')">
                <i class="fas fa-history"></i> Barangay Audit Log (<?php echo number_format($auditTotal); ?>)
            </button>
        </div>

        <!-- TAB 1: ARCHIVED APPLICATIONS -->
        <div id="tab-applications" role="tabpanel" aria-labelledby="tabButtonApplications" aria-hidden="<?php echo $activeTab !== 'audit' ? 'false' : 'true'; ?>" style="display: <?php echo $activeTab !== 'audit' ? 'block' : 'none'; ?>;">
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
                        <tbody data-paginate="10" data-pagination-label="Archived application pages">
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
                                    <tr class="archive-application-row" data-id="<?php echo htmlspecialchars($app['id_number']); ?>" data-name="<?php echo htmlspecialchars($app['full_name']); ?>" tabindex="0" role="button" aria-label="Open archived application for <?php echo htmlspecialchars($app['full_name']); ?>">
                                        <td><strong><?php echo htmlspecialchars($app['id_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                                        <td><span class="badge-type <?php echo $typeClass; ?>"><?php echo htmlspecialchars(applicationTypeLabel($app['application_type'])); ?></span></td>
                                        <td><?php echo date('M d, Y', strtotime($app['date_submitted'])); ?></td>
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

        <!-- TAB 2: BARANGAY AUDIT LOG -->
        <div id="tab-audit" role="tabpanel" aria-labelledby="tabButtonAudit" aria-hidden="<?php echo $activeTab === 'audit' ? 'false' : 'true'; ?>" style="display: <?php echo $activeTab === 'audit' ? 'block' : 'none'; ?>;">
            <section class="card audit-panel" aria-labelledby="barangayAuditHeading">
                <header class="audit-panel-header">
                    <span class="audit-panel-header__icon"><i class="fas fa-shield-halved" aria-hidden="true"></i></span>
                    <div class="audit-panel-header__copy">
                        <h2 id="barangayAuditHeading">Barangay Activity Stream</h2>
                        <p>Review account and record activity associated with Barangay <?php echo $barangayName; ?>.</p>
                    </div>
                    <span class="audit-count"><?php echo number_format($auditTotal); ?> event<?php echo $auditTotal === 1 ? '' : 's'; ?></span>
                </header>

                <form method="GET" class="audit-filters audit-filters--barangay" id="barangayAuditFilterForm">
                    <input type="hidden" name="tab" value="audit">
                    <div class="audit-filter-field">
                        <label for="barangayAuditSearch">Search activity</label>
                        <div class="audit-search"><i class="fas fa-search" aria-hidden="true"></i><input type="search" id="barangayAuditSearch" name="search" placeholder="Action, description, or staff name" value="<?php echo $activeTab === 'audit' ? htmlspecialchars($search) : ''; ?>"></div>
                    </div>
                    <div class="audit-filter-field">
                        <label for="barangayAuditEvent">Event type</label>
                        <select name="event" id="barangayAuditEvent">
                            <option value="all" <?php echo $auditEventFilter === 'all' ? 'selected' : ''; ?>>All events</option>
                            <option value="login" <?php echo $auditEventFilter === 'login' ? 'selected' : ''; ?>>Sign-ins</option>
                            <option value="logout" <?php echo $auditEventFilter === 'logout' ? 'selected' : ''; ?>>Sign-outs</option>
                            <option value="failed" <?php echo $auditEventFilter === 'failed' ? 'selected' : ''; ?>>Failed sign-ins</option>
                            <option value="archive" <?php echo $auditEventFilter === 'archive' ? 'selected' : ''; ?>>Archived items</option>
                            <option value="restore" <?php echo $auditEventFilter === 'restore' ? 'selected' : ''; ?>>Restored items</option>
                        </select>
                    </div>
                    <a href="barangay_archive.php?tab=audit" class="audit-filter-clear" <?php echo $auditHasFilters ? '' : 'hidden'; ?>><i class="fas fa-rotate-left" aria-hidden="true"></i> Clear</a>
                    <div class="audit-filter-help" aria-live="polite"><i class="fas fa-circle-info" aria-hidden="true"></i> Filters update automatically. Showing <?php echo number_format($auditFrom); ?>&ndash;<?php echo number_format($auditTo); ?> of <?php echo number_format($auditTotal); ?> matching events.</div>
                </form>

                <?php if (empty($auditLogs)): ?>
                    <div class="empty-state audit-empty"><i class="fas fa-filter-circle-xmark"></i><p><?php echo $auditHasFilters ? 'No activity matches these filters.' : 'No activity has been recorded yet.'; ?></p><?php if ($auditHasFilters): ?><a href="barangay_archive.php?tab=audit" class="btn btn-restore">Clear filters</a><?php endif; ?></div>
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
                                    <span class="audit-meta-chip audit-meta-chip--location"><i class="fas fa-location-dot" aria-hidden="true"></i> Barangay <?php echo htmlspecialchars($log['barangay'] ?: $userBarangay); ?></span>
                                </div>
                                <details class="audit-technical"><summary>Technical details</summary><div>Network: <?php echo htmlspecialchars(auditIpLabel($log['ip_address'])); ?></div></details>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    </div>
                    <?php if ($auditTotalPages > 1): ?>
                        <nav class="archive-audit-pagination" aria-label="Barangay audit log pages">
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
            btns.forEach((button, index) => button.setAttribute('aria-selected', String(
                (index === 0 && tabName !== 'audit') || (index === 1 && tabName === 'audit')
            )));
            appTab.setAttribute('aria-hidden', String(tabName === 'audit'));
            auditTab.setAttribute('aria-hidden', String(tabName !== 'audit'));
        }

        document.addEventListener('DOMContentLoaded', function() {
            const auditForm = document.getElementById('barangayAuditFilterForm');
            if (auditForm) {
                let auditSearchTimer;
                document.getElementById('barangayAuditSearch')?.addEventListener('input', function() {
                    window.clearTimeout(auditSearchTimer);
                    auditSearchTimer = window.setTimeout(() => auditForm.requestSubmit(), 350);
                });
                document.getElementById('barangayAuditEvent')?.addEventListener('change', () => auditForm.requestSubmit());
            }

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
