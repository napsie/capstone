<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/duplicate_records.php';
// A connection-local temporary table shadows applications without touching real records.
$conn->exec('CREATE TEMPORARY TABLE applications (id_number VARCHAR(255), parent_senior_id VARCHAR(255), application_type VARCHAR(40), full_name VARCHAR(255), birth_date DATE, contact_number VARCHAR(40), barangay VARCHAR(100), senior_id_no VARCHAR(100), is_archived INT)');
$insert = $conn->prepare('INSERT INTO applications VALUES (?,?,?,?,?,?,?,?,?)');
$base = ['SELF', null, 'senior', 'Juan Dela Cruz', '1950-01-01', '09123456789', 'Bagong Ilog', 'SC-001', 0];
$insert->execute($base);
foreach ([['MATCH', null, 'senior', ' JUAN   DELA CRUZ ', '1950-01-01', '', 'Bagong Ilog', '', 0],
          ['OTHER', null, 'senior', 'Juan Dela Cruz', '1950-01-01', '', 'Other Barangay', '', 0],
          ['ARCHIVED', null, 'senior', 'Juan Dela Cruz', '1950-01-01', '', 'Bagong Ilog', '', 1],
          ['LINKED', 'SELF', 'senior', 'Juan Dela Cruz', '1950-01-01', '', 'Bagong Ilog', '', 0],
          ['BENEFIT', 'SELF', 'pension', 'Juan Dela Cruz', '1950-01-01', '', 'Bagong Ilog', '', 0]] as $row) $insert->execute($row);
$person = array_combine(['id_number','parent_senior_id','application_type','full_name','birth_date','contact_number','barangay','senior_id_no','is_archived'], $base);
$city = findPossibleDuplicateRecords($conn, $person);
if ($city['count'] !== 3) throw new RuntimeException('Self or linked requests included, or candidates missed');
$local = findPossibleDuplicateRecords($conn, $person, 'Bagong Ilog');
if ($local['count'] !== 2) throw new RuntimeException('Barangay authorization scope failed');
$person['id_number'] = 'BENEFIT'; $person['parent_senior_id'] = 'SELF';
if (findPossibleDuplicateRecords($conn, $person)['count'] !== 3) throw new RuntimeException('Linked parent incorrectly flagged');
echo "PASS database matching, self/parent exclusion, linked requests, archived records, and barangay scope\n";
require_once __DIR__ . '/../includes/application_record_pagination.php';
$conn->exec("ALTER TABLE applications ADD date_submitted DATETIME DEFAULT '2026-10-24 10:00:00'");
$page = fetchApplicationRecordPage($conn, 'FROM applications WHERE is_archived = 0', [],
    'id_number as id, full_name, birth_date, application_type, barangay, date_submitted', 1, 20, true);
if ($page['totalApplicants'] !== 3 || $page['totalRecords'] !== 5) throw new RuntimeException('Independent registrations were grouped together');
$linkedGroup = array_values(array_filter($page['groups'], static fn($group) => count($group['applications']) === 3));
if (count($linkedGroup) !== 1 || $linkedGroup[0]['benefit_count'] !== 1) throw new RuntimeException('Explicitly linked requests were separated');
$first = fetchApplicationRecordPage($conn, 'FROM applications WHERE is_archived = 0', [],
    'id_number as id, full_name, birth_date, application_type, barangay, date_submitted', 1, 1, true);
$second = fetchApplicationRecordPage($conn, 'FROM applications WHERE is_archived = 0', [],
    'id_number as id, full_name, birth_date, application_type, barangay, date_submitted', 2, 1, true);
if ($first['totalPages'] !== 3 || count($first['groups']) !== 1 || $first['groups'][0]['key'] === $second['groups'][0]['key']) throw new RuntimeException('Identity pagination failed');
echo "PASS independent same-name registrations stay separate, linked services stay together, and pagination remains consistent\n";
