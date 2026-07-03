<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/password_validation.php'; // Include the password validation function

// Check if the user is logged in and has the correct role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'department_admin') {
    // Redirect to login page or show an error
    header('Location: ../index.php');
    exit;
}

$message = '';
$error = '';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once '../includes/barangays_list.php';

// Handle Add User Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['addUser'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'CSRF token validation failed.';
    } else {
        $masterPassword = $_POST['masterPassword'];
        $correctMasterPassword = 'CarelinkMaster2025!';

        if ($masterPassword !== $correctMasterPassword) {
            $error = 'Invalid Master Password. User creation failed.';
        } else {
            $firstName = $_POST['firstName'];
            $lastName = $_POST['lastName'];
            $email = $_POST['email'];
            $username = $_POST['username'];
            $role = $_POST['role'];
            $barangay = isset($_POST['barangay']) ? $_POST['barangay'] : null;
            $password = $_POST['password'];
            $profilePicture = 'default.jpg'; // Default profile picture

            if (empty($firstName) || empty($lastName) || empty($email) || empty($username) || empty($role) || empty($password)) {
                $error = 'Please fill in all required fields.';
            } else {
                // Validate role
                $allowedRoles = ['department_admin', 'barangay_staff'];
                if (!in_array($role, $allowedRoles)) {
                    $error = 'Invalid role selected.';
                }

                // Validate barangay based on the selected role
                if ($role === 'barangay_staff') {
                    if (empty($barangay)) {
                        $error = 'Barangay is required for Barangay Staff.';
                    } elseif (!in_array($barangay, $barangays_list)) {
                        $error = 'Invalid barangay selected.';
                    }
                } else { // role is department_admin
                    $barangay = null; // Ensure barangay is null for department admins
                }
                
                // Validate password using the new function
                if (empty($error)) { // Only proceed if no other errors
                    $validationResult = validatePassword($password);
                    if (!$validationResult['valid']) {
                        $error = $validationResult['message'];
                    }
                }

                if (empty($error)) {
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
                        // Handle profile picture upload
                        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
                            $fileTmpPath = $_FILES['profile_picture']['tmp_name'];
                            $fileName = $_FILES['profile_picture']['name'];
                            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

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
                                    $error = "There was an error moving the uploaded profile picture file.";
                                }
                            } else {
                                $error = "Invalid profile picture file type. Only JPG, JPEG, PNG, GIF are allowed.";
                            }
                        }

                        if (empty($error)) {
                            // Hash the password
                            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                            // Check for duplicate username
                            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
                            $stmt->execute(['username' => $username]);
                            if ($stmt->fetchColumn() > 0) {
                                $error = 'Username already exists. Please choose a different one.';
                            } else {
                                // Check for duplicate email
                                $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
                                $stmt->execute(['email' => $email]);
                                if ($stmt->fetchColumn() > 0) {
                                    $error = 'Email already exists. Please use a different one.';
                                } else {
                                    try {
                                        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, username, role, barangay, password, profile_picture) VALUES (:first_name, :last_name, :email, :username, :role, :barangay, :password, :profile_picture)");
                                        $stmt->execute([
                                            'first_name' => $firstName,
                                            'last_name' => $lastName,
                                            'email' => $email,
                                            'username' => $username,
                                            'role' => $role,
                                            'barangay' => $barangay,
                                            'password' => $hashedPassword,
                                            'profile_picture' => $profilePicture
                                        ]);
                                        $message = 'User added successfully!';
                                        // Regenerate CSRF token after successful submission
                                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                                    } catch (PDOException $e) {
                                        $error = "Error: " . $e->getMessage();
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

// Fetch users from the database
try {
    $barangayFilter = isset($_GET['barangay']) ? $_GET['barangay'] : 'all';
    
    if ($barangayFilter === 'all') {
        $stmt = $conn->prepare("SELECT id, first_name, last_name, email, role, barangay, profile_picture FROM users");
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("SELECT id, first_name, last_name, email, role, barangay, profile_picture FROM users WHERE barangay = :barangay");
        $stmt->execute(['barangay' => $barangayFilter]);
    }
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching users: " . $e->getMessage();
    $users = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CARELINK — User Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/department-sidebar.css">
    <style>
        /* Existing styles remain unchanged */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        :root { --primary: #2c3e50; --secondary: #3498db; --accent: #e74c3c; --success: #2ecc71; --warning: #f39c12; --light: #ecf0f1; --dark: #34495e; --gray: #95a5a6; }
        body { background-color: #f5f7fa; color: #333; line-height: 1.6; }
        .container { display: flex; }
        .main-content { flex-grow: 1; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 15px; border-bottom: 1px solid #e0e0e0; }
        .header h1 { color: var(--primary); font-size: 1.8rem; }
        .card { background: white; border-radius: 10px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); padding: 20px; margin-bottom: 20px; }
        .card h3 { font-size: 18px; margin-bottom: 15px; color: var(--primary); display: flex; align-items: center; }
        .card h3 i { margin-right: 10px; color: var(--secondary); }
        .btn { display: inline-block; background: var(--secondary); color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: 500; transition: background 0.3s; border: none; cursor: pointer; font-size: 14px; }
        .btn-success { background: var(--success); }
        .btn-danger { background: var(--accent); }
        .btn-warning { background: var(--warning); }
        .table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #e0e0e0; }
        .table th { background: var(--primary); color: white; font-weight: 600; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 14px; color: var(--primary); margin-bottom: 5px; font-weight: 500; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; box-sizing: border-box; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; }
        .actions { display: flex; gap: 10px; margin-top: 20px; }
        .message, .error { padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center; color: white; }
        .message { background: var(--success); }
        .error { background: var(--accent); }
        .profile-picture-preview { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; margin-top: 10px; border: 2px solid #ddd; }
        .password-input-container { position: relative; width: 100%; }
        .password-input-container .toggle-password { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--gray); }
        
        .error-message-inline {
            color: var(--accent);
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center; }
        .modal-content { background-color: #fefefe; margin: auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 700px; border-radius: 10px; box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2), 0 6px 20px 0 rgba(0,0,0,0.19); position: relative; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e0e0e0; padding-bottom: 10px; margin-bottom: 15px; }
        .modal-header h2 { margin: 0; color: var(--primary); font-size: 1.5rem; }
        .modal-header h2 i { margin-right: 10px; color: var(--secondary); }
        .close-button { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-button:hover, .close-button:focus { color: #000; }
        .modal-body { padding: 10px 0; }
        #editAlert { display: none; margin-bottom: 15px; }
    </style>
</head>
<body>
   <div class="container">
        <?php include '../partials/department_sidebar.php'; ?>

        <div class="main-content">
            <div class="header"><h1>User Management</h1></div>
            <?php if ($message): ?><div class="message"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
            
            <!-- Add User Card -->
            <div class="card">
                <h3><i class="fas fa-user-plus"></i> Add New User</h3>
                <form id="addUserForm" method="post" action="" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="firstName">First Name</label>
                            <input type="text" id="firstName" name="firstName" placeholder="Enter first name" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')" required>
                        </div>
                        <div class="form-group">
                            <label for="lastName">Last Name</label>
                            <input type="text" id="lastName" name="lastName" placeholder="Enter last name" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" placeholder="Enter email address" required>
                            <span id="emailError" class="error-message-inline"></span>
                        </div>
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" placeholder="Enter username" oninput="this.value = this.value.replace(/[^a-zA-Z0-9]/g, '')" required>
                            <span id="usernameError" class="error-message-inline"></span>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="password-input-container">
                                <input type="password" id="password" name="password" placeholder="Create a password" required>
                                <span class="toggle-password"><i class="fas fa-eye"></i></span>
                            </div>
                            <span id="passwordError" class="error-message-inline"></span>
                        </div>
                        <div class="form-group">
                            <label for="confirmPassword">Confirm Password</label>
                            <div class="password-input-container">
                                <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm the password" required>
                                <span class="toggle-password"><i class="fas fa-eye"></i></span>
                            </div>
                            <span id="confirmPasswordError" class="error-message-inline"></span>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="role">Role</label>
                            <select id="role" name="role" required>
                                <option value="">Select role</option>
                                <option value="department_admin">Administrator</option>
                                <option value="barangay_staff">Barangay Staff</option>
                            </select>
                        </div>
                        <div class="form-group" id="barangay-form-group">
                            <label for="barangay">Barangay</label>
                            <select id="barangay" name="barangay">
                                <option value="">Select barangay</option>
                                <?php foreach ($barangays_list as $b): ?>
                                    <option value="<?php echo htmlspecialchars($b); ?>"><?php echo htmlspecialchars($b); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span id="barangayError" class="error-message-inline"></span>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="profile_picture">Profile Picture (optional)</label>
                            <input type="file" id="profile_picture" name="profile_picture" accept="image/*">
                            <img id="profile_picture_preview" class="profile-picture-preview" src="../images/profile_pictures/default.jpg" alt="Profile Picture Preview">
                        </div>
                        <div class="form-group">
                            <label for="masterPassword">Master Password</label>
                            <input type="password" id="masterPassword" name="masterPassword" placeholder="Enter master password" required>
                        </div>
                    </div>
                    <div class="actions">
                        <button type="submit" name="addUser" class="btn btn-success">Add User</button>
                        <button type="reset" class="btn">Reset</button>
                    </div>
                </form>
            </div>

            <!-- Users List Card -->
            <div class="card">
                <h3><i class="fas fa-users"></i> Users List</h3>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Barangay</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <?php
                                            $userProfilePic = !empty($user['profile_picture']) ? $user['profile_picture'] : 'default.jpg';
                                            $userProfilePicPath = '../images/profile_pictures/' . $userProfilePic;
                                            if (!file_exists($userProfilePicPath) || is_dir($userProfilePicPath)) {
                                                $userProfilePicPath = '../images/profile_pictures/default.jpg';
                                            }
                                        ?>
                                        <img src="<?php echo $userProfilePicPath; ?>" alt="Profile Picture" style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover;">
                                    </td>
                                    <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars(str_replace('_', ' ', ucwords($user['role']))); ?></td>
                                    <td><?php echo htmlspecialchars($user['barangay']); ?></td>
                                    <td>
                                        <button class="btn btn-small btn-warning edit-user-btn" data-id="<?php echo $user['id']; ?>">Edit</button>
                                        <form action="delete_user.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure?');">
                                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <button type="submit" name="deleteUser" class="btn btn-small btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal (structure remains the same) -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-edit"></i> Edit User</h2>
                <span class="close-button">&times;</span>
            </div>
            <div class="modal-body">
                <div id="editAlert" class="error"></div>
                <form id="editUserForm" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="id" id="editUserId">
                    <!-- Form fields for edit modal -->
                    <div class="form-row">
                        <div class="form-group"><label for="editFirstName">First Name</label><input type="text" id="editFirstName" name="firstName" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')" required></div>
                        <div class="form-group"><label for="editLastName">Last Name</label><input type="text" id="editLastName" name="lastName" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')" required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label for="editEmail">Email</label><input type="email" id="editEmail" name="email" required></div>
                        <div class="form-group"><label for="editUsername">Username</label><input type="text" id="editUsername" name="username" oninput="this.value = this.value.replace(/[^a-zA-Z0-9]/g, '')" required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label for="editRole">Role</label><select id="editRole" name="role" required><option value="department_admin">Administrator</option><option value="barangay_staff">Barangay Staff</option></select></div>
                        <div class="form-group" id="editBarangayFormGroup"><label for="editBarangay">Barangay</label><select id="editBarangay" name="barangay"><option value="">Select barangay...</option><?php foreach ($barangays_list as $b): ?><option value="<?php echo htmlspecialchars($b); ?>"><?php echo htmlspecialchars($b); ?></option><?php endforeach; ?></select></div>
                    </div>
                    <div class="form-group"><label for="editProfilePicture">Profile Picture (optional)</label><input type="file" id="editProfilePicture" name="profile_picture" accept="image/*"><img id="editProfilePicturePreview" class="profile-picture-preview" src="../images/profile_pictures/default.jpg" alt="Profile Picture Preview"></div>
                    <div class="form-row">
                        <div class="form-group"><label for="editNewPassword">New Password (leave blank to keep)</label><input type="password" id="editNewPassword" name="newPassword"></div>
                        <div class="form-group"><label for="editConfirmPassword">Confirm New Password</label><input type="password" id="editConfirmPassword" name="confirmPassword"></div>
                    </div>
                    <div class="actions"><button type="submit" name="updateUser" class="btn btn-success">Update User</button><button type="button" class="btn close-button">Cancel</button></div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/sidebar-toggle.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Global Password Toggle ---
        window.togglePasswordVisibility = function(fieldId) {
            const passwordInput = document.getElementById(fieldId);
            if (passwordInput) {
                const icon = passwordInput.closest('.password-input-container').querySelector('i');
                if (icon) {
                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        passwordInput.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                }
            }
        };

        // --- Add User Form Logic ---
        const addUserForm = document.getElementById('addUserForm');
        if (addUserForm) {
            const roleSelect = document.getElementById('role');
            const barangayGroup = document.getElementById('barangay-form-group');

            function toggleAddBarangayField() {
                barangayGroup.style.display = (roleSelect.value === 'barangay_staff') ? 'block' : 'none';
            }
            roleSelect.addEventListener('change', toggleAddBarangayField);
            toggleAddBarangayField(); // Initial check

            const passwordField = document.getElementById('password');
            const confirmPasswordField = document.getElementById('confirmPassword');
            const passwordError = document.getElementById('passwordError');
            const confirmPasswordError = document.getElementById('confirmPasswordError');

            const usernameField = document.getElementById('username');
            const emailField = document.getElementById('email');
            const barangayField = document.getElementById('barangay');
            const usernameError = document.getElementById('usernameError');
            const emailError = document.getElementById('emailError');
            const barangayError = document.getElementById('barangayError');
            
            function validatePassword() {
                const password = passwordField.value;
                const errors = [];
                if (password.length < 8) errors.push("at least 8 characters");
                if (!/[a-z]/.test(password)) errors.push("at least one lowercase letter");
                if (!/[A-Z]/.test(password)) errors.push("at least one uppercase letter");
                if (!/\d/.test(password)) errors.push("at least one number");
                if (!/[^a-zA-Z0-9]/.test(password)) errors.push("at least one special character");

                if (errors.length > 0) {
                    passwordError.textContent = "Password must contain " + errors.join(', ') + '.';
                    passwordError.style.display = 'block';
                    return false;
                }
                passwordError.style.display = 'none';
                return true;
            }

            function validateConfirmPassword() {
                if (passwordField.value !== confirmPasswordField.value) {
                    confirmPasswordError.textContent = "Passwords do not match.";
                    confirmPasswordError.style.display = 'block';
                    return false;
                }
                confirmPasswordError.style.display = 'none';
                return true;
            }

            passwordField.addEventListener('input', () => {
                validatePassword();
                validateConfirmPassword();
            });
            confirmPasswordField.addEventListener('input', validateConfirmPassword);
            
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
                }
            }

            usernameField.addEventListener('input', debounce(e => checkAvailability('username', e.target.value, usernameError)));
            emailField.addEventListener('input', debounce(e => checkAvailability('email', e.target.value, emailError)));
            barangayField.addEventListener('change', e => {
                if (roleSelect.value === 'barangay_staff') {
                    checkAvailability('barangay', e.target.value, barangayError)
                }
            });

            const togglePasswords = document.querySelectorAll('.toggle-password');
            togglePasswords.forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const passwordInput = this.previousElementSibling;
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    this.querySelector('i').classList.toggle('fa-eye-slash');
                });
            });

            const profilePictureInput = document.getElementById('profile_picture');
            const profilePicturePreview = document.getElementById('profile_picture_preview');
            if (profilePictureInput && profilePicturePreview) {
                profilePictureInput.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = (e) => { profilePicturePreview.src = e.target.result; };
                        reader.readAsDataURL(file);
                    } else {
                        profilePicturePreview.src = '../images/profile_pictures/default.jpg';
                    }
                });
            }
        }

        // --- Edit User Modal Logic ---
        const editUserModal = document.getElementById('editUserModal');
        const editUserForm = document.getElementById('editUserForm');
        const closeButtons = document.querySelectorAll('.close-button');
        const usersTableBody = document.getElementById('usersTableBody');
        const editAlert = document.getElementById('editAlert');

        function toggleBarangayField(roleSelect, barangayGroup) {
            barangayGroup.style.display = (roleSelect.value === 'barangay_staff') ? 'block' : 'none';
        }

        const editRoleSelect = document.getElementById('editRole');
        const editBarangayGroup = document.getElementById('editBarangayFormGroup');
        editRoleSelect.addEventListener('change', () => toggleBarangayField(editRoleSelect, editBarangayGroup));

        if (usersTableBody) {
            usersTableBody.addEventListener('click', function(event) {
                const editButton = event.target.closest('.edit-user-btn');
                if (editButton) {
                    const userId = editButton.dataset.id;
                    editAlert.style.display = 'none';
                    
                    fetch(`edit_user.php?id=${userId}&modal=true`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const user = data.user;
                                document.getElementById('editUserId').value = user.id;
                                document.getElementById('editFirstName').value = user.first_name;
                                document.getElementById('editLastName').value = user.last_name;
                                document.getElementById('editEmail').value = user.email;
                                document.getElementById('editUsername').value = user.username;
                                document.getElementById('editRole').value = user.role;
                                document.getElementById('editBarangay').value = user.barangay || '';
                                document.getElementById('editProfilePicturePreview').src = user.profile_picture_path || '../images/profile_pictures/default.jpg';
                                document.getElementById('editNewPassword').value = '';
                                document.getElementById('editConfirmPassword').value = '';
                                toggleBarangayField(editRoleSelect, editBarangayGroup);
                                editUserModal.style.display = 'flex';
                            } else {
                                alert('Error: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching user details:', error);
                            alert('An error occurred while fetching user details.');
                        });
                }
            });
        }

        if (editUserForm) {
            editUserForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(editUserForm);
                
                formData.append('updateUser', '1'); // Explicitly add updateUser parameter
                
                fetch('edit_user.php?modal=true', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        // If response is not OK (e.g., 404, 500), try to read it as text
                        return response.text().then(text => { throw new Error(text) });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        editAlert.textContent = data.message || 'An unknown error occurred during update.';
                        editAlert.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error updating user:', error);
                    editAlert.textContent = 'An unexpected error occurred. Details: ' + error.message;
                    editAlert.style.display = 'block';
                });
            });
        }

        closeButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                if(editUserModal) editUserModal.style.display = 'none';
            });
        });

        window.addEventListener('click', function(event) {
            if (event.target == editUserModal) {
                editUserModal.style.display = 'none';
            }
        });
    });
    </script>
</body>
</html>