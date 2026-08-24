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

// Display one-time feedback after actions that redirect back to this page (for example, delete user).
if (!empty($_SESSION['message'])) {
    $message = (string) $_SESSION['message'];
    unset($_SESSION['message']);
}
if (!empty($_SESSION['error'])) {
    $error = (string) $_SESSION['error'];
    unset($_SESSION['error']);
}

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
        $stmt = $conn->prepare("SELECT id, first_name, last_name, email, role, barangay, profile_picture FROM users WHERE (is_archived = 0 OR is_archived IS NULL)");
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("SELECT id, first_name, last_name, email, role, barangay, profile_picture FROM users WHERE barangay = :barangay AND (is_archived = 0 OR is_archived IS NULL)");
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
    <title>SENIORLINK — User Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/department-sidebar.css?v=4">
    <style>
        /* Existing styles remain unchanged */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        :root { --primary: #0f172a; --secondary: #1e3a5f; --accent: #2563eb; --success: #10b981; --warning: #f59e0b; --light: #f8fafc; --dark: #020617; --gray: #94a3b8; }
        body { background-color: #f1f5f9; color: #0f172a; line-height: 1.6; }
        .container { display: flex; }
        .main-content { flex-grow: 1; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 15px; border-bottom: 1px solid #e0e0e0; }
        .header h1 {
            font-family: 'Inter', sans-serif;
            color: var(--primary);
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.05;
            margin: 0;
        }
        .header h1 span { color: var(--accent); }
        .welcome-message,
        .greeting {
            font-family: 'Inter', sans-serif;
            font-size: 0.98rem;
            font-weight: 500;
            color: #6b7280;
            margin-bottom: 6px;
            line-height: 1.3;
        }
        .welcome-message strong,
        .greeting strong {
            color: #2563eb;
            font-weight: 700;
        }
        .card { background: white; border-radius: 10px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); padding: 20px; margin-bottom: 20px; }
        .card h3 { font-size: 18px; margin-bottom: 15px; color: white; background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%); display: flex; align-items: center; gap: 10px; padding: 14px 18px; border-radius: 10px; }
        .card h3 i { margin-right: 10px; color: inherit; }
        .btn { display: inline-block; background: var(--secondary); color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: 500; transition: background 0.3s; border: none; cursor: pointer; font-size: 14px; }
        .btn:hover { background: #153860; }
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
        .profile-picture-preview {
            display: block;
            width: 104px;
            height: 104px;
            aspect-ratio: 1;
            border-radius: 50%;
            object-fit: cover;
            margin-top: 12px;
            padding: 3px;
            background: #f8fafc;
            border: 2px solid #cbd5e1;
            box-shadow: 0 3px 10px rgba(15, 23, 42, 0.12);
        }
        .password-input-container { position: relative; width: 100%; }
        .password-input-container .toggle-password { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--gray); }
        
        .error-message-inline {
            color: var(--accent);
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        /* Edit user modal */
        #editUserModal { display: none; position: fixed; z-index: 1000; inset: 0; width: 100%; height: 100%; padding: 24px; overflow: hidden; background: rgba(2, 6, 23, 0.62); justify-content: center; align-items: center; }
        #editUserModal .modal-content { display: flex; flex-direction: column; width: min(760px, 100%); max-width: 760px; max-height: calc(100dvh - 48px); margin: 0; padding: 0; overflow: hidden; background: #fff; border: 1px solid rgba(148, 163, 184, 0.3); border-radius: 18px; box-shadow: 0 28px 70px rgba(2, 6, 23, 0.3); }
        #editUserModal .modal-header { display: flex; flex: 0 0 auto; justify-content: space-between; align-items: center; min-height: 76px; margin: 0; padding: 18px 24px; border-bottom: 1px solid #e2e8f0; background: #fff; }
        #editUserModal .modal-header h2 { display: flex; align-items: center; gap: 12px; margin: 0; color: var(--primary); font-size: 1.35rem; line-height: 1.25; }
        #editUserModal .modal-header h2 i { display: grid; place-items: center; width: 40px; height: 40px; margin: 0; color: #1d4ed8; background: #eff6ff; border-radius: 10px; }
        #editUserModal .modal-close { display: grid; place-items: center; width: 40px; min-width: 40px; height: 40px; padding: 0; color: #64748b; background: transparent; border: 0; border-radius: 10px; cursor: pointer; font-size: 1.25rem; transition: background-color .2s ease, color .2s ease; }
        #editUserModal .modal-close:hover, #editUserModal .modal-close:focus-visible { color: #0f172a; background: #f1f5f9; outline: none; }
        #editUserModal .modal-body { min-height: 0; padding: 0; overflow: hidden; }
        #editUserForm { display: flex; flex-direction: column; max-height: calc(100dvh - 125px); }
        #editUserModal .modal-form-fields { padding: 22px 24px 8px; overflow-y: auto; overscroll-behavior: contain; }
        #editUserModal .form-row { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        #editUserModal .form-group { margin-bottom: 16px; }
        #editUserModal .form-group label { margin-bottom: 7px; font-weight: 650; color: #334155; }
        #editUserModal .form-group input:not([type="file"]), #editUserModal .form-group select { min-height: 46px; padding: 10px 12px; color: #0f172a; background: #fff; border: 1px solid #cbd5e1; border-radius: 9px; transition: border-color .2s ease, box-shadow .2s ease; }
        #editUserModal .form-group input:focus, #editUserModal .form-group select:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, .12); outline: none; }
        #editUserModal .profile-upload { display: grid; grid-template-columns: 72px minmax(0, 1fr); align-items: center; gap: 16px; padding: 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; }
        #editUserModal .profile-upload .profile-picture-preview { width: 72px; height: 72px; margin: 0; padding: 2px; border-width: 1px; box-shadow: 0 2px 8px rgba(15, 23, 42, .1); }
        #editUserModal .profile-upload input[type="file"] { width: 100%; background: #fff; }
        #editUserModal .field-help { display: block; margin-top: 6px; color: #64748b; font-size: 12px; line-height: 1.4; }
        #editAlert { display: none; margin: 18px 24px 0; }
        #editUserModal .modal-actions { display: flex; flex: 0 0 auto; justify-content: flex-end; gap: 10px; margin: 0; padding: 16px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; }
        #editUserModal .modal-actions .btn { display: inline-flex; align-items: center; justify-content: center; min-width: 122px; min-height: 44px; padding: 10px 18px; border-radius: 9px; font-size: 14px; font-weight: 700; }
        #editUserModal .modal-actions .btn-secondary { color: #334155; background: #fff; border: 1px solid #cbd5e1; }
        #editUserModal .modal-actions .btn-secondary:hover { background: #f1f5f9; }

        @media (max-width: 640px) {
            #editUserModal { padding: 0 !important; align-items: stretch !important; }
            #editUserModal .modal-content { height: 100dvh; max-height: 100dvh !important; overflow: hidden !important; border-radius: 0 !important; }
            #editUserModal .modal-header { min-height: 68px; padding: 14px 16px !important; }
            #editUserModal .modal-body { max-height: none !important; padding: 0 !important; overflow: hidden !important; }
            #editUserForm { max-height: calc(100dvh - 70px); }
            #editUserModal .modal-form-fields { padding: 18px 16px 6px; }
            #editUserModal .form-row { grid-template-columns: minmax(0, 1fr); gap: 0; }
            #editUserModal .profile-upload { grid-template-columns: 60px minmax(0, 1fr); gap: 12px; }
            #editUserModal .profile-upload .profile-picture-preview { width: 60px; height: 60px; }
            #editUserModal .modal-actions { padding: 14px 16px; }
            #editUserModal .modal-actions .btn { flex: 1 1 0; min-width: 0; }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/seniorlink-ui.css?v=15">
    <script src="../assets/js/modal-hci.js?v=2" defer></script>
    <link rel="stylesheet" href="../assets/css/table-pagination.css?v=1">
    <script src="../assets/js/table-pagination.js?v=1" defer></script>
    <link rel="stylesheet" href="../assets/css/system-header.css?v=1">
    <link rel="stylesheet" href="../assets/css/system-sidebar.css?v=3">
</head>
<body>
   <div class="container">
        <?php include '../partials/department_sidebar.php'; ?>

        <div class="main-content">
            <div class="header">
                <div class="header-content">
                    <div class="welcome-message" data-first-name="<?php echo htmlspecialchars($_SESSION['first_name'] ?? ''); ?>" data-last-name="<?php echo htmlspecialchars($_SESSION['last_name'] ?? ''); ?>"></div>
                    <h1>User <span>Management</span></h1>
                </div>
                <div class="header-actions">
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php
                                $profilePic = isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : 'default.jpg';
                                $profilePicPath = '../images/profile_pictures/' . $profilePic;
                                if (!file_exists($profilePicPath) || is_dir($profilePicPath)) {
                                    $profilePicPath = '../images/profile_pictures/default.jpg';
                                }
                            ?>
                            <img src="<?php echo $profilePicPath; ?>" alt="Profile Picture" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                        </div>
                        <div class="user-details">
                            <h2><?php echo htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')); ?></h2>
                            <p><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $_SESSION['role'] ?? ''))) . ' · Pasig City'; ?></p>
                        </div>
                    </div>
                </div>
            </div>
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
                            <img id="profile_picture_preview" class="profile-picture-preview" alt="Profile picture preview" hidden>
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
                        <tbody id="usersTableBody" data-paginate="10" data-pagination-label="User account pages">
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <?php
                                            $userProfilePic = !empty($user['profile_picture']) ? $user['profile_picture'] : 'default.jpg';
                                            $userProfilePicPath = '../images/profile_pictures/' . $userProfilePic;
                                            if ($userProfilePic === 'default.jpg' || !file_exists($userProfilePicPath) || is_dir($userProfilePicPath)) {
                                                $userProfilePicPath = '../images/LOGO.jpg';
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
                                        <form action="delete_user.php" method="POST" style="display:inline;" onsubmit="return confirmCarelinkSubmit(this, 'Are you sure you want to archive this user account? You can restore it anytime from the Archive page.');">
                                             <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                             <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                             <button type="submit" name="archiveUser" class="btn btn-small btn-warning" style="background:#f59e0b;color:#fff;"><i class="fas fa-archive"></i> Archive</button>
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

    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="editUserModalTitle">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="editUserModalTitle"><i class="fas fa-user-edit" aria-hidden="true"></i> Edit User</h2>
                <button type="button" class="modal-close" aria-label="Close edit user dialog"><i class="fas fa-times" aria-hidden="true"></i></button>
            </div>
            <div class="modal-body">
                <div id="editAlert" class="error"></div>
                <form id="editUserForm" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="id" id="editUserId">
                    <div class="modal-form-fields">
                        <div class="form-row">
                            <div class="form-group"><label for="editFirstName">First Name</label><input type="text" id="editFirstName" name="firstName" autocomplete="given-name" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')" required></div>
                            <div class="form-group"><label for="editLastName">Last Name</label><input type="text" id="editLastName" name="lastName" autocomplete="family-name" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')" required></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label for="editEmail">Email</label><input type="email" id="editEmail" name="email" autocomplete="email" required></div>
                            <div class="form-group"><label for="editUsername">Username</label><input type="text" id="editUsername" name="username" autocomplete="username" oninput="this.value = this.value.replace(/[^a-zA-Z0-9]/g, '')" required></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label for="editRole">Role</label><select id="editRole" name="role" required><option value="department_admin">Administrator</option><option value="barangay_staff">Barangay Staff</option></select></div>
                            <div class="form-group" id="editBarangayFormGroup"><label for="editBarangay">Barangay</label><select id="editBarangay" name="barangay"><option value="">Select barangay...</option><?php foreach ($barangays_list as $b): ?><option value="<?php echo htmlspecialchars($b); ?>"><?php echo htmlspecialchars($b); ?></option><?php endforeach; ?></select></div>
                        </div>
                        <div class="form-group">
                            <label for="editProfilePicture">Profile Picture <span class="field-help" style="display:inline;">(optional)</span></label>
                            <div class="profile-upload">
                                <img id="editProfilePicturePreview" class="profile-picture-preview" src="../images/LOGO.jpg" alt="Current profile picture">
                                <div>
                                    <input type="file" id="editProfilePicture" name="profile_picture" accept="image/png,image/jpeg,image/gif">
                                    <span class="field-help">JPG, PNG, or GIF. Choose a file to replace the current photo.</span>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label for="editNewPassword">New Password</label><input type="password" id="editNewPassword" name="newPassword" autocomplete="new-password"><span class="field-help">Leave blank to keep the current password.</span></div>
                            <div class="form-group"><label for="editConfirmPassword">Confirm New Password</label><input type="password" id="editConfirmPassword" name="confirmPassword" autocomplete="new-password"></div>
                        </div>
                    </div>
                    <div class="modal-actions"><button type="button" class="btn btn-secondary modal-cancel">Cancel</button><button type="submit" name="updateUser" class="btn btn-success"><i class="fas fa-check" aria-hidden="true"></i>&nbsp; Update User</button></div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/sidebar-toggle.js?v=3"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        function initializeWelcomeMessage() {
            const welcomeMessage = document.querySelector('.welcome-message');
            if (!welcomeMessage) return;
            const firstName = welcomeMessage.dataset.firstName || '';
            const lastName = welcomeMessage.dataset.lastName || '';
            const hour = new Date().getHours();
            let greeting = (hour < 12) ? "Good morning" : (hour < 18) ? "Good afternoon" : "Good evening";
            welcomeMessage.innerHTML = `${greeting}, <strong>${firstName} ${lastName}</strong>!`;
        }
        initializeWelcomeMessage();

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
                        reader.onload = (e) => {
                            profilePicturePreview.src = e.target.result;
                            profilePicturePreview.hidden = false;
                        };
                        reader.readAsDataURL(file);
                    } else {
                        profilePicturePreview.removeAttribute('src');
                        profilePicturePreview.hidden = true;
                    }
                });
                profilePictureInput.closest('form').addEventListener('reset', () => {
                    window.setTimeout(() => {
                        profilePicturePreview.removeAttribute('src');
                        profilePicturePreview.hidden = true;
                    });
                });
            }
        }

        // --- Edit User Modal Logic ---
        const editUserModal = document.getElementById('editUserModal');
        const editUserForm = document.getElementById('editUserForm');
        const closeButtons = editUserModal.querySelectorAll('.modal-close, .modal-cancel');
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
                                document.getElementById('editProfilePicturePreview').src = user.profile_picture_path || '../images/LOGO.jpg';
                                document.getElementById('editNewPassword').value = '';
                                document.getElementById('editConfirmPassword').value = '';
                                toggleBarangayField(editRoleSelect, editBarangayGroup);
                                editUserModal._returnFocus = editButton;
                                editUserModal.style.display = 'flex';
                                editUserModal.setAttribute('aria-hidden', 'false');
                                document.body.style.overflow = 'hidden';
                                document.getElementById('editFirstName').focus();
                            } else {
                                window.showCarelinkResult('Error: ' + data.message, false);
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching user details:', error);
                            window.showCarelinkResult('An error occurred while fetching user details.', false);
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
                        window.showCarelinkResult(data.message, !!data.success);
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

        function closeEditUserModal() {
            if (!editUserModal) return;
            editUserModal.style.display = 'none';
            editUserModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            editUserForm.reset();
            editUserModal._returnFocus?.focus();
        }

        closeButtons.forEach(btn => btn.addEventListener('click', closeEditUserModal));

        window.addEventListener('click', function(event) {
            if (event.target == editUserModal) {
                closeEditUserModal();
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && editUserModal.style.display === 'flex') closeEditUserModal();
        });

        const editProfilePictureInput = document.getElementById('editProfilePicture');
        const editProfilePicturePreview = document.getElementById('editProfilePicturePreview');
        editProfilePictureInput.addEventListener('change', function() {
            const file = this.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = event => { editProfilePicturePreview.src = event.target.result; };
            reader.readAsDataURL(file);
        });
    });
    </script>
<script src="../assets/js/seniorlink-feedback.js?v=1"></script>
<?php if ($message !== ''): ?>
<script>window.addEventListener('DOMContentLoaded', () => window.showCarelinkResult(<?= json_encode($message) ?>, true));</script>
<?php elseif ($error !== ''): ?>
<script>window.addEventListener('DOMContentLoaded', () => window.showCarelinkResult(<?= json_encode($error) ?>, false));</script>
<?php endif; ?>
</body>
</html>
