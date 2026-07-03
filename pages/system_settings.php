<?php
session_start();
require_once '../includes/db_connect.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Fetch user data
try {
    $stmt = $conn->prepare("SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.barangay, u.profile_picture FROM users u WHERE u.id = :id");
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching user data: " . $e->getMessage();
    $user = [];
}


// Handle System Settings Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['updateSystemSettings'])) {
    $sessionTimeout = $_POST['sessionTimeout'];
    $maxLoginAttempts = $_POST['maxLoginAttempts'];
    $backupFrequency = $_POST['backupFrequency'];
    $autoBackup = isset($_POST['autoBackup']) ? 1 : 0;

    try {
        // Check if settings exist, if not, insert, else update
        $stmt = $conn->prepare("SELECT COUNT(*) FROM system_settings");
        $stmt->execute();
        $settingsExist = $stmt->fetchColumn();

        if ($settingsExist) {
            $stmt = $conn->prepare("UPDATE system_settings SET 
                                    session_timeout = :session_timeout, 
                                    max_login_attempts = :max_login_attempts, 
                                    backup_frequency = :backup_frequency,
                                    auto_backup = :auto_backup
                                    WHERE id = 1"); // Assuming a single row with ID 1 for system settings
            $stmt->execute([
                'session_timeout' => $sessionTimeout,
                'max_login_attempts' => $maxLoginAttempts,
                'backup_frequency' => $backupFrequency,
                'auto_backup' => $autoBackup
            ]);
        } else {
            $stmt = $conn->prepare("INSERT INTO system_settings (id, session_timeout, max_login_attempts, backup_frequency, auto_backup) 
                                    VALUES (1, :session_timeout, :max_login_attempts, :backup_frequency, :auto_backup)");
            $stmt->execute([
                'session_timeout' => $sessionTimeout,
                'max_login_attempts' => $maxLoginAttempts,
                'backup_frequency' => $backupFrequency,
                'auto_backup' => $autoBackup
            ]);
        }
        $message = "System settings updated successfully!";
    } catch (PDOException $e) {
        $error = "Error updating system settings: " . $e->getMessage();
    }
}

// Fetch system settings
try {
    $stmt = $conn->prepare("SELECT * FROM system_settings WHERE id = 1");
    $stmt->execute();
    $system_settings = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$system_settings) {
        // Default settings if not found in DB
        $system_settings = [
            'session_timeout' => 30,
            'max_login_attempts' => 5,
            'backup_frequency' => 'weekly',
            'auto_backup' => 1
        ];
    }
} catch (PDOException $e) {
    $error = "Error fetching system settings: " . $e->getMessage();
    $system_settings = []; // Ensure it's an array even on error
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CARELINK — System Settings</title>
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
            overflow: auto;
            transition: background-color 240ms ease, color 240ms ease;
        }
        .profile-section {
            background: var(--card-bg);
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
            position: relative; /* allow absolute positioning for the toggle inside the card */
            border: 1px solid rgba(16,24,40,0.04);
            color: var(--text);
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

        /* Main Content */
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
            background: white;
            border-radius: 25px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
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

        /* Cards */
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

        /* Settings Grid */
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr; /* Changed to single column */
            gap: 20px;
            margin-bottom: 30px;
        }

        /* Form */
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
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--secondary);
        }

        .form-group input:disabled {
            background-color: #f5f5f5;
            color: #999;
        }

        /* Buttons */
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

        /* Actions */
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        /* Switch */
        .switch {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .switch input[type="checkbox"] {
            width: 50px;
            height: 25px;
            appearance: none;
            background: #ccc;
            border-radius: 25px;
            position: relative;
            cursor: pointer;
            transition: background 0.3s;
        }

        .switch input[type="checkbox"]:checked {
            background: var(--secondary);
        }

        .switch input[type="checkbox"]::before {
            content: '';
            position: absolute;
            width: 21px;
            height: 21px;
            border-radius: 50%;
            background: white;
            top: 2px;
            left: 2px;
            transition: transform 0.3s;
        }

        .switch input[type="checkbox"]:checked::before {
            transform: translateX(25px);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .header-actions {
                align-self: flex-end;
            }
            

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
                    <div class="welcome-message" data-first-name="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" data-last-name="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" data-role="<?php echo htmlspecialchars($user['role'] ?? ''); ?>"></div>
            <h1>System Settings</h1>
                </div>
                <div class="header-actions">
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php
                                $profilePic = isset($user['profile_picture']) ? $user['profile_picture'] : 'default.jpg';
                                $profilePicPath = '../images/profile_pictures/' . $profilePic;
                                if (!file_exists($profilePicPath) || is_dir($profilePicPath)) {
                                    $profilePicPath = '../images/profile_pictures/default.jpg'; // Fallback to default if file doesn't exist
                                }
                            ?>
                            <img src="<?php echo $profilePicPath; ?>" alt="Profile Picture" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                        </div>
                        <div class="user-details">
                            <h2><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></h2>
                            <p><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', ($user['role'] ?? '')))); ?></p>
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

            <!-- Settings Grid -->
            <div class="settings-grid">
                <!-- Profile Section Card -->
                <div class="profile-section">
                    <!-- Theme toggle moved inside profile card (top-right) -->
                    <button id="themeToggle" class="theme-toggle" title="Toggle theme" aria-pressed="false">
                        <i id="themeIcon" class="fas fa-moon"></i>
                    </button>
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?php
                                // Use the fetched $user['profile_picture']
                                $profilePicDisplay = isset($user['profile_picture']) ? $user['profile_picture'] : 'default.jpg';
                                $profilePicPathDisplay = '../images/profile_pictures/' . $profilePicDisplay;
                                if (!file_exists($profilePicPathDisplay) || is_dir($profilePicPathDisplay)) {
                                    $profilePicPathDisplay = '../images/profile_pictures/default.jpg'; // Fallback to default if file doesn't exist
                                }
                            ?>
                            <img src="<?php echo $profilePicPathDisplay; ?>" alt="Profile Picture">
                        </div>
                        <div class="profile-info">
                            <h2><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></h2>
                            <p><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', ($user['role'] ?? '')))); ?><?php if (($user['role'] ?? '') !== 'department_admin' && ($user['barangay'] ?? '') !== ''): ?> • <?php echo htmlspecialchars($user['barangay']); ?><?php endif; ?></p>
                        </div>
                    </div>
                    <p style="color: var(--gray); font-size: 14px;">Manage your account preferences and appearance.</p>
                </div>

                <!-- Security Settings -->
                <div class="card">
                    <h3><i class="fas fa-shield-alt"></i> Security Settings</h3>
                    <form action="" method="POST">
                        <div class="form-group">
                            <label for="sessionTimeout">Session Timeout (minutes)</label>
                            <input type="number" id="sessionTimeout" name="sessionTimeout" value="<?php echo htmlspecialchars($system_settings['session_timeout'] ?? 30); ?>" min="5" max="120" oninput="this.value = this.value.replace(/[^0-9]/g, '')" disabled>
                        </div>
                        <div class="form-group">
                            <label for="maxLoginAttempts">Max Login Attempts</label>
                            <input type="number" id="maxLoginAttempts" name="maxLoginAttempts" value="<?php echo htmlspecialchars($system_settings['max_login_attempts'] ?? 5); ?>" min="3" max="10" oninput="this.value = this.value.replace(/[^0-9]/g, '')" disabled>
                        </div>
                        <div class="actions">
                            <button type="button" class="btn btn-small" id="editSecuritySettingsBtn">Edit Settings</button>
                            <button type="submit" name="updateSystemSettings" class="btn btn-success btn-small" id="saveSecuritySettingsBtn" style="display: none;">Save Changes</button>
                            <button type="button" class="btn btn-secondary btn-small" id="cancelSecuritySettingsBtn" style="display: none;">Cancel</button>
                        </div>
                    </form>
                </div>

                <!-- System Maintenance -->
                <div class="card">
                    <h3><i class="fas fa-tools"></i> System Maintenance</h3>
                    <form action="" method="POST">
                        <div class="form-group">
                            <label for="backupFrequency">Backup Frequency</label>
                            <select id="backupFrequency" name="backupFrequency" disabled>
                                <option value="daily" <?php echo (($system_settings['backup_frequency'] ?? 'weekly') == 'daily') ? 'selected' : ''; ?>>Daily</option>
                                <option value="weekly" <?php echo (($system_settings['backup_frequency'] ?? 'weekly') == 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                                <option value="monthly" <?php echo (($system_settings['backup_frequency'] ?? 'weekly') == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <div class="switch">
                                <input type="checkbox" id="autoBackup" name="autoBackup" <?php echo (($system_settings['auto_backup'] ?? 1) == 1) ? 'checked' : ''; ?> disabled>
                                <label for="autoBackup">Automatic Backup</label>
                            </div>
                        </div>
                        <div class="actions">
                            <button type="button" class="btn btn-small" id="editMaintenanceSettingsBtn">Edit Settings</button>
                            <button type="submit" name="updateSystemSettings" class="btn btn-success btn-small" id="saveMaintenanceSettingsBtn" style="display: none;">Save Changes</button>
                            <button type="button" class="btn btn-secondary btn-small" id="cancelMaintenanceSettingsBtn" style="display: none;">Cancel</button>
                            <button type="button" class="btn btn-warning btn-small" onclick="runBackup()">Run Backup Now</button>
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
            // Add click event to navigation items
            const navItems = document.querySelectorAll('.sidebar-menu li');
            navItems.forEach(item => {
                item.addEventListener('click', function() {
                    navItems.forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                });
            });
            
            // Update welcome message based on time of day
            const welcomeMessage = document.querySelector('.welcome-message');
            const firstName = welcomeMessage.dataset.firstName;
            const lastName = welcomeMessage.dataset.lastName;
            const role = welcomeMessage.dataset.role;
            const hour = new Date().getHours();
            let greeting;
            
            if (hour < 12) {
                greeting = "Good morning";
            } else if (hour < 18) {
                greeting = "Good afternoon";
            } else {
                greeting = "Good evening";
            }
            
            welcomeMessage.innerHTML = `${greeting}, <strong>${firstName} ${lastName}</strong>!`;
        });

        // This function will handle the theme toggle in system settings
        document.getElementById('themeToggle').addEventListener('click', function() {
            document.body.classList.toggle('dark-mode');
            const isDarkMode = document.body.classList.contains('dark-mode');
            localStorage.setItem('darkMode', isDarkMode);
            const themeIcon = document.getElementById('themeIcon');
            if (isDarkMode) {
                themeIcon.classList.remove('fa-moon');
                themeIcon.classList.add('fa-sun');
            } else {
                themeIcon.classList.remove('fa-sun');
                themeIcon.classList.add('fa-moon');
            }
        });

        // Apply theme on load
        if (localStorage.getItem('darkMode') === 'true') {
            document.body.classList.add('dark-mode');
            document.getElementById('themeIcon').classList.remove('fa-moon');
            document.getElementById('themeIcon').classList.add('fa-sun');
        } else {
            document.getElementById('themeIcon').classList.remove('fa-sun');
            document.getElementById('themeIcon').classList.add('fa-moon');
        }


        // Universal function to handle edit/save/cancel for settings cards
        function setupSettingsCard(editBtnId, saveBtnId, cancelBtnId, inputIds) {
            const editBtn = document.getElementById(editBtnId);
            const saveBtn = document.getElementById(saveBtnId);
            const cancelBtn = document.getElementById(cancelBtnId);
            const initialValues = {}; // Store initial values for cancellation

            // Store initial values when page loads
            inputIds.forEach(id => {
                const input = document.getElementById(id);
                if (input) {
                    initialValues[id] = input.type === 'checkbox' ? input.checked : input.value;
                }
            });

            if (editBtn) {
                editBtn.addEventListener('click', () => {
                    inputIds.forEach(id => {
                        const input = document.getElementById(id);
                        if (input) input.disabled = false;
                    });
                    editBtn.style.display = 'none';
                    saveBtn.style.display = 'inline-block';
                    cancelBtn.style.display = 'inline-block';
                });
            }

            if (cancelBtn) {
                cancelBtn.addEventListener('click', () => {
                    inputIds.forEach(id => {
                        const input = document.getElementById(id);
                        if (input) {
                            input.disabled = true;
                            // Restore initial values
                            if (input.type === 'checkbox') {
                                input.checked = initialValues[id];
                            } else {
                                input.value = initialValues[id];
                            }
                        }
                    });
                    editBtn.style.display = 'inline-block';
                    saveBtn.style.display = 'none';
                    cancelBtn.style.display = 'none';
                });
            }
        }

        // Setup for Security Settings
        setupSettingsCard(
            'editSecuritySettingsBtn',
            'saveSecuritySettingsBtn',
            'cancelSecuritySettingsBtn',
            ['sessionTimeout', 'maxLoginAttempts']
        );

        // Setup for Maintenance Settings
        setupSettingsCard(
            'editMaintenanceSettingsBtn',
            'saveMaintenanceSettingsBtn',
            'cancelMaintenanceSettingsBtn',
            ['backupFrequency', 'autoBackup']
        );


        function runBackup() {
            const backupButton = document.querySelector('button[onclick="runBackup()"]');
            backupButton.disabled = true; // Disable button to prevent multiple clicks
            backupButton.textContent = 'Backing up...';

            fetch('../api/run_backup.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                // No body needed for a simple trigger
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Success: ' + data.message);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                alert('An unexpected error occurred during backup.');
            })
            .finally(() => {
                backupButton.disabled = false; // Re-enable button
                backupButton.textContent = 'Run Backup Now';
            });
        }
  </script>
</body>
</html>