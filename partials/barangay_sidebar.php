<?php
$current_page = basename($_SERVER['PHP_SELF']);
require_once __DIR__ . '/../includes/system_branding.php';
$seniorRecordsOpen = in_array($current_page, ['barangay_records.php', 'digital_ids.php', 'digital_id.php', 'senior_classifications.php', 'deceased_records.php', 'barangay_archive.php'], true);
$applicationsOpen = $current_page === 'submit_application.php';
?>
<script>
(() => {
    try {
        const collapsed = matchMedia('(min-width: 1025px)').matches && localStorage.getItem('seniorlinkSidebarCollapsed') === 'true';
        document.documentElement.classList.toggle('sidebar-pref-collapsed', collapsed);
        document.documentElement.classList.add('sidebar-preparing');
    } catch (error) { document.documentElement.classList.add('sidebar-preparing'); }
})();
</script>
<link rel="stylesheet" href="../assets/css/system-sidebar.css?v=22">
<div class="sidebar system-sidebar">
    <div class="mobile-nav-bar">
        <button class="mobile-nav-toggle" type="button" aria-expanded="false" aria-controls="mobileBarangayNavigation"><i class="fas fa-bars" aria-hidden="true"></i><span>Menu</span></button>
        <span class="mobile-nav-current" aria-live="polite">Navigation</span>
    </div>
    <div class="sidebar-header">
        <div class="logo">
            <img src="<?php echo htmlspecialchars(systemLogoUrl($conn), ENT_QUOTES, 'UTF-8'); ?>" alt="SENIORLINK system logo" class="logo-image" width="42" height="42" decoding="async">
            <h1 class="logo-text"><span style="color:#00B050">SENIOR</span><span>LINK</span></h1>
        </div>
    </div>
    <button class="sidebar-toggle-control" type="button" aria-expanded="true" aria-label="Collapse sidebar" title="Collapse sidebar">
        <i class="fas fa-angles-left" aria-hidden="true"></i><span class="toggle-label">Collapse sidebar</span>
    </button>
    <div class="sidebar-menu" id="mobileBarangayNavigation">
        <div class="mobile-nav-heading"><div><strong>Main Navigation</strong><span>Barangay Staff</span></div><button class="mobile-nav-close" type="button" aria-label="Close navigation"><i class="fas fa-xmark" aria-hidden="true"></i></button></div>
        <ul class="sidebar-root-menu">
            <li class="<?php echo $current_page === 'barangay_dash.php' ? 'active' : ''; ?>"><a href="barangay_dash.php" data-tooltip="Dashboard" aria-label="Dashboard"><i class="fas fa-tachometer-alt" aria-hidden="true"></i><span class="link-text">Dashboard</span></a></li>
            <li class="sidebar-nav-group<?php echo $seniorRecordsOpen ? ' is-open has-active-child' : ''; ?>">
                <button class="sidebar-group-toggle" type="button" aria-expanded="<?php echo $seniorRecordsOpen ? 'true' : 'false'; ?>" aria-controls="barangaySeniorRecordsMenu" data-tooltip="Senior Records"><i class="fas fa-folder-open" aria-hidden="true"></i><span class="link-text">Senior Records</span><i class="fas fa-chevron-down sidebar-group-chevron" aria-hidden="true"></i></button>
                <ul class="sidebar-submenu" id="barangaySeniorRecordsMenu"<?php echo $seniorRecordsOpen ? '' : ' hidden'; ?>>
                    <li class="<?php echo $current_page === 'barangay_records.php' ? 'active' : ''; ?>"><a href="barangay_records.php"><i class="fas fa-database" aria-hidden="true"></i><span class="link-text">Records</span></a></li>
                    <li class="<?php echo in_array($current_page, ['digital_ids.php', 'digital_id.php'], true) ? 'active' : ''; ?>"><a href="digital_ids.php"><i class="fas fa-id-card" aria-hidden="true"></i><span class="link-text">Digital IDs</span></a></li>
                    <li class="<?php echo $current_page === 'senior_classifications.php' ? 'active' : ''; ?>"><a href="senior_classifications.php"><i class="fas fa-users" aria-hidden="true"></i><span class="link-text">Classifications</span></a></li>
                    <li class="<?php echo $current_page === 'deceased_records.php' ? 'active' : ''; ?>"><a href="deceased_records.php"><i class="fas fa-ribbon" aria-hidden="true"></i><span class="link-text">Deceased Records</span></a></li>
                    <li class="<?php echo $current_page === 'barangay_archive.php' ? 'active' : ''; ?>"><a href="barangay_archive.php"><i class="fas fa-archive" aria-hidden="true"></i><span class="link-text">Archive</span></a></li>
                </ul>
            </li>
            <li class="sidebar-nav-group<?php echo $applicationsOpen ? ' is-open has-active-child' : ''; ?>">
                <button class="sidebar-group-toggle" type="button" aria-expanded="<?php echo $applicationsOpen ? 'true' : 'false'; ?>" aria-controls="barangayApplicationsMenu" data-tooltip="Applications"><i class="fas fa-file-circle-check" aria-hidden="true"></i><span class="link-text">Applications</span><i class="fas fa-chevron-down sidebar-group-chevron" aria-hidden="true"></i></button>
                <ul class="sidebar-submenu" id="barangayApplicationsMenu"<?php echo $applicationsOpen ? '' : ' hidden'; ?>>
                    <li class="<?php echo $current_page === 'submit_application.php' ? 'active' : ''; ?>"><a href="submit_application.php"><i class="fas fa-clipboard-list" aria-hidden="true"></i><span class="link-text">Queue</span></a></li>
                </ul>
            </li>
            <li class="<?php echo $current_page === 'field_operations.php' ? 'active' : ''; ?>"><a href="field_operations.php" data-tooltip="Home Visits" aria-label="Home Visits"><i class="fas fa-house-medical" aria-hidden="true"></i><span class="link-text">Home Visits</span></a></li>
            <li class="<?php echo $current_page === 'settings.php' ? 'active' : ''; ?>"><a href="settings.php" data-tooltip="Settings" aria-label="Settings"><i class="fas fa-gear" aria-hidden="true"></i><span class="link-text">Settings</span></a></li>
            <li class="logout-item"><a href="../index.php?logout=true" data-logout-confirm data-tooltip="Logout" aria-label="Logout"><i class="fas fa-sign-out-alt" aria-hidden="true"></i><span class="link-text">Logout</span></a></li>
        </ul>
    </div>
</div>
<script src="../assets/js/mobile-navigation.js?v=4"></script>
<script src="../assets/js/sidebar-toggle.js?v=10"></script>
<script src="../assets/js/sidebar-groups.js?v=2"></script>
<script src="../assets/js/session-timeout.js?v=6"></script>
<script src="../assets/js/form-language.js?v=4" defer></script>
<?php include_once __DIR__ . '/legal_quick_access.php'; ?>
