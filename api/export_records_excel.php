<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/application_types.php';
require_once '../includes/barangays_list.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['barangay_staff', 'department_admin', 'super_admin'], true)) {
    http_response_code(401);
    exit('Unauthorized.');
}

function xmlText($value): string {
    return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function excelColumnName(int $column): string {
    $name = '';
    while ($column > 0) {
        $column--;
        $name = chr(65 + ($column % 26)) . $name;
        $column = intdiv($column, 26);
    }
    return $name;
}

function excelInlineCell(string $reference, $value, int $style = 0): string {
    $text = xmlText($value);
    return '<c r="' . $reference . '" t="inlineStr" s="' . $style . '"><is><t xml:space="preserve">' . $text . '</t></is></c>';
}

function excelNumberCell(string $reference, $value, int $style = 0): string {
    return '<c r="' . $reference . '" s="' . $style . '"><v>' . (float)$value . '</v></c>';
}

function excelDateSerial(string $date): ?float {
    $timestamp = strtotime($date);
    return $timestamp === false ? null : ($timestamp / 86400) + 25569;
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
$format = strtolower(trim($_GET['format'] ?? ''));
$allowedFormats = ['excel', 'pdf'];
if (!in_array($format, $allowedFormats, true)) { http_response_code(400); exit('Select a valid report format: PDF or Excel.'); }
$allowedTypes = array_keys(getApplicationTypeOptions());
if ($type !== 'all' && !in_array($type, $allowedTypes, true)) { http_response_code(400); exit('Invalid application type.'); }
if ($status !== 'all' && $status !== 'Verified') { http_response_code(400); exit('Invalid report status.'); }
if (mb_strlen($search) > 100) { http_response_code(400); exit('Search text is too long.'); }

$parseReportDate = static function (string $value): ?DateTimeImmutable {
    if ($value === '') return null;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $date : null;
};
$fromDate = $parseReportDate($dateFrom);
$toDate = $parseReportDate($dateTo);
if (($dateFrom !== '' && !$fromDate) || ($dateTo !== '' && !$toDate)) { http_response_code(400); exit('Use valid report dates in YYYY-MM-DD format.'); }
if (!$fromDate || !$toDate) { http_response_code(400); exit('Select both the start and end dates for the report period.'); }
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
        $where[] = "{$statusExpr} NOT IN ('Verified', 'Approved', 'Released')";
    } elseif ($status === 'Verified') {
        $where[] = "{$statusExpr} IN ('Verified', 'Approved', 'Released')";
    } else {
        $where[] = "{$statusExpr} IN ('Verified', 'Approved', 'Released')";
    }
    if ($barangay !== '' && $barangay !== 'all') { $where[] = 'barangay = ?'; $params[] = $barangay; }
    $reportTitle = $reportMode === 'verification' ? 'Document Verification Queue - Pasig City' : 'Verified Application Records - Pasig City';
    $coverageLabel = ($barangay !== '' && $barangay !== 'all') ? 'Barangay Coverage: ' . $barangay : 'Barangay Coverage: All Barangays';
} else {
    $assignedBarangay = $_SESSION['barangay'] ?? '';
    $where[] = 'barangay = ?';
    $params[] = $assignedBarangay;
    if ($reportMode === 'queue') {
        $where[] = "{$statusExpr} NOT IN ('Verified', 'Approved', 'Released')";
    } elseif ($status === 'Verified') {
        $where[] = "{$statusExpr} IN ('Verified', 'Approved', 'Released')";
    } else {
        $where[] = "{$statusExpr} IN ('Verified', 'Approved', 'Released')";
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
if ($search !== '') $filterParts[] = 'Search: ' . $search;
if ($type !== '' && $type !== 'all') $filterParts[] = 'Type: ' . applicationTypeLabel($type);
if ($dateFrom !== '' && $dateTo !== '') $filterParts[] = 'Date: ' . $dateFrom . ' to ' . $dateTo;
elseif ($dateFrom !== '') $filterParts[] = 'From: ' . $dateFrom;
elseif ($dateTo !== '') $filterParts[] = 'Until: ' . $dateTo;
elseif ($year !== '' && $year !== 'all') $filterParts[] = 'Year: ' . $year;
if ($reportMode === 'records' && $status !== 'all') $filterParts[] = 'Status: ' . $status;
$filterLabel = $filterParts ? 'Applied Filters: ' . implode(' | ', $filterParts) : 'Applied Filters: All matching records';

$sql = "SELECT id_number, full_name, application_type, barangay, date_submitted, CASE WHEN COALESCE(NULLIF(workflow_state, ''), NULLIF(status, ''), 'Received') IN ('Approved','Released') THEN 'Verified' ELSE COALESCE(NULLIF(workflow_state, ''), NULLIF(status, ''), 'Received') END AS status FROM applications WHERE " . implode(' AND ', $where) . ' ORDER BY date_submitted DESC';
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$records) {
    http_response_code(422);
    exit('No records match the selected report filters. Adjust the filters and try again.');
}

if ($format === 'pdf') {
    $pdfEscape = static function ($value): string {
        $text = (string)$value;
        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
            if ($converted !== false) $text = $converted;
        }
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text) ?? '';
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    };
    $fit = static function ($value, int $limit): string {
        $value = trim((string)$value);
        return mb_strlen($value) > $limit ? mb_substr($value, 0, max(1, $limit - 3)) . '...' : $value;
    };
    $columns = [
        ['#', 28, 4], ['Applicant Name', 170, 28], ['Application ID', 105, 17],
        ['Application Type', 155, 25], ['Barangay', 120, 19], ['Date', 92, 12], ['Status', 100, 15],
    ];
    $pageWidth = 842;
    $pageHeight = 595;
    $left = 36;
    $rowsPerPage = 24;
    $recordChunks = array_chunk($records, $rowsPerPage);
    $streams = [];
    foreach ($recordChunks as $pageIndex => $chunk) {
        $commands = ['0.12 0.22 0.35 rg'];
        $commands[] = 'BT /F1 16 Tf ' . $left . ' 555 Td (' . $pdfEscape($reportTitle) . ') Tj ET';
        $commands[] = '0.25 0.32 0.42 rg BT /F1 9 Tf ' . $left . ' 537 Td (' . $pdfEscape($coverageLabel) . ') Tj ET';
        $commands[] = 'BT /F1 8 Tf ' . $left . ' 522 Td (' . $pdfEscape($fit($filterLabel, 150)) . ') Tj ET';
        $commands[] = 'BT /F1 8 Tf 650 555 Td (Generated: ' . $pdfEscape(date('Y-m-d H:i')) . ') Tj ET';
        $y = 496;
        $commands[] = '0.08 0.28 0.50 rg ' . $left . ' ' . ($y - 4) . ' 770 20 re f';
        $x = $left;
        foreach ($columns as [$label, $width]) {
            $commands[] = '1 1 1 rg BT /F1 8 Tf ' . ($x + 4) . ' ' . ($y + 2) . ' Td (' . $pdfEscape($label) . ') Tj ET';
            $x += $width;
        }
        $y -= 22;
        foreach ($chunk as $rowIndex => $record) {
            if ($rowIndex % 2 === 1) $commands[] = '0.95 0.97 0.99 rg ' . $left . ' ' . ($y - 5) . ' 770 18 re f';
            $values = [
                ($pageIndex * $rowsPerPage) + $rowIndex + 1,
                $fit($record['full_name'], 28), $fit($record['id_number'], 17),
                $fit(applicationTypeLabel($record['application_type']), 25), $fit($record['barangay'], 19),
                !empty($record['date_submitted']) ? date('Y-m-d', strtotime($record['date_submitted'])) : '',
                $fit($record['status'], 15),
            ];
            $x = $left;
            foreach ($columns as $columnIndex => $column) {
                $commands[] = '0.10 0.16 0.24 rg BT /F1 7 Tf ' . ($x + 4) . ' ' . $y . ' Td (' . $pdfEscape($values[$columnIndex]) . ') Tj ET';
                $x += $column[1];
            }
            $commands[] = '0.82 0.86 0.91 RG 0.4 w ' . $left . ' ' . ($y - 6) . ' m 806 ' . ($y - 6) . ' l S';
            $y -= 19;
        }
        $commands[] = '0.35 0.40 0.48 rg BT /F1 8 Tf 720 22 Td (Page ' . ($pageIndex + 1) . ' of ' . count($recordChunks) . ') Tj ET';
        $streams[] = implode("\n", $commands);
    }

    $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'];
    $kids = [];
    foreach ($streams as $index => $stream) {
        $pageObject = 4 + ($index * 2);
        $contentObject = $pageObject + 1;
        $kids[] = $pageObject . ' 0 R';
        $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $pageWidth . ' ' . $pageHeight . '] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $contentObject . ' 0 R >>';
        $objects[$contentObject] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
    }
    $objects[2] = '<< /Type /Pages /Count ' . count($streams) . ' /Kids [' . implode(' ', $kids) . '] >>';
    ksort($objects);
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $number => $body) {
        $offsets[$number] = strlen($pdf);
        $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $maxObject = max(array_keys($objects));
    $pdf .= "xref\n0 " . ($maxObject + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= $maxObject; $i++) $pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n";
    $pdf .= "trailer\n<< /Size " . ($maxObject + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
    $filename = ($scope === 'department' ? 'department' : 'barangay') . '_application_report_' . date('Ymd') . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: private, max-age=0, must-revalidate');
    echo $pdf;
    exit;
}

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('Excel export is unavailable because the PHP ZIP extension is not enabled.');
}

