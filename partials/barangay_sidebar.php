<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">

    <div class="logo">
        <div class="logo-image">
            <img src="../images/LOGO.jpg" alt="Barangay <?php echo $barangayName; ?> Logo" class="logo-image" onerror="this.style.display='none'; document.getElementById('fallback-logo').style.display='flex';">
        </div>
        <h1 class="logo-text"><span style="color: #00B050;">CARE</span>LINK</h1>
    </div>
    <ul class="nav-links">
        <li class="<?php echo ($current_page == 'barangay_dash.php') ? 'active' : ''; ?>"><a href="barangay_dash.php"><i class="fas fa-tachometer-alt"></i> <span class="link-text">Dashboard</span></a></li>
        <li class="<?php echo ($current_page == 'submit_application.php' || $current_page == 'new_application.php') ? 'active' : ''; ?>"><a href="submit_application.php"><i class="fas fa-user-plus"></i> <span class="link-text">Application</span></a></li>
        <li class="<?php echo ($current_page == 'proxy_registration.php') ? 'active' : ''; ?>"><a href="proxy_registration.php"><i class="fas fa-qrcode"></i> <span class="link-text">Proxy QR</span></a></li>
        <li class="<?php echo ($current_page == 'barangay_records.php') ? 'active' : ''; ?>"><a href="barangay_records.php"><i class="fas fa-database"></i> <span class="link-text">Records</span></a></li>
        <li class="<?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>"><a href="settings.php"><i class="fas fa-cog"></i> <span class="link-text">Settings</span></a></li>
        <li class="logout-item"><a href="../index.php?logout=true"><i class="fas fa-sign-out-alt"></i> <span class="link-text">Logout</span></a></li>
    </ul>
</div>
