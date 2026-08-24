<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/application_types.php';
require_once '../includes/barangays_list.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['barangay_staff', 'department_admin', 'super_admin'], true)) {
    http_response_code(401);
    exit('Unauthorized.');
}

function pdfSafeText($value): string {
    $value = (string)$value;
    if (function_exists('iconv')) $value = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value) ?: '';
    return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $value);
}
function pdfShorten($value, int $length): string {
    $value = trim((string)$value);
    return function_exists('mb_strimwidth') ? mb_strimwidth($value, 0, $length, '...') : (strlen($value) > $length ? substr($value, 0, $length - 3) . '...' : $value);
}
function pdfText(float $x, float $y, float $size, string $text, bool $bold = false, array $color = [0.09, 0.13, 0.21]): string {
    return sprintf("BT /%s %.1F Tf %.3F %.3F %.3F rg 1 0 0 1 %.1F %.1F Tm (%s) Tj ET\n", $bold ? 'F2' : 'F1', $size, $color[0], $color[1], $color[2], $x, $y, pdfSafeText($text));
}
function pdfRect(float $x, float $y, float $w, float $h, array $color): string {
    return sprintf("%.3F %.3F %.3F rg %.1F %.1F %.1F %.1F re f\n", $color[0], $color[1], $color[2], $x, $y, $w, $h);
}
function pdfLine(float $x1, float $y1, float $x2, float $y2, array $color = [0.82, 0.86, 0.91]): string {
    return sprintf("%.3F %.3F %.3F RG 0.5 w %.1F %.1F m %.1F %.1F l S\n", $color[0], $color[1], $color[2], $x1, $y1, $x2, $y2);
}

$role = $_SESSION['role'];
$scope = trim($_GET['scope'] ?? ($role === 'barangay_staff' ? 'barangay' : 'department'));
if (!in_array($scope, ['barangay', 'department'], true)) { http_response_code(400); exit('Invalid report scope.'); }
if ($scope === 'department' && $role === 'barangay_staff') { http_response_code(403); exit('Access denied.'); }
if ($scope === 'barangay' && $role !== 'barangay_staff') { http_response_code(403); exit('Access denied.'); }
$reportMode = $_GET['report_mode'] ?? 'records';
if (!in_array($reportMode, ['records', 'queue', 'verification'], true)) { http_response_code(400); exit('Invalid report mode.'); }
if (($scope === 'barangay' && !in_array($reportMode, ['records', 'queue'], true)) ||
    ($scope === 'department' && !in_array($reportMode, ['records', 'verification'], true))) {
    http_response_code(400);
    exit('The selected report mode is not valid for this scope.');
}
$search = trim($_GET['search'] ?? '');
$type = trim($_GET['type'] ?? 'all');
$year = trim($_GET['year'] ?? 'all');
$barangay = trim($_GET['barangay'] ?? 'all');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$status = trim($_GET['status'] ?? 'all');
$allowedTypes = array_keys(getApplicationTypeOptions());
if ($type !== 'all' && !in_array($type, $allowedTypes, true)) { http_response_code(400); exit('Invalid application type.'); }
if ($status !== 'all' && !in_array($status, ['Approved', 'Released'], true)) { http_response_code(400); exit('Invalid report status.'); }
if (mb_strlen($search) > 100) { http_response_code(400); exit('Search text is too long.'); }

$parseReportDate = static function (string $value): ?DateTimeImmutable {
    if ($value === '') return null;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $date : null;
};
$fromDate = $parseReportDate($dateFrom);
$toDate = $parseReportDate($dateTo);
if (($dateFrom !== '' && !$fromDate) || ($dateTo !== '' && !$toDate)) { http_response_code(400); exit('Use valid report dates in YYYY-MM-DD format.'); }
if (($fromDate && !$toDate) || (!$fromDate && $toDate)) { http_response_code(400); exit('Select both the start and end dates.'); }
if ($fromDate && $toDate && $fromDate > $toDate) { http_response_code(400); exit('The report end date cannot be earlier than the start date.'); }
$today = new DateTimeImmutable('today');
if (($fromDate && $fromDate > $today) || ($toDate && $toDate > $today)) { http_response_code(400); exit('Report dates cannot be in the future.'); }
if ($year !== 'all' && (!ctype_digit($year) || (int)$year < 2020 || (int)$year > (int)date('Y'))) { http_response_code(400); exit('Invalid report year.'); }
if ($scope === 'department' && $barangay !== 'all' && !in_array($barangay, $barangays_list, true)) { http_response_code(400); exit('Invalid barangay selection.'); }

