<?php
require_once __DIR__ . '/../includes/record_import.php';

function expectImportValue($actual, $expected, string $message): void {
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

expectImportValue(normalizeImportHeader('firstName'), 'first_name', 'camel-case form header');
expectImportValue(normalizeImportHeader('Senior Citizen ID'), 'senior_id_no', 'Senior ID header alias');
expectImportValue(normalizeImportHeader('Contact Number (fictional placeholder)'), 'contact_number', 'sample workbook contact header');
expectImportValue(normalizeImportHeader('Emergency Contact Number (fictional placeholder)'), 'emergency_contact', 'sample workbook emergency contact header');
expectImportValue(normalizeImportHeader('Approved Benefit'), 'requested_benefit', 'approved benefit header');
expectImportValue(normalizePhoneNumber('0900-0000-0001'), '090000000001', 'formatted contact converted to digits');
expectImportValue(normalizePhoneNumber('9000000001'), '09000000001', 'numeric Excel contact restores leading zero');
expectImportValue(isValidImportContactNumber('0900-0000-0001'), true, 'fictional formatted import contact accepted');
expectImportValue(isValidImportContactNumber('not-a-number'), false, 'non-numeric contact rejected');
expectImportValue(
    buildImportFullName(['first_name' => 'JUAN', 'middle_name' => 'SANTOS', 'last_name' => 'DELA CRUZ']),
    'Juan Santos Dela Cruz',
    'name assembled from form fields'
);
expectImportValue(
    buildImportAddress(['house_no' => '123', 'street' => 'Example Street', 'barangay' => 'Bagong Ilog', 'city' => 'Pasig City']),
    '123 Example Street, Bagong Ilog, Pasig City, Metro Manila',
    'address assembled from form fields'
);
expectImportValue(buildImportAddress([]), '', 'missing address is not filled with defaults');
expectImportValue(normalizeImportDate('02/29/2024'), '2024-02-29', 'valid leap-day date');
expectImportValue(normalizeImportDate('02/30/2024'), null, 'impossible date rejected');

$temporaryWorkbook = tempnam(sys_get_temp_dir(), 'seniorlink-xlsx-');
$zip = new ZipArchive();
$zip->open($temporaryWorkbook, ZipArchive::OVERWRITE);
$worksheet = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>Name' . chr(11) . '</t></is></c></row><row r="2"><c r="A2" t="inlineStr"><is><t>Test Senior</t></is></c></row></sheetData></worksheet>';
$zip->addFromString('xl/worksheets/sheet2.xml', $worksheet);
$zip->close();
$recoveredRows = parseXlsxRecords($temporaryWorkbook);
@unlink($temporaryWorkbook);
expectImportValue($recoveredRows[0][0] ?? null, 'Name', 'worksheet control characters are repaired');
expectImportValue($recoveredRows[1][0] ?? null, 'Test Senior', 'alternate worksheet entry is read');

$sampleWorkbook = 'D:/Downloads/Group_2_Pasig_Senior_Citizen_Sample_Data_Final.xlsx';
if (is_file($sampleWorkbook)) {
    $sampleRecords = importRowsToAssociative(parseXlsxRecords($sampleWorkbook));
    expectImportValue(count($sampleRecords), 50, 'provided sample workbook record count');
    expectImportValue(normalizePhoneNumber($sampleRecords[0]['contact_number'] ?? ''), '090000000001', 'provided workbook contact mapping');
}

$numericIdWorkbook = 'D:/Downloads/Group_3_Pasig_Senior_Citizen_Data_With_Numeric_ID_and_Benefits.xlsx';
if (is_file($numericIdWorkbook)) {
    $numericIdRecords = importRowsToAssociative(parseXlsxRecords($numericIdWorkbook));
    expectImportValue(count($numericIdRecords), 50, 'numeric-ID workbook record count');
    expectImportValue($numericIdRecords[0]['senior_id_no'] ?? null, '202600000001', 'numeric Senior ID is preserved');
    expectImportValue($numericIdRecords[0]['requested_benefit'] ?? null, 'Senior Citizen ID', 'approved benefit is mapped');
}

echo "PASS: record import helpers\n";
