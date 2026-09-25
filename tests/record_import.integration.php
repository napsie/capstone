<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/record_import.php';

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
    $conn->rollBack();
    echo "PASS: information-only database insert and import job\n";
} catch (Throwable $error) {
    if ($conn->inTransaction()) $conn->rollBack();
    fwrite(STDERR, "FAIL: {$error->getMessage()}\n");
    exit(1);
}
