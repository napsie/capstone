<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar system-sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <img src="../images/LOGO.jpg" alt="Logo" class="logo-image">
            <h1 class="logo-text"><span style="color: #00B050;">SENIOR</span><span>LINK</span></h1>
        </div>
    </div>
    <button class="sidebar-toggle-control" type="button" aria-expanded="true" aria-label="Collapse sidebar" title="Collapse sidebar">
        <i class="fas fa-angles-left" aria-hidden="true"></i>
        <span class="toggle-label">Collapse sidebar</span>
    </button>
    <div class="sidebar-menu">
        <ul>
            <li class="<?php echo ($current_page == 'department_dashboard.php') ? 'active' : ''; ?>"><a href="department_dashboard.php" data-tooltip="Dashboard" aria-label="Dashboard"><i class="fas fa-tachometer-alt"></i> <span class="link-text">Dashboard</span></a></li>
            <li class="<?php echo ($current_page == 'verify_document.php') ? 'active' : ''; ?>"><a href="verify_document.php" data-tooltip="Verify Documents" aria-label="Verify Documents"><i class="fas fa-check-circle"></i> <span class="link-text">Verify Documents</span></a></li>
            <li class="<?php echo ($current_page == 'department_records.php') ? 'active' : ''; ?>"><a href="department_records.php" data-tooltip="Records" aria-label="Records"><i class="fas fa-database"></i> <span class="link-text">Records</span></a></li>
            <li class="<?php echo ($current_page == 'import_records.php') ? 'active' : ''; ?>"><a href="import_records.php" data-tooltip="Import Records" aria-label="Import Records"><i class="fas fa-file-import"></i> <span class="link-text">Import Records</span></a></li>
            <li class="<?php echo ($current_page == 'digital_ids.php' || $current_page == 'digital_id.php') ? 'active' : ''; ?>"><a href="digital_ids.php" data-tooltip="Digital IDs" aria-label="Digital IDs"><i class="fas fa-id-card"></i> <span class="link-text">Digital IDs</span></a></li>
            <li class="<?php echo ($current_page == 'field_operations.php') ? 'active' : ''; ?>"><a href="field_operations.php" data-tooltip="Home Visits" aria-label="Home Visits"><i class="fas fa-house-medical"></i> <span class="link-text">Home Visits</span></a></li>
            <li class="<?php echo ($current_page == 'department_archive.php') ? 'active' : ''; ?>"><a href="department_archive.php" data-tooltip="Archive" aria-label="Archive"><i class="fas fa-archive"></i> <span class="link-text">Archive</span></a></li>
            <li class="<?php echo ($current_page == 'user_management.php' || $current_page == 'edit_user.php' || $current_page == 'signup.php') ? 'active' : ''; ?>"><a href="user_management.php" data-tooltip="User Management" aria-label="User Management"><i class="fas fa-user-cog"></i> <span class="link-text">User Management</span></a></li>
            <li class="<?php echo ($current_page == 'system_settings.php') ? 'active' : ''; ?>"><a href="system_settings.php" data-tooltip="System Settings" aria-label="System Settings"><i class="fas fa-cog"></i> <span class="link-text">System Settings</span></a></li>
            <li class="logout-item"><a href="../index.php?logout=true" data-tooltip="Logout" aria-label="Logout"><i class="fas fa-sign-out-alt"></i> <span class="link-text">Logout</span></a></li>
        </ul>
    </div>
</div>
<?php include_once __DIR__ . '/legal_quick_access.php'; ?>
