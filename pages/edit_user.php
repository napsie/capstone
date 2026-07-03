<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
error_log("DEBUG: edit_user.php: Script started.");
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/password_validation.php'; // Include the password validation function

// Check if the user is logged in and has the correct role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'department_admin') {
    error_log("DEBUG: edit_user.php: User not logged in or not department_admin. Redirecting.");
    header('Location: ../index.php');
    exit;
}
error_log("DEBUG: edit_user.php: User logged in as department_admin. User ID: " . $_SESSION['user_id']);

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    error_log("DEBUG: edit_user.php: New CSRF token generated.");
} else {
    error_log("DEBUG: edit_user.php: Existing CSRF token: " . $_SESSION['csrf_token']);
}

require_once '../includes/barangays_list.php';
error_log("DEBUG: edit_user.php: barangays_list.php included.");

$user = null;
$message = '';
$error = '';
$currentProfilePicPath = '../images/profile_pictures/default.jpg'; // Initialize with default

// Handle GET request to fetch user data for editing
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['id'])) {
    error_log("DEBUG: edit_user.php: GET request for user edit detected.");
    $id = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);
    error_log("DEBUG: edit_user.php: User ID from GET: $id");

    $response = ['success' => false, 'message' => '']; // Initialize response array for modal

    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            error_log("DEBUG: edit_user.php: User found for editing. Username: " . $user['username']);
            // Set the current profile picture path
            $currentProfilePic = isset($user['profile_picture']) ? $user['profile_picture'] : 'default.jpg';
            $currentProfilePicPath = '../images/profile_pictures/' . $currentProfilePic;
            if (!file_exists($currentProfilePicPath) || is_dir($currentProfilePicPath)) {
                $currentProfilePicPath = '../images/profile_pictures/default.jpg';
            }
            $user['profile_picture_path'] = $currentProfilePicPath; // Add path for client-side use

            if (isset($_GET['modal']) && $_GET['modal'] === 'true') {
                $response['success'] = true;
                $response['user'] = $user;
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            }
        } else {
            $error = 'User not found.';
            error_log("ERROR: edit_user.php: User not found with ID: $id for GET request.");
            if (isset($_GET['modal']) && $_GET['modal'] === 'true') {
                $response['message'] = 'User not found.'; // Changed from $response['error']
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            }
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        error_log("ERROR: edit_user.php: PDOException fetching user for GET request: " . $e->getMessage());
        if (isset($_GET['modal']) && $_GET['modal'] === 'true') {
                $response['message'] = "Database error: " . $e->getMessage(); // Changed from $response['error']
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            }
    }
}

