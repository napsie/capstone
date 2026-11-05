<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/digital_id_notifications.php';
header('Content-Type: application/json');
header('Cache-Control: private, no-store');
header('Referrer-Policy: no-referrer');
$token = $_GET['token'] ?? '';
if (!is_string($token) || !preg_match('/^PRX-[A-Z0-9]{4,12}$/', $token)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid permanent Tracking No. is required.']);
    exit;
}
try {
    echo json_encode(['success' => true, 'notification' => trackerDigitalIdNotice($conn, $token)]);
} catch (Throwable $e) {
    error_log('Digital ID notification lookup failed: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Notifications are temporarily unavailable.']);
}
