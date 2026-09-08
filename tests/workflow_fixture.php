<?php
// Synthetic data only. This helper never connects to the application's database.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$name = $argv[2] ?? '';
if (!preg_match('/^seniorlink_test_[a-f0-9]{12}$/D', $name)) throw new RuntimeException('Invalid test database name.');
$conn = new PDO('mysql:host=localhost;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (($argv[1] ?? '') === 'cleanup') {
    $conn->exec("DROP DATABASE IF EXISTS `$name`");
    exit;
}
$schema = file_get_contents(dirname(__DIR__) . '/capstone1_schema.sql');
// Explicitly remove all database-selection statements before using the isolated DB.
$schema = preg_replace('/CREATE DATABASE IF NOT EXISTS `capstone1`[\s\S]*?;/i', '', $schema, 1, $created);
$schema = preg_replace('/USE `capstone1`;/i', '', $schema, 1, $selected);
if ($created !== 1 || $selected !== 1 || preg_match('/\b(?:CREATE\s+DATABASE|USE)\s+[`\w]/i', $schema)) throw new RuntimeException('Unsafe schema selection.');
$conn->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
$conn->exec("USE `$name`");
$conn->exec($schema);
// The existing burial side effect uses this status; match the deployed migration.
$conn->exec("ALTER TABLE applications MODIFY status VARCHAR(30) NOT NULL DEFAULT 'pending'");
$user = $conn->prepare('INSERT INTO users (username, password, role, first_name, last_name, email, barangay) VALUES (?, ?, ?, ?, ?, ?, ?)');
foreach ([['test-admin', 'department_admin', null], ['test-staff', 'barangay_staff', 'Bagong Ilog'], ['test-other', 'barangay_staff', 'Ugong'], ['test-unassigned', 'barangay_staff', '']] as [$username, $role, $barangay]) {
    $user->execute([$username, password_hash('TestOnly!2026', PASSWORD_DEFAULT), $role, 'Test', 'Reviewer', $username . '@example.invalid', $barangay]);
}
$insert = $conn->prepare("INSERT INTO applications (id_number, full_name, application_type, birth_date, contact_number, complete_address, emergency_contact, barangay, workflow_state, date_submitted, date_of_death, firstName, lastName, senior_id_no, home_visit_status, is_archived, return_reason)
    VALUES (?, ?, ?, '1940-01-01', '09170000000', 'Synthetic test address', '', ?, ?, ?, ?, 'Test', ?, ?, ?, ?, ?)");
$rows = [
    ['CORRECT', 'Correction Workflow', 'senior', 'Bagong Ilog', 'For Review', '2026-08-01', null, 'Correction', null, null, 0, null],
    ['VALID', 'Verified Senior', 'senior', 'Bagong Ilog', 'Verified', '2026-07-01', null, 'Valid', 'OSCA-TEST-VALID', null, 0, null],
    ['OTHER', 'Other Barangay Senior', 'senior', 'Ugong', 'Verified', '2026-07-01', null, 'Other', 'OSCA-TEST-OTHER', null, 0, null],
    ['ARCHIVED', 'Archived Senior', 'senior', 'Bagong Ilog', 'Verified', '2026-07-01', null, 'Archived', 'OSCA-TEST-ARCHIVED', null, 1, null],
    ['BURIAL30', 'Timely Burial', 'burial', 'Bagong Ilog', 'For Review', '2026-02-16', '2026-01-05', 'Timely', null, null, 0, null],
    ['BURIAL31', 'Late Burial', 'burial', 'Bagong Ilog', 'For Review', '2026-02-17', '2026-01-05', 'Late', null, null, 0, null],
    ['BURIALBAD', 'Invalid Burial Dates', 'burial', 'Bagong Ilog', 'For Review', '2026-02-16', '2026-03-01', 'Invalid', null, null, 0, null],
    ['BURIALMISSING', 'Missing Burial Date', 'burial', 'Bagong Ilog', 'For Review', '2026-02-16', null, 'Missing', null, null, 0, null],
    ['VISIT', 'Home Visit Follow-up', 'pension', 'Bagong Ilog', 'For Review', '2026-08-10', null, 'Visit', null, 'Waiting for Home Visit', 0, null],
    ['UI-CORRECTION', 'Sample Correction Request', 'pension', 'Bagong Ilog', 'Needs Correction', '2026-08-20', null, 'Sample', null, 'Waiting for Home Visit', 0, "Items to correct: PSA birth certificate\nReason: Upload a clear copy showing the complete name."],
    ['OTHERQUEUE', 'Other Barangay Review', 'senior', 'Ugong', 'For Review', '2026-08-12', null, 'Queue', null, null, 0, null],
];
foreach ($rows as $row) $insert->execute($row);
$insert->execute(['ARCHIVED-2', 'Second Archived Senior', 'senior', 'Bagong Ilog', 'Verified', '2026-08-01', null, 'Second', 'OSCA-TEST-SECOND', null, 1, null]);
foreach (['senior', 'pension', 'national_pension', 'landbank', 'milestone_gift', 'burial', 'home_visit'] as $type) {
    $insert->execute(['FORM-' . $type, 'Sample ' . $type . ' Applicant', $type, 'Bagong Ilog', 'Verified', '2026-08-25', $type === 'burial' ? '2026-08-20' : null, 'Form', null, 'Completed', 0, null]);
}
$document = $conn->prepare('INSERT INTO application_documents (application_id, document_key, document_label, mime_type, document_data) VALUES (?, ?, ?, ?, ?)');
foreach (['ARCHIVED', 'ARCHIVED-2', 'FORM-senior', 'FORM-pension', 'FORM-national_pension', 'FORM-landbank', 'FORM-milestone_gift', 'FORM-burial', 'FORM-home_visit'] as $id) {
    $document->execute([$id, 'test_requirement', 'Supporting document for ' . $id, 'application/pdf', "%PDF-1.4\n% Test document\n%%EOF"]);
    $history = $conn->prepare('INSERT INTO application_history (application_id, previous_state, new_state, changed_by, comments) VALUES (?, ?, ?, ?, ?)');
    $history->execute([$id, 'For Review', 'Verified', 'test-admin', 'Synthetic history for ' . $id]);
}
$conn->exec("UPDATE applications SET psa_birth_cert = 'synthetic-legacy.jpg' WHERE id_number = 'CORRECT'");
echo "Synthetic test database ready.\n";
