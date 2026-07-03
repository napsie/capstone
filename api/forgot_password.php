<?php
ini_set('display_errors', 'Off'); // Turn off display errors to prevent HTML output
ini_set('log_errors', 'On'); // Enable error logging (PHP's internal errors will still go here)
ini_set('error_log', 'C:/Users/PLPASIG/.gemini/tmp/b0cdc0f307f86e1b2fa0f2ebd383b8a41ee56aff1ff6779cc4cfdc4b0ac86e61/forgot_password_error.log'); // Main PHP error log

error_log('Debug: forgot_password.php started.'); // Test log message

session_start();
header('Content-Type: application/json'); // This header will be overridden if Debugoutput is 'html'
require_once '../includes/db_connect.php';
require_once '../vendor/autoload.php'; // Include the autoloader

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception; // Though dummy class doesn't define this, include for completeness
use PHPMailer\PHPMailer\SMTP;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid email address.']);
    exit;
}

try {
    error_log('Debug: Searching for user with email: ' . $email);
    // Check if user exists
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        error_log('Debug: User with email ' . $email . ' not found in database.');
        // For security, always return a generic success message even if email not found
        // to prevent email enumeration.
        echo json_encode(['success' => true, 'message' => 'If your email address is in our database, you will receive a password reset link.']);
        exit;
    }

    // Generate a unique token
    $token = bin2hex(random_bytes(32)); // 64 character hex string
    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour

    // Store token and expiry in database
    $stmt = $conn->prepare("UPDATE users SET reset_token = :token, reset_token_expiry = :expiry WHERE id = :id");
    $stmt->execute(['token' => $token, 'expiry' => $expiry, 'id' => $user['id']]);

    // Create reset link
    // IMPORTANT: Replace 'YOUR_DOMAIN' with your actual domain or IP address
    $resetLink = "http://localhost/Carelink_2.0/pages/reset_password.php?token=" . $token . "&email=" . urlencode($email);

    // Send email using PHPMailer
    $mail = new PHPMailer(true); // true enables exceptions
    error_log('Debug: PHPMailer object created.');
    try {
        // Configure PHPMailer to output debug info directly to HTML for debugging
        $mail->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_CONNECTION; // Enable connection-level debug output
        $mail->Debugoutput = 'html'; // Output debug info to HTML

        //Server settings
        $mail->isSMTP();                                            // Send using SMTP
        $mail->Host       = 'smtp.gmail.com';                     // Set the SMTP server to send through
        $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
        $mail->Username   = 'napay_dhonebert@plpasig.edu.ph';                 // SMTP username (YOUR GMAIL ADDRESS). IMPORTANT: If using Gmail, you need to generate an App Password. See https://support.google.com/accounts/answer/185833
        $mail->Password   = 'zommwixikfkimjib';                    // SMTP password (YOUR GMAIL APP PASSWORD). See https://support.google.com/accounts/answer/185833
        $mail->SMTPSecure = 'ssl';            // Enable implicit TLS encryption using SSL for port 465
        $mail->Port       = 465;                                    // TCP port to connect to; use 587 if you set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`
        
        error_log('Debug: PHPMailer configured.');

        //Recipients
        $mail->setFrom('napay_dhonebert@plpasig.edu.ph', 'Carelink'); // Sender email (YOUR GMAIL ADDRESS). IMPORTANT: This should be the same as your Username.
        $mail->addAddress($email, $user['username']);              // Add a recipient
        error_log('Debug: Recipient added.');

        // Content
        $mail->isHTML(true);                                        // Set email format to HTML
        $mail->Subject = 'Password Reset Request for Carelink Account';
        $mail->Body    = 'Hello ' . htmlspecialchars($user['username']) . ',<br><br>'
                       . 'You have requested to reset your password. Please click on the following link to reset your password:'
                       . '<br><br>'
                       . '<a href="' . $resetLink . '">' . $resetLink . '</a>'
                       . '<br><br>'
                       . 'This link will expire in 1 hour.'
                       . '<br><br>'
                       . 'If you did not request a password reset, please ignore this email.'
                       . '<br><br>'
                       . 'Thank you,<br>'
                       . 'Carelink Team';
        $mail->AltBody = 'Hello ' . $user['username'] . ','
                       . 'You have requested to reset your password. Please copy and paste the following link into your browser to reset your password:'
                       . $resetLink
                       . 'This link will expire in 1 hour.'
                       . 'If you did not request a password reset, please ignore this email.'
                       . 'Thank you,'
                       . 'Carelink Team';
        error_log('Debug: Email content set. Attempting to send...');

        $mail->send();
        error_log('Debug: Email sent successfully (or no exception thrown).');
        echo json_encode(['success' => true, 'message' => 'If your email address is in our database, you will receive a password reset link.']);
    } catch (Exception $e) {
        // Log the error but return generic success to user for security
        error_log("PHPMailer Error: " . $e->getMessage() . " for email: " . $email);
        echo json_encode(['success' => true, 'message' => 'If your email address is in our database, you will receive a password reset link.']);
    }

} catch (PDOException $e) {
    error_log("Database Error in forgot_password.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred. Please try again later.']);
}
?>