$headers = ['#', 'Applicant Name', 'Application ID', 'Application Type', 'Barangay', 'Date Submitted', 'Status'];
$headerRow = 6;
$firstDataRow = $headerRow + 1;
$lastDataRow = $headerRow + count($records);
$rowsXml = [];
$rowsXml[] = '<row r="1" ht="30" customHeight="1">' . excelInlineCell('A1', $reportTitle, 1) . '</row>';
$rowsXml[] = '<row r="2" ht="20" customHeight="1">' . excelInlineCell('A2', $coverageLabel, 2) . '</row>';
$rowsXml[] = '<row r="3" ht="20" customHeight="1">' . excelInlineCell('A3', 'Generated: ' . date('F j, Y g:i A'), 2) . '</row>';
$rowsXml[] = '<row r="4" ht="28" customHeight="1">' . excelInlineCell('A4', $filterLabel, 3) . '</row>';
$rowsXml[] = '<row r="5" ht="22" customHeight="1">' . excelInlineCell('A5', 'Total Records', 4) . excelNumberCell('B5', count($records), 5) . '</row>';

$headerCells = '';
foreach ($headers as $index => $label) {
    $headerCells .= excelInlineCell(excelColumnName($index + 1) . $headerRow, $label, 6);
}
$rowsXml[] = '<row r="' . $headerRow . '" ht="25" customHeight="1">' . $headerCells . '</row>';

