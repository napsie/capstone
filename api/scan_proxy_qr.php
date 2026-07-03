<?php
header('Content-Type: application/json');
require_once '../includes/crypto.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Token is required.']);
    exit();
}

$data = ProxyCrypto::decrypt($token);

if ($data === null) {
    echo json_encode(['success' => false, 'message' => 'Failed to decrypt token. Data may be corrupted or invalid.']);
    exit();
}

echo json_encode([
    'success' => true,
    'data' => $data
]);
exit();
?>
