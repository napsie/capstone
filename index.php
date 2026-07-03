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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CARELINK - Centralized Profiling System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/loading-spinner.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #1a4b8c 0%, #0d3a6e 100%);
            color: white;
            height: 100vh;
            overflow: hidden;
        }

        .background-image {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background-image: url('images/system_background.png');
            background-size: cover;
            background-position: center;
            opacity: 0.3;
            animation: kenburns 30s ease-in-out infinite;
        }

        @keyframes kenburns {
            0% {
                transform: scale(1) translate(0, 0);
                opacity: 0.3;
            }
            50% {
                transform: scale(1.2) translate(-5%, 5%);
                opacity: 0.4;
            }
            100% {
                transform: scale(1) translate(0, 0);
                opacity: 0.3;
            }
        }

        /* Menu Bar */
        .menu-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 30px;
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .system-logo {
            font-size: 2rem;
            color: #4CAF50;
        }

        .logo-text h1 {
            font-size: 1.5rem;
            color: #4CAF50;
        }

        .logo-text p {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        .nav-links {
            display: flex;
            gap: 25px;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 6px 12px;
            border-radius: 5px;
            transition: all 0.3s;
            font-size: 0.9rem;
        }

        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #4CAF50;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            height: calc(100vh - 65px);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        /* Header Section */
        .header-section {
            text-align: center;
            padding: 20px 0;
            animation: fadeIn 1s ease-out;
            margin-bottom: 40px;
        }

        .system-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .brand-logo {
            font-size: 3rem;
            color: #4CAF50;
        }

        .system-name h1 {
            font-size: 2.2rem;
            margin-bottom: 8px;
            color: #4CAF50;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .system-name p {
            font-size: 1rem;
            opacity: 0.9;
        }

        .municipality-info {
            margin-top: 10px;
        }

        .municipality-info h2 {
            font-size: 1.2rem;
            font-weight: normal;
            margin-bottom: 5px;
            opacity: 0.9;
        }

        /* Role Selection */
        .role-section {
            animation: slideUp 1s ease-out 0.6s both;
        }

        .role-selection {
            display: flex;
            justify-content: center;
            gap: 25px;
            flex-wrap: wrap;
        }

        .role-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 20px;
            width: 220px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            text-decoration: none;
            color: white;
            display: block;
        }

        .role-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }

        .role-icon {
            font-size: 2.5rem;
            margin-bottom: 12px;
            color: #4CAF50;
        }

        .role-card h4 {
            font-size: 1.3rem;
            margin-bottom: 10px;
            color: #4CAF50;
        }

        .role-card p {
            font-size: 0.8rem;
            line-height: 1.4;
            opacity: 0.9;
        }

        footer {
            text-align: center;
            padding: 20px 0;
            font-size: 0.8rem;
            opacity: 0.8;
            animation: fadeIn 1s ease-out 1.2s both;
            margin-top: 40px;
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(76, 175, 80, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(76, 175, 80, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(76, 175, 80, 0);
            }
        }

        /* About Modal */
        .about-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease-out;
        }

        .about-content {
            background: linear-gradient(135deg, #1a4b8c 0%, #0d3a6e 100%);
            border-radius: 12px;
            padding: 30px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            animation: slideUp 0.5s ease-out;
        }

        .about-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .about-header h3 {
            font-size: 1.5rem;
            color: #4CAF50;
        }

        .close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.3rem;
            cursor: pointer;
            transition: color 0.3s;
        }

        .close-btn:hover {
            color: #4CAF50;
        }

        .about-body {
            line-height: 1.5;
            font-size: 0.9rem;
        }

        .about-body p {
            margin-bottom: 12px;
        }

        .team-section {
            margin-top: 25px;
            text-align: center;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
        }

        .team-section h4 {
            font-size: 1.2rem;
            color: #4CAF50;
            margin-bottom: 15px;
        }

        .team-members-container {
            display: flex;
            justify-content: center;
            gap: 25px;
            flex-wrap: wrap;
        }

        .team-member {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: white;
        }

        .team-member i {
            font-size: 2.5rem;
            color: #4CAF50;
            margin-bottom: 8px;
        }

        .team-member p {
            font-size: 0.85rem;
            margin: 0;
            opacity: 0.9;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
                max-width: 100%;
            }
            
            .system-name h1 {
                font-size: 1.8rem;
            }
            
            .system-name p {
                font-size: 0.9rem;
            }
            
            .municipality-info h2 {
                font-size: 1rem;
            }
            
            .brand-logo {
                font-size: 2.5rem;
            }
            
            .role-selection {
                gap: 20px;
            }
            
            .role-card {
                width: 100%;
                max-width: 200px;
                padding: 18px 15px;
            }
            
            .role-icon {
                font-size: 2.2rem;
            }
            
            .logo-text h1 {
                font-size: 1.3rem;
            }
            
            .logo-text p {
                display: none;
            }
            
            .menu-bar {
                padding: 10px 20px;
            }
        }

        @media (max-width: 480px) {
            .header-section {
                padding: 15px 0;
                margin-bottom: 30px;
            }
            
            .system-brand {
                flex-direction: column;
                gap: 10px;
            }
            
            .system-name h1 {
                font-size: 1.6rem;
            }
            
            .role-selection {
                gap: 15px;
            }
            
            .role-card {
                padding: 15px 12px;
            }
            
            footer {
                margin-top: 30px;
            }
        }
    </style>
