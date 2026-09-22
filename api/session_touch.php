<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/request_security.php';

header('Content-Type: application/json; charset=utf-8');
requireSameOriginMutation();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Your session has expired.']);
    exit;
}
$_SESSION['last_activity_at'] = time();
echo json_encode(['success' => true]);
