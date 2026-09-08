<?php
$current_page = basename($_SERVER['PHP_SELF']);
$barangayName = $barangayName ?? ($_SESSION['barangay'] ?? '');
$barangayLogoLabel = $barangayName !== '' ? 'Barangay ' . $barangayName . ' Logo' : 'SENIORLINK Logo';
?>
<div class="sidebar system-sidebar">
    <div class="logo">
        <div class="logo-image">
            <img src="../images/LOGO.jpg" alt="<?php echo htmlspecialchars($barangayLogoLabel, ENT_QUOTES, 'UTF-8'); ?>" class="logo-image">
        </div>
        <h1 class="logo-text"><span style="color: #00B050;">SENIOR</span><span>LINK</span></h1>
    </div>
    <button class="sidebar-toggle-control" type="button" aria-expanded="true" aria-label="Collapse sidebar" title="Collapse sidebar">
        <i class="fas fa-angles-left" aria-hidden="true"></i>
        <span class="toggle-label">Collapse sidebar</span>
    </button>
    <ul class="nav-links">
        <li class="<?php echo ($current_page == 'barangay_dash.php') ? 'active' : ''; ?>"><a href="barangay_dash.php" data-tooltip="Dashboard" aria-label="Dashboard"><i class="fas fa-tachometer-alt"></i> <span class="link-text">Dashboard</span></a></li>
        <li class="<?php echo ($current_page == 'new_application.php') ? 'active' : ''; ?>"><a href="new_application.php" data-tooltip="Application" aria-label="Application"><i class="fas fa-user-plus"></i> <span class="link-text">Application</span></a></li>
        <li class="<?php echo ($current_page == 'submit_application.php') ? 'active' : ''; ?>"><a href="submit_application.php" data-tooltip="Queue" aria-label="Queue"><i class="fas fa-clipboard-list"></i> <span class="link-text">Queue</span></a></li>
        <li class="<?php echo ($current_page == 'barangay_records.php') ? 'active' : ''; ?>"><a href="barangay_records.php" data-tooltip="Records" aria-label="Records"><i class="fas fa-database"></i> <span class="link-text">Records</span></a></li>
        <li class="<?php echo ($current_page == 'digital_ids.php' || $current_page == 'digital_id.php') ? 'active' : ''; ?>"><a href="digital_ids.php" data-tooltip="Digital IDs" aria-label="Digital IDs"><i class="fas fa-id-card"></i> <span class="link-text">Digital IDs</span></a></li>
        <li class="<?php echo ($current_page == 'field_operations.php') ? 'active' : ''; ?>"><a href="field_operations.php" data-tooltip="Home Visits" aria-label="Home Visits"><i class="fas fa-house-medical"></i> <span class="link-text">Home Visits</span></a></li>
        <li class="<?php echo ($current_page == 'barangay_archive.php') ? 'active' : ''; ?>"><a href="barangay_archive.php" data-tooltip="Archive" aria-label="Archive"><i class="fas fa-archive"></i> <span class="link-text">Archive</span></a></li>
        <li class="<?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>"><a href="settings.php" data-tooltip="Settings" aria-label="Settings"><i class="fas fa-cog"></i> <span class="link-text">Settings</span></a></li>
        <li class="logout-item"><a href="../index.php?logout=true" data-tooltip="Logout" aria-label="Logout"><i class="fas fa-sign-out-alt"></i> <span class="link-text">Logout</span></a></li>
    </ul>
</div>
<?php include_once __DIR__ . '/legal_quick_access.php'; ?>
