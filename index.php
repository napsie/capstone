<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/audit_logger.php';
require_once 'includes/barangays_list.php';
require_once 'includes/system_branding.php';

$loginView = in_array($_GET['view'] ?? '', ['staff', 'admin'], true) ? $_GET['view'] : '';
$loginError = '';

// Logout logic
if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
    if (isset($_SESSION['user_id'])) {
        $logoutRole = ucwords(str_replace('_', ' ', (string)($_SESSION['role'] ?? 'user')));
        $logoutLocation = !empty($_SESSION['barangay']) ? " for Barangay {$_SESSION['barangay']}" : '';
        logAudit($conn, 'LOGOUT', "Logged out as {$logoutRole}{$logoutLocation}.");
    }

    // Clear session variables
    $_SESSION = array();
    session_destroy();

    // Clear remember me cookie and database token
    if (isset($_COOKIE['remember_me'])) {
        list($selector, $validator) = explode(':', $_COOKIE['remember_me']);
        
        // Delete token from database
        $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE selector = :selector");
        $stmt->execute(['selector' => $selector]);

        // Clear cookie
        setcookie('remember_me', '', time() - 3600, "/");
    }

    header("Location: index.php");
    exit;
}

// Check for remember me cookie (new secure one)
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    $rememberParts = explode(':', (string)$_COOKIE['remember_me'], 2);
    $selector = $rememberParts[0] ?? '';
    $validator = $rememberParts[1] ?? '';

    $stmt = $conn->prepare("SELECT id, user_id, validator_hash FROM remember_tokens WHERE selector = :selector AND expires > NOW() LIMIT 1");
    $stmt->execute(['selector' => $selector]);
    $token = $stmt->fetch();

    if ($token && $validator !== '') {
        if (hash_equals($token['validator_hash'], hash('sha256', $validator))) {
            // Token is valid, log in the user
            $stmt = $conn->prepare("SELECT id, username, first_name, last_name, role, barangay, profile_picture FROM users WHERE id = :id AND (is_archived = 0 OR is_archived IS NULL)");
            $stmt->execute(['id' => $token['user_id']]);
            $user = $stmt->fetch();

            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['barangay'] = $user['barangay'];
                $_SESSION['profile_picture'] = $user['profile_picture'];

                // Regenerate token to prevent theft
                $newValidator = bin2hex(random_bytes(32));
                $newValidatorHash = hash('sha256', $newValidator);
                $expiry = date('Y-m-d H:i:s', time() + (86400 * 30));

                $stmt = $conn->prepare("UPDATE remember_tokens SET validator_hash = :validator_hash, expires = :expires WHERE id = :id");
                $stmt->execute([
                    'validator_hash' => $newValidatorHash,
                    'expires' => $expiry,
                    'id' => $token['id']
                ]);

                setcookie('remember_me', $selector . ':' . $newValidator, time() + (86400 * 30), "/", "", false, true);

                $rememberedRole = $user['role'] === 'barangay_staff' ? 'Senior Citizen Help Desk Office' : 'Department Administrator';
                $rememberedLocation = $user['role'] === 'barangay_staff' ? " for Barangay {$user['barangay']}" : '';
                if (logAudit($conn, 'LOGIN', "Restored remembered login as {$rememberedRole}{$rememberedLocation}.")) {
                    $_SESSION['login_audit_recorded'] = true;
                }

                if ($user['role'] === 'barangay_staff') {
                    header("Location: pages/Barangay_Dash.php");
                } else {
                    header("Location: pages/Department_Dashboard.php");
                }
                exit;
            }
        }
    }
    // If token is invalid or expired, clear the cookie
    setcookie('remember_me', '', time() - 3600, "/");
}

