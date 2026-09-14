<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');
require_once '../includes/db_connect.php';
require_once '../includes/request_security.php';
require_once '../includes/duplicate_detector.php';
requireSameOriginMutation();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'barangay_staff') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only authorized barangay staff may perform this check.']);
    exit;
}

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;
$input['barangay'] = (string)($_SESSION['barangay'] ?? '');

try {
    echo json_encode(['success' => true, 'matches' => findLikelyBeneficiaryDuplicates($conn, $input)]);
} catch (Throwable $e) {
    error_log('Duplicate beneficiary check failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'The duplicate check could not be completed. Please try again.']);
}
