<?php
require_once '../includes/db_connect.php';
require_once '../includes/barangays_list.php';
require_once '../includes/password_validation.php';

$success = '';
$error = '';

function saveSignupProfilePicture(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return 'default.jpg';
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The profile photo could not be uploaded. Please try again.');
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Profile photo must be 5 MB or smaller.');
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowedTypes[$mimeType]) || @getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('Profile photo must be a valid JPG, PNG, GIF, or WebP image.');
    }

    $uploadDirectory = __DIR__ . '/../images/profile_pictures';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('The profile photo folder is unavailable.');
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($file['tmp_name'], $uploadDirectory . DIRECTORY_SEPARATOR . $fileName)) {
        throw new RuntimeException('The profile photo could not be saved. Please try again.');
    }

    return $fileName;
}

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
                    $profilePicture = 'default.jpg';
                    try {
                        // Check if username or email already exists
                        $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username OR email = :email");
                        $stmt->execute(['username' => $username, 'email' => $email]);
                        if ($stmt->fetch()) {
                            $error = 'Username or email already exists.';
                        } else {
                            try {
                                $profilePicture = saveSignupProfilePicture($_FILES['profile_picture'] ?? []);
                            } catch (RuntimeException $uploadError) {
                                $error = $uploadError->getMessage();
                            }

                            if (!empty($error)) {
                                throw new RuntimeException($error);
                            }

                            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                            $conn->beginTransaction();

                            $sql = "INSERT INTO users (first_name, last_name, email, username, password, role, barangay, profile_picture) VALUES (:first_name, :last_name, :email, :username, :password, :role, :barangay, :profile_picture)";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([
                                'first_name' => $firstName,
                                'last_name' => $lastName,
                                'email' => $email,
                                'username' => $username,
                                'password' => $hashedPassword,
                                'role' => $role,
                                'barangay' => $barangay,
                                'profile_picture' => $profilePicture
                            ]);

                            $user_id = $conn->lastInsertId();

                            // Create default settings for the new user
                            $stmt = $conn->prepare("INSERT INTO settings (user_id) VALUES (:user_id)");
                            $stmt->execute(['user_id' => $user_id]);

                            $conn->commit();

                            $login_page = '../index.php?view=' . ($role === 'barangay_staff' ? 'staff' : 'admin');
                            $success = "User registered successfully! You can now <a href='$login_page'>login</a>.";
                        }
                    } catch (Throwable $e) {
                        if ($conn->inTransaction()) {
                            $conn->rollBack();
                        }
                        if (!empty($profilePicture) && $profilePicture !== 'default.jpg') {
                            $uploadedPath = __DIR__ . '/../images/profile_pictures/' . $profilePicture;
                            if (is_file($uploadedPath)) {
                                unlink($uploadedPath);
                            }
                        }
                        if (empty($error)) {
                            $error = $e instanceof PDOException
                                ? 'Failed to register user. Please try again.'
                                : $e->getMessage();
                        }
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
            isolation: isolate;
        }

        .background-image {
            position: fixed;
            inset: 0;
            z-index: 0;
            background-image: url('../images/landing-background-new3.png');
            background-size: cover;
            background-position: left bottom;
            opacity: 0.82;
        }

        .background-image::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, rgba(6, 24, 48, 0.48), rgba(9, 39, 72, 0.32) 55%, rgba(6, 24, 48, 0.58));
        }

        .signup-container {
            position: fixed;
            inset: 0;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 0 24px 50%;
            overflow: auto;
        }

        .signup-card {
            background: rgba(255, 255, 255, 0.97);
            border: 1px solid rgba(255, 255, 255, 0.88);
            border-radius: 22px;
            padding: 26px 28px 28px;
            width: 100%;
            max-width: 560px;
            max-height: calc(100dvh - 48px);
            box-shadow: 0 24px 70px rgba(6, 24, 48, 0.3);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            transform: translateX(clamp(-80px, -5vw, -48px));
            scrollbar-width: thin;
            scrollbar-color: #b8c7d7 transparent;
        }

        .signup-header {
            text-align: left;
            margin-bottom: 18px;
            flex-shrink: 0;
        }

        .signup-header-topline {
            display: flex;
            align-items: center;
            gap: 11px;
            margin-bottom: 12px;
        }

        .signup-header-icon {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            flex: 0 0 42px;
            color: #ffffff;
            background: linear-gradient(145deg, #2375ce, #15508f);
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(21, 80, 143, 0.24);
        }

        .signup-eyebrow {
            display: block;
            color: #14763f;
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 0.09em;
            text-transform: uppercase;
        }

        .signup-header h1 {
            font-size: 1.65rem;
            color: #0f1c2e;
            margin-bottom: 5px;
            line-height: 1.2;
        }

        .signup-header p {
            max-width: 390px;
            color: #5f7185;
            font-size: 0.86rem;
            line-height: 1.5;
        }

        .required-note {
            margin-top: 9px;
            color: #6b7f94;
            font-size: 0.75rem;
        }

        .required-note span {
            color: #b42318;
            font-weight: 800;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .form-group {
            min-width: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 650;
            font-size: 0.875rem;
            color: #0f1c2e;
        }

        .form-group label::after {
            content: ' *';
            color: #b42318;
        }

        .form-group label.optional::after { content: ''; }

        .profile-upload {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 10px 12px;
            border: 1px dashed #b7c7d8;
            border-radius: 10px;
            background: #f8fafc;
        }

        .profile-preview {
            width: 54px;
            height: 54px;
            flex: 0 0 54px;
            border: 2px solid #d9e4ee;
            border-radius: 50%;
            object-fit: cover;
            background: #fff;
        }

        .profile-upload .form-control {
            min-height: auto;
            padding: 6px;
            background: #fff;
        }

        .form-control {
            width: 100%;
            min-height: 44px;
            padding: 9px 11px;
            border: 1px solid #cbd7e3;
            border-radius: 9px;
            background: #f8fafc;
            color: #0f1c2e !important;
            font-size: 0.9rem;
            transition: border-color 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
        }

        .form-control:hover {
            border-color: #9fb1c4;
        }

        .form-control:focus {
            outline: none;
            border-color: #1769aa;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(23, 105, 170, 0.2);
        }

        .btn {
            min-height: 46px;
            padding: 10px 18px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 750;
            font-size: 0.92rem;
            width: 100%;
            background: #1b8a4a;
            color: white;
            flex-shrink: 0;
            box-shadow: 0 8px 18px rgba(27, 138, 74, 0.22);
            transition: background 0.2s, transform 0.2s, box-shadow 0.2s;
        }

        .btn:hover {
            background: #157a40;
            transform: translateY(-1px);
            box-shadow: 0 10px 22px rgba(27, 138, 74, 0.28);
        }

        .btn:focus-visible {
            outline: none;
            box-shadow: 0 0 0 3px rgba(27, 138, 74, 0.3), 0 8px 18px rgba(27, 138, 74, 0.22);
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
            font-size: 0.75rem;
            line-height: 1.35;
            margin-top: 4px;
            display: none; /* Hidden by default */
        }

        .form-hint {
            display: block;
            margin-top: 5px;
            color: #6b7f94;
            font-size: 0.75rem;
            line-height: 1.35;
        }

        form {
            min-height: 0;
            display: flex;
            flex: 1;
            flex-direction: column;
            gap: 14px;
        }

        .form-fields {
            min-height: 0;
            display: grid;
            gap: 13px;
            padding-right: 7px;
            overflow-x: hidden;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #b8c7d7 transparent;
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

        @media (max-width: 760px) {
            body { overflow: auto; }

            .signup-container {
                position: relative;
                min-height: 100dvh;
                justify-content: center;
                padding: 78px 16px 20px;
                overflow: visible;
            }

            .signup-card {
                max-width: 520px;
                max-height: none;
                padding: 24px 20px 26px;
                overflow: visible;
                transform: none;
            }

            form,
            .form-fields {
                overflow: visible;
            }

            .background-image { background-position: 22% bottom; }
        }

        @media (max-width: 520px) {
            .form-row { grid-template-columns: 1fr; }
            .signup-header h1 { font-size: 1.45rem; }
            .back-btn { top: 14px; left: 14px; }
        }

        @media (prefers-reduced-motion: reduce) {
            .btn,
            .back-btn,
            .form-control { transition: none; }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/seniorlink-ui.css?v=16">
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
                <div class="signup-header-topline">
                    <span class="signup-header-icon" aria-hidden="true"><i class="fas fa-user-plus"></i></span>
                    <span class="signup-eyebrow">Authorized registration</span>
                </div>
                <h1>Create staff account</h1>
                <p>Enter the staff member's details and assign the correct access role.</p>
                <p class="required-note"><span>*</span> All fields are required.</p>
            </div>

            <?php if ($success): ?>
                <div class="message success" role="status"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="message error" role="alert"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="post" action="" enctype="multipart/form-data">
                <div class="form-fields">
                <div class="form-row">
                    <div class="form-group">
                        <label for="firstName">First Name</label>
                        <input type="text" id="firstName" name="firstName" class="form-control" autocomplete="given-name" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')" required>
                        <span id="firstNameError" class="error-message-inline" aria-live="polite"></span>
                    </div>
                    <div class="form-group">
                        <label for="lastName">Last Name</label>
                        <input type="text" id="lastName" name="lastName" class="form-control" autocomplete="family-name" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')" required>
                        <span id="lastNameError" class="error-message-inline" aria-live="polite"></span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" autocomplete="email" required>
                    <span id="emailError" class="error-message-inline" aria-live="polite"></span>
                </div>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" autocomplete="username" oninput="this.value = this.value.replace(/[^a-zA-Z0-9]/g, '')" required>
                    <span id="usernameError" class="error-message-inline" aria-live="polite"></span>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" autocomplete="new-password" aria-describedby="passwordHint passwordError" required>
                        <span id="passwordHint" class="form-hint">Use 8+ characters with uppercase, lowercase, number, and symbol.</span>
                        <span id="passwordError" class="error-message-inline" aria-live="polite"></span>
                    </div>
                    <div class="form-group">
                        <label for="confirmPassword">Confirm Password</label>
                        <input type="password" id="confirmPassword" name="confirmPassword" class="form-control" autocomplete="new-password" required>
                        <span id="confirmPasswordError" class="error-message-inline" aria-live="polite"></span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" class="form-control" onchange="toggleBarangayField()" required>
                        <option value="barangay_staff">SHDO</option>
                        <option value="department_admin">Department Admin</option>
                    </select>
                </div>
                <div class="form-group" id="barangayField">
                    <label for="barangay">Barangay</label>
                    <select id="barangay" name="barangay" class="form-control">
                        <option value="">Select barangay</option>
                        <?php foreach ($barangays_list as $b): ?>
                            <option value="<?php echo htmlspecialchars($b); ?>"><?php echo htmlspecialchars($b); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span id="barangayError" class="error-message-inline" aria-live="polite"></span>
                </div>
                <div class="form-group">
                    <label for="profile_picture" class="optional">Profile Photo <span class="form-hint" style="display:inline;">(optional)</span></label>
                    <div class="profile-upload">
                        <img id="profilePicturePreview" class="profile-preview" src="../images/profile_pictures/default.jpg" alt="Profile photo preview">
                        <div style="min-width:0;flex:1;">
                            <input type="file" id="profile_picture" name="profile_picture" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp" aria-describedby="profilePictureHint">
                            <span id="profilePictureHint" class="form-hint">JPG, PNG, GIF, or WebP up to 5 MB.</span>
                        </div>
                    </div>
                </div>
                 <div class="form-group">
                    <label for="masterPassword">Master Password</label>
                    <input type="password" id="masterPassword" name="masterPassword" class="form-control" autocomplete="current-password" aria-describedby="masterPasswordHint" required>
                    <span id="masterPasswordHint" class="form-hint">Use the authorization password provided by the system administrator.</span>
                </div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus" aria-hidden="true"></i> Create account</button>
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

        const profilePictureInput = document.getElementById('profile_picture');
        const profilePicturePreview = document.getElementById('profilePicturePreview');
        profilePictureInput.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) {
                profilePicturePreview.src = '../images/profile_pictures/default.jpg';
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                this.value = '';
                profilePicturePreview.src = '../images/profile_pictures/default.jpg';
                window.alert('Profile photo must be 5 MB or smaller.');
                return;
            }
            const previewUrl = URL.createObjectURL(file);
            profilePicturePreview.src = previewUrl;
            profilePicturePreview.onload = () => URL.revokeObjectURL(previewUrl);
        });
    </script>
</body>
</html>
