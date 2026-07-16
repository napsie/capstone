<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/application_types.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['barangay_staff', 'department_admin', 'super_admin'], true)) {
    http_response_code(401);
    exit('Unauthorized.');
}

function excelText($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$role = $_SESSION['role'];
$scope = $_GET['scope'] ?? ($role === 'barangay_staff' ? 'barangay' : 'department');
if ($scope === 'department' && $role === 'barangay_staff') {
    http_response_code(403);
    exit('Access denied.');
}

$search = trim($_GET['search'] ?? '');
$type = trim($_GET['type'] ?? 'all');
$year = trim($_GET['year'] ?? 'all');
$barangay = trim($_GET['barangay'] ?? 'all');
$where = [];
$params = [];

if ($scope === 'department') {
    $where[] = "(workflow_state IN ('Approved', 'Released') OR status = 'Approved')";
    if ($barangay !== '' && $barangay !== 'all') {
        $where[] = 'barangay = ?';
        $params[] = $barangay;
    }
    $reportTitle = 'Approved Application Records — Pasig City';
    $coverageLabel = ($barangay !== '' && $barangay !== 'all') ? 'Barangay Coverage: ' . $barangay : 'Barangay Coverage: All Barangays';
} else {
    $where[] = 'barangay = ?';
    $assignedBarangay = $_SESSION['barangay'] ?? '';
    $params[] = $assignedBarangay;
    $reportTitle = 'Application Records — ' . ($assignedBarangay ?: 'Assigned Barangay');
    $coverageLabel = 'Barangay Coverage: ' . ($assignedBarangay ?: 'Assigned Barangay');
}

if ($search !== '') {
    $where[] = '(full_name LIKE ? OR id_number LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if ($type !== '' && $type !== 'all') {
    $where[] = 'application_type = ?';
    $params[] = $type;
}
if ($year !== '' && $year !== 'all' && ctype_digit($year)) {
    $where[] = 'YEAR(date_submitted) = ?';
    $params[] = (int)$year;
}

$sql = 'SELECT id_number, full_name, application_type, barangay, date_submitted, COALESCE(workflow_state, status, \'Received\') AS status FROM applications WHERE ' . implode(' AND ', $where) . ' ORDER BY date_submitted DESC';
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = ($scope === 'department' ? 'department' : 'barangay') . '_application_report_' . date('Ymd') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>';
echo 'body{font-family:Arial,sans-serif;color:#172033}table{border-collapse:collapse;width:100%}.title{font-size:18pt;font-weight:bold;color:#17365d}.meta{font-size:10pt;color:#52606d}.summary{font-size:11pt;font-weight:bold;background:#eaf2f8;border:1px solid #b4c7e7;padding:8px}.header th{background:#1f4e78;color:#fff;font-weight:bold;text-align:left;border:1px solid #17365d;padding:9px}.data td{border:1px solid #d9e2f3;padding:8px}.data:nth-child(even) td{background:#f7fbff}.center{text-align:center}.date{mso-number-format:"yyyy-mm-dd"}</style></head><body>';
echo '<table><tr><td colspan="7" class="title">' . excelText($reportTitle) . '</td></tr>';
echo '<tr><td colspan="7" class="meta">Generated: ' . date('F j, Y g:i A') . '</td></tr>';
echo '<tr><td colspan="7" class="meta">' . excelText($coverageLabel) . '</td></tr>';
echo '<tr><td colspan="7" class="summary">Total records: ' . number_format(count($records)) . '</td></tr>';
echo '<tr><td colspan="7">&nbsp;</td></tr>';
echo '<tr class="header"><th>#</th><th>Applicant Name</th><th>Application ID</th><th>Application Type</th><th>Barangay</th><th>Date Submitted</th><th>Status</th></tr>';
if (!$records) {
    echo '<tr class="data"><td colspan="7" class="center">No records match the selected filters.</td></tr>';
}
foreach ($records as $index => $record) {
    $date = $record['date_submitted'] ? date('Y-m-d', strtotime($record['date_submitted'])) : '';
    echo '<tr class="data"><td>' . ($index + 1) . '</td><td>' . excelText($record['full_name']) . '</td><td>' . excelText($record['id_number']) . '</td><td>' . excelText(applicationTypeLabel($record['application_type'])) . '</td><td>' . excelText($record['barangay']) . '</td><td class="date">' . excelText($date) . '</td><td>' . excelText($record['status']) . '</td></tr>';
}
echo '</table></body></html>';
