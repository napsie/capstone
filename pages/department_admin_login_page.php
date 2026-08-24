<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/audit_logger.php';

if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    $rememberParts = explode(':', (string)$_COOKIE['remember_me'], 2);
    $selector = $rememberParts[0] ?? '';
    $validator = $rememberParts[1] ?? '';

    $stmt = $conn->prepare("SELECT * FROM remember_tokens WHERE selector = :selector AND expires > NOW()");
    $stmt->execute(['selector' => $selector]);
    $token = $stmt->fetch();

    if ($token && $validator !== '') {
        if (hash_equals($token['validator_hash'], hash('sha256', $validator))) {
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id AND role = 'department_admin' AND (is_archived = 0 OR is_archived IS NULL)");
            $stmt->execute(['id' => $token['user_id']]);
            $user = $stmt->fetch();

            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['barangay'] = $user['barangay'];
                $_SESSION['profile_picture'] = $user['profile_picture'];

                $newValidator = bin2hex(random_bytes(32));
                $newValidatorHash = hash('sha256', $newValidator);
                $expiry = date('Y-m-d H:i:s', time() + (86400 * 30));

                $stmt = $conn->prepare("UPDATE remember_tokens SET validator_hash = :validator_hash, expires = :expires WHERE id = :id");
                $stmt->execute([
                    'validator_hash' => $newValidatorHash,
                    'expires' => $expiry,
                    'id' => $token['id']
                ]);

                setcookie('remember_me', $selector . ':' . $newValidator, time() + (86400 * 30), "/", "", false, true);
                if (logAudit($conn, 'LOGIN', 'Restored remembered login as Department Administrator.')) {
                    $_SESSION['login_audit_recorded'] = true;
                }
                header("Location: Department_Dashboard.php");
                exit;
            }
        }
    }
    setcookie('remember_me', '', time() - 3600, "/");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['adminUsername']);
    $password = $_POST['adminPassword'];
    $remember = isset($_POST['remember']);

    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        try {
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username AND role = 'department_admin' AND (is_archived = 0 OR is_archived IS NULL)");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['barangay'] = $user['barangay'];
                $_SESSION['profile_picture'] = $user['profile_picture'];

                if ($remember) {
                    $selector = bin2hex(random_bytes(8));
                    $validator = bin2hex(random_bytes(32));
                    $validatorHash = hash('sha256', $validator);
                    $expiry = date('Y-m-d H:i:s', time() + (86400 * 30));

                    $stmt = $conn->prepare("INSERT INTO remember_tokens (user_id, selector, validator_hash, expires) VALUES (:user_id, :selector, :validator_hash, :expires)");
                    $stmt->execute([
                        'user_id' => $user['id'],
                        'selector' => $selector,
                        'validator_hash' => $validatorHash,
                        'expires' => $expiry
                    ]);

                    setcookie('remember_me', $selector . ':' . $validator, time() + (86400 * 30), "/", "", false, true);
                }

                if (logAudit($conn, 'LOGIN', 'Logged in as Department Administrator.')) {
                    $_SESSION['login_audit_recorded'] = true;
                }
                header("Location: Department_Dashboard.php");
                exit;
            } else {
                logAudit($conn, 'FAILED_LOGIN', "Failed Department Administrator login attempt for username '{$username}'.", $user ? (int)$user['id'] : null, [
                    'username' => $username ?: 'Unknown',
                    'role' => 'department_admin',
                    'barangay' => null,
                ]);
                $error = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Admin Login - SENIORLINK</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/seniorlink-public.css?v=1">
    <link rel="stylesheet" href="../assets/css/seniorlink-ui.css?v=15">
    <script src="../assets/js/modal-hci.js?v=2" defer></script>
</head>
<body class="auth-page">
    <div class="page-bg page-bg--pages" aria-hidden="true"></div>

    <a href="../index.php" class="back-btn">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
        Back to Home
    </a>

    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-card-header">
                <div class="auth-header-topline">
                    <div class="auth-icon admin" aria-hidden="true">
                        <i class="fas fa-user-cog"></i>
                    </div>
                    <span class="auth-eyebrow">Secure administrator access</span>
                </div>
                <h1>Department Admin Login</h1>
                <p>Access system administration and management tools</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message" role="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form id="adminLoginForm" method="post" action="">
                <div class="form-group">
                    <label for="adminUsername">Username</label>
                    <input type="text" id="adminUsername" name="adminUsername" class="form-control" placeholder="Enter admin username" oninput="this.value = this.value.replace(/[^a-zA-Z0-9]/g, '')" required>
                </div>

                <div class="form-group">
                    <label for="adminPassword">Password</label>
                    <div class="password-field">
                        <input type="password" id="adminPassword" name="adminPassword" class="form-control" placeholder="Enter admin password" required>
                        <button type="button" class="toggle-password" id="toggleAdminPassword" aria-label="Show password" aria-pressed="false">
                            <i class="fas fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div class="checkbox-row">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Remember Me</label>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Login to Admin Panel</button>

                <div class="forgot-password">
                    <a href="#">Forgot Password?</a>
                </div>
            </form>
        </div>
    </div>

    <div id="forgotPasswordModal" class="cl-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="forgot-title">
        <div class="cl-modal-content">
            <div class="cl-modal-header">
                <h2 id="forgot-title">Forgot Password</h2>
                <button type="button" class="close-modal" aria-label="Close">&times;</button>
            </div>
            <p style="font-size:0.9rem;color:var(--text-body);margin-bottom:16px;">Enter your email address to receive a password reset link.</p>
            <form id="forgotPasswordForm">
                <div class="form-group">
                    <label for="resetEmail">Email Address</label>
                    <input type="email" id="resetEmail" name="email" class="form-control" placeholder="Enter your email" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Send Reset Link</button>
            </form>
            <div id="forgotPasswordMessage" style="margin-top: 15px; font-size: 0.875rem;"></div>
        </div>
    </div>

    <script>
        const toggleAdminPassword = document.querySelector('#toggleAdminPassword');
        const adminPassword = document.querySelector('#adminPassword');
        toggleAdminPassword.addEventListener('click', function () {
            const type = adminPassword.getAttribute('type') === 'password' ? 'text' : 'password';
            adminPassword.setAttribute('type', type);
            const isVisible = type === 'text';
            this.querySelector('i').classList.toggle('fa-eye-slash', isVisible);
            this.setAttribute('aria-pressed', String(isVisible));
            this.setAttribute('aria-label', isVisible ? 'Hide password' : 'Show password');
        });

        const forgotPasswordLink = document.querySelector('.forgot-password a');
        const forgotPasswordModal = document.getElementById('forgotPasswordModal');
        const closeForgotPasswordModalBtn = forgotPasswordModal.querySelector('.close-modal');
        const forgotPasswordForm = document.getElementById('forgotPasswordForm');
        const forgotPasswordMessage = document.getElementById('forgotPasswordMessage');

        forgotPasswordLink.addEventListener('click', function(e) {
            e.preventDefault();
            forgotPasswordModal.classList.add('is-open');
            forgotPasswordModal.setAttribute('aria-hidden', 'false');
            forgotPasswordMessage.innerHTML = '';
            forgotPasswordForm.reset();
            document.getElementById('resetEmail').focus();
        });

        closeForgotPasswordModalBtn.addEventListener('click', function() {
            forgotPasswordModal.classList.remove('is-open');
            forgotPasswordModal.setAttribute('aria-hidden', 'true');
            forgotPasswordLink.focus();
        });

        window.addEventListener('click', function(event) {
            if (event.target === forgotPasswordModal) {
                forgotPasswordModal.classList.remove('is-open');
                forgotPasswordModal.setAttribute('aria-hidden', 'true');
                forgotPasswordLink.focus();
            }
        });

        forgotPasswordForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const email = document.getElementById('resetEmail').value;
            forgotPasswordMessage.innerHTML = 'Sending reset link...';

            fetch('../api/forgot_password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `email=${encodeURIComponent(email)}`,
            })
            .then(response => response.json())
            .then(data => {
                forgotPasswordMessage.style.color = data.success ? 'green' : 'red';
                forgotPasswordMessage.innerHTML = data.message;
            })
            .catch(() => {
                forgotPasswordMessage.style.color = 'red';
                forgotPasswordMessage.innerHTML = 'An error occurred. Please try again.';
            });
        });
    </script>
</body>
</html>
