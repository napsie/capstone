<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">

    <div class="sidebar-header">
        <div class="logo">
            <img src="../images/LOGO.jpg" alt="Logo" class="logo-image">
            <h1 class="logo-text"><span>CARE</span><span style="color: #00B050;">LINK</span></h1>
        </div>
    </div>
    <div class="sidebar-menu">
        <ul>
            <li class="<?php echo ($current_page == 'department_dashboard.php') ? 'active' : ''; ?>"><a href="department_dashboard.php"><i class="fas fa-tachometer-alt"></i> <span class="link-text">Dashboard</span></a></li>
            <li class="<?php echo ($current_page == 'verify_document.php') ? 'active' : ''; ?>"><a href="verify_document.php"><i class="fas fa-check-circle"></i> <span class="link-text">Verify Documents</span></a></li>
            <li class="<?php echo ($current_page == 'department_records.php') ? 'active' : ''; ?>"><a href="department_records.php"><i class="fas fa-database"></i> <span class="link-text">Records</span></a></li>
            <li class="<?php echo ($current_page == 'user_management.php' || $current_page == 'edit_user.php' || $current_page == 'signup.php') ? 'active' : ''; ?>"><a href="user_management.php"><i class="fas fa-user-cog"></i> <span class="link-text">User Management</span></a></li>
            <li class="<?php echo ($current_page == 'system_settings.php') ? 'active' : ''; ?>"><a href="system_settings.php"><i class="fas fa-cog"></i> <span class="link-text">System Settings</span></a></li>
            <li class="logout-item"><a href="../index.php?logout=true"><i class="fas fa-sign-out-alt"></i> <span class="link-text">Logout</span></a></li>
        </ul>
    </div>
</div>