// Unified role login: authentication stays on this landing page while only
// the portal panel content changes between role choices and login forms.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_role'])) {
    $loginView = $_POST['login_role'] === 'barangay_staff' ? 'staff' : ($_POST['login_role'] === 'department_admin' ? 'admin' : '');
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $selectedBarangay = trim((string)($_POST['barangay'] ?? ''));
    $remember = isset($_POST['remember']);

    if ($loginView === '' || $username === '' || $password === '' || ($loginView === 'staff' && $selectedBarangay === '')) {
        $loginError = 'Please fill in all required fields.';
    } else {
        $role = $loginView === 'staff' ? 'barangay_staff' : 'department_admin';
        $sql = "SELECT id, username, password, first_name, last_name, role, barangay, profile_picture FROM users WHERE username = :username AND role = :role AND (is_archived = 0 OR is_archived IS NULL)";
        $params = ['username' => $username, 'role' => $role];
        if ($role === 'barangay_staff') {
            $sql .= ' AND barangay = :barangay';
            $params['barangay'] = $selectedBarangay;
        }
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['barangay'] = $user['barangay'];
            $_SESSION['profile_picture'] = $user['profile_picture'];

            if ($remember) {
                $selector = bin2hex(random_bytes(8));
                $validator = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', time() + (86400 * 30));
                $tokenStmt = $conn->prepare('INSERT INTO remember_tokens (user_id, selector, validator_hash, expires) VALUES (:user_id, :selector, :validator_hash, :expires)');
                $tokenStmt->execute([
                    'user_id' => $user['id'], 'selector' => $selector,
                    'validator_hash' => hash('sha256', $validator), 'expires' => $expiry,
                ]);
                setcookie('remember_me', $selector . ':' . $validator, time() + (86400 * 30), '/', '', false, true);
            }

            $roleLabel = $role === 'barangay_staff' ? 'Senior Citizen Help Desk Office' : 'Department Administrator';
            $location = $role === 'barangay_staff' ? " for Barangay {$user['barangay']}" : '';
            if (logAudit($conn, 'LOGIN', "Logged in as {$roleLabel}{$location}.")) {
                $_SESSION['login_audit_recorded'] = true;
            }
            header('Location: ' . ($role === 'barangay_staff' ? 'pages/Barangay_Dash.php' : 'pages/Department_Dashboard.php'));
            exit;
        }

        logAudit($conn, 'FAILED_LOGIN', "Failed {$role} login attempt for username '{$username}'.", $user ? (int)$user['id'] : null, [
            'username' => $username ?: 'Unknown', 'role' => $role,
            'barangay' => $role === 'barangay_staff' ? $selectedBarangay : null,
        ]);
        $loginError = $role === 'barangay_staff'
            ? 'Invalid username, password, or barangay.'
            : 'Invalid username or password.';
    }
}

// Prevent browser from serving a cached version of the landing page
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <title>SENIORLINK - Centralized Profiling System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/seniorlink-public.css?v=1">
    <link rel="stylesheet" href="assets/css/landing.css?v=34">
    <link rel="stylesheet" href="assets/css/seniorlink-ui.css?v=20">
    <script src="assets/js/modal-hci.js?v=2" defer></script>
