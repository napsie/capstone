<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/password_validation.php';

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
    $lastName  = $_POST['lastName'];
    $email     = $_POST['email'];

    try {
        $stmt = $conn->prepare("UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email WHERE id = :id");
        $stmt->execute(['first_name' => $firstName, 'last_name' => $lastName, 'email' => $email, 'id' => $user_id]);
        $message = "Profile updated successfully!";
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
    $newPassword     = $_POST['newPassword'];
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
    <title>User Settings - SENIORLINK</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/barangay-sidebar.css?v=1.1">
    <link rel="stylesheet" href="../assets/css/main-dark-mode.css?v=1.1">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

        :root {
            --primary:   #0f172a;
            --secondary: #1e3a5f;
            --accent:    #2563eb;
            --success:   #10b981;
            --warning:   #f59e0b;
            --light:     #f8fafc;
            --dark:      #020617;
            --gray:      #94a3b8;
            --bg:        #f1f5f9;
            --card-bg:   #ffffff;
            --text:      #0f172a;
        }

        body {
            background-color: var(--bg);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .header-content { display: flex; flex-direction: column; }

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

        .header h1 {
            font-family: 'Inter', sans-serif;
            color: var(--primary);
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.05;
            margin: 0;
        }

        .header h1 span { color: #2563eb; }

        .header-actions { display: flex; align-items: center; gap: 15px; }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 5px 12px;
            border-radius: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 3px solid transparent;
            background: linear-gradient(var(--card-bg), var(--card-bg)) padding-box, linear-gradient(135deg, #0f172a 0%, #3498db 100%) border-box;
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
            overflow: hidden;
        }

        .user-details h2 { font-size: 14px; margin-bottom: 2px; color: var(--primary); }
        .user-details p { color: var(--gray); font-size: 12px; }

        /* Profile Banner Section */
        .profile-section {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 20px 24px;
            margin-bottom: 24px;
            position: relative;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .profile-header { display: flex; align-items: center; gap: 16px; }

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
            overflow: hidden;
            border: 2px solid var(--accent);
        }

        .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }

        .profile-info h2 { color: var(--primary); font-size: 1.15rem; margin-bottom: 2px; }
        .profile-info p { color: var(--gray); font-size: 13px; }

        .theme-toggle-wrap { display: flex; align-items: center; gap: 10px; }
        .theme-toggle-wrap label { font-size: 13px; font-weight: 600; color: var(--primary); }

        .theme-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--card-bg);
            border: 1px solid #cbd5e1;
            color: var(--primary);
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
        }

        .theme-toggle:hover {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }

        /* Settings Grid */
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        @media (max-width: 900px) {
            .settings-grid { grid-template-columns: 1fr; }
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 24px;
            border: 1px solid #e2e8f0;
        }

        .card h3 {
            font-size: 1.05rem;
            margin-bottom: 20px;
            color: white;
            background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%);
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 18px;
            border-radius: 8px;
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group { flex: 1; }

        .form-group { margin-bottom: 18px; }

        .form-group label {
            display: block;
            font-size: 13px;
            color: var(--primary);
            margin-bottom: 6px;
            font-weight: 600;
        }

        .form-group input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: #fff;
            color: var(--primary);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .form-group input:disabled {
            background-color: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--accent);
            color: white;
            padding: 9px 18px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn:hover { background: #1d4ed8; }
        .btn-secondary { background: #64748b; }
        .btn-secondary:hover { background: #475569; }
        .btn-success { background: #10b981; }
        .btn-success:hover { background: #059669; }
        .btn-small { padding: 8px 16px; font-size: 13px; }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            align-items: center;
        }

        .message {
            background-color: #10b981;
            color: white;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .error {
            background-color: #ef4444;
            color: white;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }
    </style>
    <link rel="stylesheet" href="../assets/css/seniorlink-ui.css?v=3">
</head>
<body>
<div class="container">
    <?php include '../partials/barangay_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="header-content">
                <div class="welcome-message" data-first-name="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" data-last-name="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" data-role="<?php echo htmlspecialchars($user['role'] ?? ''); ?>"></div>
                <h1>User <span>Settings</span></h1>
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
                        <h2><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></h2>
                        <p><?php echo (($user['role'] ?? '') === 'department_admin') ? 'Department Admin · Pasig City' : htmlspecialchars(ucwords(str_replace('_', ' ', $user['role'] ?? ''))) . ' · ' . htmlspecialchars($user['barangay'] ?? $_SESSION['barangay'] ?? ''); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Profile Section Card -->
        <div class="profile-section">
            <div class="profile-header">
                <div class="profile-avatar">
                    <img src="<?php echo $profilePicPath; ?>" alt="Profile Picture">
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></h2>
                    <p><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', ($user['role'] ?? '')))); ?><?php if (($user['role'] ?? '') !== 'department_admin' && !empty($user['barangay'])): ?> • <?php echo htmlspecialchars($user['barangay']); ?><?php endif; ?></p>
                </div>
            </div>
            <div class="theme-toggle-wrap">
                <label for="themeToggle">Dark Mode</label>
                <button id="themeToggle" class="theme-toggle" title="Toggle theme" aria-pressed="false">
                    <i id="themeIcon" class="fas fa-moon"></i>
                </button>
            </div>
        </div>

        <!-- Settings Grid -->
        <div class="settings-grid">
            <!-- Profile Settings -->
            <div class="card">
                <h3><i class="fas fa-user"></i> Profile Settings</h3>
                <form method="post" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label for="role">Role</label>
                            <input type="text" id="role" name="role" value="<?php echo htmlspecialchars(ucwords(str_replace('_',' ',$user['role'] ?? ''))); ?>" disabled>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="firstName">First Name</label>
                            <input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')" disabled>
                        </div>
                        <div class="form-group">
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
                        <button type="button" class="btn btn-small" id="editProfileBtn"><i class="fas fa-edit"></i> Edit Profile</button>
                        <button type="submit" name="updateProfile" class="btn btn-success btn-small" id="saveBtn" style="display: none;"><i class="fas fa-save"></i> Save Changes</button>
                        <button type="button" class="btn btn-secondary btn-small" id="cancelBtn" style="display: none;"><i class="fas fa-times"></i> Cancel</button>
                    </div>
                </form>
            </div>

            <!-- Security Settings -->
            <div class="card">
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
                        <button type="button" class="btn btn-small" id="editPasswordBtn"><i class="fas fa-key"></i> Change Password</button>
                        <button type="submit" name="updatePassword" class="btn btn-success btn-small" id="savePasswordBtn" style="display: none;"><i class="fas fa-save"></i> Update Password</button>
                        <button type="button" class="btn btn-secondary btn-small" id="cancelPasswordBtn" style="display: none;"><i class="fas fa-times"></i> Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/sidebar-toggle.js"></script>
<script src="../assets/js/dark-mode.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const welcomeMessage = document.querySelector('.welcome-message');
        if (welcomeMessage) {
            const firstName = welcomeMessage.dataset.firstName || '';
            const lastName = welcomeMessage.dataset.lastName || '';
            const hour = new Date().getHours();
            let greeting = (hour < 12) ? "Good morning" : (hour < 18) ? "Good afternoon" : "Good evening";
            welcomeMessage.innerHTML = `${greeting}, <strong>${firstName} ${lastName}</strong>!`;
        }

        // Profile Settings
        const editProfileBtn = document.getElementById('editProfileBtn');
        const saveBtn        = document.getElementById('saveBtn');
        const cancelBtn      = document.getElementById('cancelBtn');
        const profileInputs  = ['firstName', 'lastName', 'email', 'phone'];

        if (editProfileBtn) {
            editProfileBtn.addEventListener('click', () => {
                profileInputs.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.disabled = false;
                });
                editProfileBtn.style.display = 'none';
                saveBtn.style.display = 'inline-flex';
                cancelBtn.style.display = 'inline-flex';
            });
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => {
                profileInputs.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.disabled = true;
                });
                editProfileBtn.style.display = 'inline-flex';
                saveBtn.style.display = 'none';
                cancelBtn.style.display = 'none';
            });
        }

        // Security Settings
        const editPasswordBtn   = document.getElementById('editPasswordBtn');
        const savePasswordBtn   = document.getElementById('savePasswordBtn');
        const cancelPasswordBtn = document.getElementById('cancelPasswordBtn');
        const passwordInputs    = ['currentPassword', 'newPassword', 'confirmPassword'];

        if (editPasswordBtn) {
            editPasswordBtn.addEventListener('click', () => {
                passwordInputs.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.disabled = false;
                });
                editPasswordBtn.style.display = 'none';
                savePasswordBtn.style.display = 'inline-flex';
                cancelPasswordBtn.style.display = 'inline-flex';
            });
        }

        if (cancelPasswordBtn) {
            cancelPasswordBtn.addEventListener('click', () => {
                passwordInputs.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) {
                        el.disabled = true;
                        el.value = '';
                    }
                });
                editPasswordBtn.style.display = 'inline-flex';
                savePasswordBtn.style.display = 'none';
                cancelPasswordBtn.style.display = 'none';
            });
        }
    });
</script>
</body>
</html>
