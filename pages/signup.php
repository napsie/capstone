<?php
require_once '../includes/db_connect.php';
require_once '../includes/barangays_list.php';
require_once '../includes/password_validation.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $masterPassword = $_POST['masterPassword'];
    $correctMasterPassword = 'CarelinkMaster2025!'; // This is the master password.

    if ($masterPassword !== $correctMasterPassword) {
        $error = 'Invalid Master Password. Please try again.';
    } else {
        $firstName = trim($_POST['firstName']);
        $lastName = trim($_POST['lastName']);
        $email = trim($_POST['email']);
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirmPassword'];
        $role = $_POST['role'];
        $barangay = isset($_POST['barangay']) ? $_POST['barangay'] : null;

        if (empty($firstName) || empty($lastName) || empty($email) || empty($username) || empty($password) || empty($confirmPassword) || empty($role)) {
            $error = 'Please fill in all required fields.';
        } else if ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } else {
            $validationResult = validatePassword($password);
            if (!$validationResult['valid']) {
                $error = $validationResult['message'];
            } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email format.';
            } else {
                // Check user limit for barangay_staff role
                if ($role === 'barangay_staff' && !empty($barangay)) {
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'barangay_staff' AND barangay = :barangay");
                    $stmt->execute(['barangay' => $barangay]);
                    $userCount = $stmt->fetchColumn();

                    if ($userCount >= 2) {
                        $error = 'The maximum number of users for this barangay has been reached.';
                    }
                }
                
                if (empty($error)) {
                    try {
                        // Check if username or email already exists
                        $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username OR email = :email");
                        $stmt->execute(['username' => $username, 'email' => $email]);
                        if ($stmt->fetch()) {
                            $error = 'Username or email already exists.';
                        } else {
                            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                            $conn->beginTransaction();

                            $sql = "INSERT INTO users (first_name, last_name, email, username, password, role, barangay) VALUES (:first_name, :last_name, :email, :username, :password, :role, :barangay)";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([
                                'first_name' => $firstName,
                                'last_name' => $lastName,
                                'email' => $email,
                                'username' => $username,
                                'password' => $hashedPassword,
                                'role' => $role,
                                'barangay' => $barangay
                            ]);

                            $user_id = $conn->lastInsertId();

                            // Create default settings for the new user
                            $stmt = $conn->prepare("INSERT INTO settings (user_id) VALUES (:user_id)");
                            $stmt->execute(['user_id' => $user_id]);

                            $conn->commit();

                            if ($role === 'barangay_staff') {
                                $login_page = 'Barangay_Staff_LogInPage.php';
                            } else {
                                $login_page = 'Department_Admin_LogIn_Page.php';
                            }
                            $success = "User registered successfully! You can now <a href='$login_page'>login</a>.";
                        }
                    } catch (PDOException $e) {
                        $conn->rollBack();
                        $error = 'Failed to register user: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - SENIORLINK</title>
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
            color: #0f1c2e;
            min-height: 100dvh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0;
            overflow: hidden;
        }

        .background-image {
            position: fixed;
            inset: 0;
            z-index: -1;
            background-image: url('../images/system_background.png'); /* Adjusted path */
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

        .signup-container {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px;
            overflow: hidden;
        }

        .signup-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 28px;
            width: 100%;
            max-width: 400px;
            max-height: calc(100dvh - 24px);
            box-shadow: 0 20px 60px rgba(8, 28, 58, 0.2);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .signup-header {
            text-align: center;
            margin-bottom: 12px;
            flex-shrink: 0;
        }

        .signup-header h1 {
            font-size: 1.5rem;
            color: #0f1c2e;
            margin-bottom: 2px;
        }

        .form-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }

        .form-group {
            flex: 1;
        }

        .form-group label {
            display: block;
            margin-bottom: 3px;
            font-weight: 500;
            font-size: 0.8rem;
            color: #0f1c2e;
        }

        .form-control {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid #dde4ed;
            border-radius: 8px;
            background: #f4f7fb;
            color: #0f1c2e !important;
            font-size: 0.85rem;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            width: 100%;
            background: #1b8a4a;
            color: white;
            flex-shrink: 0;
            transition: background 0.2s;
        }

        .btn:hover {
            background: #157a40;
        }

        .message {
            padding: 6px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 10px;
            font-size: 0.8rem;
            flex-shrink: 0;
        }

        .success {
            background: #f0fdf4;
            border: 1px solid #86efac;
            color: #166534;
        }

        .error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
        }

        .error-message-inline {
            color: #b91c1c;
            font-size: 0.7rem;
            margin-top: 2px;
            display: none; /* Hidden by default */
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 100;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            color: white;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: background 0.2s, transform 0.2s;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.18);
            transform: translateX(-3px);
            color: white;
        }

        .back-btn:focus-visible {
            outline: none;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.45);
        }
    </style>
