<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db_connect.php';
require_once '../includes/proxy_token_resolver.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['barangay_staff', 'department_admin', 'super_admin'], true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please sign in before scanning a representative QR code.']);
    exit();
}

$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Token is required.']);
    exit();
}

$data = resolveProxyToken($conn, $token);

if ($data === null) {
    echo json_encode(['success' => false, 'message' => 'QR code is invalid, expired, or no longer linked to an application.']);
    exit();
}

echo json_encode([
    'success' => true,
    'data' => $data
]);
exit();
?>
