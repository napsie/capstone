<?php
require_once __DIR__ . '/../includes/verification_queue.php';
$conn = new PDO('sqlite::memory:');
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$conn->exec('CREATE TABLE applications (workflow_state TEXT, status TEXT, is_archived INTEGER)');
$cases = [
    ['For Review', 'pending', 0, true],
    ['Received', 'pending', null, true],
    ['Needs Correction', 'pending', 0, true],
    [null, 'pending', 0, true],
    ['', '', 0, true],
    ['Verified', 'pending', 0, false],
    [' verified ', 'pending', 0, false],
    ['Approved', 'approved', 0, false],
    ['Released', 'approved', 0, false],
    ['Deceased', 'deceased', 0, false],
    ['For Review', 'deceased', 0, false],
    ['For Review', 'approved', 0, false],
    ['Completed', 'pending', 0, false],
    ['Rejected', 'rejected', 0, false],
    ['For Review', 'pending', 1, false],
];
foreach ($cases as [$workflow, $status, $archived, $expected]) {
    $conn->exec('DELETE FROM applications');
    $conn->prepare('INSERT INTO applications VALUES (?,?,?)')->execute([$workflow, $status, $archived]);
    $actual = (int)$conn->query('SELECT COUNT(*) FROM applications WHERE ' . verificationQueueCondition())->fetchColumn() > 0;
    if ($actual !== $expected) throw new RuntimeException('Queue mismatch: ' . json_encode([$workflow, $status, $archived]));
}
echo "Verification queue tests passed (15 cases).\n";