</head>
<body>
    <!-- Menu Bar -->
    <div class="menu-bar">
        <div class="logo-section">
            <div class="system-logo">
                <i class="fas fa-hands-helping"></i>
            </div>
            <div class="logo-text">
                <h1>CARELINK</h1>
                <p>Centralized Profiling System</p>
            </div>
        </div>
        <div class="nav-links">
            <a href="#" id="aboutLink">About</a>
            <a href="pages/signup.php">Sign Up</a>
        </div>
    </div>

    <!-- Background Image -->
    <div class="background-image"></div>
    
    <div class="container">
        <!-- Header Section -->
        <div class="header-section">
            <div class="system-brand">
                <div class="brand-logo">
                    <i class="fas fa-hands-helping"></i>
                </div>
                <div class="system-name">
                    <h1>CARELINK</h1>
                    <p>Centralized Profiling and Record Authentication System</p>
                </div>
            </div>
            <div class="municipality-info">
                <h2>For Senior Citizens and Persons with Disabilities (PWD)</h2>
            </div>
        </div>

        <!-- Role Selection Section -->
        <div class="role-section">
            <div class="role-selection">
                <a href="pages/Barangay_Staff_LogInPage.php" class="role-card pulse" id="staffCard">
                    <div class="role-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <h4>BARANGAY STAFF</h4>
                    <p>Register beneficiaries, capture facial data, and manage local records.</p>
                </a>
                
                <a href="pages/Department_Admin_LogIn_Page.php" class="role-card pulse" id="adminCard">
                    <div class="role-icon">
                        <i class="fas fa-user-cog"></i>
                    </div>
                    <h4>DEPARTMENT ADMIN</h4>
                    <p>Oversee system operations, generate reports, and monitor authentication activities.</p>
                </a>
            </div>
        </div>
        
        <footer>
            <p>&copy; 2025 CARELINK - Centralized Profiling System. All Rights Reserved.</p>
        </footer>
    </div>


    
    <!-- About Modal -->
    <div class="about-modal" id="aboutModal">
        <div class="about-content">
            <div class="about-header">
                <h3>About CARELINK</h3>
                <button class="close-btn">&times;
                </button>
            </div>
            <div class="about-body">
                <p>CARELINK is a Centralized Profiling and Record Authentication System designed specifically for Senior Citizens and Persons with Disabilities (PWD).</p>
                <p>CARELINK ensures secure and accurate identity verification while maintaining data privacy.</p>
                <p>The system provides efficient access to government services for our senior citizens and PWD community members.</p>
                <div class="team-section">
                    <h4>Our Team</h4>
                    <div class="team-members-container">
                        <div class="team-member">
                            <i class="fas fa-user-circle fa-3x"></i>
                            <p>Developer</p>
                        </div>
                        <div class="team-member">
                            <i class="fas fa-user-tie fa-3x"></i>
                            <p>Front End</p>
                        </div>
                        <div class="team-member">
                            <i class="fas fa-user-cog fa-3x"></i>
                            <p>Back End</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // DOM Elements
        const aboutLink = document.getElementById('aboutLink');
        const aboutModal = document.getElementById('aboutModal');
        const closeButtons = document.querySelectorAll('.close-btn');

        // Event Listeners
        aboutLink.addEventListener('click', (e) => {
            e.preventDefault();
            aboutModal.style.display = 'flex';
        });

        closeButtons.forEach(button => {
            button.addEventListener('click', () => {
                aboutModal.style.display = 'none';
            });
        });

        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target === aboutModal) {
                aboutModal.style.display = 'none';
            }
        });

        // Add hover effect to role cards
        const roleCards = document.querySelectorAll('.role-card');
        roleCards.forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.classList.remove('pulse');
            });
            
            card.addEventListener('mouseleave', () => {
                setTimeout(() => {
                    card.classList.add('pulse');
                }, 1000);
            });
        });
    </script>
    <script src="assets/js/dynamic-loader.js"></script>
</body>
</html>