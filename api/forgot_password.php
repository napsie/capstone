<?php
ini_set('display_errors', 'Off');
ini_set('log_errors', 'On');

header('Content-Type: application/json');
require_once '../includes/db_connect.php';
require_once '../includes/local_environment.php';
require_once '../includes/transactional_email.php';

loadLocalEnvironment(dirname(__DIR__) . '/config/local.env');

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

    $brevoApiKey = trim((string) getenv('BREVO_API_KEY'));
    $mailFrom = trim((string) getenv('MAIL_FROM_ADDRESS'));
    $mailFromName = trim((string) (getenv('MAIL_FROM_NAME') ?: 'SeniorLink'));

    if ($brevoApiKey === '' || $mailFrom === '') {
        error_log('Password reset email was not sent because Brevo environment variables are not configured.');
        echo json_encode(['success' => true, 'message' => $genericMessage]);
        exit;
    }

    $otp = (string) random_int(100000, 999999);
    $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $startedAt = microtime(true);

    $safeName = htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8');
    $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
    $htmlBody = "Hello {$safeName},<br><br>Your SENIORLINK password reset code is:<br><br>"
        . "<strong style=\"font-size:24px;letter-spacing:5px\">{$safeOtp}</strong><br><br>This code expires in 10 minutes. "
        . 'If you did not request this change, you may ignore this email.';
    $textBody = "Hello {$user['username']},\n\nYour SENIORLINK password reset code is: {$otp}\n\n"
        . 'This code expires in 10 minutes. If you did not request this change, you may ignore this email.';

    sendBrevoEmail(
        $brevoApiKey,
        ['email' => $mailFrom, 'name' => $mailFromName],
        ['email' => $email, 'name' => (string)$user['username']],
        'Password Reset Request for SeniorLink Account',
        $htmlBody,
        $textBody
    );

    // Store a usable token only after the email has been accepted by the mail server.
    $tokenHash = password_hash($otp, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('UPDATE users SET reset_token = :token, reset_token_expiry = :expiry WHERE id = :id');
    $stmt->execute(['token' => $tokenHash, 'expiry' => $expiry, 'id' => $user['id']]);
    error_log(sprintf('Password reset OTP accepted by Brevo in %.2f seconds for user ID %d.', microtime(true) - $startedAt, (int) $user['id']));

    echo json_encode(['success' => true, 'message' => $genericMessage]);
} catch (EmailDeliveryException $e) {
    error_log('Password reset email error: ' . $e->getMessage());
    echo json_encode(['success' => true, 'message' => $genericMessage]);
} catch (Throwable $e) {
    error_log('Password reset error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred. Please try again later.']);
}