$where = [];
$params = [];
$statusExpr = "COALESCE(NULLIF(workflow_state, ''), NULLIF(status, ''), 'Received')";

if ($scope === 'department') {
    if ($reportMode === 'verification') {
        $where[] = "{$statusExpr} NOT IN ('Approved', 'Released')";
    } elseif ($status === 'Approved' || $status === 'Released') {
        $where[] = "{$statusExpr} = ?";
        $params[] = $status;
    } else {
        $where[] = "{$statusExpr} IN ('Approved', 'Released')";
    }
    if ($barangay !== '' && $barangay !== 'all') { $where[] = 'barangay = ?'; $params[] = $barangay; }
    $reportTitle = $reportMode === 'verification' ? 'Document Verification Queue - Pasig City' : 'Approved Application Records - Pasig City';
    $coverageLabel = ($barangay !== '' && $barangay !== 'all') ? 'Barangay Coverage: ' . $barangay : 'Barangay Coverage: All Barangays';
} else {
    $assignedBarangay = $_SESSION['barangay'] ?? '';
    $where[] = 'barangay = ?';
    $params[] = $assignedBarangay;
    if ($reportMode === 'queue') {
        $where[] = "{$statusExpr} NOT IN ('Approved', 'Released')";
    } elseif ($status === 'Approved' || $status === 'Released') {
        $where[] = "{$statusExpr} = ?";
        $params[] = $status;
    } else {
        $where[] = "{$statusExpr} IN ('Approved', 'Released')";
    }
    $reportTitle = ($reportMode === 'queue' ? 'Applications Queue - ' : 'Application Records - ') . ($assignedBarangay ?: 'Assigned Barangay');
    $coverageLabel = 'Barangay Coverage: ' . ($assignedBarangay ?: 'Assigned Barangay');
}
if ($search !== '') { $where[] = '(full_name LIKE ? OR id_number LIKE ?)'; $params[] = "%{$search}%"; $params[] = "%{$search}%"; }
if ($type !== '' && $type !== 'all') { $where[] = 'application_type = ?'; $params[] = $type; }
if ($dateFrom !== '') { $where[] = 'DATE(date_submitted) >= ?'; $params[] = $dateFrom; }
if ($dateTo !== '') { $where[] = 'DATE(date_submitted) <= ?'; $params[] = $dateTo; }
if ($dateFrom === '' && $dateTo === '' && $year !== '' && $year !== 'all' && ctype_digit($year)) {
    $where[] = 'YEAR(date_submitted) = ?';
    $params[] = (int)$year;
}

$filterParts = [];
if ($search !== '') $filterParts[] = 'Search: ' . pdfShorten($search, 40);
if ($type !== '' && $type !== 'all') $filterParts[] = 'Type: ' . pdfShorten(applicationTypeLabel($type), 30);
if ($dateFrom !== '' && $dateTo !== '') $filterParts[] = 'Date: ' . $dateFrom . ' to ' . $dateTo;
elseif ($dateFrom !== '') $filterParts[] = 'From: ' . $dateFrom;
elseif ($dateTo !== '') $filterParts[] = 'Until: ' . $dateTo;
elseif ($year !== '' && $year !== 'all') $filterParts[] = 'Year: ' . $year;
if ($reportMode === 'records' && $status !== 'all') $filterParts[] = 'Status: ' . $status;
$filterLabel = $filterParts ? 'Applied Filters: ' . implode(' | ', $filterParts) : 'Applied Filters: All matching records';

$sql = "SELECT id_number, full_name, application_type, barangay, date_submitted, COALESCE(NULLIF(workflow_state, ''), NULLIF(status, ''), 'Received') AS status FROM applications WHERE " . implode(' AND ', $where) . ' ORDER BY date_submitted DESC';
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$records) {
    http_response_code(422);
    exit('No records match the selected report filters. Adjust the filters and try again.');
}

