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

$username = trim((string)($_POST['username'] ?? ''));
if ($username === '' || strlen($username) > 50) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please provide a valid username.']);
    exit;
}

$genericMessage = 'If the account exists, a six-digit OTP will be sent to its registered email.';

try {
    $stmt = $conn->prepare('SELECT id, username, email FROM users WHERE username = :username AND COALESCE(is_archived, 0) = 0 LIMIT 1');
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Always use the same response so this endpoint cannot be used to discover accounts.
    if (!$user) {
        echo json_encode(['success' => true, 'message' => $genericMessage]);
        exit;
    }
    $email = (string)$user['email'];

    $smtpHost = trim((string) getenv('SMTP_HOST'));
    $smtpUsername = trim((string) getenv('SMTP_USERNAME'));
    $smtpPassword = (string) getenv('SMTP_PASSWORD');
    $smtpPort = (int) (getenv('SMTP_PORT') ?: 587);
    $smtpEncryption = strtolower(trim((string) (getenv('SMTP_ENCRYPTION') ?: 'tls')));
    $mailFrom = trim((string) (getenv('MAIL_FROM_ADDRESS') ?: $smtpUsername));
    $mailFromName = trim((string) (getenv('MAIL_FROM_NAME') ?: 'SeniorLink'));

    if ($smtpHost === '' || $smtpUsername === '' || $smtpPassword === '' || $mailFrom === '') {
        error_log('Password reset email was not sent because SMTP environment variables are not configured.');
        echo json_encode(['success' => true, 'message' => $genericMessage]);
        exit;
    }

    $otp = (string) random_int(100000, 999999);
    $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $startedAt = microtime(true);

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUsername;
    $mail->Password = $smtpPassword;
    $mail->Port = $smtpPort;
    // Railway should fail clearly instead of holding the reset screen for the
    // library's default five-minute connection timeout. This does not shorten
    // a successful Gmail delivery; it limits only an unavailable SMTP server.
    $smtpTimeout = (int) (getenv('SMTP_TIMEOUT') ?: 20);
    $mail->Timeout = max(5, min(60, $smtpTimeout));
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
    $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
    $mail->Body = "Hello {$safeName},<br><br>Your SENIORLINK password reset code is:<br><br>"
        . "<strong style=\"font-size:24px;letter-spacing:5px\">{$safeOtp}</strong><br><br>This code expires in 10 minutes. "
        . 'If you did not request this change, you may ignore this email.';
    $mail->AltBody = "Hello {$user['username']},\n\nYour SENIORLINK password reset code is: {$otp}\n\n"
        . 'This code expires in 10 minutes. If you did not request this change, you may ignore this email.';

    $mail->send();

    // Store a usable token only after the email has been accepted by the mail server.
    $tokenHash = password_hash($otp, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('UPDATE users SET reset_token = :token, reset_token_expiry = :expiry WHERE id = :id');
    $stmt->execute(['token' => $tokenHash, 'expiry' => $expiry, 'id' => $user['id']]);
    error_log(sprintf('Password reset OTP accepted by SMTP in %.2f seconds for user ID %d.', microtime(true) - $startedAt, (int) $user['id']));

    echo json_encode(['success' => true, 'message' => $genericMessage]);
} catch (Exception $e) {
    error_log('Password reset mail error: ' . $e->getMessage());
    echo json_encode(['success' => true, 'message' => $genericMessage]);
} catch (Throwable $e) {
    error_log('Password reset error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred. Please try again later.']);
}
