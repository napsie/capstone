<?php
require_once __DIR__ . '/../includes/application_types.php';
$conn = new PDO('sqlite::memory:');
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$conn->exec('CREATE TABLE applications (id_number TEXT, parent_senior_id TEXT, senior_id_no TEXT, application_type TEXT, workflow_state TEXT, status TEXT, is_archived INTEGER, landbank_card_no TEXT)');
$senior = ['id_number'=>'SEN-1','senior_id_no'=>'00016'];
if (hasBurialLandbankPrerequisite($conn,$senior)) throw new RuntimeException('Empty records unlocked');
$cases = [
    [[],true],
    [[4=>'Approved'],true],
    [[4=>'Released'],true],
    [[4=>'For Review'],false],
    [[4=>'Needs Correction'],false],
    [[4=>'Draft'],false],
    [[4=>'Rejected'],false],
    [[5=>'rejected'],false],
    [[5=>'cancelled'],false],
    [[6=>1],false],
    [[1=>'SEN-2',2=>'00017'],false],
    [[1=>'SEN-2'],true],
    [[1=>'SEN-2',2=>''],false],
    [[3=>'senior',7=>'123456'],true],
    [[3=>'senior',7=>'   '],false],
    [[3=>'senior',7=>'123456',4=>'For Review'],false],
    [[5=>'deceased'],true],
];
foreach($cases as $index=>[$changes,$expected]) {
    $conn->exec('DELETE FROM applications');
    $row=array_replace(['APP-1','SEN-1','00016','landbank','Verified','approved',0,null],$changes);
    $conn->prepare('INSERT INTO applications VALUES (?,?,?,?,?,?,?,?)')->execute($row);
    if(hasBurialLandbankPrerequisite($conn,$senior)!==$expected) throw new RuntimeException('Case '.$index.' failed');
}
echo "Burial LandBank prerequisite: 18 cases passed.\n";