// Handle form submission for updating user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['updateUser'])) {
    error_log("DEBUG: edit_user.php: POST request for user update detected.");
    error_log("DEBUG: POST data: " . print_r($_POST, true));
    error_log("DEBUG: FILES data: " . print_r($_FILES, true));
    $response = ['success' => false, 'message' => '']; // Initialize response array with empty message

    // If it's a modal request, set header early
    if (isset($_GET['modal']) && $_GET['modal'] === 'true') {
        header('Content-Type: application/json');
    }

    try {
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $response['message'] = 'CSRF token validation failed.'; // Changed from $response['error']
            error_log("ERROR: edit_user.php: CSRF token validation failed for POST request.");
        } else {
            error_log("DEBUG: edit_user.php: CSRF token validated successfully for POST request.");
            $id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);
            error_log("DEBUG: edit_user.php: User ID from POST: $id");

            // Fetch user data for the update operation
            try {
                error_log("DEBUG: edit_user.php: Attempting to fetch user for update with ID: $id");
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    $response['message'] = 'User not found for update.'; // Changed from $response['error']
                    error_log("ERROR: edit_user.php: User not found for update with ID: $id.");
                } else {
                    error_log("DEBUG: edit_user.php: User found for update. Username: " . $user['username']);
                }
            } catch (PDOException $e) {
                $response['message'] = "Error fetching user for update: " . $e->getMessage(); // Changed from $response['error']
                error_log("ERROR: edit_user.php: PDOException fetching user for update: " . $e->getMessage());
            }

            error_log("DEBUG: edit_user.php: Before processing form fields and validation."); // Added log
            if (empty($response['message'])) { // Check $response['message'] for errors
                error_log("DEBUG: edit_user.php: Inside empty(\$response['message']) block."); // Added log
                $firstName = $_POST['firstName'];
                $lastName = $_POST['lastName'];
                $email = $_POST['email'];
                $username = $_POST['username'];
                $role = $_POST['role'];
                $barangay = isset($_POST['barangay']) ? $_POST['barangay'] : null;
                $newPassword = $_POST['newPassword'];
                $confirmPassword = $_POST['confirmPassword'];
                $profilePicture = ($user && isset($user['profile_picture'])) ? $user['profile_picture'] : 'default.jpg'; // Safely get existing profile picture

                if (empty($firstName) || empty($lastName) || empty($email) || empty($username) || empty($role)) {
                    $response['message'] = 'Please fill in all required fields.'; // Changed from $response['error']
                } else {
                    // Validate barangay based on the selected role for the user being edited
                    if ($role === 'barangay_staff') {
                        if (empty($barangay)) {
                            $response['message'] = 'Barangay is required for Barangay Staff.'; // Changed from $response['error']
                        } elseif (!in_array($barangay, $barangays_list)) {
                            $response['message'] = 'Invalid barangay selected.'; // Changed from $response['error']
                        }
                    } else { // role is department_admin
                        $barangay = null; // Ensure barangay is null for department admins
                    }
                    // Check for duplicate username (excluding current user)
                    if (empty($response['message'])) { // Check $response['message'] for errors
                        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = :username AND id != :id");
                        $stmt->execute(['username' => $username, 'id' => $id]);
                        if ($stmt->fetchColumn() > 0) {
                            $response['message'] = 'Username already exists. Please choose a different one.'; // Changed from $response['error']
                        }
                    }

                    if (empty($response['message'])) { // Check $response['message'] for errors
                        // Check for duplicate email (excluding current user)
                        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND id != :id");
                        $stmt->execute(['email' => $email, 'id' => $id]);
                        if ($stmt->fetchColumn() > 0) {
                            $response['message'] = 'Email already exists. Please use a different one.'; // Changed from $response['error']
                        }
                    }

                    if (empty($response['message'])) { // Check $response['message'] for errors
                        // Password validation
                        if (!empty($newPassword)) {
                            if ($newPassword !== $confirmPassword) {
                                $response['message'] = 'New password and confirm password do not match.'; // Changed from $response['error']
                            } else {
                                $validationResult = validatePassword($newPassword);
                                if (!$validationResult['valid']) {
                                    $response['message'] = $validationResult['message'];
                                }
                            }
                        }
                    }

                    // Handle profile picture upload only if no other errors yet
                    if (empty($response['message']) && isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) { // Check $response['message'] for errors
                        $fileTmpPath = $_FILES['profile_picture']['tmp_name'];
                        $fileName = $_FILES['profile_picture']['name'];
                        $fileSize = $_FILES['profile_picture']['size'];
                        $fileType = $_FILES['profile_picture']['type'];
                        $fileNameCmps = explode(".", $fileName);
                        $fileExtension = strtolower(end($fileNameCmps));

                        $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg');
                        if (in_array($fileExtension, $allowedfileExtensions)) {
                            $uploadFileDir = '../images/profile_pictures/';
                            if (!is_dir($uploadFileDir)) {
                                mkdir($uploadFileDir, 0777, true);
                            }
                            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                            $dest_path = $uploadFileDir . $newFileName;

                            if(move_uploaded_file($fileTmpPath, $dest_path)) {
                                $profilePicture = $newFileName;
                            } else {
                                $response['message'] = "There was an error moving the uploaded profile picture file."; // Changed from $response['error']
                            }
                        } else {
                            $response['message'] = "Invalid profile picture file type. Only JPG, JPEG, PNG, GIF are allowed."; // Changed from $response['error']
                        }
                    } elseif (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $response['message'] = "Profile picture upload failed with error code: " . $_FILES['profile_picture']['error']; // Changed from $response['error']
                    }

                    // Proceed with database update only if no errors
                    if (empty($response['message'])) { // Check $response['message'] for errors
                        error_log("DEBUG: edit_user.php: Attempting database update."); // Added log
                        try {
                            $sql = "UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email, username = :username, role = :role, barangay = :barangay, profile_picture = :profile_picture";
                            $params = [
                                'first_name' => $firstName,
                                'last_name' => $lastName,
                                'email' => $email,
                                'username' => $username,
                                'role' => $role,
                                'barangay' => $barangay,
                                'profile_picture' => $profilePicture,
                                'id' => $id
                            ];

                            if (!empty($newPassword)) {
                                $sql .= ", password = :password";
                                $params['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
                            }

                            $sql .= " WHERE id = :id";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute($params);

                            $response['success'] = true;
                            $response['message'] = 'User updated successfully!';
                            error_log("DEBUG: edit_user.php: User updated successfully. Re-fetching user data."); // Added log
                            // Re-fetch user data to display updated info immediately
                            $stmt = $conn->prepare('SELECT * FROM users WHERE id = :id');
                            $stmt->execute(['id' => $id]);
                            $user = $stmt->fetch(PDO::FETCH_ASSOC);
                            // Regenerate CSRF token after successful submission
                            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                            // If the logged-in user updated their own profile, update session variables
                            if ($id == $_SESSION['user_id']) {
                                $_SESSION['first_name'] = $user['first_name'];
                                $_SESSION['last_name'] = $user['last_name'];
                                $_SESSION['email'] = $user['email'];
                                $_SESSION['username'] = $user['username'];
                                $_SESSION['role'] = $user['role'];
                                $_SESSION['barangay'] = $user['barangay'];
                                $_SESSION['profile_picture'] = $user['profile_picture'];
                            }

                        } catch (PDOException $e) {
                            $response['message'] = "Error updating user: " . $e->getMessage(); // Changed from $response['error']
                            error_log("ERROR: edit_user.php: PDOException updating user: " . $e->getMessage()); // Added log
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        $response['message'] = "Server error: " . $e->getMessage(); // Changed from $response['error']
        error_log("FATAL ERROR: edit_user.php: Uncaught exception during POST request: " . $e->getMessage());
    }

    // Always return JSON for modal updates if it was a modal request
    if (isset($_GET['modal']) && $_GET['modal'] === 'true') {
        error_log("DEBUG: edit_user.php: Returning JSON response for modal."); // Added log
        echo json_encode($response);
        exit;
    } else {
        // For non-modal requests, set $message or $error based on $response
        if ($response['success']) {
            $message = $response['message'];
        } else {
            $error = $response['message']; // Changed from $error = $response['error'];
            // If the update fails, we need to re-fetch the user data to display the form again.
            if (isset($_POST['id'])) {
                $id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);
                try {
                    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($user) {
                        // Set the current profile picture path
                        $currentProfilePic = isset($user['profile_picture']) ? $user['profile_picture'] : 'default.jpg';
                        $currentProfilePicPath = '../images/profile_pictures/' . $currentProfilePic;
                        if (!file_exists($currentProfilePicPath) || is_dir($currentProfilePicPath)) {
                            $currentProfilePicPath = '../images/profile_pictures/default.jpg';
                        }
                    }
                } catch (PDOException $e) {
                    // Append to the existing error message
                    $error .= " (And there was a database error fetching user data for the form: " . $e->getMessage() . ")";
                }
            }
        }
    }
}
                        ?>
                        <?php if (!isset($_GET['modal']) || $_GET['modal'] !== 'true'): ?>
                        <!DOCTYPE html><html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CARELINK — Edit User</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/department-sidebar.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --accent: #e74c3c;
            --success: #2ecc71;
            --warning: #f39c12;
            --light: #ecf0f1;
            --dark: #34495e;
            --gray: #95a5a6;
        }

        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
            height: 100vh;
            overflow: auto;
        }

        .main-content {
            padding: 20px;
            overflow-y: auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        .header-content {
            display: flex;
            flex-direction: column;
        }

        .welcome-message {
            font-size: 1.2rem;
            color: var(--gray);
            margin-bottom: 5px;
        }

        .header h1 {
            color: var(--primary);
            font-size: 1.8rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .card h3 {
            font-size: 18px;
            margin-bottom: 15px;
            color: var(--primary);
            display: flex;
            align-items: center;
        }

        .card h3 i {
            margin-right: 10px;
            color: var(--secondary);
        }

        .btn {
            display: inline-block;
            background: var(--secondary);
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.3s;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-success {
            background: var(--success);
        }
        
        .btn-danger {
            background: var(--accent);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .table th {
            background: var(--primary);
            color: white;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            color: var(--primary);
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .message, .error {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .message {
            background: var(--success);
            color: white;
        }
        
        .error {
            background: var(--accent);
            color: white;
        }

        .password-input-container {
            position: relative;
            width: 100%;
        }

        .password-input-container input[type="password"],
        .password-input-container input[type="text"] {
            padding-right: 40px; /* Space for the toggle button */
        }

        .password-input-container .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--gray);
        }

        .password-input-container .toggle-password:hover {
            color: var(--primary);
        }

        .profile-picture-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-top: 10px;
            border: 2px solid #ddd;
        }
    </style>
</head>
<body>
   <div class="container">
        <?php include '../partials/department_sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <div class="header-content">
                    <div class="welcome-message">Welcome back, <strong><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></strong>!</div>
                    <h1>Edit User</h1>
                </div>
                <div class="user-info">
                    <div class="user-avatar">
                        <?php
                            $profilePic = isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : 'default.jpg';
                            $profilePicPath = '../images/profile_pictures/' . $profilePic;
                            if (!file_exists($profilePicPath) || is_dir($profilePicPath)) {
                                $profilePicPath = '../images/profile_pictures/default.jpg'; // Fallback to default if file doesn't exist
                            }
                        ?>
                        <img src="<?php echo $profilePicPath; ?>" alt="Profile Picture" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                    </div>
                    <div class="user-details">
                        <h2><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></h2>
                        <p><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $_SESSION['role']))); ?></p>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="message"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="card">
                <?php if ($user): ?>
                <form id="editUserForm" method="post" action="user_management.php" enctype="multipart/form-data">
                <h3><i class="fas fa-user-edit"></i> Edit User: <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($user['id']); ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="firstName">First Name</label>
                            <input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="lastName">Last Name</label>
                            <input type="text" id="lastName" name="lastName" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="role">Role</label>
                            <select id="role" name="role" required>
                                <option value="">Select role</option>
                                <option value="department_admin" <?php echo ($user['role'] == 'department_admin') ? 'selected' : ''; ?>>Administrator</option>
                                <option value="barangay_staff" <?php echo ($user['role'] == 'barangay_staff') ? 'selected' : ''; ?>>Barangay Staff</option>
                            </select>
                        </div>
                        <div class="form-group" id="barangayFormGroup" style="display: none;">
                            <label for="barangay">Barangay</label>
                            <select id="barangay" name="barangay">
                                <option value="">Select barangay</option>
                                <?php foreach ($barangays_list as $b): ?>
                                    <option value="<?php echo htmlspecialchars($b); ?>" <?php echo ($user['barangay'] == $b) ? 'selected' : ''; ?>><?php echo htmlspecialchars($b); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="profile_picture">Profile Picture (optional)</label>
                        <input type="file" id="profile_picture" name="profile_picture" accept="image/*">
                        <?php
                            $currentProfilePic = isset($user['profile_picture']) ? $user['profile_picture'] : 'default.jpg';
                            $currentProfilePicPath = '../images/profile_pictures/' . $currentProfilePic;
                            if (!file_exists($currentProfilePicPath) || is_dir($currentProfilePicPath)) {
                                $currentProfilePicPath = '../images/profile_pictures/default.jpg'; // Fallback to default if file doesn't exist
                            }
                        ?>
                        <img id="profile_picture_preview" class="profile-picture-preview" src="<?php echo $currentProfilePicPath; ?>" alt="Profile Picture Preview">
                    </div>
                    <div class="form-group">
                        <label for="newPassword">New Password (leave blank to keep current)</label>
                        <div class="password-input-container">
                            <input type="password" id="newPassword" name="newPassword" placeholder="Enter new password">
                            <span class="toggle-password" onclick="togglePasswordVisibility('newPassword')"><i class="fas fa-eye"></i></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirmPassword">Confirm New Password</label>
                        <div class="password-input-container">
                            <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm new password">
                            <span class="toggle-password" onclick="togglePasswordVisibility('confirmPassword')"><i class="fas fa-eye"></i></span>
                        </div>
                    </div>
                    <div class="actions">
                        <button type="submit" name="updateUser" class="btn btn-success">Update User</button>
                        <a href="user_management.php" class="btn">Cancel</a>
                    </div>
                </form>
                <?php else: ?>
                    <p>User not found or invalid ID.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Function to toggle password visibility
        window.togglePasswordVisibility = function(fieldId) {
            const passwordInput = document.getElementById(fieldId);
            const icon = passwordInput.nextElementSibling.querySelector('i');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        };

        document.addEventListener('DOMContentLoaded', function() {
            const welcomeMessage = document.querySelector('.welcome-message');
            const hour = new Date().getHours();
            let greeting;
            if (hour < 12) {
                greeting = "Good morning";
            } else if (hour < 18) {
                greeting = "Good afternoon";
            }
            else {
                greeting = "Good evening";
            }
            welcomeMessage.innerHTML = `${greeting}, <strong><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></strong>!`;

            // Profile picture preview for edit user
            const profilePictureInput = document.getElementById('profile_picture');
            const profilePicturePreview = document.getElementById('profile_picture_preview');

            profilePictureInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        profilePicturePreview.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                } else {
                    // If no file is selected, revert to the current profile picture or default
                    profilePicturePreview.src = '<?php echo $currentProfilePicPath; ?>';
                }
            });

            // Dynamic display of barangay field based on role
            const roleSelect = document.getElementById('role');
            const barangayFormGroup = document.getElementById('barangayFormGroup');
            const barangaySelect = document.getElementById('barangay');

            function toggleBarangayField() {
                if (roleSelect.value === 'barangay_staff') {
                    barangayFormGroup.style.display = 'block';
                    barangaySelect.setAttribute('required', 'required');
                } else {
                    barangayFormGroup.style.display = 'none';
                    barangaySelect.removeAttribute('required');
                    barangaySelect.value = ''; // Clear selected barangay if not staff
                }
            }

            // Initial check on page load
            toggleBarangayField();

            // Add event listener for role change
            roleSelect.addEventListener('change', toggleBarangayField);
        });
    </script>
    <script src="../assets/js/sidebar-toggle.js"></script>
