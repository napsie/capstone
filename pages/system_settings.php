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

if (empty($_SESSION['backup_csrf_token'])) {
    $_SESSION['backup_csrf_token'] = bin2hex(random_bytes(32));
}
$backupNotice = $_SESSION['backup_notice'] ?? null;
unset($_SESSION['backup_notice']);

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';
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

        .page-subtitle { margin:7px 0 0; color:#64748b; font-size:.84rem; line-height:1.45; }

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

        /* Settings Grid */
        .settings-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 20px;
            max-width: 1240px;
            margin: 0 auto 24px;
            width: 100%;
            align-items: start;
        }

        .header,
        .main-content > .message,
        .main-content > .error,
        .main-content > .backup-notice {
            width: 100%;
            max-width: 1240px;
            margin-left: auto;
            margin-right: auto;
        }

        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
            padding: 26px;
            border: 1px solid #e2e8f0;
        }

        .card h3 {
            font-size: 1.05rem;
            margin-bottom: 8px;
            color: var(--primary);
            background: transparent;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0;
            border-radius: 0;
        }

        .card h3 i { width:40px; height:40px; display:grid; place-items:center; flex:0 0 40px; border-radius:11px; color:#1d4ed8; background:#dbeafe; }
        .section-description { margin:0 0 22px 52px; color:#64748b; font-size:.82rem; line-height:1.5; }

        .logo-editor { display:grid; grid-template-columns:150px minmax(0,1fr); gap:26px; align-items:center; padding:20px; border:1px solid #e2e8f0; border-radius:13px; background:#f8fafc; }
        .logo-preview { text-align:center; }
        .logo-preview span { display:block; margin-bottom:8px; color:var(--gray); font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
        .logo-preview img { display:block; width:112px; height:112px; margin:auto; object-fit:contain; border:1px solid #dbe4ef; border-radius:16px; background:#fff; box-shadow:0 5px 14px rgba(15,23,42,.08); }
        .logo-upload-panel { min-width:0; }
        .logo-upload-panel label { display:block; margin-bottom:7px; color:var(--primary); font-size:.88rem; font-weight:750; }
        .logo-upload-panel small { display:block; margin-top:8px; color:var(--gray); font-size:.78rem; line-height:1.5; }
        .logo-upload-panel input[type="file"] { width:100%; padding:9px; border:1px solid #cbd5e1; border-radius:9px; background:#f8fafc; color:var(--primary); }
        .logo-upload-panel input[type="file"]::file-selector-button { margin-right:10px; padding:8px 12px; border:0; border-radius:7px; background:#1e3a5f; color:#fff; font:inherit; font-weight:700; cursor:pointer; }
        .selection-status { display:flex; align-items:center; gap:7px; margin-top:10px; color:#64748b; font-size:.77rem; }
        .selection-status.ready { color:#047857; font-weight:700; }
        .selection-status.invalid { color:#b91c1c; font-weight:700; }

        .backup-card { grid-column: 1 / -1; }
        .backup-card h3 i { color:#047857; background:#d1fae5; }
        .backup-layout { display:grid; grid-template-columns:minmax(280px, .8fr) minmax(360px, 1.2fr); gap:22px; align-items:stretch; }
        .backup-summary { padding:18px; border:1px solid #bfdbfe; border-radius:12px; background:#eff6ff; }
        .backup-summary strong { display:block; margin-bottom:8px; color:#1e3a5f; }
        .backup-summary p { margin:0 0 14px; color:#475569; font-size:.86rem; line-height:1.6; }
        .backup-summary ul { display:grid; gap:8px; margin:0; padding-left:20px; color:#334155; font-size:.82rem; }
        .backup-form { padding:20px; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; }
        .backup-form .form-group:last-of-type { margin-bottom:8px; }
        .backup-help { display:block; margin-top:7px; color:#64748b; font-size:.76rem; line-height:1.45; }
        .backup-warning { display:flex; align-items:flex-start; gap:9px; margin:14px 0; padding:11px 12px; border-radius:9px; color:#854d0e; background:#fefce8; font-size:.78rem; line-height:1.45; }
        .password-field { position:relative; }
        .password-field input { padding-right:48px; }
        .password-toggle { position:absolute; top:50%; right:7px; width:36px; height:36px; transform:translateY(-50%); border:0; border-radius:7px; color:#475569; background:transparent; cursor:pointer; }
        .password-toggle:hover { color:#1d4ed8; background:#eaf2ff; }
        .password-status { min-height:20px; margin-top:6px; color:#64748b; font-size:.74rem; }
        .password-status.valid { color:#047857; }.password-status.invalid { color:#b91c1c; }
        .backup-notice { grid-column:1/-1; padding:13px 15px; border-radius:10px; font-size:.86rem; font-weight:650; }
        .backup-notice.error { color:#991b1b; border:1px solid #fecaca; background:#fef2f2; }

        @media (max-width: 780px) {
            .backup-layout { grid-template-columns:1fr; }
            .backup-form { padding:16px; }
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
        .btn:focus-visible,.password-toggle:focus-visible { outline:3px solid rgba(37,99,235,.25); outline-offset:2px; }
        .btn:disabled { cursor:not-allowed; opacity:.55; transform:none; }

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
            .card { padding:18px; }
            .section-description { margin-left:0; }
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
                <p class="page-subtitle">Manage system-wide branding and create secure administrative backups.</p>
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
            <div class="message" role="status"><i class="fas fa-circle-check" aria-hidden="true"></i> <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error" role="alert"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (is_array($backupNotice)): ?>
            <div class="backup-notice <?php echo !empty($backupNotice['success']) ? 'message' : 'error'; ?>">
                <?php echo htmlspecialchars((string)($backupNotice['message'] ?? '')); ?>
            </div>
        <?php endif; ?>

        <!-- Settings Grid -->
        <div class="settings-grid">
            <section class="card" aria-labelledby="logoSettingsTitle">
                <h3 id="logoSettingsTitle"><i class="fas fa-image"></i> System and Report Logo</h3>
                <p class="section-description">Update the official logo used across SENIORLINK and generated reports.</p>
                <form id="systemLogoForm" action="" method="POST" enctype="multipart/form-data">
                    <div class="logo-editor">
                        <div class="logo-preview"><span>Current Logo</span><img id="systemLogoPreview" src="<?php echo htmlspecialchars(systemLogoUrl($conn)); ?>?v=<?php echo urlencode(systemLogoFilename($conn)); ?>" alt="Current system logo"></div>
                        <div class="logo-upload-panel"><label for="systemLogo">Choose a new logo</label><input type="file" id="systemLogo" name="systemLogo" accept="image/png,image/jpeg,.png,.jpg,.jpeg" aria-describedby="logoRequirements logoSelectionStatus" required><small id="logoRequirements">PNG or JPEG, up to 5 MB and at least 100 × 100 pixels. Preview the image before saving; it will appear throughout the system and in future PDF and Excel reports.</small><div id="logoSelectionStatus" class="selection-status" aria-live="polite"><i class="fas fa-circle-info" aria-hidden="true"></i><span>No new logo selected.</span></div></div>
                    </div>
                    <div class="actions"><button type="submit" id="logoSaveButton" name="updateSystemLogo" class="btn" disabled><i class="fas fa-floppy-disk"></i> Update System Logo</button></div>
                </form>
            </section>
            <?php if (in_array($_SESSION['role'] ?? '', ['department_admin', 'super_admin'], true)): ?>
            <section class="card backup-card" id="backup" aria-labelledby="backupSettingsTitle">
                <h3 id="backupSettingsTitle"><i class="fas fa-shield-halved"></i> Encrypted System Backup</h3>
                <p class="section-description">Create a password-protected administrative backup for secure offline storage.</p>
                <div class="backup-layout">
                    <div class="backup-summary">
                        <strong>One portable copy of essential system data</strong>
                        <p>The downloaded ZIP is created only when requested and is removed from the server immediately after download.</p>
                        <ul>
                            <li>MySQL tables, records, and uploaded document data</li>
                            <li>Profile pictures, system logos, and available upload folders</li>
                            <li>Manifest with file checksums for later verification</li>
                            <li>AES-256 encryption using the backup password you provide</li>
                        </ul>
                    </div>
                    <form class="backup-form" id="systemBackupForm" action="download_backup.php" method="POST" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['backup_csrf_token']); ?>">
                        <div class="form-group">
                            <label for="backupAccountPassword">Current account password</label>
                            <div class="password-field"><input type="password" id="backupAccountPassword" name="account_password" required autocomplete="current-password"><button class="password-toggle" type="button" aria-label="Show current account password" aria-pressed="false" data-password-toggle="backupAccountPassword"><i class="fas fa-eye" aria-hidden="true"></i></button></div>
                            <small class="backup-help">Confirms that the signed-in administrator authorized this download.</small>
                        </div>
                        <div class="form-group">
                            <label for="backupArchivePassword">Backup file password</label>
                            <div class="password-field"><input type="password" id="backupArchivePassword" name="archive_password" required minlength="12" autocomplete="new-password" aria-describedby="backupPasswordStatus"><button class="password-toggle" type="button" aria-label="Show backup file password" aria-pressed="false" data-password-toggle="backupArchivePassword"><i class="fas fa-eye" aria-hidden="true"></i></button></div>
                            <small class="backup-help">Use at least 12 characters. This password is never saved by SENIORLINK.</small>
                            <div id="backupPasswordStatus" class="password-status" aria-live="polite">Enter at least 12 characters.</div>
                        </div>
                        <div class="form-group">
                            <label for="backupArchivePasswordConfirmation">Confirm backup file password</label>
                            <div class="password-field"><input type="password" id="backupArchivePasswordConfirmation" name="archive_password_confirmation" required minlength="12" autocomplete="new-password" aria-describedby="backupPasswordMatch"><button class="password-toggle" type="button" aria-label="Show password confirmation" aria-pressed="false" data-password-toggle="backupArchivePasswordConfirmation"><i class="fas fa-eye" aria-hidden="true"></i></button></div>
                            <div id="backupPasswordMatch" class="password-status" aria-live="polite">Re-enter the backup password.</div>
                        </div>
                        <div class="backup-warning"><i class="fas fa-triangle-exclamation"></i><span>Keep the ZIP and its password in separate safe locations. A forgotten backup password cannot be recovered.</span></div>
                        <div class="actions"><button type="submit" class="btn"><i class="fas fa-download"></i> Create and Download Backup</button></div>
                    </form>
                </div>
            </section>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="../assets/js/sidebar-toggle.js?v=3"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const logoInput = document.getElementById('systemLogo');
        const logoPreview = document.getElementById('systemLogoPreview');
        const logoStatus = document.getElementById('logoSelectionStatus');
        const logoSaveButton = document.getElementById('logoSaveButton');
        let selectedLogoUrl = '';
        logoInput?.addEventListener('change', function() {
            const file = this.files?.[0];
            logoStatus?.classList.remove('ready', 'invalid');
            if (!file || !logoPreview) {
                if (logoStatus) logoStatus.innerHTML = '<i class="fas fa-circle-info" aria-hidden="true"></i><span>No new logo selected.</span>';
                if (logoSaveButton) logoSaveButton.disabled = true;
                return;
            }
            const validType = ['image/png', 'image/jpeg'].includes(file.type);
            const validSize = file.size <= 5 * 1024 * 1024;
            if (!validType || !validSize) {
                this.value = '';
                if (logoSaveButton) logoSaveButton.disabled = true;
                logoStatus?.classList.add('invalid');
                if (logoStatus) logoStatus.innerHTML = '<i class="fas fa-circle-exclamation" aria-hidden="true"></i><span>Select a PNG or JPEG image no larger than 5 MB.</span>';
                return;
            }
            if (selectedLogoUrl) URL.revokeObjectURL(selectedLogoUrl);
            selectedLogoUrl = URL.createObjectURL(file);
            logoPreview.src = selectedLogoUrl;
            if (logoSaveButton) logoSaveButton.disabled = false;
            logoStatus?.classList.add('ready');
            if (logoStatus) {
                logoStatus.innerHTML = '<i class="fas fa-circle-check" aria-hidden="true"></i><span></span>';
                logoStatus.querySelector('span').textContent = `${file.name} is ready to preview and save.`;
            }
        });

        document.querySelectorAll('[data-password-toggle]').forEach(button => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.passwordToggle);
                if (!input) return;
                const showing = input.type === 'text';
                input.type = showing ? 'password' : 'text';
                button.setAttribute('aria-pressed', showing ? 'false' : 'true');
                button.setAttribute('aria-label', `${showing ? 'Show' : 'Hide'} ${button.getAttribute('aria-label').replace(/^(Show|Hide)\s+/, '')}`);
                const icon = button.querySelector('i');
                if (icon) icon.className = showing ? 'fas fa-eye' : 'fas fa-eye-slash';
            });
        });

        const archivePassword = document.getElementById('backupArchivePassword');
        const archiveConfirmation = document.getElementById('backupArchivePasswordConfirmation');
        const passwordStatus = document.getElementById('backupPasswordStatus');
        const passwordMatch = document.getElementById('backupPasswordMatch');
        const updatePasswordFeedback = () => {
            if (passwordStatus && archivePassword) {
                const valid = archivePassword.value.length >= 12;
                passwordStatus.className = `password-status${archivePassword.value ? (valid ? ' valid' : ' invalid') : ''}`;
                passwordStatus.textContent = valid ? 'Password length requirement met.' : 'Enter at least 12 characters.';
            }
            if (passwordMatch && archiveConfirmation && archivePassword) {
                const hasConfirmation = archiveConfirmation.value.length > 0;
                const matches = hasConfirmation && archiveConfirmation.value === archivePassword.value;
                archiveConfirmation.setCustomValidity(hasConfirmation && !matches ? 'The backup file passwords must match.' : '');
                passwordMatch.className = `password-status${hasConfirmation ? (matches ? ' valid' : ' invalid') : ''}`;
                passwordMatch.textContent = hasConfirmation ? (matches ? 'Passwords match.' : 'Passwords do not match.') : 'Re-enter the backup password.';
            }
        };
        archivePassword?.addEventListener('input', updatePasswordFeedback);
        archiveConfirmation?.addEventListener('input', updatePasswordFeedback);
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
    document.getElementById('systemBackupForm')?.addEventListener('submit', function(event) {
        const password = document.getElementById('backupArchivePassword');
        const confirmation = document.getElementById('backupArchivePasswordConfirmation');
        if (password && confirmation && password.value !== confirmation.value) {
            event.preventDefault();
            confirmation.setCustomValidity('The backup file passwords must match.');
            confirmation.reportValidity();
            return;
        }
        confirmation?.setCustomValidity('');
        const button = this.querySelector('button[type="submit"]');
        if (button) {
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Preparing secure backup...';
        }
    });
</script>
</body>
</html>
