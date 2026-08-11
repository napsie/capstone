<?php
ini_set('display_errors', 'Off');
ini_set('log_errors', 'On');

header('Content-Type: application/json');
require_once '../includes/db_connect.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
if (!$email) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please provide a valid email address.']);
    exit;
}

$genericMessage = 'If your email address is in our database, you will receive a password reset link.';

try {
    $stmt = $conn->prepare('SELECT id, username FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Always use the same response so this endpoint cannot be used to discover accounts.
    if (!$user) {
        echo json_encode(['success' => true, 'message' => $genericMessage]);
        exit;
    }

    $smtpHost = trim((string) getenv('SMTP_HOST'));
    $smtpUsername = trim((string) getenv('SMTP_USERNAME'));
    $smtpPassword = (string) getenv('SMTP_PASSWORD');
    $smtpPort = (int) (getenv('SMTP_PORT') ?: 587);
    $smtpEncryption = strtolower(trim((string) (getenv('SMTP_ENCRYPTION') ?: 'tls')));
    $mailFrom = trim((string) (getenv('MAIL_FROM_ADDRESS') ?: $smtpUsername));
    $mailFromName = trim((string) (getenv('MAIL_FROM_NAME') ?: 'SeniorLink'));
    $baseUrl = rtrim(trim((string) getenv('APP_URL')), '/');

    if ($smtpHost === '' || $smtpUsername === '' || $smtpPassword === '' || $mailFrom === '') {
        error_log('Password reset email was not sent because SMTP environment variables are not configured.');
        echo json_encode(['success' => true, 'message' => $genericMessage]);
        exit;
    }

    if ($baseUrl === '') {
        $httpsEnabled = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
        $scheme = $httpsEnabled ? 'https' : 'http';
        $host = preg_replace('/[^A-Za-z0-9.:-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $projectPath = str_replace('\\', '/', dirname(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/api/forgot_password.php'))));
        $baseUrl = $scheme . '://' . ($host ?: 'localhost') . rtrim($projectPath, '/');
    }

    $token = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
    $resetLink = $baseUrl . '/pages/reset_password.php?token=' . rawurlencode($token)
        . '&email=' . rawurlencode($email);

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUsername;
    $mail->Password = $smtpPassword;
    $mail->Port = $smtpPort;
    if ($smtpEncryption === 'ssl' || $smtpEncryption === 'smtps') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($smtpEncryption !== '' && $smtpEncryption !== 'none') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }
    $mail->setFrom($mailFrom, $mailFromName);
    $mail->addAddress($email, $user['username']);
    $mail->isHTML(true);
    $mail->Subject = 'Password Reset Request for SeniorLink Account';
    $safeName = htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8');
    $safeLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');
    $mail->Body = "Hello {$safeName},<br><br>Use the link below to reset your password:<br><br>"
        . "<a href=\"{$safeLink}\">Reset password</a><br><br>This link expires in one hour. "
        . 'If you did not request this change, you may ignore this email.';
    $mail->AltBody = "Hello {$user['username']},\n\nReset your password using this link:\n{$resetLink}\n\n"
        . 'This link expires in one hour. If you did not request this change, you may ignore this email.';

    $mail->send();

    // Store a usable token only after the email has been accepted by the mail server.
    $stmt = $conn->prepare('UPDATE users SET reset_token = :token, reset_token_expiry = :expiry WHERE id = :id');
    $stmt->execute(['token' => $token, 'expiry' => $expiry, 'id' => $user['id']]);

    echo json_encode(['success' => true, 'message' => $genericMessage]);
} catch (Exception $e) {
    error_log('Password reset mail error: ' . $e->getMessage());
    echo json_encode(['success' => true, 'message' => $genericMessage]);
} catch (Throwable $e) {
    error_log('Password reset error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred. Please try again later.']);
}