</head>
<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <div class="page-bg page-bg--root" aria-hidden="true"></div>

    <header class="site-header">
        <div class="site-header-inner">
            <a class="brand" href="index.php" aria-label="SENIORLINK home">
                <img class="brand-logo" src="<?php echo htmlspecialchars(systemLogoUrl($conn, '.')); ?>" alt="SENIORLINK logo">
                <div class="brand-text">
                    <h1><span>SENIOR</span><span>LINK</span></h1>
                    <p>Centralized Profiling System</p>
                </div>
            </a>
            <div class="header-context" aria-label="Official Pasig City senior services portal">
                <span class="header-context-icon" aria-hidden="true"><i class="fas fa-landmark"></i></span>
                <span>
                    <strong>Official City Portal</strong>
                    <small>Pasig City Senior Services</small>
                </span>
            </div>
            <nav class="site-nav" aria-label="Main navigation">
                <button type="button" class="btn-primary" data-signup-view aria-controls="signupView">
                    <span>Sign Up</span>
                    <i class="fas fa-arrow-right" aria-hidden="true"></i>
                </button>
            </nav>
        </div>
    </header>

    <div class="landing-wrapper">
        <div id="main-content" class="landing-main">
        <section class="hero-content" aria-labelledby="hero-title">
            <div class="hero-top-actions">
                <a href="pages/benefit_tracker.php" class="hero-track" id="trackLink"
                   aria-haspopup="dialog" aria-controls="trackModal">
                    <span class="hero-track-icon" aria-hidden="true">
                        <i class="fas fa-magnifying-glass"></i>
                    </span>
                    <span class="hero-track-copy">
                        <strong>Track your application</strong>
                        <small>Check status using your permanent PRX Token ID</small>
                    </span>
                    <i class="fas fa-arrow-right hero-track-arrow" aria-hidden="true"></i>
                </a>
                <div class="hero-badge">
                    <i class="fas fa-shield-alt" aria-hidden="true"></i>
                    City of Pasig
                </div>
            </div>
            <h2 id="hero-title">Senior services, <span>made simpler</span></h2>
            <p class="hero-sub">Apply, track an application, or access the workspace for your role.</p>
            <div class="hero-assurance" aria-label="System features">
                <span><i class="fas fa-lock" aria-hidden="true"></i> Secure access</span>
                <span><i class="fas fa-check-circle" aria-hidden="true"></i> Official city portal</span>
            </div>

        </section>

        <section class="portal-panel" aria-labelledby="portal-heading">
            <div class="portal-panel-header">
                <div>
                    <span class="portal-eyebrow">Get started</span>
                    <h3 id="portal-heading"><?php echo $loginView === 'staff' ? 'SHDO sign in' : ($loginView === 'admin' ? 'Administrator sign in' : 'What do you need?'); ?></h3>
                    <p id="portal-description"><?php echo $loginView !== '' ? 'Enter your account details to continue.' : 'Choose one option to continue.'; ?></p>
                </div>
                <span class="portal-security" title="Secure role-based access">
                    <i class="fas fa-shield-alt" aria-hidden="true"></i>
                    Secure
                </span>
            </div>
            <div class="portal-cards<?php echo $loginView !== '' ? ' is-hidden' : ''; ?>" id="portalRoleChoices">
                <button type="button" class="portal-card" id="staffCard" data-login-view="staff"
                   aria-label="Senior Citizen Help Desk Office login — register beneficiaries and manage local records">
                    <div class="portal-card-icon staff" aria-hidden="true">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="portal-card-body">
                        <span class="portal-role">Staff access</span>
                        <h4>Senior Citizen Help Desk Office sign in</h4>
                        <p>Register and manage senior records.</p>
                    </div>
                    <span class="portal-card-arrow" aria-hidden="true">
                        <i class="fas fa-arrow-right"></i>
                    </span>
                </button>

                <button type="button" class="portal-card" id="adminCard" data-login-view="admin"
                   aria-label="Department Admin login — oversee operations and monitor authentication">
                    <div class="portal-card-icon admin" aria-hidden="true">
                        <i class="fas fa-user-cog"></i>
                    </div>
                    <div class="portal-card-body">
                        <span class="portal-role">Administrator access</span>
                        <h4>Department Admin sign in</h4>
                        <p>Manage operations and reports.</p>
                    </div>
                    <span class="portal-card-arrow" aria-hidden="true">
                        <i class="fas fa-arrow-right"></i>
                    </span>
                </button>

                <a href="pages/proxy_registration.php" class="portal-card" id="proxyCard"
                   aria-label="Open senior citizen application services">
                    <div class="portal-card-icon proxy" aria-hidden="true">
                        <i class="fas fa-qrcode"></i>
                    </div>
                    <div class="portal-card-body">
                        <span class="portal-role">For senior citizens</span>
                        <h4>Senior Application Portal</h4>
                        <p>Apply for a Senior ID or access senior benefits.</p>
                    </div>
                    <span class="portal-card-arrow" aria-hidden="true">
                        <i class="fas fa-arrow-right"></i>
                    </span>
                </a>

            </div>

            <div class="portal-login-view<?php echo $loginView === 'staff' ? ' is-active' : ''; ?>" id="staffLoginView" data-login-panel="staff">
                <button type="button" class="portal-login-back" data-login-back><i class="fas fa-arrow-left" aria-hidden="true"></i> Back to access choices</button>
                <?php if ($loginView === 'staff' && $loginError): ?><div class="portal-login-error" role="alert" tabindex="-1"><?php echo htmlspecialchars($loginError); ?></div><?php endif; ?>
                <form method="post" action="index.php?view=staff" class="portal-login-form">
                    <input type="hidden" name="login_role" value="barangay_staff">
                    <div class="form-group"><label for="staffUsername">Username</label><input id="staffUsername" name="username" class="form-control" value="<?php echo $loginView === 'staff' ? htmlspecialchars($_POST['username'] ?? '') : ''; ?>" autocomplete="username" required></div>
                    <div class="form-group"><label for="staffPassword">Password</label><div class="password-field"><input type="password" id="staffPassword" name="password" class="form-control" autocomplete="current-password" required><button type="button" class="toggle-password" data-password-target="staffPassword" aria-label="Show password"><i class="fas fa-eye"></i></button></div></div>
                    <div class="form-group"><label for="staffBarangay">Barangay</label><select id="staffBarangay" name="barangay" class="form-control" required><option value="">Select your barangay</option><?php foreach ($barangays_list as $b): ?><option value="<?php echo htmlspecialchars($b); ?>" <?php echo $loginView === 'staff' && ($_POST['barangay'] ?? '') === $b ? 'selected' : ''; ?>><?php echo htmlspecialchars($b); ?></option><?php endforeach; ?></select></div>
                    <div class="portal-login-options"><label class="portal-remember"><input type="checkbox" name="remember"> Remember me</label><button type="button" class="portal-forgot" data-forgot-password>Forgot password?</button></div>
                    <button type="submit" class="portal-login-submit">Sign in to SHDO <i class="fas fa-arrow-right" aria-hidden="true"></i></button>
                    <div class="forgot-password-message" data-reset-message aria-live="polite"></div>
                    <div class="portal-otp-reset" data-otp-reset hidden>
                        <div class="portal-reset-header">
                            <span class="portal-reset-icon" aria-hidden="true"><i class="fas fa-key"></i></span>
                            <div><strong>Reset SHDO password</strong><small>Enter the six-digit code sent to the account's registered email.</small></div>
                        </div>
                        <div class="form-group"><label for="staffResetOtp">Six-digit OTP</label><input id="staffResetOtp" type="text" data-reset-otp class="form-control" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" placeholder="000000" disabled></div>
                        <div class="form-group"><label for="staffResetPassword">New password</label><input id="staffResetPassword" type="password" data-reset-password class="form-control" minlength="8" autocomplete="new-password" disabled></div>
                        <div class="form-group"><label for="staffResetConfirm">Confirm new password</label><input id="staffResetConfirm" type="password" data-reset-confirm class="form-control" minlength="8" autocomplete="new-password" disabled></div>
                        <div class="portal-reset-actions">
                            <button type="button" class="portal-reset-back" data-reset-cancel><i class="fas fa-arrow-left" aria-hidden="true"></i> Back to sign in</button>
                            <button type="button" class="portal-login-submit" data-reset-submit disabled>Change password</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="portal-login-view<?php echo $loginView === 'admin' ? ' is-active' : ''; ?>" id="adminLoginView" data-login-panel="admin">
                <button type="button" class="portal-login-back" data-login-back><i class="fas fa-arrow-left" aria-hidden="true"></i> Back to access choices</button>
                <?php if ($loginView === 'admin' && $loginError): ?><div class="portal-login-error" role="alert" tabindex="-1"><?php echo htmlspecialchars($loginError); ?></div><?php endif; ?>
                <form method="post" action="index.php?view=admin" class="portal-login-form">
                    <input type="hidden" name="login_role" value="department_admin">
                    <div class="form-group"><label for="adminUsername">Username</label><input id="adminUsername" name="username" class="form-control" value="<?php echo $loginView === 'admin' ? htmlspecialchars($_POST['username'] ?? '') : ''; ?>" autocomplete="username" required></div>
                    <div class="form-group"><label for="adminPassword">Password</label><div class="password-field"><input type="password" id="adminPassword" name="password" class="form-control" autocomplete="current-password" required><button type="button" class="toggle-password" data-password-target="adminPassword" aria-label="Show password"><i class="fas fa-eye"></i></button></div></div>
                    <div class="portal-login-options"><label class="portal-remember"><input type="checkbox" name="remember"> Remember me</label><button type="button" class="portal-forgot" data-forgot-password>Forgot password?</button></div>
                    <button type="submit" class="portal-login-submit">Sign in as Administrator <i class="fas fa-arrow-right" aria-hidden="true"></i></button>
                    <div class="forgot-password-message" data-reset-message aria-live="polite"></div>
                    <div class="portal-otp-reset" data-otp-reset hidden>
                        <div class="portal-reset-header">
                            <span class="portal-reset-icon" aria-hidden="true"><i class="fas fa-key"></i></span>
                            <div><strong>Reset administrator password</strong><small>Enter the six-digit code sent to the account's registered email.</small></div>
                        </div>
                        <div class="form-group"><label for="adminResetOtp">Six-digit OTP</label><input id="adminResetOtp" type="text" data-reset-otp class="form-control" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" placeholder="000000" disabled></div>
                        <div class="form-group"><label for="adminResetPassword">New password</label><input id="adminResetPassword" type="password" data-reset-password class="form-control" minlength="8" autocomplete="new-password" disabled></div>
                        <div class="form-group"><label for="adminResetConfirm">Confirm new password</label><input id="adminResetConfirm" type="password" data-reset-confirm class="form-control" minlength="8" autocomplete="new-password" disabled></div>
                        <div class="portal-reset-actions">
                            <button type="button" class="portal-reset-back" data-reset-cancel><i class="fas fa-arrow-left" aria-hidden="true"></i> Back to sign in</button>
                            <button type="button" class="portal-login-submit" data-reset-submit disabled>Change password</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="portal-login-view portal-signup-view" id="signupView" data-login-panel="signup">
                <button type="button" class="portal-login-back" data-login-back><i class="fas fa-arrow-left" aria-hidden="true"></i> Back to access choices</button>
                <iframe class="signup-frame" id="signupFrame" src="pages/signup.php?embed=1" title="Create a SENIORLINK staff account" scrolling="yes"></iframe>
            </div>

        </section>
        </div>

        <footer class="site-footer">
            <p>&copy; <?= date('Y') ?> SENIORLINK <span aria-hidden="true">&bull;</span> Centralized Profiling System</p>
        </footer>
    </div>

    <div class="about-modal" id="trackModal" role="dialog" aria-modal="true"
         aria-labelledby="track-title" aria-hidden="true">
        <div class="about-content track-content">
            <div class="about-header">
                <div>
                    <span class="portal-eyebrow">Application tracking</span>
                    <h3 id="track-title">Check Application Status</h3>
                </div>
                <button class="close-btn" type="button" aria-label="Close application tracker">&times;</button>
            </div>
            <p class="track-intro">Enter your permanent PRX Token ID, scan your QR code, or upload a QR image to check your application status.</p>
            <form class="portal-tracker track-modal-form" action="pages/benefit_tracker.php" method="get">
                <label for="landingTrackerToken">Permanent PRX Token ID</label>
                <div class="portal-tracker-controls">
                    <input id="landingTrackerToken" name="token" type="text" placeholder="Enter PRX Token ID (e.g., PRX-7K2M)"
                           maxlength="16" autocomplete="off" autocapitalize="characters" spellcheck="false" pattern="PRX-[A-Za-z0-9]{4,12}" required>
                    <button type="submit"><span>Check Status</span><i class="fas fa-arrow-right" aria-hidden="true"></i></button>
                </div>
                <div class="tracking-qr-actions" aria-label="QR tracking options">
                    <button type="button" id="landingScanQr"><i class="fas fa-camera" aria-hidden="true"></i> Scan QR Code</button>
                    <button type="button" id="landingUploadQr"><i class="fas fa-image" aria-hidden="true"></i> Upload QR Image</button>
                    <button type="button" id="landingStopQr" hidden><i class="fas fa-stop" aria-hidden="true"></i> Stop Camera</button>
                </div>
                <input id="landingQrFile" type="file" accept="image/*" hidden>
                <div id="landingQrReader" class="landing-qr-reader" hidden></div>
                <small id="landingQrStatus" class="landing-qr-status" role="status" aria-live="polite"></small>
                <small class="track-help"><i class="fas fa-shield-halved" aria-hidden="true"></i> Your code is used only to retrieve the application status.</small>
            </form>
        </div>
    </div>

    <script src="assets/js/vendor/html5-qrcode.min.js"></script>
    <script>
        (() => {
            const trigger = document.getElementById('trackLink');
            const modal = document.getElementById('trackModal');
            const closeButton = modal?.querySelector('.close-btn');
            const input = document.getElementById('landingTrackerToken');
            const form = modal?.querySelector('.track-modal-form');
            const scanButton = document.getElementById('landingScanQr');
            const uploadButton = document.getElementById('landingUploadQr');
            const stopButton = document.getElementById('landingStopQr');
            const fileInput = document.getElementById('landingQrFile');
            const reader = document.getElementById('landingQrReader');
            const scannerStatus = document.getElementById('landingQrStatus');
            let scanner = null;
            let cameraRunning = false;
            let previousFocus = null;

            const setScannerStatus = (message, type = '') => {
                if (!scannerStatus) return;
                scannerStatus.textContent = message;
                scannerStatus.dataset.type = type;
            };

            const extractPrx = decoded => String(decoded || '').toUpperCase().match(/PRX-[A-Z0-9]{4,12}/)?.[0] || '';

            const stopScanner = async () => {
                if (scanner && cameraRunning) {
                    try { await scanner.stop(); } catch (error) {}
                }
                cameraRunning = false;
                if (reader) reader.hidden = true;
                if (stopButton) stopButton.hidden = true;
                if (scanButton) scanButton.hidden = false;
                try { scanner?.clear(); } catch (error) {}
            };

            const useQrResult = async decoded => {
                const token = extractPrx(decoded);
                if (!token) {
                    setScannerStatus('This QR image does not contain a valid permanent PRX Token ID.', 'error');
                    return;
                }
                input.value = token;
                await stopScanner();
                setScannerStatus('PRX Token ID detected. Retrieving tracking information…', 'success');
                form?.requestSubmit();
            };

            const closeTracker = async () => {
                if (!modal) return;
                await stopScanner();
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
                previousFocus?.focus();
            };

            const openTracker = event => {
                if (!modal || !input) return;
                event.preventDefault();
                previousFocus = document.activeElement;
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
                input.focus();
            };

            trigger?.addEventListener('click', openTracker);
            closeButton?.addEventListener('click', closeTracker);
            scanButton?.addEventListener('click', async () => {
                if (typeof Html5Qrcode !== 'function') {
                    setScannerStatus('QR scanner could not load. Enter the PRX Token ID manually.', 'error');
                    return;
                }
                scanner ||= new Html5Qrcode('landingQrReader');
                reader.hidden = false;
                setScannerStatus('Allow camera access, then center the QR code in the frame.');
                try {
                    await scanner.start({facingMode:'environment'}, {fps:10, qrbox:{width:220,height:220}}, useQrResult, () => {});
                    cameraRunning = true;
                    scanButton.hidden = true;
                    stopButton.hidden = false;
                } catch (error) {
                    reader.hidden = true;
                    setScannerStatus('Camera unavailable. Allow camera access or upload a QR image instead.', 'error');
                }
            });
            stopButton?.addEventListener('click', async () => {
                await stopScanner();
                setScannerStatus('Camera stopped.');
            });
            uploadButton?.addEventListener('click', () => fileInput?.click());
            fileInput?.addEventListener('change', async () => {
                const file = fileInput.files?.[0];
                if (!file || typeof Html5Qrcode !== 'function') return;
                await stopScanner();
                scanner ||= new Html5Qrcode('landingQrReader');
                setScannerStatus('Reading PRX Token ID from the QR image…');
                try { await useQrResult(await scanner.scanFile(file, true)); }
                catch (error) { setScannerStatus('No valid PRX QR code was found in that image.', 'error'); }
                fileInput.value = '';
            });
            modal?.addEventListener('click', event => {
                if (event.target === modal) closeTracker();
            });
            modal?.addEventListener('keydown', event => {
                if (event.key === 'Escape') {
                    closeTracker();
                    return;
                }
                if (event.key !== 'Tab') return;
                const focusable = [...modal.querySelectorAll('button, input, a[href], [tabindex]:not([tabindex="-1"])')]
                    .filter(element => !element.disabled);
                if (!focusable.length) return;
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            });
        })();
    </script>

    <script>
        (() => {
            const choices = document.getElementById('portalRoleChoices');
            const heading = document.getElementById('portal-heading');
            const description = document.getElementById('portal-description');
            const panels = [...document.querySelectorAll('[data-login-panel]')];
            const signupFrame = document.getElementById('signupFrame');

            const closePasswordReset = (panel, clearMessage = true) => {
                if (!panel) return;
                const otpSection = panel.querySelector('[data-otp-reset]');
                panel.classList.remove('is-resetting');
                if (otpSection) {
                    otpSection.hidden = true;
                    otpSection.querySelectorAll('input').forEach(input => input.value = '');
                    otpSection.querySelectorAll('input, button[data-reset-submit]').forEach(control => control.disabled = true);
                }
                const resetMessage = panel.querySelector('[data-reset-message]');
                if (clearMessage && resetMessage) {
                    resetMessage.textContent = '';
                    resetMessage.classList.remove('is-error');
                }
            };

            const showLogin = view => {
                document.documentElement.classList.toggle('signup-view-open', view === 'signup');
                choices?.classList.add('is-hidden');
                panels.forEach(panel => panel.classList.toggle('is-active', panel.dataset.loginPanel === view));
                if (heading) heading.textContent = view === 'staff' ? 'SHDO sign in' : (view === 'admin' ? 'Administrator sign in' : 'Create staff account');
                if (description) description.textContent = view === 'signup' ? 'Enter the staff member’s details and assign the correct access role.' : 'Enter your account details to continue.';
                const active = panels.find(panel => panel.dataset.loginPanel === view);
                window.requestAnimationFrame(() => active?.querySelector('input:not([type="hidden"]), iframe')?.focus());
            };

            const showChoices = () => {
                document.documentElement.classList.remove('signup-view-open');
                panels.forEach(panel => {
                    closePasswordReset(panel);
                    panel.classList.remove('is-active');
                });
                choices?.classList.remove('is-hidden');
                if (heading) heading.textContent = 'What do you need?';
                if (description) description.textContent = 'Choose one option to continue.';
                history.replaceState(null, '', 'index.php');
                document.getElementById('staffCard')?.focus();
            };

            document.querySelectorAll('[data-login-view]').forEach(card => {
                card.addEventListener('click', () => showLogin(card.dataset.loginView));
            });
            document.querySelectorAll('[data-signup-view]').forEach(button => button.addEventListener('click', () => showLogin('signup')));
            document.querySelectorAll('[data-login-back]').forEach(button => button.addEventListener('click', showChoices));
            document.querySelectorAll('[data-password-target]').forEach(button => {
                button.addEventListener('click', () => {
                    const input = document.getElementById(button.dataset.passwordTarget);
                    if (!input) return;
                    const visible = input.type === 'password';
                    input.type = visible ? 'text' : 'password';
                    button.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
                    button.querySelector('i')?.classList.toggle('fa-eye-slash', visible);
                });
            });

            document.querySelectorAll('[data-forgot-password]').forEach(button => button.addEventListener('click', async () => {
                const panel = button.closest('[data-login-panel]');
                const usernameInput = panel?.querySelector('input[name="username"]');
                const resetMessage = panel?.querySelector('[data-reset-message]');
                const username = usernameInput?.value.trim() || '';
                if (!username) {
                    resetMessage.textContent = 'Enter your username first so we can use its registered email.';
                    resetMessage.classList.add('is-error');
                    usernameInput?.focus();
                    return;
                }
                button.disabled = true;
                resetMessage.classList.remove('is-error');
                resetMessage.textContent = 'Sending reset instructions to your registered email…';
                try {
                    const response = await fetch('api/forgot_password.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({username})
                    });
                    const result = await response.json();
                    resetMessage.textContent = result.message || 'Please check your email for the next step.';
                    resetMessage.classList.toggle('is-error', !response.ok || !result.success);
                    if (response.ok && result.success) {
                        const otpSection = panel.querySelector('[data-otp-reset]');
                        panel.classList.add('is-resetting');
                        otpSection.hidden = false;
                        otpSection.querySelectorAll('input, button').forEach(control => control.disabled = false);
                        otpSection.querySelector('[data-reset-otp]')?.focus();
                    }
                } catch (error) {
                    resetMessage.textContent = 'We could not send the request. Please try again.';
                    resetMessage.classList.add('is-error');
                } finally {
                    button.disabled = false;
                }
            }));

            document.querySelectorAll('[data-reset-submit]').forEach(button => button.addEventListener('click', async () => {
                const panel = button.closest('[data-login-panel]');
                const resetMessage = panel.querySelector('[data-reset-message]');
                const payload = {
                    username: panel.querySelector('input[name="username"]').value.trim(),
                    otp: panel.querySelector('[data-reset-otp]').value.replace(/\D/g, '').slice(0, 6),
                    password: panel.querySelector('[data-reset-password]').value,
                    confirmPassword: panel.querySelector('[data-reset-confirm]').value
                };
                button.disabled = true;
                resetMessage.classList.remove('is-error');
                resetMessage.textContent = 'Verifying your code…';
                try {
                    const response = await fetch('api/reset_password_otp.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams(payload)
                    });
                    const result = await response.json();
                    resetMessage.textContent = result.message;
                    resetMessage.classList.toggle('is-error', !response.ok || !result.success);
                    if (response.ok && result.success) {
                        closePasswordReset(panel, false);
                        panel.querySelector('input[name="password"]')?.focus();
                    }
                } catch (error) {
                    resetMessage.textContent = 'We could not verify the code. Please try again.';
                    resetMessage.classList.add('is-error');
                } finally {
                    if (!button.closest('[data-otp-reset]').hidden) button.disabled = false;
                }
            }));

            document.querySelectorAll('[data-reset-cancel]').forEach(button => button.addEventListener('click', () => {
                const panel = button.closest('[data-login-panel]');
                closePasswordReset(panel);
                panel?.querySelector('input[name="password"]')?.focus();
            }));

            document.querySelectorAll('.portal-login-form').forEach(form => form.addEventListener('submit', event => {
                const panel = form.closest('[data-login-panel]');
                if (!panel?.classList.contains('is-resetting')) return;
                event.preventDefault();
                panel.querySelector('[data-reset-submit]')?.click();
            }));

            document.querySelectorAll('[data-otp-reset]').forEach(section => section.addEventListener('keydown', event => {
                if (event.key !== 'Enter') return;
                event.preventDefault();
                section.querySelector('[data-reset-submit]')?.click();
            }));

            document.querySelector('.portal-login-view.is-active .portal-login-error')?.focus();
        })();
    </script>

</body>
</html>
