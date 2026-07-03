<?php
session_start();
require_once '../includes/db_connect.php';

// Auto-login from remember me cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    list($selector, $validator) = explode(':', $_COOKIE['remember_me']);

    $stmt = $conn->prepare("SELECT * FROM remember_tokens WHERE selector = :selector AND expires > NOW()");
    $stmt->execute(['selector' => $selector]);
    $token = $stmt->fetch();

    if ($token) {
        if (hash_equals($token['validator_hash'], hash('sha256', $validator))) {
            // Token is valid, log in the user
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
            $stmt->execute(['id' => $token['user_id']]);
            $user = $stmt->fetch();

            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['barangay'] = $user['barangay'];

                // Regenerate token to prevent theft
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

                header("Location: Department_Dashboard.php");
                exit;
            }
        }
    }
    // If token is invalid or expired, clear the cookie
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
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username AND role = 'department_admin'");
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
                    // Generate a secure "remember me" token
                    $selector = bin2hex(random_bytes(8)); // 16 character hex
                    $validator = bin2hex(random_bytes(32)); // 64 character hex
                    $validatorHash = hash('sha256', $validator);
                    $expiry = date('Y-m-d H:i:s', time() + (86400 * 30)); // 30 days

                    // Store the token in the database
                    $stmt = $conn->prepare("INSERT INTO remember_tokens (user_id, selector, validator_hash, expires) VALUES (:user_id, :selector, :validator_hash, :expires)");
                    $stmt->execute([
                        'user_id' => $user['id'],
                        'selector' => $selector,
                        'validator_hash' => $validatorHash,
                        'expires' => $expiry
                    ]);

                    // Set the cookie
                    setcookie('remember_me', $selector . ':' . $validator, time() + (86400 * 30), "/", "", false, true);
                }

                header("Location: Department_Dashboard.php");
                exit;
            } else {
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
    <title>Department Admin Login - CARELINK</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #1a4b8c 0%, #0d3a6e 100%);
            color: white;
            height: 100vh;
            overflow: hidden;
        }

        .background-image {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background-image: url('../images/system_background.png');
            background-size: cover;
            background-position: center;
            opacity: 0.3;
            animation: kenburns 30s ease-in-out infinite;
        }

        @keyframes kenburns {
            0% {
                transform: scale(1) translate(0, 0);
                opacity: 0.3;
            }
            50% {
                transform: scale(1.2) translate(-5%, 5%);
                opacity: 0.4;
            }
            100% {
                transform: scale(1) translate(0, 0);
                opacity: 0.3;
            }
        }

        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            padding: 20px;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border-radius: 15px;
            padding: 40px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideUp 0.5s ease-out;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-icon {
            font-size: 4rem;
            color: #4CAF50;
            margin-bottom: 15px;
        }

        .login-header h1 {
            font-size: 2rem;
            color: #4CAF50;
            margin-bottom: 10px;
        }

        .login-header p {
            font-size: 1rem;
            opacity: 0.9;
        }

        .back-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(-5px);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: black !important;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #4CAF50;
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        .form-control::placeholder { /* For Chrome, Firefox, Opera, Safari 10.1+ */
            color: rgba(0, 0, 0, 0.7) !important;
            opacity: 1; /* Firefox */
        }

        .form-control:-ms-input-placeholder { /* Internet Explorer 10-11 */
            color: rgba(0, 0, 0, 0.7) !important;
        }

        .form-control::-ms-input-placeholder { /* Microsoft Edge */
            color: rgba(0, 0, 0, 0.7) !important;
        }

        .password-container {
            position: relative;
            width: 100%;
        }

        .password-container .form-control {
            padding-right: 45px; /* Make space for the icon */
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: rgba(255, 255, 255, 0.7);
            transition: color 0.3s ease;
        }

        .toggle-password:hover {
            color: #4CAF50;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s;
            width: 100%;
        }

        .btn-primary {
            background: #4CAF50;
            color: white;
        }

        .btn-primary:hover {
            background: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
        }

        .forgot-password {
            text-align: center;
            margin-top: 20px;
        }

        .forgot-password a {
            color: #4CAF50;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .forgot-password a:hover {
            text-decoration: underline;
        }

        .error-message {
            color: #ff4d4d;
            background: rgba(255, 77, 77, 0.1);
            border: 1px solid rgba(255, 77, 77, 0.5);
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            margin-bottom: 20px;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .login-card {
                padding: 30px 25px;
                margin: 20px;
            }
            
            .login-header h1 {
                font-size: 1.7rem;
            }
            
            .login-icon {
                font-size: 3.5rem;
            }
        }
        /* Modal specific styles */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1001; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
            justify-content: center; /* Center horizontally */
            align-items: center; /* Center vertically */
            -webkit-animation: fadeIn 0.5s;
            animation: fadeIn 0.5s;
        }

        .modal-content {
            background-color: #fefefe;
            margin: auto; /* For browsers that do not support justify-content/align-items for position:fixed */
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 8px;
            box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2), 0 6px 20px 0 rgba(0,0,0,0.19);
            -webkit-animation: slideIn 0.5s forwards;
            animation: slideIn 0.5s forwards;
            color: #333; /* Ensure text is readable */
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .modal-header h2 {
            margin: 0;
            color: #333;
        }

        .close-modal {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            background: none;
            border: none;
        }

        .close-modal:hover,
        .close-modal:focus {
            color: #000;
            text-decoration: none;
            cursor: pointer;
        }
        /* Animations */
        @-webkit-keyframes fadeIn {
            from {opacity: 0} 
            to {opacity: 1}
        }

        @keyframes fadeIn {
            from {opacity: 0} 
            to {opacity: 1}
        }

        @-webkit-keyframes slideIn {
            from {top: -300px; opacity: 0} 
            to {top: 0; opacity: 1}
        }

        @keyframes slideIn {
            from {top: -300px; opacity: 0}
            to {top: 0; opacity: 1}
        }
    </style>
