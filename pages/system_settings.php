<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/system_branding.php';
require_once '../includes/request_security.php';
require_once '../includes/audit_logger.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['updateSystemLogo'])) {
    requireSameOriginMutation();
    if (!in_array($_SESSION['role'] ?? '', ['department_admin', 'super_admin'], true)) {
        $error = 'Only a Department Administrator can change the system logo.';
    } elseif (!isset($_FILES['systemLogo']) || $_FILES['systemLogo']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Choose a PNG or JPEG logo to upload.';
    } else {
        $upload = $_FILES['systemLogo'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
        if (!isset($extensions[$mime]) || (int)$upload['size'] > 5 * 1024 * 1024) {
            $error = 'The logo must be a PNG or JPEG image no larger than 5 MB.';
        } else {
            $dimensions = @getimagesize($upload['tmp_name']);
            if (!$dimensions || $dimensions[0] < 100 || $dimensions[1] < 100) {
                $error = 'The logo image must be at least 100 by 100 pixels.';
            } else {
                $directory = dirname(__DIR__) . '/images/system_logos';
                if (!is_dir($directory) && !mkdir($directory, 0775, true)) {
                    $error = 'The logo storage is unavailable. Please try again.';
                } elseif (!is_writable($directory)) {
                    $error = 'The logo storage is unavailable. Please try again.';
                }
                $filename = 'system-logo-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extensions[$mime];
                if ($error === '' && !move_uploaded_file($upload['tmp_name'], $directory . '/' . $filename)) {
                    $error = 'The logo could not be saved. Please try again.';
                }
                if ($error === '') {
                    try {
                        $stmt = $conn->prepare("INSERT INTO system_settings (id, system_logo_filename, system_logo_mime) VALUES (1, ?, ?)
                            ON DUPLICATE KEY UPDATE system_logo_filename = VALUES(system_logo_filename), system_logo_mime = VALUES(system_logo_mime)");
                        $stmt->execute([$filename, $mime]);
                        $message = 'System logo updated. Future reports will use the new logo automatically.';
                    } catch (PDOException $e) {
                        unlink($directory . '/' . $filename);
                        error_log('System logo update failed: ' . $e->getMessage());
                        $error = 'The logo could not be saved. Please try again.';
                    }
                }
            }
        }
    }
}

// Fetch user data
try {
    $stmt = $conn->prepare("SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.barangay, u.profile_picture FROM users u WHERE u.id = :id");
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching user data: " . $e->getMessage();
    $user = [];
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

        /* Settings Grid */
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 24px;
            margin-bottom: 24px;
            width: 100%;
            align-items: start;
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

        .backup-summary { display:flex; gap:15px; align-items:flex-start; padding:16px; border:1px solid #bfdbfe; border-radius:10px; background:#eff6ff; color:#1e3a5f; }
        .backup-summary > i { display:grid; place-items:center; flex:0 0 42px; width:42px; height:42px; border-radius:10px; background:#2563eb; color:#fff; }
        .backup-summary strong { display:block; margin-bottom:4px; color:#172033; }
        .backup-summary p { margin:0; font-size:.84rem; line-height:1.55; }
        .backup-warning { margin-top:12px; color:#92400e; font-size:.78rem; font-weight:650; }
        .logo-editor { display:grid; grid-template-columns:112px minmax(0,1fr); gap:20px; align-items:start; }
        .logo-preview { text-align:center; }
        .logo-preview span { display:block; margin-bottom:8px; color:var(--gray); font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
        .logo-preview img { display:block; width:96px; height:96px; margin:auto; object-fit:contain; border:1px solid #dbe4ef; border-radius:14px; background:#fff; box-shadow:0 5px 14px rgba(15,23,42,.08); }
        .logo-upload-panel { min-width:0; }
        .logo-upload-panel label { display:block; margin-bottom:7px; color:var(--primary); font-size:.88rem; font-weight:750; }
        .logo-upload-panel small { display:block; margin-top:8px; color:var(--gray); font-size:.78rem; line-height:1.5; }
        .logo-upload-panel input[type="file"] { width:100%; padding:9px; border:1px solid #cbd5e1; border-radius:9px; background:#f8fafc; color:var(--primary); }
        .logo-upload-panel input[type="file"]::file-selector-button { margin-right:10px; padding:8px 12px; border:0; border-radius:7px; background:#1e3a5f; color:#fff; font:inherit; font-weight:700; cursor:pointer; }

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

        .btn-success {
            background: #10b981;
        }

        .btn-success:hover {
            background: #059669;
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
            .settings-grid { grid-template-columns:minmax(0,1fr); }
            .logo-editor { grid-template-columns:minmax(0,1fr); }
            .logo-preview { text-align:left; }
            .logo-preview img { margin:0; }
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
    <link rel="stylesheet" href="../assets/css/seniorlink-ui.css?v=20">
    <script src="../assets/js/modal-hci.js?v=2" defer></script>
    <link rel="stylesheet" href="../assets/css/system-header.css?v=1">
    <link rel="stylesheet" href="../assets/css/system-sidebar.css?v=3">
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
        </div>

        <!-- Settings Grid -->
        <div class="settings-grid">
            <div class="card">
                <h3><i class="fas fa-image"></i> Change System Logo</h3>
                <form id="systemLogoForm" action="" method="POST" enctype="multipart/form-data">
                    <div class="logo-editor">
                        <div class="logo-preview"><span>Current Logo</span><img id="systemLogoPreview" src="<?php echo htmlspecialchars(systemLogoUrl($conn)); ?>?v=<?php echo urlencode(systemLogoFilename($conn)); ?>" alt="Current system logo"></div>
                        <div class="logo-upload-panel"><label for="systemLogo">Choose a new logo</label><input type="file" id="systemLogo" name="systemLogo" accept="image/png,image/jpeg,.png,.jpg,.jpeg" required><small>PNG or JPEG, up to 5 MB. Your selected image will be previewed before saving and will appear throughout the system and on future PDF and Excel reports.</small></div>
                    </div>
                    <div class="actions"><button type="submit" name="updateSystemLogo" class="btn btn-success"><i class="fas fa-floppy-disk"></i> Save Changes</button></div>
                </form>
            </div>
            <?php if (in_array($_SESSION['role'] ?? '', ['department_admin', 'super_admin'], true)): ?>
            <div class="card">
                <h3><i class="fas fa-database"></i> Applicant Records Backup</h3>
                <div class="backup-summary">
                    <i class="fas fa-file-shield" aria-hidden="true"></i>
                    <div>
                        <strong>Download a portable records backup</strong>
                        <p>Creates a JSON backup of all applicant fields, workflow history, and uploaded-document metadata. The uploaded image and PDF contents remain in secure storage and are not duplicated in this file.</p>
                    </div>
                </div>
                <p class="backup-warning"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> This file contains sensitive personal information. Store it securely and limit access to authorized personnel.</p>
                <form id="applicantBackupForm" method="POST" action="../api/backup_applicant_records.php" style="margin-top:16px;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="actions"><button type="submit" class="btn btn-success btn-small"><i class="fas fa-download"></i> Download Applicant Backup</button></div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="../assets/js/sidebar-toggle.js?v=3"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const logoInput = document.getElementById('systemLogo');
        const logoPreview = document.getElementById('systemLogoPreview');
        let selectedLogoUrl = '';
        logoInput?.addEventListener('change', function() {
            const file = this.files?.[0];
            if (!file || !logoPreview) return;
            if (selectedLogoUrl) URL.revokeObjectURL(selectedLogoUrl);
            selectedLogoUrl = URL.createObjectURL(file);
            logoPreview.src = selectedLogoUrl;
        });
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

</script>
<script src="../assets/js/seniorlink-feedback.js?v=1"></script>
<script>
    document.getElementById('systemLogoForm')?.addEventListener('submit', function(event) {
        if (this.dataset.confirmed === 'true') return;
        if (!this.reportValidity()) {
            event.preventDefault();
            return;
        }
        event.preventDefault();
        window.showCarelinkConfirm(
            'Replace the system logo? The new logo will appear throughout the system and in future reports.',
            () => {
                this.dataset.confirmed = 'true';
                this.requestSubmit();
            }
        );
    });
    document.getElementById('applicantBackupForm')?.addEventListener('submit', function(event) {
        if (this.dataset.confirmed === 'true') return;
        event.preventDefault();
        window.showCarelinkConfirm(
            'Download a backup containing sensitive applicant records? Keep the downloaded file in secure, authorized storage.',
            () => {
                this.dataset.confirmed = 'true';
                this.requestSubmit();
            }
        );
    });
</script>
</body>
</html>
