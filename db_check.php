<?php
// TEMPORARY DIAGNOSTIC + PASSWORD RESET — DELETE THIS FILE AFTER USE
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "capstone1";

header('Content-Type: text/html; charset=utf-8');
echo "<pre style='font-family:monospace; font-size:14px; background:#1e1e2e; color:#cdd6f4; padding:20px;'>";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<span style='color:#a6e3a1;'>✓ DATABASE CONNECTION: OK</span>\n\n";

    // Check users table
    echo "<span style='color:#89b4fa;'>═══ USERS TABLE ═══</span>\n";
    $stmt = $conn->query("SELECT id, username, password, role, barangay, email FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $u) {
        $hashOk = (strlen($u['password']) >= 60) ? "✓ hash OK" : "✗ hash BROKEN";
        echo "ID: {$u['id']} | User: <b>{$u['username']}</b> | Role: {$u['role']} | Barangay: {$u['barangay']} | Hash: {$hashOk}\n";
    }

    // Check all tables exist
    echo "\n<span style='color:#89b4fa;'>═══ TABLE CHECK ═══</span>\n";
    $tables = ['users', 'applications', 'application_history', 'login_history', 'remember_tokens', 'settings', 'system_settings', 'notifications'];
    foreach ($tables as $t) {
        try {
            $conn->query("SELECT 1 FROM $t LIMIT 1");
            echo "<span style='color:#a6e3a1;'>✓ $t</span>\n";
        } catch (PDOException $e) {
            echo "<span style='color:#f38ba8;'>✗ $t — MISSING or ERROR: {$e->getMessage()}</span>\n";
        }
    }

    // Handle password reset
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_user_id'])) {
        $newPass = trim($_POST['new_password']);
        $userId = intval($_POST['reset_user_id']);
        if (!empty($newPass) && $userId > 0) {
            $hashed = password_hash($newPass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = :pw WHERE id = :id");
            $stmt->execute(['pw' => $hashed, 'id' => $userId]);
            echo "\n<span style='color:#a6e3a1; font-size:16px;'>✓ PASSWORD RESET SUCCESSFUL for user ID {$userId}!</span>\n";
            echo "<span style='color:#f9e2af;'>New password: {$newPass}</span>\n";
        }
    }

    // Password reset forms
    echo "\n<span style='color:#89b4fa;'>═══ PASSWORD RESET ═══</span>\n";
    echo "</pre>";
    
    foreach ($users as $u) {
        echo "<div style='background:#313244; padding:15px; margin:10px 20px; border-radius:8px; font-family:monospace;'>";
        echo "<form method='POST'>";
        echo "<input type='hidden' name='reset_user_id' value='{$u['id']}'>";
        echo "<span style='color:#cdd6f4;'>Reset password for <b style='color:#89b4fa;'>{$u['username']}</b> ({$u['role']}): </span>";
        echo "<input type='text' name='new_password' placeholder='Enter new password' style='padding:8px; border-radius:5px; border:1px solid #585b70; background:#1e1e2e; color:#cdd6f4; width:200px;'>";
        echo " <button type='submit' style='padding:8px 16px; background:#a6e3a1; color:#1e1e2e; border:none; border-radius:5px; cursor:pointer; font-weight:bold;'>Reset</button>";
        echo "</form></div>";
    }

    // Test login page loading
    echo "<pre style='font-family:monospace; font-size:14px; background:#1e1e2e; color:#cdd6f4; padding:20px; margin-top:10px;'>";
    echo "\n<span style='color:#89b4fa;'>═══ LOGIN PAGE CHECK ═══</span>\n";
    
    // Check if login files exist
    $loginFiles = [
        'pages/barangay_staff_loginpage.php',
        'pages/department_admin_login_page.php',
    ];
    foreach ($loginFiles as $f) {
        $fullPath = __DIR__ . '/' . $f;
        if (file_exists($fullPath)) {
            echo "<span style='color:#a6e3a1;'>✓ {$f} exists</span>\n";
        } else {
            echo "<span style='color:#f38ba8;'>✗ {$f} NOT FOUND</span>\n";
        }
    }
    
    echo "\n<span style='color:#f9e2af;'>⚠ DELETE this file (db_check.php) after you're done!</span>\n";

} catch (PDOException $e) {
    echo "<span style='color:#f38ba8;'>ERROR: " . $e->getMessage() . "</span>\n";
}
echo "</pre>";
?>
