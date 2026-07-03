<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/password_validation.php'; // Include the password validation function

$message = '';
$error = '';
$showForm = false; // Flag to control form display

// Handle GET request (when user clicks reset link)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token']) && isset($_GET['email'])) {
    $token = $_GET['token'];
    $email = $_GET['email'];

    try {
        $stmt = $conn->prepare("SELECT id, reset_token_expiry FROM users WHERE email = :email AND reset_token = :token");
        $stmt->execute(['email' => $email, 'token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Check if token has expired
            if (new DateTime($user['reset_token_expiry']) > new DateTime()) {
                $showForm = true; // Token is valid and not expired, show password reset form
            } else {
                $error = "Password reset link has expired. Please request a new one.";
            }
        } else {
            $error = "Invalid password reset link. Please ensure you copied the full link.";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Handle POST request (when user submits new password)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resetPassword'])) {
    $token = $_POST['token'] ?? '';
    $email = $_POST['email'] ?? '';
    $newPassword = $_POST['newPassword'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';

    // Re-validate token and expiry for POST request
    try {
        $stmt = $conn->prepare("SELECT id, reset_token_expiry FROM users WHERE email = :email AND reset_token = :token");
        $stmt->execute(['email' => $email, 'token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || new DateTime($user['reset_token_expiry']) <= new DateTime()) {
            $error = "Invalid or expired password reset request. Please request a new link.";
        } else {
            if (empty($newPassword) || empty($confirmPassword)) {
                $error = "Please enter and confirm your new password.";
                $showForm = true; // Still show form if validation fails
            } elseif ($newPassword !== $confirmPassword) {
                $error = "New passwords do not match.";
                $showForm = true; // Still show form if validation fails
            } else {
                $validationResult = validatePassword($newPassword);
                if (!$validationResult['valid']) {
                    $error = $validationResult['message'];
                    $showForm = true;
                } else {
                    // Hash new password
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                    // Update password and clear token
                    $updateStmt = $conn->prepare("UPDATE users SET password = :password, reset_token = NULL, reset_token_expiry = NULL WHERE id = :id");
                    $updateStmt->execute(['password' => $hashedPassword, 'id' => $user['id']]);

                    $message = "Your password has been successfully reset. You can now log in with your new password.";
                    $showForm = false; // Hide form after successful reset
                }
            }
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        $showForm = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Carelink</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/main-dark-mode.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: var(--bg);
            color: var(--text);
        }
        .reset-container {
            background-color: var(--card-bg);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .reset-container h2 {
            margin-bottom: 20px;
            color: var(--primary);
        }
        .form-group {
            margin-bottom: 15px;
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--primary);
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: rgba(255,255,255,0.02);
            color: var(--text);
        }
        .btn {
            background-color: var(--secondary);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            width: 100%;
            font-size: 1rem;
        }
        .btn:hover {
            background-color: #2980b9;
        }
        .message.success {
            color: var(--success);
            margin-bottom: 15px;
        }
        .message.error {
            color: var(--accent);
            margin-bottom: 15px;
        }
        .back-to-login {
            margin-top: 20px;
            display: block;
            color: var(--secondary);
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <h2>Reset Your Password</h2>
        <?php if ($message): ?>
            <p class="message success"><?php echo $message; ?></p>
            <a href="../index.php" class="back-to-login">Back to Login</a>
        <?php elseif ($error): ?>
            <p class="message error"><?php echo $error; ?></p>
            <a href="../index.php" class="back-to-login">Back to Login</a>
        <?php elseif ($showForm): ?>
            <form action="reset_password.php" method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <div class="form-group">
                    <label for="newPassword">New Password:</label>
                    <input type="password" id="newPassword" name="newPassword" required>
                </div>
                <div class="form-group">
                    <label for="confirmPassword">Confirm New Password:</label>
                    <input type="password" id="confirmPassword" name="confirmPassword" required>
                </div>
                <button type="submit" name="resetPassword" class="btn">Reset Password</button>
            </form>
        <?php else: ?>
            <p class="message error">Access denied or invalid request.</p>
            <a href="../index.php" class="back-to-login">Back to Login</a>
        <?php endif; ?>
    </div>
</body>
</html>