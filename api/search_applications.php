<?php
session_start();
require_once '../includes/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['barangay_staff', 'department_admin', 'super_admin'], true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$searchQuery = trim((string)($_GET['query'] ?? ''));
$filterType = trim((string)($_GET['type'] ?? ''));
$filterStatus = trim((string)($_GET['status'] ?? ''));
$filterBarangay = trim((string)($_GET['barangay'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(100, max(10, (int)($_GET['per_page'] ?? 25)));

if (($_SESSION['role'] ?? '') === 'barangay_staff') {
    $filterBarangay = (string)($_SESSION['barangay'] ?? '');
}

$where = ['COALESCE(a.is_archived, 0) = 0'];
$params = [];
if ($searchQuery !== '') {
    $where[] = '(a.full_name LIKE :search OR a.id_number LIKE :search OR a.complete_address LIKE :search)';
    $params['search'] = '%' . $searchQuery . '%';
}
if ($filterType !== '') {
    $where[] = 'a.application_type = :type';
    $params['type'] = $filterType;
}
if ($filterStatus !== '') {
    $statuses = array_values(array_filter(array_map('trim', explode(',', $filterStatus))));
    if ($statuses) {
        $marks = [];
        foreach ($statuses as $index => $status) {
            $key = 'status_' . $index;
            $marks[] = ':' . $key;
            $params[$key] = $status;
        }
        $where[] = "COALESCE(NULLIF(a.workflow_state, ''), 'Received') IN (" . implode(',', $marks) . ')';
    }
}
if ($filterBarangay !== '') {
    $where[] = 'a.barangay = :barangay';
    $params['barangay'] = $filterBarangay;
}

$whereSql = ' WHERE ' . implode(' AND ', $where);
$countStmt = $conn->prepare('SELECT COUNT(*) FROM applications a' . $whereSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT a.id_number AS id, a.full_name, a.application_type, a.birth_date,
               a.contact_number, a.date_submitted, a.status, a.complete_address,
               a.house_no, a.street, a.city, a.province, a.zip_code, a.barangay,
               a.workflow_state, a.priority_level,
               (SELECT h.comments FROM application_history h
                WHERE h.application_id = a.id_number AND h.new_state = 'Received'
                  AND h.previous_state IN ('For Review', 'Verified', 'Approved')
                ORDER BY h.changed_at DESC LIMIT 1) AS return_comments
        FROM applications a" . $whereSql . "
        ORDER BY CASE WHEN a.priority_level = 'high' THEN 0 ELSE 1 END,
                 a.date_submitted DESC, a.id_number DESC
        LIMIT :limit OFFSET :offset";
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$applications = $stmt->fetchAll();

foreach ($applications as &$application) {
    if (trim((string)($application['complete_address'] ?? '')) === '') {
        $application['complete_address'] = implode(', ', array_filter([
            $application['house_no'] ?? '', $application['street'] ?? '',
            $application['barangay'] ?? '', $application['city'] ?? '',
            $application['province'] ?? '', $application['zip_code'] ?? '',
        ], static fn($part) => trim((string)$part) !== ''));
    }
}
unset($application);

echo json_encode([
    'success' => true,
    'data' => $applications,
    'pagination' => [
        'page' => $page, 'per_page' => $perPage, 'total' => $total,
        'total_pages' => $totalPages, 'has_previous' => $page > 1,
        'has_next' => $page < $totalPages,
    ],
]);
