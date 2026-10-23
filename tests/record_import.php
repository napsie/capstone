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
expectImportValue(normalizeImportIdPurpose('New ID Application'), 'new', 'new ID purpose normalized');
expectImportValue(normalizeImportIdPurpose('ID Renewal'), 'change', 'renewal purpose normalized');
expectImportValue(normalizeImportIdPurpose('Replacement of Lost ID'), 'lost', 'lost replacement purpose normalized');
expectImportValue(normalizeImportIdPurpose('Damaged ID Replacement'), 'lost', 'damaged replacement purpose normalized');
expectImportValue(normalizeImportIdPurpose('Update of Personal Information'), 'change', 'information update purpose normalized');

$temporaryWorkbook = tempnam(sys_get_temp_dir(), 'seniorlink-xlsx-');
$zip = new ZipArchive();
$zip->open($temporaryWorkbook, ZipArchive::OVERWRITE);
$worksheet = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>Name' . chr(11) . '</t></is></c></row><row r="2"><c r="A2" t="inlineStr"><is><t>Test Senior</t></is></c></row></sheetData></worksheet>';
$zip->addFromString('xl/worksheets/sheet2.xml', $worksheet);
$zip->addFromString('xl/styles.xml', '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="00000"/></numFmts><cellXfs count="3"><xf numFmtId="0"/><xf numFmtId="164"/><xf numFmtId="14"/></cellXfs></styleSheet>');
$worksheet = str_replace('</sheetData>', '<row r="3"><c r="A3" s="1"><v>17</v></c><c r="B3"><v>17</v></c><c r="C3" s="2"><v>45000</v></c><c r="D3" s="1" t="inlineStr"><is><t>00123</t></is></c><c r="E3" s="1" t="n"><v>123456</v></c></row></sheetData>', $worksheet);
$zip->addFromString('xl/worksheets/sheet2.xml', $worksheet);
$zip->close();
$recoveredRows = parseXlsxRecords($temporaryWorkbook);
@unlink($temporaryWorkbook);
expectImportValue($recoveredRows[0][0] ?? null, 'Name', 'worksheet control characters are repaired');
expectImportValue($recoveredRows[1][0] ?? null, 'Test Senior', 'alternate worksheet entry is read');
expectImportValue($recoveredRows[2][0] ?? null, '00017', 'Excel zero-padded Senior ID retains display zeros');
expectImportValue($recoveredRows[2][1] ?? null, '17', 'unformatted numbers are preserved');
expectImportValue($recoveredRows[2][2] ?? null, '45000', 'Excel dates remain serials for date normalization');
expectImportValue($recoveredRows[2][3] ?? null, '00123', 'text IDs retain their zeros');
expectImportValue($recoveredRows[2][4] ?? null, '123456', 'longer IDs are never truncated');

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
