<?php
session_start(); // Start the session to access $_SESSION variables
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

$search_query = isset($_GET['query']) ? $_GET['query'] : '';
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_barangay = isset($_GET['barangay']) ? $_GET['barangay'] : ''; // Allow explicit barangay filter

// Enforce barangay filter for barangay_staff
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'barangay_staff') {
    $filter_barangay = $_SESSION['barangay'];
}

$sql = "SELECT a.id_number as id, a.full_name, a.application_type, a.birth_date, a.contact_number, a.date_submitted, a.status, a.complete_address, a.workflow_state, a.priority_level,
               (SELECT h.comments FROM application_history h WHERE h.application_id = a.id_number AND h.new_state = 'Received' AND h.previous_state != 'None' ORDER BY h.changed_at DESC LIMIT 1) as return_comments
        FROM applications a";
$params = [];
$where_clauses = [];

if (!empty($search_query)) {
    $where_clauses[] = "(a.full_name LIKE ? OR a.application_type LIKE ? OR a.complete_address LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

if (!empty($filter_type)) {
    $where_clauses[] = "a.application_type = ?";
    $params[] = $filter_type;
}

if (!empty($filter_status)) {
    $statuses = explode(',', $filter_status);
    if (count($statuses) > 1) {
        $placeholders    = implode(',', array_fill(0, count($statuses), '?'));
        $where_clauses[] = "COALESCE(a.workflow_state, 'Received') IN ($placeholders)";
        foreach ($statuses as $s) {
            $params[] = trim($s);
        }
    } else {
        $where_clauses[] = "COALESCE(a.workflow_state, 'Received') = ?";
        $params[]        = trim($filter_status);
    }
}

if (!empty($filter_barangay)) {
    $where_clauses[] = "a.barangay = ?";
    $params[] = $filter_barangay;
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

// Order by Priority first, then by Date Submitted
$sql .= " ORDER BY CASE WHEN a.priority_level = 'high' THEN 0 ELSE 1 END, a.date_submitted DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($applications);
?>