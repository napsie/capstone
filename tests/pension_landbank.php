<?php
require_once __DIR__ . '/../includes/application_types.php';

$conn = new PDO('sqlite::memory:');
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$conn->exec('CREATE TABLE applications (id_number TEXT, parent_senior_id TEXT, senior_id_no TEXT,
    application_type TEXT, workflow_state TEXT, status TEXT, is_archived INTEGER, landbank_card_no TEXT)');
$senior = ['id_number' => 'SEN-1', 'senior_id_no' => '00016'];
function checkPension(bool $result, string $label): void {
    if (!$result) throw new RuntimeException($label);
}
function landbankCase(PDO $conn, array $senior, array $changes, bool $expected, string $label): void {
    $conn->exec('DELETE FROM applications');
    $row = array_replace(['APP-1', 'SEN-1', '00016', 'landbank', 'For Review', 'pending', 0, null], $changes);
    $conn->prepare('INSERT INTO applications VALUES (?,?,?,?,?,?,?,?)')->execute($row);
    checkPension(hasPensionLandbankPrerequisite($conn, $senior) === $expected, $label);
}
checkPension(!hasPensionLandbankPrerequisite($conn, $senior), 'Empty records must remain locked');
foreach (['Received','Submitted','For Review','Needs Correction','Verified','Approved','Released'] as $state) {
    landbankCase($conn, $senior, [4 => $state], true, 'Submitted enrollment state ' . $state);
}
landbankCase($conn, $senior, [4 => 'Rejected'], false, 'Rejected workflow');
landbankCase($conn, $senior, [5 => 'rejected'], false, 'Rejected status');
landbankCase($conn, $senior, [6 => 1], false, 'Archived enrollment');
landbankCase($conn, $senior, [4 => 'Draft'], false, 'Unsubmitted draft');
landbankCase($conn, $senior, [1 => 'SEN-2', 2 => '00017'], false, 'Another senior');
landbankCase($conn, $senior, [1 => 'SEN-2'], true, 'Match official ID');
landbankCase($conn, $senior, [3 => 'senior', 4 => 'Verified', 7 => '1234-5678'], true, 'Existing verified card');
landbankCase($conn, $senior, [3 => 'pension', 7 => '1234-5678'], false, 'Unverified card claim');
landbankCase($conn, $senior, [1 => 'SEN-2', 2 => '', 4 => 'Verified'], false, 'Blank IDs must not link unrelated records');
checkPension(!isApplicationAgeEligible('pension', 64), 'Age 64 is ineligible');
checkPension(isApplicationAgeEligible('pension', 65), 'Age 65 is eligible');
checkPension(isApplicationAgeEligible('pension', 69), 'Age 69 is eligible');
echo "Pension age and Land Bank prerequisite tests passed.\n";
foreach ([['0000123456789012', true], ['12345', true], ['', false],
    ['12345678901234567', false], ['111-111', false], ['12 34', false], ['12abc', false], ["123\n", false]] as [$number, $expected]) {
    checkPension(isValidAtmOrStubNumber($number) === $expected, 'ATM/stub validation: ' . json_encode($number));
}
echo "ATM and temporary stub validation tests passed (8 cases).\n";
