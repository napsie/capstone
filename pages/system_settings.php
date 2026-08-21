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
                                    WHERE id = 1");
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
    $system_settings = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - SENIORLINK</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/department-sidebar.css?v=4">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary: #0f172a;
            --secondary: #1e3a5f;
            --accent: #2563eb;
            --success: #10b981;
            --warning: #f59e0b;
            --light: #f8fafc;
            --dark: #020617;
            --gray: #94a3b8;
            --bg: #f1f5f9;
            --card-bg: #ffffff;
            --text: #0f172a;
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

        .header-content {
            display: flex;
            flex-direction: column;
        }

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

        .header h1 span {
            color: #2563eb;
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

        .user-details h2 {
            font-size: 14px;
            margin-bottom: 2px;
            color: var(--primary);
        }

        .user-details p {
            color: var(--gray);
            font-size: 12px;
        }

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

        .profile-header {
            display: flex;
            align-items: center;
            gap: 16px;
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
            overflow: hidden;
            border: 2px solid var(--accent);
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-info h2 {
            color: var(--primary);
            font-size: 1.15rem;
            margin-bottom: 2px;
        }

        .profile-info p {
            color: var(--gray);
            font-size: 13px;
        }

        .theme-toggle-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .theme-toggle-wrap label {
            font-size: 13px;
            font-weight: 600;
            color: var(--primary);
        }

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
            .settings-grid {
                grid-template-columns: 1fr;
            }
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

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            color: var(--primary);
            margin-bottom: 6px;
            font-weight: 600;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: #fff;
            color: var(--primary);
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .form-group input:disabled,
        .form-group select:disabled {
            background-color: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
        }

        /* Switch */
        .switch {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
        }

        .switch input[type="checkbox"] {
            width: 44px;
            height: 24px;
            appearance: none;
            background: #cbd5e1;
            border-radius: 24px;
            position: relative;
            cursor: pointer;
            transition: background 0.3s;
        }

        .switch input[type="checkbox"]:checked {
            background: var(--accent);
        }

        .switch input[type="checkbox"]::before {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: white;
            top: 2px;
            left: 2px;
            transition: transform 0.3s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }

        .switch input[type="checkbox"]:checked::before {
            transform: translateX(20px);
        }

        /* Buttons */
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
            text-decoration: none;
        }

        .btn:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: #64748b;
        }

        .btn-secondary:hover {
            background: #475569;
        }

        .btn-success {
            background: #10b981;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-warning {
            background: #f59e0b;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .btn-small {
            padding: 8px 16px;
            font-size: 13px;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            align-items: center;
            flex-wrap: wrap;
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

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .header-actions {
                align-self: flex-end;
            }
            .profile-section {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/seniorlink-ui.css?v=11">
    <link rel="stylesheet" href="../assets/css/system-header.css?v=1">
    <link rel="stylesheet" href="../assets/css/system-sidebar.css?v=2">
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
                <h1>System <span>Settings</span></h1>
            </div>
            <div class="header-actions">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php
                            $profilePic = isset($user['profile_picture']) ? $user['profile_picture'] : 'default.jpg';
                            $profilePicPath = '../images/profile_pictures/' . $profilePic;
                            if (!file_exists($profilePicPath) || is_dir($profilePicPath)) {
                                $profilePicPath = '../images/profile_pictures/default.jpg';
                            }
                        ?>
                        <img src="<?php echo $profilePicPath; ?>" alt="Profile Picture" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                    </div>
                    <div class="user-details">
                        <h2><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></h2>
                        <p><?php echo (($user['role'] ?? '') === 'department_admin') ? 'Department Admin · Pasig City' : htmlspecialchars(ucwords(str_replace('_', ' ', $user['role'] ?? ''))) . ' · ' . htmlspecialchars($user['barangay'] ?? ''); ?></p>
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
                    <p><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', ($user['role'] ?? '')))); ?><?php if (($user['role'] ?? '') !== 'department_admin' && ($user['barangay'] ?? '') !== ''): ?> • <?php echo htmlspecialchars($user['barangay']); ?><?php else: ?> • Pasig City<?php endif; ?></p>
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
                        <button type="button" class="btn btn-small" id="editSecuritySettingsBtn"><i class="fas fa-edit"></i> Edit Settings</button>
                        <button type="submit" name="updateSystemSettings" class="btn btn-success btn-small" id="saveSecuritySettingsBtn" style="display: none;"><i class="fas fa-save"></i> Save Changes</button>
                        <button type="button" class="btn btn-secondary btn-small" id="cancelSecuritySettingsBtn" style="display: none;"><i class="fas fa-times"></i> Cancel</button>
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
                        <button type="button" class="btn btn-small" id="editMaintenanceSettingsBtn"><i class="fas fa-edit"></i> Edit Settings</button>
                        <button type="submit" name="updateSystemSettings" class="btn btn-success btn-small" id="saveMaintenanceSettingsBtn" style="display: none;"><i class="fas fa-save"></i> Save Changes</button>
                        <button type="button" class="btn btn-secondary btn-small" id="cancelMaintenanceSettingsBtn" style="display: none;"><i class="fas fa-times"></i> Cancel</button>
                        <button type="button" class="btn btn-warning btn-small" onclick="runBackup()"><i class="fas fa-database"></i> Run Backup Now</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/sidebar-toggle.js?v=3"></script>
<script src="../assets/js/dark-mode.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Dynamic greeting message update
        const welcomeMessage = document.querySelector('.welcome-message');
        if (welcomeMessage) {
            const firstName = welcomeMessage.dataset.firstName || '';
            const lastName = welcomeMessage.dataset.lastName || '';
            const hour = new Date().getHours();
            let greeting = (hour < 12) ? "Good morning" : (hour < 18) ? "Good afternoon" : "Good evening";
            welcomeMessage.innerHTML = `${greeting}, <strong>${firstName} ${lastName}</strong>!`;
        }
    });

    // Helper function to setup Edit / Save / Cancel toggles
    function setupSettingsCard(editBtnId, saveBtnId, cancelBtnId, inputIds) {
        const editBtn = document.getElementById(editBtnId);
        const saveBtn = document.getElementById(saveBtnId);
        const cancelBtn = document.getElementById(cancelBtnId);
        const initialValues = {};

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
                saveBtn.style.display = 'inline-flex';
                cancelBtn.style.display = 'inline-flex';
            });
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => {
                inputIds.forEach(id => {
                    const input = document.getElementById(id);
                    if (input) {
                        input.disabled = true;
                        if (input.type === 'checkbox') {
                            input.checked = initialValues[id];
                        } else {
                            input.value = initialValues[id];
                        }
                    }
                });
                editBtn.style.display = 'inline-flex';
                saveBtn.style.display = 'none';
                cancelBtn.style.display = 'none';
            });
        }
    }

    setupSettingsCard(
        'editSecuritySettingsBtn',
        'saveSecuritySettingsBtn',
        'cancelSecuritySettingsBtn',
        ['sessionTimeout', 'maxLoginAttempts']
    );

    setupSettingsCard(
        'editMaintenanceSettingsBtn',
        'saveMaintenanceSettingsBtn',
        'cancelMaintenanceSettingsBtn',
        ['backupFrequency', 'autoBackup']
    );

    function runBackup() {
        const backupButton = document.querySelector('button[onclick="runBackup()"]');
        if (!backupButton) return;
        backupButton.disabled = true;
        backupButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Backing up...';

        fetch('../api/run_backup.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (typeof window.showCarelinkResult === 'function') {
                window.showCarelinkResult(data.message, data.success);
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            if (typeof window.showCarelinkResult === 'function') {
                window.showCarelinkResult('An unexpected error occurred during backup.', false);
            } else {
                alert('An unexpected error occurred during backup.');
            }
        })
        .finally(() => {
            backupButton.disabled = false;
            backupButton.innerHTML = '<i class="fas fa-database"></i> Run Backup Now';
        });
    }
</script>
<script src="../assets/js/carelink-feedback.js?v=2"></script>
</body>
</html>
