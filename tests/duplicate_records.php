<?php
require_once __DIR__ . '/../includes/duplicate_records.php';
$person = ['full_name' => 'Juan Dela Cruz', 'birth_date' => '1950-01-01', 'contact_number' => '09123456789', 'barangay' => 'Bagong Ilog', 'senior_id_no' => 'SC-001'];
$cases = [
    [['full_name' => '  JUAN   DELA CRUZ ', 'birth_date' => '1950-01-01'], 80],
    [['full_name' => 'Juan Dela Cruz'], 45],
    [['barangay' => 'Bagong Ilog'], 10],
    [['birth_date' => '1950-01-01', 'contact_number' => '+63 912 345 6789'], 60],
    [['senior_id_no' => 'sc-001'], 100],
    [$person, 215],
    [[], 0],
];
foreach ($cases as [$candidate, $expected]) {
    $result = scoreDuplicateRecord($person, $candidate);
    if ($result['score'] !== $expected || $result['percentage'] !== min(100, $expected)) throw new RuntimeException('Incorrect duplicate score');
}
if (scoreDuplicateRecord([], [])['score'] !== 0) throw new RuntimeException('Blank fields matched');
echo "PASS duplicate scoring, normalization, threshold boundaries, empty fields, and percentage cap\n";