foreach ($records as $index => $record) {
    $rowNumber = $firstDataRow + $index;
    $style = $index % 2 === 0 ? 7 : 8;
    $dateSerial = !empty($record['date_submitted']) ? excelDateSerial($record['date_submitted']) : null;
    $cells = excelNumberCell('A' . $rowNumber, $index + 1, $style);
    $cells .= excelInlineCell('B' . $rowNumber, $record['full_name'], $style);
    $cells .= excelInlineCell('C' . $rowNumber, $record['id_number'], 9 + ($index % 2));
    $cells .= excelInlineCell('D' . $rowNumber, applicationTypeLabel($record['application_type']), $style);
    $cells .= excelInlineCell('E' . $rowNumber, $record['barangay'], $style);
    $cells .= $dateSerial === null ? excelInlineCell('F' . $rowNumber, '', $style) : excelNumberCell('F' . $rowNumber, $dateSerial, 11 + ($index % 2));
    $cells .= excelInlineCell('G' . $rowNumber, $record['status'], $style);
    $rowsXml[] = '<row r="' . $rowNumber . '" ht="21" customHeight="1">' . $cells . '</row>';
}

$worksheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
    . '<sheetViews><sheetView showGridLines="0" workbookViewId="0"><selection activeCell="A1" sqref="A1"/></sheetView></sheetViews>'
    . '<sheetFormatPr defaultRowHeight="18"/>'
    . '<cols><col min="1" max="1" width="15" customWidth="1"/><col min="2" max="2" width="30" customWidth="1"/><col min="3" max="3" width="20" customWidth="1"/><col min="4" max="4" width="25" customWidth="1"/><col min="5" max="5" width="20" customWidth="1"/><col min="6" max="6" width="17" customWidth="1"/><col min="7" max="7" width="16" customWidth="1"/></cols>'
    . '<sheetData>' . implode('', $rowsXml) . '</sheetData>'
    . '<autoFilter ref="A' . $headerRow . ':G' . $lastDataRow . '"/>'
    . '<mergeCells count="4"><mergeCell ref="A1:G1"/><mergeCell ref="A2:G2"/><mergeCell ref="A3:G3"/><mergeCell ref="A4:G4"/></mergeCells>'
    . '<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/><pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0" paperSize="9"/>'
    . '</worksheet>';

