<?php
session_start();
require_once '../includes/crypto.php';

$token = isset($_GET['token']) ? $_GET['token'] : '';

// Check if user is logged in
$is_authenticated = isset($_SESSION['role']) && ($_SESSION['role'] === 'barangay_staff' || $_SESSION['role'] === 'department_admin');

if (!$is_authenticated) {
    // Privacy Guard Triggered: Unauthorized device/user
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Privacy Guard — Unauthorized Access Blocked</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body {
                background: #111;
                color: #eee;
                font-family: 'Segoe UI', Tahoma, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
                padding: 20px;
                text-align: center;
            }
            .guard-box {
                background: #1a1a1a;
                border: 2px solid #e74c3c;
                border-radius: 12px;
                padding: 40px;
                max-width: 500px;
                box-shadow: 0 10px 30px rgba(231, 76, 60, 0.2);
            }
            .shield-icon {
                font-size: 4rem;
                color: #e74c3c;
                margin-bottom: 20px;
                animation: pulse 2s infinite;
            }
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); }
                100% { transform: scale(1); }
            }
            h1 {
                font-size: 1.6rem;
                margin-bottom: 15px;
                color: #ff6b6b;
            }
            p {
                font-size: 0.95rem;
                color: #ccc;
                line-height: 1.6;
                margin-bottom: 25px;
            }
            .law-ref {
                font-size: 0.8rem;
                color: #777;
                border-top: 1px solid #333;
                padding-top: 15px;
            }
        </style>
    </head>
    <body>
        <div class="guard-box">
            <i class="fas fa-user-shield shield-icon"></i>
            <h1>Privacy Guard Active</h1>
            <p>
                <strong>ACCESS DENIED:</strong> This QR code contains sensitive personal records protected by the Philippine Data Privacy Act of 2012 (RA 10173). 
                Scanning with public, unauthorized devices is blocked. The records can only be decrypted and viewed inside the secure, authenticated Carelink terminal environment.
            </p>
            <div class="law-ref">
                Office of Senior Citizen Affairs (OSCA) Affairs Section Office — Pasig City Compliance Engine
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// User is authenticated, redirect to new_application.php with the token
header("Location: new_application.php?token=" . urlencode($token));
exit();
?>
