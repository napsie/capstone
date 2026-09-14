<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db_connect.php';
require_once '../includes/password_validation.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$username = trim((string)($_POST['username'] ?? ''));
$otp = trim((string)($_POST['otp'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$confirmPassword = (string)($_POST['confirmPassword'] ?? '');

if ($username === '' || !preg_match('/^\d{6}$/', $otp)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Enter the six-digit code sent to your email.']);
    exit;
}
if ($password !== $confirmPassword) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'The new passwords do not match.']);
    exit;
}
$validation = validatePassword($password);
if (!$validation['valid']) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $validation['message']]);
    exit;
}

$attemptKey = hash('sha256', strtolower($username));
$attempts = (int)($_SESSION['password_otp_attempts'][$attemptKey] ?? 0);
if ($attempts >= 5) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many incorrect attempts. Request a new code.']);
    exit;
}

$stmt = $conn->prepare("SELECT id, reset_token, reset_token_expiry FROM users
    WHERE username = :username AND COALESCE(is_archived, 0) = 0 LIMIT 1");
$stmt->execute(['username' => $username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$valid = $user && !empty($user['reset_token']) && !empty($user['reset_token_expiry'])
    && strtotime($user['reset_token_expiry']) > time()
    && password_verify($otp, $user['reset_token']);

if (!$valid) {
    $_SESSION['password_otp_attempts'][$attemptKey] = $attempts + 1;
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'The code is incorrect or has expired.']);
    exit;
}

$stmt = $conn->prepare('UPDATE users SET password = :password, reset_token = NULL, reset_token_expiry = NULL WHERE id = :id');
$stmt->execute(['password' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['id']]);
unset($_SESSION['password_otp_attempts'][$attemptKey]);

echo json_encode(['success' => true, 'message' => 'Password updated. You can now sign in.']);
