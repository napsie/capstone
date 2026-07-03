<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/password_validation.php'; // Include the password validation function

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Fetch user data and settings
try {
    $stmt = $conn->prepare("SELECT u.*, s.theme, s.language, s.notifications FROM users u LEFT JOIN settings s ON u.id = s.user_id WHERE u.id = :id");
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch notifications (assuming 'notifications' table still exists and is used here)
    // Note: The 'notifications' table still exists as per previous analysis.
    $stmt = $conn->prepare("SELECT * FROM notifications ORDER BY created_at DESC");
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
    $user = [];
    $notifications = [];
}

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['updateProfile'])) {
    $firstName = $_POST['firstName'];
    $lastName = $_POST['lastName'];
    $email = $_POST['email'];
    // $phone = $_POST['phone']; // Column 'phone' does not exist in 'users' table.
    // $displayName = $firstName . ' ' . $lastName; // Column 'display_name' does not exist in 'users' table.

    try {
        $stmt = $conn->prepare("UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email WHERE id = :id");
        $stmt->execute([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'id' => $user_id
        ]);
        $message = "Profile updated successfully!";
        // Re-fetch user data to update session/display
        $stmt = $conn->prepare("SELECT u.*, s.theme, s.language, s.notifications FROM users u LEFT JOIN settings s ON u.id = s.user_id WHERE u.id = :id");
        $stmt->execute(['id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error updating profile: " . $e->getMessage();
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['updatePassword'])) {
    $currentPassword = $_POST['currentPassword'];
    $newPassword = $_POST['newPassword'];
    $confirmPassword = $_POST['confirmPassword'];

    if ($newPassword !== $confirmPassword) {
        $error = "New passwords do not match.";
    } else {
        $validationResult = validatePassword($newPassword);
        if (!$validationResult['valid']) {
            $error = $validationResult['message'];
        } else if (password_verify($currentPassword, $user['password'])) {
            try {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = :password WHERE id = :id");
                $stmt->execute(['password' => $hashedPassword, 'id' => $user_id]);
                $message = "Password updated successfully!";
            } catch (PDOException $e) {
                $error = "Error updating password: " . $e->getMessage();
            }
        } else {
            $error = "Incorrect current password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CARELINK — Settings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/barangay-sidebar.css">
    <link rel="stylesheet" href="../assets/css/main-dark-mode.css">
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

            /* background & card */
            --bg: #f5f7fa;
            --card-bg: #ffffff;
            --text: #222;
        }
        body {
            background-color: var(--bg);
            color: var(--text);
            line-height: 1.6;
            height: 100vh;
            overflow: hidden;
            transition: background-color 240ms ease, color 240ms ease;
        }

        .container {
            display: flex;
            height: 100vh;
        }

        /* Sidebar styles are handled by barangay-sidebar.css */

        /* Main Content */
        .main-content {
            flex: 1;
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

        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 15px;
            background: var(--card-bg);
            border-radius: 25px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            color: var(--text);
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
            font-size: 18px;
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .user-details h2 {
            font-size: 14px;
            margin-bottom: 2px;
            color: var(--primary);
        }

        .user-details p {
            color: var(--gray);
            font-size: 12px;
        }

        /* Settings Cards */
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .settings-card {
            background: var(--card-bg);
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.06);
            padding: 20px;
            border: 1px solid rgba(16,24,40,0.04);
            color: var(--text);
        }

        .settings-card h3 {
            font-size: 18px;
            margin-bottom: 15px;
            color: var(--primary);
            display: flex;
            align-items: center;
        }

        .settings-card h3 i {
            margin-right: 10px;
            color: var(--secondary);
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
            transition: border-color 0.3s;
            background: rgba(255,255,255,0.02);
            color: var(--text);
            border: 1px solid rgba(255,255,255,0.06);
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--secondary);
        }

        .form-group input:disabled {
            background-color: #f5f5f5;
            color: #999;
        }

        .dark-mode .form-group input:disabled {
            background-color: #2c3e50;
            color: #95a5a6;
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

        .btn:hover {
            background: #2980b9;
        }

        .btn-secondary {
            background: var(--gray);
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .btn-small {
            padding: 8px 16px;
            font-size: 12px;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .profile-section {
            background: var(--card-bg);
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
            position: relative; /* allow absolute positioning for the toggle inside the card */
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .profile-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
            overflow: hidden; /* Ensure image fits within avatar circle */
        }
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-info h2 {
            color: var(--primary);
            margin-bottom: 5px;
        }

        .profile-info p {
            color: var(--gray);
            font-size: 14px;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                padding: 10px;
                height: auto;
            }
            
            .nav-links {
                display: flex;
                overflow-x: auto;
            }
            
            .nav-links li {
                white-space: nowrap;
            }
            
            .main-content {
                height: auto;
                overflow-y: visible;
            }
            
            .settings-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .header-actions {
                align-self: flex-end;
            }
            
            .settings-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Custom styles for the new layout */
        .settings-card.full-width-card {
            grid-column: span 2;
        }

        @media (max-width: 768px) {
            .settings-card.full-width-card {
                grid-column: span 1;
            }
        }

        /* Theme toggle button (small round switch with icon) */
        .theme-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 20px;
            background: var(--card-bg);
            border: 1px solid rgba(16,24,40,0.06);
            color: var(--primary);
            cursor: pointer;
            transition: background 180ms ease, transform 120ms ease;
        }

        .theme-toggle i {
            font-size: 14px;
        }

        .theme-toggle:hover {
            transform: translateY(-1px);
        }

        /* When placed inside profile card: position top-right */
        .profile-section .theme-toggle {
            position: absolute;
            top: 16px;
            right: 16px;
            box-shadow: 0 6px 18px rgba(16,24,40,0.06);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <?php include '../partials/barangay_sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <div class="header-content">
                    <div class="welcome-message">Welcome back, <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>!</div>
                    <h1>User Settings</h1>
                </div>
                <div class="header-actions">

                     <div class="user-info">
                         <div class="user-avatar">
                                                <?php
                                                    $profilePic = isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : 'default.jpg';
                                                    $profilePicPath = '../images/profile_pictures/' . $profilePic;
                                                    if (!file_exists($profilePicPath) || is_dir($profilePicPath)) {
                                                        $profilePicPath = '../images/profile_pictures/default.jpg'; // Fallback to default if file doesn't exist
                                                    }
                                                ?>
                                                <img src="<?php echo $profilePicPath; ?>" alt="Profile Picture">
                                            </div>                        <div class="user-details">
                            <h2><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                            <p><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $user['role']))) . ' • ' . htmlspecialchars($_SESSION['barangay']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="message" style="background-color: var(--success); color: white; padding: 10px; border-radius: 5px; margin-bottom: 15px;"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error" style="background-color: var(--accent); color: white; padding: 10px; border-radius: 5px; margin-bottom: 15px;"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Profile Section -->
            <div class="profile-section">
                <!-- Theme toggle moved inside profile card (top-right) -->
                <button id="themeToggle" class="theme-toggle" title="Toggle theme" aria-pressed="false">
                    <i id="themeIcon" class="fas fa-moon"></i>
                </button>
                 <div class="profile-header">
                     <div class="profile-avatar">
                            <?php
                                $profilePicDisplay = isset($user['profile_picture']) ? $user['profile_picture'] : 'default.jpg';
                                $profilePicPathDisplay = '../images/profile_pictures/' . $profilePicDisplay;
                                if (!file_exists($profilePicPathDisplay) || is_dir($profilePicPathDisplay)) {
                                    $profilePicPathDisplay = '../images/profile_pictures/default.jpg'; // Fallback to default if file doesn't exist
                                }
                            ?>
                            <img src="<?php echo $profilePicPathDisplay; ?>" alt="Profile Picture">
                        </div>
                     <div class="profile-info">
                         <h2><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                         <p><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $user['role']))); ?><?php if ($_SESSION['role'] !== 'department_admin'): ?> • <?php echo htmlspecialchars($user['barangay']); ?><?php endif; ?></p>
                     </div>
                 </div>
                 <p style="color: var(--gray); font-size: 14px;">Manage your account preferences and appearance.</p>
             </div>

            <!-- Settings Grid -->
            <div class="settings-grid">
                <!-- Profile Settings (left, wider) -->
                <div class="settings-card">
                    <h3><i class="fas fa-user"></i> Profile Settings</h3>
                    <form method="post" action="">
                        <div class="form-row">
                            <div class="form-group col">
                                <label for="username">Username</label>
                                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" disabled>
                            </div>
                            <div class="form-group col">
                                <label for="role">Role</label>
                                <input type="text" id="role" name="role" value="<?php echo htmlspecialchars(ucwords(str_replace('_',' ',$user['role']))); ?>" disabled>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col">
                                <label for="firstName">First Name</label>
                                <input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')" disabled>
                            </div>
                            <div class="form-group col">
                                <label for="lastName">Last Name</label>
                                <input type="text" id="lastName" name="lastName" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')" disabled>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled>
                        </div>

                        <div class="form-group">
                            <label for="barangay">Barangay</label>
                            <input type="text" id="barangay" name="barangay" value="<?php echo htmlspecialchars($user['barangay'] ?? $_SESSION['barangay'] ?? ''); ?>" disabled>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone (optional)</label>
                            <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" oninput="this.value = this.value.replace(/[^0-9]/g, '')" disabled>
                        </div>

                        <div class="actions">
                            <button type="button" class="btn btn-small" id="editProfileBtn">Edit Profile</button>
                            <button type="submit" name="updateProfile" class="btn btn-success btn-small" id="saveBtn" style="display: none;">Save Changes</button>
                            <button type="button" class="btn btn-secondary btn-small" id="cancelBtn" style="display: none;">Cancel</button>
                        </div>
                    </form>
                </div>

                <!-- Security Settings (right, narrower) -->
                <div class="settings-card">
                    <h3><i class="fas fa-shield-alt"></i> Security Settings</h3>
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="currentPassword">Current Password</label>
                            <input type="password" id="currentPassword" name="currentPassword" placeholder="Enter current password" disabled>
                        </div>
                        <div class="form-group">
                            <label for="newPassword">New Password</label>
                            <input type="password" id="newPassword" name="newPassword" placeholder="Enter new password" disabled>
                        </div>
                        <div class="form-group">
                            <label for="confirmPassword">Confirm New Password</label>
                            <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm new password" disabled>
                        </div>
                        <div class="actions">
                            <button type="button" class="btn btn-small" id="editPasswordBtn">Change Password</button>
                            <button type="submit" name="updatePassword" class="btn btn-success btn-small" id="savePasswordBtn" style="display: none;">Update Password</button>
                            <button type="button" class="btn btn-secondary btn-small" id="cancelPasswordBtn" style="display: none;">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
    </div>

    <script src="../assets/js/sidebar-toggle.js"></script>
    <script src="../assets/js/dark-mode.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const welcomeMessage = document.querySelector('.welcome-message');
            const hour = new Date().getHours();
            let greeting;
            
            if (hour < 12) {
                greeting = "Good morning";
            } else if (hour < 18) {
                greeting = "Good afternoon";
            } else {
                greeting = "Good evening";
            }
            
            welcomeMessage.innerHTML = `${greeting}, <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>!`;

            // Profile Settings
            const editProfileBtn = document.getElementById('editProfileBtn');
            const saveBtn = document.getElementById('saveBtn');
            const cancelBtn = document.getElementById('cancelBtn');
            const profileInputs = ['firstName', 'lastName', 'email', 'phone'];

            editProfileBtn.addEventListener('click', () => {
                profileInputs.forEach(id => document.getElementById(id).disabled = false);
                editProfileBtn.style.display = 'none';
                saveBtn.style.display = 'inline-block';
                cancelBtn.style.display = 'inline-block';
            });

            cancelBtn.addEventListener('click', () => {
                profileInputs.forEach(id => document.getElementById(id).disabled = true);
                editProfileBtn.style.display = 'inline-block';
                saveBtn.style.display = 'none';
                cancelBtn.style.display = 'none';
                // Reset to original values if needed
            });

            // Security Settings
            const editPasswordBtn = document.getElementById('editPasswordBtn');
            const savePasswordBtn = document.getElementById('savePasswordBtn');
            const cancelPasswordBtn = document.getElementById('cancelPasswordBtn');
            const passwordInputs = ['currentPassword', 'newPassword', 'confirmPassword'];

            editPasswordBtn.addEventListener('click', () => {
                passwordInputs.forEach(id => document.getElementById(id).disabled = false);
                editProfileBtn.style.display = 'none';
                savePasswordBtn.style.display = 'inline-block';
                cancelBtn.style.display = 'inline-block';
            });

            cancelBtn.addEventListener('click', () => {
                passwordInputs.forEach(id => {
                    document.getElementById(id).disabled = true;
                    document.getElementById(id).value = '';
                });
                editProfileBtn.style.display = 'inline-block';
                savePasswordBtn.style.display = 'none';
                cancelBtn.style.display = 'none';
            });
        });
    </script>
</body>
</html>