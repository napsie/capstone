<?php
session_start();
require_once 'includes/db_connect.php';

// Logout logic
if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
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
    list($selector, $validator) = explode(':', $_COOKIE['remember_me']);

    $stmt = $conn->prepare("SELECT * FROM remember_tokens WHERE selector = :selector AND expires > NOW()");
    $stmt->execute(['selector' => $selector]);
    $token = $stmt->fetch();

    if ($token) {
        if (hash_equals($token['validator_hash'], hash('sha256', $validator))) {
            // Token is valid, log in the user
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
            $stmt->execute(['id' => $token['user_id']]);
            $user = $stmt->fetch();

            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['role'] = $user['role'];
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
    <title>CARELINK - Centralized Profiling System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/loading-spinner.css">
    <link rel="stylesheet" href="assets/css/carelink-theme.css?v=3">
    <link rel="stylesheet" href="assets/css/landing.css?v=3">
</head>
<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <div class="page-bg page-bg--root" aria-hidden="true"></div>

    <header class="site-header">
        <div class="brand">
            <div class="brand-icon" aria-hidden="true">
                <i class="fas fa-hands-helping"></i>
            </div>
            <div class="brand-text">
                <h1>CARELINK</h1>
                <p>Centralized Profiling System</p>
            </div>
        </div>
        <nav class="site-nav" aria-label="Main navigation">
            <a href="pages/proxy_registration.php">Proxy Registration</a>
            <a href="#" id="aboutLink" aria-haspopup="dialog">About</a>
            <a href="pages/signup.php" class="btn-primary">Sign Up</a>
        </nav>
    </header>

    <div class="landing-wrapper">
        <div id="main-content" class="landing-main">
        <section class="hero-content" aria-labelledby="hero-title">
            <div class="hero-badge">
                <i class="fas fa-shield-alt" aria-hidden="true"></i>
                Government Services Portal
            </div>
            <h2 id="hero-title">Secure profiling for <span>Seniors &amp; PWD</span></h2>
            <p class="hero-sub">CARELINK is a centralized profiling and record authentication system for efficient government service delivery.</p>
            <p class="hero-audience">
                <i class="fas fa-users" aria-hidden="true"></i>
                Built for Senior Citizens and Persons with Disabilities
            </p>
            <div class="hero-stats" aria-hidden="true">
                <div class="hero-stat">
                    <strong>Secure</strong>
                    <span>Identity Verification</span>
                </div>
                <div class="hero-stat">
                    <strong>Fast</strong>
                    <span>Record Access</span>
                </div>
                <div class="hero-stat">
                    <strong>Trusted</strong>
                    <span>Data Privacy</span>
                </div>
            </div>
        </section>

        <section class="portal-panel" aria-labelledby="portal-heading">
            <div class="portal-panel-header">
                <h3 id="portal-heading">Select your portal</h3>
                <p>Choose the login option that matches your role</p>
            </div>
            <div class="portal-cards">
                <a href="pages/Barangay_Staff_LogInPage.php" class="portal-card" id="staffCard"
                   aria-label="Barangay Staff login — register beneficiaries and manage local records">
                    <div class="portal-card-icon staff" aria-hidden="true">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="portal-card-body">
                        <h4>Barangay Staff</h4>
                        <p>Register beneficiaries, capture facial data, and manage local records.</p>
                    </div>
                    <span class="portal-card-arrow" aria-hidden="true">
                        <i class="fas fa-arrow-right"></i>
                    </span>
                </a>

                <a href="pages/Department_Admin_LogIn_Page.php" class="portal-card" id="adminCard"
                   aria-label="Department Admin login — oversee operations and monitor authentication">
                    <div class="portal-card-icon admin" aria-hidden="true">
                        <i class="fas fa-user-cog"></i>
                    </div>
                    <div class="portal-card-body">
                        <h4>Department Admin</h4>
                        <p>Oversee system operations, generate reports, and monitor authentication.</p>
                    </div>
                    <span class="portal-card-arrow" aria-hidden="true">
                        <i class="fas fa-arrow-right"></i>
                    </span>
                </a>

                <a href="pages/proxy_registration.php" class="portal-card" id="proxyCard"
                   aria-label="Proxy pre-registration for bedridden seniors">
                    <div class="portal-card-icon proxy" aria-hidden="true">
                        <i class="fas fa-qrcode"></i>
                    </div>
                    <div class="portal-card-body">
                        <h4>Proxy Pre-Registration</h4>
                        <p>Pre-register for bedridden seniors and get a priority queue QR token.</p>
                    </div>
                    <span class="portal-card-arrow" aria-hidden="true">
                        <i class="fas fa-arrow-right"></i>
                    </span>
                </a>
            </div>
        </section>
        </div>

        <footer class="site-footer">
            <p>&copy; 2025 CARELINK — Centralized Profiling System. All Rights Reserved.</p>
        </footer>
    </div>

    <div class="about-modal" id="aboutModal" role="dialog" aria-modal="true"
         aria-labelledby="about-title" aria-hidden="true">
        <div class="about-content">
            <div class="about-header">
                <h3 id="about-title">About CARELINK</h3>
                <button class="close-btn" type="button" aria-label="Close about dialog">&times;</button>
            </div>
            <div class="about-body">
                <p>CARELINK is a Centralized Profiling and Record Authentication System designed specifically for Senior Citizens and Persons with Disabilities (PWD).</p>
                <p>CARELINK ensures secure and accurate identity verification while maintaining data privacy.</p>
                <p>The system provides efficient access to government services for our senior citizens and PWD community members.</p>
                <div class="team-section">
                    <h4>Our Team</h4>
                    <div class="team-members-container">
                        <div class="team-member">
                            <i class="fas fa-user-circle" aria-hidden="true"></i>
                            <p>Developer</p>
                        </div>
                        <div class="team-member">
                            <i class="fas fa-user-tie" aria-hidden="true"></i>
                            <p>Front End</p>
                        </div>
                        <div class="team-member">
                            <i class="fas fa-user-cog" aria-hidden="true"></i>
                            <p>Back End</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const aboutLink = document.getElementById('aboutLink');
        const aboutModal = document.getElementById('aboutModal');
        const closeBtn = aboutModal.querySelector('.close-btn');
        let lastFocusedElement = null;

        function openModal() {
            lastFocusedElement = document.activeElement;
            aboutModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            closeBtn.focus();
        }

        function closeModal() {
            aboutModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            if (lastFocusedElement) lastFocusedElement.focus();
        }

        aboutLink.addEventListener('click', (e) => {
            e.preventDefault();
            openModal();
        });

        closeBtn.addEventListener('click', closeModal);

        aboutModal.addEventListener('click', (e) => {
            if (e.target === aboutModal) closeModal();
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && aboutModal.getAttribute('aria-hidden') === 'false') {
                closeModal();
            }
        });
    </script>
    <script src="assets/js/dynamic-loader.js"></script>
</body>
</html>