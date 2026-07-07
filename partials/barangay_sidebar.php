<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">

    <div class="logo">
        <div class="logo-image">
            <img src="../images/LOGO.jpg" alt="Barangay <?php echo $barangayName; ?> Logo" class="logo-image" onerror="this.style.display='none'; document.getElementById('fallback-logo').style.display='flex';">
        </div>
        <h1 class="logo-text"><span style="color: #00B050;">SENIOR</span>LINK</h1>
    </div>
    <ul class="nav-links">
        <li class="<?php echo ($current_page == 'barangay_dash.php') ? 'active' : ''; ?>"><a href="barangay_dash.php" data-tooltip="Dashboard" aria-label="Dashboard"><i class="fas fa-tachometer-alt"></i> <span class="link-text">Dashboard</span></a></li>
        <li class="<?php echo ($current_page == 'new_application.php') ? 'active' : ''; ?>"><a href="new_application.php" data-tooltip="Application" aria-label="Application"><i class="fas fa-user-plus"></i> <span class="link-text">Application</span></a></li>
        <li class="<?php echo ($current_page == 'submit_application.php') ? 'active' : ''; ?>"><a href="submit_application.php" data-tooltip="Queue" aria-label="Queue"><i class="fas fa-clipboard-list"></i> <span class="link-text">Queue</span></a></li>
        <li class="<?php echo ($current_page == 'proxy_registration.php') ? 'active' : ''; ?>"><a href="proxy_registration.php" data-tooltip="Proxy QR" aria-label="Proxy QR"><i class="fas fa-qrcode"></i> <span class="link-text">Proxy QR</span></a></li>
        <li class="<?php echo ($current_page == 'barangay_records.php') ? 'active' : ''; ?>"><a href="barangay_records.php" data-tooltip="Records" aria-label="Records"><i class="fas fa-database"></i> <span class="link-text">Records</span></a></li>
        <li class="<?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>"><a href="settings.php" data-tooltip="Settings" aria-label="Settings"><i class="fas fa-cog"></i> <span class="link-text">Settings</span></a></li>
        <li class="logout-item"><a href="../index.php?logout=true" data-tooltip="Logout" aria-label="Logout"><i class="fas fa-sign-out-alt"></i> <span class="link-text">Logout</span></a></li>
    </ul>
</div>