$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<numFmts count="1"><numFmt numFmtId="164" formatCode="yyyy-mm-dd"/></numFmts>'
    . '<fonts count="4"><font><sz val="10"/><name val="Aptos"/><family val="2"/></font><font><b/><sz val="18"/><color rgb="FFFFFFFF"/><name val="Aptos Display"/></font><font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Aptos"/></font><font><b/><sz val="10"/><color rgb="FF123052"/><name val="Aptos"/></font></fonts>'
    . '<fills count="5"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0F2E59"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F5A8A"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF3F7FB"/><bgColor indexed="64"/></patternFill></fill></fills>'
    . '<borders count="3"><border><left/><right/><top/><bottom/><diagonal/></border><border><left/><right/><top/><bottom style="thin"><color rgb="FFD7E0EA"/></bottom><diagonal/></border><border><left/><right/><top/><bottom style="medium"><color rgb="FF0F2E59"/></bottom><diagonal/></border></borders>'
    . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
    . '<cellXfs count="13">'
    . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
    . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center"/></xf>'
    . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>'
    . '<xf numFmtId="0" fontId="0" fillId="4" borderId="0" xfId="0" applyFill="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
    . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="right"/></xf>'
    . '<xf numFmtId="0" fontId="2" fillId="3" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
    . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
    . '<xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
    . '<xf numFmtId="49" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
    . '<xf numFmtId="49" fontId="0" fillId="4" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
    . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
    . '<xf numFmtId="164" fontId="0" fillId="4" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
    . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

$files = [
    '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>',
    '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>',
    'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Application Records" sheetId="1" r:id="rId1"/></sheets><calcPr calcId="191029" fullCalcOnLoad="1"/></workbook>',
    'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
    'xl/worksheets/sheet1.xml' => $worksheet,
    'xl/styles.xml' => $styles,
    'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>' . xmlText($reportTitle) . '</dc:title><dc:creator>SENIORLINK</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created></cp:coreProperties>',
    'docProps/app.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>SENIORLINK</Application></Properties>',
];

$tempFile = tempnam(sys_get_temp_dir(), 'seniorlink_report_');
$zip = new ZipArchive();
if ($tempFile === false || $zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    exit('Unable to create the Excel report.');
}
foreach ($files as $path => $content) $zip->addFromString($path, $content);
$zip->close();

$filename = ($scope === 'department' ? 'department' : 'barangay') . '_application_report_' . date('Ymd') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tempFile));
header('Cache-Control: private, max-age=0, must-revalidate');
readfile($tempFile);
unlink($tempFile);