</head>
<body>
    <!-- Back Button -->
    <a href="../index.php" class="back-btn">
        <i class="fas fa-arrow-left"></i>
        Back to Home
    </a>

    <!-- Background Image -->
    <div class="background-image"></div>
    
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-icon">
                    <i class="fas fa-user-cog"></i>
                </div>
                <h1>Department Admin Login</h1>
                <p>Access system administration and management tools</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form id="adminLoginForm" method="post" action="">
                <div class="form-group">
                    <label for="adminUsername">Username</label>
                    <input type="text" id="adminUsername" name="adminUsername" class="form-control" placeholder="Enter admin username" oninput="this.value = this.value.replace(/[^a-zA-Z0-9]/g, '')" required>
                </div>
                
                <div class="form-group">
                    <label for="adminPassword">Password</label>
                    <div class="password-container">
                        <input type="password" id="adminPassword" name="adminPassword" class="form-control" placeholder="Enter admin password" required>
                        <i class="fas fa-eye toggle-password" id="toggleAdminPassword"></i>
                    </div>
                </div>

                <div class="form-group" style="display: flex; align-items: center;">
                    <input type="checkbox" id="remember" name="remember" style="margin-right: 10px;">
                    <label for="remember" style="margin-bottom: 0;">Remember Me</label>
                </div>
                
                <button type="submit" class="btn btn-primary">Login to Admin Panel</button>
                
                <div class="forgot-password">
                    <a href="#">Forgot Password?</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Forgot Password Modal -->
    <div id="forgotPasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Forgot Password</h2>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Enter your email address to receive a password reset link.</p>
                <form id="forgotPasswordForm">
                    <div class="form-group">
                        <label for="resetEmail">Email Address</label>
                        <input type="email" id="resetEmail" name="email" class="form-control" placeholder="Enter your email" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Send Reset Link</button>
                </form>
                <div id="forgotPasswordMessage" style="margin-top: 15px;"></div>
            </div>
        </div>
    </div>
    <script>
        // Consolidated JavaScript for password toggle and forgot password modal logic
        // Password toggle for adminPassword
        const toggleAdminPassword = document.querySelector('#toggleAdminPassword');
        const adminPassword = document.querySelector('#adminPassword');

        toggleAdminPassword.addEventListener('click', function (e) {
            const type = adminPassword.getAttribute('type') === 'password' ? 'text' : 'password';
            adminPassword.setAttribute('type', type);
            this.classList.toggle('fa-eye-slash');
        });

        // Forgot Password Modal Logic
        const forgotPasswordLink = document.querySelector('.forgot-password a');
        const forgotPasswordModal = document.getElementById('forgotPasswordModal');
        const closeForgotPasswordModalBtn = forgotPasswordModal.querySelector('.close-modal');
        const forgotPasswordForm = document.getElementById('forgotPasswordForm');
        const forgotPasswordMessage = document.getElementById('forgotPasswordMessage');

        forgotPasswordLink.addEventListener('click', function(e) {
            e.preventDefault();
            forgotPasswordModal.style.display = 'flex'; // Changed from 'block' to 'flex'
            forgotPasswordMessage.innerHTML = ''; // Clear previous messages
            forgotPasswordForm.reset(); // Clear form fields
        });

        closeForgotPasswordModalBtn.addEventListener('click', function() {
            forgotPasswordModal.style.display = 'none';
        });

        // Close modal if user clicks outside of it
        window.addEventListener('click', function(event) {
            if (event.target == forgotPasswordModal) {
                forgotPasswordModal.style.display = 'none';
            }
        });

        forgotPasswordForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const email = document.getElementById('resetEmail').value;
            forgotPasswordMessage.innerHTML = 'Sending reset link...';

            fetch('../api/forgot_password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `email=${encodeURIComponent(email)}`,
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    forgotPasswordMessage.style.color = 'green';
                    forgotPasswordMessage.innerHTML = data.message;
                    // Optionally close modal after a delay or success
                    // setTimeout(() => { forgotPasswordModal.style.display = 'none'; }, 3000);
                } else {
                    forgotPasswordMessage.style.color = 'red';
                    forgotPasswordMessage.innerHTML = data.message;
                }
            })
            .catch(error => {
                forgotPasswordMessage.style.color = 'red';
                forgotPasswordMessage.innerHTML = 'An error occurred. Please try again.';
                console.error('Error:', error);
            });
        });
    </script>
</body>
</html>