</body>
</html>
<?php else: // If modal=true, only output the form content ?>
            <?php if ($user): ?>
                <form id="editUserForm" method="post" action="edit_user.php?modal=true" enctype="multipart/form-data">
                    <h3><i class="fas fa-user-edit"></i> Edit User: <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($user['id']); ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="firstName">First Name</label>
                            <input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="lastName">Last Name</label>
                            <input type="text" id="lastName" name="lastName" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="role">Role</label>
                            <select id="role" name="role" required>
                                <option value="">Select role</option>
                                <option value="department_admin" <?php echo ($user['role'] == 'department_admin') ? 'selected' : ''; ?>>Administrator</option>
                                <option value="barangay_staff" <?php echo ($user['role'] == 'barangay_staff') ? 'selected' : ''; ?>>Barangay Staff</option>
                            </select>
                        </div>
                        <div class="form-group" id="barangayFormGroup" style="display: none;">
                            <label for="barangay">Barangay</label>
                            <select id="barangay" name="barangay">
                                <option value="">Select barangay</option>
                                <?php foreach ($barangays_list as $b): ?>
                                    <option value="<?php echo htmlspecialchars($b); ?>" <?php echo ($user['barangay'] == $b) ? 'selected' : ''; ?>><?php echo htmlspecialchars($b); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="profile_picture">Profile Picture (optional)</label>
                        <input type="file" id="profile_picture" name="profile_picture" accept="image/*">
                        <?php
                            $currentProfilePic = isset($user['profile_picture']) ? $user['profile_picture'] : 'default.jpg';
                            $currentProfilePicPath = '../images/profile_pictures/' . $currentProfilePic;
                            if (!file_exists($currentProfilePicPath) || is_dir($currentProfilePicPath)) {
                                $currentProfilePicPath = '../images/profile_pictures/default.jpg'; // Fallback to default if file doesn't exist
                            }
                        ?>
                        <img id="profile_picture_preview" class="profile-picture-preview" src="<?php echo $currentProfilePicPath; ?>" alt="Profile Picture Preview">
                    </div>
                    <div class="form-group">
                        <label for="newPassword">New Password (leave blank to keep current)</label>
                        <div class="password-input-container">
                            <input type="password" id="newPassword" name="newPassword" placeholder="Enter new password">
                            <span class="toggle-password" onclick="togglePasswordVisibility('newPassword')"><i class="fas fa-eye"></i></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirmPassword">Confirm New Password</label>
                        <div class="password-input-container">
                            <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm new password">
                            <span class="toggle-password" onclick="togglePasswordVisibility('confirmPassword')"><i class="fas fa-eye"></i></span>
                        </div>
                    </div>
                    <div class="actions">
                        <button type="submit" name="updateUser" class="btn btn-success">Update User</button>
                        <a href="user_management.php" class="btn">Cancel</a>
                    </div>
                </form>
                <?php else: ?>
                    <!-- If user not found, output an empty string or a minimal error for client-side handling -->
                    <!-- The client-side JavaScript should handle displaying an error message if this is empty -->
                <?php endif; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Profile picture preview for edit user
            const profilePictureInput = document.getElementById('profile_picture');
            const profilePicturePreview = document.getElementById('profile_picture_preview');

            profilePictureInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        profilePicturePreview.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                } else {
                    // If no file is selected, revert to the current profile picture or default
                    profilePicturePreview.src = '<?php echo $currentProfilePicPath; ?>';
                }
            });

            // Dynamic display of barangay field based on role
            const roleSelect = document.getElementById('role');
            const barangayFormGroup = document.getElementById('barangayFormGroup');
            const barangaySelect = document.getElementById('barangay');

            function toggleBarangayField() {
                if (roleSelect.value === 'barangay_staff') {
                    barangayFormGroup.style.display = 'block';
                    barangaySelect.setAttribute('required', 'required');
                } else {
                    barangayFormGroup.style.display = 'none';
                    barangaySelect.removeAttribute('required');
                    barangaySelect.value = ''; // Clear selected barangay if not staff
                }
            }

            // Initial check on page load
            toggleBarangayField();

            // Add event listener for role change
            roleSelect.addEventListener('change', toggleBarangayField);
        });
    </script>
<?php endif; ?>