</head>
<body>
    <!-- Back Button -->
    <a href="../index.php" class="back-btn">
        <i class="fas fa-arrow-left"></i>
        <span>Back to Home</span>
    </a>
    <!-- Background Image -->
    <div class="background-image"></div>
    <div class="signup-container">
        <div class="signup-card">
            <div class="signup-header">
                <h1>Create Account</h1>
            </div>

            <?php if ($success): ?>
                <div class="message success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="message error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="firstName">First Name</label>
                        <input type="text" id="firstName" name="firstName" class="form-control" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')" required>
                        <span id="firstNameError" class="error-message-inline"></span>
                    </div>
                    <div class="form-group">
                        <label for="lastName">Last Name</label>
                        <input type="text" id="lastName" name="lastName" class="form-control" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')" required>
                        <span id="lastNameError" class="error-message-inline"></span>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom: 10px;">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                    <span id="emailError" class="error-message-inline"></span>
                </div>
                <div class="form-group" style="margin-bottom: 10px;">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" oninput="this.value = this.value.replace(/[^a-zA-Z0-9]/g, '')" required>
                    <span id="usernameError" class="error-message-inline"></span>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                        <span id="passwordError" class="error-message-inline"></span>
                    </div>
                    <div class="form-group">
                        <label for="confirmPassword">Confirm Password</label>
                        <input type="password" id="confirmPassword" name="confirmPassword" class="form-control" required>
                        <span id="confirmPasswordError" class="error-message-inline"></span>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom: 10px;">
                    <label for="role">Role</label>
                    <select id="role" name="role" class="form-control" onchange="toggleBarangayField()" required>
                        <option value="barangay_staff">Barangay Staff</option>
                        <option value="department_admin">Department Admin</option>
                    </select>
                </div>
                <div class="form-group" id="barangayField" style="margin-bottom: 10px;">
                    <label for="barangay">Barangay</label>
                    <select id="barangay" name="barangay" class="form-control">
                        <option value="">Select barangay</option>
                        <?php foreach ($barangays_list as $b): ?>
                            <option value="<?php echo htmlspecialchars($b); ?>"><?php echo htmlspecialchars($b); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span id="barangayError" class="error-message-inline"></span>
                </div>
                 <div class="form-group" style="margin-bottom: 10px;">
                    <label for="masterPassword">Master Password</label>
                    <input type="password" id="masterPassword" name="masterPassword" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">Sign Up</button>
            </form>
        </div>
    </div>

    <script>
        function toggleBarangayField() {
            var role = document.getElementById('role').value;
            var barangayField = document.getElementById('barangayField');
            var barangaySelect = document.getElementById('barangay');
            if (role === 'barangay_staff') {
                barangayField.style.display = 'block';
                barangaySelect.setAttribute('required', 'required');
            } else {
                barangayField.style.display = 'none';
                barangaySelect.removeAttribute('required');
            }
        }
        // Initial check
        toggleBarangayField();

        const passwordField = document.getElementById('password');
        const confirmPasswordField = document.getElementById('confirmPassword');
        const passwordError = document.getElementById('passwordError');
        const confirmPasswordError = document.getElementById('confirmPasswordError');

        function validatePassword() {
            const password = passwordField.value;
            const errors = [];
            if (password.length < 8) {
                errors.push("at least 8 characters");
            }
            if (!/[a-z]/.test(password)) {
                errors.push("at least one lowercase letter");
            }
            if (!/[A-Z]/.test(password)) {
                errors.push("at least one uppercase letter");
            }
            if (!/\d/.test(password)) {
                errors.push("at least one number");
            }
            if (!/[^a-zA-Z0-9]/.test(password)) {
                errors.push("at least one special character");
            }

            if (errors.length > 0) {
                passwordError.textContent = "Password must contain " + errors.join(', ') + '.';
                passwordError.style.display = 'block';
                return false;
            } else {
                passwordError.style.display = 'none';
                return true;
            }
        }

        function validateConfirmPassword() {
            if (passwordField.value !== confirmPasswordField.value) {
                confirmPasswordError.textContent = "Passwords do not match.";
                confirmPasswordError.style.display = 'block';
                return false;
            } else {
                confirmPasswordError.style.display = 'none';
                return true;
            }
        }

        passwordField.addEventListener('input', () => {
            validatePassword();
            validateConfirmPassword(); // Re-validate confirm password whenever the original password changes
        });
        confirmPasswordField.addEventListener('input', validateConfirmPassword);

        // --- Username, Email, and Barangay Real-time Validation ---
        const usernameField = document.getElementById('username');
        const emailField = document.getElementById('email');
        const barangayField = document.getElementById('barangay');
        
        const usernameError = document.getElementById('usernameError');
        const emailError = document.getElementById('emailError');
        const barangayError = document.getElementById('barangayError');

        function debounce(func, delay = 500) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    func.apply(this, args);
                }, delay);
            };
        }

        async function checkAvailability(field, value, errorElement) {
            if (!value) {
                errorElement.style.display = 'none';
                return;
            }
            try {
                const response = await fetch(`../api/check_user.php?field=${field}&value=${encodeURIComponent(value)}`);
                const data = await response.json();

                if (field === 'barangay') {
                    if (data.count >= 2) {
                        errorElement.textContent = 'This barangay already has the maximum number of users.';
                        errorElement.style.display = 'block';
                    } else {
                        errorElement.style.display = 'none';
                    }
                } else { // username or email
                    if (data.exists) {
                        errorElement.textContent = `This ${field} is already taken.`;
                        errorElement.style.display = 'block';
                    } else {
                        errorElement.style.display = 'none';
                    }
                }
            } catch (error) {
                console.error('Validation check failed:', error);
                // Optionally show a generic error to the user
            }
        }
        
        usernameField.addEventListener('input', debounce(e => checkAvailability('username', e.target.value, usernameError)));
        emailField.addEventListener('input', debounce(e => checkAvailability('email', e.target.value, emailError)));
        barangayField.addEventListener('change', e => checkAvailability('barangay', e.target.value, barangayError));
    </script>
</body>
</html>