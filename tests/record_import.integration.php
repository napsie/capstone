<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/record_import.php';
require_once __DIR__ . '/../includes/barangays_list.php';

$sql = "INSERT INTO applications (
    id_number, full_name, application_type, requested_benefit,
    lastName, firstName, middleName, suffix, birth_date, gender, civil_status, place_of_birth,
    contact_number, email_address, complete_address, house_no, street, barangay, city, province, zip_code, landmark,
    mothers_maiden_name, health_status, health_condition, emergency_contact_name, emergency_contact, claimant_relationship,
    id_purpose, date_submitted, status, workflow_state, senior_id_no, additional_notes
) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'approved','Verified',?,?)";

$values = [
    'IMPORT-TEST-' . bin2hex(random_bytes(4)), 'Test Senior', 'senior', null,
    'Senior', 'Test', null, null, '1950-01-01', 'Male', null, null,
    '', null, '1 Test Street, Bagong Ilog, Pasig City', '1', 'Test Street', 'Bagong Ilog', 'Pasig City', 'Metro Manila', null, null,
    null, null, null, null, '', null, null, date('Y-m-d'), null, 'Information-only import test',
];

try {
    $conn->beginTransaction();
    $statement = $conn->prepare($sql);
    $statement->execute($values);
    $jobToken = createImportJob($conn, 'sample.xlsx', hash('sha256', 'sample'), [[
        'row' => 2, 'full_name' => 'Test Senior', 'contact_number' => '09000000001', 'errors' => [],
    ]], ['id' => null, 'username' => 'Import Test']);
    $loadedRows = loadImportJobRows($conn, $jobToken);
    if (($loadedRows[0]['contact_number'] ?? null) !== '09000000001') {
        throw new RuntimeException('Import job did not preserve the numeric contact number.');
    }

    $sampleWorkbook = 'D:/Downloads/Group_2_Pasig_Senior_Citizen_Sample_Data_Final.xlsx';
    if (is_file($sampleWorkbook)) {
        $sampleRecords = importRowsToAssociative(parseXlsxRecords($sampleWorkbook));
        $imported = 0;
        foreach ($sampleRecords as $record) {
            $record = normalizeApplicationInput($record, $barangays_list);
            $fullName = buildImportFullName($record);
            $address = buildImportAddress($record);
            $birthDate = normalizeImportDate($record['birth_date'] ?? '');
            if ($fullName === '' || $address === '' || !$birthDate || !in_array($record['barangay'] ?? '', $barangays_list, true)) {
                throw new RuntimeException('The provided workbook produced an invalid required field on row ' . ($record['_row'] ?? '?'));
            }
            if (!isValidImportContactNumber($record['contact_number'] ?? '') || !isValidImportContactNumber($record['emergency_contact'] ?? '')) {
                throw new RuntimeException('The provided workbook produced an invalid contact number on row ' . ($record['_row'] ?? '?'));
            }
            $statement->execute([
                'IMPORT-XLSX-' . bin2hex(random_bytes(5)), $fullName, 'senior', 'Senior Citizen ID Registration',
                normalizePersonName($record['last_name'] ?? '') ?: null, normalizePersonName($record['first_name'] ?? '') ?: null,
                normalizePersonName($record['middle_name'] ?? '') ?: null, normalizeWhitespace($record['suffix'] ?? '') ?: null,
                $birthDate, normalizeWhitespace($record['gender'] ?? '') ?: null, normalizeWhitespace($record['civil_status'] ?? '') ?: null,
                normalizeWhitespace($record['place_of_birth'] ?? '') ?: null, normalizePhoneNumber($record['contact_number'] ?? ''),
                strtolower(trim($record['email_address'] ?? '')) ?: null, $address, normalizeWhitespace($record['house_no'] ?? '') ?: null,
                normalizeWhitespace($record['street'] ?? '') ?: null, $record['barangay'], 'Pasig City', 'Metro Manila',
                normalizeWhitespace($record['zip_code'] ?? '') ?: null, normalizeWhitespace($record['landmark'] ?? '') ?: null,
                normalizePersonName($record['mothers_maiden_name'] ?? '') ?: null, normalizeWhitespace($record['health_status'] ?? '') ?: null,
                normalizeWhitespace($record['health_condition'] ?? '') ?: null, normalizePersonName($record['emergency_contact_name'] ?? '') ?: null,
                normalizePhoneNumber($record['emergency_contact'] ?? ''), normalizeWhitespace($record['emergency_contact_relationship'] ?? '') ?: null,
                strtolower(normalizeWhitespace($record['id_purpose'] ?? '')) ?: null, date('Y-m-d'), null, 'Information-only workbook import test',
            ]);
            $imported++;
        }
        if ($imported !== 50) throw new RuntimeException("Expected 50 workbook records, inserted {$imported}.");
    }
    $conn->rollBack();
    echo "PASS: information-only database insert, import job, and 50-row workbook\n";
} catch (Throwable $error) {
    if ($conn->inTransaction()) $conn->rollBack();
    fwrite(STDERR, "FAIL: {$error->getMessage()}\n");
    exit(1);
}
