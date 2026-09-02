<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/audit_logger.php';

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

    $stmt = $conn->prepare("SELECT * FROM remember_tokens WHERE selector = :selector AND expires > NOW()");
    $stmt->execute(['selector' => $selector]);
    $token = $stmt->fetch();

    if ($token && $validator !== '') {
        if (hash_equals($token['validator_hash'], hash('sha256', $validator))) {
            // Token is valid, log in the user
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id AND (is_archived = 0 OR is_archived IS NULL)");
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

                $rememberedRole = $user['role'] === 'barangay_staff' ? 'SHDO' : 'Department Administrator';
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

// Original remember_user cookie handling (to be removed or updated if still needed for old cookies)
// This block should be removed if only the new remember_me cookie is used.
// For now, I'm keeping it commented out to show the old logic.
/*
if (isset($_COOKIE['remember_user'])) {
    $cookie_value = base64_decode($_COOKIE['remember_user']);
    list($user_id, $username) = explode('|', $cookie_value);

    if (!empty($user_id) && !empty($username)) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id AND username = :username");
        $stmt->execute(['id' => $user_id, 'username' => $username]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'barangay_staff') {
                header("Location: pages/Barangay_Dash.php");
            } else {
                header("Location: pages/Department_Dashboard.php");
            }
            exit;
        }
    }
}
*/

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
    <link rel="stylesheet" href="assets/css/landing.css?v=21">
    <link rel="stylesheet" href="assets/css/seniorlink-ui.css?v=17">
    <script src="assets/js/modal-hci.js?v=2" defer></script>
</head>
<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <div class="page-bg page-bg--root" aria-hidden="true"></div>

    <header class="site-header">
        <div class="site-header-inner">
            <a class="brand" href="index.php" aria-label="SENIORLINK home">
                <img class="brand-logo" src="images/LOGO.jpg" alt="SENIORLINK logo">
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
                <a href="pages/signup.php" class="btn-primary">
                    <span>Create account</span>
                    <i class="fas fa-arrow-right" aria-hidden="true"></i>
                </a>
            </nav>
        </div>
    </header>

    <div class="landing-wrapper">
        <div id="main-content" class="landing-main">
        <section class="hero-content" aria-labelledby="hero-title">
            <div class="hero-badge">
                <i class="fas fa-shield-alt" aria-hidden="true"></i>
                City of Pasig
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
                    <h3 id="portal-heading">What do you need?</h3>
                    <p>Choose one option to continue.</p>
                </div>
                <span class="portal-security" title="Secure role-based access">
                    <i class="fas fa-shield-alt" aria-hidden="true"></i>
                    Secure
                </span>
            </div>
            <a href="pages/benefit_tracker.php" class="portal-track-card">
                <span class="portal-track-card-icon" aria-hidden="true"><i class="fas fa-magnifying-glass"></i></span>
                <span class="portal-track-card-copy">
                    <strong>Track your application</strong>
                    <small>Check status with your PRX or PEN token.</small>
                </span>
                <i class="fas fa-arrow-right portal-track-card-arrow" aria-hidden="true"></i>
            </a>
            <div class="portal-cards">
                <a href="pages/barangay_staff_login_page.php" class="portal-card" id="staffCard"
                   aria-label="SHDO login — register beneficiaries and manage local records">
                    <div class="portal-card-icon staff" aria-hidden="true">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="portal-card-body">
                        <span class="portal-role">Staff access</span>
                        <h4>SHDO sign in</h4>
                        <p>Register and manage senior records.</p>
                    </div>
                    <span class="portal-card-arrow" aria-hidden="true">
                        <i class="fas fa-arrow-right"></i>
                    </span>
                </a>

                <a href="pages/department_admin_login_page.php" class="portal-card" id="adminCard"
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
                </a>

                <a href="pages/proxy_registration.php" class="portal-card" id="proxyCard"
                   aria-label="Public new application service for senior citizens">
                    <div class="portal-card-icon proxy" aria-hidden="true">
                        <i class="fas fa-qrcode"></i>
                    </div>
                    <div class="portal-card-body">
                        <span class="portal-role">For senior citizens</span>
                        <h4>Apply for Benefit</h4>
                        <p>Submit an online senior benefit application.</p>
                    </div>
                    <span class="portal-card-arrow" aria-hidden="true">
                        <i class="fas fa-arrow-right"></i>
                    </span>
                </a>

            </div>

        </section>
        </div>

        <footer class="site-footer">
            <p>&copy; <?= date('Y') ?> SENIORLINK <span aria-hidden="true">&bull;</span> Centralized Profiling System</p>
        </footer>
    </div>

</body>
</html>
