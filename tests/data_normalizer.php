<?php
require_once __DIR__ . '/../includes/data_normalizer.php';
$barangays = ['Bagong Ilog', 'San Antonio'];
$input = normalizeApplicationInput([
    'full_name' => '  jUAN   dela CRUZ ', 'contact_number' => '+63 912 345 6789',
    'complete_address' => '  12   Sample Street ', 'barangay' => 'bagong ilog',
    'senior_id_no' => ' sc 001 / 24 ',
], $barangays);
$expected = ['full_name'=>'Juan Dela Cruz','contact_number'=>'09123456789','complete_address'=>'12 Sample Street','barangay'=>'Bagong Ilog','senior_id_no'=>'SC-001-24'];
foreach ($expected as $key=>$value) { if (($input[$key]??null)!==$value) { fwrite(STDERR,"FAIL {$key}: ".($input[$key]??'NULL')."\n"); exit(1); } echo "PASS {$key}\n"; }

$phoneCases = [
    '09123456789' => true,
    '+63 912 345 6789' => true,
    '9123456789' => true,
    '091234567890' => false,
    '08123456789' => false,
    'letters' => false,
];
foreach ($phoneCases as $phone => $valid) {
    if (isValidPhilippineMobileNumber($phone) !== $valid) {
        fwrite(STDERR, "FAIL phone validation: {$phone}\n");
        exit(1);
    }
}
echo "PASS phone validation\n";
