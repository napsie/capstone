<?php
$current_page = basename($_SERVER['PHP_SELF']);
$barangayName = $barangayName ?? ($_SESSION['barangay'] ?? '');
$barangayLogoLabel = $barangayName !== '' ? 'Barangay ' . $barangayName . ' Logo' : 'SENIORLINK Logo';
require_once __DIR__ . '/../includes/system_branding.php';
?>
<script>
(() => {
    try {
        const collapsed = matchMedia('(min-width: 769px)').matches && localStorage.getItem('seniorlinkSidebarCollapsed') === 'true';
        document.documentElement.classList.toggle('sidebar-pref-collapsed', collapsed);
        document.documentElement.classList.add('sidebar-preparing');
    } catch (error) { document.documentElement.classList.add('sidebar-preparing'); }
})();
</script>
<link rel="stylesheet" href="../assets/css/barangay-mobile-navigation.css?v=6">
<link rel="stylesheet" href="../assets/css/system-sidebar.css?v=16">
<div class="sidebar system-sidebar">
    <div class="mobile-nav-bar">
        <button class="mobile-nav-toggle" type="button" aria-expanded="false" aria-controls="mobileBarangayNavigation"><i class="fas fa-bars" aria-hidden="true"></i><span>Menu</span></button>
        <span class="mobile-nav-current" aria-live="polite">Navigation</span>
    </div>
    <div class="logo">
        <div class="logo-image">
            <img src="<?php echo htmlspecialchars(systemLogoUrl($conn)); ?>" alt="<?php echo htmlspecialchars($barangayLogoLabel, ENT_QUOTES, 'UTF-8'); ?>" class="logo-image" width="42" height="42" decoding="async">
        </div>
        <h1 class="logo-text"><span style="color: #00B050;">SENIOR</span><span>LINK</span></h1>
    </div>
    <button class="sidebar-toggle-control" type="button" aria-expanded="true" aria-label="Collapse sidebar" title="Collapse sidebar">
        <i class="fas fa-angles-left" aria-hidden="true"></i>
        <span class="toggle-label">Collapse sidebar</span>
    </button>
    <ul class="nav-links" id="mobileBarangayNavigation">
        <li class="mobile-nav-heading"><div><strong>Main Navigation</strong><span>Barangay Staff</span></div><button class="mobile-nav-close" type="button" aria-label="Close navigation"><i class="fas fa-xmark" aria-hidden="true"></i></button></li>
        <li class="<?php echo ($current_page == 'barangay_dash.php') ? 'active' : ''; ?>"><a href="barangay_dash.php" data-tooltip="Dashboard" aria-label="Dashboard"><i class="fas fa-tachometer-alt"></i> <span class="link-text">Dashboard</span></a></li>
        <li class="<?php echo ($current_page == 'submit_application.php') ? 'active' : ''; ?>"><a href="submit_application.php" data-tooltip="Queue" aria-label="Queue"><i class="fas fa-clipboard-list"></i> <span class="link-text">Queue</span></a></li>
        <li class="<?php echo ($current_page == 'barangay_records.php') ? 'active' : ''; ?>"><a href="barangay_records.php" data-tooltip="Records" aria-label="Records"><i class="fas fa-database"></i> <span class="link-text">Records</span></a></li>
        <li class="<?php echo ($current_page == 'deceased_records.php') ? 'active' : ''; ?>"><a href="deceased_records.php" data-tooltip="Deceased Records" aria-label="Deceased Records"><i class="fas fa-ribbon"></i> <span class="link-text">Deceased Records</span></a></li>
        <li class="<?php echo ($current_page == 'digital_ids.php' || $current_page == 'digital_id.php') ? 'active' : ''; ?>"><a href="digital_ids.php" data-tooltip="Digital IDs" aria-label="Digital IDs"><i class="fas fa-id-card"></i> <span class="link-text">Digital IDs</span></a></li>
        <li class="<?php echo ($current_page == 'field_operations.php') ? 'active' : ''; ?>"><a href="field_operations.php" data-tooltip="Home Visits" aria-label="Home Visits"><i class="fas fa-house-medical"></i> <span class="link-text">Home Visits</span></a></li>
        <li class="<?php echo ($current_page == 'barangay_archive.php') ? 'active' : ''; ?>"><a href="barangay_archive.php" data-tooltip="Archive" aria-label="Archive"><i class="fas fa-archive"></i> <span class="link-text">Archive</span></a></li>
        <li class="<?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>"><a href="settings.php" data-tooltip="Settings" aria-label="Settings"><i class="fas fa-cog"></i> <span class="link-text">Settings</span></a></li>
        <li class="logout-item"><a href="../index.php?logout=true" data-logout-confirm data-tooltip="Logout" aria-label="Logout"><i class="fas fa-sign-out-alt"></i> <span class="link-text">Logout</span></a></li>
    </ul>
</div>
<script src="../assets/js/mobile-navigation.js?v=2"></script>
<script src="../assets/js/sidebar-toggle.js?v=7"></script>
<script src="../assets/js/session-timeout.js?v=6"></script>
<script src="../assets/js/form-language.js?v=4" defer></script>
<?php include_once __DIR__ . '/legal_quick_access.php'; ?>