$rowsPerPage = 23;
$pages = array_chunk($records, $rowsPerPage);
$columns = [['#', 30], ['Applicant Name', 165], ['Application ID', 112], ['Application Type', 150], ['Barangay', 115], ['Date Submitted', 88], ['Status', 90]];
$streams = [];
foreach ($pages as $pageIndex => $pageRecords) {
    $stream = pdfRect(0, 545, 842, 50, [0.06, 0.18, 0.35]);
    $stream .= pdfText(40, 564, 18, $reportTitle, true, [1, 1, 1]);
    $stream .= pdfText(40, 531, 9, $coverageLabel . '  |  Generated: ' . date('F j, Y g:i A'), false, [0.24, 0.34, 0.48]);
    $stream .= pdfText(40, 518, 8, pdfShorten($filterLabel, 115), false, [0.31, 0.38, 0.46]);
    $stream .= pdfText(40, 504, 9, 'Total records: ' . number_format(count($records)), true, [0.06, 0.18, 0.35]);
    $x = 40;
    foreach ($columns as [$label, $width]) { $stream .= pdfRect($x, 486, $width, 20, [0.12, 0.31, 0.53]) . pdfText($x + 5, 492.5, 8, $label, true, [1, 1, 1]); $x += $width; }
    $y = 470;
    if (!$pageRecords) $stream .= pdfText(40, $y, 10, 'No records match the selected report coverage and filters.', false, [0.31, 0.38, 0.46]);
    foreach ($pageRecords as $index => $record) {
        $absoluteIndex = ($pageIndex * $rowsPerPage) + $index + 1;
        if ($index % 2 === 1) $stream .= pdfRect(40, $y - 5, 750, 16, [0.96, 0.98, 1]);
        $values = [$absoluteIndex, pdfShorten($record['full_name'], 31), pdfShorten($record['id_number'], 21), pdfShorten(applicationTypeLabel($record['application_type']), 28), pdfShorten($record['barangay'], 20), $record['date_submitted'] ? date('Y-m-d', strtotime($record['date_submitted'])) : '', pdfShorten($record['status'], 15)];
        $x = 40;
        foreach ($columns as $columnIndex => [, $width]) { $stream .= pdfText($x + 5, $y, 8.2, (string)$values[$columnIndex]); $x += $width; }
        $stream .= pdfLine(40, $y - 6, 790, $y - 6);
        $y -= 16;
    }
    $stream .= pdfLine(40, 47, 790, 47) . pdfText(40, 31, 8, 'SENIORLINK - Centralized Profiling and Record Authentication System', false, [0.31, 0.38, 0.46]) . pdfText(710, 31, 8, 'Page ' . ($pageIndex + 1) . ' of ' . count($pages), false, [0.31, 0.38, 0.46]);
    $streams[] = $stream;
}

// Dependency-free PDF writer: keeps reports available in the current XAMPP setup.
$objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 2 => '', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>', 4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>'];
$pageObjectIds = [];
$nextObjectId = 5;
foreach ($streams as $stream) {
    $contentId = $nextObjectId++;
    $pageId = $nextObjectId++;
    $objects[$contentId] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
    $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$contentId} 0 R >>";
    $pageObjectIds[] = $pageId;
}
$objects[2] = '<< /Type /Pages /Kids [' . implode(' ', array_map(fn($id) => "{$id} 0 R", $pageObjectIds)) . '] /Count ' . count($pageObjectIds) . ' >>';
ksort($objects);
$pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
$offsets = [0];
foreach ($objects as $id => $object) { $offsets[$id] = strlen($pdf); $pdf .= "{$id} 0 obj\n{$object}\nendobj\n"; }
$xrefOffset = strlen($pdf);
$pdf .= 'xref' . "\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
foreach (array_keys($objects) as $id) $pdf .= sprintf('%010d 00000 n ', $offsets[$id]) . "\n";
$pdf .= 'trailer << /Size ' . (count($objects) + 1) . ' /Root 1 0 R >>' . "\nstartxref\n{$xrefOffset}\n%%EOF";

$filename = ($scope === 'department' ? 'department' : 'barangay') . '_application_report_' . date('Ymd') . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, max-age=0, must-revalidate');
echo $pdf;
