<?php
header('Content-Type: application/json');

$sssNumber = isset($_GET['sss_number']) ? trim($_GET['sss_number']) : '';

if (empty($sssNumber)) {
    echo json_encode([
        'success' => false,
        'message' => 'SSS number is required.'
    ]);
    exit();
}

// Clean SSS number (remove dashes)
$cleanSss = preg_replace('/[^0-9]/', '', $sssNumber);

if (empty($cleanSss)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid SSS number format.'
    ]);
    exit();
}

// Mock logic:
// If the last digit is odd, return <= 4000 (qualifies)
// If the last digit is even, return > 4000 (disqualifies)
$lastDigit = intval(substr($cleanSss, -1));
$pensionAmount = ($lastDigit % 2 === 1) ? 3500.00 : 5500.00;

echo json_encode([
    'success' => true,
    'sss_number' => $sssNumber,
    'pension_amount' => $pensionAmount,
    'message' => 'Simulated SSS Database Verification Successful.'
]);
exit();
?>
